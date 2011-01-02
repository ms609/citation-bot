#!/usr/bin/php
<?
// $Id$

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

$slowMode = false;
$fastMode = false;
$accountSuffix='_1'; // Keep this before including expandFns
include("expandFns.php");
$htmlOutput = false;
$editInitiator = '[Cat' . revisionID() . ']';

$category = $argument["cat"] ? $argument["cat"][0] : $_GET["cat"];

if ($category) {
  $pages_in_category = categoryMembers($category);
  print_r($pages_in_category);

  foreach ($pages_in_category as $page) {
    expand($page, $ON);
  }

  exit ("\n Done all " . count($pages_in_category) . " pages in Category:$category. \n");
} else {
  exit ("You must specify a category.  Try appending ?cat=Blah+blah to the URL.");
}
