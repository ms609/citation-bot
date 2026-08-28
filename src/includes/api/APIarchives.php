<?php

declare(strict_types=1);

const ARCHIVE_FETCH_MAX_REDIRECTS = 5;
const ARCHIVE_TITLE_SCAN_MAX_BYTES = 4 * 1024 * 1024;

/** @var list<string> */
const ARCHIVE_FETCH_HOSTS = [
    'archive-it.org',
    'archive.fo',
    'archive.is',
    'archive.md',
    'archive.org',
    'archive.ph',
    'archive.today',
    'archive.wikiwix.com',
    'ghostarchive.org',
    'perma-archives.org',
    'perma.cc',
    'wayback.archive-it.org',
    'waybackmachine.org',
    'web.archive.bibalex.org',
    'web.archive.org',
    'web.petabox.bibalex.org',
    'webarchive.loc.gov',
    'webarchive.nla.gov.au',
    'webarchive.org.uk',
    'webarchive.proni.gov.uk',
    'webcitation.org',
    'webharvest.gov',
    'www.archive.org',
    'www.archive-it.org',
    'www.webarchive.org.uk',
    'www.webcitation.org',
];

/**
 * Limit server-side title lookups to the archival services that this feature supports.
 */
function archive_url_is_allowed(string $url): bool {
    if ($url === '' || $url !== mb_trim($url) || preg_match('~[\x00-\x20\x7f\\\\]~', $url)) {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) ||
        isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }

    $scheme = mb_strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    if (isset($parts['port']) && (($scheme === 'http' && $parts['port'] !== 80) || ($scheme === 'https' && $parts['port'] !== 443))) {
        return false;
    }

    return in_array(mb_strtolower($parts['host']), ARCHIVE_FETCH_HOSTS, true);
}

/**
 * Follow redirects explicitly so that every destination passes the same allow-list check.
 */
function fetch_archive_page(CurlHandle $ch, string $url): string {
    for ($redirects = 0; $redirects <= ARCHIVE_FETCH_MAX_REDIRECTS; $redirects++) {
        if ($url === '' || !archive_url_is_allowed($url)) {
            return '';
        }

        throttle_archive();
        /** @psalm-taint-escape ssrf */
        $safe_url = $url;
        curl_setopt($ch, CURLOPT_URL, $safe_url);
        $raw_html = bot_curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($status < 300 || $status >= 400) {
            return $raw_html;
        }

        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        if (!is_string($redirect_url) || $redirect_url === '') {
            return '';
        }
        $url = $redirect_url;
    }

    return '';
}

function archive_throttle_delay(float $now, float $last, float $minimum_interval = 1.0): int {
    if ($last <= 0.0 || $minimum_interval <= 0.0) {
        return 0;
    }

    $remaining = $minimum_interval - ($now - $last);
    if ($remaining <= 0.0) {
        return 0;
    }

    return min((int) ceil($remaining * 1000000), (int) ceil($minimum_interval * 1000000));
}

function throttle_archive(): void {
    static $last = 0.0;
    $now = microtime(true);
    $delay = archive_throttle_delay($now, $last);
    if ($delay > 0) {
        usleep($delay);
    }
    $last = microtime(true);
}

/**
 * Return only the early portion of an archived response needed for title and
 * charset discovery. This prevents broad regexes from scanning a huge body.
 */
function archive_title_scan_window(string $raw_html): string {
    if ($raw_html === '') {
        return '';
    }

    $limit = min(mb_strlen($raw_html, '8bit'), ARCHIVE_TITLE_SCAN_MAX_BYTES);
    $body_position = mb_stripos($raw_html, '<body', 0, '8bit');
    if ($body_position !== false && $body_position < $limit) {
        $body_end = mb_strpos($raw_html, '>', $body_position, '8bit');
        if ($body_end !== false) {
            $limit = min($limit, $body_end + 1);
        }
    }

    return mb_substr($raw_html, 0, $limit, '8bit');
}

/**
 * @param array<Template> &$templates
 */
