<?php

declare(strict_types=1);

require_once 'constants.php';     // @codeCoverageIgnore
require_once 'Template.php';      // @codeCoverageIgnore
require_once 'big_jobs.php';      // @codeCoverageIgnore

const MONTH_SEASONS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Winter', 'Spring', 'Summer', 'Fall', 'Autumn'];
const DAYS_OF_WEEKS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Mony', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'];
const TRY_ENCODE = ["windows-1255", "maccyrillic", "windows-1253", "windows-1256", "tis-620", "windows-874", "iso-8859-11", "big5", "windows-1250"];
const INSANE_ENCODE = ['utf-8-sig', 'x-user-defined'];
const SANE_ENCODE = ['utf-8', 'iso-8859-1', 'windows-1252', 'unicode', 'us-ascii', 'none', 'iso-8859-7', 'latin1', '8859-1', '8859-7'];
const DOI_BAD_ENDS = ['.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml', '.full'];
const DOI_BAD_ENDS2 = ['/abstract', '/full', '/pdf', '/epdf', '/asset/', '/summary', '/short', '/meta', '/html', '/'];

final class HandleCache {
    // Greatly speed-up by having one array of each kind and only look for hash keys, not values
    private const MAX_CACHE_SIZE = 100000;
    public const MAX_HDL_SIZE = 1024;

    /** @var array<bool> $cache_active */
    public static array $cache_active = [];             // DOI is in CrossRef, no claims if it still works.
    /** @var array<bool> $cache_inactive */
    public static array $cache_inactive = BAD_DOI_ARRAY;// DOI is not in CrossRef
    /** @var array<bool> $cache_good */
    public static array $cache_good = [];               // DOI works
    /** @var array<string> $cache_hdl_loc */
    public static array $cache_hdl_loc = [];            // Final HDL location URL
    /** @var array<bool> $cache_hdl_bad */
    public static array $cache_hdl_bad = BAD_DOI_ARRAY; // HDL/DOI does not resolve to anything
    /** @var array<bool> $cache_hdl_null */
    public static array $cache_hdl_null = [];           // HDL/DOI resolves to null

    public static function check_memory_use(): void {
        $usage = count(self::$cache_inactive) +
                        count(self::$cache_active) +
                        count(self::$cache_good) +
                        count(self::$cache_hdl_bad) +
                        10*count(self::$cache_hdl_loc) + // These include a path too
                        count(self::$cache_hdl_null);
        if ($usage > self::MAX_CACHE_SIZE) {
            self::free_memory();    // @codeCoverageIgnore
        }
    }
    public static function free_memory(): void {
        self::$cache_active = [];
        self::$cache_inactive = BAD_DOI_ARRAY;
        self::$cache_good = [];
        self::$cache_hdl_loc = [];
        self::$cache_hdl_bad = BAD_DOI_ARRAY;
        self::$cache_hdl_null = [];
        gc_collect_cycles();
    }
}

// ============================================= DOI functions ======================================
function doi_active(string $doi): ?bool { // Does not reflect if DOI works, but if CrossRef has data
    $doi = trim($doi);
    if (isset(HandleCache::$cache_active[$doi])) {
        return true;
    }
    if (isset(HandleCache::$cache_inactive[$doi])) {
        return false;
    }
    $works = is_doi_active($doi);
    if ($works === null) { // Temporary problem - do not cache
        return null; // @codeCoverageIgnore
    }
    if ($works === false) {
        HandleCache::$cache_inactive[$doi] = true;
        return false;
    }
    HandleCache::$cache_active[$doi] = true;
    return true;
}

function doi_works(string $doi): ?bool {
    $doi = trim($doi);
    if (TRUST_DOI_GOOD && isset(NULL_DOI_BUT_GOOD[$doi])) {
        return true;
    }
    if (isset(NULL_DOI_ANNOYING[$doi])) {
        return false;
    }
    if (!TRAVIS) {
        foreach (NULL_DOI_STARTS_BAD as $bad_start) {
            if (stripos($doi, $bad_start) === 0) {
                return false; // all gone
            }
        }
    }
    if (strlen($doi) > HandleCache::MAX_HDL_SIZE) {
        return null;   // @codeCoverageIgnore
    }
    if (isset(HandleCache::$cache_good[$doi])) {
        return true;
    }
    if (isset(HandleCache::$cache_hdl_bad[$doi])) {
        return false;
    }
    if (isset(HandleCache::$cache_hdl_null[$doi])) {
        return null;   // @codeCoverageIgnore
    }
    HandleCache::check_memory_use();

    $works = is_doi_works($doi);
    if ($works === null) {  // These are unexpected nulls
        HandleCache::$cache_hdl_null[$doi] = true;   // @codeCoverageIgnore
        return null;   // @codeCoverageIgnore
    }
    if ($works === false) {
        if (isset(NULL_DOI_BUT_GOOD[$doi])) {
            bot_debug_log('Got bad for good HDL: ' . echoable_doi($doi));
            return true; // We log these and see if they have changed
        }
        HandleCache::$cache_hdl_bad[$doi] = true;
        return false;
    }
    HandleCache::$cache_good[$doi] = true;
    if (isset(NULL_DOI_LIST[$doi])) {
        bot_debug_log('Got good for bad HDL: ' . echoable_doi($doi));
    }
    return true;
}

function is_doi_active(string $doi): ?bool {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, [
            CURLOPT_HEADER => "1",
            CURLOPT_NOBODY => "0",
            CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT,
        ]);
    }
    $doi = trim($doi);
    $url = "https://api.crossref.org/v1/works/" . doi_encode($doi) . "?mailto=".CROSSREFUSERNAME; // do not encode crossref email
    curl_setopt($ch, CURLOPT_URL, $url);
    $return = bot_curl_exec($ch);
    $header_length = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($return, 0, $header_length);
    $body = substr($return, $header_length);
    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($header === "" || ($response_code === 503) || ($response_code === 429)) {
        sleep(4);                                                             // @codeCoverageIgnoreStart
        if ($response_code === 429) {
            sleep(4);  // WE are getting blocked
        }
        $return = bot_curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $header_length = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($return, 0, $header_length);
        $body = substr($return, $header_length);                               // @codeCoverageIgnoreEnd
    }
    if ($response_code === 429) {  // WE are still getting blocked
        sleep(10);   // @codeCoverageIgnore
    }
    if ($header === "" || ($response_code === 503) || ($response_code === 429)) {
        return null;  // @codeCoverageIgnore
    }
    if ($body === 'Resource not found.'){
        return false;
    }
    if ($response_code === 200) {
        return true;
    }
    if ($response_code === 404) { // @codeCoverageIgnoreStart
        return false;
    }
    $err = "CrossRef server error loading headers for DOI " . echoable_doi($doi) . " : " . echoable((string) $response_code);
    bot_debug_log($err);
    report_warning($err);
    return null;                  // @codeCoverageIgnoreEnd
}

function throttle_dx (): void {
    static $last = 0.0;
    $min_time = 40000.0;
    $now = microtime(true);
    $left = (int) ($min_time - ($now - $last));
    if ($left > 0 && $left < $min_time) {
        usleep($left); // less than min_time is paranoia, but do not want an inifinite delay
    }
    $last = $now;
}

function throttle_archive (): void {
    static $last = 0.0;
    $min_time = 1000000.0; // One second
    $now = microtime(true);
    $left = (int) ($min_time - ($now - $last));
    if ($left > 0 && $left < $min_time) {
        usleep($left); // less than min_time is paranoia, but do not want an inifinite delay
    }
    $last = $now;
}

function is_doi_works(string $doi): ?bool {
    $doi = trim($doi);
    // And now some obvious fails
    if (strpos($doi, '/') === false){
        return false;
    }
    if (strpos($doi, 'CITATION_BOT_PLACEHOLDER') !== false) {
        return false;
    }
    if (preg_match('~^10\.1007/springerreference~', $doi)) {
        return false;
    }
    if (!preg_match('~^([^\/]+)\/~', $doi, $matches)) {
        return false;
    }
    if (isset(NULL_DOI_ANNOYING[$doi])) {
        return false;
    }
    if (preg_match('~^10\.4435\/BSPI\.~i', $doi)) {
        return false;  // TODO: old ones like 10.4435/BSPI.2018.11 are casinos, and new one like 10.4435/BSPI.2024.06 go to the main page
    }
    if (isset(NULL_DOI_BUT_GOOD[$doi])) {
        if (strpos($doi, '10.1353/') === 0) {
            return true; // TODO - muse is annoying
        } elseif (strpos($doi, '10.1175/') === 0) {
            return true; // TODO - American Meteorological Society is annoying
        }
    }

    $registrant = $matches[1];
    // TODO this will need updated over time.    See registrant_err_patterns on https://en.wikipedia.org/wiki/Module:Citation/CS1/Identifiers
    // 17 August 2024 version is last check
    if (strpos($registrant, '10.') === 0) { // We have to deal with valid handles in the DOI field - very rare, so only check actual DOIs
        $registrant = substr($registrant, 3);
        if (preg_match('~^[^1-3]\d\d\d\d\.\d\d*$~', $registrant) ||    // 5 digits with subcode (0xxxx, 40000+); accepts: 10000–39999
                preg_match('~^[^1-7]\d\d\d\d$~', $registrant) ||       // 5 digits without subcode (0xxxx, 60000+); accepts: 10000–69999
                preg_match('~^[^1-9]\d\d\d\.\d\d*$~', $registrant) ||  // 4 digits with subcode (0xxx); accepts: 1000–9999
                preg_match('~^[^1-9]\d\d\d$~', $registrant) ||         // 4 digits without subcode (0xxx); accepts: 1000–9999
                preg_match('~^\d\d\d\d\d\d+~', $registrant) ||         // 6 or more digits
                preg_match('~^\d\d?\d?$~', $registrant) ||             // less than 4 digits without subcode (3 digits with subcode is legitimate)
                preg_match('~^\d\d?\.[\d\.]+~', $registrant) ||        // 1 or 2 digits with subcode
                $registrant === '5555' ||                              // test registrant will never resolve
                preg_match('~[^\d\.]~', $registrant)) {                // any character that isn't a digit or a dot
            return false;
        }
    }
    throttle_dx();

    $url = "https://doi.org/" . doi_encode($doi);
    $headers_test = get_headers_array($url);
    if ($headers_test === false) {
        if (isset(NULL_DOI_LIST[$doi])) {
            return false;
        }
        foreach (NULL_DOI_STARTS_BAD as $bad_start) {
            if (stripos($doi, $bad_start) === 0) {
                return false; // all gone
            }
        }
        if (isset(NULL_DOI_BUT_GOOD[$doi])) {
            return true;     // @codeCoverageIgnoreStart
        }
        $headers_test = get_headers_array($url);
        bot_debug_log('Got null for HDL: ' . echoable_doi($doi));     // @codeCoverageIgnoreEnd
    }
    if ($headers_test === false) {
        $headers_test = get_headers_array($url);     // @codeCoverageIgnore
    }
    if ($headers_test === false) {  // most likely bad - note that null means do not add or remove doi-broken-date from pages
        return null;     // @codeCoverageIgnore
    }
    if (stripos($doi, '10.1126/scidip.') === 0) {
        if ((string) @$headers_test['1'] === 'HTTP/1.1 404 Forbidden') {  // https://doi.org/10.1126/scidip.ado5059
            unset($headers_test['1']); // @codeCoverageIgnore
        }
    }
    if (interpret_doi_header($headers_test, $doi) !== false) {
        return interpret_doi_header($headers_test, $doi);
    }
    // Got 404 - try again, since we cache this and add doi-broken-date to pages, we should be double sure
    $headers_test = get_headers_array($url);
    /** We trust previous failure, so fail and null are both false */
    if ($headers_test === false) {
        return false;
    }
    return (bool) interpret_doi_header($headers_test, $doi);
}

