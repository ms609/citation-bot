<?php

declare(strict_types=1);

// We minimize includes so this recovery endpoint still works if an application
// deployment breaks unrelated Citation Bot code.
//
// For automation:
// curl -X POST -H "X-Deploy-Token: ${DEPLOY_TOKEN}" "https://citations.toolforge.org/gitpull.php"
// For an iPhone/browser, visit gitpull.php and use Password AutoFill on the form.

/** @psalm-suppress MissingFile */
require_once __DIR__ . '/env.php';

require_once __DIR__ . '/includes/PublicConfig.php';

enforce_public_request_configuration(is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : null);
send_configured_cors_header(is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null);
@header('Cache-Control: no-store');
@header('Referrer-Policy: no-referrer');
@header('X-Content-Type-Options: nosniff');
@header("Content-Security-Policy: default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

const LOCK_DIR = __DIR__ . '/git_pull.lock';
const GITPULL_CSRF_COOKIE = 'citation_bot_gitpull_csrf';
const GITPULL_CSRF_TTL = 600;

/**
 * Deployment credentials are machine-generated 256-bit values represented as
 * lowercase hexadecimal.  Requiring the complete format keeps online guessing
 * impractical without introducing an attacker-triggerable account lockout.
 */
function gitpull_valid_deploy_token(string $token): bool {
    return preg_match('~\A[a-f0-9]{64}\z~D', $token) === 1;
}

function gitpull_new_browser_nonce(): string {
    return bin2hex(random_bytes(32));
}

function gitpull_cookie_path(): string {
    return public_url_path('/gitpull.php');
}

function gitpull_set_browser_nonce(string $nonce): void {
    $scheme = parse_url(public_base_url(), PHP_URL_SCHEME);
    setcookie(GITPULL_CSRF_COOKIE, $nonce, [
        'expires' => time() + GITPULL_CSRF_TTL,
        'path' => gitpull_cookie_path(),
        'secure' => $scheme === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function gitpull_clear_browser_nonce(): void {
    $scheme = parse_url(public_base_url(), PHP_URL_SCHEME);
    setcookie(GITPULL_CSRF_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => gitpull_cookie_path(),
        'secure' => $scheme === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Render the deployment page and stop processing.
 */
function gitpull_page(string $message, bool $show_form, int $status, string $csrf_token = ''): never {
    http_response_code($status);
    @header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head>',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
        '<meta charset="utf-8"><title>Git Pull</title></head><body><main>';

    echo '<p>', htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'), '</p>';

    if ($show_form) {
        $escaped_csrf = htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        echo '<form method="post" action="gitpull.php">',
            '<input name="csrf_token" type="hidden" value="', $escaped_csrf, '">',
            '<label for="deploy-user">Account</label> ',
            '<input id="deploy-user" name="username" type="text" value="deploy" ',
            'autocomplete="username" autocapitalize="none" spellcheck="false" readonly> ',
            '<label for="deploy-token">Deployment token</label> ',
            /*
             * current-password makes the long random token easy to store and
             * retrieve with iPhone Password AutoFill instead of typing it.
             */
            '<input id="deploy-token" name="deploy_token" type="password" ',
            'autocomplete="current-password" autocapitalize="none" spellcheck="false" ',
            'inputmode="text" enterkeyhint="go" minlength="64" maxlength="64" ',
            'pattern="[a-f0-9]{64}" required autofocus> ',
            '<button type="submit">Deploy</button>',
            '</form>';
    }

    echo '</main></body></html>';
    flush();
    exit(0);
}

function gitpull_browser_form(string $message, int $status): never {
    $nonce = gitpull_new_browser_nonce();
    gitpull_set_browser_nonce($nonce);
    gitpull_page($message, true, $status, $nonce);
}

clearstatcache(true, LOCK_DIR);

$deployToken = (string) @getenv('DEPLOY_TOKEN');
if (!gitpull_valid_deploy_token($deployToken)) {
    gitpull_page('Error: DEPLOY_TOKEN is missing or invalid.', false, 503);
}

$requestMethod = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : '';

if ($requestMethod === 'GET') {
    if (!empty($_GET)) {
        // Never leave credentials or other flags in a bookmarked/browser URL.
        @header('Location: gitpull.php', true, 303);
        exit(0);
    }
    gitpull_browser_form('', 200);
}

if ($requestMethod !== 'POST') {
    @header('Allow: GET, POST');
    gitpull_page('Only GET and POST requests are supported.', false, 405);
}

if (!empty($_GET)) {
    gitpull_page('Deployment authorization failed.', false, 400);
}

$headerToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? null;
$browserSubmission = $headerToken === null;

if ($browserSubmission) {
    $tokenIn = $_POST['deploy_token'] ?? null;
    $csrfIn = $_POST['csrf_token'] ?? null;
    $usernameIn = $_POST['username'] ?? null;
    $csrfCookie = $_COOKIE[GITPULL_CSRF_COOKIE] ?? null;

    /*
     * Browser deployment requires a one-time same-site nonce in addition to
     * the deploy token.  This prevents another site from causing Safari to
     * submit an AutoFilled deployment credential.
     */
    $validBrowserNonce =
        is_string($csrfIn) &&
        is_string($csrfCookie) &&
        preg_match('~\A[a-f0-9]{64}\z~D', $csrfIn) === 1 &&
        hash_equals($csrfCookie, $csrfIn);

    gitpull_clear_browser_nonce();
    unset($_POST['deploy_token'], $_POST['csrf_token'], $_POST['username']);
    unset($_REQUEST['deploy_token'], $_REQUEST['csrf_token'], $_REQUEST['username']);
    unset($_COOKIE[GITPULL_CSRF_COOKIE]);

    if (!$validBrowserNonce || $usernameIn !== 'deploy') {
        gitpull_browser_form('Deployment authorization failed.', 403);
    }
} else {
    // Header authentication is intended for non-browser automation only.
    if (!empty($_POST) || !is_string($headerToken)) {
        gitpull_page('Deployment authorization failed.', false, 403);
    }
    $tokenIn = $headerToken;
    unset($_SERVER['HTTP_X_DEPLOY_TOKEN']);
}

$tokenMatches =
    is_string($tokenIn) &&
    gitpull_valid_deploy_token($tokenIn) &&
    hash_equals($deployToken, $tokenIn);
unset($deployToken, $tokenIn, $headerToken);

if (!$tokenMatches) {
    if ($browserSubmission) {
        gitpull_browser_form('Deployment authorization failed.', 403);
    }
    gitpull_page('Deployment authorization failed.', false, 403);
}

if (@mkdir(LOCK_DIR, 0700)) {
    register_shutdown_function(static function (): void {
        if (is_dir(LOCK_DIR)) {
            @rmdir(LOCK_DIR);
            clearstatcache(true, LOCK_DIR);
        }
    });
    try {
        // Note: gitpull_page() escapes output with htmlspecialchars, so keep raw here to avoid double-encoding.
        /** @psalm-suppress ForbiddenCode */
        $git_hub = (string) shell_exec("(/usr/bin/git fetch --all && /usr/bin/git reset --hard origin/master) 2>&1"); // phpcs:ignore
    } finally {
        @rmdir(LOCK_DIR);
        clearstatcache(true, LOCK_DIR);
    }
} else {
    gitpull_page('Please try again - lock file found', false, 409);
}

if ($browserSubmission) {
    gitpull_browser_form($git_hub, 200);
}

gitpull_page($git_hub, false, 200);
