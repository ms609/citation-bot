#!/usr/bin/php
<?php

error_reporting(E_ALL^E_NOTICE);
html_echo('<br><pre>','\n');
if (!isset($argv)) $argv=[]; // When run as a webpage, this does not get set
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
$SLOW_MODE = FALSE;
if (isset($_GET["slow"]) || isset($argument["slow"])) {
  $SLOW_MODE = TRUE;
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
    echo ("\n\n\n*** Processing page '{" . echoable($page_title) . "}' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      echo "\n # Writing to " . echoable($page_title) . '... ';
      while (!$page->write($api) && $attempts < 2) ++$attempts;
      safely_echo($page->parsed_text());
      if ($attempts < 3 ) {
        html_echo(
        " </pre><br><small><a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($page_title) . "&action=history>history</a> / "
        . "<a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . get_last_revision($page_title) . ">last edit</a></small></i>\n\n<br><pre>"
        , ".");
      } else {
         echo "\n # Failed. \n";
      }
    } else {
      echo "\n # " . ($page->parsed_text() ? 'No changes required.' : 'Blank page') . "\n # # # ";
    }
  }

  exit ("\n Done all " . count($pages_in_category) . " pages in Category:$category. \n");
} else {
  exit ("You must specify a category.  Try appending ?cat=Blah+blah to the URL, or -cat Category_name at the command line.");
}
