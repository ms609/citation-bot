<?php
// $Id: $

include("expandFns.php");
$db = udbconnect("yarrow");

if (preg_match("~\d{8}~", $_GET['date'], $date)) $date = $date[0]; else $date = date('Ymd', strtotime('-3 month'));

// Return count of how many left to do
$result = mysql_query ("SELECT page FROM citation WHERE fast > $date") or die(mysql_error());
print "\nStatus: done " . mysql_num_rows($result).  " pages since $date; ";
$result = mysql_query ("SELECT * FROM citation ORDER BY fast ASC") or die(mysql_error());
print mysql_num_rows($result)." in database.\nOldest page: ";
$row =  mysql_fetch_array($result, MYSQL_ASSOC);
print $row['page'] . " - checked on " . $row['fast'] . " by revision " . $row['revision'] . "\n";