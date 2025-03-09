<?php

declare(strict_types=1);

/*
 * Page contains methods that will do most of the higher-level work of expanding
 * citations on the wikipage associated with the Page object.
 * Provides functions to read, parse, expand text,
 * handle collected page modifications, and save the edited page text.
 */

require_once 'Comment.php';       // @codeCoverageIgnore
require_once 'Template.php';      // @codeCoverageIgnore
require_once 'apiFunctions.php';  // @codeCoverageIgnore
require_once 'expandFns.php';     // @codeCoverageIgnore
require_once 'user_messages.php'; // @codeCoverageIgnore
require_once 'Zotero.php';        // @codeCoverageIgnore
require_once 'constants.php';     // @codeCoverageIgnore

const UNPROTECTED_PAGE = ["autoconfirmed", "extendedconfirmed", "editautoreviewprotected"];
const PROTECTED_PAGE = ["sysop", "templateeditor"];

class Page {
    protected string $text = '';
    protected string $title = '';
    /** @var array<bool|array<string>> $modifications */
    private array $modifications = [];
    private DateStyle $date_style = DateStyle::DATES_WHATEVER;
    private VancStyle $name_list_style = VancStyle::NAME_LIST_STYLE_DEFAULT;
    private string $read_at = '';
    private string $start_text = '';
    private int $lastrevid = 0;
    private bool $page_error = false;
    private static bool $told_fast = false;
    public static string $last_title = '';

    public function __construct() {
        $this->construct_modifications_array();
        if (!self::$told_fast) {
            if (!SLOW_MODE) {
                report_info("Will skip the search for new bibcodes and the expanding of URLS in non-slow mode"); // @codeCoverageIgnore
            }
            self::$told_fast = true;
        }
    }

    public function get_text_from(string $title): bool {
        $this->construct_modifications_array(); // Could be new page

        $details = WikipediaBot::ReadDetails($title);

        if (!isset($details->query->pages)) {
            // @codeCoverageIgnoreStart
            $message = "Error: Could not fetch page.";
            if (isset($details->error->info)) {
                $message .= " " . (string) $details->error->info;
            }
            report_warning(echoable($message));
            return false;
            // @codeCoverageIgnoreEnd
        }
        foreach ($details->query->pages as $p) {
            /** @var object $my_details */
            $my_details = $p;
        }
        if (!isset($my_details)) {
            report_warning("Page fetch error - could not even get details"); // @codeCoverageIgnore
            return false;                                                                                                        // @codeCoverageIgnore
        }
        $this->read_at = $details->curtimestamp ?? '';

        $details = $my_details;
        if (isset($details->invalid)) {
            report_warning("Page invalid: " . (isset($details->invalidreason) ? echoable((string) $details->invalidreason) : ''));
            return false;
        }
        if ( !isset($details->touched) || !isset($details->lastrevid)) {
            report_warning("Could not even get the page.     Perhaps non-existent?");
            return false;
        }

        if (!isset($details->title)) {
            report_warning("Could not even get the page title.");   // @codeCoverageIgnore
            return false;                                           // @codeCoverageIgnore
        }

        if (!empty($details->protection)) {
            /** @var array<object> $the_protections */
            $the_protections = (array) $details->protection;
            foreach ($the_protections as $protects) {
                if (isset($protects->type) && (string) $protects->type === "edit" && isset($protects->level)) {
                    $the_level = (string) $protects->level;
                    if (in_array($the_level, UNPROTECTED_PAGE, true)) {
                        // We are good
                    } elseif (in_array($the_level, PROTECTED_PAGE, true)) {
                        report_warning("Page is protected.");
                        return false;
                    } else {
                        report_minor_error("Unexpected protection status: " . echoable($the_level));    // @codeCoverageIgnore
                    }
                }
            }
        }

        $this->title = (string) $details->title;
        self::$last_title = $this->title;
        $this->lastrevid = (int) $details->lastrevid ;

        $this->text = WikipediaBot::GetAPage($title);

        if ($this->text === '') {
            report_warning('Page ' . echoable($title) . ' from ' . str_replace(['/w/index.php', 'https://'], ['',''], WIKI_ROOT) . ' appears to be empty '); // @codeCoverageIgnore
            return false;                                                                                                                                    // @codeCoverageIgnore
        }
        $this->start_text = $this->text;
        $this->set_date_pattern();
        $this->set_name_list_style();

        if (preg_match('~\#redirect *\[\[~i', $this->text)) {
            report_warning("Page is a redirect."); // @codeCoverageIgnoreStart
            if (strlen($this->text) > 2000) {
                $test_text = preg_replace("~\[\[Category\:[^\]\{\}\[]+\]\]~", "", $this->text);
                if (strlen($test_text) > 1500) {
                     bot_debug_log($this->title . " is probably not a redirect");
                }
            }
            return false; // @codeCoverageIgnoreEnd
        }
        return true;
    }

    public function parse_text(string $text): void {
        $this->construct_modifications_array(); // Could be new page
        $this->text = $text;
        $this->start_text = $this->text;
        $this->set_date_pattern();
        $this->set_name_list_style();
        $this->title = '';
        self::$last_title = '';
        $this->read_at = '';
        $this->lastrevid = 0;
    }

