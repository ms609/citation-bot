<?php
declare(strict_types=1);

require_once 'constants.php';  // @codeCoverageIgnore
require_once 'Template.php';   // @codeCoverageIgnore 

function query_url_api(array $ids, array &$templates) : void {  // Pointer to save memory
   Zotero::query_url_api_class($ids, $templates);
}

final class Zotero {
  private const ZOTERO_GIVE_UP = 5;
  private const ZOTERO_SKIPS = 100;
  private const ERROR_DONE = 'ERROR_DONE'; 
  protected static int $zotero_announced = 0;
  /** @var resource|null $zotero_ch */
  protected static $zotero_ch;
  protected static int $zotero_failures_count = 0;

private static function set_default_ch_zotero() : void {
  /** @phan-suppress-next-line PhanRedundantCondition */
  if ( USE_CITOID ) {
        /** @psalm-suppress PossiblyNullArgument */ 
        curl_setopt_array(self::$zotero_ch,
            [CURLOPT_URL => CITOID_ZOTERO,
            CURLOPT_HTTPHEADER => ['accept: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
            // Defaults used in TRAVIS overiden below when deployed
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45]);
  } else {
        // @codeCoverageIgnoreStart
        /** @psalm-suppress PossiblyNullArgument */ 
        curl_setopt_array(self::$zotero_ch,
            [CURLOPT_URL => ZOTERO_ROOT,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
            // Defaults used in TRAVIS overiden below when deployed
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45]);
        // @codeCoverageIgnoreEnd
    }
}

public static function block_zotero() : void {
  self::$zotero_failures_count = 1000000;  
}

public static function unblock_zotero() : void {
  self::$zotero_failures_count = 0;  
}

public static function query_url_api_class(array $ids, array &$templates) : void { // Pointer to save memory
  if (!SLOW_MODE) return; // Zotero takes time
  if (!is_resource(self::$zotero_ch)) { // When closed will return FALSE
     self::$zotero_ch = curl_init();
     self::set_default_ch_zotero();
  }

  if (!TRAVIS) { // try harder in tests
    // @codeCoverageIgnoreStart
    curl_setopt(self::$zotero_ch, CURLOPT_CONNECTTIMEOUT, 3);
    $url_count = 0;
    foreach ($templates as $template) {
     if (!$template->blank(['url', 'chapter-url', 'chapterurl'])) {
       $url_count = $url_count + 1;
     }
    }
    if ($url_count < 5) {
      curl_setopt(self::$zotero_ch, CURLOPT_TIMEOUT, 15);
    } elseif ($url_count < 25) {
      curl_setopt(self::$zotero_ch, CURLOPT_TIMEOUT, 10);
    } else {
      curl_setopt(self::$zotero_ch, CURLOPT_TIMEOUT, 5);
    }
    // @codeCoverageIgnoreEnd
  }
  self::$zotero_announced = 1;
  foreach ($templates as $template) {
     self::expand_by_zotero($template);
  }
  if (!TRAVIS) { // These are pretty reliable, unlike random urls
      curl_setopt(self::$zotero_ch, CURLOPT_TIMEOUT, 10);  // @codeCoverageIgnore
  }
  self::$zotero_announced = 2;
  foreach ($templates as $template) {
       if ($template->has('biorxiv')) {
         if ($template->blank('doi')) {
           $template->add_if_new('doi', '10.1101/' . $template->get('biorxiv'));
           expand_by_doi($template, TRUE);  // this data is better than zotero
         } elseif (strstr($template->get('doi') , '10.1101') === FALSE) {
           expand_doi_with_dx($template, '10.1101/' . $template->get('biorxiv'));  // dx data is better than zotero
           self::expand_by_zotero($template, 'https://dx.doi.org/10.1101/' . $template->get('biorxiv'));  // Rare case there is a different DOI
         }
       }
       $doi = $template->get('doi');
       if (!doi_active($doi)) {
         if ($template->has('citeseerx')) self::expand_by_zotero($template, 'http://citeseerx.ist.psu.edu/viewdoc/summary?doi=' . $template->get('citeseerx'));
         if ($template->has('hdl'))       self::expand_by_zotero($template, 'https://hdl.handle.net/' . $template->get('hdl'));
         //  Has a CAPCHA --  if ($template->has('jfm'))       self::expand_by_zotero($template, 'https://zbmath.org/?format=complete&q=an:' . $template->get('jfm'));
         //  Has a CAPCHA --  if ($template->has('zbl'))       self::expand_by_zotero($template, 'https://zbmath.org/?format=complete&q=an:' . $template->get('zbl'));
         //  Do NOT do MR --  it is a review not the article itself.  Note that html does have doi, but do not use it.
         if ($template->has('osti'))      self::expand_by_zotero($template, 'https://www.osti.gov/biblio/' . $template->get('osti'));
         if ($template->has('rfc'))       self::expand_by_zotero($template, 'https://tools.ietf.org/html/rfc' . $template->get('rfc'));
         if ($template->has('ssrn'))      self::expand_by_zotero($template, 'https://papers.ssrn.com/sol3/papers.cfm?abstract_id=' . $template->get('ssrn'));
       }
       if ($template->has('doi')) {
         $doi = $template->get('doi');
         if (!doi_active($doi) && doi_works($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
           self::expand_by_zotero($template, 'https://dx.doi.org/' . $doi);  // DOIs without meta-data
         }
       }
  }
}

public static function query_ieee_webpages(array &$templates) : void {  // Pointer to save memory
  $matches_url = ['', '']; // prevent memory leak in some PHP versions
  $matches = ['', '']; // prevent memory leak in some PHP versions
  $ch_ieee = curl_init();
  curl_setopt_array($ch_ieee,
         [CURLOPT_RETURNTRANSFER => TRUE,
          CURLOPT_HEADER => FALSE,
          CURLOPT_TIMEOUT => 15,
          CURLOPT_FOLLOWLOCATION => TRUE,
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_CONNECTTIMEOUT => 8,
          CURLOPT_COOKIEFILE => 'cookie.txt',
          CURLOPT_USERAGENT => 'curl/7.55.1']); // IEEE now requires JavaScript, unless you specify curl
  
  foreach (['url', 'chapter-url', 'chapterurl'] as $kind) {
   foreach ($templates as $template) {
    set_time_limit(120);
    if ($template->blank('doi') && preg_match("~^https://ieeexplore\.ieee\.org/document/(\d{5,})$~", $template->get($kind), $matches_url)) {
       usleep(100000); // 0.10 seconds
       curl_setopt($ch_ieee, CURLOPT_URL, $template->get($kind));
       $return = (string) @curl_exec($ch_ieee);
       if ($return !== "" && preg_match_all('~"doi":"(10\.\d{4}/[^\s"]+)"~', $return, $matches, PREG_PATTERN_ORDER)) {
          $dois = array_unique($matches[1]);
          if (count($dois) === 1) {
            if ($template->add_if_new('doi', $dois[0])) {
              if (strpos($template->get('doi'), $matches_url[1]) !== FALSE && doi_works($template->get('doi'))) {
                // SEP 2020 $template->forget($kind);  // It is one of those DOIs with the document number in it
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

public static function drop_urls_that_match_dois(array &$templates) : void {  // Pointer to save memory
  // Now that we have expanded URLs, try to lose them
  $ch = curl_init();
  curl_setopt_array($ch,
        [CURLOPT_FOLLOWLOCATION => TRUE,
         CURLOPT_MAXREDIRS => 20, // No infinite loops for us, 20 for Elsivier and Springer websites
         CURLOPT_CONNECTTIMEOUT =>  4, 
         CURLOPT_TIMEOUT => 20,
         CURLOPT_RETURNTRANSFER => TRUE,
         CURLOPT_COOKIEFILE => 'cookie.txt',
         CURLOPT_AUTOREFERER => TRUE,
         CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org']);
  foreach ($templates as $template) {
    $doi = $template->get_without_comments_and_placeholders('doi');
    if ($template->has('url')) {
       $url = $template->get('url');
       $url_kind = 'url';
    } elseif ($template->has('chapter-url')) {
       $url = $template->get('chapter-url');
       $url_kind = 'chapter-url';
    } elseif ($template->has('chapterurl')) {
       $url = $template->get('chapterurl'); // @codeCoverageIgnore
       $url_kind = 'chapterurl';            // @codeCoverageIgnore
    } else {
       $url = '';
       $url_kind = '';
    }
    if ($doi &&  // IEEE code does not require "not incomplete"
        $url &&
        !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
        $template->blank(DOI_BROKEN_ALIASES) &&
        preg_match("~^https?://ieeexplore\.ieee\.org/document/\d{5,}/?$~", $url) && strpos($doi, '10.1109') === 0) {
          // SEP 2020 report_forget("Existing IEEE resulting from equivalent DOI; dropping URL");
          // SEP 2020 $template->forget($url_kind);
    }
    
    if ($doi &&
        $url &&
        !$template->profoundly_incomplete() &&
        !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
        (strpos($doi, '10.1093/') === FALSE) &&
        $template->blank(DOI_BROKEN_ALIASES))
    {
       set_time_limit(120);
       if (str_ireplace(PROXY_HOSTS_TO_DROP,'', $url) !== $url && $template->get('doi-access') === 'free') {
          report_forget("Existing proxy URL resulting from equivalent free DOI; dropping URL");
          $template->forget($url_kind);
       } elseif (str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP,'', $url) !== $url && $template->get('doi-access') === 'free') {
          report_forget("Existing proxy URL resulting from equivalent free DOI; dropping URL");
          $template->forget($url_kind);
       } elseif (str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP,'', $url) !== $url) {
          report_forget("Existing proxy URL resulting from equivalent DOI; fixing URL");
          $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
       } elseif (preg_match('~www.sciencedirect.com/science/article/B[^/\-]*\-[^/\-]+\-[^/\-]+/~', $url)) {
          report_forget("Existing Invalid ScienceDirect URL when DOI is present; fixing URL");
          $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
       } elseif (preg_match('~www.sciencedirect.com/science/article/pii/\S{0,16}$~i', $url)) { // Too Short
          report_forget("Existing Invalid ScienceDirect URL when DOI is present; fixing URL");
          $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
       } elseif (preg_match('~www.springerlink.com/content~i', $url)) { // Dead website
          report_forget("Existing Invalid Springer Link URL when DOI is present; fixing URL");
          $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
       } elseif (str_ireplace('insights.ovid.com/pubmed','', $url) !== $url && $template->has('pmid')) {
          // SEP 2020 report_forget("Existing OVID URL resulting from equivalent PMID and DOI; dropping URL");
          // SEP 2020 $template->forget($url_kind);
       } elseif ($template->has('pmc') && str_ireplace('iopscience.iop.org','', $url) !== $url) {
          // SEP 2020 report_forget("Existing IOP URL resulting from equivalent DOI; dropping URL");
          // SEP 2020 $template->forget($url_kind);;
          $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
       } elseif (str_ireplace('wkhealth.com','', $url) !== $url) {
          report_forget("Existing Outdated WK Health URL resulting from equivalent DOI; fixing URL");
          $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
       } elseif ($template->has('pmc') && str_ireplace('bmj.com/cgi/pmidlookup','', $url) !== $url && $template->has('pmid') && $template->get('doi-access') === 'free' && stripos($url, 'pdf') === FALSE) {
          report_forget("Existing The BMJ URL resulting from equivalent PMID and free DOI; dropping URL");
          $template->forget($url_kind);
       } elseif ($template->get('doi-access') === 'free' && $template->get('url-status') === 'dead' && $url_kind === 'url') {
          report_forget("Existing free DOI; dropping dead URL");
          $template->forget($url_kind);
       } elseif (doi_active($template->get('doi')) &&
                 !preg_match(REGEXP_DOI_ISSN_ONLY, $template->get('doi')) &&
                 $url_kind != '' &&
                 (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $template->get($url_kind)) != $template->get($url_kind)) &&
                 $template->has_good_free_copy() &&
                 (stripos($template->get($url_kind), 'pdf') === FALSE)) {
          report_forget("Existing canonical URL resulting in equivalent free DOI/pmc; dropping URL");
          $template->forget($url_kind);  
       } elseif (stripos($url, 'pdf') === FALSE && $template->get('doi-access') === 'free' && $template->has('pmc')) {
          curl_setopt($ch, CURLOPT_URL, "https://dx.doi.org/" . doi_encode($doi));
          /** @phpstan-ignore-next-line **/ CONFLICT/** it saves the return value **/
          if (@curl_exec($ch)) {
            $redirectedUrl_doi = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);  // Final URL
            if (stripos($redirectedUrl_doi, 'cookie') !== FALSE) break;
            if (stripos($redirectedUrl_doi, 'denied') !== FALSE) break;
            $redirectedUrl_doi = self::url_simplify($redirectedUrl_doi);
            $url_short         = self::url_simplify($url);
            if ( preg_match('~^https?://.+/pii/?(S?\d{4}[^/]+)~i', $redirectedUrl_doi, $matches ) === 1 ) { // Grab PII numbers
                 $redirectedUrl_doi = $matches[1] ;  // @codeCoverageIgnore 
            }
            if (stripos($url_short, $redirectedUrl_doi) !== FALSE ||
                stripos($redirectedUrl_doi, $url_short) !== FALSE) {
               report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
               $template->forget($url_kind);
            } else { // See if $url redirects
               curl_setopt($ch, CURLOPT_URL, $url);
               /** @phpstan-ignore-next-line **/ /** it saves the return value **/
               if (@curl_exec($ch)) {
                  $redirectedUrl_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                  $redirectedUrl_url = self::url_simplify($redirectedUrl_url);
                  if (stripos($redirectedUrl_url, $redirectedUrl_doi) !== FALSE ||
                      stripos($redirectedUrl_doi, $redirectedUrl_url) !== FALSE) {
                    report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
                    $template->forget($url_kind);
                  }
               }
            }
          }
       }
    }
    $url = $template->get($url_kind);
    if ($url && !$template->profoundly_incomplete() && str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP,'', $url) !== $url) {
       if (!$template->blank_other_than_comments('pmc')) {
          report_forget("Existing proxy URL resulting from equivalent PMC; dropping URL");
          $template->forget($url_kind);
       }
    }
  }
  curl_close($ch);
  /** @psalm-suppress UnusedFunctionCall */
  @strtok('',''); // Free internal buffers with empty unused call
}

private static function zotero_request(string $url) : string {
  set_time_limit(120);
  if (self::$zotero_failures_count > self::ZOTERO_GIVE_UP) {
    self::$zotero_failures_count = self::$zotero_failures_count - 1;                            // @codeCoverageIgnore
    if (self::ZOTERO_GIVE_UP == self::$zotero_failures_count) self::$zotero_failures_count = 0; // @codeCoverageIgnore
  }

  if (!is_resource(self::$zotero_ch)) {
     self::$zotero_ch = curl_init();   // @codeCoverageIgnore
     self::set_default_ch_zotero();    // @codeCoverageIgnore
  }
  /** @phan-suppress-next-line PhanRedundantCondition */
  if ( USE_CITOID ) {
     curl_setopt(self::$zotero_ch, CURLOPT_URL, CITOID_ZOTERO . urlencode($url));
  } else {
     curl_setopt(self::$zotero_ch, CURLOPT_POSTFIELDS, $url);    // @codeCoverageIgnore 
  }
   
  if (self::$zotero_failures_count > self::ZOTERO_GIVE_UP) return self::ERROR_DONE;
  
  usleep(100000*(1+self::$zotero_failures_count)); // 0.10 seconds delay throttle
  $zotero_response = (string) @curl_exec(self::$zotero_ch);
  if ($zotero_response == '') {
     // @codeCoverageIgnoreStart
     sleep(2);
     $zotero_response = (string) @curl_exec(self::$zotero_ch);
     // @codeCoverageIgnoreEnd
  }
  if ($zotero_response == '') {
    // @codeCoverageIgnoreStart
    report_warning(curl_error(self::$zotero_ch) . "   For URL: " . $url);
    if (strpos(curl_error(self::$zotero_ch), 'timed out after') !== FALSE) {
      self::$zotero_failures_count = self::$zotero_failures_count + 1;
      if (self::$zotero_failures_count > self::ZOTERO_GIVE_UP) {
        report_warning("Giving up on URL expansion for a while");
        self::$zotero_failures_count = self::$zotero_failures_count + self::ZOTERO_SKIPS;
      }
    }
    $zotero_response = self::ERROR_DONE;
    // @codeCoverageIgnoreEnd
  }
  return $zotero_response;
}

public static function expand_by_zotero(Template $template, ?string $url = NULL) : bool {
  $access_date = 0;
  $url_kind = '';
  if (is_null($url)) {
     if (in_array($template->get('url-status'),  ['usurped', 'unfit', 'dead'])) return FALSE;
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
     if (preg_match('~^https?://(?:dx\.|)doi\.org~i', $url)) {
        return FALSE;
     }
     if (preg_match('~^https?://semanticscholar\.org~i', $url)) {
        return FALSE;
     }
     if (preg_match(REGEXP_BIBCODE, urldecode($url))) {
        return FALSE;
     }
     if (preg_match("~^https?://citeseerx\.ist\.psu\.edu~i", $url)) {
        return FALSE;
     }
     if (preg_match("~\barxiv\.org/.*(?:pdf|abs|ftp/arxiv/papers/\d{4})/(.+?)(?:\.pdf)?$~i", $url)) {
        return FALSE;
     }     
  }

  if (!$template->profoundly_incomplete($url)) return FALSE; // Only risk unvetted data if there's little good data to sully
  
  if(stripos($url, 'CITATION_BOT_PLACEHOLDER') !== FALSE) return FALSE; // That's a bad url
  
  $bad_url = implode('|', ZOTERO_AVOID_REGEX);
  if(preg_match("~^https?://(?:www\.|m\.|)(?:" . $bad_url . ")~i", $url)) return FALSE; 

  // Is it actually a URL.  Zotero will search for non-url things too!
  if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return FALSE; // PHP does not like it
  if (preg_match('~^https?://[^/]+/?$~', $url) === 1) return FALSE; // Just a host name
  if (preg_match(REGEXP_IS_URL, $url) !== 1) return FALSE;  // See https://mathiasbynens.be/demo/url-regex/  This regex is more exact than validator.  We only spend time on this after quick and dirty check is passed
   
  if (self::$zotero_announced === 1) {
    report_action("Using Zotero translation server to retrieve details from URLs.");
    self::$zotero_announced = 0;
  } elseif (self::$zotero_announced === 2) {
    report_action("Using Zotero translation server to retrieve details from identifiers.");
    self::$zotero_announced = 0;
  }
  $zotero_response = self::zotero_request($url);
  return self::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date);
}

public static function process_zotero_response(string $zotero_response, Template $template, string $url, string $url_kind, int $access_date) : bool {
  $matches = ['', '']; // prevent memory leak in some PHP versions
  if ($zotero_response === self::ERROR_DONE) return FALSE;  // Error message already printed in zotero_request()
 
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
  // Remove unused stuff.  TODO - Is there any value in these:
  unset($result->abstractNote);
  unset($result->version);
  unset($result->accessDate);
  unset($result->libraryCatalog);
  unset($result->url);
  unset($result->tags);
  unset($result->key);
  unset($result->websiteTitle);
  unset($result->journalAbbreviation);
  unset($result->ISSN);

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
  if ($test_data === '404') return FALSE;
  if (isset($result->bookTitle) && strtolower($result->bookTitle) === 'undefined') unset($result->bookTitle); // S2 without journals
  if (isset($result->publicationTitle) && strtolower($result->publicationTitle) === 'undefined') unset($result->publicationTitle); // S2 without journals
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
   // Specific bad data that is correctable
   $tester = strtolower($result->publicationTitle);
   if ($tester === 'nationalpost') {
      $result->publicationTitle = 'National Post';
   } elseif ($tester === 'financialpost') {
      $result->publicationTitle = 'Financial Post';
   }
  }
   
  if (preg_match('~^([^\]]+)\|([^\]]+)\| ?THE DAILY STAR$~i', @$result->title, $matches)) {
    $result->title = $matches[1];
    $result->publicationTitle = 'The Daily Star';
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
    if (preg_match('~\s(Google-Books-ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(ISSN: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(Page Version ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(Citation Key: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // Not precise enough to use
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\sADS Bibcode: (\d{4}\S{15})\s~i', ' ' . $result->extra . ' ', $matches)) {
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
      $template->add_if_new('bibcode',  $matches[1]);
    } 
    if (trim($result->extra) !== '') {
      report_minor_error("Unhandled extra data: " . $result->extra);                       // @codeCoverageIgnore
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
  
  if (strpos($url, 'biodiversitylibrary.org') !== FALSE) {
    unset($result->publisher); // Not reliably set
  }
  if (isset($result->title) && $result->title === 'Cultural Advice' && strpos($url, 'edu.au') !== FALSE) {
      unset($result->title); // A warning, not a title
  }
  if ($template->has('title')) {
     if(isset($result->title) && titles_are_similar($template->get('title'), (string) $result->title)) {
        unset($result->title);
     }
  }
  if ($template->has('chapter')) {
     if(isset($result->title) && titles_are_similar($template->get('chapter'), (string) $result->title)) {
        unset($result->title);
     }
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
  if ( isset($result->pages)) {
     $pos_pages = (string) $result->pages;
     if (preg_match('~\d~', $pos_pages) && !preg_match('~\d+\.\d+.\d+~', $pos_pages)) { // At least one number but not a dotted number from medRxiv 
        $template->add_if_new('pages'  , $pos_pages);
     }
  }
  if (isset($result->itemType) && $result->itemType == 'newspaperArticle') {
    if ( isset($result->publicationTitle)) {
       $new_title = (string) $result->publicationTitle;
       if (in_array(strtolower($new_title), WORKS_ARE_PUBLISHERS)) {
          $template->add_if_new('publisher', $new_title);
       } else {
          $template->add_if_new('newspaper', $new_title);
       }
    }
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
  // Sometimes zotero lists the last name as "published" and puts the whole name in the first place
  $i = 0;
  while (isset($result->author[$i])) {
      if (@$result->author[$i][1] === 'published' || @$result->author[$i][1] === 'Published') unset($result->author[$i][1]);
      if (@$result->author[$i][0] === 'published' || @$result->author[$i][0] === 'Published') unset($result->author[$i][0]);
      $i++;
  }
  unset($i);
  if ( isset($result->author[0]) && !isset($result->author[1]) &&
      !author_is_human(@$result->author[0][0] . ' ' . @$result->author[0][1])) {
    unset($result->author[0]); // Do not add a single non-human author
  }
  $i = 0;
  while (isset($result->author[$i])) {
      if (author_is_human(@$result->author[$i][0] . ' ' . @$result->author[$i][1])) $template->validate_and_add('author' . (string)($i+1), (string) @$result->author[$i][1], (string) @$result->author[$i][0],
                                      isset($result->rights) ? (string) $result->rights : '', FALSE);
      $i++;
     if ($template->blank(['author' . (string)($i), 'first' . (string)($i), 'last' . (string)($i)])) break; // Break out if nothing added
  }
  unset($i);
   
  if (stripos($url, 'sfdb.org') !== FALSE && $template->blank(WORK_ALIASES)) {
     $template->add_if_new('website', 'sfdb.org');
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
        if($template->wikiname() == 'cite web' && str_ireplace(NON_JOURNAL_WEBSITES, '', $url) === $url && !$template->blank(WORK_ALIASES)) {
          $template->change_name_to('cite journal');
        }
        break;
      case 'magazineArticle':
        if($template->wikiname() == 'cite web') {
          $template->change_name_to('cite magazine');
        }
        break;      
      case 'newspaperArticle':
        if ($template->wikiname() === 'cite web') {
           $test_data = $template->get('work') . $template->get('website') .
                        $template->get('url') . $template->get('chapter-url') .
                        $template->get('title') . $template->get('encyclopedia') .
                        $template->get('encyclopædia') . $url; // Some things get called "news" in error
           if (str_ireplace(['.gov', 'encyclopedia', 'encyclopædia'], '', $test_data) === $test_data) {
              $template->change_name_to('cite news');
           }
        }
        break;
      case 'webpage':
      case 'blogPost':
      case 'document':
        
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
      case 'audioRecording';   // @codeCoverageIgnore
      case 'presentation';     // @codeCoverageIgnore
      case 'computerProgram';  // @codeCoverageIgnore
      case 'forumPost';        // @codeCoverageIgnore
          // Do not change type. This seems to include things that will just make people angry if we change type to encyclopedia
      case 'encyclopediaArticle';  // @codeCoverageIgnore
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
              $authorParam = 'author' . (string) ++$author_i;
              break;
            case 'editor':
              $authorParam = 'editor' . (string) ++$editor_i;
              break;
            case 'translator':
              $authorParam = 'translator' . (string) ++$translator_i;
              break;
            default:                                                               // @codeCoverageIgnore
              report_minor_error("Unrecognized creator type: " . $creatorType);    // @codeCoverageIgnore
              $authorParam = '';                                                   // @codeCoverageIgnore
          }
         if ($authorParam && author_is_human($result->creators[$i]->firstName . ' ' . $result->creators[$i]->lastName)) {
            if (strtolower((string) $result->creators[$i]->lastName ) === 'published') $result->creators[$i]->lastName  ='';
            if (strtolower((string) $result->creators[$i]->firstName) === 'published') $result->creators[$i]->firstName ='';
            $template->validate_and_add($authorParam, (string) $result->creators[$i]->lastName, (string) $result->creators[$i]->firstName,
            isset($result->rights) ? (string) $result->rights : '', FALSE);
            if ($template->blank(['author' . (string)($i), 'first' . (string)($i), 'last' . (string)($i)])) break; // Break out if nothing added
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

public static function url_simplify(string $url) : string {
  $matches = ['', '']; // prevent memory leak in some PHP versions
  $url = str_replace('/action/captchaChallenge?redirectUri=', '', $url);
  $url = urldecode($url);
  // IEEE is annoying
  if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
    $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
  }
  $url = $url . '/';
  $url = str_replace(['/abstract/', '/full/', '/full+pdf/', '/pdf/', '/document/', '/html/', '/html+pdf/', '/abs/', '/epdf/', '/doi/', '/xprint/', '/print/', '.short', '.long', '.abstract', '.full', '///', '//'],
                     ['/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/'], $url);
  $url = substr($url, 0, -1); // Remove the ending slash we added
  $url = strtok($url, '?#');
  $url = str_ireplace('https', 'http', $url);
  return $url;
}
  
} // End of CLASS

