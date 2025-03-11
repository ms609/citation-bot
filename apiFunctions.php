<?php

declare(strict_types=1);

require_once 'constants.php';     // @codeCoverageIgnore
require_once 'user_messages.php'; // @codeCoverageIgnore
require_once 'Template.php';      // @codeCoverageIgnore
require_once 'NameTools.php';     // @codeCoverageIgnore

/** @param array<string> $pmids
    @param array<Template> $templates */
function query_pmid_api (array $pmids, array &$templates): void {  // Pointer to save memory
    entrez_api($pmids, $templates, 'pubmed');
}
/** @param array<string> $pmcs
    @param array<Template> $templates */
function query_pmc_api (array $pmcs, array &$templates): void {  // Pointer to save memory
    entrez_api($pmcs, $templates, 'pmc');
}

final class AdsAbsControl {
    private const MAX_CACHE_SIZE = 50000;
    private static int $big_counter = 0;
    private static int $small_counter = 0;
    /** @var array<string> $doi2bib */
    private static array $doi2bib = [];
    /** @var array<string> $bib2doi */
    private static array $bib2doi = [];

    public static function big_gave_up_yet(): bool {
        self::$big_counter = max(self::$big_counter - 1, 0);
        return self::$big_counter !== 0;
    }
    public static function big_give_up(): void {
        self::$big_counter = 1000;
    }
    public static function big_back_on(): void {
        self::$big_counter = 0;
    }

    public static function small_gave_up_yet(): bool {
        self::$small_counter = max(self::$small_counter - 1, 0);
        return self::$small_counter !== 0;
    }
    public static function small_give_up(): void {
        self::$small_counter = 1000;
    }
    public static function small_back_on(): void {
        self::$small_counter = 0;
    }

    public static function add_doi_map(string $bib, string $doi): void {
        self::check_memory_use();
        if ($bib === '' || $doi === '') {
            report_minor_error('Bad parameter in add_doi_map: ' . echoable($bib) . ' : ' . echoable($doi)); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
        if ($doi === 'X') {
            self::$bib2doi[$bib] = 'X';
        } elseif (doi_works($doi)) { // paranoid
            self::$bib2doi[$bib] = $doi;
            if (stripos($bib, 'tmp') === false && stripos($bib, 'arxiv') === false) {
                self::$doi2bib[$doi] = $bib;
            }
        }
    }
    public static function get_doi2bib(string $doi): string {
        return (string) @self::$doi2bib[$doi];
    }
    public static function get_bib2doi(string $bib): string {
        return (string) @self::$bib2doi[$bib];
    }

    public static function check_memory_use(): void {
        $usage = count(self::$doi2bib) + count(self::$bib2doi);
        if ($usage > self::MAX_CACHE_SIZE) {
            self::free_memory(); // @codeCoverageIgnore
        }
    }
    public static function free_memory(): void {
        self::$doi2bib = [];
        self::$bib2doi = [];
        gc_collect_cycles();
    }

}

function is_a_book_bibcode(string $id): bool {
    $check = str_replace(['book', 'conf', 'PhD'], '', $id);
    return ($check !== $id);
}

/**
  @param array<string> $ids
  @param array<Template> $templates
*/
function entrez_api(array $ids, array &$templates, string $db): void {    // Pointer to save memory
    set_time_limit(120);
    if (!count($ids) ||
        $ids === ['XYZ'] ||
        $ids === ['1'] ||
        $ids === ['']) {
        return; // junk data from test suite
    }
    if ($db !== 'pubmed' && $db !== 'pmc') {
        report_error("Invalid Entrez type passed in: " . echoable($db));  // @codeCoverageIgnore
    }

    report_action("Using {$db} API to retrieve publication details: ");
    $xml = get_entrez_xml($db, implode(',', $ids));

    if ($xml === null) {
        report_warning("Error in PubMed search: No response from Entrez server");    // @codeCoverageIgnore
        return;                                                                // @codeCoverageIgnore
    }

    // A few PMC do not have any data, just pictures of stuff
    if (isset($xml->DocSum->Item) && count($xml->DocSum->Item) > 0) {
        foreach($xml->DocSum as $document) {
            report_info("Found match for {$db} identifier " . echoable((string) $document->Id));
            foreach($ids as $template_key => $an_id) { // Cannot use array_search since that only returns first
                $an_id = (string) $an_id;
                if (!array_key_exists($template_key, $templates)) {
                    bot_debug_log('Key not found in entrez_api ' . (string) $template_key . ' ' . $an_id); // @codeCoverageIgnore
                    $an_id = '-3333';  // @codeCoverageIgnore
                }
                if ($an_id === (string) $document->Id) {
                    $this_template = $templates[$template_key];
                    $this_template->record_api_usage('entrez', $db === 'pubmed' ? 'pmid' : 'pmc');

                    foreach ($document->Item as $item) {
                        if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $item, $match)) {
                            $this_template->add_if_new('doi', $match[0], 'entrez');
                        }
                        switch ($item["Name"]) {
                            case "Title":
                                $this_template->add_if_new('title', str_replace(["[", "]"], "", (string) $item), 'entrez'); // add_if_new will format the title
                                break;
                            case "PubDate":
                                if (preg_match("~(\d+)\s*(\w*)~", (string) $item, $match)) {
                                    $this_template->add_if_new('year', $match[1], 'entrez');
                                }
                                break;
                            case "FullJournalName":
                                $this_template->add_if_new('journal', mb_ucwords((string) $item), 'entrez'); // add_if_new will format the title
                                break;
                            case "Volume":
                                $this_template->add_if_new('volume', (string) $item, 'entrez');
                                break;
                            case "Issue":
                                $this_template->add_if_new('issue', (string) $item, 'entrez');
                                break;
                            case "Pages":
                                $this_template->add_if_new('pages', (string) $item, 'entrez');
                                break;
                            case "PmId":
                                $this_template->add_if_new('pmid', (string) $item, 'entrez');
                                break;
                            case "AuthorList":
                                $i = 0;
                                foreach ($item->Item as $key => $subItem) {
                                    $subItem = (string) $subItem;
                                    if (preg_match('~^\d~', $subItem)) { // Author started with a number, skip all remaining authors.
                                        break;    // @codeCoverageIgnore
                                    } elseif ((string) $key === "CollectiveName") { // This is often really long string of gibberish
                                        break;    // @codeCoverageIgnore
                                    } elseif (strlen($subItem) > 100) {
                                        break;    // @codeCoverageIgnore
                                    } elseif (author_is_human($subItem)) {
                                        $jr_test = junior_test($subItem);
                                        $subItem = $jr_test[0];
                                        $junior = $jr_test[1];
                                        if (preg_match("~(.*) (\w+)$~", $subItem, $names)) {
                                            $first = trim(preg_replace('~(?<=[A-Z])([A-Z])~', ". $1", $names[2]));
                                            if (strpos($first, '.') && substr($first, -1) !== '.') {
                                                $first .= '.';
                                            }
                                            $i++;
                                            $this_template->add_if_new("author{$i}", $names[1] . $junior . ',' . $first, 'entrez');
                                        }
                                    } else {
                                        // We probably have a committee or similar.    Just use 'author$i'.
                                        $i++;
                                        $this_template->add_if_new("author{$i}", $subItem, 'entrez');
                                    }
                                }
                                break;
                            case "LangList":
                            case 'ISSN':
                                break;
                            case "ArticleIds":
                                foreach ($item->Item as $subItem) {
                                    switch ($subItem["Name"]) {
                                        case "pubmed":
                                        case "pmid":
                                            preg_match("~\d+~", (string) $subItem, $match);
                                            $this_template->add_if_new("pmid", $match[0], 'entrez');
                                            break;
                                        case "pmc":
                                            preg_match("~\d+~", (string) $subItem, $match);
                                            $this_template->add_if_new('pmc', $match[0], 'entrez');
                                            break;
                                        case "pmcid":
                                            if (preg_match("~embargo-date: ?(\d{4})\/(\d{2})\/(\d{2})~", (string) $subItem, $match)) {
                                                    $date_emb = date("F j, Y", mktime(0, 0, 0, (int) $match[2], (int) $match[3], (int) $match[1])); // @codeCoverageIgnore
                                                    $this_template->add_if_new('pmc-embargo-date', $date_emb, 'entrez');                              // @codeCoverageIgnore
                                            }
                                            break;
                                        case "doi":
                                        case "pii":
                                            if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $subItem, $match)) {
                                                $this_template->add_if_new('doi', $match[0], 'entrez');
                                            }
                                    }
                                }
                                break;
                        }
                    }
                }
            }
        }
    }
    return;
}

