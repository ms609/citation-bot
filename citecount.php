<?

error_reporting(E_ALL^E_NOTICE);
$slowMode=false;
$fastMode=false;
$editInitiator = '[4]';
$accountSuffix = '_4';

$ON = true;
#$ON=false;
$linkto2 = '';
include("expandFns.php");

function nextPage(){
	#return 'User:DOI bot/Zandbox';
	global $db;
	$result = mysql_query ("SELECT page FROM citation ORDER BY fast ASC") or die(mysql_error());
	// Increment i< for # erroneous pages here.
	for ($i=0; $i<5; $i++) $chaff = mysql_fetch_row($result);
	$result = mysql_fetch_row($result);
	return $result[0];
}

$page = nextPage();

include("expand_task4.php");

print "\n\n=== End ===\n\n";