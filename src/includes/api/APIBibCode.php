<?php

declare(strict_types=1);

final class AdsAbsControl {
    private const MAX_CACHE_SIZE = 50000;
    private static int $big_counter = 0;
    private static int $small_counter = 0;
    /** @var array<string> */
    private static array $doi2bib = [];
    /** @var array<string> */
    private static array $bib2doi = [];

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {
        // This is a static class
    }

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
            if (mb_stripos($bib, 'tmp') === false && mb_stripos($bib, 'arxiv') === false) {
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

/**
 * @param array<string> $bibcodes
 * @param array<Template> &$templates
 */
function query_bibcode_api(array $bibcodes, array &$templates): void {  // Pointer to save memory
    adsabs_api($bibcodes, $templates, 'bibcode');
}

function is_a_book_bibcode(string $id): bool {
    $check = str_replace(['book', 'conf', 'PhD'], '', $id);
    return ($check !== $id);
}

function expand_by_adsabs(Template $template): void {
    static $needs_told = true;
    set_time_limit(120);
    if ($template->has('bibcode') && $template->blank('doi')) {
        $doi = AdsAbsControl::get_bib2doi($template->get('bibcode'));
        if (doi_works($doi)) {
            $template->add_if_new('doi', $doi);
        }
    }
    if ($template->has('doi') && ($template->blank('bibcode') || mb_stripos($template->get('bibcode'), 'tmp') !== false || mb_stripos($template->get('bibcode'), 'arxiv') !== false)) {
        $doi = $template->get('doi');
        if (doi_works($doi)) {
            $bib = AdsAbsControl::get_doi2bib($doi);
            if (mb_strlen($bib) > 12) {
                $template->add_if_new('bibcode_nosearch', $bib);
            }
        }
    }

    // API docs at https://github.com/adsabs/adsabs-dev-api
    if (
        $template->has('bibcode') &&
        !$template->incomplete() &&
        mb_stripos($template->get('bibcode'), 'tmp') === false &&
        mb_stripos($template->get('bibcode'), 'arxiv') === false &&
        ($template->has('doi') || AdsAbsControl::get_bib2doi($template->get('bibcode')) === 'X')
    ) {
        // Don't waste a query, if it has a doi or will not find a doi
        return; // @codeCoverageIgnore
    }

    if (!SLOW_MODE && $template->blank('bibcode')) {
        return;
    } // Only look for new bibcodes in slow mode
    if (mb_stripos($template->get('bibcode'), 'CITATION') !== false) {
        return;
    }
    // Do not search if it is a book - might find book review
    if (mb_stripos($template->get('jstor'), 'document') !== false) {
        return;
    }
    if (mb_stripos($template->get('jstor'), '.ch.') !== false) {
        return;
    }

    if (!$template->blank_other_than_comments('bibcode') && mb_stripos($template->get('bibcode'), 'tmp') === false && mb_stripos($template->get('bibcode'), 'arxiv') === false) {
        return;
    }

    if ($template->api_has_used('adsabs', equivalent_parameters('bibcode'))) {
        return;
    }

    if ($template->has('bibcode')) {
        $template->record_api_usage('adsabs', 'bibcode');
    }
    if (mb_strpos($template->get('doi'), '10.1093/') === 0) {
        return;
    }
    report_action("Checking AdsAbs database");
    if ($template->has('doi') && preg_match(REGEXP_DOI, $template->get_without_comments_and_placeholders('doi'), $doi)) {
        $result = query_adsabs("identifier:" . urlencode('"' . $doi[0] . '"')); // In DOI we trust
    } elseif ($template->has('eprint')) {
        $result = query_adsabs("identifier:" . urlencode('"' . $template->get('eprint') . '"'));
    } elseif ($template->has('arxiv')) {
        $result = query_adsabs("identifier:" . urlencode('"' . $template->get('arxiv') . '"')); // @codeCoverageIgnore
    } else {
        $result = (object) ["numFound" => 0];
    }

    if ($result->numFound > 1) {
        report_warning("Multiple articles match identifiers "); // @codeCoverageIgnore
        return; // @codeCoverageIgnore
    }

    if ($result->numFound === 0) {
        // Avoid blowing through our quota
        if (
            !in_array($template->wikiname(), ['cite journal', 'citation', 'cite conference', 'cite book', 'cite arxiv'], true) || // Unlikely to find anything
            // If the book has someway to find it, or it is just a chapter and not the full book, or it has a location and publisher so it can be googled
            // This also greatly reduces the book review false positives
            (($template->wikiname() === 'cite book' || $template->wikiname() === 'citation') && ($template->has('isbn') || $template->has('oclc') || $template->has('chapter') || ($template->has('location') && $template->has('publisher')))) ||
            $template->has_good_free_copy() || // Already links out to something free
            $template->has('s2cid') || // good enough, usually includes abstract and link to copy
            ($template->has('doi') && doi_works($template->get('doi'))) || // good enough, usually includes abstract
            $template->has('bibcode')
        ) {
            // Must be GIGO
            report_inline('no record retrieved.'); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
    }

    if ($result->numFound !== 1 && $template->has('title')) {
        // Do assume failure to find arXiv means that it is not there
        $have_more = false;
        if (mb_strlen($template->get_without_comments_and_placeholders("title")) < 15 ||
         mb_strpos($template->get_without_comments_and_placeholders("title"), ' ') === false) {
            return;
        }
        $the_query = "title:" . urlencode('"' . mb_trim(remove_brackets(str_replace(['"', "\\", "^", "_", '   ', '  '], [' ', ' ', ' ', ' ', ' ', ' '], $template->get_without_comments_and_placeholders("title")))) . '"');
        $pages = $template->page_range();
        if ($pages) {
            $the_query = $the_query . "&fq=page:" . urlencode('"' . $pages[1] . '"');
            $have_more = true;
        }
        if ($template->year()) {
            $the_query = $the_query . "&fq=year:" . urlencode($template->year());
            $have_more = true;
        }
        if ($template->has('volume')) {
            $the_query = $the_query . "&fq=volume:" . urlencode('"' . $template->get('volume') . '"');
            $have_more = true;
        }
        if ($template->has('issn')) {
            $the_query = $the_query . "&fq=issn:" . urlencode($template->get('issn'));
            $have_more = true;
        }
        if (!$have_more) {
            return; // A title is not enough
        }
        $result = query_adsabs($the_query);
        if ($result->numFound === 0) {
            return;
        }
        $record = $result->docs[0];
        if (titles_are_dissimilar($template->get_without_comments_and_placeholders("title"), $record->title[0])) {
            // Considering we searched for title, this is very paranoid
            report_inline("Similar title not found in database."); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
        // If we have a match, but other links exists, and we have nothing journal like, then require exact title match
        if (
            !$template->blank(['doi', 'pmc', 'pmid', 'eprint', 'arxiv', ...ALL_URL_TYPES]) &&
            $template->blank(['issn', 'journal', 'volume', 'issue', 'number']) &&
            mb_strtolower($record->title[0]) !== mb_strtolower($template->get_without_comments_and_placeholders('title'))
        ) {
            // Probably not a journal, trust zotero more
            report_inline("Exact title match not found in database."); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
    }

    if ($result->numFound !== 1 && ($template->has('journal') || $template->has('issn'))) {
        $journal = $template->get('journal');
        // try partial search using bibcode components:
        $pages = $template->page_range();
        if (!$pages) {
            return;
        }
        if ($template->blank('volume') && !$template->year()) {
            return;
        }
        $result = query_adsabs(
        ($template->has('journal') ? "pub:" . urlencode('"' . remove_brackets($journal) . '"') : "&fq=issn:" . urlencode($template->get('issn'))) .
        ($template->year() ? "&fq=year:" . urlencode($template->year()) : '') .
        ($template->has('volume') ? "&fq=volume:" . urlencode('"' . $template->get('volume') . '"') : '') .
        ("&fq=page:" . urlencode('"' . $pages[1] . '"'))
        );
        if ($result->numFound === 0 || !isset($result->docs[0]->pub)) {
            report_inline('no record retrieved.'); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
        $journal_string = explode(",", (string) $result->docs[0]->pub);
        $journal_fuzzyer = "~\([iI]ncorporating.+|\bof\b|\bthe\b|\ba|eedings\b|\W~";
        if (mb_strlen($journal_string[0]) && mb_strpos(mb_strtolower(safe_preg_replace($journal_fuzzyer, "", $journal)), mb_strtolower(safe_preg_replace($journal_fuzzyer, "", $journal_string[0]))) === false) {
            report_inline(   // @codeCoverageIgnoreStart
            "Partial match but database journal \"" .
            echoable($journal_string[0]) .
            "\" didn't match \"" .
            echoable($journal) .
            "\"."
            );
            return; // @codeCoverageIgnoreEnd
        }
    }
    if ($result->numFound === 1) {
        $record = $result->docs[0];
        if (isset($record->year) && $template->year()) {
            $diff = abs((int) $record->year - (int) $template->year()); // Check for book reviews (fuzzy >2 for arxiv data)
            $today = (int) date("Y");
            if ($diff > 2) {
                return;
            }
            if ($record->year < $today - 5 && $diff > 1) {
                return;
            }
            if ($record->year < $today - 10 && $diff !== 0) {
                return;
            }
            if ($template->has('doi') && $diff !== 0) {
                return;
            }
        }

        if (!isset($record->title[0]) || !isset($record->bibcode)) {
            report_inline("Database entry not complete"); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
        if ($template->has('title') && titles_are_dissimilar($template->get('title'), $record->title[0]) && !in_array($template->get('title'), GOOFY_TITLES, true)) {
            // Verify the title matches. We get some strange mis-matches {
            report_inline("Similar title not found in database"); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }

        if (isset($record->doi) && $template->get_without_comments_and_placeholders('doi')) {
            if (!str_i_same((string) $record->doi[0], $template->get_without_comments_and_placeholders('doi'))) {
                return;
            } // New DOI does not match
        }

        if (mb_strpos((string) $record->bibcode, '.......') !== false) {
            return;  // Reject things like 2012PhDT.........1B
        }
        if (is_a_book_bibcode((string) $record->bibcode)) {
            $template->add_if_new('bibcode_nosearch', (string) $record->bibcode);
            expand_book_adsabs($template, $record);
            return;
        }

        if (looksLikeBookReview($template, $record)) {
            // Possible book and we found book review in journal
            report_info("Suspect that BibCode " . bibcode_link((string) $record->bibcode) . " is book review. Rejecting.");
            return;
        }

        if ($template->blank('bibcode')) {
            $template->add_if_new('bibcode_nosearch', (string) $record->bibcode);
        }
        process_bibcode_data($template, $record);
        return;
    } elseif ($result->numFound === 0) {
        // @codeCoverageIgnoreStart
        report_inline('no record retrieved.');
        return;
    } else {
        report_inline('multiple records retrieved.  Ignoring.');
        return; // @codeCoverageIgnoreEnd
    }
}

/**
 * @param array<string> $ids
 * @param array<Template> &$templates
 */
function adsabs_api(array $ids, array &$templates, string $identifier): void {  // Pointer to save memory
    set_time_limit(120);
    if (count($ids) === 0) {
        return;
    }

    foreach ($ids as $key => $bibcode) {
        if (mb_stripos($bibcode, 'CITATION') !== false || mb_strlen($bibcode) !== 19) {
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
        if (mb_stripos($template->get('bibcode'), 'tmp') !== false || mb_stripos($template->get('bibcode'), 'arxiv') !== false) {
            $NONE_IS_INCOMPLETE = false;
            break;
        }
    }
    if ($NONE_IS_INCOMPLETE ||
        AdsAbsControl::big_gave_up_yet() || !PHP_ADSABSAPIKEY) {
        return;
    }

    // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/API_documentation_UNIXshell/Search_API.ipynb
    $adsabs_url = "https://" . (CI ? 'qa' : 'api')
                    . ".adsabs.harvard.edu/v1/search/bigquery?q=*:*"
                    . "&fl=arxiv_class,author,bibcode,doi,doctype,identifier,"
                    . "issue,page,pub,pubdate,title,volume,year&rows=2000";

    report_action("Expanding from BibCodes via AdsAbs API");
    $curl_opts = [
        CURLOPT_URL => $adsabs_url,
        CURLOPT_HTTPHEADER => ['Content-Type: big-query/csv', 'Authorization: Bearer ' . PHP_ADSABSAPIKEY],
        CURLOPT_HEADER => true,
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
        foreach ($ids as $template_key => $an_id) { // Cannot use array_search since that only returns first
            if (isset($record->bibcode) && mb_strtolower($an_id) === mb_strtolower((string) $record->bibcode)) { // BibCodes at not case-sensitive
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

/**
 * @param string $options should be a series of field names, colons (optionally urlencoded), and  URL-ENCODED search strings, separated by (unencoded) ampersands. Surround search terms in (url-encoded) ""s, i.e. doi:"10.1038/bla(bla)bla"
 */
function query_adsabs(string $options): object {
    set_time_limit(120);
    // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/API_documentation_UNIXshell/Search_API.ipynb
    if (AdsAbsControl::small_gave_up_yet()) {
        return (object) ['numFound' => 0];
    }
    if (!PHP_ADSABSAPIKEY) {
        return (object) ['numFound' => 0]; // @codeCoverageIgnore
    }
    $adsabs_url = "https://" . (CI ? 'qa' : 'api')
                    . ".adsabs.harvard.edu/v1/search/query"
                    . "?q={$options}&fl=arxiv_class,author,bibcode,doi,doctype,identifier,"
                    . "issue,page,pub,pubdate,title,volume,year";
    $curl_opts = [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PHP_ADSABSAPIKEY],
        CURLOPT_HEADER => true,
        CURLOPT_URL => $adsabs_url,
    ];
    return Bibcode_Response_Processing($curl_opts, $adsabs_url);
}

/** @param array<string|bool|array<string>> $curl_opts */
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
        }   // These are byte counts, not character counts
        $header = substr($return, 0, $header_length);  // phpcs:ignore
        $body = substr($return, $header_length);       // phpcs:ignore
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
            $retry_msg = '';                                                  // @codeCoverageIgnoreStart
            $time_to_sleep = null;
            $limit_action = null;
            if (is_int($ratelimit_total) && is_int($ratelimit_left) && is_int($ratelimit_current) && ($ratelimit_left <= 0) && ($ratelimit_current >= $ratelimit_total) && preg_match('~\nretry-after:\s*(\d+)\r~i', $header, $retry_after)) {
                // AdsAbs limit reached: proceed according to the action configured in PHP_ADSABSAPILIMITACTION;
                // available actions are: sleep, exit, ignore (default).
                $rai = intval($retry_after[1]);
                $retry_msg .= 'Need to retry after ' . strval($rai) . 's (' . date('H:i:s', $rai) . ').';
                if (defined('PHP_ADSABSAPILIMITACTION') && is_string(PHP_ADSABSAPILIMITACTION)) {
                    $limit_action = mb_strtolower(PHP_ADSABSAPILIMITACTION);
                }
                if ($limit_action === 'sleep') {
                    $time_to_sleep = $rai + 1;
                } elseif ($limit_action === 'exit') {
                    $time_to_sleep = -1;
                } elseif ($limit_action === 'ignore' || $limit_action === '' || $limit_action === null) {
                    // just ignore the limit and continue
                } else {
                    $retry_msg .= ' The AdsAbs API limit reached, but the on-limit action "' . strval($limit_action) . '" is not recognized and thus ignored.';
                }
            }
            if (preg_match('~\nx-ratelimit-reset:\s*(\d+)\r~i', $header, $rate_limit_reset)) {
                $rlr = intval($rate_limit_reset[1]);
                $retry_msg .= ' Rate limit resets on ' . date('Y-m-d H:i:s', $rlr) . ' UTC.';
            }
            $retry_msg = mb_trim($retry_msg);
            if ($retry_msg !== '') {
                if (is_int($time_to_sleep) && ($time_to_sleep > 0)) {
                    $retry_msg .= ' Sleeping...';
                    report_warning($retry_msg);
                    sleep($time_to_sleep);
                } elseif (is_int($time_to_sleep) && ($time_to_sleep < 0)) {
                    $retry_msg .= ' Exiting. Please run the bot later to retry AdsAbs API call when the limit will reset.';
                    report_warning($retry_msg);
                    report_error('The AdsAbs API limit reached, exiting due to "' . strval($limit_action) . '" action configured in PHP_ADSABSAPILIMITACTION environment variable.');
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
            if (mb_stripos($body, 'down for maintenance') !== false) {
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
        } elseif (mb_strpos($e->getMessage(), 'org.apache.solr.search.SyntaxError') !== false) {
            report_info(sprintf("Internal Error %d in query_adsabs: %s", $e->getCode(), echoable($e->getMessage())));
        } elseif (mb_strpos($e->getMessage(), 'HTTP') === 0) {
            report_warning(sprintf("HTTP Error %d in query_adsabs: %s", $e->getCode(), echoable($e->getMessage())));
        } elseif (mb_strpos($e->getMessage(), 'Too many requests') !== false) {
            report_warning('Giving up on AdsAbs for a while.  Too many requests.');
            if (mb_strpos($adsabs_url, 'bigquery') !== false) {
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

function process_bibcode_data(Template $this_template, object $record): void {
    $this_template->record_api_usage('adsabs', 'bibcode');
    if (!isset($record->title[0])) {
        return;
    }
    $this_template->add_if_new('title', (string) $record->title[0], 'adsabs'); // add_if_new will format the title text and check for unknown
    if (mb_stripos((string) $record->title[0], 'book') !== false && mb_stripos((string) $record->title[0], 'review') !== false) {
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
        } elseif (mb_substr($journal_start, 0, 6) === 'eprint') {  // No longer used
            if (mb_substr($journal_start, 0, 13) === 'eprint arxiv:') {          //@codeCoverageIgnore
                if (isset($record->arxivclass)) {
                    $this_template->add_if_new('class', (string) $record->arxivclass);  //@codeCoverageIgnore
                }
                $this_template->add_if_new('arxiv', mb_substr($journal_start, 13));    //@codeCoverageIgnore
            }
        } else {
            $this_template->add_if_new('journal', $journal_string[0], 'adsabs');
        }
    }
    if (isset($record->page)) {
        $tmp = implode($record->page);
        if ((mb_stripos($tmp, 'arxiv') !== false) || (mb_strpos($tmp, '/') !== false)) {  // Bad data
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
            if (mb_strtolower(mb_substr($recid, 0, 6)) === 'arxiv:') {
                if (isset($record->arxivclass)) {
                    $this_template->add_if_new('class', (string) $record->arxivclass, 'adsabs');
                }
                $this_template->add_if_new('arxiv', mb_substr($recid, 6), 'adsabs');
            }
        }
    }
    if (isset($record->doi)) {
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
    if ($template->blank([...FIRST_EDITOR_ALIASES, ...FIRST_AUTHOR_ALIASES, 'publisher'])) { // Avoid re-adding editors as authors, etc.
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

function looksLikeBookReview(Template $template, object $record): bool {
    if ($template->wikiname() === 'cite book' || $template->wikiname() === 'citation') {
        $book_count = 0;
        if ($template->has('publisher')) {
            $book_count += 1;
        }
        if ($template->has('isbn')) {
            $book_count += 2;
        }
        if ($template->has('location')) {
            $book_count += 1;
        }
        if ($template->has('chapter')) {
            $book_count += 2;
        }
        if ($template->has('oclc')) {
            $book_count += 1;
        }
        if ($template->has('lccn')) {
            $book_count += 2;
        }
        if ($template->has('journal')) {
            $book_count -= 2;
        }
        if ($template->has('series')) {
            $book_count += 1;
        }
        if ($template->has('edition')) {
            $book_count += 2;
        }
        if ($template->has('asin')) {
            $book_count += 2;
        }
        if (mb_stripos($template->get('url'), 'google') !== false && mb_stripos($template->get('url'), 'book') !== false) {
            $book_count += 2;
        }
        if (isset($record->year) && $template->year() && (int) $record->year !== (int) $template->year()) {
            $book_count += 1;
        }
        if ($template->wikiname() === 'cite book') {
            $book_count += 3;
        }
        if ($book_count > 3) {
            return true;
        }
    }
    return false;
}
