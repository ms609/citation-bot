<?php 
function query_url_api($ids, $templates) {
  report_action("Using Zotero translation server to retrieve details from URLs.");
  foreach ($templates as $template) {
    if ($template->has('url')) {
      expand_by_zotero($template);
    }
  }
  report_action("Using Zotero translation server to retrieve details from identifiers.");
  foreach ($templates as $template) {
       if ($template->has('biorxiv')) {
         if ($template->blank('doi')) {
           $template->add_if_new('doi', '10.1101/' . $template->get('biorxiv'));
         } elseif (strstr($template->get('doi') , '10.1101') === FALSE) {
           expand_by_zotero($template, 'https://dx.doi.org/10.1101/' . $template->get('biorxiv'));  // Rare case there is a different DOI
         }
       }
       if ($template->has('citeseerx')) expand_by_zotero($template, 'http://citeseerx.ist.psu.edu/viewdoc/summary?doi=' . $template->get('citeseerx'));
       if ($template->has('hdl'))       expand_by_zotero($template, 'https://hdl.handle.net/' . $template->get('hdl'));
       //  Has a CAPCHA --  if ($template->has('jfm'))       expand_by_zotero($template, 'https://zbmath.org/?format=complete&q=an:' . $template->get('jfm'));
       //  Has a CAPCHA --  if ($template->has('zbl'))       expand_by_zotero($template, 'https://zbmath.org/?format=complete&q=an:' . $template->get('zbl'));
       //  Has "MR: Matches for: MR=154360" title -- if ($template->has('mr'))        expand_by_zotero($template, 'https://mathscinet.ams.org/mathscinet-getitem?mr=' . $template->get('mr'));
       if ($template->has('osti'))      expand_by_zotero($template, 'https://www.osti.gov/biblio/' . $template->get('osti'));
       if ($template->has('rfc'))       expand_by_zotero($template, 'https://tools.ietf.org/html/rfc' . $template->get('rfc'));
       if ($template->has('ssrn'))      expand_by_zotero($template, 'https://papers.ssrn.com/sol3/papers.cfm?abstract_id=' . $template->get('ssrn'));
       if ($template->has('doi') && !doi_active($template->get('doi')))  expand_by_zotero($template, 'https://dx.doi.org/' . urlencode($template->get('doi'))); // Non-crossref DOIs, such as 10.13140/RG.2.1.1002.9609
  }
}

function zotero_request($url) {
  
  #$ch = curl_init('http://' . TOOLFORGE_IP . '/translation-server/web');
  $ch = curl_init('https://tools.wmflabs.org/translation-server/web');
  
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_USERAGENT, "Citation_bot");  
  curl_setopt($ch, CURLOPT_POSTFIELDS, $url);  
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);   
  if (getenv('TRAVIS')) { // try harder in TRAVIS to make tests more successful and make it his zotero less often
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
  } else {
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
  }
  
  $zotero_response = curl_exec($ch);
  if ($zotero_response === FALSE) {
    report_warning(curl_error($ch) . "   For URL: " . $url);
  }
  curl_close($ch);
  return $zotero_response;
}
  
