<?php

declare(strict_types=1);

// Keep the dependency set deliberately small so recovery remains available
// even when unrelated application code is unhealthy. Authentication and
// public-request hardening intentionally mirror gitpull.php.
/** @psalm-suppress MissingFile */
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/includes/PublicConfig.php';
require_once __DIR__ . '/includes/RequestRateLimit.php';

enforce_public_request_configuration(
    is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : null
);
send_configured_cors_header(
    is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null
);
@header('Cache-Control: no-store');
@header('Referrer-Policy: no-referrer');
@header('X-Content-Type-Options: nosniff');
@header("Content-Security-Policy: default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

const BIG_RUN_RECOVERY_CSRF_COOKIE = 'citation_bot_big_run_recovery_csrf';
const BIG_RUN_RECOVERY_CSRF_TTL = 600;

function big_run_recovery_valid_deploy_token(string $token): bool {
    return preg_match('~\A[a-f0-9]{64}\z~D', $token) === 1;
}

function big_run_recovery_new_browser_nonce(): string {
    return bin2hex(random_bytes(32));
}

function big_run_recovery_cookie_path(): string {
    return public_url_path('/reset_big_run_state.php');
}

function big_run_recovery_set_browser_nonce(string $nonce): void {
    $scheme = parse_url(public_base_url(), PHP_URL_SCHEME);
    setcookie(BIG_RUN_RECOVERY_CSRF_COOKIE, $nonce, [
        'expires' => time() + BIG_RUN_RECOVERY_CSRF_TTL,
        'path' => big_run_recovery_cookie_path(),
        'secure' => $scheme === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function big_run_recovery_clear_browser_nonce(): void {
    $scheme = parse_url(public_base_url(), PHP_URL_SCHEME);
    setcookie(BIG_RUN_RECOVERY_CSRF_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => big_run_recovery_cookie_path(),
        'secure' => $scheme === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Render the authenticated big-run recovery page and stop processing.
 */
function big_run_recovery_http_page(
    string $message,
    bool $show_form,
    int $status,
    string $csrf_token = ''
): never {
    http_response_code($status);
    @header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head>',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
        '<meta charset="utf-8"><title>Big-run state recovery</title></head><body><main>',
        '<h1>Big-run state recovery</h1>';

    if ($message !== '') {
        echo '<p>', htmlspecialchars(
            $message,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        ), '</p>';
    }

    if ($show_form) {
        $escaped_csrf = htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        echo '<h2>Check state</h2>',
            '<form method="post" action="reset_big_run_state.php">',
            '<input type="hidden" name="csrf_token" value="', $escaped_csrf, '">',
            '<input type="hidden" name="action" value="check">',
            '<label for="check-user">Account</label> ',
            '<input id="check-user" name="username" type="text" value="deploy" ',
            'autocomplete="username" autocapitalize="none" spellcheck="false" readonly> ',
            '<label for="check-token">Deployment token</label> ',
            '<input id="check-token" name="deploy_token" type="password" ',
            'autocomplete="current-password" autocapitalize="none" spellcheck="false" ',
            'inputmode="text" minlength="64" maxlength="64" pattern="[a-f0-9]{64}" required> ',
            '<button type="submit">Check state</button>',
            '</form>',
            '<h2>Reset state</h2>',
            '<p>Reset forgets every shared big-run lease. Drain or quiesce bulk workers first.</p>',
            '<form method="post" action="reset_big_run_state.php">',
            '<input type="hidden" name="csrf_token" value="', $escaped_csrf, '">',
            '<input type="hidden" name="action" value="reset">',
            '<label for="reset-user">Account</label> ',
            '<input id="reset-user" name="username" type="text" value="deploy" ',
            'autocomplete="username" autocapitalize="none" spellcheck="false" readonly> ',
            '<label for="reset-token">Deployment token</label> ',
            '<input id="reset-token" name="deploy_token" type="password" ',
            'autocomplete="current-password" autocapitalize="none" spellcheck="false" ',
            'inputmode="text" minlength="64" maxlength="64" pattern="[a-f0-9]{64}" required><br>',
            '<label><input name="confirm_reset" type="checkbox" value="yes" required> ',
            'I have drained or quiesced bulk workers and understand that reset forgets all shared leases.</label> ',
            '<button type="submit">Reset shared state</button>',
            '</form>';
    }

    echo '</main></body></html>';
    flush();
    exit(0);
}

function big_run_recovery_browser_form(string $message, int $status): never {
    $nonce = big_run_recovery_new_browser_nonce();
    big_run_recovery_set_browser_nonce($nonce);
    big_run_recovery_http_page($message, true, $status, $nonce);
}

function big_run_recovery_response(string $message, int $status, bool $browser_submission): never {
    if ($browser_submission) {
        big_run_recovery_browser_form($message, $status);
    }
    big_run_recovery_http_page($message, false, $status);
}

$deployToken = (string) @getenv('DEPLOY_TOKEN');
if (!big_run_recovery_valid_deploy_token($deployToken)) {
    big_run_recovery_http_page(
        'Error: DEPLOY_TOKEN is missing or invalid.',
        false,
        503
    );
}

$requestMethod = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : '';

if ($requestMethod === 'GET') {
    if (!empty($_GET)) {
        // Credentials and actions are never accepted from the query string.
        @header('Location: reset_big_run_state.php', true, 303);
        exit(0);
    }
    big_run_recovery_browser_form('', 200);
}

if ($requestMethod !== 'POST') {
    @header('Allow: GET, POST');
    big_run_recovery_http_page('Only GET and POST requests are supported.', false, 405);
}

if (!empty($_GET)) {
    big_run_recovery_http_page('Deployment authorization failed.', false, 400);
}

$headerToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? null;
$browserSubmission = $headerToken === null;
$action_in = $_POST['action'] ?? null;
$confirm_reset = $_POST['confirm_reset'] ?? null;

if ($browserSubmission) {
    $tokenIn = $_POST['deploy_token'] ?? null;
    $csrfIn = $_POST['csrf_token'] ?? null;
    $usernameIn = $_POST['username'] ?? null;
    $csrfCookie = $_COOKIE[BIG_RUN_RECOVERY_CSRF_COOKIE] ?? null;

    $validBrowserNonce =
        is_string($csrfIn) &&
        is_string($csrfCookie) &&
        preg_match('~\A[a-f0-9]{64}\z~D', $csrfIn) === 1 &&
        hash_equals($csrfCookie, $csrfIn);

    big_run_recovery_clear_browser_nonce();
    unset($_COOKIE[BIG_RUN_RECOVERY_CSRF_COOKIE]);

    if (!$validBrowserNonce || $usernameIn !== 'deploy') {
        big_run_recovery_browser_form('Deployment authorization failed.', 403);
    }
} else {
    /*
     * Header authentication may carry recovery-control fields, but never
     * browser credentials or CSRF state.
     */
    $allowed_fields = ['action', 'confirm_reset'];
    foreach (array_keys($_POST) as $field) {
        if (!is_string($field) || !in_array($field, $allowed_fields, true)) {
            big_run_recovery_http_page('Deployment authorization failed.', false, 403);
        }
    }
    if (!is_string($headerToken)) {
        big_run_recovery_http_page('Deployment authorization failed.', false, 403);
    }
    $tokenIn = $headerToken;
}

unset(
    $_SERVER['HTTP_X_DEPLOY_TOKEN'],
    $_POST['deploy_token'],
    $_REQUEST['deploy_token'],
    $_POST['csrf_token'],
    $_REQUEST['csrf_token'],
    $_POST['username'],
    $_REQUEST['username'],
    $_POST['action'],
    $_REQUEST['action'],
    $_POST['confirm_reset'],
    $_REQUEST['confirm_reset']
);

$tokenMatches =
    is_string($tokenIn) &&
    big_run_recovery_valid_deploy_token($tokenIn) &&
    hash_equals($deployToken, $tokenIn);
unset($deployToken, $tokenIn, $headerToken);

if (!$tokenMatches) {
    big_run_recovery_response('Deployment authorization failed.', 403, $browserSubmission);
}

if (!is_string($action_in) || !in_array($action_in, ['check', 'reset'], true)) {
    big_run_recovery_response('A valid recovery action is required.', 400, $browserSubmission);
}

if ($action_in === 'check') {
    $result = big_run_recovery_check();
    if ($result['ok']) {
        big_run_recovery_response(
            'Big-run state is valid. Persisted leases: ' .
                (string) ($result['lease_entries'] ?? 0) .
                '; token balance: ' . (string) ($result['tokens'] ?? 0.0) . '.',
            200,
            $browserSubmission
        );
    }

    big_run_recovery_response(
        'Big-run state is invalid (' . (string) ($result['reason'] ?? 'unknown') .
            '). Bulk admission remains fail-closed; no automatic recovery was attempted.',
        409,
        $browserSubmission
    );
}

if ($confirm_reset !== 'yes') {
    big_run_recovery_response(
        'Reset confirmation is required. Drain or quiesce bulk workers before resetting.',
        400,
        $browserSubmission
    );
}

$result = big_run_recovery_reset();
if (!$result['ok']) {
    $status = ($result['reason'] ?? null) === 'lock_busy' ? 409 : 503;
    big_run_recovery_response(
        'Big-run state reset failed (' . (string) ($result['reason'] ?? 'unknown') . ').',
        $status,
        $browserSubmission
    );
}

$backup = $result['backup_path'];
$message = 'Big-run state reset succeeded. Fresh state contains no leases and a full token bucket.';
if (is_string($backup) && $backup !== '') {
    $message .= ' Previous snapshot preserved as ' . basename($backup) . '.';
} else {
    $message .= ' No previous snapshot existed.';
}
big_run_recovery_response($message, 200, $browserSubmission);
