<?php

declare(strict_types=1);

/**
 * @param array<Template> &$templates
 */
function query_ieee_webpages(array &$templates): void {  // Pointer to save memory
    static $ch_ieee;
    if ($ch_ieee === null) {
        if (CI) {
            $time = 3.0;
        } else {
            $time = 1.0; // @codeCoverageIgnore
        }
        $ch_ieee = bot_curl_init($time, [CURLOPT_USERAGENT => 'curl']); // IEEE requires JavaScript, unless curl is specified
    }
    foreach (['url', 'chapter-url', 'chapterurl'] as $kind) {
        foreach ($templates as $template) {
            set_time_limit(120);
            /** @psalm-taint-escape ssrf */
            $the_url = $template->get($kind);
            if (preg_match("~^https://ieeexplore\.ieee\.org/document/(\d{5,})$~", $the_url, $matches_url)) {
                curl_setopt($ch_ieee, CURLOPT_URL, $the_url);
                if ($template->blank('doi')) {
                    usleep(100000); // 0.10 seconds
                    $return = bot_curl_exec($ch_ieee);
                    if ($return !== "" && preg_match_all('~"doi":"(10\.\d{4}/[^\s"]+)"~', $return, $matches, PREG_PATTERN_ORDER)) {
                        $dois = array_unique($matches[1]);
                        if (count($dois) === 1) {
                            if ($template->add_if_new('doi', $dois[0])) {
                                if (mb_strpos($template->get('doi'), $matches_url[1]) !== false && doi_works($template->get('doi'))) {
                                    $template->forget($kind);
                                }
                            }
                        }
                    }
                } elseif (doi_works($template->get('doi'))) {
                    usleep(100000); // 0.10 seconds
                    $return = bot_curl_exec($ch_ieee);
                    if ($return !== "" && mb_strpos($return, "<title> -  </title>") !== false) {
                        report_forget("Existing IEEE no longer works - dropping URL"); // @codeCoverageIgnore
                        $template->forget($kind);                   // @codeCoverageIgnore
                    }
                }
            }
        }
    }
}