/** @param array<string|array<string>> $headers_test */
function interpret_doi_header(array $headers_test, string $doi): ?bool {
    if (empty($headers_test['Location']) && empty($headers_test['location'])) {
        return false; // leads nowhere
    }
    /** @psalm-suppress InvalidArrayOffset */
    $resp0 = (string) @$headers_test['0'];
    /** @psalm-suppress InvalidArrayOffset */
    $resp1 = (string) @$headers_test['1'];
    /** @psalm-suppress InvalidArrayOffset */
    $resp2 = (string) @$headers_test['2'];

    if (strpos($resp0, '302') !== false && strpos($resp1, '301') !== false && strpos($resp2, '404') !== false) {
        if (isset(NULL_DOI_LIST[$doi])) {
            return false;
        }
        if (isset(NULL_DOI_BUT_GOOD[$doi])) {
            return true;
        }
        bot_debug_log('Got weird stuff for HDL: ' . echoable_doi($doi));
        return null;
    }
    if (strpos($resp0, '302') !== false && strpos($resp1, '503') !== false && $resp2 === '') {
        if (isset(NULL_DOI_LIST[$doi])) {
            return false;
        }
        if (isset(NULL_DOI_BUT_GOOD[$doi])) {
            return true;
        }
        bot_debug_log('Got two bad hops for HDL: ' . echoable_doi($doi));
        return null;
    }
    if (stripos($resp0 . $resp1 . $resp2, '404 Not Found') !== false || stripos($resp0 . $resp1 . $resp2, 'HTTP/1.1 404') !== false) {
        return false; // Bad
    }
    if (stripos($resp0, '302 Found') !== false || stripos($resp0, 'HTTP/1.1 302') !== false) {
        return true;    // Good
    }
    if (stripos((string) @json_encode($headers_test), 'dtic.mil') !== false) { // grumpy
        return true;  // @codeCoverageIgnore
    }
    if (stripos($resp0, '301 Moved Permanently') !== false || stripos($resp0, 'HTTP/1.1 301') !== false) { // Could be DOI change or bad prefix
        if (stripos($resp1, '302 Found') !== false || stripos($resp1, 'HTTP/1.1 302') !== false) {
            return true;    // Good
        } elseif (stripos($resp1, '301 Moved Permanently') !== false || stripos($resp1, 'HTTP/1.1 301') !== false) {        // @codeCoverageIgnoreStart
            if (stripos($resp2, '200 OK') !== false || stripos($resp2, 'HTTP/1.1 200') !== false) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    report_minor_error("Unexpected response in is_doi_works " . echoable($resp0));
    return null; // @codeCoverageIgnoreEnd
}

/** @param array<string|array<string>> $headers_test */
function get_loc_from_hdl_header(array $headers_test): ?string {
    if (isset($headers_test['Location'][0]) && is_array(@$headers_test['Location'])) { // Should not be an array, but on rare occasions we get one
        return (string) $headers_test['Location'][0];    // @codeCoverageIgnore
    } elseif (isset($headers_test['location'][0]) && is_array(@$headers_test['location'])) {
        return (string) $headers_test['location'][0];    // @codeCoverageIgnore
    } elseif (isset($headers_test['location'])) {
        return (string) $headers_test['location'];
    } elseif (isset($headers_test['Location'])) {        // @codeCoverageIgnore
        return (string) $headers_test['Location'];       // @codeCoverageIgnore
    } else { // @codeCoverageIgnoreStart
        bot_debug_log("Got weird header from handle: " . echoable(print_r($headers_test, true)));    // Is this even possible
        return null;
    }                // @codeCoverageIgnoreEnd
}

/** @param array<string> $_ids
    @param array<Template> $templates */
function query_jstor_api(array $_ids, array &$templates): void {  // Pointer to save memory
    foreach ($templates as $template) {
        expand_by_jstor($template);
    }
}

function sanitize_doi(string $doi): string {
    if (substr($doi, -1) === '.') {
        $try_doi = substr($doi, 0, -1);
        if (doi_works($try_doi)) { // If it works without dot, then remove it
            $doi = $try_doi;
        } elseif (doi_works($try_doi . '.x')) { // Missing the very common ending .x
            $doi = $try_doi . '.x';
        } elseif (!doi_works($doi)) { // It does not work, so just remove it to remove wikipedia error.  It's messed up
            $doi = $try_doi;
        }
    }
    $doi = safe_preg_replace('~^https?://d?x?\.?doi\.org/~i', '', $doi); // Strip URL part if present
    $doi = safe_preg_replace('~^/?d?x?\.?doi\.org/~i', '', $doi);
    $doi = safe_preg_replace('~^doi:~i', '', $doi); // Strip doi: part if present
    $doi = str_replace("+", "%2B", $doi); // plus signs are valid DOI characters, but in URLs are "spaces"
    $doi = str_replace(HTML_ENCODE_DOI, HTML_DECODE_DOI, trim(urldecode($doi)));
    $pos = (int) strrpos($doi, '.');
    if ($pos) {
        $extension = (string) substr($doi, $pos);
        if (in_array(strtolower($extension), DOI_BAD_ENDS, true)) {
            $doi = (string) substr($doi, 0, $pos);
        }
    }
    $pos = (int) strrpos($doi, '#');
    if ($pos) {
        $extension = (string) substr($doi, $pos);
        if (strpos(strtolower($extension), '#page_scan_tab_contents') === 0) {
            $doi = (string) substr($doi, 0, $pos);
        }
    }
    $pos = (int) strrpos($doi, ';');
    if ($pos) {
        $extension = (string) substr($doi, $pos);
        if (strpos(strtolower($extension), ';jsessionid') === 0) {
            $doi = (string) substr($doi, 0, $pos);
        }
    }
    $pos = (int) strrpos($doi, '/');
    if ($pos) {
        $extension = (string) substr($doi, $pos);
        if (in_array(strtolower($extension), DOI_BAD_ENDS2, true)) {
            $doi = (string) substr($doi, 0, $pos);
        }
    }
    $new_doi = str_replace('//', '/', $doi);
    if ($new_doi !== $doi) {
        if (doi_works($new_doi) || !doi_works($doi)) {
            $doi = $new_doi; // Double slash DOIs do exist
        }
    }
    // And now for 10.1093 URLs
    // The add chapter/page stuff after the DOI in the URL and it looks like part of the DOI to us
    // Things like 10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-003 and 10.1093/acprof:oso/9780195304923.001.0001/acprof-9780195304923-chapter-7
    if (strpos($doi, '10.1093') === 0 && doi_works($doi) === false) {
        if (preg_match('~^(10\.1093/oxfordhb.+)(?:/oxfordhb.+)$~', $doi, $match) ||
                preg_match('~^(10\.1093/acprof.+)(?:/acprof.+)$~', $doi, $match) ||
                preg_match('~^(10\.1093/acref.+)(?:/acref.+)$~', $doi, $match) ||
                preg_match('~^(10\.1093/ref:odnb.+)(?:/odnb.+)$~', $doi, $match) ||
                preg_match('~^(10\.1093/ww.+)(?:/ww.+)$~', $doi, $match) ||
                preg_match('~^(10\.1093/anb.+)(?:/anb.+)$~', $doi, $match)) {
            $new_doi = $match[1];
            if (doi_works($new_doi)) {
                $doi = $new_doi;
            }
        }
    }
    // Clean up book DOIs
    if (!doi_works($doi) && preg_match('~^(10\.\d+\/9\d{12})(\-\d{1,3})(\/.+)$~', $doi, $matches)) {
        if (doi_works($matches[1] . $matches[2]) || doi_works($matches[1])) {
            $doi = $matches[1] . $matches[2];
        }
    }
   
    return $doi;
}

/* extract_doi
 * Returns an array containing:
 * 0 => text containing a DOI, possibly encoded, possibly with additional text
 * 1 => the decoded DOI
 */
/** @return array<string> */
function extract_doi(string $text): array {
    if (preg_match(
                "~(10\.\d{4}\d?(/|%2[fF])..([^\s\|\"\?&>]|&l?g?t;|<[^\s\|\"\?&]*>)+)~",
                $text, $match)) {
        $doi = $match[1];
        if (preg_match(
                    "~^(.*?)(/abstract|/e?pdf|/full|/figure|/default|</span>|[\s\|\"\?]|</).*+$~",
                    $doi, $new_match)) {
            $doi = $new_match[1];
        }
        $doi_candidate = sanitize_doi($doi);
        while (preg_match(REGEXP_DOI, $doi_candidate) && !doi_works($doi_candidate)) {
            $last_delimiter = 0;
            foreach (['/', '.', '#', '?'] as $delimiter) {
                $delimiter_position = (int) strrpos($doi_candidate, $delimiter);
                $last_delimiter = ($delimiter_position > $last_delimiter) ? $delimiter_position : $last_delimiter;
            }
            $doi_candidate = substr($doi_candidate, 0, $last_delimiter);
        }
        if (doi_works($doi_candidate)) {
            $doi = $doi_candidate;
        }
        if (!doi_works($doi) && !doi_works(sanitize_doi($doi))) { // Reject URLS like ...../25.10.2015/2137303/default.htm
            if (preg_match('~^10\.([12]\d{3})~', $doi, $new_match)) {
                if (preg_match("~[0-3][0-9]\.10\." . $new_match[1] . "~", $text)) {
                    return ['', ''];
                }
            }
        }
        return [$match[0], sanitize_doi($doi)];
    }
    return ['', ''];
}

// ============================================= String/Text functions ======================================
function wikify_external_text(string $title): string {
    $replacement = [];
    $placeholder = [];
    $title = safe_preg_replace_callback('~(?:\$\$)([^\$]+)(?:\$\$)~iu',
            static function (array $matches): string {
                return "<math>" . $matches[1] . "</math>";
            },
            $title);
    if (preg_match_all("~<(?:mml:)?math[^>]*>(.*?)</(?:mml:)?math>~", $title, $matches)) {
        $num_matches = count($matches[0]);
        for ($i = 0; $i < $num_matches; $i++) {
            $replacement[$i] = '<math>' .
                str_replace(array_keys(MML_TAGS), array_values(MML_TAGS),
                    str_replace(['<mml:', '</mml:'], ['<', '</'], $matches[1][$i]))
                . '</math>';
            $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i);
            // Need to use a placeholder to protect contents from URL-safening
            $title = str_replace($matches[0][$i], $placeholder[$i], $title);
        }
        $title = str_replace(['<mo stretchy="false">', "<mo stretchy='false'>"], '', $title);
    }
    if (mb_substr($title, -6) === "&nbsp;") {
        $title = mb_substr($title, 0, -6);
    }
    if (mb_substr($title, -10) === "&amp;nbsp;") {       
        $title = mb_substr($title, 0, -10);
    } 
    // Sometimes stuff is encoded more than once
    $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
    $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
    $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
    $title = safe_preg_replace("~\s+~", " ", $title);    // Remove all white spaces before
    if (mb_substr($title, -6) === "&nbsp;") {
        $title = mb_substr($title, 0, -6); // @codeCoverageIgnore
    }
    // Special code for ending periods
    while (mb_substr($title, -2) === "..") {
        $title = mb_substr($title, 0, -1);
    }
    if (mb_substr($title, -1) === ".") { // Ends with a period
        if (mb_substr_count($title, '.') === 1) { // Only one period
            $title = mb_substr($title, 0, -1);
        } elseif (mb_substr_count($title, ' ') === 0) { // No spaces at all and multiple periods
            /** do nothing */
        } else { // Multiple periods and at least one space
            $last_word_start = (int) mb_strrpos(' ' . $title, ' ');
            $last_word = mb_substr($title, $last_word_start);
            if (mb_substr_count($last_word, '.') === 1 && // Do not remove if something like D.C. or D. C.
                mb_substr($title, $last_word_start-2, 1) !== '.') {
                $title = mb_substr($title, 0, -1);
            }
        }
    }
    $title = safe_preg_replace('~[\*]$~', '', $title);
    $title = title_capitalization($title, true);

    $htmlBraces = ["&lt;", "&gt;"];
    $angleBraces = ["<", ">"];
    $title = str_ireplace($htmlBraces, $angleBraces, $title);

    $originalTags = ['<title>', '</title>', '</ title>', 'From the Cover: ', '<SCP>', '</SCP>', '</ SCP>', '<formula>', '</formula>', '<roman>', '</roman>', ];
    $wikiTags = ['', '', '', '', '', '', '', '', '', '', ''];
    $title = str_ireplace($originalTags, $wikiTags, $title);
    $originalTags = ['<inf>', '</inf>'];
    $wikiTags = ['<sub>', '</sub>'];
    $title = str_ireplace($originalTags, $wikiTags, $title);
    $originalTags = ['.<br>', '.</br>', '.</ br>', '.<p>', '.</p>', '.</ p>', '.<strong>', '.</strong>', '.</ strong>'];
    $wikiTags = ['. ','. ','. ','. ','. ','. ','. ','. ','. '];
    $title = str_ireplace($originalTags, $wikiTags, $title);
    $originalTags = ['<br>', '</br>', '</ br>', '<p>', '</p>', '</ p>', '<strong>', '</strong>', '</ strong>'];
    $wikiTags = ['. ','. ','. ','. ','. ','. ', ' ',' ',' '];
    $title = trim(str_ireplace($originalTags, $wikiTags, $title));
    if (preg_match("~^\. (.+)$~", $title, $matches)) {
        $title = trim($matches[1]);
    }
    if (preg_match("~^(.+)(\.\s+)\.$~s", $title, $matches)) {
        $title = trim($matches[1] . ".");
    }
    $title_orig = '';
    while ($title !== $title_orig) {
        $title_orig = $title;    // Might have to do more than once.     The following do not allow < within the inner match since the end tag is the same :-( and they might nest or who knows what
        $title = safe_preg_replace_callback('~(?:<Emphasis Type="Italic">)([^<]+)(?:</Emphasis>)~iu',
            static function (array $matches): string {
                return "''" . $matches[1] . "''";
            },
            $title);
        $title = safe_preg_replace_callback('~(?:<Emphasis Type="Bold">)([^<]+)(?:</Emphasis>)~iu',
            static function (array $matches): string {
                return "'''" . $matches[1] . "'''";
            },
            $title);
        $title = safe_preg_replace_callback('~(?:<em>)([^<]+)(?:</em>)~iu',
            static function (array $matches): string {
                return "''" . $matches[1] . "''";
            },
            $title);
        $title = safe_preg_replace_callback('~(?:<i>)([^<]+)(?:</i>)~iu',
            static function (array $matches): string {
                return "''" . $matches[1] . "''";
            },
            $title);
        $title = safe_preg_replace_callback('~(?:<italics>)([^<]+)(?:</italics>)~iu',
            static function (array $matches): string {
                return "''" . $matches[1] . "''";
            },
            $title);
    }

    if (mb_substr($title, -1) === '.') {
        $title = sanitize_string($title) . '.';
    } else {
        $title = sanitize_string($title);
    }

    $title = str_replace(['​'], [' '], $title); // Funky spaces

    $title = str_ireplace('<p class="HeadingRun \'\'In\'\'">', ' ', $title);

    $title = str_ireplace(['        ', '     ', '    '], [' ', ' ', ' '], $title);
    if (mb_strlen($title) === strlen($title)) {
        $title = trim($title, " \t\n\r\0\x0B\xc2\xa0");
    } else {
        $title = trim($title, " \t\n\r\0");
    }

    $num_replace = count($replacement);
    for ($i = 0; $i < $num_replace; $i++) {
        $title = str_ireplace($placeholder[$i], $replacement[$i], $title); // @phan-suppress-current-line PhanTypePossiblyInvalidDimOffset
    }

    foreach (['<msup>', '<msub>', '<mroot>', '<msubsup>', '<munderover>', '<mrow>', '<munder>', '<mtable>', '<mtr>', '<mtd>'] as $mathy) {
        if (strpos($title, $mathy) !== false) {
            return '<nowiki>' . $title . '</nowiki>';
        }
    }
    return $title;
}

function restore_italics (string $text): string {
    $text = trim(str_replace(['              ', '            ', '        ', '       ', '    '], [' ', ' ', ' ', ' ', ' '], $text));
    // <em> tags often go missing around species names in CrossRef
    /** $old = $text; */
    $text = str_replace(ITALICS_HARDCODE_IN, ITALICS_HARDCODE_OUT, $text); // Ones to always do, since they keep popping up in our logs
    $text = str_replace("xAzathioprine therapy for patients with systemic lupus erythematosus", "Azathioprine therapy for patients with systemic lupus erythematosus", $text); // Annoying stupid bad data
    $text = trim(str_replace(['              ', '            ', '        ', '       ', '    '], [' ', ' ', ' ', ' ', ' '], $text));
    while (preg_match('~([a-z])(' . ITALICS_LIST . ')([A-Z\-\?\:\.\)\(\,]|species|genus| in| the|$)~', $text, $matches)) {
        if (in_array($matches[3], [':', '.', '-', ','], true)) {
            $pad = "";
        } else {
            $pad = " ";
        }
        $text = str_replace($matches[0], $matches[1] . " ''" . $matches[2] . "''" . $pad . $matches[3], $text);
    }
    $text = trim(str_replace(['              ', '            ', '        ', '       ', '    '], [' ', ' ', ' ', ' ', ' '], $text));
    $padded = ' '. $text . ' ';
    if (str_replace(CAMEL_CASE, '', $padded) !== $padded) {
        return $text; // Words with capitals in the middle, but not the first character
    }
    $new = safe_preg_replace('~([a-z]+)([A-Z][a-z]+\b)~', "$1 ''$2''", $text);
    if ($new === $text) {
        return $text;
    }
    // Do not return $new, since we are wrong much more often here than wrong with new CrossRef Code
    bot_debug_log('restore_italics: ' . $text . '               SHOULD BE           ' . $new); // @codeCoverageIgnore
    return $text; // @codeCoverageIgnore
}

function sanitize_string(string $str): string {
    // ought only be applied to newly-found data.
    if ($str === '') {
        return '';
    }
    if (strtolower(trim($str)) === 'science (new york, n.y.)') {
        return 'Science';
    }
    if (preg_match('~^\[http.+\]$~', $str)) {
        return $str; // It is a link out
    }
    $replacement = [];
    $placeholder = [];
    $math_templates_present = preg_match_all("~<\s*math\s*>.*<\s*/\s*math\s*>~", $str, $math_hits);
    if ($math_templates_present) {
        $num_maths = count($math_hits[0]);
        for ($i = 0; $i < $num_maths; $i++) {
            $replacement[$i] = $math_hits[0][$i];
            $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i);
        }
        $str = str_replace($replacement, $placeholder, $str);
    }
    $dirty = ['[', ']', '|', '{', '}', " what�s "];
    $clean = ['&#91;', '&#93;', '&#124;', '&#123;', '&#125;', " what's "];
    $str = trim(str_replace($dirty, $clean, safe_preg_replace('~[;.,]+$~', '', $str)));
    if ($math_templates_present) {
        $str = str_replace($placeholder, $replacement, $str);
    }
    return $str;
}

function truncate_publisher(string $p): string {
    return safe_preg_replace("~\s+(group|inc|ltd|publishing)\.?\s*$~i", "", $p);
}

function str_remove_irrelevant_bits(string $str): string {
    if ($str === '') {
        return '';
    }
    $str = trim($str);
    $str = str_replace('�', 'X', $str);
    $str = safe_preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $str);     // Convert [[X]] wikilinks into X
    $str = safe_preg_replace(REGEXP_PIPED_WIKILINK, "$2", $str);     // Convert [[Y|X]] wikilinks into X
    $str = trim($str);
    $str = safe_preg_replace("~^the\s+~i", "", $str);    // Ignore leading "the" so "New York Times" == "The New York Times"
    $str = safe_preg_replace("~\s~u", ' ', $str);
    // punctuation
    $str = str_replace(['.', ',', ';', ': ', "…"], [' ', ' ', ' ', ' ', ' '], $str);
    $str = str_replace([':', '-', '&mdash;', '&ndash;', '—', '–'], ['', '', '', '', '', ''], $str);
    $str = str_replace(['       ', '    '], [' ', ' '], $str);
    $str = str_replace(" & ", " and ", $str);
    $str = str_replace(" / ", " and ", $str);
    $str = trim($str);
    $str = str_ireplace(['Proceedings', 'Proceeding', 'Symposium', 'Huffington ', 'the Journal of ', 'nytimes.com', '& ', '(Clifton, N.J.)', '(Clifton NJ)'],
                        ['Proc', 'Proc', 'Sym', 'Huff ', 'journal of ', 'New York Times', 'and ', '', ''], $str);
    $str = str_ireplace(['<sub>', '<sup>', '<i>', '<b>', '</sub>', '</sup>', '</i>', '</b>', '<p>', '</p>', '<title>', '</title>'], '', $str);
    $str = str_ireplace(['SpringerVerlag', 'Springer Verlag Springer', 'Springer Verlag', 'Springer Springer'],
                                            ['Springer',             'Springer',                                 'Springer',                'Springer'               ], $str);
    $str = straighten_quotes($str, true);
    $str = str_replace("′", "'", $str);
    $str = safe_preg_replace('~\(Incorporating .*\)$~i', '', $str);  // Physical Chemistry Chemical Physics (Incorporating Faraday Transactions)
    $str = safe_preg_replace('~\d+ Volume Set$~i', '', $str);    // Ullmann's Encyclopedia of Industrial Chemistry, 40 Volume Set
    $str = safe_preg_replace('~^Retracted~i', '', $str);
    $str = safe_preg_replace('~\d?\d? ?The ?sequence ?of ?\S+ ?has ?been ?deposited ?in ?the ?GenBank ?database ?under ?accession ?number ?\S+ ?\d?~i', '', $str);
    $str = safe_preg_replace('~(?:\:\.\,)? ?(?:an|the) official publication of the.+$~i', '', $str);
    $str = trim($str);
    return strip_diacritics($str);
}

