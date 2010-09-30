#!/usr/bin/php
<?php
// $Id: $

$accountSuffix = '_2'; // Before expandfunctions, which contains login
require_once("expandFns.php"); //Must include first
require_once("DOItools.php");
require_once("wikiFunctions.php");
define("wikiroot", "http://en.wikipedia.org/w/index.php?");
$editInitiator = '[ax'. revisionID() . ']';
$htmlOutput = false;
echo 1;
$toDo = categoryMembers("Articles with missing Cite arXiv inputs");
echo 2;
function nextPage(){
	global $toDo; 
  $now = array_pop($toDo);
	if ($now) {
		print "\n** Next page: $now\n"; return $now;
	} else return null;
}
echo 3;
$page = nextPage();
echo 4;
$ON = true;
require_once("expand.php");
print "\n===End===\n\n";