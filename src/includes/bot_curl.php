<?php

declare(strict_types=1);

const COOKIE_FILE_PATH = __DIR__ . '/cookie.txt'; // Proquest needs

function curl_limit_page_size(CurlHandle $_ch, int $_DE = 0, int $down = 0, int $_UE = 0, int $_Up = 0): int {
    // MOST things are sane, some things are stupidly large like S2 json data or archived PDFs
    // If $down exceeds max-size of 128MB, returning non-0 breaks the connection!
    if ($down > 134217728) {
         bot_debug_log("Absurdly large curl");
         return 1;
    }
    return 0;
}
/**
 * @param float $time
 * @param array<int, int|string|bool|array<int, string>> $ops
 */
function bot_curl_init(float $time, array $ops): CurlHandle {
    $ch = curl_init();
    if ($ch === false) {
        report_error("curl_init failure"); // @codeCoverageIgnore
    }
    // 1 - Global Defaults
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_BUFFERSIZE => 524288, // 512kB chunks
        CURLOPT_MAXREDIRS => 20, // No infinite loops for us, 20 for Elsevier and Springer websites
        CURLOPT_USERAGENT => BOT_USER_AGENT,
        CURLOPT_AUTOREFERER => "1",
        CURLOPT_REFERER => "https://en.wikipedia.org",
        CURLOPT_COOKIESESSION => "1",
        CURLOPT_RETURNTRANSFER => "1",
        CURLOPT_HEADEROPT => CURLHEADER_UNIFIED,
        CURLOPT_PROGRESSFUNCTION => 'curl_limit_page_size',
        CURLOPT_NOPROGRESS => "0",
        CURLOPT_COOKIEJAR => COOKIE_FILE_PATH,
        CURLOPT_COOKIEFILE => COOKIE_FILE_PATH,
        // 2 - Default Time by ratio
        CURLOPT_TIMEOUT => BOT_HTTP_TIMEOUT * $time,
        CURLOPT_CONNECTTIMEOUT => BOT_CONNECTION_TIMEOUT * $time,
    ]);
    // 3 - Specific options and overrides of defaults
    curl_setopt_array($ch, $ops);
    return $ch;
}

function bot_curl_exec(CurlHandle $ch): string {
    curl_setopt($ch, CURLOPT_REFERER, WIKI_ROOT . "title=" . Page::$last_title);
    return (string) @curl_exec($ch);
}
