<?php

declare(strict_types=1);

const BOT_CURL_DEFAULT_MAX_RESPONSE_BYTES = 134217728; // 128 MiB
const BOT_CURL_ALLOWED_PROTOCOLS_USE = CURLPROTO_HTTP | CURLPROTO_HTTPS;
const BOT_CURL_ALLOWED_PROTOCOLS_END = CURLPROTO_HTTP | CURLPROTO_HTTPS | CURLPROTO_FTP; // Some DOIs resolve to FTP sites, which is okay.  Some resolve to files, which we reject.

/**
 * Return true only for globally routable IP addresses.
 * Some DOIs resolve to bogus IP addresses
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

/** @return WeakMap<CurlHandle, int> */
function bot_curl_response_limits(): WeakMap {
    static $limits = null;
    if ($limits === null) {
        $limits = new WeakMap();
    }
    return $limits;
}

function bot_curl_get_max_response_bytes(CurlHandle $ch): int {
    return bot_curl_response_limits()[$ch] ?? BOT_CURL_DEFAULT_MAX_RESPONSE_BYTES;
}

/**
 * @return WeakMap<CurlHandle, array{ok: bool, errno: int, error: string, http_code: int}>
 */
function bot_curl_transfer_results(): WeakMap {
    static $results = null;
    if ($results === null) {
        $results = new WeakMap();
    }
    return $results;
}

/** @return array{ok: bool, errno: int, error: string, http_code: int} */
function bot_curl_last_transfer(CurlHandle $ch): array {
    return bot_curl_transfer_results()[$ch] ?? [
        'ok' => false,
        'errno' => 0,
        'error' => 'cURL transfer has not run',
        'http_code' => 0,
    ];
}

function bot_curl_apply_security_options(CurlHandle $ch): void {
    if (!curl_setopt_array($ch, [
        CURLOPT_PROTOCOLS => BOT_CURL_ALLOWED_PROTOCOLS_USE,
        CURLOPT_REDIR_PROTOCOLS => BOT_CURL_ALLOWED_PROTOCOLS_END,
        CURLOPT_PREREQFUNCTION => 'bot_curl_check_destination',
    ])) {
        throw new RuntimeException('Unable to apply mandatory cURL security options.');
    }
}

function curl_limit_page_size(CurlHandle $_ch, int $_DE = 0, int $down = 0, int $_UE = 0, int $_Up = 0): int {
    $max_bytes = bot_curl_get_max_response_bytes($_ch);
    if ($down > $max_bytes) {
         bot_debug_log("cURL response exceeded configured limit of " . $max_bytes . " bytes");
         return 1;
    }
    return 0;
}

/**
 * @param float $time
 * @param array<int, int|string|bool|array<int, string>> $ops
 * @param int $max_bytes
 */
function bot_curl_init(float $time, array $ops, int $max_bytes): CurlHandle {
    $ch = curl_init(); // phpcs:ignore
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
        // Enable libcurl's in-memory cookie engine. Cookies remain available
        // to subsequent transfers on this handle without placing shared
        // cookie state inside the web-served source tree.
        CURLOPT_COOKIEFILE => '',
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
    bot_curl_apply_security_options($ch);
    if ($max_bytes <= 0) {
        throw new InvalidArgumentException('Maximum cURL response size must be positive.');
    } elseif ($max_bytes > BOT_CURL_DEFAULT_MAX_RESPONSE_BYTES) {
        throw new InvalidArgumentException('Maximum cURL response size is too large.');
    }
    bot_curl_response_limits()[$ch] = $max_bytes;
    return $ch;
}

function bot_curl_exec(CurlHandle $ch): string {
    $result = bot_curl_exec_withFalse($ch);
    return $result === false ? '' : (string) $result;
}

function bot_curl_exec_withFalse(CurlHandle $ch): string|bool {
    curl_setopt($ch, CURLOPT_REFERER, WIKI_ROOT . "title=" . Page::get_last_title());
    bot_curl_apply_security_options($ch);
    $result = @curl_exec($ch);  // phpcs:ignore
    bot_curl_transfer_results()[$ch] = [
        'ok' => $result !== false,
        'errno' => curl_errno($ch),
        'error' => curl_error($ch),
        'http_code' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
    ];
    return $result;
}
