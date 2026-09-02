<?php

declare(strict_types=1);

// We minimize include files so that this works even if we break the deployment.
// For automation:
// curl -X POST -H "X-Deploy-Token: ${DEPLOY_PASSWORD}" "https://citations.toolforge.org/gitpull.php"
// For interactive use, visit gitpull.php in a browser and submit the password form.

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

/**
 * Render the deployment page and stop processing.
 */
function gitpull_page(string $message, bool $show_form, int $status): never {
    http_response_code($status);
    @header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head>',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
        '<meta charset="utf-8"><title>Git Pull</title></head><body><main>';

    echo '<p>', htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'), '</p>';

    if ($show_form) {
        echo '<form method="post" action="gitpull.php">',
            '<label for="deploy-password">Deployment password</label> ',
            '<input id="deploy-password" name="password" type="password" ',
            'autocomplete="current-password" required autofocus> ',
            '<button type="submit">Deploy</button>',
            '</form>';
    }

    echo '</main></body></html>';
    flush();
    exit(0);
}

clearstatcache(true, LOCK_DIR);

$deployPassword = (string) @getenv('DEPLOY_PASSWORD');
if ($deployPassword === '') {
    gitpull_page('Error: No DEPLOY_PASSWORD is configured.', false, 503);
}

$requestMethod = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : '';

if ($requestMethod === 'GET') {
    if (!empty($_GET)) { // Clean up all URLs with flags passed in
        @header('Location: gitpull.php', true, 303);
        exit(0);
    }
    gitpull_page('', true, 200);
}

if ($requestMethod !== 'POST') {
    @header('Allow: GET, POST');
    gitpull_page('Only GET and POST requests are supported.', false, 405);
}

$password_in = array_key_exists('HTTP_X_DEPLOY_TOKEN', $_SERVER)
    ? $_SERVER['HTTP_X_DEPLOY_TOKEN']
    : ($_POST['password'] ?? null);

unset($_SERVER['HTTP_X_DEPLOY_TOKEN'], $_POST['password'], $_REQUEST['password']);

if ($password_in === null || $password_in === '') {
    gitpull_page('Deployment password is required.', true, 400);
}
if (!is_string($password_in)) {
    gitpull_page('Invalid password submission.', true, 400);
}

$passwordMatches = hash_equals($deployPassword, $password_in);
unset($deployPassword, $password_in);

if (!$passwordMatches) {
    gitpull_page('Incorrect password.', true, 403);
}

if (@mkdir(LOCK_DIR, 0700)) {
    register_shutdown_function(static function (): void {
        if (is_dir(LOCK_DIR)) {
            @rmdir(LOCK_DIR);
            clearstatcache(true, LOCK_DIR);
        }
    });
    try {
        /** @psalm-suppress ForbiddenCode */
        $git_hub = htmlspecialchars(
            (string) shell_exec("(/usr/bin/git fetch --all && /usr/bin/git reset --hard origin/master) 2>&1"), // phpcs:ignore
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'
        );
    } finally {
        @rmdir(LOCK_DIR);
        clearstatcache(true, LOCK_DIR);
    }
} else {
    gitpull_page('Please try again - lock file found', false, 409);
}

gitpull_page($git_hub, true, 200);