// See also titles_are_similar()
function str_equivalent(string $str1, string $str2): bool {
    if (str_i_same(str_remove_irrelevant_bits($str1), str_remove_irrelevant_bits($str2))) {
        return true;
    }
    if (string_is_book_series($str1) && string_is_book_series($str2)) { // Both series, but not the same
        $str1 = trim(str_replace(COMPARE_SERIES_IN, COMPARE_SERIES_OUT, strtolower($str1)));
        $str2 = trim(str_replace(COMPARE_SERIES_IN, COMPARE_SERIES_OUT, strtolower($str2)));
        if ($str1 === $str2) {
            return true;
        }
    }
    return false;
}

// See also str_equivalent()
function titles_are_similar(string $title1, string $title2): bool {
    if (!titles_are_dissimilar($title1, $title2)) {
        return true;
    }
    // Try again but with funky stuff mapped out of existence
    $title1 = str_replace('�', '', str_replace(array_keys(MAP_DIACRITICS), '', $title1));
    $title2 = str_replace('�', '', str_replace(array_keys(MAP_DIACRITICS), '', $title2));
    if (!titles_are_dissimilar($title1, $title2)) {
        return true;
    }
    return false;
}

function de_wikify(string $string): string {
    return str_replace(["[", "]", "'''", "''", "&"], ["", "", "'", "'", ""], preg_replace(["~<[^>]*>~", "~\&[\w\d]{2,7};~", "~\[\[[^\|\]]*\|([^\]]*)\]\]~"], ["", "", "$1"], $string));
}

function titles_are_dissimilar(string $inTitle, string $dbTitle): bool {
        // Blow away junk from OLD stuff
    if (stripos($inTitle, 'CITATION_BOT_PLACEHOLDER_') !== false) {
        $possible = preg_replace("~# # # CITATION_BOT_PLACEHOLDER_[A-Z]+ \d+ # # #~isu", ' ', $inTitle);
        if ($possible !== null) {
                $inTitle = $possible;
        } else { // When PHP fails with unicode, try without it
            $inTitle = preg_replace("~# # # CITATION_BOT_PLACEHOLDER_[A-Z]+ \d+ # # #~i", ' ', $inTitle);  // @codeCoverageIgnore
            if ($inTitle === null) {     // @codeCoverageIgnore
                return true;             // @codeCoverageIgnore
            }
        }
    }
    // Strip diacritics before decode
    $inTitle = strip_diacritics($inTitle);
    $dbTitle = strip_diacritics($dbTitle);
    // always decode new data
    $dbTitle = titles_simple(htmlentities(html_entity_decode($dbTitle)));
    // old data both decoded and not
    $inTitle2 = titles_simple($inTitle);
    $inTitle = titles_simple(htmlentities(html_entity_decode($inTitle)));
    $dbTitle = strip_diacritics($dbTitle);
    $inTitle = strip_diacritics($inTitle);
    $inTitle2 = strip_diacritics($inTitle2);
    $dbTitle = mb_strtolower($dbTitle);
    $inTitle = mb_strtolower($inTitle);
    $inTitle2 = mb_strtolower($inTitle2);
    $drops = [" ", "<strong>", "</strong>", "<em>", "</em>", "&nbsp", "&ensp", "&emsp", "&thinsp", "&zwnj",
        "&#45", "&#8208", "&#700", "&#039", "&#022", "&", "'", ",", ".", ";", '"', "\n", "\r", "\t", "\v", "\e", "‐",
        "-", "ʼ", "`", "]", "[", "(", ")", ":", "′", "−",
    ];
    $inTitle = str_replace($drops, "", $inTitle);
    $inTitle2 = str_replace($drops, "", $inTitle2);
    $dbTitle = str_replace($drops, "", $dbTitle);
    // This will convert &delta into delta
    return ((strlen($inTitle) > 254 || strlen($dbTitle) > 254)
                ? (strlen($inTitle) !== strlen($dbTitle)
            || similar_text($inTitle, $dbTitle) / strlen($inTitle) < 0.98)
                : (levenshtein($inTitle, $dbTitle) > 3))
    &&
    ((strlen($inTitle2) > 254 || strlen($dbTitle) > 254)
                ? (strlen($inTitle2) !== strlen($dbTitle)
            || similar_text($inTitle2, $dbTitle) / strlen($inTitle2) < 0.98)
                : (levenshtein($inTitle2, $dbTitle) > 3));
}

function titles_simple(string $inTitle): string {
    // Failure leads to null or empty strings!!!!
    // Leading Chapter # -   Use callback to make sure there are a few characters after this
    $inTitle = safe_preg_replace_callback('~^(?:Chapter \d+ \- )(.....+)~iu',
            static function (array $matches): string {
                return $matches[1];
            }, trim($inTitle));
    // Chapter number at start
    $inTitle = safe_preg_replace('~^\[\d+\]\s*~iu', '', trim($inTitle));
    // Trailing "a review"
    $inTitle = safe_preg_replace('~(?:\: | |\:)a review$~iu', '', trim($inTitle));
    // Strip trailing Online
    $inTitle = safe_preg_replace('~ Online$~iu', '', $inTitle);
    // Strip trailing (Third Edition)
    $inTitle = safe_preg_replace('~\([^\s\(\)]+ Edition\)^~iu', '', $inTitle);
    // Strip leading International Symposium on
    $inTitle = safe_preg_replace('~^International Symposium on ~iu', '', $inTitle);
    // Strip leading the
    $inTitle = safe_preg_replace('~^The ~iu', '', $inTitle);
    // Strip trailing
    $inTitle = safe_preg_replace('~ A literature review$~iu', '', $inTitle);
    $inTitle = safe_preg_replace("~^Editorial: ~ui", "", $inTitle);
    $inTitle = safe_preg_replace("~^Brief communication: ~ui", "", $inTitle);
    // Reduce punctuation
    $inTitle = straighten_quotes(mb_strtolower($inTitle), true);
    $inTitle = safe_preg_replace("~(?: |‐|−|-|—|–|â€™|â€”|â€“)~u", "", $inTitle);
    $inTitle = str_replace(["\n", "\r", "\t", "&#8208;", ":", "&ndash;", "&mdash;", "&ndash", "&mdash"], "", $inTitle);
    // Retracted
    $inTitle = safe_preg_replace("~\[RETRACTED\]~ui", "", $inTitle);
    $inTitle = safe_preg_replace("~\(RETRACTED\)~ui", "", $inTitle);
    $inTitle = safe_preg_replace("~RETRACTED~ui", "", $inTitle);
    // Drop normal quotes
    $inTitle = str_replace(["'", '"'], "", $inTitle);
    // Strip trailing periods
    $inTitle = trim(rtrim($inTitle, '.'));
    // &
    $inTitle = str_replace(" & ", " and ", $inTitle);
    $inTitle = str_replace(" / ", " and ", $inTitle);
    // greek
    $inTitle = strip_diacritics($inTitle);
    return str_remove_irrelevant_bits($inTitle);
}

function strip_diacritics (string $input): string {
    return str_replace(array_keys(MAP_DIACRITICS), array_values(MAP_DIACRITICS), $input);
}

function straighten_quotes(string $str, bool $do_more): string { // (?<!\') and (?!\') means that it cannot have a single quote right before or after it
    // These Regex can die on Unicode because of backward looking
    if ($str === '') {
        return '';
    }
    $str = str_replace('Hawaiʻi', 'CITATION_BOT_PLACEHOLDER_HAWAII', $str);
    $str = str_replace('Ha‘apai', 'CITATION_BOT_PLACEHOLDER_HAAPAI', $str);
    $str = safe_preg_replace('~(?<!\')&#821[679];|&#39;|&#x201[89];|[\x{FF07}\x{2018}-\x{201B}`]|&[rl]s?[b]?quo;(?!\')~u', "'", $str);
    if((mb_strpos($str, '&rsaquo;') !== false && mb_strpos($str, '&[lsaquo;')    !== false) ||
            (mb_strpos($str, '\x{2039}') !== false && mb_strpos($str, '\x{203A}') !== false) ||
            (mb_strpos($str, '‹') !== false && mb_strpos($str, '›') !== false)) { // Only replace single angle quotes if some of both
            $str = safe_preg_replace('~&[lr]saquo;|[\x{2039}\x{203A}]|[‹›]~u', "'", $str);                      // Websites tiles: Jobs ›› Iowa ›› Cows ›› Ames
    }
    $str = safe_preg_replace('~&#822[013];|[\x{201C}-\x{201F}]|&[rlb][d]?quo;~u', '"', $str);
    if(in_array(WIKI_BASE, ENGLISH_WIKI, true) && (
        (mb_strpos($str, '&raquo;')  !== false && mb_strpos($str, '&laquo;')  !== false) ||
            (mb_strpos($str, '\x{00AB}') !== false && mb_strpos($str, '\x{00AB}') !== false) ||
            (mb_strpos($str, '«') !== false && mb_strpos($str, '»') !== false))) { // Only replace double angle quotes if some of both // Websites tiles: Jobs » Iowa » Cows » Ames
        if ($do_more){
            $str = safe_preg_replace('~&[lr]aquo;|[\x{00AB}\x{00BB}]|[«»]~u', '"', $str);
        } else { // Only outer funky quotes, not inner quotes
            if (preg_match('~^(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)~u', $str, $match1) &&
                preg_match('~(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)$~u', $str, $match2)
            ) {
                $count1 = substr_count($str, $match1[0]);
                $count2 = substr_count($str, $match2[0]);
                if ($match1[0] === $match2[0]) { // Avoid double counting
                    $count1 -= 1;
                    $count2 -= 1;
                }
                if ($count1 === 1 && $count2 === 1) {
                    $str = safe_preg_replace('~^(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)~u', '"', $str);
                    $str = safe_preg_replace('~(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)$~u', '"', $str);
                }
            }
        }
    }
    $str = str_ireplace('CITATION_BOT_PLACEHOLDER_HAAPAI', 'Ha‘apai', $str);
    return str_ireplace('CITATION_BOT_PLACEHOLDER_HAWAII', 'Hawaiʻi', $str);
}

// ============================================= Capitalization functions ======================================

function title_case(string $text): string {
    if (stripos($text, 'www.') !== false || stripos($text, 'www-') !== false || stripos($text, 'http://') !== false) {
        return $text; // Who knows - duplicate code below
    }
    return mb_convert_case($text, MB_CASE_TITLE, "UTF-8");
}

/** Returns a properly capitalized title.
 *          If $caps_after_punctuation is true (or there is an abundance of periods), it allows the
 *          letter after colons and other punctuation marks to remain capitalized.
 *          If not, it won't capitalize after : etc.
 */
