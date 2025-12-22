<?php

declare(strict_types=1);

final class HandleCache {
    // Greatly speed-up by having one array of each kind and only look for hash keys, not values
    private const MAX_CACHE_SIZE = 100000;
    public const MAX_HDL_SIZE = 1024;

    /** @var array<bool> */
    public static array $cache_active = [];             // DOI is in CrossRef, no claims if it still works.
    /** @var array<bool> */
    public static array $cache_inactive = BAD_DOI_ARRAY;// DOI is not in CrossRef
    /** @var array<bool> */
    public static array $cache_good = [];               // DOI works
    /** @var array<string> */
    public static array $cache_hdl_loc = [];            // Final HDL location URL
    /** @var array<bool> */
    public static array $cache_hdl_bad = BAD_DOI_ARRAY; // HDL/DOI does not resolve to anything
    /** @var array<bool> */
    public static array $cache_hdl_null = [];           // HDL/DOI resolves to null

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {
        // This is a static class
    }

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

// phpcs:ignore MediaWiki.Commenting.FunctionComment.WrongStyle
function doi_active(string $doi): ?bool { // Does not reflect if DOI works, but if CrossRef has data
    $doi = mb_trim($doi);
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
    $doi = mb_trim($doi);
    if (TRUST_DOI_GOOD && isset(NULL_DOI_BUT_GOOD[$doi])) {
        return true;
    }
    if (isset(NULL_DOI_ANNOYING[$doi])) {
        return false;
    }
    if (!TRAVIS) {
        foreach (NULL_DOI_STARTS_BAD as $bad_start) { // @codeCoverageIgnoreStart
            if (mb_stripos($doi, $bad_start) === 0) {
                return false; // all gone
            }
        }                                             // @codeCoverageIgnoreEnd
    }
    if (mb_strlen($doi) > HandleCache::MAX_HDL_SIZE) {
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
    $doi = mb_trim($doi);
    $url = "https://api.crossref.org/v1/works/" . doi_encode($doi) . "?mailto=".CROSSREFUSERNAME; // do not encode crossref email
    curl_setopt($ch, CURLOPT_URL, $url);
    $return = bot_curl_exec($ch);
    $header_length = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE); // Byte count, not characters
    $header = substr($return, 0, $header_length);    // phpcs:ignore
    $body = substr($return, $header_length);         // phpcs:ignore
    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($header === "" || ($response_code === 503) || ($response_code === 429)) {
        sleep(4);                                                             // @codeCoverageIgnoreStart
        if ($response_code === 429) {
            sleep(4);  // WE are getting blocked
        }
        $return = bot_curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $header_length = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE); // Byte count, not characters
        $header = substr($return, 0, $header_length);  // phpcs:ignore
        $body = substr($return, $header_length);       // phpcs:ignore
    }                                                                       // @codeCoverageIgnoreEnd
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

function is_doi_works(string $doi): ?bool {
    $doi = mb_trim($doi);
    // And now some obvious fails
    if (mb_strpos($doi, '/') === false){
        return false;
    }
    if (mb_strpos($doi, 'CITATION_BOT_PLACEHOLDER') !== false) {
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
        if (mb_strpos($doi, '10.1353/') === 0) {
            return true; // TODO - muse is annoying
        } elseif (mb_strpos($doi, '10.1175/') === 0) {
            return true; // TODO - American Meteorological Society is annoying
        }
    }

    $registrant = $matches[1];
    // TODO this will need updated over time.    See registrant_err_patterns on https://en.wikipedia.org/wiki/Module:Citation/CS1/Identifiers
    // 17 August 2024 version is last check
    if (mb_strpos($registrant, '10.') === 0) { // We have to deal with valid handles in the DOI field - very rare, so only check actual DOIs
        $registrant = mb_substr($registrant, 3);
        if (preg_match('~^[^1-3]\d\d\d\d\.\d\d*$~', $registrant) || // 5 digits with subcode (0xxxx, 40000+); accepts: 10000–39999
                preg_match('~^[^1-7]\d\d\d\d$~', $registrant) || // 5 digits without subcode (0xxxx, 60000+); accepts: 10000–69999
                preg_match('~^[^1-9]\d\d\d\.\d\d*$~', $registrant) || // 4 digits with subcode (0xxx); accepts: 1000–9999
                preg_match('~^[^1-9]\d\d\d$~', $registrant) || // 4 digits without subcode (0xxx); accepts: 1000–9999
                preg_match('~^\d\d\d\d\d\d+~', $registrant) || // 6 or more digits
                preg_match('~^\d\d?\d?$~', $registrant) || // less than 4 digits without subcode (3 digits with subcode is legitimate)
                preg_match('~^\d\d?\.[\d\.]+~', $registrant) || // 1 or 2 digits with subcode
                $registrant === '5555' || // test registrant will never resolve
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
            if (mb_stripos($doi, $bad_start) === 0) {
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
    if (mb_stripos($doi, '10.1126/scidip.') === 0) {
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

    if (mb_strpos($resp0, '302') !== false && mb_strpos($resp1, '301') !== false && mb_strpos($resp2, '404') !== false) {
        if (isset(NULL_DOI_LIST[$doi])) {
            return false;
        }
        if (isset(NULL_DOI_BUT_GOOD[$doi])) {
            return true;
        }
        bot_debug_log('Got weird stuff for HDL: ' . echoable_doi($doi));
        return null;
    }
    if (mb_strpos($resp0, '302') !== false && mb_strpos($resp1, '503') !== false && $resp2 === '') {
        if (isset(NULL_DOI_LIST[$doi])) {
            return false;
        }
        if (isset(NULL_DOI_BUT_GOOD[$doi])) {
            return true;
        }
        bot_debug_log('Got two bad hops for HDL: ' . echoable_doi($doi));
        return null;
    }
    if (mb_stripos($resp0 . $resp1 . $resp2, '404 Not Found') !== false || mb_stripos($resp0 . $resp1 . $resp2, 'HTTP/1.1 404') !== false) {
        return false; // Bad
    }
    if (mb_stripos($resp0, '302 Found') !== false || mb_stripos($resp0, 'HTTP/1.1 302') !== false) {
        return true;    // Good
    }
    if (mb_stripos((string) @json_encode($headers_test), 'dtic.mil') !== false) { // grumpy
        return true;  // @codeCoverageIgnore
    }
    if (mb_stripos($resp0, '301 Moved Permanently') !== false || mb_stripos($resp0, 'HTTP/1.1 301') !== false) { // Could be DOI change or bad prefix
        if (mb_stripos($resp1, '302 Found') !== false || mb_stripos($resp1, 'HTTP/1.1 302') !== false) {
            return true;    // Good
        } elseif (mb_stripos($resp1, '301 Moved Permanently') !== false || mb_stripos($resp1, 'HTTP/1.1 301') !== false) {        // @codeCoverageIgnoreStart
            if (mb_stripos($resp2, '200 OK') !== false || mb_stripos($resp2, 'HTTP/1.1 200') !== false) {
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
        bot_debug_log("Got weird header from a handle");    // Have NEVER seen this - do not log/print since probably crazy text
        return null;
    }                // @codeCoverageIgnoreEnd
}

function sanitize_doi(string $doi): string {
    if (mb_substr($doi, -1) === '.') {
        $try_doi = mb_substr($doi, 0, -1);
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
    $doi = str_replace(HTML_ENCODE_DOI, HTML_DECODE_DOI, mb_trim(urldecode($doi)));
    $pos = (int) mb_strrpos($doi, '.');
    if ($pos) {
        $extension = (string) mb_substr($doi, $pos);
        if (in_array(mb_strtolower($extension), DOI_BAD_ENDS, true)) {
            $doi = (string) mb_substr($doi, 0, $pos);
        }
    }
    $pos = (int) mb_strrpos($doi, '#');
    if ($pos) {
        $extension = (string) mb_substr($doi, $pos);
        if (mb_strpos(mb_strtolower($extension), '#page_scan_tab_contents') === 0) {
            $doi = (string) mb_substr($doi, 0, $pos);
        }
    }
    $pos = (int) mb_strrpos($doi, ';');
    if ($pos) {
        $extension = (string) mb_substr($doi, $pos);
        if (mb_strpos(mb_strtolower($extension), ';jsessionid') === 0) {
            $doi = (string) mb_substr($doi, 0, $pos);
        }
    }
    $pos = (int) mb_strrpos($doi, '/');
    if ($pos) {
        $extension = (string) mb_substr($doi, $pos);
        if (in_array(mb_strtolower($extension), DOI_BAD_ENDS2, true)) {
            $doi = (string) mb_substr($doi, 0, $pos);
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
    if (mb_strpos($doi, '10.1093') === 0 && doi_works($doi) === false) {
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

/**
 * @return array<string> Returns an array containing:
 * 0 => text containing a DOI, possibly encoded, possibly with additional text.
 * 1 => the decoded DOI
 */
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
                $delimiter_position = (int) mb_strrpos($doi_candidate, $delimiter);
                $last_delimiter = ($delimiter_position > $last_delimiter) ? $delimiter_position : $last_delimiter;
            }
            $doi_candidate = mb_substr($doi_candidate, 0, $last_delimiter);
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

function not_bad_10_1093_doi(string $url): bool { // We assume DOIs are bad, unless on good list
    if ($url === '') {
        return true;
    }
    if(!preg_match('~10.1093/([^/]+)/~u', $url, $match)) {
        return true;
    }
    $test = mb_strtolower($match[1]);
    // March 2019 Good list
    if (in_array($test, GOOD_10_1093_DOIS, true)) {
        return true;
    }
    return false;
}

function bad_10_1093_doi(string $url): bool {
    return !not_bad_10_1093_doi($url);
}

    /** Returns null/false/String of location */
function is_hdl_works(string $hdl): string|null|false {
    $hdl = mb_trim($hdl);
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

function conference_doi(string $doi): bool {
    if (mb_stripos($doi, '10.1007/978-3-662-44777') === 0) {
        return false; // Manual override of stuff
    }
    if (mb_strpos($doi, '10.1109/') === 0 ||
        mb_strpos($doi, '10.1145/') === 0 ||
        mb_strpos($doi, '10.1117/') === 0 ||
        mb_strpos($doi, '10.2991/') === 0 ||
        mb_stripos($doi, '10.21437/Eurospeech') === 0 ||
        mb_stripos($doi, '10.21437/interspeech') === 0 ||
        mb_stripos($doi, '10.21437/SLTU') === 0 ||
        mb_stripos($doi, '10.21437/TAL') === 0 ||
        (mb_strpos($doi, '10.1007/978-') === 0 && mb_strpos($doi, '_') !== false) ||
        mb_stripos($doi, '10.2991/erss') === 0 ||
        mb_stripos($doi, '10.2991/jahp') === 0) {
        return true;
    }
    return false;
}

/** null/false/String of location */
function hdl_works(string $hdl): string|null|false {
    $hdl = mb_trim($hdl);
    $hdl = str_replace('%2F', '/', $hdl);
    // And now some obvious fails
    if (mb_strpos($hdl, '/') === false) {
        return false;
    }
    if (mb_strpos($hdl, 'CITATION_BOT_PLACEHOLDER') !== false) {
        return false;
    }
    if (mb_strpos($hdl, '123456789') === 0) {
        return false;
    }
    if (mb_strlen($hdl) > HandleCache::MAX_HDL_SIZE) {
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
    if (mb_strpos($hdl, '10.') === 0 && doi_works($hdl) === false) {
        return false;
    }
    $works = is_hdl_works($hdl);
    if ($works === null) {
        if (isset(NULL_DOI_LIST[$hdl])) {                // @codeCoverageIgnoreStart
            HandleCache::$cache_hdl_bad[$hdl] = true;    // These are know to be bad, so only check one time during run
            return false;
        }
        foreach (NULL_DOI_STARTS_BAD as $bad_start) {
            if (mb_stripos($hdl, $bad_start) === 0) {
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

/** @return array<string> */
function get_possible_dois(string $doi): array {
    $trial = [];
    $trial[] = $doi;
    // DOI not correctly formatted
    switch (mb_substr($doi, -1)) {
        case ".":
            // Missing a terminal 'x'?
            $trial[] = $doi . "x";
            $trial[] = mb_substr($doi, 0, -1);
            break;
        case ",":
        case ";":
        case "\"":
            // Or is this extra punctuation copied in?
            $trial[] = mb_substr($doi, 0, -1);
    }
    if (mb_substr($doi, -4) === '</a>' || mb_substr($doi, -4) === '</A>') {
        $trial[] = mb_substr($doi, 0, -4);
    }
    if (mb_substr($doi, 0, 3) !== "10.") {
        if (mb_substr($doi, 0, 2) === "0.") {
            $trial[] = "1" . $doi;
        } elseif (mb_substr($doi, 0, 1) === ".") {
            $trial[] = "10" . $doi;
        } else {
            $trial[] = "10." . $doi;
        }
    }
    if (preg_match("~^(.+)(10\.\d{4,6}/.+)~", mb_trim($doi), $match)) {
        $trial[] = $match[1];
        $trial[] = $match[2];
    }
    if (mb_strpos($doi, '10.1093') === 0 && doi_works($doi) !== true) {
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
                $trial[] = '10.1093/oso/' . $matches[1] . '.003.' . mb_str_pad($matches[3], 4, "0", STR_PAD_LEFT);
            }
        }
        if (preg_match('~^10\.1093/med/9780199592548\.001\.0001/med\-9780199592548-chapter-(\d+)$~', $doi, $matches)) {
            $trial[] = '10.1093/med/9780199592548.003.' . mb_str_pad($matches[1], 4, "0", STR_PAD_LEFT);
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
        $pos = mb_strrpos($try, '.');
        if ($pos) {
            $extension = mb_substr($try, $pos);
            if (in_array(mb_strtolower($extension), DOI_BAD_ENDS, true)) {
                $try = mb_substr($try, 0, $pos);
                $trial[] = $try;
                $changed = true;
            }
        }
        $pos = mb_strrpos($try, '#');
        if ($pos) {
            $extension = mb_substr($try, $pos);
            if (mb_strpos(mb_strtolower($extension), '#page_scan_tab_contents') === 0) {
                $try = mb_substr($try, 0, $pos);
                $trial[] = $try;
                $changed = true;
            }
        }
        $pos = mb_strrpos($try, ';');
        if ($pos) {
            $extension = mb_substr($try, $pos);
            if (mb_strpos(mb_strtolower($extension), ';jsessionid') === 0) {
                $try = mb_substr($try, 0, $pos);
                $trial[] = $try;
                $changed = true;
            }
        }
        $pos = mb_strrpos($try, '/');
        if ($pos) {
            $extension = mb_substr($try, $pos);
            if (in_array(mb_strtolower($extension), DOI_BAD_ENDS2, true)) {
                $try = mb_substr($try, 0, $pos);
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

function check_doi_for_jstor(string $doi, Template $template): void {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, []);
    }
    if ($template->has('jstor')) {
        return;
    }
    /** @psalm-taint-escape ssrf */
    $doi = mb_trim($doi);
    if ($doi === '') {
        return;
    }
    if (preg_match('~^\d+$~', $doi)) {
        return; // Just numbers - this WILL match a JSTOR, but who knows what it really is!
    }
    if (mb_strpos($doi, '10.2307') === 0) { // special case
        $doi = mb_substr($doi, 8);
    }
    $pos = mb_strpos($doi, '?');
    if ($pos) {
            $doi = mb_substr($doi, 0, $pos);
    }
    curl_setopt($ch, CURLOPT_URL, "https://www.jstor.org/citation/ris/" . $doi);
    $ris = bot_curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode === 200 &&
            mb_stripos($ris, $doi) !== false &&
            mb_strpos($ris, 'Provider') !== false &&
            mb_stripos($ris, 'No RIS data found for') === false &&
            mb_stripos($ris, 'Block Reference') === false &&
            mb_stripos($ris, 'A problem occurred trying to deliver RIS data') === false &&
            mb_substr_count($ris, '-') > 3) { // It is actually a working JSTOR
        $template->add_if_new('jstor', $doi);
    }
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
    if (mb_strpos($url, 'https://doi.org') === 0) {
        return @get_headers($url, true, $context_insecure_doi);
    } elseif (mb_strpos($url, 'https://hdl.handle.net') === 0) {
        return @get_headers($url, true, $context_insecure_hdl);
    } else {
        report_error("BAD URL in get_headers_array"); // @codeCoverageIgnore
    }
}

function doi_is_bad (string $doi): bool {
    $doi = mb_strtolower($doi);
    if ($doi === '10.5284/1000184' || // DOI for the entire database
        $doi === '10.1267/science.040579197' || //  PMID test doi
        $doi === '10.2307/3511692' || // common review
        $doi === '10.1377/forefront' || // over-truncated
        $doi === '10.1126/science' || // over-truncated
        $doi === '10.1111/j' || // over-truncated
        $doi === '10.3138/j' || // over-truncated
        $doi === '10.7556/jaoa' || // over-truncated
        $doi === '10.7591/j' || // over-truncated
        $doi === '10.7722/j' || // over-truncated
        $doi === '10.1002/bies' || // over-truncated
        $doi === '10.1002/job' || // over-truncated
        $doi === '10.5465/ame' || // over-truncated
        $doi === '10.5465/amp' || // over-truncated
        $doi === '10.3316/ielapa' || // over-truncated
        $doi === '10.3316/informit' || // over-truncated
        $doi === '10.1023/b:boli' || // over-truncated
        $doi === '10.1023/b:cyto' || // over-truncated
        $doi === '10.1023/b:land' || // over-truncated
        $doi === '10.1093/acrefore' || // over-truncated
        $doi === '10.1093/acref' || // over-truncated
        $doi === '10.1093/gao' || // over-truncated
        $doi === '10.1093/gmo' || // over-truncated
        $doi === '10.1093/nsr' || // over-truncated
        $doi === '10.1093/oi' || // over-truncated
        $doi === '10.1093/logcom' || // over-truncated
        $doi === '10.1111/bjep' || // over-truncated
        $doi === '10.1146/annurev' || // over-truncated
        $doi === '10.1093/oi/authority' || // over-truncated
        $doi === '10.1377/forefront' || // over-truncated
        $doi === '10.3905/jpm' || // over-truncated
        (mb_strpos($doi, '10.0000/') === 0 && !TRAVIS) || // just urls that look like DOIs - TODO: Fix test suite
        mb_strpos($doi, '10.5779/hypothesis') === 0 || // SPAM took over
        mb_strpos($doi, '10.5555/') === 0 || // Test DOI prefix
        mb_strpos($doi, '10.5860/choice.') === 0 || // Paywalled book review
        mb_strpos($doi, '10.1093/law:epil') === 0 || // Those do not work
        mb_strpos($doi, '10.1093/oi/authority') === 0 || // Those do not work
        (mb_strpos($doi, '10.10520/') === 0 && !doi_works($doi)) || // Has doi in the URL, but is not a doi
        (mb_strpos($doi, '10.1967/') === 0 && !doi_works($doi)) || // Retired DOIs
        (mb_strpos($doi, '10.1043/0003-3219(') === 0 && !doi_works($doi)) || // Per-email.  The Angle Orthodontist will NEVER do these, since they have <> and [] in them
        (mb_strpos($doi, '10.3316/') === 0 && !doi_works($doi)) || // These do not work - https://search.informit.org/doi/10.3316/aeipt.207729 etc.
        (mb_strpos($doi, '10.1002/was.') === 0 && !doi_works($doi)) || // do's not doi's
        mb_strpos($doi, '10.48550/arxiv') === 0 || // ignore
        preg_match(REGEXP_DOI_ISSN_ONLY, $doi) // Journal landing page
       ) {
        return true;
    }
    return false;
}
