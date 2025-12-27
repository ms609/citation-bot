<?php

declare(strict_types=1);

function get_doi_from_pii(string $pii): string {
    static $ch_pii;
    if ($ch_pii === null) {
        $time = (float) run_type_mods(1, 3, 3, 3, 3);
        $ch_pii = bot_curl_init($time, []);
    }
    curl_setopt($ch_pii, CURLOPT_URL, "https://api.elsevier.com/content/object/pii/" . $pii);
    $ch_return = (string) bot_curl_exec($ch_pii);
    if (preg_match('~<prism:doi>(10\..+)<\/prism:doi>~', $ch_return, $match)) {
        return $match[1];
    }
    return '';
}
