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
    
die (expand_text('
* Deleting way too many!
==References==

{{reflist|refs=
<ref name="TosiEtAl1991">
{{Cite journal
| doi = 10.1086/115925
| last = Tosi
| first = M
| coauthors = Greggio, L., Marconi, G., Focardi, P.
| title = Star formation in dwarf irregular galaxies – Sextans B
| journal = [[Astronomical Journal]]
| volume = 102
| pages = 951–974
| date = September 1991
| bibcode = 1991AJ....102..951T}}</ref>

<ref name="MagriniEtAl2002">
{{Cite journal
| last = Magrini
| first = L.
| coauthors = Corradi, R.L.M., Walton, N.A., Zijlstra, A.A., Pollaco, D.L., Walsh, J.R., Perinotto, M., Lennon, D.J., Greimel, R.
| title = The Local Group Census: planetary nebulae in Sextans B
| url = http://arxiv.org/PS_cache/astro-ph/pdf/0202/0202516v1.pdf
| accessdate = 2010-11-28}}</ref>

<ref name="SandageCarlson1985">
{{Cite journal
| doi = 10.1086/113809
| last = Sandage
| first = Allan
| coauthors = Carlson, George
| title = The brightest stars in nearby galaxies. V – Cepheids and the brightest stars in the dwarf galaxy Sextans B compared with those in Sextans A
| bibcode = 1985AJ.....90.1019S
| journal = [[Astronomical Journal]]
| volume = 90
| pages = 1019–1026
| date = July 1985}}</ref>

<ref name="TosiEtAl1991">
{{Cite journal
| doi = 10.1086/115925
| last = Tosi
| first = M
| coauthors = Greggio, L., Marconi, G., Focardi, P.
| title = Star formation in dwarf irregular galaxies – Sextans B
| journal = [[Astronomical Journal]]
| volume = 102
| pages = 951–974
| date = September 1991
| bibcode = 1991AJ....102..951T}}</ref>

<ref name="KniazevEtAl2005">
{{Cite journal
| doi = 10.1086/432931
| last = Kniazev
| first = Alexei Y.
| coauthors = Grebel, Eva K., Pustilnik, Simon A., Pramskij, Alexander G., Zucker, Daniel B.
| title = Spectrophotometry of Sextans A and B: Chemical Abundances of H II Regions and Planetary Nebulae
| journal = [[Astronomical Journal]]
| volume = 130
| issue = 4
| pages = 1558–1573
| date = October 2005
| url = http://iopscience.iop.org/1538-3881/130/4/1558/pdf/204645.web.pdf
| accessdate = 2010-11-28
| bibcode=2005AJ....130.1558K|arxiv = astro-ph/0502562 }}</ref>

<ref name="SharinaEtAl2007">
{{Cite journal
| last = Sharina
| first = M.E.
| coauthors = Puzia, T. H.; Krylatyh, A. S.
| title = A Globular Cluster in Sextans B
| journal = [[Astronomy and Astrophysics]]
| volume = 62
| issue =3
| pages = 209–216
| date = September 2009
| bibcode = 2007AstBu..62..209S|doi = 10.1134/S1990341307030029 }}</ref>

<ref name="vandenbergh1999">
{{Cite journal
| last = van den Bergh
| first = Sidney
| title = Stellar Content of Local Group Galaxies – An Introduction
| year = 1999
| bibcode = 1999IAUS..192....3V}}</ref>

<ref name="vandenBergh2000">{{Cite book |last = van den bergh |first = Sidney|title = The galaxies of the Local Group|publisher = University of Cambridge|year = 2000 |pages = 265|url = http://books.google.com/?id=NfOwG3cyAGIC&pg=PA265 |isbn = 9780521651813}}</ref>

<ref name="AlloinGieren">{{Cite book |last = Alloin |first = Danielle M. |coauthors = Gieren, Wolfgang|title = Stellar candles for the extragalactic distance scale|publisher = University of Cambridge|year = 2000 |pages = 265|url = http://books.google.com.au/books?id=rpR1xfK3yFoC&pg=PA265 |isbn = 9783540201281}}</ref>
}}




    

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