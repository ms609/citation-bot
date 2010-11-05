#!/usr/bin/php
<?php
// $Id$
$ON = true;


$accountSuffix = '_2'; // Before expandfunctions
require_once("expandFns.php"); // includes login
require_once("citewatchFns.php"); // inadvertently removed from wikiFunctions.php in r192

$editInitiator = '[cw' . revisionID() . ']';
$htmlOutput = false;

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29", " ");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")", "_");

require_once("expand_it.php");

echo "\n Retrieving category members: ";
$toDo = array_merge(categoryMembers("Pages_with_incomplete_DOI_references"), categoryMembers("Pages_with_incomplete_PMID_references"), categoryMembers("Pages_with_incomplete_PMC_references"), categoryMembers("Pages_with_incomplete_JSTOR_references"));
#$toDo = array("User:DOI bot/Zandbox");
shuffle($toDo);
echo count($toDo) . " pages found.";

function getCiteList($page) {
	global $bot;
	$bot->fetch(wikiroot . "title=" . urlencode($page) . "&action=raw");
	$raw = $bot->results;
	preg_match_all ("~\{\{[\s\n]*cite[ _]doi[\s\n]*\|[\s\n]*(10\.[^ \}]+)[\s\n]*\}\}~i", $raw, $doi);
	preg_match_all ("~\{\{[\s\n]*cite[ _]jstor[\n\s]*\|[\n\s]*(\d+)[\n\s]*\}\}~i", $raw, $jstorid);
	preg_match_all ("~\{\{[\s\n]*cite[ _]pmid[\n\s]*\|[\n\s]*(\d+)[\n\s]*\}\}~i", $raw, $pmid);
	preg_match_all ("~\{\{[\s\n]*cite[ _]pmc[\n\s]*\|[\n\s]*(\d+)[\n\s]*\}\}~i", $raw, $pmc);
	preg_match_all ("~\{\{[\s\n]*cite[ _]google[ )]book[\n\s]*\|[\n\s]*(\d\w+)[\n\s]*\}\}~i", $raw, $gbook);
	return Array($doi[1], $jstorid[1], $pmid[1], $pmc[1], $gbook[1]);
}

function create_page ($type, $id) {
  print "\n Creating page of type $type with ID $id \n";
  $type = strtolower($type);
  global $ON, $dotDecode, $dotEncode;
  switch ($type) {
    case "doi":
      $encoded_id = str_replace($dotDecode, $dotEncode, $id);
    break;
    default:
      $encoded_id = $id;
  }
  return expand("Template:Cite $type/$encoded_id", $ON, true,
                  "{{Cite journal\n| $type = $id\n}}<noinclude>{{Documentation|Template:cite_$type/subpage}}</noinclude>");
}

