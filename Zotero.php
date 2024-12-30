<?php

declare(strict_types=1);

require_once 'constants.php'; // @codeCoverageIgnore
require_once 'Template.php';  // @codeCoverageIgnore

const MAGIC_STRING_URLS = 'CITATION_BOT_PLACEHOLDER_URL_POINTER_';
const CITOID_ZOTERO = "https://en.wikipedia.org/api/rest_v1/data/citation/zotero/";
const THESIS_TYPES = ['PhD', 'MS', 'MA', 'MFA', 'MBA', 'EdD', 'BSN', 'DMin', 'DDiv'];
const BAD_URL_STATUS = ['usurped', 'unfit', 'dead', 'deviated'];
/**
    @param array<string> $_ids
    @param array<Template> $templates
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
    private static CurlHandle $ch_ieee;
    private static CurlHandle $ch_jstor;
    private static CurlHandle $ch_dx;
    private static CurlHandle $ch_pmc;
    private static CurlHandle $ch_doi;
    private static CurlHandle $ch_pii;
    private static int $zotero_failures_count = 0;

    public static function create_ch_zotero(): void {
        static $is_setup = false;
        if ($is_setup) {
            return;
        }
        $is_setup = true;
        if (TRAVIS) {
            $time = 3.0;
        } else {
            $time = 1.0; // @codeCoverageIgnore
        }
        self::$zotero_ch = bot_curl_init($time, [
            CURLOPT_URL => CITOID_ZOTERO,
            CURLOPT_HTTPHEADER => ['accept: application/json; charset=utf-8', 'Accept-Language: en-US,en,en-GB,en-CA', 'Cache-Control: no-cache, must-revalidate'],
        ]);

        self::$ch_ieee = bot_curl_init($time, [CURLOPT_USERAGENT => 'curl']); // IEEE requires JavaScript, unless curl is specified

        self::$ch_jstor = bot_curl_init($time, []);

        self::$ch_dx = bot_curl_init($time, []);

        self::$ch_pmc = bot_curl_init($time, []);

        self::$ch_doi = bot_curl_init($time, []);

        self::$ch_pii = bot_curl_init($time, []);
    }

    public static function block_zotero(): void {
        self::$zotero_failures_count = 1000000;
    }

    public static function unblock_zotero(): void {
        self::$zotero_failures_count = 0;
    }

    /**
        @param array<Template> $templates
    */
    public static function query_url_api_class(array &$templates): void { // Pointer to save memory
        foreach ($templates as $template) {
            if (preg_match('~pii/(S\d{16})(?:|\/|\?|\:|\&|\;)$~i', $template->get('url'), $matches)) { // PII
                if ($template->blank('doi')) {
                    $doi = self::get_doi_from_pii($matches[1]);
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
            self::expand_by_zotero($template);
        }
        self::$zotero_announced = 2;
        foreach ($templates as $template) {
            if ($template->has('biorxiv')) {
                if ($template->blank('doi')) {
                    $template->add_if_new('doi', '10.1101/' . $template->get('biorxiv'));
                    expand_by_doi($template, true); // this data is better than zotero
                } elseif (strstr($template->get('doi'), '10.1101') === false) {
                     expand_doi_with_dx($template, '10.1101/' . $template->get('biorxiv'));  // dx data is better than zotero
                     self::expand_by_zotero($template, 'https://dx.doi.org/10.1101/' . $template->get('biorxiv')); // Rare case there is a different DOI
                }
            }
            $doi = $template->get('doi');
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
            if ($template->has('doi')) {
                $doi = $template->get('doi');
                if (!doi_active($doi) && doi_works($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
                    self::expand_by_zotero($template, 'https://dx.doi.org/' . $doi);  // DOIs without meta-data
                }
                if (doi_works($doi) && $template->blank('title') && stripos($doi, "10.1023/A:") === 0) {
                    self::expand_by_zotero($template, 'https://link.springer.com/article/' . $doi); // DOIs without title meta-data
                }
            }
        }
    }

    /**
        @param array<Template> $templates
    */
    public static function query_ieee_webpages(array &$templates): void {  // Pointer to save memory
        foreach (['url', 'chapter-url', 'chapterurl'] as $kind) {
            foreach ($templates as $template) {
                set_time_limit(120);
                /** @psalm-taint-escape ssrf */
                $the_url = $template->get($kind);
                if (preg_match("~^https://ieeexplore\.ieee\.org/document/(\d{5,})$~", $the_url, $matches_url)) {
                    curl_setopt(self::$ch_ieee, CURLOPT_URL, $the_url);
                    if ($template->blank('doi')) {
                        usleep(100000); // 0.10 seconds
                        $return = bot_curl_exec(self::$ch_ieee);
                        if ($return !== "" && preg_match_all('~"doi":"(10\.\d{4}/[^\s"]+)"~', $return, $matches, PREG_PATTERN_ORDER)) {
                            $dois = array_unique($matches[1]);
                            if (count($dois) === 1) {
                                if ($template->add_if_new('doi', $dois[0])) {
                                    if (strpos($template->get('doi'), $matches_url[1]) !== false && doi_works($template->get('doi'))) {
                                        // SEP 2020 $template->forget($kind);  // It is one of those DOIs with the document number in it
                                    }
                                }
                            }
                        }
                    } elseif (doi_works($template->get('doi'))) {
                        usleep(100000); // 0.10 seconds
                        $return = bot_curl_exec(self::$ch_ieee);
                        if ($return !== "" && strpos($return, "<title> -  </title>") !== false) {
                            report_forget("Existing IEEE no longer works - dropping URL"); // @codeCoverageIgnore
                            $template->forget($kind);                   // @codeCoverageIgnore
                        }
                    }
                }
            }
        }
    }

    /**
        @param array<Template> $templates
    */
    public static function drop_urls_that_match_dois(array &$templates): void {  // Pointer to save memory
        // Now that we have expanded URLs, try to lose them
        foreach ($templates as $template) {
            $doi = $template->get_without_comments_and_placeholders('doi');
            if ($template->has('url')) {
                $url = $template->get('url');
                $url_kind = 'url';
            } elseif ($template->has('chapter-url')) {
                $url = $template->get('chapter-url');
                $url_kind = 'chapter-url';
            } elseif ($template->has('chapterurl')) {
                $url = $template->get('chapterurl'); // @codeCoverageIgnore
                $url_kind = 'chapterurl';      // @codeCoverageIgnore
            } else {
                $url = '';
                $url_kind = '';
            }
            if ($doi &&  // IEEE code does not require "not incomplete"
                $url &&
                !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
                $template->blank(DOI_BROKEN_ALIASES) &&
                preg_match("~^https?://ieeexplore\.ieee\.org/document/\d{5,}/?$~", $url) && strpos($doi, '10.1109') === 0) {
                // SEP 2020 report_forget("Existing IEEE resulting from equivalent DOI; dropping URL");
                // SEP 2020 $template->forget($url_kind);
            }

            if ($doi &&
                    $url &&
                    !$template->profoundly_incomplete() &&
                    !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
                    (strpos($doi, '10.1093/') === false) &&
                    $template->blank(DOI_BROKEN_ALIASES)) {
                    set_time_limit(120);
                if (str_ireplace(PROXY_HOSTS_TO_DROP, '', $url) !== $url && $template->get('doi-access') === 'free') {
                    report_forget("Existing proxy URL resulting from equivalent free DOI; dropping URL");
                    $template->forget($url_kind);
                } elseif (str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url && $template->get('doi-access') === 'free') {
                    report_forget("Existing proxy URL resulting from equivalent free DOI; dropping URL");
                    $template->forget($url_kind);
                } elseif (str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url && $template->blank(['archive-url', 'archiveurl'])) {
                    report_forget("Existing proxy URL resulting from equivalent DOI; fixing URL");
                    $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
                } elseif (preg_match('~www.sciencedirect.com/science/article/B[^/\-]*\-[^/\-]+\-[^/\-]+/~', $url)) {
                    report_forget("Existing Invalid ScienceDirect URL when DOI is present; fixing URL");
                    $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
                } elseif (preg_match('~www.sciencedirect.com/science/article/pii/\S{0,16}$~i', $url)) { // Too Short
                    report_forget("Existing Invalid ScienceDirect URL when DOI is present; fixing URL");
                    $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
                } elseif (preg_match('~www.springerlink.com/content~i', $url)) { // Dead website
                    report_forget("Existing Invalid Springer Link URL when DOI is present; fixing URL");
                    $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
                } elseif (str_ireplace('insights.ovid.com/pubmed', '', $url) !== $url && $template->has('pmid')) {
                    // SEP 2020 report_forget("Existing OVID URL resulting from equivalent PMID and DOI; dropping URL");
                    // SEP 2020 $template->forget($url_kind);
                } elseif ($template->has('pmc') && str_ireplace('iopscience.iop.org', '', $url) !== $url) {
                    // SEP 2020 report_forget("Existing IOP URL resulting from equivalent DOI; dropping URL");
                    // SEP 2020 $template->forget($url_kind);;
                    $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
                } elseif (str_ireplace('wkhealth.com', '', $url) !== $url) {
                    report_forget("Existing Outdated WK Health URL resulting from equivalent DOI; fixing URL");
                    $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
                } elseif ($template->has('pmc') && str_ireplace('bmj.com/cgi/pmidlookup', '', $url) !== $url && $template->has('pmid') && $template->get('doi-access') === 'free' && stripos($url, 'pdf') === false) {
                    report_forget("Existing The BMJ URL resulting from equivalent PMID and free DOI; dropping URL");
                    $template->forget($url_kind);
                } elseif ($template->get('doi-access') === 'free' && $template->get('url-status') === 'dead' && $url_kind === 'url') {
                    report_forget("Existing free DOI; dropping dead URL");
                    $template->forget($url_kind);
                } elseif (doi_active($template->get('doi')) &&
                            !preg_match(REGEXP_DOI_ISSN_ONLY, $template->get('doi')) &&
                            $url_kind !== '' &&
                            (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $template->get($url_kind)) !== $template->get($url_kind)) &&
                            $template->has_good_free_copy() &&
                            (stripos($template->get($url_kind), 'pdf') === false)) {
                    report_forget("Existing canonical URL resulting in equivalent free DOI/pmc; dropping URL");
                    $template->forget($url_kind);
                } elseif (stripos($url, 'pdf') === false && $template->get('doi-access') === 'free' && $template->has('pmc')) {
                    curl_setopt(self::$ch_dx, CURLOPT_URL, "https://dx.doi.org/" . doi_encode($doi));
                    $ch_return = bot_curl_exec(self::$ch_dx);
                    if (strlen($ch_return) > 50) { // Avoid bogus tiny pages
                        $redirectedUrl_doi = curl_getinfo(self::$ch_dx, CURLINFO_EFFECTIVE_URL); // Final URL
                        if (stripos($redirectedUrl_doi, 'cookie') !== false) {
                            break; // @codeCoverageIgnore
                        }
                        if (stripos($redirectedUrl_doi, 'denied') !== false) {
                            break; // @codeCoverageIgnore
                        }
                        $redirectedUrl_doi = self::url_simplify($redirectedUrl_doi);
                        $url_short = self::url_simplify($url);
                        if (preg_match('~^https?://.+/pii/?(S?\d{4}[^/]+)~i', $redirectedUrl_doi, $matches ) === 1 ) { // Grab PII numbers
                            $redirectedUrl_doi = $matches[1] ;  // @codeCoverageIgnore
                        }
                        if (stripos($url_short, $redirectedUrl_doi) !== false ||
                            stripos($redirectedUrl_doi, $url_short) !== false) {
                            report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
                            $template->forget($url_kind);
                        } else { // See if $url redirects
                            /** @psalm-taint-escape ssrf */
                            $the_url = $url;
                            curl_setopt(self::$ch_doi, CURLOPT_URL, $the_url);
                            $ch_return = bot_curl_exec(self::$ch_doi);
                            if (strlen($ch_return) > 60) {
                                $redirectedUrl_url = curl_getinfo(self::$ch_doi, CURLINFO_EFFECTIVE_URL);
                                $redirectedUrl_url = self::url_simplify($redirectedUrl_url);
                                if (stripos($redirectedUrl_url, $redirectedUrl_doi) !== false ||
                                                stripos($redirectedUrl_doi, $redirectedUrl_url) !== false) {
                                    report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
                                    $template->forget($url_kind);
                                }
                            }
                        }
                    }
                    unset($ch_return);
                }
            }
            $url = $template->get($url_kind);
            if ($url && !$template->profoundly_incomplete() && str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url) {
                if (!$template->blank_other_than_comments('pmc')) {
                    report_forget("Existing proxy URL resulting from equivalent PMC; dropping URL");
                    $template->forget($url_kind);
                }
            }
        }
    }

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

        $delay = max(min(100000*(1+self::$zotero_failures_count), 10), 0); // 0.10 seconds delay, with paranoid bounds checks
        usleep($delay);
        $zotero_response = bot_curl_exec(self::$zotero_ch);
        if ($zotero_response === '') {
            sleep(2); // @codeCoverageIgnore
            $zotero_response = bot_curl_exec(self::$zotero_ch); // @codeCoverageIgnore
        }
        if ($zotero_response === '') {
            // @codeCoverageIgnoreStart
            report_warning(curl_error(self::$zotero_ch) . "  For URL: " . echoable($url));
            if (strpos(curl_error(self::$zotero_ch), 'timed out after') !== false) {
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

        if (stripos($url, 'CITATION_BOT_PLACEHOLDER') !== false) {
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
        if (preg_match("~^https?://(?:www\.|m\.|)(?:" . $bad_url . ")~i", $url)) {
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
            report_action("Using Zotero translation server to retrieve details from URLs.");
            self::$zotero_announced = 0;
        } elseif (self::$zotero_announced === 2) {
            report_action("Using Zotero translation server to retrieve details from identifiers.");
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

        switch (trim($zotero_response)) {
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

        if (strpos($zotero_response, '502 Bad Gateway') !== false) {
            report_warning("Bad Gateway error for URL ". echoable($url));
            return;
        }
        if (strpos($zotero_response, '503 Service Temporarily Unavailable') !== false) {
            report_warning("Temporarily Unavailable error for URL " . echoable($url)); // @codeCoverageIgnore
            return;                           // @codeCoverageIgnore
        }
        if (strpos($zotero_response, '<title>Wikimedia Error</title>') !== false) {
            report_warning("Temporarily giving an error for URL " . echoable($url)); // @codeCoverageIgnore
            return;                           // @codeCoverageIgnore
        }
        $zotero_data = @json_decode($zotero_response, false);
        if (!isset($zotero_data)) {
            report_warning("Could not parse JSON for URL ". echoable($url) . ": " . $zotero_response);
            return;
        } elseif (!is_array($zotero_data)) {
            if (is_object($zotero_data)) {
                $zotero_data = (array) $zotero_data;
            } else {
                report_warning("JSON did not parse correctly for URL ". echoable($url) . ": " . $zotero_response);
                return;
            }
        }
        if (!isset($zotero_data[0])) {
            $result = $zotero_data;
        } else {
            $result = $zotero_data[0];
        }
        $result = (object) $result ;

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
            if (strpos($zotero_response, 'unknown_error') !== false) { // @codeCoverageIgnoreStart
                report_info("Did not get a title for URL ". echoable($url));
            } else {
                report_minor_error("Did not get a title for URL ". echoable($url) . ": " . $zotero_response); // Odd Error
            }
            return;  // @codeCoverageIgnoreEnd
        }
        if (substr(strtolower(trim($result->title)), 0, 9) === 'not found') {
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

        if (isset($result->publicationTitle) && substr($result->publicationTitle, -2) === " |") {
            $result->publicationTitle = substr($result->publicationTitle, 0, -2);
        }
        if (stripos($url, 'www.royal.uk') !== false || stripos($url, 'astanatimes.com') !== false) {
            unset($result->creators);  // @codeCoverageIgnore
            unset($result->author);   // @codeCoverageIgnore
        }

        if (stripos($url, 'theathletic.com') !== false) { // Returns NYT
            unset($result->publicationTitle);  // @codeCoverageIgnore
        }

        if (stripos($url, '/x.com') !== false || stripos($url, 'twitter.com') !== false) {
            $result->itemType = 'webpage';   // @codeCoverageIgnore
        }

        if (stripos($url, 'newrepublic.com') !== false) { // Bad data for all but first one
            unset($result->creators['1']);
            unset($result->author['1']);
        }

        if (stripos($url, 'flickr.') !== false) {
            $result->itemType = 'webpage';
            unset($result->publicationTitle); //Flickr is not a work
        }

        if (stripos($url, 'pressbooks.online.ucf.edu') !== false) {
            $result->itemType = 'webpage';
            unset($result->author); // They list themself
        }

        if (stripos($url, '.tumblr.com') !== false) { // Returns tumblr, and it is a sub-domain
            unset($result->publicationTitle);  // @codeCoverageIgnore
        }
        if (stripos($url, 'tumblr.com') !== false) {
            $result->itemType = 'webpage';  // @codeCoverageIgnore
        }
        if (stripos($url, 'tate.org.uk') !== false) {
            $result->itemType = 'webpage';
            unset($result->creators);
            unset($result->author);
        }
        
        // Reject if we find more than 5 or more than 10% of the characters are �. This means that character
        // set was not correct in Zotero and nothing is good.  We allow a couple of � for German umlauts that arer easily fixable by humans.
        // We also get a lot of % and $ if the encoding was something like iso-2022-jp and converted wrong
        $bad_count = substr_count($result->title, '�') + mb_substr_count($result->title, '$') + mb_substr_count($result->title, '%');
        $total_count = mb_strlen($result->title);
        if (isset($result->bookTitle)) {
            $bad_count += substr_count($result->bookTitle, '�') + mb_substr_count($result->bookTitle, '$') + mb_substr_count($result->bookTitle, '%');
            $total_count += mb_strlen($result->bookTitle);
        }
        if (($bad_count > 5) || ($total_count > 1 && (($bad_count/$total_count) > 0.1))) {
            report_info("Could parse unicode characters in " . echoable($url));
            return;
        }

        report_info("Retrieved info from " . echoable($url));
        // Verify that Zotero translation server did not think that this was a website and not a journal
        if (strtolower(substr(trim($result->title), -9)) === ' on jstor') {  // Not really "expanded", just add the title without " on jstor"
            $template->add_if_new('title', substr(trim($result->title), 0, -9)); // @codeCoverageIgnore
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
        if (isset($result->bookTitle) && strtolower($result->bookTitle) === 'undefined') {
            unset($result->bookTitle); // S2 without journals
        }
        if (isset($result->publicationTitle) && strtolower($result->publicationTitle) === 'undefined') {
            unset($result->publicationTitle); // S2 without journals
        }
        if (isset($result->bookTitle)) {
            foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
                if (str_i_same($result->bookTitle, $bad_title)) {
                    report_info("Received invalid book title data for URL " . echoable($url . ": " . $result->bookTitle));
                    return;
                }
            }
        }
        if (isset($result->title)) {
            foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
                if (str_i_same($result->title, $bad_title)) {
                    report_info("Received invalid title data for URL ". echoable($url . ": " . $result->title));
                    return;
                }
            }
        }
        if (isset($result->publicationTitle)) {
            foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
                if (str_i_same($result->publicationTitle, $bad_title)) {
                    report_info("Received invalid publication title data for URL ". echoable($url . ": " . $result->publicationTitle));
                    return;
                }
            }
            // Specific bad data that is correctable
            $tester = strtolower($result->publicationTitle);
            if ($tester === 'nationalpost') {
                $result->publicationTitle = 'National Post';
            } elseif ($tester === 'financialpost') {
                $result->publicationTitle = 'Financial Post';
            } elseif ($tester === 'bloomberg.com') {
                $result->publicationTitle = 'Bloomberg';
            } elseif ($tester === 'radiofreeeurope/radioliberty') {
                $result->publicationTitle = 'Radio Free Europe/Radio Liberty';
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
                    $result->DOI = trim($matches[1]);
                }
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
            }
            if (preg_match('~\stype:\s?([^\s]+)\s~i', ' ' . $result->extra . ' ', $matches)) { // [extra] => type: dataset has been seen in the wild
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
            }
            if (preg_match('~\sPMID: (\d+)\s+PMCID: PMC(\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
                $template->add_if_new('pmid', $matches[1]);
                $template->add_if_new('pmc', $matches[2]);
            }
            if (preg_match('~\sPMID: (\d+), (\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
                if ($matches[1] === $matches[2]) {
                    $template->add_if_new('pmid', $matches[1]);
                }
            }
            if (preg_match('~\sPMID: (\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
                $template->add_if_new('pmid', $matches[1]);
            }
            if (preg_match('~\sOCLC: (?:|ocn|ocm)(\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
                $template->add_if_new('oclc', $matches[1]);
            }
            if (preg_match('~\sOpen Library ID: OL(\d+M)\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
                $template->add_if_new('ol', $matches[1]);
            }

            // UNUSED stuff goes below

            if (preg_match('~\sFormat: PDF\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
            }
            if (preg_match('~\sIMDb ID: ((?:tt|co|nm)\d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
            }
            if (preg_match('~\s(original-date: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Google-Books-ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(ISSN: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Page Version ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Citation Key: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(number-of-pages: [ivx]+, \d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(number-of-pages: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Version: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(RSLID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(QID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(National Archives Identifier: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Catalog Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(BMCR ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(PubAg AGID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(IP-\d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\s(Accession Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~\sADS Bibcode: (\d{4}\S{15})\s~i', ' ' . $result->extra . ' ', $matches)) {
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
                $template->add_if_new('bibcode', $matches[1]);
            }
            if (preg_match('~\s(arXiv: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - only comes from arXiv DOIs
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra)); // @codeCoverageIgnore
            }
            if (preg_match('~\s(INIS Reference Number: \d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - https://inis.iaea.org
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra)); // @codeCoverageIgnore
            }
            if (preg_match('~\s(ERIC Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra)); // @codeCoverageIgnore
            }
            if (preg_match('~\s(\d+ cm\.)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - size of book
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra)); // @codeCoverageIgnore
            }
            // These go at end since it is unbound on end often with linefeeds and such
            if (preg_match('~submitted:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~event\-location:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it and it is long verbose
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Translated title:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~reviewed\-title:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Physical Description:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~BBK:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Place Manufactured: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Dimensions: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Category: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Credit: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Manufacturer: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~pl., cartes, errata.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Post URL:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~Reference Number:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            if (preg_match('~jurisdiction:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
                $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));      // @codeCoverageIgnore
            }
            $result->extra = trim($result->extra);
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
                if (stripos($url, 'jstor')) {
                    check_doi_for_jstor($template->get('doi'), $template);
                }
                if (!$template->profoundly_incomplete()) {
                    return;
                }
            }
        }

        if (isset($result->date)) {
            foreach (NO_DATE_WEBSITES as $bad_website ) {
                if (stripos($url, $bad_website) !== false) {
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
        if (str_i_same(substr((string) @$result->publicationTitle, 0, 4), 'http') ||
                str_i_same(substr((string) @$result->bookTitle, 0, 4), 'http') ||
                str_i_same(substr((string) @$result->title, 0, 4), 'http')) {
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

        if (strpos($url, 'biodiversitylibrary.org') !== false) {
            unset($result->publisher); // Not reliably set
        }
        if (isset($result->title) && $result->title === 'Cultural Advice' && strpos($url, 'edu.au') !== false) {
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
            if (isset($result->title)){
                $template->add_if_new('title', (string) $result->title);
            }
            if (isset($result->itemType) && ($result->itemType === 'book' || $result->itemType === 'bookSection')) {
                if (isset($result->publisher)) {
                    $template->add_if_new('publisher', (string) $result->publisher);
                }
            }
        }

        if (isset($result->issue)) {
            $template->add_if_new('issue', self::clean_volume((string) $result->issue));
        }
        if (isset($result->pages)) {
            $pos_pages = (string) $result->pages;
            if (preg_match('~\d~', $pos_pages) && !preg_match('~\d+\.\d+.\d+~', $pos_pages)) { // At least one number but not a dotted number from medRxiv
                $pos_pages = str_ireplace(['σελ.', 'σελ ', 'pages ', 'page ', 'pages:', 'page:', 'pages', 'page'], [' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '], $pos_pages);
                $pos_pages = trim($pos_pages);
                $pos_pages = str_ireplace(['  ', '  ', '  '], [' ', ' ', ' '], $pos_pages);
                $template->add_if_new('pages', $pos_pages);
            }
        }
        if (isset($result->itemType) && $result->itemType === 'newspaperArticle') {
            if (isset($result->publicationTitle)) {
                $new_title = (string) $result->publicationTitle;
                if (in_array(strtolower($new_title), WORKS_ARE_PUBLISHERS, true)) {
                    $template->add_if_new('publisher', $new_title);
                } elseif ($template->blank(WORK_ALIASES)) {
                    $template->add_if_new('work', $new_title);
                } else {
                    $use_it = false;
                    foreach (WORK_ALIASES as $work_type) {
                        $test_it = substr($template->get($work_type), -4);
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
                    (stripos((string) $result->publicationTitle, ' edition') === false)) { // Do not add if "journal" includes "edition"
                    if (str_replace(NON_JOURNALS, '', (string) $result->publicationTitle) === (string) $result->publicationTitle) {
                        if (str_ireplace(NON_JOURNAL_WEBSITES, '', $url) === $url || $template->wikiname() === 'cite journal') {
                            if (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $url) === $url && str_ireplace(JOURNAL_ARCHIVES_SITES, '', $url) === $url) {
                                if ($url !== '' && strpos($url, 'dx.doi.org') === FALSE && $url !== 'X') { // '' and 'X" are only in test suite
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
            $template->add_if_new('volume', self::clean_volume((string) $result->volume));
        }
        if (isset($result->date) && strlen((string) $result->date)>3) {
            $new_date = tidy_date((string) $result->date);
            if (stripos($url, 'indiatimes') !== false) { // "re-posted" website all at once
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
        if (isset($result->series) && stripos($url, '.acm.org')===false) {
            $template->add_if_new('series', (string) $result->series);
        }
        $i = 0;
        while (isset($result->author[$i])) {
            if (self::is_bad_author((string) @$result->author[$i][1])) {
                unset($result->author[$i][1]);
            }
            if (self::is_bad_author((string) @$result->author[$i][0])) {
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
                $template->validate_and_add('author' . (string) ($i+1), (string) @$result->author[$i][1], (string) @$result->author[$i][0],
                                                                isset($result->rights) ? (string) $result->rights : '', false);
            }
            $i++;
            if ($template->blank(['author' . (string) $i, 'first' . (string) $i, 'last' . (string) $i])) {
                break; // Break out if nothing added
            }
        }
        unset($i);

        if ((stripos($url, '/sfdb.org') !== false || stripos($url, '.sfdb.org') !== false) && $template->blank(WORK_ALIASES)) {
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
                            stripos($url . @$result->title . @$result->bookTitle . @$result->publicationTitle, 'review') === false &&
                            stripos($url, 'archive.org') === false && !preg_match('~^https?://[^/]*journal~', $url) &&
                            stripos($url, 'booklistonline') === false &&
                            stripos($url, 'catalogue.bnf') === false &&
                            stripos($url, 'finna.fi') === false &&
                            stripos($url, 'planetebd.com') === false &&
                            stripos($url, 'data.bnf.fr') === false &&
                            stripos($url, 'audible.com') === false &&
                            stripos($url, 'elonet.fi') === false
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
                    /** if ($template->wikiname() === 'cite web') {
                        $test_data = $template->get('work') . $template->get('website') .
                            $template->get('url') . $template->get('chapter-url') .
                            $template->get('title') . $template->get('encyclopedia') .
                            $template->get('encyclopædia') . $url;
                        if (str_ireplace(['.gov', 'encyclopedia', 'encyclopædia'], '', $test_data) === $test_data) {
                            $template->change_name_to('cite news');
                        }
                    } **/
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
                            if (self::is_bad_author((string) $result->creators[$i]->lastName)) {
                                $result->creators[$i]->lastName  ='';
                            }
                            if (self::is_bad_author((string) $result->creators[$i]->firstName)) {
                                $result->creators[$i]->firstName ='';
                            }
                            $template->validate_and_add($authorParam, (string) $result->creators[$i]->lastName, (string) $result->creators[$i]->firstName,
                            isset($result->rights) ? (string) $result->rights : '', false);
                                // Break out if nothing added
                            if ((strpos($authorParam, 'author') === 0) && $template->blank(['author' . (string) ($author_i), 'first' . (string) ($author_i), 'last' . (string) ($author_i)])) {
                                break;
                            }
                            if ((strpos($authorParam, 'editor') === 0) && $template->blank(['editor' . (string) ($editor_i)])) {
                                break;
                            }
                            if ((strpos($authorParam, 'translator') === 0) && $template->blank(['translator' . (string) ($translator_i)])) {
                                break;
                            }
                        }
                    }
                    $i++;
                }
            }
            if (stripos(trim($template->get('publisher')), 'Associated Press') !== false &&
                stripos($url, 'ap.org') === false  ) {
                if ($template->wikiname() === 'cite news') {
                    $template->rename('publisher', 'agency'); // special template parameter just for them
                }
                if (stripos(trim($template->get('author')), 'Associated Press') === 0) {
                    $template->forget('author'); // all too common
                }
            }
            if (stripos(trim($template->get('publisher')), 'Reuters') !== false &&
                stripos($url, 'reuters.org') === false ) {
                if ($template->wikiname() === 'cite news') {
                    $template->rename('publisher', 'agency'); // special template parameter just for them
                }
                if (stripos(trim($template->get('author')), 'Reuters') === 0) {
                    $template->forget('author'); // all too common
                }
            }
        }
        if ($template->wikiname() === 'cite web') {
            if (stripos($url, 'businesswire.com/news') !== false ||
                stripos($url, 'prnewswire.com/') !== false ||
                stripos($url, 'globenewswire.com/') !== false ||
                stripos($url, 'newswire.com/') !== false) {
                $template->change_name_to('cite press release');
            }
        }
        return;
    }

    public static function url_simplify(string $url): string {
        $url = str_replace('/action/captchaChallenge?redirectUri=', '', $url);
        $url = urldecode($url);
        // IEEE is annoying
        if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
        }
        $url .= '/';
        $url = str_replace(['/abstract/', '/full/', '/full+pdf/', '/pdf/', '/document/', '/html/', '/html+pdf/', '/abs/', '/epdf/', '/doi/', '/xprint/', '/print/', '.short', '.long', '.abstract', '.full', '///', '//'],
                                                ['/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/'], $url);
        $url = substr($url, 0, -1); // Remove the ending slash we added
        $url = (string) preg_split("~[\?\#]~", $url, 2)[0];
        return str_ireplace('https', 'http', $url);
    }

    public static function find_indentifiers_in_urls(Template $template, ?string $url_sent = null): bool {
        set_time_limit(120);
        if (is_null($url_sent)) {
            // Chapter URLs are generally better than URLs for the whole book.
            if ($template->has('url') && $template->has('chapterurl')) {
                return (bool) ((int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'chapterurl ') +
                                                (int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'url '));
            } elseif ($template->has('url') && $template->has('chapter-url')) {
                return (bool) ((int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'chapter-url ') +
                                                (int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'url '));
            } elseif ($template->has('url')) {
                $url = $template->get('url');
                $url_type = 'url';
            } elseif ($template->has('chapter-url')) {
                $url = $template->get('chapter-url');
                $url_type = 'chapter-url';
            } elseif ($template->has('chapterurl')) {
                $url = $template->get('chapterurl');
                $url_type = 'chapterurl';
            } elseif ($template->has('conference-url')) {
                $url = $template->get('conference-url');
                $url_type = 'conference-url';
            } elseif ($template->has('conferenceurl')) {
                $url = $template->get('conferenceurl');
                $url_type = 'conferenceurl';
            } elseif ($template->has('contribution-url')) {
                $url = $template->get('contribution-url');
                $url_type = 'contribution-url';
            } elseif ($template->has('contributionurl')) {
                $url = $template->get('contributionurl');
                $url_type = 'contributionurl';
            } elseif ($template->has('article-url')) {
                $url = $template->get('article-url');
                $url_type = 'article-url';
            } elseif ($template->has('website')) { // No URL, but a website
                $url = trim($template->get('website'));
                if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
                    $url = "h" . $url;
                }
                if (strtolower(substr( $url, 0, 4 )) !== "http" ) {
                    $url = "http://" . $url; // Try it with http
                }
                if (preg_match(REGEXP_IS_URL, $url) !== 1) {
                    return false;  // See https://mathiasbynens.be/demo/url-regex/ This regex is more exact than validator. We only spend time on this after quick and dirty check is passed
                }
                if (preg_match('~^https?://[^/]+/?$~', $url) === 1) {
                    return false; // Just a host name
                }
                $template->rename('website', 'url'); // Change name it first, so that parameters stay in same order
                $template->set('url', $url);
                $url_type = 'url';
                quietly('report_modification', "website is actually HTTP URL; converting to use url parameter.");
            } else {
                // If no URL or website, nothing to worth with.
                return false;
            }
        } elseif (preg_match('~^' . MAGIC_STRING_URLS . '(\S+) $~', $url_sent, $matches)) {
            $url_sent = null;
            $url_type = $matches[1];
            $url   = $template->get($matches[1]);
        } else {
            $url = $url_sent;
            $url_type = 'An invalid value';
        }

        if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
            $url = "h" . $url;
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Save it
            }
        }
        // Common ones that do not help
        if (strpos($url, 'books.google') !== false ||
                strpos($url, 'researchgate.net') !== false||
                strpos($url, 'academia.edu') !== false) {
            return false;
        }

        // Abstract only websites
        if (strpos($url, 'orbit.dtu.dk/en/publications') !== false) { // This file path only
            if (is_null($url_sent)) {
                if ($template->has('pmc')) {
                    $template->forget($url_type); // Remove it to make room for free-link
                } elseif ($template->has('doi') && $template->get('doi-access') === 'free') {
                    $template->forget($url_type); // Remove it to make room for free-link
                }
            }
                return false;
        }
        // IEEE
        if (strpos($url, 'ieeexplore') !== false) {
            if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
                $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Update URL with cleaner one
                }
            }
            if (preg_match('~^https?://ieeexplore\.ieee\.org(?:|\:80)/(?:|abstract/)document/(\d+)/?(?:|\?reload=true)$~', $url, $matches)) {
                $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Normalize to HTTPS and remove abstract and remove trailing slash etc
                }
            }
            if (preg_match('~^https?://ieeexplore\.ieee\.org.*/iel5/\d+/\d+/(\d+).pdf(?:|\?.*)$~', $url, $matches)) {
                $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Normalize
                }
            }
            if (preg_match('~^https://ieeexplore\.ieee\.org/document/0+(\d+)$~', $url, $matches)) {
                $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Trimming leading zeroes
                }
            }
        }

        // semanticscholar
        if (stripos($url, 'semanticscholar.org') !== false) {
            $s2cid = getS2CID($url);
            if ($s2cid === '') {
                return false;
            }
            if ($template->has('s2cid') && $s2cid !== $template->get('s2cid')) {
                report_warning('Existing URL does not match existing S2CID: ' .  echoable($template->get('s2cid')));
                return false;
            }
            if ($template->has('S2CID') && $s2cid !== $template->get('S2CID')) {
                report_warning('Existing URL does not match existing S2CID: ' .  echoable($template->get('S2CID')));
                return false;
            }
            $template->add_if_new('s2cid', $s2cid);
            if ($template->wikiname() !== 'cite web' || !$template->blank(['doi', 'pmc', 'pmid', 'journal'])) { // Avoid template errors
                if ($template->has('s2cid') && is_null($url_sent) && $template->blank(['archiveurl', 'archive-url'])) {
                    $template->forget($url_type);
                    return true;  // Time to clean up
                }
                if (is_null($url_sent) && stripos($url, 'pdf') === false) {
                    $template->forget($url_type);
                    return true;
                }
                if (is_null($url_sent) && $template->has_good_free_copy() && get_semanticscholar_license($s2cid) === false) {
                    report_warning('Removing un-licensed Semantic Scholar URL that was converted to S2CID parameter');
                    $template->forget($url_type);
                    return true;
                }
            }
            return true;
        }

        if (preg_match("~^(https?://.+\/.+)\?casa_token=.+$~", $url, $matches)) {
            $url = $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }

        if (stripos($url, 'jstor') !== false) {
            // Trim ?seq=1#page_scan_tab_contents off of jstor urls
            // We do this since not all jstor urls are recognized below
            if (preg_match("~^(https?://\S*jstor.org\S*)\?seq=1#[a-zA-Z_]+$~", $url, $matches)) {
                $url = $matches[1];
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Update URL with cleaner one
                }
            }
            if (preg_match("~^(https?://\S*jstor.org\S*)\?refreqid=~", $url, $matches)) {
                $url = $matches[1];
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Update URL with cleaner one
                }
            }
            if (preg_match("~^(https?://\S*jstor.org\S*)\?origin=~", $url, $matches)) {
                if (stripos($url, "accept") !== false) {
                    bot_debug_log("Accept Terms and Conditions JSTOR found : " . $url); // @codeCoverageIgnore
                } else {
                    $url = $matches[1];
                    if (is_null($url_sent)) {
                        $template->set($url_type, $url); // Update URL with cleaner one
                    }
                }
            }
            if (stripos($url, 'plants.jstor.org') !== false) {
                return false; # Plants database, not journal
            }
            // https://www.jstor.org.stuff/proxy/stuff/stable/10.2307/3347357 and such
            // Optional 0- at front.
            // DO NOT change www.jstor.org to www\.jstor\.org -- Many proxies use www-jstor-org
            if (preg_match('~^(https?://(?:0-www.|www.|)jstor.org)(?:\S*proxy\S*/|/)(?:stable|discover)/10.2307/(.+)$~i', $url, $matches)) {
                $url = $matches[1] . '/stable/' . $matches[2] ; // that is default. This also means we get jstor not doi
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Update URL with cleaner one.  Will probably call forget on it below
                }
            }
            // https://www.jstor.org.libweb.lib.utsa.edu/stable/3347357 and such
            // Optional 0- at front.
            // DO NOT change www.jstor.org to www\.jstor\.org -- Many proxies use www-jstor-org
            // https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10 and such
            if (preg_match('~^https?://(?:0-www.|www.|)jstor.org\.[^/]+/(?:stable|discover)/(.+)$~i', $url, $matches)) {
                $url = 'https://www.jstor.org/stable/' . $matches[1] ;
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Update URL with cleaner one
                }
            }
            // Remove junk from URLs
            while (preg_match('~^https?://www\.jstor\.org/stable/(.+)(?:&ved=|&usg=|%3Fseq%3D1|\?|#metadata_info_tab_contents)~i', $url, $matches)) {
                $url = 'https://www.jstor.org/stable/' . $matches[1] ;
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Update URL with cleaner one
                }
            }

            if (preg_match('~^https?://(?:www\.|)jstor\.org/stable/(?:pdf|pdfplus)/(.+)\.pdf$~i', $url, $matches) ||
                preg_match('~^https?://(?:www\.|)jstor\.org/tc/accept\?origin=(?:\%2F|/)stable(?:\%2F|/)pdf(?:\%2F|/)(\d{3,})\.pdf$~i', $url, $matches)) {
                if ($matches[1] === $template->get('jstor')) {
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                        }
                    }
                    return false;
                } elseif ($template->blank('jstor')) {
                    curl_setopt(self::$ch_jstor, CURLOPT_URL, 'https://www.jstor.org/citation/ris/' . $matches[1]);
                    $dat = bot_curl_exec(self::$ch_jstor);
                    if ($dat &&
                            stripos($dat, 'No RIS data found for') === false &&
                            stripos($dat, 'Block Reference') === false &&
                            stripos($dat, 'A problem occurred trying to deliver RIS data') === false &&
                            substr_count($dat, '-') > 3) { // It is actually a working JSTOR.  Not sure if all PDF links are done right
                        if (is_null($url_sent) && $template->has_good_free_copy()) {
                            $template->forget($url_type);
                        }
                        return $template->add_if_new('jstor', $matches[1]);
                    }
                    unset($dat);
                }
            }
            if ($template->has('jstor') && preg_match('~^https?://(?:www\.|)jstor\.org/(?:stable|discover)/(?:|pdf/)' . $template->get('jstor') . '(?:|\.pdf)$~i', $url)) {
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return false;
            }
        } // JSTOR
        if (preg_match('~^https?://(?:www\.|)archive\.org/detail/jstor\-(\d{5,})$~i', $url, $matches)) {
            $template->add_if_new('jstor', $matches[1]);
            if (is_null($url_sent)) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
            return false;
        }

        if (preg_match('~^https?(://(?:0-www\.|www\.|ucsb\.|)worldcat(?:libraries|)\.org.+)(?:\&referer=brief_results|\?referer=di&ht=edition|\?referer=brief_results|%26referer%3Dbrief_results|\?ht=edition&referer=di|\?referer=br&ht=edition|\/viewport)$~i', $url, $matches)) {
            $url = 'https' . $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }
        if (preg_match('~^https?(://(?:0-www\.|www\.|ucsb\.)worldcat(?:libraries|)\.org.+)/oclc/(\d+)$~i', $url, $matches)) {
            $url = 'https://www.worldcat.org/oclc/' . $matches[2];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }

        if (preg_match('~^https?://onlinelibrary\.wiley\.com/doi/(.+)/abstract\?(?:deniedAccessCustomise|userIsAuthenticated)~i', $url, $matches)) {
            $url = 'https://onlinelibrary.wiley.com/doi/' . $matches[1] . '/abstract';
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }
     
        if (preg_match('~^https?://(?:dx\.|)doi\.org/10\.1007/springerreference_(\d+)$~i', $url, $matches)) {
            $url = 'http://www.springerreference.com/index/doi/10.1007/springerreference_' . $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }

        if (preg_match("~^https?://(?:(?:dx\.|www\.|)doi\.org|doi\.library\.ubc\.ca)/([^\?]*)~i", $url, $match)) {
            if ($template->has('doi')) {
                $doi = $template->get('doi');
                if (str_i_same($doi, $match[1]) || str_i_same($doi, urldecode($match[1]))) {
                    if (is_null($url_sent) && $template->get('doi-access') === 'free') {
                        quietly('report_modification', "URL is hard-coded DOI; removing since we already have free DOI parameter");
                        $template->forget($url_type);
                    }
                    return false;
                }
                // The DOIs do not match
                if (is_null($url_sent)) {
                    report_warning('doi.org URL does not match existing DOI parameter, investigating...');
                }
                if ($doi !== $template->get3('doi')) {
                    return false;
                }
                if (doi_works($match[1]) && !doi_works($doi)) {
                    $template->set('doi', $match[1]);
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                        }
                    }
                    return true;
                }
                if (!doi_works($match[1]) && doi_works($doi)) {
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                        }
                    }
                    return false;
                }
                return false; // Both valid or both invalid (could be legit if chapter and book are different DOIs
            }
            if ($template->add_if_new('doi', urldecode($match[1]))) { // Will expand from DOI when added
                if (is_null($url_sent) && $template->has_good_free_copy()) {
                    quietly('report_modification', "URL is hard-coded DOI; converting to use DOI parameter.");
                    $template->forget($url_type);
                }
                return true;
            } else {
                return false; // "bad" doi?
            }
        }
        if (stripos($url, 'oxforddnb.com') !== false) {
            return false; // generally bad
        }
        $doi = extract_doi($url)[1];
        if ($doi) {
            if (bad_10_1093_doi($doi)) {
                return false;
            }
            $old_jstor = $template->get('jstor');
            if (stripos($url, 'jstor')) {
                check_doi_for_jstor($doi, $template);
            }
            if (is_null($url_sent) && $old_jstor !== $template->get('jstor') && stripos($url, 'pdf') === false) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
            $template->tidy_parameter('doi'); // Sanitize DOI before comparing
            if ($template->has('doi') && stripos($doi, $template->get('doi')) === 0) { // DOIs are case-insensitive
                if (doi_works($doi) && is_null($url_sent) && strpos(strtolower($url), ".pdf") === false && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
                    if ($template->has_good_free_copy()) {
                        report_forget("Recognized existing DOI in URL; dropping URL");
                        $template->forget($url_type);
                    }
                }
                return false;  // URL matched existing DOI, so we did not use it
            }
            if ($template->add_if_new('doi', $doi)) {
                if (doi_active($doi)) {
                    if (is_null($url_sent)) {
                        if (mb_strpos(strtolower($url), ".pdf") === false && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
                            if ($template->has_good_free_copy()) {
                                report_forget("Recognized DOI in URL; dropping URL");
                                $template->forget($url_type);
                            }
                        } else {
                            report_info("Recognized DOI in URL.  Leaving *.pdf URL.");
                        }
                    }
                } else {
                    $template->mark_inactive_doi();
                }
                return true; // Added new DOI
            }
            return false; // Did not add it
        } elseif ($template->has('doi')) { // Did not find a doi, perhaps we were wrong
            $template->tidy_parameter('doi'); // Sanitize DOI before comparing
            $doi = $template->get('doi');
            if (stripos($url, $doi) !== false) { // DOIs are case-insensitive
                if (doi_works($doi) && is_null($url_sent) && strpos(strtolower($url), ".pdf") === false && not_bad_10_1093_doi($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
                    if ($template->has_good_free_copy()) {
                        report_forget("Recognized the existing DOI in URL; dropping URL");
                        $template->forget($url_type);
                    }
                }
                return false;  // URL matched existing DOI, so we did not use it
            }
        }

        // JSTOR

        if (stripos($url, "jstor.org") !== false) {
            $sici_pos = stripos($url, "sici");
            if ($sici_pos) { // Outdated url style
                $template->use_sici(); // Grab what we can.  We do not want this URL incorrectly parsed below, or even waste time trying.
                return false;
            }
            if (preg_match("~^/(?:\w+/)*(\d{5,})[^\d%\-]*(?:\?|$)~", substr($url, (int) stripos($url, 'jstor.org') + 9), $match) ||
                                preg_match("~^https?://(?:www\.)?jstor\.org\S+(?:stable|discovery)/(?:10\.7591/|)(\d{5,}|(?:j|J|histirel|jeductechsoci|saoa|newyorkhist)\.[a-zA-Z0-9\.]+)$~", $url, $match)) {
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                if ($template->has('jstor')) {
                    quietly('report_inaction', "Not using redundant URL (jstor parameter set)");
                } else {
                    quietly('report_modification', "Converting URL to JSTOR parameter " . jstor_link(urldecode($match[1])));
                    $template->set('jstor', urldecode($match[1]));
                }
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                return true;
            } else {
                return false; // Jstor URL yielded nothing
            }
        } else {
            if (preg_match(REGEXP_BIBCODE, urldecode($url), $bibcode)) {
                if ($template->blank('bibcode')) {
                    quietly('report_modification', "Converting url to bibcode parameter");
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                        }
                    }
                    return $template->add_if_new('bibcode', urldecode($bibcode[1]));
                } elseif (is_null($url_sent) && urldecode($bibcode[1]) === $template->get('bibcode')) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
            } elseif (stripos($url, '.nih.gov') !== false) {

                if (preg_match("~^https?://(?:www\.|)pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d{4,})"
                                                . "|^https?://(?:www\.|pmc\.|)ncbi\.nlm\.nih\.gov/(?:m/|labs/|)pmc/articles/(?:PMC|instance)?(\d{4,})"
                                                . "|^https?://pmc\.ncbi\.nlm\.nih\.gov/(?:m/|labs/|)articles/(?:PMC)?(\d{4,})~i", $url, $match)) {
                    if (preg_match("~\?term~i", $url)) {  // ALWAYS ADD new @$mathch[] below
                        return false; // A search such as https://www.ncbi.nlm.nih.gov/pmc/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
                    }
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite journal');
                    }
                    if ($template->blank('pmc')) {
                        quietly('report_modification', "Converting URL to PMC parameter");
                    }
                    $new_pmc = @$match[1] . @$match[2] . @$match[3];
                    if ($new_pmc === '') {
                        bot_debug_log("PMC oops");
                        return false;
                    }
                    if (is_null($url_sent)) {
                        if (stripos($url, ".pdf") !== false) {
                            $test_url = "https://www.ncbi.nlm.nih.gov/pmc/articles/PMC" . $new_pmc . "/";
                            curl_setopt(self::$ch_pmc, CURLOPT_URL, $test_url);
                            $the_pmc_body = bot_curl_exec(self::$ch_pmc);
                            $httpCode = (int) curl_getinfo(self::$ch_pmc, CURLINFO_HTTP_CODE);
                            if ($httpCode > 399 || $httpCode === 0 || strpos($the_pmc_body, 'Administrative content — journal masthead, notices, indexes, etc - PMC') !== false) { // Some PMCs do NOT resolve. So leave URL
                                return $template->add_if_new('pmc', $new_pmc);
                            }
                        }
                        if (stripos(str_replace("printable", "", $url), "table") === false) {
                            $template->forget($url_type); // This is the same as PMC auto-link
                        }
                    }
                    return $template->add_if_new('pmc', $new_pmc);

                } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?\?term=(\d+)$~', $url, $match)) {
                    $pos_pmid = $match[1];
                    $old_pmid = $template->get('pmid');
                    if ($old_pmid === '' || ($old_pmid === $pos_pmid)) {
                        $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $pos_pmid .'/');
                        $template->add_if_new('pmid', $pos_pmid);
                        return true;
                    } else {
                        report_warning(echoable($url) . ' does not match PMID of ' . echoable($old_pmid));
                    }
                    return false;
                } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?\?term=.*$~', $url) && ($template->has('pmid') || $template->has('pmc'))) {
                    report_info('Dropped non-specific pubmed search URL, since PMID is present');
                    $template->forget($url_type);
                    return false;
                } elseif (preg_match("~^https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?"
                . "(?:pubmed/|"
                . "/eutils/elink\.fcgi\S+dbfrom=pubmed\S+/|"
                . "entrez/query\.fcgi\S+db=pubmed\S+?|"
                . "pmc/articles/pmid/)"
                . ".*?=?(\d{4,})~i", $url, $match)||
                        preg_match("~^https?://(?:pubmed|www)\.ncbi\.nlm\.nih\.gov/(?:|entrez/eutils/elink.fcgi\?dbfrom=pubmed(?:|\&tool=sumsearch.org/cite)\&retmode=ref\&cmd=prlinks\&id=)(\d{4,})/?(?:|#.+|-.+|\?.+)$~", $url, $match)
                    ) {
                    if (preg_match("~\?term~i", $url) && !preg_match("~pubmed\.ncbi\.nlm\.nih\.gov/\d{4,}/\?from_term=~", $url)) {
                        if (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?(\?term=.*)$~', $url, $matches)) {
                            $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $matches[1]);
                        }
                        return false; // A search such as https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
                    }
                    if ($template->blank('pmid')) {
                        quietly('report_modification', "Converting URL to PMID parameter");
                    }
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                        }
                    }
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite journal');
                    }
                    return $template->add_if_new('pmid', $match[1]);

                } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/entrez/eutils/elink.fcgi\?.+tool=sumsearch\.org.+id=(\d+)$~', $url, $match)) {
                    if ($url_sent) {
                        return false;  // Many do not work
                    }
                    if ($template->blank(['doi', 'pmc'])) {
                        return false;  // This is a redirect to the publisher, not pubmed
                    }
                    if ($match[1] === $template->get('pmc')) {
                            $template->forget($url_type); // Same as PMC-auto-link
                    } elseif ($match[1] === $template->get('pmid')) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                        } else {
                            $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $match[1]);
                        }
                    }
                    return false;
                } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?(\?term=.*)$~', $url, $matches)) {
                    $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $matches[1]);
                    return false;
                } elseif (preg_match('~^(https://pubmed\.ncbi\.nlm\.nih\.gov/\d+)/#:~', $url, $matches)) {
                    $template->set($url_type, $matches[1]);
                    return false;
                }

            } elseif (stripos($url, 'europepmc.org') !== false) {
                if (preg_match("~^https?://(?:www\.|)europepmc\.org/articles?/pmc/?(\d{4,})~i", $url, $match) ||
                        preg_match("~^https?://(?:www\.|)europepmc\.org/scanned\?pageindex=(?:\d+)\&articles=pmc(\d{4,})~i", $url, $match)) {
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite journal');
                    }
                    if ($template->blank('pmc')) {
                        quietly('report_modification', "Converting Europe URL to PMC parameter");
                    }
                    if (is_null($url_sent) && stripos($url, ".pdf") === false) {
                        $template->forget($url_type); // This is same as PMC-auto-link
                    }
                    return $template->add_if_new('pmc', $match[1]);
                } elseif (preg_match("~^https?://(?:www\.|)europepmc\.org/(?:abstract|articles?)/med/(\d{4,})~i", $url, $match)) {
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite journal');
                    }
                    if ($template->blank('pmid')) {
                        quietly('report_modification', "Converting Europe URL to PMID parameter");
                    }
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                        }
                    }
                    return $template->add_if_new('pmid', $match[1]);
                }
                return false;
            } elseif (stripos($url, 'pubmedcentralcanada.ca') !== false) {
                if (preg_match("~^https?://(?:www\.|)pubmedcentralcanada\.ca/pmcc/articles/PMC(\d{4,})(?:|/.*)$~i", $url, $match)) {
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                if ($template->blank('pmc')) {
                    quietly('report_modification', "Converting Canadian URL to PMC parameter");
                }
                if (is_null($url_sent)) {
                    $template->forget($url_type);  // Always do this conversion, since website is gone!
                }
                return $template->add_if_new('pmc', $match[1]);
                }
                return false;
            } elseif (stripos($url, 'citeseerx') !== false) {
                if (preg_match("~^https?://citeseerx\.ist\.psu\.edu/viewdoc/(?:summary|download)(?:\;jsessionid=[^\?]+|)\?doi=([0-9.]*)(?:&.+)?~", $url, $match)) {
                    if ($template->blank('citeseerx')) {
                        quietly('report_modification', "URL is hard-coded citeseerx; converting to use citeseerx parameter.");
                    }
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                            if ($template->wikiname() === 'cite web') {
                                $template->change_name_to('cite journal');
                            }
                        }
                    }
                    return $template->add_if_new('citeseerx', urldecode($match[1])); // We cannot parse these at this time
                }
                return false;

            } elseif (stripos($url, 'arxiv') !== false) {
                if (preg_match("~\barxiv\.org/.*(?:pdf|abs|ftp/arxiv/papers/\d{4})/(.+?)(?:\.pdf)?$~i", $url, $match)) {
                    /* ARXIV
                    * See https://arxiv.org/help/arxiv_identifier for identifier formats
                    */
                    if (preg_match("~[A-z\-\.]+/\d{7}~", $match[1], $arxiv_id) // pre-2007
                            || preg_match("~\d{4}\.\d{4,5}(?:v\d+)?~", $match[1], $arxiv_id) // post-2007
                            ) {
                        quietly('report_modification', "Converting URL to arXiv parameter");
                        $ret = $template->add_if_new('arxiv', $arxiv_id[0]); // Have to add before forget to get cite type right
                        if (is_null($url_sent)) {
                            if ($template->has_good_free_copy() || $template->has('arxiv') || $template->has('eprint')) {
                                $template->forget($url_type);
                            }
                        }
                        return $ret;
                    }
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite arxiv');
                    }
                }
                return false;

            } elseif (preg_match("~^https?://(?:www\.|)amazon(?P<domain>\.[\w\.]{1,7})/.*dp/(?P<id>\d+X?)~i", $url, $match)) {

                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite book');
                }
                if ($match['domain'] === ".com") {
                    if (is_null($url_sent)) {
                        $template->forget($url_type);
                        if (stripos($template->get('publisher'), 'amazon') !== false) {
                            $template->forget('publisher');
                        }
                    }
                    if ($template->blank('asin')) {
                        quietly('report_modification', "Converting URL to ASIN parameter");
                        return $template->add_if_new('asin', $match['id']);
                    }
                } else {
                    if ($template->has('isbn')) { // Already have ISBN
                        quietly('report_inaction', "Not converting ASIN URL: redundant to existing ISBN.");
                    } else {
                        if ($template->blank('id')) { // TODO - deal with when already does and does not have {{ASIN}}
                            quietly('report_modification', "Converting URL to ASIN template");
                            $template->set('id', $template->get('id') . " {{ASIN|{$match['id']}|country=" . str_replace([".co.", ".com.", "."], "", $match['domain']) . "}}");
                        } else {
                            return false;  // do not continue and delete it, because of TODO above
                        }
                    }
                    if (is_null($url_sent)) {
                        $template->forget($url_type); // will forget accessdate too
                        if (stripos($template->get('publisher'), 'amazon') !== false) {
                            $template->forget('publisher');
                        }
                    }
                }
            } elseif (stripos($url, 'handle') !== false || stripos($url, 'persistentId=hdl:') !== false) {
                // Special case of hdl.handle.net/123/456
                if (preg_match('~^https?://hdl\.handle\.net/(\d{2,}.*/.+)$~', $url, $matches)) {
                    $url = 'https://hdl.handle.net/handle/' . $matches[1];
                }
                // Hostname
                $handle1 = false;
                foreach (HANDLES_HOSTS as $hosts) {
                    if (preg_match('~^https?://' . str_replace('.', '\.', $hosts) . '(/.+)$~', $url, $matches)) {
                        $handle1 = $matches[1];
                        break;
                    }
                }
                if ($handle1 === false) {
                    return false;
                }
                // file path
                $handle = false;
                foreach (HANDLES_PATHS as $handle_path) {
                    if (preg_match('~^' . $handle_path . '(.+)$~', $handle1, $matches)) {
                        $handle = $matches[1];
                        break;
                    }
                }
                if ($handle === false) {
                    return false;
                }
                // Trim off session stuff - urlappend seems to be used for page numbers and such
                $handle = str_ireplace('%3B', ';', $handle);
                while (preg_match('~^(.+)(?:/browse\?|;jsessionid|;sequence=|\?sequence=|&isAllowed=|&origin=|&rd=|\?value=|&type=|/browse-title|&submit_browse=|;ui=embed)~',
                                                     $handle, $matches)) {
                    $handle = $matches[1];
                }
                $handle = hdl_decode($handle);
                if (preg_match('~^(.+)\%3Bownerid=~', $handle, $matches) || preg_match('~^(.+)\;ownerid=~', $handle, $matches)) {
                    if (hdl_works($matches[1])) {
                        $handle = $matches[1];
                    }
                }
                // Verify that it works as a hdl - first with urlappend, since that is often page numbers
                if (preg_match('~^(.+)\?urlappend=~', $handle, $matches)) {  // should we shorten it?
                    if (hdl_works($handle) === false) {
                        $handle = $matches[1];  // @codeCoverageIgnore
                    } elseif (hdl_works($handle) === null && (hdl_works($matches[1]) === null || hdl_works($matches[1]) === false)) {
                        // Do nothing
                    } elseif (hdl_works($handle) === null) {
                        $handle = $matches[1]; // @codeCoverageIgnore
                    } else { // Both work
                        $long = hdl_works($handle);
                        $short = hdl_works($matches[1]);
                        if ($long === $short) { // urlappend does nothing
                            $handle = $matches[1]; // @codeCoverageIgnore
                        }
                    }
                }
                while (preg_match('~^(.+)/$~', $handle, $matches)) { // Trailing slash
                    $handle = $matches[1];
                }
                while (preg_match('~^/(.+)$~', $handle, $matches)) { // Leading slash
                    $handle = $matches[1];
                }
                // Safety check
                if (strlen($handle) < 6 || strpos($handle, '/') === false) {
                    return false;
                }
                if (strpos($handle, '123456789') === 0) {
                    return false;
                }

                $the_question = strpos($handle, '?');
                if ($the_question !== false) {
                    $handle = substr($handle, 0, $the_question) . '?' . str_replace('%3D', '=', urlencode(substr($handle, $the_question+1)));
                }

                // Verify that it works as a hdl
                $the_header_loc = hdl_works($handle);
                if ($the_header_loc === false || $the_header_loc === null) {
                    return false;
                }
                if ($template->blank('hdl')) {
                    quietly('report_modification', "Converting URL to HDL parameter");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                if (preg_match('~^([^/]+/[^/]+)/.*$~', $handle, $matches)  // Might be padded with stuff
                    && stripos($the_header_loc, $handle) === false
                    && stripos($the_header_loc, $matches[1]) !== false) {  // Too long ones almost never resolve, but we have seen at least one
                    $handle = $matches[1]; // @codeCoverageIgnore
                }
                return $template->add_if_new('hdl', $handle);
            } elseif (stripos($url, 'zbmath.org') !== false) {
                if (preg_match("~^https?://zbmath\.org/\?(?:format=complete&|)q=an:([0-9][0-9][0-9][0-9]\.[0-9][0-9][0-9][0-9][0-9])~i", $url, $match)) {
                    if ($template->blank('zbl')) {
                        quietly('report_modification', "Converting URL to ZBL parameter");
                    }
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                            if ($template->wikiname() === 'cite web') {
                                $template->change_name_to('cite journal');
                            }
                        }
                    }
                    return $template->add_if_new('zbl', $match[1]);
                }
                if (preg_match("~^https?://zbmath\.org/\?(?:format=complete&|)q=an:([0-9][0-9]\.[0-9][0-9][0-9][0-9]\.[0-9][0-9])~i", $url, $match)) {
                    if ($template->blank('jfm')) {
                        quietly('report_modification', "Converting URL to JFM parameter");
                    }
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                            if ($template->wikiname() === 'cite web') {
                                $template->change_name_to('cite journal');
                            }
                        }
                    }
                    return $template->add_if_new('jfm', $match[1]);
                }
                return false;
            } elseif (preg_match("~^https?://mathscinet\.ams\.org/mathscinet-getitem\?mr=([0-9]+)~i", $url, $match)) {
                if ($template->blank('mr')) {
                    quietly('report_modification', "Converting URL to MR parameter");
                }
                if (is_null($url_sent)) {
                    // SEP 2020 $template->forget($url_type); This points to a review and not the article
                }
                    return $template->add_if_new('mr', $match[1]);
            } elseif (preg_match("~^https?://papers\.ssrn\.com(?:/sol3/papers\.cfm\?abstract_id=|/abstract=)([0-9]+)~i", $url, $match)) {
                if ($template->blank('ssrn')) {
                    quietly('report_modification', "Converting URL to SSRN parameter");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                        if ($template->wikiname() === 'cite web') {
                            $template->change_name_to('cite journal');
                        }
                    }
                }
                return $template->add_if_new('ssrn', $match[1]);
            } elseif (stripos($url, 'osti.gov') !== false) {
                if (preg_match("~^https?://(?:www\.|)osti\.gov/(?:scitech/|)(?:biblio/|)(?:purl/|)([0-9]+)(?:\.pdf|)~i", $url, $match)) {
                    if ($template->blank('osti')) {
                        quietly('report_modification', "Converting URL to OSTI parameter");
                    }
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                            if ($template->wikiname() === 'cite web') {
                                $template->change_name_to('cite journal');
                            }
                        }
                    }
                    return $template->add_if_new('osti', $match[1]);
                }
                if (preg_match("~^https?://(?:www\.|)osti\.gov/energycitations/product\.biblio\.jsp\?osti_id=([0-9]+)~i", $url, $match)) {
                    if ($template->blank('osti')) {
                        quietly('report_modification', "Converting URL to OSTI parameter");
                    }
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy()) {
                            $template->forget($url_type);
                            if ($template->wikiname() === 'cite web') {
                                $template->change_name_to('cite journal');
                            }
                        }
                    }
                    return $template->add_if_new('osti', $match[1]);
                }
                return false;
            } elseif (stripos($url, 'worldcat.org') !== false) {
                if (preg_match("~^https?://(?:www\.|)worldcat\.org(?:/title/\S+)?/oclc/([0-9]+)~i", $url, $match)) {
                    if (strpos($url, 'edition') && ($template->wikiname() !== 'cite book')) {
                        report_warning('Not adding OCLC because is appears to be a weblink to a list of editions: ' . echoable($match[1]));
                        return false;
                    }
                    if ($template->blank('oclc')) {
                        quietly('report_modification', "Converting URL to OCLC parameter");
                    }
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite book');  // Better template choice
                    }
                    if (is_null($url_sent)) {
                        // SEP 2020 $template->forget($url_type);
                    }
                    return $template->add_if_new('oclc', $match[1]);
                } elseif (preg_match("~^https?://(?:www\.|)worldcat\.org/issn/(\d{4})(?:|-)(\d{3}[\dxX])$~i", $url, $match)) {
                    if ($template->blank('issn')) {
                        quietly('report_modification', "Converting URL to ISSN parameter");
                    }
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite journal'); // Better template choice
                    }
                    if (is_null($url_sent)) {
                        // SEP 2020 $template->forget($url_type);
                    }
                    return $template->add_if_new('issn_force', $match[1] . '-' . $match[2]);
                }
                return false;
            } elseif (preg_match("~^https?://lccn\.loc\.gov/(\d{4,})$~i", $url, $match)  &&
                                (stripos($template->parsed_text(), 'library') === false)) { // Sometimes it is web cite to Library of Congress
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite book');  // Better template choice
                }
                if ($template->blank('lccn')) {
                    quietly('report_modification', "Converting URL to LCCN parameter");
                }
                if (is_null($url_sent)) {
                    // SEP 2020 $template->forget($url_type);
                }
                return $template->add_if_new('lccn', $match[1]);
            } elseif (preg_match("~^https?://openlibrary\.org/books/OL/?(\d{4,}[WM])(?:|/.*)$~i", $url, $match)) { // We do W "work" and M "edition", but not A, which is author
                if ($template->blank('ol')) {
                    quietly('report_modification', "Converting URL to OL parameter");
                }
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite book');  // Better template choice
                }
                if (is_null($url_sent)) {
                    // SEP 2020 $template->forget($url_type);
                }
                return $template->add_if_new('ol', $match[1]);
            } elseif (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/(\d{4,})$~i", $url, $match) && $template->has('title') && $template->blank('id')) {
                if ($template->add_if_new('id', '{{ProQuest|' . $match[1] . '}}')) {
                    quietly('report_modification', 'Converting URL to ProQuest parameter');
                    if (is_null($url_sent)) {
                            if ($template->has_good_free_copy()) {
                                $template->forget($url_type);
                            }
                    }
                    return true;
                }
            } elseif (preg_match("~^https?:\/\/(?:www\.)?sciencedirect\.com\/book\/(978\d{10})(?:$|\/)~i", $url, $match) && $template->blank('isbn')) {
                if ($template->add_if_new('isbn', $match[1])) {
                    return true;
                }
            /// THIS MUST BE LAST
            } elseif (($template->has('chapterurl') || $template->has('chapter-url') || $template->has('url') || ($url_type === 'url') || ($url_type === 'chapterurl') || ($url_type === 'chapter-url')) && preg_match("~^https?://web\.archive\.org/web/\d{14}(?:|fw_)/(https?://.*)$~", $url, $match) && $template->blank(['archiveurl', 'archive-url'])) {
                if (is_null($url_sent)) {
                    quietly('report_modification', 'Extracting URL from archive');
                    $template->set($url_type, $match[1]);
                    $template->add_if_new('archive-url', $match[0]);
                    return false; // We really got nothing
                }
            }
            /// THIS MUST BE LAST
        }
        return false ;
    }

    // Sometimes zotero lists the last name as "published" and puts the whole name in the first place or other silliness
    private static function is_bad_author(string $aut): bool {
        if ($aut === '|') {
            return true;
        }
        $aut = strtolower($aut);
        if ($aut === 'published') {
            return true;
        }
        return false;
    }

    public static function get_doi_from_pii(string $pii): string {
        curl_setopt(self::$ch_pii, CURLOPT_URL, "https://api.elsevier.com/content/object/pii/" . $pii);
        $ch_return = (string) bot_curl_exec(self::$ch_pii);
        if (preg_match('~<prism:doi>(10\..+)<\/prism:doi>~', $ch_return, $match)) {
               return $match[1];
        }
        return '';
    }

    private static function clean_volume(string $volume): string {
        if (strpos($volume, "(") !== false) {
            return '';
        }
        if (preg_match('~[a-zA-Z]~', $volume) && (bool) strtotime($volume)) {
            return ''; // Do not add date
        }
        if (stripos($volume, "november") !== false) {
            return '';
        }
        if (stripos($volume, "nostradamus") !== false) {
            return '';
        }
        return trim(str_ireplace(['volumes', 'volume', 'vol.', 'vols.', 'vols',
         'vol', 'issues', 'issue', 'iss.', 'iss', 'numbers', 'number',
         'num.', 'num', 'nos.', 'nos', 'nr.', 'nr', '°', '№'], '', $volume));
    }

} // End of CLASS
