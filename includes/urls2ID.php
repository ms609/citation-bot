<?php

declare(strict_types=1);

function find_indentifiers_in_urls(Template $template, ?string $url_sent = null): bool {
    static $ch_jstor;
    static $ch_pmc;
    if (null === $ch_jstor) {
        if (TRAVIS) {
            $time = 3.0;
        } else {
            $time = 1.0; // @codeCoverageIgnore
        }
        $ch_jstor = bot_curl_init($time, []);
        $ch_pmc = bot_curl_init($time, []);        
    }
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
            if (mb_strtolower(substr( $url, 0, 6 )) === "ttp://" || mb_strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
                $url = "h" . $url;
            }
            if (mb_strtolower(substr( $url, 0, 4 )) !== "http" ) {
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

    if (mb_strtolower(substr( $url, 0, 6 )) === "ttp://" || mb_strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
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
                $template->set($url_type, $url); // remove leading zeroes
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
        // remove ?seq=1#page_scan_tab_contents off of jstor urls
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
        while (preg_match('~^https?://www\.jstor\.org/stable/(.+)(?:&ved=|&usg=|%3Fseq%3D1#|\?seq=1#|#metadata_info_tab_contents|;uid=|\?uid=|;sid=|\?sid=)~i', $url, $matches)) {
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
                curl_setopt($ch_jstor, CURLOPT_URL, 'https://www.jstor.org/citation/ris/' . $matches[1]);
                $dat = bot_curl_exec($ch_jstor);
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
            if (doi_works($doi) && is_null($url_sent) && strpos(mb_strtolower($url), ".pdf") === false && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) && strpos(mb_strtolower($url), "supplemental") === false && strpos(mb_strtolower($url), "figure") === false) {
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
                if (is_null($url_sent)) {
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
        if (stripos($url, $doi) !== false) { // DOIs are case-insensitive
            if (doi_works($doi) && is_null($url_sent) && strpos(mb_strtolower($url), ".pdf") === false && not_bad_10_1093_doi($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
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
                $new_pmc = (string) @$match[1] . @$match[2] . @$match[3];
                // php stan does not understand that this could because of the insanity of regex and 8-bit characters and PHP bugs end up being empty
                if ($new_pmc === '') { // @phpstan-ignore-line
                    bot_debug_log("PMC oops");
                    return false;
                }
                if (is_null($url_sent)) {
                    if (stripos($url, ".pdf") !== false) {
                        $test_url = "https://www.ncbi.nlm.nih.gov/pmc/articles/PMC" . $new_pmc . "/";
                        curl_setopt($ch_pmc, CURLOPT_URL, $test_url);
                        $the_pmc_body = bot_curl_exec($ch_pmc);
                        $httpCode = (int) curl_getinfo($ch_pmc, CURLINFO_HTTP_CODE);
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
            //if (is_null($url_sent)) {
            //    $template->forget($url_type); // This points to a review and not the article
            //}
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
                $check_me = $template->get('work') . $template->get('website') . $template->get('publisher');
                if (stripos($check_me, 'oclc') !== false || stripos($check_me, 'open library') !== false) {
                    return $template->add_if_new('oclc', $match[1]);
                }
                if ($template->blank('oclc')) {
                    quietly('report_modification', "Converting URL to OCLC parameter");
                }
                if ($template->wikiname() === 'cite web') {
                    // $template->change_name_to('cite book');  // Better template choice
                }
                if (is_null($url_sent) && $template->wikiname() === 'cite book') {
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
                if (is_null($url_sent)) {
                    $template->forget($url_type);
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
            if (is_null($url_sent)) {
                $template->forget($url_type);
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