function expand_templates_from_archives(array &$templates): void { // This is done very late as a latch ditch effort  // Pointer to save memory
    static $ch = null;
    set_time_limit(120);
    if ($ch === null) {
        $ch = bot_curl_init(0.5, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ]);
        bot_curl_set_max_response_bytes($ch, 16 * 1024 * 1024);
    }
    foreach ($templates as $template) {
        set_time_limit(120);
        if ($template->has('script-title') && (mb_strtolower($template->get('title')) === 'usurped title' || mb_strtolower($template->get('title')) === 'archived copy' || mb_strtolower($template->get('title')) === 'archive copy')) {
            $template->forget('title');
        }
        if ($template->blank(['chapter', 'series', 'script-title']) &&
            !$template->blank(['archive-url', 'archiveurl']) &&
            ($template->blank(WORK_ALIASES) || $template->has('website')) &&
            ($template->blank('title') || mb_strtolower($template->get('title')) === 'archived copy' ||
            mb_strtolower($template->get('title')) === 'archive copy' ||
            mb_strtolower($template->get('title')) === 'usurped title' ||
            mb_substr_count($template->get('title'), '?') > 10 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '') > 0 ||
            mb_substr_count($template->get('title'), '�') > 0 )) {
            $archive_url = $template->get('archive-url') . $template->get('archiveurl');
            if (archive_url_is_allowed($archive_url) && mb_stripos($archive_url, '.pdf') === false) {
                set_time_limit(120);
                $raw_html = fetch_archive_page($ch, $archive_url);
                $title_scan_html = archive_title_scan_window($raw_html);
                unset($raw_html);
                foreach ([
                    '~doctype[\S\s]+?<head[\S\s]+?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~doctype[\S\s]+?<head[\S\s]+?<meta property="og:title" content="([\S\s]+?\S[\S\s]+?)"\/>[\S\s]+?<title[\S\s]+?head[\S\s]+?<body~i',
                    '~doctype[\S\s]+?<head[\S\s]+?<title>([\S\s]+?\S[\S\s]+?) \| Ghostarchive<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~<html[\S\s]+<head[\S\s]+?<!-- End Wayback Rewrite JS Include -->[\s\S]*?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~<html[\S\s]+<head[\S\s]+?<!-- End Wayback Rewrite JS Include -->\s*?<!-- WebPoet\(tm\) Web Page Pull[\s\S]+?-->[\S\s]+?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head~i',
                    '~archive\.org/includes/analytics\.js[\S\s]+?-- End Wayback Rewrite JS Include[\S\s]+?head[\S\s]+<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~',
                ] as $regex) {
                    set_time_limit(120); // Slow regex sometimes
                    if ($title_scan_html !== '' && preg_match($regex, $title_scan_html, $match)) {
                        set_time_limit(120);
                        $title = mb_trim($match[1]);
                        if (mb_stripos($title, 'archive') === false &&
                            mb_stripos($title, 'wayback') === false &&
                            $title !== ''
                            ) {
                            $cleaned = false;
                            $encode = [];
                            if (preg_match('~x-archive-guessed-charset: (\S+)~i', $title_scan_html, $match)) {
                                if (is_encoding_reasonable($match[1])) {
                                    $encode[] = $match[1];
                                }
                            }
                            if (preg_match('~<meta http-equiv="?content-type"? content="text\/html;[\s]*charset=([^"]+)"~i', $title_scan_html, $match)) {
                                if (is_encoding_reasonable($match[1])) {
                                    $encode[] = $match[1];
                                }
                            }
                            if (preg_match('~<meta http-equiv="?content-type"? content="text\/html;[\s]*charset=([^"]+)"~i', $title_scan_html, $match)) {
                                if (mb_strtolower($match[1]) !== 'utf-8' && mb_strtolower($match[1]) !== 'iso-8859-1') {
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
                            if (in_array(mb_strtolower($title), BAD_ACCEPTED_MANUSCRIPT_TITLES, true) ||
                                in_array(mb_strtolower($title), IN_PRESS_ALIASES, true)) {
                                $good_title = false;
                            }
                            foreach (ZOTERO_BAD_TITLES as $bad_title) {
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
                                    $bad_count = mb_substr_count($new, '�') + mb_substr_count($new, '$') + mb_substr_count($new, '%') + mb_substr_count($new, '');
                                    if ($bad_count > 5) {
                                        $template->set('title', $old); // UTF-8 craziness
                                    } else {
                                        $title_scan_html = '';
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

function convert_to_utf8(string $value): string {
    $value = convert_to_utf8_inside($value);
    $test = preg_replace('~[\'a-zA-Z0-9 ]+~', '', $value);
    $test = mb_convert_encoding($test, 'utf-8', 'windows-1252');
    $count_cr1 = mb_substr_count($value, '®') + mb_substr_count($value, '©');
    $count_cr2 = mb_substr_count($test, '®') + mb_substr_count($test, '©');
    $len1 = mb_strlen($value);
    $len2 = mb_strlen($test);
    $bad1 = mb_substr_count($value, "");
    $bad2 = mb_substr_count($test, "");
    $rq1 = mb_substr_count($value, "”");
    $rq2 = mb_substr_count($test, "”");
    $lq1 = mb_substr_count($value, "„");
    $lq2 = mb_substr_count($test, "„");
    if ((1 + $count_cr1) === $count_cr2 && (4 + $len1 > $len2) && ($bad1 >= $bad2) && ($lq1 <= $lq2) && ($rq1 <= $rq2)) { // Special case for single (c) or (r) and did not grow much
        $value = mb_convert_encoding($value, 'utf-8', 'windows-1252');
    }
    // Special cases
    $value = str_replace([" �Livelong� ", "Uni�o", "Independ�ncia", "Folke Ekstr�m"], [' "Livelong" ', "União", "Independência", "Folke Ekström"], $value);
    return $value;
}

function convert_to_utf8_inside(string $value): string {
    $encode1 = mb_detect_encoding($value, ["UTF-8", "EUC-KR", "EUC-CN", "ISO-2022-JP", "Windows-1252", "iso-8859-1"], true);
    if ($encode1 === false || $encode1 === 'UTF-8' || $encode1 === 'Windows-1252') {
        return $value;
    }
    $encode2 = mb_detect_encoding($value, ["UTF-8", "EUC-CN", "EUC-KR", "ISO-2022-JP", "Windows-1252", "iso-8859-1"], true);
    if ($encode1 !== $encode2) {
        return $value;
    }
    $encode3 = mb_detect_encoding($value, ["UTF-8", "ISO-2022-JP", "EUC-CN", "EUC-KR", "Windows-1252", "iso-8859-1"], true);
    if ($encode1 !== $encode3) {
        return $value;
    }
    $encode4 = mb_detect_encoding($value, ["iso-8859-1", "UTF-8", "Windows-1252", "ISO-2022-JP", "EUC-CN", "EUC-KR"], true);
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
    $encode = mb_strtolower($encode);
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
    if ($encode === 'en-utf-8') {
        $encode = 'UTF-8';
    }
    if ($encode === 'utf8') {
        $encode = 'UTF-8';
    }
    if ($encode === 'windows-utf-8') {
        $encode = 'UTF-8';
    }
    if ($encode === 'utf8_unicode_ci') {
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
    if (preg_match('~^ISO-(.+)$~iD', $encode, $matches)) {
        $encode = 'iso-' . $matches[1];
    }
    if (in_array($encode, INSANE_ENCODE, true)) {
        return "";
    }
    $master_list = mb_list_encodings();
    $valid = [];
    foreach ($master_list as $enc) {
        $valid[] = mb_strtolower($enc);
    }
    try {
        if (in_array(mb_strtolower($encode), TRY_ENCODE, true) ||
            !in_array(mb_strtolower($encode), $valid, true)) {
            $try = (string) @iconv($encode, "UTF-8", $title);
        } else {
            $try = (string) @mb_convert_encoding($title, "UTF-8", $encode);
        }
    } catch (Exception) { // @codeCoverageIgnoreStart
        $try = "";
    } catch (ValueError) {
        $try = "";
    }                     // @codeCoverageIgnoreEnd
    if ($try === "") {
        bot_debug_log('Bad Encoding: ' . $encode . ' for ' . echoable($archive_url)); // @codeCoverageIgnore
    }
    return $try;
}
