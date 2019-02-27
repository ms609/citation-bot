<?php
error_reporting(E_ALL^E_NOTICE);

$cat  = isset($_REQUEST["cat"])  ? "&cat="  . $_REQUEST["cat"]  : "";
$user = isset($_REQUEST["user"]) ? "&user=" . $_REQUEST["user"] : "";
$slow = isset($_REQUEST["slow"]) ? "&slow=" . $_REQUEST["slow"] : "";
  
if ($cat) {
  $url = "https://tools.wmflabs.org/citations/process_page.php?edit=category" . $slow . $user . $cat;
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
