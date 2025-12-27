<?php

declare(strict_types=1);

require_once __DIR__ . '/../constants.php'; // @codeCoverageIgnoreStart
require_once __DIR__ . '/../Template.php';
require_once __DIR__ . '/../URLtools.php';
require_once __DIR__ . '/../miscTools.php'; // @codeCoverageIgnoreEnd

const MAGIC_STRING_URLS = 'CITATION_BOT_PLACEHOLDER_URL_POINTER_';
const CITOID_ZOTERO = "https://en.wikipedia.org/api/rest_v1/data/citation/zotero/";
const THESIS_TYPES = ['PhD', 'MS', 'MA', 'MFA', 'MBA', 'EdD', 'BSN', 'DMin', 'DDiv'];
const BAD_URL_STATUS = ['usurped', 'unfit', 'dead', 'deviated'];

/**
 * @param array<string> $_ids
 * @param array<Template> &$templates
 */
function query_url_api(array $_ids, array &$templates): void { // Pointer to save memory
    Zotero::query_url_api_class($templates);
}

final class Zotero {
    private const ZOTERO_GIVE_UP = 5;
    private const ZOTERO_SKIPS = 100;
    private const ERROR_DONE = 'ERROR_DONE';
    private static int $zotero_announced = 0;
    private static CurlHandle $zotero_ch;
    private static int $zotero_failures_count = 0;

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {
        // This is a static class
    }

    public static function create_ch_zotero(): void {
        static $is_setup = false;
        if ($is_setup) {
            return;
        }
        $is_setup = true;
        $time = (float) run_type_mods(1, 3, 3, 3, 3);
        self::$zotero_ch = bot_curl_init($time, [
            CURLOPT_URL => CITOID_ZOTERO,
            CURLOPT_HTTPHEADER => ['accept: application/json; charset=utf-8', 'Accept-Language: en-US,en,en-GB,en-CA', 'Cache-Control: no-cache, must-revalidate'],
        ]);
    }

    public static function block_zotero(): void {
        self::$zotero_failures_count = 1000000;
    }

    public static function unblock_zotero(): void {
        self::$zotero_failures_count = 0;
    }

