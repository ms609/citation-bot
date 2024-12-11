<?php

declare(strict_types=1);

set_time_limit(120);

@header('Access-Control-Allow-Origin: https://citations.toolforge.org');
@header('Access-Control-Allow-Origin: null');

if (isset($_GET["page"]) && empty($_COOKIE['CiteBot'])) {
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Citation Bot: error</title></head><body><main><h1>You need to run the bot using the <a href="/">web interface</a> first to get permission tokens</h1></main></body></html>'; // Fast exit, do not even include setup.php
    exit;
}

require_once 'setup.php';

if (isset($argv[1])) {
    $pages = $argv[1];
    if (in_array($pages, ['page_list.txt', 'page_list2.txt'], true)) {
        $pages = trim((string) file_get_contents($pages));
    }
} elseif (isset($_GET["page"])) {
    $pages = $_GET["page"];
    if (!is_string($pages)) {
        bot_html_header();
        report_warning('Non-string found in GET for page.');
        bot_html_footer();
        exit;
    }
    if (strpos($pages, '|') !== false) {
        bot_html_header();
        report_warning('Use the webform for multiple pages.');
        bot_html_footer();
        exit;
    }
} elseif (isset($_POST["page"])) {
    $pages = $_POST["page"];
    if (!is_string($pages)) {
        bot_html_header();
        report_warning('Non-string found in POST for page.');
        bot_html_footer();
        exit;
    }
} else {
    bot_html_header();
    report_warning('Nothing requested -- OR -- pages got lost during initial authorization ');
    bot_html_footer();
    exit;
}

// Do not open session until we know we have good data
session_start(['read_and_close' => true]);

bot_html_header();

$api = new WikipediaBot();

if (HTML_OUTPUT) {
    $edit_summary_end = "| Suggested by " . $api->get_the_user() . " ";
} else {
    $edit_summary_end = ""; // Command line edits as the person
}

check_blocked();

if (isset($_REQUEST["edit"]) && $_REQUEST["edit"]) {
    if ($_REQUEST["edit"] === 'automated_tools') {
        $edit_summary_end .= "| #UCB_automated_tools ";
    } elseif ($_REQUEST["edit"] === 'toolbar') {
        $edit_summary_end .= "| #UCB_toolbar ";
    } elseif ($_REQUEST["edit"] === 'webform') {
        $edit_summary_end .= "| #UCB_webform ";
    } elseif ($_REQUEST["edit"] === 'Headbomb') {
        $edit_summary_end .= "| #UCB_Headbomb ";
    } elseif ($_REQUEST["edit"] === 'Smith609') {
        $edit_summary_end  .= "| #UCB_Smith609 ";
    } elseif ($_REQUEST["edit"] === 'arXiv') {
        $edit_summary_end .= "| #UCB_arXiv ";
    } else {
        $edit_summary_end .= "| #UCB_Other ";
    }
} else {
    if (HTML_OUTPUT) {
        $edit_summary_end .= "| #UCB_webform ";
    } else {
        $edit_summary_end .= "| #UCB_CommandLine ";
    }
}

$pages_to_do = array_unique(explode('|', $pages));
unset($pages);
unset($_GET, $_POST, $_REQUEST); // Memory minimize

edit_a_list_of_pages($pages_to_do, $api, $edit_summary_end);

?>
