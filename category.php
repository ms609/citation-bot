#!/usr/bin/php
<?php

error_reporting(E_ALL^E_NOTICE);
$argument["cat"]=NULL;
$argument["pages"]=NULL;
$pages_in_category=NULL;

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

include("expandFns.php");

$category = $argument["cat"] ? $argument["cat"][0] : $_GET["cat"];
if ($catagory) $pages_in_category = category_members($category);
foreach ($argument["pages"]) as $page_name) { // Add on pages
  $pages_in_category[] = $page_name;
}

if ($pages_in_category) {
  $attempts = 0;
  shuffle($pages_in_category);
  $page = new Page();
  foreach ($pages_in_category as $page_title) {
    echo ("\n\n\n*** Processing page '{" . htmlspecialchars($page_title) . "}' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title) && $page->expand_text()) {
      echo "\n # Writing to " . htmlspecialchars($page->title) . '... ';
      while (!$page->write() && $attempts < 2) ++$attempts;
      echo htmlspecialchars($page->text);
      if ($attempts < 3 ) {
        html_echo(
        " <small><a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($page->title) . "&action=history>history</a> / "
        . "<a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($page->title) . "&diff=prev&oldid="
        . get_last_revision($page->title) . ">last edit</a></small></i>\n\n<br>"
        , ".");
      } else {
         echo "\n # Failed. \n";
      }
    } else {
      echo "\n # " . ($page->text ? 'No changes required.' : 'Blank page') . "\n # # # ";
    }
  }

  exit ("\n Done all " . count($pages_in_category) . " pages in Category:$category. \n");
} else {
  exit ("You must specify a category with items in it.  Try appending ?cat=Blah+blah to the URL.");
}
