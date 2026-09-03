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
        try {
            $raw_html = bot_curl_exec($ch);
        } catch (Throwable $e) {
            bot_debug_log('Archive request failed: ' . $e::class . ': ' . $e->getMessage());
            report_warning("Archive request failed; continuing without archived-page metadata.");
            return '';
        }
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
 * Return only the HTTP header block from a curl response that includes headers.
 */
function archive_http_header_block(string $response): string {
    $offset = 0;
    $last = '';
    $length = mb_strlen($response, '8bit');

    // curl can return more than one header block for an interim response or
    // a proxy CONNECT. Use the last consecutive HTTP header block.
    for ($blocks = 0; $blocks < 8 && $offset < $length; ++$blocks) {
        $remaining = mb_substr($response, $offset, null, '8bit');
        if (!preg_match('~^HTTP/\d(?:\.\d)?\s~i', $remaining)) {
            break;
        }

        $crlf_end = mb_strpos($response, "\r\n\r\n", $offset, '8bit');
        $lf_end = mb_strpos($response, "\n\n", $offset, '8bit');
        if ($crlf_end === false && $lf_end === false) {
            return $last;
        }

        if ($crlf_end === false) {
            $end = $lf_end;
            $separator_length = 2;
        } elseif ($lf_end === false || $crlf_end < $lf_end) {
            $end = $crlf_end;
            $separator_length = 4;
        } else {
            $end = $lf_end;
            $separator_length = 2;
        }

        $last = mb_substr($response, $offset, $end - $offset, '8bit');
        $offset = $end + $separator_length;

        $status_line_end = mb_strpos($last, "\n", 0, '8bit');
        $status_line = $status_line_end === false
            ? $last
            : mb_substr($last, 0, $status_line_end, '8bit');
        $is_interim = preg_match(
            '~^HTTP/\d(?:\.\d)?\s+1\d{2}\b~i',
            $status_line
        ) === 1;
        $is_connect = mb_stripos($status_line, 'connection established') !== false;
        if (!$is_interim && !$is_connect) {
            break;
        }

        if (!preg_match(
            '~^HTTP/\d(?:\.\d)?\s~i',
            mb_substr($response, $offset, null, '8bit')
        )) {
            break;
        }
    }

    return $last;
}

/**
 * Extract an exact charset parameter from a Content-Type value.
 */
function archive_charset_parameter(string $content_type): ?string {
    // Split parameters while respecting quoted strings. In particular, a
    // semicolon inside boundary="..." must not introduce a fake charset.
    $segments = [];
    $start = 0;
    $quote = '';
    $escaped = false;
    $length = mb_strlen($content_type, '8bit');

    for ($i = 0; $i < $length; ++$i) {
        $char = $content_type[$i];
        if ($quote !== '') {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $quote) {
                $quote = '';
            }
            continue;
        }
        if ($char === '"' || $char === "'") {
            $quote = $char;
            continue;
        }
        if ($char === ';') {
            $segments[] = mb_substr($content_type, $start, $i - $start, '8bit');
            $start = $i + 1;
        }
    }
    $segments[] = mb_substr($content_type, $start, null, '8bit');

    foreach ($segments as $segment) {
        $equals = mb_strpos($segment, '=', 0, '8bit');
        if ($equals === false) {
            continue;
        }
        $name = mb_trim(mb_substr($segment, 0, $equals, '8bit'));
        if (strcasecmp($name, 'charset') !== 0) {
            continue;
        }

        $raw = mb_trim(mb_substr($segment, $equals + 1, null, '8bit'));
        if ($raw === '') {
            return null;
        }
        $first = $raw[0];
        if ($first !== '"' && $first !== "'") {
            return preg_match('~\s~', $raw) ? null : $raw;
        }

        $value = '';
        $escaped = false;
        $closed = false;
        $value_length = mb_strlen($raw, '8bit');
        for ($i = 1; $i < $value_length; ++$i) {
            $char = $raw[$i];
            if ($escaped) {
                $value .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $first) {
                $closed = true;
                if (mb_trim(mb_substr($raw, $i + 1, null, '8bit')) !== '') {
                    return null;
                }
                break;
            }
            $value .= $char;
        }
        if (!$closed) {
            return null;
        }
        $value = mb_trim($value);
        return $value !== '' ? $value : null;
    }

    return null;
}

