<?php
declare(strict_types=1);
const ZOTERO_GIVE_UP = 5;
const ZOTERO_SKIPS = 100;
const ERROR_DONE = 'ERROR_DONE';

require_once("constants.php");

function query_url_api(array $ids, array $templates) : void {
  global $SLOW_MODE;
  global $zotero_failures_count;
  global $ch_zotero;
  global $zotero_announced;
  if (!isset($zotero_failures_count) || getenv('TRAVIS')) $zotero_failures_count = 0;
  if (!$SLOW_MODE) return; // Zotero takes time
  
  $ch_zotero = curl_init(ZOTERO_ROOT);
  curl_setopt($ch_zotero, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch_zotero, CURLOPT_USERAGENT, "Citation_bot");  
  curl_setopt($ch_zotero, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
  curl_setopt($ch_zotero, CURLOPT_RETURNTRANSFER, TRUE);   
  if (getenv('TRAVIS')) { // try harder in TRAVIS to make tests more successful and make it his zotero less often
    curl_setopt($ch_zotero, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch_zotero, CURLOPT_TIMEOUT, 145);
  } else {
    // @codeCoverageIgnoreStart
    curl_setopt($ch_zotero, CURLOPT_CONNECTTIMEOUT, 1);
    $url_count = 0;
    foreach ($templates as $template) {
     if (!$template->blank(['url', 'chapter-url', 'chapterurl'])) {
       $url_count = $url_count + 1;
     }
    }
    if ($url_count < 5) {
      curl_setopt($ch_zotero, CURLOPT_TIMEOUT, 15);
    } elseif ($url_count < 25) {
      curl_setopt($ch_zotero, CURLOPT_TIMEOUT, 10);
    } else {
      curl_setopt($ch_zotero, CURLOPT_TIMEOUT, 5);
    }
    // @codeCoverageIgnoreEnd
  }

  $zotero_announced = 1;
  foreach ($templates as $template) {
     expand_by_zotero($template);
  }
  if (!getenv('TRAVIS')) { // These are pretty reliable, unlike random urls
      curl_setopt($ch_zotero, CURLOPT_TIMEOUT, 10);  // @codeCoverageIgnore
  }
  $zotero_announced = 2;
  foreach ($templates as $template) {
       if ($template->has('biorxiv')) {
         if ($template->blank('doi')) {
           $template->add_if_new('doi', '10.1101/' . $template->get('biorxiv'));
           expand_by_doi($template, TRUE);  // this data is better than zotero
         } elseif (strstr($template->get('doi') , '10.1101') === FALSE) {
           expand_doi_with_dx($template, '10.1101/' . $template->get('biorxiv'));  // dx data is better than zotero
           expand_by_zotero($template, 'https://dx.doi.org/10.1101/' . $template->get('biorxiv'));  // Rare case there is a different DOI
         }
       }
       if ($template->has('citeseerx')) expand_by_zotero($template, 'http://citeseerx.ist.psu.edu/viewdoc/summary?doi=' . $template->get('citeseerx'));
       if ($template->has('hdl'))       expand_by_zotero($template, 'https://hdl.handle.net/' . $template->get('hdl'));
       //  Has a CAPCHA --  if ($template->has('jfm'))       expand_by_zotero($template, 'https://zbmath.org/?format=complete&q=an:' . $template->get('jfm'));
       //  Has a CAPCHA --  if ($template->has('zbl'))       expand_by_zotero($template, 'https://zbmath.org/?format=complete&q=an:' . $template->get('zbl'));
       //  Has "MR: Matches for: MR=154360" title -- if ($template->has('mr'))        expand_by_zotero($template, 'https://mathscinet.ams.org/mathscinet-getitem?mr=' . $template->get('mr'));
       //   if ($template->has('mr') && $template->blank('doi')) {
       // Do NOT do.  This is a DOI to the ariticle reviewed.  Not the review itself
       //     $mr_data = @file_get_contents('https://mathscinet.ams.org/mathscinet-getitem?mr=' . $template->get('mr'));
       //     if (preg_match('~<a class="link" target="_blank" href="/leavingmsn\?url=https://doi\.org/(10\.[^\s"]+)">Article</a>~i', $mr_data, $matches)) {
       //       $template->add_if_new('doi', $matches[1]);
       //       expand_by_doi($template, TRUE);
       //     }
       //   }
       if ($template->has('osti'))      expand_by_zotero($template, 'https://www.osti.gov/biblio/' . $template->get('osti'));
       if ($template->has('rfc'))       expand_by_zotero($template, 'https://tools.ietf.org/html/rfc' . $template->get('rfc'));
       if ($template->has('ssrn'))      expand_by_zotero($template, 'https://papers.ssrn.com/sol3/papers.cfm?abstract_id=' . $template->get('ssrn'));
       if ($template->has('doi')) {
         $doi = $template->get('doi');
         if (!doi_active($doi) && doi_works($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
           expand_by_zotero($template, 'https://dx.doi.org/' . urlencode($doi));  // DOIs without meta-data
         }
       }
  }
  curl_close($ch_zotero);
}

function query_ieee_webpages(array $templates) : void {
  $ch_ieee = curl_init();
  curl_setopt($ch_ieee, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch_ieee, CURLOPT_HEADER, FALSE);
  
  foreach (['url', 'chapter-url', 'chapterurl'] as $kind) {
   foreach ($templates as $template) {
    if ($template->blank('doi') && preg_match("~^https://ieeexplore\.ieee\.org/document/(\d{5,})$~", $template->get($kind), $matches_url)) {
       usleep(100000); // 0.10 seconds
       curl_setopt($ch_ieee, CURLOPT_URL, $template->get($kind));
       $return = (string) @curl_exec($ch_ieee);
       if ($return !== "" && preg_match_all('~"doi":"(10\.\d{4}/[^\s"]+)"~', $return, $matches, PREG_PATTERN_ORDER)) {
          $dois = array_unique($matches[1]);
          if (count($dois) === 1) {
            if ($template->add_if_new('doi', $dois[0])) {
              if (strpos($template->get('doi'), $matches_url[1]) !== FALSE && doi_works($template->get('doi'))) {
                $template->forget($kind);  // It is one of those DOIs with the document number in it
              }
            }
          }
       }
    } elseif ($template->has('doi') && preg_match("~^https://ieeexplore\.ieee\.org/document/(\d{5,})$~", $template->get($kind), $matches_url) && doi_works($template->get('doi'))) {
       usleep(100000); // 0.10 seconds
       curl_setopt($ch_ieee, CURLOPT_URL, $template->get($kind));
       $return = (string) @curl_exec($ch_ieee);
       if ($return != "" && strpos($return, "<title> -  </title>") !== FALSE) {
         report_forget("Existing IEEE no longer works - dropping URL"); // @codeCoverageIgnore
         $template->forget($kind);                                      // @codeCoverageIgnore
       }
    }
   }
  }
  curl_close($ch_ieee);
}

function drop_urls_that_match_dois(array $templates) : void {
  // Now that we have expanded URLs, try to lose them
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 20); // No infinite loops for us, 20 for Elsivier and Springer websites
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4); 
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_COOKIEFILE, "");
  curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
  foreach ($templates as $template) {
    $doi = $template->get_without_comments_and_placeholders('doi');
    if ($template->has('url')) {
       $url = $template->get('url');
       $url_kind = 'url';
    } elseif ($template->has('chapter-url')) {
       $url = $template->get('chapter-url');
       $url_kind = 'chapter-url';
    } elseif ($template->has('chapterurl')) {
       $url = $template->get('chapterurl');
       $url_kind = 'chapterurl';
    } else {
       $url = '';
       $url_kind = '';
    }
    if ($doi &&  // IEEE code does not require "not incomplete"
        $url &&
        !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
        $template->blank(DOI_BROKEN_ALIASES) &&
        preg_match("~^https?://ieeexplore\.ieee\.org/document/\d{5,}/?$~", $url) && strpos($doi, '10.1109') === 0) {
          report_forget("Existing IEEE resulting from equivalent DOI; dropping URL");
          $template->forget($url_kind);
    }
    
    if ($doi &&
        $url &&
        !$template->profoundly_incomplete() &&
        !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
        (strpos($doi, '10.1093/') === FALSE) &&
        $template->blank(DOI_BROKEN_ALIASES))
    {
       if (str_ireplace(PROXY_HOSTS_TO_DROP,'', $url) !== $url) {
          report_forget("Existing proxy URL resulting from equivalent DOI; dropping URL");
          $template->forget($url_kind);
       } elseif (preg_match('~www.sciencedirect.com/science/article/B[^/\-]*\-[^/\-]+\-[^/\-]+/~', $url)) {
          report_forget("Existing Invalid ScienceDirect URL when DOI is present; dropping URL");
          $template->forget($url_kind);
       } elseif (preg_match('~www.sciencedirect.com/science/article/pii/\S{0,16}$~i', $url)) { // Too Short
          report_forget("Existing Invalid ScienceDirect URL when DOI is present; dropping URL");
          $template->forget($url_kind);
       } elseif (preg_match('~www.springerlink.com/content~i', $url)) { // Dead website
          report_forget("Existing Invalid Springer Link URL when DOI is present; dropping URL");
          $template->forget($url_kind);
       } elseif (str_ireplace('insights.ovid.com/pubmed','', $url) !== $url && $template->has('pmid')) {
          report_forget("Existing OVID URL resulting from equivalent PMID and DOI; dropping URL");
          $template->forget($url_kind);
       } elseif ($template->has('pmc') && str_ireplace('iopscience.iop.org','', $url) !== $url) {
          report_forget("Existing IOP URL resulting from equivalent DOI; dropping URL");
          $template->forget($url_kind);
       } elseif (str_ireplace('journals.lww.com','', $url) !== $url) {
          report_forget("Existing Outdated LWW URL resulting from equivalent DOI; dropping URL");
          $template->forget($url_kind);
       } elseif (str_ireplace('wkhealth.com','', $url) !== $url) {
          report_forget("Existing Outdated WK Health URL resulting from equivalent DOI; dropping URL");
          $template->forget($url_kind);
       } elseif ($template->has('pmc') && str_ireplace('bmj.com/cgi/pmidlookup','', $url) !== $url && $template->has('pmid')) {
          report_forget("Existing The BMJ URL resulting from equivalent PMID and DOI; dropping URL");
          $template->forget($url_kind);
       } elseif ($template->get('doi-access') === 'free' && $template->get('url-status') === 'dead' && $url_kind === 'url') {
          report_forget("Existing free DOI; dropping dead URL");
          $template->forget($url_kind);
       } elseif ($template->has('pmc')) {
          curl_setopt($ch, CURLOPT_URL, "https://dx.doi.org/" . urlencode($doi));
          if (@curl_exec($ch)) {
            $redirectedUrl_doi = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);  // Final URL
            if (stripos($redirectedUrl_doi, 'cookie') !== FALSE) break;
            if (stripos($redirectedUrl_doi, 'denied') !== FALSE) break;
            $redirectedUrl_doi = url_simplify($redirectedUrl_doi);
            $url_short         = url_simplify($url);
            if ( preg_match('~^https?://.+/pii/?(S?\d{4}[^/]+)~i', $redirectedUrl_doi, $matches ) === 1 ) { // Grab PII numbers
                 $redirectedUrl_doi = $matches[1] ;  // @codeCoverageIgnore 
            }
            if (stripos($url_short, $redirectedUrl_doi) !== FALSE ||
                stripos($redirectedUrl_doi, $url_short) !== FALSE) {
               report_forget("Existing canonical URL resulting from equivalent DOI; dropping URL");
               $template->forget($url_kind);
            } else { // See if $url redirects
               curl_setopt($ch, CURLOPT_URL, $url);
               if (@curl_exec($ch)) {
                  $redirectedUrl_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                  $redirectedUrl_url = url_simplify($redirectedUrl_url);
                  if (stripos($redirectedUrl_url, $redirectedUrl_doi) !== FALSE ||
                      stripos($redirectedUrl_doi, $redirectedUrl_url) !== FALSE) {
                    report_forget("Existing canonical URL resulting from equivalent DOI; dropping URL");
                    $template->forget($url_kind);
                  }
               }
            }
          }
       }
    }
  }
  curl_close($ch);
  @strtok('',''); // Free internal buffers
}

