#!/usr/bin/php
<?php
// $Id$
$ON = true;

$accountSuffix = '_2'; // Should use account _2. Include this line before expandfunctions
require_once("expandFns.php"); // includes login
require_once("citewatchFns.php");

$editInitiator = '[cw' . revisionID() . ']';
$htmlOutput = false;

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29", " ");
$dotDecode = array("/"  , "["  , "{"  , "}"  , "]"  , "<"  , ">"  , ";"  , "("  , ")"  , "_");

echo "\n Retrieving category members: ";
#$toDo = array_merge(categoryMembers("Pages_with_incomplete_DOI_references"), categoryMembers("Pages_with_incomplete_PMID_references"), categoryMembers("Pages_with_incomplete_PMC_references"), categoryMembers("Pages_with_incomplete_JSTOR_references"));
$toDo = array("User:DOI bot/Zandbox");

shuffle($toDo);
$space = (array_keys($toDo, " "));
if ($space) {
  unset($toDo[$space[0]]);
}
echo count($toDo) . " pages found.";

function getCiteList($page) {
	global $bot;
	$bot->fetch(wikiroot . "title=" . urlencode($page) . "&action=raw");
	$raw = $bot->results;
	preg_match_all ("~\{\{[\s\n]*(?:cite|ref)[ _]doi[\s\n]*\|[\s\n]*([^ \}\|]+)[^ \}]*[\s\n]*(\||\}\})~i", $raw, $doi);
	preg_match_all ("~\{\{[\s\n]*(?:cite|ref)[ _]jstor[\n\s]*\|[\n\s]*(\d+)(?:\|[^ \}]*)?[\n\s]*(\||\}\})~i", $raw, $jstorid);
	preg_match_all ("~\{\{[\s\n]*(?:ref|cite)[ _]pmid[\n\s]*\|[\n\s]*(\d+)(?:\|[^ \}]*)?[\n\s]*(\||\}\})~i", $raw, $pmid);
	preg_match_all ("~\{\{[\s\n]*(?:ref|cite)[ _]pmc[\n\s]*\|[\n\s]*(\d+)(?:\|[^ \}]*)?[\n\s]*(\||\}\})~i", $raw, $pmc);
  $category = "[[Category:Articles citing non-functional identifiers]]";
	if ($raw && !$doi && !$jstorid && !$pmid && !$pmc && !strpos($raw, $category)) {
    global $editInitiator;
    write($page, $raw . "\n$category", "$editInitiator Page contains malformed 'Cite xxx' templates; please fix!");
  }
  return Array($doi[1], $jstorid[1], $pmid[1], $pmc[1]);
}

function create_page ($type, $id, $bonus_ids) {
  $template = 'Cite ' . $type;
  $type = strtolower($type);
  global $ON, $dotDecode, $dotEncode;
  switch ($type) {
    case "doi":
      if (!get_data_from_doi($id, true) && substr($id, 0, 8) == "10.2307/") {
        // Invalid DOI generated from 10.2307/JSTORID.  Just use JSTOR parameter
        $encoded_id = anchorencode($id);
        $type = 'jstor';
        $template = 'Cite doi';
        $id = substr($id, 8);
      } else {
        $encoded_id = anchorencode($id);
      }
    break;
    default:
      $encoded_id = $id;
  }

  
  // Don't go creating a page that already exists.
  print "\nTemplate:Cite $type/$encoded_id\n";
  print (getArticleId("Template:Cite $type/$encoded_id"));
  if (getArticleId("Template:Cite $type/$encoded_id")) return false;

  foreach ($bonus_ids as $key => $value) {
    $bonus .= " | $key = $value\n";
  }
  print "{{Cite journal\n | $type = $id\n$bonus}}\n\n";
  return expand("Template:$template/$encoded_id", $ON, true,
                  "{{Cite journal\n | $type = $id\n$bonus}}<noinclude>{{Documentation|Template:cite_$type/subpage}}</noinclude>", -1);
}
/*
function swap_doi_for_jstor ($page, $doi) {
  $page_code = getRawWikiText($page);
  if ($page_code) {
    global $editInitiator;
    return write($page
            , preg_replace("~\{\{\s*cite doi\s*\|\s*" . preg_quote($doi) . "\s*\}\}~i",
                    '{{cite jstor|' . substr($doi, 8) . '}}', $page_code)
            , "$editInitiator JSTOR parameter substituted for broken [[doi:$doi]]"
    );
  } else {
    return false;
  }
}*/

