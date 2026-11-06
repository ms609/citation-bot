<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/setup.php';
require_once __DIR__ . '/includes/request_security.php';

session_start(public_session_start_options());
$nonce = $_GET['request'] ?? null;
$pending = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && is_string($nonce)
    ? take_oauth_form($_SESSION, $nonce)
    : null;
$csrf_token = ensure_session_csrf_token($_SESSION);
session_write_close();

if ($pending === null) {
    http_response_code(400);
    bot_html_header();
    report_warning('The saved request has expired or was already opened. Return to the main form to start again.');
} else {
    bot_html_header();
    [, $button] = OAUTH_FORM_ACTIONS[$pending['action']];
    echo post_confirmation_form($pending['action'], $pending['fields'], $csrf_token, $button);
}
bot_html_footer();
