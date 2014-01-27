#!/usr/bin/php
<?
// $Id$
error_reporting(E_ALL^E_NOTICE);

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
$accountSuffix='_4'; // Whilst testing
include("expandFns.php");
$htmlOutput = false;
$edit_initiator = '[Cat' . revisionID() . '-dev]';

$category = $argument["cat"] ? $argument["cat"][0] : $_GET["cat"];
if (!$category) $category = "Pages_using_citations_with_old-style_implicit_et_al.";
if ($category) {
  $pages_in_category = categoryMembers($category);
  #print_r($pages_in_category);
  shuffle($pages_in_category);
  $page = new Page();
  $pages_in_category = array('User:DOI bot/Zandbox');
  foreach ($pages_in_category as $page_title) {
    if ($page->get_text_from($page_title) && $page->expand_text()) {
      echo "\n # Writing to " . $page->title . '... ';
      die($page->text);
      while (!$page->write() && $attempts < 2) ++$attempts;
      #print $page->text; die("\n\n Written to {$page->title}. \nbyebye\n");
      if ($attempts < 3 ) echo $html_output ?
           " <small><a href=http://en.wikipedia.org/w/index.php?title=" . urlencode($page) . "&action=history>history</a> / "
           . "<a href=http://en.wikipedia.org/w/index.php?title=" . urlencode($page) . "&diff=prev&oldid="
           . getLastRev($page) . ">last edit</a></small></i>\n\n<br>"
           : ".";
      else echo "\n # Failed. \n" . $page->text;
    } else {
      echo "\n # " . ($page->text ? 'No changes required.' : 'Blank page') . "\n # # # ";
      updateBacklog($page->title);
    }
  }

  exit ("\n Done all " . count($pages_in_category) . " pages in Category:$category. \n");
} else {
  exit ("You must specify a category.  Try appending ?cat=Blah+blah to the URL.");
}
