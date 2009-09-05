#!/usr/bin/php
<?php
$accountSuffix = '_2';
$editInitiator = '[ax]';
require_once("/home/verisimilus/public_html/Bot/DOI_bot/expandFns.php"); //Must include first
require_once("/home/verisimilus/public_html/DOItools.php");
require_once("/home/verisimilus/public_html/Bot/wikiFunctions.php");
define("wikiroot", "http://en.wikipedia.org/w/index.php?");

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
require_once("/home/verisimilus/public_html/Bot/DOI_bot/expand.php");
print "\n===End===\n\n";