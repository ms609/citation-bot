<?php

declare(strict_types=1);

function clean_google_books(Template $template): void {
    if (!in_array(WIKI_BASE, ENGLISH_WIKI, true)) { // TODO - support other countries
        return;
    }
    foreach (ALL_URL_TYPES as $url_type) {
        if ($template->has($url_type)) {
            $url = $template->get($url_type);
            if (mb_strpos($url, '#about_author_anchor') !== false) {
                continue;
            }
            if (mb_strpos($url, 'vid=') !== false) {
                continue; // must be done by hand
            }
            if (preg_match('~^(https?://(?:books|www)\.google\.[^/]+/books.+)\?$~', $url, $matches)) {
                $url = $matches[1]; // trailing ?
            }
            if (preg_match('~^https?://books\.google\.[^/]+/booksid=(.+)$~', $url, $matches)) {
                $url = 'https://books.google.com/books?id=' . $matches[1];
            }
            if (preg_match('~^https?://www\.google\.[^/]+/books\?(.+)$~', $url, $matches)) {
                $url = 'https://books.google.com/books?' . $matches[1];
            }
            if (preg_match('~^https?://books\.google\.[^/\?]+\?id=(.+)$~', $url, $matches)) {
                $url = 'https://books.google.com/books?id=' . $matches[1];
            }
            if (preg_match('~^https?://books\.google\.[^/]+\/books\/about\/[^/]+\.html$~', $url, $matches) ||
            preg_match('~^https?://(?:books|www)\.google\.[^/]+\/books\/edition\/[a-zA-Z0-9\_]+\/?$~', $url, $matches) ||
            preg_match('~^https?://(?:books|www)\.google\.[^/]+\/books\?pg=P\S\S\S\S*$~', $url, $matches)) {
                $url = '';
            }
            if (preg_match('~^https?://(?:books|www)\.google\.[^/]+\/books\/edition\/[a-zA-Z0-9\_]+\/([a-zA-Z0-9\-]+)\?(.+)$~', $url, $matches)) {
                $url = 'https://books.google.com/books?id=' . $matches[1] . '&' . $matches[2];
            }
            if (preg_match('~^https?://books\.google\..*id\&\#61\;.*$~', $url, $matches)) {
                $url = str_replace('&#61;', '=', $url);
            }
            if (preg_match('~^https?://books\.google\.[^/]+/(?:books|)\?qid=(.+)$~', $url, $matches)) {
                $url = 'https://books.google.com/books?id=' . $matches[1];
            }
            if (preg_match('~^https?://books\.google\.[^/]+/(?:books|)\?vid=(.+)$~', $url, $matches)) {
                if (str_ireplace(['isbn', 'lccn', 'oclc'], '', $matches[1]) === $matches[1]) {
                    $url = 'https://books.google.com/books?id=' . $matches[1];
                }
            }
            if (preg_match('~^https?://(?:|www\.)books\.google\.com/\?id=(.+)$~', $url, $matches)) {
                $url = 'https://books.google.com/books?id=' . $matches[1];
            }
            if (preg_match('~^https?://www\.google\.[a-z\.]+/books\?id=(.+)$~', $url, $matches)) {
                $url = 'https://books.google.com/books?id=' . $matches[1];
            }
            $template->set($url_type, $url);
            if ($url === '') {
                $template->forget($url_type);
                if ($template->blank('title')) {
                    bot_debug_log('dropped google url completely');
                }
            }
            expand_by_google_books_inner($template, $url_type, false);
            if (preg_match('~^https?://books\.google\.([^/]+)/books\?((?:isbn|vid)=.+)$~', $template->get($url_type), $matches)) {
                if ($matches[1] !== 'com') {
                    $template->set($url_type, 'https://books.google.com/books?' . $matches[2]);
                }
            }
        }
    }
}

function expand_by_google_books(Template $template): void {
    clean_google_books($template);
    if ($template->has('doi') && doi_works($template->get('doi'))) {
        return;
    }
    foreach (['url', 'chapterurl', 'chapter-url'] as $url_type) {
        if (expand_by_google_books_inner($template, $url_type, true)) {
            return;
        }
    }
    expand_by_google_books_inner($template, '', true);
    return;
}