/**
  @param array<string> $bibcodes
  @param array<Template> $templates
*/
function query_bibcode_api(array $bibcodes, array &$templates): void {  // Pointer to save memory
    adsabs_api($bibcodes, $templates, 'bibcode');
}

/**
  @param array<Template> $templates
*/
function expand_arxiv_templates (array &$templates): void {    // Pointer to save memory
    $ids = [];
    $arxiv_templates = [];
    foreach ($templates as $this_template) {
        if ($this_template->wikiname() === 'cite arxiv') {
            $this_template->rename('arxiv', 'eprint');
        } else {
            $this_template->rename('eprint', 'arxiv');
        }
        $eprint = str_ireplace("arXiv:", "", $this_template->get('eprint') . $this_template->get('arxiv'));
        if ($eprint && stripos($eprint, 'CITATION_BOT') === false) {
            $ids[] = $eprint;
            $arxiv_templates[] = $this_template;
        }
    }
    arxiv_api($ids, $arxiv_templates);
}

/**
  @param array<string> $ids
  @param array<Template> $templates
*/
function arxiv_api(array $ids, array &$templates): void {  // Pointer to save memory
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, []);
    }
    set_time_limit(120);
    if (count($ids) === 0) {
        return;
    }
    report_action("Getting data from arXiv API");
    /** @psalm-taint-escape ssrf */
    $request = "https://export.arxiv.org/api/query?start=0&max_results=2000&id_list=" . implode(',', $ids);
    curl_setopt($ch, CURLOPT_URL, $request);
    $response = bot_curl_exec($ch);
    if ($response) {
        $xml = @simplexml_load_string(
            preg_replace("~(</?)(\w+):([^>]*>)~", "$1$2$3", $response)
        );
        unset($response);
    } else {
        report_warning("No response from arXiv.");        // @codeCoverageIgnore
        return;                                      // @codeCoverageIgnore
    }
    if (!is_object($xml)) {
        report_warning("No valid from arXiv.");        // @codeCoverageIgnore
        return;                                  // @codeCoverageIgnore
    }
    if ((string) $xml->entry->title === "Error") {
        $the_error = (string) $xml->entry->summary;
        if (stripos($the_error, 'incorrect id format for') !== false) {
            report_warning("arXiv search failed: " . echoable($the_error));
        } else {
            report_minor_error("arXiv search failed - please report the error: " . echoable($the_error));    // @codeCoverageIgnore
        }
        return;
    }

    $this_template = current($templates); // advance at end of foreach loop
    foreach ($xml->entry as $entry) {
        $i = 0;
        report_info("Found match for arXiv " . echoable($ids[$i]));
        if ($this_template->add_if_new("doi", (string) $entry->arxivdoi, 'arxiv')) {
            if ($this_template->blank(['journal', 'volume', 'issue']) && $this_template->has('title')) {
                // Move outdated/bad arXiv title out of the way
                $the_arxiv_title = $this_template->get('title');
                $the_arxiv_contribution = $this_template->get('contribution');
                if ($the_arxiv_contribution !== '') {
                    $this_template->set('contribution', '');
                }
                $this_template->set('title', '');
                expand_by_doi($this_template);
                if ($this_template->blank('title')) {
                    $this_template->set('title', $the_arxiv_title);
                    if ($the_arxiv_contribution !== '') {
                        $this_template->set('contribution', $the_arxiv_contribution);
                    }
                } else {
                    if ($the_arxiv_contribution !== '' && $this_template->blank('contribution')) {
                        $this_template->forget('contribution');
                    }
                }
                unset($the_arxiv_title);
                unset($the_arxiv_contribution);
            } else {
                expand_by_doi($this_template);
            }
        }
        foreach ($entry->author as $auth) {
            $i++;
            $name = (string) $auth->name;
            if (preg_match("~(.+\.)(.+?)$~", $name, $names) || preg_match('~^\s*(\S+) (\S+)\s*$~', $name, $names)) {
                $this_template->add_if_new("last{$i}", $names[2], 'arxiv');
                $this_template->add_if_new("first{$i}", $names[1], 'arxiv');
            } else {
                $this_template->add_if_new("author{$i}", $name, 'arxiv');
            }
            if ($this_template->blank(["last{$i}", "first{$i}", "author{$i}"])) {
                $i--;    // Deal with authors that are empty or just a colon as in https://export.arxiv.org/api/query?start=0&max_results=2000&id_list=2112.04678
            }
        }
        $the_title = (string) $entry->title;
        // arXiv fixes these when it sees them
        while (preg_match('~\$\^{(\d+)}\$~', $the_title, $match)) {
            $the_title = str_replace($match[0], '<sup>' . $match[1] . '</sup>', $the_title); // @codeCoverageIgnore
        }
        while (preg_match('~\$_(\d+)\$~', $the_title, $match)) {
            $the_title = str_replace($match[0], '<sub>' . $match[1] . '</sub>', $the_title); // @codeCoverageIgnore
        }
        while (preg_match('~\\ce{([^}{^ ]+)}~', $the_title, $match)) {    // arXiv fixes these when it sees them
            $the_title = str_replace($match[0], ' ' . $match[1] . ' ', $the_title);    // @codeCoverageIgnore
            $the_title = str_replace('  ', ' ', $the_title);                          // @codeCoverageIgnore
        }
        $this_template->add_if_new("title", $the_title, 'arxiv'); // Formatted by add_if_new
        $this_template->add_if_new("class", (string) $entry->category["term"], 'arxiv');
        $int_time = strtotime((string) $entry->published);
        if ($int_time) {
            $this_template->add_if_new("year", date("Y", $int_time), 'arxiv');
        }

        if ($this_template->has('publisher')) {
            if (stripos($this_template->get('publisher'), 'arxiv') !== false) {
                $this_template->forget('publisher');
            }
        }
        $this_template = next($templates);
    }
    if ($this_template !== false) {
        report_minor_error('Unexpected error in arxiv_api()' . echoable($this_template->parsed_text()));    // @codeCoverageIgnore
    }
    return;
}

