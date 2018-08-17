#!/usr/bin/php
<?php

error_reporting(E_ALL^E_NOTICE);

if (php_sapi_name() == 'cli') {
  define("HTML_OUTPUT", FALSE);
} else {
  define("HTML_OUTPUT", TRUE);
}

$argument["cat"] = NULL;
foreach ($argv as $arg) {
  if (substr($arg, 0, 2) == "--") {
    $argument[substr($arg, 2)] = 1;
  } elseif (substr($arg, 0, 1) == "-") {
    $oArg = substr($arg, 1);
  } else {
    switch ($oArg) {
      case "P": case "A": case "T":
        $argument["pages"][] = $arg;
        break;
      default:
      $argument[$oArg][] = $arg;
    }
  }
}
require_once __DIR__ . '/expandFns.php';

$category = $argument["cat"] ? $argument["cat"][0] : $_GET["cat"];
if ($category) {
  $attempts = 0;
  $api = new WikipediaBot();
  $pages_in_category = $api->category_members($category);
  shuffle($pages_in_category);
  $page = new Page();
  #$pages_in_category = array('User:DOI bot/Zandbox');
  foreach ($pages_in_category as $page_title) {
    html_echo ("<br><br><br>*** Processing page '{" . echoable($page_title) . "}' : " . date("H:i:s") . "<br>",
               "\n\n\n*** Processing page '{" . echoable($page_title) . "}' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      html_echo ("<br> # Writing to " . echoable($page_title) . '... ',
                 "\n # Writing to " . echoable($page_title) . '... ');
      while (!$page->write($api) && $attempts < 2) ++$attempts;
      safely_echo($page->parsed_text());
      if ($attempts < 3 ) {
        html_echo(
        " <small><a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($page_title) . "&action=history>history</a> / "
        . "<a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . get_last_revision($page_title) . ">last edit</a></small></i>\n\n<br>"
        , ".");
      } else {
         html_echo ("<br> # Failed. <br>",
                    "\n # Failed. \n")
      }
    } else {
      html_echo ( "<br> # " . ($page->parsed_text() ? 'No changes required.' : 'Blank page') . "<br> # # # ",
                  "\n # " . ($page->parsed_text() ? 'No changes required.' : 'Blank page') . "\n # # # ");
    }
  }

  html_echo("<br> Done all " . count($pages_in_category) . " pages in Category:$category. <br>",
            "\n Done all " . count($pages_in_category) . " pages in Category:$category. \n");
} else {
  html_echo("You must specify a category.  Try appending ?cat=Blah+blah to the URL" ,
            "You must specify a category.  Try appending -cat Category_name at the command line.");
}
exit(0);