function zotero_request(string $url) : string {
  global $zotero_failures_count;
  global $ch_zotero;
  global $BLOCK_ZOTERO_SEARCH;

  curl_setopt($ch_zotero, CURLOPT_POSTFIELDS, $url);  
  if ($BLOCK_ZOTERO_SEARCH) return ERROR_DONE;
  
  $zotero_response = (string) @curl_exec($ch_zotero);
  if ($zotero_response == '') {
    // @codeCoverageIgnoreStart
    report_warning(curl_error($ch_zotero) . "   For URL: " . $url);
    if (strpos(curl_error($ch_zotero), 'timed out after') !== FALSE) {
      $zotero_failures_count = $zotero_failures_count + 1;
      if ($zotero_failures_count > ZOTERO_GIVE_UP) {
        report_warning("Giving up on URL expansion for a while");
        $zotero_failures_count = $zotero_failures_count + ZOTERO_SKIPS;
      }
    }
    $zotero_response = ERROR_DONE;
    // @codeCoverageIgnoreEnd
  }
  return $zotero_response;
}

function expand_by_zotero(Template &$template, ?string $url = NULL) : bool {
  global $zotero_failures_count;
  global $zotero_announced;
  if ($zotero_failures_count > ZOTERO_GIVE_UP) {
    $zotero_failures_count = $zotero_failures_count - 1;                      // @codeCoverageIgnore
    if (ZOTERO_GIVE_UP == $zotero_failures_count) $zotero_failures_count = 0; // @codeCoverageIgnore
  }
  if ($zotero_failures_count > ZOTERO_GIVE_UP) return FALSE;
  $access_date = 0;
  $url_kind = '';
  if (is_null($url)) {
     if (in_array((string) $template->get('url-status'),  ['usurped', 'unfit', 'dead'])) return FALSE;
     $access_date = (int) strtotime(tidy_date($template->get('accessdate') . ' ' . $template->get('access-date')));
     $archive_date = (int) strtotime(tidy_date($template->get('archivedate') . ' ' . $template->get('archive-date')));
     if ($access_date && $archive_date) {
       $access_date = min($access_date, $archive_date); // Whichever was first
     } elseif ($archive_date) {
       $access_date = $archive_date;
     }
     if ($template->has('url')) {
       $url = $template->get('url');
       $url_kind = 'url';
     } elseif ($template->has('chapter-url')) {
       $url = $template->get('chapter-url');
       $url_kind = 'chapter-url';
     } elseif ($template->has('chapterurl')) {
       $url = $template->get('chapterurl');
       $url_kind = 'chapterurl';
     } else {
       return FALSE;
     }
  }

  if (!$template->profoundly_incomplete($url)) return FALSE; // Only risk unvetted data if there's little good data to sully
  
  if(stripos($url, 'CITATION_BOT_PLACEHOLDER') !== FALSE) return FALSE; // That's a bad url
  
  $bad_url = implode('|', ZOTERO_AVOID_REGEX);
  if(preg_match("~^https?://(?:www\.|)(?:" . $bad_url . ")~i", $url)) return FALSE; 

  if ($zotero_announced === 1) {
    report_action("Using Zotero translation server to retrieve details from URLs.");
    $zotero_announced = 0;
  } elseif ($zotero_announced === 2) {
    report_action("Using Zotero translation server to retrieve details from identifiers.");
    $zotero_announced = 0;
  }
  $zotero_response = zotero_request($url);
  return process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date);
}