/**
 * Parse ASCII HTML attributes without treating strings such as data-charset as
 * a real charset attribute.
 *
 * @return array<string, string>
 */
function archive_html_attributes(string $tag): array {
    $attributes = [];
    $pattern =
        '~(?:^|\s)([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*' .
        '(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'<>`]+?)(?=\s|/?>))~';

    if (!preg_match_all(
        $pattern,
        $tag,
        $matches,
        PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
    )) {
        return $attributes;
    }

    foreach ($matches as $match) {
        $name = strtolower($match[1]);
        if (array_key_exists($name, $attributes)) {
            continue;
        }
        $attributes[$name] = (string) (
            $match[2] ??
            $match[3] ??
            $match[4] ??
            ''
        );
    }

    return $attributes;
}

/**
 * Extract explicit charset declarations from HTML meta elements.
 *
 * @return list<string>
 */
function archive_meta_declared_encodings(string $html): array {
    // An unterminated comment or raw-text element consumes the rest of the
    // document for this lightweight scan; do not expose fake meta tags inside it.
    $scan = preg_replace('~<!--(?:.*?-->|.*\z)~s', '', $html);
    $scan = is_string($scan) ? $scan : $html;
    $without_embedded_text = preg_replace(
        '~<(script|style|template|noscript)\b[^>]*>(?:.*?</\1\s*>|.*\z)~is',
        '',
        $scan
    );
    $scan = is_string($without_embedded_text) ? $without_embedded_text : $scan;

    $declared = [];
    if (!preg_match_all(
        '~<meta\b(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>~i',
        $scan,
        $meta_tags
    )) {
        return $declared;
    }

    foreach ($meta_tags[0] as $tag) {
        $attributes = archive_html_attributes($tag);
        if (isset($attributes['charset']) && $attributes['charset'] !== '') {
            $declared[] = $attributes['charset'];
            continue;
        }

        if (
            isset($attributes['http-equiv'], $attributes['content']) &&
            strcasecmp(mb_trim($attributes['http-equiv']), 'content-type') === 0
        ) {
            $charset = archive_charset_parameter($attributes['content']);
            if ($charset !== null) {
                $declared[] = $charset;
            }
        }
    }

    return $declared;
}

/**
 * Extract charset candidates from HTTP headers and HTML meta tags.
 *
 * Explicit declarations are authoritative enough to try even when the charset
 * is a common default. Archive-guessed charsets remain lower-priority hints.
 *
 * @return list<string>
 */
function archive_candidate_encodings(string $html): array {
    $declared = [];
    $guessed = [];
    $headers = archive_http_header_block($html);

    if ($headers !== '') {
        if (preg_match_all('~^content-type:\s*([^\r\n]*)\r?$~im', $headers, $header_matches)) {
            foreach ($header_matches[1] as $content_type) {
                $charset = archive_charset_parameter($content_type);
                if ($charset !== null) {
                    $declared[] = $charset;
                }
            }
        }

        if (preg_match('~^x-archive-guessed-charset:\s*([^\s;]+)~im', $headers, $match)) {
            $guessed[] = mb_trim($match[1], "\"'");
        }
    }

    foreach (archive_meta_declared_encodings($html) as $charset) {
        $declared[] = $charset;
    }

    $candidates = [];
    $seen = [];

    foreach ($declared as $charset) {
        $charset = mb_trim($charset, " \t\n\r\0\x0B\"'");
        $key = mb_strtolower($charset);
        if ($charset !== '' && !isset($seen[$key])) {
            $candidates[] = $charset;
            $seen[$key] = true;
        }
    }

    foreach ($guessed as $charset) {
        $charset = mb_trim($charset, " \t\n\r\0\x0B\"'");
        $key = mb_strtolower($charset);
        if (
            $charset !== '' &&
            is_encoding_reasonable($charset) &&
            !isset($seen[$key])
        ) {
            $candidates[] = $charset;
            $seen[$key] = true;
        }
    }

    return $candidates;
}

/**
 * Decode an archive title only when it is not already valid UTF-8.
 *
 * @param list<string> $encodings
 */
function archive_decode_title(string $title, array $encodings, string $archive_url): string {
    if ($title === '' || mb_check_encoding($title, 'UTF-8')) {
        return $title;
    }

    foreach ($encodings as $encoding) {
        $try = smart_decode($title, $encoding, $archive_url);
        if ($try !== '' && mb_check_encoding($try, 'UTF-8')) {
            return $try;
        }
    }

    return convert_to_utf8($title);
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
        ], 16 * 1024 * 1024);
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
                            $encode = archive_candidate_encodings($title_scan_html);
                            $title = archive_decode_title($title, $encode, $archive_url);
                            unset($encode, $match);
                            $good_title = $title !== '' && mb_check_encoding($title, 'UTF-8');
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
    // Do not guess an unresolved encoding. Returning an empty string lets the
    // archive caller retain existing metadata instead of emitting mojibake.
    if (!mb_check_encoding($value, 'UTF-8')) {
        return '';
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

    $encode = mb_trim($encode);
    $encode_key = mb_strtolower($encode);

    if ($encode_key === 'maccentraleurope') {
        $encode = 'mac-centraleurope';
    } elseif (in_array(
        $encode_key,
        [
            'utf-8; charset=utf-8',
            'en-utf-8',
            'unicode-1-1-utf-8',
            'unicode11utf8',
            'unicode20utf8',
            'utf8',
            'windows-utf-8',
            'utf8_unicode_ci',
            'x-unicode20utf8',
        ],
        true
    )) {
        $encode = 'UTF-8';
    } elseif (in_array(
        $encode_key,
        [
            'ansi_x3.4-1968',
            'iso-8859-1',
            'iso-ir-100',
            'iso8859-1',
            'iso88591',
            'iso_8859-1',
            'iso_8859-1:1987',
            '8859-1',
            'latin1',
            'latin-1',
            'l1',
            'us-ascii',
            'ascii',
            'cp819',
            'ibm819',
            'csisolatin1',
            'cp1252',
            'x-cp1252',
            'windows-1252',
        ],
        true
    )) {
        // HTML labels in the ISO-8859-1/ASCII family are Windows-1252.
        $encode = 'Windows-1252';
    } elseif (in_array(
        $encode_key,
        ['csshiftjis', 'ms932', 'ms_kanji', 'shift_jis', 'shift-jis', 'x-sjis', 'sjis', 'windows-31j', 'cp932'],
        true
    )) {
        $encode = 'SJIS-win';
    } elseif (in_array($encode_key, ['big5', 'big-5'], true)) {
        $encode = 'BIG-5';
    }

    if (preg_match('~^\d{4}\-\d{1,2}$~', $encode)) {
        $encode = 'iso-' . $encode;
    }
    if (preg_match('~^ISO-(.+)$~iD', $encode, $matches)) {
        $encode = 'iso-' . $matches[1];
    }

    $encode_key = mb_strtolower($encode);
    if (
        in_array($encode_key, INSANE_ENCODE, true) ||
        in_array($encode_key, ['utf-7', 'unicode', 'none'], true)
    ) {
        return "";
    }
    if ($encode_key === 'utf-8') {
        return mb_check_encoding($title, 'UTF-8') ? $title : '';
    }

    $master_list = mb_list_encodings();
    $valid = [];
    foreach ($master_list as $enc) {
        $valid[] = mb_strtolower($enc);
    }

    // mb_convert_encoding() substitutes malformed source bytes by default.
    // Reject malformed input for known mbstring encodings instead.
    if (
        in_array($encode_key, $valid, true) &&
        !mb_check_encoding($title, $encode)
    ) {
        return '';
    }

    try {
        if (in_array($encode_key, TRY_ENCODE, true) ||
            !in_array($encode_key, $valid, true)) {
            $try = (string) @iconv($encode, "UTF-8", $title);
        } else {
            $try = (string) @mb_convert_encoding($title, "UTF-8", $encode);
        }
    } catch (Throwable) { // @codeCoverageIgnoreStart
        $try = "";
    }                     // @codeCoverageIgnoreEnd
    if ($try === "") {
        bot_debug_log('Bad Encoding: ' . $encode . ' for ' . echoable($archive_url)); // @codeCoverageIgnore
    }
    return $try;
}
