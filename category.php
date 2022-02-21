<?php
declare(strict_types=1);
@session_start();
@header('Content-type: text/html; charset=utf-8');
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

require_once 'setup.php';

$api = new WikipediaBot();
$category = $_POST["cat"];
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
  <link rel="copyright" type="text/html" href="https://www.gnu.org/licenses/gpl-3.0" />
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
    echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
    exit();
  }
  $pages_in_category = array_unique($pages_in_category); // Paranoid
  shuffle($pages_in_category);

  $total = count($pages_in_category);
  if ($total > intval(MAX_PAGES / 4)) {
    echo 'Category is huge (' . (string) $total . ')  Cancelling run. Pick a smaller category (maximum size is ' . (string) intval(MAX_PAGES / 4) . ').  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.';
    echo "\n\n";
     foreach ($pages_in_category as $page_title) {
       echo (string) $page_title;
    }
    echo "\n\n";
    echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
    exit();
  }
  edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end);
} else {
  if (isset($_POST["cat"])) {
    echo "You must specify a valid category on the webform.";
  } elseif (isset($_GET["cat"])) {
    echo "You must specify a category on the webform.  We do not support using as a parameter to the php file anymore";
  } else {
    echo "You must specify a category using the API -- OR -- category got lost during initial authorization ";
  }
  echo ' # # #</pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
}
exit();
?>
