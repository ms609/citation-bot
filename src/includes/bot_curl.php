<?php

declare(strict_types=1);

const COOKIE_FILE_PATH = __DIR__ . '/cookie.txt'; // Proquest needs

/**
 * Return true only for globally routable IP addresses.
 */
function bot_curl_ip_is_public(string $ip): bool {
    $packed = @inet_pton($ip);
    if ($packed === false) {
        return false;
    }

    /*
     * Normalize IPv4-mapped IPv6, including:
     *   ::ffff:127.0.0.1
     *   ::ffff:7f00:1
     */
    if (
        mb_strlen($packed, '8bit') === 16 &&
        mb_substr($packed, 0, 10, '8bit') === str_repeat("\0", 10) &&
        mb_substr($packed, 10, 2, '8bit') === "\xff\xff"
    ) {
        $packed = mb_substr($packed, 12, 4, '8bit');
        $ip = inet_ntop($packed);
    }
    if ($ip === false) {
        return false;
    }
    /*
     * Reject non-global special-purpose ranges such as:
     *   10.0.0.0/8
     *   100.64.0.0/10
     *   127.0.0.0/8
     *   169.254.0.0/16
     *   172.16.0.0/12
     *   192.0.2.0/24
     *   192.168.0.0/16
     *   198.18.0.0/15
     *   198.51.100.0/24
     *   203.0.113.0/24
     *   IPv6 loopback, link-local, ULA, documentation, etc.
     */
    if (
        filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_GLOBAL_RANGE
        ) === false
    ) {
        return false;
    }

    $packed = inet_pton($ip);
    if ($packed === false) {
        return false;
    }

    /*
     * FILTER_FLAG_GLOBAL_RANGE still accepts multicast,
     * so explicitly reject it.
     */
    if (mb_strlen($packed, '8bit') === 4) {
        // IPv4 multicast: 224.0.0.0/4
        if ((ord($packed[0]) & 0xf0) === 0xe0) {
            return false;
        }
    } else {
        // IPv6 multicast: ff00::/8
        if (ord($packed[0]) === 0xff) {
            return false;
        }
    }

    return true;
}

/**
 * Abort cURL before it sends a request to a private, loopback, link-local,
 * reserved, or otherwise non-public address.
 */
function bot_curl_check_destination(
    CurlHandle $_ch,
    string $destination_ip,
    string $_local_ip,
    int $_destination_port,
    int $_local_port
): int {
    if (!bot_curl_ip_is_public($destination_ip)) {
        bot_debug_log('Blocked cURL request to non-public address: ' . $destination_ip);
        return CURL_PREREQFUNC_ABORT;
    }

    return CURL_PREREQFUNC_OK;
}

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
    $user_agent = BOT_USER_AGENT;
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_BUFFERSIZE => 524288, // 512kB chunks
        CURLOPT_MAXREDIRS => 20, // No infinite loops for us, 20 for Elsevier and Springer websites
        CURLOPT_USERAGENT => $user_agent,
        CURLOPT_AUTOREFERER => true,
        CURLOPT_REFERER => "https://en.wikipedia.org",
        CURLOPT_COOKIESESSION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADEROPT => CURLHEADER_UNIFIED,
        CURLOPT_PROGRESSFUNCTION => 'curl_limit_page_size',
        CURLOPT_NOPROGRESS => false,
        CURLOPT_COOKIEJAR => COOKIE_FILE_PATH,
        CURLOPT_COOKIEFILE => COOKIE_FILE_PATH,
        // 2 - Default Time by ratio
        CURLOPT_TIMEOUT => (int) ceil(BOT_HTTP_TIMEOUT * $time),
        CURLOPT_CONNECTTIMEOUT => (int) ceil(BOT_CONNECTION_TIMEOUT * $time),
    ]);
    // 3 - Specific options and overrides of defaults
    curl_setopt_array($ch, $ops);
    // 4 - Security restrictions. These must be applied after caller-supplied
    // options so callers cannot accidentally enable unsafe protocols.
    // Some malformed DOI's redirect to file:// URLs
    //
    // CURLOPT_PREREQFUNCTION protects redirected requests and DNS
    // rebinding by inspecting the actual connected destination address.
    curl_setopt_array($ch, [
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS | CURLPROTO_FTP,
        CURLOPT_PREREQFUNCTION => 'bot_curl_check_destination',
    ]);

    return $ch;
}

function bot_curl_exec(CurlHandle $ch): string {
    curl_setopt($ch, CURLOPT_REFERER, WIKI_ROOT . "title=" . Page::get_last_title());
    /** Make sure this is always in effect */
    curl_setopt_array($ch, [
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS | CURLPROTO_FTP,
        CURLOPT_PREREQFUNCTION => 'bot_curl_check_destination',
    ]);
    return (string) @curl_exec($ch);
}
