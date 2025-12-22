<?php

declare(strict_types=1);

require_once __DIR__ . '/../Template.php'; // @codeCoverageIgnore

/** @todo find a good API to really use these - worldcat used to have one, but they took that away. */
function use_issn(Template $template): void {
    if ($template->blank('issn')) {
        return;
    }
    if (!$template->blank(WORK_ALIASES)) {
        return;
    }
    if ($template->has('series')) {
        return;
    }
    if ($template->wikiname() === 'cite book' && $template->has('isbn')) {
        return;
    }
    $issn = $template->get('issn');
    if ($issn === '9999-9999') {
        return;
    }
    if (!preg_match('~^\d{4}.?\d{3}[0-9xX]$~u', $issn)) {
        return;
    }
    if ($issn === '0140-0460') {
        // Use set to avoid escaping [[ and ]]
        $template->set('newspaper', '[[The Times]]');
    } elseif ($issn === '0190-8286') {
        $template->set('newspaper', '[[The Washington Post]]');
    } elseif ($issn === '0362-4331') {
        $template->set('newspaper', '[[The New York Times]]');
    } elseif ($issn === '0163-089X' || $issn === '1092-0935') {
        $template->set('newspaper', '[[The Wall Street Journal]]');
    }
    return;
}
