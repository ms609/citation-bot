<?php

declare(strict_types=1);

/**
 * Return a normalized host, with an optional port, or null for invalid input.
 */
function normalize_public_host(string $host): ?string {
    if ($host === '' || $host !== mb_trim($host) || preg_match('~[\x00-\x20\x7f/\\\\,@?#]~', $host)) {
        return null;
    }

    if (mb_substr($host, 0, 1) === '[') {
        if (!preg_match('~^\[([0-9a-fA-F:.]+)\](?::([0-9]{1,5}))?$~D', $host, $matches)) {
            return null;
        }
        if (filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }
        $hostname = '[' . mb_strtolower($matches[1]) . ']';
        $port = $matches[2] ?? '';
    } else {
        if (!preg_match('~^([a-zA-Z0-9.-]+)(?::([0-9]{1,5}))?$~D', $host, $matches)) {
            return null;
        }
        $hostname = mb_strtolower($matches[1]);
        $port = $matches[2] ?? '';
        if (mb_strlen($hostname) > 253 || mb_substr($hostname, 0, 1) === '.' || mb_substr($hostname, -1) === '.') {
            return null;
        }
        if (preg_match('~^[0-9.]+$~D', $hostname) && filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }
        foreach (explode('.', $hostname) as $label) {
            if (!preg_match('~^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$~D', $label)) {
                return null;
            }
        }
    }

    if ($port !== '' && (intval($port) < 1 || intval($port) > 65535)) {
        return null;
    }

    return $hostname . (($port === '') ? '' : ':' . $port);
}

/**
 * Get the externally visible base URL.  It is deliberately never inferred from
 * request headers because Host is controlled by the requester unless a trusted
 * proxy or web server has already replaced it.
 */
function public_base_url(): string {
    $configured = getenv('PUBLIC_BASE_URL');
    if (!is_string($configured) || $configured === '' || $configured !== mb_trim($configured) || preg_match('~[\x00-\x20\x7f\\\\]~', $configured)) {
        throw new RuntimeException('PUBLIC_BASE_URL is missing or invalid');
    }

    $parts = parse_url($configured);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        throw new RuntimeException('PUBLIC_BASE_URL must be an absolute URL');
    }
    $scheme = mb_strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        throw new RuntimeException('PUBLIC_BASE_URL must contain only an HTTP(S) origin and optional path');
    }

    $authority = normalize_public_host($parts['host'] . (isset($parts['port']) ? ':' . (string) $parts['port'] : ''));
    if ($authority === null) {
        throw new RuntimeException('PUBLIC_BASE_URL contains an invalid host');
    }

    $path = $parts['path'] ?? '';
    if (($path !== '' && mb_substr($path, 0, 1) !== '/') || preg_match('~[\x00-\x20\x7f\\\\]~', $path)) {
        throw new RuntimeException('PUBLIC_BASE_URL contains an invalid path');
    }
    $path = mb_rtrim($path, '/');

    return $scheme . '://' . $authority . $path;
}

function public_base_host(): string {
    $host = parse_url(public_base_url(), PHP_URL_HOST);
    $port = parse_url(public_base_url(), PHP_URL_PORT);
    if (!is_string($host)) {
        throw new RuntimeException('PUBLIC_BASE_URL contains an invalid host');
    }
    $authority = normalize_public_host($host . (is_int($port) ? ':' . (string) $port : ''));
    if ($authority === null) {
        throw new RuntimeException('PUBLIC_BASE_URL contains an invalid host');
    }
    return $authority;
}

function public_url(string $path): string {
    if (mb_substr($path, 0, 1) !== '/' || mb_substr($path, 0, 2) === '//' || preg_match('~[\x00-\x20\x7f\\\\]~', $path)) {
        throw new InvalidArgumentException('A public URL path must be local and absolute');
    }
    return public_base_url() . $path;
}

function public_url_path(string $path): string {
    $base_path = parse_url(public_base_url(), PHP_URL_PATH);
    if (!is_string($base_path)) {
        $base_path = '';
    }
    if (mb_substr($path, 0, 1) !== '/' || mb_substr($path, 0, 2) === '//' || preg_match('~[\x00-\x20\x7f\\\\]~', $path)) {
        throw new InvalidArgumentException('A public URL path must be local and absolute');
    }
    return $base_path . $path;
}

/** @return list<string> */
function configured_allowed_hosts(): array {
    $configured = getenv('ALLOWED_HOSTS');
    if (!is_string($configured) || mb_trim($configured) === '') {
        throw new RuntimeException('ALLOWED_HOSTS is missing');
    }

    $allowed_hosts = [];
    foreach (explode(',', $configured) as $host) {
        $normalized = normalize_public_host(mb_trim($host));
        if ($normalized === null) {
            throw new RuntimeException('ALLOWED_HOSTS contains an invalid host');
        }
        $allowed_hosts[] = $normalized;
    }
    return array_values(array_unique($allowed_hosts));
}

function request_host_is_allowed(?string $host): bool {
    if ($host === null) {
        return false;
    }
    $normalized = normalize_public_host($host);
    if ($normalized === null) {
        return false;
    }
    return in_array($normalized, configured_allowed_hosts(), true);
}

