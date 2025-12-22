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
        $jstor = mb_trim($template->get('jstor'));
    } elseif(preg_match('~^https?://(?:www\.|)jstor\.org/stable/(.*)$~', $template->get('url'), $match)) {
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
    curl_setopt($ch, CURLOPT_URL, 'https://www.jstor.org/citation/ris/' . $jstor);
    $dat = bot_curl_exec($ch);
    if ($dat === '') {
        report_info("JSTOR API returned nothing for ". jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                             // @codeCoverageIgnore
    }
    if (mb_stripos($dat, 'No RIS data found for') !== false) {
        report_info("JSTOR API found nothing for ".    jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                             // @codeCoverageIgnore
    }
    if (mb_stripos($dat, 'Block Reference') !== false) {
        report_info("JSTOR API blocked bot for ".    jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                           // @codeCoverageIgnore
    }
    if (mb_stripos($dat, 'A problem occurred trying to deliver RIS data') !== false) {
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
            switch (mb_trim($ris_part[0])) {
                case "T1":
                case "TI":
                case "T2":
                case "BT":
                    $new_title = mb_trim($ris_part[1]);
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
                switch (mb_trim($ris_part[0])) {
                    case "T1":
                        $new_title .= mb_trim($ris_part[1]);
                        $got_count += 10;
                        break;
                    case "TI":
                        $new_title = mb_trim($ris_part[1]) . $new_title;
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
                switch (mb_trim($ris_part[0])) {
                    case "T1":
                    case "TI":
                    case "T2":
                    case "BT":
                        $new_title = mb_trim($ris_part[1]);
                        if ($new_title) {
                            report_info("    Possible new title: " . echoable($new_title));
                        }
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
    expand_by_RIS($template, $dat, false);
    return;
}

function expand_by_RIS(Template $template, string &$dat, bool $add_url): void
 {
    // Pass by pointer to wipe this data when called from use_unnamed_params()
    $ris_review = false;
    $ris_issn = false;
    $ris_publisher = false;
    $ris_book = false;
    $ris_fullbook = false;
    $has_T2 = false;
    $bad_EP = false;
    $bad_SP = false;
    // Convert &#x__; to characters
    $ris = explode("\n", html_entity_decode($dat, ENT_COMPAT | ENT_HTML401, 'UTF-8'));
    $ris_authors = 0;

    if (preg_match('~(?:T[I1]).*-(.*)$~m', $dat, $match)) {
        if (in_array(mb_strtolower(mb_trim($match[1])), BAD_ACCEPTED_MANUSCRIPT_TITLES, true)) {
            return;
        }
    }

    foreach ($ris as $ris_line) {
        $ris_part = explode(" - ", $ris_line . " ", 2);
        if (!isset($ris_part[1])) {
            $ris_part[0] = "";
        } // Ignore
        if (mb_trim($ris_part[0]) === "TY") {
            if (in_array(mb_trim($ris_part[1]), RIS_IS_BOOK, true)) {
                  $ris_book = true; // See https://en.wikipedia.org/wiki/RIS_(file_format)#Type_of_reference
            }
            if (in_array(mb_trim($ris_part[1]), RIS_IS_FULL_BOOK, true)) {
                $ris_fullbook = true;
            }
        } elseif (mb_trim($ris_part[0]) === "T2") {
            $has_T2 = true;
        } elseif (mb_trim($ris_part[0]) === "SP" && (mb_trim($ris_part[1]) === 'i' || mb_trim($ris_part[1]) === '1')) {
            $bad_SP = true;
        } elseif (mb_trim($ris_part[0]) === "EP" && preg_match('~^\d{3,}$~', mb_trim($ris_part[1]))) {
            $bad_EP = true;
        }
    }

    foreach ($ris as $ris_line) {
        $ris_part = explode(" - ", $ris_line . " ", 2);
        $ris_parameter = false;
        if (!isset($ris_part[1])) {
            $ris_part[0] = "";
        } // Ignore
        switch (mb_trim($ris_part[0])) {
            case "T1":
                if ($ris_fullbook) {
                    // Sub-title of main title most likely
                } elseif ($ris_book) {
                    $ris_parameter = "chapter";
                } else {
                    $ris_parameter = "title";
                }
                break;
            case "TI":
                $ris_parameter = "title";
                if ($ris_book && $has_T2) {
                    $ris_parameter = "chapter";
                }
                break;
            case "AU":
                $ris_authors++;
                $ris_parameter = "author". $ris_authors;
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
            case "SN": // Deal with ISSN later
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
                if (isset($ris_part[1])) { // After logging this for several years, nothing of value ever found
                    report_info("Unexpected RIS data type ignored: " . echoable(mb_trim($ris_part[0])) . " set to " . echoable(mb_trim($ris_part[1]))); // @codeCoverageIgnore
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
        } elseif (preg_match("~\d{4}\-?\d{3}[\dXx]~", $ris_issn)) {
            if ($template->blank('journal')) {
                  $template->add_if_new('issn', $ris_issn);
            }
        }
    }
    if ($ris_publisher) {
        if ($ris_book || $template->blank(['journal', 'magazine'])) {
            $template->add_if_new('publisher', $ris_publisher);
        }
    }
}
