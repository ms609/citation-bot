#!/usr/bin/php
<?
// $Id$

error_reporting(E_ALL^E_NOTICE);
$slowMode=false;
$fastMode=false;
$accountSuffix='_1'; // Keep this before including expandFns
include("expandFns.php");
$htmlOutput = false;
$editInitiator = '[Pu' . revisionID() . ']';
$ON = true; // Override later if necessary
define ("START_HOUR", date("H"));


function nextPage(){
  global $ON, $STOP;
	if (!$ON || $STOP) die ("\n** EXIT: Bot switched off.\n");
  if (date("H") != START_HOUR) die ("\n ** EXIT: It's " . date("H") . " o'clock!\n");
	$db = udbconnect();
	$result = mysql_query ("SELECT page FROM citation ORDER BY fast ASC") or die(mysql_error());
	$result = mysql_query("SELECT page FROM citation ORDER BY fast ASC") or die (mysql_error());
	$result = mysql_fetch_row($result);
  mysql_close($db);
	return $result[0];
}

#$STOP = true;
$ON = false; // Uncomment this line to set the bot onto the Zandbox, switched off.

$page = "User:DOI bot/Zandbox";  // Leave this line as is.  It'll be over-written when the bot is turned on.
if ($ON) {
  echo "\n Fetching first page from backlog ... ";
  $page = nextPage();
  echo " done. ";
}
#$page = " Template:Cite doi/10.1002.2F.28SICI.291097-0290.2819980420.2958:2.2F3.3C121::AID-BIT2.3E3.0.CO.3B2-N";
#$ON = true; // Uncomment this line to test edits in the Zandbox; but remember to break the bot after it touches the page or it'll keep on going!
// The line to swtich between active & sandbox modes is in the comment block above.
#$page = "";
include("expand.php");// i.e. GO!

print "\n Done. \n";
