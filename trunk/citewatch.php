#!/usr/bin/php
<?php
// $Id$

require_once("expandFns.php"); // includes login

$accountSuffix = '_2';
$editInitiator = '[cw' . revisionID() . ']';
$htmlOutput = false;

$toDo = array_merge(categoryMembers("Pages_with_incomplete_DOI_references"), categoryMembers("Pages_with_incomplete_PMID_references"));
$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

function getCiteList($page){
	global $bot;
	$bot->fetch(wikiroot . "title=" . urlencode($page) . "&action=raw");
	$raw = $bot->results;
	preg_match_all ("~\{\{[\s\n]*cite[ _]doi[\s\n]*\|[\s\n]*(10\.[^ \}]+)[\s\n]*\}\}~i", $raw, $doi);
	preg_match_all ("~\{\{[\s\n]*cite[ _]jstor[\n\s]*\|[\n\s]*(\d+)[\n\s]*\}\}~i", $raw, $jstorid);
	preg_match_all ("~\{\{[\s\n]*cite[ _]pmid[\n\s]*\|[\n\s]*(\d+)[\n\s]*\}\}~i", $raw, $pmid);
	return Array($doi[1], $jstorid[1], $pmid[1]);
}

function nextPage(){
	global $toDo, $toDoi, $toPmid, $dotDecode, $dotEncode, $freshcode, $now, $oDoi;
	$freshcode = '';
  $oPmid = @array_shift($toPmid);
	if ($oPmid) {
		print "\n   > PMID $oPmid: ";
		$page = "Template:Cite pmid/$oPmid";
		if (getArticleId($page)) {
			print "Page already exists.";
			return (nextPage());
		} else {
			$pma = (pmArticleDetails($oPmid));
			$getDoi=$pma["doi"];
			if ($getDoi) {
				$encDoi = str_replace($dotDecode, $dotEncode, $getDoi);
				print "Redirecting PMID $oPmid to $encDoi";
				print write($page, "#REDIRECT[[Template:Cite doi/$encDoi]]", "Redirecting to DOI citation")?" : Done.":" : ERROR\n\n > Write failed!\n";
				$toDoi[]=$getDoi;	
			} else {
				$freshcode = "{{Cite journal\n| pmid = $oPmid\n}}<noinclude>{{template doc|Template:cite_pmid/subpage}}</noinclude>";
				print "No DOI found!";
				return $page;
			}
		}
	}
  $oDoi = @array_shift($toDoi);
	if ($oDoi){
			$page = "Template:Cite doi/" . str_replace($dotDecode, $dotEncode, $oDoi);
			if (articleID($page)) {
				print "\n    > DOI $oDoi already exists.";
				return (nextPage());
			} else {
				print "\n > New DOI: $oDoi";
				$freshcode = "{{Cite journal\n| doi = $oDoi\n}}<noinclude>{{template doc|Template:cite_doi/subpage}}</noinclude>";
				return $page;
			}
	} else {
		// Next page, please
    $now = array_pop($toDo);
		if ($now && trim($now)) {
			print "\n\n** Next page: $now";
			$toCite = getCiteList($now);
			$toDoi = $toCite[0];
      foreach ($toCite[1] as $jid){
        $toDoi[] = "10.2307/$jid";
      }
      $toDoi = array_unique($toDoi);
			$toPmid = array_unique($toCite[2]);
		} elseif ($now) {
      return nextPage();
    } else {
      return null;
    }
	}
	return nextPage();
}
$page = nextPage();
$ON = true;
$citedoi = true;
#print "\n\n";exit;
require_once("expand.php");
print "\n===End===\n\n";