function expand_by_google_books_inner(Template $template, string $url_type, bool $use_it): bool {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, []);
    }
    set_time_limit(120);
    if ($url_type) {
        $url = $template->get($url_type);
        if (!$url) {
            return false;
        }
        if (mb_strpos($url, '#about_author_anchor') !== false) {
            return false;
        }
        if (mb_strpos($url, 'vid=') !== false) {
            return false; // Must be done by hand
        }
        if (
            preg_match(
            '~^https?:\/\/(?:www|books)\.google\.[a-zA-Z\.][a-zA-Z\.][a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?\/books\/(?:editions?|about)\/[^\/\/\s\<\|\{\}\>\]]+\/([^\? \]\[]+)\?([^\s\<\|\{\}\>\]]+)$~i',
            $url,
            $matches
        )
        ) {
            $url = 'https://books.google.com/books?id=' . $matches[1] . '&' . $matches[2];
            $template->set($url_type, $url);
        } elseif (preg_match('~^https?:\/\/(?:www|books)\.google\.[a-zA-Z\.][a-zA-Z\.][a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?\/books\/(?:editions?|about)\/_\/([^\s\<\|\{\}\>\]\&\?\%]+)$~i', $url, $matches)) {
            $url = 'https://books.google.com/books?id=' . $matches[1];
            $template->set($url_type, $url);
        } elseif (
            preg_match(
            '~^https?:\/\/(?:www|books)\.google\.[a-zA-Z\.][a-zA-Z\.][a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?\/books\/(?:editions?|about)\/_\/([^\s\<\|\{\}\>\]\&\?\%]+)?([^\s\<\|\{\}\>\]\?\%]+)$~i',
            $url,
            $matches
            )
        ) {
            $url = 'https://books.google.com/books?id=' . $matches[1] . '&' . $matches[2];
            $template->set($url_type, $url);
        } elseif (
            preg_match('~^https?:\/\/(?:www|books)\.google\.[a-zA-Z\.][a-zA-Z\.][a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?\/books\/(?:editions?|about)\/[^\/\/\s\<\|\{\}\>\]]+\/([^\? \]\[\&\%]+)$~i', $url, $matches)
        ) {
            $url = 'https://books.google.com/books?id=' . $matches[1];
            $template->set($url_type, $url);
        }
        if (preg_match("~^https?://www\.google\.(?:[^\./]+)/books/(?:editions?|about)/_/(.+)$~", $url, $matches)) {
            $url = 'https://www.google.com/books/edition/_/' . $matches[1];
            $template->set($url_type, $url);
        }
        if (!preg_match("~(?:[Bb]ooks|[Ee]ncrypted)\.[Gg]oogle\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid) && !preg_match("~\.[Gg]oogle\.com/books/edition/_/([a-zA-Z0-9]+)(?:\?.+|)$~", $url, $gid)) {
            return false; // Got nothing usable
        }
    } else {
        $url = '';
        $isbn = $template->get('isbn');
        if ($isbn) {
            $isbn = str_replace([" ", "-"], "", $isbn);
            if (preg_match("~[^0-9Xx]~", $isbn) === 1) {
                $isbn = '';
            }
            if (mb_strlen($isbn) !== 13 && mb_strlen($isbn) !== 10) {
                $isbn = '';
            }
        }
        if ($isbn && !doi_works($template->get('doi'))) {
            // Try Books.Google.Com
            /** @psalm-taint-escape ssrf */
            $google_book_url = 'https://books.google.com/books?vid=ISBN' . $isbn;
            curl_setopt($ch, CURLOPT_URL, $google_book_url);
            $google_content = bot_curl_exec($ch);
            $google_content = preg_replace('~book_other_versions_anchor.*$~', '', $google_content);
            if (preg_match_all('~(?:content|html)\?id=(............)(?:&amp|")~', $google_content, $google_results)) {
                  $google_results = $google_results[1];
                  $google_results = array_unique($google_results);
                if (count($google_results) === 1) {
                    $gid = $google_results[0];
                    $url = 'https://books.google.com/books?id=' . $gid;
                }
            }
        }
    }
    // Now we parse a Google Books URL
    if ($url && (preg_match("~[Bb]ooks\.[Gg]oogle\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid) || preg_match("~[Ee]ncrypted\.[Gg]oogle\..+book.*\bid=([\w\d\-]+)~", $url, $gid))) {
        $orig_book_url = $url;
        $removed_redundant = 0;
        $removed_parts = '';
        normalize_google_books($url, $removed_redundant, $removed_parts, $gid);
        if ($url !== $orig_book_url && $url_type && mb_strpos($url_type, 'url') !== false) {
            if ($removed_redundant > 1) {
                // http:// is counted as 1 parameter
                report_forget(echoable($removed_parts));
            } else {
                report_forget('Standardized Google Books URL');
            }
            $template->set($url_type, $url);
        }
        if ($use_it) {
            google_book_details($template, $gid[1]);
        }
        return true;
    }
    if (preg_match("~^(.+\.google\.com/books/edition/[^\/]+/)([a-zA-Z0-9]+)(\?.+|)$~", $url, $gid)) {
        if ($url_type && $gid[3] === '?hl=en') {
            report_forget('Anonymized/Standardized/Denationalized Google Books URL');
            $template->set($url_type, $gid[1] . $gid[2]);
        }
        if ($use_it) {
            google_book_details($template, $gid[2]);
        }
        return true;
    }
    return false;
}

function google_book_details(Template $template, string $gid): void {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, []);
    }
    set_time_limit(120);
    $google_book_url = "https://books.google.com/books/feeds/volumes/" . $gid;
    curl_setopt($ch, CURLOPT_URL, $google_book_url);
    $data = bot_curl_exec($ch);
    if ($data === '') {
        return;
    }
    $simplified_xml = str_replace('http___//www.w3.org/2005/Atom', 'http://www.w3.org/2005/Atom', str_replace(":", "___", $data));
    $xml = @simplexml_load_string($simplified_xml);
    if ($xml === false) {
        return;
    }
    if ($xml->dc___title[1]) {
        $template->add_if_new('title', wikify_external_text(str_replace("___", ":", $xml->dc___title[0] . ": " . $xml->dc___title[1])));
    } else {
        $template->add_if_new('title', wikify_external_text(str_replace("___", ":", (string) $xml->title)));
    }
    $isbn = '';
    foreach ($xml->dc___identifier as $ident) {
        if (preg_match("~isbn.*?([\d\-]{9}[\d\-]+)~i", (string) $ident, $match)) {
            $isbn = $match[1];
        }
    }
    $template->add_if_new('isbn', $isbn);

    $i = 0;
    if ($template->blank([...FIRST_EDITOR_ALIASES, ...FIRST_AUTHOR_ALIASES, ...['publisher', 'journal', 'magazine', 'periodical']])) {
        // Too many errors in gBook database to add to existing data. Only add if blank.
        foreach ($xml->dc___creator as $author) {
            if (mb_strtolower(str_replace("___", ":", (string) $author)) === "gale group") {
                break;
            }
            if (preg_match('~\d{4}~', (string) $author)) {
                break;
            } // Has a date in it
            if (preg_match('~^.+ \(.+\)$~', (string) $author)) {
                break;
            } // State or territory
            ++$i;
            $template->validate_and_add('author' . (string) $i, str_replace("___", ":", (string) $author), '', '', true);
            if ($template->blank(['author' . (string) $i, 'first' . (string) $i, 'last' . (string) $i])) {
                $i--;
            } // It did not get added
        }
    }

    // Possibly contains dud information on occasion - only add if data is good enough to have ISBN, and is probably a stand-alone book
    if (isset($xml->dc___publisher) && $isbn !== '' && $template->blank(['doi', 'pmid', 'pmc', 's2cid', 'arxiv', 'eprint', 'journal', 'magazine', 'newspaper', 'series'])) {
        $template->add_if_new('publisher', str_replace("___", ":", (string) $xml->dc___publisher));
    }

    $google_date = sanitize_string(mb_trim((string) $xml->dc___date)); // Google often sends us YYYY-MM
    if ($google_date === '101-01-01') {
        $google_date = '';
    }
    if (mb_substr_count($google_date, "-") === 1) {
        $date = @date_create($google_date);
        if ($date !== false) {
            $date = @date_format($date, "F Y");
            /** @phpstan-ignore notIdentical.alwaysTrue */
            if ($date !== false) {
                $google_date = $date; // only now change data
            }
        }
    }
    $google_date = tidy_date($google_date);
    $now = (int) date("Y");
    // Some publishers give next year always for OLD stuff
    for ($i = 1; $i <= 30; $i++) {
        $next_year = (string) ($now + $i);
        if (mb_strpos($google_date, $next_year) !== false) {
            return;
        }
    }
    if ($template->has('isbn')) {
        // Assume this is recent, and any old date is bogus
        if (preg_match('~1[0-8]\d\d~', $google_date)) {
            return;
        }
        if (!preg_match('~[12]\d\d\d~', $google_date)) {
            return;
        }
    }
    if (!preg_match("~^\d{4}$~", $google_date)) {
        // More than a year
        $almost_now = time() - 604800;
        $new = (int) strtotime($google_date);
        if ($new > $almost_now) {
            return;
        }
    }
    $template->add_if_new('date', $google_date);
    // Don't add page count
    return;
}

