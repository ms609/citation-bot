#!/usr/bin/php
<?      
// $Id$
#$abort_mysql_connection = true; // Whilst there's a problem with login

foreach ($argv as $arg) {
  if (substr($arg, 0, 2) == "--") {
    $argument[substr($arg, 2)] = 1;
  } elseif (substr($arg, 0, 1) == "-") {
    $oArg = substr($arg, 1);
  } else {
    switch ($oArg) {
      case "P": case "A": case "T":
        $argument["pages"][] = $arg;
        break;
      default:
      $argument[$oArg][] = $arg;
    }
  }
}

error_reporting(E_ALL^E_NOTICE);
$slow_mode = ($argument["slow"] || $argument["slowmode"] || $argument["thorough"]) ? true : false;
$accountSuffix = '_' . ($argument['user'] ? $argument['user'][0] : '1'); // Keep this before including expandFns
include("expandFns.php");
$htmlOutput = false;
$editInitiator = '[Pu' . (revisionID() + 1) . '&beta;]';
define ("START_HOUR", date("H"));
#die (findISBN(""));

function nextPage($page){
  // touch last page
  if ($page) {
    touch_page($page);
  }

  // Get next page
  global $ON, $STOP;
	if (!$ON || $STOP) die ("\n** EXIT: Bot switched off.\n");
  if (date("H") != START_HOUR) die ("\n ** EXIT: It's " . date("H") . " o'clock!\n");
	$db = udbconnect("yarrow");
	$result = mysql_query ("SELECT page FROM citation ORDER BY fast ASC") or die(mysql_error());
	$result = mysql_query("SELECT page FROM citation ORDER BY fast ASC") or die (mysql_error());
	$result = mysql_fetch_row($result);
  mysql_close($db);
	return $result[0];
}
$ON = $argument["on"];
###########
/*/
/*
foreach ($argument["pages"] as $page) {
  $input[] = array("{{automatic taxobox$paras}}", $page);
  $input[] = array("{{automatic taxobox/sandbox$paras}}", $page);
};
//$paras = "|fossil range = Cretaceous";

foreach ($input as $code) {
  $output = explode("NewPP limit report", parse_wikitext($code[0], $code[1]));
  print "$code[0] @ $code[1] \n " . $output[1];
}
// Fossil range adds about 10,000 / 30,000 /. 10,000 to counts if it's set. "Cretaceous";
// The fossil range template itself adds 8311 / 11600 / 1552

die();
*/
###########


if ($argument["pages"]) {
  foreach ($argument["pages"] as $page) {
    expand($page, $ON);
  }
} elseif ($argument["sandbox"] || $argument["sand"]) {
  expand("User:DOI bot/Zandbox", $ON);
} else {
   if ($ON) {
    echo "\n Fetching first page from backlog ... ";
    $page = nextPage($page);
    echo " done. ";
  } else {
   
    
    die (expand_text("
      
{{cite journal | volume = test | pages = C-12}}
{{cite journal | volume = test | pages = 9-12}}
{{cite journal | volume = test | pages = 9a-12b}}
{{cite journal | volume = test | pages = e9-e12b}}

"));
// die (expand_text("JSTOR lookup failure: {{Cite journal | jstor = 1303024}}")); - JSTOR API down
    
die(expand_text("
  More title tampering
{cite journal |author=Fazilleau et al. |title=Follicular helper T cells: lineage and location |journal=Immunity |volume=30 |issue=3 |pages=324–35 |year=2009 |month=March |pmid=19303387 |doi=10.1016/j.immuni.2009.03.003 
|last2=Mark |first2=L |last3=McHeyzer-Williams |first3=LJ |last4=McHeyzer-Williams |first4=MG |pmc=2731675}}</ref>.
"));

die(expand_text("

  Does not expand.  This appears to be a (long-term) problem with the JSTOR API.
{{Cite journal | jstor = 4494763}}


    

"));
    
die (expand_text('

Reference renaming:

{{ref doi|10.1016/S0016-6995(97)80056-3}}

.<ref name="Wilby1997">{{cite doi|10.1016/S0016-6995(97)80056-3 }}</ref>


'));
    
/*/
// For version 3:
die (expand_text("

{{cite journal | author = Ridzon R, Gallagher K, Ciesielski C ''et al.'' | year = 1997 | title = Simultaneous transmission of human immunodeficiency virus and hepatitis C virus from a needle-stick injury | url = | journal = N Engl J Med | volume = 336 | issue = | pages = 919–22 }}. (full stop to innards)<
<ref>http://www.ncbi.nlm.nih.gov/pubmed/15361495</ref>
", false));
/**/
  }
  /*$start_code = getRawWikiText($page, false, false);*/
  $slow_mode = true;

  print "\n";
  //
  
  while ($page) {
    $page = nextPage($page);
    $end_text = expand($page, $ON);
  }
  //write($page, $end_text, $editInitiator . "Re task #6 : Trial edit");
}
die ("\n Done. \n");