    public function parsed_text(): string {
        return $this->text;
    }
    // $identifier: parameter to send to api_function, e.g. "pmid"
    // $templates: array of pointers to the templates
/** @param array<Template> $templates */
    public function expand_templates_from_identifier(string $identifier, array &$templates): void { // Pointer to save memory
        $ids = [];
        set_time_limit(120);
        switch ($identifier) {
            case 'pmid':
            case 'pmc':
                $api = 'entrez';
                break;
            case 'bibcode':
                $api = 'adsabs';
                break;
            case 'doi':
                $api = 'crossref';
                break;
            case 'url':
                $api = 'zotero';
                break;
            case 'jstor':
                $api = 'jstor';
                break;
            default:
                $api = $identifier;                                            // @codeCoverageIgnore
                report_error('expand_templates_from_identifier got: ' . $api); // @codeCoverageIgnore
        }
        $num_template = count($templates);
        for ($i = 0; $i < $num_template; $i++) {
            if (in_array($templates[$i]->wikiname(), TEMPLATES_WE_PROCESS, true)) {
                if ($templates[$i]->has($identifier)
                && !$templates[$i]->api_has_used($api, equivalent_parameters($identifier))) {
                        $ids[$i] = $templates[$i]->get_without_comments_and_placeholders($identifier);
                }
            }
        }
        $api_function = 'query_' . $identifier . '_api';
        $api_function($ids, $templates);

        foreach (array_keys($ids) as $i) {
            // Record this afterwards so we don't block the api_function itself
            $templates[$i]->record_api_usage($api, $identifier);
        }
    }