/**
 * @param string &$url
 * @param int &$removed_redundant
 * @param string &$removed_parts
 * @param array<string> &$gid
 */
function normalize_google_books(string &$url, int &$removed_redundant, string &$removed_parts, array &$gid): void { // PASS BY REFERENCE!!!!!!
    $removed_redundant = 0;
    $hash = '';
    $removed_parts = '';
    if (mb_strpos($url, "vid=")) {
        return;  // These must be fixed by hand
    }
    $url = str_replace('&quot;', '"', $url);

    if (mb_strpos($url, "#")) {
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
    $hash = '&' . mb_trim($hash) . '&';
    $hash = str_replace(['&f=false', '&f=true', 'v=onepage'], ['', '', ''], $hash); // onepage is default
    $hash = str_replace(['&q&', '&q=&', '&&&&', '&&&', '&&', '%20&%20'], ['&', '&', '&', '&', '&', '%20%26%20'], $hash);
    if (preg_match('~(&q=[^&]+)&~', $hash, $matcher)) {
        $hash = str_replace($matcher[1], '', $hash);
        if (isset($book_array['q'])) {
            $removed_parts .= '&q=' . $book_array['q'];
            $book_array['q'] = urlencode(urldecode(mb_substr($matcher[1], 3))); // #q= wins over &q= before # sign
        } elseif (isset($book_array['dq'])) {
            $removed_parts .= '&dq=' . $book_array['dq'];
            $dum_dq = str_replace('+', ' ', urldecode($book_array['dq']));
            $dum_q = str_replace('+', ' ', urldecode(mb_substr($matcher[1], 3)));
            if ($dum_dq !== $dum_q) {
                $book_array['q'] = urlencode(urldecode(mb_substr($matcher[1], 3)));
                unset($book_array['dq']);
            } else {
                $book_array['dq'] = urlencode(urldecode(mb_substr($matcher[1], 3)));
            }
        } else {
            $book_array['q'] = urlencode(urldecode(mb_substr($matcher[1], 3)));
        }
    }
    if (preg_match('~(&dq=[^&]+)&~', $hash, $matcher)) {
        $hash = str_replace($matcher[1], '', $hash);
        if (isset($book_array['dq'])) {
            $removed_parts .= '&dq=' . $book_array['dq'];
        }
        $book_array['dq'] = urlencode(urldecode(mb_substr($matcher[1], 3))); // #dq= wins over &dq= before # sign
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
    if (preg_match('~^&(.*)$~', $hash, $matcher)) {
        $hash = $matcher[1];
    }
    if (preg_match('~^(.*)&$~', $hash, $matcher)) {
        $hash = $matcher[1];
    }
    if (preg_match('~^P*(PA\d+),M1$~', $hash, $matcher)) {
        $book_array['pg'] = $matcher[1];
        $hash = '';
    }
    if (preg_match('~^P*(PP\d+),M1$~', $hash, $matcher)) {
        $book_array['pg'] = $matcher[1];
        $hash = '';
    }
    if (preg_match('~^P*(PT\d+),M1$~', $hash, $matcher)) {
        $book_array['pg'] = $matcher[1];
        $hash = '';
    }
    if (preg_match('~^P*(PR\d+),M1$~', $hash, $matcher)) {
        $book_array['pg'] = $matcher[1];
        $hash = '';
    }

    if (isset($book_array['q'])) {
        if (((mb_stripos($book_array['q'], 'isbn') === 0) && ($book_array['q'] !== 'ISBN') && ($book_array['q'] !== 'isbn')) || // Sometimes the search is for the term isbn
                mb_stripos($book_array['q'], 'subject:') === 0 ||
                mb_stripos($book_array['q'], 'inauthor:') === 0 ||
                mb_stripos($book_array['q'], 'inpublisher:') === 0) {
            unset($book_array['q']);
        }
    }
    if (isset($book_array['dq'])) {
        if (((mb_stripos($book_array['dq'], 'isbn') === 0) && ($book_array['dq'] !== 'ISBN') && ($book_array['dq'] !== 'isbn')) || // Sometimes the search is for the term isbn
                mb_stripos($book_array['dq'], 'subject:') === 0 ||
                mb_stripos($book_array['dq'], 'inauthor:') === 0 ||
                mb_stripos($book_array['dq'], 'inpublisher:') === 0) {
            unset($book_array['dq']);
        }
    }
    if (isset($book_array['sitesec'])) { // Overrides all other setting
        if (mb_strtolower($book_array['sitesec']) === 'reviews') {
            $url .= '&sitesec=reviews';
            unset($book_array['q']);
            unset($book_array['pg']);
            unset($book_array['lpg']);
            unset($book_array['article_id']);
        }
    }
    if (isset($book_array['q'])) {
        $url .= '&q=' . $book_array['q'];
    }
    if (isset($book_array['dq'])) {
        $url .= '&dq=' . $book_array['dq'];
    }
    if (isset($book_array['pg'])) {
        if (preg_match('~^[pra]+\d~i', $book_array['pg'])) {
            $book_array['pg'] = mb_strtoupper($book_array['pg']);
        }
        $url .= '&pg=' . $book_array['pg'];
    }
    if (isset($book_array['lpg'])) { // Currently NOT POSSIBLE - failsafe code for changes
        $url .= '&lpg=' . $book_array['lpg']; // @codeCoverageIgnore
    }
    if (isset($book_array['article_id'])) {
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
