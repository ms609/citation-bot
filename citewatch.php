#!/usr/bin/php
<?php
// $Id$

$accountSuffix = '_2'; // Before expandfunctions
require_once("expandFns.php"); // includes login
#die ("[" . isRedirect("Template:Cite pmc/2749442") . "]");

$editInitiator = '[cw' . revisionID() . ']';
$htmlOutput = false;

echo "\n Retrieving category members: ";
$toDo = array_merge(categoryMembers("Pages_with_incomplete_DOI_references"), categoryMembers("Pages_with_incomplete_PMID_references"), categoryMembers("Pages_with_incomplete_PMC_references"), categoryMembers("Pages_with_incomplete_JSTOR_references"));
#$toDo = array("User:Smith609/Sandbox");
#shuffle($toDo);
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
	preg_match_all ("~\{\{[\s\n]*cite[ _]pmc[\n\s]*\|[\n\s]*(\d+)[\n\s]*\}\}~i", $raw, $pmc);
	return Array($doi[1], $jstorid[1], $pmid[1], $pmc[1]);
}

function nextPage(){
	global $toDo, $doi_todo, $pmid_todo, $pmc_todo, $dotDecode, $dotEncode, $cite_doi_start_code, $article_in_progress, $oDoi; 

  // Get the next PMC from our to-do list
  $oPmc = @array_shift($pmc_todo);
	if ($oPmc) {
    print "\n   > PMC $oPmc: ";
    $pmc_page = "Template:Cite pmc/$oPmc";
    // Is there already a page for this PMC?
    switch (citation_is_redirect ("pmc", $oPmc)) {
      case -1:
        // page does not exist
        $pmc_details = pmArticleDetails($oPmc, "pmc");
        $doi_from_pmc = $pmc_details["doi"]; // DOI is preferable to PMID to avoid double-redirect.
        if (!$doi_from_pmc) {
          $pmid_details = pmArticleDetails($pmc_details["pmid"]);
          $doi_from_pmc = $pmid_details["doi"];
        }
        if ($doi_from_pmc) {
          // redirect to a Cite Doi page, to avoid duplication
          $encoded_doi = str_replace($dotDecode, $dotEncode, $doi_from_pmc);
          print "\n  > Redirecting PMC $oPmc to $encoded_doi";
          print write($pmc_page, "#REDIRECT[[Template:Cite doi/$encoded_doi]]", "Redirecting to DOI citation")
              ? " : Done."
              : " : ERROR\n\n > Write failed!\n";
          $pmid_todo[] = ""; // Skip PMID section below and create this new DOI ASAP
          $doi_todo[] = $doi_from_pmc;


        } else {
          print "No DOI found; using PMID instead";
          $pmid_from_pmc = $pmc_details["pmid"];
          if ($pmid_from_pmc) {
            print "\n  > Redirecting PMC $oPmc to PMID $pmid_from_pmc";
            print write($pmc_page, "#REDIRECT[[Template:Cite pmid/$pmid_from_pmc]]", "Redirecting to PMID citation")
                ? " : Done."
                : " : ERROR\n\n > Write failed!\n";
            $pmid_todo[] = $pmid_from_pmc;


          } else {
            print "Could not find PMID or DOI for this PMC.  \n??????????????????????????????????????????????????";
          }
        }
      case 0:
        // Page exists and is not redirect
        print "Page exists and is not redirect.";
        log_citation("pmc", $oPmc);
        return nextPage();


      case 1:
        // page is a redirect
        $pmc_page_text = getRawWikiText(urlencode($pmc_page));
        // Check that redirect leads to  a cite DOI:
        if (preg_match("~/(10.\d{4}/.*)]]~", str_replace($dotEncode, $dotDecode, $pmc_page_text), $redirect_target_doi)) {
          print "Redirects to ";
          // Check that destination page exists
          if (getArticleId("Template:Cite doi/" . str_replace($dotDecode, $dotEncode, trim($redirect_target_doi[1])))) {
            log_citation("pmc", $oPmc, $redirect_target_doi[1]);
            print $redirect_target_doi[1] . ".";
          } else {
            // Create it if it doesn't
            print "non-existent page. Creating > ";
            $pmid_todo[] = ""; // Skip straight to the DOI, missing the PMID consideration below
            $doi_todo[] = $redirect_target_doi[1];


          }
        } else if (preg_match("~pmid/(\d+)\s*]]~", $pmc_page_text, $redirect_target_pmid)) {
          print "Redirects to ";
          // Check that the destination page exists
          if (getArticleId("Template:Cite pmid/" . $redirect_target_pmid[1])) {
            print "PMID " . $redirect_target_pmid[1] . ".";
          } else {
            // Create the target page
            print "non-existent page; creating > ";
            $pmc_todo[] = $redirect_target_pmid[1];


          }
        } else {
          print "Cannot identify destination of redirect.  \n??????????????????????????????????????????????????????? ";
        }
        break;
      }
    }

  // Get next PMID from our to-do list.
  $oPmid = @array_shift($pmid_todo);
	if ($oPmid) {
		print "\n   > PMID $oPmid: ";
		$pmid_page = "Template:Cite pmid/$oPmid";
    // Is there already a page for this PMID?
		switch (citation_is_redirect("pmid", $oPmid)) {
      case -1:
        // Page has not yet been created for this PMID.
        // Can we retrive a DOI from PubMed?
        $pubmed_result = (pmArticleDetails($oPmid));
        $doi_from_pmid = $pubmed_result["doi"];
        if ($doi_from_pmid) {
          // redirect to a Cite Doi page, to avoid duplication
          $encoded_doi = str_replace($dotDecode, $dotEncode, $doi_from_pmid);
          print "Redirecting PMID $oPmid to $encoded_doi";
          print write($pmid_page, "#REDIRECT[[Template:Cite doi/$encoded_doi]]", "Redirecting to DOI citation")
              ? " : Done."
              : " : ERROR\n\n > Write failed!\n";
          $doi_todo[] = $doi_from_pmid;
        } else {
          print "No DOI found!";
          // No DOI found.  Create a new page with a {cite journal}, then trigger the Citation Bot process on it.
          $cite_doi_start_code = "{{Cite journal\n| pmid = $oPmid\n}}<noinclude>{{template doc|Template:cite_pmid/subpage}}</noinclude>";
          return $pmid_page;
        }
        break;
      case 0:
        log_citation("pmid", $oPmid);
        print "Citation OK.";
  			return nextPage();
      case 1:
        // Check that redirect leads to a cite doi:
        if (preg_match("~/(10.\d{4}/.*)]]~",
              str_replace($dotEncode, $dotDecode, getRawWikiText(urlencode($pmid_page))), $redirect_target_doi)) {
          print "Redirects to ";
          // Check that destination page exists
          if (getArticleId("Template:Cite doi/" . str_replace($dotDecode, $dotEncode, trim($redirect_target_doi[1])))) {
            log_citation("pmid", $oPmid, $redirect_target_doi[1]);
            print $redirect_target_doi[1] . ".";
          } else {
           // Create it if it doesn't
           print "nonexistent page. Creating > ";
           $doi_todo[] = $redirect_target_doi[1];
          }
        }
      break;
    }
  }
  // Pop from the end so we immediately handle the new doi added by the PMID process, if there was one.
  $oDoi = @array_pop($doi_todo);
	if ($oDoi) {
    $doi_citation_exists = doi_citation_exists($oDoi);
    if ($doi_citation_exists) {
      //print "\n   > DOI $oDoi already exists.";
      if ($doi_citation_exists > 1) {
        log_citation("doi", $oDoi);
      }
      print ".";
      return nextPage();
    } else {
      print "\n   > New DOI $oDoi added to queue.\n";
      $cite_doi_start_code = "{{Cite journal\n| doi = $oDoi\n}}<noinclude>{{template doc|Template:cite_doi/subpage}}</noinclude>";
      return "Template:Cite doi/" . str_replace($dotDecode, $dotEncode, $oDoi);
    }
	} else {
		// Next page, please
    $article_in_progress = array_pop($toDo);
		if ($article_in_progress && trim($article_in_progress)) {
			print "\n\n** Next article: $article_in_progress";
			$toCite = getCiteList($article_in_progress);
      $doi_todo = $toCite[0];
      foreach ($toCite[1] as $jid){
        $doi_todo[] = "10.2307/$jid";
      }
      $doi_todo = array_unique($doi_todo);
			$pmid_todo = array_unique($toCite[2]);
			$pmc_todo = array_unique($toCite[3]);
		} elseif ($article_in_progress) {
      print "!";
      return nextPage();
    } else {
      return null;
    }
	}
  // Loaded $article_in_progress; now xxx_todo will all be full again.
  print " D" . count($doi_todo) . "/M" . count($pmid_todo) . "/C" . count($pmc_todo);
	return nextPage();
}

$page = nextPage();
$ON = true;
$editing_cite_doi_template = true;
#print "\n\n";exit;
require_once("expand.php");
print "\n===End===\n\n";