    public function expand_text(): bool {
        set_time_limit(120);
        $this->page_error = false;
        $this->announce_page();
        if (!$this->text) {
            report_warning("No text retrieved.\n");
            return false;
        }

        // COMMENTS AND NOWIKI ETC. //
        /** @var array<Comment>         $comments        */
        $comments    = $this->extract_object('Comment');
        /** @var array<Nowiki>          $nowiki          */
        $nowiki      = $this->extract_object('Nowiki');
        /** @var array<Chemistry>       $chemistry   */
        $chemistry   = $this->extract_object('Chemistry');
        /** @var array<Mathematics> $mathematics */
        $mathematics = $this->extract_object('Mathematics');
        /** @var array<Musicscores> $musicality  */
        $musicality  = $this->extract_object('Musicscores');
        /** @var array<Preformated> $preformated */
        $preformated = $this->extract_object('Preformated');
        set_time_limit(120);
        if (!$this->allow_bots()) {
            report_warning("Page marked with {{nobots}} template.    Skipping.");
            $this->text = $this->start_text;
            return false;
        }
        $citation_count = substr_count($this->text, '{{cite ') +
                                            substr_count($this->text, '{{Cite ') +
                                            substr_count($this->text, '{{citation') +
                                            substr_count($this->text, '{{Citation');
        $ref_count = substr_count($this->text, '<ref') + substr_count($this->text, '<Ref');
        // PLAIN URLS Converted to Templates
        // Ones like <ref>http://www.../....{{full|date=April 2016}}</ref> (?:full) so we can add others easily
        $this->text = preg_replace_callback(
                                            "~(<(?:\s*)ref[^>]*?>)(\s*\[?(https?:\/\/[^ >}{\]\[]+?)\]?\s*{{(?:full|Full citation needed)(?:|\|date=[a-zA-Z0-9 ]+)}})(<\s*?\/\s*?ref(?:\s*)>)~i",
                                            static function(array $matches): string {
                                                return $matches[1] . '{{cite web | url=' . wikifyURL($matches[3]) . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[4];
                                            },
                                            $this->text
                                            );
        // Ones like <ref>http://www.../....{{Bare URL inline|date=April 2016}}</ref>
        $this->text = preg_replace_callback(
                                            "~(<(?:\s*)ref[^>]*?>)(\s*\[?(https?:\/\/[^ >}{\]\[]+?)\]?\s*{{Bare URL inline(?:|\|date=[a-zA-Z0-9 ]+)}})(<\s*?\/\s*?ref(?:\s*)>)~i",
                                            static function(array $matches): string {
                                                return $matches[1] . '{{cite web | url=' . wikifyURL($matches[3]) . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[4];
                                            },
                                            $this->text
                                            );
        // Ones like <ref>http://www.../....</ref>; <ref>[http://www.../....]</ref>     Also, allow a trailing period, space+period, or comma
        $this->text = preg_replace_callback(
                                            "~(<(?:\s*)ref[^>]*?>)(\s*\[?(https?:\/\/[^ >}{\]\[]+?)[ \,\.]*\]?[\s\.\,]*)(<\s*?\/\s*?ref(?:\s*)>)~i",
                                            static function(array $matches): string {
                                                return $matches[1] . '{{cite web | url=' . wikifyURL($matches[3]) . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[4];
                                            },
                                            $this->text
                                            );
        // Ones like <ref>[http://www... http://www...]</ref>
        $this->text = preg_replace_callback(
                                            "~(<(?:\s*)ref[^>]*?>)((\s*\[)(https?:\/\/[^\s>\}\{\]\[]+?)(\s+)(https?:\/\/[^\s>\}\{\]\[]+?)(\s*\]\s*))(<\s*?\/\s*?ref(?:\s*)>)~i",
                                            static function(array $matches): string {
                                                if ($matches[4] === $matches[6]) {
                                                    return $matches[1] . '{{cite web | url=' . wikifyURL($matches[4]) . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[8] ;
                                                }
                                                return $matches[0];
                                            },
                                            $this->text
                                            );
        // PLAIN {{DOI}}, {{PMID}}, {{PMC}} {{isbn}} {{oclc}} {{bibcode}} {{arxiv}} Converted to templates
        $this->text = preg_replace_callback(        // like <ref>{{doi|10.1244/abc}}</ref>
                                            "~(<(?:\s*)ref[^>]*?>)(\s*\{\{(?:doi\|10\.\d{4,6}\/[^\s\}\{\|]+?|pmid\|\d{4,9}|pmc\|\d{4,9}|oclc\|\d{4,9}|isbn\|[0-9\-xX]+?|arxiv\|\d{4}\.\d{4,5}(?:|v\d+)|arxiv\|[a-z\.\-]{2,12}\/\d{7,8}(?:|v\d+)|bibcode\|[12]\d{3}[\w\d\.&]{15}|jstor\|[^\s\}\{\|]+?)\}\}\s*)(<\s*?\/\s*?ref(?:\s*)>)~i",
                                            static function(array $matches): string  {
                                                if (stripos($matches[2], 'arxiv')) {
                                                    $type = 'arxiv';
                                                } elseif (stripos($matches[2], 'isbn') || stripos($matches[2], 'oclc')) {
                                                    $type = 'book';
                                                } else {
                                                    $type = 'journal';
                                                }
                                                return $matches[1] . '{{cite ' . $type . ' | id=' . $matches[2] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[3];
                                            },
                                            $this->text
                                            );
        // PLAIN DOIS Converted to templates
        $this->text = preg_replace_callback(        // like <ref>10.1244/abc</ref>
                                            "~(<(?:\s*)ref[^>]*?>)(\s*10\.[0-9]{4,6}\/\S+?\s*)(<\s*?\/\s*?ref(?:\s*)>)~i",
                                            static function(array $matches): string {
                                                return $matches[1] . '{{cite journal | doi=' . str_replace('|', '%7C', $matches[2]) . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[3];
                                            },
                                            $this->text
                                            );
        if (
            ($ref_count < 2) ||
            (($citation_count/$ref_count) >= 0.5)
        ) {
            $this->text = preg_replace_callback( // like <ref>John Doe, [https://doi.org/10.1244/abc Foo], Bar 1789.</ref>
                                                 // also without titles on the urls
                            "~(<(?:\s*)ref[^>]*?>)([^\{\}<\[\]]+\[)(https?://\S+?/10\.[0-9]{4,6}\/[^\[\]\{\}\s]+?)( [^\]\[\{\}]+?\]|\])(\s*[^<\]\[]+?)(<\s*?\/\s*?ref(?:\s*)>)~i",
                            static function(array $matches): string  {
                                $UPPER = mb_strtoupper($matches[0]);
                                if (substr_count($UPPER, 'HTTP') !== 1 || // more than one url
                                        substr_count($UPPER, '10.') > 3 || // More than one doi probably
                                        substr_count($UPPER, '*') !== 0 || // A list!!!
                                        substr_count($UPPER, "\n") > 8 || // who knows
                                        substr_count($UPPER, 'SEE ALSO') !== 0 ||
                                        substr_count($UPPER, ', SEE ') !== 0 ||
                                        substr_count($UPPER, 'CITATION_BOT_PLACEHOLDER_COMMENT') !== 0 ||
                                        substr_count($UPPER, '{{CITE') !== 0 ||
                                        substr_count($UPPER, '{{CITATION') !== 0 ||
                                        substr_count($UPPER, '{{ CITE') !== 0 ||
                                        substr_count($UPPER, '{{ CITATION') !== 0 ||
                                        strpos($matches[1], 'note') !== false
                                   ) {
                                    return $matches[0];
                                }
                                return $matches[1] . '{{cite journal | url=' . wikifyURL($matches[3]) . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2] . $matches[3] . $matches[4] . $matches[5]) . ' }}' . $matches[6];
                            },
                            $this->text
                            );
        }
        // TEMPLATES
        set_time_limit(120);
        /** @var array<TripleBracket> $triplebrack */
        $triplebrack = $this->extract_object('TripleBracket');
        /** @var array<SingleBracket> $singlebrack */
        $singlebrack = $this->extract_object('SingleBracket');
        /** @var array<Template> $all_templates */
        $all_templates = $this->extract_object('Template');
        set_time_limit(120);
        if ($this->page_error) {
            $this->text = $this->start_text;
            if ($this->title !== "") {
                bot_debug_log($this->title . " page failed");
            }
            return false;
        }
        Template::$all_templates = &$all_templates; // Pointer to save memory
        Template::$date_style = $this->date_style;
        Template::$name_list_style = $this->name_list_style;
        foreach ($all_templates as $this_template) {
            if ($this_template->wikiname() === 'void') {
                $this_template->block_modifications();
            }
        }
        /** @var array<Template> $our_templates */
        $our_templates = [];
        /** @var array<Template> $our_templates_slight */
        $our_templates_slight = [];
        /** @var array<Template> $our_templates_conferences */
        $our_templates_conferences = [];
        /** @var array<Template> $our_templates_ieee */
        $our_templates_ieee = [];
        report_phase('Remedial work to prepare citations');
        foreach ($all_templates as $this_template) {
            set_time_limit(120);
            if (in_array($this_template->wikiname(), TEMPLATES_WE_PROCESS, true)) {
                $our_templates[] = $this_template;
                $this_template->correct_param_mistakes();
                $this_template->prepare();
            } elseif (in_array($this_template->wikiname(), TEMPLATES_WE_SLIGHTLY_PROCESS, true)) {
                $our_templates_slight[] = $this_template;
                $this_template->correct_param_mistakes();
                $this_template->prepare(); // does very little
                $this_template->get_identifiers_from_url();
                $this_template->expand_by_google_books();
                $this_template->tidy();
                $this_template->tidy_parameter('dead-url');
                $this_template->tidy_parameter('deadurl');
                $our_templates_ieee[] = $this_template;
            } elseif (in_array($this_template->wikiname(), TEMPLATES_WE_BARELY_PROCESS, true)) { // No capitalization of thesis, etc.
                $our_templates_slight[] = $this_template;
                $this_template->clean_google_books();
                $this_template->correct_param_mistakes();
                $this_template->get_identifiers_from_url();
                $this_template->tidy();
                $this_template->tidy_parameter('dead-url');
                $this_template->tidy_parameter('deadurl');
                if ($this_template->wikiname() === 'cite conference') {
                    $our_templates_conferences[] = $this_template;
                }                                                                               
            } elseif (in_array($this_template->wikiname(), TEMPLATES_WE_CHAPTER_URL, true)) {
                $our_templates_slight[] = $this_template;
                $this_template->rename('chapterurl', 'chapter-url');
            } elseif ($this_template->wikiname() === 'cite magazine' || $this_template->wikiname() === 'cite periodical') {
                $our_templates_slight[] = $this_template;
                if ($this_template->blank('magazine') && $this_template->has('work')) {
                    $this_template->rename('work', 'magazine');
                }
                if ($this_template->has('magazine')) {
                    $this_template->set('magazine', straighten_quotes(trim($this_template->get('magazine')), true));
                }
                $this_template->correct_param_mistakes();
                $this_template->prepare(); // does very little
                $this_template->get_identifiers_from_url();
                $this_template->expand_by_google_books();
                $this_template->tidy();
                $this_template->tidy_parameter('dead-url');
                $this_template->tidy_parameter('deadurl');
            } elseif ($this_template->wikiname() === 'cite lsa') {
                $this_template->clean_google_books();
                $this_template->forget('ref'); // Common parameter that does not actually work
                $this_template->tidy_parameter('title');
            } elseif ($this_template->wikiname() === 'cite odnb') {
                $this_template->clean_cite_odnb();
                $this_template->clean_google_books();
                $this_template->tidy_parameter('title');
            } elseif ($this_template->wikiname() === 'cite episode' || $this_template->wikiname() === 'cite interview') {
                $this_template->clean_google_books();
                $this_template->correct_param_mistakes();
                $this_template->tidy_parameter('dead-url');
                $this_template->tidy_parameter('deadurl');
                $this_template->tidy_parameter('title');
            } elseif ((strpos($this_template->wikiname(), 'cite ') === 0)    || (strpos($this_template->wikiname(), 'vcite ') === 0)) {
                $this_template->clean_google_books();
                $this_template->tidy_parameter('dead-url');
                $this_template->tidy_parameter('deadurl');
                $this_template->tidy_parameter('title');
                // THIS CATCH ALL NEEDS TO BE LAST IN THE LIST!!!!!!
            }
        }
        // BATCH API CALLS
        report_phase('Consult APIs to expand templates');
        set_time_limit(120);
        $this->expand_templates_from_identifier('doi', $our_templates);    // Do DOIs first!  Try again later for added DOIs
        $this->expand_templates_from_identifier('doi', $our_templates_slight);
        foreach ($our_templates_slight as $this_template) { // Is is really a journal, after expanding DOI
            if ($this_template->has('journal') &&
                    $this_template->has('doi') &&
                    ($this_template->has('volume') || $this_template->has('issue')) &&
                    ($this_template->has('year') || $this_template->has('date')) &&
                    ($this_template->has('page') || $this_template->has('pages')) &&
                    $this_template->has('title')) {
                $this_template->change_name_to('cite journal', true, true);
            }
            if ($this_template->has('url')) {
                $the_url = $this_template->get('url');
                // TODO - add more "blessed" hosts that probably should not be cite news OR have good Zotero translators
                $new_url = str_ireplace(['bangkokpost.com', 'nytimes.com', 'mdpi.com', 'frontiersin.org', 'plos.org', 'sciencedirect.com', 'onlinelibrary.wiley.com'], '', $the_url);
                if (($the_url !== $new_url) || $this_template->blank('title') || ($this_template->has('via') && $this_template->blank(WORK_ALIASES))) {
                     $array_of_template = [$this_template];
                     $this->expand_templates_from_identifier('url', $array_of_template);
                }
            }
        }
        $this->expand_templates_from_identifier('pmid', $our_templates);
        $this->expand_templates_from_identifier('pmc', $our_templates);
        $this->expand_templates_from_identifier('bibcode', $our_templates);
        $this->expand_templates_from_identifier('jstor', $our_templates);
        $this->expand_templates_from_identifier('doi', $our_templates);
        expand_arxiv_templates($our_templates);
        $this->expand_templates_from_identifier('url', $our_templates);
        Zotero::query_ieee_webpages($our_templates_ieee);
        Zotero::query_ieee_webpages($our_templates);

        report_phase('Expand individual templates by API calls');
        foreach ($our_templates as $this_template) {
            set_time_limit(120);
            $this_template->expand_by_google_books();
            $this_template->get_doi_from_crossref();
            $this_template->get_doi_from_semanticscholar();
            $this_template->find_pmid();
            if ($this_template->blank('bibcode') ||
                    stripos($this_template->get('bibcode'), 'arxiv') !== false ||
                    stripos($this_template->get('bibcode'), 'tmp') !== false) {
                $no_arxiv = $this_template->blank('arxiv');
                $this_template->expand_by_adsabs(); // Try to get a bibcode
                if (!$this_template->blank('arxiv') && $no_arxiv) {  // Added an arXiv.  Stuff to learn and sometimes even find a DOI -- VERY RARE
                    $tmp_array = [$this_template];                  // @codeCoverageIgnore
                    expand_arxiv_templates($tmp_array);         // @codeCoverageIgnore
                }
            }
            $this_template->get_open_access_url();
        }
        $this->expand_templates_from_identifier('doi', $our_templates);
        set_time_limit(120);
        Zotero::drop_urls_that_match_dois($our_templates);
        Zotero::drop_urls_that_match_dois($our_templates_conferences);

        // Last ditch usage of ISSN - This could mean running the bot again will add more things
        $issn_templates = array_merge(TEMPLATES_WE_PROCESS, TEMPLATES_WE_SLIGHTLY_PROCESS, ['cite magazine']);
        foreach ($all_templates as $this_template) {
            if (in_array($this_template->wikiname(), $issn_templates, true)) {
                $this_template->use_issn();
            }
        }
        expand_templates_from_archives($our_templates);

        report_phase('Remedial work to clean up templates');
        foreach ($our_templates as $this_template) {
            // Clean up:
            if (!$this_template->initial_author_params()) {
                $this_template->handle_et_al();
            }
            $this_template->final_tidy();

            // Record any modifications that have been made:
            $template_mods = $this_template->modifications();
            foreach (array_keys($template_mods) as $key) {
                if (!isset($this->modifications[$key])) {
                    $this->modifications[$key] = $template_mods[$key];                                       // @codeCoverageIgnore
                    report_minor_error('unexpected modifications key: ' . echoable((string) $key));  // @codeCoverageIgnore
                } elseif (is_array($this->modifications[$key])) {
                    $this->modifications[$key] = array_unique(array_merge($this->modifications[$key], $template_mods[$key]));
                } else {
                    $this->modifications[$key] = $this->modifications[$key] || $template_mods[$key]; // bool like mod_dashes
                }
            }
        }
        $log_bad_chapter = false;
        foreach ($all_templates as $this_template) {
            if ($this_template->has('chapter')) {
                if (in_array($this_template->wikiname(), ['cite journal', 'cite news'], true)) {
                    $log_bad_chapter = true;
                }
            }
        }
        if ($log_bad_chapter) { // We can fix these and find these fast
            bot_debug_log($this->title . " page has ignored chapter"); // @codeCoverageIgnore
        }

        foreach ($our_templates_slight as $this_template) {
            // Record any modifications that have been made:
            $template_mods = $this_template->modifications();
            foreach (array_keys($template_mods) as $key) {
                if (!isset($this->modifications[$key])) {
                    $this->modifications[$key] = $template_mods[$key];                                       // @codeCoverageIgnore
                    report_minor_error('unexpected modifications key: ' . echoable((string) $key));  // @codeCoverageIgnore
                } elseif (is_array($this->modifications[$key])) {
                    $this->modifications[$key] = array_unique(array_merge($this->modifications[$key], $template_mods[$key]));
                } else {
                    $this->modifications[$key] = $this->modifications[$key] || $template_mods[$key]; // bool like mod_dashes
                }
            }
            $this_template->final_tidy();
        }
        set_time_limit(120);
        // Release memory ASAP
        unset($our_templates);
        unset($our_templates_slight);
        unset($our_templates_conferences);
        unset($our_templates_ieee);

        $this->replace_object($all_templates);
        // remove circular memory reference that makes garbage collection harder and reset
        Template::$all_templates = [];
        Template::$date_style = DateStyle::DATES_WHATEVER;
        Template::$name_list_style = VancStyle::NAME_LIST_STYLE_DEFAULT;
        unset($all_templates);

        $this->text = safe_preg_replace('~(\{\{[Cc]ite ODNB\s*\|[^\{\}\_]+_?[^\{\}\_]+\}\}\s*)\{\{ODNBsub\}\}~u', '$1', $this->text); // Allow only one underscore to shield us from MATH etc.
        $this->text = safe_preg_replace('~(\{\{[Cc]ite ODNB\s*\|[^\{\}\_]*ref ?= ?\{\{sfn[^\{\}\_]+\}\}[^\{\}\_]*\}\}\s*)\{\{ODNBsub\}\}~u', '$1', $this->text); // Allow a ref={{sfn in the template

        set_time_limit(120);
        $this->replace_object($singlebrack);
        unset($singlebrack);
        $this->replace_object($triplebrack);
        unset($triplebrack);
        $this->replace_object($preformated);
        unset($preformated);
        $this->replace_object($musicality);
        unset($musicality);
        $this->replace_object($mathematics);
        unset($mathematics);
        $this->replace_object($chemistry);
        unset($chemistry);
        $this->replace_object($nowiki);
        unset($nowiki);
        $this->replace_object($comments);
        unset($comments);
        set_time_limit(120);

        if (stripos($this->text, 'CITATION_BOT_PLACEHOLDER') !== false) {
            echo '<p>', echoable($this->text), '</p>'; // @codeCoverageIgnoreStart
            $this->text = $this->start_text;
            if ($this->title !== "") {
                bot_debug_log($this->title . " page failed");
            }
            report_error('CITATION_BOT_PLACEHOLDER found after processing');  // @codeCoverageIgnoreEnd
        }

        // we often just fix Journal caps, so must be case sensitive compare
        // Avoid minor edits - gadget API will make these changes, since it does not check return code
        $caps_ok = ['isbn', '{{jstor', '{{youtube'];
        $last_first_in  = [' last=',  ' last =',  '|last=',  '|last =',  ' first=',  ' first =',  '|first=',  '|first =', 'ite newspaper', '|format=PDF', '|format = PDF', '|format =PDF', '|format= PDF', '| format=PDF', '| format = PDF', '| format =PDF', '| format= PDF', '|format=PDF ', '|format = PDF ', '|format =PDF ', '|format= PDF ', '| format=PDF ', '| format = PDF ', '| format =PDF ', '| format= PDF ', 'Cite ', 'cite ', 'ubscription required', 'newspaper'];
        $last_first_out = [' last1=', ' last1 =', '|last1=', '|last1 =', ' first1=', ' first1 =', '|first1=', '|first1 =','ite news',      '',            '',              '',             '',             '',             '',               '',              '',              '',             '',               '',              '',              '',              '',                '',               '',               'Cite',  'cite',  'ubscription',          'work'];
        // @codeCoverageIgnoreStart
        if ((WIKI_ROOT === 'https://simple.wikipedia.org/w/index.php') || (stripos($this->title, "draft:") === 0)) { // Backload clean-up
            $caps_ok = [];
            $last_first_in   = [];
            $last_first_out = [];
        } // @codeCoverageIgnoreEnd
        return strcmp(str_replace($last_first_in, $last_first_out, str_ireplace($caps_ok, $caps_ok, $this->text)),
                                    str_replace($last_first_in, $last_first_out, str_ireplace($caps_ok, $caps_ok, $this->start_text))) !== 0;
    }

    public function edit_summary(string $edit_summary_end = ''): string {
        $auto_summary = "";
        $altered_list = $this->modifications["changeonly"];
        if (count($altered_list) !== 0) {
            if (count($altered_list)===1) {
                $op = "Altered";
            } else {
                $op = "Alter:";
            }
            $auto_summary .= $op . " " . implode(", ", $altered_list) . ". ";
            unset($op);
        }
        unset($altered_list);
        if (strpos(implode(" ", $this->modifications["changeonly"]), 'url') !== false) {
            $auto_summary .= "URLs might have been anonymized. ";
        }
        if (count($this->modifications['additions']) !== 0) {
            /** @var array<string> $addns */
            $addns = $this->modifications["additions"];
            if (count($addns)===1) {
                $op = "Added";
            } else {
                $op = "Add:";
            }
            $auto_summary .= $op . " ";
            unset($op);
            $min_au = 9999;
            $max_au = 0;
            $min_ed = 9999;
            $max_ed = 0;
            while ($add = array_pop($addns)) {
                if (preg_match('~editor[^\d]*(\d+)~', $add, $match)) {
                    if ($match[1] < $min_ed) {
                        $min_ed = $match[1];
                    }
                    if ($match[1] > $max_ed) {
                        $max_ed = $match[1];
                    }
                } elseif (preg_match('~(?:author|last|first)(\d+)~', $add, $match)) {
                    if ($match[1] < $min_au) {
                        $min_au = $match[1];
                    }
                    if ($match[1] > $max_au) {
                        $max_au = $match[1];
                    }
                } else {
                    $auto_summary .= $add . ', ';
                }
            }
            if ($max_au) {
                $auto_summary .= "authors {$min_au}-{$max_au}. ";
            }
            if ($max_ed) {
                $auto_summary .= "editors {$min_ed}-{$max_ed}. ";
            }
            if (!$max_ed && !$max_au) {
                $auto_summary = substr($auto_summary, 0, -2) . '. ';
            }
        }

        if (count($this->modifications["deletions"]) !== 0) {
            $pos1 = array_search('url', $this->modifications["deletions"]);
            if ($pos1 !== false) {
                unset($this->modifications["deletions"][$pos1]);
            }
            $pos2 = array_search('chapter-url', $this->modifications["deletions"]);
            if ($pos2 !== false) {
                unset($this->modifications["deletions"][$pos2]);
            }
            $pos3 = array_search('chapterurl', $this->modifications["deletions"]);
            if ($pos3 !== false) {
                unset($this->modifications["deletions"][$pos3]);
            }
            $pos4 = array_search('accessdate', $this->modifications["deletions"]);
            if ($pos4 !== false) {
                unset($this->modifications["deletions"][$pos4]);
            }
            $pos5 = array_search('access-date', $this->modifications["deletions"]);
            if ($pos5 !== false) {
                unset($this->modifications["deletions"][$pos5]);
            }
            $pos6 = array_search(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'), $this->modifications["deletions"]);
            if ($pos6 !== false) {
                unset($this->modifications["deletions"][$pos6]);
            }
            if ($pos1 !==false || $pos2 !==false || $pos3 !==false) {
                if (strpos($auto_summary, 'chapter-url') !== false) {
                    $auto_summary .= "Removed or converted URL. ";
                } else {
                    $auto_summary .= "Removed URL that duplicated identifier. ";
                }
            }
            if ($pos4 !== false || $pos5 !== false) {
                $auto_summary .= "Removed access-date with no URL. ";
            }
            if ($pos6 !== false) {
                $auto_summary .= "Changed bare reference to CS1/2. ";
            }
        }
        $auto_summary .= ((count($this->modifications["deletions"]) !==0)
            ? "Removed parameters. "
            : ""
            ) . (($this->modifications["dashes"])
            ? "Formatted [[WP:ENDASH|dashes]]. "
            : "");
        if (count($this->modifications["deletions"]) !== 0 && count($this->modifications["additions"]) !== 0 && $this->modifications["names"]) {
            $auto_summary .= 'Some additions/deletions were parameter name changes. ';
        }
        $isbn978_added = (substr_count($this->text, '978 ') + substr_count($this->text, '978-')) - (substr_count($this->start_text, '978 ') + substr_count($this->start_text, '978-'));
        $isbn_added = (substr_count($this->text, 'isbn') + substr_count($this->text, 'ISBN')) -
                      (substr_count($this->start_text, 'isbn') + substr_count($this->start_text, 'ISBN'));
        if (($isbn978_added > 0) && ($isbn978_added > $isbn_added)) { // Still will get false positives for isbn=blank converted to isbn=978......
            $auto_summary .= 'Upgrade ISBN10 to 13. ';
        }
        if (stripos($auto_summary, 'template') !== false) {
            foreach (['cite|', 'Cite|', 'citebook', 'Citebook', 'cit book', 'Cit book', 'cite books', 'Cite books',
                'book reference', 'Book reference', 'citejournal', 'Citejournal', 'citeweb', 'Citeweb',
                'cite-web', 'Cite-web', 'cit web', 'Cit web', 'cit journal', 'Cit journal',
                'cit news', 'Cit news', 'cite url', 'Cite url', 'web cite', 'Web cite',
                'book cite', 'Book cite', 'cite-book', 'Cite-book', 'citenews', 'Citenews',
                'citepaper', 'Citepaper', 'cite new|', 'cite new|', 'citation journal', 'Citation journal',
                'cite new |', 'cite new |', 'cite |', 'Cite |',
            ] as $try_me) {
                    if (substr_count($this->text, $try_me) < substr_count($this->start_text, $try_me)) {
                        $auto_summary .= 'Removed Template redirect. ';
                        break;
                    }
            }
        }
        if (!$auto_summary) {
            $auto_summary = "Misc citation tidying. ";
        }
        $auto_summary .= "| [[:en:WP:UCB|Use this bot]]. [[:en:WP:DBUG|Report bugs]]. " . $edit_summary_end;

        switch (WIKI_BASE) {
            case 'en':
            case 'simple':
            case 'mdwiki':
                break; // English
            case 'mk':
                foreach (MK_TRANS as $eng => $not_eng) {
                    $auto_summary = str_replace($eng, $not_eng, $auto_summary);
                }
                break; // Macedonian
            case 'ru':
                foreach (RU_TRANS as $eng => $not_eng) {
                    $auto_summary = str_replace($eng, $not_eng, $auto_summary);
                }
                break; // Russian
            default:
                report_error('invalid wiki in edit summary');
        }
        return $auto_summary;
    }

    public function write(WikipediaBot $api, string $edit_summary_end = ''): bool {
        /** @var array<bool> $failures */
        static $failures = [false, false, false, false, false];
        if (!$this->allow_bots()) {
            report_warning("Can't write to " . echoable($this->title) . " - prohibited by {{bots}} template.");
            return false;
        }
        $failures[0] = $failures[1];
        $failures[1] = $failures[2];
        $failures[2] = $failures[3];
        $failures[3] = $failures[4];
        $failures[4] = false;
        throttle(); // This is only writing.    Not pages that are left unchanged
        if ($api->write_page($this->title, $this->text,
                        $this->edit_summary($edit_summary_end),
                        $this->lastrevid, $this->read_at)) {
            return true;
        }
        // @codeCoverageIgnoreStart
        if (TRAVIS) {
            return false;
        }
        sleep(9);    // could be database being locked
        report_info("Trying to write again after waiting");
        $return = $api->write_page($this->title, $this->text,
                    $this->edit_summary($edit_summary_end),
                    $this->lastrevid, $this->read_at);
        if ($return) {
            return true;
        }
        $failures[4] = true;
        if ($failures[0] && $failures[1] && $failures[2] && $failures[3]) {
            report_error("Five failures in a row -- shutting down the bot on page " . echoable($this->title));
        }
        sleep(9);
        return false;
        // @codeCoverageIgnoreEnd
    }

    /** @param class-string $class

        @return array<WikiThings|Template>
    */
    public function extract_object(string $class): array {
        $i = 0;
        $text = $this->text;
        /** @var array<string> $regexp_in */
        $regexp_in = $class::REGEXP;
        /** @var string $placeholder_text */
        $placeholder_text = $class::PLACEHOLDER_TEXT;
        /** @var bool $treat_identical_separately */
        $treat_identical_separately = $class::TREAT_IDENTICAL_SEPARATELY;
        /** @var array<WikiThings|Template> $objects */
        $objects = [];

        if (count($regexp_in) > 1) { // Loop over array four times, since sometimes more complex regex fails and starting over works
            foreach ($regexp_in as $regexp) {
                $regexp_in[] = $regexp;
            }
            foreach ($regexp_in as $regexp) {
                $regexp_in[] = $regexp;
            }
        }

        $preg_ok = true;
        foreach ($regexp_in as $regexp) {
            while ($preg_ok = preg_match($regexp, $text, $match)) {
                /** @var WikiThings|Template $obj */
                $obj = new $class();
                try {
                    $obj->parse_text($match[0]);
                } catch (Exception $e) {
                    $this->page_error = true;
                    $this->text = $text;
                    return $objects;
                }
                /** @var non-empty-string $separator */
                $separator = $match[0];
                $exploded = $treat_identical_separately ? explode($separator, $text, 2) : explode($separator, $text);
                unset($separator, $text, $match);
                $text = implode(sprintf($placeholder_text, $i), $exploded);
                $i++;
                unset($exploded);
                $objects[] = $obj;
            }
        }
        if ($preg_ok === false && isset($regexp)) {
            // @codeCoverageIgnoreStart
            $regexp = str_replace('~su', '~s', $regexp); // Try without unicode
            while ($preg_ok = preg_match($regexp, $text, $match)) { // Just use last most powerful REGEX
                $obj = new $class();
                try {
                    $obj->parse_text($match[0]);
                } catch (Exception $e) {
                    $this->page_error = true;
                    $this->text = $text;
                    return $objects;
                }
                /** @var non-empty-string $separator */
                $separator = $match[0];
                $exploded = $treat_identical_separately ? explode($separator, $text, 2) : explode($separator, $text);
                unset($separator, $text, $match);
                $text = implode(sprintf($placeholder_text, $i), $exploded);
                $i++;
                unset($exploded);
                $objects[] = $obj;
            }
            // @codeCoverageIgnoreEnd
        }

        if ($preg_ok === false) { // Something went wrong.  Often from bad wiki-text.
            gc_collect_cycles();
            $this->page_error = true;
            report_minor_error('Regular expression failure in ' . echoable($this->title) . ' when extracting ' . $class . 's');
            // @codeCoverageIgnoreStart
            if ($class === "Template") {
                if (WIKI_BASE === 'mk') {
                    $err1 = 'Следниот текст може да ви помогне да сфатите каде е грешката на страницата (Барајте само { и } знаци или незатворен коментар)';
                    $err2 = 'Ако тоа не е проблемот, тогаш стартувајте ја единствената страница со &prce=1 додадена на URL-то за да го промените моторот за парсирање';
                } elseif (WIKI_BASE === 'ru') {
                    $err1 = 'Следующий текст может помочь вам выяснить, где находится ошибка на странице (ищите одинокие символы { и } или незакрытый комментарий)';
                    $err2 = 'Если проблема не в этом, то запустите отдельную страницу с &prce=1, добавленным к URL, чтобы изменить механизм синтаксического анализа.';
                } else {
                    $err1 = 'The following text might help you figure out where the error on the page is (Look for lone { and } characters, or unclosed comment)';
                    $err2 = 'If that is not the problem, then run the single page with &prce=1 added to the URL to change the parsing engine';
                }
                echo '<p><h3>', $err1, '</h3><h4>', $err2, '</h4></p><p>', echoable($text), '</p>';
            }
            // @codeCoverageIgnoreEnd
        }
        $this->text = $text;
        return $objects;
    }

    /** @param array<WikiThings|Template> $objects */
    private function replace_object(array &$objects): void {  // Pointer to save memory
        set_time_limit(120);
        if ($objects) {
            $i = count($objects);
            $reverse = array_reverse($objects);
            foreach ($reverse as $obj) {
                --$i;
                $this->text = str_ireplace(sprintf($obj::PLACEHOLDER_TEXT, $i), $obj->parsed_text(), $this->text); // Case insensitive, since placeholder might get title case, etc.
            }
        }
    }

    private function announce_page(): void {
        $url_encoded_title =    urlencode($this->title);
        if ($url_encoded_title === ''){
            return;
        }
        html_echo("\n<hr>[" . date("H:i:s") . "] Processing page '<a href='" . WIKI_ROOT . "?title={$url_encoded_title}' style='font-weight:bold;'>"
                . echoable($this->title)
                . "</a>' &mdash; <a href='" . WIKI_ROOT . "?title={$url_encoded_title}"
                . "&action=edit' style='font-weight:bold;'>edit</a>&mdash;<a href='" . WIKI_ROOT . "?title={$url_encoded_title}"
                . "&action=history' style='font-weight:bold;'>history</a> ",
                "\n[" . date("H:i:s") . "] Processing page " . $this->title . "...\n");
    }

    private function allow_bots(): bool {
        if (defined("BAD_PAGE_API") && BAD_PAGE_API !== "") {    // When testing the bot on a specific page, allow "editing"
            return true; // @codeCoverageIgnore
        }
        // see {{bots}} and {{nobots}}
        $bot_username = 'Citation[ _]bot';
        if (preg_match('~\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.$bot_username.'.*?)\}\}~iS', $this->text)) {
            return false;
        }
        if (preg_match('~\{\{(bots\|allow=all|bots\|allow=.*?'.$bot_username.'.*?)\}\}~iS', $this->text)) {
            return true;
        }
        if (preg_match('~\{\{(bots\|allow=.*?)\}\}~iS', $this->text)) {
            return false;
        }
        return true;
    }

    private function set_name_list_style(): void {

        // get value of name-list-style parameter in "cs1 config" templates such as {{cs1 config |name-list-style=vanc }}

        $name_list_style = VancStyle::NAME_LIST_STYLE_DEFAULT;
        $pattern = '/{{\s*?cs1\s*?config[^}]*?name-list-style\s*?=\s*?(\w+)\b[^}]*?}}/im';
        if (preg_match($pattern, $this->text, $matches) && array_key_exists(1, $matches)) {
            $s = strtolower($matches[1]); // We ONLY deal with first one
            if ($s === 'default' || $s === 'none') {
                $name_list_style = VancStyle::NAME_LIST_STYLE_DEFAULT;
            } elseif ($s === 'vanc') {
                $name_list_style = VancStyle::NAME_LIST_STYLE_VANC;
            } elseif ($s === 'amp') {
                $name_list_style = VancStyle::NAME_LIST_STYLE_AMP;
            } elseif ($s !== '') {
                bot_debug_log('Weird name-list-style found: ' . echoable($s));
            }
        }
        $this->name_list_style = $name_list_style;
    }

    private function set_date_pattern(): void {
        // see {{use_mdy_dates}} and {{use_dmy_dates}}
        $date_style = DateStyle::DATES_WHATEVER;
        if (WIKI_BASE === 'mk' || WIKI_BASE === 'ru') {
            $date_style = DateStyle::DATES_ISO;
        }
        if (preg_match('~\{\{Use mdy dates[^\}\{]*\}\}~i', $this->text)) {
            $date_style = DateStyle::DATES_MDY;
        }
        if (preg_match('~\{\{Use mdy[^\}\{]*\}\}~i', $this->text)) {
            $date_style = DateStyle::DATES_MDY;
        }
        if (preg_match('~\{\{mdy[^\}\{]*\}\}~i', $this->text)) {
            $date_style = DateStyle::DATES_MDY;
        }
        if (preg_match('~\{\{Use dmy dates[^\}\{]*\}\}~i', $this->text)) {
            $date_style = DateStyle::DATES_DMY;
        }
        if (preg_match('~\{\{Use dmy[^\}\{]*\}\}~i', $this->text)) {
            $date_style = DateStyle::DATES_DMY;
        }
        if (preg_match('~\{\{dmy[^\}\{]*\}\}~i', $this->text)) {
            $date_style = DateStyle::DATES_DMY;
        }
        $this->date_style = $date_style;
    }

    private function construct_modifications_array(): void {
        $this->modifications['changeonly'] = [];
        $this->modifications['additions'] = [];
        $this->modifications['deletions'] = [];
        $this->modifications['modifications'] = [];
        $this->modifications['dashes'] = false;
        $this->modifications['names'] = false;
    }
}
