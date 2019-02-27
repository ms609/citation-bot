<?php
error_reporting(E_ALL^E_NOTICE);

$cat  = isset($_REQUEST["cat"])  ? "&cat="  . $_REQUEST["cat"]  : "";
$user = isset($_REQUEST["user"]) ? "&user=" . $_REQUEST["user"] : "";
$slow = isset($_REQUEST["slow"]) ? "&slow=" . $_REQUEST["slow"] : "";
  
if ($cat) {
  $url = "https://tools.wmflabs.org/citations/process_page.php?edit=category" . $slow . $user . $cat;
  header("Location: " . $url);
} else {
?>
<!DOCTYPE html>
 <html lang="en" dir="ltr">
  <head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
   <meta content="Smith609" name="author" />
   <meta name="keywords" content="User:DOI bot,Citation, citation bot,Digital object identifier,wikipedia,cite journal" />
   <meta name="robots" content="noindex, nofollow" />
   <link rel="apple-touch-icon" href="https://en.wikipedia.org/apple-touch-icon.png" />
   <link rel="copyright" href="https://www.gnu.org/copyleft/fdl.html" />
   <link rel="stylesheet" type="text/css" href="css/results.css" />
   <title>Citation bot: Category landing page</title>
  </head>
  <body>
   You must specify a category. See <a href="https://tools.wmflabs.org/citations/">https://tools.wmflabs.org/citations/</a>
  </body>
 </html>
<?php
}
exit(0);
