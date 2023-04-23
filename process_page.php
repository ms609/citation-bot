<?php
declare(strict_types=1);
set_time_limit(120);
@session_start(['read_and_close' => TRUE]);

require_once 'html_headers.php';

require_once 'setup.php';

$api = new WikipediaBot();
/** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
if (HTML_OUTPUT) {
  bot_html_header();
  $edit_summary_end = "| Suggested by " . $api->get_the_user() . " ";
} else {
  $edit_summary_end = ""; // Command line edits as the person
}

check_blocked();

if (isset($argv[1])) {
  $pages = $argv[1];
  if ($pages === 'page_list.txt' ) $pages = trim((string) @file_get_contents('page_list.txt' ));
  if ($pages === 'page_list1.txt') $pages = trim((string) @file_get_contents('page_list1.txt'));
  if ($pages === 'page_list2.txt') $pages = trim((string) @file_get_contents('page_list2.txt'));
  if ($pages === 'page_list3.txt') $pages = trim((string) @file_get_contents('page_list3.txt'));
} elseif (isset($_GET["page"])) {
  $pages = $_GET["page"];
  if (!is_string($pages)) {
    report_warning('Non-string found in GET for page.');
    $pages = '';
  }
  if (strpos($pages, '|') !== FALSE) {
    report_warning('Use the webform for multiple pages.');
    $pages = '';
  }
} elseif (isset($_POST["page"])) {
  $pages = $_POST["page"];
  if (!is_string($pages)) {
    report_warning('Non-string found in POST for page.');
    $pages = '';
  }
} else {
  report_warning('Nothing requested -- OR -- pages got lost during initial authorization ');
  $pages = '';
}

if (isset($_REQUEST["edit"]) && $_REQUEST["edit"]) {
   if ($_REQUEST["edit"] === 'automated_tools') {
      $edit_summary_end = $edit_summary_end . "| #UCB_automated_tools ";
   } elseif ($_REQUEST["edit"] === 'toolbar') {
      $edit_summary_end = $edit_summary_end . "| #UCB_toolbar ";
   } elseif ($_REQUEST["edit"] === 'webform') {
      $edit_summary_end = $edit_summary_end . "| #UCB_webform ";
   } elseif ($_REQUEST["edit"] === 'Headbomb') {
      $edit_summary_end = $edit_summary_end . "| #UCB_Headbomb ";
   } elseif ($_REQUEST["edit"] === 'Smith609') {
      $edit_summary_end = $edit_summary_end . "| #UCB_Smith609 ";
   } elseif ($_REQUEST["edit"] === 'arXiv') {
      $edit_summary_end = $edit_summary_end . "| #UCB_arXiv ";
   } else {
      $edit_summary_end = $edit_summary_end . "| #UCB_Other ";
   }
} else {
  /** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
  if (HTML_OUTPUT) {
     $edit_summary_end = $edit_summary_end . "| #UCB_webform ";
  } else {
     $edit_summary_end = $edit_summary_end . "| #UCB_CommandLine ";
  }
}

$pages_to_do = array_unique(explode('|', $pages));
unset($pages);

edit_a_list_of_pages($pages_to_do, $api, $edit_summary_end);

exit("\n");
?>
