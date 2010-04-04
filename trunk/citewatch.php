#!/usr/bin/php
<?php
// $Id$

$accountSuffix = '_2'; // Before expandfunctions
require_once("expandFns.php"); // includes login

$editInitiator = '[cw' . revisionID() . ']';
$htmlOutput = false;

echo "Retrieving category members: ";
$toDo = array_merge(categoryMembers("Pages_with_incomplete_DOI_references"), categoryMembers("Pages_with_incomplete_PMID_references"), categoryMembers("Pages_with_incomplete_PMC_references"));
echo count($toDo);
$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29", " ");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")", "_");

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
	global $toDo, $toDoi, $toPmid, $dotDecode, $dotEncode, $cite_doi_start_code, $article_in_progress, $oDoi, $ii;
  $ii++;
  if ($cite_doi_start_code) print "\n ### $ii -- $cite_doi_start_code ";
  #if ($ii > 10) die ("Fresh code: \n " . $cite_doi_start_code);
	$cite_doi_start_code = '';
  // Get next PMID from our to-do list
  $oPmid = @array_shift($toPmid);
	if ($oPmid) {
		print "\n   > PMID $oPmid: ";
		$pmid_page = "Template:Cite pmid/$oPmid";
    // Is there already a page for this PMID?
		if (getArticleId($pmid_page)) {
      if (isRedirect($pmid_page)) {
        // Check that redirect leads to a cite doi:
        if (preg_match("~/(10.\d{4}/.*)]]~", str_replace($dotEncode, $dotDecode, getRawWikiText(urlencode($pmid_page))), $reDoi)) {
          print "Redirects to ";
          // Check that destination page exists
          if (getArticleId("Template:Cite doi/" . str_replace($dotDecode, $dotEncode, trim($reDoi[1])))) {
            print $reDoi[1] . ".";
          } else {
             // Create it if it doesn't
             print "nonexistant page. Creating > ";
             $toDoi[] = $reDoi[1];
          }
        }
      } else {
        print "Page exists.";
  			return (nextPage());
      }
		} else {
      // Page has not yet been created for this PMID.
      // Can we retriev a DOI from PubMed?
			$pma = (pmArticleDetails($oPmid));
			$getDoi = $pma["doi"];
			if ($getDoi) {
        // redirect to a Cite Doi page, to avoid duplication
				$encDoi = str_replace($dotDecode, $dotEncode, $getDoi);
				print "Redirecting PMID $oPmid to $encDoi";
				print write($pmid_page, "#REDIRECT[[Template:Cite doi/$encDoi]]", "Redirecting to DOI citation")
            ? " : Done."
            : " : ERROR\n\n > Write failed!\n";
				$toDoi[] = $getDoi;
			} else {
				print "No DOI found!";
        // No DOI found.  Create a new page with a {cite journal}, then trigger the Citation Bot process on it.
				$cite_doi_start_code = "{{Cite journal\n| pmid = $oPmid\n}}<noinclude>{{template doc|Template:cite_pmid/subpage}}</noinclude>";
				return $pmid_page;
			}
		}
	}
  // Pop from the end so we immediately handle the new doi added by the PMID process, if there was one.
  $oDoi = @array_pop($toDoi);
	if ($oDoi){
			$doi_page = "Template:Cite doi/" . str_replace($dotDecode, $dotEncode, $oDoi);
			if (articleID($doi_page)) {
				print "\n    > DOI $oDoi already exists.";
				return (nextPage());
			} else {
				print "\n > New DOI: $oDoi";
				$cite_doi_start_code = "{{Cite journal\n| doi = $oDoi\n}}<noinclude>{{template doc|Template:cite_doi/subpage}}</noinclude>";
				return $doi_page;
			}
	} else {
		// Next page, please
    $article_in_progress = array_pop($toDo);
		if ($article_in_progress && trim($article_in_progress)) {
			print "\n\n** Next article: $article_in_progress";
			$toCite = getCiteList($article_in_progress);
			$toDoi = $toCite[0];
      foreach ($toCite[1] as $jid){
        $toDoi[] = "10.2307/$jid";
      }
      $toDoi = array_unique($toDoi);
			$toPmid = array_unique($toCite[2]);
		} elseif ($article_in_progress) {
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