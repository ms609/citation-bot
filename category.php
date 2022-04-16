<?php
declare(strict_types=1);
set_time_limit(120);
@session_start();

require_once 'html_headers.php';

require_once 'setup.php';

$api = new WikipediaBot();
$category = '';
if (isset($_POST["cat"])) $category = trim((string) $_POST["cat"]);
if (strtolower(substr($category, 0, 9)) === 'category:') $category = trim(substr($category, 9));
if ($category === '' && isset($_GET["cat"])) {
   $maybe = (string) $_GET["cat"];
   if (in_array($maybe, ['CS1 errors: DOI' , 'CS1 maint: PMC format', 'CS1 maint: MR format', 'Articles with missing Cite arXiv inputs',
                         'CS1 maint: PMC embargo expired', 'CS1 maint: ref=harv'])) $category = $maybe;
}

bot_html_header();

check_blocked();

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | [[Category:$category]] | #UCB_Category ";

if ($category) {
  $pages_in_category = WikipediaBot::category_members($category);
  if (empty($pages_in_category)) {
    report_warning('Category appears to be empty');
    echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
    exit();
  }
  $pages_in_category = array_unique($pages_in_category); // Paranoid
  shuffle($pages_in_category);

  $total = count($pages_in_category);
  if ($total > intval(MAX_PAGES / 4)) {
    report_warning( 'Category is huge (' . (string) $total . ')  Cancelling run. Pick a smaller category (maximum size is ' . (string) intval(MAX_PAGES / 4) . ').  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.');
    echo "\n\n";
    foreach ($pages_in_category as $page_title) {
       echo echoable((string) $page_title) . "\n";
    }
    echo "\n\n";
    echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
    exit();
  }
  unset($total, $category);
  edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end);
} else {
  if (isset($_POST["cat"])) {
    report_warning("Invalid category on the webform.");
  } elseif (isset($_GET["cat"])) {
    report_warning("You must specify the category using the webform.");
  } else {
    report_warning("Nothing requested -- OR -- category got lost during initial authorization.");
  }
  echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
}
exit();
?>
