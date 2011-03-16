#!/usr/bin/php
<?php
// $Id: $

$accountSuffix = '_2'; // Before expandfunctions, which contains login
require_once("expandFns.php"); //Must include first
define("wikiroot", "http://en.wikipedia.org/w/index.php?");
$editInitiator = '[ax'. revisionID() . ']';
$htmlOutput = false;
$toDo = categoryMembers("Articles with missing Cite arXiv inputs");
function nextPage(){
  // Get next page
	global $toDo; 
  $now = array_pop($toDo);
	if ($now) {
		print "\n** Next page: $now\n"; return $now;
	} else return null;
}
$page = nextPage();
$ON = true;
require_once("expand.php");
print "\n===End===\n\n";