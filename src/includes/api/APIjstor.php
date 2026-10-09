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

function jstor_expand_via_zotero(Template $template, string $jstor): void {
    Zotero::expand_by_zotero(
        $template,
        'https://www.jstor.org/stable/' . $jstor,
        true, // Explicit JSTOR URL bypasses Zotero's URL exclusion list.
        true, // Reject metadata whose title does not match the citation.
        true, // Use JSTOR's historical incomplete() policy.
        true  // JSTOR metadata must not trigger additional Crossref enrichment.
    );
}

function expand_by_jstor(Template $template): void {
    set_time_limit(120);
    if ($template->incomplete() === false) {
        return;
    }
    if ($template->has('jstor')) {
        $jstor = mb_trim($template->get('jstor'));
    } elseif (preg_match('~^https?://(?:www\.|)jstor\.org/stable/(.*)$~', $template->get('url'), $match)) {
        $jstor = $match[1];
    } else {
        return;
    }
    $jstor = explode('#', explode('?', $jstor, 2)[0], 2)[0]; // Strip query and fragment.
    /** @psalm-taint-escape ssrf */
    $jstor = mb_trim($jstor);
    if (mb_strpos($jstor, ' ') !== false) {
        return; // Comment/template found
    }
    if (mb_substr($jstor, 0, 1) === 'i') {
        return; // We do not want i12342 kind
    }
    if (!jstor_valid($jstor)) {
        return;
    }

    // The stable URL itself is authoritative for its JSTOR identifier. Keep
    // identifier discovery independent of whether Citoid/Zotero can retrieve
    // metadata for the item.
    if ($template->blank('jstor')) {
        $template->add_if_new('jstor', $jstor);
    }

    // JSTOR's RIS endpoint now presents a browser-only JavaScript challenge to
    // server-side clients. Citoid already runs Zotero's JSTOR translator, which
    // understands numeric IDs, non-DOI JSTOR IDs, books/reports and publisher
    // DOI-shaped stable IDs. The caller already applied JSTOR's historical
    // incomplete() gate, so bypass Zotero's stricter profoundly_incomplete()
    // gate here; when a title already exists, require title agreement before
    // accepting any returned metadata.
    jstor_expand_via_zotero($template, $jstor);
}
