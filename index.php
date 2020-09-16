<?php
declare(strict_types=1);
@session_start();
@header( 'Content-type: text/html; charset=utf-8' );
@header("Content-Encoding: None", TRUE);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <link rel="apple-touch-icon" href="https://en.wikipedia.org/apple-touch-icon.png" />
  <link rel="copyright" href="https://www.gnu.org/copyleft/fdl.html" />
  <link rel="stylesheet" type="text/css" href="css/results.css" />
  <meta content="A bot to automatically tidy and complete citations on Wikipedia, the free encyclopaedia" name="description" />
  <title>
   Citation bot
  </title>   
  <script type="text/javascript" src="js/index.js"></script>
</head>  
<body class="mediawiki ns--1 ltr page-Spezial_Beiträge">
  <header>
    <h1>Wikipedia citation bot</h1>
    <p>Populates empty fields in {{cite journal}} family templates, and fix other citation issues.</p>
  </header>
  <form id="botForm" action="process_page.php" method="get">
    <p>
      <input type="checkbox" name="slow" id="slow" checked />
      <label for="slow">Thorough mode - a slower, but more exhaustive, search. Finds bibcodes and flags "broken" DOIs.</label>
    </p>
    <p>
      <input type="checkbox" name="edit" id="edit" value="webform" checked />
      <label for="edit">Commit edits.  Leave blank to check Wikicode.</label>
    </p>
    <p>
      <code>Single page:&nbsp;</code>
      <input name="page" id="botPage" value="" placeholder="Page name" />
        <button type="submit" name="pageSubmit" id="PageSubmit" value="Process page" formaction="process_page.php">Process page</button>
        <img style="display:none" src="img/spinner_18_18.gif" id="PageSpinner" alt="" />
        <br />Separate multiple pages with a pipe (<code>Page 1|Page 2</code>)
    </p>
    <p>– or –</p>
    <p>
      <code>Category:&nbsp;</code>
      <input name="cat" id="botCat" value="" placeholder="Category name" />
        <button type="submit" name="catSubmit" id="CatSubmit" value="Process category" formaction="category.php">Process pages in category</button>
        <img style="display:none" src="img/spinner_18_18.gif" id="CatSpinner" alt="" />
        <br />
    </p>
    <p>
    </p>
  </form>
  <footer>  
    <p>Maintained by <a href="https://en.wikipedia.org/wiki/User:Smith609">Martin Smith</a>.</p>
    <p>
      <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" title="Using Citation Bot">More details</a> | 
      <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia">Report bugs</a> |
      <a href="https://github.com/ms609/citation-bot" title="GitHub repository">Source code</a>
    </p>
    <p>
    Your Wikipedia user name will be included in the edit, such as "Suggested&nbsp;by&nbsp;
  <?php
  if (isset($_SESSION['citation_bot_user_id'])) {
     echo (string) @htmlspecialchars((string) $_SESSION['citation_bot_user_id']);
   } else {
     echo 'YourUserID';
   }
   ?>"
    </p>
  </footer>
</body>
</html>