function expand_by_zotero(&$template, $url = NULL) {
  $access_date = FALSE;
  if (is_null($url)) {
     $access_date = strtotime(tidy_date($template->get('accessdate') . ' ' . $template->get('access-date'))); 
  }
  if (is_null($url)) $url = $template->get('url');
  if (!$url) {
    report_info("Aborting Zotero expansion: No URL found");
    return FALSE;
  }
  if (!$template->profoundly_incomplete($url)) return FALSE; // Only risk unvetted data if there's little good data to sully
  
  if(stristr($url, 'CITATION_BOT_PLACEHOLDER') !== FALSE) return FALSE; // That's a bad url
  
  $bad_url = implode('|', ZOTERO_AVOID_REGEX);
  if(preg_match("~^https?://(?:www\.|)(?:" . $bad_url . ")~i", $url)) return FALSE; 

  $zotero_response = zotero_request($url);
  if ($zotero_response === FALSE) return FALSE;  // Error message already printed
  switch (trim($zotero_response)) {
    case '':
      report_info("Nothing returned for URL $url");
      return FALSE;
    case 'Internal Server Error':
      report_info("Internal server error with URL $url");
      return FALSE;
    case 'Remote page not found':
      report_info("Remote page not found for URL ". $url);
      return FALSE;
  }
  
  if (strpos($zotero_response, '502 Bad Gateway') !== FALSE) {
    report_warning("Bad Gateway error for URL ". $url);
    return FALSE;
  }
  
  $zotero_data = @json_decode($zotero_response, FALSE);
  if (!isset($zotero_data)) {
    report_warning("Could not parse JSON for URL ". $url . ": $zotero_response");
    return FALSE;
  } else if (!is_array($zotero_data) || !isset($zotero_data[0]) || !isset($zotero_data[0]->title)) {
    report_warning("Unsupported response for URL ". $url . ": $zotero_response");
    return FALSE;
  } else {
    $result = $zotero_data[0];
  }
  if (substr(strtolower(trim($result->title)), 0, 9) == 'not found') {
    report_info("Could not resolve URL ". $url);
    return FALSE;
  }
  
  // Reject if we find more than 5 or more than 10% of the characters are �.  This means that character
  // set was not correct in Zotero and nothing is good.  We allow a couple of � for German umlauts that arer easily fixable by humans.
  $bad_count = mb_substr_count($result->title, '�');
  $total_count = mb_strlen($result->title);
  if (isset($result->bookTitle)) {
    $bad_count += mb_substr_count($result->bookTitle, '�');
    $total_count += mb_strlen($result->bookTitle);
  }
  if (($bad_count > 5) || ($total_count > 1 && (($bad_count/$total_count) > 0.1))) {
    report_info("Could parse unicode characters in ". $url);
    return FALSE;
  }
  
  report_info("Retrieved info from ". $url);
  // Verify that Zotero translation server did not think that this was a website and not a journal
  if (strtolower(substr(trim($result->title), -9)) === ' on jstor') {
    $template->add_if_new('title', substr(trim($result->title), 0, -9)); // Add the title without " on jstor"
    return FALSE; // Not really "expanded"
  }
  // fwrite(STDERR, print_r($result, TRUE)); // for debug
  
  $test_data = '';
  if (isset($result->bookTitle)) $test_data .= $result->bookTitle . '  ';
  if (isset($result->title))     $test_data .= $result->title;
  foreach (BAD_ZOTERO_TITLES as $bad_title ) {
      if (stripos($test_data, $bad_title) !== FALSE) {
        report_info("Received invalid title data for URL ". $url . ": $test_data");
        return FALSE;
      }
  }
  foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
      if (strcasecmp($test_data, $bad_title) === 0) {
        report_info("Received invalid title data for URL ". $url . ": $test_data");
        return FALSE;
      }
  }
  
  if (isset($result->extra)) { // [extra] => DOI: 10.1038/546031a has been seen in the wild
    if (preg_match('~\sdoi:\s?([^\s]+)\s~i', ' ' . $result->extra . ' ', $matches)) {
      if (!isset($result->DOI) && !isset($matches[2])) $result->DOI = trim($matches[1]); // Only set if only one DOI
      $result->extra = str_ireplace('doi:', '', $result->extra);
      $result->extra = str_replace(trim($matches[1]), '', $result->extra);
      $result->extra = trim($result->extra);
      if (isset($matches[2])) $result->extra = ''; // Obviously not gonna parse this in any way
    }
    if (preg_match('~\stype:\s?([^\s]+)\s~i', ' ' . $result->extra . ' ', $matches)) { // [extra] => type: dataset has been seen in the wild
      $result->extra = str_ireplace('type:', '', $result->extra);
      $result->extra = str_replace(trim($matches[1]), '', $result->extra);
      $result->extra = trim($result->extra);
    }
    if ($result->extra !== '') {
        if (getenv('TRAVIS')) {
          trigger_error("Unhandled extra data: " . $result->extra);
        } else {
          report_warning("Unhandled extra data: " . $result->extra);
        }
    }
  } 
  
  if ( isset($result->DOI) && $template->blank('doi')) {
    $template->add_if_new('doi', $result->DOI);
    expand_by_doi($template);
    if (stripos($url, 'jstor')) check_doi_for_jstor($template->get('doi'), $template);
    if (!$template->incomplete() && doi_active($template->get('doi')) && !preg_match(REGEXP_DOI_ISSN_ONLY, $template->get('doi')) &&
        (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $url) != $url)) { // This is the use a replace to see if a substring is present trick
      report_forget("Existing canonical URL resulting in equivalent DOI; dropping URL");
      $template->forget('url');
    }
    if (!$template->profoundly_incomplete()) return TRUE;
  }

  if (isset($result->date)) {
    foreach (NO_DATE_WEBSITES as $bad_website ) {
      if (stripos($url, $bad_website) !== FALSE) {
        unset($result->date);
        break;
      }
    }
  }
  
  if ( isset($result->ISBN)) $template->add_if_new('isbn'   , $result->ISBN);
  if ($access_date && isset($result->date)) {
    $new_date = strtotime(tidy_date($result->date));
    if($new_date) { // can compare
      if($new_date > $access_date) {
        report_info("URL appears to have changed since access-date ". $url);
        return FALSE;
      }
    }
  }
  
  if (isset($result->bookTitle)) {
    $result->bookTitle = preg_replace('~\s*\(pdf\)$~i', '', $result->bookTitle);
    $result->bookTitle = preg_replace('~^\(pdf\)\s*~i', '', $result->bookTitle);
  }
  if (isset($result->title)) {
    $result->title = preg_replace('~\s*\(pdf\)$~i', '', $result->title);
    $result->title = preg_replace('~^\(pdf\)\s*~i', '', $result->title);
  }
  
  if (isset($result->bookTitle)) {
    $template->add_if_new('title', $result->bookTitle);
    if (isset($result->title))      $template->add_if_new('chapter',   $result->title);
    if (isset($result->publisher))  $template->add_if_new('publisher', $result->publisher);
  } else {
    if (isset($result->title))      $template->add_if_new('title'  , $result->title);
    if (isset($result->itemType) && ($result->itemType === 'book' || $result->itemType === 'bookSection')) {
       if (isset($result->publisher))  $template->add_if_new('publisher', $result->publisher); 
    }
  }

  if ( isset($result->issue))            $template->add_if_new('issue'  , $result->issue);
  if ( isset($result->pages))            $template->add_if_new('pages'  , $result->pages);
  if (isset($result->itemType) && $result->itemType == 'newspaperArticle') {
    if ( isset($result->publicationTitle)) $template->add_if_new('newspaper', $result->publicationTitle);
  } else {
    if ( isset($result->publicationTitle)) {
      if ((!$template->has('title') || !$template->has('chapter')) && // Do not add if already has title and chapter
          (stripos($result->publicationTitle, ' edition') === FALSE)) {  // Do not add if "journal" includes "edition"
        $template->add_if_new('journal', $result->publicationTitle);
      }
    }
  }
  if ( isset($result->volume) 
  &&   strpos($result->volume, "(") === FALSE ) $template->add_if_new('volume', $result->volume);
  if ( isset($result->date))             $template->add_if_new('date'   , tidy_date($result->date));
  if ( isset($result->series))           $template->add_if_new('series' , $result->series);
  $i = 0;
  while (isset($result->author[$i])) {
      if (isset($result->author[$i][0])) $template->add_if_new('first' . ($i+1), $result->author[$i][0]);
      if (isset($result->author[$i][1])) $template->add_if_new('last'  . ($i+1), $result->author[$i][1]);
      $i++;
  }
  
  if (isset($result->itemType)) {
    switch ($result->itemType) {
      case 'book':
      case 'bookSection':
        // Too much bad data to risk switching journal to book or vice versa.
        if ($template->wikiname() == 'cite web') 
          $template->change_name_to('cite book');      
        break;
      case 'journalArticle':
      case 'report':  // ssrn uses this
        if($template->wikiname() == 'cite web')
          $template->change_name_to('cite journal');
        break;
      case 'magazineArticle':
        if($template->wikiname() == 'cite web')
          $template->change_name_to('cite magazine');
        break;      
      case 'newspaperArticle':
        $template->change_name_to('cite news'); 
        break;
      case 'webpage':
      case 'blogPost':
        
        break; // Could be a journal article or a genuine web page.
      default:
        if (getenv('TRAVIS')) {
          trigger_error("Unhandled itemType: " . $result->itemType . " for $url");
        } else {
          report_warning("Unhandled itemType: " . $result->itemType . " for $url");
        } // see https://www.mediawiki.org/wiki/Citoid/itemTypes
    }
    
    $i = 0; $author_i = 0; $editor_i = 0; $translator_i = 0;
    if (in_array($result->itemType, ['journalArticle', 'newspaperArticle', 'report'])) {
      // Websites often have non-authors listed in metadata
      // "Books" are often bogus
       $i = 0; $author_i = 0; $editor_i = 0; $translator_i = 0;
      while (isset($result->creators[$i])) {
        $creatorType = isset($result->creators[$i]->creatorType) ? $result->creators[$i]->creatorType : 'author';
        if (isset($result->creators[$i]->firstName) && isset($result->creators[$i]->lastName)) {
          switch ($creatorType) {
            case 'author':
              $authorParam = 'author' . ++$author_i;
              break;
            case 'editor':
              $authorParam = 'editor' . ++$editor_i;
              break;
            case 'translator':
              $authorParam = 'translator' . ++$translator_i;
              break;
            default:
              if (getenv('TRAVIS')) {
                trigger_error("Unrecognized creator type: " . $creatorType);
              } else {
                report_warning("Unrecognised creator type: " . $creatorType);
              }
          }
          $template->validate_and_add($authorParam, $result->creators[$i]->lastName, $result->creators[$i]->firstName,
                                      isset($result->rights) ? $result->rights : '');
        }
        $i++;
      }
    }
    if (stripos(trim($template->get('publisher')), 'Associated Press') !== FALSE &&
        stripos($url, 'ap.org') === FALSE  ) {
       if (stripos($template->wikiname(), 'cite news') === 0) {
          $template->rename('publisher', 'agency'); // special template parameter just for them
       }
       if (stripos(trim($template->get('author')), 'Associated Press') === 0) $template->forget('author'); // all too common
    }
    if (stripos(trim($template->get('publisher')), 'Reuters') !== FALSE &&
        stripos($url, 'reuters.org') === FALSE  ) {
       if (stripos($template->wikiname(), 'cite news') === 0) {
          $template->rename('publisher', 'agency'); // special template parameter just for them
       }
       if (stripos(trim($template->get('author')), 'Reuters') === 0) $template->forget('author'); // all too common
    }
  }
  return TRUE;
}

?>
