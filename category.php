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
  bot_html_header();
}

check_blocked();

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | [[Category:$category]] | #UCB_Category ";

if ($category) {
  $pages_in_category = $api->category_members($category);
  if (empty($pages_in_category)) {
    report_warning('Category appears to be empty');
    html_echo(' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>', "\n");
    exit();
  }
  $pages_in_category = array_unique($pages_in_category); // Paranoid
  shuffle($pages_in_category);

  $total = count($pages_in_category);
  if ($total > intval(MAX_PAGES / 4)) {
    report_warning( 'Category is huge (' . (string) $total . ')  Cancelling run. Pick a smaller category (maximum size is ' . (string) intval(MAX_PAGES / 4) . ').  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.');
    echo "\n\n";
    foreach ($pages_in_category as $page_title) {
       html_echo((string) $page_title . "\n");
    }
    echo "\n\n";
    html_echo(' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>', "\n");
    exit();
  }
  edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end);
} else {
  if (isset($argv[1])) {
    echo "You must specify a category on the command line.";
  } elseif (isset($_POST["cat"])) {
    report_warning("You must specify a valid category on the webform.");
  } elseif (isset($_GET["cat"])) {
    report_warning("You must specify a category on the webform.  We do not support using as a parameter to the php file anymore");
  } else {
    report_warning("You must specify a category using the API -- OR -- category got lost during initial authorization ");
  }
  html_echo(' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>', "\n");
}
exit();
?>
