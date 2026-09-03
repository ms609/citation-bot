<?php

declare(strict_types=1);

set_time_limit(120);

require_once __DIR__ . '/includes/setup.php';
require_once __DIR__ . '/includes/request_security.php';

send_configured_cors_header(is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null);

const GET_IS_OKAY = [
    'Articles with missing Cite arXiv inputs',
    'CS1 errors: archive-url',
    'CS1 errors: chapter ignored',
    'CS1 errors: DOI',
    'CS1 errors: dates',
    'CS1 errors: extra text: edition',
    'CS1 errors: extra text: issue',
    'CS1 errors: extra text: pages',
    'CS1 errors: extra text: volume',
    'CS1 errors: invisible characters',
    'CS1 maint: article number as page number',
    'CS1 maint: bibcode',
    'CS1 maint: date format',
    'CS1 maint: extra punctuation',
    'CS1 maint: MR format',
    'CS1 maint: PMC embargo expired',
    'CS1 maint: PMC format',
    'CS1 maint: missing class',
    'CS1 maint: unflagged free DOI',
    'Cite IUCN maint',
    'Unflagged free DOI',
];

const DEV_USERS = [
    'AManWithNoPlan',
    'Redalert2fan',
];

$category = '';
$from_get = false;
if (is_string(@$_POST["cat"])) {
    $category = mb_trim($_POST["cat"]);
}
if (mb_strtolower(mb_substr($category, 0, 9)) === 'category:') {
    $category = mb_trim(mb_substr($category, 9));
}
if ($category === '' && is_string(@$_GET["cat"])) {
    $try = mb_trim(urldecode($_GET["cat"]));
    if (in_array($try, GET_IS_OKAY, true)) {
        $category = $try;
        $from_get = true;
    }
}
if (!$category) {
    bot_html_header();
    if (isset($_POST["cat"])) {
        report_warning("Invalid category on the webform.");
    } elseif (is_string(@$_GET["cat"])) {
        report_warning("You must specify this category using the webform, not a cat= URL");
    } elseif (isset($_GET["cat"])) {
        report_warning("Invalid category passed via cat= URL. I suggest using the webform.");
    } else {
        report_warning("Nothing requested -- OR -- category got lost during initial authorization.");
    }
    bot_html_footer();
    exit(0);
}
session_start(public_session_start_options());
$csrf_token = ensure_session_csrf_token($_SESSION);
session_write_close();

if ($from_get) {
    // Authenticate while REQUEST_URI still contains the GET action. OAuth can then return here
    // before the action is converted to a confirmed POST.
    $api = new WikipediaBot();
    unset($api);
    bot_html_header();
    $fields = category_confirmation_fields($category, $_GET);
    echo post_confirmation_form('category.php', $fields, $csrf_token, 'Process category');
    bot_html_footer();
    exit(0);
}
unset($from_get);

bot_html_header();

if (!request_has_valid_post_csrf($_SERVER, $_POST, $_SESSION)) {
    http_response_code(403);
    report_warning('A POST request with a valid CSRF token is required.');
    bot_html_footer();
    exit(0);
}
if (($_POST['extended_limit'] ?? '') === '1' && in_array($category, GET_IS_OKAY, true)) {
    define('MAX_PAGES_OVERRIDE', 1000000); // Match the historical whitelisted-link limit
}
$api = new WikipediaBot();
check_blocked();

$dev_user_run = false;
if (!defined('MAX_PAGES_OVERRIDE') && in_array($api->get_the_user(), DEV_USERS, true)) {
    define('MAX_PAGES_OVERRIDE', 1000000);
    $dev_user_run = true;
}

$pages_in_category = array_unique(WikipediaBot::category_members($category));
shuffle($pages_in_category);
$total = count($pages_in_category);
if ($total === 0) {
    report_warning('Category appears to be empty');
    bot_html_footer();
    exit(0);
}
$effective_max = defined('MAX_PAGES_OVERRIDE') ? MAX_PAGES_OVERRIDE : MAX_PAGES;
$default_web_limit = intval(MAX_PAGES / 4);
if ($total > intval($effective_max / 4)) {
    report_warning('Category is huge. Cancelling run. Maximum size is ' . (string) intval($effective_max / 4));
    echo "\n\n";
    foreach ($pages_in_category as $page_title) {
        echo echoable(str_replace(' ', '_', (string) $page_title)), "\n";
    }
    echo "\n\n";
    bot_html_footer();
    exit(0);
}
if (defined('MAX_PAGES_OVERRIDE') && $total > $default_web_limit) {
    report_info('Whitelisted category has ' . (string) $total . ' pages; proceeding with extended limit.');
}
$edit_summary_end = category_edit_summary_end(
    $api->get_the_user(),
    $category,
    defined('MAX_PAGES_OVERRIDE'),
    $dev_user_run
);
unset($_GET, $_POST, $_REQUEST); // Memory minimize
edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end);
