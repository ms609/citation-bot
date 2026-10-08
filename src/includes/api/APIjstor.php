<?php

declare(strict_types=1);

/**
 * @param array<string> $_ids
 * @param array<Template> &$templates
 */
function query_jstor_api(array $_ids, array &$templates): void {  // Pointer to save memory
    foreach ($templates as $template) {
        expand_by_jstor($template);
    }
}

/**
 * Split one RIS line without allowing a malformed upstream line to leave
 * callers with an undefined value field.
 *
 * @return array{0: string, 1: string}
 */
function ris_line_parts(string $ris_line): array {
    if (str_starts_with($ris_line, "\xEF\xBB\xBF")) {
        $ris_line = mb_substr($ris_line, 3, null, '8bit');
    }
    if (str_ends_with($ris_line, "\r")) {
        $ris_line = mb_substr($ris_line, 0, -1, '8bit');
    }
    $parts = explode(" - ", $ris_line . " ", 2);
    return isset($parts[1]) ? [$parts[0], $parts[1]] : ['', ''];
}

/**
 * Return true only for one complete RIS record in the shape accepted by this
 * parser. JSTOR can return HTML or challenge pages with HTTP 200, so callers
 * must not treat a merely non-empty response as metadata.
 */
function jstor_response_is_ris(string $data): bool {
    if (str_starts_with($data, "\xEF\xBB\xBF")) {
        $data = mb_substr($data, 3, null, '8bit');
    }
    if (
        $data === '' ||
        !mb_check_encoding($data, 'UTF-8') ||
        preg_match('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~', $data) === 1
    ) {
        return false;
    }

    $state = 'before';
    $jstor_preamble = [
        'Provider: JSTOR http://www.jstor.org',
        'Database: JSTOR',
        'Content: text/plain; charset="UTF-8"',
    ];

    foreach (explode("\n", $data) as $ris_line) {
        $line = mb_trim($ris_line);
        if ($line === '') {
            continue;
        }

        [$tag, $value] = ris_line_parts($ris_line);
        $tag = mb_trim($tag);
        $value = mb_trim($value);

        if ($tag === '') {
            if ($state === 'before' && in_array($line, $jstor_preamble, true)) {
                continue;
            }
            return false;
        }
        if (preg_match('~^[A-Z][A-Z0-9]$~D', $tag) !== 1) {
            return false;
        }

        if ($tag === 'TY') {
            if (
                $state !== 'before' ||
                $value === '' ||
                preg_match('~^[A-Z0-9]+$~D', $value) !== 1
            ) {
                return false;
            }
            $state = 'record';
            continue;
        }

        if ($tag === 'ER') {
            if ($state !== 'record' || $value !== '') {
                return false;
            }
            $state = 'after';
            continue;
        }

        if ($state !== 'record') {
            return false;
        }
    }

    return $state === 'after';
}

/**
 * Return true for JSTOR/Fastly's browser-only JavaScript challenge.
 *
 * The challenge is returned with HTTP 200 to some non-browser clients and is
 * not metadata. Keep this deliberately specific so ordinary HTML/error pages
 * continue to use the generic non-RIS handling below.
 */
function jstor_response_is_client_challenge(string $data): bool {
    return preg_match('~<title>\s*Client Challenge\s*</title>~i', $data) === 1 &&
        mb_stripos($data, '/_fs-ch-') !== false;
}

function jstor_expand_via_zotero(Template $template, string $jstor): void {
    Zotero::expand_by_zotero(
        $template,
        'https://www.jstor.org/stable/' . $jstor,
        true,  // Explicit JSTOR URL bypasses Zotero's URL exclusion list.
        true,  // Reject metadata whose title does not match the citation.
        true,  // Use JSTOR's historical incomplete() policy.
        true   // JSTOR metadata must not trigger additional Crossref enrichment.
    );
}

function expand_by_jstor(Template $template): void {
    set_time_limit(120);
    if ($template->incomplete() === false) {
        return;
    }
    if ($template->has('jstor')) {
        $jstor = mb_trim($template->get('jstor'));
    } elseif (preg_match('~^https?://(?:www\.|)jstor\.org/stable/(.*)$~', $template->get('url'), $match)) {
        $jstor = $match[1];
    } else {
        return;
    }
    if (preg_match('~^(.*)(?:\?.*)$~', $jstor, $match)) {
        $jstor = $match[1]; // remove ?seq= stuff
    }
    /** @psalm-taint-escape ssrf */
    $jstor = mb_trim($jstor);
    if (mb_strpos($jstor, ' ') !== false) {
        return; // Comment/template found
    }
    if (mb_substr($jstor, 0, 1) === 'i') {
        return; // We do not want i12342 kind
    }
    if (!jstor_valid($jstor)) {
        return;
    }

    // The stable URL itself is authoritative for its JSTOR identifier. Keep
    // identifier discovery independent of whether Citoid/Zotero can retrieve
    // metadata for the item.
    if ($template->blank('jstor')) {
        $template->add_if_new('jstor', $jstor);
    }

    // JSTOR's RIS endpoint now presents a browser-only JavaScript challenge to
    // server-side clients. Citoid already runs Zotero's JSTOR translator, which
    // understands numeric IDs, non-DOI JSTOR IDs, books/reports and publisher
    // DOI-shaped stable IDs. The caller already applied JSTOR's historical
    // incomplete() gate, so bypass Zotero's stricter profoundly_incomplete()
    // gate here; when a title already exists, require title agreement before
    // accepting any returned metadata.
    jstor_expand_via_zotero($template, $jstor);
}