/**
  @param array<string> $ids
  @param array<Template> $templates
*/
function adsabs_api(array $ids, array &$templates, string $identifier): void {  // Pointer to save memory
    set_time_limit(120);
    if (count($ids) === 0) {
        return;
    }

    foreach ($ids as $key => $bibcode) {
        if (stripos($bibcode, 'CITATION') !== false || strlen($bibcode) !== 19) {
            unset($ids[$key]);  // @codeCoverageIgnore
        }
    }

    // Use cache
    foreach ($templates as $template) {
        if ($template->has('bibcode') && $template->blank('doi')) {
            $doi = AdsAbsControl::get_bib2doi($template->get('bibcode'));
            if (doi_works($doi)) {
                $template->add_if_new('doi', $doi);
            }
        }
    }

    $NONE_IS_INCOMPLETE = true;
    foreach ($templates as $template) {
        if ($template->has('bibcode') && $template->incomplete()) {
            $NONE_IS_INCOMPLETE = false;
            break;
        }
        if (stripos($template->get('bibcode'), 'tmp') !== false || stripos($template->get('bibcode'), 'arxiv') !== false) {
            $NONE_IS_INCOMPLETE = false;
            break;
        }
    }
    if ($NONE_IS_INCOMPLETE ||
        AdsAbsControl::big_gave_up_yet() || !PHP_ADSABSAPIKEY) {
        return;
    }

    // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/API_documentation_UNIXshell/Search_API.ipynb
    $adsabs_url = "https://" . (TRAVIS ? 'qa' : 'api')
                    . ".adsabs.harvard.edu/v1/search/bigquery?q=*:*"
                    . "&fl=arxiv_class,author,bibcode,doi,doctype,identifier,"
                    . "issue,page,pub,pubdate,title,volume,year&rows=2000";

    report_action("Expanding from BibCodes via AdsAbs API");
    $curl_opts=[
        CURLOPT_URL => $adsabs_url,
        CURLOPT_HTTPHEADER => ['Content-Type: big-query/csv', 'Authorization: Bearer ' . PHP_ADSABSAPIKEY],
        CURLOPT_HEADER => "1",
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => "{$identifier}\n" . implode("\n", $ids),
    ];
    $response = Bibcode_Response_Processing($curl_opts, $adsabs_url);
    if (!isset($response->docs)) {
        return;
    }

    foreach ($response->docs as $record) { // Check for remapped bibcodes
        $record = (object) $record; // Make static analysis happy
        if (isset($record->bibcode) && !in_array($record->bibcode, $ids, true) && isset($record->identifier)) {
            foreach ($record->identifier as $identity) {
                if (in_array($identity, $ids, true)) {
                    $record->citation_bot_new_bibcode = $record->bibcode; // save it
                    $record->bibcode = $identity; // unmap it
                }
            }
        }
    }

    $matched_ids = [];
    foreach ($response->docs as $record) {
        report_info("Found match for bibcode " . bibcode_link($record->bibcode));
        $matched_ids[] = $record->bibcode;
        foreach($ids as $template_key => $an_id) { // Cannot use array_search since that only returns first
            if (isset($record->bibcode) && strtolower($an_id) === strtolower((string) $record->bibcode)) { // BibCodes at not case-sensitive
                $this_template = $templates[$template_key];
                if (isset($record->citation_bot_new_bibcode)) {
                    $this_template->set('bibcode', (string) $record->citation_bot_new_bibcode);
                    $record->bibcode = $record->citation_bot_new_bibcode;
                    unset($record->citation_bot_new_bibcode);
                } elseif ($an_id !== (string) $record->bibcode) {  // Existing one is wrong case
                    $this_template->set('bibcode', (string) $record->bibcode);
                }
                if (is_a_book_bibcode($an_id)) {
                    expand_book_adsabs($this_template, $record);
                } else {
                    process_bibcode_data($this_template, $record);
                }
            }
        }
    }
    $unmatched_ids = array_udiff($ids, $matched_ids, 'strcasecmp');
    if (count($unmatched_ids)) {
        foreach ($unmatched_ids as $bad_boy) {
            if (preg_match('~^(\d{4}NatSR....)E(.....)$~i', $bad_boy, $match_bad)) {
                $good_boy = $match_bad[1] . '.' . $match_bad[2];
                foreach ($templates as $template) {
                    if ($template->get('bibcode') === $bad_boy) {
                        $template->set('bibcode', $good_boy);
                    }
                }
            } else {
                bot_debug_log("No match for bibcode identifier: " . $bad_boy);
                report_warning("No match for bibcode identifier: " . $bad_boy);
            }
        }
            
    }
    foreach ($templates as $template) {
        if ($template->blank(['year', 'date']) && preg_match('~^(\d{4}).*book.*$~', $template->get('bibcode'), $matches)) {
            $template->add_if_new('year', $matches[1]); // Fail safe book code to grab a year directly from the bibcode itself
        }
    }
    return;
}

/** @param array<string> $_ids
    @param array<Template> $templates */
function query_doi_api(array $_ids, array &$templates): void { // $id not used yet  // Pointer to save memory
    foreach ($templates as $template) {
        expand_by_doi($template);
    }
    return;
}

function expand_by_doi(Template $template, bool $force = false): void {
    set_time_limit(120);
    $template->verify_doi();  // Sometimes CrossRef has Data even when DOI is broken, so try CrossRef anyway even when return is false
    $doi = $template->get_without_comments_and_placeholders('doi');
    if ($doi === $template->last_searched_doi) {
        return;
    }
    $template->last_searched_doi = $doi;
    if (preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
        return;
    }
    if (isset(BAD_DOI_ARRAY[$doi])) { // Really bad ones that do not really exist at all
        return;
    }
    if ($doi && preg_match('~^10\.2307/(\d+)$~', $doi)) {
        $template->add_if_new('jstor', substr($doi, 8));
    }
    if ($doi && ($force || $template->incomplete())) {
        $crossRef = query_crossref($doi);
        if ($crossRef) {
            if (in_array(strtolower((string) @$crossRef->article_title), BAD_ACCEPTED_MANUSCRIPT_TITLES, true)) {
                return ;
            }
            if ($template->has('title') && trim((string) @$crossRef->article_title) && $template->get('title') !== 'none') { // Verify title of DOI matches existing data somewhat
                $bad_data = true;
                $new = (string) $crossRef->article_title;
                if (preg_match('~^(.................+)[\.\?]\s+([IVX]+)\.\s.+$~i', $new, $matches)) {
                    $new = $matches[1];
                    $new_roman = $matches[2];
                } elseif (preg_match('~^([IVX]+)\.[\s\-\—]*(.................+)$~i', $new, $matches)) {
                    $new = $matches[2];
                    $new_roman = $matches[1];
                } else {
                    $new_roman = false;
                }
                foreach (THINGS_THAT_ARE_TITLES as $possible) {
                    if ($template->has($possible)) {
                        $old = $template->get($possible);
                        if (preg_match('~^(.................+)[\.\?]\s+([IVX]+)\.\s.+$~i', $old, $matches)) {
                            $old = $matches[1];
                            $old_roman = $matches[2];
                        } elseif (preg_match('~^([IVX]+)\.[\s\-\—]*(.................+)$~i', $old, $matches)) {
                            $old = $matches[2];
                            $old_roman = $matches[1];
                        } else {
                            $old_roman = false;
                        }
                        if (titles_are_similar($old, $new)) {
                            if ($old_roman && $new_roman) {
                                if ($old_roman === $new_roman) { // If they got roman numeral truncted, then must match
                                    $bad_data = false;
                                    break;
                                }
                            } else {
                                $bad_data = false;
                                break;
                            }
                        }
                    }
                }
                if (isset($crossRef->series_title)) {
                    foreach (THINGS_THAT_ARE_TITLES as $possible) { // Series === series could easily be false positive
                        if ($template->has($possible) && titles_are_similar(preg_replace("~# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #~i", "�", $template->get($possible)), (string) $crossRef->series_title)) {
                            $bad_data = false;
                            break;
                        }
                    }
                }
                if ($bad_data) {
                    report_warning("CrossRef title did not match existing title: doi:" . doi_link($doi));
                    if (isset($crossRef->series_title)) {
                        report_info("Possible new title: " . str_replace("\n", "", echoable((string) $crossRef->series_title)));
                    }
                    if (isset($crossRef->article_title)) {
                        report_info("Possible new title: " .  echoable((string) $crossRef->article_title));
                    }
                    foreach (THINGS_THAT_ARE_TITLES as $possible) {
                        if ($template->has($possible)) {
                            report_info("Existing old title: " .  echoable(preg_replace("~# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #~i", "�", $template->get($possible))));
                        }
                    }
                    return;
                }
            }
            report_action("Querying CrossRef: doi:" . doi_link($doi));

            if ((string) @$crossRef->volume_title === 'Professional Paper') {
                unset($crossRef->volume_title);
            }
            if ((string) @$crossRef->series_title === 'Professional Paper') {
                unset($crossRef->series_title);
            }
            if ($template->has('book-title')) {
                unset($crossRef->volume_title);
            }

            if ($crossRef->volume_title && ($template->blank(WORK_ALIASES) || $template->wikiname() === 'cite book')) {
                if (mb_strtolower($template->get('title')) === mb_strtolower((string) $crossRef->article_title)) {
                    $template->rename('title', 'chapter');
                } else {
                    $new_title = CrossRefTitle($doi);
                    if ($new_title !== '' && $crossRef->article_title) {
                        $template->add_if_new('chapter', $new_title);
                    } else {
                        $template->add_if_new('chapter', restore_italics((string) $crossRef->article_title), 'crossref');
                    }
                }
                $template->add_if_new('title', restore_italics((string) $crossRef->volume_title), 'crossref'); // add_if_new will wikify title and sanitize the string
            } else {
                $new_title = CrossRefTitle($doi);
                if ($new_title !== '' && $crossRef->article_title) {
                    $template->add_if_new('title', $new_title, 'crossref');
                } else {
                    $template->add_if_new('title', restore_italics((string) $crossRef->article_title), 'crossref');
                }
            }
            $template->add_if_new('series', (string) $crossRef->series_title, 'crossref'); // add_if_new will format the title for a series?
            if (strpos($doi, '10.7817/jameroriesoci') === false || (string) $crossRef->year !== '2021') { // 10.7817/jameroriesoci "re-published" everything in 2021
                $template->add_if_new("year", (string) $crossRef->year, 'crossref');
            }
            if (    $template->blank(['editor', 'editor1', 'editor-last', 'editor1-last', 'editor-last1']) // If editors present, authors may not be desired
                    && $crossRef->contributors->contributor
                ) {
                $au_i = 0;
                $ed_i = 0;
                // Check to see whether a single author is already set
                // This might be, for example, a collaboration
                $existing_author = $template->first_author();
                $add_authors = $existing_author === '' || author_is_human($existing_author);

                foreach ($crossRef->contributors->contributor as $author) {
                    if (strtoupper((string) $author->surname) === '&NA;') {
                        break; // No Author, leave loop now!  Have only seen upper-case in the wild
                    }
                    if ((string) $author["contributor_role"] === 'editor') {
                        ++$ed_i;
                        $ed_i_str = (string) $ed_i;
                        if ($ed_i < 31 && !isset($crossRef->journal_title)) {
                            $template->add_if_new("editor-last" . $ed_i_str, format_surname((string) $author->surname), 'crossref');
                            $template->add_if_new("editor-first" . $ed_i_str, format_forename((string) $author->given_name), 'crossref');
                        }
                    } elseif ((string) $author['contributor_role'] === 'author' && $add_authors) {
                        ++$au_i;
                        $au_i_str = (string) $au_i;
                        if ((string) $author->surname === 'Editor' && (string) $author->given_name === 'The') {
                            $template->add_if_new("author" . $au_i_str, 'The Editor', 'crossref');
                        } else {
                            $template->add_if_new("last" . $au_i_str, format_surname((string) $author->surname), 'crossref');
                            $template->add_if_new("first" . $au_i_str, format_forename((string) $author->given_name), 'crossref');
                        }
                    }
                }
            }
            $template->add_if_new('isbn', (string) $crossRef->isbn, 'crossref');
            $template->add_if_new('journal', (string) $crossRef->journal_title); // add_if_new will format the title
            if ((int) $crossRef->volume > 0) {
                $template->add_if_new('volume', (string) $crossRef->volume, 'crossref');
            }
            if (((strpos((string) $crossRef->issue, '-') > 0 || (int) $crossRef->issue > 1))) {
            // "1" may refer to a journal without issue numbers,
            //  e.g. 10.1146/annurev.fl.23.010191.001111, as well as a genuine issue 1.    Best ignore.
                $template->add_if_new('issue', (string) $crossRef->issue, 'crossref');
            }
            if ($template->blank("page")) {
                if ($crossRef->last_page && (strcmp((string) $crossRef->first_page, (string) $crossRef->last_page) !== 0)) {
                    if (strpos((string) $crossRef->first_page . (string) $crossRef->last_page, '-') === false) { // Very rarely get stuff like volume/issue/year added to pages
                        $template->add_if_new("pages", $crossRef->first_page . "-" . $crossRef->last_page, 'crossref'); //replaced by an endash later in script
                    }
                } else {
                    if (strpos((string) $crossRef->first_page, '-') === false) { // Very rarely get stuff like volume/issue/year added to pages
                        $template->add_if_new("pages", (string) $crossRef->first_page, 'crossref');
                    }
                }
            }
        } else {
            report_info("No CrossRef record found for doi '" . echoable($doi) ."'");
            expand_doi_with_dx($template, $doi);
        }
    }
    return;
}

