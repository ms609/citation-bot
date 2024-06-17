<?php

declare(strict_types=1);

set_time_limit(120);

require_once 'setup.php';

const GET_IS_OKAY = [
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
if (isset($_POST["cat"]) && is_string($_POST["cat"])) {
    $category = trim($_POST["cat"]);
}
if (strtolower(substr($category, 0, 9)) === 'category:') {
    $category = trim(substr($category, 9));
}
if ($category === '' && isset($_GET["cat"])) {
   $try = trim(urldecode((string) $_GET["cat"]));
   if (in_array($try, GET_IS_OKAY, true)) {
       $category = $try;
   }
}
if (!$category) {
    bot_html_header();
    if (isset($_POST["cat"])) {
        report_warning("Invalid category on the webform.");
    } elseif (isset($_GET["cat"])) {
        report_warning("You must specify the category using the webform.  Got: " . echoable($_GET["cat"]));
    } else {
        report_warning("Nothing requested -- OR -- category got lost during initial authorization.");
    }
    bot_html_footer();
    exit;
}

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
    exit;
}
if ($total > intval(MAX_PAGES / 4)) {
    report_warning('Category is huge. Cancelling run. Maximum size is ' . (string) intval(MAX_PAGES / 4));
    echo "\n\n";
    foreach ($pages_in_category as $page_title) {
       echo echoable((string) $page_title) . "\n";
    }
    echo "\n\n";
    bot_html_footer();
    exit;
}
$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | [[Category:{$category}]] | #UCB_Category ";
edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end);

?>
