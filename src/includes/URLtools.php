<?php

// TODO - merge these two _INSIDE functions - perhaps with a "expand or not to expand" flag

declare(strict_types=1);

/**
 * @param array<Template> &$templates
 */
function drop_urls_that_match_dois(array &$templates): void {  // Pointer to save memory
    static $ch_dx;
    static $ch_doi;
    if ($ch_dx === null) {
        if (TRAVIS) {
            $time = 3.0;
        } else {
            $time = 1.0; // @codeCoverageIgnore
        }
        $ch_dx = bot_curl_init($time, []);
        $ch_doi = bot_curl_init($time, []);
    }
    // Now that we have expanded URLs, try to lose them
    foreach ($templates as $template) {
        $doi = $template->get_without_comments_and_placeholders('doi');
        if ($template->has('url')) {
            $url = $template->get('url');
            $url_type = 'url';
        } elseif ($template->has('chapter-url')) {
            $url = $template->get('chapter-url');
            $url_type = 'chapter-url';
        } elseif ($template->has('chapterurl')) {
            $url = $template->get('chapterurl'); // @codeCoverageIgnore
            $url_type = 'chapterurl';      // @codeCoverageIgnore
        } else {
            $url = '';
            $url_type = '';
        }
        if ($doi && // IEEE code does not require "not incomplete"
            $url &&
            !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
            $template->blank(DOI_BROKEN_ALIASES) &&
            preg_match("~^https?://ieeexplore\.ieee\.org/document/\d{5,}/?$~", $url) && mb_strpos($doi, '10.1109') === 0) {
            report_forget("Existing IEEE resulting from equivalent DOI; dropping URL");
            $template->forget($url_type);
        }

        if ($doi &&
                $url &&
                !$template->profoundly_incomplete() &&
                !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
                (mb_strpos($doi, '10.1093/') === false) &&
                $template->blank(DOI_BROKEN_ALIASES)) {
                set_time_limit(120);
            if (str_ireplace(PROXY_HOSTS_TO_DROP, '', $url) !== $url && $template->get('doi-access') === 'free') {
                report_forget("Existing proxy URL resulting from equivalent free DOI; dropping URL");
                $template->forget($url_type);
            } elseif (str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url && $template->get('doi-access') === 'free') {
                report_forget("Existing proxy URL resulting from equivalent free DOI; dropping URL");
                $template->forget($url_type);
            } elseif (str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url && $template->blank(['archive-url', 'archiveurl'])) {
                report_forget("Existing proxy URL resulting from equivalent DOI; fixing URL");
                $template->set($url_type, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (preg_match('~www.sciencedirect.com/science/article/B[^/\-]*\-[^/\-]+\-[^/\-]+/~', $url)) {
                report_forget("Existing Invalid ScienceDirect URL when DOI is present; fixing URL");
                $template->set($url_type, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (preg_match('~www.sciencedirect.com/science/article/pii/\S{0,16}$~i', $url)) { // Too Short
                report_forget("Existing Invalid ScienceDirect URL when DOI is present; fixing URL");
                $template->set($url_type, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (preg_match('~www.springerlink.com/content~i', $url)) { // Dead website
                report_forget("Existing Invalid Springer Link URL when DOI is present; fixing URL");
                $template->set($url_type, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (str_ireplace('insights.ovid.com/pubmed', '', $url) !== $url && $template->has('pmid')) {
                report_forget("Existing OVID URL resulting from equivalent PMID and DOI; dropping URL");
                $template->forget($url_type);
            } elseif ($template->has('pmc') && str_ireplace('iopscience.iop.org', '', $url) !== $url) {
                report_forget("Existing IOP URL resulting from equivalent DOI; dropping URL");
                $template->forget($url_type);
                $template->set($url_type, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (str_ireplace('wkhealth.com', '', $url) !== $url) {
                report_forget("Existing Outdated WK Health URL resulting from equivalent DOI; fixing URL");
                $template->set($url_type, "https://dx.doi.org/" . doi_encode($doi));
            } elseif ($template->has('pmc') && str_ireplace('bmj.com/cgi/pmidlookup', '', $url) !== $url && $template->has('pmid') && $template->get('doi-access') === 'free' && mb_stripos($url, 'pdf') === false) {
                report_forget("Existing The BMJ URL resulting from equivalent PMID and free DOI; dropping URL");
                $template->forget($url_type);
            } elseif ($template->get('doi-access') === 'free' && $template->get('url-status') === 'dead' && $url_type === 'url') {
                report_forget("Existing free DOI; dropping dead URL");
                $template->forget($url_type);
            } elseif (doi_works($template->get('doi')) &&
                        !preg_match(REGEXP_DOI_ISSN_ONLY, $template->get('doi')) &&
                        $url_type !== '' &&
                        (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $template->get($url_type)) !== $template->get($url_type)) &&
                        $template->has_good_free_copy() &&
                        (mb_stripos($template->get($url_type), 'pdf') === false)) {
                report_forget("Existing canonical URL resulting in equivalent free DOI/pmc; dropping URL");
                $template->forget($url_type);
            } elseif (mb_stripos($url, 'pdf') === false && $template->get('doi-access') === 'free' && $template->has('pmc')) {
                curl_setopt($ch_dx, CURLOPT_URL, "https://dx.doi.org/" . doi_encode($doi));
                $ch_return = bot_curl_exec($ch_dx);
                if (mb_strlen($ch_return) > 50) { // Avoid bogus tiny pages
                    $redirectedUrl_doi = curl_getinfo($ch_dx, CURLINFO_EFFECTIVE_URL); // Final URL
                    if (mb_stripos($redirectedUrl_doi, 'cookie') !== false) {
                        break; // @codeCoverageIgnore
                    }
                    if (mb_stripos($redirectedUrl_doi, 'denied') !== false) {
                        break; // @codeCoverageIgnore
                    }
                    $redirectedUrl_doi = url_simplify($redirectedUrl_doi);
                    $url_short = url_simplify($url);
                    if (preg_match('~^https?://.+/pii/?(S?\d{4}[^/]+)~i', $redirectedUrl_doi, $matches ) === 1 ) { // Grab PII numbers
                        $redirectedUrl_doi = $matches[1];  // @codeCoverageIgnore
                    }
                    if (mb_stripos($url_short, $redirectedUrl_doi) !== false ||
                        mb_stripos($redirectedUrl_doi, $url_short) !== false) {
                        report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
                        $template->forget($url_type);
                    } else { // See if $url redirects
                        /** @psalm-taint-escape ssrf */
                        $the_url = $url;
                        curl_setopt($ch_doi, CURLOPT_URL, $the_url);
                        $ch_return = bot_curl_exec($ch_doi);
                        if (mb_strlen($ch_return) > 60) {
                            $redirectedUrl_url = curl_getinfo($ch_doi, CURLINFO_EFFECTIVE_URL);
                            $redirectedUrl_url =url_simplify($redirectedUrl_url);
                            if (mb_stripos($redirectedUrl_url, $redirectedUrl_doi) !== false ||
                                            mb_stripos($redirectedUrl_doi, $redirectedUrl_url) !== false) {
                                report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
                                $template->forget($url_type);
                            }
                        }
                    }
                }
                unset($ch_return);
            }
        }
        $url = $template->get($url_type);
        if ($url && !$template->profoundly_incomplete() && str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url) {
            if (!$template->blank_other_than_comments('pmc')) {
                report_forget("Existing proxy URL resulting from equivalent PMC; dropping URL");
                $template->forget($url_type);
            }
        }
    }
}

function simplify_google_search(string $url): string {
    if (mb_stripos($url, 'q=') === false) {
        return $url;     // Not a search
    }
    if (preg_match('~^https?://.*google.com/search/~', $url)) {
        return $url; // Not a search if the slash is there
    }
    $hash = '';
    if (mb_strpos($url, "#")) {
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
                $url .=  $part . "&";
                break;
            case "cf":
                if ($it_is_blank || str_i_same($part_start1, 'all')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "cs":
                if ($it_is_blank || str_i_same($part_start1, '0')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "btnK":
                if ($it_is_blank || str_i_same($part_start1, 'Google+Search')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "as_epq":
                if ($it_is_blank) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "btnG":
                if ($it_is_blank || str_i_same($part_start1, 'Search')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "rct":
                if ($it_is_blank || str_i_same($part_start1, 'j')) {
                    break; // default
                }
                $url .=  $part . "&";
                break;
            case "resnum":
                if ($it_is_blank || str_i_same($part_start1, '11')) {
                    break; // default
                }
                $url .=  $part . "&";
                break;
            case "ie":
            case "oe":
                if ($it_is_blank || str_i_same($part_start1, 'utf-8')) {
                    break; // UTF-8 is the default
                }
                $url .=  $part . "&";
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
                $url .=  $part . "&";
                break;
            // @codeCoverageIgnoreStart
            default:
                report_minor_error("Unexpected Google URL component:    " . echoable($part));
                $url .=  $part . "&";
                break;
            // @codeCoverageIgnoreEnd
        }
    }

    if (mb_substr($url, -1) === "&") {
        $url = mb_substr($url, 0, -1); //remove trailing &
    }
    $url .= $hash;
    return $url;
}

function clean_and_expand_up_oxford_stuff(Template $template, string $param): void {
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
            $template->set('title', mb_trim($matches[1]));
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
            $new_doi = '10.1093/' . $matches[1] . '/' . $matches[2] . '.003.' . mb_str_pad($matches[4], 4, "0", STR_PAD_LEFT);
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
        $new_doi = '10.1093/med/9780199592548.003.' . mb_str_pad($matches[1], 4, "0", STR_PAD_LEFT);
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

/** This function is recursive sometimes */
function find_indentifiers_in_urls(Template $template, ?string $url_sent = null): bool {
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
            $url = mb_trim($template->get('website'));
            if (mb_strtolower(mb_substr( $url, 0, 6 )) === "ttp://" || mb_strtolower(mb_substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
                $url = "h" . $url;
            }
            if (mb_strtolower(mb_substr( $url, 0, 4 )) !== "http" ) {
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
    return find_indentifiers_in_urls_INSIDE($template, $url, $url_type, !is_null($url_sent));
}

function url_simplify(string $url): string {
    $url = str_replace('/action/captchaChallenge?redirectUri=', '', $url);
    $url = urldecode($url);
    // IEEE is annoying
    if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
        $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
    }
    $url .= '/';
    $url = str_replace(['/abstract/', '/full/', '/full+pdf/', '/pdf/', '/document/', '/html/', '/html+pdf/', '/abs/', '/epdf/', '/doi/', '/xprint/', '/print/', '.short', '.long', '.abstract', '.full', '///', '//'],
                                            ['/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/'], $url);
    $url = mb_substr($url, 0, -1); // Remove the ending slash we added
    $url = (string) preg_split("~[\?\#]~", $url, 2)[0];
    return str_ireplace('https', 'http', $url);
}

function not_an_archive_url_clean(Template $template, string $param): void {
    if (
        preg_match("~^https?://watermark\.silverchair\.com/~", $template->get($param)) ||
        preg_match("~^https?://s3\.amazonaws\.com/academia\.edu~", $template->get($param)) ||
        preg_match("~^https?://onlinelibrarystatic\.wiley\.com/store/~", $template->get($param))
    ) {
        $template->forget($param);
        return;
    }
    clean_existing_urls($template, $param);
    if ($template->get_identifiers_from_url($template->get($param))) {
        if (extract_doi($template->get($param))[1] === '') {
            $template->forget($param);
            return;
        }
    }
}

function clean_existing_urls(Template $template, string $param): void {
    if ($template->blank($param)) {
        return;
    }
    clean_existing_urls_INSIDE($template, $param);
}

function clean_existing_urls_INSIDE(Template $template, string $param): void {
    if (preg_match('~^(?:web\.|www\.).+$~', $template->get($param), $matches) && mb_stripos($template->get($param), 'citation') === false) {
        $template->set($param, 'http://' . $matches[0]);
    }
    $the_original_url = $template->get($param);
    if (preg_match("~^https?://(?:www\.|)researchgate\.net/[^\s]*publication/([0-9]+)_*~i", $template->get($param), $matches)) {
        $template->set($param, 'https://www.researchgate.net/publication/' . $matches[1]);
        if (preg_match('~^\(PDF\)(.+)$~i', mb_trim($template->get('title')), $match)) {
            $template->set('title', mb_trim($match[1]));
        }
    } elseif (preg_match("~^https?://(?:www\.|)academia\.edu/(?:documents/|)([0-9]+)/*~i", $template->get($param), $matches)) {
        $template->set($param, 'https://www.academia.edu/' . $matches[1]);
    } elseif (preg_match("~^https?://(?:www\.|)essopenarchive\.org/users/([0-9]+)/articles/([0-9]+)~i", $template->get($param), $matches)) {
        $template->set($param, 'https://essopenarchive.org/users/' . $matches[1] . '/articles/' . $matches[2]);
    } elseif (preg_match("~^https?://(?:www\.|)zenodo\.org/record/([0-9]+)(?:#|/files/)~i", $template->get($param), $matches)) {
        $template->set($param, 'https://zenodo.org/record/' . $matches[1]);
    } elseif (preg_match("~^https?://(?:www\.|)google\.com/search~i", $template->get($param))) {
        $template->set($param, simplify_google_search($template->get($param)));
    } elseif (preg_match("~^(https?://(?:www\.|)sciencedirect\.com/\S+)\?via(?:%3d|=)\S*$~i", $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    } elseif (preg_match("~^(https?://(?:www\.|)bloomberg\.com/\S+)\?(?:utm_|cmpId=)\S*$~i", $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    } elseif (
        preg_match("~^https?://watermark\.silverchair\.com/~", $template->get($param)) ||
        preg_match("~^https?://s3\.amazonaws\.com/academia\.edu~", $template->get($param)) ||
        preg_match("~^https?://onlinelibrarystatic\.wiley\.com/store/~", $template->get($param))
    ) {
        if ($template->blank(['archive-url', 'archiveurl'])) {
            // Sometimes people grabbed a snap of it
            $template->forget($param);
        }
        return;
    } elseif (preg_match("~^https?://(?:www\.|)bloomberg\.com/tosv2\.html\?vid=&uuid=(?:.+)&url=([a-zA-Z0-9/\+]+=*)$~", $template->get($param), $matches)) {
        if (base64_decode($matches[1])) {
            quietly('report_modification', "Decoding Bloomberg URL.");
            $template->set($param, 'https://www.bloomberg.com' . base64_decode($matches[1]));
        }
    } elseif (preg_match("~^https:?//myprivacy\.dpgmedia\.nl/.+callbackUrl=(.+)$~", $template->get($param), $matches)) {
        $the_match = $matches[1];
        $the_match = urldecode(urldecode($the_match));
        if (preg_match("~^(https.+)/privacy\-?(?:gate|wall|confirm)(?:|/accept)(?:|\-tcf2)\?redirectUri=(/.+)$~", $the_match, $matches)) {
            $template->set($param, $matches[1] . $matches[2]);
        }
    } elseif (preg_match("~^https?://academic\.oup\.com/crawlprevention/governor\?content=([^\s]+)$~", $template->get($param), $matches)) {
        quietly('report_modification', "Decoding OUP URL.");
        $template->set($param, 'https://academic.oup.com' . preg_replace('~(?:\?login=false|\?redirectedFrom=fulltext|\?login=true)$~i', '', urldecode($matches[1])));
        if ($template->get('title') === 'Validate User') {
            $template->set('title', '');
        }
        if ($template->get('website') === 'academic.oup.com') {
            $template->forget('website');
        }
    } elseif (preg_match("~^https?://.*ebookcentral.proquest.+/lib/.+docID(?:%3D|=)(\d+)(|#.*|&.*)(?:|\.)$~i", $template->get($param), $matches)) {
        if ($matches[2] === '#' || $matches[2] === '#goto_toc' || $matches[2] === '&' || $matches[2] === '&query=' || $matches[2] === '&query=#' || preg_match('~^&tm=\d*$~', $matches[2])) {
            $matches[2] = '';
        }
        if (mb_substr($matches[2], -1) === '#' || mb_substr($matches[2], -1) === '.') {
            $matches[2] = mb_substr($matches[2], 0, -1);
        } // Sometime just a trailing # after & part
        quietly('report_modification', "Unmasking Proquest eBook URL.");
        $template->set($param, 'https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=' . $matches[1] . $matches[2]);
    } elseif (preg_match("~^https?://(?:www\.|)figshare\.com/articles/journal_contribution/[^/]+/([0-9]+)$~i", $template->get($param), $matches)) {
        $template->set($param, 'https://figshare.com/articles/journal_contribution/' . $matches[1]);
    }

    if (preg_match("~ebscohost.com.*AN=(\d+)$~", $template->get($param), $matches)) {
        $template->set($param, 'http://connection.ebscohost.com/c/articles/' . $matches[1]);
    }
    if (preg_match("~https?://www\.britishnewspaperarchive\.co\.uk/account/register.+viewer\%252fbl\%252f(\d+)\%252f(\d+)\%252f(\d+)\%252f(\d+)(?:\&|\%253f)~", $template->get($param), $matches)) {
        $template->set($param, 'https://www.britishnewspaperarchive.co.uk/viewer/bl/' . $matches[1] . '/' . $matches[2] . '/' . $matches[3] . '/' . $matches[4]);
    }
    if (preg_match("~^https?(://pubs\.rsc\.org.+)#!divAbstract$~", $template->get($param), $matches)) {
        $template->set($param, 'https' . $matches[1]);
    }
    if (preg_match("~^https?(://pubs\.rsc\.org.+)\/unauth$~", $template->get($param), $matches)) {
        $template->set($param, 'https' . $matches[1]);
    }
    if (preg_match("~^https?://www.healthaffairs.org/do/10.1377/hblog(\d+\.\d+)/full/$~", $template->get($param), $matches)) {
        $template->set($param, 'https://www.healthaffairs.org/do/10.1377/forefront.' . $matches[1] . '/full/');
        $template->forget('access-date');
        $template->forget('accessdate');
        $template->add_if_new('doi', '10.1377/forefront.' . $matches[1]);
        if (mb_strpos($template->get('doi'), 'forefront') !== false) {
            if (mb_strpos($template->get('archiveurl') . $template->get('archive-url'), 'healthaffairs') !== false) {
                $template->forget('archiveurl');
                $template->forget('archive-url');
            }
        }
    }

    if (mb_stripos($template->get($param), 'youtube') !== false) {
        if (preg_match("~^(https?://(?:|www\.|m\.)youtube\.com/watch)(%3F.+)$~", $template->get($param), $matches)) {
            report_info("Decoded YouTube URL");
            $template->set($param, $matches[1] . urldecode($matches[2]));
        }
    }

    if (preg_match("~^https?://(.+\.springer\.com/.+)#citeas$~", $template->get($param), $matches)) {
        $template->set($param, 'https://' . $matches[1]);
    }

    // Proxy stuff
    if (mb_stripos($template->get($param), 'proxy') !== false) {
        // Look for proxy first for speed, this list will grow and grow
        // Use dots, not \. since it might match dot or dash
        if (preg_match("~^https?://ieeexplore.ieee.org.+proxy.*/document/(.+)$~", $template->get($param), $matches)) {
            report_info("Remove proxy from IEEE URL");
            $template->set($param, 'https://ieeexplore.ieee.org/document/' . $matches[1]);
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^https?://(?:www.|)oxfordhandbooks.com.+proxy.*/view/(.+)$~", $template->get($param), $matches)) {
            $template->set($param, 'https://www.oxfordhandbooks.com/view/' . $matches[1]);
            report_info("Remove proxy from Oxford Handbooks URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^https?://(?:www.|)oxfordartonline.com.+proxy.*/view/(.+)$~", $template->get($param), $matches)) {
            $template->set($param, 'https://www.oxfordartonline.com/view/' . $matches[1]);
            report_info("Remove proxy from Oxford Art URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^https?://(?:www.|)sciencedirect.com[^/]+/(\S+)$~i", $template->get($param), $matches)) {
            report_info("Remove proxy from ScienceDirect URL");
            $template->set($param, 'https://www.sciencedirect.com/' . $matches[1]);
            if ($template->has('via')) {
                if (mb_stripos($template->get('via'), 'library') !== false || mb_stripos($template->get('via'), 'direct') === false) {
                       $template->forget('via');
                }
            }
        } elseif (preg_match("~^https?://(?:login\.|)(?:lib|)proxy\.[^\?\/]+\/login\?q?url=(https?://)(.+)$~", $template->get($param), $matches)) {
            if (mb_strpos($matches[2], '/') === false) {
                $template->set($param, $matches[1] . urldecode($matches[2]));
            } else {
                $template->set($param, $matches[1] . $matches[2]);
            }
            report_info("Remove proxy from URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^https?://(?:login\.|)(?:lib|)proxy\.[^\?\/]+\/login\?q?url=(https?%3A%2F%2F.+)$~i", $template->get($param), $matches)) {
            $template->set($param, urldecode($matches[1]));
            report_info("Remove proxy from URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
        }
    }
    if (preg_match("~^https://wikipedialibrary\.idm\.oclc\.org/login\?auth=production&url=(https?://.+)$~i", $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    if (preg_match("~^(https://www\.ancestry(?:institution|).com/discoveryui-content/view/\d+:\d+)\?.+$~i", $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    if (preg_match("~ancestry\.com/cs/offers/join.*url=(http.*)$~i", $template->get($param), $matches)) {
        $template->set($param, str_replace(' ', '+', urldecode($matches[1])));
    }
    if (preg_match("~ancestry\.com/account/create.*returnurl=(http.*)$~i", $template->get($param), $matches)) {
        $template->set($param, str_replace(' ', '+', urldecode($matches[1])));
    }
    if (preg_match("~^https://search\.ancestry(?:|institution)\.com.*cgi-bin/sse.dll.*_phcmd.*(http.+)\'\,\'successSource\'\)$~i", $template->get($param), $matches)) {
        $template->set($param, str_replace(' ', '+', urldecode($matches[1])));
    }
    if (preg_match("~^https://search\.ancestry(?:|institution)\.com.*cgi-bin/sse.dll.*_phcmd.*(http.+)%27\,%27successSource%27\)$~i", $template->get($param), $matches)) {
        $template->set($param, str_replace(' ', '+', urldecode($matches[1])));
    }
    if (preg_match("~^https://www\.ancestry(?:|institution)\.com/facts.*_phcmd.*(http.+)\'\,\'successSource\'\)$~i", $template->get($param), $matches)) {
        $template->set($param, str_replace(' ', '+', urldecode($matches[1])));
    }
    if (preg_match("~^https://www\.ancestry(?:|institution)\.com/facts.*_phcmd.*(http.+)%27\,%27successSource%27\)$~i", $template->get($param), $matches)) {
        $template->set($param, str_replace(' ', '+', urldecode($matches[1])));
    }
    // idm.oclc.org Proxy
    if (mb_stripos($template->get($param), 'idm.oclc.org') !== false && mb_stripos($template->get($param), 'ancestryinstitution') === false) {
        $oclc_found = false;
        if (preg_match("~^https://([^\.\-\/]+)-([^\.\-\/]+)-([^\.\-\/]+)\.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $template->get($param), $matches)) {
            $template->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '/' . $matches[4]);
            $oclc_found = true;
        } elseif (preg_match("~^https://([^\.\-\/]+)\.([^\.\-\/]+)\.com.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $template->get($param), $matches)) {
            $template->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '.com/' . $matches[3]);
            $oclc_found = true;
        } elseif (preg_match("~^https://([^\.\-\/]+)-([^\.\-\/]+)\.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $template->get($param), $matches)) {
            $template->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '/' . $matches[3]);
            $oclc_found = true;
        } elseif (preg_match("~^https://(?:login.?|)[^\.\-\/]+\.idm\.oclc\.org/login\?q?url=(https?://[^\.\-\/]+\.[^\.\-\/]+\.[^\.\-\/]+/.*)$~i", $template->get($param), $matches)) {
            $template->set($param, $matches[1]);
            $oclc_found = true;
        } elseif (preg_match("~^https://(?:login.?|)[^\.\-\/]+\.idm\.oclc\.org/login\?q?url=(https?://[^\.\-\/\%]+\.[^\.\-\/\%]+\.[^\.\-\/\%]+)(\%2f.*)$~i", $template->get($param), $matches)) {
            $template->set($param, $matches[1] . urldecode($matches[2]));
            $oclc_found = true;
        }
        if ($oclc_found) {
            report_info("Remove OCLC proxy from URL");
            if (mb_stripos($template->get('via'), 'wiki') !== false || mb_stripos($template->get('via'), 'oclc') !== false) {
                $template->forget('via');
            }
        }
    }
    if (mb_stripos($template->get($param), 'https://access.newspaperarchive.com/') === 0) {
        $template->set($param, str_ireplace('https://access.newspaperarchive.com/', 'https://www.newspaperarchive.com/', $template->get($param)));
    }
    if (mb_stripos($template->get($param), 'http://access.newspaperarchive.com/') === 0) {
        $template->set($param, str_ireplace('http://access.newspaperarchive.com/', 'https://www.newspaperarchive.com/', $template->get($param)));
    }
    clean_and_expand_up_oxford_stuff($template, $param);

    if (preg_match('~^https?://([^/]+)/~', $template->get($param), $matches)) {
        $the_host = $matches[1];
    } else {
        $the_host = '';
    }
    if (mb_stripos($the_host, 'proxy') !== false || mb_stripos($the_host, 'lib') !== false || mb_stripos($the_host, 'mutex') !== false) {
        // Generic proxy code www.host.com.proxy-stuff/dsfasfdsfasdfds
        if (preg_match("~^https?://(www\.[^\./\-]+\.com)\.[^/]*(?:proxy|library|\.lib\.|mutex\.gmu)[^/]*/(\S+)$~i", $template->get($param), $matches)) {
            report_info("Remove proxy from " . echoable($matches[1]) . " URL");
            $template->set($param, 'https://' . $matches[1] . '/' . $matches[2]);
            if ($template->has('via')) {
                $template->forget('via');
            }
            // Generic proxy code www-host-com.proxy-stuff/dsfasfdsfasdfds
        } elseif (preg_match("~^https?://www\-([^\./\-]+)\-com[\.\-][^/]*(?:proxy|library|\.lib\.|mutex\.gmu)[^/]*/(\S+)$~i", $template->get($param), $matches)) {
            $matches[1] = 'www.' . $matches[1] . '.com';
            report_info("Remove proxy from " . echoable($matches[1]) . " URL");
            $template->set($param, 'https://' . $matches[1] . '/' . $matches[2]);
            if ($template->has('via')) {
                $template->forget('via');
            }
        }
    }
    if (mb_stripos($template->get($param), 'galegroup') !== false) {
        if (preg_match("~^(?:http.+url=|)https?://go.galegroup.com(%2fps.+)$~i", $template->get($param), $matches)) {
            $template->set($param, 'https://go.galegroup.com' . urldecode($matches[1]));
            report_info("Remove proxy from Gale URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
            if ($template->has('via') && mb_stripos($template->get('via'), 'gale') === false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^http.+url=https?://go\.galegroup\.com/(.+)$~i", $template->get($param), $matches)) {
            $template->set($param, 'https://go.galegroup.com/' . $matches[1]);
            report_info("Remove proxy from Gale URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
            if ($template->has('via') && mb_stripos($template->get('via'), 'gale') === false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^(?:http.+url=|)https?://link.galegroup.com(%2fps.+)$~i", $template->get($param), $matches)) {
            $template->set($param, 'https://link.galegroup.com' . urldecode($matches[1]));
            report_info("Remove proxy from Gale URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
            if ($template->has('via') && mb_stripos($template->get('via'), 'gale') === false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^http.+url=https?://link\.galegroup\.com/(.+)$~", $template->get($param), $matches)) {
            $template->set($param, 'https://link.galegroup.com/' . $matches[1]);
            report_info("Remove proxy from Gale URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
            if ($template->has('via') && mb_stripos($template->get('via'), 'gale') === false) {
                $template->forget('via');
            }
        }
    }
    if (mb_stripos($template->get($param), 'proquest') !== false) {
        if (preg_match("~^(?:http.+/login/?\?url=|)https?://(?:0\-|)(?:search|www).proquest.com[^/]+(|/[^/]+)+/docview/(.+)$~", $template->get($param), $matches)) {
            $template->set($param, 'https://www.proquest.com' . $matches[1] . '/docview/' . $matches[2]);
            report_info("Remove proxy from ProQuest URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
            if ($template->has('via') && mb_stripos($template->get('via'), 'proquest') === false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^(?:http.+/login/?\?url=|)https?://(?:0\-|)(?:www|search).proquest.+scoolaid\.net(|/[^/]+)+/docview/(.+)$~", $template->get($param), $matches)) {
            $template->set($param, 'https://www.proquest.com' . $matches[1] . '/docview/' . $matches[2]);
            report_info("Remove proxy from ProQuest URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
            if ($template->has('via') && mb_stripos($template->get('via'), 'proquest') === false) {
                $template->forget('via');
            }
        } elseif (preg_match("~^http.+/login/?\?url=https://www\.proquest\.com/docview/(.+)$~", $template->get($param), $matches)) {
            $template->set($param, 'https://www.proquest.com/docview/' . $matches[1]);
            report_info("Remove proxy from ProQuest URL");
            if ($template->has('via') && mb_stripos($template->get('via'), 'library') !== false) {
                $template->forget('via');
            }
            if ($template->has('via') && mb_stripos($template->get('via'), 'proquest') === false) {
                $template->forget('via');
            }
        }
        $changed = false;
        if (preg_match("~^https?://(?:|search|www).proquest.com/(.+)/docview/(.+)$~", $template->get($param), $matches)) {
            if ($matches[1] !== 'dissertations') {
                $changed = true;
                $template->set($param, 'https://www.proquest.com/docview/' . $matches[2]); // Remove specific search engine
            }
        }
        if (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/(.+)/(?:abstract|record|fulltext|preview|page).*$~i", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, 'https://www.proquest.com/docview/' . $matches[1]); // You have to login to get that
        }
        if (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/(.+)\?.+$~", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, 'https://www.proquest.com/docview/' . $matches[1]); // User specific information
        }
        if (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/([0-9]+)/$~i", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, 'https://www.proquest.com/docview/' . $matches[1]);
        }
        if (preg_match("~^https?://(?:www|search)\.proquest\.com/docview/([0-9]+)/[0-9A-Z]+/?\??$~", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, 'https://www.proquest.com/docview/' . $matches[1]); // User specific information
        }
        if (preg_match("~^https?://(?:www|search)\.proquest\.com/docview/([0-9]+)/[0-9A-Z]+/[0-9]+\??$~", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, 'https://www.proquest.com/docview/' . $matches[1]); // User specific information
        }
        if (preg_match("~^https?://search\.proquest\.com/docview/(.+)$~", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, 'https://www.proquest.com/docview/' . $matches[1]);
        }
        if (preg_match("~^https?://search\.proquest\.com/dissertations/docview/(.+)$~", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, 'https://www.proquest.com/dissertations/docview/' . $matches[1]);
        }
        if (preg_match("~^https?://search\.proquest\.com/openview/(.+)$~", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, 'https://www.proquest.com/openview/' . $matches[1]);
        }
        if (preg_match("~^(https://www\.proquest\.com/docview/.+)\?$~", $template->get($param), $matches)) {
            $changed = true;
            $template->set($param, $matches[1]);
        }
        if (preg_match("~^(.+)/se-[^\/]+/?$~", $template->get($param), $matches)) {
            $template->set($param, $matches[1]);
            $changed = true;
        }
        if ($changed) {
            report_info("Normalized ProQuest URL");
        }
    }
    if ($param === 'url' && $template->wikiname() === 'cite book' && should_url2chapter($template, false)) {
        $template->rename('url', 'chapter-url');
        // Comment out because "never used" $param = 'chapter-url';
        return;
    }
    $the_new_url = $template->get('url');
    if ($the_original_url !== $the_new_url) {
        $template->get_identifiers_from_url();
    }
    if (mb_stripos($template->get('url'), 'cinemaexpress.com') !== false) {
        foreach (WORK_ALIASES as $worky) {
            $lower = mb_strtolower($template->get($worky));
            if ($lower === 'the new indian express' || $lower === '[[the new indian express]]' || $lower === 'm.cinemaexpress.com' || $lower === 'cinemaexpress.com' || $lower === 'www.cinemaexpress.com') {
                $template->set($worky, '[[Cinema Express]]');
            }
        }
    }
}

function find_indentifiers_in_urls_INSIDE(Template $template, string $url, string $url_type, bool $url_sent): bool {
    static $ch_jstor;
    static $ch_pmc;
    if ($ch_jstor === null) {
        if (TRAVIS) {
            $time = 3.0;
        } else {
            $time = 1.0; // @codeCoverageIgnore
        }
        $ch_jstor = bot_curl_init($time, []);
        $ch_pmc = bot_curl_init($time, []);
    }

    $update_url = function (string $url_type, string $url) use ($url_sent, $template) {
        if (!$url_sent) {
            $template->set($url_type, $url);
        }
    };

    if (mb_strtolower(mb_substr( $url, 0, 6 )) === "ttp://" || mb_strtolower(mb_substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
        $url = "h" . $url;
        $update_url($url_type, $url);
    }
    // Common ones that do not help
    if (mb_strpos($url, 'books.google') !== false ||
            mb_strpos($url, 'researchgate.net') !== false ||
            mb_strpos($url, 'academia.edu') !== false) {
        return false;
    }

    // Abstract only websites
    if (mb_strpos($url, 'orbit.dtu.dk/en/publications') !== false) { // This file path only
        if (!$url_sent) {
            if ($template->has('pmc')) {
                $template->forget($url_type); // Remove it to make room for free-link
            } elseif ($template->has('doi') && $template->get('doi-access') === 'free') {
                $template->forget($url_type); // Remove it to make room for free-link
            }
        }
        return false;
    }
    // IEEE
    if (mb_strpos($url, 'ieeexplore') !== false) {
        if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
            $update_url($url_type, $url);
        }
        if (preg_match('~^https?://ieeexplore\.ieee\.org(?:|\:80)/(?:|abstract/)document/(\d+)/?(?:|\?reload=true)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
            $update_url($url_type, $url); // Normalize to HTTPS and remove abstract and remove trailing slash etc
        }
        if (preg_match('~^https?://ieeexplore\.ieee\.org.*/iel5/\d+/\d+/(\d+).pdf(?:|\?.*)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
            $update_url($url_type, $url);
        }
        if (preg_match('~^https://ieeexplore\.ieee\.org/document/0+(\d+)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1]; // Remove leading zeros
            $update_url($url_type, $url);
        }
    }

    // semanticscholar
    if (mb_stripos($url, 'semanticscholar.org') !== false) {
        $s2cid = getS2CID($url);
        if ($s2cid === '') {
            return false;
        }
        if ($template->has('s2cid') && $s2cid !== $template->get('s2cid')) {
            report_warning('Existing URL does not match existing S2CID: ' . echoable($template->get('s2cid')));
            return false;
        }
        if ($template->has('S2CID') && $s2cid !== $template->get('S2CID')) {
            report_warning('Existing URL does not match existing S2CID: ' . echoable($template->get('S2CID')));
            return false;
        }
        $template->add_if_new('s2cid', $s2cid);
        if ($template->wikiname() !== 'cite web' || !$template->blank(['doi', 'pmc', 'pmid', 'journal'])) { // Avoid template errors
            if ($template->has('s2cid') && !$url_sent && $template->blank(['archiveurl', 'archive-url'])) {
                $template->forget($url_type);
                return true;  // Time to clean up
            }
            if (!$url_sent && mb_stripos($url, 'pdf') === false) {
                $template->forget($url_type);
                return true;
            }
            if (!$url_sent && $template->has_good_free_copy() && get_semanticscholar_license($s2cid) === false) {
                report_warning('Removing un-licensed Semantic Scholar URL that was converted to S2CID parameter');
                $template->forget($url_type);
                return true;
            }
        }
        return true;
    }

    if (preg_match("~^(https?://.+\/.+)\?casa_token=.+$~", $url, $matches)) {
        $url = $matches[1];
        $update_url($url_type, $url);
    }

    if (mb_stripos($url, 'jstor') !== false) {
        // remove ?seq=1#page_scan_tab_contents off of jstor urls
        // We do this since not all jstor urls are recognized below
        if (preg_match("~^(https?://\S*jstor.org\S*)\?seq=1#[a-zA-Z_]+$~", $url, $matches)) {
            $url = $matches[1];
            $update_url($url_type, $url);
        }
        if (preg_match("~^(https?://\S*jstor.org\S*)\?refreqid=~", $url, $matches)) {
            $url = $matches[1];
            $update_url($url_type, $url);
        }
        if (preg_match("~^(https?://\S*jstor.org\S*)\?origin=~", $url, $matches)) {
            if (mb_stripos($url, "accept") !== false) {
                bot_debug_log("Accept Terms and Conditions JSTOR found : " . $url); // @codeCoverageIgnore
            } else {
                $url = $matches[1];
                $update_url($url_type, $url);
            }
        }
        if (mb_stripos($url, 'plants.jstor.org') !== false) {
            return false; # Plants database, not journal
        }
        // https://www.jstor.org.stuff/proxy/stuff/stable/10.2307/3347357 and such
        // Optional 0- at front.
        // DO NOT change www.jstor.org to www\.jstor\.org -- Many proxies use www-jstor-org
        if (preg_match('~^(https?://(?:0-www.|www.|)jstor.org)(?:\S*proxy\S*/|/)(?:stable|discover)/10.2307/(.+)$~i', $url, $matches)) {
            $url = $matches[1] . '/stable/' . $matches[2]; // that is default. This also means we get jstor not doi
            $update_url($url_type, $url); // Will probably call forget below
        }
        // https://www.jstor.org.libweb.lib.utsa.edu/stable/3347357 and such
        // Optional 0- at front.
        // DO NOT change www.jstor.org to www\.jstor\.org -- Many proxies use www-jstor-org
        // https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10 and such
        if (preg_match('~^https?://(?:0-www.|www.|)jstor.org\.[^/]+/(?:stable|discover)/(.+)$~i', $url, $matches)) {
            $url = 'https://www.jstor.org/stable/' . $matches[1];
            $update_url($url_type, $url);
        }
        // Remove junk from URLs
        while (preg_match('~^https?://www\.jstor\.org/stable/(.+)(?:&ved=|&usg=|%3Fseq%3D1#|\?seq=1#|#metadata_info_tab_contents|;uid=|\?uid=|;sid=|\?sid=)~i', $url, $matches)) {
            $url = 'https://www.jstor.org/stable/' . $matches[1];
            $update_url($url_type, $url);
        }

        if (preg_match('~^https?://(?:www\.|)jstor\.org/stable/(?:pdf|pdfplus)/(.+)\.pdf$~i', $url, $matches) ||
            preg_match('~^https?://(?:www\.|)jstor\.org/tc/accept\?origin=(?:\%2F|/)stable(?:\%2F|/)pdf(?:\%2F|/)(\d{3,})\.pdf$~i', $url, $matches)) {
            if ($matches[1] === $template->get('jstor')) {
                if (!$url_sent) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return false;
            } elseif ($template->blank('jstor')) {
                curl_setopt($ch_jstor, CURLOPT_URL, 'https://www.jstor.org/citation/ris/' . $matches[1]);
                $dat = bot_curl_exec($ch_jstor);
                if ($dat &&
                        mb_stripos($dat, 'No RIS data found for') === false &&
                        mb_stripos($dat, 'Block Reference') === false &&
                        mb_stripos($dat, 'A problem occurred trying to deliver RIS data') === false &&
                        mb_substr_count($dat, '-') > 3) { // It is actually a working JSTOR.  Not sure if all PDF links are done right
                    if (!$url_sent && $template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                    return $template->add_if_new('jstor', $matches[1]);
                }
                unset($dat);
            }
        }
        if ($template->has('jstor') && preg_match('~^https?://(?:www\.|)jstor\.org/(?:stable|discover)/(?:|pdf/)' . $template->get('jstor') . '(?:|\.pdf)$~i', $url)) {
            if (!$url_sent) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
            return false;
        }
    } // JSTOR
    if (preg_match('~^https?://(?:www\.|)archive\.org/detail/jstor\-(\d{5,})$~i', $url, $matches)) {
        $template->add_if_new('jstor', $matches[1]);
        if (!$url_sent) {
            if ($template->has_good_free_copy()) {
                $template->forget($url_type);
            }
        }
        return false;
    }

    if (preg_match('~^https?(://(?:0-www\.|www\.|ucsb\.|)worldcat(?:libraries|)\.org.+)(?:\&referer=brief_results|\?referer=di&ht=edition|\?referer=brief_results|%26referer%3Dbrief_results|\?ht=edition&referer=di|\?referer=br&ht=edition|\/viewport)$~i', $url, $matches)) {
        $url = 'https' . $matches[1];
        $update_url($url_type, $url);
    }
    if (preg_match('~^https?(://(?:0-www\.|www\.|ucsb\.)worldcat(?:libraries|)\.org.+)/oclc/(\d+)$~i', $url, $matches)) {
        $url = 'https://www.worldcat.org/oclc/' . $matches[2];
        $update_url($url_type, $url);
    }

    if (preg_match('~^https?://onlinelibrary\.wiley\.com/doi/(.+)/abstract\?(?:deniedAccessCustomise|userIsAuthenticated)~i', $url, $matches)) {
        $url = 'https://onlinelibrary.wiley.com/doi/' . $matches[1] . '/abstract';
        $update_url($url_type, $url);
    }

    if (preg_match('~^https?://(?:dx\.|)doi\.org/10\.1007/springerreference_(\d+)$~i', $url, $matches)) {
        $url = 'http://www.springerreference.com/index/doi/10.1007/springerreference_' . $matches[1];
        $update_url($url_type, $url);
    }

    if (preg_match("~^https?://(?:(?:dx\.|www\.|)doi\.org|doi\.library\.ubc\.ca)/([^\?]*)~i", $url, $match)) {
        if ($template->has('doi')) {
            $doi = $template->get('doi');
            if (str_i_same($doi, $match[1]) || str_i_same($doi, urldecode($match[1]))) {
                if (!$url_sent && $template->get('doi-access') === 'free') {
                    quietly('report_modification', "URL is hard-coded DOI; removing since we already have free DOI parameter");
                    $template->forget($url_type);
                }
                return false;
            }
            // The DOIs do not match
            if (!$url_sent) {
                report_warning('doi.org URL does not match existing DOI parameter, investigating...');
            }
            if ($doi !== $template->get3('doi')) {
                return false;
            }
            if (doi_works($match[1]) && !doi_works($doi)) {
                $template->set('doi', $match[1]);
                if (!$url_sent) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return true;
            }
            if (!doi_works($match[1]) && doi_works($doi)) {
                if (!$url_sent) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return false;
            }
            return false; // Both valid or both invalid (could be legit if chapter and book are different DOIs
        }
        if ($template->add_if_new('doi', urldecode($match[1]))) { // Will expand from DOI when added
            if (!$url_sent && $template->has_good_free_copy()) {
                quietly('report_modification', "URL is hard-coded DOI; converting to use DOI parameter.");
                $template->forget($url_type);
            }
            return true;
        } else {
            return false; // "bad" doi?
        }
    }
    if (mb_stripos($url, 'oxforddnb.com') !== false) {
        return false; // generally bad
    }
    $doi = extract_doi($url)[1];
    if ($doi) {
        if (bad_10_1093_doi($doi)) {
            return false;
        }
        $old_jstor = $template->get('jstor');
        if (mb_stripos($url, 'jstor')) {
            check_doi_for_jstor($doi, $template);
        }
        if (!$url_sent && $old_jstor !== $template->get('jstor') && mb_stripos($url, 'pdf') === false) {
            if ($template->has_good_free_copy()) {
                $template->forget($url_type);
            }
        }
        $template->tidy_parameter('doi'); // Sanitize DOI before comparing
        if ($template->has('doi') && mb_stripos($doi, $template->get('doi')) === 0) { // DOIs are case-insensitive
            if (doi_works($doi) && !$url_sent && mb_strpos(mb_strtolower($url), ".pdf") === false && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) && mb_strpos(mb_strtolower($url), "supplemental") === false && mb_strpos(mb_strtolower($url), "figure") === false) {
                if ($template->has_good_free_copy()) {
                    report_forget("Recognized existing DOI in URL; dropping URL");
                    $template->forget($url_type);
                }
            }
            return false;  // URL matched existing DOI, so we did not use it
        }
        if ($template->add_if_new('doi', $doi)) {
            $doi = $template->get('doi');
            if (doi_works($doi)) {
                if (!$url_sent) {
                    if (mb_strpos(mb_strtolower($url), ".pdf") === false && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
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
        if (mb_stripos($url, $doi) !== false) { // DOIs are case-insensitive
            if (doi_works($doi) && !$url_sent && mb_strpos(mb_strtolower($url), ".pdf") === false && not_bad_10_1093_doi($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
                if ($template->has_good_free_copy()) {
                    report_forget("Recognized the existing DOI in URL; dropping URL");
                    $template->forget($url_type);
                }
            }
            return false;  // URL matched existing DOI, so we did not use it
        }
    }

    // JSTOR

    if (mb_stripos($url, "jstor.org") !== false) {
        $sici_pos = mb_stripos($url, "sici");
        if ($sici_pos) { // Outdated url style
            use_sici($template); // Grab what we can.  We do not want this URL incorrectly parsed below, or even waste time trying.
            return false;
        }
        if (preg_match("~^/(?:\w+/)*(\d{5,})[^\d%\-]*(?:\?|$)~", mb_substr($url, (int) mb_stripos($url, 'jstor.org') + 9), $match) ||
                    preg_match("~^https?://(?:www\.)?jstor\.org\S+(?:stable|discovery)/(?:10\.7591/|)(\d{5,}|(?:j|J|histirel|jeductechsoci|saoa|newyorkhist)\.[a-zA-Z0-9\.]+)$~", $url, $match)) {
            if (!$url_sent) {
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
                if (!$url_sent) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return $template->add_if_new('bibcode', urldecode($bibcode[1]));
            } elseif (!$url_sent && urldecode($bibcode[1]) === $template->get('bibcode')) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
        } elseif (mb_stripos($url, '.nih.gov') !== false) {

            if (preg_match("~^https?://(?:www\.|)pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d{4,})"
                            . "|^https?://(?:www\.|pmc\.|)ncbi\.nlm\.nih\.gov/(?:m/|labs/|)pmc/articles/(?:PMC|instance)?(\d{4,})"
                            . "|^https?://pmc\.ncbi\.nlm\.nih\.gov/(?:m/|labs/|)articles/(?:PMC)?(\d{4,})~i", $url, $match)) {
                if (preg_match("~\?term~i", $url)) {  // ALWAYS ADD new @$match[] below
                    return false; // A search such as https://www.ncbi.nlm.nih.gov/pmc/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
                }
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                if ($template->blank('pmc')) {
                    quietly('report_modification', "Converting URL to PMC parameter");
                }
                $new_pmc = (string) @$match[1] . @$match[2] . @$match[3];
                // php stan does not understand that this could because of the insanity of regex and 8-bit characters and PHP bugs end up being empty
                if ($new_pmc === '') { // @phpstan-ignore-line
                    bot_debug_log("PMC oops");
                    return false;
                }
                if (!$url_sent) {
                    if (mb_stripos($url, ".pdf") !== false) {
                        $test_url = "https://www.ncbi.nlm.nih.gov/pmc/articles/PMC" . $new_pmc . "/";
                        curl_setopt($ch_pmc, CURLOPT_URL, $test_url);
                        $the_pmc_body = bot_curl_exec($ch_pmc);
                        $httpCode = (int) curl_getinfo($ch_pmc, CURLINFO_HTTP_CODE);
                        if ($httpCode > 399 || $httpCode === 0 || mb_strpos($the_pmc_body, 'Administrative content — journal masthead, notices, indexes, etc - PMC') !== false) { // Some PMCs do NOT resolve. So leave URL
                            return $template->add_if_new('pmc', $new_pmc);
                        }
                    }
                    if (mb_stripos(str_replace("printable", "", $url), "table") === false) {
                        $template->forget($url_type); // This is the same as PMC auto-link
                    }
                }
                return $template->add_if_new('pmc', $new_pmc);

            } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?\?term=(\d+)$~', $url, $match)) {
                $pos_pmid = $match[1];
                $old_pmid = $template->get('pmid');
                if ($old_pmid === '' || ($old_pmid === $pos_pmid)) {
                    $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $pos_pmid . '/');
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
            . ".*?=?(\d{4,})~i", $url, $match) ||
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
                if (!$url_sent) {
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

        } elseif (mb_stripos($url, 'europepmc.org') !== false) {
            if (preg_match("~^https?://(?:www\.|)europepmc\.org/articles?/pmc/?(\d{4,})~i", $url, $match) ||
                    preg_match("~^https?://(?:www\.|)europepmc\.org/scanned\?pageindex=(?:\d+)\&articles=pmc(\d{4,})~i", $url, $match)) {
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                if ($template->blank('pmc')) {
                    quietly('report_modification', "Converting Europe URL to PMC parameter");
                }
                if (!$url_sent && mb_stripos($url, ".pdf") === false) {
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
                if (!$url_sent) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return $template->add_if_new('pmid', $match[1]);
            }
            return false;
        } elseif (mb_stripos($url, 'pubmedcentralcanada.ca') !== false) {
            if (preg_match("~^https?://(?:www\.|)pubmedcentralcanada\.ca/pmcc/articles/PMC(\d{4,})(?:|/.*)$~i", $url, $match)) {
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                if ($template->blank('pmc')) {
                    quietly('report_modification', "Converting Canadian URL to PMC parameter");
                }
                if (!$url_sent) {
                    $template->forget($url_type);  // Always do this conversion, since website is gone!
                }
                return $template->add_if_new('pmc', $match[1]);
            }
            return false;
        } elseif (mb_stripos($url, 'citeseerx') !== false) {
            if (preg_match("~^https?://citeseerx\.ist\.psu\.edu/viewdoc/(?:summary|download)(?:\;jsessionid=[^\?]+|)\?doi=([0-9.]*)(?:&.+)?~", $url, $match)) {
                if ($template->blank('citeseerx')) {
                    quietly('report_modification', "URL is hard-coded citeseerx; converting to use citeseerx parameter.");
                }
                if (!$url_sent) {
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

        } elseif (mb_stripos($url, 'arxiv') !== false) {
            if (preg_match("~\barxiv\.org/.*(?:pdf|abs|ftp/arxiv/papers/\d{4})/(.+?)(?:\.pdf)?$~i", $url, $match)) {
                /* ARXIV
                * See https://arxiv.org/help/arxiv_identifier for identifier formats
                */
                if (preg_match("~[A-z\-\.]+/\d{7}~", $match[1], $arxiv_id) // pre-2007
                        || preg_match("~\d{4}\.\d{4,5}(?:v\d+)?~", $match[1], $arxiv_id) // post-2007
                        ) {
                    quietly('report_modification', "Converting URL to arXiv parameter");
                    $ret = $template->add_if_new('arxiv', $arxiv_id[0]); // Have to add before forget to get cite type right
                    if (!$url_sent) {
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
                if (!$url_sent) {
                    $template->forget($url_type);
                    if (mb_stripos($template->get('publisher'), 'amazon') !== false) {
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
                if (!$url_sent) {
                    $template->forget($url_type); // will forget accessdate too
                    if (mb_stripos($template->get('publisher'), 'amazon') !== false) {
                        $template->forget('publisher');
                    }
                }
            }
        } elseif (mb_stripos($url, 'handle') !== false || mb_stripos($url, 'persistentId=hdl:') !== false) {
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
            // take off session stuff - urlappend seems to be used for page numbers and such
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
            if (mb_strlen($handle) < 6 || mb_strpos($handle, '/') === false) {
                return false;
            }
            if (mb_strpos($handle, '123456789') === 0) {
                return false;
            }

            $the_question = mb_strpos($handle, '?');
            if ($the_question !== false) {
                $handle = mb_substr($handle, 0, $the_question) . '?' . str_replace('%3D', '=', urlencode(mb_substr($handle, $the_question+1)));
            }

            // Verify that it works as a hdl
            $the_header_loc = hdl_works($handle);
            if ($the_header_loc === false || $the_header_loc === null) {
                return false;
            }
            if ($template->blank('hdl')) {
                quietly('report_modification', "Converting URL to HDL parameter");
            }
            if (!$url_sent) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
            if (preg_match('~^([^/]+/[^/]+)/.*$~', $handle, $matches)  // Might be padded with stuff
                && mb_stripos($the_header_loc, $handle) === false
                && mb_stripos($the_header_loc, $matches[1]) !== false) {  // Too long ones almost never resolve, but we have seen at least one
                $handle = $matches[1]; // @codeCoverageIgnore
            }
            return $template->add_if_new('hdl', $handle);
        } elseif (mb_stripos($url, 'zbmath.org') !== false) {
            if (preg_match("~^https?://zbmath\.org/\?(?:format=complete&|)q=an:([0-9][0-9][0-9][0-9]\.[0-9][0-9][0-9][0-9][0-9])~i", $url, $match)) {
                if ($template->blank('zbl')) {
                    quietly('report_modification', "Converting URL to ZBL parameter");
                }
                if (!$url_sent) {
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
                if (!$url_sent) {
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
            //if (!$url_sent) {
            //    $template->forget($url_type); // This points to a review and not the article
            //}
            return $template->add_if_new('mr', $match[1]);
        } elseif (preg_match("~^https?://papers\.ssrn\.com(?:/sol3/papers\.cfm\?abstract_id=|/abstract=)([0-9]+)~i", $url, $match)) {
            if ($template->blank('ssrn')) {
                quietly('report_modification', "Converting URL to SSRN parameter");
            }
            if (!$url_sent) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite journal');
                    }
                }
            }
            return $template->add_if_new('ssrn', $match[1]);
        } elseif (mb_stripos($url, 'osti.gov') !== false) {
            if (preg_match("~^https?://(?:www\.|)osti\.gov/(?:scitech/|)(?:biblio/|)(?:purl/|)([0-9]+)(?:\.pdf|)~i", $url, $match)) {
                if ($template->blank('osti')) {
                    quietly('report_modification', "Converting URL to OSTI parameter");
                }
                if (!$url_sent) {
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
                if (!$url_sent) {
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
        } elseif (mb_stripos($url, 'worldcat.org') !== false) {
            if (preg_match("~^https?://(?:www\.|)worldcat\.org(?:/title/\S+)?/oclc/([0-9]+)~i", $url, $match)) {
                if (mb_strpos($url, 'edition') && ($template->wikiname() !== 'cite book')) {
                    report_warning('Not adding OCLC because is appears to be a weblink to a list of editions: ' . echoable($match[1]));
                    return false;
                }
                $check_me = $template->get('work') . $template->get('website') . $template->get('publisher');
                if (mb_stripos($check_me, 'oclc') !== false || mb_stripos($check_me, 'open library') !== false) {
                    return $template->add_if_new('oclc', $match[1]);
                }
                if ($template->blank('oclc')) {
                    quietly('report_modification', "Converting URL to OCLC parameter");
                }
                if ($template->wikiname() === 'cite web') {
                    // $template->change_name_to('cite book');  // Better template choice
                }
                if (!$url_sent && $template->wikiname() === 'cite book') {
                    $template->forget($url_type);
                }
                return $template->add_if_new('oclc', $match[1]);
            } elseif (preg_match("~^https?://(?:www\.|)worldcat\.org/issn/(\d{4})(?:|-)(\d{3}[\dxX])$~i", $url, $match)) {
                if ($template->blank('issn')) {
                    quietly('report_modification', "Converting URL to ISSN parameter");
                }
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal'); // Better template choice
                }
                if (!$url_sent) {
                    $template->forget($url_type);
                }
                return $template->add_if_new('issn_force', $match[1] . '-' . $match[2]);
            }
            return false;
        } elseif (preg_match("~^https?://lccn\.loc\.gov/(\d{4,})$~i", $url, $match) &&
                            (mb_stripos($template->parsed_text(), 'library') === false)) { // Sometimes it is web cite to Library of Congress
            if ($template->wikiname() === 'cite web') {
                $template->change_name_to('cite book');  // Better template choice
            }
            if ($template->blank('lccn')) {
                quietly('report_modification', "Converting URL to LCCN parameter");
            }
            if (!$url_sent) {
                $template->forget($url_type);
            }
            return $template->add_if_new('lccn', $match[1]);
        } elseif (preg_match("~^https?://openlibrary\.org/books/OL/?(\d{4,}[WM])(?:|/.*)$~i", $url, $match)) { // We do W "work" and M "edition", but not A, which is author
            if ($template->blank('ol')) {
                quietly('report_modification', "Converting URL to OL parameter");
            }
            if ($template->wikiname() === 'cite web') {
                $template->change_name_to('cite book');  // Better template choice
            }
            if (!$url_sent) {
                $template->forget($url_type);
            }
            return $template->add_if_new('ol', $match[1]);
        } elseif (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/(\d{4,})$~i", $url, $match) && $template->has('title') && $template->blank('id')) {
            if ($template->add_if_new('id', '{{ProQuest|' . $match[1] . '}}')) {
                quietly('report_modification', 'Converting URL to ProQuest parameter');
                if (!$url_sent) {
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
            if (!$url_sent) {
                quietly('report_modification', 'Extracting URL from archive');
                $template->set($url_type, $match[1]);
                $template->add_if_new('archive-url', $match[0]);
                return false; // We really got nothing
            }
        }
        /// THIS MUST BE LAST
    }
    return false;
}
