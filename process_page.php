<?php
declare(strict_types=1);
set_time_limit(120);
@session_start();
@header('Content-type: text/html; charset=utf-8');
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

require_once 'setup.php';

$api = new WikipediaBot();
/** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
if (HTML_OUTPUT) {
  bot_html_header();
}

check_blocked();

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " ";

if (isset($argv[1])) {
  $pages = (string) $argv[1];
} elseif (isset($_GET["page"])) {
  $pages = (string) $_GET["page"];
  if (strpos($pages, '|') !== FALSE) {
    report_error('We do not support multiple pages passed as part of the URL anymore. Use the webform.');
  }
} elseif (isset($_POST["page"])) {
  $pages = (string) $_POST["page"];
} else {
  report_warning('Nothing requested -- OR -- pages got lost during initial authorization ');
  $pages = ''; // Errors out below
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

edit_a_list_of_pages($pages_to_do, $api, $edit_summary_end);

exit();
?>