function process_zotero_response(string $zotero_response, Template &$template, string $url, string $url_kind, int $access_date) : bool {
  if ($zotero_response === ERROR_DONE) return FALSE;  // Error message already printed in zotero_request()
 
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
    case 'No items returned from any translator':
      report_info("Remote page not interpretable for URL ". $url);
      return FALSE;
    case 'An error occurred during translation. Please check translation with the Zotero client.':
      report_info("An error occurred during translation for URL ". $url);
      return FALSE;
  }
  
  if (strpos($zotero_response, '502 Bad Gateway') !== FALSE) {
    report_warning("Bad Gateway error for URL ". $url);
    return FALSE;
  }
  if (strpos($zotero_response, '503 Service Temporarily Unavailable') !== FALSE) {
    report_warning("Temporarily Unavailable error for URL ". $url);  // @codeCoverageIgnore
    return FALSE;                                                    // @codeCoverageIgnore
  }
  $zotero_data = @json_decode($zotero_response, FALSE);
  if (!isset($zotero_data)) {
    report_warning("Could not parse JSON for URL ". $url . ": " . $zotero_response);
    return FALSE;
  } elseif (!is_array($zotero_data)) {
    if (is_object($zotero_data)) {
      $zotero_data = (array) $zotero_data;
    } else {
      report_warning("JSON did not parse correctly for URL ". $url . ": " . $zotero_response);
      return FALSE;
    }
  }
  if (!isset($zotero_data[0])) {
    $result = $zotero_data;
  } else {
    $result = $zotero_data[0];
  }
  $result = (object) $result ;
  
  if (!isset($result->title)) {
    report_warning("Did not get a title for URL ". $url . ": " . $zotero_response);  // @codeCoverageIgnore
    return FALSE;                                                                    // @codeCoverageIgnore
  }
  if (substr(strtolower(trim($result->title)), 0, 9) == 'not found') {
    report_info("Could not resolve URL ". $url);
    return FALSE;
  }
  
  // Reject if we find more than 5 or more than 10% of the characters are �.  This means that character
  // set was not correct in Zotero and nothing is good.  We allow a couple of � for German umlauts that arer easily fixable by humans.
  // We also get a lot of % and $ if the encoding was something like iso-2022-jp and converted wrong
  $bad_count = mb_substr_count($result->title, '�') + mb_substr_count($result->title, '$') + mb_substr_count($result->title, '%');
  $total_count = mb_strlen($result->title);
  if (isset($result->bookTitle)) {
    $bad_count += mb_substr_count($result->bookTitle, '�') + mb_substr_count($result->bookTitle, '$') + mb_substr_count($result->bookTitle, '%');
    $total_count += mb_strlen($result->bookTitle);
  }
  if (($bad_count > 5) || ($total_count > 1 && (($bad_count/$total_count) > 0.1))) {
    report_info("Could parse unicode characters in ". $url);
    return FALSE;
  }
  
  report_info("Retrieved info from ". $url);
  // Verify that Zotero translation server did not think that this was a website and not a journal
  if (strtolower(substr(trim($result->title), -9)) === ' on jstor') {  // Not really "expanded", just add the title without " on jstor"
    $template->add_if_new('title', substr(trim($result->title), 0, -9)); // @codeCoverageIgnore
    return FALSE;  // @codeCoverageIgnore
  }
  
  $test_data = '';
  if (isset($result->bookTitle)) $test_data .= $result->bookTitle . '  ';
  if (isset($result->title))     $test_data .= $result->title;
  foreach (BAD_ZOTERO_TITLES as $bad_title ) {
      if (mb_stripos($test_data, $bad_title) !== FALSE) {
        report_info("Received invalid title data for URL ". $url . ": $test_data");
        return FALSE;
      }
  }
  if (isset($result->bookTitle)) {
   foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
      if (str_i_same($result->bookTitle, $bad_title)) {
        report_info("Received invalid book title data for URL ". $url . ": $result->bookTitle");
        return FALSE;
      }
   }
  }
  if (isset($result->title)) {
   foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
      if (str_i_same($result->title, $bad_title)) {
        report_info("Received invalid title data for URL ". $url . ": $result->title");
        return FALSE;
      }
   }
  }
  if (isset($result->publicationTitle)) {
   foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
      if (str_i_same($result->publicationTitle, $bad_title)) {
        report_info("Received invalid publication title data for URL ". $url . ": $result->publicationTitle");
        return FALSE;
      }
   }
  }
  
  if (isset($result->extra)) { // [extra] => DOI: 10.1038/546031a has been seen in the wild
    if (preg_match('~\sdoi:\s?([^\s]+)\s~i', ' ' . $result->extra . ' ', $matches)) {
      if (!isset($result->DOI)) $result->DOI = trim($matches[1]);
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
    }
    if (preg_match('~\stype:\s?([^\s]+)\s~i', ' ' . $result->extra . ' ', $matches)) { // [extra] => type: dataset has been seen in the wild
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
    }
    if (preg_match('~\sPMID: (\d+)\s+PMCID: PMC(\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
      $template->add_if_new('pmid', $matches[1]);
      $template->add_if_new('pmc',  $matches[2]);
    }
    if (preg_match('~\sPMID: (\d+), (\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
      if ($matches[1] === $matches[2]) {
        $template->add_if_new('pmid', $matches[1]);
      }
    }
    if (preg_match('~\sIMDb ID: ((?:tt|co|nm)\d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
    }
    if (preg_match('~\s(original-date: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (trim($result->extra) !== '') {
      report_minor_error("Unhandled extra data: " . $result->extra); // @codeCoverageIgnore
    }
  } 
  
  if ( isset($result->DOI) && $template->blank('doi')) {
    if (preg_match('~^(?:https://|http://|)(?:dx\.|)doi\.org/(.+)$~i', $result->DOI, $matches)) {
       $result->DOI = $matches[1];
    }
    $possible_doi = sanitize_doi($result->DOI);
    if (doi_works($possible_doi)) {
      $template->add_if_new('doi', $possible_doi);
      expand_by_doi($template);
      if (stripos($url, 'jstor')) check_doi_for_jstor($template->get('doi'), $template);
      if (!$template->incomplete() && doi_active($template->get('doi')) && !preg_match(REGEXP_DOI_ISSN_ONLY, $template->get('doi')) && $url_kind != '') {
          if ((str_ireplace(CANONICAL_PUBLISHER_URLS, '', $template->get($url_kind)) != $template->get($url_kind))) { // This is the use a replace to see if a substring is present trick
            report_forget("Existing canonical URL resulting in equivalent DOI; dropping URL");
            $template->forget($url_kind);
          }
      }
      if (!$template->profoundly_incomplete()) return TRUE;
    }
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
  if (str_i_same(substr((string) @$result->publicationTitle, 0, 4), 'http') ||
      str_i_same(substr((string) @$result->bookTitle, 0, 4), 'http') ||
      str_i_same(substr((string) @$result->title, 0, 4), 'http')) {
    report_info("URL returned in Journal/Newpaper/Title/Chapter field for " . $url);  // @codeCoverageIgnore
    return FALSE;                                                                     // @codeCoverageIgnore
  }
  
  if (isset($result->bookTitle)) {
    $result->bookTitle = preg_replace('~\s*\(pdf\)$~i', '', (string) $result->bookTitle);
    $result->bookTitle = preg_replace('~^\(pdf\)\s*~i', '', (string) $result->bookTitle);
    $result->bookTitle = preg_replace('~ \- ProQuest\.?~i', '', (string) $result->bookTitle);
  }
  if (isset($result->title)) {
    $result->title = preg_replace('~\s*\(pdf\)$~i', '', (string) $result->title);
    $result->title = preg_replace('~^\(pdf\)\s*~i', '', (string) $result->title);
    $result->title = preg_replace('~ \- ProQuest\.?~i', '', (string) $result->title);
  }
  
  if (isset($result->bookTitle)) {
    $template->add_if_new('title', (string) $result->bookTitle);
    if (isset($result->title))      $template->add_if_new('chapter',   (string) $result->title);
    if (isset($result->publisher))  $template->add_if_new('publisher', (string) $result->publisher);
  } else {
    if (isset($result->title))      $template->add_if_new('title'  , (string) $result->title);
    if (isset($result->itemType) && ($result->itemType === 'book' || $result->itemType === 'bookSection')) {
       if (isset($result->publisher))  $template->add_if_new('publisher', (string) $result->publisher);
    }
  }

  if ( isset($result->issue))            $template->add_if_new('issue'  , (string) $result->issue);
  if ( isset($result->pages))            $template->add_if_new('pages'  , (string) $result->pages);
  if (isset($result->itemType) && $result->itemType == 'newspaperArticle') {
    if ( isset($result->publicationTitle)) $template->add_if_new('newspaper', (string) $result->publicationTitle);
  } else {
    if ( isset($result->publicationTitle)) {
      if ((!$template->has('title') || !$template->has('chapter')) && // Do not add if already has title and chapter
          (stripos((string) $result->publicationTitle, ' edition') === FALSE)) {  // Do not add if "journal" includes "edition"
        $template->add_if_new('journal', (string) $result->publicationTitle);
      }
    }
  }
  if ( isset($result->volume) 
  &&   strpos($result->volume, "(") === FALSE ) $template->add_if_new('volume', (string) $result->volume);
  if ( isset($result->date) && strlen($result->date)>3)$template->add_if_new('date', tidy_date($result->date));
  if ( isset($result->series) && stripos($url, 'portal.acm.org')===FALSE)  $template->add_if_new('series' , (string) $result->series);
  if ( isset($result->author[0]) && !isset($result->author[1]) &&
      !author_is_human(@$result->author[0][0] . ' ' . @$result->author[0][1])) {
    unset($result->author[0]); // Do not add a single non-human author
  }
  $i = 0;
  while (isset($result->author[$i])) {
      if (author_is_human(@$result->author[$i][0] . ' ' . @$result->author[$i][1])) $template->validate_and_add('author' . ($i+1), (string) @$result->author[$i][1], (string) @$result->author[$i][0],
                                      isset($result->rights) ? (string) $result->rights : '', FALSE);
      $i++;
  }
  
  // see https://www.mediawiki.org/wiki/Citoid/itemTypes
  if (isset($result->itemType)) {
    switch ($result->itemType) {
      case 'book':
      case 'bookSection':
        // Too much bad data to risk switching journal to book or vice versa.
        // also reject 'review' 
        if ($template->wikiname() === 'cite web' &&
            stripos($url . @$result->title . @$result->bookTitle . @$result->publicationTitle, 'review') === FALSE &&
            stripos($url, 'archive.org') === FALSE && !preg_match('~^https?://[^/]*journal~', $url)) {
          $template->change_name_to('cite book');
        }
        break;
      case 'journalArticle':
      case 'conferencePaper':
      case 'report':  // ssrn uses this
        if($template->wikiname() == 'cite web' && str_ireplace(NON_JOURNAL_WEBSITES, '', $url) === $url) {
          $template->change_name_to('cite journal');
        }
        break;
      case 'magazineArticle':
        if($template->wikiname() == 'cite web') {
          $template->change_name_to('cite magazine');
        }
        break;      
      case 'newspaperArticle':
        $template->change_name_to('cite news'); 
        break;
      case 'webpage':
      case 'blogPost':
        
        break; // Could be a journal article or a genuine web page.
        
      case 'thesis':
        $template->change_name_to('cite thesis');
        if (isset($result->university)) $template->add_if_new('publisher' , $result->university);
        if (isset($result->thesisType) && $template->blank(['type', 'medium', 'degree'])) {
          $template->add_if_new('type' , (string) $result->thesisType); // Prefer type since it exists in cite journal too
        }
        break;
        
      case 'videoRecording':
      case 'film':
      case 'presentation';     // @codeCoverageIgnore
      case 'computerProgram';  // @codeCoverageIgnore
        // Nothing special that we know of yet
        break;

      default:                                                                         // @codeCoverageIgnore
        report_minor_error("Unhandled itemType: " . $result->itemType . " for $url");  // @codeCoverageIgnore
    }
    
    if (in_array($result->itemType, ['journalArticle', 'newspaperArticle', 'report', 'magazineArticle', 'thesis'])) {
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
            default:                                                               // @codeCoverageIgnore
              report_minor_error("Unrecognized creator type: " . $creatorType);    // @codeCoverageIgnore
              $authorParam = '';                                                   // @codeCoverageIgnore
          }
         if ($authorParam && author_is_human($result->creators[$i]->firstName . ' ' . $result->creators[$i]->lastName)) {
                        $template->validate_and_add($authorParam, (string) $result->creators[$i]->lastName, (string) $result->creators[$i]->firstName,
                        isset($result->rights) ? (string) $result->rights : '', FALSE);
         }
        }
        $i++;
      }
    }
    if (stripos(trim($template->get('publisher')), 'Associated Press') !== FALSE &&
        stripos($url, 'ap.org') === FALSE  ) {
       if ($template->wikiname() === 'cite news') {
          $template->rename('publisher', 'agency'); // special template parameter just for them
       }
       if (stripos(trim($template->get('author')), 'Associated Press') === 0) $template->forget('author'); // all too common
    }
    if (stripos(trim($template->get('publisher')), 'Reuters') !== FALSE &&
        stripos($url, 'reuters.org') === FALSE  ) {
       if ($template->wikiname() === 'cite news') {
          $template->rename('publisher', 'agency'); // special template parameter just for them
       }
       if (stripos(trim($template->get('author')), 'Reuters') === 0) $template->forget('author'); // all too common
    }
  }
  return TRUE;
}

function url_simplify(string $url) : string {
  $url = str_replace('/action/captchaChallenge?redirectUri=', '', $url);
  $url = urldecode($url);
  // IEEE is annoying
  if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
    $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
  }
  $url = $url . '/';
  $url = str_replace(['/abstract/', '/full/', '/full+pdf/', '/pdf/', '/document/', '/html/', '/html+pdf/', '/abs/', '/epdf/', '/doi/', '/xprint/', '/print/', '.short', '.long', '.abstract', '.full', '///', '//'],
                     ['/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/'], $url);
  $ul = substr($url, 0, -1); // Remove the ending slash we added
  $url = strtok($url, '?#');
  $url = str_ireplace('https', 'http', $url);
  return $url;
}

?>
