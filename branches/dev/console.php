#!/usr/bin/php
<?
// $Id$
#$abort_mysql_connection = true; // Whilst there's a problem with login

$account_suffix = '_4'; // Keep this before including expandFns
error_reporting(E_ALL^E_NOTICE);
$slow_mode = 1;
include('expandFns.php');

$bot_exclusion_compliant = TRUE;

$problem_text =  <<<problemtxt

#todo


problemtxt;

$page = new Page();
$page->text =  <<<problemtxt

{{Cite journal 
| doi = 10.1038/ng.736 
| last1 = Argout | first1 = X. 
| last2 = Salse | first2 = J. 
| last3 = Aury | first3 = J. M. 
| last4 = Guiltinan | first4 = M. J. 
| last5 = Droc | first5 = G. 
| last6 = Gouzy | first6 = J. 
| last7 = Allegre | first7 = M. 
| last8 = Chaparro | first8 = C. 
| last9 = Legavre | first9 = T. 
| last10 = Maximova | first10 = S. N. 
| last11 = Abrouk | first11 = M. 
| last12 = Murat | first12 = F. 
| last13 = Fouet | first13 = O. 
| last14 = Poulain | first14 = J. 
| last15 = Ruiz | first15 = M. 
| last16 = Roguet | first16 = Y. 
| last17 = Rodier-Goud | first17 = M. 
| last18 = Barbosa-Neto | first18 = J. F. 
| last19 = Sabot | first19 = F. 
| last20 = Kudrna | first20 = D. 
| last21 = Ammiraju | first21 = J. S. S. 
| last22 = Schuster | first22 = S. C. 
| last23 = Carlson | first23 = J. E. 
| last24 = Sallet | first24 = E. 
| last25 = Schiex | first25 = T. 
| last26 = Dievart | first26 = A. 
| last27 = Kramer | first27 = M. 
| last28 = Gelley | first28 = L. 
| last29 = Shi | first29 = Z. 
| last30 = Bérard | first30 = A. L. 
| title = The genome of Theobroma cacao 
| journal = Nature Genetics 
| volume = 43 
| issue = 2 
| pages = 101–108 
| year = 2010 
| pmid = 21186351 
| displayauthors = 30
}}<noinclude>{{Documentation|Template:cite_doi/subpage}}</noinclude>

 
problemtxt;
$page->expand_text();
die($page->text .  "\n \n \n" . $page->edit_summary() . "\n");

if ($page->get_text_from('User:DOI_bot/Zandbox') && $page->expand_text()) {
  echo "\n # Writing to " . $page->title . ' with edit summary ' . $page->edit_summary() . "\n";
  #print $page->text; die("\n\nbyebye\n");
  while (!$page->write() && $attempts < 2) ++$attempts;
  if ($attempts < 3 ) echo $html_output ?
       " <small><a href=http://en.wikipedia.org/w/index.php?title=" . urlencode($page) . "&action=history>history</a> / "
       . "<a href=http://en.wikipedia.org/w/index.php?title=" . urlencode($page) . "&diff=prev&oldid="
       . getLastRev($page) . ">last edit</a></small></i>\n\n<br>"
       : ".";
  else echo "\n # Failed. \n" . $page->text;
} else {
  echo "\n # " . ($page->text ? 'No changes required.' : 'Blank page') . "\n # # # ";
  updateBacklog($page->title);
}

    
    die("\n# # # \n");
    





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

$slow_mode = ($argument["slow"] || $argument["slowmode"] || $argument["thorough"]) ? true : false;
$account_suffix = '_' . ($argument['user'] ? $argument['user'][0] : '1'); // Keep this before including expandFns
include("object_expandFns.php");
$htmlOutput = false;
$editInitiator = '[Pu' . (revisionID() + 1) . '&beta;]';
define ("START_HOUR", date("H"));

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
	$result = mysql_query ("SELECT /* SLOW_OK */ page FROM citation ORDER BY fast ASC") or die(mysql_error());
	$result = mysql_query("SELECT /* SLOW_OK */ page FROM citation ORDER BY fast ASC") or die (mysql_error());
	$result = mysql_fetch_row($result);
  mysql_close($db);
	return $result[0];
}
$ON = $argument["on"];


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
   

      $slow_mode = true;
    die (expand_text(
            $problem_text, false, false
));
    
die(expand_text("
  More title tampering
{cite journal |author=Fazilleau et al. |title=Follicular helper T cells: lineage and location |journal=Immunity |volume=30 |issue=3 |pages=324–35 |year=2009 |month=March |pmid=19303387 |doi=10.1016/j.immuni.2009.03.003 
|last2=Mark |first2=L |last3=McHeyzer-Williams |first3=LJ |last4=McHeyzer-Williams |first4=MG |pmc=2731675}}</ref>.
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