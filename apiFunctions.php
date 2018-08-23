<?php
function query_pmid_api ($pmids, $templates) { return entrez_api($pmids, $templates, 'pubmed'); }
function query_pmc_api  ($pmcs, $templates)  { return entrez_api($pmcs,  $templates, 'pmc'); }
  
function entrez_api($ids, $templates, $db) {
  if (!count($ids)) return FALSE;
  $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?tool=DOIbot&email=martins@gmail.com&db=$db&id=" 
               . implode(',', $ids);
  report_action("Using $db API to retrieve publication details: ");
  $xml = @simplexml_load_file($url);
  if ($xml === FALSE) {
    report_warning("Unable to do PubMed search");
    return;
  }
  
  foreach (array_keys($ids) as $i) {
    $templates[$i]->record_api_usage('entrez', $db == 'pubmed' ? 'pmid' : 'pmc');
  }
  if (isset($xml->DocSum->Item) && count($xml->DocSum->Item) > 0) foreach($xml->DocSum as $document) {
    $this_template = $templates[array_search($document->Id, $ids)];
    report_info("Found match for $db identifier " . $document->Id);
    foreach ($document->Item as $item) {
      if (preg_match("~10\.\d{4}/[^\s\"']*~", $item, $match)) {
        $this_template->add_if_new('doi', $match[0], 'entrez');
      }
      switch ($item["Name"]) {
                case "Title":   $this_template->add_if_new('title',  str_replace(array("[", "]"), "", (string) $item), 'entrez'); // add_if_new will format the title
        break;  case "PubDate": preg_match("~(\d+)\s*(\w*)~", $item, $match);
                                $this_template->add_if_new('year', (string) $match[1], 'entrez');
        break;  case "FullJournalName": $this_template->add_if_new('journal',  ucwords((string) $item), 'entrez'); // add_if_new will format the title
        break;  case "Volume":  $this_template->add_if_new('volume', (string) $item, 'entrez');
        break;  case "Issue":   $this_template->add_if_new('issue', (string) $item, 'entrez');
        break;  case "Pages":   $this_template->add_if_new('pages', (string) $item, 'entrez');
        break;  case "PmId":    $this_template->add_if_new('pmid', (string) $item, 'entrez');
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
                $this_template->add_if_new("author$i", $names[1] . $junior . ',' . $first, 'entrez');
              }
            } else {
              // We probably have a committee or similar.  Just use 'author$i'.
              $this_template->add_if_new("author$i", (string) $subItem, 'entrez');
            }
          }
        break; case "LangList": case 'ISSN':
        break; case "ArticleIds":
          foreach ($item->Item as $subItem) {
            switch ($subItem["Name"]) {
              case "pubmed": case "pmid":
                  preg_match("~\d+~", (string) $subItem, $match);
                  $this_template->add_if_new("pmid", $match[0], 'entrez');
                  break; ### TODO PLACEHOLDER YOU ARE HERE CONTINUATION POINT ###
              case "pmc":
                preg_match("~\d+~", (string) $subItem, $match);
                $this_template->add_if_new('pmc', $match[0], 'entrez');
                break;
              case "doi": case "pii":
              default:
                if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $subItem, $match)) {
                  $this_template->add_if_new('doi', $match[0], 'entrez');
                }
                if (preg_match("~PMC\d+~", (string) $subItem, $match)) {
                  $this_template->add_if_new('pmc', substr($match[0], 3), 'entrez');
                }
            }
          }
        break;
      }
    }
  }
}

function query_bibcode_api($bibcodes, $templates) { return adsabs_api($bibcodes, $templates, 'bibcode'); }

function expand_arxiv_templates ($templates) {
  $ids = array();
  $arxiv_templates = array();
  foreach ($templates as $this_template) {
    if ($this_template->wikiname() == 'cite arxiv') {
      $arxiv_param = 'eprint';
      $this_template->rename('arxiv', 'eprint');
    } else {
      $arxiv_param = 'arxiv';
      $this_template->rename('eprint', 'arxiv');
    }
    $eprint = str_ireplace("arXiv:", "", $this_template->get('eprint') . $this_template->get('arxiv'));
    if ($eprint) {
      array_push($ids, $eprint);
      array_push($arxiv_templates, $this_template);
    }
  }
  return arxiv_api($ids, $arxiv_templates);
}