function query_crossref(string $doi): ?object {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, []);
    }
    if (strpos($doi, '10.2307') === 0) {
        return null; // jstor API is better
    }
    set_time_limit(120);
    $url = "https://www.crossref.org/openurl/?pid=" . CROSSREFUSERNAME . "&id=doi:" . doi_encode($doi) . "&noredirect=TRUE";
    curl_setopt($ch, CURLOPT_URL, $url);
    for ($i = 0; $i < 2; $i++) {
        $raw_xml = bot_curl_exec($ch);
        if (!$raw_xml) {
            sleep(1);                // @codeCoverageIgnore
            continue;                // @codeCoverageIgnore
            // Keep trying...
        }
        $raw_xml = preg_replace(
            '~(\<year media_type=\"online\"\>\d{4}\<\/year\>\<year media_type=\"print\"\>)~',
                    '<year media_type="print">',
                    $raw_xml);
        $xml = @simplexml_load_string($raw_xml);
        unset($raw_xml);
        if (is_object($xml) && isset($xml->query_result->body->query)) {
            $result = $xml->query_result->body->query;
            if ((string) @$result["status"] === "resolved") {
                if (stripos($doi, '10.1515/crll') === 0) {
                    $volume = intval(trim((string) @$result->volume));
                    if ($volume > 1820) {
                        if (isset($result->issue)) {
                            $result->volume = $result->issue;
                            unset($result->issue);
                        } else {
                            unset($result->volume);
                        }
                    }
                }
                if (stripos($doi, '10.3897/ab.') === 0) {
                    unset($result->volume);
                    unset($result->page);
                    unset($result->issue);
                }
                return $result;
            } else {
                return null;
            }
        } else {
            sleep(1);                // @codeCoverageIgnore
            // Keep trying...
        }
    }
    report_warning("Error loading CrossRef file from DOI " . echoable($doi) . "!");      // @codeCoverageIgnore
    return null;                                                                        // @codeCoverageIgnore
}

