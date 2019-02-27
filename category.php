<?php
error_reporting(E_ALL^E_NOTICE);
define("HTML_OUTPUT", TRUE);
require_once('expandFns.php');		
$api = new WikipediaBot();

$category = isset($_REQUEST["cat"]) ? $_REQUEST["cat"] : NULL;

$user = isset($_REQUEST["user"]) ? $_REQUEST["user"] : NULL;

$slow = isset($_REQUEST["slow"]) ? $_REQUEST["slow"] : NULL;
  
if ($category) {
  $pages_in_category = $api->category_members($category);
  shuffle($pages_in_category);
  $url = "https://tools.wmflabs.org/citations/process_page.php?edit=category&slow=" . $slow . ' . "&user=" . $user . "&category=" . $category . "&page=";
  foreach ($pages_in_category as $page_title) {
     $url = $url . urlencode($page_title) . "|";
  }
  header("Location: " . $url);
} else {
  echo '<!DOCTYPE html>';
  echo '<html lang="en" dir="ltr">';
  echo '<head>';
  echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
  echo '<meta content="Smith609" name="author">';
  echo '<meta name="keywords" content="User:DOI bot,Citation, citation bot,Digital object identifier,wikipedia,cite journal" />';
  echo '<link rel="apple-touch-icon" href="https://en.wikipedia.org/apple-touch-icon.png" />';
  echo '<link rel="copyright" href="https://www.gnu.org/copyleft/fdl.html" />';
  echo '<title>Citation bot: Category landing page</title>';
  echo '<link rel="stylesheet" type="text/css" href="css/results.css" />';
  echo '</head>';
  echo '<body>';
  echo 'You must specify a category.  Try appending ?cat=Blah+blah to the URL';
  echo '</body></html>';
}
exit(0);