    /**
     * @param array<Template> &$templates
     */
    public static function query_url_api_class(array &$templates): void { // Pointer to save memory
        foreach ($templates as $template) {
            if (preg_match('~pii/(S\d{16})(?:|\/|\?|\:|\&|\;)$~i', $template->get('url'), $matches)) { // PII
                if ($template->blank('doi')) {
                    $doi = get_doi_from_pii($matches[1]);
                    if (doi_works($doi)) {
                        $template->add_if_new('doi', $doi);
                    }
                }
                unset($doi, $matches);
            }
        }

        if (!SLOW_MODE) {
            return; // @codeCoverageIgnore
        }

        self::$zotero_announced = 1;
        foreach ($templates as $template) {
            $doi = $template->get('doi');
            if (!doi_active($doi)) { // Do not expand if DOI works with CrossRef
                self::expand_by_zotero($template);
            }
            if ($template->has('biorxiv')) {
                if ($template->blank('doi')) {
                    $template->add_if_new('doi', '10.1101/' . $template->get('biorxiv'));
                    expand_by_doi($template, true); // this data is better than zotero
                } elseif (mb_strstr($template->get('doi'), '10.1101') === false) {
                    expand_doi_with_dx($template, '10.1101/' . $template->get('biorxiv'));  // dx data is better than zotero
                    self::expand_by_zotero($template, 'https://dx.doi.org/10.1101/' . $template->get('biorxiv')); // Rare case there is a different DOI
                }
            }
            $doi = $template->get('doi'); // might have changed
            if (!doi_active($doi)) {
                if ($template->has('citeseerx')) {
                    self::expand_by_zotero($template, ' https://citeseerx.ist.psu.edu/viewdoc/summary?doi=' . $template->get('citeseerx'));
                }
                //  Has a CAPCHA -- if ($template->has('jfm'))
                //  Has a CAPCHA -- if ($template->has('zbl'))
                //  Do NOT do MR -- it is a review not the article itself. Note that html does have doi, but do not use it.
                if ($template->has('hdl')) {
                    self::expand_by_zotero($template, 'https://hdl.handle.net/' . $template->get('hdl'));
                }
                if ($template->has('osti')) {
                    self::expand_by_zotero($template, 'https://www.osti.gov/biblio/' . $template->get('osti'));
                }
                if ($template->has('rfc')) {
                    self::expand_by_zotero($template, 'https://tools.ietf.org/html/rfc' . $template->get('rfc'));
                }
                if ($template->has('ssrn')) {
                    self::expand_by_zotero($template, 'https://papers.ssrn.com/sol3/papers.cfm?abstract_id=' . $template->get('ssrn'));
                }
            }
            $doi = $template->get('doi'); // might have changed
            if (doi_works($doi)) {
                if (!doi_active($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
                    self::expand_by_zotero($template, 'https://dx.doi.org/' . $doi);  // DOIs without meta-data
                }
                if ($template->blank('title') && mb_stripos($doi, "10.1023/A:") === 0) {
                    self::expand_by_zotero($template, 'https://link.springer.com/article/' . $doi); // DOIs without title meta-data
                }
            }
        }
    }

    /**
     * @performance Keeps track of errors and adds small delays (0.1-0.2 seconds) when things go wrong.
     * After 5 errors in a row, pauses for 100 tries to avoid overloading the service. Tries again once if it times out.
     */
    private static function zotero_request(string $url): string {
        set_time_limit(120);
        if (self::$zotero_failures_count > self::ZOTERO_GIVE_UP) {
            self::$zotero_failures_count -= 1;
            if (self::$zotero_failures_count === self::ZOTERO_GIVE_UP) {
                self::$zotero_failures_count = 0; // @codeCoverageIgnore
            }
        }

        /** @psalm-taint-escape ssrf */
        $the_url = CITOID_ZOTERO . urlencode($url);
        curl_setopt(self::$zotero_ch, CURLOPT_URL, $the_url);

        if (self::$zotero_failures_count > self::ZOTERO_GIVE_UP) {
            return self::ERROR_DONE;
        }

        $delay = max(min(100000 * (1 + self::$zotero_failures_count), 10), 0); // 0.10 seconds delay, with paranoid bounds checks
        usleep($delay);
        $zotero_response = bot_curl_exec(self::$zotero_ch);
        if ($zotero_response === '') {
            sleep(2); // @codeCoverageIgnore
            $zotero_response = bot_curl_exec(self::$zotero_ch); // @codeCoverageIgnore
        }
        if ($zotero_response === '') {
            // @codeCoverageIgnoreStart
            report_warning(curl_error(self::$zotero_ch) . "  For URL: " . echoable($url));
            if (mb_strpos(curl_error(self::$zotero_ch), 'timed out after') !== false) {
                self::$zotero_failures_count += 1;
                if (self::$zotero_failures_count > self::ZOTERO_GIVE_UP) {
                    report_warning("Giving up on URL expansion for a while");
                    self::$zotero_failures_count += self::ZOTERO_SKIPS;
                }
            }
            $zotero_response = self::ERROR_DONE;
            // @codeCoverageIgnoreEnd
        }
        return $zotero_response;
    }

    public static function expand_by_zotero(Template $template, ?string $url = null): void {
        $access_date = 0;
        if (is_null($url)) {
            if (in_array($template->get('url-status'), BAD_URL_STATUS, true)) {
                return;
            }
            $access_date = (int) strtotime(tidy_date($template->get('accessdate') . ' ' . $template->get('access-date')));
            $archive_date = (int) strtotime(tidy_date($template->get('archivedate') . ' ' . $template->get('archive-date')));
            if ($access_date && $archive_date) {
                $access_date = min($access_date, $archive_date); // Whichever was first
            } elseif ($archive_date) {
                $access_date = $archive_date;
            }
            if ($template->has('url')) {
                $url = $template->get('url');
            } elseif ($template->has('chapter-url')) {
                $url = $template->get('chapter-url');
            } elseif ($template->has('chapterurl')) {
                $url = $template->get('chapterurl');
            } else {
                return;
            }
            if (preg_match('~^https?://(?:dx\.|)doi\.org~i', $url)) {
                return;
            }
            if (preg_match('~^https?://semanticscholar\.org~i', $url)) {
                return;
            }
            if (preg_match(REGEXP_BIBCODE, urldecode($url))) {
                return;
            }
            if (preg_match("~^https?://citeseerx\.ist\.psu\.edu~i", $url)) {
                return;
            }
            if (preg_match("~\barxiv\.org/.*(?:pdf|abs|ftp/arxiv/papers/\d{4})/(.+?)(?:\.pdf)?$~i", $url)) {
                return;
            }
        }

        if (!$template->profoundly_incomplete($url)) {
            return; // Only risk unvetted data if there's little good data to sully
        }

        if (mb_stripos($url, 'CITATION_BOT_PLACEHOLDER') !== false) {
            return; // That's a bad url
        }

        // Clean up URLs
        if (preg_match('~^(https?://(?:www\.|)nature\.com/articles/[a-zA-Z0-9\.]+)\.pdf(?:|\?.*)$~i', $url, $matches)) { // remove .PDF from Nature urls
            $url = $matches[1]; // @codeCoverageIgnore
        }
        if (preg_match('~^(https?://(?:www\.|)mdpi\.com/.+)(?:/pdf\-vor|/pdf)$~', $url, $matches)) {
            $url = $matches[1];
        }

        $bad_url = implode('|', ZOTERO_AVOID_REGEX);
        if (preg_match("~^https?://(?:www\.|m\.|ftp\.|web\.|)(?:" . $bad_url . ")~i", $url)) {
            return;
        }

        // Is it actually a URL. Zotero will search for non-url things too!
        if (preg_match('~^https?://[^/]+/?$~', $url) === 1) {
            return;  // Just a host name
        }
        set_time_limit(120); // This can be slow
        if (preg_match(REGEXP_IS_URL, $url) !== 1) {
            return;  // See https://mathiasbynens.be/demo/url-regex/ This regex is more exact than validator. We only spend time on this after quick and dirty check is passed
        }
        set_time_limit(120);
        if (self::$zotero_announced === 1) {
            report_action("Using Zotero translation server to retrieve details from URLs and identifiers");
            self::$zotero_announced = 0;
        }
        $zotero_response = self::zotero_request($url);
        self::process_zotero_response($zotero_response, $template, $url, $access_date);
        return;
    }

    public static function process_zotero_response(string $zotero_response, Template $template, string $url, int $access_date): void {
        if ($zotero_response === self::ERROR_DONE) {
            return;  // Error message already printed in zotero_request()
        }

        switch (mb_trim($zotero_response)) {
            case '':
                report_info("Nothing returned for URL " . echoable($url));
                return;
            case 'Internal Server Error':
                report_info("Internal server error with URL " . echoable($url));
                return;
            case 'Remote page not found':
                report_info("Remote page not found for URL " . echoable($url));
                return;
            case 'No items returned from any translator':
                report_info("Remote page not interpretable for URL " . echoable($url));
                return;
            case 'An error occurred during translation. Please check translation with the Zotero client.':
                report_info("An error occurred during translation for URL " . echoable($url));
                return;
        }

        if (mb_strpos($zotero_response, '502 Bad Gateway') !== false) {
            report_warning("Bad Gateway error for URL " . echoable($url));
            return;
        }
        if (mb_strpos($zotero_response, '503 Service Temporarily Unavailable') !== false) {
            report_warning("Temporarily Unavailable error for URL " . echoable($url)); // @codeCoverageIgnore
            return;                           // @codeCoverageIgnore
        }
        if (mb_strpos($zotero_response, '<title>Wikimedia Error</title>') !== false) {
            report_warning("Temporarily giving an error for URL " . echoable($url)); // @codeCoverageIgnore
            return;                           // @codeCoverageIgnore
        }
        $zotero_data = @json_decode($zotero_response, false);
        if (!isset($zotero_data)) {
            report_warning("Could not parse JSON for URL " . echoable($url) . ": " . $zotero_response);
            return;
        } elseif (!is_array($zotero_data)) {
            if (is_object($zotero_data)) {
                $zotero_data = (array) $zotero_data;
            } else {
                report_warning("JSON did not parse correctly for URL " . echoable($url) . ": " . $zotero_response);
                return;
            }
        }
        if (!isset($zotero_data[0])) {
            $result = $zotero_data;
        } else {
            $result = $zotero_data[0];
        }
        $result = (object) $result;

        if (empty($result->publicationTitle) && empty($result->bookTitle) && !isset($result->title)) {
            if (!empty($result->subject)) {
                $result->title = $result->subject;
            } elseif (!empty($result->caseName)) {
                $result->title = $result->caseName;
            } elseif (!empty($result->nameOfAct)) {
                $result->title = $result->nameOfAct;
            }
        }
        if (!isset($result->title)) {
            $the_url = mb_substr(echoable(mb_substr($url, 0, 500)), 0, 600); // Limit length
            if (mb_strpos($zotero_response, 'unknown_error') !== false) { // @codeCoverageIgnoreStart
                report_info("Did not get a title for unknown reason from URL " . $the_url);
            } elseif (mb_strpos($zotero_response, 'The remote document is not in a supported format') !== false) {
                report_info("Document type not supported (usually PDF) for URL " . $the_url);
            } elseif (mb_strpos($zotero_response, 'Unable to load URL') !== false) {
                report_info("Zotero could not fetch anything for URL " . $the_url);
            } elseif (mb_strpos($zotero_response, 'Invalid host supplied') !== false) {
                report_info("DNS lookup failed for URL " . $the_url);
            } elseif (mb_strpos($zotero_response, 'Unknown error') !== false) {
                report_info("Did not get a title for unknown reason from URL " . $the_url);
            } elseif (mb_strpos($zotero_response, 'Unable to get any metadata from url') !== false) {
                report_info("Did not get a title for unknown meta-data reason from URL " . $the_url);
            } elseif (mb_strpos($zotero_response, 'Maximum number of allowed redirects reached') !== false) {
                report_info("Too many redirects for URL " . $the_url);
            } else {
                report_minor_error("For some odd reason (" . $zotero_response . ") we did not get a title for URL " . $the_url); // Odd Error
            }
            return;  // @codeCoverageIgnoreEnd
        }
        if (mb_substr(mb_strtolower(mb_trim($result->title)), 0, 9) === 'not found') {
            report_info("Could not resolve URL " . echoable($url));
            return;
        }
        if ($result->title === 'Newstream') {
            report_info("No good meta-data from URL " . echoable($url));
            return;
        }
        // Remove unused stuff
        unset($result->abstractNote);
        unset($result->version);
        unset($result->accessDate);
        unset($result->libraryCatalog);
        unset($result->url);
        unset($result->tags);
        unset($result->key);
        unset($result->websiteTitle);
        unset($result->journalAbbreviation);
        unset($result->ISSN);
        unset($result->subject);
        unset($result->caseName);
        unset($result->nameOfAct);
        unset($result->language);
        unset($result->source);

        if (isset($result->publicationTitle) && mb_substr($result->publicationTitle, -2) === " |") {
            $result->publicationTitle = mb_substr($result->publicationTitle, 0, -2);
        }
        if (mb_stripos($url, 'www.royal.uk') !== false || mb_stripos($url, 'astanatimes.com') !== false || mb_stripos($url, 'theyucatantimes.com') !== false) {
            unset($result->creators);  // @codeCoverageIgnore
            unset($result->author);   // @codeCoverageIgnore
        }

        if (mb_stripos($url, 'theathletic.com') !== false) { // Returns NYT
            unset($result->publicationTitle);  // @codeCoverageIgnore
        }
        if (mb_stripos($url, 'music.mthai.com') !== false) { // Returns spamming ad verbiage
            unset($result->publicationTitle);  // @codeCoverageIgnore
        }
        if (mb_stripos($url, 'newsen.com') !== false) { // Includes title of article
            $result->publicationTitle = 'Newsen';
        }

        if (mb_stripos($url, '/x.com') !== false || mb_stripos($url, 'twitter.com') !== false) {
            $result->itemType = 'webpage';   // @codeCoverageIgnore
        }

        if (isset($result->publicationTitle) && $result->publicationTitle === 'news') {
            unset($result->publicationTitle);
        }

        if (mb_stripos($url, 'newrepublic.com') !== false) { // Bad data for all but first one
            unset($result->creators['1']);
            unset($result->author['1']);
        }

        if (mb_stripos($url, 'flickr.') !== false) {
            $result->itemType = 'webpage';
            unset($result->publicationTitle); //Flickr is not a work
        }

        if (mb_stripos($url, 'pressbooks.online.ucf.edu') !== false) {
            $result->itemType = 'webpage';
            unset($result->author); // They list themself
        }

        if (mb_stripos($url, '.tumblr.com') !== false) { // Returns tumblr, and it is a sub-domain
            unset($result->publicationTitle);  // @codeCoverageIgnore
        }
        if (mb_stripos($url, 'tumblr.com') !== false) {
            $result->itemType = 'webpage';  // @codeCoverageIgnore
        }
        if (mb_stripos($url, 'tate.org.uk') !== false) {
            $result->itemType = 'webpage';
            unset($result->creators);
            unset($result->author);
        }
        if (mb_stripos((string) @$result->publicationTitle, 'Extended Abstracts') !== false) { // https://research.vu.nl/en/publications/5a946ccf-5f5b-4cab-b47e-824508c4d709
            unset($result->publicationTitle);
        }

        // Reject if we find more than 5 or more than 10% of the characters are �. This means that character
        // set was not correct in Zotero and nothing is good.  We allow a couple of � for German umlauts that arer easily fixable by humans.
        // We also get a lot of % and $ if the encoding was something like iso-2022-jp and converted wrong
        $bad_count = mb_substr_count($result->title, '�') + mb_substr_count($result->title, '$') + mb_substr_count($result->title, '%');
        $total_count = mb_strlen($result->title);
        if (isset($result->bookTitle)) {
            $bad_count += mb_substr_count($result->bookTitle, '�') + mb_substr_count($result->bookTitle, '$') + mb_substr_count($result->bookTitle, '%');
            $total_count += mb_strlen($result->bookTitle);
        }
        if (($bad_count > 5) || ($total_count > 1 && (($bad_count / $total_count) > 0.1))) {
            report_info("Could parse unicode characters in " . echoable($url));
            return;
        }

        report_info("Retrieved info from " . echoable($url));
        // Verify that Zotero translation server did not think that this was a website and not a journal
        if (mb_strtolower(mb_substr(mb_trim($result->title), -9)) === ' on jstor') {  // Not really "expanded", just add the title without " on jstor"
            $template->add_if_new('title', mb_substr(mb_trim($result->title), 0, -9)); // @codeCoverageIgnore
            return;  // @codeCoverageIgnore
        }

        $test_data = '';
        if (isset($result->bookTitle)) {
            $test_data .= $result->bookTitle . ' ';
        }
        if (isset($result->title)) {
            $test_data .= $result->title;
        }
        foreach (BAD_ZOTERO_TITLES as $bad_title ) {
            if (mb_stripos($test_data, $bad_title) !== false) {
                report_info("Received invalid title data for URL " . echoable($url . ": " . $test_data));
                return;
            }
        }
        if ($test_data === '404' || $test_data === '/404') {
            return;
        }
        if (isset($result->bookTitle) && mb_strtolower($result->bookTitle) === 'undefined') {
            unset($result->bookTitle); // S2 without journals
        }
        if (isset($result->publicationTitle) && mb_strtolower($result->publicationTitle) === 'undefined') {
            unset($result->publicationTitle); // S2 without journals
        }
        if (isset($result->bookTitle)) {
            foreach (ZOTERO_LIST_OF_EVIL as $bad_title ) {
                if (str_i_same($result->bookTitle, $bad_title)) {
                    report_info("Received invalid book title data for URL " . echoable($url . ": " . $result->bookTitle));
                    return;
                }
            }
        }
        if (isset($result->title)) {
            foreach (ZOTERO_LIST_OF_EVIL as $bad_title ) {
                if (str_i_same($result->title, $bad_title)) {
                    report_info("Received invalid title data for URL " . echoable($url . ": " . $result->title));
                    return;
                }
            }
        }
        if (isset($result->publicationTitle)) {
            foreach (ZOTERO_LIST_OF_EVIL as $bad_title ) {
                if (str_i_same($result->publicationTitle, $bad_title)) {
                    report_info("Received invalid publication title data for URL " . echoable($url . ": " . $result->publicationTitle));
                    return;
                }
            }
            // Specific bad data that is correctable
            $tester = mb_strtolower($result->publicationTitle);
            if ($tester === 'nationalpost') {
                $result->publicationTitle = 'National Post';
            } elseif ($tester === 'financialpost') {
                $result->publicationTitle = 'Financial Post';
            } elseif ($tester === 'bloomberg.com') {
                $result->publicationTitle = 'Bloomberg';
            } elseif ($tester === 'radiofreeeurope/radioliberty') {
                $result->publicationTitle = 'Radio Free Europe/Radio Liberty';
            } elseif (mb_strpos($tester, 'the yucatan times') === 0) {
                $result->publicationTitle = 'The Yucatan Times';
            } elseif ($tester === 'advanced books') {
                unset($result->issue);
                unset($result->volume);
                unset($result->pages);
                unset($result->publicationTitle);
            }
        }

        // Ignore junk website names
        if (isset($result->publicationTitle) && preg_match('~^https?://([^/]+)~', $url, $hostname) === 1) {
            $hostname = str_ireplace('www.', '', (string) $hostname[1]);
            $pub_name = str_ireplace('www.', '', (string) $result->publicationTitle);
            if (str_i_same($pub_name, $hostname)) {
                unset($result->publicationTitle);
            }
        }

        if (preg_match('~^([^\]]+)\|([^\]]+)\| ?THE DAILY STAR$~i', (string) @$result->title, $matches)) {
            $result->title = $matches[1];
            $result->publicationTitle = 'The Daily Star';
        }

        if (isset($result->extra)) { // [extra] => DOI: 10.1038/546031a has been seen in the wild
            if (preg_match('~\sdoi:\s?([^\s]+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                if (!isset($result->DOI)) {
                    $result->DOI = mb_trim($matches[1]);
                }
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
            }
            if (preg_match('~\stype:\s?([^\s]+)\s~i', ' ' . $result->extra . ' ', $matches)) { // [extra] => type: dataset has been seen in the wild
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
            }
            if (preg_match('~\sPMID: (\d+)\s+PMCID: PMC(\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
                $template->add_if_new('pmid', $matches[1]);
                $template->add_if_new('pmc', $matches[2]);
            }
            if (preg_match('~\sPMID: (\d+), (\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
                if ($matches[1] === $matches[2]) {
                    $template->add_if_new('pmid', $matches[1]);
                }
            }
            if (preg_match('~\sPMID: (\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
                $template->add_if_new('pmid', $matches[1]);
            }
            if (preg_match('~\sOCLC: (?:|ocn|ocm)(\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
                $template->add_if_new('oclc', $matches[1]);
            }
            if (preg_match('~\sOpen Library ID: OL(\d+M)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
                $template->add_if_new('ol', $matches[1]);
            }

            // UNUSED stuff goes below

            if (preg_match('~\sFormat: PDF\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
            }
            if (preg_match('~\sIMDb ID: ((?:tt|co|nm)\d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
            }
            if (preg_match('~\s(original-date: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Google-Books-ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(ISSN: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Page Version ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Citation Key: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(number-of-pages: [ivx]+, \d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(number-of-pages: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Version: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(RSLID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(QID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(National Archives Identifier: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Catalog Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(BMCR ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(PubAg AGID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(IP-\d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Accession Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\sADS Bibcode: (\d{4}\S{15})\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));
                $template->add_if_new('bibcode', $matches[1]);
            }
            if (preg_match('~\s(arXiv: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - only comes from arXiv DOIs
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra)); // @codeCoverageIgnore
            }
            if (preg_match('~\s(INIS Reference Number: \d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - https://inis.iaea.org
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra)); // @codeCoverageIgnore
            }
            if (preg_match('~\s(ERIC Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra)); // @codeCoverageIgnore
            }
            if (preg_match('~\s(\d+ cm\.)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - size of book
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra)); // @codeCoverageIgnore
            }
            // These go at end since it is unbound on end often with linefeeds and such
            if (preg_match('~submitted:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~event\-location:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it and it is long verbose
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Translated title:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~reviewed\-title:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Physical Description:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~BBK:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Place Manufactured: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Dimensions: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Category: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Credit: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Manufacturer: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~pl., cartes, errata.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Post URL:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Reference Number:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~jurisdiction:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = mb_trim(str_replace(mb_trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            $result->extra = mb_trim($result->extra);
            if ($result->extra !== '') {
                // TODO - check back later on report_minor_error("Unhandled extra data: " . echoable($result->extra) .  ' FROM ' . echoable($url));     // @codeCoverageIgnore
            }
        }

        if (isset($result->DOI) && $template->blank('doi')) {
            if (preg_match('~^(?:https://|http://|)(?:dx\.|)doi\.org/(.+)$~i', $result->DOI, $matches)) {
                    $result->DOI = $matches[1];
            }
            $possible_doi = sanitize_doi($result->DOI);
            if (doi_works($possible_doi)) {
                $template->add_if_new('doi', $possible_doi);
                expand_by_doi($template);
                if (mb_stripos($url, 'jstor')) {
                    check_doi_for_jstor($template->get('doi'), $template);
                }
                if (!$template->profoundly_incomplete()) {
                    return;
                }
            }
        }

        if (isset($result->date)) {
            foreach (NO_DATE_WEBSITES as $bad_website ) {
                if (mb_stripos($url, $bad_website) !== false) {
                    unset($result->date);
                    break;
                }
            }
        }

        if (isset($result->ISBN)) {
            $template->add_if_new('isbn', $result->ISBN);
        }
        if ($access_date && isset($result->date)) {
            $new_date = strtotime(tidy_date((string) $result->date)); // One time got an integer
            if ($new_date) { // can compare
                if ($new_date > $access_date) {
                    report_info("URL appears to have changed since access-date " . echoable($url));
                    return;
                }
            }
        }
        if (str_i_same(mb_substr((string) @$result->publicationTitle, 0, 4), 'http') ||
                str_i_same(mb_substr((string) @$result->bookTitle, 0, 4), 'http') ||
                str_i_same(mb_substr((string) @$result->title, 0, 4), 'http')) {
            report_info("URL returned in Journal/Newpaper/Title/Chapter field for " . echoable($url)); // @codeCoverageIgnore
            return;                                   // @codeCoverageIgnore
        }

        if (isset($result->bookTitle)) {
            $result->bookTitle = safe_preg_replace('~\s*\(pdf\)$~i', '', $result->bookTitle);
            $result->bookTitle = safe_preg_replace('~^\(pdf\)\s*~i', '', $result->bookTitle);
            $result->bookTitle = safe_preg_replace('~ \- ProQuest\.?~i', '', $result->bookTitle);
        }
        if (isset($result->title)) {
            $result->title = safe_preg_replace('~\s*\(pdf\)$~i', '', (string) $result->title);
            $result->title = safe_preg_replace('~^\(pdf\)\s*~i', '', $result->title);
            $result->title = safe_preg_replace('~ \- ProQuest\.?~i', '', $result->title);
        }

        if (mb_strpos($url, 'biodiversitylibrary.org') !== false) {
            unset($result->publisher); // Not reliably set
        }
        if (isset($result->title) && $result->title === 'Cultural Advice' && mb_strpos($url, 'edu.au') !== false) {
            unset($result->title); // A warning, not a title
        }
        if ($template->has('title')) {
            if (isset($result->title) && titles_are_similar($template->get('title'), (string) $result->title)) {
                unset($result->title);
            }
        }
        if ($template->has('chapter')) {
            if (isset($result->title) && titles_are_similar($template->get('chapter'), (string) $result->title)) {
                unset($result->title);
            }
        }
        if (isset($result->bookTitle)) {
            $template->add_if_new('title', (string) $result->bookTitle);
            if (isset($result->title)) {
                $template->add_if_new('chapter', (string) $result->title);
            }
            if (isset($result->publisher)) {
                $template->add_if_new('publisher', (string) $result->publisher);
            }
        } else {
            if (isset($result->title)) {
                $template->add_if_new('title', (string) $result->title);
            }
            if (isset($result->itemType) && ($result->itemType === 'book' || $result->itemType === 'bookSection')) {
                if (isset($result->publisher)) {
                    $template->add_if_new('publisher', (string) $result->publisher);
                }
            }
        }

        if (isset($result->issue)) {
            $the_issue = clean_volume((string) $result->issue);
            if (mb_strlen($the_issue) < 10) { // Sometimes issues are full on phrases
                $template->add_if_new('issue', $the_issue);
            }
            unset($the_issue);
        }
        if (isset($result->pages)) {
            $pos_pages = (string) $result->pages;
            if (preg_match('~\d~', $pos_pages) && !preg_match('~\d+\.\d+.\d+~', $pos_pages)) { // At least one number but not a dotted number from medRxiv
                $pos_pages = str_ireplace(['σελ.', 'σελ ', 'pages ', 'page ', 'pages:', 'page:', 'pages', 'page'], [' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '], $pos_pages);
                $pos_pages = mb_trim($pos_pages);
                $pos_pages = str_ireplace(['  ', '  ', '  '], [' ', ' ', ' '], $pos_pages);
                $template->add_if_new('pages', $pos_pages);
            }
        }
        if (isset($result->itemType) && $result->itemType === 'newspaperArticle') {
            if (isset($result->publicationTitle)) {
                $new_title = (string) $result->publicationTitle;
                if (in_array(mb_strtolower($new_title), WORKS_ARE_PUBLISHERS, true)) {
                    $template->add_if_new('publisher', $new_title);
                } elseif ($template->blank(WORK_ALIASES)) {
                    $template->add_if_new('work', $new_title);
                } else {
                    $use_it = false;
                    foreach (WORK_ALIASES as $work_type) {
                        $test_it = mb_substr($template->get($work_type), -4);
                        if (str_i_same($test_it, '.com') || str_i_same($test_it, '.org') || str_i_same($test_it, '.net')) {
                            $use_it = true;
                        }
                    }
                    if ($use_it) {
                        $template->add_if_new('work', $new_title);
                    }
                }
            }
        } else {
            if (isset($result->publicationTitle)) {
                if ((!$template->has('title') || !$template->has('chapter')) && // Do not add if already has title and chapter
                    (mb_stripos((string) $result->publicationTitle, ' edition') === false)) { // Do not add if "journal" includes "edition"
                    if (str_replace(NON_JOURNALS, '', (string) $result->publicationTitle) === (string) $result->publicationTitle) {
                        if (str_ireplace(NON_JOURNAL_WEBSITES, '', $url) === $url || $template->wikiname() === 'cite journal') {
                            if (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $url) === $url && str_ireplace(JOURNAL_ARCHIVES_SITES, '', $url) === $url) {
                                if ($url !== '' && $url !== 'X' && str_ireplace(['digitalcommons', 'repository', 'scholarship', 'digitalcollection', 'dialnet.', 'handle.net', '.library.', 'dx.doi.org'], '', $url) === $url && str_ireplace(NON_JOURNAL_WEBSITES, '', $url) === $url) { // '' and 'X" are only in test suite
                                    bot_debug_log('Possible journal URL: ' . $url);
                                }
                                $template->add_if_new('work', (string) $result->publicationTitle);
                            } else {
                                $template->add_if_new('journal', (string) $result->publicationTitle);
                            }
                        } else {
                            $template->add_if_new('work', (string) $result->publicationTitle);
                        }
                    }
                }
            }
        }
        if (isset($result->volume)) {
            $template->add_if_new('volume', clean_volume((string) $result->volume));
        }
        if (isset($result->date) && mb_strlen((string) $result->date) > 3) {
            $new_date = tidy_date((string) $result->date);
            if (mb_stripos($url, 'indiatimes') !== false) { // "re-posted" website all at once
                $maybe_date = (int) strtotime($new_date);
                $end_date1 = strtotime('10 January 2017');
                $end_date2 = strtotime('21 January 2017');
                if ($maybe_date > $end_date1 && $maybe_date < $end_date2) {
                    $new_date = '';
                }
            }
            if ($new_date) {
                $template->add_if_new('date', $new_date);
            }
        }
        if (isset($result->series) && mb_stripos($url, '.acm.org') === false) {
            $template->add_if_new('series', (string) $result->series);
        }
        $i = 0;
        while (isset($result->author[$i])) {
            if (is_bad_author((string) @$result->author[$i][1])) {
                unset($result->author[$i][1]);
            }
            if (is_bad_author((string) @$result->author[$i][0])) {
                unset($result->author[$i][0]);
            }
            $i++;
        }
        unset($i);
        if (isset($result->author[0]) && !isset($result->author[1]) &&
                !author_is_human(@$result->author[0][0] . ' ' . @$result->author[0][1])) {
            unset($result->author[0]); // Do not add a single non-human author
        }
        $i = 0;
        while (isset($result->author[$i])) {
            if (author_is_human(@$result->author[$i][0] . ' ' . @$result->author[$i][1])) {
                $template->validate_and_add('author' . (string) ($i + 1), (string) @$result->author[$i][1], (string) @$result->author[$i][0],
                                                                isset($result->rights) ? (string) $result->rights : '', false);
            }
            $i++;
            if ($template->blank(['author' . (string) $i, 'first' . (string) $i, 'last' . (string) $i])) {
                break; // Break out if nothing added
            }
        }
        unset($i);

        if ((mb_stripos($url, '/sfdb.org') !== false || mb_stripos($url, '.sfdb.org') !== false) && $template->blank(WORK_ALIASES)) {
                $template->add_if_new('website', 'sfdb.org');
        }

        // see https://www.mediawiki.org/wiki/Citoid/itemTypes
        if (isset($result->itemType)) {
            switch ($result->itemType) {
                case 'book':
                case 'bookSection':
                    // Too much bad data to risk switching journal to book or vice versa.
                    // also reject 'review'
                    if ($template->wikiname() === 'cite web' &&
                            $template->blank('website') && // Leads to error
                            mb_stripos($url . @$result->title . @$result->bookTitle . @$result->publicationTitle, 'review') === false &&
                            mb_stripos($url, 'archive.org') === false && !preg_match('~^https?://[^/]*journal~', $url) &&
                            mb_stripos($url, 'booklistonline') === false &&
                            mb_stripos($url, 'catalogue.bnf') === false &&
                            mb_stripos($url, 'finna.fi') === false &&
                            mb_stripos($url, 'planetebd.com') === false &&
                            mb_stripos($url, 'data.bnf.fr') === false &&
                            mb_stripos($url, 'audible.com') === false &&
                            mb_stripos($url, 'elonet.fi') === false
                        ) {
                        $template->change_name_to('cite book');
                    }
                    break;
                case 'journalArticle':
                case 'conferencePaper':
                case 'report': // ssrn uses this
                    if (($template->wikiname() === 'cite web') &&
                            (str_ireplace(NON_JOURNAL_WEBSITES, '', $url) === $url) &&
                            !$template->blank(WORK_ALIASES) &&
                            (str_ireplace('breakingnews', '', $url) === $url) &&
                            (str_ireplace('/blog/', '', $url) === $url)) {
                        $template->change_name_to('cite journal');
                    }
                    break;
                case 'magazineArticle':
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite magazine');
                    }
                    break;
                case 'newspaperArticle':  // Many things get called "news" in error
                    /* if ($template->wikiname() === 'cite web') {
                        $test_data = $template->get('work') . $template->get('website') .
                            $template->get('url') . $template->get('chapter-url') .
                            $template->get('title') . $template->get('encyclopedia') .
                            $template->get('encyclopædia') . $url;
                        if (str_ireplace(['.gov', 'encyclopedia', 'encyclopædia'], '', $test_data) === $test_data) {
                            $template->change_name_to('cite news');
                        }
                    } */
                    break;
                case 'thesis':
                    $template->change_name_to('cite thesis');
                    if (isset($result->university)) {
                        $template->add_if_new('publisher', $result->university);
                    }
                    if (isset($result->thesisType) && $template->blank(['type', 'medium', 'degree'])) {
                        $type = (string) $result->thesisType;
                        $type = str_replace('.', '', $type);
                        if (in_array($type, THESIS_TYPES, true)) {
                            $template->add_if_new('type', $type); // Prefer type since it exists in cite journal too
                        }
                    }
                    break;

                case 'email': // Often uses subject, not title
                case 'webpage':
                case 'blogPost':
                case 'document':// Could be a journal article or a genuine web page.
                case 'preprint':
                case 'entry':
                case 'videoRecording':
                case 'film':
                case 'map':   // @codeCoverageIgnore
                case 'bill':  // @codeCoverageIgnore
                case 'manuscript':  // @codeCoverageIgnore
                case 'audioRecording':  // @codeCoverageIgnore
                case 'presentation':   // @codeCoverageIgnore
                case 'computerProgram':  // @codeCoverageIgnore
                case 'forumPost':    // @codeCoverageIgnore
                case 'tvBroadcast':    // @codeCoverageIgnore
                case 'podcast':    // @codeCoverageIgnore
                case 'manuscript':    // @codeCoverageIgnore
                case 'artwork':    // @codeCoverageIgnore
                case 'case':    // @codeCoverageIgnore
                case 'statute':    // @codeCoverageIgnore
                case 'interview':  // @codeCoverageIgnore
                case 'letter':  // @codeCoverageIgnore
                case 'dataset':  // @codeCoverageIgnore
                case 'radioBroadcast':  // @codeCoverageIgnore
                        // Do not change type. Would need to think about parameters
                case 'patent':    // @codeCoverageIgnore
                        // Do not change type. This seems to include things that will just make people angry if we change type to encyclopedia
                case 'encyclopediaArticle':  // @codeCoverageIgnore
                        // Probably tick people off too
                case 'dictionaryEntry':  // @codeCoverageIgnore
                    // Nothing special that we know of yet
                    break;

                default:                                     // @codeCoverageIgnore
                    report_minor_error("Unhandled itemType: " . echoable($result->itemType) . " for " . echoable($url)); // @codeCoverageIgnore
            }

            if (in_array($result->itemType, ['journalArticle', 'newspaperArticle', 'report', 'magazineArticle', 'thesis'], true)) {
                // Websites often have non-authors listed in metadata
                // "Books" are often bogus
                $i = 0;
                $author_i = 0;
                $editor_i = 0;
                $translator_i = 0;
                while (isset($result->creators[$i])) {
                    $creatorType = $result->creators[$i]->creatorType ?? 'author';
                    if (isset($result->creators[$i]->firstName) && isset($result->creators[$i]->lastName)) {
                        switch ($creatorType) {
                            case 'author':
                            case 'contributor':
                            case 'artist':
                                ++$author_i;
                                $authorParam = 'author' . (string) $author_i;
                                break;
                            case 'editor':
                                ++$editor_i;
                                $authorParam = 'editor' . (string) $editor_i;
                                break;
                            case 'translator':
                                ++$translator_i;
                                $authorParam = 'translator' . (string) $translator_i;
                                break;
                            case 'reviewedAuthor':  // @codeCoverageIgnore
                                $authorParam = '';   // @codeCoverageIgnore
                                break;         // @codeCoverageIgnore
                            case 'performer': // http://catalog.nypl.org/search/o77059475
                                $authorParam = '';
                                break;
                            default:                                // @codeCoverageIgnore
                                report_minor_error("Unrecognized creator type: " . echoable($creatorType) . ' FROM ' . echoable($url));   // @codeCoverageIgnore
                                $authorParam = '';                          // @codeCoverageIgnore
                        }
                        if ($authorParam && author_is_human($result->creators[$i]->firstName . ' ' . $result->creators[$i]->lastName)) {
                            if (is_bad_author((string) $result->creators[$i]->lastName)) {
                                $result->creators[$i]->lastName  = '';
                            }
                            if (is_bad_author((string) $result->creators[$i]->firstName)) {
                                $result->creators[$i]->firstName = '';
                            }
                            $template->validate_and_add($authorParam, (string) $result->creators[$i]->lastName, (string) $result->creators[$i]->firstName,
                            isset($result->rights) ? (string) $result->rights : '', false);
                                // Break out if nothing added
                            if ((mb_strpos($authorParam, 'author') === 0) && $template->blank(['author' . (string) ($author_i), 'first' . (string) ($author_i), 'last' . (string) ($author_i)])) {
                                break;
                            }
                            if ((mb_strpos($authorParam, 'editor') === 0) && $template->blank(['editor' . (string) ($editor_i)])) {
                                break;
                            }
                            if ((mb_strpos($authorParam, 'translator') === 0) && $template->blank(['translator' . (string) ($translator_i)])) {
                                break;
                            }
                        }
                    }
                    $i++;
                }
            }
            if (mb_stripos(mb_trim($template->get('publisher')), 'Associated Press') !== false &&
                mb_stripos($url, 'ap.org') === false  ) {
                if ($template->wikiname() === 'cite news') {
                    $template->rename('publisher', 'agency'); // special template parameter just for them
                }
                if (mb_stripos(mb_trim($template->get('author')), 'Associated Press') === 0) {
                    $template->forget('author'); // all too common
                }
            }
            if (mb_stripos(mb_trim($template->get('publisher')), 'Reuters') !== false &&
                mb_stripos($url, 'reuters.org') === false ) {
                if ($template->wikiname() === 'cite news') {
                    $template->rename('publisher', 'agency'); // special template parameter just for them
                }
                if (mb_stripos(mb_trim($template->get('author')), 'Reuters') === 0) {
                    $template->forget('author'); // all too common
                }
            }
        }
        if ($template->wikiname() === 'cite web') {
            if (mb_stripos($url, 'businesswire.com/news') !== false ||
                mb_stripos($url, 'prnewswire.com/') !== false ||
                mb_stripos($url, 'globenewswire.com/') !== false ||
                mb_stripos($url, 'newswire.com/') !== false) {
                $template->change_name_to('cite press release');
            }
        }
        return;
    }

} // End of CLASS
