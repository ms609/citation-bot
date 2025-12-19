<?php

declare(strict_types=1);

function get_doi_from_pii(string $pii): string {
    static $ch_pii;
    if ($ch_pii === null) {
        if (TRAVIS) {
            $time = 3.0;
        } else {
            $time = 1.0; // @codeCoverageIgnore
        }
        $ch_pii = bot_curl_init($time, []);
    }
    curl_setopt($ch_pii, CURLOPT_URL, "https://api.elsevier.com/content/object/pii/" . $pii);
    $ch_return = (string) bot_curl_exec($ch_pii);
    if (preg_match('~<prism:doi>(10\..+)<\/prism:doi>~', $ch_return, $match)) {
        return $match[1];
    }
    return '';
}
