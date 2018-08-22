<?php
function pmid_api ($pmids, $templates) { return entrez_api($pmids, $templates, 'pubmed'); }
function pmc_api  ($pmcs, $templates)  { return entrez_api($pmcs,  $templates, 'pmc'); }
  
function entrez_api($ids, $templates, $db) {
  $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?tool=DOIbot&email=martins@gmail.com&db=$db&id=" 
               . implode(',', $ids);
  report_action("Using PMID API to retrieve publication details: ");
  $xml = @simplexml_load_file($url);
  if ($xml === FALSE) {
    report_warning("Unable to do PubMed search");
    return;
  }
  
  if (count($xml->DocSum->Item) > 0) foreach($xml->DocSum as $document) {
    $this_template = $templates[array_search($document->Id, $ids)];
    report_info("Found match for $db identifier " . $document->Id);
    foreach ($document->Item as $item) {
      if (preg_match("~10\.\d{4}/[^\s\"']*~", $item, $match)) {
        $this_template->add_if_new('doi', $match[0]);
      }
      switch ($item["Name"]) {
                case "Title":   $this_template->add_if_new('title',  str_replace(array("[", "]"), "", (string) $item)); // add_if_new will format the title
        break;  case "PubDate": preg_match("~(\d+)\s*(\w*)~", $item, $match);
                                $this_template->add_if_new('year', (string) $match[1]);
        break;  case "FullJournalName": $this_template->add_if_new('journal',  ucwords((string) $item)); // add_if_new will format the title
        break;  case "Volume":  $this_template->add_if_new('volume', (string) $item);
        break;  case "Issue":   $this_template->add_if_new('issue', (string) $item);
        break;  case "Pages":   $this_template->add_if_new('pages', (string) $item);
        break;  case "PmId":    $this_template->add_if_new('pmid', (string) $item);
        break;  case "AuthorList":
          $i = 0;
          foreach ($item->Item as $subItem) {
            $i++;
            if (author_is_human((string) $subItem)) {
              $jr_test = junior_test($subItem);
              $subItem = $jr_test[0];
              $junior = $jr_test[1];
              if (preg_match("~(.*) (\w+)$~", $subItem, $names)) {
                $first = trim(preg_replace('~(?<=[A-Z])([A-Z])~', ". $1", $names[2]));
                if (strpos($first, '.') && substr($first, -1) != '.') {
                  $first = $first . '.';
                }
                $this_template->add_if_new("author$i", $names[1] . $junior . ',' . $first);
              }
            } else {
              // We probably have a committee or similar.  Just use 'author$i'.
              $this_template->add_if_new("author$i", (string) $subItem);
            }
          }
        break; case "LangList": case 'ISSN':
        break; case "ArticleIds":
          foreach ($item->Item as $subItem) {
            switch ($subItem["Name"]) {
              case "pubmed": case "pmid":
                  preg_match("~\d+~", (string) $subItem, $match);
                  if ($this_template->add_if_new("pmid", $match[0])) $this_template->expand_by_pubmed();
                  break; ### TODO PLACEHOLDER YOU ARE HERE CONTINUATION POINT ###
              case "pmc":
                preg_match("~\d+~", (string) $subItem, $match);
                $this_template->add_if_new('pmc', $match[0]);
                break;
              case "doi": case "pii":
              default:
                if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $subItem, $match)) {
                  if ($this_template->add_if_new('doi', $match[0])) {
                    $this_template->expand_by_doi();
                  }
                }
                if (preg_match("~PMC\d+~", (string) $subItem, $match)) {
                  $this_template->add_if_new('pmc', substr($match[0], 3));
                }
                break;
            }
          }
        break;
      }
    }
  }
}
?>