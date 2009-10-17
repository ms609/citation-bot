#!/usr/bin/php
<?php
// $Id: $

require_once("expandFns.php"); //Must include first
require_once("DOItools.php");
require_once("wikiFunctions.php");
define("wikiroot", "http://en.wikipedia.org/w/index.php?");
$accountSuffix = '_2';
$editInitiator = '[ax'. revisionID() . ']';
$htmlOutput = false;

$toDo = categoryMembers("Articles with missing Cite arXiv inputs");

function nextPage(){
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