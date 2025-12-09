<?php

declare(strict_types=1);
/**
 * @param array<Template> $templates
 */
function drop_urls_that_match_dois(array &$templates): void {  // Pointer to save memory
    static $ch_dx;
    static $ch_doi;
    if (null === $ch_dx) {
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
            preg_match("~^https?://ieeexplore\.ieee\.org/document/\d{5,}/?$~", $url) && mb_strpos($doi, '10.1109') === 0) {
            report_forget("Existing IEEE resulting from equivalent DOI; dropping URL");
            $template->forget($url_kind);
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
                report_forget("Existing OVID URL resulting from equivalent PMID and DOI; dropping URL");
                $template->forget($url_kind);
            } elseif ($template->has('pmc') && str_ireplace('iopscience.iop.org', '', $url) !== $url) {
                report_forget("Existing IOP URL resulting from equivalent DOI; dropping URL");
                $template->forget($url_kind);;
                $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (str_ireplace('wkhealth.com', '', $url) !== $url) {
                report_forget("Existing Outdated WK Health URL resulting from equivalent DOI; fixing URL");
                $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
            } elseif ($template->has('pmc') && str_ireplace('bmj.com/cgi/pmidlookup', '', $url) !== $url && $template->has('pmid') && $template->get('doi-access') === 'free' && mb_stripos($url, 'pdf') === false) {
                report_forget("Existing The BMJ URL resulting from equivalent PMID and free DOI; dropping URL");
                $template->forget($url_kind);
            } elseif ($template->get('doi-access') === 'free' && $template->get('url-status') === 'dead' && $url_kind === 'url') {
                report_forget("Existing free DOI; dropping dead URL");
                $template->forget($url_kind);
            } elseif (doi_works($template->get('doi')) &&
                        !preg_match(REGEXP_DOI_ISSN_ONLY, $template->get('doi')) &&
                        $url_kind !== '' &&
                        (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $template->get($url_kind)) !== $template->get($url_kind)) &&
                        $template->has_good_free_copy() &&
                        (mb_stripos($template->get($url_kind), 'pdf') === false)) {
                report_forget("Existing canonical URL resulting in equivalent free DOI/pmc; dropping URL");
                $template->forget($url_kind);
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
                        $redirectedUrl_doi = $matches[1] ;  // @codeCoverageIgnore
                    }
                    if (mb_stripos($url_short, $redirectedUrl_doi) !== false ||
                        mb_stripos($redirectedUrl_doi, $url_short) !== false) {
                        report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
                        $template->forget($url_kind);
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



    
    
