<?php

declare(strict_types=1);

set_time_limit(120);
if (file_exists(__DIR__ . '/env.php')) {
    /** @psalm-suppress MissingFile */
    include_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/includes/PublicConfig.php';
enforce_public_request_configuration(is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : null);
send_configured_cors_header(is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null);
session_start(public_session_start_options(true));

require_once __DIR__ . '/includes/big_jobs.php';
require_once __DIR__ . '/includes/request_security.php';

// setup.php is deliberately not loaded on this lightweight page, so the two
// output-mode constants it defines must be provided here, matching a web request.
if (!defined('CI')) {
    define('CI', (bool) getenv('CI'));
}
if (!defined('HTML_OUTPUT')) {
    define('HTML_OUTPUT', true);
}

ob_implicit_flush(true);

if (!isset($_SESSION['citation_bot_user_id'])) {
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><link rel="stylesheet" type="text/css" href="assets/results.css" /><title>Killing the big job</title></head><body><main><pre>You are not logged in</pre></main></body></html>';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><link rel="stylesheet" type="text/css" href="assets/results.css" /><title>Killing the big job</title></head><body><main><pre>';
    echo post_confirmation_form('kill_big_job.php', [], (string) ($_SESSION['csrf_token'] ?? ''), 'Stop large job');
    echo '</pre></main></body></html>';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><link rel="stylesheet" type="text/css" href="assets/results.css" /><title>Killing the big job</title></head><body><main><pre>Only GET and POST requests are supported</pre></main></body></html>';
} elseif (!request_has_valid_post_csrf($_SERVER, $_POST, $_SESSION)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><link rel="stylesheet" type="text/css" href="assets/results.css" /><title>Killing the big job</title></head><body><main><pre>Invalid CSRF token</pre></main></body></html>';
} elseif (!big_jobs_kill()) {
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><link rel="stylesheet" type="text/css" href="assets/results.css" /><title>Killing the big job</title></head><body><main><pre>No existing large job found</pre></main></body></html>';
} else {
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><link rel="stylesheet" type="text/css" href="assets/results.css" /><title>Killing the big job</title></head><body><main><pre>Existing large job flagged for stopping</pre></main></body></html>';
}
