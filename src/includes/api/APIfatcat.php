<?php

declare(strict_types=1);

/**
 * @return array{status: int, location: string, content_type: string}|null
 */
function fatcat_http_response(string $url, bool $head, int $deadline_ns): ?array {
    static $retry_after_ns = 0;
    $now_ns = (int) hrtime(true);
    $remaining_ms = intdiv($deadline_ns - $now_ns, 1000000);
    if ($remaining_ms <= 0 || $now_ns < $retry_after_ns) {
        return null;
    }
    try {
        $ch = bot_curl_init(0.2, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY => $head,
            CURLOPT_TIMEOUT_MS => $remaining_ms,
            CURLOPT_CONNECTTIMEOUT_MS => min(2000, $remaining_ms),
            CURLOPT_URL => $url,
        ], 64 * 1024);
        if (bot_curl_exec_withFalse($ch) === false) {
            return null;
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($status === 429 || $status === 503) {
            $retry_after = (int) curl_getinfo($ch, CURLINFO_RETRY_AFTER);
            $retry_after_ns = (int) hrtime(true) + ($retry_after > 0 ? min($retry_after, 3600) : 60) * 1000000000;
        }
        return [
            'status' => $status,
            'location' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
            'content_type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        ];
    } catch (Throwable) {
        return null;
    }
}

/** Look up the DOI's PDF in Archive.org's public-domain pre-1909 collection. */
function fatcat_fulltext_url(string $doi): ?string {
    if (!doi_valid($doi)) {
        return null;
    }

    $doi = mb_strtolower($doi);
    $deadline_ns = (int) hrtime(true) + 4000000000;
    $lookup = 'https://scholar.archive.org/api/fatcat/v2/release/lookup/fulltext?id_type=doi&id_value=' . rawurlencode($doi);
    $response = fatcat_http_response($lookup, false, $deadline_ns);
    if ($response === null || $response['status'] !== 302) {
        return null;
    }
    $download_url = $response['location'];
    $collection = 'crossref-pre-1909-scholarly-works';
    if (preg_match('~\Ahttps://(?i:(?:www\.)?archive\.org)/download/' . $collection .
        '/([A-Za-z0-9_.%+-]+\.zip)/([A-Za-z0-9_.%+-]+)\.pdf\z~', $download_url, $file) !== 1 ||
        mb_strtolower(rawurldecode(rawurldecode($file[2]))) !== $doi) {
        return null;
    }

    // Fatcat can return a related release. Match the PDF member DOI and keep it fixed on storage redirects.
    $storage_path = '/items/' . $collection . '/' . $file[1] . '&file=' . $file[2] . '.pdf';
    $url = $download_url;
    for ($redirects = 0; $redirects <= 2; $redirects++) {
        if ($url !== $download_url && preg_match('~\Ahttps://(?i:ia[0-9]+(?:\.[a-z]{2})?\.archive\.org)/' .
            'view_archive\.php\?archive=/[0-9]+' . preg_quote($storage_path, '~') . '\z~', $url) !== 1) {
            return null;
        }
        /** @psalm-taint-escape ssrf */
        $safe_url = $url;
        $response = fatcat_http_response($safe_url, true, $deadline_ns);
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
    $year = $template->year();
    if (
        !in_array($template->wikiname(), ['cite journal', 'citation', 'cite web'], true) ||
        $title === '' || $title === 'none' || mb_strpos($title, '[') !== false ||
        (preg_match('~\A[0-9]{4}~', $year) === 1 && (int) $year > 1909) ||
        !$template->blank([
            ...ALL_URL_TYPES, ...TITLE_LINK_ALIASES, ...CHAPTER_ALIASES_AND_SCRIPT,
            'script-entry', 'script-contribution', 'script-article', 'script-section',
            'archive-url', 'archiveurl', 'archive-date', 'archivedate', 'url-status', 'url-access', 'format',
        ])
    ) {
        return false;
    }
    $url = fatcat_fulltext_url($doi);
    // The ZIP filename can contain a different DOI from the PDF member.
    return $url !== null && $template->add('url', $url);
}