function title_capitalization(string $in, bool $caps_after_punctuation): string {
    // Use 'straight quotes' per WP:MOS
    $new_case = straighten_quotes(trim($in), false);
    if (mb_substr($new_case, 0, 1) === "[" && mb_substr($new_case, -1) === "]") {
        return $new_case; // We ignore wikilinked names and URL linked since who knows what's going on there.
                                             // Changing case may break links (e.g. [[Journal YZ|J. YZ]] etc.)
    }

    if (stripos($new_case, 'www.') !== false || stripos($new_case, 'www-') !== false || stripos($new_case, 'http://') !== false) {
        return $new_case; // Who knows - duplicate code above
    }

    if ($new_case === mb_strtoupper($new_case)
            && mb_strlen(str_replace(["[", "]"], "", trim($in))) > 6
            ) {
        // ALL CAPS to Title Case
        $new_case = mb_convert_case($new_case, MB_CASE_TITLE, "UTF-8");
    }

    // Implicit acronyms
    $new_case = ' ' . $new_case . ' ';
    $new_case = safe_preg_replace_callback("~[^\w&][b-df-hj-np-tv-xz]{3,}(?=\W)~ui",
            static function (array $matches): string {  // Three or more consonants.  NOT Y
                return mb_strtoupper($matches[0]);
            },
            $new_case);
    $new_case = safe_preg_replace_callback("~[^\w&][aeiou]{3,}(?=\W)~ui",
            static function (array $matches): string {  // Three or more vowels.  NOT Y
                return mb_strtoupper($matches[0]);
            },
            $new_case);
    $new_case = mb_substr($new_case, 1, -1); // Remove added spaces

    $new_case = mb_substr(str_replace(UC_SMALL_WORDS, LC_SMALL_WORDS, " " . $new_case . " "), 1, -1);
    foreach(UC_SMALL_WORDS as $key=>$_value) {
        $upper = UC_SMALL_WORDS[$key];
        $lower = LC_SMALL_WORDS[$key];
        foreach ([': ', ', ', '. ', '; '] as $char) {
            $new_case = str_replace(mb_substr($upper, 0, -1) . $char, mb_substr($lower, 0, -1) . $char, $new_case);
        }
    }

    if ($caps_after_punctuation || (substr_count($in, '.') / strlen($in)) > .07) {
        // When there are lots of periods, then they probably mark abbreviations, not sentence ends
        // We should therefore capitalize after each punctuation character.
        $new_case = safe_preg_replace_callback("~[?.:!/]\s+[a-z]~u" /* Capitalize after punctuation */,
            static function (array $matches): string {
                return mb_strtoupper($matches[0]);
            },
            $new_case);
        $new_case = safe_preg_replace_callback("~(?<!<)/[a-z]~u" /* Capitalize after slash unless part of ending html tag */,
            static function (array $matches): string {
                return mb_strtoupper($matches[0]);
            },
            $new_case);
        // But not "Ann. Of...." which seems to be common in journal titles
        $new_case = str_replace("Ann. Of ", "Ann. of ", $new_case);
    }

    $new_case = safe_preg_replace_callback(
        "~ \([a-z]~u" /* uppercase after parenthesis */,
        static function (array $matches): string {
            return mb_strtoupper($matches[0]);
        },
        trim($new_case)
    );

    $new_case = safe_preg_replace_callback(
        "~\w{2}'[A-Z]\b~u" /* Lowercase after apostrophes */,
        static function (array $matches): string {
            return mb_strtolower($matches[0]);
        },
        trim($new_case)
    );
    /** French l'Words and d'Words */
    $new_case = safe_preg_replace_callback(
        "~(\s[LD][\'\x{00B4}])([a-zA-ZÀ-ÿ]+)~u",
        static function (array $matches): string {
            return mb_strtolower($matches[1]) . mb_ucfirst_force($matches[2]);
        },
        ' ' . $new_case
    );

    /** Italian dell'xxx words */
    $new_case = safe_preg_replace_callback(
        "~(\s)(Dell|Degli|Delle)([\'\x{00B4}][a-zA-ZÀ-ÿ]{3})~u",
        static function (array $matches): string {
            return $matches[1] . mb_strtolower($matches[2]) . $matches[3];
        },
        $new_case
    );

    $new_case = mb_ucfirst_bot(trim($new_case));

    // Solitary 'a' should be lowercase
    $new_case = safe_preg_replace("~(\w\s+)A(\s+\w)~u", "$1a$2", $new_case);
    // but not in "U S A"
    $new_case = trim(str_replace(" U S a ", " U S A ", ' ' . $new_case . ' '));

    // This should be capitalized
    $new_case = str_replace(['(new Series)', '(new series)'], ['(New Series)', '(New Series)'], $new_case);

    // Catch some specific epithets, which should be lowercase
    $new_case = safe_preg_replace_callback(
        "~(?:'')?(?P<taxon>\p{L}+\s+\p{L}+)(?:'')?\s+(?P<nova>(?:(?:gen\.? no?v?|sp\.? no?v?|no?v?\.? sp|no?v?\.? gen)\b[\.,\s]*)+)~ui" /* Species names to lowercase */,
        static function (array $matches): string {
            return "''" . mb_ucfirst_bot(mb_strtolower($matches['taxon'])) . "'' " . mb_strtolower($matches["nova"]);
        },
        $new_case);

    // "des" at end is "Des" for Design not german "The"
    if (mb_substr($new_case, -4, 4) === ' des') {
        $new_case = mb_substr($new_case, 0, -4)  . ' Des';
    }

    // Capitalization exceptions, e.g. Elife -> eLife
    $new_case = str_replace(UCFIRST_JOURNAL_ACRONYMS, JOURNAL_ACRONYMS, " " .    $new_case . " ");
    $new_case = mb_substr($new_case, 1, mb_strlen($new_case) - 2); // remove spaces, needed for matching in LC_SMALL_WORDS

    // Single letter at end should be capitalized    J Chem Phys E for example.  Obviously not the spanish word "e".
    if (mb_substr($new_case, -2, 1) === ' ') {
        $new_case = mb_strrev(mb_ucfirst_bot(mb_strrev($new_case)));
    }

    if ($new_case === 'Now and then') {
        $new_case = 'Now and Then'; // Odd journal name
    }

    // Trust existing "ITS", "its", ...
    $its_in = preg_match_all('~ its(?= )~iu', ' ' . trim($in) . ' ', $matches_in, PREG_OFFSET_CAPTURE);
    $new_case = trim($new_case);
    $its_out = preg_match_all('~ its(?= )~iu', ' ' . $new_case . ' ', $matches_out, PREG_OFFSET_CAPTURE);
    if ($its_in === $its_out && $its_in !== 0 && $its_in !== false) {
        $matches_in = $matches_in[0];
        $matches_out = $matches_out[0];
        foreach ($matches_in as $key => $_value) {
            if ($matches_in[$key][0] !== $matches_out[$key][0]  &&
                    $matches_in[$key][1] === $matches_out[$key][1]) {
                $new_case = substr_replace($new_case, trim($matches_in[$key][0]), $matches_out[$key][1], 3); // PREG_OFFSET_CAPTURE is ALWAYS in BYTES, even for unicode
            }
        }
    }
    // Trust existing "DOS", "dos", ...
    $its_in = preg_match_all('~ dos(?= )~iu', ' ' . trim($in) . ' ', $matches_in, PREG_OFFSET_CAPTURE);
    $new_case = trim($new_case);
    $its_out = preg_match_all('~ dos(?= )~iu', ' ' . $new_case . ' ', $matches_out, PREG_OFFSET_CAPTURE);
    if ($its_in === $its_out && $its_in !== 0 && $its_in !== false) {
        $matches_in = $matches_in[0];
        $matches_out = $matches_out[0];
        foreach ($matches_in as $key => $_value) {
            if ($matches_in[$key][0] !== $matches_out[$key][0]  &&
                    $matches_in[$key][1] === $matches_out[$key][1]) {
                $new_case = substr_replace($new_case, trim($matches_in[$key][0]), $matches_out[$key][1], 3); // PREG_OFFSET_CAPTURE is ALWAYS in BYTES, even for unicode
            }
        }
    }

    if (preg_match('~Series ([a-zA-Z] )(\&|and)( [a-zA-Z] )~', $new_case . ' ', $matches)) {
        $replace_me = 'Series ' . $matches[1] . $matches[2] . $matches[3];
        $replace = 'Series ' . strtoupper($matches[1]) . $matches[2] . strtoupper($matches[3]);
        $new_case = trim(str_replace($replace_me, $replace, $new_case . ' '));
    }

    // 42th, 33rd, 1st, ...
    if(preg_match('~\s\d+(?:st|nd|rd|th)[\s\,\;\:\.]~i', ' ' . $new_case . ' ', $matches)) {
        $replace_me = $matches[0];
        $replace = strtolower($matches[0]);
        $new_case = trim(str_replace($replace_me, $replace, ' ' .$new_case . ' '));
    }

    // Part XII: Roman numerals
    $new_case = safe_preg_replace_callback(
        "~ part ([xvil]+): ~iu",
        static function (array $matches): string {
            return " Part " . strtoupper($matches[1]) . ": ";
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~ part ([xvi]+) ~iu",
        static function (array $matches): string {
            return " Part " . strtoupper($matches[1]) . " ";
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~ (?:Ii|Iii|Iv|Vi|Vii|Vii|Ix)$~u",
        static function (array $matches): string {
            return strtoupper($matches[0]);
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~^(?:Ii|Iii|Iv|Vi|Vii|Vii|Ix):~u",
        static function (array $matches): string {
            return strtoupper($matches[0]);
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~ Proceedings ([a-z]) ~u",
        static function (array $matches): string {
            return ' Proceedings ' . strtoupper($matches[1]) . ' ';
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~ var\. ([A-Z])~u",
        static function (array $matches): string {
            return ' var. ' . strtolower($matches[1]);
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~([\–\- ])(PPM)([\.\,\:\; ])~u",
        static function (array $matches): string {
            return $matches[1] . 'ppm' . $matches[3];
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~(Serie )([a-z])( )~u",
        static function (array $matches): string {
            return $matches[1] . strtoupper($matches[2]) . $matches[3];
        },
        $new_case);
    $new_case = trim($new_case);
    // Special cases - Only if the full title
    if ($new_case === 'Bioscience') {
        $new_case = 'BioScience';
    } elseif ($new_case === 'Aids') {
        $new_case = 'AIDS';
    } elseif ($new_case === 'Biomedical Engineering Online') {
        $new_case = 'BioMedical Engineering OnLine';
    } elseif ($new_case === 'Sage Open') {
        $new_case = 'SAGE Open';
    } elseif ($new_case === 'Ca') {
        $new_case = 'CA';
    } elseif ($new_case === 'Time off') {
        $new_case = 'Time Off';
    } elseif ($new_case === 'It Professional') {
        $new_case = 'IT Professional';
    } elseif ($new_case === 'Jom') {
        $new_case = 'JOM';
    } elseif ($new_case === 'NetWorker') {
        $new_case = 'netWorker';
    } elseif ($new_case === 'Melus') {
        $new_case = 'MELUS';
    }
    return $new_case;
}

function mb_ucfirst_bot(string $string): string
{
    $first = mb_substr($string, 0, 1);
    if (mb_strlen($first) !== strlen($first)) {
        return $string;
    } else {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1, null);
    }
}

function mb_ucfirst_force(string $string): string
{
    return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1, null);
}

function mb_strrev(string $string, string $encode = ''): string
{
    $chars = mb_str_split($string, 1, $encode ? '' : mb_internal_encoding());
    return implode('', array_reverse($chars));
}

function mb_ucwords(string $string): string
{
    if (mb_ereg_search_init($string, '(\S)(\S*\s*)|(\s+)')) {
        $output = '';
        while ($match = mb_ereg_search_regs()) {
            $output .= $match[3] ? $match[3] : mb_strtoupper($match[1]) . $match[2];
        }
        return $output;
    } else {
        return $string;  // @codeCoverageIgnore
    }
}

function mb_substr_replace(string $string, string $replacement, int $start, int $length): string {
    return mb_substr($string, 0, $start).$replacement.mb_substr($string, $start+$length);
}

function remove_brackets(string $string): string {
    return str_replace(['(', ')', '{', '}', '[', ']'], '', $string);
}

// ============================================= Wikipedia functions ======================================

function throttle(): void {
    static $last_write_time = 0;
    static $phase = 0;
    $cycles = 20;    // Check every this many writes
    $min_interval = 2 * $cycles;    // How many seconds we want per-write on average
    if ($last_write_time === 0) {
        $last_write_time = time();
    }

    $mem_max = (string) @ini_get('memory_limit');
    if (preg_match('~^(\d+)M$~', $mem_max, $matches)) {
        $mem_max = (int) (0.3 * @intval($matches[1])); // Memory limit is set super high just to avoid crash
        unset($matches);
        $mem_used = (int) (memory_get_usage() / 1048576);
        if (($mem_max !== 0) && ($mem_used > $mem_max)) {    // Clear every buffer we have
                HandleCache::free_memory();                                                 // @codeCoverageIgnoreStart
                $mem_used1 = (string) (int) (memory_get_usage() / 1048576);
                AdsAbsControl::free_memory();
                $mem_used2 = (string) (int) (memory_get_usage() / 1048576);
                $mem_used0 = (string) $mem_used;
            bot_debug_log("Cleared memory: " . $mem_used2 . ' : '   . $mem_used1 . ' : ' . $mem_used0);
        }                                                                                                                // @codeCoverageIgnoreEnd
    } else {
        bot_debug_log("Memory Limit should end in M, but got: " . echoable($mem_max));  // @codeCoverageIgnore
    }
    $phase += 1;
    if ($phase < $cycles) {
        return;
    } else {
        $phase = 0;
    }

    $time_since_last_write = time() - $last_write_time;
    if ($time_since_last_write < 0) {
        $time_since_last_write = 0; // Super paranoid, this would be a freeze point
    }
    if ($time_since_last_write < $min_interval) {
        $time_to_pause = (int) floor($min_interval - $time_since_last_write); // @codeCoverageIgnore
        report_info("Throttling: waiting " . $time_to_pause . " seconds..."); // @codeCoverageIgnore
        sleep($time_to_pause);                                                // @codeCoverageIgnore
    }
    $last_write_time = time();
}

// ============================================= Data processing functions ======================================

function tidy_date(string $string): string { // Wrapper to change all pre-1900 dates to just years
    $string = tidy_date_inside($string);
    if ($string === '') {
        return $string;
    }
    $time = strtotime($string);
    if (!$time) {
        return $string;
    }
    $old = strtotime('1 January 1900');
    if ($old < $time) {
        return $string;
    }
    $new = date('Y', $time);
    if (strlen($new) === 4) {
        return ltrim($new, "0"); // Also cleans up 0000
    }
    if (strlen($new) === 5 && substr($new, 0, 1) === '-') {
        $new = ltrim($new, "-");
        $new = ltrim($new, "0");
        $new = $new . ' BC';
        return $new;
    }
    return $string;
}

function tidy_date_inside(string $string): string {
    $string=trim($string);
    if (stripos($string, 'Invalid') !== false) {
        return '';
    }
    if (strpos($string, '1/1/0001') !== false) {
        return '';
    }
    if (strpos($string, '0001-01-01') !== false) {
        return '';
    }
    if (!preg_match('~\d{2}~', $string)) {
        return ''; // Not two numbers next to each other
    }
    if (preg_match('~^\d{2}\-\-$~', $string)) {
        return '';
    }
    // Google sends ranges
    if (preg_match('~^(\d{4})(\-\d{2}\-\d{2})\s+\-\s+(\d{4})(\-\d{2}\-\d{2})$~', $string, $matches)) { // Date range
        if ($matches[1] === $matches[3]) {
            return date('j F', strtotime($matches[1].$matches[2])) . ' – ' . date('j F Y', strtotime($matches[3].$matches[4]));
        } else {
            return date('j F Y', strtotime($matches[1].$matches[2])) . ' – ' . date('j F Y', strtotime($matches[3].$matches[4]));
        }
    }
    // Huge amount of character cleaning
    if (strlen($string) !== mb_strlen($string)) {    // Convert all multi-byte characters to dashes
        $cleaned = '';
        $the_str_length = mb_strlen($string);
        for ($i = 0; $i < $the_str_length; $i++) {
            $char = mb_substr($string, $i, 1);
            if (mb_strlen($char) === strlen($char)) {
                $cleaned .= $char;
            } else {
                $cleaned .= '-';
            }
        }
        $string = $cleaned;
    }
    $string = safe_preg_replace("~[^\x01-\x7F]~", "-", $string); // Convert any non-ASCII Characters to dashes
    $string = safe_preg_replace('~[\s\-]*\-[\s\-]*~', '-', $string); // Combine dash with any following or preceding white space and other dash
    $string = safe_preg_replace('~^\-*(.+?)\-*$~', '\1', $string);  // Remove trailing/leading dashes
    $string = trim($string);
    // End of character clean-up
    $string = safe_preg_replace('~[^0-9]+\d{2}:\d{2}:\d{2}$~', '', $string); //trailing time
    $string = safe_preg_replace('~^Date published \(~', '', $string); // seen this
    // https://stackoverflow.com/questions/29917598/why-does-0000-00-00-000000-return-0001-11-30-000000
    if (strpos($string, '0001-11-30') !== false) {
        return '';
    }
    if (strpos($string, '1969-12-31') !== false) {
        return '';
    }
    if (str_i_same('19xx', $string)) {
        return ''; //archive.org gives this if unknown
    }
    if (preg_match('~^\d{4} \d{4}\-\d{4}$~', $string)) {
        return ''; // si.edu
    }
    if (preg_match('~^(\d\d?)/(\d\d?)/(\d{4})$~', $string, $matches)) { // dates with slashes
        if (intval($matches[1]) < 13 && intval($matches[2]) > 12) {
            if (strlen($matches[1]) === 1) {
                $matches[1] = '0' . $matches[1];
            }
            return $matches[3] . '-' . $matches[1] . '-' . $matches[2];
        } elseif (intval($matches[2]) < 13 && intval($matches[1]) > 12) {
            if (strlen($matches[2]) === 1) {
                $matches[2] = '0' . $matches[2];
            }
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        } elseif (intval($matches[2]) > 12 && intval($matches[1]) > 12) {
            return '';
        } elseif ($matches[1] === $matches[2]) {
            if (strlen($matches[2]) === 1) {
                $matches[2] = '0' . $matches[2];
            }
            return $matches[3] . '-' . $matches[2] . '-' . $matches[2];
        } else {
            return $matches[3];// do not know. just give year
        }
    }
    $string = trim($string);
    if (preg_match('~^(\d{4}\-\d{2}\-\d{2})T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$~', $string, $matches)) {
        return tidy_date_inside($matches[1]); // Remove time zone stuff from standard date format
    }
    if (preg_match('~^\-?\d+$~', $string)) {
        $string = intval($string);
        if ($string < -2000 || $string > (int) date("Y") + 10) {
            return ''; // A number that is not a year; probably garbage
        }
        if ($string > -2 && $string < 2) {
            return ''; // reject -1,0,1
        }
        return (string) $string; // year
    }
    if (preg_match('~^(\d{1,2}) ([A-Za-z]+\.?), ?(\d{4})$~', $string, $matches)) { // strtotime('3 October, 2016') gives 2019-10-03.    The comma is evil and strtotime is stupid
        $string = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];   // Remove comma
    }
    $time = strtotime($string);
    if ($time) {
        $day = date('d', $time);
        $year = intval(date('Y', $time));
        if ($year < -2000 || $year > (int) date("Y") + 10) {
            return ''; // We got an invalid year
        }
        if ($year < 100 && $year > -100) {
            return '';
        }
        if ($day === '01') { // Probably just got month and year
            $string = date('F Y', $time);
        } else {
            $string = date('Y-m-d', $time);
        }
        if (stripos($string, 'Invalid') !== false) {
            return '';
        }
        return $string;
    }
    if (preg_match('~^(\d{4}\-\d{1,2}\-\d{1,2})[^0-9]~', $string, $matches)) {
        return tidy_date_inside($matches[1]); // Starts with date
    }
    if (preg_match('~\s(\d{4}\-\d{1,2}\-\d{1,2})$~', $string, $matches)) {
        return tidy_date_inside($matches[1]);  // Ends with a date
    }
    if (preg_match('~^(\d{1,2}/\d{1,2}/\d{4})[^0-9]~', $string, $matches)) {
        return tidy_date_inside($matches[1]); // Recursion to clean up 3/27/2000
    }
    if (preg_match('~[^0-9](\d{1,2}/\d{1,2}/\d{4})$~', $string, $matches)) {
        return tidy_date_inside($matches[1]);
    }

    // Dates with dots -- convert to slashes and try again.
    if (preg_match('~(\d\d?)\.(\d\d?)\.(\d{2}(?:\d{2})?)$~', $string, $matches) || preg_match('~^(\d\d?)\.(\d\d?)\.(\d{2}(?:\d{2})?)~', $string, $matches)) {
        if (intval($matches[3]) < ((int) date("y") + 2)) {
            $matches[3] = (int) $matches[3] + 2000;
        }
        if (intval($matches[3]) < 100)  {
            $matches[3] = (int) $matches[3] + 1900;
        }
        return tidy_date_inside((string) $matches[1] . '/' . (string) $matches[2] . '/' . (string) $matches[3]);
    }

    if (preg_match('~\s(\d{4})$~', $string, $matches)) {
        return $matches[1]; // Last ditch effort - ends in a year
    }
    return ''; // And we give up
}

function not_bad_10_1093_doi(string $url): bool { // We assume DOIs are bad, unless on good list
    if ($url === '') {
        return true;
    }
    if(!preg_match('~10.1093/([^/]+)/~u', $url, $match)) {
        return true;
    }
    $test = strtolower($match[1]);
    // March 2019 Good list
    if (in_array($test, GOOD_10_1093_DOIS, true)) {
        return true;
    }
    return false;
}

function bad_10_1093_doi(string $url): bool {
    return !not_bad_10_1093_doi($url);
}

// ============================================= Other functions ======================================

function remove_comments(string $string): string {
    // See Comment::PLACEHOLDER_TEXT for syntax
    $string = preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~isu', "", $string);
    return preg_replace("~<!--.*?-->~us", "", $string);
}

/** @param array<string> $list

    @return array<string> */
function prior_parameters(string $par, array $list=[]): array {
    array_unshift($list, $par);
    if (preg_match('~(\D+)(\d+)~', $par, $match) && stripos($par, 's2cid') === false) {
        $before = (string) ((int) $match[2] - 1);
        switch ($match[1]) {
            case 'first':
            case 'initials':
            case 'forename':
                return ['last' . $match[2], 'surname' . $match[2], 'author' . $before];
            case 'last':
            case 'surname':
            case 'author':
                return ['first' . $before, 'forename' . $before, 'initials' . $before, 'author' . $before];
            default:
                $base = $match[1] . $before;
                return array_merge(FLATTENED_AUTHOR_PARAMETERS, [$base, $base . '-last', $base . '-first']);
        }
    }
    switch ($par) {
        case 'author':
        case 'authors':
        case 'last':
        case 'first':
            return $list;
        case 'dummy':
            return $list;
        case 'title':
        case 'others':
        case 'display-editors':
        case 'displayeditors':
        case 'display-authors':
        case 'displayauthors':
        case 'author-link':
            return prior_parameters('dummy', array_merge(FLATTENED_AUTHOR_PARAMETERS, $list));
        case 'title-link':
        case 'titlelink':
            return prior_parameters('title', $list);
        case 'chapter':
            return prior_parameters('title-link', array_merge(['titlelink'], $list));
        case 'journal':
        case 'work':
        case 'newspaper':
        case 'website':
        case 'magazine':
        case 'periodical':
        case 'encyclopedia':
        case 'encyclopaedia':
            return prior_parameters('chapter', $list);
        case 'series':
            return prior_parameters('journal', array_merge(['work', 'newspaper', 'magazine', 'periodical', 'website', 'encyclopedia', 'encyclopaedia'], $list));
        case 'year':
        case 'date':
            return prior_parameters('series', $list);
        case 'volume':
            return prior_parameters('year', array_merge(['date'], $list));
        case 'issue':
        case 'number':
            return prior_parameters('volume', $list);
        case 'page':
        case 'pages':
            return prior_parameters('issue', array_merge(['number'], $list));
        case 'location':
        case 'publisher':
        case 'edition':
        case 'agency':
            return prior_parameters('page', array_merge(['pages'], $list));
        case 'doi':
            return prior_parameters('location', array_merge(['publisher', 'edition'], $list));
        case 'doi-broken-date':
            return prior_parameters('doi', $list);
        case 'doi-access':
            return prior_parameters('doi-broken-date', $list);
        case 'jstor':
            return prior_parameters('doi-access', $list);
        case 'pmid':
            return prior_parameters('jstor', $list);
        case 'pmc':
            return prior_parameters('pmid', $list);
        case 'pmc-embargo-date':
            return prior_parameters('pmc', $list);
        case 'arxiv':
        case 'eprint':
        case 'class':
            return prior_parameters('pmc-embargo-date', $list);
        case 'bibcode':
            return prior_parameters('arxiv', array_merge(['eprint', 'class'], $list));
        case 'hdl':
            return prior_parameters('bibcode', $list);
        case 'isbn':
        case 'biorxiv':
        case 'citeseerx':
        case 'jfm':
        case 'zbl':
        case 'mr':
        case 'osti':
        case 'ssrn':
        case 'rfc':
            return prior_parameters('hdl', $list);
        case 'lccn':
        case 'issn':
        case 'ol':
        case 'oclc':
        case 'asin':
        case 's2cid':
            return prior_parameters('isbn', array_merge(['biorxiv', 'citeseerx', 'jfm', 'zbl', 'mr', 'osti', 'ssrn', 'rfc'], $list));
        case 'url':
            return prior_parameters('lccn', array_merge(['issn', 'ol', 'oclc', 'asin', 's2cid'], $list));
        case 'chapter-url':
        case 'article-url':
        case 'chapterurl':
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
            return prior_parameters('url', $list);
        case 'archive-url':
        case 'archiveurl':
        case 'accessdate':
        case 'access-date':
            return prior_parameters('chapter-url', array_merge(['article-url', 'chapterurl', 'conference-url', 'conferenceurl',
                'contribution-url', 'contributionurl', 'entry-url', 'event-url', 'eventurl', 'lay-url',
                'layurl', 'map-url', 'mapurl', 'section-url', 'sectionurl', 'transcript-url',
                'transcripturl', 'URL',
            ], $list));
        case 'archive-date':
        case 'archivedate':
            return prior_parameters('archive-url', array_merge(['archiveurl', 'accessdate', 'access-date'], $list));
        case 'id':
        case 'type':
        case 'via':
            return prior_parameters('archive-date', array_merge(['archivedate'], $list));
        default:
            bot_debug_log("prior_parameters missed: " . $par);
            return $list;
    }
}

/** @return array<string> */
function equivalent_parameters(string $par): array {
    switch ($par) {
        case 'author':
        case 'authors':
        case 'author1':
        case 'last1':
            return FLATTENED_AUTHOR_PARAMETERS;
        case 'pmid':
        case 'pmc':
            return ['pmc', 'pmid'];
        case 'page_range':
        case 'start_page':
        case 'end_page': // From doi_crossref
        case 'pages':
        case 'page':
            return ['page_range', 'pages', 'page', 'end_page', 'start_page'];
        default:
            return [$par];
    }
}

function check_doi_for_jstor(string $doi, Template $template): void {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, []);
    }
    if ($template->has('jstor')) {
        return;
    }
    /** @psalm-taint-escape ssrf */
    $doi = trim($doi);
    if ($doi === '') {
        return;
    }
    if (preg_match('~^\d+$~', $doi)) {
        return; // Just numbers - this WILL match a JSTOR, but who knows what it really is!
    }
    if (strpos($doi, '10.2307') === 0) { // special case
        $doi = substr($doi, 8);
    }
    $pos = strpos($doi, '?');
    if ($pos) {
            $doi = substr($doi, 0, $pos);
    }
    curl_setopt($ch, CURLOPT_URL, "https://www.jstor.org/citation/ris/" . $doi);
    $ris = bot_curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode === 200 &&
            stripos($ris, $doi) !== false &&
            strpos($ris, 'Provider') !== false &&
            stripos($ris, 'No RIS data found for') === false &&
            stripos($ris, 'Block Reference') === false &&
            stripos($ris, 'A problem occurred trying to deliver RIS data') === false &&
            substr_count($ris, '-') > 3) { // It is actually a working JSTOR
        $template->add_if_new('jstor', $doi);
    }
}

function can_safely_modify_dashes(string $value): bool {
    return (stripos($value, "http") === false)
            && (strpos($value, "[//") === false)
            && (substr_count($value, "<") === 0) // <span></span> stuff
            && (stripos($value, 'CITATION_BOT_PLACEHOLDER') === false)
            && (strpos($value, "(") === false)
            && (preg_match('~(?:[a-zA-Z].*\s|\s.*[a-zA-Z])~u', trim($value)) !== 1) // Spaces and letters
            && ((substr_count($value, '-') + substr_count($value, '–') + substr_count($value, ',') + substr_count($value, 'dash')) < 3) // This line helps us ignore with 1-5–1-6 stuff
            && (preg_match('~^[a-zA-Z]+[0-9]*.[0-9]+$~u', $value) !== 1) // A-3, A3-5 etc.   Use "." for generic dash
            && (preg_match('~^\d{4}\-[a-zA-Z]+$~u', $value) !== 1); // 2005-A used in {{sfn}} junk
}

function str_i_same(string $str1, string $str2): bool {
    if ($str1 === 'Eulerian Numbers') {
        return false; // very special case
    }
    if (strcasecmp($str1, $str2) === 0) {
        return true; // Quick non-multi-byte compare short cut
    }
    return strcmp(mb_strtoupper($str1), mb_strtoupper($str2)) === 0;
}

function doi_encode (string $doi): string {
    /** @psalm-taint-escape html
        @psalm-taint-escape has_quotes
        @psalm-taint-escape ssrf */
    $doi = urlencode($doi);
    return str_replace('%2F', '/', $doi);
}

function hdl_decode(string $hdl): string {
    $hdl = urldecode($hdl);
    $hdl = str_replace(';', '%3B', $hdl);
    $hdl = str_replace('#', '%23', $hdl);
    return str_replace(' ', '%20', $hdl);
}

/**
 * Only on webpage
 */

// @codeCoverageIgnoreStart

/** @param array<string> $pages_in_category */
function edit_a_list_of_pages(array $pages_in_category, WikipediaBot $api, string $edit_summary_end): void {
    $final_edit_overview = "";
    // Remove pages with blank as the name, if present
    $key = array_search("", $pages_in_category);
    if ($key !== false) {
        unset($pages_in_category[$key]);
    }
    if (empty($pages_in_category)) {
        report_warning('No links to expand found');
        bot_html_footer();
        return;
    }
    $total = count($pages_in_category);
    if ($total > MAX_PAGES) {
        report_warning('Number of links is huge. Cancelling run. Maximum size is ' . (string) MAX_PAGES);
        bot_html_footer();
        return;
    }
    big_jobs_check_overused($total);

    $page = new Page();
    $done = 0;

    foreach ($pages_in_category as $page_title) {
        big_jobs_check_killed();
        $done++;
        if (strpos($page_title, 'Wikipedia:Requests') === false && $page->get_text_from($page_title) && $page->expand_text()) {
            if (SAVETOFILES_MODE) {
                // Sanitize file name by replacing characters that are not allowed on most file systems to underscores, and also replace path characters
                // And add .md extension to avoid troubles with devices such as 'con' or 'aux'
                $filename = preg_replace('~[\/\\:*?"<>|\s]~', '_', $page_title) . '.md';
                report_phase("Saving to file " . echoable($filename));
                $body = $page->parsed_text();
                $bodylen = strlen($body);
                if (file_put_contents($filename, $body)===$bodylen) {
                    report_phase("Saved to file " . echoable($filename));
                } else {
                    report_warning("Save to file failed.");
                }
                unset($body);
            } else {
                report_phase("Writing to " . echoable($page_title) . '... ');
                $attempts = 0;
                if ($total === 1) {
                    $edit_sum = $edit_summary_end;
                } else {
                    $edit_sum = $edit_summary_end . (string) $done . '/' . (string) $total . ' ';
                }
                while (!$page->write($api, $edit_sum) && $attempts < MAX_TRIES) {
                    ++$attempts;
                }
                if ($attempts < MAX_TRIES) {
                    $last_rev = WikipediaBot::get_last_revision($page_title);
                    html_echo(
                    "\n  <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
                    . $last_rev . ">diff</a>" .
                    " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a>",
                    "\n" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid=". $last_rev . "\n");
                    $final_edit_overview .=
                        "\n [ <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
                    . $last_rev . ">diff</a>" .
                    " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a> ] " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
                } else {
                    report_warning("Write failed.");
                    $final_edit_overview .= "\n Write failed.            " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
                }
            }
        } else {
            report_phase($page->parsed_text() ? "No changes required. \n\n      # # # " : "Blank page. \n\n      # # # ");
                $final_edit_overview .= "\n No changes needed. " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
        }
        echo "\n";
        check_memory_usage("After writing page");
        $page->parse_text("");  // Clear variables before doing GC
        gc_collect_cycles();        // This should do nothing
        memory_reset_peak_usage();
    }
    if ($total > 1) {
        if (!HTML_OUTPUT) {
            $final_edit_overview = '';
        }
        echo "\n Done all " . (string) $total . " pages. \n  # # # \n" . $final_edit_overview;
    } else {
        echo "\n Done with page.";
    }
    bot_html_footer();
}

// @codeCoverageIgnoreEnd

function check_memory_usage(string $where): void {
    $mem_used = (int) (memory_get_usage() / 1048576);
    if ($mem_used > 24) {
        bot_debug_log("Memory Usage is up to " . (string) $mem_used . "MB in " . $where);
    }
    $mem_used = (int) (memory_get_peak_usage() / 1048576);
    if ($mem_used > 128) {
        bot_debug_log("Peak memory Usage is up to " . (string) $mem_used . "MB in " . $where); // @codeCoverageIgnore
    }
}

/**
 * @codeCoverageIgnore
 */
function bot_html_header(): void {
    if (! HTML_OUTPUT) {
        echo "\n";
        return;
    }
    echo '<!DOCTYPE html><html lang="en" dir="ltr">', "\n",
    ' <head>', "\n",
    '  <title>Citation Bot: running</title>', "\n",
    '  <link rel="copyright" type="text/html" href="https://www.gnu.org/licenses/gpl-3.0" />', "\n",
    '  <link rel="stylesheet" type="text/css" href="results.css" />', "\n",
    ' </head>', "\n",
    ' <body>', "\n",
    '  <header>', "\n",
    '   <p>Follow Citation bots progress below.</p>', "\n",
    '   <p>', "\n",
    '    <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" title="Using Citation Bot" aria-label="Using Citation Bot (opens new window)">How&nbsp;to&nbsp;Use&nbsp;/&nbsp;Tips&nbsp;and&nbsp;Tricks</a> |', "\n",
    '    <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank" aria-label="Report bugs at Wikipedia (opens new window)">Report&nbsp;bugs</a> |', "\n",
    '    <a href="https://github.com/ms609/citation-bot" target="_blank" title="GitHub repository"  aria-label="GitHub repository (opens new window)">Source&nbsp;code</a>', "\n",
    '   </p>', "\n",
    '  </header>', "\n",
    '  <pre id="botOutput">', "\n";
    if (ini_get('pcre.jit') === '0') {
        report_warning('PCRE JIT Disabled');
    }
}

// @codeCoverageIgnoreStart
function bot_html_footer(): void {
    if (HTML_OUTPUT) {
        echo '</pre><footer><a href="./" title="Use Citation Bot again" aria-label="Use Citation Bot again (return to main page)">Another</a>?</footer></body></html>'; // @codeCoverageIgnore
    }
    echo "\n";
}
// @codeCoverageIgnoreEnd

/** null/false/String of location */
function hdl_works(string $hdl): string|null|false {
    $hdl = trim($hdl);
    $hdl = str_replace('%2F', '/', $hdl);
    // And now some obvious fails
    if (strpos($hdl, '/') === false) {
        return false;
    }
    if (strpos($hdl, 'CITATION_BOT_PLACEHOLDER') !== false) {
        return false;
    }
    if (strpos($hdl, '123456789') === 0) {
        return false;
    }
    if (strlen($hdl) > HandleCache::MAX_HDL_SIZE) {
        return null;
    }
    if (isset(HandleCache::$cache_hdl_loc[$hdl])) {
        return HandleCache::$cache_hdl_loc[$hdl];
    }
    if (isset(HandleCache::$cache_hdl_bad[$hdl])) {
        return false;
    }
    if (isset(HandleCache::$cache_hdl_null[$hdl])) {
        return null; // @codeCoverageIgnore
    }
    if (strpos($hdl, '10.') === 0 && doi_works($hdl) === false) {
        return false;
    }
    $works = is_hdl_works($hdl);
    if ($works === null) {
        if (isset(NULL_DOI_LIST[$hdl])) {                // @codeCoverageIgnoreStart
            HandleCache::$cache_hdl_bad[$hdl] = true;    // These are know to be bad, so only check one time during run
            return false;
        }
        foreach (NULL_DOI_STARTS_BAD as $bad_start) {
            if (stripos($hdl, $bad_start) === 0) {
                HandleCache::$cache_hdl_bad[$hdl] = true;  // all bad
                return false;
            }
        }
        HandleCache::$cache_hdl_null[$hdl] = true;
        return null;                                     // @codeCoverageIgnoreEnd
    }
    if ($works === false) {
        HandleCache::$cache_hdl_bad[$hdl] = true;
        return false;
    }
    HandleCache::$cache_hdl_loc[$hdl] = $works;
    return $works;
}

    /** Returns null/false/String of location */
function is_hdl_works(string $hdl): string|null|false {
    $hdl = trim($hdl);
    usleep(100000);
    $url = "https://hdl.handle.net/" . $hdl;
    $headers_test = get_headers_array($url);
    if ($headers_test === false) {
        $headers_test = get_headers_array($url); // @codeCoverageIgnore
    }
    if ($headers_test === false) { // most likely bad
        return null; // @codeCoverageIgnore
    }
    if (interpret_doi_header($headers_test, $hdl) === null) {
        return null; // @codeCoverageIgnore
    }
    if (interpret_doi_header($headers_test, $hdl) === false) {
        return false;
    }
    return get_loc_from_hdl_header($headers_test);
}

// Sometimes (UTF-8 non-english characters) preg_replace fails, and we would rather have the original string than a null
function safe_preg_replace(string $regex, string $replace, string $old): string {
    if ($old === "") {
        return "";
    }
    $new = preg_replace($regex, $replace, $old);
    if ($new === null) {
        return $old; // @codeCoverageIgnore
    }
    return $new;
}
function safe_preg_replace_callback(string $regex, callable $replace, string $old): string {
    if ($old === "") {
        return "";
    }
    $new = preg_replace_callback($regex, $replace, $old);
    if ($new === null) {
        return $old; // @codeCoverageIgnore
    }
    return $new;
}

function wikifyURL(string $url): string {
    $in = [' ', '"', '\'', '<','>', '[', ']', '{', '|', '}'];
    $out = ['%20', '%22', '%27', '%3C', '%3E', '%5B', '%5D', '%7B', '%7C', '%7D'];
    return str_replace($in, $out, $url);
}

function numberToRomanRepresentation(int $number): string { // https://stackoverflow.com/questions/14994941/numbers-to-roman-numbers-with-php
    $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
    $returnValue = '';
    while ($number > 0) {
        foreach ($map as $roman => $int) {
            if($number >= $int) {
                $number -= $int;
                $returnValue .= $roman;
                break;
            }
        }
    }
    return $returnValue;
}

function convert_to_utf8(string $value): string {
    $value = convert_to_utf8_inside($value);
    $test = preg_replace('~[\'a-zA-Z0-9 ]+~', '', $value);
    $test = mb_convert_encoding($test, 'utf-8', 'windows-1252');
    $count_cr1 = substr_count($value, '®') + substr_count($value, '©');
    $count_cr2 = substr_count($test, '®') + substr_count($test, '©');
    $len1 = strlen($value);
    $len2 = strlen($test);
    $bad1 = substr_count($value, "");
    $bad2 = substr_count($test, "");
    $rq1 = substr_count($value, "”");
    $rq2 = substr_count($test, "”");
    $lq1 = substr_count($value, "„");
    $lq2 = substr_count($test, "„");
    if ((1 + $count_cr1) === $count_cr2 && (4 + $len1 > $len2) && ($bad1 >= $bad2) && ($lq1 <= $lq2) && ($rq1 <= $rq2)) { // Special case for single (c) or (r) and did not grow much
        $value = mb_convert_encoding($value, 'utf-8', 'windows-1252');
    }
    // Special cases
    $value = str_replace([" �Livelong� ", "Uni�o", "Independ�ncia", "Folke Ekstr�m"],[' "Livelong" ', "União", "Independência", "Folke Ekström"], $value);
    return $value;
}

function convert_to_utf8_inside(string $value): string {
    $encode1 =  mb_detect_encoding($value, ["UTF-8", "EUC-KR", "EUC-CN", "ISO-2022-JP", "Windows-1252", "iso-8859-1"], true);
    if ($encode1 === false || $encode1 === 'UTF-8' || $encode1 === 'Windows-1252') {
        return $value;
    }
    $encode2 =  mb_detect_encoding($value, ["UTF-8", "EUC-CN", "EUC-KR", "ISO-2022-JP", "Windows-1252", "iso-8859-1"], true);
    if ($encode1 !== $encode2) {
        return $value;
    }
    $encode3 =  mb_detect_encoding($value, ["UTF-8", "ISO-2022-JP", "EUC-CN", "EUC-KR", "Windows-1252", "iso-8859-1"], true);
    if ($encode1 !== $encode3) {
        return $value;
    }
    $encode4 =  mb_detect_encoding($value, ["iso-8859-1", "UTF-8", "Windows-1252", "ISO-2022-JP", "EUC-CN", "EUC-KR"], true);
    if ($encode1 !== $encode4) {
        return $value;
    }
    $new_value = (string) @mb_convert_encoding($value, "UTF-8", $encode1);
    if ($new_value === "") {
        return $value;
    }
    return $new_value;
}

function is_encoding_reasonable(string $encode): bool { // common "default" ones that are often wrong
    $encode = strtolower($encode);
    return !in_array($encode, SANE_ENCODE, true);
}

function smart_decode(string $title, string $encode, string $archive_url): string {
    if ($title === "") {
        return "";
    }
    if ($encode === 'maccentraleurope') {
        $encode = 'mac-centraleurope';
    }
    if ($encode === 'UTF-8; charset=UTF-8') {
        $encode = 'UTF-8';
    }
    if ($encode === 'Shift_JIS' || $encode === 'x-sjis' || $encode === 'SJIS') {
        $encode = 'SJIS-win';
    }
    if ($encode === 'big5') {
        $encode = 'BIG-5';
    }
    if (preg_match('~^\d{4}\-\d{1,2}$~', $encode)) {
        $encode = 'iso-' . $encode;
    }
    if (preg_match('~^ISO\-(.+)$~', $encode)) {
        $encode = 'iso-' . $encode[1];
    }
    if (in_array($encode, INSANE_ENCODE, true)) {
        return "";
    }
    $master_list = mb_list_encodings();
    $valid = [];
    foreach ($master_list as $enc) {
        $valid[] = strtolower($enc);
    }
    try {
        if (in_array(strtolower($encode), TRY_ENCODE, true) ||
            !in_array(strtolower($encode), $valid, true)) {
            $try = (string) @iconv($encode, "UTF-8", $title);
        } else {
            $try = (string) @mb_convert_encoding($title, "UTF-8", $encode);
        }
    } catch (Exception $e) { // @codeCoverageIgnoreStart
        $try = "";
    } catch (ValueError $v) {
        $try = "";
    }                                                // @codeCoverageIgnoreEnd
    if ($try === "") {
        bot_debug_log('Bad Encoding: ' . $encode . ' for ' . echoable($archive_url)); // @codeCoverageIgnore
    }
    return $try;
}

/** @param array<string> $gid */
function normalize_google_books(string &$url, int &$removed_redundant, string &$removed_parts, array &$gid): void { // PASS BY REFERENCE!!!!!!
    $removed_redundant = 0;
    $hash = '';
    $removed_parts ='';
    $url = str_replace('&quot;', '"', $url);

    if (strpos($url, "#")) {
        $url_parts = explode("#", $url, 2);
        $url = $url_parts[0];
        $hash = $url_parts[1];
    }
    // And symbol in a search quote
    $url = str_replace("+&+", "+%26+", $url);
    $url = str_replace("+&,+", "+%26,+", $url);
    $url_parts = explode("&", str_replace("&&", "&", str_replace("?", "&", $url)));
    $url = "https://books.google.com/books?id=" . $gid[1];
    $book_array = [];
    foreach ($url_parts as $part) {
        $part_start = explode("=", $part, 2);
        if ($part_start[0] === 'text') {
            $part_start[0] = 'dq';
        }
        if ($part_start[0] === 'keywords') {
            $part_start[0] = 'q';
        }
        if ($part_start[0] === 'page') {
            $part_start[0] = 'pg';
        }
        switch ($part_start[0]) {
            case "dq":
            case "pg":
            case "lpg":
            case "q":
            case "printsec":
            case "cd":
            case "vq":
            case "jtp":
            case "sitesec":
            case "article_id":
            case "bsq":
                if (empty($part_start[1])) {
                    $removed_redundant++;
                    $removed_parts .= $part;
                } else {
                    $book_array[$part_start[0]] = $part_start[1];
                }
                break;
            case "id":
                break; // Don't "remove redundant"
            // These all go away
            case "hl":
            case "ei":
            case "ots":
            case "sig":
            case "source":
            case "lr":
            case "ved":
            case "gs_lcp":
            case "sxsrf":
            case "gfe_rd":
            case "gws_rd":
            case "sa":
            case "oi":
            case "ct":
            case "client":
            case "redir_esc":
            case "callback":
            case "jscmd":
            case "bibkeys":
            case "newbks":
            case "gbpv":
            case "newbks_redir":
            case "resnum":
            case "ci":
            case "surl":
            case "safe":
            case "as_maxm_is":
            case "as_maxy_is":
            case "f":
            case "as_minm_is":
            case "pccc":
            case "as_miny_is":
            case "authuser":
            case "cad":
            case "focus":
            case "pjf":
            case "gl":
            case "ovdme":
            case "sqi":
            case "w":
            case "rview":
            case "":
            case "kptab":
            case "pgis":
            case "ppis":
            case "output":
            case "gboemv":
            case "ie":
            case "nbsp;":
            case "fbclid":
            case "num":
            case "oe":
            case "pli":
            case "prev":
            case "vid":
            case "view":
            case "as_drrb_is":
            case "sourceid":
            case "btnG":
            case "rls":
            case "ov2":
            case "buy":
            case "edge":
            case "zoom":
            case "img":
            case "as_pt":
                $removed_parts .= $part;
                $removed_redundant++;
                break;
            default:
                if ($removed_redundant !== 0) {
                    $removed_parts .= $part; // http://blah-blah is first parameter and it is not actually dropped
                    bot_debug_log("Unexpected dropping from Google Books " . $part);
                }
                $removed_redundant++;
        }
    }
    // Clean up hash first
    $hash = '&' . trim($hash) . '&';
    $hash = str_replace(['&f=false', '&f=true', 'v=onepage'], ['','',''], $hash); // onepage is default
    $hash = str_replace(['&q&', '&q=&', '&&&&', '&&&', '&&', '%20&%20'], ['&', '&', '&', '&', '&', '%20%26%20'], $hash);
    if (preg_match('~(&q=[^&]+)&~', $hash, $matcher)) {
        $hash = str_replace($matcher[1], '', $hash);
        if (isset($book_array['q'])) {
            $removed_parts .= '&q=' . $book_array['q'];
            $book_array['q'] = urlencode(urldecode(substr($matcher[1], 3))); // #q= wins over &q= before # sign
        } elseif (isset($book_array['dq'])) {
            $removed_parts .= '&dq=' . $book_array['dq'];
            $dum_dq = str_replace('+', ' ', urldecode($book_array['dq']));
            $dum_q = str_replace('+', ' ', urldecode(substr($matcher[1], 3)));
            if ($dum_dq !== $dum_q) {
                $book_array['q'] = urlencode(urldecode(substr($matcher[1], 3)));
                unset($book_array['dq']);
            } else {
                $book_array['dq'] = urlencode(urldecode(substr($matcher[1], 3)));
            }
        } else {
            $book_array['q'] = urlencode(urldecode(substr($matcher[1], 3)));
        }
    }
    if (preg_match('~(&dq=[^&]+)&~', $hash, $matcher)) {
        $hash = str_replace($matcher[1], '', $hash);
        if (isset($book_array['dq'])) {
            $removed_parts .= '&dq=' . $book_array['dq'];
        }
        $book_array['dq'] = urlencode(urldecode(substr($matcher[1], 3))); // #dq= wins over &dq= before # sign
    }
    if (isset($book_array['vq']) && !isset($book_array['q']) && !isset($book_array['dq'])) {
        $book_array['q'] = $book_array['vq'];
        unset($book_array['vq']);
    }
    if (isset($book_array['vq']) && isset($book_array['pg'])) { // VQ wins if and only if a page is set
        unset($book_array['q']);
        unset($book_array['dq']);
        $book_array['q'] = $book_array['vq'];
        unset($book_array['vq']);
    }
    if (isset($book_array['bsq'])) {
        if (!isset($book_array['q']) && !isset($book_array['dq'])) {
            $book_array['q'] = $book_array['bsq'];
        }
        unset($book_array['bsq']);
    }
    if (isset($book_array['q']) && isset($book_array['dq'])) { // Q wins over DQ
        $removed_redundant++;
        $removed_parts .= '&dq=' . $book_array['dq'];
        unset($book_array['dq']);
    } elseif (isset($book_array['dq'])) {            // Prefer Q parameters to DQ
        if (!isset($book_array['pg']) && !isset($book_array['lpg'])) { // DQ requires that a page be set
            $book_array['q'] = $book_array['dq'];
            unset($book_array['dq']);
        }
    }
    if (isset($book_array['pg']) && isset($book_array['lpg'])) { // PG wins over LPG
        $removed_redundant++;
        $removed_parts .= '&lpg=' . $book_array['lpg'];
        unset($book_array['lpg']);
    }
    if (!isset($book_array['pg']) && isset($book_array['lpg'])) { // LPG by itself does not work
            $book_array['pg'] = $book_array['lpg'];
            unset($book_array['lpg']);
    }
    if (preg_match('~^&(.*)$~', $hash, $matcher)){
        $hash = $matcher[1];
    }
    if (preg_match('~^(.*)&$~', $hash, $matcher)){
        $hash = $matcher[1];
    }
    if (preg_match('~^P*(PA\d+),M1$~', $hash, $matcher)){
        $book_array['pg'] = $matcher[1];
        $hash = '';
    }
    if (preg_match('~^P*(PP\d+),M1$~', $hash, $matcher)){
        $book_array['pg'] = $matcher[1];
        $hash = '';
    }
    if (preg_match('~^P*(PT\d+),M1$~', $hash, $matcher)){
        $book_array['pg'] = $matcher[1];
        $hash = '';
    }
    if (preg_match('~^P*(PR\d+),M1$~', $hash, $matcher)){
        $book_array['pg'] = $matcher[1];
        $hash = '';
    }

    if (isset($book_array['q'])){
        if (((stripos($book_array['q'], 'isbn') === 0) && ($book_array['q'] !=='ISBN') && ($book_array['q'] !== 'isbn')) || // Sometimes the search is for the term isbn
                stripos($book_array['q'], 'subject:') === 0 ||
                stripos($book_array['q'], 'inauthor:') === 0 ||
                stripos($book_array['q'], 'inpublisher:') === 0) {
            unset($book_array['q']);
        }
    }
    if (isset($book_array['dq'])){
        if (((stripos($book_array['dq'], 'isbn') === 0) && ($book_array['dq'] !=='ISBN') && ($book_array['dq'] !== 'isbn')) || // Sometimes the search is for the term isbn
                stripos($book_array['dq'], 'subject:') === 0 ||
                stripos($book_array['dq'], 'inauthor:') === 0 ||
                stripos($book_array['dq'], 'inpublisher:') === 0) {
            unset($book_array['dq']);
        }
    }
    if (isset($book_array['sitesec'])) { // Overrides all other setting
        if (strtolower($book_array['sitesec']) === 'reviews') {
            $url .= '&sitesec=reviews';
            unset($book_array['q']);
            unset($book_array['pg']);
            unset($book_array['lpg']);
            unset($book_array['article_id']);
        }
    }
    if (isset($book_array['q'])){
        $url .= '&q=' . $book_array['q'];
    }
    if (isset($book_array['dq'])){
        $url .= '&dq=' . $book_array['dq'];
    }
    if (isset($book_array['pg'])){
        if (preg_match('~^[pra]+\d~i', $book_array['pg'])) {
            $book_array['pg'] = mb_strtoupper($book_array['pg']);
        }
        $url .= '&pg=' . $book_array['pg'];
    }
    if (isset($book_array['lpg'])){ // Currently NOT POSSIBLE - failsafe code for changes
        $url .= '&lpg=' . $book_array['lpg']; // @codeCoverageIgnore
    }
    if (isset($book_array['article_id'])){
        $url .= '&article_id=' . $book_array['article_id'];
        if (!isset($book_array['dq']) && isset($book_array['q'])) {
            $url .= '#v=onepage'; // Explicit onepage needed for these
        }
    }
    if ($hash) {
        $hash = "#" . $hash;
        $removed_parts .= $hash;
        $removed_redundant++;
    }           // CLEANED UP, so do not add $url = $url . $hash;
    if (preg_match('~^(https://books\.google\.com/books\?id=[^#^&]+)(?:&printsec=frontcover|)(?:#v=onepage|v=snippet|)$~', $url, $matches)) {
        $url = $matches[1]; // URL Just wants the landing page
    }
}

function doi_is_bad (string $doi): bool {
    $doi = strtolower($doi);
    if ($doi === '10.5284/1000184' || // DOI for the entire database
        $doi === '10.1267/science.040579197' || //  PMID test doi
        $doi === '10.2307/3511692' ||   // common review
        $doi === '10.1377/forefront' || // over-truncated
        $doi === '10.1126/science' ||   // over-truncated
        $doi === '10.1111/j' ||         // over-truncated
        $doi === '10.3138/j' ||         // over-truncated
        $doi === '10.7556/jaoa' ||      // over-truncated
        $doi === '10.7591/j' ||         // over-truncated
        $doi === '10.7722/j' ||         // over-truncated
        $doi === '10.1002/bies' ||      // over-truncated
        $doi === '10.1002/job' ||       // over-truncated
        $doi === '10.5465/ame' ||       // over-truncated
        $doi === '10.5465/amp' ||       // over-truncated
        $doi === '10.3316/ielapa' ||    // over-truncated
        $doi === '10.3316/informit' ||  // over-truncated
        $doi === '10.1023/b:boli' ||    // over-truncated
        $doi === '10.1023/b:cyto' ||    // over-truncated
        $doi === '10.1023/b:land' ||    // over-truncated
        $doi === '10.1093/acrefore' ||  // over-truncated
        $doi === '10.1093/acref' ||     // over-truncated
        $doi === '10.1093/gao' ||       // over-truncated
        $doi === '10.1093/gmo' ||       // over-truncated
        $doi === '10.1093/nsr' ||       // over-truncated
        $doi === '10.1093/oi' ||        // over-truncated
        $doi === '10.1093/logcom' ||    // over-truncated
        $doi === '10.1111/bjep' ||      // over-truncated
        $doi === '10.1146/annurev' ||   // over-truncated
        $doi === '10.1093/oi/authority' || // over-truncated
        $doi === '10.1377/forefront' || // over-truncated
        $doi === '10.3905/jpm' ||       // over-truncated
        strpos($doi, '10.5779/hypothesis') === 0 || // SPAM took over
        strpos($doi, '10.5555/') === 0 || // Test DOI prefix
        strpos($doi, '10.5860/choice.') === 0 || // Paywalled book review
        strpos($doi, '10.1093/law:epil') === 0 || // Those do not work
        strpos($doi, '10.1093/oi/authority') === 0 || // Those do not work
        (strpos($doi, '10.10520/') === 0 && !doi_works($doi)) || // Has doi in the URL, but is not a doi
        (strpos($doi, '10.1967/') === 0 && !doi_works($doi)) || // Retired DOIs
        (strpos($doi, '10.1043/0003-3219(') === 0 && !doi_works($doi)) || // Per-email.  The Angle Orthodontist will NEVER do these, since they have <> and [] in them
        (strpos($doi, '10.3316/') === 0 && !doi_works($doi)) || // These do not work - https://search.informit.org/doi/10.3316/aeipt.207729 etc.
        (strpos($doi, '10.1002/was.') === 0 && !doi_works($doi)) || // do's not doi's
        strpos($doi, '10.48550/arxiv') === 0) {
        return true;
    }
    return false;
}

/** @return array<string> */
function get_possible_dois(string $doi): array {
    $trial = [];
    $trial[] = $doi;
    // DOI not correctly formatted
    switch (substr($doi, -1)) {
        case ".":
            // Missing a terminal 'x'?
            $trial[] = $doi . "x";
            $trial[] = substr($doi, 0, -1);
            break;
        case ",":
        case ";":
        case "\"":
            // Or is this extra punctuation copied in?
            $trial[] = substr($doi, 0, -1);
    }
    if (substr($doi, -4) === '</a>' || substr($doi, -4) === '</A>') {
        $trial[] = substr($doi, 0, -4);
    }
    if (substr($doi, 0, 3) !== "10.") {
        if (substr($doi, 0, 2) === "0.") {
            $trial[] = "1" . $doi;
        } elseif (substr($doi, 0, 1) === ".") {
            $trial[] = "10" . $doi;
        } else {
            $trial[] = "10." . $doi;
        }
    }
    if (preg_match("~^(.+)(10\.\d{4,6}/.+)~", trim($doi), $match)) {
        $trial[] = $match[1];
        $trial[] = $match[2];
    }
    if (strpos($doi, '10.1093') === 0 && doi_works($doi) !== true) {
        if (preg_match('~^10\.1093/(?:ref:|)odnb/9780198614128\.001\.0001/odnb\-9780198614128\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/ref:odnb/' . $matches[1];
            $trial[] = '10.1093/odnb/9780198614128.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/odnb/(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/ref:odnb/' . $matches[1];
            $trial[] = '10.1093/odnb/9780198614128.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/ref:odnb/(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/odnb/9780198614128.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/9780198614128.013.(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/odnb/9780198614128.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/anb/9780198606697\.001\.0001/anb\-9780198606697\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/anb/9780198606697.article.' . $matches[1];
        }
        if (preg_match('~^10\.1093/benz/9780199773787\.001\.0001/acref-9780199773787\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/benz/9780199773787.article.B' . $matches[1];
        }
        if (preg_match('~^10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-7000(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/gao/9781884446054.article.T' . $matches[1];
        }
        if (preg_match('~^10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-700(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/gao/9781884446054.article.T' . $matches[1];
        }
        if (preg_match('~^10\.1093/acref/9780195301731\.001\.0001/acref\-9780195301731\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acref/9780195301731.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/ww/(9780199540891|9780199540884)\.001\.0001/ww\-9780199540884\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/ww/9780199540884.013.U' . $matches[2];
        }
        if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-00000(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/gmo/9781561592630.article.' . $matches[1];
        }
        if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-100(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/gmo/9781561592630.article.A' . $matches[1];
        }
        if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-5000(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/gmo/9781561592630.article.O' . $matches[1];
        }
        if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-400(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/gmo/9781561592630.article.L' . $matches[1];
        }
        if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-2000(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/gmo/9781561592630.article.J' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780199366439\.001\.0001/acrefore\-9780199366439\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780199366439.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780190228613\.001\.0001/acrefore\-9780190228613\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780190228613.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780199389414\.001\.0001/acrefore\-9780199389414\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780199389414.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780199329175\.001\.0001/acrefore\-9780199329175\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780199329175.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780190277734\.001\.0001/acrefore\-9780190277734\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780190277734.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780190846626\.001\.0001/acrefore\-9780190846626\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780190846626.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780190228620\.001\.0001/acrefore\-9780190228620\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780190228620.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780199340378\.001\.0001/acrefore\-9780199340378\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780199340378.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780190854584\.001\.0001/acrefore\-9780190854584\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780190854584.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780199381135\.001\.0001/acrefore\-9780199381135\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780199381135.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780190236557\.001\.0001/acrefore\-9780190236557\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780190236557.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780190228637\.001\.0001/acrefore\-9780190228637\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780190228637.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/acrefore/9780190201098\.001\.0001/acrefore\-9780190201098\-e\-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/acrefore/9780190201098.013.' . $matches[1];
        }
        if (preg_match('~^10\.1093/oso/(\d{13})\.001\.0001/oso\-(\d{13})\-chapter\-(\d+)$~', $doi, $matches)) {
            if ($matches[1] === $matches[2]) {
                $trial[] = '10.1093/oso/' . $matches[1] . '.003.' . str_pad($matches[3], 4, "0", STR_PAD_LEFT);
            }
        }
        if (preg_match('~^10\.1093/med/9780199592548\.001\.0001/med\-9780199592548-chapter-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/med/9780199592548.003.' . str_pad($matches[1], 4, "0", STR_PAD_LEFT);
        }
        if (preg_match('~^10\.1093/oso/(\d{13})\.001\.0001/oso\-(\d{13})$~', $doi, $matches)) {
            if ($matches[1] === $matches[2]) {
                $trial[] = '10.1093/oso/' . $matches[1] . '.001.0001';
            }
        }
        if (preg_match('~^10\.1093/oxfordhb/(\d{13})\.001\.0001/oxfordhb\-(\d{13})-e-(\d+)$~', $doi, $matches)) {
            if ($matches[1] === $matches[2]) {
                $trial[] = '10.1093/oxfordhb/' . $matches[1] . '.013.'  . $matches[3];
                $trial[] = '10.1093/oxfordhb/' . $matches[1] . '.013.0' . $matches[3];
                $trial[] = '10.1093/oxfordhb/' . $matches[1] . '.003.'  . $matches[3];
                $trial[] = '10.1093/oxfordhb/' . $matches[1] . '.003.0' . $matches[3];
            }
        }
    }
    $replacements = ["&lt;" => "<", "&gt;" => ">"];
    if (preg_match("~&[lg]t;~", $doi)) {
        $trial[] = str_replace(array_keys($replacements), $replacements, $doi);
    }
    $changed = true;
    $try = $doi;
    while ($changed) {
        $changed = false;
        $pos = strrpos($try, '.');
        if ($pos) {
            $extension = substr($try, $pos);
            if (in_array(strtolower($extension), DOI_BAD_ENDS, true)) {
                $try = substr($try, 0, $pos);
                $trial[] = $try;
                $changed = true;
            }
        }
        $pos = strrpos($try, '#');
        if ($pos) {
            $extension = substr($try, $pos);
            if (strpos(strtolower($extension), '#page_scan_tab_contents') === 0) {
                $try = substr($try, 0, $pos);
                $trial[] = $try;
                $changed = true;
            }
        }
        $pos = strrpos($try, ';');
        if ($pos) {
            $extension = substr($try, $pos);
            if (strpos(strtolower($extension), ';jsessionid') === 0) {
                $try = substr($try, 0, $pos);
                $trial[] = $try;
                $changed = true;
            }
        }
        $pos = strrpos($try, '/');
        if ($pos) {
            $extension = substr($try, $pos);
            if (in_array(strtolower($extension), DOI_BAD_ENDS2, true)) {
                $try = substr($try, 0, $pos);
                $trial[] = $try;
                $changed = true;
            }
        }
        if (preg_match('~^(.+)v\d{1,2}$~', $try, $matches)) { // Versions
            $try = $matches[1];
            $trial[] = $try;
            $changed = true;
        }
    }
    return $trial;
}

function clean_up_oxford_stuff(Template $template, string $param): void {
    if (preg_match('~^https?://(latinamericanhistory|classics|psychology|americanhistory|africanhistory|internationalstudies|climatescience|religion|environmentalscience|politics)\.oxfordre\.com(/.+)$~', $template->get($param), $matches)) {
        $template->set($param, 'https://oxfordre.com/' . $matches[1] . $matches[2]);
    }

    if (preg_match('~^(https?://(?:[\.+]|)oxfordre\.com)/([^/]+)/([^/]+)/([^/]+)/(.+)$~', $template->get($param), $matches)) {
        if ($matches[2] === $matches[3] && $matches[2] === $matches[4]) {
            $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[5]);
        } elseif ($matches[2] === $matches[3]) {
            $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[4] . '/' . $matches[5]);
        }
    }
    if (preg_match('~^(https?://(?:[\.+]|)oxfordmusiconline\.com)/([^/]+)/([^/]+)/([^/]+)/(.+)$~', $template->get($param), $matches)) {
        if ($matches[2] === $matches[3] && $matches[2] === $matches[4]) {
            $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[5]);
        } elseif ($matches[2] === $matches[3]) {
            $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[4] . '/' . $matches[5]);
        }
    }

    while (preg_match('~^(https?://www\.oxforddnb\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://www\.anb\.org/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://www\.oxfordartonline\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://www\.ukwhoswho\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://www\.oxfordmusiconline\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://oxfordre\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://oxfordaasc\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://oxford\.universitypressscholarship\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://oxfordreference\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    if (preg_match('~^https?://www\.oxforddnb\.com/view/10\.1093/(?:ref:|)odnb/9780198614128\.001\.0001/odnb\-9780198614128\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/ref:odnb/' . $matches[1];
        if (!doi_works($new_doi)) {
            $new_doi = '10.1093/odnb/9780198614128.013.' . $matches[1];
        }
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-861412-8');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
        $the_title = $template->get('title');
        if (preg_match('~^(.+) \- Oxford Dictionary of National Biography$~', $the_title, $matches) ||
                preg_match('~^(.+) # # # (?:CITATION_BOT_PLACEHOLDER_TEMPLATE|citation_bot_placeholder_template) \d+ # # # Oxford Dictionary of National Biography$~i', $the_title, $matches) ||
                preg_match('~^(.+)  Oxford Dictionary of National Biography$~', $the_title, $matches) ||
                preg_match('~^(.+) &#\d+; Oxford Dictionary of National Biography$~', $the_title, $matches)) {
            $template->set('title', trim($matches[1]));
        }
    }

    if (preg_match('~^https?://www\.anb\.org/(?:view|abstract)/10\.1093/anb/9780198606697\.001\.0001/anb\-9780198606697\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/anb/9780198606697.article.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-860669-7');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordartonline\.com/(?:benezit/|)(?:view|abstract)/10\.1093/benz/9780199773787\.001\.0001/acref-9780199773787\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/benz/9780199773787.article.B' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-977378-7');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }
    if (preg_match('~^https?://www\.oxfordartonline\.com/(?:groveart/|)(?:view|abstract)/10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-7000(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gao/9781884446054.article.T' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-884446-05-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }
    if (preg_match('~^https?://www\.oxfordartonline\.com/(?:groveart/|)(?:view|abstract)/10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-700(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gao/9781884446054.article.T' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-884446-05-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordaasc\.com/view/10\.1093/acref/9780195301731\.001\.0001/acref\-9780195301731\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acref/9780195301731.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-530173-1');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.ukwhoswho\.com/(?:view|abstract)/10\.1093/ww/(9780199540891|9780199540884)\.001\.0001/ww\-9780199540884\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/ww/9780199540884.013.U' . $matches[2];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', $matches[1]);
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-00000(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-100(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.A' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-5000(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.O' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-400(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.L' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-2000(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.J' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|latinamericanhistory/)(?:view|abstract)/10\.1093/acrefore/9780199366439\.001\.0001/acrefore\-9780199366439\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199366439.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-936643-9');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|communication/)(?:view|abstract)/10\.1093/acrefore/9780190228613\.001\.0001/acrefore\-9780190228613\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190228613.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-022861-3');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|environmentalscience/)(?:view|abstract)/10\.1093/acrefore/9780199389414\.001\.0001/acrefore\-9780199389414\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199389414.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-938941-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|americanhistory/)(?:view|abstract)/10\.1093/acrefore/9780199329175\.001\.0001/acrefore\-9780199329175\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199329175.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-932917-5');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|africanhistory/)(?:view|abstract)/10\.1093/acrefore/9780190277734\.001\.0001/acrefore\-9780190277734\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190277734.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-027773-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|internationalstudies/)(?:view|abstract)/10\.1093/acrefore/9780190846626\.001\.0001/acrefore\-9780190846626\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190846626.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-084662-6');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|climatescience/)(?:view|abstract)/10\.1093/acrefore/9780190228620\.001\.0001/acrefore\-9780190228620\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190228620.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-022862-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|religion/)(?:view|abstract)/10\.1093/acrefore/9780199340378\.001\.0001/acrefore\-9780199340378\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199340378.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-934037-8');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|anthropology/)(?:view|abstract)/10\.1093/acrefore/9780190854584\.001\.0001/acrefore\-9780190854584\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190854584.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-085458-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|classics/)(?:view|abstract)/10\.1093/acrefore/9780199381135\.001\.0001/acrefore\-9780199381135\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199381135.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-938113-5');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|psychology/)(?:view|abstract)/10\.1093/acrefore/9780190236557\.001\.0001/acrefore\-9780190236557\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190236557.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-023655-7');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|politics/)(?:view|abstract)/10\.1093/acrefore/9780190228637\.001\.0001/acrefore\-9780190228637\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190228637.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-022863-7');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|literature/)(?:view|abstract)/10\.1093/acrefore/9780190201098\.001\.0001/acrefore\-9780190201098\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190201098.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-020109-8');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxford\.universitypressscholarship\.com/(?:view|abstract)/10\.1093/(oso|acprof:oso)/(\d{13})\.001\.0001/oso\-(\d{13})\-chapter\-(\d+)$~', $template->get($param), $matches)) {
        if ($matches[2] === $matches[3]) {
            $template->add_if_new('isbn', $matches[2]);
            $new_doi = '10.1093/' . $matches[1] . '/' . $matches[2] . '.003.' . str_pad($matches[4], 4, "0", STR_PAD_LEFT);
            if (doi_works($new_doi)) {
                if ($template->has('doi') && $template->has('doi-broken-date')) {
                    $template->set('doi', '');
                    $template->forget('doi-broken-date');
                    $template->add_if_new('doi', $new_doi);
                } elseif ($template->blank('doi')) {
                    $template->add_if_new('doi', $new_doi);
                }
            }
        }
    }

    if (preg_match('~^https?://(?:www\.|)oxfordmedicine\.com/(?:view|abstract)/10\.1093/med/9780199592548\.001\.0001/med\-9780199592548-chapter-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/med/9780199592548.003.' . str_pad($matches[1], 4, "0", STR_PAD_LEFT);
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-959254-8');
            if ($template->has('doi') && ($template->has('doi-broken-date') || $template->get('doi') === '10.1093/med/9780199592548.001.0001')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxford\.universitypressscholarship\.com/(?:view|abstract)/10\.1093/oso/(\d{13})\.001\.0001/oso\-(\d{13})$~', $template->get($param), $matches)) {
        if ($matches[1] === $matches[2]) {
            $template->add_if_new('isbn', $matches[1]);
            $new_doi = '10.1093/oso/' . $matches[1] . '.001.0001';
            if (doi_works($new_doi)) {
                if ($template->has('doi') && $template->has('doi-broken-date')) {
                    $template->set('doi', '');
                    $template->forget('doi-broken-date');
                    $template->add_if_new('doi', $new_doi);
                } elseif ($template->blank('doi')) {
                    $template->add_if_new('doi', $new_doi);
                }
            }
        }
    }

    if (preg_match('~^https?://(?:www\.|)oxfordhandbooks\.com/(?:view|abstract)/10\.1093/oxfordhb/(\d{13})\.001\.0001/oxfordhb\-(\d{13})-e-(\d+)$~', $template->get($param), $matches)) {
        if ($matches[1] === $matches[2]) {
            $new_doi = '10.1093/oxfordhb/' . $matches[1] . '.013.' . $matches[3];
            if (doi_works($new_doi)) {
                $template->add_if_new('isbn', $matches[1]);
                if (($template->has('doi') && $template->has('doi-broken-date')) || ($template->get('doi') === '10.1093/oxfordhb/9780199552238.001.0001')) {
                    $template->set('doi', '');
                    $template->forget('doi-broken-date');
                    $template->add_if_new('doi', $new_doi);
                } elseif ($template->blank('doi')) {
                    $template->add_if_new('doi', $new_doi);
                }
            }
        }
    }
}

function conference_doi(string $doi): bool {
    if (stripos($doi, '10.1007/978-3-662-44777') === 0) {
        return false; // Manual override of stuff
    }
    if (strpos($doi, '10.1109/') === 0 ||
        strpos($doi, '10.1145/') === 0 ||
        strpos($doi, '10.1117/') === 0 ||
        strpos($doi, '10.2991/') === 0 ||
        stripos($doi, '10.21437/Eurospeech') === 0 ||
        stripos($doi, '10.21437/interspeech') === 0 ||
        stripos($doi, '10.21437/SLTU') === 0 ||
        stripos($doi, '10.21437/TAL') === 0 ||
        (strpos($doi, '10.1007/978-') === 0 && strpos($doi, '_') !== false) ||
        stripos($doi, '10.2991/erss') === 0 ||
        stripos($doi, '10.2991/jahp') === 0) {
        return true;
    }
    return false;
}

function clean_dates(string $input): string { // See https://en.wikipedia.org/wiki/Help:CS1_errors#bad_date
    if ($input === '0001-11-30') {
        return '';
    }
    $input = str_ireplace(MONTH_SEASONS, MONTH_SEASONS, $input); // capitalization
    if (preg_match('~^(\d{4})[\-\/](\d{4})$~', $input, $matches)) { // Hyphen or slash in year range (use en dash)
        return $matches[1] . '–' . $matches[2];
    }
    if (preg_match('~^(\d{4})\/ed$~i', $input, $matches)) { // 2002/ed
        return $matches[1];
    }
    if (preg_match('~^First published(?: |\: | in | in\: | in\:)(\d{4})$~i', $input, $matches)) { // First published: 2002
        return $matches[1];
    }
    if (preg_match('~^([A-Z][a-z]+)[\-\/]([A-Z][a-z]+) (\d{4})$~', $input, $matches)) { // Slash or hyphen in date range (use en dash)
        return $matches[1] . '–' . $matches[2] . ' ' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+ \d{4})[\-\–]([A-Z][a-z]+ \d{4})$~', $input, $matches)) { // Missing space around en dash for range of full dates
        return $matches[1] . ' – ' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+), (\d{4})$~', $input, $matches)) { // Comma with month/season and year
        return $matches[1] . ' ' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+), (\d{4})[\-\–](\d{4})$~', $input, $matches)) { // Comma with month/season and years
        return $matches[1] . ' ' . $matches[2] . '–' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+) 0(\d),? (\d{4})$~', $input, $matches)) { // Zero-padding
        return $matches[1] . ' ' . $matches[2] . ', ' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+ \d{1,2})( \d{4})$~', $input, $matches)) { // Missing comma in format which requires it
        return $matches[1] . ',' . $matches[2];
    }
    if (preg_match('~^Collected[\s\:]+((?:|[A-Z][a-z]+ )\d{4})$~', $input, $matches)) { // Collected 1999 stuff
        return $matches[1];
    }
    if (preg_match('~^Effective[\s\:]+((?:|[A-Z][a-z]+ )\d{4})$~', $input, $matches)) { // Effective 1999 stuff
        return $matches[1];
    }
    if (preg_match('~^(\d+ [A-Z][a-z]+ \d{4})\.$~', $input, $matches)) { // 8 December 2022. (period on end)
        return $matches[1];
    }
    if (preg_match('~^0(\d [A-Z][a-z]+ \d{4})$~', $input, $matches)) { // 08 December 2022 - leading zero
        return $matches[1];
    }
    if (preg_match('~^([A-Z][a-z]+)\, ([A-Z][a-z]+ \d+,* \d{4})$~', $input, $matches)) { // Monday, November 2, 1981
        if (in_array($matches[1], DAYS_OF_WEEKS, true)) {
            return $matches[2];
        }
    }
    if (preg_match('~^(\d{4})\s*(?:&|and)\s*(\d{4})$~', $input, $matches)) { // &/and between years
        $first = (int) $matches[1];
        $second = (int) $matches[2];
        if ($second === $first+1) {
            return $matches[1] . '–' . $matches[2];
        }
    }

    if (preg_match('~^(\d{4})\-(\d{2})$~', $input, $matches) && in_array(WIKI_BASE, ENGLISH_WIKI, true)) { // 2020-12 i.e. backwards
        $year = $matches[1];
        $month = (int) $matches[2];
        if ($month > 0 && $month < 13) {
            return MONTH_SEASONS[$month-1] . ' ' . $year;
        }
    }
    return $input;
}