while ($toDo && (false !== ($article_in_progress = array_pop($toDo))/* pages in list */)) {

  // load citations from article
  if ($article_in_progress && trim($article_in_progress)) {
    print "\n\n** Finding citations in article: $article_in_progress";
    $toCite = getCiteList($article_in_progress);
    $doi_todo = $toCite[0];
    foreach ($toCite[1] as $jid){
      $doi_todo[] = "10.2307/$jid";
    }
    $doi_todo = array_unique($doi_todo);
    $pmid_todo = array_unique($toCite[2]);
    $pmc_todo = array_unique($toCite[3]);
  } elseif ($article_in_progress) {
    print_r($toDo);
    print "Null article: [$article_in_progress]";
    break;
  }

  // Get the next PMC from our to-do list
  while ($pmc_todo && (false !== ($oPmc = array_shift($pmc_todo)))) {
     print "\n   > PMC $oPmc: ";
    $pmc_page = "Template:Cite pmc/$oPmc";
    // Is there already a page for this PMC?
    switch (citation_is_redirect ("pmc", $oPmc)) {
      case -1:
        // page does not exist
        $pmc_details = pmArticleDetails($oPmc, "pmc");
        $doi_from_pmc = $pmc_details["doi"]; // DOI is preferable to PMID to avoid double-redirect.
        $pmid_from_pmc = $pmc_details["pmid"];
        if (!$doi_from_pmc) {
          $pmid_details = pmArticleDetails($pmc_details["pmid"]);
          $doi_from_pmc = $pmid_details["doi"];
          $pmid_from_pmc = $pmid_details["pmid"];
        }
        $pmid_page = "Template:Cite pmid/$pmid_from_pmc";
        if ($doi_from_pmc) {
          // redirect to a Cite Doi page, to avoid duplication
          $encoded_doi = str_replace($dotDecode, $dotEncode, $doi_from_pmc);
          print "\n  > Creating page at Template:Cite doi/$encoded_doi...";
          if (create_page("doi", $doi_from_pmc)) {
            print "\n  > Now redirecting PMC $oPmc to $encoded_doi";
            print write($pmc_page, "#REDIRECT[[Template:Cite doi/$encoded_doi]]", "Redirecting to DOI citation")
                ? " : Done."
                : " : ERROR\n\n > Write failed!\n";
            if ($pmid_from_pmc && !getArticleId($pmid_page)) {
              print "\n  > Now redirecting PMID $pmid_from_pmc to $encoded_doi";
              print write($pmid_page, "#REDIRECT[[Template:Cite doi/$encoded_doi]]", "Redirecting to DOI citation")
                  ? " : Done."
                  : " : ERROR\n\n > Write failed!\n";
            }
          }
        } else {
          print "No DOI found; using PMID instead";
          if ($pmid_from_pmc && create_page("pmid", $pmid_from_pmc)) {
            print "\n  > Redirecting PMC $oPmc to PMID $pmid_from_pmc";
            print write($pmc_page, "#REDIRECT[[Template:Cite pmid/$pmid_from_pmc]]", "Redirecting to PMID citation")
                ? " : Done."
                : " : ERROR\n\n > Write failed!\n";
          } else {
            print "Could not find PMID or DOI for this PMC.  \n??????????????????????????????????????????????????";
          }
        }
      break;
      case 0:
        // Page exists and is not redirect
        print "Page exists and is not redirect.";
        log_citation("pmc", $oPmc);
      break;
      case 1:
        print "On record as a redirect";
      break;
      case 2:
        // page is a redirect
        $pmc_page_text = getRawWikiText($pmc_page);
        // Check that redirect leads to  a cite DOI:
        if (preg_match("~/(10.\d{4}/.*)]]~", str_replace($dotEncode, $dotDecode, $pmc_page_text), $redirect_target_doi)) {
          print "Redirects to ";
          // Check that destination page exists
          if (getArticleId("Template:Cite doi/" . str_replace($dotDecode, $dotEncode, trim($redirect_target_doi[1])))) {
         -   log_citation("pmc", $oPmc, $redirect_target_doi[1]);
            print $redirect_target_doi[1] . ".";
          } else {
            // Create it if it doesn't
            print "non-existent page. Creating > ";
            create_page("doi", $redirect_target_doi[1]);
          }
        } else if (preg_match("~pmid/(\d+)\s*]]~", $pmc_page_text, $redirect_target_pmid)) {
          print "Redirects to ";
          // Check that the destination page exists
          if (getArticleId("Template:Cite pmid/" . $redirect_target_pmid[1])) {
            print "PMID " . $redirect_target_pmid[1] . ".";
          } else {
            // Create the target page
            print "non-existent page; creating > ";
            create_page("pmid", $redirect_target_pmid[1]);
          }
        } else {
          print "Cannot identify destination of redirect.  \n??????????????????????????????????????????????????????? ";
        }
      break;
    }
  }

  while ($pmid_todo && (false !== ($oPmid = array_shift($pmid_todo)))) {
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
          if (create_page("doi", $doi_from_pmid)) {
            print "Redirecting PMID $oPmid to $encoded_doi";
            print write($pmid_page, "#REDIRECT[[Template:Cite doi/$encoded_doi]]", "Redirecting to DOI citation")
                ? " : Done."
                : " : ERROR\n\n > Write failed!\n";
          } else {
            print "\n Could not create doi target page.  Hmmph.";
          }
        } else {
          print "No DOI found!";
          // No DOI found.  Create a new page with a {cite journal}, then trigger the Citation Bot process on it.
          create_page("pmid", $oPmid);
        }
      break;
      case 0:
        log_citation("pmid", $oPmid);
        print "Citation OK.";
      break;
      case 1:
        print "On record as a redirect";
      break;
      case 2:
        // Check that redirect leads to a cite doi:
        if (preg_match("~/(10.\d{4}/.*)]]~",
              str_replace($dotEncode, $dotDecode, getRawWikiText($pmid_page)), $redirect_target_doi)) {
          print "Redirects to ";
          // Check that destination page exists
          if (getArticleId("Template:Cite doi/" . str_replace($dotDecode, $dotEncode, trim($redirect_target_doi[1])))) {
            log_citation("pmid", $oPmid, $redirect_target_doi[1]);
            print $redirect_target_doi[1] . ".";
          } else {
           // Create it if it doesn't
           print "nonexistent page. Creating > ";
           create_page("doi", $redirect_target_doi[1]);
          }
        } else {
          die ("$pmid_page Redirects to " . getRawWikiText($pmid_page));
        }
      // end of case 2.
    }
  }
  
  while ($doi_todo && (false !== ($oDoi = @array_pop($doi_todo)))) {
    $doi_citation_exists = doi_citation_exists($oDoi);
    if ($doi_citation_exists) {
      //print "\n   > DOI $oDoi already exists.";
      if ($doi_citation_exists > 1) {
        log_citation("doi", $oDoi);
      }
      print ".";
    } else {
      print "\n   > Creating new page at $oDoi \n";
      create_page("doi", $oDoi);
    }
  }

  // Now that we've created all the necessary templates for this page, let's move on to the next in our to-do list.

  print "That's this one done; let's touch it.";
  // Touch the current article to update its categories:
  touch($article_in_progress);
}

print "\n===End===\n\n";?>