function arxiv_api($ids, $templates) {
  if (count($ids) == 0) return FALSE;
  report_action("Getting data from arXiv API");
  $context = stream_context_create(array(
    'http' => array('ignore_errors' => true),
  ));
  $request = "https://export.arxiv.org/api/query?start=0&max_results=2000&id_list=" . implode($ids, ',');
  $response = @file_get_contents($request, FALSE, $context);
  if ($response) {
    $xml = @simplexml_load_string(
      preg_replace("~(</?)(\w+):([^>]*>)~", "$1$2$3", $response)
    );
  } else {
    report_warning("No response from arXiv.");
    return FALSE;
  }
  if ($xml) {
    if ((string)$xml->entry->title === "Error") {
      report_warning("arXiv search failed; please report error: " . (string)$xml->entry->summary);
      return FALSE;
    }
  }
  
  $this_template = current($templates); // advance at end of foreach loop
  foreach ($xml->entry as $entry) {
    $i = 0;
    report_info("Found match for arXiv " . $ids[$i]);
    foreach ($xml->entry->author as $auth) {
      $i++;
      $name = $auth->name;
      if (preg_match("~(.+\.)(.+?)$~", $name, $names) || preg_match('~^\s*(\S+) (\S+)\s*$~', $name, $names)) {
        $this_template->add_if_new("last$i", $names[2], 'arxiv');
        $this_template->add_if_new("first$i", $names[1], 'arxiv');
      } else {
        $this_template->add_if_new("author$i", $name, 'arxiv');
      }
    }
    $this_template->add_if_new("title", (string) $entry->title, 'arxiv'); // Formatted by add_if_new
    $this_template->add_if_new("class", (string) $entry->category["term"], 'arxiv');
    $this_template->add_if_new("year", substr($entry->published, 0, 4), 'arxiv');
    $this_template->add_if_new("doi", (string) $entry->arxivdoi, 'arxiv');

    if ($entry->arxivjournal_ref) {
      $journal_data = (string) $entry->arxivjournal_ref;
      if (preg_match("~(, *\(?([12]\d{3})\)?).*?$~u", $journal_data, $match)) {
        $journal_data = str_replace($match[1], "", $journal_data);
        $current_year = $this_template->get_without_comments_and_placeholders('year');
        if (!$current_year
        ||  (preg_match('~\d{4}~', $current_year) && $current_year < $match[2])) {
          $this_template->add('year', $match[2]);
        }
      }
      if (preg_match("~\w?\d+-\w?\d+~", $journal_data, $match)) {
        $journal_data = str_replace($match[0], "", $journal_data);
        $this_template->add_if_new("pages", str_replace("--", REGEXP_EN_DASH, $match[0]), 'arxiv');
      }
      if (preg_match("~(\d+)(?:\D+(\d+))?~", $journal_data, $match)) {
        $this_template->add_if_new("volume", $match[1], 'arxiv');
        if (isset($match[2])) {
          $this_template->add_if_new("issue", $match[2], 'arxiv');
        }
        $journal_data = preg_replace("~[\s:,;]*$~", "",
                str_replace($match[-0], "", $journal_data));
      }
      if ($this_template->has('publisher') && $journal_data) {
        $this_template->forget('publisher'); // This is either bad data, or refers to a preprint, not the journal
      }
      $this_template->add_if_new("journal", wikify_external_text($journal_data), 'arxiv');
    } else {
      $this_template->add_if_new("year", date("Y", strtotime((string)$entry->published)), 'arxiv');
    }
    $this_template = next($templates);
  }
}

