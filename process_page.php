<?php
declare(strict_types=1);

@session_start();
@header('Content-type: text/html; charset=utf-8');
@header('Content-Encoding: None');
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

$my_page = new Page();
$pages_to_do = array_unique(explode('|', $pages));
$done = 0;
$total = count($pages_to_do);

if ($total > MAX_PAGES) {
   report_error('Number of pages is huge (' . (string)$total . ')  Cancelling run (maximum size is ' . (string) MAX_PAGES . ').  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.');
}
if ($total > BIG_RUN) {
  check_overused();
}

gc_collect_cycles();
foreach ($pages_to_do as $page_title) {
  check_killed();
  $done++;
  if (trim($page_title) === '') {  // Default is to edit Wikipedia's main page if user just clicks button.  Let's not even try
     echo "\n\n No page given.  <a href='./' title='Main interface'>Specify one here</a>. \n\n";
     continue;
  }
  // $page->expand_text will take care of this notice if we are in HTML mode.
  html_echo('', "\n\n\n*** Processing page '" . echoable($page_title) . "' : " . date("H:i:s") . "\n");
  if ($my_page->get_text_from($page_title, $api)) {
    $text_expanded = $my_page->expand_text();
    if ($text_expanded && $ON) {
      $attempts = 0;
      if ($total > 1) {
        $extra_end = (string) $done . '/' . (string) $total . ' ';
      } else {
        $extra_end = '';
      }
      while (!$my_page->write($api, $edit_summary_end . $extra_end) && $attempts < MAX_TRIES) ++$attempts;
      if ($attempts < MAX_TRIES ) {
        $last_rev = urlencode($api->get_last_revision($page_title));
        html_echo(
          "\n <small><a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
          . $last_rev . ">diff</a> | "
          . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a></small></i>\n\n"
          , ".");
        $final_edit_overview .=
          "\n [ <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
          . $last_rev . ">diff</a>" .
          " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a> ] " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
      } else {
        echo "\n # Failed. Text was:\n" . echoable($my_page->parsed_text());
        $final_edit_overview .= "\n Write failed.      " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
      }
    } elseif (!$ON && HTML_OUTPUT) {
      echo "\n # Proposed code for " . echoable($page_title) . ', which you have asked the bot to commit with edit summary ' . echoable($my_page->edit_summary()) . "<br><pre>";
      echo echoable($my_page->parsed_text());
      echo "</pre>";
  ?>
  <form method="post" action="process_page.php">
    <input type="hidden" name="page" value="<?php echo urlencode(str_replace(' ', '_', $page_title));?>" />
    <input type="hidden" name="edit" value="webform" />
    <input type="hidden" name="slow" value="<?php echo (string) SLOW_MODE;?>" />
    <input type="submit" value="Submit edits" />
  </form>
  <?php
    } else {
      report_phase($my_page->parsed_text() ? 'No changes required.' : 'Blank page');
      $final_edit_overview .= "\n No changes needed. " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
    }
    echo "\n";
  } else {
    echo "\n Page      '" . echoable($page_title) . "' not found.";
  }
}
if (strpos($pages, '|') !== FALSE) {
  $final_edit_overview .= "\n\n";
  html_echo($final_edit_overview, '');
}
?>
    </pre>
    </main>
    <footer>
      <a href="./" title="Use Citation Bot again">Another&nbsp;page</a>?
    </footer>
  </body>
</html>
<?php
exit();
?>
