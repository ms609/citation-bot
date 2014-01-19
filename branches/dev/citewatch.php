#!/usr/bin/php
<?php
// $Id$
$ON = true;
error_reporting(E_ALL^E_NOTICE);

$accountSuffix = '_2'; // Should use account _2. Include this line before expandfunctions
require_once("expandFns.php"); // includes login
require_once("citewatchFns.php");

$editInitiator = '[cw' . revisionID() . ']';
$htmlOutput = false;

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29", " ");
$dotDecode = array("/"  , "["  , "{"  , "}"  , "]"  , "<"  , ">"  , ";"  , "("  , ")"  , "_");

echo "\n Retrieving category members: ";
#$toDo = array_merge(categoryMembers("Pages_with_incomplete_DOI_references"), categoryMembers("Pages_with_incomplete_PMID_references"), categoryMembers("Pages_with_incomplete_PMC_references"), categoryMembers("Pages_with_incomplete_JSTOR_references"));
$toDo = #array_merge(
categoryMembers("Pages_with_incomplete_DOI_references")
#categoryMembers("Pages_with_incomplete_PMID_references"),
#categoryMembers("Pages_with_incomplete_PMC_references")#,
 #categoryMembers("Pages_with_incomplete_JSTOR_references");
;
#
$toDo = array("User:DOI bot/Zandbox");
print_r($toDo);
#shuffle($toDo);
$space = (array_keys($toDo, " "));
if ($space) {
  unset($toDo[$space[0]]);
}
echo count($toDo) . " pages found.";

function getCiteList($page) {
	global $bot;
	$bot->fetch(wikiroot . "title=" . urlencode($page) . "&action=raw");
	$raw = $bot->results;
	preg_match_all ("~\{\{[\s\n]*(?:cite|ref)[ _]doi[\s\n]*\|[\s\n]*([^ \}\|]+)[^ \}]*[\s\n]*(\||\}\})~i", $raw, $doi);
	preg_match_all ("~\{\{[\s\n]*(?:cite|ref)[ _]jstor[\n\s]*\|[\n\s]*(\d+)(?:\|[^ \}]*)?[\n\s]*(\||\}\})~i", $raw, $jstorid);
	preg_match_all ("~\{\{[\s\n]*(?:ref|cite)[ _]pmid[\n\s]*\|[\n\s]*(\d+)(?:\|[^ \}]*)?[\n\s]*(\||\}\})~i", $raw, $pmid);
	preg_match_all ("~\{\{[\s\n]*(?:ref|cite)[ _]pmc[\n\s]*\|[\n\s]*(\d+)(?:\|[^ \}]*)?[\n\s]*(\||\}\})~i", $raw, $pmc);
  $category = "[[Category:Articles citing non-functional identifiers]]";
	if ($raw && !$doi && !$jstorid && !$pmid && !$pmc && !strpos($raw, $category)) {
    global $editInitiator;
    #TODO mark as erroneous:
    #write($page, $raw . "\n$category", "$editInitiator Page contains malformed 'Cite xxx' templates; please fix!");
  }
  return Array($doi[1], $jstorid[1], $pmid[1], $pmc[1]);
}

while ($toDo && (false !== ($article_in_progress = array_pop($toDo))/* pages in list */)) {

  // load citations from article
  print "\n\n** Finding citations in article: $article_in_progress ... ";
  $current_page = new Page();
  $current_page->get_text_from($article_in_progress);
  $current_page->expand_remote_templates();
  die("\ncold die in citewatch.php\n");
  $current_page->write();  # Touch, to update category membership
  print "\n** Completed page; touching...";
  // Touch the current article to update its categories:
  touch_page($article_in_progress);
  print " Done.";
}

print "\n===End===\n\n";?>