function expand_doi_with_dx(Template $template, string $doi): void {
    // See https://crosscite.org/docs.html for discussion of API we are using -- not all agencies resolve the same way
    // https://api.crossref.org/works/$doi can be used to find out the agency
    // https://www.doi.org/registration_agencies.html  https://www.doi.org/RA_Coverage.html List of all ten doi granting agencies - many do not do journals
    // Examples of DOI usage  https://www.doi.org/demos.html
    // This basically does this:
    // curl -LH "Accept: application/vnd.citationstyles.csl+json" https://dx.doi.org/10.5524/100077
    // Data Quality is CrossRef > DX.doi.org > Zotero
    static $ch = null;
    if ($ch === null) {
    $ch = bot_curl_init(1.5,  // can take a long time when nothing to be found
        [CURLOPT_HTTPHEADER => ["Accept: application/vnd.citationstyles.csl+json"]]);
    }
    if (strpos($doi, '10.2307') === 0 || // jstor API is better
        strpos($doi, '10.24436') === 0 || // They have horrible meta-data
        strpos($doi, '10.5284/1028203') === 0) { // database
        return;
    }
    set_time_limit(120);
    /** @param array|string|int|null $data */
    $try_to_add_it = static function(string $name, $data) use($template): void {
        if ($template->has($name)) {
            return; // Not worth updating based upon DX
        }
        if (is_null($data)) {
            return;
        }
        while (is_array($data)) {
            if (!isset($data['0']) || isset($data['1'])) {
                return;
            }
            $data = $data['0'];
        }
        $data = (string) $data;
        if ($data === '') {
            return;
        }
        if ($data === 'Array') {
            return;
        }
        if (str_ends_with(strtolower($data), '.pdf')) {
            return;
        }
        if (strpos($name, 'author') !== false) { // Remove dates from names from 10.11501/ dois
            if (preg_match('~^(.+), \d{3,4}\-\d{3,4}$~', $data, $matched)) {
                $data = $matched[1];
            }
            if (preg_match('~^(.+), \d{3,4}\-$~', $data, $matched)) {
                $data = $matched[1];
            }
            if (preg_match('~^(.+), \-\d{3,4}$~', $data, $matched)) {
                $data = $matched[1];
            }
        }
        $template->add_if_new($name, $data, 'dx');
        return;
    };
    if (!$doi) {
        return;
    }
    /** @psalm-taint-escape ssrf */
    $doi = trim($doi);
    curl_setopt($ch, CURLOPT_URL, 'https://doi.org/' . $doi);
    report_action("Querying dx.doi.org: doi:" . doi_link($doi));
    try {
        $data = bot_curl_exec($ch);
    } catch (Exception $e) {          // @codeCoverageIgnoreStart
        $template->mark_inactive_doi();
        return;
    }                      // @codeCoverageIgnoreEnd
    if ($data === "" || stripos($data, 'DOI Not Found') !== false || stripos($data, 'DOI prefix') !== false) {
        $template->mark_inactive_doi();
        return;
    }
    $json = @json_decode($data, true);
    unset($data);
    if($json === false || $json === null) {
        return;
    }
    // BE WARNED:  this code uses the "@$var" method.
    // If the variable is not set, then PHP just passes null, then that is interpreted as a empty string
    if ($template->blank(['date', 'year'])) {
        $try_to_add_it('year', @$json['issued']['date-parts']['0']['0']);
        $try_to_add_it('year', @$json['created']['date-parts']['0']['0']);
        $try_to_add_it('year', @$json['published-print']['date-parts']['0']['0']);
    }
    $try_to_add_it('issue', @$json['issue']);
    $try_to_add_it('pages', @$json['pages']);
    $try_to_add_it('volume', @$json['volume']);
    $try_to_add_it('isbn', @$json['ISBN']['0']);
    $try_to_add_it('isbn', @$json['isbn-type']['0']['value']);
    if (isset($json['author'])) {
        $i = 0;
        foreach ($json['author'] as $auth) {
            $i += 1;
            $full_name = mb_strtolower(trim((string) @$auth['given'] . ' ' . (string) @$auth['family'] . (string) @$auth['literal']));
            if (in_array($full_name, BAD_AUTHORS, true)) {
                break;
            }
            if (((string) @$auth['family'] === '') && ((string) @$auth['given'] !== '')) {
                $try_to_add_it('author' . (string) $i, @$auth['given']); // First name without last name.  Probably an organization or chinese/korean/japanese name
            } else {
                $try_to_add_it('last' . (string) $i, @$auth['family']);
                $try_to_add_it('first' . (string) $i, @$auth['given']);
                $try_to_add_it('author' . (string) $i, @$auth['literal']);
            }
        }
    }
    // Publisher hiding as journal name - defective data
    if (isset($json['container-title']) && isset($json['publisher']) && ($json['publisher'] === $json['container-title'])) {
        unset($json['container-title']);   // @codeCoverageIgnore
    }

    $type = (string) @$json['type'];
    if ($type === 'article-journal' ||
            $type === 'journal-article' ||
            $type === 'article' ||
            $type === 'proceedings-article' ||
            $type === 'conference-paper' ||
            $type === 'entry' ||
            ($type === '' && (isset($json['container-title']) || isset($json['issn']['0'])))) {
        $try_to_add_it('journal', @$json['container-title']);
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('issn', @$json['issn']); // Will not add if journal is set
    } elseif ($type === 'journal-issue') { // Very rare: Do not add "title": should be blank anyway.  Got this once from DOI:10.7592/fejf2015.62
        $try_to_add_it('journal', @$json['container-title']);  // @codeCoverageIgnore
        $try_to_add_it('issn', @$json['issn']);          // @codeCoverageIgnore
    } elseif ($type === 'journal') { // Very rare: Do not add "title": should be blank anyway.  Got this once from DOI:10.1007/13539.2190-6009 and DOI:10.14296/rih/issn.1749.8155
        $try_to_add_it('issn', @$json['issn']);          // @codeCoverageIgnore
    } elseif ($type === 'reference-entry' || $type === 'component') { // Very rare: reference-entry from 10.1002/14356007.a02_115.pub2, component from 10.3998/mpub.11422327.cmp.11
        $try_to_add_it('work', @$json['container-title']);    // @codeCoverageIgnore
        $try_to_add_it('title', @$json['title']);        // @codeCoverageIgnore
    } elseif ($type === 'monograph' || $type === 'book' || $type === 'edited-book') {
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('title', @$json['container-title']);// Usually not set, but just in case this instead of title is set
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
    } elseif ($type === 'reference-book') { // VERY rare
        $try_to_add_it('title', @$json['title']);          // @codeCoverageIgnore
        $try_to_add_it('title', @$json['container-title']);      // @codeCoverageIgnore
        $try_to_add_it('chapter', @$json['original-title']);    // @codeCoverageIgnore
        $try_to_add_it('location', @$json['publisher-location']); // @codeCoverageIgnore
        $try_to_add_it('publisher', @$json['publisher']);      // @codeCoverageIgnore
    } elseif ($type === 'chapter' || $type === 'book-chapter' || $type === 'book-section') {
        $try_to_add_it('title', @$json['container-title']);
        $try_to_add_it('chapter', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
    } elseif ($type === 'other') {
        if (isset($json['container-title'])) {
            $try_to_add_it('title', @$json['container-title']);
            $try_to_add_it('chapter', @$json['title']);
        } else {
            $try_to_add_it('title', @$json['title']);
        }
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
    } elseif ($type === 'dataset') {
        $try_to_add_it('type', 'Data Set');
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
        if (!isset($json['categories']['1']) &&
                (($template->wikiname() === 'cite book') || $template->blank(WORK_ALIASES))) { // No journal/magazine set and can convert to book
            $try_to_add_it('chapter', @$json['categories']['0']);  // Not really right, but there is no cite data set template
        }
    } elseif ($type === '' || $type === 'graphic' || $type === 'report' || $type === 'report-component') {  // Add what we can where we can
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
    } elseif ($type === 'thesis' || $type === 'dissertation' || $type === 'dissertation-thesis') {
        $template->change_name_to('cite thesis');
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
        if (stripos(@$json['URL'], 'hdl.handle.net')) {
            $template->get_identifiers_from_url($json['URL']);
        }
    } elseif ($type === 'posted-content' || $type === 'grant' || $type === 'song' || $type === 'motion_picture' || $type === 'patent' || $type === 'database') { // posted-content is from bioRxiv
        $try_to_add_it('title', @$json['title']);
    } else {
        $try_to_add_it('title', @$json['title']);                          // @codeCoverageIgnore
        if (!HTML_OUTPUT) {
            print_r($json);                              // @codeCoverageIgnore
        }
        report_minor_error('dx.doi.org returned unexpected data type ' . echoable($type) . ' for ' . doi_link($doi));    // @codeCoverageIgnore
    }
    return;
}

function expand_by_jstor(Template $template): void {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, []);
    }
    set_time_limit(120);
    if ($template->incomplete() === false) {
        return;
    }
    if ($template->has('jstor')) {
        $jstor = trim($template->get('jstor'));
    } elseif(preg_match('~^https?://(?:www\.|)jstor\.org/stable/(.*)$~', $template->get('url'), $match)) {
        $jstor = $match[1];
    } else {
        return;
    }
    if (preg_match('~^(.*)(?:\?.*)$~', $jstor, $match)) {
        $jstor = $match[1]; // remove ?seq= stuff
    }
    /** @psalm-taint-escape ssrf */
    $jstor = trim($jstor);
    if (strpos($jstor, ' ') !== false) {
        return ; // Comment/template found
    }
    if (substr($jstor, 0, 1) === 'i') {
        return ; // We do not want i12342 kind
    }
    curl_setopt($ch, CURLOPT_URL, 'https://www.jstor.org/citation/ris/' . $jstor);
    $dat = bot_curl_exec($ch);
    if ($dat === '') {
        report_info("JSTOR API returned nothing for ". jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                             // @codeCoverageIgnore
    }
    if (stripos($dat, 'No RIS data found for') !== false) {
        report_info("JSTOR API found nothing for ".    jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                             // @codeCoverageIgnore
    }
    if (stripos($dat, 'Block Reference') !== false) {
        report_info("JSTOR API blocked bot for ".    jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                           // @codeCoverageIgnore
    }
    if (stripos($dat, 'A problem occurred trying to deliver RIS data')  !== false) {
        report_info("JSTOR API had a problem for ".    jstor_link($jstor));
        return;
    }
    if ($template->has('title')) {
        $bad_data = true;
        $ris = explode("\n", html_entity_decode($dat, ENT_COMPAT | ENT_HTML401, 'UTF-8'));
        foreach ($ris as $ris_line) {
            $ris_part = explode(" - ", $ris_line . " ", 2);
            if (!isset($ris_part[1])) {
                $ris_part[0] = ""; // Ignore
            }
            switch (trim($ris_part[0])) {
                case "T1":
                case "TI":
                case "T2":
                case "BT":
                    $new_title = trim($ris_part[1]);
                    foreach (THINGS_THAT_ARE_TITLES as $possible) {
                        if ($template->has($possible) && titles_are_similar($template->get($possible), $new_title)) {
                            $bad_data = false;
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        if ($bad_data) { // Now for TI: T1 existing titles (title followed by sub-title)
            $got_count = 0;
            $new_title = ': ';
            foreach ($ris as $ris_line) {
                $ris_part = explode(" - ", $ris_line . " ", 2);
                if (!isset($ris_part[1])) {
                    $ris_part[0] = ""; // Ignore
                }
                switch (trim($ris_part[0])) {
                    case "T1":
                        $new_title .= trim($ris_part[1]);
                        $got_count += 10;
                        break;
                    case "TI":
                        $new_title = trim($ris_part[1]) . $new_title;
                        $got_count += 100;
                        break;
                    default:
                        break;
                }
            }
            if ($got_count === 110) { // Exactly one of each
                foreach (THINGS_THAT_ARE_TITLES as $possible) {
                    if ($template->has($possible) && titles_are_similar(preg_replace("~# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #~i", "�", $template->get($possible)), $new_title)) {
                        $bad_data = false;
                    }
                }
            }
        }
        if ($bad_data) {
            report_info('Old title did not match for ' . jstor_link($jstor));
            foreach ($ris as $ris_line) {
                if (!isset($ris_part[1])) {
                    $ris_part[0] = ""; // Ignore
                }
                $ris_part = explode(" - ", $ris_line . " ", 2);
                switch (trim($ris_part[0])) {
                    case "T1":
                    case "TI":
                    case "T2":
                    case "BT":
                        $new_title = trim($ris_part[1]);
                        if ($new_title) report_info("    Possible new title: " . echoable($new_title));
                        break;
                    default: // @codeCoverageIgnore
                }
            }
            foreach (THINGS_THAT_ARE_TITLES as $possible) {
                if ($template->has($possible)) {
                    report_info("    Existing old title: " . echoable(preg_replace("~# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #~i", "�", $template->get($possible))));
                }
            }
            return;
        }
    }
    $template->expand_by_RIS($dat, false);
    return;
}

function getS2CID(string $url): string {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(0.5, HEADER_S2);
    }
    $url = 'https://api.semanticscholar.org/v1/paper/URL:' .  urlencode(urldecode($url));
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = bot_curl_exec($ch);
    if (!$response) {
        report_warning("No response from semanticscholar.");    // @codeCoverageIgnore
        return '';                                              // @codeCoverageIgnore
    }
    $json = @json_decode($response);
    unset($response);
    if (!$json) {
        report_warning("Bad response from semanticscholar.");    // @codeCoverageIgnore
        return '';                                              // @codeCoverageIgnore
    }
    if (!isset($json->corpusId)) {
        report_warning("No corpusId found from semanticscholar for " . echoable($url)); // @codeCoverageIgnore
        return '';                                                      // @codeCoverageIgnore
    }
    if (is_array($json->corpusId) || is_object($json->corpusId)) {
        report_warning("Bad data from semanticscholar.");    // @codeCoverageIgnore
        return '';                                          // @codeCoverageIgnore
    }
    return (string) $json->corpusId;
}

function ConvertS2CID_DOI(string $s2cid): string {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(0.5, HEADER_S2);
    }
    /** @psalm-taint-escape ssrf */
    $url = 'https://api.semanticscholar.org/v1/paper/CorpusID:' . urlencode($s2cid);
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = bot_curl_exec($ch);
    if (!$response) {
        report_warning("No response from semanticscholar.");  // @codeCoverageIgnore
        return '';                                            // @codeCoverageIgnore
    }
    $json = @json_decode($response);
    unset($response);
    if (!$json) {
        report_warning("Bad response from semanticscholar."); // @codeCoverageIgnore
        return '';                                            // @codeCoverageIgnore
    }
    if (!isset($json->doi)) {
        return '';                                         // @codeCoverageIgnore
    }
    if (is_array($json->doi) || is_object($json->doi)) {
        report_warning("Bad data from semanticscholar."); // @codeCoverageIgnore
        return '';                                        // @codeCoverageIgnore
    }
    $doi = (string) $json->doi;
    if (doi_works($doi)) {
        return $doi;
    } else {
        report_info("non-functional doi found from semanticscholar: " . echoable_doi($doi));// @codeCoverageIgnore
        return '';                                                    // @codeCoverageIgnore
    }
}

function get_semanticscholar_license(string $s2cid): ?bool {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(0.5, HEADER_S2);
    }
    $url = 'https://api.semanticscholar.org/v1/paper/CorpusID:' . urlencode($s2cid);
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = bot_curl_exec($ch);
    if ($response === '') {
        return null; // @codeCoverageIgnore
    }
    if (stripos($response, 'Paper not found') !== false) {
        return false; // @codeCoverageIgnore
    }
    $oa = @json_decode($response);
    if ($oa === false) {
        return null; // @codeCoverageIgnore
    }
    if (isset($oa->is_publisher_licensed) && $oa->is_publisher_licensed) {
        return true;
    }
    return false;
}

/**
  @param array<Template> $templates
*/
function expand_templates_from_archives(array &$templates): void { // This is done very late as a latch ditch effort  // Pointer to save memory
    static $ch = null;
    set_time_limit(120);
    if ($ch === null) {
        $ch = bot_curl_init(0.5, [CURLOPT_HEADER => "1"]);
    }
    foreach ($templates as $template) {
        set_time_limit(120);
        if ($template->has('script-title') && (strtolower($template->get('title')) === 'usurped title' || strtolower($template->get('title')) === 'archived copy' || strtolower($template->get('title')) === 'archive copy')) {
            $template->forget('title');
        }
        if ($template->blank(['chapter', 'series', 'script-title']) &&
            !$template->blank(['archive-url', 'archiveurl']) &&
            ($template->blank(WORK_ALIASES) || $template->has('website'))    &&
            ($template->blank('title') || strtolower($template->get('title')) === 'archived copy' ||
            strtolower($template->get('title')) === 'archive copy' ||
            strtolower($template->get('title')) === 'usurped title' ||
            substr_count($template->get('title'), '?') > 10 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '�') >0 )) {
            /** @psalm-taint-escape ssrf */
            $archive_url = $template->get('archive-url') . $template->get('archiveurl');
            if (stripos($archive_url, 'archive') !== false && stripos($archive_url, '.pdf') === false) {
                set_time_limit(120);
                throttle_archive();
                curl_setopt($ch, CURLOPT_URL, $archive_url);
                $raw_html = bot_curl_exec($ch);
                foreach ([
                    '~doctype[\S\s]+?<head[\S\s]+?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~doctype[\S\s]+?<head[\S\s]+?<meta property="og:title" content="([\S\s]+?\S[\S\s]+?)"\/>[\S\s]+?<title[\S\s]+?head[\S\s]+?<body~i',
                    '~doctype[\S\s]+?<head[\S\s]+?<title>([\S\s]+?\S[\S\s]+?) \| Ghostarchive<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~<html[\S\s]+<head[\S\s]+?<!-- End Wayback Rewrite JS Include -->[\s\S]*?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~<html[\S\s]+<head[\S\s]+?<!-- End Wayback Rewrite JS Include -->\s*?<!-- WebPoet\(tm\) Web Page Pull[\s\S]+?-->[\S\s]+?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head~i',
                    '~archive\.org/includes/analytics\.js[\S\s]+?-- End Wayback Rewrite JS Include[\S\s]+?head[\S\s]+<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~',
                ] as $regex) {
                    set_time_limit(120); // Slow regex sometimes
                    if ($raw_html && preg_match($regex, $raw_html, $match)) {
                        set_time_limit(120);
                            $title = trim($match[1]);
                            if (stripos($title, 'archive') === false &&
                            stripos($title, 'wayback') === false &&
                            $title !== ''
                            ) {
                            $cleaned = false;
                            $encode = [];
                            if (preg_match('~x-archive-guessed-charset: (\S+)~i', $raw_html, $match)) {
                                if (is_encoding_reasonable($match[1])) {
                                    $encode[] = $match[1];
                                }
                            }
                            if (preg_match('~<meta http-equiv="?content-type"? content="text\/html;[\s]*charset=([^"]+)"~i', $raw_html, $match)) {
                                if (is_encoding_reasonable($match[1])) {
                                    $encode[] = $match[1];
                                }
                            }
                            if (preg_match('~<meta http-equiv="?content-type"? content="text\/html;[\s]*charset=([^"]+)"~i', $raw_html, $match)) {
                                if (strtolower($match[1]) !== 'utf-8' && strtolower($match[1]) !== 'iso-8859-1') {
                                    $encode[] = $match[1];
                                }
                            }
                            foreach ($encode as $pos_encode) {
                                if (!$cleaned) {
                                    $try = smart_decode($title, $pos_encode, $archive_url);
                                    if ($try !== "") {
                                        $title = $try;
                                        $cleaned = true;
                                    }
                                }
                            }
                            if (!$cleaned) {
                                $title = convert_to_utf8($title);
                            }
                            unset($encode, $cleaned, $try, $match, $pos_encode);
                            $good_title = true;
                            if (in_array(strtolower($title), BAD_ACCEPTED_MANUSCRIPT_TITLES, true) ||
                                    in_array(strtolower($title), IN_PRESS_ALIASES, true)) {
                                $good_title = false;
                            }
                            foreach (BAD_ZOTERO_TITLES as $bad_title) {
                                if (mb_stripos($title, $bad_title) !== false) {
                                    $good_title = false;
                                }
                            }
                            if ($good_title) {
                                $old = $template->get('title');
                                $template->set('title', '');
                                $template->add_if_new('title', $title);
                                $new = $template->get('title');
                                if ($new === '') {
                                    $template->set('title', $old); // UTF-8 craziness
                                } else {
                                    $bad_count = substr_count($new, '�') + mb_substr_count($new, '$') + mb_substr_count($new, '%') + substr_count($new, '');
                                    if ($bad_count > 5) {
                                        $template->set('title', $old); // UTF-8 craziness
                                    } else {
                                        $raw_html = ''; // We are done
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/** @param array<int|string|bool|array<string>> $curl_opts */
function Bibcode_Response_Processing(array $curl_opts, string $adsabs_url): object {
    try {
        $ch = bot_curl_init(1.0, $curl_opts); // Type varies greatly
        $return = bot_curl_exec($ch);
        if ($return === "") {
            // @codeCoverageIgnoreStart
            $errorStr = curl_error($ch);
            $errnoInt = curl_errno($ch);
            throw new Exception('Curl error from AdsAbs website: ' . $errorStr, $errnoInt);
            // @codeCoverageIgnoreEnd
        }
        $http_response_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_length = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        if ($http_response_code === 0 || $header_length === 0) {
            throw new Exception('Size of zero from AdsAbs website'); // @codeCoverageIgnore
        }
        $header = substr($return, 0, $header_length);
        $body = substr($return, $header_length);
        unset($return);
        $decoded = @json_decode($body);

        $ratelimit_total = null;
        $ratelimit_left = null;
        $ratelimit_current = null;

        if (preg_match_all('~\nx\-ratelimit\-\w+:\s*(\d+)\r~i', $header, $rate_limit)) {
            // @codeCoverageIgnoreStart
            if ($rate_limit[1][2]) {
                $ratelimit_total = intval($rate_limit[1][0]);
                $ratelimit_left = intval($rate_limit[1][1]);
                $ratelimit_current = $ratelimit_total - $ratelimit_left;
                report_info("AdsAbs search " . strval($ratelimit_current) . "/" . strval($ratelimit_total));
            } else {
                throw new Exception('Too many requests', $http_response_code);
            }
            // @codeCoverageIgnoreEnd
        }

        if (is_object($decoded) && isset($decoded->error)) {
            $retry_msg='';                                                  // @codeCoverageIgnoreStart
            $time_to_sleep = null;
            $limit_action = null;
            if (is_int($ratelimit_total) && is_int($ratelimit_left) && is_int($ratelimit_current) && ($ratelimit_left <= 0) && ($ratelimit_current >= $ratelimit_total) && preg_match('~\nretry-after:\s*(\d+)\r~i', $header, $retry_after)) {
                // AdsAbs limit reached: proceed according to the action configured in PHP_ADSABSAPILIMITACTION;
                // available actions are: sleep, exit, ignore (default).
                $rai=intval($retry_after[1]);
                $retry_msg.='Need to retry after '.strval($rai).'s ('.date('H:i:s', $rai).').';
                if (defined('PHP_ADSABSAPILIMITACTION') && is_string(PHP_ADSABSAPILIMITACTION)) {
                    $limit_action = strtolower(PHP_ADSABSAPILIMITACTION);
                }
                if ($limit_action === 'sleep') {
                    $time_to_sleep = $rai+1;
                } elseif ($limit_action === 'exit') {
                    $time_to_sleep = -1;
                } elseif ($limit_action === 'ignore' || $limit_action === '' || $limit_action === null) {
                    // just ignore the limit and continue
                } else {
                    $retry_msg.= ' The AdsAbs API limit reached, but the on-limit action "'.strval($limit_action).'" is not recognized and thus ignored.';
                }
            }
            if (preg_match('~\nx-ratelimit-reset:\s*(\d+)\r~i', $header, $rate_limit_reset)) {
                $rlr=intval($rate_limit_reset[1]);
                $retry_msg.=' Rate limit resets on '.date('Y-m-d H:i:s', $rlr).' UTC.';
            }
            $retry_msg = trim($retry_msg);
            if ($retry_msg !== '') {
                if (is_int($time_to_sleep) && ($time_to_sleep > 0)) {
                    $retry_msg .= ' Sleeping...';
                    report_warning($retry_msg);
                    sleep($time_to_sleep);
                } elseif (is_int($time_to_sleep) && ($time_to_sleep < 0)) {
                    $retry_msg .= ' Exiting. Please run the bot later to retry AdsAbs API call when the limit will reset.';
                    report_warning($retry_msg);
                    report_error('The AdsAbs API limit reached, exiting due to "'.strval($limit_action).'" action configured in PHP_ADSABSAPILIMITACTION environment variable.');
                } else {
                    report_warning($retry_msg);
                }
            }
            unset($retry_msg);
            unset($time_to_sleep);

            if (isset($decoded->error->trace)) {
                bot_debug_log("AdsAbs website returned a stack trace - URL was:    " . $adsabs_url);
                throw new Exception("AdsAbs website returned a stack trace" . "\n - URL was:  " . $adsabs_url,
                ($decoded->error->code ?? 999));
            } else {
                    throw new Exception(((isset($decoded->error->msg)) ? $decoded->error->msg : $decoded->error) . "\n - URL was:  " . $adsabs_url,
                ($decoded->error->code ?? 999));
            }
            // @codeCoverageIgnoreEnd
        }
        if ($http_response_code !== 200) {
            // @codeCoverageIgnoreStart
            $message = (string) explode("\n", $header, 2)[0];
            throw new Exception($message, $http_response_code);
            // @codeCoverageIgnoreEnd
        }

        if (!is_object($decoded)) {
            if (stripos($body, 'down for maintenance') !== false) {
                AdsAbsControl::big_give_up();  // @codeCoverageIgnore
                AdsAbsControl::small_give_up();  // @codeCoverageIgnore
                throw new Exception("ADSABS is down for maintenance", 5000);  // @codeCoverageIgnore
            }
            bot_debug_log("Could not decode ADSABS API response:\n" . $body . "\nURL was:    " . $adsabs_url);  // @codeCoverageIgnore
            throw new Exception("Could not decode API response:\n" . $body, 5000);  // @codeCoverageIgnore
        } elseif (isset($decoded->response)) {
            return $decoded->response;  /** NORMAL RETURN IS HIDDEN HERE */
        } elseif (isset($decoded->error)) {                  // @codeCoverageIgnore
            throw new Exception('' . $decoded->error, 5000); // @codeCoverageIgnore
        } else {
            throw new Exception("Could not decode AdsAbs response", 5000);        // @codeCoverageIgnore
        }
    // @codeCoverageIgnoreStart
    } catch (Exception $e) {
        if ($e->getCode() === 5000) { // made up code for AdsAbs error
            report_warning(sprintf("API Error in query_adsabs: %s", echoable($e->getMessage())));
        } elseif ($e->getCode() === 60) {
            AdsAbsControl::big_give_up();
            AdsAbsControl::small_give_up();
            report_warning('Giving up on AdsAbs for a while.  SSL certificate has expired.');
        } elseif (strpos($e->getMessage(), 'org.apache.solr.search.SyntaxError') !== false) {
            report_info(sprintf("Internal Error %d in query_adsabs: %s", $e->getCode(), echoable($e->getMessage())));
        } elseif (strpos($e->getMessage(), 'HTTP') === 0) {
            report_warning(sprintf("HTTP Error %d in query_adsabs: %s", $e->getCode(), echoable($e->getMessage())));
        } elseif (strpos($e->getMessage(), 'Too many requests') !== false) {
            report_warning('Giving up on AdsAbs for a while.  Too many requests.');
            if (strpos($adsabs_url, 'bigquery') !== false) {
                AdsAbsControl::big_give_up();
            } else {
                AdsAbsControl::small_give_up();
            }
        } else {
            report_warning(sprintf("Error %d in query_adsabs: %s", $e->getCode(), echoable($e->getMessage())));
        }
    }
    return (object) ['numFound' => 0];
    // @codeCoverageIgnoreEnd
}

function get_entrez_xml(string $type, string $query): ?SimpleXMLElement {
    $url =    "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/";
    $request = NLM_LOGIN;
    if ($type === "esearch_pubmed") {
        $url  .= "esearch.fcgi";
        $request .= "&db=pubmed&term=" . $query;
    } elseif ($type === "pubmed") {
        $url .= "esummary.fcgi";
        $request .= "&db=pubmed&id=" . $query;
    } elseif ($type === "pmc") {
        $url .= "esummary.fcgi";
        $request .= "&db=pmc&id=" . $query;
    } else {
        report_error("Invalid type passed to get_entrez_xml: " . echoable($type));  // @codeCoverageIgnore
    }
    return xml_post($url, $request);
}
// Must use post in order to get DOIs with <, >, [, and ] in them and other problems
function xml_post(string $url, string $post): ?SimpleXMLElement {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, [
            CURLOPT_POST => "1",
            CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded", "Accept: application/xml"],
        ]);
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS => $post,
    ]);
    $output = bot_curl_exec($ch);
    $xml = @simplexml_load_string($output);
    if ($xml === false) {
        sleep(1);
        return null;
    }
    return $xml;
}

function process_bibcode_data(Template $this_template, object $record): void {
    $this_template->record_api_usage('adsabs', 'bibcode');
    if (!isset($record->title[0])) {
        return;
    }
    $this_template->add_if_new('title', (string) $record->title[0], 'adsabs'); // add_if_new will format the title text and check for unknown
    if (stripos((string) $record->title[0], 'book') !== false && stripos((string) $record->title[0], 'review') !== false) {
        unset($record->author); // often book author
    }
    $i = 0;
    if (isset($record->author)) {
        foreach ($record->author as $author) {
            ++$i;
            $this_template->add_if_new('author' . (string) $i, $author, 'adsabs');
        }
    }
    if (isset($record->pub)) {
        $journal_string = explode(',', (string) $record->pub);
        $journal_start = mb_strtolower($journal_string[0]);
        if (preg_match("~\bthesis\b~ui", $journal_start)) {
            // Do nothing
        } elseif (substr($journal_start, 0, 6) === 'eprint') {  // No longer used
            if (substr($journal_start, 0, 13) === 'eprint arxiv:') {          //@codeCoverageIgnore
                if (isset($record->arxivclass)) {
                    $this_template->add_if_new('class', (string) $record->arxivclass);  //@codeCoverageIgnore
                }
                $this_template->add_if_new('arxiv', substr($journal_start, 13));    //@codeCoverageIgnore
            }
        } else {
            $this_template->add_if_new('journal', $journal_string[0], 'adsabs');
        }
    }
    if (isset($record->page)) {
        $tmp = implode($record->page);
        if ((stripos($tmp, 'arxiv') !== false) || (strpos($tmp, '/') !== false)) {  // Bad data
            unset($record->page);
            unset($record->volume);
            unset($record->issue);
        } elseif (preg_match('~[A-Za-z]~', $tmp)) { // Do not trust anything with letters
            unset($record->page);
        } elseif (($tmp === $this_template->get('issue')) || ($tmp === $this_template->get('volume'))) {
            unset($record->page); // Probably is journal without pages, but article numbers and got mis-encoded
        }
    }
    if (isset($record->volume)) {
        $this_template->add_if_new('volume', (string) $record->volume, 'adsabs');
    }
    if (isset($record->issue)) {
        $this_template->add_if_new('issue', (string) $record->issue, 'adsabs');
    }
    if (isset($record->year)) {
        $this_template->add_if_new('year', preg_replace("~\D~", "", (string) $record->year), 'adsabs');
    }
    if (isset($record->page)) {
        $dum = implode('–', $record->page);
        if (preg_match('~^[\-\–\d]+$~u', $dum)) {
            $this_template->add_if_new('pages', $dum, 'adsabs');
        }
        unset($record->page);
    }
    if (isset($record->identifier)) { // Sometimes arXiv is in journal (see above), sometimes here in identifier
        foreach ($record->identifier as $recid) {
            $recid = (string) $recid;
            if(strtolower(substr($recid, 0, 6)) === 'arxiv:') {
                if (isset($record->arxivclass)) {
                    $this_template->add_if_new('class', (string) $record->arxivclass, 'adsabs');
                }
                $this_template->add_if_new('arxiv', substr($recid, 6), 'adsabs');
            }
        }
    }
    if (isset($record->doi)){
        $doi = (string) @$record->doi[0];
        if (doi_works($doi)) {
            $this_template->add_if_new('doi', $doi);
            if ($this_template->has('bibcode')) {
                AdsAbsControl::add_doi_map($this_template->get('bibcode'), $doi);
            }
        }
    } elseif ($this_template->has('bibcode')) { // Slow mode looks for existent bibcodes
        AdsAbsControl::add_doi_map($this_template->get('bibcode'), 'X');
    }
}

function expand_book_adsabs(Template $template, object $record): void {
    set_time_limit(120);
    if (isset($record->year)) {
        $template->add_if_new('year', preg_replace("~\D~", "", (string) $record->year));
    }
    if (isset($record->title)) {
        $template->add_if_new('title', (string) $record->title[0]);
    }
    if ($template->blank(array_merge(FIRST_EDITOR_ALIASES, FIRST_AUTHOR_ALIASES, ['publisher']))) { // Avoid re-adding editors as authors, etc.
        $i = 0;
        if (isset($record->author)) {
            foreach ($record->author as $author) {
                ++$i;
                $template->add_if_new('author' . (string) $i, $author);
            }
        }
    }
    return;
}

  // $options should be a series of field names, colons (optionally urlencoded), and
  // URL-ENCODED search strings, separated by (unencoded) ampersands.
  // Surround search terms in (url-encoded) ""s, i.e. doi:"10.1038/bla(bla)bla"
function query_adsabs(string $options): object {
    set_time_limit(120);
    // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/API_documentation_UNIXshell/Search_API.ipynb
    if (AdsAbsControl::small_gave_up_yet()) {
        return (object) ['numFound' => 0];
    }
    if (!PHP_ADSABSAPIKEY) {
        return (object) ['numFound' => 0]; // @codeCoverageIgnore
    }
    $adsabs_url = "https://" . (TRAVIS ? 'qa' : 'api')
                    . ".adsabs.harvard.edu/v1/search/query"
                    . "?q={$options}&fl=arxiv_class,author,bibcode,doi,doctype,identifier,"
                    . "issue,page,pub,pubdate,title,volume,year";
    $curl_opts=[
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PHP_ADSABSAPIKEY],
        CURLOPT_HEADER => "1",
        CURLOPT_URL => $adsabs_url,
    ];
    return Bibcode_Response_Processing($curl_opts, $adsabs_url);
}

// Might want to look at using instead https://doi.crossref.org/openurl/?pid=email@address.com&id=doi:10.1080/00222938700771131&redirect=no&format=unixref
function CrossRefTitle(string $doi): string {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0,
            [CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT]);
    }
    $url = "https://api.crossref.org/v1/works/".doi_encode($doi)."?mailto=".CROSSREFUSERNAME; // do not encode crossref email
    curl_setopt($ch, CURLOPT_URL, $url);
    $json = bot_curl_exec($ch);
    $json = @json_decode($json);
    if (isset($json->message->title[0]) && !isset($json->message->title[1])) {
        $title = (string) $json->message->title[0];
        if (conference_doi($doi) && isset($json->message->subtitle[0]) && strlen((string) $json->message->subtitle[0]) > 4) {
            $title .= ": " . (string) $json->message->subtitle[0];
        }
        return str_ireplace(['<i>', '</i>', '</i> :', '  '], [' <i>', '</i> ', '</i>:', ' '], $title);
    } else {
        sleep(2);  // @codeCoverageIgnore
        return ''; // @codeCoverageIgnore
    }
}
