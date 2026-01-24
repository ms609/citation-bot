<?php

declare(strict_types=1);

/*
 * Template means wiki {{}} templates
 * add_if_new()/tidy() do most work
 */

// @codeCoverageIgnoreStart
require_once __DIR__ . '/Parameter.php';
require_once __DIR__ . '/TextTools.php';
require_once __DIR__ . '/miscTools.php';
require_once __DIR__ . '/URLtools.php';
require_once __DIR__ . '/doiTools.php';
require_once __DIR__ . '/user_messages.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/NameTools.php';
require_once __DIR__ . '/api/APIsici.php';
// @codeCoverageIgnoreEnd

final class Template
{
    public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_TEMPLATE %s # # #';
    public const REGEXP = ['~(?<!\{)\{\{\}\}(?!\})~su', '~\{\{[^\{\}\|]+\}\}~su', '~\{\{[^\{\}]+\}\}~su', '~\{\{(?>[^\{]|\{[^\{])+?\}\}~su']; // Please see https://stackoverflow.com/questions/1722453/need-to-prevent-php-regex-segfault for discussion of atomic regex
    public const TREAT_IDENTICAL_SEPARATELY = false; // This is safe because templates are the last thing we do AND we do not directly edit $all_templates that are sub-templates - we might remove them, but do not change their content directly
    /** @var array<Template> */
    public static array $all_templates = []; // List of all the Template() on the Page() including this one.  Can only be set by the page class after all templates are made
    public static DateStyle $date_style = DateStyle::DATES_WHATEVER;
    public static VancStyle $name_list_style = VancStyle::NAME_LIST_STYLE_DEFAULT;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $rawtext; // Must start out as unset
    public string $last_searched_doi = '';
    private string $example_param = '';
    private string $name = '';
    /** @var array<Parameter> */
    private array $param = [];
    /** @var array<string> */
    private array $initial_param = [];
    /** @var array<string> */
    private array $initial_author_params = [];
    private string $initial_name = '';
    private bool $doi_valid = false;
    private bool $had_initial_eds = false;
    private bool $had_initial_publisher = false;
    private bool $mod_dashes = false;
    private bool $mod_names = false;
    private bool $mod_ref = false;
    private bool $mod_na = false;
    private bool $no_initial_doi = false;
    private bool $held_work_done = false;
    /** @var array<array<string>> */
    private array $used_by_api = [
        'adsabs' => [],
        'arxiv' => [],
        'crossref' => [],
        'dx' => [],
        'entrez' => [],
        'jstor' => [],
        'zotero' => [],
    ];
    /** @var array<Template> */
    private array $this_array = []; // Unset after using to avoid pointer loop that makes garbage collection harder

    public function __construct() {
        // Done in parse_text() and in variable initialization
    }

    public function parse_text(string $text): void {
        set_time_limit(120);
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (isset($this->rawtext)) {
            report_error("Template already initialized"); // @codeCoverageIgnore
        }
        $this->rawtext = $text;
        $pipe_pos = mb_strpos($text, '|');
        if ($pipe_pos) {
            $this->name = mb_substr($text, 2, $pipe_pos - 2); # Remove {{ and }}
            $this->split_params(mb_substr($text, $pipe_pos + 1, -2));
        } else {
            $this->name = mb_substr($text, 2, -2);
        }
        $this->initial_name = $this->name;
        // Clean up outdated redirects
        if (!preg_match("~^(\s*)[\s\S]*?(\s*)$~", $this->name, $spacing)) {
            bot_debug_log("RegEx failure in Template name: " . $this->name); // @codeCoverageIgnoreStart
            $trim_name = $this->name;
            $spacing = [];
            $spacing[1] = '';
            $spacing[2] = ''; // @codeCoverageIgnoreEnd
        } else {
            $trim_name = mb_trim($this->name);
        }
        if (mb_strpos($trim_name, "_") !== false) {
            $tmp_name = str_replace("_", " ", $trim_name);
            if (in_array(mb_strtolower($tmp_name), [...TEMPLATES_WE_PROCESS, ...TEMPLATES_WE_SLIGHTLY_PROCESS, ...TEMPLATES_WE_BARELY_PROCESS, ...TEMPLATES_WE_RENAME], true)) {
                $this->name = $spacing[1] . str_replace("_", " ", $trim_name) . $spacing[2];
                $trim_name = str_replace("_", " ", $trim_name);
            }
        }

        if (in_array(WIKI_BASE, ENGLISH_WIKI)) {
            foreach (TEMPLATE_CONVERSIONS as /** @var array{0: string, 1: string} */ $trial) {
                if ($trim_name === $trial[0]) {
                    $this->name = $spacing[1] . $trial[1] . $spacing[2];
                    break;
                }
            }
        }
        while (mb_strpos($this->name, 'Cite  ') === 0 || mb_strpos($this->name, 'cite  ') === 0) {
            $this->name = mb_substr_replace($this->name, 'ite ', 1, 5);
        }
        $trim_name = mb_trim($this->name); // Update if changed above
        // Cite paper is really cite journal
        if (mb_strtolower($trim_name) === 'cite paper' || mb_strtolower($trim_name) === 'cite document') {
            if ($trim_name === 'Cite paper' || $trim_name === 'Cite document') {
                $cite_caps = $spacing[1] . "Cite ";
            } else {
                $cite_caps = $spacing[1] . "cite ";
            }
            if (!$this->blank_other_than_comments('journal')) {
                $this->name = $cite_caps . 'journal' . $spacing[2];
            } elseif (!$this->blank_other_than_comments('newspaper')) {
                $this->name = $cite_caps . 'news' . $spacing[2];
            } elseif (!$this->blank_other_than_comments('website') && $this->has('url')) {
                $this->name = $cite_caps . 'web' . $spacing[2];
            } elseif (!$this->blank_other_than_comments('magazine')) {
                $this->name = $cite_caps . 'magazine' . $spacing[2];
            } elseif (!$this->blank_other_than_comments(['encyclopedia', 'encyclopaedia'])) {
                $this->name = $cite_caps . 'encyclopedia' . $spacing[2];
            } elseif (mb_strpos($this->get('doi'), '/978-') !== false || mb_strpos($this->get('doi'), '/978019') !== false || mb_strpos($this->get('isbn'), '978-0-19') === 0 || mb_strpos($this->get('isbn'), '978019') === 0) {
                $this->name = $cite_caps . 'book' . $spacing[2];
            } elseif (!$this->blank_other_than_comments('chapter') || !$this->blank_other_than_comments('isbn')) {
                $this->name = $cite_caps . 'book' . $spacing[2];
            } elseif (!$this->blank_other_than_comments(['journal', 'pmid', 'pmc'])) {
                $this->name = $cite_caps . 'journal' . $spacing[2];
            } elseif (!$this->blank_other_than_comments('publisher') && $this->blank(['url', 'citeseerx', 's2cid'])) {
                $this->name = $cite_caps . 'document' . $spacing[2];
            }
        }

        if (mb_substr($this->wikiname(), 0, 5) === 'cite ' || $this->wikiname() === 'citation') {
            if (preg_match('~< */? *ref *>~i', $this->rawtext) && mb_strpos($this->wikiname(), 'cite check') !== 0) {
                report_warning('reference within citation template: most likely unclosed template. ' . "\n" . echoable($this->rawtext) . "\n");
                throw new Exception('page_error');
            }
        }

        // extract initial parameters/values from Parameters in $this->param
        foreach ($this->param as $p) {
            $this->initial_param[$p->param] = $p->val;

            // Save author params for special handling
            if (in_array($p->param, FLATTENED_AUTHOR_PARAMETERS, true) && $p->val) {
                $this->initial_author_params[$p->param] = $p->val;
            }

            // Save editor information for special handling
            if (in_array($p->param, FIRST_EDITOR_ALIASES, true) && $p->val) {
                $this->had_initial_eds = true;
            }
            if ($p->param === 'veditors' && $p->val) {
                $this->had_initial_eds = true;
            }
            if ($p->param === 'others' && $p->val) {
                $this->had_initial_eds = true;
            } // Often tossed in there
        }
        $this->no_initial_doi = $this->blank('doi');

        if (!$this->blank(['publisher', 'location', 'publication-place', 'place'])) {
            $this->had_initial_publisher = true;
        }

        if (isset($this->param[0])) {
            // Use second param as a template if present, in case first pair
            // is last1 = Smith | first1 = J.\n
            if (isset($this->param[1])) {
                $example = $this->param[1]->parsed_text();
            } else {
                $example = $this->param[0]->parsed_text();
            }
            $example = preg_replace('~[^\s=][^=]*[^\s=]~u', 'X', $example); // Collapse strings
            $example = preg_replace('~ +~u', ' ', $example); // Collapse spaces
            // Check if messed up, and do not use bad styles
            if (mb_substr_count($example, '=') !== 1 || mb_substr_count($example, "\n") > 1 || $example === 'X = X') {
                $example = ' X=X ';
            } elseif ($example === 'X=X') {
                $example = 'X=X ';
            }
        } else {
            $example = ' X=X ';
        }
        $this->example_param = $example;

        if (in_array($this->wikiname(), TEMPLATES_WE_HARV, true)) {
            $this->tidy_parameter('ref'); // Remove ref=harv or empty ref=
        }
    }

