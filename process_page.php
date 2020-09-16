<?php
declare(strict_types=1);

@session_start();
@header( 'Content-type: text/html; charset=utf-8' );
@header("Content-Encoding: None", TRUE);

require_once('setup.php');

$api = new WikipediaBot();
/** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
if (HTML_OUTPUT) {?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
        <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <meta content="Smith609" name="author">
                <meta name="keywords" content="User:DOI bot,Citation, citation bot,Digital object identifier,wikipedia,cite journal" />
                <link rel="apple-touch-icon" href="https://en.wikipedia.org/apple-touch-icon.png" />
                <link rel="copyright" href="https://www.gnu.org/copyleft/fdl.html" />
                <title>Citation bot: Preparing to run</title>
                <link rel="stylesheet" type="text/css" href="css/results.css" />
        </head>
<body>
  <header>
    <p>Follow <a href="https://en.wikipedia.org/wiki/User:Citation_bot">Citation&nbsp;bot</a>&rsquo;s&nbsp;progress&nbsp;below.</p>
    <p>
      <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" title="Using Citation Bot">How&nbsp;to&nbsp;Use&nbsp;/&nbsp;Tips&nbsp;and&nbsp;Tricks</a> |
      <a href="https://en.wikipedia.org/wiki/Special:Contributions/Citation_bot" target="_blank" title="Recent contributions">Bot&rsquo;s&nbsp;recent&nbsp;edits</a> |
      <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank">Report&nbsp;bugs</a> |
      <a href="https://github.com/ms609/citation-bot" target="_blank" title="GitHub repository">Source&nbsp;code</a>
    </p>
  </header>

<pre id="botOutput">
<?php
}

check_blocked();

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " ";
$final_edit_overview = "";

$pages = isset($_REQUEST["page"]) ? (string) $_REQUEST["page"] : (string) @$argv[1];

if (isset($_REQUEST["edit"]) && $_REQUEST["edit"]) {		
  $ON = TRUE;
   if ($_REQUEST["edit"] == 'automated_tools') {
      $edit_summary_end = $edit_summary_end . "| via #UCB_automated_tools ";
   } elseif ($_REQUEST["edit"] == 'toolbar') {
      $edit_summary_end = $edit_summary_end . "| via #UCB_toolbar ";
   } elseif ($_REQUEST["edit"] == 'webform') {
      $edit_summary_end = $edit_summary_end . "| via #UCB_webform ";
   } elseif ($_REQUEST["edit"] == 'Headbomb') {
      $edit_summary_end = $edit_summary_end . "| via #UCB_Headbomb ";
   } elseif ($_REQUEST["edit"] == 'Smith609') {
      $edit_summary_end = $edit_summary_end . "| via #UCB_Smith609 ";
   } else {
      $edit_summary_end = $edit_summary_end . "| via #UCB_Other ";
   }
}
if (!isset($ON)) {
  $ON = isset($argv[2]);
  /** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
  if (HTML_OUTPUT) {
     $edit_summary_end = $edit_summary_end . "| via #UCB_webform ";  // Assuming
  } else {
     $edit_summary_end = $edit_summary_end . "| via #UCB_CommandLine ";
  }
}

foreach (array_unique(explode('|', $pages)) as $page_title) {

  if (trim($page_title) === '') {  // Default is to edit Wikipedia's main page if user just clicks button.  Let's not even try
     echo "\n\n No page given.  <a href='./' title='Main interface'>Specify one here</a>. \n\n";
     continue;
  }
  // $page->expand_text will take care of this notice if we are in HTML mode.
  html_echo('', "\n\n\n*** Processing page '" . echoable($page_title) . "' : " . date("H:i:s") . "\n");
  $my_page = new Page();
  if ($my_page->get_text_from($page_title, $api)) {
    $text_expanded = $my_page->expand_text();
    if ($text_expanded && $ON) {
      $attempts = 0;
      while (!$my_page->write($api, $edit_summary_end) && $attempts < MAX_TRIES) ++$attempts;
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
      ob_flush();
  ?>
  <form method="post" action="process_page.php">
    <input type="hidden" name="page" value="<?php echo $page_title;?>" />
    <input type="hidden" name="edit" value="webform" />
    <input type="hidden" name="slow" value="<?php echo (string) SLOW_MODE;?>" />
    <input type="submit" value="Submit edits" />
  </form>
  <?php
    } else {
      report_phase($my_page->parsed_text() ? 'No changes required.' : 'Blank page');
      $final_edit_overview .= "\n No changes needed. " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
    }
  } else {
    echo "\n Page      '" . htmlspecialchars($page_title) . "' not found.";
  }
}
if (strpos($pages, '|') !== FALSE) {
  $final_edit_overview .= "\n\n" . ' To get the best results, see our helpful <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use">user guides</a>' . "\n\n";
  html_echo($final_edit_overview, '');
}
ob_end_flush();
?>
    </pre>
    <footer>
      <a href="./" title="Use Citation Bot again">Another&nbsp;page</a>?
    </footer>
  </body>
</html>