function expand_by_RIS(Template $template, string &$dat, bool $add_url): void {
    // Pass by pointer to wipe this data when called from use_unnamed_params()
    $ris_review = false;
    $ris_issn = false;
    $ris_publisher = false;
    $ris_book = false;
    $ris_fullbook = false;
    $ris_report = false;
    $ris_report_ti = null;
    $ris_report_t1 = null;
    $ris_report_title = null;
    $ris_report_title_lines = [];
    $has_T2 = false;
    $bad_EP = false;
    $bad_SP = false;
    // Convert &#x__; to characters
    $ris = explode("\n", html_entity_decode($dat, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
    $ris_authors = 0;

    if (preg_match('~(?:T[I1]).*-(.*)$~m', $dat, $match)) {
        if (in_array(mb_strtolower(mb_trim($match[1])), BAD_ACCEPTED_MANUSCRIPT_TITLES, true)) {
            return;
        }
    }

    foreach ($ris as $ris_line) {
        $ris_part = ris_line_parts($ris_line);
        if (mb_trim($ris_part[0]) === "TY") {
            $ris_type = mb_trim($ris_part[1]);
            if ($ris_type === "RPRT") {
                $ris_report = true;
            }
            if (in_array($ris_type, RIS_IS_BOOK, true)) {
                  $ris_book = true; // See https://en.wikipedia.org/wiki/RIS_(file_format)#Type_of_reference
            }
            if (in_array($ris_type, RIS_IS_FULL_BOOK, true)) {
                $ris_fullbook = true;
            }
        } elseif (mb_trim($ris_part[0]) === "T2") {
            $has_T2 = true;
        } elseif (mb_trim($ris_part[0]) === "TI") {
            $value = mb_trim($ris_part[1]);
            if ($value !== '') {
                if ($ris_report_ti === null) {
                    $ris_report_ti = $value;
                }
                $ris_report_title_lines[] = $ris_line;
            }
        } elseif (mb_trim($ris_part[0]) === "T1") {
            $value = mb_trim($ris_part[1]);
            if ($value !== '') {
                if ($ris_report_t1 === null) {
                    $ris_report_t1 = $value;
                }
                $ris_report_title_lines[] = $ris_line;
            }
        } elseif (mb_trim($ris_part[0]) === "SP" && (mb_trim($ris_part[1]) === 'i' || mb_trim($ris_part[1]) === '1')) {
            $bad_SP = true;
        } elseif (mb_trim($ris_part[0]) === "EP" && preg_match('~^\d{3,}$~', mb_trim($ris_part[1]))) {
            $bad_EP = true;
        }
    }

    if ($ris_report) {
        if ($ris_report_ti !== null && $ris_report_t1 !== null) {
            if (mb_strtolower($ris_report_ti) === mb_strtolower($ris_report_t1)) {
                $ris_report_title = $ris_report_ti;
            } else {
                $separator = str_ends_with($ris_report_ti, ':') ? ' ' : ': ';
                $ris_report_title = $ris_report_ti . $separator . $ris_report_t1;
            }
        } elseif ($ris_report_ti !== null) {
            $ris_report_title = $ris_report_ti;
        } elseif ($ris_report_t1 !== null) {
            $ris_report_title = $ris_report_t1;
        }
    }
    if ($ris_report_title !== null && $template->add_if_new('title', $ris_report_title)) {
        foreach ($ris_report_title_lines as $ris_line) {
            $dat = mb_trim(str_replace("\n" . $ris_line, "", "\n" . $dat));
        }
    }

    foreach ($ris as $ris_line) {
        $ris_part = ris_line_parts($ris_line);
        $ris_parameter = false;
        switch (mb_trim($ris_part[0])) {
            case "T1":
                if ($ris_report) {
                    break;
                } elseif ($ris_fullbook) {
                    // Sub-title of main title most likely
                } elseif ($ris_book) {
                    $ris_parameter = "chapter";
                } else {
                    $ris_parameter = "title";
                }
                break;
            case "TI":
                if ($ris_report) {
                    break;
                }
                $ris_parameter = "title";
                if ($ris_book && $has_T2) {
                    $ris_parameter = "chapter";
                }
                break;
            case "AU":
                $ris_authors++;
                $ris_parameter = "author" . $ris_authors;
                $ris_part[1] = format_author($ris_part[1]);
                break;
            case "Y1":
                $ris_parameter = "date";
                break;
            case "PY":
                $ris_parameter = "date";
                $ris_part[1] = preg_replace("~([\-\s]+)$~", '', str_replace('/', '-', $ris_part[1]));
                break;
            case "SP": // Deal with start pages later
                $start_page = mb_trim($ris_part[1]);
                $dat = mb_trim(str_replace("\n" . $ris_line, "", "\n" . $dat));
                break;
            case "EP": // Deal with end pages later
                $end_page = mb_trim($ris_part[1]);
                $dat = mb_trim(str_replace("\n" . $ris_line, "", "\n" . $dat));
                break;
            case "DO":
                $ris_parameter = doi_works($ris_part[1]) ? "doi" : false;
                break;
            case "JO":
            case "JF":
                $ris_parameter = "journal";
                break;
            case "T2":
            case "BT":
                if ($ris_book) {
                    $ris_parameter = "title";
                } else {
                    $ris_parameter = "journal";
                }
                break;
            case "VL":
                $ris_parameter = "volume";
                break;
            case "IS":
                $ris_parameter = "issue";
                break;
            case "RI": // Deal with review titles later
                $ris_review = "Reviewed work: " . mb_trim($ris_part[1]); // Get these from JSTOR
                $dat = mb_trim(str_replace("\n" . $ris_line, "", "\n" . $dat));
                break;
            case "SN": // Deal with SN later (may be ISBN; ISSN addition is disabled per request on bot talk page)
                $ris_issn = mb_trim($ris_part[1]);
                $dat = mb_trim(str_replace("\n" . $ris_line, "", "\n" . $dat));
                break;
            case "UR":
                $ris_parameter = "url";
                break;
            case "PB": // Deal with publisher later
                $ris_publisher = mb_trim($ris_part[1]); // Get these from JSTOR
                $dat = mb_trim(str_replace("\n" . $ris_line, "", "\n" . $dat));
                break;
            case "M3":
            case "N1":
            case "N2":
            case "ER":
            case "TY":
            case "KW":
            case "T3": // T3 is often the sub-title of a book
            case "A2": // This can be of the book that is reviewed
            case "A3": // Only seen this once and it duplicated AU
            case "ET": // Might be edition of book as an int
            case "LA": // Language
            case "DA": // Date this is based upon, not written or published
            case "CY": // Location
            case "CR": // Cited Reference
            case "TT": // Translated title - very rare and often poor
            case "C1":
            case "DB":
            case "AB":
            case "H1":
            case "Y2": // The following line is from JSTOR RIS (basically the header and blank lines)
            case "":
            case "Provider: JSTOR http://www.jstor.org":
            case "Database: JSTOR":
            case "Content: text/plain; charset=\"UTF-8\"":
                $dat = mb_trim(str_replace("\n" . $ris_line, "", "\n" . $dat)); // Ignore these completely
                break;
            default:
                // After logging this for several years, nothing of value ever found
                if (isset($ris_part[1])) { // @phpstan-ignore isset.offset
                    report_minor_error("Unexpected RIS data type ignored: " . echoable(mb_trim($ris_part[0])) . " set to " . echoable(mb_trim($ris_part[1]))); // @codeCoverageIgnore
                }
        }
        unset($ris_part[0]);
        if ($ris_parameter && (($ris_parameter === 'url' && !$add_url) || $template->add_if_new($ris_parameter, mb_trim(implode($ris_part))))) {
            $dat = mb_trim(str_replace("\n" . $ris_line, "", "\n" . $dat));
        }
    }
    if ($ris_review) {
        $template->add_if_new('title', mb_trim($ris_review));
    } // Do at end in case we have real title
    if (isset($start_page) && (!$bad_EP || !$bad_SP)) {
        // Have to do at end since might get end pages before start pages
        if (isset($end_page) && $start_page !== $end_page) {
            $template->add_if_new('pages', $start_page . '–' . $end_page);
        } else {
            $template->add_if_new('pages', $start_page);
        }
    }
    if ($ris_issn) {
        if (preg_match("~[\d\-]{9,}[\dXx]~", $ris_issn)) {
            $template->add_if_new('isbn', $ris_issn);
        }
        // ISSN addition is disabled per request on bot talk page - ISSN values from SN field are not added
    }
    if ($ris_publisher) {
        if ($ris_book || $template->blank(['journal', 'magazine'])) {
            $template->add_if_new('publisher', $ris_publisher);
        }
    }
}
