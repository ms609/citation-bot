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
$toDo = #array_merge(
categoryMembers("Pages_with_incomplete_DOI_references")
#categoryMembers("Pages_with_incomplete_PMID_references"),
#categoryMembers("Pages_with_incomplete_PMC_references")#,
 #categoryMembers("Pages_with_incomplete_JSTOR_references");
;
#
$toDo = array("User:DOI bot/Zandbox");
print_r($toDo);
#shuffle($toDo);
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
  $current_page->write();  # Touch, to update category membership
  die("\ncold die in citewatch.php\n");
  $toCite = getCiteList($article_in_progress);

  while ($pmid_todo && (false !== ($oPmid = array_shift($pmid_todo)))) {
    print "\n   > PMID $oPmid: ";
    // Is there already a page for this PMID?
    switch (citation_is_redirect("pmid", $oPmid)) {
      case -1:#done
      break;
      case 0: #done
      break;
      case 1:
      #done
      break;
      case 2: // Page exists; we need to check that the redirect has been created.
      #done
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