    /** Re-assemble parsed template into string */
    public function parsed_text(): string {
        if ($this->has(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
            if ($this->has('title') || $this->has('chapter') || ($this->has('journal') && $this->get('volume') . $this->get('issue') !== '' && $this->page() !== '' && $this->year() !== '')) {
                report_action("Converted Bare reference to template: " . echoable(mb_trim(base64_decode($this->get(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))))));
                $this->quietly_forget(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
            } else {
                return base64_decode($this->get(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL')));
            }
        }
        if (mb_stripos(mb_trim($this->name), '#invoke:') === 0) {
            $add_pipe = false;
            $wikiname = $this->wikiname();
            if (
                in_array($wikiname, TEMPLATES_WE_PROCESS, true) ||
                in_array($wikiname, TEMPLATES_WE_SLIGHTLY_PROCESS, true) ||
                in_array($wikiname, TEMPLATES_WE_BARELY_PROCESS, true) ||
                in_array($wikiname, TEMPLATES_WE_RENAME, true) ||
                mb_strpos($wikiname, 'cite ') === 0
            ) {
                $add_pipe = true;
            }
            if ($wikiname === 'citation') {
                $add_pipe = false; // Do not double pipe this one - actually it is "cite"
            }
            $joined = str_replace(["\t", "\n", "\r", " "], '', $this->join_params());
            if (mb_strpos($joined, "||") === 0) {
                $add_pipe = false;
            }
            if ($add_pipe) {
                return '{{' . $this->name . '|' . $this->join_params() . '}}';
            }
        }
        return '{{' . $this->name . $this->join_params() . '}}';
    }

    /** Parts of each param: | [pre] [param] [eq] [value] [post] */
    private function split_params(string $text): void {
        // Replace | characters that are inside template parameter/value pairs
        $PIPE_REGEX = "~(\[\[[^\[\]]*)(?:\|)([^\[\]]*\]\])~u";
        while (preg_match($PIPE_REGEX, $text)) {
            $text = preg_replace_callback(
            $PIPE_REGEX,
            static function (array $matches): string {
                return $matches[1] . PIPE_PLACEHOLDER . $matches[2];
            },
            $text
            );
        }
        $params = explode('|', $text);
        foreach ($params as $i => $text_found) {
            $this->param[$i] = new Parameter();
            $this->param[$i]->parse_text($text_found);
        }
    }

    public function prepare(): void {
        set_time_limit(120);
        if (in_array($this->wikiname(), TEMPLATES_WE_PROCESS, true) || in_array($this->wikiname(), TEMPLATES_WE_SLIGHTLY_PROCESS, true)) {
            // Clean up bad data
            if (in_array(mb_strtolower($this->get('title')), ALWAYS_BAD_TITLES, true)) {
                $this->set('title', '');
            }
            if (($this->get('title') === "Wayback Machine" || $this->get('title') === "Internet Archive Wayback Machine") && !$this->blank(['archive-url', 'archiveurl']) && $this->get('url') !== 'https://archive.org/web/') {
                $this->set('title', '');
            }
            if ($this->get('last1') === 'Published' || $this->get('last1') === 'published') {
                $this->forget('last1');
                if ($this->has('first1')) {
                    $this->rename('first1', 'author1');
                } elseif ($this->has('first')) {
                    $this->rename('first', 'author1');
                }
            } elseif ($this->get('last') === 'Published' || $this->get('last') === 'published') {
                $this->forget('last');
                if ($this->has('first1')) {
                    $this->rename('first1', 'author1');
                } elseif ($this->has('first')) {
                    $this->rename('first', 'author1');
                }
            }
        }
        if ($this->should_be_processed()) {
            // Remove empty duplicate parameters by checking the ALL_ALIASES list
            if (!empty($this->param)) {
                $drop_me_maybe = [];
                foreach (ALL_ALIASES as $alias_list) {
                    if (!$this->blank($alias_list)) {
                        // At least one is set
                        $drop_me_maybe = [...$drop_me_maybe, ...$alias_list];
                    }
                }
                // Do it this way to avoid massive N*M work load (N=size of $param and M=size of $drop_me_maybe) which happens when checking if each one is blank
                foreach ($this->param as $key => $p) {
                    if (@$p->val === '' && in_array(@$p->param, $drop_me_maybe, true)) {
                        unset($this->param[$key]);
                    }
                }
            }
            // clean date parameters
            foreach (DATES_TO_CLEAN as $date) {
                if ($this->has($date)) {
                    $input = $this->get($date);
                    if (mb_stripos($input, 'citation') === false) {
                        $output = clean_dates($input);
                        if ($input !== $output) {
                            $this->set($date, $output);
                        }
                    }
                }
            }
            // delete obviously incorrect author parameters
            if (mb_strtolower ($this->get('last')) === 'archive' || mb_strtolower ($this->get('last1')) === 'archive') {
                if (
                    $this->get('first2') === 'Get author RSS' ||
                    $this->get('first3') === 'Get author RSS' ||
                    $this->get('first4') === 'Get author RSS' ||
                    (
                        (
                            $this->get('first2') === 'Email the' &&
                            $this->get('last2') === 'Author'
                        ) ||
                        $this->get('first1') === 'From our online'
                    )
                ) {
                    foreach (FLATTENED_AUTHOR_PARAMETERS as $author) {
                        $this->forget($author);
                    }
                }
            }
            if (doi_works($this->get('doi')) === null) {
                // this can be slow. Prime cache for better slow step determination
                $this->tidy_parameter('doi'); // Clean it up now
            }
            $this->get_inline_doi_from_title();
            $this->parameter_names_to_lowercase();
            $this->use_unnamed_params();
            $this->get_identifiers_from_url();
            $this->id_to_param();
            $this->correct_param_spelling();
            $this->get_doi_from_text();
            $this->fix_rogue_etal();

            switch ($this->wikiname()) {
                case "cite arxiv":
                    // Forget dates so that DOI can update with publication date, not ARXIV date
                    $this->rename('date', 'CITATION_BOT_PLACEHOLDER_date');
                    $this->rename('year', 'CITATION_BOT_PLACEHOLDER_year');
                    expand_by_doi($this);
                    if ($this->blank('year') && $this->blank('date')) {
                        $this->rename('CITATION_BOT_PLACEHOLDER_date', 'date');
                        $this->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
                    } else {
                        $this->quietly_forget('CITATION_BOT_PLACEHOLDER_year'); // @codeCoverageIgnore
                        $this->quietly_forget('CITATION_BOT_PLACEHOLDER_date'); // @codeCoverageIgnore
                    }
                    break;
                case "cite journal":
                    use_sici($this);
            }

            // APA PsycNet
            $the_title = $this->get('title');
            if ($the_title === 'APA PsycNet' && $this->has('doi')) {
                $this->set('title', '');
                $this->set('website', '');
                expand_by_doi($this);
            }
            if ($the_title === 'APA PsycNet' && mb_strpos($this->get('url'), 'psycnet.apa.org/record') !== false && mb_stripos($this->rawtext, 'citation_bot_placeholder_comment') === false) {
                $the_website = $this->get('website');
                $this->set('title', '');
                if ($this->has('website')) {
                    $this->set('website', '');
                }
                Zotero::expand_by_zotero($this);
                if ($this->has('title')) {
                    $the_title = $this->get('title');
                } else {
                    $this->set('title', $the_title);
                    if ($the_website !== '') {
                        $this->set('website', $the_website);
                    }
                }
            }
            unset($the_title, $the_website);

            handleConferencePretendingToBeAJournal($this, $this->rawtext);

            $this->tidy();
            // Fix up URLs hiding in identifiers
            foreach (['issn', 'oclc', 'pmc', 'doi', 'pmid', 'jstor', 'arxiv', 'zbl', 'mr', 'lccn', 'hdl', 'ssrn', 'ol', 'jfm', 'osti', 'biorxiv', 'citeseerx', 'hdl', 'medrxiv'] as $possible) {
                if ($this->has($possible)) {
                    $url = $this->get($possible);
                    if (
                        mb_stripos($url, 'CITATION_BOT') === false &&
                        !preg_match('~^https?://[^/]+/?$~', $url) && // Ignore just a hostname
                        preg_match(REGEXP_IS_URL, $url) === 1
                    ) {
                        $this->rename($possible, 'CITATION_BOT_PLACEHOLDER_possible');
                        $this->get_identifiers_from_url($url);
                        if ($this->has($possible)) {
                            $this->forget('CITATION_BOT_PLACEHOLDER_possible');
                        } else {
                            $this->rename('CITATION_BOT_PLACEHOLDER_possible', $possible);
                        }
                    }
                }
            }
            if ($this->wikiname() === 'cite document') {
                foreach (ALL_URL_TYPES as $thing) {
                    if ($this->blank($thing)) {
                        $this->forget($thing);
                    }
                    if ($this->blank($thing . '-access')) {
                        $this->forget($thing . '-access');
                    }
                }
                foreach (WORK_ALIASES as $thing) {
                    if ($this->blank($thing)) {
                        $this->forget($thing);
                    }
                }
                foreach (['archive-url', 'archiveurl', 'archivedate', 'archive-date', 'url-status'] as $thing) {
                    if ($this->blank($thing)) {
                        $this->forget($thing);
                    }
                }
            }
        } elseif ($this->wikiname() === 'cite magazine' && $this->blank('magazine') && $this->has_but_maybe_blank('work')) {
            // This is all we do with cite magazine
            $this->rename('work', 'magazine');
        }
    }

    public function fix_rogue_etal(): void {
        if ($this->blank(DISPLAY_AUTHORS)) {
            $i = 2;
            while (!$this->blank(['author' . (string) $i, 'last' . (string) $i])) {
                $i++;
            }
            $i--;
            if (preg_match('~^et\.? ?al\.?$~i', $this->get('author' . (string) $i))) {
                $this->rename('author' . (string) $i, 'display-authors', 'etal');
            }
            if (preg_match('~^et\.? ?al\.?$~i', $this->get('last' . (string) $i))) {
                $this->rename('last' . (string) $i, 'display-authors', 'etal');
            }
        }
    }

    public function record_api_usage(string $api, string $param): void {
        $param = [$param];
        foreach ($param as $p) {
            if (!in_array($p, $this->used_by_api[$api], true)) {
                $this->used_by_api[$api][] = $p;
            }
        }
    }

    /**
     * @param string $api
     * @param array<string> $param
     */
    public function api_has_used(string $api, array $param): bool {
        if (!isset($this->used_by_api[$api])) {
            report_error("Invalid API: " . $api); // @codeCoverageIgnore
        }
        /** @psalm-suppress all */
        return (bool) count(array_intersect($param, $this->used_by_api[$api]));
    }

    public function incomplete(): bool {
        // FYI: some references will never be considered complete
        $possible_extra_authors = $this->get('author') . $this->get('authors') . $this->get('vauthors');
        if (
            mb_strpos($possible_extra_authors, ' and ') !== false ||
            mb_strpos($possible_extra_authors, '; ') !== false ||
            mb_strpos($possible_extra_authors, 'et al ') !== false ||
            mb_strpos($possible_extra_authors, 'et al.') !== false ||
            mb_strpos($possible_extra_authors, ' et al') !== false ||
            mb_substr_count($possible_extra_authors, ',') > 3 ||
            $this->has('author2') ||
            $this->has('last2') ||
            $this->has('surname2')
        ) {
            $two_authors = true;
        } else {
            $two_authors = false;
        }
        if ($this->wikiname() === 'cite book' || ($this->wikiname() === 'citation' && $this->has('isbn'))) {
            // Assume book
            if ($this->display_authors() >= $this->number_of_authors()) {
                return true;
            }
            return !($this->has('isbn') && $this->has('title') && ($this->has('date') || $this->has('year')) && $two_authors);
        }
        if ($this->wikiname() === 'cite conference') {
            // cite conference uses very different parameters
            if ($this->has('title') && ($this->has('conference') || $this->has('book-title') || $this->has('chapter'))) {
                return false;
            }
        }
        // And now everything else
        if (
            $this->blank(['pages', 'page', 'at', 'article-number']) ||
            preg_match('~no.+no|n/a|in press|none~', $this->get('pages') . $this->get('page') . $this->get('at')) ||
            (
                preg_match('~^1[^0-9]~', $this->get('pages') . $this->get('page') . '-') &&
                (
                    $this->blank('year') ||
                    (int) date("Y") - (int) $this->get('year') < 2
                )
            ) // It claims to be on page one
        ) {
            return true;
        }
        if ($this->display_authors() >= $this->number_of_authors()) {
            return true;
        }

        if ($this->wikiname() === 'citation' && $this->has('work')) {
            return true; // Should consider changing the work parameter, since {{citation}} uses the work parameter type to determine format :-(
        }

        return !(
        ($this->has('journal') || $this->has('periodical') || $this->has('work') || $this->has('newspaper') || $this->has('magazine') || $this->has('trans-work') || $this->has('script-work')) &&
        $this->has('volume') &&
        ($this->has('issue') || $this->has('number')) &&
        $this->has('title') &&
        ($this->has('date') || $this->has('year')) &&
        $two_authors &&
        $this->get('journal') !== 'none' &&
        $this->get('title') !== 'none'
        );
    }

    public function profoundly_incomplete(string $url = ''): bool {
        // Zotero translation server often returns bad data, which is worth having if we have no data,
        // but we don't want to fill a single missing field with garbage if a reference is otherwise well formed.
        $has_date = $this->has('date') || $this->has('year');
        foreach (NO_DATE_WEBSITES as $bad_website) {
            if (mb_stripos($url, $bad_website) !== false) {
                $has_date = true;
                break;
            }
        }

        if ($this->wikiname() === 'cite book' || ($this->wikiname() === 'citation' && $this->has('isbn'))) {
            if ($this->display_authors() >= $this->number_of_authors()) {
                return true;
            }
            return !($this->has('isbn') && $this->has('title') && $has_date);
        }

        if (str_ireplace(NON_JOURNAL_WEBSITES, '', $url) !== $url) {
            // A website that will never give a volume
            return !(
            ($this->has('journal') ||
            $this->has('periodical') ||
            $this->has('work') ||
            $this->has('trans-work') ||
            $this->has('script-work') ||
            $this->has('website') ||
            $this->has('publisher') ||
            $this->has('newspaper') ||
            $this->has('magazine') ||
            $this->has('encyclopedia') ||
            $this->has('encyclopaedia') ||
            $this->has('contribution')) &&
            $this->has('title') &&
            $has_date
            );
        }
        return !(($this->has('journal') || $this->has('periodical') || $this->has('trans-work') || $this->has('script-work')) && $this->has('volume') && $this->has('title') && $has_date);
    }

    /**
     * @param array<string>|string $param
     */
    public function blank(array|string $param): bool {
        // Accepts arrays of strings and string
        if (!$param) {
            report_error('null passed to blank()'); // @codeCoverageIgnore
        }
        if (empty($this->param)) {
            return true;
        }
        if (!is_array($param)) {
            $param = [$param];
        }
        foreach ($this->param as $p) {
            if (in_array($p->param, $param, true) && mb_trim($p->val) !== '' && !str_i_same('Epub ahead of print', $p->val)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string>|string $param
     */
    public function blank_other_than_comments(array|string $param): bool {
        // Accepts arrays of strings and string
        if (!$param) {
            report_error('null passed to blank_other_than_comments()'); // @codeCoverageIgnore
        }
        if (empty($this->param)) {
            return true;
        }
        if (!is_array($param)) {
            $param = [$param];
        }
        foreach ($this->param as $p) {
            if (in_array($p->param, $param, true)) {
                $value = $p->val;
                $value = mb_trim($value);
                if (mb_stripos($value, '# # # CITATION_BOT_PLACEHOLDER_COMMENT') !== false) {
                    // Regex failure paranoia
                    $value = mb_trim(preg_replace('~^# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~i', '', $value));
                    $value = mb_trim(preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #$~i', '', $value));
                    $value = mb_trim(preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~i', '', $value));
                }
                $value = mb_trim($value);
                if ($value !== '') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Adds a parameter to a template if the parameter and its equivalents are blank.
     * $api (string) specifies the API route by which a parameter was found; this will log the
     * parameter so it is not used to trigger a new search via the same API.
     */
    public function add_if_new(string $param_name, string $value, string $api = ''): bool {
        // Clean up weird stuff from CrossRef etc.
        $value = safe_preg_replace('~[\x{2000}-\x{200B}\x{00A0}\x{202F}\x{205F}\x{3000}]~u', ' ', $value); // Non-standard spaces
        $value = safe_preg_replace("~^\xE2\x80\x8B~", " ", $value); // Zero-width at start
        $value = safe_preg_replace("~\xE2\x80\x8B$~", " ", $value); // Zero-width at end
        $value = safe_preg_replace('~[\t\n\r\0\x0B]~u', ' ', $value); // tabs, linefeeds, null bytes
        $value = safe_preg_replace('~  +~u', ' ', $value); // multiple spaces
        $value = safe_preg_replace('~&#124;$~u', ' ', $value); // Ends with pipe
        $value = safe_preg_replace('~^&#124;~u', ' ', $value); // Starts with pipe
        $value = mb_trim($value);
        if ($value === '' || $value === '--' || $value === '-') {
            return false;
        }
        $param_name = mb_trim($param_name); // Pure paranoia
        if ($param_name === '') {
            report_error('invalid param_name passed to add_if_new()'); // @codeCoverageIgnore
        }
        // Clean up things we get from floaters
        if ($param_name === 'editor-first') {
            $param_name = 'editor-first1';
        }
        if ($param_name === 'editor-last') {
            $param_name = 'editor-last1';
        }
        if ($param_name === 'editor') {
            $param_name = 'editor1';
        }

        $low_value = mb_strtolower($value);
        if (in_array($low_value, REJECT_NEW, true)) {
            // Hopeully name is not actually null
            return false;
        }

        if (mb_stripos($this->get($param_name), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== false) {
            return false; // We let comments block the bot
        }

        if (array_key_exists($param_name, COMMON_MISTAKES)) {
            // This is not an error, since sometimes the floating text code finds odd stuff
            report_minor_error("Attempted to add invalid parameter: " . echoable($param_name)); // @codeCoverageIgnore
        }

        // We have to map these, since sometimes we get floating accessdate and such
        if (array_key_exists($param_name, COMMON_MISTAKES_TOOL)) {
            $param_name = COMMON_MISTAKES_TOOL[$param_name];
        }

        // Block URLs from being added to non-URL parameters
        if ($this->is_url_in_non_url_parameter($param_name, $value)) {
            report_warning("Rejecting URL in non-URL parameter |" . echoable($param_name) . "=");
            bot_debug_log("URL rejected for parameter: " . $param_name . " = " . $value);
            return false;
        }

        // Block journal, newspaper, etc. (CITE_BOOK_UNSUPPORTED_PARAMS) from being added to cite book templates
        // We might want to think about if there are any cases with bad existing data
        if (in_array($param_name, CITE_BOOK_UNSUPPORTED_PARAMS, true) && $this->wikiname() === 'cite book') {
            report_warning("Not adding " . echoable($param_name) . " parameter to cite book template (unsupported)");
            return false;
        }

        // Block parameters not allowed in cite bioRxiv template
        if ($this->wikiname() === 'cite biorxiv') {
            $param_name_lower = mb_strtolower($param_name);
            $is_allowed = in_array($param_name_lower, CITE_BIORXIV_ALLOWED_PARAMS, true);

            // Allow author and editor parameters even if not in the explicit allowed list
            if (!$is_allowed && (
                preg_match('~^(?:author|last|first|given|surname|forename|initials)\d*$~i', $param_name) ||
                preg_match('~^(?:author|editor)\d+-(?:last|first|given|surname|forename|initials|link|mask)$~i', $param_name) ||
                preg_match('~^(?:author|editor)-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $param_name) ||
                preg_match('~^(?:authorlink|authormask|editorlink|editormask)\d*$~i', $param_name) ||
                preg_match('~^editor\d*$~i', $param_name) ||
                preg_match('~^editor-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $param_name) ||
                preg_match('~^(?:vauthors|authors|display-authors|displayauthors|veditors|editors|display-editors|displayeditors)$~i', $param_name) ||
                mb_stripos($param_name, 'CITATION_BOT') !== false ||
                mb_stripos($param_name, 'DUPLICATE') !== false
            )) {
                $is_allowed = true;
            }

            if (!$is_allowed) {
                report_warning("Not adding " . echoable($param_name) . " parameter to cite bioRxiv template (unsupported)");
                return false;
            }
        }

        // Block parameters not allowed in cite medRxiv template
        if ($this->wikiname() === 'cite medrxiv') {
            $param_name_lower = mb_strtolower($param_name);
            $is_allowed = in_array($param_name_lower, CITE_MEDRXIV_ALLOWED_PARAMS, true);

            // Allow author and editor parameters even if not in the explicit allowed list
            if (!$is_allowed && (
                preg_match('~^(?:author|last|first|given|surname|forename|initials)\d*$~i', $param_name) ||
                preg_match('~^(?:author|editor)\d+-(?:last|first|given|surname|forename|initials|link|mask)$~i', $param_name) ||
                preg_match('~^(?:author|editor)-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $param_name) ||
                preg_match('~^(?:authorlink|authormask|editorlink|editormask)\d*$~i', $param_name) ||
                preg_match('~^editor\d*$~i', $param_name) ||
                preg_match('~^editor-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $param_name) ||
                preg_match('~^(?:vauthors|authors|display-authors|displayauthors|veditors|editors|display-editors|displayeditors)$~i', $param_name) ||
                mb_stripos($param_name, 'CITATION_BOT') !== false ||
                mb_stripos($param_name, 'DUPLICATE') !== false
            )) {
                $is_allowed = true;
            }

            if (!$is_allowed) {
                report_warning("Not adding " . echoable($param_name) . " parameter to cite medRxiv template (unsupported)");
                return false;
            }
        }

        /** @psalm-assert string $param_name */

        if ($api) {
            $this->record_api_usage($api, $param_name);
        }

        // If we already have name parameters for author, don't add more
        if ($this->had_initial_author() && in_array($param_name, FLATTENED_AUTHOR_PARAMETERS, true)) {
            return false;
        }

        if ($param_name !== 's2cid') {
            if (mb_strpos($param_name, 'last') === 0 || mb_strpos($param_name, 'first') === 0 || mb_strpos($param_name, 'author') === 0) {
                if ((int) mb_substr($param_name, -4) > 0 || (int) mb_substr($param_name, -3) > 0 || (int) mb_substr($param_name, -2) > 30) {
                    // Stop at 30 authors - or page codes will become cluttered!
                    if ((bool) $this->get('last29') || (bool) $this->get('author29') || (bool) $this->get('surname29')) {
                        $this->add_if_new('display-authors', '1');
                    }
                    return false;
                }
            }
            if (mb_strpos($param_name, 'editor') === 0) {
                if ((int) mb_substr($param_name, -4) > 0 || (int) mb_substr($param_name, -3) > 0 || (int) mb_substr($param_name, -2) > 30) {
                    // Stop at 30 editors - or page codes will become cluttered!
                    if ((bool) $this->get('editor29') || (bool) $this->get('editor-last29') || (bool) $this->get('editor29-last')) {
                        $this->add_if_new('display-editors', '1');
                    }
                    return false;
                }
            }
        }

        $auNo = preg_match('~\d+$~', $param_name, $auNo) ? $auNo[0] : '';

        switch ($param_name) {
            // EDITORS
            case (bool) preg_match('~^editor(\d{1,})$~', $param_name, $match):
                if ($this->had_initial_editor()) {
                    return false;
                }
                if (mb_stripos($this->get('doi'), '10.1093/gmo') !== false) {
                    return false;
                }
                if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) {
                    return false;
                } // Existing incompatible data
                if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-last', 'editor' . $match[1] . '-first', 'editor-last' . $match[1], 'editor-first' . $match[1]])) {
                    /** @psalm-suppress NoValue */
                    return $this->add($param_name, clean_up_full_names($value));
                }
                return false;

            case (bool) preg_match('~^editor-first(\d{1,})$~', $param_name, $match):
                if ($this->had_initial_editor()) {
                    return false;
                }
                if (mb_stripos($this->get('doi'), '10.1093/gmo') !== false) {
                    return false;
                }
                if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) {
                    return false;
                } // Existing incompatible data
                if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-first', 'editor-first' . $match[1]])) {
                    /** @psalm-suppress NoValue */
                    return $this->add($param_name, clean_up_first_names($value));
                }
                return false;

            case (bool) preg_match('~^editor-last(\d{1,})$~', $param_name, $match):
                if ($this->had_initial_editor()) {
                    return false;
                }
                if (mb_stripos($this->get('doi'), '10.1093/gmo') !== false) {
                    return false;
                }
                if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) {
                    return false;
                } // Existing incompatible data
                if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-last', 'editor-last' . $match[1]])) {
                    /** @psalm-suppress NoValue */
                    return $this->add($param_name, clean_up_last_names($value));
                }
                return false;

            case (bool) preg_match('~^translator(\d{1,})$~', $param_name, $match):
                if (!$this->blank(['translators', 'translator', 'translator-last', 'translator-first'])) {
                    return false;
                } // Existing incompatible data
                if ($this->blank(['translator' . $match[1], 'translator' . $match[1] . '-last', 'translator' . $match[1] . '-first'])) {
                    /** @psalm-suppress NoValue */
                    return $this->add($param_name, clean_up_full_names($value));
                }
                return false;

            // AUTHORS
            case "author":
            case "author1":
            case "last1":
            case "last":
            case "authors":
                if ($this->blank(FIRST_AUTHOR_ALIASES)) {
                    $value = clean_up_full_names($value);
                    $au = split_author($value);
                    if (!empty($au) && mb_substr($param_name, 0, 3) === 'aut') {
                        $this->add('last' . (mb_substr($param_name, -1) === '1' ? '1' : ''), clean_up_last_names(format_surname($au[0])));
                        return $this->add_if_new('first' . (mb_substr($param_name, -1) === '1' ? '1' : ''), clean_up_first_names(format_forename(mb_trim($au[1]))));
                    } elseif (mb_strpos($param_name, 'last') === false) {
                        return $this->add($param_name, $value);
                    } else {
                        return $this->add($param_name, clean_up_last_names($value));
                    }
                }
                return false;

            case "first":
            case "first1":
                if ($this->blank(FIRST_FORENAME_ALIASES)) {
                    return $this->add($param_name, clean_up_first_names($value));
                }
                return false;
            case "last2":
            case "last3":
            case "last4":
            case "last5":
            case "last6":
            case "last7":
            case "last8":
            case "last9":
            case "last10":
            case "last20":
            case "last30":
            case "last40":
            case "last50":
            case "last60":
            case "last70":
            case "last80":
            case "last90":
            case "last11":
            case "last21":
            case "last31":
            case "last41":
            case "last51":
            case "last61":
            case "last71":
            case "last81":
            case "last91":
            case "last12":
            case "last22":
            case "last32":
            case "last42":
            case "last52":
            case "last62":
            case "last72":
            case "last82":
            case "last92":
            case "last13":
            case "last23":
            case "last33":
            case "last43":
            case "last53":
            case "last63":
            case "last73":
            case "last83":
            case "last93":
            case "last14":
            case "last24":
            case "last34":
            case "last44":
            case "last54":
            case "last64":
            case "last74":
            case "last84":
            case "last94":
            case "last15":
            case "last25":
            case "last35":
            case "last45":
            case "last55":
            case "last65":
            case "last75":
            case "last85":
            case "last95":
            case "last16":
            case "last26":
            case "last36":
            case "last46":
            case "last56":
            case "last66":
            case "last76":
            case "last86":
            case "last96":
            case "last17":
            case "last27":
            case "last37":
            case "last47":
            case "last57":
            case "last67":
            case "last77":
            case "last87":
            case "last97":
            case "last18":
            case "last28":
            case "last38":
            case "last48":
            case "last58":
            case "last68":
            case "last78":
            case "last88":
            case "last98":
            case "last19":
            case "last29":
            case "last39":
            case "last49":
            case "last59":
            case "last69":
            case "last79":
            case "last89":
            case "last99":
            case "author2":
            case "author3":
            case "author4":
            case "author5":
            case "author6":
            case "author7":
            case "author8":
            case "author9":
            case "author10":
            case "author20":
            case "author30":
            case "author40":
            case "author50":
            case "author60":
            case "author70":
            case "author80":
            case "author90":
            case "author11":
            case "author21":
            case "author31":
            case "author41":
            case "author51":
            case "author61":
            case "author71":
            case "author81":
            case "author91":
            case "author12":
            case "author22":
            case "author32":
            case "author42":
            case "author52":
            case "author62":
            case "author72":
            case "author82":
            case "author92":
            case "author13":
            case "author23":
            case "author33":
            case "author43":
            case "author53":
            case "author63":
            case "author73":
            case "author83":
            case "author93":
            case "author14":
            case "author24":
            case "author34":
            case "author44":
            case "author54":
            case "author64":
            case "author74":
            case "author84":
            case "author94":
            case "author15":
            case "author25":
            case "author35":
            case "author45":
            case "author55":
            case "author65":
            case "author75":
            case "author85":
            case "author95":
            case "author16":
            case "author26":
            case "author36":
            case "author46":
            case "author56":
            case "author66":
            case "author76":
            case "author86":
            case "author96":
            case "author17":
            case "author27":
            case "author37":
            case "author47":
            case "author57":
            case "author67":
            case "author77":
            case "author87":
            case "author97":
            case "author18":
            case "author28":
            case "author38":
            case "author48":
            case "author58":
            case "author68":
            case "author78":
            case "author88":
            case "author98":
            case "author19":
            case "author29":
            case "author39":
            case "author49":
            case "author59":
            case "author69":
            case "author79":
            case "author89":
            case "author99":
                if (
                    $this->blank([...COAUTHOR_ALIASES, "last{$auNo}", "author{$auNo}"]) &&
                    mb_strpos($this->get('author') . $this->get('authors'), ' and ') === false &&
                    mb_strpos($this->get('author') . $this->get('authors'), '; ') === false &&
                    mb_strpos($this->get('author') . $this->get('authors'), ' et al') === false
                ) {
                    $value = clean_up_full_names($value);
                    $au = split_author($value);
                    if (!empty($au) && mb_substr($param_name, 0, 3) === 'aut') {
                        $this->add('last' . $auNo, clean_up_last_names(format_surname($au[0])));
                        return $this->add_if_new('first' . $auNo, clean_up_first_names(format_forename(mb_trim($au[1]))));
                    } else {
                        return $this->add($param_name, $value);
                    }
                }
                return false;
            case "first2":
            case "first3":
            case "first4":
            case "first5":
            case "first6":
            case "first7":
            case "first8":
            case "first9":
            case "first10":
            case "first11":
            case "first12":
            case "first13":
            case "first14":
            case "first15":
            case "first16":
            case "first17":
            case "first18":
            case "first19":
            case "first20":
            case "first21":
            case "first22":
            case "first23":
            case "first24":
            case "first25":
            case "first26":
            case "first27":
            case "first28":
            case "first29":
            case "first30":
            case "first31":
            case "first32":
            case "first33":
            case "first34":
            case "first35":
            case "first36":
            case "first37":
            case "first38":
            case "first39":
            case "first40":
            case "first41":
            case "first42":
            case "first43":
            case "first44":
            case "first45":
            case "first46":
            case "first47":
            case "first48":
            case "first49":
            case "first50":
            case "first51":
            case "first52":
            case "first53":
            case "first54":
            case "first55":
            case "first56":
            case "first57":
            case "first58":
            case "first59":
            case "first60":
            case "first61":
            case "first62":
            case "first63":
            case "first64":
            case "first65":
            case "first66":
            case "first67":
            case "first68":
            case "first69":
            case "first70":
            case "first71":
            case "first72":
            case "first73":
            case "first74":
            case "first75":
            case "first76":
            case "first77":
            case "first78":
            case "first79":
            case "first80":
            case "first81":
            case "first82":
            case "first83":
            case "first84":
            case "first85":
            case "first86":
            case "first87":
            case "first88":
            case "first89":
            case "first90":
            case "first91":
            case "first92":
            case "first93":
            case "first94":
            case "first95":
            case "first96":
            case "first97":
            case "first98":
            case "first99":
                if ($this->blank([...COAUTHOR_ALIASES, $param_name, "author" . $auNo]) && under_two_authors($this->get('author'))) {
                    return $this->add($param_name, clean_up_first_names($value));
                }
                return false;

            case 'displayauthors':
            case 'display-authors':
                if ($this->blank(DISPLAY_AUTHORS)) {
                    return $this->add('display-authors', $value);
                }
                return false;

            case 'displayeditors':
            case 'display-editors':
                if ($this->blank(DISPLAY_EDITORS)) {
                    return $this->add('display-editors', $value);
                }
                return false;

            case 'accessdate':
            case 'access-date':
                if (!$this->blank(['access-date', 'accessdate'])) {
                    return false;
                }
                $time = strtotime($value);
                if ($time) { // should come in cleaned up
                    $value = self::localize_dates($time);
                    return $this->add('access-date', $value);
                }
                return false;

            case 'archivedate':
            case 'archive-date':
                if (!$this->blank(['archive-date', 'archivedate'])) {
                    return false;
                }
                $time = strtotime($value);
                if ($time) { // should come in cleaned up
                    $value = self::localize_dates($time);
                    return $this->add('archive-date', $value);
                }
                return false;

            case 'pmc-embargo-date': // Must come in formatted right!
                if (!$this->blank('pmc-embargo-date')) {
                    return false;
                }
                if (!preg_match('~2\d\d\d~', $value)) {
                    return false;
                } // 2000 or later
                $new_date = strtotime($value);
                $now_date = strtotime('now');
                if ($now_date > $new_date) {
                    return false;
                }
                return $this->add('pmc-embargo-date', $value);

            // DATE AND YEAR

            case "date":
                if (!preg_match('~^\d{4}$~', $value)) {
                    $time = strtotime($value);
                    $almost_today = strtotime('-14 days');
                    $the_future = strtotime('+14 days');
                    if ((int) $time > $almost_today && (int) $time < $the_future) {
                        return false;  // Reject bad data
                    }
                    if (self::$date_style !== DateStyle::DATES_WHATEVER || preg_match('~^\d{4}\-\d{2}\-\d{2}$~', $value)) {
                        if ($time) {
                            $day = date('d', $time);
                            if ($day !== '01') { // Probably just got month and year if day=1
                                $value = self::localize_dates($time);
                            }
                        }
                    }
                }
                // no break; we want to go straight in to year;
            case "year":
                if ($this->has('publication-date')) {
                    return false;
                } // No idea what to do with this
                if ($value === $this->year()) {
                    return false;
                }
                if (
                    ($this->blank('date') || in_array(mb_trim(mb_strtolower($this->get_without_comments_and_placeholders('date'))), IN_PRESS_ALIASES, true)) &&
                    ($this->blank('year') || in_array(mb_trim(mb_strtolower($this->get_without_comments_and_placeholders('year'))), IN_PRESS_ALIASES, true))
                ) {
                    // Delete any "in press" dates.
                    $this->forget('year'); // "year" is discouraged
                    if ($this->add('date', $value)) {
                        $this->tidy_parameter('isbn'); // We just added a date, we now know if 2007 or later
                        return true;
                    }
                }
                // Update Year with CrossRef data in a few limited cases
                if ($param_name === 'year' && $api === 'crossref' && $this->no_initial_doi && $this->year_int() < $value && (int) date('Y') - 3 < $value) {
                    if ($this->blank('year')) {
                        $this->forget('year');
                        $this->set('date', $value);
                    } else {
                        $this->forget('date');
                        $this->set('year', $value);
                    }
                    $this->tidy_parameter('isbn');
                    return true;
                }
                return false;

            // JOURNAL IDENTIFIERS

            case 'issn':
                if ($this->blank(["journal", "periodical", "work", $param_name]) && preg_match('~^\d{4}-\d{3}[\dxX]$~', $value)) {
                    // Only add ISSN if journal is unspecified
                    return $this->add($param_name, $value);
                }
                return false;

            case 'issn_force': // When dropping URL, force adding it
                if ($this->blank('issn') && preg_match('~^\d{4}-\d{3}[\dxX]$~', $value)) {
                    return $this->add('issn', $value);
                }
                return false;

            case 'ismn':
                $value = str_ireplace('m', '9790', $value); // update them
                if ($this->blank('ismn')) {
                    return $this->add('ismn', $value);
                }
                return false;

            case 'periodical':
            case 'journal':
            case 'newspaper':
            case 'magazine':
                if (in_array($value, ['HEP Lib.Web', 'High Energy Physics Libraries Webzine'])) {
                    return false;
                }
                if ($value === 'Peoplemag') {
                    $value = 'People';
                }
                if (preg_match('~Conference Proceedings.*IEEE.*IEEE~', $value) ||
                preg_match('~International Workshop~', $value) ||
                preg_match('~ Held at ~', $value) ||
                $value === 'Wiley Online Library' ||
                mb_stripos($value, 'Capstone Projects') !== false ||
                mb_stripos($value, 'Dissertations') !== false ||
                mb_stripos($value, 'Theses and Projects') !== false ||
                mb_stripos($value, 'Electronic Thesis') !== false ||
                mb_stripos($value, ' and Capstones') !== false ||
                mb_stripos($value, ' and Problem Reports') !== false ||
                mb_stripos($value, 'Doctoral ') !== false ||
                mb_stripos($value, 'IETF Datatracker') !== false ||
                mb_stripos($value, 'Springerlink') !== false ||
                mb_stripos($value, 'Report No. ') !== false ||
                mb_stripos($value, 'Report Number ') !== false ||
                !$this->blank('book-title') ||
                !$this->blank(['trans-work', 'script-work']) ||
                in_array(mb_strtolower(sanitize_string($value)), BAD_TITLES, true)) {
                    return false;
                }
                if (in_array(mb_strtolower($value), ARE_MANY_THINGS, true)) {
                    if ($this->wikiname() === 'cite news' && $param_name === 'newspaper') {
                        // Only time we trust zotero on these (people already said news)
                    } else {
                        $param_name = 'website';
                    }
                }
                if (in_array(mb_strtolower(sanitize_string($this->get('journal'))), BAD_TITLES, true)) {
                    $this->forget('journal');
                } // Update to real data
                if (preg_match('~^(?:www\.|)rte.ie$~i', $value)) {
                    $value = 'RTÉ News';
                } // Russian special case code
                if ($this->wikiname() === 'cite book' && $this->has('chapter') && $this->has('title') && $this->has('series')) {
                    return false;
                }
                if ($this->has('title') && str_equivalent($this->get('title'), $value)) {
                    return false;
                } // Messed up already or in database
                if (!$this->blank(['agency', 'publisher', ...WORK_ALIASES]) && in_array(mb_strtolower($value), DUBIOUS_JOURNALS, true)) {
                    return false;
                } // non-journals that are probably same as agency or publisher that come from zotero
                if ($this->get($param_name) === 'none' || $this->blank(["journal", "periodical", "encyclopedia", "encyclopaedia", "newspaper", "magazine", "contribution"])) {
                    if (in_array(mb_strtolower(sanitize_string($value)), HAS_NO_VOLUME, true)) {
                        $this->forget('volume');
                    } // No volumes, just issues.
                    if (in_array(mb_strtolower(sanitize_string($value)), HAS_NO_ISSUE, true)) {
                        $this->forget('issue');
                        $this->forget('number');
                    } // No issues, just volumes
                    $value = wikify_external_text(title_case($value));
                    if ($this->has('series') && str_equivalent($this->get('series'), $value)) {
                        return false;
                    }
                    if ($this->has('work')) {
                        if (str_equivalent($this->get('work'), $value) && !in_array(mb_strtolower($value), ARE_MANY_THINGS, true)) {
                            if ($param_name === 'journal') {
                                $this->rename('work', $param_name);
                            } // Distinction between newspaper and magazine and websites are not clear to zotero
                            if (!$this->blank(['pmc', 'doi', 'pmid'])) {
                                $this->forget('issn');
                            }
                            return true;
                        } else {
                            return false; // Cannot have both work and journal
                        }
                    }
                    if ($this->has('via')) {
                        if (str_equivalent($this->get('via'), $value)) {
                            $this->rename('via', $param_name);
                            if (!$this->blank(['pmc', 'doi', 'pmid'])) {
                                $this->forget('issn');
                            }
                            return true;
                        }
                    }
                    $this->forget('class');
                    if ($this->wikiname() === 'cite arxiv') {
                        $this->change_name_to('cite journal');
                    }
                    if ($param_name === 'newspaper' && in_array(mb_strtolower($value), WEB_NEWSPAPERS, true)) {
                        if ($this->has('publisher') && str_equivalent($this->get('publisher'), $value)) {
                            return false;
                        }
                        if ($this->blank('work')) {
                            $this->set('work', $value);
                            $this->quietly_forget('website');
                            if (mb_stripos($this->get('publisher'), 'bbc') !== false && mb_stripos($value, 'bbc') !== false) {
                                $this->quietly_forget('publisher');
                            }
                            return true;
                        }
                        report_error('Unreachable code reached in newspaper add'); // @codeCoverageIgnore
                    }
                    if ($param_name === 'newspaper' && $this->has('via')) {
                        if (mb_stripos($value, 'times') !== false && mb_stripos($this->get('via'), 'times') !== false) {
                            $this->forget('via'); // eliminate via= that matches newspaper mostly
                        }
                        if (mb_stripos($value, ' post') !== false && mb_stripos($this->get('via'), 'post') !== false) {
                            $this->forget('via'); // eliminate via= that matches newspaper mostly
                        }
                        if (mb_stripos($value, ' bloomberg') !== false && mb_stripos($this->get('via'), 'bloomberg') !== false) {
                            $this->forget('via'); // eliminate via= that matches newspaper mostly
                        }
                    }
                    if (($param_name === 'newspaper' || $param_name === 'journal') && $this->has('publisher') && str_equivalent($this->get('publisher'), $value) && $this->blank('website')) {
                        // Website is an alias for newspaper/work/journal, and did not check above
                        $this->rename('publisher', $param_name);
                        return true;
                    }
                    if ($this->has('website')) {
                        if (str_equivalent($this->get('website'), $value)) {
                            if ($param_name === 'journal') {
                                $this->rename('website', $param_name);
                            } // alias for journal. Distinction between newspaper and magazine and websites are not clear to zotero
                        } elseif (preg_match('~^\[.+\]$~', $this->get('website'))) {
                            if ($param_name === 'journal') {
                                $this->rename('website', $param_name);
                            } // existing data is linked
                        } elseif (!in_array(mb_strtolower($value), ARE_MANY_THINGS, true)) {
                            $this->rename('website', $param_name, $value);
                        }
                        return true;
                    } else {
                        $my_return = $this->add($param_name, $value);
                        // Avoid running twice
                        $this->tidy_parameter('publisher');
                        return $my_return;
                    }
                }
                return false;

            case 'series':
                if ($this->blank($param_name)) {
                    $value = wikify_external_text($value);
                    if ($this->has('journal') && str_equivalent($this->get('journal'), $value)) {
                        return false;
                    }
                    if ($this->has('title') && str_equivalent($this->get('title'), $value)) {
                        return false;
                    }
                    if ($value === 'A Penguin book') {
                        return false;
                    }
                    if ($value === 'Also known as:Official records of the Union and Confederate armies') {
                        return false;
                    }
                    // Correct series misspellings before adding
                    $lower = mb_strtolower($value);
                    if (isset(SERIES_CORRECTIONS[$lower])) {
                        $value = SERIES_CORRECTIONS[$lower];
                    }
                    return $this->add($param_name, $value);
                }
                return false;

            case 'chapter':
            case 'contribution':
            case 'article':
            case 'section': //  We do not add article/section, but sometimes found floating in a template
                if (!$this->blank('book-title') && $this->has('title')) {
                    return false;
                }
                if (!$this->blank(WORK_ALIASES) && $this->wikiname() === 'citation') {
                    return false;
                } // TODO - check for things that should be swapped etc.
                $value = preg_replace('~^\[\d+\]\s*~', '', $value); // Remove chapter numbers
                if ($this->blank(CHAPTER_ALIASES)) {
                    return $this->add($param_name, wikify_external_text($value));
                }
                return false;

            //  ARTICLE LOCATORS
            // (page, volume etc)

            case 'title':
                if ($this->has('trans-title')) {
                    return false;
                }
                if (in_array(mb_strtolower(sanitize_string($value)), BAD_TITLES, true)) {
                    return false;
                }
                if (
                    $this->blank($param_name) ||
                    in_array($this->get($param_name), GOOFY_TITLES, true) ||
                    (mb_stripos($this->get($param_name), 'EZProxy') !== false && mb_stripos($value, 'EZProxy') === false)
                ) {
                    foreach (['encyclopedia', 'encyclopaedia', 'work', 'dictionary', 'journal'] as $worky) {
                        if (str_equivalent($this->get($worky), sanitize_string($value))) {
                               return false;
                        }
                    }
                    if ($this->has('article') && ($this->wikiname() === 'cite encyclopedia' || $this->wikiname() === 'cite dictionary' || $this->wikiname() === 'cite encyclopaedia')) {
                        return false;
                    } // Probably the same thing
                    if (!$this->blank('book-title')) {
                        return false;
                    } // Cite conference uses this
                    if ($this->blank('script-title')) {
                        return $this->add($param_name, wikify_external_text($value));
                    } else {
                        $value = mb_trim($value);
                        $script_value = $this->get('script-title');
                        if (preg_match('~^[a-zA-Z0-9\.\,\-\; ]+$~u', $value) && mb_stripos($script_value, $value) === false && mb_stripos($value, $script_value) === false && !preg_match('~^[a-zA-Z0-9\.\,\-\; ]+$~u', $script_value)) {
                            // Neither one is part of the other and script is not all ascii and new title is all ascii
                            return $this->add($param_name, wikify_external_text($value));
                        }
                    }
                }
                return false;

            case 'volume':
                if ($this->blank($param_name) || str_i_same('in press', $this->get($param_name))) {
                    if ($value === '0') {
                        return false;
                    }
                    if ($value === 'Online First') {
                        return false;
                    }
                    if ($value === $this->year()) {
                        return false;
                    }
                    if ($value === 'volume') {
                        return false;
                    }
                    if ($value === '1') {
                        // dubious
                        if (bad_10_1093_doi($this->get('doi'))) {
                            return false;
                        }
                        if (mb_stripos($this->rawtext, 'oxforddnb') !== false) {
                            return false;
                        }
                        if (mb_stripos($this->rawtext, 'escholarship.org') !== false) {
                            return false;
                        }
                    }
                    if (preg_match('~^volume[:\s]+0*(.*)~i', $value, $matches)) {
                        $value = $matches[1];
                    }
                    if (intval($value) > 1820 && mb_stripos($this->get('doi'), '10.1515/crll') === 0) {
                        return false;
                    }
                    if (mb_strlen($value) > 20) {
                        return false;
                    }
                    $temp_string = mb_strtolower($this->get('journal'));
                    if (mb_substr($temp_string, 0, 2) === "[[" && mb_substr($temp_string, -2) === "]]") {
                        // Wikilinked journal title
                        $temp_string = mb_substr(mb_substr($temp_string, 2), 0, -2); // Remove [[ and ]]
                        $temp_string = preg_replace('~^.+\|~', '', $temp_string); // Remove part before pipe, if it has one
                    }
                    if (in_array($temp_string, HAS_NO_VOLUME, true)) {
                        // This journal has no volume. This is really the issue number
                        return $this->add_if_new('issue', $value);
                    } else {
                        return $this->add($param_name, $value);
                    }
                }
                return false;

            case 'issue':
            case 'number':
                if ($value === '0') {
                    return false;
                }
                if ($value === '1' && $this->year() !== '' && $this->year_int() < 1960) {
                    return false;
                }
                if ($value === 'Online First') {
                    return false;
                }
                if ($value === 'issue') {
                    return false;
                }
                if (preg_match('~\d\d\d\d\d\d~', $value)) {
                    return false;
                }
                $temp_string = mb_strtolower($this->get('journal'));
                if (mb_substr($temp_string, 0, 2) === "[[" && mb_substr($temp_string, -2) === "]]") {
                    // Wikilinked journal title
                    $temp_string = mb_substr(mb_substr($temp_string, 2), 0, -2); // Remove [[ and ]]
                    $temp_string = preg_replace('~^.+\|~', '', $temp_string); // Remove part before pipe, if it has one
                }
                if (in_array($temp_string, HAS_NO_ISSUE, true)) {
                    return $this->add_if_new('volume', $value);
                }
                if ($this->blank(ISSUE_ALIASES) || str_i_same('in press', $this->get($param_name))) {
                    if ($value === '1') {
                        // dubious
                        if (bad_10_1093_doi($this->get('doi'))) {
                               return false;
                        }
                        if (mb_stripos($this->rawtext, 'oxforddnb') !== false) {
                            return false;
                        }
                        if (mb_stripos($this->rawtext, 'escholarship.org') !== false) {
                            return false;
                        }
                    }
                    return $this->add($param_name, $value);
                } elseif ($this->get('issue') . $this->get('number') === '1' && $value !== '1' && $this->blank('volume')) {
                    if ($param_name === 'issue' && $this->has('number')) {
                        $this->rename('number', 'issue');
                    }
                    if ($param_name === 'number' && $this->has('issue')) {
                        $this->rename('issue', 'number');
                    }
                    $this->set($param_name, $value); // Updating bad data
                    return true;
                }
                return false;

            case "page":
            case "pages":
                if (in_array($value, BAD_NEW_PAGES, true)) {
                    return false;
                }
                if ($this->has('at') || $this->has('article-number')) {
                    return false;
                } // Leave at= alone.  People often use that for at=See figure 17 on page......
                if (preg_match('~^\d+$~', $value) && intval($value) > 50000) {
                    return false;
                } // Sometimes get HUGE values
                if (mb_stripos($value, 'gigabyte') !== false) {
                    return false;
                } // bad pmid data
                // Check if this is a Zootaxa/Phytotaxa DOI and reject page values that look like DOI suffixes
                $doi = $this->get('doi');
                if ($doi && preg_match('~^10\.11646/(?:zoo|phyto)taxa\.\d+\.\d+\.\d+$~i', $doi)) {
                    // DOI suffix format: journal.volume.issue.article
                    if (preg_match('~^(?:zoo|phyto)taxa\.\d+\.\d+\.\d+$~i', $value)) {
                        // Page value looks like a DOI suffix - reject it
                        report_warning("Rejecting page value that appears to be DOI suffix for Zootaxa/Phytotaxa: " . echoable($value));
                        return false;
                    }
                }
                $pages_value = $this->get('pages');
                $all_page_values = $pages_value . $this->get('page') . $this->get('pp') . $this->get('p') . $this->get('at');
                $en_dash = [chr(2013), chr(150), chr(226), '-', '&ndash;'];
                $en_dash_X = ['X', 'X', 'X', 'X', 'X'];
                if (
                    mb_stripos($all_page_values, 'see ') !== false || // Someone is pointing to a specific part
                    mb_stripos($all_page_values, 'table') !== false ||
                    mb_stripos($all_page_values, 'footnote') !== false ||
                    mb_stripos($all_page_values, 'endnote') !== false ||
                    mb_stripos($all_page_values, 'article') !== false ||
                    mb_stripos($all_page_values, '[') !== false ||
                    mb_stripos($all_page_values, ',') !== false ||
                    mb_stripos($all_page_values, '(') !== false ||
                    mb_stripos($all_page_values, 'CITATION_BOT_PLACEHOLDER') !== false
                ) {
                    // A comment or template will block the bot
                    return false;
                }
                if (
                    $this->blank(PAGE_ALIASES) || // no page yet set
                    $all_page_values === "" ||
                    (
                        str_i_same($all_page_values, 'no') ||
                        str_i_same($all_page_values, 'none')
                    ) || // Is exactly "no" or "none"
                    (
                        mb_strpos(mb_strtolower($all_page_values), 'no') !== false &&
                        $this->blank('at')
                    ) || // "None" or "no" contained within something other than "at"
                    (
                        str_replace($en_dash, $en_dash_X, $value) !== $value && // dash in new `pages`
                        str_replace($en_dash, $en_dash_X, $pages_value) === $pages_value
                    ) || // No dash already // Document with bogus pre-print page ranges
                    (
                         $value !== '1' &&
                        mb_substr(str_replace($en_dash, $en_dash_X, $value), 0, 2) !== '1X' && // New is not 1-
                        (
                            $all_page_values === '1' ||
                            mb_substr(str_replace($en_dash, $en_dash_X, $all_page_values), 0, 2) === '1X'
                        ) && // Old is 1-
                        (
                            $this->blank('year') ||
                            (int) date("Y") - (int) $this->get('year') < 2
                        )
                    ) // Less than two years old
                ) {
                    if ($param_name === "pages" && preg_match('~^\d{1,}$~', $value)) {
                        $param_name = 'page';
                    } // No dashes, just numbers
                    // One last check to see if old template had a specific page listed
                    if (
                        $all_page_values !== '' &&
                        preg_match("~^[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?[-–—‒]+[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?$~u", $value, $newpagenos) && // Adding a range
                        preg_match("~^[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?~u", $all_page_values, $oldpagenos)
                    ) {
                        // Just had a single number before
                        $first_page = (int) $newpagenos[1];
                        $last_page = (int) $newpagenos[2];
                        $old_page = (int) $oldpagenos[1];
                        if ($last_page < $first_page) {
                            // 2342-5 instead of 2342-2345
                            if ($last_page < 10) {
                                $last_page = $last_page + 10 * (int) ($first_page / 10);
                            } else {
                                $last_page = $last_page + 100 * (int) ($first_page / 100);
                            }
                        }
                        if ($old_page > $first_page && $old_page <= $last_page) {
                            foreach (['pages', 'page', 'pp', 'p', 'article-number'] as $forget_blank) {
                                if ($this->blank($forget_blank)) {
                                       $this->forget($forget_blank);
                                }
                            }
                            return false;
                        }
                    }
                    // 1-34 vs article 431234 -- Some give range and article ID as page numbers depending upon database - at least 4 characters though. Prefer article number
                    if (preg_match('~^1[-–]\d+$~u', $value) && preg_match('~^[a-zA-Z1-9]\d{3,}$~', $all_page_values)) {
                        return false;
                    }
                    if ($param_name !== "pages") {
                        $this->forget('pages');
                    } // Forget others -- sometimes we upgrade page=123 to pages=123-456
                    if ($param_name !== "page") {
                        $this->forget('page');
                    }
                    // Forget ones we never even add
                    $this->forget('pp');
                    $this->forget('p');
                    $this->forget('at');

                    if (preg_match("~^(\d+)[-–—‒]+(\d+)$~u", $value, $newpagenos)) {
                        $first_page = (int) $newpagenos[1];
                        $last_page = (int) $newpagenos[2];
                        if ($last_page < $first_page) {
                            // 2342-5 instead of 2342-2345
                            if ($last_page < 10) {
                                $last_page = $last_page + 10 * (int) ($first_page / 10);
                            } else {
                                $last_page = $last_page + 100 * (int) ($first_page / 100);
                            }
                            if ($last_page > $first_page) {
                                // Paranoid
                                $value = (string) $first_page . "–" . (string) $last_page;
                            }
                        }
                    }

                    $param_key = $this->get_param_key($param_name);
                    if (!is_null($param_key)) {
                        $this->param[$param_key]->val = sanitize_string($value); // Minimize template changes (i.e. location) when upgrading from page=123 to pages=123-456
                    } else {
                        $this->add($param_name, sanitize_string($value));
                    }
                    $this->tidy_parameter($param_name); // Clean up dashes etc
                    return true;
                }
                return false;

            //  ARTICLE IDENTIFIERS

            case 'url':
                // look for identifiers in URL - might be better to add a PMC parameter, say
                if ($this->get_identifiers_from_url($value)) {
                    return false;
                }
                if (!$this->blank([$param_name, ...TITLE_LINK_ALIASES])) {
                    return false;
                }
                if ($this->get('title') === 'none') {
                    return false;
                }
                if (mb_strpos($this->get('title'), '[') !== false) {
                    return false;
                } // TITLE_LINK_ALIASES within the title
                $value = sanitize_string($value);
                foreach (ALL_URL_TYPES as $existing) {
                    if (str_i_same($value, $this->get($existing))) {
                        return false;
                    }
                }
                return $this->add($param_name, $value);

            case 'chapter-url':
                $value = sanitize_string($value);
                foreach (ALL_URL_TYPES as $existing) {
                    if (str_i_same($value, $this->get($existing))) {
                        return false;
                    }
                }
                $chap = '';
                foreach (CHAPTER_ALIASES as $alias) {
                    $chap .= $this->get($alias);
                }
                if (preg_match('~\[\[.+\]\]~', $chap)) {
                    return false;
                } // Chapter is already wikilinked
                return $this->add($param_name, $value);

            case 'archive-url':
                if ($this->blank(['archive-url', 'archiveurl'])) {
                    $this->add($param_name, $value);
                    $this->tidy_parameter($param_name);
                    return true;
                }
                return false;

            case 'title-link':
                if ($this->blank([...TITLE_LINK_ALIASES, 'url'])) {
                    return $this->add($param_name, $value); // We do not sanitize this, since it is not new data
                }
                return false;

            case 'class':
                if ($this->blank($param_name) && mb_strpos($this->get('eprint') . $this->get('arxiv'), '/') === false) {
                    // Old eprints include class in the ID
                    if ($this->wikiname() === 'cite arxiv') {
                        // Only relevant for cite arxiv
                        return $this->add($param_name, sanitize_string($value));
                    }
                }
                return false;

            case 'doi':
                if (doi_is_bad($value)) {
                    return false;
                }
                if (preg_match(REGEXP_DOI, $value, $match)) {
                    if ($this->blank($param_name)) {
                        if ($this->wikiname() === 'cite arxiv') {
                            if (doi_works($value)) {
                                $this->change_name_to('cite journal');
                            } else {
                                return false; // We get bad ones
                            }
                        }
                        $this->add('doi', $match[0]);
                        return true;
                    } elseif (!str_i_same($this->get('doi'), $match[0]) && !$this->blank(DOI_BROKEN_ALIASES) && doi_works($match[0])) {
                        report_action("Replacing non-working DOI with a working one");
                        $this->set('doi', $match[0]);
                        $this->tidy_parameter('doi');
                        return true;
                    } elseif (!str_i_same($this->get('doi'), $match[0]) && mb_strpos($this->get('doi'), '10.13140/') === 0 && doi_works($match[0])) {
                        report_action("Replacing ResearchGate DOI with publisher's");
                        $this->set('doi', $match[0]);
                        $this->tidy_parameter('doi');
                        return true;
                    }
                }
                return false;

            case 'doi-access':
                if ($this->blank('doi') || $this->has($param_name)) {
                    return false;
                }
                $this->add($param_name, $value);
                if ($value === 'free' && doi_works($this->get('doi'))) {
                    if (preg_match('~^https?://(?:dx\.|)doi\.org~', $this->get('url'))) {
                        $this->forget('url');
                    }
                    $this->this_array = [$this];
                    drop_urls_that_match_dois($this->this_array);
                    $this->this_array = [];
                }
                return true;

            case 's2cid':
                if (in_array($value, ['11008564'], true)) {
                    return false;
                } // known bad values
                if ($this->blank(['s2cid', 'S2CID'])) {
                    $this->add($param_name, $value);
                    get_doi_from_semanticscholar($this);
                    return true;
                }
                return false;

            case 'eprint':
            case 'arxiv':
                if ($this->blank(ARXIV_ALIASES)) {
                    $this->add($param_name, $value);
                    return true;
                }
                return false;

            case 'doi-broken-date':
                if ($this->blank('jstor')) {
                    check_doi_for_jstor($this->get('doi'), $this);
                    if ($this->has('jstor')) {
                        $this->quietly_forget('doi');
                        return true;
                    }
                }
                if ($this->has('jstor') && mb_strpos($this->get('doi'), '10.1355/') === 0) {
                    $this->quietly_forget('doi');
                    return true;
                }
                // Forget any others that are blank
                foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
                    if ($this->blank($alias)) {
                        $this->forget($alias);
                    }
                }
                // Switch any that are set to doi-broken-date
                if ($this->blank('doi-broken-date')) {
                    foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
                        $this->rename($alias, 'doi-broken-date');
                    }
                } else {
                    foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
                        $this->forget($alias);
                    }
                }
                $time = strtotime($value);
                if ($time) { // paranoid
                    $value = self::localize_dates($time);
                }
                if ($this->blank(DOI_BROKEN_ALIASES)) {
                    if (!isset(NULL_DOI_LIST[$this->get('doi')])) {
                        bot_debug_log("Marking bad HDL: " . $this->get('doi'));
                    }
                    return $this->add($param_name, $value);
                }
                $existing = strtotime($this->get('doi-broken-date'));
                $the_new = strtotime($value);
                if ($existing === false || $existing + 7 * 2592000 < $the_new || 2592000 * 7 + $the_new < $existing) {
                    // Seven months of difference
                    return $this->add($param_name, $value);
                }
                // TODO : re-checked & change this back to 6 months ago everyone in a while to compact all DOIs
                $last_day = strtotime("23:59:59 1 July 2025");
                $check_date = $last_day - 126000;
                // @codeCoverageIgnoreStart
                if ($the_new > $last_day && $existing < $check_date) {
                    $last_day = self::localize_dates($last_day);
                    return $this->add($param_name, $last_day);
                }
                // @codeCoverageIgnoreEnd
                return false;

            case 'pmid':
                if ($value === "0") {
                    return false;
                } // Got PMID of zero once from pubmed
                if ($this->blank($param_name)) {
                    if ($this->wikiname() === 'cite web') {
                        $this->change_name_to('cite journal');
                    }
                    $this->add($param_name, sanitize_string($value));
                    $this->expand_by_pubmed($this->blank('pmc') || $this->blank('doi')); //Force = true if missing DOI or PMC
                    get_doi_from_crossref($this);
                    return true;
                }
                return false;

            case 'pmc':
                if ($value === "PMC0" || $value === "0") {
                    return false;
                } // Got PMID of zero once from pubmed
                if ($this->blank($param_name)) {
                    $this->add($param_name, sanitize_string($value));
                    if ($this->blank('pmid')) {
                        $this->expand_by_pubmed(true); // Almost always can get a PMID (it is rare not too)
                    }
                    return true;
                }
                return false;

            case 'bibcode_nosearch': // Avoid recursive loop
            case 'bibcode':
                if (mb_stripos($value, 'arxiv') === false && mb_stripos($value, 'tmp') === false && (mb_stripos($this->get('bibcode'), 'arxiv') !== false || mb_stripos($this->get('bibcode'), 'tmp') !== false) && mb_strlen(mb_trim($value)) > 16) {
                    $this->quietly_forget('bibcode'); // Upgrade bad bibcode
                }
                if ($this->blank('bibcode')) {
                    $bibcode_pad = 19 - mb_strlen($value);
                    if ($bibcode_pad > 0) {
                        // Paranoid, don't want a negative value, if bibcodes get longer
                        $value .= str_repeat(".", $bibcode_pad); // Add back on trailing periods
                    }
                    if (mb_stripos($value, 'arxiv') !== false) {
                        if ($this->has('arxiv') || $this->has('eprint')) {
                            return false;
                        }
                        $low_quality = true;
                    } else {
                        $low_quality = false;
                    }
                    $this->add('bibcode', $value);
                    if ($param_name === 'bibcode') {
                        $bib_array = [$value];
                        $this->this_array = [$this];
                        query_bibcode_api($bib_array, $this->this_array);
                        $this->this_array = [];
                    }
                    if ($low_quality) {
                        $this->quietly_forget('bibcode');
                    }
                    return true;
                }
                return false;

            case 'isbn':
                if (in_array($value, BAD_ISBN, true)) {
                    return false;
                }
                $year = $this->year_int();
                $today = intval(date("Y")) + 2; // padding
                if ($year !== 0) {
                    if ($year < 1965 || $year > $today) {
                        return false; // Avoid bogus ISBN from pre-preprints and google
                    }
                }
                if ($this->blank($param_name)) {
                    $value = $this->isbn10Toisbn13($value, false);
                    if (mb_strlen($value) === 13 && mb_substr($value, 0, 6) === '978019') {
                        // Oxford
                        $value = '978-0-19-' . mb_substr($value, 6, 6) . '-' . mb_substr($value, 12, 1);
                    }
                    if (mb_strlen($value) > 19) {
                        return false;
                    } // Two ISBNs
                    $value = addISBNdashes($value);
                    return $this->add($param_name, $value);
                }
                return false;

            case 'asin':
                if ($this->blank($param_name)) {
                    if ($this->has('isbn')) {
                        // Already have ISBN
                        report_inaction("Not adding ASIN: redundant to existing ISBN.");
                        return false;
                    } elseif (preg_match("~^\d~", $value) && mb_substr($value, 0, 2) !== '63') {
                        // 630 and 631 ones are not ISBNs, so block all of 63*
                        $possible_isbn = sanitize_string($value);
                        $possible_isbn13 = $this->isbn10Toisbn13($possible_isbn, true);
                        if ($possible_isbn === $possible_isbn13) {
                            return $this->add('asin', $possible_isbn); // Something went wrong, add as ASIN
                        } else {
                            return $this->add('isbn', $this->isbn10Toisbn13($possible_isbn, false));
                        }
                    } else {
                        // NOT ISBN
                        return $this->add($param_name, sanitize_string($value));
                    }
                }
                return false;

            case 'publisher':
                if ($this->had_initial_publisher) {
                    return false;
                }
                if (mb_strlen(preg_replace('~[\.\s\d\,]~', '', $value)) < 5) {
                    return false;
                } // too few characters
                if (mb_stripos($value, 'Springer') === 0) {
                    $value = 'Springer';
                } // they add locations often
                if (mb_stripos($value, '[s.n.]') !== false) {
                    return false;
                }
                if (preg_match('~^\[([^\|\[\]]*)\]$~', $value, $match)) {
                    $value = $match[1];
                } // usually zotero problem of [data]
                if (preg_match('~^(.+), \d{4}$~', $value, $match)) {
                    $value = $match[1];
                } // remove years from zotero

                if (mb_strpos(mb_strtolower($value), 'london') !== false || // Common junk from archive.org
                mb_strpos(mb_strtolower($value), 'edinburg') !== false ||
                mb_strpos(mb_strtolower($value), 'privately printed') !== false ||
                str_equivalent($this->get('location'), $value) ||
                mb_strpos(mb_strtolower($value), 'impressum') !== false ||
                mb_strpos(mb_strtolower($value), ':') !== false ||
                mb_strpos(mb_strtolower($value), '[etc.]') !== false) { // biodiversitylibrary.org
                    return false;
                }
                if ($this->wikiname() !== 'cite book' && !$this->blank(WORK_ALIASES)) {
                    return false;
                } // Do not add if work is set, unless explicitly a book

                $value = truncate_publisher($value);
                if (in_array(mb_trim(mb_strtolower($value), " \.\,\[\]\:\;\t\n\r\0\x0B"), BAD_PUBLISHERS, true)) {
                    return false;
                }
                if ($this->has('via') && str_equivalent($this->get('via'), $value)) {
                    $this->rename('via', $param_name);
                }
                if (mb_strtoupper($value) === $value || mb_strtolower($value) === $value) {
                    $value = title_capitalization($value, true);
                }
                if ($value === 'Oxford University PressOxford' || $value === 'OUP Oxford') {
                    $value = 'Oxford University Press';
                }
                if ($this->blank($param_name)) {
                    return $this->add($param_name, $value);
                }
                return false;

            case 'type':
                if (
                    $this->blank($param_name) &&
                    !in_array(mb_strtolower($value), ['text', 'data set'], true) &&
                    mb_strlen($value) === mb_strlen($value) &&
                    mb_strpos($value, 'purl.org') === false &&
                    mb_strpos($value, 'dcmitype') === false &&
                    mb_strpos($value, 'http') === false
                ) {
                    return $this->add($param_name, sanitize_string($value));
                }
                return false;

            case 'agency':
                if ($this->blank($param_name)) {
                    return $this->add($param_name, sanitize_string($value));
                }
                return false;

            case 'location':
                if ($this->had_initial_publisher) {
                    return false;
                }
                if ($this->blank($param_name)) {
                    return $this->add($param_name, sanitize_string($value));
                }
                return false;

            case 'jstor':
                if ($value === '3511692') {
                    return false;
                } // common review
                if ($this->blank($param_name)) {
                    return $this->add($param_name, sanitize_string($value));
                }
                return false;

            case 'zbl':
            case 'oclc':
            case 'mr':
            case 'lccn':
            case 'hdl':
            case 'ssrn':
            case 'ol':
            case 'jfm':
            case 'osti':
            case 'biorxiv':
            case 'medrxiv':
            case 'citeseerx':
            case 'via':
                if ($this->blank($param_name)) {
                    return $this->add($param_name, sanitize_string($value));
                }
                return false;

            case (bool) preg_match('~author(?:\d{1,}|)-link~', $param_name):
                /** @psalm-suppress NoValue */
                if ($this->blank($param_name)) {
                    return $this->add($param_name, sanitize_string($value));
                }
                return false;

            case 'id':
                if ($this->blank($param_name)) {
                    return $this->add($param_name, $value); // Do NOT Sanitize.  It includes templates often
                }
                return false;

            case 'edition':
                if ($this->blank($param_name)) {
                    $this->add($param_name, $value);
                    return true;
                }
                return false;

            case 'work':
            case 'encyclopedia':
                $value = html_entity_decode($value, ENT_COMPAT | ENT_HTML401, "UTF-8");
                $value = html_entity_decode($value, ENT_COMPAT | ENT_HTML401, "UTF-8");
                $value = html_entity_decode($value, ENT_COMPAT | ENT_HTML401, "UTF-8");
                if (mb_substr($value, -1) === '.') {
                    $value = sanitize_string($value) . '.';
                } else {
                    $value = sanitize_string($value);
                }
                if ($this->blank(WORK_ALIASES)) {
                    return $this->add($param_name, $value);
                }
                return false;

            case 'website':
                if ($this->blank(WORK_ALIASES)) {
                    return $this->add($param_name, $value); // Do NOT Sanitize
                }
                return false;

            case 'article-number':
                if ($this->blank($param_name)) {
                    if ($this->get('pages') === $value) {
                        $this->rename('pages', $param_name);
                        return true;
                    }
                    if ($this->get('page') === $value) {
                        $this->rename('page', $param_name);
                        return true;
                    }
                    if ($this->get('at') === $value) {
                        $this->rename('at', $param_name);
                        return true;
                    }
                    return $this->add($param_name, $value); // Do NOT Sanitize
                }
                return false;

            default:
                // We want to make sure we understand what we are adding - sometimes we find odd floating parameters
                // @codeCoverageIgnoreStart
                report_minor_error('Unexpected parameter: ' . echoable($param_name) . ' trying to be set to ' . echoable($value));
                // if ($this->blank($param_name)) {
                //  return $this->add($param_name, sanitize_string($value));
                // }
                return false;
            // @codeCoverageIgnoreEnd
        }
    }

    private function is_url_in_non_url_parameter(string $param_name, string $value): bool {
        // Define parameters that are allowed to contain URLs
        $url_holding_params = [
            'url', 'archive-url', 'archiveurl', 'article-url',
            'chapter-url', 'chapterurl', 'conference-url', 'conferenceurl',
            'contribution-url', 'contributionurl', 'entry-url', 'entryurl',
            'event-url', 'eventurl', 'lay-url', 'layurl',
            'map-url', 'mapurl', 'section-url', 'sectionurl',
            'transcript-url', 'transcripturl'
        ];

        $insource_locator_params = [
            'page', 'pages', 'p', 'pp', 'at', 'quote-page', 'quote-pages'
        ];

        // Check if value looks like a URL and parameter doesn't allow URLs
        if (!in_array($param_name, array_merge($url_holding_params, $insource_locator_params), true)) {
            // Remove XML/HTML tags and their attributes to avoid false positives from xmlns attributes
            $value_without_tags = preg_replace('~<[^>]+>~', '', $value);

            if (preg_match('~^https?://~i', $value_without_tags) ||
                preg_match('~://~', $value_without_tags) ||
                preg_match('~^www\.~i', $value_without_tags)) {
                return true;
            }
        }
        return false;
    }

    public function validate_and_add(string $author_param, string $author, string $forename, string $check_against, bool $add_even_if_existing): void {
        if (!$add_even_if_existing && ($this->had_initial_author() || $this->had_initial_editor())) {
            return;
        } // Zotero does not know difference between editors and authors often
        if (
            in_array(mb_strtolower($author), BAD_AUTHORS, true) === false &&
            in_array(mb_strtolower($forename), BAD_AUTHORS, true) === false &&
            in_array(mb_strtolower($forename . ' ' . $author), BAD_AUTHORS, true) === false &&
            author_is_human($author) &&
            author_is_human($forename)
        ) {
            while (
            preg_match('~^(.*)\s[\S]+@~', ' ' . $author, $match) || // Remove emails
            preg_match('~^(.*)\s+@~', ' ' . $author, $match)
            ) {
                // Remove twitter handles
                $author = mb_trim($match[1]);
            }
            while (
            preg_match('~^(.*)\s[\S]+@~', ' ' . $forename, $match) || // Remove emails
            preg_match('~^(.*)\s+@~', ' ' . $forename, $match)
            ) {
                // Remove twitter handles
                $forename = mb_trim($match[1]);
            }
            while (preg_match('~^(?:rabbi|prof\.|doctor|professor|dr\.)\s([\s\S]+)$~i', $forename, $match)) {
                // Remove titles
                $forename = mb_trim($match[1]);
            }
            while (preg_match('~^(?:rabbi|prof\.|doctor|professor|dr\.)\s([\s\S]+)$~i', $author, $match)) {
                // Remove titles
                $author = mb_trim($match[1]);
            }
            if (mb_trim($author) === '') {
                $author = mb_trim($forename);
                $forename = '';
            }
            $author_parts = explode(" ", $author);
            $author_ending = end($author_parts);
            $name_as_publisher = mb_trim($forename . ' ' . $author);
            if (in_array(mb_strtolower($author_ending), PUBLISHER_ENDINGS, true) || mb_stripos($check_against, $name_as_publisher) !== false) {
                $this->add_if_new('publisher', $name_as_publisher);
            } else {
                $this->add_if_new($author_param, format_author($author . ($forename ? ", {$forename}" : '')));
            }
        }
    }

    public function mark_inactive_doi(): void {
        $doi = $this->get_without_comments_and_placeholders('doi');
        if (doi_works($doi) === false) {
            // null which would cast to false means we don't know, so use ===
            $this->add_if_new('doi-broken-date', date('Y-m-d'));
        }
    }

    /** This is also called when adding a URL with add_if_new, in which case it looks for a parameter before adding the url. */
    public function get_identifiers_from_url(?string $url_sent = null): bool {
        return find_indentifiers_in_urls($this, $url_sent);
    }

    private function get_doi_from_text(): void {
        set_time_limit(120);
        if ($this->blank('doi') && preg_match('~10\.\d{4}/[^&\s\|\}\{]*~', urldecode($this->parsed_text()), $match)) {
            if (mb_stripos($this->rawtext, 'oxforddnb.com') !== false) {
                return;
            } // generally bad, and not helpful
            if (mb_strpos($this->rawtext, '10.1093') !== false) {
                return;
            } // generally bad, and not helpful
            // Search the entire citation text for anything in a DOI format.
            // This is quite a broad match, so we need to ensure that no baggage has been tagged on to the end of the URL.
            $doi = preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]);
            $doi = extract_doi($doi)[1];
            if ($doi === '') {
                return;
            } // Found nothing
            if ($this->has('quote') && mb_strpos($this->get('quote'), $doi) !== false) {
                return;
            }
            if (doi_works($doi)) {
                $this->add_if_new('doi', $doi);
            }
        }
    }

    public function expand_by_pubmed(bool $force = false): void {
        if (!$force && !$this->incomplete()) {
            return;
        }
        $this->this_array = [$this];
        $pmid = $this->get('pmid');
        $pmc = $this->get('pmc');
        if ($pmid) {
            report_action('Checking ' . pubmed_link('pmid', $pmid) . ' for more details');
            query_pmid_api([$pmid], $this->this_array);
        } elseif ($pmc) {
            report_action('Checking ' . pubmed_link('pmc', $pmc) . ' for more details');
            query_pmc_api([$pmc], $this->this_array);
        }
        $this->this_array = [];
    }

    /** parameter processing */
    private function parameter_names_to_lowercase(): void {
        if (empty($this->param)) {
            return;
        }
        $keys = array_keys($this->param);
        foreach ($keys as $the_key) {
            if (mb_stripos($this->param[$the_key]->param, 'http') === false && mb_strlen($this->param[$the_key]->param) < 30) {
                $this->param[$the_key]->param = str_replace('duplicate_', 'DUPLICATE_', mb_strtolower($this->param[$the_key]->param));
            }
        }
    }

    private function use_unnamed_params(): void {
        if (empty($this->param)) {
            return;
        }

        $param_occurrences = [];
        $duplicated_parameters = [];
        $duplicate_identical = [];

        foreach ($this->param as $pointer => $par) {
            if ($par->param && isset($param_occurrences[$par->param])) {
                $duplicate_pos = $param_occurrences[$par->param];
                if ($par->val === '') {
                    $par->val = $this->param[$duplicate_pos]->val;
                } elseif ($this->param[$duplicate_pos]->val === '') {
                    $this->param[$duplicate_pos]->val = $par->val;
                }
                array_unshift($duplicated_parameters, $duplicate_pos);
                array_unshift($duplicate_identical, mb_strtolower(mb_trim($par->val)) === mb_strtolower(mb_trim($this->param[$duplicate_pos]->val))); // Drop duplicates that differ only by case
            }
            $param_occurrences[$par->param] = $pointer;
        }

        $n_dup_params = count($duplicated_parameters);

        for ($i = 0; $i < $n_dup_params; $i++) {
            $the_dup = $duplicated_parameters[$i];
            /** @psalm-suppress InvalidArrayOffset */
            $is_same = $duplicate_identical[$i];
            if ($is_same) {
                report_forget("Deleting identical duplicate of parameter: " . echoable($this->param[$the_dup]->param));
                unset($this->param[$the_dup]);
            } else {
                $this->param[$the_dup]->param = str_ireplace('DUPLICATE_DUPLICATE_', 'DUPLICATE_', 'DUPLICATE_' . $this->param[$the_dup]->param);
                report_modification("Marking duplicate parameter: " . echoable($this->param[$the_dup]->param));
            }
        }

        if ($this->blank('url')) {
            $need_one = true;
            foreach ($this->param as $param_key => $p) {
                if ($need_one && !empty($p->param)) {
                    if (preg_match('~^\s*(https?://|www\.)\S+~', $p->param)) {
                        // URL ending ~ xxx.com/?para=val
                        $val = $p->val;
                        $param = $p->param;
                        $this->param[$param_key]->val = $param . '=' . $val;
                        $this->param[$param_key]->param = 'url';
                        $this->param[$param_key]->eq = ' = '; // Upgrade it to nicely spread out
                        $need_one = false;
                        if (mb_stripos($param . $val, 'books.google.') !== false) {
                               $this->change_name_to('cite book');
                        }
                    }
                }
            }
        }
        $blank_count = 0;
        foreach ($this->param as &$p) {
            // Protect them from being overwritten
            if (empty($p->param)) {
                $p->param = 'CITATION_BOT_PLACEHOLDER_EMPTY_' . (string) $blank_count;
                $blank_count++;
                $p->eq = ' = ';
            }
        }
        unset($p); // Destroy pointer to be safe
        foreach ($this->param as &$p) {
            if (mb_stripos($p->param, 'CITATION_BOT_PLACEHOLDER_EMPTY') === false) {
                continue;
            }
            $dat = $p->val;
            $endnote_test = explode("\n%", "\n" . $dat);
            if (isset($endnote_test[1])) {
                $endnote_authors = 0;
                foreach ($endnote_test as $endnote_line) {
                    $endnote_linetype = mb_substr($endnote_line, 0, 1);
                    $endnote_datum = mb_trim((string) mb_substr($endnote_line, 2)); // Cast to string in case of false
                    switch ($endnote_linetype) {
                        case "A":
                            ++$endnote_authors;
                            $this->add_if_new('author' . (string) $endnote_authors, format_author($endnote_datum));
                            $dat = mb_trim(str_replace("\n%" . $endnote_line, "", "\n" . $dat));
                            $endnote_parameter = false;
                            break;
                        case "D":
                            $endnote_parameter = "date";
                            break;
                        case "I":
                            $endnote_parameter = "publisher";
                            break;
                        case "C":
                            $endnote_parameter = "location";
                            break;
                        case "J":
                            $endnote_parameter = "journal";
                            break;
                        case "N":
                                     $endnote_parameter = "issue";
                            break;
                        case "P":
                                  $endnote_parameter = "pages";
                            break;
                        case "T":
                               $endnote_parameter = "title";
                            break;
                        case "U":
                            $endnote_parameter = "url";
                            break;
                        case "V":
                            $endnote_parameter = "volume";
                            break;
                        case "@": // ISSN / ISBN
                            if (preg_match("~@\s*([\d\-]{9,}[\dxX])~", $endnote_line, $matches)) {
                                $endnote_datum = $matches[1];
                                $endnote_parameter = "isbn";
                            } elseif (preg_match("~@\s*(\d{4}\-?\d{3}[\dxX])~", $endnote_line, $matches)) {
                                $endnote_datum = $matches[1];
                                $endnote_parameter = "issn";
                            } else {
                                $endnote_parameter = false;
                            }
                            break;
                        case "R": // Resource identifier... *may* be DOI but probably isn't always.
                            $matches = extract_doi($endnote_datum)[1];
                            if ($matches !== '') {
                                $endnote_datum = $matches;
                                $endnote_parameter = 'doi';
                            } else {
                                $endnote_parameter = false;
                            }
                            break;
                        case "8": // Date
                        case "0": // Citation type
                        case "X": // Abstract
                        case "M": // Object identifier
                            $dat = mb_trim(str_replace("\n%" . $endnote_line, "", "\n" . $dat));
                            $endnote_parameter = false;
                            break;
                        default:
                            $endnote_parameter = false;
                    }
                    if ($endnote_parameter) {
                        $this->add_if_new($endnote_parameter, $endnote_datum);
                        $dat = mb_trim(str_replace("\n%" . $endnote_line, "", "\n" . $dat));
                    }
                }
            }

            if (preg_match("~^TY\s+-\s+[A-Z]+~", $dat)) {
                // RIS formatted data:
                expand_by_RIS($this, $dat, true);
            }

            $doi = extract_doi($dat);
            if ($doi[1] !== '') {
                $this->add_if_new('doi', $doi[1]);
                $this->change_name_to('cite journal');
                $dat = str_replace($doi[0], '', $dat);
            }

            if (preg_match('~^(https?://|www\.)\S+~', $dat, $match)) {
                // Takes priority over more tentative matches
                report_add("Found URL floating in template; setting url");
                $url = $match[0];
                if ($this->blank('url')) {
                    $this->add_if_new('url', $url);
                } elseif ($this->blank(['archive-url', 'archiveurl']) && mb_stripos($url, 'archive') !== false) {
                    $this->add_if_new('archive-url', $url);
                }
                $dat = str_replace($url, '', $dat);
            }

            $shortest = -1;
            $test_dat = '';
            $shortish = -1;
            $comp = '';
            $closest = '';
            $parameter_list = [];
            foreach (PARAMETER_LIST as $parameter) {
                if (mb_strpos($parameter, '#') === false) {
                    $parameter_list[] = $parameter;
                } else {
                    for ($i = 1; $i < 99; $i++) {
                        $parameter_list[] = str_replace('#', (string) $i, $parameter);
                    }
                }
            }
            $parameter_list = array_reverse($parameter_list); // Longer things first

            foreach ($parameter_list as $parameter) {
                /** @phpstan-ignore argument.invalidPregQuote */
                if (mb_strpos($parameter, '#') === false && $parameter === mb_strtolower($parameter) && preg_match('~^(' . preg_quote($parameter) . '(?: -|:| )\s*)~iu', $dat, $match)) {
                    // Avoid adding "URL" instead of "url"
                    $parameter_value = mb_trim(mb_substr($dat, mb_strlen($match[1])));
                    report_add("Found " . echoable($parameter) . " floating around in template; converted to parameter");
                    $this->add_if_new($parameter, $parameter_value);
                    $numSpaces = preg_match_all('~[\s]+~', $parameter_value);
                    if ($numSpaces < 4) {
                        $dat = '';
                        $p->val = '';
                        break;
                    }
                }
                $para_len = mb_strlen($parameter);
                if ($para_len < 3) {
                    continue;
                } // minimum length to avoid false positives
                $test_dat = preg_replace("~\d~", "_$0", preg_replace("~[ -+].*$~", "", mb_substr(mb_strtolower($dat), 0, $para_len)));
                if (preg_match("~\d~", $parameter)) {
                    $lev = (float) levenshtein($test_dat, preg_replace("~\d~", "_$0", $parameter));
                } else {
                    $lev = (float) levenshtein($test_dat, $parameter);
                }
                if ($lev === 0.0) {
                    $closest = $parameter;
                    $shortest = 0.0;
                    break;
                } else {
                    $closest = '';
                }
                // Strict inequality as we want to favor the longest match possible
                if ($lev < $shortest || $shortest < 0) {
                    $comp = $closest;
                    $closest = $parameter;
                    $shortish = $shortest;
                    $shortest = $lev;
                } elseif ($lev < $shortish) {
                    // Keep track of the second shortest result, to ensure that our chosen parameter is an out and out winner
                    $shortish = $lev;
                    $comp = $parameter;
                }
            }
            // Deal with # values
            if (preg_match('~\d+~', $dat, $match)) {
                $closest = str_replace('#', $match[0], $closest);
                $comp = str_replace('#', $match[0], $comp);
            } else {
                $closest = str_replace('#', "", $closest);
                $comp = str_replace('#', "", $comp);
            }
            if (
                $shortest < 3 &&
                mb_strlen($test_dat) > 0 &&
                (float) similar_text($closest, $test_dat) / (float) mb_strlen($test_dat) > 0.4 &&
                ((float) $shortest + 1.0 < $shortish || // No close competitor
                mb_strlen($closest) > mb_strlen($comp))
            ) {
                // remove leading spaces or hyphens (which may have been typoed for an equals)
                if (preg_match("~^[ \\-+]*(.+)~", (string) mb_substr($dat, mb_strlen($closest)), $match)) {
                    // Cast false to string
                    $this->add_if_new($closest, $match[1] /* . " [$shortest / $comp = $shortish]"*/);
                    $replace_pos = mb_strrpos($dat, $match[1]) + mb_strlen($match[1]);
                    $dat = mb_trim(mb_substr($dat, $replace_pos));
                }
            } elseif (preg_match("~(?<!\d)(\d{10})(?!\d)~", str_replace([" ", "-"], "", $dat), $match)) {
                $the_isbn = mb_str_split($match[1]);
                preg_match(
                '~' .
                $the_isbn[0] .
                '[ -]?' .
                $the_isbn[1] .
                '[ -]?' .
                $the_isbn[2] .
                '[ -]?' .
                $the_isbn[3] .
                '[ -]?' .
                $the_isbn[4] .
                '[ -]?' .
                $the_isbn[5] .
                '[ -]?' .
                $the_isbn[6] .
                '[ -]?' .
                $the_isbn[7] .
                '[ -]?' .
                $the_isbn[8] .
                '[ -]?' .
                $the_isbn[9] .
                '~',
                $dat,
                $match
                ); // Crazy to deal with dashes and spaces
                $this->add_if_new('isbn', $match[0]);
                $dat = mb_trim(str_replace($match[0], '', $dat));
            } elseif (preg_match("~(?<!\d)(\d{13})(?!\d)~", str_replace([" ", "-"], "", $dat), $match)) {
                $the_isbn = mb_str_split($match[1]);
                preg_match(
                '~' .
                $the_isbn[0] .
                '[ -]?' .
                $the_isbn[1] .
                '[ -]?' .
                $the_isbn[2] .
                '[ -]?' .
                $the_isbn[3] .
                '[ -]?' .
                $the_isbn[4] .
                '[ -]?' .
                $the_isbn[5] .
                '[ -]?' .
                $the_isbn[6] .
                '[ -]?' .
                $the_isbn[7] .
                '[ -]?' .
                $the_isbn[8] .
                '[ -]?' .
                $the_isbn[9] .
                '[ -]?' .
                $the_isbn[10] .
                '[ -]?' .
                $the_isbn[11] .
                '[ -]?' .
                $the_isbn[12] .
                '~',
                $dat,
                $match
                ); // Crazy to deal with dashes and spaces
                $this->add_if_new('isbn', $match[0]);
                $dat = mb_trim(str_replace($match[0], '', $dat));
            }
            if (preg_match("~^access date[ :]+(.+)$~i", $dat, $match)) {
                if ($this->add_if_new('access-date', $match[1])) {
                    $dat = mb_trim(str_replace($match[0], '', $dat));
                }
            }
            $p->val = mb_trim($dat, " \t\0\x0B");
        }
        unset($p); // Destroy pointer to be safe
        foreach ($this->param as $param_key => &$p) {
            if (mb_stripos($p->param, 'CITATION_BOT_PLACEHOLDER_EMPTY') === false) {
                continue;
            }
            $p->param = '';
            $p->eq = '';
            if ($p->val === '') {
                unset($this->param[$param_key]);
            }
        }
        unset($p); // Destroy pointer to be safe
    }

    private function id_to_param(): void {
        set_time_limit(120);
        $id = $this->get('id');
        if (mb_trim($id)) {
            report_action("Trying to convert ID parameter to parameterized identifiers.");
        } else {
            return;
        }
        if ($id === "<small></small>" || $id === "<small> </small>" || $id === ".") {
            $this->forget('id');
            return;
        }
        while (preg_match("~\b(PMID|DOI|ISBN|ISSN|ARXIV|LCCN|CiteSeerX|s2cid|PMC)[\s:]*(\d[\d\s\-][^\s\}\{\|,;]*)(?:[,;] )?~iu", $id, $match)) {
            $the_type = mb_strtolower($match[1]);
            $the_data = $match[2];
            $the_all = $match[0];
            if ($the_type !== 'doi' && preg_match("~^([^\]\}\{\s\,\;\:\|\<\>]+)$~", $the_data, $matches)) {
                $the_data = $matches[1];
            }
            $this->add_if_new($the_type, $the_data);
            $id = str_replace($the_all, '', $id);
        }
        if (preg_match_all('~' . sprintf(self::PLACEHOLDER_TEXT, '(\d+)') . '~', $id, $matches)) {
            $num_placeholders = count($matches[1]);
            for ($i = 0; $i < $num_placeholders; $i++) {
                $subtemplate = self::$all_templates[$matches[1][$i]];
                $subtemplate_name = $subtemplate->wikiname();
                switch ($subtemplate_name) {
                    case "arxiv":
                        if ($subtemplate->get('id')) {
                               $archive_parameter = mb_trim($subtemplate->get('archive') ? $subtemplate->get('archive') . '/' : '');
                               $this->add_if_new('arxiv', $archive_parameter . $subtemplate->get('id'));
                        } elseif ($subtemplate->has_multiple_params()) {
                            $this->add_if_new('arxiv', mb_trim($subtemplate->param_value(0)) . "/" . mb_trim($subtemplate->param_value(1)));
                        } else {
                            $this->add_if_new('arxiv', $subtemplate->param_value(0));
                        }
                        $id = str_replace($matches[0][$i], '', $id);
                        break;
                    case "asin":
                    case "oclc":
                    case "bibcode":
                    case "doi":
                    case "isbn":
                    case "issn":
                    case "jfm":
                    case "jstor":
                    case "lccn":
                    case "mr":
                    case "osti":
                    case "pmid":
                    case "pmc":
                    case "ssrn":
                    case "citeseerx":
                    case "s2cid":
                    case "hdl":
                    case "zbl":
                    case "ol":
                    case "lcc":
                    case "ismn":
                    case "biorxiv":
                    case "medrxiv":
                        // Specific checks for particular templates:
                        if ($subtemplate_name === 'asin' && $subtemplate->has('country')) {
                            report_info("{{ASIN}} country parameter not supported: cannot convert.");
                            break;
                        }
                        if ($subtemplate_name === 'ol' && $subtemplate->has('author')) {
                            report_info("{{OL}} author parameter not supported: cannot convert.");
                            break;
                        }
                        if ($subtemplate_name === 'ol' && mb_stripos($subtemplate->parsed_text(), "ia:") !== false) {
                            report_info("{{OL}} ia: parameter not supported: cannot convert.");
                            break;
                        }
                        if (($subtemplate_name === 'jstor' && $subtemplate->has('sici')) || $subtemplate->has('issn')) {
                            report_info("{{JSTOR}} named parameters are not supported: cannot convert.");
                            break;
                        }
                        if ($subtemplate_name === 'oclc' && $subtemplate->has_multiple_params()) {
                            report_info("{{OCLC}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                            break;
                        }
                        if ($subtemplate_name === 'issn' && $subtemplate->has_multiple_params()) {
                            report_info("{{ISSN}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                            break;
                        }
                        if ($subtemplate_name === 'ismn' && $subtemplate->has_multiple_params()) {
                            report_info("{{ISMN}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                            break;
                        }
                        if ($subtemplate_name === 'biorxiv' && $subtemplate->has_multiple_params()) {
                            report_info("{{biorxiv}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                            break;
                        }
                        if ($subtemplate_name === 'medrxiv' && $subtemplate->has_multiple_params()) {
                            report_info("{{medrxiv}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                            break;
                        }
                        if ($subtemplate_name === 'lcc') {
                            if (preg_match('~^[\d\-]+$~', $subtemplate->param_value(0))) {
                                report_minor_error("Possible bad LCC template (did they mean LCCN) : " . echoable($subtemplate->param_value(0))); // @codeCoverageIgnore
                            }
                            break;
                        }

                        // All tests okay; move identifier to suitable parameter
                        $subtemplate_identifier = $subtemplate->has('id') ? $subtemplate->get('id') : $subtemplate->param_value(0);

                        $did_it = $this->add_if_new($subtemplate_name, $subtemplate_identifier);
                        if ($did_it) {
                            $id = str_replace($matches[0][$i], '', $id);
                        }
                        break;

                    // TODO: Check if these have been added https://en.wikipedia.org/wiki/Template:Cite_journal
                    case "circe":
                    case "ill":
                    case "cs":
                    case "proquest":
                    case "inist":
                    case "gale":
                    case "eric":
                    case "naid":
                    case "dtic":
                    case "project muse":
                    case "pii":
                    case "ebscohost":
                    case "libris":
                    case "selibr":
                    case "cobiss":
                    case "crosbi":
                    case "euclid":
                    case "federal register":
                    case "jpno":
                    case "lancaster university library":
                    case "listed invalid isbn":
                    case "ncbibook2":
                    case "ncj":
                    case "researchgatepub":
                    case "university of south wales pure":
                    case "usgs index id":
                    case "us patent":
                    case "us trademark":
                    case "zdb":
                    case "subscription required":
                    case "ncid":
                    case "wikileaks cable":
                    case "idp":
                    case "bhl page":
                    case "internet archive":
                    case "youtube":
                    case "nypl":
                    case "bnf":
                    case "dnb-idn":
                    case "nara catalog record":
                    case "urn":
                    case "viaf":
                    case "so-vid":
                    case "philpapers":
                    case "iccu":
                    case "hathitrust":
                    case "allmusic":
                    case "hal":
                    case "icd11":
                    case "coden":
                    case "blcat":
                    case "cobiss.bih":
                    case "cobiss.rs":
                    case "cobiss.sr":
                    case "harvtxt":
                    case "mathnet":
                    case "eissn":
                    case "ndljp":
                    case "orcid":
                    case "pq":
                    case "sudoc":
                    case "upc":
                    case "ceeol":
                    case "nps history library":
                    case "smaller":
                    case "zenodo":
                    case "!":
                    case "hathitrust catalog":
                    case "eccc":
                    case "ean":
                    case "ethos":
                    case "chmid":
                    case "factiva":
                    case "mesh":
                    case "dggs citation id":
                    case "harvp":
                    case "nla":
                    case "catkey":
                    case "hyphen":
                    case "mit libraries":
                    case "epa national catalog":
                    case "unt key":
                    case "eram":
                    case "regreq":
                    case "nobr":
                    case "subscription":
                    case "uspl":
                    case "small":
                    case "rism":
                    case "jan":
                    case "nbsp":
                    case "abbr":
                    case "closed access":
                    case "interp":
                    case "genbank":
                    case "better source needed":
                    case "free access":
                    case "required subscription":
                    case "fahrplan-ch":
                    case "incomplete short citation":
                    case "music":
                    case "bar-ads":
                    case "subscription or libraries":
                    case "gallica":
                    case "gnd":
                    case "ncbibook":
                    case "spaces":
                    case "ndash":
                    case "dggs":
                    case "self-published source":
                    case "nobreak":
                    case "university of twente pure":
                    case "mathscinet":
                    case "discogs master":
                    case "harv":
                    case "registration required":
                    case "snd":
                    case "hsdl":
                    case "academia.edu":
                    case "gbooks":
                    case "gburl": // TODO - should use
                    case "isbnt":
                    case "issn link":
                    case "project euclid":
                    case "circa":
                    case "ndlpid":
                    case "core output":
                    case "core work":
                    case "ark":
                    case "internet archive id":
                    case "lccn8": // Assume not normal template for a reason
                    case "google books": // Usually done for fancy formatting and because already has title-link/url
                    case "url": // Untrustable: used by bozos
                        break;
                    default:
                        report_minor_error("No match found for subtemplate type: " . echoable($subtemplate_name)); // @codeCoverageIgnore
                }
            }
        }
        if (mb_trim($id)) {
            $this->set('id', $id);
        } else {
            $this->forget('id');
        }
        if ($id === "<small></small>" || $id === "<small> </small>") {
            $this->forget('id');
            return;
        }
    }

    public function correct_param_mistakes(): void {
        // It will correct any that appear to be mistyped in minor templates
        if (empty($this->param)) {
            return;
        }
        $mistake_corrections = array_values(COMMON_MISTAKES);
        $mistake_keys = array_keys(COMMON_MISTAKES);

        foreach ($this->param as $p) {
            if (mb_strlen($p->param) > 0) {
                $mistake_id = array_search($p->param, $mistake_keys);
                if ($mistake_id) {
                    $new = $mistake_corrections[$mistake_id];
                    if ($this->blank($new)) {
                        $old = $p->param;
                        $p->param = $new;
                        report_modification('replaced ' . echoable($old) . ' with ' . echoable($new) . ' (common mistakes list)');
                    }
                    continue;
                }
            }
        }
        // Catch archive=url=http......
        foreach ($this->param as $p) {
            if (mb_substr_count($p->val, "=") === 1 && !in_array($p->param, PARAMETER_LIST, true)) {
                $param = $p->param;
                $value = $p->val;
                $equals = (int) mb_strpos($value, '=');
                $before = mb_trim(mb_substr($value, 0, $equals));
                $after = mb_trim(mb_substr($value, $equals + 1));
                $possible = $param . '-' . $before;
                if (in_array($possible, PARAMETER_LIST, true)) {
                    $p->param = $possible;
                    $p->val = $after;
                }
            }
        }
    }

    private function correct_param_spelling(): void {
        // check each parameter name against the list of accepted names (loaded in expand.php).
        // It will correct any that appear to be mistyped.
        set_time_limit(120);
        if (empty($this->param)) {
            return;
        }
        $parameter_list = PARAMETER_LIST;
        $parameter_dead = DEAD_PARAMETERS;
        $parameters_used = [];
        $mistake_corrections = array_values(COMMON_MISTAKES);
        $mistake_keys = array_keys(COMMON_MISTAKES);
        foreach ($this->param as $p) {
            $parameters_used[] = $p->param;
        }

        $parameter_list = array_diff($parameter_list, $mistake_keys); // This way it does not contain "URL", but only "url"
        $unused_parameters = array_diff($parameter_list, $parameters_used);

        foreach ($this->param as $p) {
            if (
                mb_strlen($p->param) > 0 &&
                !(in_array(preg_replace('~\d+~', '#', $p->param), $parameter_list, true) || in_array($p->param, $parameter_list, true)) && // Some parameters have actual numbers in them
                mb_stripos($p->param, 'CITATION_BOT') === false
            ) {
                if (mb_trim($p->val) === '') {
                    if (mb_stripos($p->param, 'DUPLICATE_') === 0) {
                        report_forget("Dropping empty left-over duplicate parameter " . echoable($p->param) . " ");
                    } else {
                        report_forget("Dropping empty unrecognized parameter " . echoable($p->param) . " ");
                    }
                    $this->quietly_forget($p->param);
                    continue;
                }

                if (mb_stripos($p->param, 'DUPLICATE_') === 0) {
                    report_modification("Left-over duplicate parameter " . echoable($p->param) . " ");
                    continue;
                } else {
                    report_modification("Unrecognized parameter " . echoable($p->param) . " ");
                }
                $mistake_id = array_search($p->param, $mistake_keys);
                if ($mistake_id) {
                    // Check for common mistakes.  This will over-ride anything found by levenshtein: important for "editor1link" !-> "editor-link" (though this example is no longer relevant as of 2017)
                    $p->param = $mistake_corrections[$mistake_id];
                    report_modification('replaced with ' . echoable($mistake_corrections[$mistake_id]) . ' (common mistakes list)');
                    continue;
                }

                $p->param = preg_replace('~author(\d+)-(la|fir)st~', "$2st$1", $p->param);
                $p->param = preg_replace('~surname\-?_?(\d+)~', "last$1", $p->param);
                $p->param = preg_replace('~(?:forename|initials?)\-?_?(\d+)~', "first$1", $p->param);
                $p->param = preg_replace('~[\r\n]+~u', ' ', $p->param); // Have to be unicode safe

                // Check the parameter list to find a likely replacement
                $shortest = -1.0;
                $closest = '';
                $comp = '';
                $shortish = -1.0;

                if (preg_match('~\d+~', $p->param, $match)) {
                    // Deal with # values
                    $param_number = $match[0];
                } else {
                    $param_number = '#';
                }
                foreach ($unused_parameters as $parameter) {
                    $parameter = str_replace('#', $param_number, $parameter);
                    if (mb_strpos($parameter, '#') !== false) {
                        continue;
                    } // Do no use # items unless we have a number
                    $lev = (float) levenshtein($p->param, $parameter, 5, 4, 6);
                    // Strict inequality as we want to favor the longest match possible
                    if ($lev < $shortest || $shortest < 0) {
                        $comp = $closest;
                        $closest = $parameter;
                        $shortish = $shortest;
                        $shortest = $lev;
                    } elseif ($lev < $shortish) { // Keep track of the second-shortest result, to ensure that our chosen parameter is an out and out winner
                        $shortish = $lev;
                        $comp = $parameter;
                    }
                }
                $str_len = mb_strlen($p->param);

                // Account for short words...
                if ($str_len < 4) {
                    $shortest *= (float) $str_len / (float) (similar_text($p->param, $closest) ? similar_text($p->param, $closest) : 0.001);
                    $shortish *= (float) $str_len / (float) (similar_text($p->param, $comp) ? similar_text($p->param, $comp) : 0.001);
                }

                if (in_array($p->param, $parameter_dead, true)) {
                    report_inline("Could not fix outdated " . echoable($p->param));
                } elseif ($shortest < 12 && $shortest < $shortish) {
                    bot_debug_log("levenshtein replaced " . $p->param . " with " . $closest);
                    $p->param = $closest;
                    report_inline("replaced with {$closest} (likelihood " . (string) round(24.0 - $shortest, 1) . "/24)"); // Scale arbitrarily re-based by adding 12 so users are more impressed by size of similarity
                } else {
                    $similarity = (float) similar_text($p->param, $closest) / (float) mb_strlen($p->param);
                    if ($similarity > 0.6) {
                        bot_debug_log("levenshtein replaced " . $p->param . " with " . $closest);
                        $p->param = $closest;
                        report_inline("replaced with {$closest} (similarity " . (string) round(24.0 * $similarity, 1) . "/24)"); // Scale arbitrarily re-based by multiplying by 2 so users are more impressed by size of similarity
                    } else {
                        bot_debug_log("levenshtein could not fix " . $p->param);
                        report_inline("could not be replaced with confidence. Please check the citation yourself.");
                    }
                }
            }
        }
    }

    /** @return array<string> */
    public function initial_author_params(): array {
        return $this->initial_author_params;
    }

     /** @param array<string> $auths */
    public function initial_author_params_set(array $auths): void {
        $this->initial_author_params = $auths;
    }

    public function had_initial_author(): bool {
        return count($this->initial_author_params) > 0;
    }

    public function had_initial_editor(): bool {
        return $this->had_initial_eds;
    }

    private function join_params(): string {
        $this->convert_to_vanc();
        $ret = '';
        foreach ($this->param as $p) {
            $ret .= '|' . $p->parsed_text();
        }
        return $ret;
    }

    private function convert_to_vanc(): void {
        if (self::$name_list_style === VancStyle::NAME_LIST_STYLE_VANC && !$this->had_initial_author() && !$this->had_initial_editor()) {
            $vanc_attribs = ['vauthors', 'veditors'];
            $vanc_fa = ['first', 'editor-first'];
            $vanc_la = ['last', 'editor-last'];
            foreach ($vanc_attribs as $vanc_idx => $vanc_attrib) {
                $vanc_f = $vanc_fa[$vanc_idx];
                $vanc_l = $vanc_la[$vanc_idx];
                $v = '';
                /** @phpstan-ignore isset.offset */
                if (!array_key_exists($vanc_attrib, $this->param) || !isset($this->param[$vanc_attrib])) {
                    $arr = [];
                    foreach ($this->param as $k => $p) {
                        if (str_starts_with($p->param, $vanc_f) || str_starts_with($p->param, $vanc_l)) {
                            $arr[$p->param] = $p->val;
                            unset($this->param[$k]);
                        }
                    }

                    // Convert firstN/lastN to vauthors
                    $i = 1;
                    while (true) {
                        $fv = '';
                        $lv = '';
                        $fk = $vanc_f . strval($i);
                        $lk = $vanc_l . strval($i);
                        if (array_key_exists($fk, $arr)) {
                               $tfk = $arr[$fk];
                               unset($arr[$fk]);
                               /** @phpstan-ignore function.alreadyNarrowedType */
                            if (is_string($tfk) && mb_strlen($tfk) > 0) {
                                $fv = $tfk;
                            }
                               unset($tfk);
                        }
                        if (array_key_exists($lk, $arr)) {
                               $tlk = $arr[$lk];
                               unset($arr[$lk]);
                               /** @phpstan-ignore function.alreadyNarrowedType */
                            if (is_string($tlk) && mb_strlen($tlk) > 0) {
                                $lv = $tlk;
                            }
                            unset($tlk);
                        }
                        if ($fv === '' && $lv === '') {
                            break;
                        }
                        if ($v !== '') {
                            $v .= ', ';
                        }
                        $v .= mb_trim($lv . ' ' . mb_substr($fv, 0, 1));
                        $lve = explode(' ', $fv);
                        if (array_key_exists(1, $lve)) {
                            $v .= mb_substr($lve[1], 0, 1);
                        }
                        $i++;
                    }
                }
                if ($v !== '') {
                    $p = new Parameter();
                    $p->parse_text(' ' . $vanc_attrib . ' = ' . $v . ' ');
                    $p->param = $vanc_attrib;
                    $p->val = $v;
                    array_push($this->param, $p);
                }
            }
        }
        // Deal with {{cs1 config|name-list-style=vanc}}<ref>https://pmc.ncbi.nlm.nih.gov/articles/PMC11503076/</ref>{{cs1 config|name-list-style=vanc}}
        if ($this->has('vauthors')) {
            $this->initial_author_params['vauthors'] = $this->get('vauthors');
        }
        if ($this->has('veditors')) {
            $this->had_initial_eds = true;
        }
    }

    public function change_name_to(string $new_name, bool $rename_cite_book = true, bool $rename_anything = false): void {
        if (mb_strpos($this->get('doi'), '10.1093') !== false && $this->wikiname() !== 'cite web') {
            return;
        }
        if ($new_name === 'cite document' && $this->blank('publisher')) {
            return;
        }
        if (bad_10_1093_doi($this->get('doi'))) {
            return;
        }
        foreach (WORK_ALIASES as $work) {
            $worky = mb_strtolower($this->get($work));
            if (preg_match(REGEXP_PLAIN_WIKILINK, $worky, $matches) || preg_match(REGEXP_PIPED_WIKILINK, $worky, $matches)) {
                $worky = $matches[1]; // Always the wikilink for easier standardization
            }
            if (in_array($worky, ARE_MANY_THINGS, true)) {
                return;
            }
        }
        if ($this->wikiname() === 'cite book' && !$this->blank_other_than_comments(CHAPTER_ALIASES_AND_SCRIPT)) {
            return; // Changing away leads to error
        }
        if ($this->wikiname() === 'cite document' && in_array(mb_strtolower($this->get('work')), ARE_WORKS, true)) {
            return; // Things with DOIs that are works
        }
        $new_name = mb_strtolower(mb_trim($new_name)); // Match wikiname() output and cite book below
        if ($new_name === $this->wikiname()) {
            return;
        }
        if ($this->has('conference') && $this->wikiname() === 'cite conference') {
            return;
        } // Need to lose conference first
        if (
            (in_array($this->wikiname(), TEMPLATES_WE_RENAME, true) && ($rename_cite_book || $this->wikiname() !== 'cite book')) ||
            ($this->wikiname() === 'cite news' && $new_name === 'cite magazine') ||
            ($rename_anything && in_array($new_name, TEMPLATES_WE_RENAME, true)) // In rare cases when we are positive that cite news is really cite journal
        ) {
            if ($new_name === 'cite arxiv') {
                if (
                    !$this->blank(
                            [
                                'website',
                                'displayauthors',
                                'display-authors',
                                'access-date',
                                'accessdate',
                                'translator',
                                'translator1',
                                'translator1-first',
                                'translator1-given',
                                'translator1-last',
                                'translator1-surname',
                                'translator-first',
                                'translator-first1',
                                'translator-given',
                                'translator-given1',
                                'translator-last',
                                'translator-last1',
                                'translator-surname',
                                'translator-surname1',
                                'display-editors',
                                'displayeditors',
                                'url',
                                ...FIRST_EDITOR_ALIASES
                            ]
                    )
                ) {
                    return;
                } // Unsupported parameters
            }
            if (mb_stripos($this->name, '#invoke:') !== false) {
                $this->name = str_ireplace('#invoke:', '', $this->name);
                $invoke = '#invoke:';
            } else {
                $invoke = '';
            }
            if (!preg_match("~^(\s*)[\s\S]*?(\s*)$~", $this->name, $spacing)) {
                bot_debug_log("RegEx failure in Template name: " . $this->name); // @codeCoverageIgnoreStart
                $spacing = [];
                $spacing[1] = '';
                $spacing[2] = ''; // @codeCoverageIgnoreEnd
            }
            $new_name_mapped = $new_name;
            if (!in_array(WIKI_BASE, ENGLISH_WIKI)) {
                foreach (ALL_TEMPLATES_MAP as $map_array) {
                    if (in_array(mb_strtolower($this->name), $map_array)) {
                        foreach ($map_array as $map_in => $map_out) {
                            if ($new_name === $map_out) {
                                 $new_name_mapped = $map_in;
                            }
                        }
                    }
                }
            }
            $new_name_mapped = str_replace('arxiv', 'arXiv', $new_name_mapped); // Without the capital X is the alias
            $new_name_mapped = str_replace('biorxiv', 'bioRxiv', $new_name_mapped); // Without the capital R is the alias
            $new_name_mapped = str_replace('medrxiv', 'medRxiv', $new_name_mapped); // Without the capital R is the alias
            if (ctype_upper(mb_substr($this->name, 0, 1))) {
                $new_name_mapped = mb_ucfirst($new_name_mapped);
            }
            $this->name = $spacing[1] . $invoke . $new_name_mapped . $spacing[2];
            switch ($new_name) {
                case 'cite journal':
                    $this->rename('eprint', 'arxiv');
                    $this->forget('class');
                    break;
            }
        }
        if ($new_name === 'cite book' && $this->wikiname() === 'cite book') {
            // all open-access versions of conference papers point to the paper itself
            // not to the whole proceedings
            // so we use chapter-url so that the template is well rendered afterwards
            if (should_url2chapter($this, true)) {
                $this->rename('url', 'chapter-url');
            } elseif (!$this->blank(['chapter-url', 'chapterurl']) && str_i_same($this->get('chapter-url'), $this->get('url'))) {
                $this->forget('url');
            } // otherwise they are different urls

            // If there is work=/title= pair and we are converting the template to a cite book
            // we need to convert them to use the chapter=/title= pair instead as required by CS1
            if ( $this->has( 'work' ) && $this->has( 'title' ) ) {
                $tmp = $this->get( 'work' );
                $this->rename( 'title', 'chapter' );
                $this->add('title', $tmp);
            }

            // Remove blank unsupported parameters when converting to cite book
            foreach (CITE_BOOK_UNSUPPORTED_PARAMS as $unsupported) {
                if ($this->blank($unsupported)) {
                    $this->forget($unsupported);
                }
            }
        }
    }

    /**
     * The name of the template, lowercased. For example, for {{Cite book |last=Jones |first=bob}}, will return "cite book". Will strip out #invoke, will follow some redirects, and changes its dictionary of templates depending on the language specified in WIKI_BASE.
     */
    public function wikiname(): string {
        $name = mb_trim(mb_strtolower(str_replace('_', ' ', $this->name)));
        $name = mb_trim(mb_strtolower(str_replace('#invoke:', '', $name)));
        // Treat the same since alias
        if ($name === 'cite work') {
            $name = 'cite book';
        }
        if ($name === 'cite chapter') {
            $name = 'cite book';
        }
        if ($name === 'cite newspaper') {
            $name = 'cite news';
        }
        if ($name === 'cite website') {
            $name = 'cite web';
        }
        if ($name === 'cite manual') {
            $name = 'cite book';
        }
        if ($name === 'cite paper') {
            $name = 'cite journal';
        }
        if ($name === 'cite contribution') {
            $name = 'cite encyclopedia';
        }
        if ($name === 'cite periodical') {
            $name = 'cite magazine';
        }
        if ($name === 'cite') {
            $name = 'citation';
        }
        if (!in_array(WIKI_BASE, ENGLISH_WIKI)) { // Do not map on english wiki's
            foreach (ALL_TEMPLATES_MAP as $map_array) {
                foreach ($map_array as $map_in => $map_out) {
                    if ($name === $map_in) {
                        $name = $map_out;
                    }
                }
            }
        }
        return $name;
    }

    public function should_be_processed(): bool {
        return in_array($this->wikiname(), TEMPLATES_WE_PROCESS, true);
    }

    public function tidy_parameter(string $param): void {
        set_time_limit(120);
        // Note: Parameters are treated in alphabetical order, except where one
        // case necessarily continues from the previous (without a return).

        if (!$param) {
            return;
        }

        if ($param === 'postscript' && $this->wikiname() !== 'citation' && preg_match('~^(?:# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #)\s*(?:# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #|)$~i', $this->get('postscript'))) {
            // Remove misleading stuff -- comments of "NONE" etc mean nothing!
            // Cannot call forget, since it will not remove items with comments in it
            $key = $this->get_param_key('postscript');
            /** @psalm-suppress PossiblyNullArrayOffset */
            unset($this->param[$key]); // Key cannot be null because of get() call above
            report_forget('Dropping postscript that is only a comment');
            return;
        }

        if (mb_stripos($this->get($param), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== false && $param !== 'ref') {
            return; // We let comments block the bot
        }
        if ($this->get($param) !== $this->get3($param)) {
            return;
        }
        if ($this->has($param)) {
            if (
                mb_stripos($param, 'separator') === false && // lone punctuation valid
                mb_stripos($param, 'postscript') === false && // periods valid
                mb_stripos($param, 'url') === false && // all characters are valid
                mb_stripos($param, 'quot') === false && // someone might have formatted the quote
                mb_stripos($param, 'link') === false && // inter-wiki links
                mb_stripos($param, 'mask') === false && // sometimes used for asian names is a very odd way
                $param !== 'script-title' && // these can be very weird
                (($param !== 'chapter' && $param !== 'title') || mb_strlen($this->get($param)) > 4) // Avoid tiny titles that might be a smiley face
            ) {
                $this->set($param, safe_preg_replace('~[\x{2000}-\x{200A}\x{00A0}\x{202F}\x{205F}\x{3000}]~u', ' ', $this->get($param))); // Non-standard spaces
                $this->set($param, safe_preg_replace('~[\t\n\r\0\x0B]~u', ' ', $this->get($param))); // tabs, linefeeds, null bytes
                $bom = pack('H*', 'EFBBBF');
                $this->set($param, safe_preg_replace('~' . $bom . '~', ' ', $this->get($param)));
                $this->set($param, safe_preg_replace('~  +~u', ' ', $this->get($param))); // multiple spaces
                $this->set($param, safe_preg_replace('~(?<!:)[:,]$~u', '', $this->get($param))); // Remove trailing commas, colons, but not semi-colons--They are HTML encoding stuff
                $this->set($param, safe_preg_replace('~^[:,;](?!:)~u', '', $this->get($param))); // Remove leading commas, colons, and semi-colons
                $this->set($param, safe_preg_replace('~^\=+\s*(?![^a-zA-Z0-9\[\'\"])~u', '', $this->get($param))); // Remove leading ='s sign if in front of letter or number
                $this->set($param, safe_preg_replace('~&#x2010;~u', '-', $this->get($param)));
                $this->set($param, safe_preg_replace('~\x{2010}~u', '-', $this->get($param)));
                $this->set($param, safe_preg_replace('~&#x2013;~u', '&ndash;', $this->get($param)));
                $this->set($param, safe_preg_replace('~&#x2014;~u', '&mdash;', $this->get($param)));
                $this->set($param, safe_preg_replace('~&#x00026;~u', '&', $this->get($param)));
                $this->set($param, safe_preg_replace('~&#8203;~u', ' ', $this->get($param)));
                $this->set($param, safe_preg_replace('~  +~u', ' ', $this->get($param))); // multiple spaces
                $this->set($param, safe_preg_replace('~(?<!\&)&[Aa]pos;(?!&)~u', "'", $this->get($param))); // $apos;
                $this->set($param, safe_preg_replace('~(?<!\&)&[Aa]mp;(?!&)~u', '&', $this->get($param))); // &Amp; => & but not if next character is & or previous character is ;

                // Remove final semi-colon from a few items
                if ((in_array($param, REMOVE_SEMI, true) || in_array($param, FLATTENED_AUTHOR_PARAMETERS, true)) && mb_strpos($this->get($param), '&') === false) {
                    $this->set($param, safe_preg_replace('~;$~u', '', $this->get($param)));
                }
                // Remove final period from a few items
                if (in_array($param, REMOVE_PERIOD, true)) {
                    if (preg_match('~^(\d+)\.$~', $this->get($param), $match)) {
                        $this->set($param, $match[1]);
                    }
                }

                // Remove quotes, if only at start and end -- In the case of title, leave them unless they are messed up
                // Do not do titles of non-books, since they sometimes have quotes in the actual one
                if (($param !== 'title' || $this->has('chapter')) && preg_match("~^([\'\"]+)([^\'\"]+)([\'\"]+)$~u", $this->get($param), $matches)) {
                    if ($matches[1] !== $matches[3] || ($param !== 'title' && $param !== 'chapter' && $param !== 'publisher' && $param !== 'trans-title')) {
                        $this->set($param, $matches[2]);
                    }
                }
                if (preg_match("~^\'\'\'([^\']+)\'\'\'$~u", $this->get($param), $matches)) {
                    $this->set($param, $matches[1]); // Remove bold
                }
                $this->set($param, safe_preg_replace('~\x{00AD}~u', '', $this->get($param))); // Remove soft hyphen
            }
            if (
                mb_stripos($param, 'separator') === false && // punctuation valid
                mb_stripos($param, 'url') === false && // everything is valid
                mb_stripos($param, 'link') === false && // inter-wiki links
                $param !== 'trans-title' // these can be very weird
            ) {
                // Non-breaking spaces at ends
                $this->set($param, mb_trim($this->get($param), " \t\n\r\0\x0B"));
                $this->set($param, safe_preg_replace("~^\xE2\x80\x8B~", " ", $this->get($param))); // Zero-width at start
                $this->set($param, safe_preg_replace("~\xE2\x80\x8B$~", " ", $this->get($param))); // Zero-width at end
                $this->set($param, safe_preg_replace("~\x{200B}~u", " ", $this->get($param))); //Zero-width anywhere
                while (preg_match("~^&nbsp;(.+)$~u", $this->get($param), $matches)) {
                    $this->set($param, mb_trim($matches[1], " \t\n\r\0\x0B"));
                }
                while (preg_match("~^(.+)&nbsp;$~u", $this->get($param), $matches)) {
                    $this->set($param, mb_trim($matches[1], " \t\n\r\0\x0B"));
                }
            }
        }
        if (in_array(mb_strtolower($param), ['series', 'journal', 'newspaper'], true) && $this->has($param)) {
            $this->set($param, safe_preg_replace('~[™|®]$~u', '', $this->get($param))); // remove trailing TM/(R)
        }
        if (
            in_array(
                str_replace(['-', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], '', mb_strtolower($param)),
                LINK_LIST,
                true
            ) &&
            $this->has($param) &&
            mb_stripos($this->get($param), 'http') === false &&
            mb_stripos($this->get($param), 'PLACEHOLDER') === false
        ) {
            $this->set($param, safe_preg_replace('~_~u', ' ', $this->get($param)));
        }

        if (!preg_match('~^(\D+)(\d*)(\D*)$~', $param, $pmatch)) {
            report_minor_error("Unrecognized parameter name format in " . echoable($param)); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        } else {
            // Put "odd ones" in "normalized" order - be careful down below about $param vs $pmatch values
            if (in_array(mb_strtolower($param), ['s2cid', 's2cid-access'], true)) {
                $pmatch = [$param, $param, '', ''];
            }
            if (in_array(mb_strtolower($pmatch[3]), ['-first', '-last', '-surname', '-given', 'given', '-link', 'link', '-mask', 'mask'], true)) {
                $pmatch = [$param, $pmatch[1] . $pmatch[3], $pmatch[2], ''];
            }
            if ($pmatch[3] !== '') {
                report_minor_error("Unrecognized parameter name format in " . echoable($param)); // @codeCoverageIgnore
                return; // @codeCoverageIgnore
            }
            switch ($pmatch[1]) {
                // Parameters are listed mostly alphabetically, though those with numerical content are grouped under "year"

                case 'accessdate':
                case 'access-date':
                    if ($this->has($param) && $this->blank(ALL_URL_TYPES)) {
                        $this->forget($param);
                    }
                    return;

                case 'agency':
                    if (
                        in_array($this->get('agency'), BAD_AGENT, true) &&
                        in_array($this->get('publisher'), BAD_AGENT_PUBS, true)
                    ) {
                        $this->forget('publisher');
                        $this->rename('agency', 'publisher'); // A single user messed this up on a lot of pages with "agency"
                        return;
                    }
                    // Undo some bad bot/human edits
                    if ($this->blank(WORK_ALIASES)) {
                        $the_url = '';
                        foreach (ALL_URL_TYPES as $thingy) {
                            $the_url .= $this->get($thingy);
                        }
                        $cleaned = mb_strtolower(str_replace(['[', ']', '.'], '', $this->get($param)));

                        if (in_array($cleaned, ['reuters'], true)) {
                            if (mb_stripos($the_url, 'reuters.co') !== false) {
                                $this->rename($param, 'work');
                            }
                        } elseif (in_array($cleaned, ['associated press', 'ap', 'ap news', 'associated press news'], true)) {
                            if (mb_stripos($the_url, 'apnews.co') !== false) {
                                $this->rename($param, 'work');
                                if ($this->get('work') === 'AP') {
                                       $this->set('work', 'AP News');
                                } elseif ($this->get('work') === 'Associated Press') {
                                    $this->set('work', 'Associated Press News');
                                } elseif ($this->get('work') === '[[Associated Press]]') {
                                    $this->set('work', '[[Associated Press News]]');
                                } elseif ($this->get('work') === '[[AP]]') {
                                    $this->set('work', '[[AP News]]');
                                }
                            }
                        } elseif (in_array($cleaned, ['united press international', 'upi'], true)) {
                            if (mb_stripos($the_url, 'upi.com') !== false) {
                                $this->rename($param, 'work');
                            }
                        } elseif (in_array($cleaned, ['philippine news agency', 'philippine information agency'], true)) {
                            if (mb_stripos($the_url, 'pia.gov.ph') !== false || mb_stripos($the_url, 'pna.gov.ph') !== false) {
                                $this->rename($param, 'work');
                            }
                        } elseif (in_array($cleaned, ['yonhap news agency'], true)) {
                            if (mb_stripos($the_url, 'yna.co.kr') !== false) {
                                $this->rename($param, 'work');
                            }
                        } elseif (in_array($cleaned, ['official charts company'], true)) {
                            if (mb_stripos($the_url, 'officialcharts.com') !== false) {
                                $this->rename($param, 'work');
                            }
                        }
                    }
                    return;

                case 'arxiv':
                    if ($this->has($param) && $this->wikiname() === 'cite web') {
                        $this->change_name_to('cite arxiv');
                    }
                    return;

                case 'author':
                    $the_author = $this->get($param);
                    // Check for all-caps author names
                    if ($the_author && preg_match('/^[A-Z\s]{4,}$/', $the_author) && !preg_match('/^[IVX]+$/', $the_author)) {
                        report_warning("Author name is in all-caps and should be properly capitalized: " . echoable($the_author));
                    }
                    if ($this->blank('agency') && in_array(mb_strtolower($the_author), ['associated press', 'reuters'], true) && $this->wikiname() !== 'cite book') {
                        $this->rename('author' . $pmatch[2], 'agency');
                        if ($pmatch[2] === '1' || $pmatch[2] === '') {
                               $this->forget('author-link');
                               $this->forget('authorlink');
                               $this->forget('author-link1');
                               $this->forget('authorlink1');
                               $this->forget('author1-link');
                        } else {
                            $this->forget('author-link' . $pmatch[2]);
                            $this->forget('authorlink' . $pmatch[2]);
                            $this->forget('author' . $pmatch[2] . '-link');
                        }
                        for ($looper = (int) $pmatch[2] + 1; $looper <= 100; $looper++) {
                            $old = (string) $looper;
                            $new = (string) ($looper - 1);
                            $this->rename('author' . $old, 'author' . $new);
                            $this->rename('last' . $old, 'last' . $new);
                            $this->rename('first' . $old, 'first' . $new);
                            $this->rename('author-link' . $old, 'author-link' . $new);
                            $this->rename('authorlink' . $old, 'authorlink' . $new);
                            $this->rename('author' . $old . '-link', 'author' . $new . '-link');
                        }
                        return;
                    }
                    // Convert authorX to lastX, if firstX is set
                    if ($pmatch[2] && $this->has('first' . $pmatch[2]) && $this->blank('last' . $pmatch[2])) {
                        $this->rename('author' . $pmatch[2], 'last' . $pmatch[2]);
                        $pmatch[1] = 'last';
                        // Comment out since "never used"  $param = 'last' . $pmatch[2];
                        return;
                    }
                    // no break
                case 'authors':
                    if ($this->has('author') && $this->has('authors')) {
                        $this->rename('author', 'DUPLICATE_authors');
                    }
                    if (!$this->had_initial_author()) {
                        $this->handle_et_al();
                    }
                    // no break; Continue from authors without break
                case 'last':
                case 'surname':
                    // Check for all-caps last names
                    if ($pmatch[1] === 'last' || $pmatch[1] === 'surname') {
                        $the_last = $this->get($param);
                        if ($the_last && preg_match('/^[A-Z\s]{4,}$/', $the_last) && !preg_match('/^[IVX]+$/', $the_last)) {
                            report_warning("Author last name is in all-caps and should be properly capitalized: " . echoable($the_last));
                        }
                    }
                    if (!$this->had_initial_author()) {
                        if ($pmatch[2]) {
                            $translator_regexp = "~\b([Tt]r(ans(lat...?(by)?)?)?\.?)\s([\w\p{L}\p{M}\s]+)$~u";
                            if (preg_match($translator_regexp, mb_trim($this->get($param)), $match)) {
                                $others = mb_trim($match[1] . ' ' . $match[5]);
                                if ($this->has('others')) {
                                    $this->append_to('others', '; ' . $others);
                                } else {
                                    $this->set('others', $others);
                                }
                                $this->set($param, mb_trim(preg_replace($translator_regexp, "", $this->get($param))));
                            }
                        }
                    }
                    if ($pmatch[2] && $pmatch[1] === 'last') {
                        $the_author = $this->get($param);
                        if (
                            mb_substr($the_author, 0, 2) === '[[' &&
                            mb_substr($the_author, -2) === ']]' &&
                            mb_substr_count($the_author, '[[') === 1 &&
                            mb_substr_count($the_author, ']]') === 1 &&
                            mb_strpos($the_author, 'CITATION_BOT') === false &&
                            mb_strpos($the_author, '{{!}}') === false
                        ) {
                            // Has a normal wikilink
                            $did_something = false;
                            if (preg_match(REGEXP_PLAIN_WIKILINK_ONLY, $the_author, $matches)) {
                                $this->set($param, $matches[1]);
                                $this->add_if_new('author' . $pmatch[2] . '-link', $matches[1]);
                                $did_something = true;
                            } elseif (preg_match(REGEXP_PIPED_WIKILINK_ONLY, $the_author, $matches)) {
                                $this->set($param, $matches[2]);
                                $this->add_if_new('author' . $pmatch[2] . '-link', $matches[1]);
                                $did_something = true;
                            }
                            if ($pmatch[2] === '1' && $this->has('first')) {
                                $this->rename('first', 'first1');
                                $this->rename('author-link', 'author-link1');
                                $this->rename('author-mask', 'author-mask1');
                            }
                            if ($did_something && mb_strpos($this->get('first' . $pmatch[2]), '[') !== false) {
                                // Clean up links in first names
                                $the_author = $this->get('first' . $pmatch[2]);
                                if (preg_match(REGEXP_PLAIN_WIKILINK_ONLY, $the_author, $matches)) {
                                    $this->set('first' . $pmatch[2], $matches[1]);
                                } elseif (preg_match(REGEXP_PIPED_WIKILINK_ONLY, $the_author, $matches)) {
                                    $this->set('first' . $pmatch[2], $matches[2]);
                                }
                            }
                        }
                    }
                    if (!$pmatch[2] && $pmatch[1] === 'last' && !$this->blank(['first1', 'first2', 'last2'])) {
                        if ($this->blank('last1')) {
                            $this->rename('last', 'last1');
                            $this->rename('author-link', 'author-link1');
                            $this->rename('author-mask', 'author-mask1');
                        }
                        if ($this->blank('first1')) {
                            $this->rename('first', 'first1');
                            $this->rename('author-link', 'author-link1');
                            $this->rename('author-mask', 'author-mask1');
                        }
                    }
                    return;

                case 'first':
                    if (!$pmatch[2] && !$this->blank(['last1', 'first2', 'last2'])) {
                        if ($this->blank('last1')) {
                            $this->rename('last', 'last1');
                            $this->rename('author-link', 'author-link1');
                            $this->rename('author-mask', 'author-mask1');
                        }
                        if ($this->blank('first1')) {
                            $this->rename('first', 'first1');
                            $this->rename('author-link', 'author-link1');
                            $this->rename('author-mask', 'author-mask1');
                        }
                    }
                    return;

                case 'bibcode':
                    if ($this->blank($param)) {
                        return;
                    }
                    $bibcode_journal = (string) mb_substr($this->get($param), 4);
                    if ($bibcode_journal === '') {
                        return;
                    } // bad bibcodes would not have four characters, use ==, since it might be "" or false depending upon error/PHP version
                    foreach (NON_JOURNAL_BIBCODES as $exception) {
                        if (mb_substr($bibcode_journal, 0, mb_strlen($exception)) === $exception) {
                            return;
                        }
                    }
                    if (mb_strpos($this->get($param), 'book') !== false) {
                        $this->change_name_to('cite book', false);
                    } else {
                        $this->change_name_to('cite journal', false);
                    }
                    return;

                case 'chapter':
                    if ($this->has('chapter')) {
                        if (str_equivalent($this->get($param), $this->get('work'))) {
                            $this->forget('work');
                        }
                        if (str_equivalent($this->get('chapter'), $this->get('title'))) {
                            $this->forget('chapter');
                            return; // Nonsense to have both.
                        }
                        if ($this->get('chapter') === 'Cultural Advice' && mb_strpos($this->get('url') . $this->get('chapter-url'), 'anu.edu.au') !== false) {
                            $this->forget('chapter');
                            return;
                        }
                    }
                    if ($this->has('chapter') && $this->blank(['journal', 'bibcode', 'jstor', 'pmid'])) {
                        $this->change_name_to('cite book');
                    }
                    return;

                case 'contribution':
                    if ($this->has('contribution') && $this->has('url') && $this->blank('contribution-url')) {
                        if (preg_match('~^https?://portal\.acm\.org/citation\.cfm\?id=\d+$~', $this->get('url'))) {
                            $this->rename('url', 'contribution-url');
                        }
                    }
                    return;

                case 'class':
                    if ($this->blank('class')) {
                        if ($this->wikiname() !== 'cite arxiv' && !$this->blank(['doi', 'pmid', 'pmc', 'journal', 'series', 'isbn'])) {
                            $this->forget('class');
                        }
                    }
                    return;

                case 'date':
                    if ($this->blank('date') && $this->has('year')) {
                        $this->forget('date');
                    }
                    if (preg_match('~^([A-Za-z]+)\-([A-Za-z]+ \d{4})$~', $this->get('date'), $matched)) {
                        $this->set('date', $matched[1] . '–' . $matched[2]);
                    }
                    return;

                case 'month':
                    if ($this->blank($param)) {
                        $this->forget($param);
                        return;
                    }
                    if ($this->has('date')) {
                        if (mb_stripos($this->get('date'), $this->get($param)) !== false) {
                            // Date has month already
                            $this->forget('month');
                            $this->forget('day');
                            return;
                        }
                    }
                    if ($this->has('date') || $this->blank('year')) {
                        return;
                    }
                    $day = $this->get('day');
                    $month = $this->get('month');
                    $year = $this->get('year');
                    if ($day === '' && preg_match('~^([a-zA-Z]+) 0*(\d+)$~', $month, $matches)) {
                        $day = $matches[2];
                        $month = $matches[1];
                    }
                    if ($day === '' && preg_match('~^0*(\d+) ([a-zA-Z]+)$~', $month, $matches)) {
                        $day = $matches[1];
                        $month = $matches[2];
                    }
                    if (!preg_match('~^\d*$~', $day)) {
                        return;
                    }
                    if (!preg_match('~^[a-zA-Z\–\-]+$~u', $month)) {
                        return;
                    }
                    if (!preg_match('~^\d{4}$~', $year)) {
                        return;
                    }
                    $new_date = mb_trim($day . ' ' . $month . ' ' . $year);
                    $this->forget('day');
                    $this->rename($param, 'date', $new_date);
                    return;

                case 'dead-url':
                case 'deadurl':
                    $the_data = mb_strtolower($this->get($param));
                    if (in_array($the_data, YES_LANGS, true)) {
                        $this->rename($param, 'url-status', 'dead');
                        $this->forget($param);
                    } elseif (in_array($the_data, NO_LANGS, true)) {
                        $this->rename($param, 'url-status', 'live');
                        $this->forget($param);
                    } elseif (in_array($the_data, ['', 'bot: unknown'], true)) {
                        $this->forget($param);
                    } elseif (in_array($the_data, ['unfit'], true)) {
                        $this->rename($param, 'url-status');
                        $this->forget($param);
                    }
                    return;

                case 'arşivengelli': // "ignore archive"
                    $the_data = mb_strtolower($this->get($param));
                    if (in_array($the_data, YES_LANGS, true)) {
                        $this->rename($param, 'url-status', 'live');
                        $this->forget($param);
                    } elseif (in_array($the_data, NO_LANGS, true)) {
                        $this->rename($param, 'url-status', 'dead');
                        $this->forget($param);
                    } elseif (in_array($the_data, ['', 'bot: unknown'], true)) {
                        $this->forget($param);
                    } else {
                        $this->forget($param);
                    }
                    return;

                case 'url-status':
                    $the_data = mb_strtolower($this->get($param));
                    if (in_array($the_data, YES_LANGS, true)) {
                        $this->set($param, 'dead');
                    } elseif (in_array($the_data, NO_LANGS, true)) {
                        $this->set($param, 'live');
                    }
                    return;

                case 'df':
                    if ($this->blank('df')) {
                        $this->forget('df');
                    }
                    return;

                case 'last-author-amp':
                case 'lastauthoramp':
                    $the_data = mb_strtolower($this->get($param));
                    if (in_array($the_data, NO_LANGS, true)) {
                        $this->forget($param);
                        return;
                    }
                    if (in_array($the_data, YES_LANGS, true)) {
                        $this->rename($param, 'name-list-style', 'amp');
                        $this->forget($param);
                    }
                    return;

                case 'doi-access':
                    if ($this->blank('doi') && $this->has('doi-access')) {
                        $this->forget('doi-access');
                    }
                    return;

                case 'doi':
                    $doi = $this->get($param);
                    if (!$doi) {
                        return;
                    }
                    if ($this->wikiname() === 'cite journal') {
                        if (mb_stripos($doi, '10.2307/j.') === 0 || preg_match('~^10\.\d+/\d+\.ch\d+$~', $doi)) {
                            $this->change_name_to('cite book');
                        }
                    }
                    if (preg_match('~^(10\.[^\/]+\/)\/(.+)$~', $doi, $matches)) {
                        $try = $matches[1] . $matches[2];
                        if (doi_works($try)) {
                            $doi = $try;
                            $this->set('doi', $try);
                        }
                    }
                    if (preg_match('~^10\.(10520\/.+)$~', $doi, $matches)) {
                        $this->rename($param, 'hdl', $matches[1]);
                        return;
                    }
                    if ($doi === '10.1267/science.040579197') {
                        // This is a bogus DOI from the PMID example file
                        $this->forget('doi');
                        return;
                    }
                    if ($doi === '10.5284/1000184') {
                        // This is a DOI for an entire database, not anything within it
                        $this->forget('doi');
                        return;
                    }
                    if ($doi === '10.7556/jaoa' || $doi === '10.5334/sta.az') {
                        // Over truncated
                        $this->forget('doi');
                        return;
                    }
                    if (mb_stripos($doi, '10.48550/arXiv.') === 0) {
                        $pos0 = mb_strtolower(mb_substr($doi, 15));
                        $pos1 = mb_strtolower($this->get('eprint'));
                        $pos2 = mb_strtolower($this->get('arxiv'));
                        if ($pos0 === $pos1 || $pos0 === $pos2) {
                            $this->forget('doi');
                            return;
                        }
                    }
                    if (preg_match('~^(10\.2173\/bow\..+)species_shared\.bow\.project_name$~', $doi, $matched)) {
                        $this->set('doi', $matched[1]);
                        $doi = $matched[1];
                    }
                    if (mb_substr($doi, 0, 8) === '10.5555/') {
                        // Test DOI prefix. NEVER will work
                        $this->forget('doi');
                        if ($this->blank('url')) {
                            /** @psalm-taint-escape ssrf */
                            $test_url = 'https://plants.jstor.org/stable/' . $doi;
                            $ch = bot_curl_init(1.5, [CURLOPT_URL => $test_url]);
                            bot_curl_exec($ch);
                            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            unset($ch);
                            if ($httpCode === 200) {
                                $this->add_if_new('url', $test_url);
                            }
                        }
                        return;
                    }
                    if (!doi_works($doi) && mb_stripos($doi, '10.3316/') === 0) {
                        if ($this->has('url') || $this->has('pmid') || $this->has('jstor') || $this->has('pmc')) {
                            $this->forget('doi');
                            return;
                        }
                    }
                    if (!doi_works($doi) && mb_stripos($doi, '10.1043/0003-3219(') === 0) {
                        $this->forget('doi'); // Per-email. The Angle Orthodontist will NEVER do these, since they have <> and [] in them
                        return;
                    }
                    if (doi_works($doi) === null) {
                        // This is super slow and rare
                        // @codeCoverageIgnoreStart
                        if ($this->has('pmc') || $this->has('pmid')) {
                            if (
                                mb_stripos($doi, '10.1210/me.') === 0 ||
                                mb_stripos($doi, '10.1210/jc.') === 0 ||
                                mb_stripos($doi, '10.1210/er.') === 0 ||
                                mb_stripos($doi, '10.1210/en.') === 0 ||
                                mb_stripos($doi, '10.1128/.61') === 0 ||
                                mb_stripos($doi, '10.1379/1466-1268') === 0
                            ) {
                                $this->forget('doi'); // Need updated and replaced
                                return;
                            }
                        }
                        if (mb_stripos($doi, '10.1258/jrsm.') === 0 || mb_stripos($doi, '10.1525/as.') === 0 || mb_stripos($doi, '10.1525/sp.') === 0 || mb_stripos($doi, '10.1067/mva.') === 0) {
                            $doi = $this->get('doi');
                            $this->set('doi', ''); // Need updated and replaced
                            get_doi_from_crossref($this);
                            if (doi_works($this->get('doi')) !== true) {
                                $this->set('doi', $doi);
                            }
                            return;
                        }
                        if (mb_stripos($doi, '10.2979/new.') === 0 || mb_stripos($doi, '10.2979/FSR.') === 0 || mb_stripos($doi, '10.2979/NWS') === 0 || mb_stripos($doi, '10.1353/nwsa.') === 0) {
                            if ($this->has('url') || $this->has('jstor')) {
                                $this->forget('doi'); // Dead/Jstor/Muse
                                return;
                            }
                        }
                        if (mb_stripos($doi, '10.1093/em/') === 0) {
                            if (preg_match('~^10\.1093/em/(\d+)(\.\d+\.\d+)$~', $doi, $matches)) {
                                $romed = numberToRomanRepresentation((int) $matches[1]) . $matches[2];
                                $try_doi = '10.1093/earlyj/' . $romed;
                                if (doi_works($try_doi) === true) {
                                       $this->set('doi', $try_doi);
                                       return;
                                }
                                $try_doi = '10.1093/em/' . $romed;
                                if (doi_works($try_doi) === true) {
                                    $this->set('doi', $try_doi);
                                    return;
                                }
                            }
                            return;
                        }
                        if (mb_strpos($doi, '10.2310/') === 0) {
                            $this->set('doi', '');
                            get_doi_from_crossref($this);
                            if ($this->blank('doi')) {
                                $this->set('doi', $doi);
                            }
                            return;
                        }
                        if (mb_stripos($doi, '10.1093/ml/') === 0) {
                            if (preg_match('~^10\.1093/ml/(\d+)(\.\d+\.\d+)$~', $doi, $matches)) {
                                $romed = numberToRomanRepresentation((int) $matches[1]) . $matches[2];
                                $try_doi = '10.1093/ml/' . $romed;
                                if (doi_works($try_doi) === true) {
                                       $this->set('doi', $try_doi);
                                }
                            }
                            return;
                        }
                        // @codeCoverageIgnoreEnd
                    }
                    if (!doi_works($doi)) {
                        $this->verify_doi();
                        $doi = $this->get($param);
                        if ($doi === '') {
                            return;
                        }
                    }
                    if (!doi_works($doi) && mb_strpos($doi, '10.1111/j.1475-4983.' . $this->year()) === 0) {
                        $this->forget('doi'); // Special Papers in Palaeontology - they do not work
                        return;
                    }
                    if (doi_works($doi) !== true && mb_strpos($doi, '10.2277/') === 0) {
                        $this->forget('doi'); // contentdirections.com DOI provider is gone
                        return;
                    }
                    if (doi_works($doi) !== true && mb_strpos($doi, '10.1336/') === 0 && $this->has('isbn')) {
                        $this->forget('doi'); // contentdirections.com DOI provider is gone
                        return;
                    }
                    if (doi_works($doi) !== true && mb_strpos($doi, '10.1036/') === 0 && $this->has('isbn')) {
                        $this->forget('doi'); // contentdirections.com DOI provider is gone
                        return;
                    }
                    if (!doi_works($doi)) {
                        $doi = sanitize_doi($doi);
                        $this->set($param, $doi);
                    }
                    if (!doi_works($doi)) {
                        if (preg_match('~^10.1093\/oi\/authority\.\d{10,}$~', $doi) && preg_match('~(?:oxfordreference\.com|oxfordindex\.oup\.com)\/[^\/]+\/10.1093\/oi\/authority\.\d{10,}~', $this->get('url'))) {
                            $this->forget('doi');
                            return;
                        } elseif (preg_match('~^10\.1093\/law\:epil\/9780199231690\/law\-9780199231690~', $doi) && preg_match('~ouplaw.com\/view\/10\.1093/law\:epil\/9780199231690\/law\-9780199231690~', $this->get('url'))) {
                            $this->forget('doi');
                            return;
                        }
                    }
                    if (mb_stripos($doi, '10.1093/law:epil') === 0 || mb_stripos($doi, '10.1093/oi/authority') === 0) {
                        return;
                    }
                    if (!preg_match(REGEXP_DOI_ISSN_ONLY, $doi) && doi_works($doi)) {
                        if (!in_array(mb_strtolower($doi), NON_JOURNAL_DOIS, true) && mb_strpos($doi, '10.14344/') === false && mb_stripos($doi, '10.7289/V') === false && mb_stripos($doi, '10.7282/') === false && mb_stripos($doi, '10.5962/bhl.title.') === false && mb_stripos($doi, '10.20944/preprints') === false) {
                            $the_journal = $this->get('journal') . $this->get('work') . $this->get('periodical');
                            if (str_replace(NON_JOURNALS, '', $the_journal) === $the_journal && !$this->blank(WORK_ALIASES) && ($the_journal !== '' || doi_active($doi))) { // Be pickier with non-crossref DOIs
                                $this->change_name_to('cite journal', false);
                            }
                        }
                    }
                    if ($this->blank('jstor') && preg_match('~^10\.2307/(\d+)$~', $this->get_without_comments_and_placeholders('doi'))) {
                        $this->add_if_new('jstor', mb_substr($this->get_without_comments_and_placeholders('doi'), 8));
                    }
                    if ($this->wikiname() === 'cite arxiv') {
                        $this->change_name_to('cite journal');
                    }
                    if (preg_match('~^10\.3897/zookeys\.(\d+)\.\d+$~', $doi, $matches)) {
                        if ($this->blank(ISSUE_ALIASES)) {
                            $this->add_if_new('issue', $matches[1]);
                        } elseif ($this->has('number')) {
                            $this->rename('number', 'issue', $matches[1]);
                        } else {
                            $this->set('issue', $matches[1]);
                        }
                    }
                    /** if (doi_works($doi)) { We are flagging free dois even when the do not work, since template does this right now */
                    foreach (DOI_FREE_PREFIX as $prefix) {
                        if (mb_stripos($doi, $prefix) === 0) {
                            $this->add_if_new('doi-access', 'free');
                        }
                    }
                    // Weird ones that are time dependent
                    $year = $this->year_int(); // Will be zero if not set
                    if (mb_strpos($doi, '10.1155/') === 0 && $year > 2006) {
                        $this->add_if_new('doi-access', 'free');
                    }
                    unset($year);
                    /** } */
                    if (/** doi_works($doi) && */ mb_strpos($doi, '10.1073/pnas') === 0) {
                        $template_year = $this->year();
                        if ($template_year === '') {
                            $template_year = $this->get('publication-date');
                        }
                        if ($template_year !== '') {
                            $template_year = (int) $template_year;
                            $year = (int) date("Y");
                            if ($template_year + 1 < $year) {
                                // At least one year old, up to three
                                $this->add_if_new('doi-access', 'free');
                            }
                        }
                    }
                    if (preg_match('~^10\.48550/arXiv\.(\S{4}\.\S{5})$~i', $doi, $matches)) {
                        if ($this->blank(ARXIV_ALIASES)) {
                            $this->rename('doi', 'eprint', $matches[1]);
                        } elseif ($this->has('eprint')) {
                            $eprint = $this->get('eprint');
                            if ($matches[1] === $eprint) {
                                $this->forget('doi');
                            }
                        } elseif ($this->has('arxiv')) {
                            $eprint = $this->get('arxiv');
                            if ($matches[1] === $eprint) {
                                $this->forget('doi');
                            }
                        }
                    }
                    return;

                case 'hdl':
                    $handle = $this->get($param);
                    if (!$handle) {
                        return;
                    }
                    $handle = hdl_decode($handle);
                    if (preg_match('~^(.+)(\%3Bownerid=.*)$~', $handle, $matches) || preg_match('~^(.+)(;ownerid=.*)$~', $handle, $matches)) {
                        // should we shorten it?
                        if (hdl_works($handle) === false) {
                            $handle = $matches[1];
                        }
                    }
                    if (preg_match('~^(.+)\?urlappend=~', $handle, $matches)) {
                        // should we shorten it?
                        if (hdl_works($handle) === false) {
                            $handle = $matches[1]; // @codeCoverageIgnore
                        } elseif (hdl_works($handle) === null) {
                            // Do nothing
                        } else {
                            $long = hdl_works($handle);
                            $short = hdl_works($matches[1]);
                            if ($long === $short) {
                                // urlappend does nothing
                                $handle = $matches[1];
                            }
                        }
                    }
                    $this->set('hdl', $handle);
                    return;

                case 'doi-broken':
                case 'doi_brokendate':
                case 'doi-broken-date':
                case 'doi_inactivedate':
                case 'doi-inactive-date':
                    if ($this->blank('doi')) {
                        $this->forget($param);
                    }
                    return;

                case 'edition':
                    if ($this->blank($param)) {
                        return;
                    }
                    $this->set($param, safe_preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $this->get($param)));
                    return; // Don't want 'Edition ed.'

                case 'eprint':
                    if ($this->blank($param)) {
                        return;
                    }
                    if ($this->wikiname() === 'cite web') {
                        $this->change_name_to('cite arxiv');
                    }
                    return;

                case 'encyclopedia':
                case 'encyclopeadia':
                    if ($this->blank($param)) {
                        return;
                    }
                    if ($this->wikiname() === 'cite web') {
                        $this->change_name_to('cite encyclopedia');
                    }
                    return;

                case 'format': // clean up bot's old (pre-2018-09-18) edits
                    if ($this->get($param) === 'Accepted manuscript' || $this->get($param) === 'Submitted manuscript' || $this->get($param) === 'Full text') {
                        $this->forget($param);
                    }
                    // Citation templates do this automatically -- also remove if there is no url
                    if (in_array(mb_strtolower($this->get($param)), PDF_LINKS, true)) {
                        if ($this->blank('url') || mb_strtolower(mb_substr($this->get('url'), -4)) === '.pdf') {
                            $this->forget($param);
                        }
                    }
                    return;

                case 'chapter-format':
                    // clean up bot's old (pre-2018-09-18) edits
                    if ($this->get($param) === 'Accepted manuscript' || $this->get($param) === 'Submitted manuscript' || $this->get($param) === 'Full text') {
                        $this->forget($param);
                    }
                    // Citation templates do this automatically -- also remove if there is no url, which is template error
                    if (in_array(mb_strtolower($this->get($param)), PDF_LINKS, true)) {
                        if ($this->has('chapter-url')) {
                            if (mb_substr($this->get('chapter-url'), -4) === '.pdf' || mb_substr($this->get('chapter-url'), -4) === '.PDF') {
                                $this->forget($param);
                            }
                        } elseif ($this->has('chapterurl')) {
                            if (mb_substr($this->get('chapterurl'), -4) === '.pdf' || mb_substr($this->get('chapterurl'), -4) === '.PDF') {
                                $this->forget($param);
                            }
                        } else {
                            $this->forget($param); // Has no chapter URL at all
                        }
                    }
                    return;

                case 'isbn':
                    if ($this->blank('isbn')) {
                        return;
                    }
                    $this->set('isbn', safe_preg_replace('~\s?-\s?~', '-', $this->get('isbn'))); // a White space next to a dash
                    $this->set('isbn', $this->isbn10Toisbn13($this->get('isbn'), false));
                    if ($this->blank('journal') || $this->has('chapter') || $this->wikiname() === 'cite web') {
                        $this->change_name_to('cite book');
                    }
                    $this->forget('asin');
                    return;

                case 'issn':
                case 'eissn':
                    if ($this->blank($param)) {
                        return;
                    }
                    $orig = $this->get($param);
                    $new = safe_preg_replace('~\s?[\-\–]+\s?~', '-', $orig); // a White space next to a dash or bad dash
                    $new = str_replace('x', 'X', $new);
                    if (preg_match('~^(\d{4})\s?(\d{3}[\dX])$~', $new, $matches)) {
                        $new = $matches[1] . '-' . mb_strtoupper($matches[2]); // Add dash
                    }
                    if ($orig !== $new) {
                        $this->set($param, $new);
                    }
                    return;

                case 'asin':
                    if ($this->blank($param)) {
                        return;
                    }
                    if ($this->has('isbn')) {
                        return;
                    }
                    $value = $this->get($param);
                    if (preg_match("~^\d~", $value) && mb_substr($value, 0, 2) !== '63') {
                        // 630 and 631 ones are not ISBNs, so block all of 63*
                        $possible_isbn = sanitize_string($value);
                        $possible_isbn13 = $this->isbn10Toisbn13($possible_isbn, true);
                        if ($possible_isbn !== $possible_isbn13) {
                            // It is an ISBN
                            $this->rename('asin', 'isbn', $this->isbn10Toisbn13($possible_isbn, false));
                        }
                    }
                    return;

                case 'journal':
                case 'periodical':
                    if ($this->blank($param)) {
                        return;
                    }
                    if ($this->get($param) === 'Undefined' || $this->get($param) === 'Semantic Scholar' || $this->get($param) === '[[Semantic Scholar]]') {
                        $this->forget($param);
                        return;
                    }
                    if (preg_match('~^(|[a-zA-Z0-9][a-zA-Z0-9]+\.)([a-zA-Z0-9][a-zA-Z0-9][a-zA-Z0-9]+)\.(org|net|com)$~', $this->get($param))) {
                        $this->rename($param, 'website');
                        return;
                    }
                    if (str_equivalent($this->get($param), $this->get('work'))) {
                        $this->forget('work');
                    }

                    $periodical = mb_trim($this->get($param));
                    if (mb_stripos($periodical, 'arxiv') !== false) {
                        return;
                    }
                    // Check for bioRxiv journal conversion - only for cite journal
                    if (
                        (mb_stripos($periodical, 'biorxiv') !== false || mb_strtolower($periodical) === 'biorxiv: the preprint server for biology') &&
                        $this->wikiname() === 'cite journal' &&
                        $this->has('doi')
                    ) {
                        $doi_value = $this->get('doi');
                        if (preg_match('~^10\.1101/|^10\.64898/~', $doi_value)) {
                            // Convert to cite bioRxiv
                            $this->change_name_to('cite bioRxiv', true, true);
                            $this->rename('doi', 'biorxiv');

                            // Remove parameters not allowed in cite bioRxiv
                            $params_to_remove = [];
                            foreach ($this->param as $p) {
                                $param_name = mb_strtolower($p->param);
                                $keep = in_array($param_name, CITE_BIORXIV_ALLOWED_PARAMS, true);

                                if (!$keep && (
                                    preg_match('~^(?:author|last|first|given|surname|forename|initials)\d*$~i', $p->param) ||
                                    preg_match('~^(?:author|editor)\d+-(?:last|first|given|surname|forename|initials|link|mask)$~i', $p->param) ||
                                    preg_match('~^(?:author|editor)-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $p->param) ||
                                    preg_match('~^(?:authorlink|authormask|editorlink|editormask)\d*$~i', $p->param) ||
                                    preg_match('~^editor\d*$~i', $p->param) ||
                                    preg_match('~^editor-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $p->param) ||
                                    preg_match('~^(?:vauthors|authors|display-authors|displayauthors|veditors|editors|display-editors|displayeditors)$~i', $p->param) ||
                                    mb_stripos($p->param, 'CITATION_BOT') !== false ||
                                    mb_stripos($p->param, 'DUPLICATE') !== false
                                )) {
                                    $keep = true;
                                }

                                if (!$keep) {
                                    $params_to_remove[] = $p->param;
                                }
                            }

                            foreach ($params_to_remove as $param_name) {
                                $this->forget($param_name);
                            }

                            report_modification('Converted cite journal to cite bioRxiv');
                            return;
                        }
                    }
                    // Check for medRxiv journal conversion - only for cite journal
                    if (
                        (mb_stripos($periodical, 'medrxiv') !== false || mb_strtolower($periodical) === 'medrxiv: the preprint server for health sciences') &&
                        $this->wikiname() === 'cite journal' &&
                        $this->has('doi')
                    ) {
                        $doi_value = $this->get('doi');
                        if (preg_match('~^10\.1101/|^10\.64898/~', $doi_value)) {
                            // Convert to cite medRxiv
                            $this->change_name_to('cite medRxiv', true, true);
                            $this->rename('doi', 'medrxiv');

                            // Remove parameters not allowed in cite medRxiv
                            $params_to_remove = [];
                            foreach ($this->param as $p) {
                                $param_name = mb_strtolower($p->param);
                                $keep = in_array($param_name, CITE_MEDRXIV_ALLOWED_PARAMS, true);

                                if (!$keep && (
                                    preg_match('~^(?:author|last|first|given|surname|forename|initials)\d*$~i', $p->param) ||
                                    preg_match('~^(?:author|editor)\d+-(?:last|first|given|surname|forename|initials|link|mask)$~i', $p->param) ||
                                    preg_match('~^(?:author|editor)-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $p->param) ||
                                    preg_match('~^(?:authorlink|authormask|editorlink|editormask)\d*$~i', $p->param) ||
                                    preg_match('~^editor\d*$~i', $p->param) ||
                                    preg_match('~^editor-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $p->param) ||
                                    preg_match('~^(?:vauthors|authors|display-authors|displayauthors|veditors|editors|display-editors|displayeditors)$~i', $p->param) ||
                                    mb_stripos($p->param, 'CITATION_BOT') !== false ||
                                    mb_stripos($p->param, 'DUPLICATE') !== false
                                )) {
                                    $keep = true;
                                }

                                if (!$keep) {
                                    $params_to_remove[] = $p->param;
                                }
                            }

                            foreach ($params_to_remove as $param_name) {
                                $this->forget($param_name);
                            }

                            report_modification('Converted cite journal to cite medRxiv');
                            return;
                        }
                    }
                    // Special odd cases go here
                    if ($periodical === 'TAXON') {
                        // All caps that should not be
                        $this->set($param, 'Taxon');
                        return;
                    }
                    // End special odd cases
                    if (mb_substr(mb_strtolower($periodical), 0, 7) === 'http://' || mb_substr(mb_strtolower($periodical), 0, 8) === 'https://') {
                        if ($this->blank('url')) {
                            $this->rename($param, 'url');
                        }
                        return;
                    } elseif (mb_substr(mb_strtolower($periodical), 0, 4) === 'www.') {
                        if ($this->blank('website')) {
                            $this->rename($param, 'website');
                        }
                        return;
                    }
                    if ($this->blank(['chapter', 'isbn']) && $param === 'journal' && mb_stripos($this->get($param), 'arxiv') === false) {
                        // Avoid renaming between cite journal and cite book
                        $this->change_name_to('cite journal');
                    }

                    if (
                        (mb_substr($periodical, 0, 2) !== "[[" || // Only remove partial wikilinks
                        mb_substr($periodical, -2) !== "]]" ||
                        mb_substr_count($periodical, '[[') !== 1 ||
                        mb_substr_count($periodical, ']]') !== 1) &&
                        !preg_match('~^(?:the |)(?:publications|publication|journal|transactions|letters|annals|bulletin|reports|history) of the ~i', $periodical) &&
                        !preg_match('~(magazin für |magazin fur |magazine for |section )~i', $periodical)
                    ) {
                        $this->set($param, preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $periodical));
                        $this->set($param, preg_replace(REGEXP_PIPED_WIKILINK, "$2", $this->get($param)));
                    }
                    $periodical = mb_trim($this->get($param));
                    if (mb_substr($periodical, 0, 1) !== "[" && mb_substr($periodical, -1) !== "]") {
                        if (mb_strlen($periodical) - mb_strlen($periodical) < 9) {
                            // eight or fewer UTF-8 stuff
                            if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $periodical . ' ') === ' ' . $periodical . ' ' && strip_diacritics($periodical) === $periodical) {
                                $periodical = mb_ucwords($periodical); // Found NO foreign words/phrase
                            }
                            $this->set($param, title_capitalization($periodical, true));
                        }
                    } elseif (mb_strpos($periodical, ":") !== 2) {
                        // Avoid inter-wiki links
                        if (preg_match(REGEXP_PLAIN_WIKILINK_ONLY, $periodical, $matches)) {
                            $periodical = $matches[1];
                            $periodical = str_replace("’", "'", $periodical); // Fix quotes for links
                            $this->set($param, '[[' . $periodical . ']]');
                            $new_periodical = title_capitalization(mb_ucwords($periodical), true);
                            if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $periodical . ' ') === ' ' . $periodical . ' ' && str_replace(['(', ')'], '', $periodical) === $periodical && $new_periodical !== $periodical) {
                                $now = WikipediaBot::is_redirect($periodical);
                                if ($now === -1) {
                                    // Dead link
                                    $this->set($param, '[[' . $new_periodical . ']]');
                                } elseif ($now === 1) {
                                    // Redirect
                                    if (WikipediaBot::is_redirect($new_periodical) === 0) {
                                        $this->set($param, '[[' . $new_periodical . ']]');
                                    }
                                }
                            }
                        } elseif (preg_match(REGEXP_PIPED_WIKILINK_ONLY, $periodical, $matches)) {
                            $linked_text = str_replace("’", "'", $matches[1]); // Fix quotes for links
                            $human_text = $matches[2];
                            if (preg_match("~^[\'\"]+([^\'\"]+)[\'\"]+$~", $human_text, $matches)) {
                                // Remove quotes
                                $human_text = $matches[1];
                            }
                            $new_linked_text = title_capitalization(mb_ucwords($linked_text), true);
                            if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $linked_text . ' ') === ' ' . $linked_text . ' ' && str_replace(['(', ')'], '', $linked_text) === $linked_text && $new_linked_text !== $linked_text) {
                                $now = WikipediaBot::is_redirect($linked_text);
                                if ($now === -1) {
                                    $linked_text = $new_linked_text; // Dead to something
                                } elseif ($now === 1) {
                                    if (WikipediaBot::is_redirect($new_linked_text) === 0) {
                                        $linked_text = $new_linked_text; // Redirect to actual page
                                    }
                                }
                            }
                            // We assume that human text is some kind of abbreviations that we really do not want to mess with
                            $periodical = '[[' . $linked_text . '|' . $human_text . ']]';
                            $this->set($param, $periodical);
                        }
                    }
                    if ($this->wikiname() === 'cite arxiv') {
                        $this->change_name_to('cite journal');
                    }
                    if ($this->is_book_series($param)) {
                        $this->change_name_to('cite book');
                        if ($this->blank('series')) {
                            $this->rename($param, 'series');
                        } elseif ($this->is_book_series('series') || str_equivalent($this->get($param), $this->get('series'))) {
                            $this->forget($param);
                        }
                    }
                    $the_param = $this->get($param);
                    if (preg_match(REGEXP_PLAIN_WIKILINK, $the_param, $matches) || preg_match(REGEXP_PIPED_WIKILINK, $the_param, $matches)) {
                        $the_param = $matches[1]; // Always the wikilink for easier standardization
                    }
                    if (in_array(mb_strtolower($the_param), ARE_MAGAZINES, true) && $this->blank(['pmc', 'doi', 'pmid'])) {
                        $this->change_name_to('cite magazine');
                        $this->rename($param, 'magazine');
                        return;
                    } elseif (in_array(mb_strtolower($the_param), ARE_NEWSPAPERS, true)) {
                        $this->change_name_to('cite news');
                        if ($param !== 'work') {
                            $this->rename($param, 'newspaper');
                        } // Grumpy people
                        return;
                    } elseif (in_array(mb_strtolower($the_param), ARE_WORKS, true)) {
                        if ($this->held_work_done) {
                            return;
                        }
                        $this->held_work_done = true;
                        $this->rename($param, 'CITATION_BOT_HOLDS_WORK');
                        $this->change_name_to('cite document');
                        $this->rename('CITATION_BOT_HOLDS_WORK', 'work');
                        return;
                    }
                    $the_title = $this->get('title');
                    $the_param = str_ireplace(['Proc. of ', 'Conf. on ', 'Proc. ', 'Conf. ', '3rd', '2nd', '1st', ' the the '], ['Proceedings of ', 'Conference on ', 'Proceedings of ', 'Conference on ', 'the third', 'the second', 'the first', ' the '], $the_param);
                    $the_title = str_ireplace(['Proc. of ', 'Conf. on ', 'Proc. ', 'Conf. ', '3rd', '2nd', '1st', ' the the '], ['Proceedings of ', 'Conference on ', 'Proceedings of ', 'Conference on ', 'the third', 'the second', 'the first', ' the '], $the_title);
                    foreach (CONFERENCE_LIST as $conf) {
                        if (mb_stripos($the_title, $conf) !== false && mb_stripos($the_title, $the_param) !== false) {
                            $this->forget($param);
                            return;
                        }
                        if (mb_stripos($the_title, $conf) !== false && mb_stripos($the_param, $conf) !== false && $this->wikiname() === 'cite book') {
                            $this->forget($param);
                            return;
                        }
                    }
                    if ($this->wikiname() === 'cite book' && $the_title === '') {
                        if (in_array($this->get($param), ['Automata, Languages and Programming'], true)) {
                            $this->rename($param, 'title');
                            return;
                        }
                    }
                    if ($this->wikiname() === 'cite book' && $this->blank('chapter')) {
                        /*
                        if (in_array($this->get($param), [], true)) {
                         $this->rename('title', 'chapter');
                         $this->rename($param, 'title');
                         return;
                        }
                        */
                        if (in_array($this->get('series'), ['Lecture Notes in Computer Science', 'Klassische Texte der Wissenschaft'], true)) {
                            $this->rename('title', 'chapter');
                            $this->rename($param, 'title');
                            return;
                        }
                    }

                    return;

                case 'jstor':
                    if ($this->blank($param)) {
                        return;
                    }
                    if (mb_substr($this->get($param), 0, 8) === '10.2307/') {
                        $this->set($param, mb_substr($this->get($param), 8));
                    } elseif (preg_match('~^https?://www\.jstor\.org/stable/(.*)$~', $this->get($param), $matches)) {
                        $this->set($param, $matches[1]);
                    }
                    $this->change_name_to('cite journal', false);
                    return;

                case 'magazine':
                    if ($this->blank($param)) {
                        return;
                    }
                    // Remember, we don't process cite magazine.
                    if ($this->wikiname() === 'cite journal' && !$this->has('journal')) {
                        $this->rename('magazine', 'journal');
                    }
                    return;

                case 'orig-year':
                case 'origyear':
                    if ($this->blank($param)) {
                        return;
                    }
                    if ($this->blank(['year', 'date'])) {
                        // Will not show unless one of these is set, so convert
                        if (preg_match('~^\d\d\d\d$~', $this->get($param))) {
                            // Only if a year, might contain text like "originally was...."
                            $this->rename($param, 'year');
                        }
                    }
                    return;

                case 'mr':
                    if (preg_match("~mr(\d+)$~i", $this->get($param), $matches)) {
                        $this->set($param, $matches[1]);
                    }
                    return;

                case 'day':
                    if ($this->blank($param)) {
                        $this->forget($param);
                    }
                    return;

                case 'others':
                    if ($this->blank($param)) {
                        $this->forget($param);
                    }
                    return;

                case 'pmc-embargo-date':
                    if ($this->blank($param)) {
                        return;
                    }
                    $value = $this->get($param);
                    if (!preg_match('~2\d\d\d~', $value)) {
                        $this->forget($param);
                        return;
                    }
                    $pmc_date = strtotime($value);
                    $now_date = strtotime('now') - 86400; // Pad one day for paranoia
                    if ($now_date > $pmc_date) {
                        $this->forget($param);
                    }
                    return;

                case 'pmc':
                    if (preg_match("~pmc(\d+)$~i", $this->get($param), $matches)) {
                        $this->set($param, $matches[1]);
                    }
                    if ($this->blank($param)) {
                        return;
                    }
                    $this->change_name_to('cite journal', false);
                    return;

                case 'pmid':
                    if ($this->blank($param)) {
                        return;
                    }
                    if ($this->get('url') === 'https://pubmed.ncbi.nlm.nih.gov/' . $this->get('pmid') ||
                    $this->get('url') === 'http://pubmed.ncbi.nlm.nih.gov/' . $this->get('pmid') ||
                    $this->get('url') === 'https://pubmed.ncbi.nlm.nih.gov/' . $this->get('pmid') . '/' ||
                    $this->get('url') === 'http://pubmed.ncbi.nlm.nih.gov/' . $this->get('pmid') . '/' ||
                    $this->get('url') === 'https://www.ncbi.nlm.nih.gov/pubmed/' . $this->get('pmid') ||
                    $this->get('url') === 'http://www.ncbi.nlm.nih.gov/pubmed/' . $this->get('pmid') ||
                    $this->get('url') === 'https://www.ncbi.nlm.nih.gov/pubmed/' . $this->get('pmid') . '/' ||
                    $this->get('url') === 'http://www.ncbi.nlm.nih.gov/pubmed/' . $this->get('pmid') . '/'
                    ) {
                        $this->forget('url');
                    }
                    $this->change_name_to('cite journal', false);
                    return;

                case 'publisher':
                    if ($this->wikiname() === 'cite journal' && $this->has('journal') && $this->has('title') && $this->blank($param)) {
                        $this->forget($param); // Not good to encourage adding this
                        return;
                    }
                    if ($this->wikiname() === 'cite journal' && $this->has('journal') && $this->has('title') && $this->has('doi')) {
                        $test_me = str_replace([']', '['], '', mb_strtolower($this->get($param)));
                        if (in_array($test_me, ['sciencedirect', 'science direct'], true)) {
                            // TODO add more
                            $this->forget($param);
                        }
                        return;
                    }
                    if (mb_stripos($this->get($param), 'proquest') !== false && mb_stripos($this->get($param), 'llc') === false && mb_stripos($this->get('title'), 'Magazines for Libraries') === false) {
                        $this->forget($param);
                        if ($this->blank('via')) {
                            $this_big_url = $this->get('url') . $this->get('thesis-url') . $this->get('thesisurl') . $this->get('chapter-url') . $this->get('chapterurl');
                            if (mb_stripos($this_big_url, 'proquest') !== false) {
                                $this->add_if_new('via', 'ProQuest');
                            }
                        }
                        return;
                    }
                    if ($this->blank($param)) {
                        return;
                    }
                    $publisher = mb_strtolower($this->get($param));
                    if (
                        $this->wikiname() === 'cite journal' &&
                        $this->has('journal') &&
                        $this->has('title') &&
                        !$this->blank(['pmc', 'pmid']) &&
                        (mb_strpos($publisher, 'national center for biotechnology information') !== false || mb_strpos($publisher, 'u.s. national library of medicine') !== false)
                    ) {
                        $this->forget($param);
                        return;
                    }
                    if (mb_substr($publisher, 0, 2) === '[[' && mb_substr($publisher, -2) === ']]' && mb_substr_count($publisher, '[[') === 1 && mb_substr_count($publisher, ']]') === 1) {
                        if (preg_match(REGEXP_PLAIN_WIKILINK, $publisher, $matches)) {
                            $publisher = $matches[1];
                        } elseif (preg_match(REGEXP_PIPED_WIKILINK, $publisher, $matches)) {
                            $publisher = $matches[2];
                        }
                        foreach (['journal', 'newspaper'] as $the_same) {
                            // Prefer wiki-linked
                            if (mb_strtolower($this->get($the_same)) === $publisher) {
                                $this->forget($the_same);
                                $this->rename($param, $the_same);
                                return;
                            }
                        }
                    }
                    if (mb_stripos($this->get('url'), 'maps.google') !== false && mb_stripos($publisher, 'google') !== false) {
                        $this->set($param, 'Google Maps'); // Case when Google actually IS a publisher
                        return;
                    }
                    if (mb_stripos($this->get('url'), 'developers.google.com') !== false && mb_stripos($publisher, 'google') !== false) {
                        $this->set($param, 'Google Inc.'); // Case when Google actually IS a publisher
                        return;
                    }
                    if (mb_stripos($this->get('url'), 'support.google.com') !== false && mb_stripos($publisher, 'google') !== false) {
                        $this->set($param, 'Google Inc.'); // Case when Google actually IS a publisher
                        return;
                    }
                    if (mb_stripos($publisher, 'google') !== false) {
                        $this_host = (string) parse_url($this->get('url'), PHP_URL_HOST);
                        if (mb_stripos($this_host, 'google') === false || mb_stripos($this_host, 'blog') !== false || mb_stripos($this_host, 'github') !== false) {
                            return; // Case when Google actually IS a publisher
                        }
                    }
                    if (preg_match('~\S+\s*\/\s*(?:|\[\[)Google News Archive~i', $publisher)) {
                        return; // this is Newspaper / Google News Archive
                    }
                    if ($publisher === 'pubmed' || $publisher === 'pubmed central') {
                        if ($this->has('doi') || $this->has('pmid')) {
                            $this->forget($param);
                            return;
                        }
                    }

                    foreach (NON_PUBLISHERS as $not_publisher) {
                        if (mb_stripos($publisher, $not_publisher) !== false) {
                            $this->forget($param);
                            return;
                        }
                    }
                    // It might not be a product/book, but a "top 100" list
                    if (mb_strtolower(str_replace(['[', ' ', ']'], '', $publisher)) === 'amazon.com') {
                        $all_urls = '';
                        foreach (ALL_URL_TYPES as $a_url_type) {
                            $all_urls .= $this->get($a_url_type);
                        }
                        $all_urls = mb_strtolower($all_urls);
                        if (mb_strpos($all_urls, '/dp/') !== false && mb_strpos($all_urls, '/feature/') === false && mb_strpos($all_urls, '/exec/obidos/') === false) {
                            $this->forget($param);
                            return;
                        }
                    }
                    if (str_replace(['[', ' ', ']'], '', $publisher) === 'google') {
                        $this->forget($param);
                        return;
                    }
                    if (mb_strtolower($this->get('journal')) === $publisher) {
                        $this->forget($param);
                        return;
                    }
                    if (mb_strtolower($this->get('newspaper')) === $publisher) {
                        $this->forget($param);
                        return;
                    }
                    if ($this->blank(WORK_ALIASES)) {
                        if (in_array(str_replace(['[', ']', '"', "'", 'www.'], '', $publisher), PUBLISHERS_ARE_WORKS, true)) {
                            if ($this->wikiname() !== 'cite book') {
                                $this->rename($param, 'work'); // Don't think about which work it is
                            }
                            return;
                        }
                    } elseif ($this->has('website')) {
                        if (in_array(str_replace(['[', ']', '"', "'", 'www.'], '', $publisher), PUBLISHERS_ARE_WORKS, true)) {
                            $webby = str_replace(['[', ']', '"', "'", 'www.', 'the ', '.com', ' '], '', mb_strtolower($this->get('website')));
                            $pubby = str_replace(['[', ']', '"', "'", 'www.', 'the ', '.com', ' '], '', $publisher);
                            if ($webby === $pubby) {
                                if (mb_stripos($this->get('website'), 'www') === 0 || mb_strpos($publisher, '[') !== false || mb_strpos($this->get('website'), '[') === false) {
                                    $this->forget('website');
                                    $this->rename($param, 'work');
                                    return;
                                }
                            }
                        }
                    }
                    if ($publisher === 'nytc') {
                        $publisher = 'new york times company';
                    }
                    if ($publisher === 'nyt') {
                        $publisher = 'new york times';
                    }
                    if ($publisher === 'wpc') {
                        $publisher = 'washington post company';
                    }
                    if (in_array(str_replace(['[', ']', '"', "'", 'www.', ' company', ' digital archive', ' communications llc'], '', $publisher), PUBLISHERS_ARE_WORKS, true)) {
                        $pubby = str_replace(['the ', ' company', ' digital archive', ' communications llc'], '', $publisher);
                        foreach (WORK_ALIASES as $work) {
                            $worky = str_replace(['the ', ' company', ' digital archive', ' communications llc'], '', mb_strtolower($this->get($work)));
                            if ($worky === $pubby) {
                                $this->forget($param);
                                return;
                            }
                        }
                    }

                    if (!$this->blank(['eprint', 'arxiv']) && mb_strtolower($publisher) === 'arxiv') {
                        $this->forget($param);
                        return;
                    }

                    if ($publisher === 'the times digital archive.') {
                        $this->set($param, 'The Times Digital Archive');
                        $publisher = 'the times digital archive';
                    }
                    if ($publisher === 'the times digital archive') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'the times') !== false || mb_stripos($this->get($work), 'times (london') !== false || mb_stripos($this->get($work), 'times [london') !== false) {
                                $this->forget($param);
                                return;
                            }
                        }
                    }
                    if ($this->blank('via') && $publisher === 'the washington post – via legacy.com') {
                        $publisher = 'the washington post';
                        $this->set($param, '[[The Washington Post]]');
                        $this->set('via', 'Legacy.com');
                    }
                    if (
                        $publisher === 'the washington post' ||
                        $publisher === 'washington post' ||
                        $publisher === 'the washington post company' ||
                        $publisher === 'the washington post websites' ||
                        $publisher === 'washington post websites' ||
                        $publisher === 'the washington post (subscription required)'
                    ) {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'washington post') !== false || mb_stripos($this->get($work), 'washingtonpost.com') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'washingtonpost.com') !== false) {
                                    $this->set($work, '[[The Washington Post]]');
                                }
                                return;
                            }
                        }
                        if (
                            in_array(
                                mb_strtolower($this->get('work')),
                                [
                                    'local',
                                    'editorial',
                                    'international',
                                    'national',
                                    'communication',
                                    'letter to the editor',
                                    'review',
                                    'coronavirus',
                                    'race & reckoning',
                                    'politics',
                                    'opinion',
                                    'opinions',
                                    'investigations',
                                    'tech',
                                    'technology',
                                    'world',
                                    'sports',
                                    'world',
                                    'arts & entertainment',
                                    'arts',
                                    'entertainment',
                                    'u.s.',
                                    'n.y.',
                                    'business',
                                    'science',
                                    'health',
                                    'books',
                                    'style',
                                    'food',
                                    'travel',
                                    'real estate',
                                    'magazine',
                                    'economy',
                                    'markets',
                                    'life & arts',
                                    'uk news',
                                    'world news',
                                    'health news',
                                    'lifestyle',
                                    'photos',
                                    'education',
                                    'arts',
                                    'life',
                                    'puzzles',
                                ],
                                true
                            ) &&
                            $this->blank('department')
                        ) {
                            $this->rename('work', 'department');
                            $this->rename($param, 'work');
                            return;
                        }
                    }

                    if ($publisher === 'the new york times' || $publisher === 'new york times' || $publisher === 'the new york times (subscription required)') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'new york times') !== false || mb_stripos($this->get($work), 'nytimes.com') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'nytimes.com') !== false) {
                                    $this->set($work, '[[The New York Times]]');
                                }
                                return;
                            }
                        }
                    }

                    if ($publisher === 'the guardian' || $publisher === 'the guardian media' || $publisher === 'the guardian media group' || $publisher === 'guardian media' || $publisher === 'guardian media group') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'The Guardian') !== false || mb_stripos($this->get($work), 'theguardian.com') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'theguardian.com') !== false) {
                                    $this->set($work, '[[The Guardian]]');
                                }
                                return;
                            }
                        }
                    }

                    if ($publisher === 'the economist' || $publisher === 'the economist group') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'the economist') !== false || mb_stripos($this->get($work), 'economist.com') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'economist.com') !== false) {
                                    $this->set($work, '[[The Economist]]');
                                }
                                return;
                            }
                        }
                    }

                    if ($publisher === 'news uk') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'the times') !== false || mb_stripos($this->get($work), 'thetimes.co.uk') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'thetimes.co.uk') !== false) {
                                    $this->set($work, '[[The Times]]');
                                }
                                return;
                            }
                        }
                    }

                    if ($publisher === 'san jose mercury news' || $publisher === 'san jose mercury-news') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'mercurynews.com') !== false || mb_stripos($this->get($work), 'mercury news') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'mercurynews.com') !== false) {
                                    $this->set($work, '[[San Jose Mercury News]]');
                                }
                                return;
                            }
                        }
                    }
                    if ($publisher === 'the san diego union-tribune, llc' || $publisher === 'the san diego union tribune, llc') {
                        $publisher = 'the san diego union-tribune';
                        $this->set($param, 'The San Diego Union-Tribune');
                    }
                    if ($publisher === 'the san diego union-tribune' || $publisher === 'the san diego union tribune' || $publisher === 'san diego union-tribune' || $publisher === 'san diego union tribune') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'sandiegouniontribune.com') !== false || mb_stripos($this->get($work), 'SignOnSanDiego.com') !== false || mb_stripos($this->get($work), 'san diego union') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'sandiegouniontribune.com') !== false || mb_stripos($this->get($work), 'SignOnSanDiego.com') !== false) {
                                    $this->set($work, '[[The San Diego Union-Tribune]]');
                                }
                                return;
                            }
                        }
                        if (
                            in_array(mb_strtolower($this->get('work')), DEPARTMENTS, true) &&
                            $this->blank('department')
                        ) {
                            $this->rename('work', 'department');
                            $this->rename($param, 'work');
                            return;
                        }
                    }

                    if ($publisher === 'forbes media llc' || $publisher === 'forbes media, llc' || $publisher === 'forbes media, llc.' || $publisher === 'forbes (forbes media)' || $publisher === 'forbes media llc.') {
                        $publisher = 'forbes media';
                        $this->set($param, 'Forbes Media');
                    }
                    if ($publisher === 'forbes inc' || $publisher === 'forbes inc.' || $publisher === 'forbes, inc' || $publisher === 'forbes, inc.' || $publisher === 'forbes.' || $publisher === 'forbes ny') {
                        $publisher = 'forbes';
                        $this->set($param, 'Forbes');
                    }
                    if ($publisher === 'forbes.com llc' || $publisher === 'forbes.com' || $publisher === 'forbes.com llc™' || $publisher === 'forbes.com llc.') {
                        $publisher = 'forbes';
                        $this->set($param, 'Forbes');
                    }
                    if ($publisher === 'forbes publishing' || $publisher === 'forbes publishing company' || $publisher === 'forbes publishing co' || $publisher === 'forbes publishing co.') {
                        $publisher = 'forbes publishing';
                        $this->set($param, 'Forbes Publishing');
                    }
                    if ($publisher === 'forbes publishing' || $publisher === 'forbes' || $publisher === 'forbes magazine' || $publisher === 'forbes media') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'forbes') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'forbes.com') !== false) {
                                    $this->set($work, '[[Forbes]]');
                                }
                                return;
                            }
                            if ($this->blank('agency')) {
                                if (mb_stripos($this->get($work), 'AFX News') !== false || mb_stripos($this->get($work), 'Thomson Financial News') !== false) {
                                    $this->rename($work, 'agency');
                                }
                            }
                        }
                    }

                    if ($publisher === 'la times' || $publisher === 'latimes' || $publisher === 'latimes.com' || $publisher === 'the la times' || $publisher === 'the los angeles times' || $publisher === '[[los angeles times]] (latimes.com)') {
                        $publisher = 'los angeles times';
                        if (mb_strpos($this->get($param), '[') !== false) {
                            $this->set($param, '[[Los Angeles Times]]');
                        } else {
                            $this->set($param, 'Los Angeles Times');
                        }
                    }
                    if ($publisher === 'los angeles times' || $publisher === 'los angeles times media group') {
                        foreach (WORK_ALIASES as $work) {
                            if (mb_stripos($this->get($work), 'latimes') !== false || mb_stripos($this->get($work), 'los angeles times') !== false) {
                                $this->forget($param);
                                if (mb_stripos($this->get($work), 'latimes') !== false) {
                                    $this->set($work, '[[Los Angeles Times]]');
                                }
                                return;
                            }
                            if ($this->blank('via')) {
                                if (mb_stripos($this->get($work), 'laweekly') !== false) {
                                    $this->rename($work, 'via');
                                }
                            }
                        }
                    }

                    foreach (WORK_ALIASES as $work) {
                        $worky = mb_strtolower($this->get($work));
                        $worky = str_replace(["[[", "]]"], "", $worky);
                        if (in_array($worky, NO_PUBLISHER_NEEDED, true)) {
                            $this->forget($param);
                            return;
                        }
                    }

                    if ($publisher === 'www.pressreader.com' || $publisher === 'pressreader.com' || $publisher === 'pressreader.com (archived)' || $publisher === 'pressreader' || $publisher === 'www.pressreader.com/') {
                        if ($this->blank('via')) {
                            $this->set($param, '[[PressReader]]');
                            $this->rename($param, 'via');
                        } elseif (mb_stripos($this->get('via'), 'pressreader') !== false) {
                            $this->forget($param);
                        }
                        return;
                    }

                    if ($publisher === 'www.sify.com' || $publisher === 'sify.com' || $publisher === 'sify') {
                        $this->set($param, '[[Sify]]');
                        $publisher = 'sify';
                    }
                    if (mb_stripos($publisher, 'sify.com') !== false || mb_stripos($publisher, 'sify ') !== false || $publisher === 'sify') {
                        if ($this->blank(WORK_ALIASES)) {
                            $this->rename($param, 'website');
                        } else {
                            $lower = "";
                            foreach (WORK_ALIASES as $worky) {
                                $lower .= mb_strtolower($this->get($worky));
                            }
                            if (mb_strpos($lower, 'sify') !== false) {
                                $this->forget($param);
                            }
                        }
                        return;
                    }

                    if ($publisher === 'www.bollywoodhungama.com' || $publisher === 'bollywoodhungama.com' || $publisher === 'bollywoodhungama' || $publisher === 'bollywood hungama') {
                        $this->set($param, '[[Bollywood Hungama]]');
                        $publisher = 'bollywood hungama';
                    }
                    if (mb_stripos($publisher, 'bollywoodhungama.com') !== false || mb_stripos($publisher, 'bollywood hungama') !== false || mb_stripos($publisher, 'BH News Network') !== false) {
                        if ($this->blank(WORK_ALIASES)) {
                            $this->rename($param, 'website');
                        } else {
                            $lower = "";
                            foreach (WORK_ALIASES as $worky) {
                                $lower .= mb_strtolower($this->get($worky));
                            }
                            if (mb_strpos($lower, 'bollywoodhungama') !== false || mb_strpos($lower, 'bollywood hungama') !== false) {
                                $this->forget($param);
                            } elseif ($lower === 'bh news network') {
                                foreach (WORK_ALIASES as $worky) {
                                    $this->forget($worky);
                                }
                                $this->rename($param, 'website');
                            }
                        }
                        $authtmp = $this->get('author');
                        if ($authtmp === 'Bollywood Hungama News Network' || $authtmp === 'Bollywood Hungama') {
                            $this->forget('author');
                        }
                        return;
                    }

                    return;

                case 'quote':
                    $quote = $this->get('quote');
                    if ($quote === '') {
                        return;
                    }
                    $quote_out = safe_preg_replace('~[\n\r]+~', ' ', $quote);
                    if ($quote_out !== $quote && $quote_out !== '') {
                        $this->set('quote', $quote_out);
                    }
                    return;

                case 'quotes':
                    switch (mb_strtolower(mb_trim($this->get($param)))) {
                        case 'yes':
                        case 'y':
                        case 'true':
                        case 'no':
                        case 'n':
                        case 'false':
                            $this->forget($param);
                    }
                    return;

                case 'ref':
                    if ($this->has_but_maybe_blank($param)) {
                        $content = mb_strtolower($this->get($param));
                        if ($content === '' || $content === 'harv') {
                            $this->mod_ref = true;
                            $this->forget($param);
                        } elseif (preg_match('~^harv( *# # # CITATION_BOT_PLACEHOLDER_COMMENT.*?# # #)$~sui', $content, $matches)) {
                            $this->set($param, $matches[1]); // Sometimes it is ref=harv <!-- {{harvid|....}} -->
                        }
                    }
                    return;

                case 'series':
                    if (str_equivalent($this->get($param), $this->get('work'))) {
                        $this->forget('work');
                    }
                    if ($this->is_book_series('series')) {
                        $this->change_name_to('cite book');
                        if ($this->has('journal')) {
                            if ($this->is_book_series('journal') || str_equivalent($this->get('series'), $this->get('journal'))) {
                                $this->forget('journal');
                            }
                        }
                    }
                    return;

                case 'book-title':
                    if ($this->wikiname() === 'cite book') {
                        if ($this->blank('title')) {
                            $this->rename('book-title', 'title');
                        } elseif ($this->blank(CHAPTER_ALIASES)) {
                            $this->rename('title', 'chapter');
                            $this->rename('book-title', 'title');
                        } elseif (titles_are_similar($this->get('title'), $this->get('book-title'))) {
                            $this->forget('book-title');
                        }
                    }
                    return;

                case 'title':
                    if ($this->blank($param)) {
                        return;
                    }
                    $title = $this->get($param);
                    // Check for MathML
                    if (preg_match('~<(?:mml:)?m(?:sup|sub|subsup|frac|root|under|over|underover|row|i|n|o|text|multiscripts)[\s>]~', $title)) {
                        report_warning("Title contains MathML markup that should be converted to LaTeX: " . echoable(mb_substr($title, 0, 100)));
                    }
                    if (preg_match('~^(.+) # # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # # Reuters(?:|\.com)$~i', $title, $matches)) {
                        if (mb_stripos($this->get('agency') . $this->get('work') . $this->get('website') . $this->get('newspaper') . $this->get('website') . $this->get('publisher'), 'reuters') !== false) {
                            $title = $matches[1];
                            $this->set('title', $title);
                        }
                    }
                    if ($title === 'Validate User' || $title === 'Join Ancestry' || $title === 'Join Ancestry.com' || $title === 'Ancestry - Sign Up') {
                        $this->set('title', '');
                        return;
                    }
                    $title = straighten_quotes($title, false);
                    if ((mb_substr($title, 0, 1) === '"' && mb_substr($title, -1) === '"' && mb_substr_count($title, '"') === 2) || (mb_substr($title, 0, 1) === "'" && mb_substr($title, -1) === "'" && mb_substr_count($title, "'") === 2)) {
                        report_warning("The quotes around the title are most likely an editor's error: " . echoable(mb_substr($title, 1, -1)));
                    }
                    // Messed up cases: [[sdfsad] or [dsfasdf]]
                    if (preg_match('~^\[\[([^\]\[\|]+)\]$~', $title, $matches) || preg_match('~^\[([^\]\[\|]+)\]\]$~', $title, $matches)) {
                        $title = $matches[1];
                    }
                    // Only do for cite book, since might be title="A review of the book Bob (Robert Edition)"
                    if ($this->wikiname() === 'cite book' && $this->blank('edition') && preg_match('~^(.+)\(([^\(\)]+) edition\)$~i', $title, $matches)) {
                        $title = mb_trim($matches[1]);
                        $this->add_if_new('edition', mb_trim($matches[2]));
                    }
                    if (
                        mb_substr_count($title, '[[') !== 1 || // Completely remove multiple wikilinks
                        mb_substr_count($title, ']]') !== 1
                    ) {
                        if (mb_stripos($title, 'reviewed work') === false) {
                            $title = preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $title); // Convert [[X]] wikilinks into X
                            $title = preg_replace(REGEXP_PIPED_WIKILINK, "$2", $title); // Convert [[Y|X]] wikilinks into X
                            $title = preg_replace("~\[\[~", "", $title); // Remove any extra [[ or ]] that should not be there
                            $title = preg_replace("~\]\]~", "", $title);
                        }
                    } elseif (mb_strpos($title, '{{!}}') === false) {
                        // Convert a single link to a title-link
                        if (preg_match(REGEXP_PLAIN_WIKILINK, $title, $matches)) {
                            if (mb_strlen($matches[1]) > 0.7 * (float) mb_strlen($title) && $title !== '[[' . $matches[1] . ']]') {
                                // Only add as title-link if a large part of title text
                                $matches[2] = str_replace(["[[", "]]"], "", $title);
                                if ($matches[2] === "''" . $matches[1] . "''") {
                                    $title = $matches[1];
                                    $this->set('title-link', $matches[1]);
                                } else {
                                    $title = '[[' . $matches[1] . "|" . $matches[2] . ']]';
                                }
                            }
                        } elseif (preg_match(REGEXP_PIPED_WIKILINK_ONLY, $title, $matches) && mb_strpos($title, ':') === false) {
                            // Avoid touching inter-wiki links
                            if ($matches[1] === $matches[2] && $title === $matches[0]) {
                                $title = '[[' . $matches[1] . ']]'; // Clean up double links
                            }
                        }
                    }
                    $this->set($param, $title);
                    if ($title && str_equivalent($this->get($param), $this->get('work'))) {
                        $this->forget('work');
                    }
                    if ($title && str_equivalent($this->get($param), $this->get('encyclopedia'))) {
                        $this->forget($param);
                    }
                    if ($title && str_equivalent($this->get($param), $this->get('encyclopaedia'))) {
                        $this->forget($param);
                    }
                    if (preg_match('~^(.+)\{\{!\}\} Request PDF$~i', mb_trim($this->get($param)), $match)) {
                        $this->set($param, mb_trim($match[1]));
                    } elseif (!$this->blank(['isbn', 'doi', 'pmc', 'pmid']) && preg_match('~^(.+) \(PDF\)$~i', mb_trim($this->get($param)), $match)) {
                        $this->set($param, mb_trim($match[1])); // Books/journals probably don't end in (PDF)
                    }

                    if (preg_match("~^(.+national conference) on \-$~i", $this->get($param), $matches)) {
                        $this->set($param, mb_trim($matches[1])); // ACM conference titles
                    }
                    if (preg_match("~^in (Proc\.? .+)$~i", $this->get($param), $matches)) {
                        $this->set($param, mb_trim($matches[1]));
                    }
                    return;

                case 'archivedate':
                    if ($this->has('archivedate') && $this->get('archive-date') === $this->get('archivedate')) {
                        $this->forget('archivedate');
                    }
                    return;

                case 'archive-url':
                case 'archiveurl':
                    if ($this->blank(['archive-date', 'archivedate'])) {
                        if (preg_match('~^https?://(?:web\.archive\.org/web/|archive\.today/|archive\.\S\S/|webarchive\.loc\.gov/all/|www\.webarchive\.org\.uk/wayback/archive/)(\d{4})(\d{2})(\d{2})\d{6}~', $this->get($param), $matches)) {
                               $this->add_if_new('archive-date', $matches[1] . '-' . $matches[2] . '-' . $matches[3]);
                        }
                        if (preg_match('~^https?://wayback\.archive\-it\.org/\d{4}/(\d{4})(\d{2})(\d{2})\d{6}~', $this->get($param), $matches)) {
                            $this->add_if_new('archive-date', $matches[1] . '-' . $matches[2] . '-' . $matches[3]);
                        }
                    }
                    if (preg_match('~^(?:web\.|www\.).+$~', $this->get($param), $matches) && mb_stripos($this->get($param), 'citation') === false) {
                        $this->set($param, 'http://' . $matches[0]);
                    }
                    if (
                        preg_match('~^https?://(?:web\.archive\.org/web|archive\.today|archive\.\S\S|webarchive\.loc\.gov/all|www\.webarchive\.org\.uk/wayback/archive)/(?:save|\*)/~', $this->get($param)) ||
                        preg_match('~https://www\.bloomberg\.com/tosv2\.html~', $this->get($param)) ||
                        preg_match('~googleads\.g\.doubleclick\.net~', $this->get($param)) ||
                        preg_match('~https://apis\.google\.com/js/plusone\.js$~', $this->get($param)) ||
                        preg_match('~https?://www\.britishnewspaperarchive\.co\.uk/account/register~', $this->get($param)) ||
                        preg_match('~https://www\.google\-analytics\.com/ga\.js$~', $this->get($param)) ||
                        preg_match('~academic\.oup\.com/crawlprevention~', $this->get($param)) ||
                        preg_match('~ancestryinstitution~', $this->get($param)) ||
                        preg_match('~ancestry\.com/cs/offers~', $this->get($param)) ||
                        preg_match('~myprivacy\.dpgmedia\.nl~', $this->get($param)) ||
                        preg_match('~^https://web\.archive\.org/web/\d+/https://wikipedialibrary\.wmflabs\.org/\?next_url=/ezproxy/~', $this->get($param)) ||
                        preg_match('~https://meta\.wikimedia\.org/w/index\.php\?title\=Special\:UserLogin~', $this->get($param))
                    ) {
                        $this->forget($param);
                        if ($this->get('title') === 'Validate User') {
                            $this->set('title', '');
                        }
                        return;
                    }
                    if (preg_match('~^(https?://(?:www\.|)webcitation\.org/)([0-9a-zA-Z]{9})(?:|\?url=.*)$~', $this->get($param), $matches)) {
                        // $this->set($param, $matches[1] . $matches[2]); // The url part is actually NOT binding, but other wikipedia bots check it
                        if ($this->blank(['archive-date', 'archivedate'])) {
                            $base62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                            $num62 = mb_str_split($matches[2]);
                            $time = 0;
                            for ($i = 0; $i < 9; $i++) {
                                $time = 62 * $time + (int) mb_strpos($base62, $num62[$i]);
                            }
                            $this->add_if_new('archive-date', date("Y-m-d", (int) ($time / 1000000)));
                        }
                        return;
                    }
                    if (mb_stripos($this->get($param), 'archive') === false) {
                        if ($this->get($param) === $this->get('url')) {
                            $this->forget($param); // The archive url is the real one
                            return;
                        }
                    }
                    // Clean up a bunch on non-archive URLs
                    if (
                        mb_stripos($this->get($param), 'archive') === false &&
                        mb_stripos($this->get($param), 'webcitation') === false &&
                        mb_stripos($this->get($param), 'perma.') === false &&
                        mb_stripos($this->get($param), 'wayback') === false &&
                        mb_stripos($this->get($param), 'webharvest') === false &&
                        mb_stripos($this->get($param), 'freezepage') === false &&
                        mb_stripos($this->get($param), 'petabox.bibalex.org') === false
                    ) {
                        not_an_archive_url_clean($this, $param);
                    }
                    if ($this->blank(ALL_URL_TYPES)) {
                        if (preg_match("~^https?://web\.archive\.org/web/\d{14}/(https?://.*)$~", $this->get($param), $match)) {
                            report_modification('Extracting URL from archive');
                            $this->add_if_new('url', $match[1]);
                        }
                    }
                    // Remove trailing #
                    if (preg_match("~^(\S+)#$~u", $this->get($param), $matches)) {
                        $this->set($param, $matches[1]);
                        foreach (ALL_URL_TYPES as $url_types) {
                            if (preg_match("~^(\S+)#$~u", $this->get($url_types), $matches)) {
                                $this->set($url_types, $matches[1]);
                            }
                        }
                    }
                    return;

                case 'chapter-url':
                case 'chapterurl':
                    if ($this->blank($param)) {
                        return;
                    }
                    if ($this->blank('url') && $this->blank(CHAPTER_ALIASES_AND_SCRIPT)) {
                        $this->rename($param, 'url');
                        $param = 'url';
                    }
                    // no break
                case 'url':
                    clean_existing_urls($this, $param);
                    return;

                case 'work':
                    if (
                        $this->has('work') &&
                        (str_equivalent($this->get('work'), $this->get('series')) ||
                        str_equivalent($this->get('work'), $this->get('title')) ||
                        str_equivalent($this->get('work'), $this->get('journal')) ||
                        str_equivalent($this->get('work'), $this->get('website')))
                    ) {
                        $this->forget('work');
                        return;
                    }
                    if ($this->get('work') === 'The Times Digital Archive') {
                        $this->set('work', '[[The Times]]');
                    }
                    if (mb_strtolower($this->get('work')) === 'latimes' || mb_strtolower($this->get('work')) === 'latimes.com') {
                        $this->set('work', '[[Los Angeles Times]]');
                    }
                    if (mb_strtolower($this->get('work')) === 'nytimes' || mb_strtolower($this->get('work')) === 'nytimes.com') {
                        $this->set('work', '[[The New York Times]]');
                    }

                    switch ($this->wikiname()) {
                        case 'cite book':
                            $work_becomes = 'title';
                            break;
                        case 'cite journal':
                            $work_becomes = 'journal';
                            break;
                        // case 'cite web': $work_becomes = 'website'; break;  this change should correct, but way too much crap gets put in work that does not belong there.  Secondly this make no change to the what the user sees
                        default:
                            $work_becomes = 'work';
                    }
                    if ($this->has('url') && str_replace(ENCYCLOPEDIA_WEB, '', $this->get('url')) !== $this->get('url')) {
                        $work_becomes = 'encyclopedia';
                    }
                    if ($this->has('work') && str_ireplace(['encyclopedia', 'encyclopædia', 'encyclopaedia'], '', $this->get('work')) !== $this->get('work')) {
                        $work_becomes = 'encyclopedia';
                    }

                    if ($this->has_but_maybe_blank($param) && $this->blank($work_becomes)) {
                        if ($work_becomes === 'encyclopedia' && $this->wikiname() === 'cite web') {
                            $this->change_name_to('cite encyclopedia');
                        }
                        if ($work_becomes !== 'encyclopedia' || in_array($this->wikiname(), ['cite dictionary', 'cite encyclopedia', 'citation'], true)) {
                            $this->rename('work', $work_becomes); // encyclopedia=XYZ only valid in some citation types
                        }
                    }
                    if ($this->wikiname() === 'cite book') {
                        $publisher = mb_strtolower($this->get($param));
                        foreach (NON_PUBLISHERS as $not_publisher) {
                            if (mb_stripos($publisher, $not_publisher) !== false) {
                                $this->forget($param);
                                return;
                            }
                        }
                        if (mb_stripos($publisher, 'amazon') !== false) {
                            $this->forget($param);
                            return;
                        }
                    }
                    if ($this->blank('agency') && in_array(mb_strtolower(str_replace(['[', ']', '.'], '', $this->get($param))), ['reuters', 'associated press'], true)) {
                        $the_url = '';
                        foreach (ALL_URL_TYPES as $thingy) {
                            $the_url .= $this->get($thingy);
                        }
                        if (mb_stripos($the_url, 'reuters.co') === false && mb_stripos($the_url, 'apnews.co') === false) {
                            $this->rename($param, 'agency');
                        }
                    }
                    $the_param = $this->get($param);
                    if (preg_match(REGEXP_PLAIN_WIKILINK, $the_param, $matches) || preg_match(REGEXP_PIPED_WIKILINK, $the_param, $matches)) {
                        $the_param = $matches[1]; // Always the wikilink for easier standardization
                    }
                    if (in_array(mb_strtolower($the_param), ARE_MAGAZINES, true) && $this->blank(['pmc', 'doi', 'pmid'])) {
                        $this->change_name_to('cite magazine');
                        $this->rename($param, 'magazine');
                        return;
                    } elseif (in_array(mb_strtolower($the_param), ARE_NEWSPAPERS, true)) {
                        $this->change_name_to('cite news');
                        $this->rename($param, 'newspaper');
                        return;
                    }

                    if (mb_strtolower($the_param) === 'www.pressreader.com' || mb_strtolower($the_param) === 'pressreader.com' || mb_strtolower($the_param) === 'pressreader.com (archived)' || mb_strtolower($the_param) === 'www.pressreader.com/') {
                        if ($this->blank('via')) {
                            $this->set($param, 'PressReader');
                            $this->rename($param, 'via');
                        } elseif (mb_stripos($this->get('via'), 'pressreader') !== false) {
                            $this->forget($param);
                        }
                    }

                    return;

                case 'via': // Should just remove all 'via' with no url, but do not want to make people angry
                    if ($this->blank(ALL_URL_TYPES)) {
                        // Include blank via
                        if (mb_stripos($this->get('via'), 'PubMed') !== false && ($this->has('pmc') || $this->has('pmid'))) {
                               $this->forget('via');
                        } elseif (mb_stripos($this->get('via'), 'JSTOR') !== false && $this->has('jstor')) {
                            $this->forget('via');
                        } elseif (mb_stripos($this->get('via'), 'google book') !== false && $this->has('isbn')) {
                            $this->forget('via');
                        } elseif (mb_stripos($this->get('via'), 'questia') !== false && $this->has('isbn')) {
                            $this->forget('via');
                        } elseif (mb_stripos($this->get('via'), 'library') !== false) {
                            $this->forget('via');
                        } elseif (in_array($this->wikiname(), ['cite arxiv', 'cite biorxiv', 'cite citeseerx', 'cite ssrn', 'cite medrxiv'], true)) {
                            $this->forget('via');
                        } elseif (
                            $this->has('pmc') ||
                            $this->has('pmid') ||
                            ($this->has('doi') && $this->blank(DOI_BROKEN_ALIASES)) ||
                            $this->has('jstor') ||
                            $this->has('arxiv') ||
                            $this->has('isbn') ||
                            ($this->has('issn') && $this->has('title')) ||
                            $this->has('oclc') ||
                            $this->has('lccn') ||
                            $this->has('bibcode')
                        ) {
                            $via = mb_trim(str_replace(['[', ']'], '', mb_strtolower($this->get('via'))));
                            if (in_array($via, BAD_VIA, true)) {
                                $this->forget('via');
                                return;
                            }
                        }
                    }
                    if ($this->blank('via')) {
                        return;
                    }
                    foreach (array_merge(['publisher'], WORK_ALIASES) as $others) {
                        if ($this->has($others)) {
                            if (str_equivalent($this->get($others), $this->get('via')) || (mb_stripos($this->get($others), 'bbc') !== false && mb_stripos($this->get('via'), 'bbc')) !== false) {
                                $this->forget('via');
                                return;
                            }
                        }
                    }
                    if (str_i_same('DOI.org (Crossref)', $this->get('via'))) {
                        $this->forget('via');
                    }
                    if (str_i_same('researchgate', $this->get('via'))) {
                        $this->set('via', 'ResearchGate');
                    }
                    return;
                case 'volume':
                    if ($this->blank($param)) {
                        return;
                    }
                    if ($this->get($param) === 'Online First') {
                        $this->forget($param);
                        return;
                    }
                    $temp_string = mb_strtolower($this->get('journal'));
                    if (mb_substr($temp_string, 0, 2) === "[[" && mb_substr($temp_string, -2) === "]]") {
                        // Wikilinked journal title
                        $temp_string = mb_substr(mb_substr($temp_string, 2), 0, -2); // Remove [[ and ]]
                        $temp_string = preg_replace('~^.+\|~', '', $temp_string); // Remove part before pipe, if it has one
                    }
                    if (in_array($temp_string, HAS_NO_VOLUME, true)) {
                        if ($this->blank(ISSUE_ALIASES)) {
                            $this->rename('volume', 'issue');
                        } else {
                            $this->forget('volume');
                            return;
                        }
                    }
                    if (in_array($temp_string, PREFER_VOLUMES, true) && $this->has('volume')) {
                        if ($this->get('volume') === $this->get('issue')) {
                            $this->forget('issue');
                        } elseif ($this->get('volume') === $this->get('number')) {
                            $this->forget('number');
                        }
                    }
                    if (in_array($temp_string, PREFER_ISSUES, true) && $this->has('volume')) {
                        if ($this->get('volume') === $this->get('issue')) {
                            $this->forget('volume');
                        } elseif ($this->get('volume') === $this->get('number')) {
                            $this->forget('volume');
                        }
                    }
                    // Remove leading zeroes
                    $value = $this->get($param);
                    if ($value !== '' && $value !== '0') { // Single zero is valid for some CS journals
                        $value = safe_preg_replace('~^0+~', '', $value);
                        if ($value === '') {
                            $this->forget($param); // Was all zeros
                        }
                    }
                    $this->volume_issue_demix($this->get($param), $param);
                    return;

                case 'year':
                    if ($this->blank('year')) {
                        if ($this->has('date')) {
                            $this->forget('year');
                        }
                        return;
                    }
                    if ($this->get('year') === $this->get('date')) {
                        $this->forget('year');
                        return;
                    }
                    if (preg_match("~\d\d*\-\d\d*\-\d\d*~", $this->get('year'))) {
                        // We have more than one dash, must not be range of years.
                        if ($this->blank('date')) {
                            $this->rename('year', 'date');
                        }
                        $this->forget('year');
                        return;
                    }
                    if (preg_match("~[A-Za-z][A-Za-z][A-Za-z]~", $this->get('year'))) {
                        // At least three letters
                        if ($this->blank('date')) {
                            $this->rename('year', 'date');
                        }
                        $this->forget('year');
                        return;
                    }
                    if (preg_match("~^(\d{4})\.$~", $this->get($param), $matches)) {
                        $this->set($param, $matches[1]); // trailing period
                        return;
                    }
                    if ($this->get($param) === 'n.d.') {
                        return;
                    } // Special no-date code that citation template recognize.
                    // Issue should follow year with no break.  [A bit of redundant execution but simpler.]
                    // no break
                case 'issue':
                case 'number':
                    if ($this->blank($param)) {
                        return;
                    }
                    $value = $this->get($param);
                    if ($value === 'Online First') {
                        $this->forget($param);
                        return;
                    }
                    if ($param === 'issue' || $param === 'number') {
                        if (preg_match('~^(?:iss\.|iss|issue|number|num|num\.|no|no:|no\.|№|№\.)\s*(\d+)$~iu', $value, $matches)) {
                            $value = $matches[1];
                        }
                    }
                    // Remove leading zeroes
                    if ($value && $this->get('journal') !== 'Insecta Mundi') {
                        $value = safe_preg_replace('~^0+~', '', $value);
                        if ($value === '') {
                            $this->forget($param); // Was all zeros
                        }
                    }
                    if ($value) {
                        $this->set($param, $value);
                    } else {
                        if (!$this->blank($param)) {
                            $this->forget($param);
                        }
                        return;
                    }
                    if ($param === 'issue' || $param === 'number') {
                        $this->volume_issue_demix($this->get($param), $param);
                        if ($this->blank($param)) {
                            $this->forget($param);
                            return;
                        }
                        $temp_string = mb_strtolower($this->get('journal'));
                        if (mb_substr($temp_string, 0, 2) === "[[" && mb_substr($temp_string, -2) === "]]") {
                            // Wikilinked journal title
                            $temp_string = mb_substr(mb_substr($temp_string, 2), 0, -2); // Remove [[ and ]]
                            $temp_string = preg_replace('~^.+\|~', '', $temp_string); // Remove part before pipe, if it has one
                        }
                        if (in_array($temp_string, HAS_NO_ISSUE, true)) {
                            if ($this->blank('volume')) {
                                $this->rename($param, 'volume');
                            } else {
                                $this->forget($param);
                            }
                            return;
                        }
                        if (in_array($temp_string, PREFER_VOLUMES, true) && $this->has('volume')) {
                            if ($this->get('volume') === $this->get($param)) {
                                $this->forget($param);
                                return;
                            }
                        }
                        if (in_array($temp_string, PREFER_ISSUES, true) && $this->has('volume')) {
                            if ($this->get('volume') === $this->get($param)) {
                                $this->forget('volume');
                                return;
                            }
                        }
                    }
                    // no break; pages, issue and year (the previous case) should be treated in this fashion.
                case 'pages':
                case 'page':
                case 'p':
                case 'pp': // And cases 'year' and'issue' following from previous
                    $value = $this->get($param);
                    $value = str_replace('--', '-', $value);
                    if (str_i_same('null', $value)) {
                        $this->forget($param);
                        return;
                    }
                    if (str_i_same('n.p', $value)) {
                        $this->set($param, 'n.p.'); // clean up after REMOVE_PERIOD
                        return;
                    }
                    if (mb_strpos($value, "[//") === 0) {
                        // We can fix them, if they are the very first item
                        $value = "[https://" . mb_substr($value, 3);
                        $this->set($param, $value);
                    }
                    if (preg_match('~^p\.?p\.?[(?:&nbsp;)\s]*(\d+[–-]?\d+)$~ui', $value, $matches)) {
                        $value = $matches[1];
                        $this->set($param, $value);
                    }
                    if (preg_match('~^pages?[\.\:]? *(\d+[–-]?\d+)$~ui', $value, $matches)) {
                        $value = $matches[1];
                        $this->set($param, $value);
                    }
                    if (preg_match('~^p\.? +(\d+[–-]?\d+)$~ui', $value, $matches)) {
                        $value = $matches[1];
                        $this->set($param, $value);
                    }
                    if (preg_match('~^p\. *(\d+[–-]?\d+)$~ui', $value, $matches)) {
                        $value = $matches[1];
                        $this->set($param, $value);
                    }
                    if (preg_match('~^p\. *(\d+)$~ui', $value, $matches)) {
                        $value = $matches[1];
                        $this->set($param, $value);
                    }
                    if (preg_match('~^pp?\. ([0-9\-\–\s\,]+)$~', $value, $matches)) {
                        $value = $matches[1];
                        $this->set($param, $value);
                    }
                    if (!preg_match("~^[A-Za-z ]+\-~", $value) && mb_ereg(REGEXP_TO_EN_DASH, $value) && can_safely_modify_dashes($value) && $pmatch[1] !== 'page') {
                        $this->mod_dashes = true;
                        report_modification("Upgrading to en-dash in " . echoable($param) . " parameter");
                        $value = mb_ereg_replace(REGEXP_TO_EN_DASH, REGEXP_EN_DASH, $value);
                        $this->set($param, $value);
                    }
                    if (
                        mb_substr_count($value, "–") === 1 && // Exactly one EN_DASH.
                        can_safely_modify_dashes($value)
                    ) {
                        if ($pmatch[1] === 'page') {
                            $bad = true;
                            if (preg_match('~^(\d+)\–(\d+)$~', $value, $matches_dash)) {
                                $part1 = (int) $matches_dash[1];
                                $part2 = (int) $matches_dash[2];
                                if ($matches_dash[1][0] !== '0' && $matches_dash[2][0] !== '0' && $part1 < $part2 && $part1 > 9) {
                                    // Probably not a section
                                    $this->rename($param, 'pages');
                                    $bad = false;
                                }
                            }
                            if ($bad) {
                                report_warning('Perhaps page= of ' . echoable($value) . ' is actually a page range. If so, change to pages=, otherwise change minus sign to {{hyphen}}');
                            }
                        } else {
                            $the_dash = (int) mb_strpos($value, "–"); // ALL must be mb_ functions because of long dash
                            $part1 = mb_trim(mb_substr($value, 0, $the_dash));
                            $part2 = mb_trim(mb_substr($value, $the_dash + 1));
                            if ($part1 === $part2) {
                                $this->set($param, $part1);
                            } elseif (is_numeric($part1) && is_numeric($part2)) {
                                $this->set($param, $part1 . "–" . $part2); // Remove any extra spaces
                            }
                        }
                    }
                    if (mb_strpos($this->get($param), '&') === false) {
                        $this->set($param, safe_preg_replace("~^[.,;]*\s*(.*?)\s*[,.;]*$~", "$1", $this->get($param)));
                    } else {
                        $this->set($param, safe_preg_replace("~^[.,;]*\s*(.*?)\s*[,.]*$~", "$1", $this->get($param))); // Not trailing ;
                    }
                    if (mb_substr($this->get($param), -4) === ' etc') {
                        $this->set($param, $this->get($param) . '.');
                    }

                    if ($param === 'page' || $param === 'pages') {
                        if (preg_match("~^pg\.? +(\d+)$~i", $this->get($param), $matches) || preg_match("~^pg\.? +(\d+–\d+)$~iu", $this->get($param), $matches)) {
                            $this->set($param, $matches[1]);
                        }
                    }
                    return;

                case 'pages totales':
                    if ($this->blank($param) || !$this->blank(PAGE_ALIASES)) {
                        $this->forget($param);
                    }
                    return;

                case 'postscript':
                    if ($this->wikiname() !== 'citation' && $this->get('mode') !== 'cs2' && $this->get($param) === '.') {
                        $this->forget($param); // Default action does not need specified
                    }
                    if ($this->wikiname() !== 'citation' && $this->blank($param)) {
                        $this->forget($param);
                    } // Misleading -- blank means default, not blank!!!!
                    return;

                case 'website':
                    if ($this->get($param) === 'Undefined' || $this->get($param) === 'undefined' || $this->get($param) === 'myprivacy.dpgmedia.nl') {
                        $this->forget($param);
                        return;
                    }
                    if ($this->wikiname() === 'cite book') {
                        if (str_i_same($this->get($param), 'google.com') ||
                         str_i_same($this->get($param), 'Google Books') ||
                         str_i_same($this->get($param), 'Google Book') ||
                         mb_stripos($this->get($param), 'Books.google.') === 0
                        ) {
                            $this->forget($param);
                            return;
                        }
                        if ($this->has('doi') && (
                        str_i_same($this->get($param), 'Springerlink') ||
                        str_i_same($this->get($param), 'elsevier') ||
                        str_i_same($this->get($param), 'wiley') ||
                        str_i_same($this->get($param), 'sciencedirect') ||
                        str_i_same($this->get($param), 'science direct') ||
                        str_i_same($this->get($param), 'Wiley Online Library'))
                        ) {
                            $this->forget($param);
                            return;
                        }
                    }
                    if (mb_stripos($this->get($param), 'archive.org') !== false && mb_stripos($this->get('url') . $this->get('chapter-url') . $this->get('chapterurl'), 'archive.org') === false) {
                        $this->forget($param);
                        return;
                    }
                    if ($this->wikiname() === 'cite arxiv' || $this->has('eprint') || $this->has('arxiv')) {
                        if (str_i_same($this->get($param), 'arxiv')) {
                            $this->forget($param);
                            return;
                        }
                    }
                    if (mb_strtolower($this->get($param)) === 'latimes' || mb_strtolower($this->get($param)) === 'latimes.com') {
                        $this->set($param, '[[Los Angeles Times]]');
                        return;
                    }
                    if (mb_strtolower($this->get($param)) === 'nytimes' || mb_strtolower($this->get($param)) === 'nytimes.com') {
                        $this->set($param, '[[The New York Times]]');
                        return;
                    }

                    if ($this->get($param) === 'The Times Digital Archive') {
                        $this->set($param, '[[The Times]]');
                        return;
                    }
                    $the_param = $this->get($param);
                    if (preg_match(REGEXP_PLAIN_WIKILINK, $the_param, $matches) || preg_match(REGEXP_PIPED_WIKILINK, $the_param, $matches)) {
                        $the_param = $matches[1]; // Always the wikilink for easier standardization
                    }
                    if (in_array(mb_strtolower($the_param), ARE_MAGAZINES, true)) {
                        $this->change_name_to('cite magazine');
                        $this->rename($param, 'magazine');
                        return;
                    } elseif (in_array(mb_strtolower($the_param), ARE_NEWSPAPERS, true)) {
                        $this->change_name_to('cite news');
                        $this->rename($param, 'newspaper');
                        return;
                    }
                    if ((mb_strtolower($the_param) === 'www.britishnewspaperarchive.co.uk' || mb_strtolower($the_param) === 'britishnewspaperarchive.co.uk') && $this->blank('via')) {
                        $this->set($param, '[[British Newspaper Archive]]');
                        $this->rename($param, 'via');
                        return;
                    }
                    if (mb_strtolower($the_param) === 'www.pressreader.com' || mb_strtolower($the_param) === 'pressreader.com' || mb_strtolower($the_param) === 'pressreader.com (archived)' || mb_strtolower($the_param) === 'www.pressreader.com/') {
                        if ($this->blank('via')) {
                            $this->set($param, 'PressReader');
                            $this->rename($param, 'via');
                        } elseif (mb_stripos($this->get('via'), 'pressreader') !== false) {
                            $this->forget($param);
                        }
                        return;
                    }

                    if (mb_strtolower($the_param) === 'www.sify.com' || mb_strtolower($the_param) === 'sify.com' || mb_strtolower($the_param) === 'sify') {
                        $this->set($param, '[[Sify]]');
                        return;
                    }
                    if (mb_strtolower($the_param) === 'www.bollywoodhungama.com' || mb_strtolower($the_param) === 'bollywoodhungama.com' || mb_strtolower($the_param) === 'bollywoodhungama') {
                        $this->set($param, '[[Bollywood Hungama]]');
                        $authtmp = $this->get('author');
                        if ($authtmp === 'Bollywood Hungama News Network' || $authtmp === 'Bollywood Hungama') {
                            $this->forget('author');
                        }
                        return;
                    } elseif (mb_stripos($the_param, 'bollywoodhungama') !== false || mb_stripos($the_param, 'bollywood hungama') !== false) {
                        $authtmp = $this->get('author');
                        if ($authtmp === 'Bollywood Hungama News Network' || $authtmp === 'Bollywood Hungama') {
                            $this->forget('author');
                        }
                    }
                    if (mb_strtolower($the_param) === 'www.sciencedirect.com' || mb_strtolower($the_param) === 'sciencedirect.com' || mb_strtolower($the_param) === 'sciencedirect') {
                        if ($this->has('isbn')) {
                            $this->forget($param);
                        }
                    }

                    if (mb_strtolower($the_param) === 'ieeexplore.ieee.org') {
                        if ($this->has('isbn') || $this->has('doi') || $this->has('s2cid')) {
                            $this->forget($param);
                        }
                    }

                    if (mb_strtolower($the_param) === 'dx.doi.org') {
                        if (mb_strpos($this->get('url'), 'https://dx.doi.org/10.') === 0 || mb_strpos($this->get('url'), 'http://dx.doi.org/10.') === 0) {
                            $this->forget($param);
                        }
                    }

                    return;

                case 'location':
                    // Check if it is a URL
                    $the_param = $this->get($param);
                    if (preg_match(REGEXP_IS_URL, $the_param) !== 1) {
                        return;
                    } // complete
                    if ($this->has('url')) {
                        $url = $this->get('url');
                        if (mb_strpos($url, $the_param) === 0) {
                            $this->forget($param);
                        } elseif (mb_strpos($the_param, $url) === 0) {
                            $this->rename($param, 'url');
                        }
                    } else {
                        $this->rename($param, 'url');
                    }
                    return;

                case 'article-url':
                case 'conference-url':
                case 'conferenceurl':
                case 'contribution-url':
                case 'contributionurl':
                case 'entry-url':
                case 'event-url':
                case 'eventurl':
                case 'lay-url':
                case 'layurl':
                case 'map-url':
                case 'mapurl':
                case 'section-url':
                case 'sectionurl':
                case 'transcript-url':
                case 'transcripturl':
                case 'URL':
                    if (preg_match('~^(?:web\.|www\.).+$~', $this->get($param), $matches) && mb_stripos($this->get($param), 'citation') === false) {
                        $this->set($param, 'http://' . $matches[0]);
                    }
                    return;
            }
        }
    }

    /**
     * Should only be run once (perhaps when template is first loaded).
     * Future tidying should occur when parameters are added using tidy_parameter.
     * Called in final_tidy when the template type is changed.
     * We do this again when anything changes - up to three times.
     */
    public function tidy(): void {
        $orig = $this->parsed_text();
        foreach ($this->param as $param) {
            $this->tidy_parameter($param->param);
        }
        $new = $this->parsed_text();
        if ($orig !== $new) {
            $orig = $new;
            foreach ($this->param as $param) {
                $this->tidy_parameter($param->param);
            }
        }
        $new = $this->parsed_text();
        if ($orig !== $new) {
            foreach ($this->param as $param) {
                $this->tidy_parameter($param->param);
            }
        } // Give up tidy after third time. Something is goofy.
    }

    public function final_tidy(): void {
        set_time_limit(120);
        if ($this->should_be_processed()) {
            if ($this->initial_name !== $this->name) {
                $this->tidy();
            }
            // Sometimes title and chapter come from different databases
            if ($this->has('chapter') && $this->get('chapter') === $this->get('title')) {
                // Leave only one
                if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
                    $this->forget('title');
                } elseif ($this->wikiname() === 'cite journal' || $this->wikiname() === 'citation') {
                    $this->forget('chapter');
                }
            }
            // Sometimes series and journal come from different databases
            if ($this->has('series') && $this->has('journal') && str_equivalent($this->get('series'), $this->get('journal'))) {
                // Leave only one
                if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
                    $this->forget('journal');
                } elseif ($this->wikiname() === 'cite journal' || $this->wikiname() === 'citation') {
                    $this->forget('series');
                }
            }
            // Correct existing series misspellings
            if ($this->has('series')) {
                $series_value = $this->get('series');
                $lower = mb_strtolower($series_value);
                if (isset(SERIES_CORRECTIONS[$lower])) {
                    $this->set('series', SERIES_CORRECTIONS[$lower]);
                }
            }
            if ($this->has('journal') && str_equivalent($this->get('title'), $this->get('journal'))) {
                if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
                    $this->forget('journal');
                }
            }
            // Double check these troublesome "journals"
            if (
                $this->is_book_series('journal') ||
                $this->is_book_series('series') ||
                $this->is_book_series('chapter') ||
                $this->is_book_series('title') ||
                ($this->wikiname() !== 'cite book' && $this->wikiname() !== 'citation' && $this->has('chapter'))
            ) {
                // Do it twice - since things change
                $this->tidy_parameter('series');
                $this->tidy_parameter('journal');
                $this->tidy_parameter('title');
                $this->tidy_parameter('chapter');
                $this->tidy_parameter('series');
                $this->tidy_parameter('journal');
                $this->tidy_parameter('title');
                $this->tidy_parameter('chapter');
            }
            // "Work is a troublesome parameter
            if ($this->has_but_maybe_blank('work') && $this->blank('work')) {
                // Have work=, but it is blank
                if ($this->has('journal') || $this->has('newspaper') || $this->has('magazine') || $this->has('periodical') || $this->has('website')) {
                    $this->forget('work'); // Delete if we have alias
                } elseif ($this->wikiname() === 'cite web') {
                    $this->forget('work'); // The likelihood of this being a good thing to add is very low
                } elseif ($this->wikiname() === 'cite journal') {
                    $this->rename('work', 'journal');
                }
            }
            if ($this->has(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
                if ($this->has('title') || $this->has('chapter')) {
                    $this->quietly_forget(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
                }
            }
            if ($this->get('issue') === 'n/a' && preg_match('~^\d+$~', $this->get('volume'))) {
                $this->mod_na = true;
                $this->forget('issue');
            }
            if ($this->get('volume') === 'n/a' && preg_match('~^\d+$~', $this->get('issue'))) {
                $this->mod_na = true;
                $this->forget('volume');
            }
            if (
                $this->has('doi') &&
                $this->has('issue') &&
                $this->get('issue') === $this->get('volume') && // Issue = Volume and not null
                $this->get('issue') === $this->get_without_comments_and_placeholders('issue') &&
                $this->get('volume') === $this->get_without_comments_and_placeholders('volume')
            ) {
                // No comments to flag problems
                $doi_template = $this->get_without_comments_and_placeholders('doi');
                $crossRef = query_crossref($doi_template);
                if ($crossRef) {
                    $orig_data = mb_trim($this->get('volume'));
                    $possible_issue = mb_trim((string) @$crossRef->issue);
                    $possible_volume = mb_trim((string) @$crossRef->volume);
                    $doi_crossref = mb_trim((string) @$crossRef->doi);
                    unset($crossRef);
                    if ($possible_issue !== $possible_volume) {
                        // They don't match
                        if ((mb_strpos($possible_issue, '-') > 0 || (int) $possible_issue > 1) && (int) $possible_volume > 0) {
                            // Legit data
                            if ($possible_issue === $orig_data) {
                                $this->set('volume', $possible_volume);
                                report_action('Citation had volume and issue the same. Changing volume.');
                            } elseif ($possible_volume === $orig_data) {
                                $this->set('issue', $possible_issue);
                                report_action('Citation had volume and issue the same. Changing issue.');
                            } else {
                                if (mb_strlen($doi_crossref) < 2) {
                                    $doi_crossref = $doi_template;
                                }
                                report_inaction(
                                'Citation for doi:' . echoable($doi_crossref) . ' has volume and issue set to ' . echoable($orig_data) . ' which disagrees with CrossRef (volume ' . echoable($possible_volume) . ', issue ' . echoable($possible_issue) . ')'
                                );
                            }
                        }
                    }
                }
            }
            $this->tidy_parameter('url'); // depending upon end state, convert to chapter-url
            if ($this->has_good_free_copy()) {
                // One last try to drop URLs
                $url = $this->get('url');
                if ($url !== str_ireplace(['nih.gov', 'pubmed', 'pmc', 'doi'], '', $url)) {
                    $this->get_identifiers_from_url();
                }
            }
            $this->tidy_parameter('via');
            $this->tidy_parameter('publisher');
            if ($this->has('publisher') && preg_match("~^([\'\"]+)([^\'\"]+)([\'\"]+)$~u", $this->get('publisher'), $matches)) {
                if ($this->blank(WORK_ALIASES)) {
                    $this->rename('publisher', 'work', $matches[2]);
                    $this->tidy_parameter('work');
                } else {
                    $this->set('publisher', $matches[2]);
                    $this->tidy_parameter('publisher');
                }
            }
            if ($this->wikiname() === 'cite journal' && $this->blank(WORK_ALIASES) && mb_stripos($this->initial_name, 'journal') === false) {
                if ($this->has('arxiv') || $this->has('eprint')) {
                    $this->change_name_to('cite arxiv');
                } else {
                    $this->change_name_to($this->initial_name);
                }
            }
            if (
                ($this->wikiname() === 'cite document' || $this->wikiname() === 'cite journal' || $this->wikiname() === 'cite web') &&
                (mb_strpos($this->get('isbn'), '978-0-19') === 0 || mb_strpos($this->get('isbn'), '978019') === 0 || mb_strpos($this->get('isbn'), '978-019') === 0)
            ) {
                $this->change_name_to('cite book', true, true);
            }
            if ($this->blank('pmc-embargo-date')) {
                $this->forget('pmc-embargo-date');
            } // Do at the very end, so we do not delete it, then add it later in a different position
            if ($this->wikiname() === 'cite arxiv' && $this->get_without_comments_and_placeholders('doi') && mb_stripos($this->get_without_comments_and_placeholders('doi'), 'arxiv') === false) {
                $this->change_name_to('cite journal');
            }
            if ($this->wikiname() === 'cite arxiv' && $this->has('bibcode')) {
                $this->forget('bibcode'); // Not supported and 99% of the time just a arxiv bibcode anyway
            }

            // Final cleanup for cite biorxiv - ensure only allowed parameters are present
            if ($this->wikiname() === 'cite biorxiv') {
                $params_to_remove = [];
                foreach ($this->param as $p) {
                    $param_name = $p->param;
                    $param_name_lower = mb_strtolower($param_name);
                    $is_allowed = in_array($param_name_lower, CITE_BIORXIV_ALLOWED_PARAMS, true);

                    // Allow author and editor parameters even if not in the explicit allowed list
                    if (!$is_allowed && (
                        preg_match('~^(?:author|last|first|given|surname|forename|initials)\d*$~i', $param_name) ||
                        preg_match('~^(?:author|editor)\d+-(?:last|first|given|surname|forename|initials|link|mask)$~i', $param_name) ||
                        preg_match('~^(?:author|editor)-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $param_name) ||
                        preg_match('~^(?:authorlink|authormask|editorlink|editormask)\d*$~i', $param_name) ||
                        preg_match('~^editor\d*$~i', $param_name) ||
                        preg_match('~^editor-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $param_name) ||
                        preg_match('~^(?:vauthors|authors|display-authors|displayauthors|veditors|editors|display-editors|displayeditors)$~i', $param_name) ||
                        mb_stripos($param_name, 'CITATION_BOT') !== false ||
                        mb_stripos($param_name, 'DUPLICATE') !== false
                    )) {
                        $is_allowed = true;
                    }

                    if (!$is_allowed) {
                        $params_to_remove[] = $param_name;
                    }
                }

                foreach ($params_to_remove as $param_name) {
                    $this->forget($param_name);
                }
            }

            // Final cleanup for cite medRxiv - ensure only allowed parameters are present
            if ($this->wikiname() === 'cite medrxiv') {
                $params_to_remove = [];
                foreach ($this->param as $p) {
                    $param_name = $p->param;
                    $param_name_lower = mb_strtolower($param_name);
                    $is_allowed = in_array($param_name_lower, CITE_MEDRXIV_ALLOWED_PARAMS, true);

                    // Allow author and editor parameters even if not in the explicit allowed list
                    if (!$is_allowed && (
                        preg_match('~^(?:author|last|first|given|surname|forename|initials)\d*$~i', $param_name) ||
                        preg_match('~^(?:author|editor)\d+-(?:last|first|given|surname|forename|initials|link|mask)$~i', $param_name) ||
                        preg_match('~^(?:author|editor)-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $param_name) ||
                        preg_match('~^(?:authorlink|authormask|editorlink|editormask)\d*$~i', $param_name) ||
                        preg_match('~^editor\d*$~i', $param_name) ||
                        preg_match('~^editor-(?:last|first|given|surname|forename|initials|link|mask)\d*$~i', $param_name) ||
                        preg_match('~^(?:vauthors|authors|display-authors|displayauthors|veditors|editors|display-editors|displayeditors)$~i', $param_name) ||
                        mb_stripos($param_name, 'CITATION_BOT') !== false ||
                        mb_stripos($param_name, 'DUPLICATE') !== false
                    )) {
                        $is_allowed = true;
                    }

                    if (!$is_allowed) {
                        $params_to_remove[] = $param_name;
                    }
                }

                foreach ($params_to_remove as $param_name) {
                    $this->forget($param_name);
                }
            }

            if ($this->wikiname() === 'cite web') {
                if (!$this->blank_other_than_comments('title') && !$this->blank_other_than_comments('chapter')) {
                    if ($this->name === 'cite web') {
                        // Need special code to keep caps the same
                        $this->name = 'cite book';
                    } else {
                        $this->name = 'Cite book';
                    }
                }
                if (($this->has('arxiv') || $this->has('eprint')) && mb_stripos($this->get('url'), 'arxiv') !== false) {
                    if ($this->name === 'cite web') {
                        $this->name = 'cite arXiv';
                    } else {
                        $this->name = 'Cite arXiv';
                    }
                    $this->quietly_forget('url');
                }
            }
            if (
                !$this->blank(DOI_BROKEN_ALIASES) &&
                $this->has('jstor') &&
                (mb_strpos($this->get('doi'), '10.2307') === 0 || $this->get('doi') === $this->get('jstor') || mb_substr($this->get('doi'), 0, -2) === $this->get('jstor') || mb_substr($this->get('doi'), 0, -3) === $this->get('jstor'))
            ) {
                $this->forget('doi'); // Forget DOI that is really jstor, if it is broken
                foreach (DOI_BROKEN_ALIASES as $alias) {
                    $this->forget($alias);
                }
            }
            if ($this->has('journal') && mb_stripos($this->get('journal'), 'arxiv') === false) {
                // Do this at the very end of work in case we change type/etc during expansion
                if ($this->blank(['chapter', 'isbn'])) {
                    // Avoid renaming between cite journal and cite book
                    $this->change_name_to('cite journal');
                    // Remove blank stuff that will most likely never get filled in
                    $this->forget('isbn');
                    $this->forget('chapter');
                    foreach (['location', 'place', 'publisher', 'publication-place', 'publicationplace'] as $to_drop) {
                        if ($this->blank($to_drop)) {
                            $this->forget($to_drop);
                        }
                    }
                } elseif (in_array(mb_strtolower($this->get('journal')), [...NON_PUBLISHERS, ...BAD_TITLES, ...DUBIOUS_JOURNALS, 'amazon.com'], true)) {
                    report_forget('Citation has chapter/ISBN already, dropping dubious Journal title: ' . echoable($this->get('journal')));
                    $this->forget('journal');
                } else {
                    report_warning(echoable('Citation should probably not have journal = ' . $this->get('journal') . ' as well as chapter / ISBN ' . $this->get('chapter') . ' ' . $this->get('isbn')));
                }
            }
            if ($this->wikiname() === 'cite book' && $this->blank(['issue', 'journal'])) {
                // Remove blank stuff that will most likely never get filled in
                $this->forget('issue');
                $this->forget('journal');
            }
            if (preg_match('~^10\.1093/ref\:odnb/\d+$~', $this->get('doi')) && $this->has('title') && $this->wikiname() !== 'cite encyclopedia' && $this->wikiname() !== 'cite encyclopaedia') {
                if (!preg_match("~^(\s*)[\s\S]*?(\s*)$~", $this->name, $spacing)) {
                    bot_debug_log("RegEx failure in Template name: " . $this->name); // @codeCoverageIgnoreStart
                    $spacing = [];
                    $spacing[1] = '';
                    $spacing[2] = ''; // @codeCoverageIgnoreEnd
                }
                if (mb_substr($this->name, 0, 1) === 'c') {
                    $this->name = $spacing[1] . 'cite ODNB' . $spacing[2];
                } else {
                    $this->name = $spacing[1] . 'Cite ODNB' . $spacing[2];
                }
                foreach (array_diff(WORK_ALIASES, ['encyclopedia', 'encyclopaedia']) as $worker) {
                    $this->forget($worker);
                }
                if (mb_stripos($this->get('publisher'), 'oxford') !== false) {
                    $this->forget('publisher');
                }
                $this->forget('dictionary');
            }
            if (preg_match('~^10\.1093/~', $this->get('doi')) && $this->has('title') && ($this->wikiname() === 'cite web' || $this->wikiname() === 'cite journal') && $this->blank(WORK_ALIASES) && $this->blank('url')) {
                if (!preg_match("~^(\s*)[\s\S]*?(\s*)$~", $this->name, $spacing)) {
                    bot_debug_log("RegEx failure in Template name: " . $this->name); // @codeCoverageIgnoreStart
                    $spacing = [];
                    $spacing[1] = '';
                    $spacing[2] = ''; // @codeCoverageIgnoreEnd
                }
                if ($this->has('chapter')) {
                    if (mb_substr($this->name, 0, 1) === 'c') {
                        $this->name = $spacing[1] . 'cite book' . $spacing[2];
                    } else {
                        $this->name = $spacing[1] . 'Cite book' . $spacing[2];
                    }
                } else {
                    if (mb_substr($this->name, 0, 1) === 'c') {
                        $this->name = $spacing[1] . 'cite document' . $spacing[2];
                    } else {
                        $this->name = $spacing[1] . 'Cite document' . $spacing[2];
                    }
                }
            }
            if (conference_doi($this->get('doi')) && $this->has('title') && $this->has('chapter') && $this->has('isbn') && $this->wikiname() === 'cite book' && doi_works($this->get('doi'))) {
                foreach (WORK_ALIASES as $worky) {
                    foreach (['Conference', 'Symposium', 'SIGGRAPH', 'workshop'] as $thingy) {
                        if (mb_stripos($this->get('title'), $thingy) !== false && mb_stripos($this->get($worky), $thingy) !== false) {
                            $this->forget($worky);
                        }
                    }
                }
            }
            if (mb_stripos($this->get('journal'), 'SIGGRAPH') !== false && mb_stripos($this->get('title'), 'SIGGRAPH') !== false) {
                $this->forget('journal');
            }
            if (mb_stripos($this->get('journal'), 'SIGGRAPH') !== false && $this->blank('title')) {
                $this->rename('journal', 'title');
            }
            if ($this->has('series') && mb_stripos($this->get('title'), $this->get('series')) !== false && $this->get('series') !== 'Surtees Society' && !preg_match('~^\d+$~', $this->get('series'))) {
                $this->forget('series');
            }

            $this->tidy_parameter('doi'); // might be free, and freedom is date dependent for some journals
            if ($this->blank(PAGE_ALIASES) && preg_match('~^10\.1103\/[a-zA-Z]+\.(\d+)\.(\d+)$~', $this->get('doi'), $matches)) {
                if ($matches[1] === $this->get('volume')) {
                    $this->set('page', $matches[2]); // Often not in CrossRef
                }
            }
            if ($this->wikiname() === 'cite book') {
                foreach (WORK_ALIASES as $worky) {
                    if ($this->blank($worky)) {
                        $this->forget($worky);
                    } // Discourage filling these in
                    if (mb_strtolower($this->get('publisher')) === mb_strtolower($this->get($worky))) {
                        $this->forget($worky);
                    }
                }
                /*
                // If one and only one work alias is set, the move it to publisher
                if ($this->blank('publisher')) {
                    $counting = 0;
                    foreach (WORK_ALIASES as $worky) {
                    if ($this->has($worky)) $counting = $counting + 1;
                }
                if ($counting === 1) {
                    foreach (WORK_ALIASES as $worky) {
                        //TODO: convert to via/publisher/delete/log depending upon specificsif ($this->has($worky)) bot_debug_log('WORKY ' . $this->get($worky));
                    }
                }
                */
            } elseif ($this->has('publisher')) {
                foreach (WORK_ALIASES as $worky) {
                    if (mb_strtolower($this->get('publisher')) === mb_strtolower($this->get($worky))) {
                        $this->forget('publisher');
                    }
                }
            }
            if (!empty($this->param)) {
                $drop_me_maybe = [];
                foreach (ALL_ALIASES as $alias_list) {
                    if (!$this->blank($alias_list)) {
                        // At least one is set
                        $drop_me_maybe = [...$drop_me_maybe, ...$alias_list];
                    }
                }
                if (!$this->incomplete()) {
                    $drop_me_maybe = [...$drop_me_maybe, ...LOTS_OF_EDITORS]; // Always drop empty editors at end, if "complete"
                }
                // Do it this way to avoid massive N*M work load (N=size of $param and M=size of $drop_me_maybe) which happens when checking if each one is blank
                foreach ($this->param as $key => $p) {
                    if (@$p->val === '' && in_array(@$p->param, $drop_me_maybe, true)) {
                        unset($this->param[$key]);
                    }
                }
            }
            if (!empty($this->param)) {
                // Forget author-link and such that have no such author
                foreach ($this->param as $p) {
                    $alias = $p->param;
                    if ($alias !== '' && $this->blank($alias)) {
                        if (preg_match('~^author(\d+)\-?link$~', $alias, $matches) || preg_match('~^author\-?link(\d+)$~', $alias, $matches)) {
                            if ($this->blank(AUTHOR_PARAMETERS[(int) $matches[1]])) {
                                $this->forget($alias);
                            }
                        }
                    }
                }
            }
            if ($this->get('newspaper') === 'Reuters') {
                $this->rename('newspaper', 'work');
            }
            if (($this->wikiname() === 'cite journal' || $this->wikiname() === 'cite document' || $this->wikiname() === 'cite web') && $this->has('chapter') && $this->blank('title')) {
                $this->rename('chapter', 'title');
            }
            if (($this->wikiname() === 'cite journal' || $this->wikiname() === 'cite document' || $this->wikiname() === 'cite web') && $this->has('chapter')) {
                // At least avoid a template error
                $this->change_name_to('cite book', true, true);
            }
            if (
                ($this->wikiname() === 'cite web' || $this->wikiname() === 'cite news') &&
                $this->blank(WORK_ALIASES) &&
                $this->blank(['publisher', 'via', 'pmc', 'pmid', 'doi', 'mr', 'asin', 'issn', 'eissn', 'hdl', 'id', 'isbn', 'jfm', 'jstor', 'oclc', 'ol', 'osti', 's2cid', 'ssrn', 'zbl', 'citeseerx', 'arxiv', 'eprint', 'biorxiv', 'medrxiv']) &&
                $this->blank(array_diff_key(ALL_URL_TYPES, [0 => 'url'])) &&
                $this->has('url')
            ) {
                $url = $this->get('url');
                if (
                    mb_stripos($url, 'CITATION_BOT') === false &&
                    !preg_match('~^https?://[^/]+/*?$~', $url) && // Ignore just a hostname
                    preg_match(REGEXP_IS_URL, $url) === 1 &&
                    preg_match('~^https?://([^/]+)/~', $url, $matches)
                ) {
                    $hostname = mb_strtolower($matches[1]);
                    $hostname = (string) preg_replace('~^(m\.|www\.)~', '', $hostname);
                    if (preg_match('~^https?://([^/]+/+[^/]+)~', $url, $matches)) {
                        $hostname_plus = mb_strtolower($matches[1]);
                    } else {
                        bot_debug_log($url . " generated matches nothing event"); // @codeCoverageIgnore
                        $hostname_plus = 'matches nothing'; // @codeCoverageIgnore
                    }
                    $hostname_plus = (string) preg_replace('~^(m\.|www\.)~', '', $hostname_plus);
                    $hostname_plus = (string) preg_replace('~//+~', '/', $hostname_plus);
                    if (
                        str_ireplace(CANONICAL_PUBLISHER_URLS, '', $hostname) === $hostname &&
                        str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $hostname) === $hostname &&
                        str_ireplace(PROXY_HOSTS_TO_DROP, '', $hostname) === $hostname &&
                        str_ireplace(HOSTS_TO_NOT_ADD, '', $hostname) === $hostname
                    ) {
                        foreach (HOSTNAME_MAP as $i_key => $i_value) {
                            // Scan longer url first
                            if ($hostname_plus === $i_key) {
                                $this->add_if_new('website', $i_value);
                            }
                        }
                        foreach (HOSTNAME_MAP as $i_key => $i_value) {
                            // Scan longer url first
                            if ($hostname === $i_key) {
                                $this->add_if_new('website', $i_value);
                            }
                        }
                        // Special Cases
                        if ($hostname === 'theweek.in') {
                            foreach (WORK_ALIASES as $works) {
                                if (mb_strpos($this->get($works), '[[The Week]]') !== false) {
                                       $this->set($works, '[[The Week (Indian magazine)|The Week]]');
                                }
                            }
                        }
                    }
                }
            }
            if ($this->get('url-status') === 'live' && $this->blank(['archive-url', 'archivedate', 'archiveurl', 'archived-date'])) {
                $this->forget('url-status');
            }
            // If dictionary and entry are set, don't set a title.
            if ($this->wikiname() === 'cite dictionary' && $this->get('dictionary') && $this->get('entry') && $this->get('title')) {
                $this->forget('title');
            }
        } elseif (in_array($this->wikiname(), TEMPLATES_WE_SLIGHTLY_PROCESS, true)) {
            $this->tidy_parameter('publisher');
            $this->tidy_parameter('via');
            if ($this->get('url-status') === 'live' && $this->blank(['archive-url', 'archivedate', 'archiveurl', 'archived-date'])) {
                $this->forget('url-status');
            }
            if (!empty($this->param)) {
                $drop_me_maybe = [];
                foreach (ALL_ALIASES as $alias_list) {
                    if (!$this->blank($alias_list)) {
                        // At least one is set
                        $drop_me_maybe = [...$drop_me_maybe, ...$alias_list];
                    }
                }
                // Do it this way to avoid massive N*M work load (N=size of $param and M=size of $drop_me_maybe) which happens when checking if each one is blank
                foreach ($this->param as $key => $p) {
                    if (@$p->val === '' && in_array(@$p->param, $drop_me_maybe, true)) {
                        unset($this->param[$key]);
                    }
                }
            }
        }

        // Check for existing URLs in non-URL parameters and warn the user
        if ($this->should_be_processed() && !empty($this->param)) {
            foreach ($this->param as $p) {
                $param_name = $p->param;
                $value = $p->val;
                if ($param_name && $value && $this->is_url_in_non_url_parameter($param_name, $value)) {
                    report_warning("Found URL in non-URL parameter |" . echoable($param_name) . "=" . echoable($value));
                }
            }
        }
    }

    public function verify_doi(): bool {
        set_time_limit(120);
        static $last_doi = '';
        $doi = $this->get_without_comments_and_placeholders('doi');
        if (!$doi) {
            return false;
        }
        if ($this->doi_valid) {
            return true;
        }
        if ($last_doi === $doi) {
            $chatty = false;
        } else {
            $last_doi = $doi;
            $chatty = true;
        }
        if ($chatty) {
            report_info("Checking that DOI " . echoable($doi) . " is operational...");
        }
        $trial = get_possible_dois($doi);
        foreach ($trial as $try) {
            // Check that it begins with 10.
            if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) {
                $try = "10." . $match[1];
            }
            if (doi_works($try)) {
                $this->set('doi', $try);
                $this->doi_valid = true;
                foreach (DOI_BROKEN_ALIASES as $alias) {
                    $this->forget($alias);
                }
                if ($doi === $try) {
                    if ($chatty) {
                        report_inline('DOI ok.');
                    }
                } else {
                    report_inline("Modified DOI:  " . echoable($try) . " is operational...");
                }
                return true;
            }
        }
        $doi_status = doi_works($doi);
        if ($doi_status === null) {
            report_warning("DOI status unknown.  doi.org failed to respond to: " . doi_link($doi)); // @codeCoverageIgnore
            return false; // @codeCoverageIgnore
        } elseif ($doi_status === false) {
            if ($chatty) {
                report_inline("It's not...");
            }
            $this->add_if_new('doi-broken-date', date("Y-m-d"));
            return false;
        } else {
            // Only get to this code if we got null earlier and now suddenly get OK
            // @codeCoverageIgnoreStart
            foreach (DOI_BROKEN_ALIASES as $alias) {
                $this->forget($alias);
            }
            $this->doi_valid = true;
            if ($chatty) {
                report_inline('DOI ok.');
            }
            return true;
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * To preserve user-input data, this function will only be called if no author parameters were specified at the start of the expansion process.
     */
    public function handle_et_al(): void {
        foreach (AUTHOR_PARAMETERS as $author_cardinality => $group) {
            foreach ($group as $param) {
                if (mb_strpos($this->get($param), 'et al') !== false) {
                    // Have to deal with 0 !== false
                    // remove 'et al' from the parameter value if present
                    $val_base = preg_replace("~,?\s*'*et al['.]*~", '', $this->get($param));
                    if ($author_cardinality === 1) {
                        // then we (probably) have a list of authors joined by commas in our first parameter
                        if (under_two_authors($val_base)) {
                            $this->set($param, $val_base);
                            if ($param === 'authors' && $this->blank('author')) {
                                   $this->rename('authors', 'author');
                                   $param = 'author';
                            }
                        } else {
                            $this->forget($param);
                            $authors = split_authors($val_base);
                            foreach ($authors as $i => $author_name) {
                                $this->add_if_new('author' . (string) ((int) $i + 1), format_author($author_name));
                            }
                        }
                    }
                    if (mb_trim($val_base) === "") {
                        $this->forget($param);
                    }
                    $this->add_if_new('display-authors', 'etal');
                }
            }
        }
    }

    /**
     * Functions to retrieve values that may be specified in various ways
     */
    private function display_authors(): int {
        $da = $this->get('display-authors');
        if ($da === '') {
            $da = $this->get('displayauthors');
        }
        return ctype_digit($da) ? (int) $da : 0;
    }

    private function number_of_authors(): int {
        $max = 0;
        foreach ($this->param as $p) {
            if (preg_match('~(?:author|last|first|forename|initials|surname|given)(\d+)~', $p->param, $matches)) {
                if (mb_stripos($p->param, 'editor') === false) {
                    $max = max((int) $matches[1], $max);
                }
            }
        }
        if ($max === 0) {
            foreach ($this->param as $p) {
                if (preg_match('~(?:author|last|first|forename|initials|surname|given)$~', $p->param)) {
                    if (mb_stripos($p->param, 'editor') === false) {
                        return 1;
                    }
                }
            }
        }
        return $max;
    }

    /** Retrieve properties of template */
    public function first_author(): string {
        foreach (['author', 'author1', 'authors', 'vauthors'] as $auth_param) {
            $author = $this->get($auth_param);
            if ($author) {
                return $author;
            }
        }
        $forenames = $this->get('given') . $this->get('first') . $this->get('forename') . $this->get('initials') . $this->get('given1') . $this->get('first1') . $this->get('forename1') . $this->get('initials1');
        foreach (['last', 'surname', 'last1', 'surname1'] as $surname_param) {
            $surname = $this->get($surname_param);
            if ($surname) {
                return $surname . ', ' . $forenames;
            }
        }
        return '';
    }

    public function first_surname(): string {
        // Fetch the surname of the first author only
        if (preg_match("~[^.,;\s]{2,}~u", $this->first_author(), $first_author)) {
            return $first_author[0];
        } else {
            return '';
        }
    }

    public function page(): string {
        if ($this->has('pages')) {
            $page = $this->get('pages');
        } elseif ($this->has('page')) {
            $page = $this->get('page');
        } else {
            $page = $this->get('article-number');
        }
        return str_replace(['&mdash;', '--', '&ndash;', '—', '–'], ['-', '-', '-', '-', '-'], $page);
    }

    public function year(): string {
        if ($this->has('year')) {
            return $this->get('year');
        }
        if ($this->has('date')) {
            $date = $this->get('date');
            if (preg_match("~^\d{4}$~", $date)) {
                return $date; // Just a year
            } elseif (preg_match("~^(\d{4})[^0-9]~", $date, $matches)) {
                return $matches[1]; // Start with year
            } elseif (preg_match("~[^0-9](\d{4})$~", $date, $matches)) {
                return $matches[1]; // Ends with year
            }
        }
        return '';
    }

    private function year_int(): int {
        return intval($this->year());
    }

    /** @return array<string> */
    public function page_range(): array {
        preg_match("~(\w?\w?\d+\w?\w?)(?:\D+(\w?\w?\d+\w?\w?))?~", $this->page(), $pagenos);
        return $pagenos;
    }

    /** Amend parameters */
    public function rename(string $old_param, string $new_param, ?string $new_value = null): void {
        if (empty($this->param)) {
            return;
        }
        if ($old_param === $new_param) {
            if ($new_value !== null) {
                $this->set($new_param, $new_value);
                return;
            }
            return;
        }
        $have_nothing = true;
        foreach ($this->param as $p) {
            if ($p->param === $old_param) {
                $have_nothing = false;
                break;
            }
        }
        if ($have_nothing) {
            if ($new_value !== null) {
                $this->add_if_new($new_param, $new_value);
                return;
            }
            return;
        }
        // Forget old copies
        $pos = $this->get_param_key($new_param);
        while ($pos !== null) {
            unset($this->param[$pos]);
            $pos = $this->get_param_key($new_param);
        }
        foreach ($this->param as $p) {
            if ($p->param === $old_param) {
                $p->param = $new_param;
                if ($new_value !== null) {
                    $p->val = $new_value;
                }
                if (
                    mb_strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_year') === false &&
                    mb_strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_date') === false &&
                    mb_strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_title') === false &&
                    mb_strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_volume') === false &&
                    mb_strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_issue') === false &&
                    mb_strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_journal') === false
                ) {
                    report_modification("Renamed \"" . echoable($old_param) . "\" -> \"" . echoable($new_param) . "\"");
                    $this->mod_names = true;
                }
                $this->tidy_parameter($new_param);
            }
        }
        if ($old_param === 'url' && $new_param === 'chapter-url') {
            $this->rename('urlaccess', 'chapter-url-access');
            $this->rename('url-access', 'chapter-url-access');
            $this->rename('format', 'chapter-format');
        } elseif ($old_param === 'url' && $new_param === 'contribution-url') {
            $this->rename('urlaccess', 'contribution-url-access');
            $this->rename('url-access', 'contribution-url-access');
            $this->rename('format', 'contribution-format');
        } elseif (($old_param === 'chapter-url' || $old_param === 'chapterurl') && $new_param === 'url') {
            $this->rename('chapter-url-access', 'url-access');
            $this->rename('chapter-format', 'format');
        } elseif ($old_param === 'title' && $new_param === 'chapter') {
            $this->rename('url', 'chapter-url');
        } elseif ($old_param === 'chapter' && $new_param === 'title') {
            $this->rename('chapter-url', 'url');
            $this->rename('chapterurl', 'url');
        }
        if ($this->has('script-' . $old_param)) {
            $this->rename('script-' . $old_param, 'script-' . $new_param);
        }
        if ($this->has('trans-' . $old_param)) {
            $this->rename('trans-' . $old_param, 'trans-' . $new_param);
        }
    }

    public function get(string $name): string {
        // NOTE $this->param and $p->param are different and refer to different types!
        // $this->param is an array of Parameter objects
        // $parameter_i->param is the parameter name within the Parameter object
        foreach ($this->param as $parameter_i) {
            if ($parameter_i->param === $name) {
                $the_val = $parameter_i->val;
                if (preg_match("~^\(\((.*)\)\)$~", $the_val, $matches)) {
                    $the_val = mb_trim($matches[1]);
                }
                return $the_val;
            }
        }
        return '';
    }

    /** This one is used in the test suite to distinguish there-but-blank vs not-there-at-all */
    public function get2(string $name): ?string {
        foreach ($this->param as $parameter_i) {
            if ($parameter_i->param === $name) {
                $the_val = $parameter_i->val;
                if (preg_match("~^\(\((.*)\)\)$~", $the_val, $matches)) {
                    $the_val = mb_trim($matches[1]);
                }
                return $the_val;
            }
        }
        return null;
    }

    public function get3(string $name): string {
        // like get() only includes (( ))
        foreach ($this->param as $parameter_i) {
            if ($parameter_i->param === $name) {
                return $parameter_i->val;
            }
        }
        return '';
    }

    public function has_but_maybe_blank(string $name): bool {
        foreach ($this->param as $parameter_i) {
            if ($parameter_i->param === $name) {
                return true;
            }
        }
        return false;
    }

    private function has_multiple_params(): bool {
        return isset($this->param[1]);
    }

    private function param_value(int $i): string {
        if (isset($this->param[$i])) {
            return $this->param[$i]->val;
        }
        return '';
    }

    public function get_without_comments_and_placeholders(string $name): string {
        $ret = $this->get($name);
        $ret = safe_preg_replace('~<!--.*?-->~su', '', $ret); // Comments
        $ret = safe_preg_replace('~# # # CITATION_BOT_PLACEHOLDER.*?# # #~sui', '', $ret); // Other place holders already escaped. Case insensitive
        $ret = str_replace("\xc2\xa0", ' ', $ret); // Replace non-breaking with breaking spaces
        return mb_trim($ret);
    }

    private function get_param_key(string $needle): ?int {
        if (empty($this->param)) {
            return null;
        }
        foreach ($this->param as $i => $p) {
            if ($p->param === $needle) {
                return $i;
            }
        }
        return null;
    }

    public function has(string $par): bool {
        return (bool) mb_strlen($this->get($par));
    }

    public function add(string $par, string $val): bool {
        report_add(echoable("Adding " . $par . ": " . $val));
        $could_set = $this->set($par, $val);
        $this->tidy_parameter($par);
        return $could_set;
    }

    public function set(string $par, string $val): bool {
        if ($par === '') {
            report_error('null parameter passed to set with value of ' . echoable($val));
        }
        if (mb_stripos($this->get($par), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== false && $par !== 'ref') {
            return false;
        }
        if ($this->get($par) !== $this->get3($par)) {
            return false;
        }
        $pos = $this->get_param_key($par);
        if ($pos !== null) {
            $this->param[$pos]->val = $val;
            return true;
        }
        $p = new Parameter();
        $p->parse_text($this->example_param);
        $p->param = $par;
        $p->val = $val;

        $insert_after = prior_parameters($par);
        $prior_pos_best = -1;
        foreach (array_reverse($insert_after) as $after) {
            $after_key = $this->get_param_key($after);
            if ($after_key !== null) {
                $keys = array_keys($this->param);
                $keys_count = count($keys);
                for ($prior_pos = 0; $prior_pos < $keys_count; $prior_pos++) {
                    if ($keys[$prior_pos] === $after_key) {
                        if ($prior_pos > $prior_pos_best) {
                               $prior_pos_best = $prior_pos;
                        }
                        break;
                    }
                }
            }
        }
        $prior_pos = $prior_pos_best;
        if ($p->post !== '' && ($prior_pos + 1) === count($this->param)) {
            // Often templates are {{cite this|x=y |a=b |l=m}}  with last space missing
            $last = array_key_last($this->param);
            if ($last !== null && $this->param[$last]->post === '') {
                $this->param[$last]->post = $p->post;
            }
        }
        if (mb_stripos(mb_trim($this->name), '#invoke:') === 0 && $prior_pos < 2) {
            $prior_pos = 2;
        }
        $this->param = array_merge(array_slice($this->param, 0, $prior_pos + 1), [$p], array_slice($this->param, $prior_pos + 1));
        return true;
    }

    public function append_to(string $par, string $val): void {
        if (mb_stripos($this->get($par), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== false) {
            return;
        }
        $pos = $this->get_param_key($par);
        if ($pos !== null) {
            // Could be zero which is "false"
            $this->param[$pos]->val .= $val;
        } else {
            $this->set($par, $val);
        }
        return;
    }

    public function quietly_forget(string $par): void {
        $this->forgetter($par, false);
    }

    public function forget(string $par): void {
        $this->forgetter($par, true);
    }

    private function forgetter(string $par, bool $echo_forgetting): void {
        if ($par === 'doi-broken-date' &&
            $this->has('doi-broken-date') &&
            !isset(NULL_DOI_BUT_GOOD[$this->get('doi')]) &&
            !isset(NULL_DOI_ANNOYING[$this->get('doi')]) && // Dropped for evilness
            $this->has('doi')
        ) {
                bot_debug_log('Thinks it fixed HDL: ' . $this->get('doi'));
        }
        // Do not call this function directly
        if (!$this->blank($par)) {
            // do not remove all this other stuff if blank
            if ($par === 'url') {
                if ($this->blank(array_diff(ALL_URL_TYPES, [$par]))) {
                    $this->forgetter('archive-url', $echo_forgetting);
                    $this->forgetter('archiveurl', $echo_forgetting);
                    $this->forgetter('accessdate', $echo_forgetting);
                    $this->forgetter('access-date', $echo_forgetting);
                }
                $this->forgetter('format', $echo_forgetting);
                $this->forgetter('registration', $echo_forgetting);
                $this->forgetter('subscription', $echo_forgetting);
                $this->forgetter('url-access', $echo_forgetting);
                $this->forgetter('deadurl', $echo_forgetting);
                $this->forgetter('url-status', $echo_forgetting);
                if ($this->has('work') && mb_stripos($this->get('work'), 'www.') === 0) {
                    $this->forgetter('work', $echo_forgetting);
                }
                if ($this->blank(array_diff(WORK_ALIASES, ['website'])) && bad_10_1093_doi($this->get('doi'))) {
                    if ($this->has('via') && $this->blank('website')) {
                        $this->rename('via', 'work');
                    } elseif ($this->has('website') && $this->blank('via')) {
                        $this->rename('website', 'work');
                    } elseif ($this->has('website') && $this->has('via')) {
                        if (titles_are_similar($this->get('website'), $this->get('via'))) {
                            $this->forgetter('via', $echo_forgetting);
                            $this->rename('website', 'work');
                        } else {
                            $tmp = $this->get('website') . ' via ' . $this->get('via');
                            $this->forgetter('via', $echo_forgetting);
                            $this->rename('website', 'work', $tmp);
                        }
                    }
                    if (!preg_match("~^(\s*)[\s\S]*?(\s*)$~", $this->name, $spacing)) {
                        bot_debug_log("RegEx failure in Template name: " . $this->name); // @codeCoverageIgnoreStart
                        $spacing = [];
                        $spacing[1] = '';
                        $spacing[2] = ''; // @codeCoverageIgnoreEnd
                    }
                    if (mb_substr($this->name, 0, 1) === 'c') {
                        $this->name = $spacing[1] . 'cite document' . $spacing[2];
                    } else {
                        $this->name = $spacing[1] . 'Cite document' . $spacing[2];
                    }
                }
                $this->forgetter('via', $echo_forgetting);
                $this->forgetter('website', $echo_forgetting);
            }
            if ($par === 'chapter' && $this->blank('url')) {
                if ($this->has('chapter-url')) {
                    $this->rename('chapter-url', 'url');
                } elseif ($this->has('chapterurl')) {
                    $this->rename('chapterurl', 'url');
                }
            }
            if ($par === 'chapter-url' || $par === 'chapterurl') {
                $this->forgetter('chapter-format', $echo_forgetting);
                $this->forgetter('chapter-url-access', $echo_forgetting);
                if ($this->blank(array_diff(ALL_URL_TYPES, [$par]))) {
                    $this->forgetter('accessdate', $echo_forgetting);
                    $this->forgetter('access-date', $echo_forgetting);
                    $this->forgetter('archive-url', $echo_forgetting);
                    $this->forgetter('archiveurl', $echo_forgetting);
                }
            }
        } // even if blank try to remove
        if ($par === 'doi') {
            foreach (DOI_BROKEN_ALIASES as $broke) {
                $this->forgetter($broke, false);
            }
        }
        if ($par === 'archive-url' && $this->blank('archiveurl')) {
            $this->forgetter('archive-date', false);
            $this->forgetter('archivedate', false);
            $this->forgetter('dead-url', false);
            $this->forgetter('url-status', false);
        }
        if ($par === 'archiveurl' && $this->blank('archive-url')) {
            $this->forgetter('archive-date', false);
            $this->forgetter('archivedate', false);
            $this->forgetter('dead-url', false);
            $this->forgetter('url-status', false);
        }
        $pos = $this->get_param_key($par);
        if ($pos !== null) {
            if ($echo_forgetting && $this->has($par) && mb_stripos($par, 'CITATION_BOT_PLACEHOLDER') === false) {
                // Do not mention forgetting empty parameters or internal temporary parameters
                report_forget("Dropping parameter \"" . echoable($par) . '"');
            }
            while ($pos !== null) {
                // paranoid
                unset($this->param[$pos]);
                $pos = $this->get_param_key($par);
            }
        }
        if (mb_strpos($par, 'url') !== false && $this->wikiname() === 'cite web' && $this->blank(array_diff(ALL_URL_TYPES, [$par]))) {
            if ($this->has('journal')) {
                $this->change_name_to('cite journal');
            } elseif ($this->has('newspaper')) {
                $this->change_name_to('cite news');
            } elseif (!$this->blank(['isbn', 'lccn', 'oclc', 'ol', 'chapter'])) {
                $this->change_name_to('cite book');
            } elseif ($this->has('arxiv') || $this->has('eprint')) {
                $this->change_name_to('cite arxiv');
            } else {
                $this->change_name_to('cite document');
            }
        }
    }

    /** @return array<bool|array<string>> */
    public function modifications(): array {
        if ($this->has(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
            if ($this->has('title') || $this->has('chapter')) {
                $this->forget(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
            }
        }
        if ($this->has(mb_strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
            return [];
        }
        $new = [];
        $ret = [];
        foreach ($this->param as $p) {
            $new[$p->param] = $p->val;
        }

        $old = $this->initial_param ? $this->initial_param : [];

        $old['template type'] = mb_trim($this->initial_name);
        $new['template type'] = mb_trim($this->name);

        // Do not call ISSN to issn "Added issn, deleted ISSN"
        $old = array_change_key_case($old, CASE_LOWER);
        $new = array_change_key_case($new, CASE_LOWER);

        $mistake_corrections = array_values(COMMON_MISTAKES);
        $mistake_keys = array_keys(COMMON_MISTAKES);
        foreach ($old as $old_name => $old_data) {
            $mistake_id = array_search($old_name, $mistake_keys);
            if ($mistake_id !== false) {
                if ($this->should_be_processed()) {
                    $this->mod_names = true;
                } // 99.99% of the time this is true
                $old[$mistake_corrections[$mistake_id]] = $old_data;
                unset($old[$old_name]);
            }
        }
        // 99.99% of the time does nothing, since they should already be switched. This will be needed for templates that we do very little to, such as TEMPLATES_WE_CHAPTER_URL
        foreach ($new as $old_name => $old_data) {
            $mistake_id = array_search($old_name, $mistake_keys);
            if ($mistake_id !== false) {
                $new[$mistake_corrections[$mistake_id]] = $old_data;
                unset($new[$old_name]);
            }
        }

        $ret['modifications'] = array_keys(array_diff_assoc($new, $old));
        $ret['additions'] = array_diff(array_keys($new), array_keys($old));
        $ret['deletions'] = array_diff(array_keys($old), array_keys($new));
        $ret['changeonly'] = array_diff($ret['modifications'], $ret['additions']);
        foreach ($ret['deletions'] as $inds => $vals) {
            if ($vals === '') {
                unset($ret['deletions'][$inds]);
            } // If we get rid of double pipe that appears as a deletion, not misc.
        }

        $no_dash_to_start = true;
        foreach ($old as $old_name => $old_data) {
            if (in_array($old_name, PAGE_ALIASES, true)) {
                if (mb_strpos($old_data, '-') !== false) {
                    $no_dash_to_start = false;
                }
            }
            if (in_array($old_name, VOL_NUM, true)) {
                if (mb_strpos($old_data, '-') !== false) {
                    $no_dash_to_start = false;
                }
            }
        }
        if ($no_dash_to_start) {
            $this->mod_dashes = false;
        }

        $ret['dashes'] = $this->mod_dashes;
        $ret['names'] = $this->mod_names;
        $ret['ref'] = $this->mod_ref;
        $ret['na'] = $this->mod_na;
        return $ret;
    }

    private function isbn10Toisbn13(string $isbn10, bool $ignore_year): string {
        $year = $this->year_int();
        if ($ignore_year) {
            $year = 3000;
        }
        return changeisbn10Toisbn13($isbn10, $year);
    }

    /** @return array<string> */
    private function inline_doi_information(): array {
        if ($this->name !== "doi-inline") {
            return [];
        }
        if (count($this->param) !== 2) {
            return [];
        }
        $vals = [];
        $vals[0] = $this->param[0]->parsed_text();
        $vals[1] = $this->param[1]->parsed_text();
        return $vals;
    }

    private function get_inline_doi_from_title(): void {
        if (preg_match("~(?:\s)*(?:# # # CITATION_BOT_PLACEHOLDER_TEMPLATE )(\d+)(?: # # #)(?:\s)*~i", $this->get('title'), $match)) {
            $inline_doi = self::$all_templates[$match[1]]->inline_doi_information();
            if ($inline_doi) {
                if ($this->add_if_new('doi', mb_trim($inline_doi[0]))) {
                    // Add doi
                    $this->set('title', mb_trim($inline_doi[1]));
                    report_modification("Converting inline DOI to DOI parameter");
                } elseif ($this->get('doi') === mb_trim($inline_doi[0])) {
                    // Already added by someone else
                    $this->set('title', mb_trim($inline_doi[1]));
                    report_modification("Remove duplicate inline DOI ");
                }
            }
        }
    }

    private function volume_issue_demix(string $data, string $param): void {
        if ($param === 'year') {
            return;
        }
        if (!in_array($param, VOL_NUM, true)) {
            report_error('volume_issue_demix ' . echoable($param)); // @codeCoverageIgnore
        }
        if (in_array($this->wikiname(), ['cite encyclopaedia', 'cite encyclopedia', 'cite book'], true)) {
            return;
        }
        if ($param === 'issue') {
            $the_issue = 'issue';
        } elseif ($param === 'number') {
            $the_issue = 'number';
            /** @phpstan-ignore identical.alwaysTrue */
        } elseif ($param === 'volume' && $this->has('number')) {
            $the_issue = 'number';
        } else {
            $the_issue = 'issue';
        }
        $data = mb_trim($data);
        $data = str_replace('--', '-', $data);
        if (
            preg_match("~^(\d+)\s*\((\d+(-|–|\–|\{\{ndash\}\})?\d*)\)$~", $data, $matches) ||
            preg_match("~^(?:vol\. |Volume |vol |vol\.|)(\d+)[,\s]\s*(?:no\.|number|issue|Iss.|no )\s*(\d+(-|–|\–|\{\{ndash\}\})?\d*)$~i", $data, $matches) ||
            preg_match("~^(\d+)\.(\d+)$~i", $data, $matches) ||
            preg_match("~^(\d+)\((\d+\/\d+)\)$~i", $data, $matches) ||
            preg_match("~^(\d+) \((\d+ Suppl\.* \d+)\)$~i", $data, $matches) ||
            preg_match("~^(\d+) \((Suppl\.* \d+)\)$~i", $data, $matches) ||
            preg_match("~^(\d+) (Suppl\.* \d+)\)$~i", $data, $matches) ||
            preg_match("~^(\d+) *\((S\d+)\)$~i", $data, $matches) ||
            preg_match("~^Vol\.?(\d+)\((\d+)\)$~", $data, $matches) ||
            preg_match("~^(\d+) +\(No(?:\.|\. | )(\d+)\)$~i", $data, $matches) ||
            preg_match("~^(\d+):(\d+)$~", $data, $matches) ||
            preg_match("~^(\d+) +\(Iss(?:\.|\. | )(\d+)\)$~i", $data, $matches)
        ) {
            $possible_volume = $matches[1];
            $possible_issue = $matches[2];
            if (preg_match("~^\d{4}.\d{4}$~", $possible_issue)) {
                return;
            } // Range of years
            if ($possible_issue === $this->get('year')) {
                return;
            }
            if ($possible_issue === $this->get('date')) {
                return;
            }
            if ($this->year() !== '' && mb_strpos($possible_issue, $this->year()) !== false) {
                return;
            }
            if (preg_match('~\d{4}~', $possible_issue)) {
                return;
            } // probably a year or range of years
            if ($param === 'volume') {
                if ($this->blank(ISSUE_ALIASES)) {
                    $this->add_if_new($the_issue, $possible_issue);
                    $this->set('volume', $possible_volume);
                } elseif (str_replace(".", "", $this->get('issue')) === str_replace(".", "", $possible_issue) || str_replace(".", "", $this->get('number')) === str_replace(".", "", $possible_issue)) {
                    $this->set('volume', $possible_volume);
                }
            } else {
                if ($this->blank('volume')) {
                    $this->set($the_issue, $possible_issue);
                    $this->add_if_new('volume', $possible_volume);
                } elseif ($this->get('volume') === $possible_volume) {
                    $this->set($the_issue, $possible_issue);
                }
            }
        } elseif (preg_match('~^\((\d+)\)\.?$~', $data, $matches)) {
            $this->set($param, $matches[1]);
            return;
        } elseif (preg_match('~^(\d+)\.$~', $data, $matches)) {
            $this->set($param, $matches[1]); // remove period
            return;
        }
        // volume misuse seems to be popular in cite book, and we would need to move volume to title
        // Obvious books
        if ($this->wikiname() === 'cite book') {
            return;
        }
        if ($this->wikiname() === 'citation' && ($this->has('chapter') || $this->has('isbn') || mb_strpos($this->rawtext, 'archive.org') !== false)) {
            return;
        }
        // Might not be a journal
        if (
            !in_array($this->wikiname(), ['citation', 'cite journal', 'cite web', 'cite magazine'], true) &&
            $this->get_without_comments_and_placeholders('issue') === '' &&
            $this->get_without_comments_and_placeholders('number') === '' &&
            $this->get_without_comments_and_placeholders('journal') === '' &&
            $this->get_without_comments_and_placeholders('magazine') === ''
        ) {
            return;
        }
        if ($param === 'volume') {
            if (preg_match("~^(?:vol\.|volume\s+|vol\s+|vol:)\s*([\dLXVI]+)$~i", $data, $matches)) {
                $data = $matches[1];
                $this->set('volume', $data);
            } elseif (preg_match("~^v\.\s+(\d+)$~i", $data, $matches)) {
                $data = $matches[1];
                $this->set('volume', $data);
            }
        }
        if ($param === 'issue' || $param === 'number') {
            if (preg_match("~^(?:num\.|number\s+|num\s+|num:|number:|iss\.|issue\s+|iss\s+|iss:|issue:)\s*([\dLXVI]+)$~i", $data, $matches)) {
                $data = $matches[1];
                $this->set($param, $data);
            }
        }
        if (!$this->blank(['doi', 'jstor', 'pmid', 'pmc'])) {
            // Have some data to fix it up with
            if ($param === 'issue' || $param === 'number') {
                if (preg_match("~^(?:vol\.|volume\s+|vol\s+|vol:)\s*([\dLXVI]+)$~i", $data, $matches)) {
                    $data = $matches[1];
                    if ($this->blank('volume')) {
                        $this->rename($param, 'volume', $data);
                    } elseif (mb_stripos($this->get('volume'), $data) !== false) {
                        $this->forget($param); // Duplicate data
                    }
                }
            }
            if ($param === 'volume') {
                if (preg_match("~^(?:num\.|number\s+|num\s+|num:|number:|iss\.|issue\s+|iss\s+|iss:|issue:)\s*([\dLXVI]+)$~i", $data, $matches)) {
                    $data = $matches[1];
                    if ($this->blank(['issue', 'number'])) {
                        $this->rename($param, 'issue', $data);
                    } elseif (mb_stripos($this->get('issue') . $this->get('number'), $data) !== false) {
                        $this->forget($param); // Duplicate data
                    }
                }
            }
        }
    }

    public function is_book_series(string $param): bool {
        return string_is_book_series($this->get($param));
    }

    public function has_good_free_copy(): bool {
        // Must link title - TODO add more if jstor-access or hdl-access link
        $this->tidy_parameter('pmc');
        $this->tidy_parameter('pmc-embargo-date');
        if (($this->has('pmc') && $this->blank('pmc-embargo-date') && preg_match('~^\d+$~', $this->get('pmc'))) || ($this->has('doi') && $this->get('doi-access') === 'free' && $this->blank(DOI_BROKEN_ALIASES) && doi_works($this->get('doi')))) {
            return true;
        }
        return false;
    }

    public function block_modifications(): void {
        // {{void}} should be just like a comment, BUT this code will not stop the normalization of the hidden template which has already been done
        $tmp = $this->parsed_text();
        while (preg_match_all('~' . sprintf(self::PLACEHOLDER_TEXT, '(\d+)') . '~', $tmp, $matches)) {
            $num_matches = count($matches[1]);
            for ($i = 0; $i < $num_matches; $i++) {
                $subtemplate = self::$all_templates[$matches[1][$i]];
                $tmp = str_replace($matches[0][$i], $subtemplate->parsed_text(), $tmp);
            }
        }
        // So we do not get an error when we parse a second time
        unset($this->rawtext); // @phan-suppress-current-line PhanTypeObjectUnsetDeclaredProperty
        $this->parse_text($tmp);
    }

    private static function localize_dates(int $time): string {
        if (self::$date_style === DateStyle::DATES_MDY) {
            $value = date('F j, Y', $time);
        } elseif (self::$date_style === DateStyle::DATES_DMY) {
            $value = date('j F Y', $time);
        } elseif (self::$date_style === DateStyle::DATES_ISO) {
            $value = date('Y-m-d', $time);
        } else {
            $value = date('j F Y', $time);
        }
        return $value;
    }
}
