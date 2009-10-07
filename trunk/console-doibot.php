<?
// $Id$

error_reporting(E_ALL^E_NOTICE);
$slowMode=false;
$fastMode=false;
$accountSuffix='_1';
include("expandFns.php");
$htmlOutput = false;
$editInitiator = '[Pu' . revisionID() . ']';
$ON = true; // Override later if necessary

function updateQueue() {
  print "** Updating backlog...\nSeeing what links to 'Cite Journal'...";
  $cite_journal = whatTranscludes2("Cite_journal", 0);
  print "\nand 'Citation'... ";
  $citation =  whatTranscludes2("Citation", 0);
  $pages = array_merge($cite_journal["title"], $citation["title"]);
  $ids = array_merge($cite_journal["id"], $citation["id"]);
  print "and writing to file...";
  $count = count($pages);
  for ($i=0; $i<$count; $i++){
    $result = mysql_query("SELECT page FROM citation WHERE id = {$ids[$i]}") or die (mysql_error());
    if (!mysql_fetch_row($result)) {
      mysql_query("INSERT INTO citation (id, page) VALUES ('{$ids[$i]}', '". addslashes($pages[$i]) ."')" )or die(mysql_error());
      print "<br>{$pages[$i]} @ {$ids[$i]}";
    } else print ".";
  }
  print "\ndone.";
}

function nextPage(){
  global $ON, $STOP;
	if (!$ON || $STOP) die ("\n** EXIT: Bot switched off.\n");
	global $db;
	$result = mysql_query ("SELECT page FROM citation ORDER BY fast ASC") or die(mysql_error());
	$result = mysql_query("SELECT page FROM citation ORDER BY fast ASC") or die (mysql_error());
	$result = mysql_fetch_row($result);
	return $result[0];
}
#$STOP = true;
$ON = false;
$page = "User:DOI bot/Zandbox";
if ($ON) $page = nextPage();
#$page = "";
include("expand.php"); // i.e. GO!

