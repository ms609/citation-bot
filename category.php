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
$category = isset($_POST["cat"]) ? (string) $_POST["cat"] : (string) @$argv[1];
$category = trim($category);
if ($category === '' && isset($_GET["cat"])) {
   $maybe = (string) $_GET["cat"];
   if (in_array($maybe, ['CS1 errors: DOI' , 'CS1 maint: PMC format', 'CS1 maint: MR format', 'Articles with missing Cite arXiv inputs',
                         'CS1 maint: PMC embargo expired', 'CS1 maint: ref=harv'])) $category = $maybe;
}

if (strtolower(substr($category, 0, 9)) == 'category:') $category = trim(substr($category, 9));
/** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
if (HTML_OUTPUT) {
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
  <title>Citation Bot: running in category mode</title>
  <link rel="copyright" href="https://www.gnu.org/copyleft/fdl.html" />
  <link rel="stylesheet" type="text/css" href="results.css" />
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

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | [[Category:$category]] | #UCB_Category ";
$final_edit_overview = "";

if ($category) {
  $pages_in_category = $api->category_members($category);
  if (empty($pages_in_category)) {
    echo 'Category appears to be empty';
    html_echo(' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>', "\n");
    exit();
  }
  $pages_in_category = array_unique($pages_in_category); // Paranoid
  shuffle($pages_in_category);

  $total = count($pages_in_category);
  if ($total > intval(MAX_PAGES / 4)) {
    echo 'Category is huge (' . (string) $total . ')  Cancelling run. Pick a smaller category (maximum size is ' . (string) intval(MAX_PAGES / 4) . ').  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.';
    echo "\n\n";
     foreach ($pages_in_category as $page_title) {
       html_echo((string) $page_title . "\n");
    }
    echo "\n\n";
    html_echo(' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>', "\n");
    exit();
  }
  if ($total > BIG_RUN) check_overused();
  $page = new Page();
  $done = 0;

  gc_collect_cycles();
  foreach ($pages_in_category as $page_title) {
    check_killed();
    $done++;
    // $page->expand_text will take care of this notice if we are in HTML mode.
    html_echo('', "\n\n\n*** Processing page '" . echoable($page_title) . "' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      report_phase("Writing to " . echoable($page_title) . '... ');
      $attempts = 0;
      while (!$page->write($api, $edit_summary_end . (string) $done . '/' . (string) $total . ' ') && $attempts < MAX_TRIES) ++$attempts;
      if ($attempts < MAX_TRIES ) {
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
    echo "\n";
  }
  echo "\n Done all " . (string) $total . " pages in Category:" . echoable($category) . ". \n";
  $final_edit_overview .= "\n\n" . ' To get the best results, see our helpful <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use">user guides</a>' . "\n\n";
  html_echo($final_edit_overview, '');
} else {
  if (isset($argv[1])) {
    echo "You must specify a category on the command line.";
  } elseif (isset($_POST["cat"])) {
    echo "You must specify a valid category on the webform.";
  } elseif (isset($_GET["cat"])) {
    echo "You must specify a category on the webform.  We do not support using as a parameter to the php file anymore";
  } else {
    echo "You must specify a category using the API -- OR -- category got lost during initial authorization ";
  }
}
html_echo(' # # #</pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>', "\n");
exit();
?>
