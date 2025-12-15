<?php

declare(strict_types=1);

public function clean_cite_odnb(Template $template): void
{
    if ($template->has('url')) {
        while (preg_match('~^(https?://www\.oxforddnb\.com/.+)(?:\;jsession|\?rskey|\#)~', $template->get('url'), $matches)) {
            $template->set('url', $matches[1]);
        }
    }
    if ($template->has('doi')) {
        $doi = $template->get('doi');
        if (doi_works($doi) === false) {
            if (preg_match("~^10\.1093/(?:\S+odnb-9780198614128-e-|ref:odnb|odnb/9780198614128\.013\.|odnb/)(\d+)$~", $doi, $matches)) {
                $try1 = '10.1093/ref:odnb/' . $matches[1];
                $try3 = '10.1093/odnb/9780198614128.013.' . $matches[1];
                if (doi_works($try1)) {
                    $template->set('doi', $try1);
                } elseif (doi_works($try3)) {
                    $template->set('doi', $try3);
                }
            }
        }
    }
    if ($template->has('id')) {
        $doi = $template->get('doi');
        $try1 = '10.1093/ref:odnb/' . $template->get('id');
        $try3 = '10.1093/odnb/9780198614128.013.' . $template->get('id');
        if (doi_works($try1) !== false) {
            // Template does this
        } elseif (doi_works($try3)) {
            if ($doi === '') {
                $template->rename('id', 'doi', $try3);
            } elseif ($doi === $try3) {
                $template->forget('id');
            } elseif (doi_works($doi)) {
                $template->forget('id');
            } else {
                $template->forget('doi');
                $template->rename('id', 'doi', $try3);
            }
        }
    }
    if ($template->has('doi')) {
        $works = doi_works($template->get('doi'));
        if ($works === false) {
            $template->add_if_new('doi-broken-date', date('Y-m-d'));
        } elseif ($works === true) {
            $template->forget('doi-broken-date');
        }
    }
}
