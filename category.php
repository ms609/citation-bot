<?php
declare(strict_types=1);
@session_start();

require_once('setup.php');
$api = new WikipediaBot();
if (!isset($argv)) {
  $category = isset($_REQUEST["cat"]) ? (string) $_REQUEST["cat"] : '';
} else {
  $category = $argv[1];
}

$category = trim($category);
if (strtolower(substr($category, 0, 9)) == 'category:') $category = trim(substr($category, 9));

if (HTML_OUTPUT) {
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
  <title>Citation bot: Category mode</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta content="Smith609" name="author">
  <meta name="keywords" content="User:DOI bot,Citation, citation bot,Digital object identifier,wikipedia,cite journal" />
  <link rel="apple-touch-icon" href="https://en.wikipedia.org/apple-touch-icon.png" />
  <link rel="copyright" href="https://www.gnu.org/copyleft/fdl.html" />
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

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | [[Category:$category]] | via #UCB_Category ";
$final_edit_overview = "";

if ($category) {
  $attempts = 0;
  $pages_in_category = $api->category_members($category);
  if (!empty($pages_in_category)) {
    echo('Category appears to be empty');
    html_echo(' </pre></body></html>', "\n");
    exit(0);
  }
  if (count($pages_in_category) > 1000) {
    echo('Category is huge.  Cancelling run. Pick a smaller category.  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.');
    html_echo(' </pre></body></html>', "\n");
    exit(0);
  }
  shuffle($pages_in_category);
  $page = new Page();
  foreach ($pages_in_category as $page_title) {
    // $page->expand_text will take care of this notice if we are in HTML mode.
    html_echo('', "\n\n\n*** Processing page '" . echoable($page_title) . "' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      report_phase("Writing to " . echoable($page_title) . '... ');
      while (!$page->write($api, $edit_summary_end) && $attempts < 2) ++$attempts;
      if ($attempts < 3 ) {
        $last_rev = urlencode($api->get_last_revision($page_title));
        html_echo(
        "\n  <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . $last_rev . ">diff</a>" .
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a>", ".");
        $final_edit_overview .=
          "\n [ <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . $last_rev . ">diff</a>" .
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a> ] " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
      } else {
         report_warning("Write failed.");
         $final_edit_overview .= "\n Write failed.      " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
      }
    } else {
      report_phase($page->parsed_text() ? 'No changes required.' : 'Blank page');
      echo "\n\n    # # # ";
      $final_edit_overview .= "\n No changes needed. " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
    }
  }
  echo ("\n Done all " . count($pages_in_category) . " pages in Category:$category. \n");
  $final_edit_overview .= "\n\n" . ' To get the best results, see our helpful <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use">user guides</a>' . "\n\n";
  html_echo($final_edit_overview, '');
} else {
  echo ("You must specify a category.  Try appending ?cat=Blah+blah to the URL, or -cat Category_name at the command line.");
}
html_echo(' # # #</pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>', "\n");
exit(0);
