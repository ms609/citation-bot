<?php
error_reporting(E_ALL^E_NOTICE);

if (isset($_REQUEST["slow"])) {
  $SLOW_MODE = TRUE;
} else {
  $SLOW_MODE = FALSE;
}

define("HTML_OUTPUT", TRUE);
require_once __DIR__ . '/expandFns.php';

$category = $_REQUEST["cat"];

$api = new WikipediaBot();

$user = isset($_REQUEST["user"]) ? $_REQUEST["user"] : NULL;
if (is_valid_user($user)) {
  echo " Activated by $user.\n";
  $edit_summary_end = " | [[User:$user|$user]]; [[Category:$category]].";
} else {
  echo " Anonymous user.  Add &user=MyUserName to URL to sign the bot's edits";
  $edit_summary_end = " | [[WP:UCB|User-activated]]; [[Category:$category]].";
}

?>
<html>
  <body>
  <head>
  <title>Citation bot: Category mode</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <link rel="stylesheet" type="text/css" href="css/results.css" />
  </head>
  <body>
    <pre>
<?php

if ($category) {
  $attempts = 0;
  $pages_in_category = $api->category_members($category);
  shuffle($pages_in_category);
  $page = new Page();
  #$pages_in_category = array('User:DOI bot/Zandbox');
  foreach ($pages_in_category as $page_title) {
    // $page->expand_text will take care of this notice if we are in HTML mode.
    html_echo('', "\n\n\n*** Processing page '" . echoable($page_title) . "' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      report_phase("Writing to " . echoable($page_title) . '... ');
      while (!$page->write($api, $edit_summary_end) && $attempts < 2) ++$attempts;
      // Parsed text can be viewed by diff link; don't clutter page. 
      // print "\n\n"; safely_echo($page->parsed_text());
      if ($attempts < 3 ) {
        html_echo(
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . $api->get_last_revision($page_title) . ">diff</a>" .
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a>", ".");
      } else {
         report_warning("Write failed.");
      }
    } else {
      report_phase($page->parsed_text() ? 'No changes required.' : 'Blank page');
      echo "\n\n    # # # ";
    }
  }
  echo ("\n Done all " . count($pages_in_category) . " pages in Category:$category. \n");
} else {
  echo ("You must specify a category.  Try appending ?cat=Blah+blah to the URL, or -cat Category_name at the command line.");
}
html_echo(' # # #</pre></body></html>', "\n");
ob_end_flush(); 
exit(0);
