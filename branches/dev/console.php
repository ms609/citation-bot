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
$slowMode = $argument["slow"] || $argument["slowmode"] || $argument["thorough"];
$accountSuffix = '_1'; // Keep this before including expandFns
include("expandFns.php");
$htmlOutput = false;
$editInitiator = '[Pu' . revisionID() . '&beta;]';
define ("START_HOUR", date("H"));


/*

print "\n";

print preg_replace("~(\p{L})\p{L}*\.? ?~", "$1.", "Amélie SMth");

die("\n");

 */

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


die (expand_text("{{ cite journal | journal=Annual Review of Fluid Mechanics
  | volume=23 | pages=159–177 | year=1991 | doi=10.1146/annurev.fl.23.010191.001111
  | title=Exact solutions of the steady-state Navier-Stokes equations | first=C. Y. | last=Wang }}

") );

die (expand_text("*{{cite journal | journal=Annual Review of Fluid Mechanics | volume=20 | issue=1 | pages=225–256
  | year=1988 | doi=10.1146/annurev.fl.20.010188.001301
  | title=Hamiltonian Fluid Mechanics | author=R. Salmon}}

") );

#die (expand_text("{{Cite journal | doi = 10.1126/science.284.5423.2129. }}") );
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
  }
  #$page = " Template:Cite doi/10.1002.2F.28SICI.291097-0290.2819980420.2958:2.2F3.3C121::AID-BIT2.3E3.0.CO.3B2-N";
  #$ON = true; // Uncomment this line to test edits in the Zandbox; but remember to break the bot after it touches the page or it'll keep on going!
  // The line to swtich between active & sandbox modes is in the comment block above.
  #$page = "";
  #$slowMode = true;

  //
  //include("expand.php");// i.e. GO!

  /*$start_code = getRawWikiText($page, false, false);*/
  $slow_mode = true;

  print "\n";
  //
  /*
  while ($page) {
    $page = nextPage($page);
    $end_text = expand($page, $ON);
  }
  *///name_references(combine_duplicate_references(ref_templates(ref_templates(ref_templates(ref_templates($start_code, "doi"), "pmid"), "jstor"), "pmc"))),
  //$end_text = ref_templates($end_text, "pmid");
  //print "\n" . $end_text;
  //write($page, $end_text, $editInitiator . "Re task #6 : Trial edit");
}
die ("\n Done. \n");