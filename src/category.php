<?php

declare(strict_types=1);

set_time_limit(120);

@header('Access-Control-Allow-Origin: https://citations.toolforge.org');
@header('Access-Control-Allow-Origin: null');

require_once __DIR__ . '/includes/setup.php';

const GET_IS_OKAY = [
    'CS1 errors: archive-url',
    'CS1 maint: PMC format',
    'CS1 maint: date format',
    'CS1 maint: MR format',
    'CS1 maint: bibcode',
    'CS1 maint: PMC embargo expired',
    'CS1 maint: extra punctuation',
    'CS1 maint: unflagged free DOI',
    'Articles with missing Cite arXiv inputs',
    'CS1 errors: DOI',
    'CS1 errors: dates',
    'CS1 errors: extra text: edition',
    'CS1 errors: extra text: issue',
    'CS1 errors: extra text: pages',
    'CS1 errors: extra text: volume',
    'CS1 errors: chapter ignored',
    'CS1 errors: invisible characters',
];

$category = '';
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
    }
}
if (!$category) {
    bot_html_header();
    if (isset($_POST["cat"])) {
        report_warning("Invalid category on the webform.");
    } elseif (is_string(@$_GET["cat"])) {
        report_warning("You must specify this category using the webform.  Got: " . echoable($_GET["cat"]));
    } elseif (isset($_GET["cat"])) {
        report_warning("You must specify your single category as a string using the webform. "); // Get array if multiple cat's are sent
    } else {
        report_warning("Nothing requested -- OR -- category got lost during initial authorization.");
    }
    bot_html_footer();
    exit(0);
}
unset($_GET, $_POST, $_REQUEST); // Memory minimize

session_start(['read_and_close' => true]);
bot_html_header();
$api = new WikipediaBot();
check_blocked();

$pages_in_category = array_unique(WikipediaBot::category_members($category));
shuffle($pages_in_category);
$total = count($pages_in_category);
if ($total === 0) {
    report_warning('Category appears to be empty');
    bot_html_footer();
    exit(0);
}
if ($total > intval(MAX_PAGES / 4)) {
    report_warning('Category is huge. Cancelling run. Maximum size is ' . (string) intval(MAX_PAGES / 4));
    echo "\n\n";
    foreach ($pages_in_category as $page_title) {
        echo echoable(str_replace(' ', '_', (string) $page_title)), "\n";
    }
    echo "\n\n";
    bot_html_footer();
    exit(0);
}
$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | [[Category:{$category}]] | #UCB_Category ";
edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end);
