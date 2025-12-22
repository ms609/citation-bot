<?php

declare(strict_types=1);

function get_unpaywall_url(Template $template, string $doi): string
 {
    static $ch_oa = null;
    if ($ch_oa === null) {
        $ch_oa = bot_curl_init(0.5, [CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT]);
    }
    if (in_array($doi, BAD_OA_URL, true)) {
        return 'wrong';
    } // TODO - maybe all ISBN
    set_time_limit(120);
    /** @psalm-taint-escape ssrf */
    $url = "https://api.unpaywall.org/v2/{$doi}?email=" . CROSSREFUSERNAME;
    curl_setopt($ch_oa, CURLOPT_URL, $url);
    $json = bot_curl_exec($ch_oa);
    if ($json) {
        $oa = @json_decode($json);
        unset($json);
        if ($oa !== false && isset($oa->best_oa_location)) {
            $best_location = $oa->best_oa_location;
            if ($best_location->host_type === 'publisher') {
                // The best location is already linked to by the doi link
                return 'publisher';
            }
            if (!isset($best_location->evidence)) {
                return 'nothing';
            }
            if (isset($oa->journal_name) && $oa->journal_name === "Cochrane Database of Systematic Reviews") {
                report_warning("Ignored a OA from Cochrane Database of Systematic Reviews for DOI: " . echoable($doi)); // @codeCoverageIgnore
                return 'unreliable'; // @codeCoverageIgnore
            }
            if (isset($best_location->url_for_landing_page)) {
                $oa_url = (string) $best_location->url_for_landing_page; // Prefer to PDF
            } elseif (isset($best_location->url)) {
                // @codeCoverageIgnoreStart
                $oa_url = (string) $best_location->url;
            } else {
                return 'nothing'; // @codeCoverageIgnoreEnd
            }
            if (!$oa_url) {
                return 'nothing';
            }

            if (mb_stripos($oa_url, 'semanticscholar.org') !== false) {
                return 'semanticscholar';
            } // use API call instead (avoid blacklisting)
            if (mb_stripos($oa_url, 'timetravel.mementoweb.org') !== false) {
                return 'mementoweb';
            } // Not good ones
            if (mb_stripos($oa_url, 'citeseerx') !== false) {
                return 'citeseerx';
            } // blacklisted due to copyright concerns
            if (mb_stripos($oa_url, 'zenodo') !== false) {
                return 'zenodo';
            } // blacklisted due to copyright concerns
            if (mb_stripos($oa_url, 'palgraveconnect') !== false) {
                return 'palgraveconnect';
            }
            if (mb_stripos($oa_url, 'muse.jhu.edu') !== false) {
                return 'projectmuse';
            } // Same as DOI 99% of the time
            if (mb_stripos($oa_url, 'doaj.org') !== false) {
                return 'doaj.org';
            }
            if (mb_stripos($oa_url, 'lib.myilibrary.com') !== false) {
                return 'proquest';
            } // Rubbish
            if (mb_stripos($oa_url, 'repository.upenn.edu') !== false) {
                return 'epository.upenn.edu';
            } // All links broken right now
            if ($template->get('url')) {
                if ($template->get('url') !== $oa_url) {
                      $template->get_identifiers_from_url($oa_url);
                } // Maybe we can get a new link type
                    return 'have url';
            }
            if (!preg_match("~^https?://([^\/]+)/~", $oa_url, $match)) {
                return 'no_slash'; // On very rare occasions we get a non-valid url, such as http://lib.myilibrary.com?id=281759
            }
            $host_name = $match[1];
            if (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $host_name) !== $host_name) {
                return 'publisher';
            }
            if (mb_stripos($oa_url, 'bioone.org/doi') !== false) {
                return 'publisher';
            }
            if (mb_stripos($oa_url, 'gateway.isiknowledge.com') !== false) {
                return 'nothing';
            }
            if (mb_stripos($oa_url, 'orbit.dtu.dk/en/publications') !== false) {
                return 'nothing';
            } // Abstract only
            // Check if free location is already linked
            if (
                ($template->has('pmc') && preg_match("~^https?://europepmc\.org/articles/pmc\d" . "|^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=\d" . "|^https?://www\.ncbi\.nlm\.nih\.gov/(?:m/)?pmc/articles/PMC\d~", $oa_url)) ||
                ($template->has('arxiv') && preg_match("~arxiv\.org/~", $oa_url)) ||
                ($template->has('eprint') && preg_match("~arxiv\.org/~", $oa_url)) ||
                ($template->has('citeseerx') && preg_match("~citeseerx\.ist\.psu\.edu~", $oa_url))
            ) {
                return 'have free';
            }
            // @codeCoverageIgnoreStart
            // These are not generally full-text.  Will probably never see
            if (($template->has('bibcode') && preg_match(REGEXP_BIBCODE, urldecode($oa_url))) || ($template->has('pmid') && preg_match("~^https?://www.ncbi.nlm.nih.gov/.*pubmed/~", $oa_url))) {
                return 'probably not free';
            }
            // This should be found above when listed as location=publisher
            if ($template->has('doi') && preg_match("~^https?://doi\.library\.ubc\.ca/|^https?://(?:dx\.|)doi\.org/~", $oa_url)) {
                return 'publisher';
            }
            // @codeCoverageIgnoreEnd
            if (preg_match('~^https?://hdl\.handle\.net/(\d{2,}.*/.+)$~', $oa_url, $matches)) {
                // Normalize Handle URLs
                $oa_url = 'https://hdl.handle.net/handle/' . $matches[1];
            }
            if ($template->has('hdl')) {
                if (mb_stripos($oa_url, $template->get('hdl')) !== false) {
                      return 'have free';
                }
                foreach (HANDLES_HOSTS as $hosts) {
                    if (preg_match('~^https?://' . str_replace('.', '\.', $hosts) . '(/.+)$~', $oa_url, $matches)) {
                        $handle1 = $matches[1];
                        foreach (HANDLES_PATHS as $handle_path) {
                            if (preg_match('~^' . $handle_path . '(.+)$~', $handle1)) {
                                return 'have free';
                            }
                        }
                    }
                }
            }

            if (
                $template->has('arxiv') ||
                $template->has('eprint') ||
                $template->has('biorxiv') ||
                $template->has('citeseerx') ||
                $template->has('pmc') ||
                $template->has('rfc') ||
                ($template->has('doi') && $template->get('doi-access') === 'free') ||
                ($template->has('jstor') && $template->get('jstor-access') === 'free') ||
                ($template->has('osti') && $template->get('osti-access') === 'free') ||
                ($template->has('hdl') && $template->get('hdl-access') === 'free') ||
                ($template->has('ol') && $template->get('ol-access') === 'free')
            ) {
                return 'have free'; // do not add url if have OA already
            }
            // Double check URL against existing data
            if (!preg_match('~^(?:https?|ftp):\/\/\/?([^\/\.]+\.[^\/]+)\/~i', $oa_url, $matches)) {
                report_minor_error(' OA database gave invalid URL: ' . echoable($oa_url)); // @codeCoverageIgnore
                return 'nothing'; // @codeCoverageIgnore
            }
            $oa_hostname = $matches[1];
            if (
                ($template->has('osti') && mb_stripos($oa_hostname, 'osti.gov') !== false) ||
                ($template->has('ssrn') && mb_stripos($oa_hostname, 'ssrn.com') !== false) ||
                ($template->has('jstor') && mb_stripos($oa_hostname, 'jstor.org') !== false) ||
                ($template->has('pmid') && mb_stripos($oa_hostname, 'nlm.nih.gov') !== false) ||
                ($template->has('jstor') && mb_stripos($oa_hostname, 'jstor') !== false) ||
                mb_stripos($oa_hostname, 'doi.org') !== false
            ) {
                return 'have free';
            }
            if (preg_match("~^https?://([^\/]+)/~", $oa_url . '/', $match)) {
                $new_host_name = str_replace('www.', '', mb_strtolower($match[1]));
                foreach (ALL_URL_TYPES as $old_url) {
                    if (preg_match("~^https?://([^\/]+)/~", $template->get($old_url), $match)) {
                        $old_host_name = str_replace('www.', '', mb_strtolower($match[1]));
                        if ($old_host_name === $new_host_name) {
                            return 'have free';
                        }
                    }
                }
            }
            $url_type = 'url';
            if ($template->has('chapter')) {
                if (preg_match('~^10\.\d+/9[\-\d]+_+\d+~', $doi) || mb_strpos($oa_url, 'eprints') !== false || mb_strpos($oa_url, 'chapter') !== false) {
                      $url_type = 'chapter-url';
                }
            }
            $has_url_already = $template->has($url_type);
            $template->add_if_new($url_type, $oa_url); // Will check for PMCs etc hidden in URL
            if ($template->has($url_type) && !$has_url_already) {
                // The above line might have eaten the URL and upgraded it
                $the_url = $template->get($url_type);
                $ch = bot_curl_init(1.5, [
                CURLOPT_HEADER => '1',
                CURLOPT_NOBODY => '1',
                CURLOPT_SSL_VERIFYHOST => '0',
                CURLOPT_SSL_VERIFYPEER => '0',
                CURLOPT_SSL_VERIFYSTATUS => '0',
                CURLOPT_URL => $the_url,
                    ]);
                $headers_test = bot_curl_exec($ch);
                // @codeCoverageIgnoreStart
                if ($headers_test === "") {
                    $template->forget($url_type);
                    report_warning("Open access URL was unreachable from Unpaywall API for doi: " . echoable($doi));
                    return 'nothing';
                }
                // @codeCoverageIgnoreEnd
                $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                // @codeCoverageIgnoreStart
                if ($response_code > 400) {
                      // Generally 400 and below are okay, includes redirects too though
                      $template->forget($url_type);
                      report_warning("Open access URL gave response code " . (string) $response_code . " from oiDOI API for doi: " . echoable($doi));
                      return 'nothing';
                }
                    // @codeCoverageIgnoreEnd
            }
            return 'got one';
        }
    }
    return 'nothing';
}

function get_open_access_url(Template $template): void
{
    if (!$template->blank(DOI_BROKEN_ALIASES)) {
        return;
    }
    $doi = $template->get_without_comments_and_placeholders('doi');
    if (!$doi) {
        return;
    }
    if (mb_strpos($doi, '10.1093/') === 0) {
        return;
    }
    $return = get_unpaywall_url($template, $doi);
    if (in_array($return, GOOD_FREE, true)) {
        return;
    } // Do continue on
    get_semanticscholar_url($template, $doi);
}