function normalize_cors_origin(string $origin): ?string {
    if ($origin === '' || $origin !== mb_trim($origin) || preg_match('~[\x00-\x20\x7f\\\\,;]~', $origin)) {
        return null;
    }
    $parts = parse_url($origin);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    $scheme = mb_strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) {
        return null;
    }
    $authority = normalize_public_host($parts['host'] . (isset($parts['port']) ? ':' . (string) $parts['port'] : ''));
    if ($authority === null) {
        return null;
    }
    return $scheme . '://' . $authority;
}

function normalize_cors_origin_pattern(string $origin): ?string {
    if (preg_match('~^(https?)://\*\.([a-zA-Z0-9.-]+)(?::([0-9]{1,5}))?$~iD', $origin, $matches)) {
        $authority = normalize_public_host($matches[2] . (isset($matches[3]) ? ':' . $matches[3] : ''));
        if ($authority === null || filter_var($matches[2], FILTER_VALIDATE_IP) !== false) {
            return null;
        }
        return mb_strtolower($matches[1]) . '://*.' . $authority;
    }
    return normalize_cors_origin($origin);
}

/** @return list<string> */
function configured_allowed_origins(): array {
    $configured = getenv('ALLOWED_ORIGINS');
    if (!is_string($configured) || mb_trim($configured) === '') {
        throw new RuntimeException('ALLOWED_ORIGINS is missing');
    }

    $allowed_origins = [];
    foreach (explode(',', $configured) as $origin) {
        $normalized = normalize_cors_origin_pattern(mb_trim($origin));
        if ($normalized === null) {
            throw new RuntimeException('ALLOWED_ORIGINS contains an invalid origin');
        }
        $allowed_origins[] = $normalized;
    }
    return array_values(array_unique($allowed_origins));
}

function allowed_cors_origin(?string $origin): ?string {
    if ($origin === null) {
        return null;
    }
    $normalized = normalize_cors_origin($origin);
    if ($normalized === null) {
        return null;
    }
    $origin_parts = parse_url($normalized);
    if (!is_array($origin_parts) || !isset($origin_parts['scheme'], $origin_parts['host'])) {
        return null;
    }

    foreach (configured_allowed_origins() as $allowed) {
        if ($allowed === $normalized) {
            return $normalized;
        }
        if (mb_strpos($allowed, '://*.') === false) {
            continue;
        }
        $wildcard_parts = parse_url(str_replace('://*.', '://wildcard.', $allowed));
        if (!is_array($wildcard_parts) || !isset($wildcard_parts['scheme'], $wildcard_parts['host'])) {
            continue;
        }
        $suffix = mb_substr((string) $wildcard_parts['host'], mb_strlen('wildcard.'));
        $origin_host = (string) $origin_parts['host'];
        $origin_port = $origin_parts['port'] ?? null;
        $wildcard_port = $wildcard_parts['port'] ?? null;
        if ($origin_parts['scheme'] === $wildcard_parts['scheme'] && $origin_port === $wildcard_port && mb_substr($origin_host, -mb_strlen('.' . $suffix)) === '.' . $suffix) {
            return $normalized;
        }
    }
    return null;
}

function send_configured_cors_header(?string $origin): void {
    if ($origin === null || normalize_cors_origin($origin) === null) {
        return;
    }
    @header('Vary: Origin', false);
    $allowed = allowed_cors_origin($origin);
    if ($allowed === null) {
        return;
    }
    @header('Access-Control-Allow-Origin: ' . $allowed);
}

function is_valid_local_return_path(string $path): bool {
    return $path !== '' &&
        $path === mb_trim($path) &&
        mb_substr($path, 0, 1) === '/' &&
        mb_substr($path, 0, 2) !== '//' &&
        !preg_match('~[\x00-\x20\x7f\\\\]~', $path);
}

function oauth_authentication_url(string $return_path): string {
    if (!is_valid_local_return_path($return_path)) {
        throw new InvalidArgumentException('OAuth return path is invalid');
    }
    return public_url('/authenticate.php') . '?' . http_build_query(['return' => $return_path], '', '&', PHP_QUERY_RFC3986);
}

function oauth_callback_url(?string $return_path): string {
    if ($return_path === null) {
        return public_url('/authenticate.php');
    }
    return oauth_authentication_url($return_path);
}

function public_request_configuration_is_valid(?string $request_host): bool {
    try {
        configured_allowed_origins();
        return request_host_is_allowed($request_host) && request_host_is_allowed(public_base_host());
    } catch (Throwable) {
        return false;
    }
}

/**
 * Centralize security-sensitive PHP session options so every web entry point
 * uses the same cookie and session-ID policy.
 *
 * @return array<string, bool|string>
 */
function public_session_start_options(bool $read_and_close = false): array {
    $scheme = parse_url(public_base_url(), PHP_URL_SCHEME);
    if (!is_string($scheme)) {
        throw new RuntimeException('PUBLIC_BASE_URL lacks a valid scheme');
    }

    $options = [
        'use_strict_mode' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => mb_strtolower($scheme) === 'https',
    ];
    if ($read_and_close) {
        $options['read_and_close'] = true;
    }
    return $options;
}

function enforce_public_request_configuration(?string $request_host): void {
    if (PHP_SAPI === 'cli' || public_request_configuration_is_valid($request_host)) {
        return;
    }
    http_response_code(400); // @codeCoverageIgnore
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta charset="utf-8"><title>Invalid request</title></head><body><main>Invalid request host or public URL configuration.</main></body></html>'; // @codeCoverageIgnore
    exit(0); // @codeCoverageIgnore
}
