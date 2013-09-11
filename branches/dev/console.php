#!/usr/bin/php
<?
// $Id$
#$abort_mysql_connection = true; // Whilst there's a problem with login

ini_set('display_errors', '1');
include('object_expandFns.php');

$bot_exclusion_compliant = TRUE;

$problem_text =  <<<problemtxt


{{tempalte}}}
{{cite doi|: 10.1111/ahgao ,}}
{{cite doi|doi: 10.1111/ahgao }}
{{template | para1 = 8 | pages = 1&mdash;2}}
{{nested | te = {{template | gah = 8 = two-equals}} | <!--comment | comment --> }}
<ref name="SGP review" group="tester">
{{cite journal 
| class = test }}
</ref>
<ref name="SGP review" />
<ref name="SGP review" group='Test gr"oup'/>

==References==
{{Reflist|2|refs=
<ref name="gamefanPS">Ref1</ref>
<ref name="gamespot">Ref2012</ref>
}}


problemtxt;

$problem_text = <<<testcase

{nobots}}
{{Citation | jorunal=J Heart Lung Transplant|year= 1992|pp=375-6|volume=11}}
{{Cite mate|pal=Pay}} | {{Cite boy|title=Cit2}} {{harv}}
testcase;

/* Outline 
*  PLAINTEXT 
*  - Comments
  *  - Templates
  []  o Templates
  /  + Templates
  /  - Refs
  /  - References
  /  o References
  /  + References
/  + Comments*/

    print "begin";
    $text = $problem_text;
    $comments = extract_object($text, Comment);
      $text = $comments[0]; $comments = $comments[1];
    if ($bot_exclusion_compliant && !allowBots($text)) {
      echo "\n ! Page marked with {{nobots}} template.  Skipping.";
      die('\n#Todo: NEXT PAGE!');
    }
    $templates = extract_object($text, Template);
      $text = $templates[0]; $templates = $templates[1];
      $start_templates = $templates;
      $citation_templates = 0; $cite_templates = 0;
      foreach ($templates as $template) {
        if ($template->wikiname() == 'citation') $citation_templates++;
        elseif (preg_match("~[cC]ite[ _]\w+~", $template->wikiname())) $cite_templates++;
        elseif (stripos($template->wikiname(), 'harv') === 0) $harvard_templates++;
      }
      $citation_template_dominant = $citation_templates > $cite_templates;
      echo "\n * $citation_templates {{Citation}} templates and $cite_templates {{Cite XXX}} templates identified.  Using dominant template {{" . ($citation_template_dominant?'Citation':'Cite XXX') . '}}.';
      for ($i = 0; $i < count($templates); $i++) {
        $templates[$i]->process();
        $templates[$i]->cite_doi_format();
        $citation_template_dominant ? $templates[$i]->cite2citation() : $templates[$i]->citation2cite($harvard_templates);
      }

    $text = replace_object($text, $templates);
    die("\n$text\n");  
    
    $short_refs = extract_object($text, Short_Reference);
      $text = $short_refs[0]; $short_refs = $short_refs[1];
    $long_refs = extract_object($text, Long_Reference);
      $text = $long_refs[0]; $long_refs = $long_refs[1];
    
    $text = replace_object($text, $long_refs);
    $text = replace_object($text, $short_refs);
    $text = replace_object($text, $comments);
    print $text;

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

error_reporting(E_ALL^E_NOTICE);
$slow_mode = ($argument["slow"] || $argument["slowmode"] || $argument["thorough"]) ? true : false;
$accountSuffix = '_' . ($argument['user'] ? $argument['user'][0] : '1'); // Keep this before including expandFns
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