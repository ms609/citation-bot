#!/usr/bin/php
<?php
// $Id$
$ON = true;
error_reporting(E_ALL^E_NOTICE);

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
    #TODO mark as erroneous:
    #write($page, $raw . "\n$category", "$editInitiator Page contains malformed 'Cite xxx' templates; please fix!");
  }
  return Array($doi[1], $jstorid[1], $pmid[1], $pmc[1]);
}

while ($toDo && (false !== ($article_in_progress = array_pop($toDo))/* pages in list */)) {

  // load citations from article
  print "\n\n** Finding citations in article: $article_in_progress";
  $current_page = new Page();
  $current_page->get_text_from($article_in_progress);
  $current_page->expand_remote_templates();
  die("\n");
  $toCite = getCiteList($article_in_progress);

  while ($pmid_todo && (false !== ($oPmid = array_shift($pmid_todo)))) {
    print "\n   > PMID $oPmid: ";
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
    if ($doi_citation_exists) {
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