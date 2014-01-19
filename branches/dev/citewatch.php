#!/usr/bin/php
<?php
// $Id$
$ON = true;
error_reporting(E_ALL^E_NOTICE);

$accountSuffix = '_2'; // Should use account _2. Include this line before expandfunctions
require_once("expandFns.php"); // includes login
require_once("citewatchFns.php");

$edit_initiator = '[cw' . revisionID() . ']';
$htmlOutput = false;

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29", " ");
$dotDecode = array("/"  , "["  , "{"  , "}"  , "]"  , "<"  , ">"  , ";"  , "("  , ")"  , "_");

echo "\n Retrieving category members: ";
$toDo = array_unique(array_merge(
categoryMembers("Pages_with_incomplete_DOI_references"),
categoryMembers("Pages_with_incomplete_PMID_references"),
categoryMembers("Pages_with_incomplete_PMC_references"),
categoryMembers("Pages_with_incomplete_JSTOR_references")));
#
#$toDo = array("User:DOI bot/Zandbox");
#$toDo = array("Xymmer");
print_r($toDo);
shuffle($toDo);
$space = (array_keys($toDo, " "));
if ($space) unset($toDo[$space[0]]);
echo count($toDo) . " pages found.";
while ($toDo && (false !== ($article_in_progress = array_pop($toDo))/* pages in list */)) {
  // load citations from article
  echo "\n\n** Finding citations in article: $article_in_progress ... ";
  $current_page = new Page();
  $current_page->get_text_from($article_in_progress);
  $current_page->expand_remote_templates();
  echo "\n** Completed page; touching...";
  #print $current_page->text;
  #die("\nSTOP\n");
  $current_page->write(); # Touch, to update category membership; page may have been updated to fix malformed DOIs
  echo " $article_in_progress complete.";
}
print "\n===End===\n\n";?>