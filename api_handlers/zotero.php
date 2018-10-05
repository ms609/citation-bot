<?php 
function query_url_api($ids, $templates) {
  report_action("Using Zotero translation server to retrieve details from URLs.");
  foreach ($templates as $template) {
    if ($template->has('url')) {
      expand_by_zotero($template);
    }
  }
}

function zotero_request($url) {
  
  #$ch = curl_init('http://' . TOOLFORGE_IP . '/translation-server/web');
  $ch = curl_init('http://tools.wmflabs.org/translation-server/web');
  
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_USERAGENT, "Citation_bot");  
  curl_setopt($ch, CURLOPT_POSTFIELDS, $url);  
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);     
  curl_setopt($ch, CURLOPT_REFERER, 'https://en.wikipedia.org/wiki/User:Citation_bot');
  
  $zotero_response = curl_exec($ch);
  if ($zotero_response === FALSE) {
    throw new Exception(curl_error($ch), curl_errno($ch));
  }
  curl_close($ch);
  return $zotero_response;
}
  
function expand_by_zotero(&$template, $url = NULL) {
  if (!$template->profoundly_incomplete()) return FALSE; // Only risk unvetted data if there's little good data to sully
  if (is_null($url)) $url = $template->get('url');
  if (!$url) {
    report_info("Aborting Zotero expansion: No URL found");
    return FALSE;
  }

  if(preg_match("~^https?://books\.google\.~", $url)) return FALSE;  // We have special google gooks code
  if(stristr($url, 'CITATION_BOT_PLACEHOLDER') !== FALSE) return FALSE; // That's a bad url
  
  $zotero_response = zotero_request($url);
  switch (trim($zotero_response)) {
    case '':
      report_info("Nothing returned for URL $url");
      return FALSE;
    case 'Internal Server Error':
      report_info("Internal server error with URL $url");
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
  
  report_info("Retrieved info from ". $url);
  // Verify that Zotero translation server did not think that this was a website and not a journal
  if (strtolower(substr(trim($result->title), -9)) === ' on jstor') {
    $template->add_if_new('title', substr(trim($result->title), 0, -9)); // Add the title without " on jstor"
    return FALSE; // Not really "expanded"
  }
  // var_dump($result); for debug
  
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
  
  if ( isset($result->DOI)) {
    $template->add_if_new('doi', $result->DOI);
    return TRUE; // We can just use this.  If this is wrong, then we should not trust anything else anyway
  }

  if (isset($result->bookTitle)) {
    $template->add_if_new('title', $result->bookTitle);
    if (isset($result->title))      $template->add_if_new('chapter',   $result->title);
    if (isset($result->publisher))  $template->add_if_new('publisher', $result->publisher);
  } else {
    if (isset($result->title))      $template->add_if_new('title'  , $result->title);
  }
    
  if ( isset($result->ISBN))             $template->add_if_new('isbn'   , $result->ISBN);
  if ( isset($result->issue))            $template->add_if_new('issue'  , $result->issue);
  if ( isset($result->pages))            $template->add_if_new('pages'  , $result->pages);
  if ( isset($result->publicationTitle)) $template->add_if_new('journal', $result->publicationTitle);
  if ( isset($result->volume))           $template->add_if_new('volume' , $result->volume);
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
        // Too much bad data to risk switching journal to book or vice versa.
        if ($template->wikiname() == 'cite web') 
          $template->change_name_to('cite book');      
        break;
      case 'journalArticle': 
        if($template->wikiname() == 'cite web')
          $template->change_name_to('cite journal');
        break;
      case 'newspaperArticle': 
        if (isset($result->libraryCatalog) && in_array($result->libraryCatalog, WEB_NEWSPAPERS)) break;
        $template->change_name_to('cite newspaper'); 
        break;
      case 'webpage': 
        break; // Could be a journal article or a genuine web page.
      default: report_warning("Unhandled itemType: " . $result->itemType);
    }
    
    $i = 0; $author_i = 0; $editor_i = 0; $translator_i = 0;
    if (in_array($result->itemType, ['journalArticle', 'newspaperArticle'])) {
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
              report_warning("Unrecognised creator type: " . $creatorType);
          }
          $template->validate_and_add($authorParam, $result->creators[$i]->lastName, $result->creators[$i]->firstName,
                                      isset($result->rights) ? $result->rights : '');
        }
        $i++;
      }
    }
  }
  return TRUE;
}

?>