/** @return false|array<string|array<string>> */
function get_headers_array(string $url): false|array {
    static $last_url = "none yet";
    // Allow cheap journals to work
    static $context_insecure_doi;
    static $context_insecure_hdl;
    if (!isset($context_insecure_doi)) {
        $timeout = BOT_HTTP_TIMEOUT * 1.0;
        if (TRAVIS) {
            $timeout = 5.0; // Give up fast
        }
        $context_insecure_doi = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true, 'security_level' => 0, 'verify_depth' => 0],
            'http' => ['ignore_errors' => true, 'max_redirects' => 40, 'timeout' => $timeout, 'follow_location' => 1, "user_agent" => BOT_USER_AGENT],
        ]);
        $timeout = BOT_HTTP_TIMEOUT * 2.5; // Handles suck
        $context_insecure_hdl = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true, 'security_level' => 0, 'verify_depth' => 0],
            'http' => ['ignore_errors' => true, 'max_redirects' => 40, 'timeout' => $timeout, 'follow_location' => 1, "user_agent" => BOT_USER_AGENT],
        ]);
    }
    set_time_limit(120);
    if ($last_url === $url) {
        sleep(5);
    }
    $last_url = $url;
    if (strpos($url, 'https://doi.org') === 0) {
        return @get_headers($url, true, $context_insecure_doi);
    } elseif (strpos($url, 'https://hdl.handle.net') === 0) {
        return @get_headers($url, true, $context_insecure_hdl);
    } else {
        report_error("BAD URL in get_headers_array"); // @codeCoverageIgnore
    }
}

