<?php

declare(strict_types=1);

function parse_pii_doi_response(string $response): string {
    if (preg_match('~<prism:doi>\s*(10\.[^<\s]{3,512})\s*</prism:doi>~i', $response, $match)) {
        return $match[1];
    }
    return '';
}

function get_doi_from_pii(string $pii): string {
    static $ch_pii;
    if ($ch_pii === null) {
        $time = (float) run_type_mods(1, 3, 3, 3, 3);
        $ch_pii = bot_curl_init($time, [], 2 * 1024 * 1024);
    }
    curl_setopt($ch_pii, CURLOPT_URL, "https://api.elsevier.com/content/object/pii/" . $pii);
    return parse_pii_doi_response(bot_curl_exec($ch_pii));
}
