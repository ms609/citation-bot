<?php
declare(strict_types=1);

@session_start();
@header('Content-type: text/html; charset=utf-8');
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

require_once 'setup.php';

$api = new WikipediaBot();
/** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
if (HTML_OUTPUT) {?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
        <head>
                <link rel="copyright" type="text/html" href="https://www.gnu.org/licenses/gpl-3.0" />
                <title>Citation Bot: running on pages</title>
                <link rel="stylesheet" type="text/css" href="results.css" />
        </head>
<body>
  <header>
    <p>Follow Citation bots progress below.</p>
    <p>
      <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" title="Using Citation Bot">How&nbsp;to&nbsp;Use&nbsp;/&nbsp;Tips&nbsp;and&nbsp;Tricks</a> |
      <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank">Report&nbsp;bugs</a> |
      <a href="https://github.com/ms609/citation-bot" target="_blank" title="GitHub repository">Source&nbsp;code</a>
    </p>
  </header>
  <main>
<pre id="botOutput">
<?php
}

check_blocked();

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " ";
$final_edit_overview = "";

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
   $ON = TRUE;
   if ($_REQUEST["edit"] == 'automated_tools') {
      $edit_summary_end = $edit_summary_end . "| #UCB_automated_tools ";
   } elseif ($_REQUEST["edit"] == 'toolbar') {
      $edit_summary_end = $edit_summary_end . "| #UCB_toolbar ";
   } elseif ($_REQUEST["edit"] == 'webform') {
      $edit_summary_end = $edit_summary_end . "| #UCB_webform ";
   } elseif ($_REQUEST["edit"] == 'Headbomb') {
      $edit_summary_end = $edit_summary_end . "| #UCB_Headbomb ";
   } elseif ($_REQUEST["edit"] == 'Smith609') {
      $edit_summary_end = $edit_summary_end . "| #UCB_Smith609 ";
   } elseif ($_REQUEST["edit"] == 'arXiv') {
      $edit_summary_end = $edit_summary_end . "| #UCB_arXiv ";
   } else {
      $edit_summary_end = $edit_summary_end . "| #UCB_Other ";
   }
}
if (!isset($ON)) {
  $ON = isset($argv[2]);
  /** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
  if (HTML_OUTPUT) {
     $edit_summary_end = $edit_summary_end . "| #UCB_webform ";  // Assuming
  } else {
     $edit_summary_end = $edit_summary_end . "| #UCB_CommandLine ";
  }
}

$pages_to_do = array_unique(explode('|', $pages));

edit_a_category($pages_to_do, $api);

exit();
?>
