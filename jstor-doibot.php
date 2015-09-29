<?php

error_reporting(E_ALL^E_NOTICE);
$slowMode=false;
$fastMode=false;
include("expandFns.php");


$accountSuffix='_1';

$ON = true;
//$ON=false;


function nextPage(){
	//return 'User:DOI bot/Zandbox';
	//return 'Template:Cite_doi/10.1016.2Fj.coldregions.2004.12.002';
	global $db;
	$result = mysql_query ("SELECT page FROM citation ORDER BY fast ASC") or die(mysql_error());
	if(rand(1, 5000) == 100000)	{
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
	$result = mysql_query("SELECT page FROM citation ORDER BY fast ASC") or die (mysql_error());
	// Increment i< for # erroneous pages here.
	for ($i=0; $i<7; $i++) $chaff = mysql_fetch_row($result);
	$result = mysql_fetch_row($result);
	return $result[0];
}


//$page = nextPage();
$page = 'Belemnoidea';
$id = getArticleID($page);
print getRawWikiText($id);

exit;
include("expand.php");
