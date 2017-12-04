#!/usr/bin/php
<?php
// $Id$
error_reporting(E_ALL^E_NOTICE);
$argument["cat"]=NULL;
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

$account_suffix='_4'; // Whilst testing
$account_suffix='_1'; // Keep this before including expandFns
include("expandFns.php");

$category = $argument["cat"] ? $argument["cat"][0] : $_GET["cat"];
if (!$category) $category = "Pages_using_citations_with_old-style_implicit_et_al.";
if ($category) {
  $attempts = 0;
  $pages_in_category = category_members($category);
  #print_r($pages_in_category);
  shuffle($pages_in_category);
  $page = new Page();
  $api = new WikipediaBot();
  #$pages_in_category = array('User:DOI bot/Zandbox');
  foreach ($pages_in_category as $page_title) {
    echo ("\n\n\n*** Processing page '{" . htmlspecialchars($page_title) . "}' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      echo "\n # Writing to " . htmlspecialchars($page->title) . '... ';
      while (!$page->write($api) && $attempts < 2) ++$attempts;
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
  exit ("You must specify a category.  Try appending ?cat=Blah+blah to the URL.");
}
