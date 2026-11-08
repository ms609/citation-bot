<?php

declare(strict_types=1);

/**
 * @return array{status: int, location: string, content_type: string}|null
 */
function fatcat_http_response(string $url, bool $head): ?array {
    try {
        $ch = bot_curl_init(0.2, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY => $head,
            CURLOPT_URL => $url,
        ], 64 * 1024);
        if (bot_curl_exec_withFalse($ch) === false) {
            return null;
        }
        return [
            'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'location' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
            'content_type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        ];
    } catch (Throwable) {
        return null;
    }
}

/** Look up a public Archive.org PDF for the exact DOI in the citation. */
function fatcat_fulltext_url(string $doi): ?string {
    if (!doi_valid($doi)) {
        return null;
    }

    $lookup = 'https://scholar.archive.org/api/fatcat/v2/release/lookup/fulltext?id_type=doi&id_value=' .
        rawurlencode(mb_strtolower($doi));
    $response = fatcat_http_response($lookup, false);
    if ($response === null || $response['status'] !== 302) {
        return null;
    }
    $download_url = $response['location'];
    if (preg_match('~\Ahttps://(?:www\.)?archive\.org/download/[a-z0-9_.-]+/[a-z0-9_./%+-]+\z~i', $download_url) !== 1) {
        return null;
    }

    // Keep the stable download URL in the citation, even when Archive.org serves it from a storage host.
    $url = $download_url;
    for ($redirects = 0; $redirects <= 2; $redirects++) {
        if (preg_match('~\Ahttps://(?:(?:www\.)?archive\.org|ia[0-9]+(?:\.us)?\.archive\.org)/[a-z0-9_./%+?=&:-]+\z~i', $url) !== 1) {
            return null;
        }
        /** @psalm-taint-escape ssrf */
        $safe_url = $url;
        $response = fatcat_http_response($safe_url, true);
        if ($response === null) {
            return null;
        }
        if ($response['status'] === 200) {
            $content_type = mb_strtolower(mb_trim(explode(';', $response['content_type'], 2)[0]));
            return $content_type === 'application/pdf' ? $download_url : null;
        }
        if (!in_array($response['status'], [301, 302, 303, 307, 308], true)) {
            return null;
        }
        $url = $response['location'];
    }

    return null;
}

function add_fatcat_open_access_url(Template $template, string $doi): bool {
    $title = $template->get_without_comments_and_placeholders('title');
    if (
        !in_array($template->wikiname(), ['cite journal', 'citation', 'cite web'], true) ||
        $title === '' || $title === 'none' || mb_strpos($title, '[') !== false ||
        !$template->blank([
            ...ALL_URL_TYPES, ...TITLE_LINK_ALIASES, ...CHAPTER_ALIASES_AND_SCRIPT,
            'script-entry', 'script-contribution', 'script-article', 'script-section',
            'archive-url', 'archiveurl', 'archive-date', 'archivedate', 'url-status', 'url-access', 'format',
        ])
    ) {
        return false;
    }
    $url = fatcat_fulltext_url($doi);
    // The lookup already identified the work by DOI. Archive filenames can contain other DOIs.
    return $url !== null && $template->add('url', $url);
}
