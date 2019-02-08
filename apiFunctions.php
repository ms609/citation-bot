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
    report_warning("Error in PubMed search: No response from Entrez server");
    return;
  }
  
  foreach (array_keys($ids) as $i) {
    $templates[$i]->record_api_usage('entrez', $db == 'pubmed' ? 'pmid' : 'pmc');
  }
  if (isset($xml->DocSum->Item) && count($xml->DocSum->Item) > 0) foreach($xml->DocSum as $document) {
    report_info("Found match for $db identifier " . $document->Id);
    $template_key = array_search($document->Id, $ids);
    if ($template_key === FALSE) {
      report_warning("Pubmed returned an identifier, [" . $document->Id . "] that we didn't search for.");
      continue;
    }
    $this_template = $templates[$template_key];
  
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
            if (author_is_human((string) $subItem)) {
              $jr_test = junior_test($subItem);
              $subItem = $jr_test[0];
              $junior = $jr_test[1];
              if (preg_match("~(.*) (\w+)$~", $subItem, $names)) {
                $first = trim(preg_replace('~(?<=[A-Z])([A-Z])~', ". $1", $names[2]));
                if (strpos($first, '.') && substr($first, -1) != '.') {
                  $first = $first . '.';
                }
                $i++;
                $this_template->add_if_new("author$i", $names[1] . $junior . ',' . $first, 'entrez');
              }
            } else {
              // We probably have a committee or similar.  Just use 'author$i'.
              $i++;
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
    if ($this_template->add_if_new("doi", (string) $entry->arxivdoi, 'arxiv')) {
      expand_by_doi($this_template);
    }
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
    $this_template->add_if_new("year", date("Y", strtotime((string)$entry->published)));

    if ($entry->arxivjournal_ref) {
      $journal_data = trim((string) $entry->arxivjournal_ref); // this is human readble text
      parse_plain_text_reference($journal_data, $this_template, TRUE);
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
  
  foreach ($ids as $key => $bibcode) {
    if (strpos($bibcode, 'book') !== false) {
        unset($ids[$key]);
    } elseif (
        strpos($bibcode, '&') !== false) {
        unset($ids[$key]);
    }
  }
  foreach ($templates as $template) {
    if ((strpos($template->get('bibcode'), '&') !== false) || (strpos($template->get('bibcode'), 'book') !== false)) {
      $template->expand_by_adsabs(); // This single bibcode API supports bibcodes with & in them, and special book code
    }
  }
  if (count($ids) == 0) return TRUE; // None left after removing books and & symbol

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
    if (getenv('TRAVIS') && defined('PHP_VERSION_ID') && (PHP_VERSION_ID < 60000)) {
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
        report_info("AdsAbs 'big-query' request " . ($rate_limit[2][0] - $rate_limit[2][1]) . "/" . $rate_limit[2][0] .
             ":\n       ");
             // "; reset at " . date('r', $rate_limit[2][2]);
      } else {
        report_warning("AdsAbs daily search limit exceeded. Big queries stopped until " . date('r', $rate_limit[2][2]) . "\n");
        foreach ($templates as $template) {
           $template->expand_by_adsabs();
        }
        return TRUE;
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
    if (!in_array($record->bibcode, $ids)) {  // Remapped bibcodes cause corrupt big queries
      foreach ($templates as $template) {
        $template->expand_by_adsabs();
      }
      return;
    }
  }

  $matched_ids = [];
  foreach ($response->docs as $record) {
    report_info("Found match for bibcode " . bibcode_link($record->bibcode));
    $matched_ids[] = $record->bibcode;
    $this_template = $templates[array_search((string) $record->bibcode, $ids)];
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
    if (isset($record->page)) {
      if ((stripos(implode('–', $record->page), 'arxiv') !== FALSE) || (stripos(implode('–', $record->page), '/') !== FALSE)) {  // Bad data
       unset($record->page);
       unset($record->volume);
       unset($record->issue);
      }
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
  $unmatched_ids = array_diff($ids, $matched_ids);
  if (sizeof($unmatched_ids)) {
    report_warning("No match for bibcode identifier: " . implode('; ', $unmatched_ids));
  }
}

function query_doi_api($ids, $templates) {
  foreach ($templates as $template) {
    expand_by_doi($template);
  }
}

function expand_by_doi($template, $force = FALSE) {
  // Because it can recover rarely used parameters such as editors, series & isbn, 
  // there will be few instances where it could not in principle be profitable to 
  // run this function, so we don't check this first.
  
  if (!$template->verify_doi()) return FALSE;
  $doi = $template->get_without_comments_and_placeholders('doi');
  if ($doi === $template->last_searched_doi) return FALSE;
  $template->last_searched_doi = $doi;
  if (preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) return FALSE; // We do not use DOI's that are just an ISSN.
  if ($doi && preg_match('~^10\.2307/(\d+)$~', $doi)) {
      $template->add_if_new('jstor', substr($doi, 8));
  }
  if ($doi && ($force || $template->incomplete())) {
    $crossRef = query_crossref($doi);
    if ($crossRef) {
      if (in_array(strtolower($crossRef->article_title), BAD_ACCEPTED_MANUSCRIPT_TITLES)) return FALSE ;
      report_action("Querying CrossRef: doi:" . doi_link($doi));

      if ($crossRef->volume_title && $template->blank('journal')) {
        if (strtolower($template->get('title')) == strtolower($crossRef->article_title)) {
           $template->rename('title', 'chapter');
         } else {
           $template->add_if_new('chapter', restore_italics($crossRef->article_title)); // add_if_new formats this value as a title
        }
        $template->add_if_new('title', restore_italics($crossRef->volume_title)); // add_if_new will wikify title and sanitize the string
      } else {
        $template->add_if_new('title', restore_italics($crossRef->article_title)); // add_if_new will wikify title and sanitize the string
      }
      $template->add_if_new('series', $crossRef->series_title); // add_if_new will format the title for a series?
      $template->add_if_new("year", $crossRef->year);
      if (   $template->blank(array('editor', 'editor1', 'editor-last', 'editor1-last')) // If editors present, authors may not be desired
          && $crossRef->contributors->contributor
        ) {
        $au_i = 0;
        $ed_i = 0;
        // Check to see whether a single author is already set
        // This might be, for example, a collaboration
        $existing_author = $template->first_author();
        $add_authors = is_null($existing_author)
                    || $existing_author = ''
                    || author_is_human($existing_author);
        
        foreach ($crossRef->contributors->contributor as $author) {
          if (strtoupper($author->surname) === '&NA;') break; // No Author, leave loop now!  Have only seen upper-case in the wild
          if ($author["contributor_role"] == 'editor') {
            ++$ed_i;
            if ($ed_i < 31 && $crossRef->journal_title === NULL) {
              $template->add_if_new("editor$ed_i-last", format_surname($author->surname));
              $template->add_if_new("editor$ed_i-first", format_forename($author->given_name));
            }
          } elseif ($author['contributor_role'] == 'author' && $add_authors) {
            ++$au_i;
            $template->add_if_new("last$au_i", format_surname($author->surname));
            $template->add_if_new("first$au_i", format_forename($author->given_name));
          }
        }
      }
      $template->add_if_new('isbn', $crossRef->isbn);
      $template->add_if_new('journal', $crossRef->journal_title); // add_if_new will format the title
      if ($crossRef->volume > 0) $template->add_if_new('volume', $crossRef->volume);
      if (((strpos($crossRef->issue, '-') > 0 || (integer) $crossRef->issue > 1))) {
      // "1" may refer to a journal without issue numbers,
      //  e.g. 10.1146/annurev.fl.23.010191.001111, as well as a genuine issue 1.  Best ignore.
        $template->add_if_new('issue', $crossRef->issue);
      }
      if ($template->blank("page")) {
        if ($crossRef->last_page && (strcmp($crossRef->first_page, $crossRef->last_page) !== 0)) {
          $template->add_if_new("pages", $crossRef->first_page . "-" . $crossRef->last_page); //replaced by an endash later in script
        } else {
          $template->add_if_new("pages", $crossRef->first_page);
        }
      }
    } else {
      report_warning("No CrossRef record found for doi '" . echoable($doi) ."'");
      expand_doi_with_dx($template, $doi);
    }
  }
}

function query_crossref($doi) {
  $doi = str_replace(DOI_URL_DECODE, DOI_URL_ENCODE, $doi);
  $url = "https://www.crossref.org/openurl/?pid=" . CROSSREFUSERNAME . "&id=doi:$doi&noredirect=TRUE";
  for ($i = 0; $i < 2; $i++) {
    $raw_xml = @file_get_contents($url);
    $raw_xml = preg_replace(
      '~(\<year media_type=\"online\"\>\d{4}\<\/year\>\<year media_type=\"print\"\>)~',
          '<year media_type="print">',
          $raw_xml);
    $xml = @simplexml_load_string($raw_xml);
    if ($xml) {
      $result = $xml->query_result->body->query;
      if ($result["status"] == "resolved") {
        return $result;
      } else {
        return FALSE;
      }
    } else {
      sleep(1);
      // Keep trying...
    }
  }
  report_warning("Error loading CrossRef file from DOI " . echoable($doi) . "!");
  return FALSE;
}

function expand_doi_with_dx($template, $doi) {
     // See https://crosscite.org/docs.html for discussion of API we are using -- not all agencies resolve the same way
     // https://api.crossref.org/works/$doi can be used to find out the agency
     // https://www.doi.org/registration_agencies.html  https://www.doi.org/RA_Coverage.html List of all ten doi granting agencies - many do not do journals
     // Examples of DOI usage   https://www.doi.org/demos.html
     $try_to_add_it = function($name, $data) use($template) {
       if (is_null($data)) return;
       while (is_array($data)) {
         if (empty($data)) return;
         if (!is_set($data['0'])) return;
         if (is_set($data['1'])) return; // How dow we choose?
         $data = $data['0'];  // Going down deeper
       }
       if ($data == '') return;
       $template->add_if_new($name, $data);
     };
     if (!$doi) return FALSE;
     $ch = curl_init();
     curl_setopt($ch, CURLOPT_URL,'https://doi.org/' . $doi);
     curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/vnd.citationstyles.csl+json"));
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
     try {
       $ris = @curl_exec($ch);
     } catch (Exception $e) {
       $template->mark_inactive_doi($doi);
       return FALSE;
     }
     if ($ris == FALSE || stripos($ris, 'DOI Not Found') !== FALSE || stripos($ris, 'DOI prefix') !== FALSE) {
       $template->mark_inactive_doi($doi);
       return FALSE;
     }
     $json = @json_decode($ris, TRUE);
     if($json === FALSE) return FALSE;
     report_action("Querying dx.doi.org: doi:" . doi_link($doi));
     // BE WARNED:  this code uses the "@$var" method.
     // If the variable is not set, then PHP just passes NULL, then that is interpreted as a empty string
     if ($template->blank(['date', 'year'])) {
       if (isset($json['issued']['date-parts']['0']['0'])) {
         $try_to_add_it('year', $json['issued']['date-parts']['0']['0']);
       } elseif (isset($json['created']['date-parts']['0']['0'])) {
         $try_to_add_it('year', $json['created']['date-parts']['0']['0']);
       } elseif (isset($json['published-print']['date-parts']['0']['0'])) {
         $try_to_add_it('year', $json['published-print']['date-parts']['0']['0']);
       }
     }
     $try_to_add_it('issue', @$json['issue']);
     $try_to_add_it('pages', @$json['pages']);
     $try_to_add_it('volume', @$json['volume']);
     if ($template->blank('isbn')) {
       if (isset($json['ISBN']['0'])) {
         $try_to_add_it('isbn', $json['ISBN']['0']);
       } elseif (isset($json['isbn-type']['0']['value'])) {
         $try_to_add_it('isbn', $json['isbn-type']['0']['value']);
       }
     }
     if (isset($json['author'])) {
       $i = 0;
       foreach ($json['author'] as $auth) {
          $i = $i + 1;
          $try_to_add_it('last' . (string) $i, @$auth['family']);
          $try_to_add_it('first' . (string) $i, @$auth['given']);
          $try_to_add_it('author' . (string) $i, @$auth['literal']);
       }
     }
     if (isset($json['container-title']) && isset($json['publisher']) && ($json['publisher'] === $json['container-title'])) {
        unset($json['container-title']);  // Publisher hiding as journal name too
     }
     if (@$json['type'] == 'article-journal' ||
         @$json['type'] == 'article' ||
         (@$json['type'] == '' && (isset($json['container-title']) || isset($json['issn']['0'])))) {
       $try_to_add_it('journal', @$json['container-title']);
       $try_to_add_it('title', @$json['title']);
     } elseif (@$json['type'] == 'monograph') {
       $try_to_add_it('title', @$json['title']);
       $try_to_add_it('title', @$json['container-title']);// Usually not set, but just in case this and not title is set
       $try_to_add_it('location', @$json['publisher-location']);
       $try_to_add_it('publisher', @$json['publisher']);
     } elseif (@$json['type'] == 'chapter') {
       $try_to_add_it('title', @$json['container-title']);
       $try_to_add_it('chapter', @$json['title']);
       $try_to_add_it('location', @$json['publisher-location']);
       $try_to_add_it('publisher', @$json['publisher']);
     } elseif (@$json['type'] == 'dataset') {
       $try_to_add_it('type', 'Data Set');
       $try_to_add_it('title', @$json['title']);
       $try_to_add_it('location', @$json['publisher-location']);
       $try_to_add_it('publisher', @$json['publisher']);
       $try_to_add_it('chapter', @$json['categories']['0']);  // Not really right, but there is no cite data set template
     } elseif (@$json['type'] == '') {  // Add what we can where we can
       $try_to_add_it('title', @$json['title']);
       $try_to_add_it('location', @$json['publisher-location']);
       $try_to_add_it('publisher', @$json['publisher']);
     } else {
       if (getenv('TRAVIS')) {
         print_r($json);
         trigger_error ('dx.doi.org returned unexpected data type for ' . doi_link($doi));
       } else {
         $try_to_add_it('title', @$json['title']);
         report_warning('dx.doi.org returned unexpected data type for ' . doi_link($doi));
       }
     }
     return TRUE;
}

function doi_active($doi) {
  static $cache = [];
  if (!isset($cache[$doi]) || $cache[$doi] === NULL) {
    $cache[$doi] = is_doi_active($doi);
  }
  return $cache[$doi];
}

function is_doi_active($doi) {
  $response = get_headers("https://api.crossref.org/works/" . urlencode($doi))[0];
  if (stripos($response, '200 OK') !== FALSE) return TRUE;
  if (stripos($response, '404 Not Found') !== FALSE) return FALSE;
  report_warning("CrossRef server error loading headers for DOI " . echoable($doi) . ": $response");
  return NULL;
}

function query_jstor_api($ids, $templates) {
  foreach ($templates as $template) expand_by_jstor($template);
}

function expand_by_jstor($template) {
  if ($template->incomplete() === FALSE) return FALSE;
  if ($template->has('jstor')) {
     $jstor = trim($template->get('jstor'));
  } elseif(preg_match('~^https?://(?:www\.|)jstor\.org/stable/(.*)$~', $template->get('url'), $match)) {
     $jstor = $match[1];
  } else {
     return FALSE;
  }
  if (preg_match('~^(.*)(?:\?.*)$~', $jstor, $match)) {
     $jstor = $match[1]; // remove ?seq= stuff
  }
  $jstor = trim($jstor);
  if (strpos($jstor, ' ') !== FALSE) return FALSE ; // Comment/template found
  if (substr($jstor, 0, 1) === 'i') return FALSE ; // We do not want i12342 kind
  $dat = @file_get_contents('https://www.jstor.org/citation/ris/' . $jstor);
  if ($dat === FALSE) {
    report_info("JSTOR API returned nothing for ". jstor_link($jstor));
    return FALSE;
  }
  if (stripos($dat, 'No RIS data found for') !== FALSE) {
    report_info("JSTOR API found nothing for ".  jstor_link($jstor));
    return FALSE;
  }
  if (stripos($dat, 'Block Reference') !== FALSE) {
    report_info("JSTOR API blocked bot for ".  jstor_link($jstor));
    return FALSE;
  }
  $has_a_url = $template->has('url');
  $template->expand_by_RIS($dat, FALSE);
  if ($template->has('url') && !$has_a_url) { // we added http://www.jstor.org/stable/12345, so remove quietly
      $template->quietly_forget('url');
  }
  return TRUE;
}

// This routine is actually not used much, since we often get a DOI and thus do not need to parse this thankfully
// Do not add a new regex without adding a test too in TemplateTest.php
function parse_plain_text_reference($journal_data, &$this_template, $upgrade_years = FALSE ) { // WARNING: Reference passing
      $journal_data = trim($journal_data);
      if ($journal_data === "") return;
      $arxiv_journal=FALSE;
      $arxiv_volume=FALSE;
      $arxiv_issue=FALSE;
      $arxiv_pages=FALSE;
      $arxiv_year=FALSE;
      // JournalVolume:Pages,Year
      if (preg_match("~^([a-zA-ZÀ-ÿ \.]+)([0-9]+):([0-9]+[\-]+[0-9]+|[0-9]+),([12][0-9][0-9][0-9])$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[3];
        $arxiv_year=$matches[4];
      // Journal Volume (Year) Pages
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+) ([0-9]+) \(([12][0-9][0-9][0-9])\) ([0-9]+[\-]+[0-9]+|[0-9]+)$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_year=$matches[3];
        $arxiv_pages=$matches[4];
      // Journal, Volume, Pages (Year)
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+), ([0-9]+), ([0-9]+[\-]+[0-9]+|[0-9]+) \(([12][0-9][0-9][0-9])\)$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[3];
        $arxiv_year=$matches[4];
      // Journal, Volume:Pages, Year
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+), ([0-9]+):([0-9]+[\-]+[0-9]+|[0-9]+), ([12][0-9][0-9][0-9])$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[3];
        $arxiv_year=$matches[4];
      // Journal Volume (issue), Pages (year)   // Allow up to three didgets in issue to avoid years
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+) ([0-9]+) \(([0-9][0-9]?[0-9]?)\), ([0-9]+[\-]+[0-9]+|[0-9]+) \(([12][0-9][0-9][0-9])\)$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_issue=$matches[3];
        $arxiv_pages=$matches[4];
        $arxiv_year=$matches[5];
      // Journal volume (Year), ArticleID, #OfPages pages
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+) ([0-9]+) \(([12][0-9][0-9][0-9])\), ([0-9]+), ([0-9]+) pages$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_year=$matches[3];
        $arxiv_pages=$matches[4]; // Not exactly pages
      // Journal Volume, pages (YEAR)
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+) ([0-9]+), ([0-9]+[\-]+[0-9]+|[0-9]+) \(([12][0-9][0-9][0-9])\)$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[3];
        $arxiv_year=$matches[4];
      // JournalVolume (YEAR), pages
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+)([0-9]+) \(([12][0-9][0-9][0-9])\), ([0-9]+[\-]+[0-9]+|[0-9]+)$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_year=$matches[3];
        $arxiv_pages=$matches[4];
      // Journal Volume(Issue), Pages, year
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+) ([0-9]+)\(([0-9][0-9]?[0-9]?)\), ([0-9]+[\-]+[0-9]+|[0-9]+), ([12][0-9][0-9][0-9])$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_issue=$matches[3];
        $arxiv_pages=$matches[4];
        $arxiv_year=$matches[5];
      // A&A 619, A49 (2018)
      } elseif (preg_match("~^A&A ([0-9]+), ([A-Z0-9]+) \((\d{4})\)$~u", $journal_data, $matches)) {
        $arxiv_journal='Astronomy & Astrophysics'; // We expand this out
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[1];
        $arxiv_year=$matches[3];
      // ApJ, 767:L7, 2013 April 10
      } elseif (preg_match("~^ApJ, \d+:L\d+, (\d{4}) .+$~", $journal_data, $matches)) {
        $arxiv_journal='The Astrophysical Journal'; // We expand this out
        $arxiv_year=$matches[1];
      // Astrophys.J.639:L43-L46,2006F
      } elseif (preg_match("~^Astrophys\.J\.\d.*:L.+,(\d{4})F$~", $journal_data, $matches)) {
        $arxiv_journal='The Astrophysical Journal'; // We expand this out
        $arxiv_year=$matches[1];
      //Information Processing Letters 115 (2015), pp. 633-634
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+) ([0-9]+) \((\d{4})\), pp\. (\d{1,5}-\d{1,5})$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[4];
        $arxiv_year=$matches[3];           
      //Theoretical Computer Science, Volume 561, Pages 113-121, 2015
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+), Volume ([0-9]+), Pages (\d{1,5}-\d{1,5}), (\d{4})$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[3];
        $arxiv_year=$matches[4];        
      // Scientometrics, volume 69, number 3, pp. 669-687, 2006
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+), volume ([0-9]+), number ([0-9]+), pp\. (\d{1,5}-\d{1,5}), (\d{4})$~u", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[4];
        $arxiv_year=$matches[5];
        $arxiv_issue=$matches[3]; 
      // International Journal of Geographical Information Science, 23(7), 2009, 823-837.
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.]+), (\d{1,3})\((\d{1,3})\), (\d{4}), (\d{1,5}-\d{1,5})\.$~", $journal_data, $matches)) {
        $arxiv_journal=$matches[1];
        $arxiv_volume=$matches[2];
        $arxiv_pages=$matches[5];
        $arxiv_year=$matches[4];
        $arxiv_issue=$matches[3];
      // journal of Statistical Mechanics: Theory and Experiment, 2008 July
      } elseif (preg_match("~^([a-zA-ZÀ-ÿ \.\:]+), (\d{4}) ([a-zA-ZÀ-ÿ])+$~", $journal_data, $matches)) {  
         // not enough to reliably go on
      // ICALP 2013, Part I, LNCS 7965, 2013, pp 497-503
       } elseif (preg_match("~^ICALP .*$~", $journal_data, $matches)) {  
          // not wanting to figure this out
      // Future formats -- print diagnostic message
      } else {
        if (getenv('TRAVIS')) {
          trigger_error("Unexpected data found in parse_plain_text_reference. " . $journal_data );
        } else {
          report_info("Unexpected data found in parse_plain_text_reference.  Citation bot cannot parse. Please report. " . $journal_data );
        }
      }
      if ($arxiv_journal && $arxiv_year && (intval($arxiv_year) > 1900) && (intval($arxiv_year) < (1+intval(date("Y"))))) { // if no journal then doomed.  If bad date then doomed.
        if ($arxiv_year) {
          $current_year = $this_template->get_without_comments_and_placeholders('year');
          if (!$current_year) {
            $current_date = $this_template->get_without_comments_and_placeholders('date');
            if ($current_date && preg_match('~\d{4}~', $current_date, $match)) {
               $current_year = $match[0];
            }
          }
          if (!$current_year
          ||  (preg_match('~\d{4}~', $current_year) && $current_year < $arxiv_year && $upgrade_years)) {
            if ($this_template->has('date')) {
              $this_template->rename('date', 'year',$arxiv_year);
            } else {
              $this_template->add('year',$arxiv_year);
            }
          }
        }
        if ($arxiv_pages) {
           $this_template->add_if_new("pages", str_replace("--", REGEXP_EN_DASH, $arxiv_pages), 'arxiv');
        }
        if ($arxiv_volume) {
          $this_template->add_if_new("volume", $arxiv_volume, 'arxiv');
        }
        if ($arxiv_issue) {
          $this_template->add_if_new("issue", $arxiv_issue, 'arxiv');
        }
        $this_template->add_if_new("journal", wikify_external_text($arxiv_journal), 'arxiv');
        $this_template->forget('publisher'); // This is either bad data, or refers to a preprint, not the journal
      }
} 

?>
