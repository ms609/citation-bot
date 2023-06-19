<?php
declare(strict_types=1);

require_once 'constants.php';  // @codeCoverageIgnore
require_once 'Template.php';   // @codeCoverageIgnore

const MAGIC_STRING_URLS = 'CITATION_BOT_PLACEHOLDER_URL_POINTER_';
const CITOID_ZOTERO = "https://en.wikipedia.org/api/rest_v1/data/citation/zotero/";

/**
  @param array<string> $ids
  @param array<Template> $templates
**/
function query_url_api(array $ids, array &$templates) : void {  // Pointer to save memory
   Zotero::query_url_api_class($ids, $templates);
}

final class Zotero {
  private const ZOTERO_GIVE_UP = 5;
  private const ZOTERO_SKIPS = 100;
  private const ERROR_DONE = 'ERROR_DONE'; 
  protected static int $zotero_announced = 0;
  /**
   * @var resource $zotero_ch
   * @var resource $ch_ieee
   * @var resource $ch_jstor
   * @var resource $ch_dx
   * @var resource $ch_pmc
   **/
  protected static $zotero_ch, $ch_ieee, $ch_jstor, $ch_dx, $ch_pmc;
  protected static int $zotero_failures_count = 0;
  private static bool $is_setup = FALSE;

public static function create_ch_zotero() : void {
  if (self::$is_setup) return;
  self::$is_setup = TRUE;
  self::$zotero_ch = curl_init();
  /** @psalm-suppress PossiblyNullArgument */ 
  curl_setopt_array(self::$zotero_ch,
         [CURLOPT_URL => CITOID_ZOTERO,
          CURLOPT_HTTPHEADER => ['accept: application/json; charset=utf-8'],
          CURLOPT_RETURNTRANSFER => TRUE,
          CURLOPT_USERAGENT => BOT_USER_AGENT,
          CURLOPT_COOKIESESSION => TRUE,
          // Defaults used in TRAVIS overridden below when deployed
          CURLOPT_CONNECTTIMEOUT => 10,
          CURLOPT_TIMEOUT => 45]);

  self::$ch_ieee = curl_init();
  curl_setopt_array(self::$ch_ieee,
         [CURLOPT_RETURNTRANSFER => TRUE,
          CURLOPT_HEADER => FALSE,
          CURLOPT_TIMEOUT => 15,
          CURLOPT_FOLLOWLOCATION => TRUE,
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_CONNECTTIMEOUT => 8,
          CURLOPT_COOKIESESSION => TRUE,
          CURLOPT_USERAGENT => 'curl/7.55.1']); // IEEE now requires JavaScript, unless you specify curl
   
  self::$ch_jstor = curl_init();
  curl_setopt_array(self::$ch_jstor,
       [CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_COOKIESESSION => TRUE,
        CURLOPT_USERAGENT => BOT_USER_AGENT]);
   
  self::$ch_dx = curl_init();
  curl_setopt_array(self::$ch_dx,
        [CURLOPT_FOLLOWLOCATION => TRUE,
         CURLOPT_MAXREDIRS => 20, // No infinite loops for us, 20 for Elsevier and Springer websites
         CURLOPT_CONNECTTIMEOUT =>  4, 
         CURLOPT_TIMEOUT => 20,
         CURLOPT_RETURNTRANSFER => TRUE,
         CURLOPT_AUTOREFERER => TRUE,
         CURLOPT_COOKIESESSION => TRUE,
         CURLOPT_USERAGENT => BOT_USER_AGENT]);

  self::$ch_pmc = curl_init();
  curl_setopt_array(self::$ch_pmc,
        [CURLOPT_RETURNTRANSFER => TRUE,
         CURLOPT_TIMEOUT => 15,
         CURLOPT_CONNECTTIMEOUT => 10,
         CURLOPT_COOKIESESSION => TRUE,
         CURLOPT_USERAGENT => BOT_USER_AGENT]);
}

public static function block_zotero() : void {
  self::$zotero_failures_count = 1000000;  
}

public static function unblock_zotero() : void {
  self::$zotero_failures_count = 0;  
}

/**
  @param array<string> $ids
  @param array<Template> $templates
**/
public static function query_url_api_class(array $ids, array &$templates) : void { // Pointer to save memory
  if (!SLOW_MODE) return; // Zotero takes time

  curl_setopt(self::$zotero_ch, CURLOPT_TIMEOUT, 45); // Reset default
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
  self::$zotero_announced = 2;
  if (!TRAVIS) { // These are pretty reliable, unlike random urls
      curl_setopt(self::$zotero_ch, CURLOPT_TIMEOUT, 10);  // @codeCoverageIgnore
  }
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
         if ($template->has('citeseerx')) self::expand_by_zotero($template, ' https://citeseerx.ist.psu.edu/viewdoc/summary?doi=' . $template->get('citeseerx'));
         //  Has a CAPCHA --  if ($template->has('jfm'))
         //  Has a CAPCHA --  if ($template->has('zbl'))
         //  Do NOT do MR --  it is a review not the article itself.  Note that html does have doi, but do not use it.
         if ($template->has('hdl'))       self::expand_by_zotero($template, 'https://hdl.handle.net/' . $template->get('hdl'));
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

/**
  @param array<Template> $templates
**/
public static function query_ieee_webpages(array &$templates) : void {  // Pointer to save memory
  
  foreach (['url', 'chapter-url', 'chapterurl'] as $kind) {
   foreach ($templates as $template) {
    set_time_limit(120);
    if ($template->blank('doi') && preg_match("~^https://ieeexplore\.ieee\.org/document/(\d{5,})$~", $template->get($kind), $matches_url)) {
       usleep(100000); // 0.10 seconds
       /** @psalm-taint-escape ssrf */
       $the_url = $template->get($kind);
       curl_setopt(self::$ch_ieee, CURLOPT_URL, $the_url);
       $return = (string) @curl_exec(self::$ch_ieee);
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
       /** @psalm-taint-escape ssrf */
       $the_url = $template->get($kind);
       curl_setopt(self::$ch_ieee, CURLOPT_URL, $the_url);
       $return = (string) @curl_exec(self::$ch_ieee);
       if ($return !== "" && strpos($return, "<title> -  </title>") !== FALSE) {
         report_forget("Existing IEEE no longer works - dropping URL"); // @codeCoverageIgnore
         $template->forget($kind);                                      // @codeCoverageIgnore
       }
    }
   }
  }
}

/**
  @param array<Template> $templates
**/
public static function drop_urls_that_match_dois(array &$templates) : void {  // Pointer to save memory
  // Now that we have expanded URLs, try to lose them
  $ch = curl_init();
  curl_setopt_array($ch,
        [CURLOPT_FOLLOWLOCATION => TRUE,
         CURLOPT_MAXREDIRS => 20, // No infinite loops for us, 20 for Elsevier and Springer websites
         CURLOPT_CONNECTTIMEOUT =>  4, 
         CURLOPT_TIMEOUT => 20,
         CURLOPT_RETURNTRANSFER => TRUE,
         CURLOPT_AUTOREFERER => TRUE,
         CURLOPT_COOKIESESSION => TRUE,
         CURLOPT_USERAGENT => BOT_USER_AGENT]);
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
                 $url_kind !== '' &&
                 (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $template->get($url_kind)) !== $template->get($url_kind)) &&
                 $template->has_good_free_copy() &&
                 (stripos($template->get($url_kind), 'pdf') === FALSE)) {
          report_forget("Existing canonical URL resulting in equivalent free DOI/pmc; dropping URL");
          $template->forget($url_kind);  
       } elseif (stripos($url, 'pdf') === FALSE && $template->get('doi-access') === 'free' && $template->has('pmc')) {
          curl_setopt(self::$ch_dx, CURLOPT_URL, "https://dx.doi.org/" . doi_encode($doi));
          $ch_return = (string) @curl_exec(self::$ch_dx);
          if (strlen($ch_return) > 50) { // Avoid bogus tiny pages
            $redirectedUrl_doi = curl_getinfo(self::$ch_dx, CURLINFO_EFFECTIVE_URL);  // Final URL
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
               /** @psalm-taint-escape ssrf */
               $the_url = $url;
               curl_setopt($ch, CURLOPT_URL, $the_url);
               $ch_return = (string) @curl_exec($ch);
               if (strlen($ch_return) > 60) {
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
}

private static function zotero_request(string $url) : string {
  set_time_limit(120);
  if (self::$zotero_failures_count > self::ZOTERO_GIVE_UP) {
    self::$zotero_failures_count = self::$zotero_failures_count - 1;                            // @codeCoverageIgnore
    if (self::ZOTERO_GIVE_UP === self::$zotero_failures_count) self::$zotero_failures_count = 0; // @codeCoverageIgnore
  }

  /** @psalm-taint-escape ssrf */
  $the_url = CITOID_ZOTERO . urlencode($url);
  curl_setopt(self::$zotero_ch, CURLOPT_URL, $the_url);

  if (self::$zotero_failures_count > self::ZOTERO_GIVE_UP) return self::ERROR_DONE;
  
  $delay = max(min(100000*(1+self::$zotero_failures_count), 10), 0); // 0.10 seconds delay throttle, with paranoid bounds checks
  usleep($delay);
  $zotero_response = (string) @curl_exec(self::$zotero_ch);
  if ($zotero_response === '') {
     // @codeCoverageIgnoreStart
     sleep(2);
     $zotero_response = (string) @curl_exec(self::$zotero_ch);
     // @codeCoverageIgnoreEnd
  }
  if ($zotero_response === '') {
    // @codeCoverageIgnoreStart
    report_warning(curl_error(self::$zotero_ch) . "   For URL: " . echoable($url));
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
  if (preg_match('~^https?://[^/]+/?$~', $url) === 1) return FALSE; // Just a host name
  set_time_limit(120); // This can be slow
  if (preg_match(REGEXP_IS_URL, $url) !== 1) return FALSE;  // See https://mathiasbynens.be/demo/url-regex/  This regex is more exact than validator.  We only spend time on this after quick and dirty check is passed
  set_time_limit(120);
  if (self::$zotero_announced === 1) {
    report_action("Using Zotero translation server to retrieve details from URLs.");
    self::$zotero_announced = 0;
  } elseif (self::$zotero_announced === 2) {
    report_action("Using Zotero translation server to retrieve details from identifiers.");
    self::$zotero_announced = 0;
  }
  $zotero_response = self::zotero_request($url);
  $return = self::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date);
  /**
  if ($return) {
     bot_debug_log('ZoteroWorked: ' . $url);
  } else {
     bot_debug_log('ZoteroFailed: ' . $url);
  }
  **/
  return $return;
}

public static function process_zotero_response(string $zotero_response, Template $template, string $url, string $url_kind, int $access_date) : bool {
  if ($zotero_response === self::ERROR_DONE) return FALSE;  // Error message already printed in zotero_request()
 
  switch (trim($zotero_response)) {
    case '':
      report_info("Nothing returned for URL " . echoable($url));
      return FALSE;
    case 'Internal Server Error':
      report_info("Internal server error with URL " . echoable($url));
      return FALSE;
    case 'Remote page not found':
      report_info("Remote page not found for URL " . echoable($url));
      return FALSE;
    case 'No items returned from any translator':
      report_info("Remote page not interpretable for URL " . echoable($url));
      return FALSE;
    case 'An error occurred during translation. Please check translation with the Zotero client.':
      report_info("An error occurred during translation for URL " . echoable($url));
      return FALSE;
  }
  
  if (strpos($zotero_response, '502 Bad Gateway') !== FALSE) {
    report_warning("Bad Gateway error for URL ". echoable($url));
    return FALSE;
  }
  if (strpos($zotero_response, '503 Service Temporarily Unavailable') !== FALSE) {
    report_warning("Temporarily Unavailable error for URL " . echoable($url));  // @codeCoverageIgnore
    return FALSE;                                                    // @codeCoverageIgnore
  }
  $zotero_data = @json_decode($zotero_response, FALSE);
  if (!isset($zotero_data)) {
    report_warning("Could not parse JSON for URL ". echoable($url) . ": " . $zotero_response);
    return FALSE;
  } elseif (!is_array($zotero_data)) {
    if (is_object($zotero_data)) {
      $zotero_data = (array) $zotero_data;
    } else {
      report_warning("JSON did not parse correctly for URL ". echoable($url) . ": " . $zotero_response);
      return FALSE;
    }
  }
  if (!isset($zotero_data[0])) {
    $result = $zotero_data;
  } else {
    $result = $zotero_data[0];
  }
  $result = (object) $result ;
  
  if (empty($result->publicationTitle) && empty($result->bookTitle) && !isset($result->title)) {
    if (!empty($result->subject)) {
      $result->title = $result->subject;
    } elseif (!empty($result->caseName)) {
      $result->title = $result->caseName;
    } elseif (!empty($result->nameOfAct)) {
      $result->title = $result->nameOfAct;
    }
  }
  if (!isset($result->title)) {
    if (strpos($zotero_response, 'unknown_error') !== FALSE) {  // @codeCoverageIgnoreStart
       report_info("Did not get a title for URL ". echoable($url));
    } else {
       report_minor_error("Did not get a title for URL ". echoable($url) . ": " . $zotero_response); // Odd Error
    }
    return FALSE;   // @codeCoverageIgnoreEnd
  }
  if (substr(strtolower(trim($result->title)), 0, 9) === 'not found') {
    report_info("Could not resolve URL " . echoable($url));
    return FALSE;
  }
  // Remove unused stuff
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
  unset($result->subject);
  unset($result->caseName);
  unset($result->nameOfAct);
  
  if (stripos($url, 'www.royal.uk') !== FALSE) {
    unset($result->creators);
    unset($result->author);
  }

  $result->title = convert_to_utf8($result->title);
  // Reject if we find more than 5 or more than 10% of the characters are �.  This means that character
  // set was not correct in Zotero and nothing is good.  We allow a couple of � for German umlauts that arer easily fixable by humans.
  // We also get a lot of % and $ if the encoding was something like iso-2022-jp and converted wrong
  $bad_count = substr_count($result->title, '�') + mb_substr_count($result->title, '$') + mb_substr_count($result->title, '%');
  $total_count = mb_strlen($result->title);
  if (isset($result->bookTitle)) {
    $result->bookTitle = convert_to_utf8($result->bookTitle);
    $bad_count += substr_count($result->bookTitle, '�') + mb_substr_count($result->bookTitle, '$') + mb_substr_count($result->bookTitle, '%');
    $total_count += mb_strlen($result->bookTitle);
  }
  if (($bad_count > 5) || ($total_count > 1 && (($bad_count/$total_count) > 0.1))) {
    report_info("Could parse unicode characters in " . echoable($url));
    return FALSE;
  }
  
  report_info("Retrieved info from " . echoable($url));
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
        report_info("Received invalid title data for URL " . echoable($url) . ": $test_data");
        return FALSE;
      }
  }
  if ($test_data === '404' || $test_data === '/404') return FALSE;
  if (isset($result->bookTitle) && strtolower($result->bookTitle) === 'undefined') unset($result->bookTitle); // S2 without journals
  if (isset($result->publicationTitle) && strtolower($result->publicationTitle) === 'undefined') unset($result->publicationTitle); // S2 without journals
  if (isset($result->bookTitle)) {
   foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
      if (str_i_same($result->bookTitle, $bad_title)) {
        report_info("Received invalid book title data for URL " . echoable($url) . ": $result->bookTitle");
        return FALSE;
      }
   }
  }
  if (isset($result->title)) {
   foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
      if (str_i_same($result->title, $bad_title)) {
        report_info("Received invalid title data for URL ". echoable($url) . ": $result->title");
        return FALSE;
      }
   }
  }
  if (isset($result->publicationTitle)) {
   foreach (array_merge(BAD_ACCEPTED_MANUSCRIPT_TITLES, IN_PRESS_ALIASES) as $bad_title ) {
      if (str_i_same($result->publicationTitle, $bad_title)) {
        report_info("Received invalid publication title data for URL ". echoable($url) . ": $result->publicationTitle");
        return FALSE;
      }
   }
   // Specific bad data that is correctable
   $tester = strtolower($result->publicationTitle);
   if ($tester === 'nationalpost') {
      $result->publicationTitle = 'National Post';
   } elseif ($tester === 'financialpost') {
      $result->publicationTitle = 'Financial Post';
   } elseif ($tester === 'bloomberg.com') {
      $result->publicationTitle = 'Bloomberg';
   }
  }
   
  if (preg_match('~^([^\]]+)\|([^\]]+)\| ?THE DAILY STAR$~i', (string) @$result->title, $matches)) {
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
    if (preg_match('~\sPMID: (\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
      $template->add_if_new('pmid', $matches[1]);
    }
    if (preg_match('~\sOCLC: (?:|ocn|ocm)(\d+)\s~i', ' ' . $result->extra . ' ', $matches)) {
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
      $template->add_if_new('oclc', $matches[1]);
    }
    if (preg_match('~\sOpen Library ID: OL(\d+M)\s~i', ' ' . $result->extra . ' ', $matches)) {
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
      $template->add_if_new('ol', $matches[1]);
    }
    
    // UNUSED stuff goes below
    
    if (preg_match('~\sFormat: PDF\s~i', ' ' . $result->extra . ' ', $matches)) {
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
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
    if (preg_match('~\s(Citation Key: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(number-of-pages: [ivx]+, \d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(number-of-pages: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(Version: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(RSLID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(QID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(National Archives Identifier: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(Catalog Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(BMCR ID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(PubAg AGID: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(IP-\d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\s(Accession Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~\sADS Bibcode: (\d{4}\S{15})\s~i', ' ' . $result->extra . ' ', $matches)) {
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));
      $template->add_if_new('bibcode',  $matches[1]);
    }
    if (preg_match('~\s(arXiv: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - only comes from arXiv DOIs
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));  // @codeCoverageIgnore
    }
    if (preg_match('~\s(INIS Reference Number: \d+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - https://inis.iaea.org
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));  // @codeCoverageIgnore
    }
    if (preg_match('~\s(ERIC Number: \S+)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));  // @codeCoverageIgnore
    }
    if (preg_match('~\s(\d+ cm\.)\s~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it - size of book
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));  // @codeCoverageIgnore
    }
    // These go at end since it is unbound on end often with linefeeds and such
    if (preg_match('~submitted:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~event\-location:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) { // We don't use it and it is long verbose
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Translated title:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~reviewed\-title:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Physical Description:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~BBK:[\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Place Manufactured: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Dimensions: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Category: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Credit: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Manufacturer: [\s\S]*$~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~pl., cartes, errata.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Post URL:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~Reference Number:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    if (preg_match('~jurisdiction:.+~i', ' ' . $result->extra . ' ', $matches)) {  // We don't use it
      $result->extra = trim(str_replace(trim($matches[0]), '', $result->extra));           // @codeCoverageIgnore
    }
    $result->extra = trim($result->extra);
    if ($result->extra !== '') {
      ;// TODO - check back later on report_minor_error("Unhandled extra data: " . echoable($result->extra) .  ' FROM ' . echoable($url));          // @codeCoverageIgnore
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
    $new_date = strtotime(tidy_date((string) $result->date)); // One time got an integer
    if($new_date) { // can compare
      if($new_date > $access_date) {
        report_info("URL appears to have changed since access-date " . echoable($url));
        return FALSE;
      }
    }
  }
  if (str_i_same(substr((string) @$result->publicationTitle, 0, 4), 'http') ||
      str_i_same(substr((string) @$result->bookTitle, 0, 4), 'http') ||
      str_i_same(substr((string) @$result->title, 0, 4), 'http')) {
    report_info("URL returned in Journal/Newpaper/Title/Chapter field for " . echoable($url));  // @codeCoverageIgnore
    return FALSE;                                                                     // @codeCoverageIgnore
  }
  
  if (isset($result->bookTitle)) {
    $result->bookTitle = safe_preg_replace('~\s*\(pdf\)$~i', '', $result->bookTitle);
    $result->bookTitle = safe_preg_replace('~^\(pdf\)\s*~i', '', $result->bookTitle);
    $result->bookTitle = safe_preg_replace('~ \- ProQuest\.?~i', '', $result->bookTitle);
  }
  if (isset($result->title)) {
    $result->title = safe_preg_replace('~\s*\(pdf\)$~i', '', (string) $result->title);
    $result->title = safe_preg_replace('~^\(pdf\)\s*~i', '', $result->title);
    $result->title = safe_preg_replace('~ \- ProQuest\.?~i', '', $result->title);
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
  if (isset($result->itemType) && $result->itemType === 'newspaperArticle') {
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
         if (str_replace(NON_JOURNALS, '', (string) $result->publicationTitle) === (string) $result->publicationTitle) {
            $template->add_if_new('journal', (string) $result->publicationTitle);
         }
      }
    }
  }
  if (isset($result->volume)) {
     $volume = (string) $result->volume;
     if (strpos($volume, "(") === FALSE ) {
        if (preg_match('~[a-zA-Z]~', $volume) && (bool) strtotime($volume)) {
           ; // Do not add date
        } else {
           $template->add_if_new('volume', $volume);
        }
     }
  }
  if ( isset($result->date) && strlen((string) $result->date)>3)$template->add_if_new('date', tidy_date((string) $result->date));
  if ( isset($result->series) && stripos($url, '.acm.org')===FALSE)  $template->add_if_new('series' , (string) $result->series);
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
   
  if ((stripos($url, '/sfdb.org') !== FALSE || stripos($url, '.sfdb.org') !== FALSE) && $template->blank(WORK_ALIASES)) {
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
            stripos($url, 'archive.org') === FALSE && !preg_match('~^https?://[^/]*journal~', $url) &&
            stripos($url, 'booklistonline') === FALSE
           ) {
          $template->change_name_to('cite book');
        }
        break;
      case 'journalArticle':
      case 'conferencePaper':
      case 'report':  // ssrn uses this
        if($template->wikiname() === 'cite web' && str_ireplace(NON_JOURNAL_WEBSITES, '', $url) === $url && !$template->blank(WORK_ALIASES)) {
          $template->change_name_to('cite journal');
        }
        break;
      case 'magazineArticle':
        if($template->wikiname() === 'cite web') {
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
      case 'thesis':
        $template->change_name_to('cite thesis');
        if (isset($result->university)) $template->add_if_new('publisher' , $result->university);
        if (isset($result->thesisType) && $template->blank(['type', 'medium', 'degree'])) {
          $template->add_if_new('type' , (string) $result->thesisType); // Prefer type since it exists in cite journal too
        }
        break;

      case 'email': // Often uses subject, not title
      case 'webpage':
      case 'blogPost':
      case 'document':// Could be a journal article or a genuine web page.
      case 'preprint':
      case 'entry':
      case 'videoRecording':
      case 'film':
      case 'map':    // @codeCoverageIgnore
      case 'bill':    // @codeCoverageIgnore
      case 'manuscript':   // @codeCoverageIgnore
      case 'audioRecording':   // @codeCoverageIgnore
      case 'presentation':     // @codeCoverageIgnore
      case 'computerProgram':  // @codeCoverageIgnore
      case 'forumPost':       // @codeCoverageIgnore
      case 'tvBroadcast':       // @codeCoverageIgnore
      case 'podcast':       // @codeCoverageIgnore
      case 'manuscript':       // @codeCoverageIgnore
      case 'artwork':       // @codeCoverageIgnore
      case 'case':       // @codeCoverageIgnore
      case 'statute':       // @codeCoverageIgnore
      case 'interview':   // @codeCoverageIgnore
      case 'letter':   // @codeCoverageIgnore
      case 'dataset':  // @codeCoverageIgnore
      case 'radioBroadcast':   // @codeCoverageIgnore
          // Do not change type.  Would need to think about parameters
      case 'patent':       // @codeCoverageIgnore
          // Do not change type. This seems to include things that will just make people angry if we change type to encyclopedia
      case 'encyclopediaArticle':  // @codeCoverageIgnore
          // Probably tick people off too
      case 'dictionaryEntry':  // @codeCoverageIgnore
        // Nothing special that we know of yet
        break;

      default:                                                                         // @codeCoverageIgnore
        report_minor_error("Unhandled itemType: " . echoable($result->itemType) . " for " . echoable($url));  // @codeCoverageIgnore
    }
    
    if (in_array($result->itemType, ['journalArticle', 'newspaperArticle', 'report', 'magazineArticle', 'thesis'])) {
      // Websites often have non-authors listed in metadata
      // "Books" are often bogus
      $i = 0; $author_i = 0; $editor_i = 0; $translator_i = 0;
      while (isset($result->creators[$i])) {
        $creatorType = isset($result->creators[$i]->creatorType) ? $result->creators[$i]->creatorType : 'author';
        if (isset($result->creators[$i]->firstName) && isset($result->creators[$i]->lastName)) {
          switch ($creatorType) {
            case 'author': case 'contributor': case 'artist':
              $authorParam = 'author' . (string) ++$author_i;
              break;
            case 'editor':
              $authorParam = 'editor' . (string) ++$editor_i;
              break;
            case 'translator':
              $authorParam = 'translator' . (string) ++$translator_i;
              break;
             case 'reviewedAuthor':   // @codeCoverageIgnore
              $authorParam = '';      // @codeCoverageIgnore
              break;                  // @codeCoverageIgnore
            default:                                                               // @codeCoverageIgnore
              report_minor_error("Unrecognized creator type: " . echoable($creatorType) . ' FROM ' . echoable($url));    // @codeCoverageIgnore
              $authorParam = '';                                                   // @codeCoverageIgnore
          }
         if ($authorParam && author_is_human($result->creators[$i]->firstName . ' ' . $result->creators[$i]->lastName)) {
            if (strtolower((string) $result->creators[$i]->lastName ) === 'published') $result->creators[$i]->lastName  ='';
            if (strtolower((string) $result->creators[$i]->firstName) === 'published') $result->creators[$i]->firstName ='';
            $template->validate_and_add($authorParam, (string) $result->creators[$i]->lastName, (string) $result->creators[$i]->firstName,
            isset($result->rights) ? (string) $result->rights : '', FALSE);
             // Break out if nothing added
            if ((strpos($authorParam, 'author') === 0) &&
                     $template->blank(['author' . (string)($author_i), 'first' . (string)($author_i), 'last' . (string)($author_i)])) break;
            if ((strpos($authorParam, 'editor') === 0) &&
                     $template->blank(['editor' . (string)($editor_i)])) break;
            if ((strpos($authorParam, 'translator') === 0) &&
                     $template->blank(['translator' . (string)($translator_i)])) break;
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
  if ($template->wikiname() === 'cite web') {
    if (stripos($url, 'businesswire.com/news') !== FALSE ||
        stripos($url, 'prnewswire.com/') !== FALSE ||
        stripos($url, 'globenewswire.com/') !== FALSE ||
        stripos($url, 'newswire.com/') !== FALSE) {
      $template->change_name_to('cite press release');
    }
  }
  return TRUE;
}

public static function url_simplify(string $url) : string {
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
  /** @psalm-suppress UnusedFunctionCall */
  @strtok('',''); // Free internal buffers with empty unused call
  return $url;
}

public static function find_indentifiers_in_urls(Template $template, ?string $url_sent = NULL) : bool {
    set_time_limit(120);
    if (is_null($url_sent)) {
       // Chapter URLs are generally better than URLs for the whole book.
        if ($template->has('url') && $template->has('chapterurl')) {
           return (bool) ((int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'chapterurl ') +
                          (int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'url '));
        } elseif ($template->has('url') && $template->has('chapter-url')) {
           return (bool) ((int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'chapter-url ') +
                          (int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'url '));
        } elseif ($template->has('url')) {
           $url = $template->get('url');
           $url_type = 'url';
        } elseif ($template->has('chapter-url')) {
           $url = $template->get('chapter-url');
           $url_type = 'chapter-url';
        } elseif ($template->has('chapterurl')) {
           $url = $template->get('chapterurl');
           $url_type = 'chapterurl';
        } elseif ($template->has('conference-url')) {
           $url = $template->get('conference-url');
           $url_type = 'conference-url';
        } elseif ($template->has('conferenceurl')) {
           $url = $template->get('conferenceurl');
           $url_type = 'conferenceurl';
        } elseif ($template->has('contribution-url')) {
           $url = $template->get('contribution-url');
           $url_type = 'contribution-url';
        } elseif ($template->has('contributionurl')) {
           $url = $template->get('contributionurl');
           $url_type = 'contributionurl';
        } elseif ($template->has('article-url')) {
           $url = $template->get('article-url');
           $url_type = 'article-url';
        } elseif ($template->has('website')) { // No URL, but a website
          $url = trim($template->get('website'));
          if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
            $url = "h" . $url;
          }
          if (strtolower(substr( $url, 0, 4 )) !== "http" ) {
            $url = "http://" . $url; // Try it with http
          }
          if (preg_match (REGEXP_IS_URL, $url) !== 1) return FALSE;  // See https://mathiasbynens.be/demo/url-regex/  This regex is more exact than validator.  We only spend time on this after quick and dirty check is passed
          if (preg_match ('~^https?://[^/]+/?$~', $url) === 1) return FALSE; // Just a host name
          $template->rename('website', 'url'); // Change name it first, so that parameters stay in same order
          $template->set('url', $url);
          $url_type = 'url';
          quietly('report_modification', "website is actually HTTP URL; converting to use url parameter.");
        } else {
          // If no URL or website, nothing to worth with.
          return FALSE;
        }
    } elseif (preg_match('~^' . MAGIC_STRING_URLS . '(\S+) $~', $url_sent, $matches)) {
      $url_sent = NULL;
      $url_type = $matches[1];
      $url      = $template->get($matches[1]);
    } else {
      $url = $url_sent;
      $url_type = 'An invalid value';
    }

    if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
      $url = "h" . $url;
      if (is_null($url_sent)) {
        $template->set($url_type, $url); // Save it
      }
    }
    // Common ones that do not help
    if (strpos($url, 'books.google') !==FALSE) return FALSE;
    if (strpos($url, 'researchgate.net') !==FALSE) return FALSE;
    if (strpos($url, 'academia.edu') !==FALSE) return FALSE;
   
    // Abstract only websites
    if (strpos($url, 'orbit.dtu.dk/en/publications') !== FALSE) { // This file path only
       if (is_null($url_sent)) {
         if ($template->has('pmc')) {
            $template->forget($url_type); // Remove it to make room for free-link
         } elseif ($template->has('doi') && $template->get('doi-access') === 'free') {
            $template->forget($url_type); // Remove it to make room for free-link
         }
       }
       return FALSE;
    }
    // IEEE
    if (strpos($url, 'ieeexplore') !== FALSE) {
     if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
       $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Update URL with cleaner one
       }
     }
     if (preg_match('~^https?://ieeexplore\.ieee\.org(?:|\:80)/(?:|abstract/)document/(\d+)/?(?:|\?reload=true)$~', $url, $matches)) {
       $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Normalize to HTTPS and remove abstract and remove trailing slash etc
       }
     }
     if (preg_match('~^https?://ieeexplore\.ieee\.org.*/iel5/\d+/\d+/(\d+).pdf(?:|\?.*)$~', $url, $matches)) {
       $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Normalize
       }
     }
     if (preg_match('~^https://ieeexplore\.ieee\.org/document/0+(\d+)$~', $url, $matches)) {
       $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Trimming leading zeroes
       }
     }
    }

    // semanticscholar
    if (stripos($url, 'semanticscholar.org') !== FALSE) {
       $s2cid = getS2CID($url);
       if ($s2cid === '') return FALSE;
       if ($template->has('s2cid') && $s2cid !== $template->get('s2cid')) {
          report_warning('Existing URL does not match existing S2CID: ' .  echoable($template->get('s2cid')));
          return FALSE;
       }
       if ($template->has('S2CID') && $s2cid !== $template->get('S2CID')) {
          report_warning('Existing URL does not match existing S2CID: ' .  echoable($template->get('S2CID')));
          return FALSE;
       }
       $template->add_if_new('s2cid', $s2cid);
       if (is_null($url_sent) && stripos($url, 'pdf') === FALSE) {
         if ($template->has_good_free_copy()) {
           $template->forget($url_type);
           return TRUE;
         }
       }
       if (is_null($url_sent) && get_semanticscholar_license($s2cid) === FALSE) {
         report_warning('Should probably remove un-licensed Semantic Scholar URL that was converted to S2CID parameter');
         // SEP 2020 $template->forget($url_type);
         return TRUE;
       }
       return TRUE;
    }
  
    if (preg_match("~^(https?://.+\/.+)\?casa_token=.+$~", $url, $matches)) {
      $url = $matches[1];
      if (is_null($url_sent)) {
        $template->set($url_type, $url); // Update URL with cleaner one
      }
    }

    if (stripos($url, 'jstor') !== FALSE) {
     // Trim ?seq=1#page_scan_tab_contents off of jstor urls
     // We do this since not all jstor urls are recognized below
     if (preg_match("~^(https?://\S*jstor.org\S*)\?seq=1#[a-zA-Z_]+$~", $url, $matches)) {
       $url = $matches[1];
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Update URL with cleaner one
       }
     }
     if (preg_match("~^(https?://\S*jstor.org\S*)\?refreqid=~", $url, $matches)) {
       $url = $matches[1];
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Update URL with cleaner one
       }
     }
     if (preg_match("~^(https?://\S*jstor.org\S*)\?origin=~", $url, $matches)) {
       if (stripos($url, "accept") !== FALSE) {
         bot_debug_log("Accept Terms and Conditions JSTOR found : " . $url);
       } else {
         $url = $matches[1];
         if (is_null($url_sent)) {
           $template->set($url_type, $url); // Update URL with cleaner one
         }
       }
     }
     if (stripos($url, 'plants.jstor.org') !== FALSE) {
      return FALSE; # Plants database, not journal
     }
     // https://www.jstor.org.stuff/proxy/stuff/stable/10.2307/3347357 and such
     // Optional 0- at front.
     // DO NOT change www.jstor.org to www\.jstor\.org  -- Many proxies use www-jstor-org
     if (preg_match('~^(https?://(?:0-www.|www.|)jstor.org)(?:\S*proxy\S*/|/)(?:stable|discover)/10.2307/(.+)$~i', $url, $matches)) {
       $url = $matches[1] . '/stable/' . $matches[2] ; // that is default.  This also means we get jstor not doi
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Update URL with cleaner one.  Will probably call forget on it below
       }
     }
     // https://www.jstor.org.libweb.lib.utsa.edu/stable/3347357 and such
     // Optional 0- at front.
     // DO NOT change www.jstor.org to www\.jstor\.org  -- Many proxies use www-jstor-org
     // https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10 and such
     if (preg_match('~^https?://(?:0-www.|www.|)jstor.org\.[^/]+/(?:stable|discover)/(.+)$~i', $url, $matches)) {
       $url = 'https://www.jstor.org/stable/' . $matches[1] ;
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Update URL with cleaner one
       }
     }
     // Remove junk from URLs
     while (preg_match('~^https?://www\.jstor\.org/stable/(.+)(?:&ved=|&usg=|%3Fseq%3D1|\?|#metadata_info_tab_contents)~i', $url, $matches)) {
       $url = 'https://www.jstor.org/stable/' . $matches[1] ;
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Update URL with cleaner one
       }
     }

     if (preg_match('~^https?://(?:www\.|)jstor\.org/stable/(?:pdf|pdfplus)/(.+)\.pdf$~i', $url, $matches) ||
        preg_match('~^https?://(?:www\.|)jstor\.org/tc/accept\?origin=(?:\%2F|/)stable(?:\%2F|/)pdf(?:\%2F|/)(\d{3,})\.pdf$~i', $url, $matches)) {
       if ($matches[1] === $template->get('jstor')) {
         if (is_null($url_sent)) {
           if ($template->has_good_free_copy()) $template->forget($url_type);
         }
         return FALSE;
       } elseif ($template->blank('jstor')) {
          curl_setopt_array(self::$ch_jstor,
                            [CURLOPT_URL => 'https://www.jstor.org/citation/ris/' . $matches[1],
                             CURLOPT_HEADER => FALSE,
                             CURLOPT_NOBODY => FALSE]);
          $dat = (string) @curl_exec(self::$ch_jstor);
          if ($dat &&
              stripos($dat, 'No RIS data found for') === FALSE &&
              stripos($dat, 'Block Reference') === FALSE &&
              stripos($dat, 'A problem occurred trying to deliver RIS data') === FALSE &&
              substr_count($dat, '-') > 3) { // It is actually a working JSTOR.  Not sure if all PDF links are done right
            if (is_null($url_sent) && $template->has_good_free_copy()) $template->forget($url_type);
            return $template->add_if_new('jstor', $matches[1]);
          }
        }
     }
     if ($template->has('jstor') && preg_match('~^https?://(?:www\.|)jstor\.org/(?:stable|discover)/(?:|pdf/)' . $template->get('jstor') . '(?:|\.pdf)$~i', $url)) {
       if (is_null($url_sent)) {
         if ($template->has_good_free_copy()) $template->forget($url_type);
       }
       return FALSE;
     }
    } // JSTOR
    if (preg_match('~^https?://(?:www\.|)archive\.org/detail/jstor\-(\d{5,})$~i', $url, $matches)) {
       $template->add_if_new('jstor', $matches[1]);
       if (is_null($url_sent)) {
         if ($template->has_good_free_copy()) $template->forget($url_type);
       }
       return FALSE;
    }

    if (preg_match('~^https?(://(?:0-www\.|www\.|ucsb\.|)worldcat(?:libraries|)\.org.+)(?:\&referer=brief_results|\?referer=di&ht=edition|\?referer=brief_results|%26referer%3Dbrief_results|\?ht=edition&referer=di|\?referer=br&ht=edition|\/viewport)$~i', $url, $matches)) {
       $url = 'https' . $matches[1];
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Update URL with cleaner one
       }
    }
    if (preg_match('~^https?(://(?:0-www\.|www\.|ucsb\.)worldcat(?:libraries|)\.org.+)/oclc/(\d+)$~i', $url, $matches)) {
       $url = 'https://www.worldcat.org/oclc/' . $matches[2];
       if (is_null($url_sent)) {
         $template->set($url_type, $url); // Update URL with cleaner one
       }
    }
  
    if (preg_match("~^https?://(?:(?:dx\.|www\.|)doi\.org|doi\.library\.ubc\.ca)/([^\?]*)~i", $url, $match)) {
      if ($template->has('doi')) {
        $doi = $template->get('doi');
        if (str_i_same($doi, $match[1]) || str_i_same($doi, urldecode($match[1]))) {
         if (is_null($url_sent) && $template->get('doi-access') === 'free') {
          quietly('report_modification', "URL is hard-coded DOI; removing since we already have free DOI parameter");
          $template->forget($url_type);
         }
         return FALSE;
        }
        // The DOIs do not match
        if (is_null($url_sent)) {
         report_warning('doi.org URL does not match existing DOI parameter, investigating...');
        }
        if ($doi !== $template->get3('doi')) return FALSE;
        if (doi_works($match[1]) && !doi_works($doi)) {
          $template->set('doi', $match[1]);
          if (is_null($url_sent)) {
            if ($template->has_good_free_copy()) $template->forget($url_type);
          }
          return TRUE;
        }
        if (!doi_works($match[1]) && doi_works($doi)) {
          if (is_null($url_sent)) {
             if ($template->has_good_free_copy()) $template->forget($url_type);
          }
          return FALSE;
        }
        return FALSE; // Both valid or both invalid (could be legit if chapter and book are different DOIs
      }
      if ($template->add_if_new('doi', urldecode($match[1]))) { // Will expand from DOI when added
        if (is_null($url_sent) && $template->has_good_free_copy()) {
          quietly('report_modification', "URL is hard-coded DOI; converting to use DOI parameter.");
          $template->forget($url_type);
        }
        return TRUE;
      } else {
        return FALSE; // "bad" doi?
      }
    }
    if (stripos($url, 'oxforddnb.com') !== FALSE) return FALSE; // generally bad, and not helpful
    if ($doi = extract_doi($url)[1]) {
      if (bad_10_1093_doi($doi)) return FALSE;
      $old_jstor = $template->get('jstor');
      if (stripos($url, 'jstor')) check_doi_for_jstor($doi, $template);
      if (is_null($url_sent) && $old_jstor !== $template->get('jstor') && stripos($url, 'pdf') === FALSE) {
         if ($template->has_good_free_copy()) $template->forget($url_type);
      }
      $template->tidy_parameter('doi'); // Sanitize DOI before comparing
      if ($template->has('doi') && stripos($doi, $template->get('doi')) === 0) { // DOIs are case-insensitive
        if (doi_works($doi) && is_null($url_sent) && strpos(strtolower($url), ".pdf") === FALSE && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
          if ($template->has_good_free_copy()) {
             report_forget("Recognized existing DOI in URL; dropping URL");
             $template->forget($url_type);
          }
        }
        return FALSE;  // URL matched existing DOI, so we did not use it
      }
      if ($template->add_if_new('doi', $doi)) {
        if (doi_active($doi)) {
          if (is_null($url_sent)) {
            if (mb_strpos(strtolower($url), ".pdf") === FALSE && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
              if ($template->has_good_free_copy()) {
                report_forget("Recognized DOI in URL; dropping URL");
                $template->forget($url_type);
              }
            } else {
              report_info("Recognized DOI in URL.  Leaving *.pdf URL.");
            }
          }
        } else {
          $template->mark_inactive_doi();
        }
        return TRUE; // Added new DOI
      }
      return FALSE; // Did not add it
    } elseif ($template->has('doi')) { // Did not find a doi, perhaps we were wrong
      $template->tidy_parameter('doi'); // Sanitize DOI before comparing
      $doi = $template->get('doi');
      if (stripos($url, $doi) !== FALSE) { // DOIs are case-insensitive
        if (doi_works($doi) && is_null($url_sent) && strpos(strtolower($url), ".pdf") === FALSE && not_bad_10_1093_doi($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
          if ($template->has_good_free_copy()) {
             report_forget("Recognized the existing DOI in URL; dropping URL");
             $template->forget($url_type);
          }
        }
        return FALSE;  // URL matched existing DOI, so we did not use it
      }
    }

    // JSTOR

    if (stripos($url, "jstor.org") !== FALSE) {
      $sici_pos = stripos($url, "sici");
      if ($sici_pos) {  //  Outdated url style
        $template->use_sici(); // Grab what we can before getting rid off it
        // Need to encode the sici bit that follows sici?sici= [10 characters]
        $encoded_url = substr($url, 0, $sici_pos + 10) . urlencode(urldecode(substr($url, $sici_pos + 10)));
        curl_setopt_array(self::$ch_jstor, [CURLOPT_URL => $encoded_url,
                                            CURLOPT_HEADER => TRUE,
                                            CURLOPT_NOBODY => TRUE]);
        if (@curl_exec(self::$ch_jstor)) {
          $redirect_url = (string) @curl_getinfo(self::$ch_jstor, CURLINFO_REDIRECT_URL);
          if (strpos($redirect_url, "jstor.org/stable/")) {
            $url = $redirect_url;
            if (is_null($url_sent)) {
              $template->set($url_type, $url); // Save it
            }
          } else {  // We do not want this URL incorrectly parsed below, or even waste time trying.
            return FALSE;     // @codeCoverageIgnore
          }
        }
      }
      if (preg_match("~^/(?:\w+/)*(\d{5,})[^\d%\-]*(?:\?|$)~", substr($url, (int) stripos($url, 'jstor.org') + 9), $match) ||
                preg_match("~^https?://(?:www\.)?jstor\.org\S+(?:stable|discovery)/(?:10\.7591/|)(\d{5,}|(?:j|J|histirel|jeductechsoci|saoa|newyorkhist)\.[a-zA-Z0-9\.]+)$~", $url, $match)) {
        if (is_null($url_sent)) {
          if ($template->has_good_free_copy()) $template->forget($url_type);
        }
        if ($template->has('jstor')) {
          quietly('report_inaction', "Not using redundant URL (jstor parameter set)");
        } else {
          quietly('report_modification', "Converting URL to JSTOR parameter " . jstor_link(urldecode($match[1])));
          $template->set('jstor', urldecode($match[1]));
        }
        if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
        return TRUE;
      } else {
        return FALSE; // Jstor URL yielded nothing
      }
    } else {
      if (preg_match(REGEXP_BIBCODE, urldecode($url), $bibcode)) {
        if ($template->blank('bibcode')) {
          quietly('report_modification', "Converting url to bibcode parameter");
          if (is_null($url_sent)) {
            if ($template->has_good_free_copy()) $template->forget($url_type);
          }
          return $template->add_if_new('bibcode', urldecode($bibcode[1]));
        } elseif (is_null($url_sent) && urldecode($bibcode[1]) === $template->get('bibcode')) {
          if ($template->has_good_free_copy()) $template->forget($url_type);
        }
      } elseif (stripos($url, '.nih.gov') !== FALSE) {
         
        if (preg_match("~^https?://(?:www\.|)pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d{4,})"
                        . "|^https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/|labs/|)pmc/articles/(?:PMC|instance)?(\d{4,})~i", $url, $match)) {
          if (preg_match("~\?term~i", $url)) return FALSE; // A search such as https://www.ncbi.nlm.nih.gov/pmc/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
          if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
          if ($template->blank('pmc')) {
            quietly('report_modification', "Converting URL to PMC parameter");
          }
          $new_pmc = @$match[1] . @$match[2];
          if (is_null($url_sent)) {
            if (stripos($url, ".pdf") !== FALSE) {
              $test_url = "https://www.ncbi.nlm.nih.gov/pmc/articles/PMC" . $new_pmc . "/";
              curl_setopt_array(self::$ch_pmc, [CURLOPT_URL => $test_url]);
              @curl_exec(self::$ch_pmc);
              $httpCode = (int) @curl_getinfo(self::$ch_pmc, CURLINFO_HTTP_CODE);
              if ($httpCode > 399 || ($httpCode === 0)) { // Some PMCs do NOT resolve.  So leave URL
                return $template->add_if_new('pmc', $new_pmc);
              }
            }
            if (stripos(str_replace("printable", "", $url), "table") === FALSE) $template->forget($url_type); // This is the same as PMC auto-link
          }
          return $template->add_if_new('pmc', $new_pmc);
        
        } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?\?term=(\d+)$~', $url, $match)) {
           $pos_pmid = $match[1];
           $old_pmid = $template->get('pmid');
           if ($old_pmid === '' || ($old_pmid === $pos_pmid)) {
              $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $pos_pmid .'/');
              $template->add_if_new('pmid', $pos_pmid);
              return TRUE;
           } else {
              report_warning(echoable($url) . ' does not match PMID of ' . echoable($old_pmid));
           }
           return FALSE;
        } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?\?term=.*$~', $url) && ($template->has('pmid') || $template->has('pmc'))) {
           report_info('Dropped non-specific pubmed search URL, since PMID is present');
           $template->forget($url_type);
           return FALSE;
        } elseif (preg_match("~^https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?"
        . "(?:pubmed/|"
        . "/eutils/elink\.fcgi\S+dbfrom=pubmed\S+/|"
        . "entrez/query\.fcgi\S+db=pubmed\S+|"
        . "pmc/articles/pmid/)"
        . ".*?=?(\d{4,})~i", $url, $match)||
            preg_match("~^https?://pubmed\.ncbi\.nlm\.nih\.gov/(?:|entrez/eutils/elink.fcgi\?dbfrom=pubmed\&retmode=ref\&cmd=prlinks\&id=)(\d{4,})/?(?:|#.+|-.+|\?.+)$~", $url, $match)
          ) {
          if (preg_match("~\?term~i", $url) && !preg_match("~pubmed\.ncbi\.nlm\.nih\.gov/\d{4,}/\?from_term=~", $url)) {
            if (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?(\?term=.*)$~', $url, $matches)) {
                $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $matches[1]);
            }
            return FALSE; // A search such as https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
          }
          if ($template->blank('pmid')) quietly('report_modification', "Converting URL to PMID parameter");
          if (is_null($url_sent)) {
            if ($template->has_good_free_copy()) $template->forget($url_type);
          }
          if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
          return $template->add_if_new('pmid', $match[1]);
        
        } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/entrez/eutils/elink.fcgi\?.+tool=sumsearch\.org.+id=(\d+)$~', $url, $match)) {
          if ($url_sent) return FALSE;   // Many do not work
          if ($template->blank(['doi', 'pmc'])) return FALSE;  // This is a redirect to the publisher, not pubmed
          if ($match[1] === $template->get('pmc')) {
             $template->forget($url_type); // Same as PMC-auto-link
          } elseif ($match[1] === $template->get('pmid')) {
             if ($template->has_good_free_copy()) $template->forget($url_type);
          }
          return FALSE;
        } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?(\?term=.*)$~', $url, $matches)) {
           $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $matches[1]);
           return FALSE;
        }

      } elseif (stripos($url, 'europepmc.org') !== FALSE) {
        if (preg_match("~^https?://(?:www\.|)europepmc\.org/articles?/pmc/?(\d{4,})~i", $url, $match) ||
            preg_match("~^https?://(?:www\.|)europepmc\.org/scanned\?pageindex=(?:\d+)\&articles=pmc(\d{4,})~i", $url, $match)) {
         if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
         if ($template->blank('pmc')) {
           quietly('report_modification', "Converting Europe URL to PMC parameter");
         }
         if (is_null($url_sent) && stripos($url, ".pdf") === FALSE) {
            $template->forget($url_type); // This is same as PMC-auto-link
         }
         return $template->add_if_new('pmc', $match[1]);
        } elseif (preg_match("~^https?://(?:www\.|)europepmc\.org/(?:abstract|articles?)/med/(\d{4,})~i", $url, $match)) {
         if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
         if ($template->blank('pmid')) {
           quietly('report_modification', "Converting Europe URL to PMID parameter");
         }
         if (is_null($url_sent)) {
            if ($template->has_good_free_copy()) $template->forget($url_type);
         }
         return $template->add_if_new('pmid', $match[1]);
        }
        return FALSE;
      } elseif (stripos($url, 'pubmedcentralcanada.ca') !== FALSE) {
       if (preg_match("~^https?://(?:www\.|)pubmedcentralcanada\.ca/pmcc/articles/PMC(\d{4,})(?:|/.*)$~i", $url, $match)) {
        if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
        if ($template->blank('pmc')) quietly('report_modification', "Converting Canadian URL to PMC parameter");
        if (is_null($url_sent)) {
            $template->forget($url_type);  // Always do this conversion, since website is gone!
        }
        return $template->add_if_new('pmc', $match[1]);
       }
       return FALSE;
      } elseif (stripos($url, 'citeseerx') !== FALSE) {
       if (preg_match("~^https?://citeseerx\.ist\.psu\.edu/viewdoc/(?:summary|download)(?:\;jsessionid=[^\?]+|)\?doi=([0-9.]*)(?:&.+)?~", $url, $match)) {
        if ($template->blank('citeseerx')) quietly('report_modification', "URL is hard-coded citeseerx; converting to use citeseerx parameter.");
        if (is_null($url_sent)) {
          if ($template->has_good_free_copy()) {
            $template->forget($url_type);
            if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
          }
        }
        return $template->add_if_new('citeseerx', urldecode($match[1])); // We cannot parse these at this time
       }
       return FALSE;

      } elseif (stripos($url, 'arxiv') !== FALSE) {
       if (preg_match("~\barxiv\.org/.*(?:pdf|abs|ftp/arxiv/papers/\d{4})/(.+?)(?:\.pdf)?$~i", $url, $match)) {

        /* ARXIV
         * See https://arxiv.org/help/arxiv_identifier for identifier formats
         */
        if (   preg_match("~[A-z\-\.]+/\d{7}~", $match[1], $arxiv_id) // pre-2007
            || preg_match("~\d{4}\.\d{4,5}(?:v\d+)?~", $match[1], $arxiv_id) // post-2007
            ) {
          quietly('report_modification', "Converting URL to arXiv parameter");
          $ret = $template->add_if_new('arxiv', $arxiv_id[0]); // Have to add before forget to get cite type right
          if (is_null($url_sent)) {
            if ($template->has_good_free_copy() || $template->has('arxiv')  || $template->has('eprint')) $template->forget($url_type);
          }
          return $ret;
        }
        if ($template->wikiname() === 'cite web') $template->change_name_to('cite arxiv');
       }
       return FALSE;

      } elseif (preg_match("~^https?://(?:www\.|)amazon(?P<domain>\.[\w\.]{1,7})/.*dp/(?P<id>\d+X?)~i", $url, $match)) {

        if ($template->wikiname() === 'cite web') $template->change_name_to('cite book');
        if ($match['domain'] === ".com") {
          if (is_null($url_sent)) {
            $template->forget($url_type);
            if (stripos($template->get('publisher'), 'amazon') !== FALSE) {
              $template->forget('publisher');
            }
          }
          if ($template->blank('asin')) {
            quietly('report_modification', "Converting URL to ASIN parameter");
            return $template->add_if_new('asin', $match['id']);
          }
        } else {
          if ($template->has('isbn')) { // Already have ISBN
            quietly('report_inaction', "Not converting ASIN URL: redundant to existing ISBN.");
          } else {
            quietly('report_modification', "Converting URL to ASIN template");
            $template->set('id', $template->get('id') . " {{ASIN|{$match['id']}|country=" . str_replace(array(".co.", ".com.", "."), "", $match['domain']) . "}}");
          }
          if (is_null($url_sent)) {
            $template->forget($url_type); // will forget accessdate too
            if (stripos($template->get('publisher'), 'amazon') !== FALSE) {
              $template->forget('publisher');
            }
          }
        }
      } elseif (stripos($url, 'handle') !== FALSE || stripos($url, 'persistentId=hdl:') !== FALSE) {
          // Special case of hdl.handle.net/123/456
          if (preg_match('~^https?://hdl\.handle\.net/(\d{2,}.*/.+)$~', $url, $matches)) {
            $url = 'https://hdl.handle.net/handle/' . $matches[1];
          }
          // Hostname
          $handle1 = FALSE;
          foreach (HANDLES_HOSTS as $hosts) {
            if (preg_match('~^https?://' . str_replace('.', '\.', $hosts) . '(/.+)$~', $url, $matches)) {
              $handle1 = $matches[1];
              break;
            }
          }
          if ($handle1 === FALSE) return FALSE;
          // file path
          $handle = FALSE;
          foreach (HANDLES_PATHS as $handle_path) {
            if (preg_match('~^' . $handle_path . '(.+)$~', $handle1, $matches)) {
              $handle = $matches[1];
              break;
            }
          }
          if ($handle === FALSE) return FALSE;
          // Trim off session stuff - urlappend seems to be used for page numbers and such
          $handle = str_ireplace('%3B', ';', $handle);
          while (preg_match('~^(.+)(?:/browse\?|;jsessionid|;sequence=|\?sequence=|&isAllowed=|&origin=|&rd=|\?value=|&type=|/browse-title|&submit_browse=|;ui=embed)~',
                                $handle, $matches)) {
            $handle = $matches[1];
          }
          $handle = hdl_decode($handle);
          if (preg_match('~^(.+)%3Bownerid=~', $handle, $matches)) {
            if (hdl_works($matches[1])) {
               $handle = $matches[1];
            }
          }
          // Verify that it works as a hdl - first with urlappend, since that is often page numbers
          if (preg_match('~^(.+)\?urlappend=~', $handle, $matches)) {  // should we shorten it?
            if (hdl_works($handle) === FALSE) {
               $handle = $matches[1];   // @codeCoverageIgnore
            } elseif (hdl_works($handle) === NULL && (hdl_works($matches[1]) === NULL || hdl_works($matches[1]) === FALSE)) {
               ; // Do nothing
            } elseif (hdl_works($handle) === NULL) {
               $handle = $matches[1]; // @codeCoverageIgnore
            } else  { // Both work
               $long  = hdl_works($handle);
               $short = hdl_works($matches[1]);
               if ($long === $short) { // urlappend does nothing
                 $handle = $matches[1]; // @codeCoverageIgnore
               }
            }
          }
          while (preg_match('~^(.+)/$~', $handle, $matches)) { // Trailing slash
            $handle = $matches[1];
          }
          while (preg_match('~^/(.+)$~', $handle, $matches)) { // Leading slash
            $handle = $matches[1];
          }
          // Safety check
          if (strlen($handle) < 6 || strpos($handle, '/') === FALSE) return FALSE;
          if (strpos($handle, '123456789') === 0) return FALSE;

          $the_question = strpos($handle, '?');
          if ($the_question !== FALSE) {
             $handle = substr($handle, 0, $the_question) . '?' . str_replace('%3D', '=', urlencode(substr($handle, $the_question+1)));
          }

          // Verify that it works as a hdl
          $the_header_loc = hdl_works($handle);
          if ($the_header_loc === FALSE || $the_header_loc === NULL) return FALSE;
          if ($template->blank('hdl')) quietly('report_modification', "Converting URL to HDL parameter");
          if (is_null($url_sent)) {
             if ($template->has_good_free_copy()) $template->forget($url_type);
          }
          if (preg_match('~^([^/]+/[^/]+)/.*$~', $handle, $matches)   // Might be padded with stuff
            && stripos($the_header_loc, $handle) === FALSE
            && stripos($the_header_loc, $matches[1]) !== FALSE) {  // Too long ones almost never resolve, but we have seen at least one
              $handle = $matches[1]; // @codeCoverageIgnore
          }
          return $template->add_if_new('hdl', $handle);
      } elseif (stripos($url, 'zbmath.org') !== FALSE) {
         if (preg_match("~^https?://zbmath\.org/\?(?:format=complete&|)q=an:([0-9][0-9][0-9][0-9]\.[0-9][0-9][0-9][0-9][0-9])~i", $url, $match)) {
          if ($template->blank('zbl')) quietly('report_modification', "Converting URL to ZBL parameter");
          if (is_null($url_sent)) {
             if ($template->has_good_free_copy()) {
               $template->forget($url_type);
               if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
             }
          }
          return $template->add_if_new('zbl', $match[1]);
         } elseif (preg_match("~^https?://zbmath\.org/\?(?:format=complete&|)q=an:([0-9][0-9]\.[0-9][0-9][0-9][0-9]\.[0-9][0-9])~i", $url, $match)) {
          if ($template->blank('jfm')) quietly('report_modification', "Converting URL to JFM parameter");
          if (is_null($url_sent)) {
             if ($template->has_good_free_copy()) {
               $template->forget($url_type);
               if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
             }
          }
          return $template->add_if_new('jfm', $match[1]);
         }
         return FALSE;
      } elseif (preg_match("~^https?://mathscinet\.ams\.org/mathscinet-getitem\?mr=([0-9]+)~i", $url, $match)) {
          if ($template->blank('mr')) quietly('report_modification', "Converting URL to MR parameter");
          if (is_null($url_sent)) {
             // SEP 2020 $template->forget($url_type); This points to a review and not the article
          }
          return $template->add_if_new('mr', $match[1]);
      } elseif (preg_match("~^https?://papers\.ssrn\.com(?:/sol3/papers\.cfm\?abstract_id=|/abstract=)([0-9]+)~i", $url, $match)) {
          if ($template->blank('ssrn')) quietly('report_modification', "Converting URL to SSRN parameter");
          if (is_null($url_sent)) {
             if ($template->has_good_free_copy()) {
               $template->forget($url_type);
               if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
             }
          }
          return $template->add_if_new('ssrn', $match[1]);
      } elseif (stripos($url, 'osti.gov') !== FALSE) {
         if (preg_match("~^https?://(?:www\.|)osti\.gov/(?:scitech/|)(?:biblio/|)(?:purl/|)([0-9]+)(?:\.pdf|)~i", $url, $match)) {
          if ($template->blank('osti')) quietly('report_modification', "Converting URL to OSTI parameter");
          if (is_null($url_sent)) {
             if ($template->has_good_free_copy()) {
               $template->forget($url_type);
               if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
             }
          }
          return $template->add_if_new('osti', $match[1]);
         } elseif (preg_match("~^https?://(?:www\.|)osti\.gov/energycitations/product\.biblio\.jsp\?osti_id=([0-9]+)~i", $url, $match)) {
          if ($template->blank('osti')) quietly('report_modification', "Converting URL to OSTI parameter");
          if (is_null($url_sent)) {
             if ($template->has_good_free_copy()) {
               $template->forget($url_type);
               if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');
             }
          }
          return $template->add_if_new('osti', $match[1]);
         }
         return FALSE;
      } elseif (stripos($url, 'worldcat.org') !== FALSE) {
        if (preg_match("~^https?://(?:www\.|)worldcat\.org(?:/title/\S+)?/oclc/([0-9]+)~i", $url, $match)) {
          if (strpos($url, 'edition') && ($template->wikiname() !== 'cite book')) {
            report_warning('Not adding OCLC because is appears to be a weblink to a list of editions: ' . $match[1]);
            return FALSE;
          }
          if ($template->blank('oclc')) quietly('report_modification', "Converting URL to OCLC parameter");
          if ($template->wikiname() === 'cite web') $template->change_name_to('cite book');  // Better template choice
          if (is_null($url_sent)) {
             // SEP 2020 $template->forget($url_type);
          }
          return $template->add_if_new('oclc', $match[1]);
        } elseif (preg_match("~^https?://(?:www\.|)worldcat\.org/issn/(\d{4})(?:|-)(\d{3}[\dxX])$~i", $url, $match)) {
          if ($template->blank('issn')) quietly('report_modification', "Converting URL to ISSN parameter");
          if ($template->wikiname() === 'cite web') $template->change_name_to('cite journal');  // Better template choice
          if (is_null($url_sent)) {
             // SEP 2020 $template->forget($url_type);
          }
          return $template->add_if_new('issn_force', $match[1] . '-' . $match[2]);
        }
        return FALSE;
      } elseif (preg_match("~^https?://lccn\.loc\.gov/(\d{4,})$~i", $url, $match)  &&
                (stripos($template->parsed_text(), 'library') === FALSE)) { // Sometimes it is web cite to Library of Congress
          if ($template->wikiname() === 'cite web') $template->change_name_to('cite book');  // Better template choice
          if ($template->blank('lccn')) quietly('report_modification', "Converting URL to LCCN parameter");
          if (is_null($url_sent)) {
             // SEP 2020 $template->forget($url_type);
          }
          return $template->add_if_new('lccn', $match[1]);
      } elseif (preg_match("~^https?://openlibrary\.org/books/OL/?(\d{4,}[WM])(?:|/.*)$~i", $url, $match)) { // We do W "work" and M "edition", but not A, which is author
          if ($template->blank('ol')) quietly('report_modification', "Converting URL to OL parameter");
          if ($template->wikiname() === 'cite web') $template->change_name_to('cite book');  // Better template choice
          if (is_null($url_sent)) {
             // SEP 2020 $template->forget($url_type);
          }
          return $template->add_if_new('ol', $match[1]);
      } elseif (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/(\d{4,})$~i", $url, $match) && $template->has('title') && $template->blank('id')) {
        if ($template->add_if_new('id', '{{ProQuest|' . $match[1] . '}}')) {
          quietly('report_modification', 'Converting URL to ProQuest parameter');
          if (is_null($url_sent)) {
             if ($template->has_good_free_copy()) $template->forget($url_type);
          }
          return TRUE;
        }
      } elseif (($template->has('chapterurl') || $template->has('chapter-url') || $template->has('url') || ($url_type === 'url') || ($url_type === 'chapterurl')  || ($url_type === 'chapter-url')) && preg_match("~^https?://web\.archive\.org/web/\d{14}/(https?://.*)$~", $url, $match) && $template->blank(['archiveurl', 'archive-url'])) {
          if (is_null($url_sent)) {
             quietly('report_modification', 'Extracting URL from archive');
             $template->set($url_type, $match[1]);
             $template->add_if_new('archive-url', $match[0]);
             return FALSE; // We really got nothing
          }
      }
    }
    return FALSE ;
 }
  
} // End of CLASS
