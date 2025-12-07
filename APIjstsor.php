<?php

declare(strict_types=1);


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
        $jstor = trim($template->get('jstor'));
    } elseif(preg_match('~^https?://(?:www\.|)jstor\.org/stable/(.*)$~', $template->get('url'), $match)) {
        $jstor = $match[1];
    } else {
        return;
    }
    if (preg_match('~^(.*)(?:\?.*)$~', $jstor, $match)) {
        $jstor = $match[1]; // remove ?seq= stuff
    }
    /** @psalm-taint-escape ssrf */
    $jstor = trim($jstor);
    if (strpos($jstor, ' ') !== false) {
        return ; // Comment/template found
    }
    if (substr($jstor, 0, 1) === 'i') {
        return ; // We do not want i12342 kind
    }
    curl_setopt($ch, CURLOPT_URL, 'https://www.jstor.org/citation/ris/' . $jstor);
    $dat = bot_curl_exec($ch);
    if ($dat === '') {
        report_info("JSTOR API returned nothing for ". jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                             // @codeCoverageIgnore
    }
    if (stripos($dat, 'No RIS data found for') !== false) {
        report_info("JSTOR API found nothing for ".    jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                             // @codeCoverageIgnore
    }
    if (stripos($dat, 'Block Reference') !== false) {
        report_info("JSTOR API blocked bot for ".    jstor_link($jstor)); // @codeCoverageIgnore
        return;                                                           // @codeCoverageIgnore
    }
    if (stripos($dat, 'A problem occurred trying to deliver RIS data')  !== false) {
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
            switch (trim($ris_part[0])) {
                case "T1":
                case "TI":
                case "T2":
                case "BT":
                    $new_title = trim($ris_part[1]);
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
                switch (trim($ris_part[0])) {
                    case "T1":
                        $new_title .= trim($ris_part[1]);
                        $got_count += 10;
                        break;
                    case "TI":
                        $new_title = trim($ris_part[1]) . $new_title;
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
                switch (trim($ris_part[0])) {
                    case "T1":
                    case "TI":
                    case "T2":
                    case "BT":
                        $new_title = trim($ris_part[1]);
                        if ($new_title) report_info("    Possible new title: " . echoable($new_title));
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
    $template->expand_by_RIS($dat, false);
    return;
}
