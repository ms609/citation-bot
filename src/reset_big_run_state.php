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

/**
 * Render the authenticated big-run recovery page and stop processing.
 */
function big_run_recovery_http_page(string $message, bool $show_form, int $status): never {
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
        echo '<h2>Check state</h2>',
            '<form method="post" action="reset_big_run_state.php">',
            '<input type="hidden" name="action" value="check">',
            '<label for="check-password">Deployment password</label> ',
            '<input id="check-password" name="password" type="password" ',
            'autocomplete="current-password" required> ',
            '<button type="submit">Check state</button>',
            '</form>',
            '<h2>Reset state</h2>',
            '<p>Reset forgets every shared big-run lease. Drain or quiesce bulk workers first.</p>',
            '<form method="post" action="reset_big_run_state.php">',
            '<input type="hidden" name="action" value="reset">',
            '<label for="reset-password">Deployment password</label> ',
            '<input id="reset-password" name="password" type="password" ',
            'autocomplete="current-password" required><br>',
            '<label><input name="confirm_reset" type="checkbox" value="yes" required> ',
            'I have drained or quiesced bulk workers and understand that reset forgets all shared leases.</label> ',
            '<button type="submit">Reset shared state</button>',
            '</form>';
    }

    echo '</main></body></html>';
    flush();
    exit(0);
}

$deployPassword = (string) @getenv('DEPLOY_PASSWORD');
if ($deployPassword === '') {
    big_run_recovery_http_page('Error: No DEPLOY_PASSWORD is configured.', false, 503);
}

$requestMethod = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : '';

if ($requestMethod === 'GET') {
    if (!empty($_GET)) {
        // Credentials and actions are never accepted from the query string.
        @header('Location: reset_big_run_state.php', true, 303);
        exit(0);
    }
    big_run_recovery_http_page('', true, 200);
}

if ($requestMethod !== 'POST') {
    @header('Allow: GET, POST');
    big_run_recovery_http_page('Only GET and POST requests are supported.', false, 405);
}

$password_in = array_key_exists('HTTP_X_DEPLOY_TOKEN', $_SERVER)
    ? $_SERVER['HTTP_X_DEPLOY_TOKEN']
    : ($_POST['password'] ?? null);
$action_in = $_POST['action'] ?? null;
$confirm_reset = $_POST['confirm_reset'] ?? null;

unset(
    $_SERVER['HTTP_X_DEPLOY_TOKEN'],
    $_POST['password'],
    $_REQUEST['password'],
    $_POST['action'],
    $_REQUEST['action'],
    $_POST['confirm_reset'],
    $_REQUEST['confirm_reset']
);

if ($password_in === null || $password_in === '') {
    big_run_recovery_http_page('Deployment password is required.', true, 400);
}
if (!is_string($password_in)) {
    big_run_recovery_http_page('Invalid password submission.', true, 400);
}

$passwordMatches = hash_equals($deployPassword, $password_in);
unset($deployPassword, $password_in);

if (!$passwordMatches) {
    big_run_recovery_http_page('Incorrect password.', true, 403);
}

if (!is_string($action_in) || !in_array($action_in, ['check', 'reset'], true)) {
    big_run_recovery_http_page('A valid recovery action is required.', true, 400);
}

if ($action_in === 'check') {
    $result = big_run_recovery_check();
    if ($result['ok']) {
        big_run_recovery_http_page(
            'Big-run state is valid. Persisted leases: ' .
                (string) ($result['lease_entries'] ?? 0) .
                '; token balance: ' . (string) ($result['tokens'] ?? 0.0) . '.',
            true,
            200
        );
    }

    big_run_recovery_http_page(
        'Big-run state is invalid (' . (string) ($result['reason'] ?? 'unknown') .
            '). Bulk admission remains fail-closed; no automatic recovery was attempted.',
        true,
        409
    );
}

if ($confirm_reset !== 'yes') {
    big_run_recovery_http_page(
        'Reset confirmation is required. Drain or quiesce bulk workers before resetting.',
        true,
        400
    );
}

$result = big_run_recovery_reset();
if (!$result['ok']) {
    $status = ($result['reason'] ?? null) === 'lock_busy' ? 409 : 503;
    big_run_recovery_http_page(
        'Big-run state reset failed (' . (string) ($result['reason'] ?? 'unknown') . ').',
        true,
        $status
    );
}

$backup = $result['backup_path'];
$message = 'Big-run state reset succeeded. Fresh state contains no leases and a full token bucket.';
if (is_string($backup) && $backup !== '') {
    $message .= ' Previous snapshot preserved as ' . basename($backup) . '.';
} else {
    $message .= ' No previous snapshot existed.';
}
big_run_recovery_http_page($message, true, 200);
