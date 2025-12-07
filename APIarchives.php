<?php

declare(strict_types=1);


/**
  @param array<Template> $templates
*/

function expand_templates_from_archives(array &$templates): void { // This is done very late as a latch ditch effort  // Pointer to save memory
    static $ch = null;
    set_time_limit(120);
    if ($ch === null) {
        $ch = bot_curl_init(0.5, [CURLOPT_HEADER => "1"]);
    }
    foreach ($templates as $template) {
        set_time_limit(120);
        if ($template->has('script-title') && (strtolower($template->get('title')) === 'usurped title' || strtolower($template->get('title')) === 'archived copy' || strtolower($template->get('title')) === 'archive copy')) {
            $template->forget('title');
        }
        if ($template->blank(['chapter', 'series', 'script-title']) &&
            !$template->blank(['archive-url', 'archiveurl']) &&
            ($template->blank(WORK_ALIASES) || $template->has('website'))    &&
            ($template->blank('title') || strtolower($template->get('title')) === 'archived copy' ||
            strtolower($template->get('title')) === 'archive copy' ||
            strtolower($template->get('title')) === 'usurped title' ||
            substr_count($template->get('title'), '?') > 10 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '') >0 ||
            substr_count($template->get('title'), '�') >0 )) {
            /** @psalm-taint-escape ssrf */
            $archive_url = $template->get('archive-url') . $template->get('archiveurl');
            if (stripos($archive_url, 'archive') !== false && stripos($archive_url, '.pdf') === false) {
                set_time_limit(120);
                throttle_archive();
                curl_setopt($ch, CURLOPT_URL, $archive_url);
                $raw_html = bot_curl_exec($ch);
                foreach ([
                    '~doctype[\S\s]+?<head[\S\s]+?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~doctype[\S\s]+?<head[\S\s]+?<meta property="og:title" content="([\S\s]+?\S[\S\s]+?)"\/>[\S\s]+?<title[\S\s]+?head[\S\s]+?<body~i',
                    '~doctype[\S\s]+?<head[\S\s]+?<title>([\S\s]+?\S[\S\s]+?) \| Ghostarchive<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~<html[\S\s]+<head[\S\s]+?<!-- End Wayback Rewrite JS Include -->[\s\S]*?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~i',
                    '~<html[\S\s]+<head[\S\s]+?<!-- End Wayback Rewrite JS Include -->\s*?<!-- WebPoet\(tm\) Web Page Pull[\s\S]+?-->[\S\s]+?<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head~i',
                    '~archive\.org/includes/analytics\.js[\S\s]+?-- End Wayback Rewrite JS Include[\S\s]+?head[\S\s]+<title>([\S\s]+?\S[\S\s]+?)<\/title>[\S\s]+?head[\S\s]+?<body~',
                ] as $regex) {
                    set_time_limit(120); // Slow regex sometimes
                    if ($raw_html && preg_match($regex, $raw_html, $match)) {
                        set_time_limit(120);
                            $title = trim($match[1]);
                            if (stripos($title, 'archive') === false &&
                            stripos($title, 'wayback') === false &&
                            $title !== ''
                            ) {
                            $cleaned = false;
                            $encode = [];
                            if (preg_match('~x-archive-guessed-charset: (\S+)~i', $raw_html, $match)) {
                                if (is_encoding_reasonable($match[1])) {
                                    $encode[] = $match[1];
                                }
                            }
                            if (preg_match('~<meta http-equiv="?content-type"? content="text\/html;[\s]*charset=([^"]+)"~i', $raw_html, $match)) {
                                if (is_encoding_reasonable($match[1])) {
                                    $encode[] = $match[1];
                                }
                            }
                            if (preg_match('~<meta http-equiv="?content-type"? content="text\/html;[\s]*charset=([^"]+)"~i', $raw_html, $match)) {
                                if (strtolower($match[1]) !== 'utf-8' && strtolower($match[1]) !== 'iso-8859-1') {
                                    $encode[] = $match[1];
                                }
                            }
                            foreach ($encode as $pos_encode) {
                                if (!$cleaned) {
                                    $try = smart_decode($title, $pos_encode, $archive_url);
                                    if ($try !== "") {
                                        $title = $try;
                                        $cleaned = true;
                                    }
                                }
                            }
                            if (!$cleaned) {
                                $title = convert_to_utf8($title);
                            }
                            unset($encode, $cleaned, $try, $match, $pos_encode);
                            $good_title = true;
                            if (in_array(strtolower($title), BAD_ACCEPTED_MANUSCRIPT_TITLES, true) ||
                                    in_array(strtolower($title), IN_PRESS_ALIASES, true)) {
                                $good_title = false;
                            }
                            foreach (BAD_ZOTERO_TITLES as $bad_title) {
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
                                    $bad_count = substr_count($new, '�') + mb_substr_count($new, '$') + mb_substr_count($new, '%') + substr_count($new, '');
                                    if ($bad_count > 5) {
                                        $template->set('title', $old); // UTF-8 craziness
                                    } else {
                                        $raw_html = ''; // We are done
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
