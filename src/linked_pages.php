<?php

declare(strict_types=1);

set_time_limit(120);

require_once __DIR__ . '/includes/setup.php';
require_once __DIR__ . '/includes/request_security.php';
send_configured_cors_header(is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null);

if (isset($_POST['linkpage']) && is_string($_POST['linkpage'])) {
    $page_name = $_POST['linkpage'];
} else {
    bot_html_header();
    report_warning(' Error in passing of linked page name ');
    bot_html_footer();
    exit(0);
}

session_start(public_session_start_options(true));

bot_admission_buffer_start();
bot_html_header();

if (!isset($_POST['csrf_token']) || !request_has_valid_post_csrf($_SERVER, $_POST, $_SESSION)) {
    report_warning('Invalid CSRF token.');
    bot_html_footer();
    exit(0);
}

$api = new WikipediaBot();
check_blocked();

$page_name = str_replace(' ', '_', mb_trim($page_name));
if ($page_name === '') {
    report_warning('Nothing requested on webform -- OR -- page name got lost during initial authorization ');
    bot_html_footer();
    exit(0);
} elseif (mb_substr($page_name, 0, 5) !== 'User:' && !in_array($api->get_the_user(), ['Headbomb', 'AManWithNoPlan', 'Redalert2fan'], true)) { // Do not let people run willy-nilly
    report_warning('API only intended for User generated pages for fixing specific issues ');
    bot_html_footer();
    exit(0);
}

$dev_user_run = false;
if (!defined('MAX_PAGES_OVERRIDE') && linked_pages_should_override_limit($api->get_the_user())) {
    define('MAX_PAGES_OVERRIDE', 1000000);
    $dev_user_run = true;
}

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | Linked from {$page_name} | #UCB_webform_linked ";
if ($dev_user_run) {
    $edit_summary_end .= "| Developer - max linked-pages limit override enabled ";
}

$effective_max = defined('MAX_PAGES_OVERRIDE') ? MAX_PAGES_OVERRIDE : MAX_PAGES;
$discovery_entry_id = gate_big_run_probe('webform_linked', $api->get_the_user());
$bulk_discovery_signaled = false;
$pages_in_category = [];
$seen_pages = [];
$continue = null;
$raw_links_scanned = 0;
$probe_batches = 0;

do {
    $batch = WikipediaBot::linked_pages_batch(
        $page_name,
        $continue,
        $bulk_discovery_signaled ? 500 : (BIG_RUN_PAGE_THRESHOLD + 1)
    );
    if ($batch === null) {
        release_big_run_lease($discovery_entry_id);
        report_warning(' Error getting page list');
        bot_html_footer();
        bot_admission_buffer_flush();
        exit(0);
    }

    ++$probe_batches;
    $raw_links_scanned += $batch['scanned'];
    foreach ($batch['links'] as $link) {
        if ($link['ns'] !== 0 && $link['ns'] !== 118) {
            continue;
        }
        $linked_page = str_replace(' ', '_', $link['title']);
        if (in_array($linked_page, AVOIDED_LINKS, true) || mb_stripos($linked_page, 'disambiguation') !== false) {
            continue;
        }
        if (isset($seen_pages[$linked_page])) {
            continue;
        }
        $seen_pages[$linked_page] = true;
        $pages_in_category[] = $linked_page;
        $runnable_count = count($pages_in_category);

        if ($runnable_count === BIG_RUN_LARGE_THRESHOLD) {
            big_jobs_acquire_large_run();
            if ($discovery_entry_id !== null) {
                gate_big_run_discovery_large($discovery_entry_id);
            }
        }
        if (!$bulk_discovery_signaled && $runnable_count > BIG_RUN_PAGE_THRESHOLD) {
            $bulk_discovery_signaled = true;
            gate_big_run_probe_to_discovery($discovery_entry_id);
        }
        if ($runnable_count > $effective_max) {
            break 2;
        }
    }
    if (
        !$bulk_discovery_signaled &&
        big_run_discovery_probe_requires_lease(
            $raw_links_scanned,
            $probe_batches,
            $batch['continue'] !== null
        )
    ) {
        $bulk_discovery_signaled = true;
        gate_big_run_probe_to_discovery($discovery_entry_id);
    }
    $continue = $batch['continue'];
} while ($continue !== null);

unset($page_name, $seen_pages, $continue, $raw_links_scanned, $probe_batches);

unset($_GET, $_POST, $_REQUEST); // Memory minimize

edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end, 'webform_linked', $discovery_entry_id);