while ($toDo && (false !== ($article_in_progress = array_pop($toDo))/* pages in list */)) {

  // load citations from article
  if ($article_in_progress && trim($article_in_progress)) {
    print "\n\n** Finding citations in article: $article_in_progress";
    $toCite = getCiteList($article_in_progress);
    if (!$toCite) {
      echo " - None found!";
    }
    $doi_todo = $toCite[0];
    foreach ($toCite[1] as $jid){
      $doi_todo[] = "10.2307/$jid";
    }
    $doi_todo = array_unique($doi_todo);
    $pmid_todo = array_unique($toCite[2]);
    $pmc_todo = array_unique($toCite[3]);
    print " ... " . (count($doi_todo) + count($pmid_todo) + count($pmc_todo)) . " found.";
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
          $encoded_doi = anchorencode($doi_from_pmc);
          print "\n  > Creating page at Template:Cite doi/$encoded_doi...";
          if (create_page("doi", $doi_from_pmc, array ("pmid" => $pmid_from_pmc, "pmc" => $oPmc))) {
            print "\n  > Now redirecting PMC $oPmc to $encoded_doi";
            print write($pmc_page, "#REDIRECT[[Template:Cite doi/$encoded_doi]]", "Redirecting to DOI citation [citewatch.php]")
                ? " : Done."
                : " : ERROR\n\n > Write failed!\n";
            if ($pmid_from_pmc && !getArticleId($pmid_page)) {
              print "\n  > Now redirecting PMID $pmid_from_pmc to $encoded_doi";
              print write($pmid_page, "#REDIRECT[[Template:Cite doi/$encoded_doi]]", "Redirecting to DOI citation [citewatch.php]")
                  ? " : Done."
                  : " : ERROR\n\n > Write failed!\n";
            }
          }
        } else {
          print "No DOI found; using PMID instead";
          if ($pmid_from_pmc){
            if (create_page("pmid", $pmid_from_pmc, array("pmc" => $oPmc))) {
            print "\n  > Redirecting PMC $oPmc to PMID $pmid_from_pmc";
            print write($pmc_page, "#REDIRECT[[Template:Cite pmid/$pmid_from_pmc]]", "Redirecting to PMID citation [citewatch.php]")
                ? " : Done."
                : " : ERROR\n\n > Write failed!\n";
          
            } else {
              print "\n     - Page already exists.";
            }
          } else {
            print "\n Could not find PMID or DOI for this PMC.  \n??????????????????????????????????????????????????";
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
        if (preg_match("~/(10\..*)]]~", str_replace($dotEncode, $dotDecode, $pmc_page_text), $redirect_target_doi)) {
          print "Redirects to ";
          // Check that destination page exists
          if (getArticleId("Template:Cite doi/" . anchorencode(trim($redirect_target_doi[1])))) {
         -   log_citation("pmc", $oPmc, $redirect_target_doi[1]);
            print $redirect_target_doi[1] . ".";
          } else {
            // Create it if it doesn't
            print "non-existent page. Creating > ";
            print create_page("doi", $redirect_target_doi[1]) ? " Done." : "Failed )-: ";
          }
        } else if (preg_match("~pmid/(\d+)\s*]]~", $pmc_page_text, $redirect_target_pmid)) {
          print "Redirects to ";
          // Check that the destination page exists
          if (getArticleId("Template:Cite pmid/" . $redirect_target_pmid[1])) {
            print "PMID " . $redirect_target_pmid[1] . ".";
          } else {
            // Create the target page
            print "non-existent page; creating > ";
            print create_page("pmid", $redirect_target_pmid[1]) ? "Done. " : "Failed )-:";
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
          $encoded_doi = anchorencode($doi_from_pmid);
          print "Creating new page at DOI $doi_from_pmid";
          if (create_page("doi", $doi_from_pmid, array("pmid" => $oPmid))) {
            print "\n    Created. \n  > Redirecting PMID $oPmid to $encoded_doi";
            print write($pmid_page, "#REDIRECT[[Template:Cite doi/$encoded_doi]]", "Redirecting to DOI citation")
                ? " : Done."
                : " : ERROR\n\n > Write failed!\n";
          } else {
            print "\n Could not create doi target page.";
          }
        } else {
          print "No DOI found!";
          // No DOI found.  Create a new page with a {cite journal}, then trigger the Citation Bot process on it.
          print create_page("pmid", $oPmid) ? " - Created page at PMID $oPmid" : " Couldn't create page at PMID $oPmid";
        }
      break;
      case 0:
        log_citation("pmid", $oPmid);
        // Save to database
        print "Citation OK.";
      break;
      case 1:
        print "On record as a redirect";
      break;
      case 2:
        // Check that redirect leads to a cite doi:
        if (preg_match("~/(10\..*)]]~",
              str_replace($dotEncode, $dotDecode, getRawWikiText($pmid_page)), $redirect_target_doi)) {
          print "Redirects to ";
          // Check that destination page exists
          if (getArticleId("Template:Cite doi/" . anchorencode(trim($redirect_target_doi[1])))) {
            log_citation("pmid", $oPmid, $redirect_target_doi[1]);
            print $redirect_target_doi[1] . ".";
          } else {
           // Create it if it doesn't
           print "nonexistent page. Creating > ";
           print create_page("doi", $redirect_target_doi[1]) ? "Success " : "Failure";
          }
        } else {
          exit ("$pmid_page Redirects to " . getRawWikiText($pmid_page));
        }
      break;
      default:
        exit ("That's odd. This hasn't worked.  our PMID: $oPmid; citation_is_redirect: " . citation_is_redirect("pmid", $oPmid));
    }
  }
  while ($doi_todo && (false !== ($oDoi = @array_pop($doi_todo)))) {
    if (preg_match("~^[\s,\.:;>]*(?:d?o?i?[:.,>\s]+|(?:http://)?dx\.doi\.org/)(?P<doi>.+)~i", $oDoi, $match)
      || preg_match('~^0?(?P<end>\.\d{4}/.+)~', $oDoi, $match)
            ) {
      $oDoi = $match['doi'] ? $match['doi'] : '10' . $match['end'];
      $this_page_wikitext = getRawWikiText($article_in_progress);
      if ($this_page_wikitext) {
        echo "\n   > Fixing prefixes in {{cite doi}} templates, in [[$article_in_progress]]: ";
        if ($ON) 
          echo write ($article_in_progress,
                str_replace("1$oDoi", $oDoi, str_replace($match[0], $oDoi, $this_page_wikitext)),
                "$editInitiator Corrected syntax in Cite doi-type template.") ? '.' : 'failed.';
        else
          echo ' [$ON = false; won\'t write]';
      }
    }
    $doi_citation_exists = doi_citation_exists($oDoi); // Checks in our database
    if (false && $doi_citation_exists) {
      if ($doi_citation_exists > 1) {
        log_citation("doi", $oDoi);
      }
      echo ".";
      print $oDoi;
    } else {
      echo "\n   > Creating new page at DOI $oDoi: ";
      if (get_data_from_doi($oDoi, true) || substr(trim($oDoi), 0, 8) == '10.2307/') {
        echo create_page("doi", $oDoi) ? "Done. " : "Failed. )-: ";
      } else {
        echo "\n   > Invalid DOI. Aborted operation.\n  > Marking DOI as broken: ";
        echo mark_broken_doi_template($article_in_progress, $oDoi) ? " done. " : " write operation failed. ";
      }
      unset ($p["doi"]);
    }
  }

  // Now that we've created all the necessary templates for this page, let's move on to the next in our to-do list.

  print "\n** Completed page; touching...";
  // Touch the current article to update its categories:
  touch_page($article_in_progress);
  print " Done.";
}

print "\n===End===\n\n";?>