function adsabs_api($ids, $templates, $identifier) {
  if (count($ids) == 0) return FALSE;
  if (count($ids) < 5) {
    foreach ($templates as $template) {
      $template->expand_by_adsabs();
    }
    return TRUE;
  }
  
  // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/Search_API.ipynb
  $adsabs_url = "https://api.adsabs.harvard.edu/v1/search/bigquery?q=*:*"
              . "&fl=arxiv_class,author,bibcode,doi,doctype,identifier,"
              . "issue,page,pub,pubdate,title,volume,year&rows=2000";

  if (!getenv('PHP_ADSABSAPIKEY')) {
    report_warning("PHP_ADSABSAPIKEY environment variable not set. Cannot query AdsAbs.");
    return FALSE;
  }
  
  try {
    report_action("Expanding from BibCodes via AdsAbs API");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $adsabs_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: big-query/csv', 
      'Authorization: Bearer ' . getenv('PHP_ADSABSAPIKEY')));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, "$identifier\n" . str_replace("%0A", "\n", urlencode(implode("\n", $ids))));
    if (getenv('TRAVIS')) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Delete once Travis CI recompile their PHP binaries
    }
    $return = curl_exec($ch);
    if ($return === FALSE) {
      throw new Exception(curl_error($ch), curl_errno($ch));
    } 
    $http_response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_length = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header = substr($return, 0, $header_length);
    $body = substr($return, $header_length);
    $decoded = @json_decode($body);
    if (is_object($decoded) && isset($decoded->error)) {
      throw new Exception(
      ((isset($decoded->error->msg)) ? $decoded->error->msg : $decoded->error)
      . "\n - URL was:  " . $adsabs_url,
      (isset($decoded->error->code) ? $decoded->error->code : 999));
    }
    if ($http_response != 200) {
      throw new Exception(strtok($header, "\n"), $http_response);
    }
    
    if (preg_match_all('~\nX\-RateLimit\-(\w+):\s*(\d+)\r~i', $header, $rate_limit)) {
      if ($rate_limit[2][2]) {
        report_info("AdsAbs search " . ($rate_limit[2][0] - $rate_limit[2][1]) . "/" . $rate_limit[2][0] .
             ":\n       " . implode("\n       ", $ids));
             // "; reset at " . date('r', $rate_limit[2][2]);
      } else {
        report_warning("AdsAbs daily search limit exceeded. Retry at " . date('r', $rate_limit[2][2]) . "\n");
        return (object) array('numFound' => 0);
      }
    } else {
      throw new Exception("Headers do not contain rate limit information:\n" . $header, 5000);
    }
    if (!is_object($decoded)) {
      throw new Exception("Could not decode API response:\n" . $body, 5000);
    }
    
    if (isset($decoded->response)) {
      $response = $decoded->response;
    } else {
      if ($decoded->error) throw new Exception("" . $decoded->error, 5000); // "". to force string
      throw new Exception("Could not decode AdsAbs response", 5000);
    }
  } catch (Exception $e) {
    if ($e->getCode() == 5000) { // made up code for AdsAbs error
      trigger_error(sprintf("API Error in query_adsabs: %s",
                    $e->getMessage()), E_USER_NOTICE);
    } else if (strpos($e->getMessage(), 'HTTP') === 0) {
      trigger_error(sprintf("HTTP Error %d in query_adsabs: %s",
                    $e->getCode(), $e->getMessage()), E_USER_NOTICE);
    } else {
      trigger_error(sprintf("Error %d in query_adsabs: %s",
                    $e->getCode(), $e->getMessage()), E_USER_WARNING);
      curl_close($ch);
    }
  }
  foreach ($response->docs as $record) {
    $this_template = $templates[array_search((string) $record->bibcode, $templates)];
    $this_template->add_if_new('bibcode', (string) $record->bibcode, 'adsabs');
    $this_template->add_if_new("title", (string) $record->title[0], 'adsabs'); // add_if_new will format the title text and check for unknown
    $i = 0;
    if (isset($record->author)) {
     foreach ($record->author as $author) {
      $this_template->add_if_new("author" . ++$i, $author, 'adsabs');
     }
    }
    if (isset($record->pub)) {
      $journal_string = explode(",", (string) $record->pub);
      $journal_start = mb_strtolower($journal_string[0]);
      if (preg_match("~\bthesis\b~ui", $journal_start)) {
        // Do nothing
      } elseif (substr($journal_start, 0, 6) == "eprint") {
        if (substr($journal_start, 7, 6) == "arxiv:") {
          if (isset($record->arxivclass)) $this_template->add_if_new("class", $record->arxivclass, 'adsabs');
        } else {
          $this_template->append_to('id', ' ' . substr($journal_start, 13));
        }
      } else {
        $this_template->add_if_new('journal', $journal_string[0], 'adsabs');
      }          
    }
    if (isset($record->page) && (stripos(implode('–', $record->page), 'arxiv') !== FALSE)) {  // Bad data
       unset($record->page);
       unset($record->volume);
       unset($record->issue);
    }
    if (isset($record->volume)) {
      $this_template->add_if_new("volume", (string) $record->volume, 'adsabs');
    }
    if (isset($record->issue)) {
      $this_template->add_if_new("issue", (string) $record->issue, 'adsabs');
    }
    if (isset($record->year)) {
      $this_template->add_if_new("year", preg_replace("~\D~", "", (string) $record->year), 'adsabs');
    }
    if (isset($record->page)) {
      $this_template->add_if_new("pages", implode('–', $record->page), 'adsabs');
    }
    if (isset($record->identifier)) { // Sometimes arXiv is in journal (see above), sometimes here in identifier
      foreach ($record->identifier as $recid) {
        if(strtolower(substr($recid, 0, 6)) === 'arxiv:') {
           if (isset($record->arxivclass)) $this_template->add_if_new("class", $record->arxivclass, 'adsabs');
        }
      }
    }
  }
}

?>