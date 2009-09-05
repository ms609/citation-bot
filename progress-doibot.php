<?
// $Id: $

include("expandFns.php");
if (preg_match("~\d{8}~", $_GET['date'], $date)) $date = $date[0]; else $date = 0;

// Return count of how many left to do
$result = mysql_query ("SELECT page FROM citation WHERE fast > $date") or die(mysql_error());
print "\nStatus: done " . mysql_num_rows($result).  " pages " . (($date>0)?" since $date; ":" in total; ");
$result = mysql_query ("SELECT page FROM citation") or die(mysql_error());
print mysql_num_rows($result)." in database.\n\n";