function simplify_google_search(string $url): string {
    if (stripos($url, 'q=') === false) {
        return $url;     // Not a search
    }
    if (preg_match('~^https?://.*google.com/search/~', $url)) {
        return $url; // Not a search if the slash is there
    }
    $hash = '';
    if (strpos($url, "#")) {
        $url_parts = explode("#", $url, 2);
        $url = $url_parts[0];
        $hash = "#" . $url_parts[1];
    }

    $url_parts = explode("&", str_replace("&&", "&", str_replace("?", "&", $url)));
    array_shift($url_parts);
    $url = "https://www.google.com/search?";

    foreach ($url_parts as $part) {
        $part_start = explode("=", $part, 2);
        $part_start0 = $part_start[0];
        if (isset($part_start[1]) && $part_start[1] === '') {
            $part_start0 = "donotaddmeback"; // Do not add blank ones
            $part_start1 = '';
            $it_is_blank = true;
        } elseif (empty($part_start[1])) {
            $part_start1 = '';
            $it_is_blank = true;
        } else {
            $part_start1 = $part_start[1];
            $it_is_blank = false;
        }
        switch ($part_start0) {
            // Stuff that gets dropped
            case "aq":
            case "aqi":
            case "bih":
            case "biw":
            case "client":
            case "as":
            case "useragent":
            case "as_brr":
            case "ei":
            case "ots":
            case "sig":
            case "source":
            case "lr":
            case "sa":
            case "oi":
            case "ct":
            case "id":
            case "cd":
            case "oq":
            case "rls":
            case "sourceid":
            case "ved":
            case "aqs":
            case "gs_l":
            case "uact":
            case "tbo":
            case "tbs":
            case "num":
            case "redir_esc":
            case "gs_lcp":
            case "sxsrf":
            case "gfe_rd":
            case "gws_rd":
            case "rlz":
            case "sclient":
            case "prmd":
            case "dpr":
            case "newwindow":
            case "gs_ssp":
            case "spell":
            case "shndl":
            case "sugexp":
            case "donotaddmeback":
            case "usg":
            case "fir":
            case "entrypoint":
            case "as_qdr":
            case "as_drrb":
            case "as_minm":
            case "as_mind":
            case "as_maxm":
            case "as_maxd":
            case "kgs":
            case "ictx":
            case "shem":
            case "vet":
            case "iflsig":
            case "tab":
            case "sqi":
            case "noj":
            case "hs":
            case "es_sm":
            case "site":
            case "btnmeta_news_search":
            case "channel":
            case "espv":
            case "cad":
            case "gs_sm":
            case "imgil":
            case "ins":
            case "npsic=":
            case "rflfq":
            case "lei":
            case "rlha":
            case "rldoc":
            case "rldimm":
            case "npsic":
            case "phdesc":
            case "prmdo":
            case "ssui":
            case "lqi":
            case "rlst":
            case "pf":
            case "authuser":
            case "gsas":
            case "ned":
            case "pz":
            case "e":
            case "surl":
            case "aql":
            case "gs_lcrp":
            case "sca_esv":
                break;
            case "as_occt":
                if ($it_is_blank || str_i_same($part_start1, 'any')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "cf":
                if ($it_is_blank || str_i_same($part_start1, 'all')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "cs":
                if ($it_is_blank || str_i_same($part_start1, '0')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "btnK":
                if ($it_is_blank || str_i_same($part_start1, 'Google+Search')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "as_epq":
                if ($it_is_blank) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "btnG":
                if ($it_is_blank || str_i_same($part_start1, 'Search')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "rct":
                if ($it_is_blank || str_i_same($part_start1, 'j')) {
                    break; // default
                }
                $url .=  $part . "&" ;
                break;
            case "resnum":
                if ($it_is_blank || str_i_same($part_start1, '11')) {
                    break; // default
                }
                $url .=  $part . "&" ;
                break;
            case "ie":
            case "oe":
                if ($it_is_blank || str_i_same($part_start1, 'utf-8')) {
                    break; // UTF-8 is the default
                }
                $url .=  $part . "&" ;
                break;
            case "hl":
            case "safe":
            case "q":
            case "tbm":
            case "start":
            case "ludocid":
            case "cshid":
            case "stick":
            case "as_eq":
            case "kgmid":
            case "as_drrb":
            case "gbv":
            case "as_scoring":
            case "gl":
            case "rllag":
            case "lsig":
            case "lpsid":
            case "as_q":
            case "kponly":
                $url .=  $part . "&" ;
                break;
            default:
            // @codeCoverageIgnoreStart
                report_minor_error("Unexpected Google URL component:    " . echoable($part));
                $url .=  $part . "&" ;
                break;
            // @codeCoverageIgnoreEnd
        }
    }

    if (substr($url, -1) === "&") {
        $url = substr($url, 0, -1); //remove trailing &
    }
    $url .= $hash;
    return $url;
}

function addISBNdashes(string $isbn): string {
    if (substr_count($isbn, '-') > 1) {
        return $isbn;
    }
    $new = str_replace('-', '', $isbn);
    if (strlen($new) === 10) {
        $num = 9780000000000 + (int) str_ireplace('x', '9', $new);
        foreach (ISBN_HYPHEN_POS as $k => $v) {
            if ($num <= (int) $k) {
                $split = $v;
                break;
            }
        }
        if (!isset($split)) {
            return $isbn; // Paranoid
        }
        $v = $split;
        return substr($new, 0, $v[0]) . '-' . substr($new, $v[0], $v[1]) . '-' . substr($new, $v[0]+$v[1], $v[2]) . '-' . substr($new, $v[0]+$v[1]+$v[2], 1) ;
        // split = SKIP3, $v[0], $v[1], $v[2], 1
    } elseif (strlen($new) === 13) {
        $num = (int) $new;
        foreach (ISBN_HYPHEN_POS as $k => $v) {
            if ($num <= (int) $k) {
                $split = $v;
                break;
            }
        }
        if (!isset($split)) {
            return $isbn; // Paranoid
        }
        $v = $split;
        return substr($new, 0, 3) . '-' . substr($new, 3, $v[0]) . '-' . substr($new, 3+$v[0], $v[1]) . '-' . substr($new, 3+$v[0]+$v[1], $v[2]) . '-' . substr($new, 3+$v[0]+$v[1]+$v[2], 1) ;
        // split = 3, $v[0], $v[1], $v[2], 1
    } else {
        return $isbn;
    }
}

function string_is_book_series(string $str): bool {
    $simple = trim(str_replace(['-', '.', '   ', '  ', '[[', ']]'], [' ', ' ', ' ', ' ', ' ', ' '], strtolower($str)));
    $simple = trim(str_replace(['    ', '   ', '  '], [' ', ' ', ' '], $simple));
    return in_array($simple, JOURNAL_IS_BOOK_SERIES, true);
}

function echoable_doi(string $doi): string {
    return str_ireplace(['&lt;', '&gt;'], ['<', '>'], echoable($doi));
}
