<?php
declare(strict_types=1);

require_once 'constants.php';     // @codeCoverageIgnore
require_once 'Template.php';      // @codeCoverageIgnore
require_once 'big_jobs.php';      // @codeCoverageIgnore

final class HandleCache {
  // Greatly speed-up by having one array of each kind and only look for hash keys, not values
  private const MAX_CACHE_SIZE = 100000;
  public const MAX_HDL_SIZE = 1024;
  private const BAD_DOI_ARRAY = ['10.1126/science' => TRUE,
	'' => TRUE,
	'10.1267/science.040579197' => TRUE,
	'10.0000/Rubbish_bot_failure_test' => TRUE,
	'10.0000/Rubbish_bot_failure_test2' => TRUE,
	'10.0000/Rubbish_bot_failure_test.x' => TRUE];

  /** @var array<bool> $cache_active */
  static public array $cache_active = [];        // DOI is in CrossRef and works
  /** @var array<bool> $cache_inactive */
  static public array $cache_inactive  = [];     // DOI either is not in CrossRef or does not work
  /** @var array<bool> $cache_good */
  static public array $cache_good = [];          // DOI works
  /** @var array<string> $cache_hdl_loc */
  static public array $cache_hdl_loc = [];       // Final HDL location URL
  /** @var array<bool> $cache_hdl_bad */
  static public array $cache_hdl_bad  = self::BAD_DOI_ARRAY;  // HDL/DOI does not resolve to anything
  /** @var array<bool> $cache_hdl_null */
  static public array $cache_hdl_null = [];      // HDL/DOI resolves to NULL

  public static function check_memory_use() : void {
      $usage = count(self::$cache_inactive) +
	       count(self::$cache_active) +
	       count(self::$cache_good) +
	       count(self::$cache_hdl_bad) +
	       2*count(self::$cache_hdl_loc) + // These include a path too
	       count(self::$cache_hdl_null);
      if ($usage > self::MAX_CACHE_SIZE) {
	self::$cache_active = [];
	self::$cache_inactive  = [];
	self::$cache_good = [];
	self::$cache_hdl_loc = [];
	self::$cache_hdl_bad  = self::BAD_DOI_ARRAY;
	self::$cache_hdl_null = [];
	gc_collect_cycles();
      }
  }
}


// ============================================= DOI functions ======================================
function doi_active(string $doi) : ?bool {
  $doi = trim($doi);
  if (isset(HandleCache::$cache_active[$doi])) return TRUE;
  if (isset(HandleCache::$cache_inactive[$doi]))  return FALSE;

  $works = doi_works($doi);
  if ($works !== TRUE) {
    return $works;
  }

  $works = is_doi_active($doi);
  if ($works === NULL) {
    return NULL; // Temporary problem - do not cache
  }
  if ($works === FALSE) {
    HandleCache::$cache_inactive[$doi] = TRUE;
    return FALSE;
  }
  HandleCache::$cache_active[$doi] = TRUE;
  return TRUE;
}

function doi_works(string $doi) : ?bool {
  $doi = trim($doi);
  if (strlen($doi) > HandleCache::MAX_HDL_SIZE) return NULL;
  if (isset(HandleCache::$cache_good[$doi])) return TRUE;
  if (isset(HandleCache::$cache_hdl_bad[$doi]))  return FALSE;
  if (isset(HandleCache::$cache_hdl_null[$doi])) return NULL;
  HandleCache::check_memory_use();

  $start_time = time();
  $works = is_doi_works($doi);
  if ($works === NULL) {
    if (in_array($doi, NULL_DOI_LIST)) { // These are know to be bad, so only check one time during run
        HandleCache::$cache_hdl_bad[$doi] = TRUE;
        return FALSE;
    }
    if (in_array($doi, NULL_DOI_BUT_GOOD)) { // These are know to be good, but null since PDF
        HandleCache::$cache_good[$doi] = TRUE;
        return TRUE;
    }
    if (abs(time() - $start_time) < max(BOT_HTTP_TIMEOUT, BOT_CONNECTION_TIMEOUT))
    {
      return NULL;
    } else {
      HandleCache::$cache_hdl_null[$doi] = TRUE;
      return NULL;
    }
  }
  if ($works === FALSE) {
    HandleCache::$cache_hdl_bad[$doi] = TRUE;
    return FALSE;
  }
  HandleCache::$cache_good[$doi] = TRUE;
  return TRUE;
}

function is_doi_active(string $doi) : ?bool {
  $doi = trim($doi);
  $url = "https://api.crossref.org/v1/works/" . doi_encode($doi) . "?mailto=".CROSSREFUSERNAME; // do not encode crossref email
  $ch = curl_init_array(1.0,[
		CURLOPT_HEADER => TRUE,
		CURLOPT_NOBODY => TRUE,
		CURLOPT_SSL_VERIFYHOST => 0,
		CURLOPT_SSL_VERIFYPEER => FALSE,
		CURLOPT_SSL_VERIFYSTATUS => FALSE,
		CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT,
		CURLOPT_URL => $url
		]);				 
  $headers_test = @curl_exec($ch);
  if ($headers_test === FALSE || (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 503)) {
    sleep(4);
    report_inline(' .');
    $headers_test = @curl_exec($ch);
  }
  if ($headers_test === FALSE) return NULL; // most likely bad, but will recheck again an again
  $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  if ($response_code === 200) return TRUE;
  if ($response_code === 404) return FALSE;
  if ($response_code === 503) return NULL;
  $err = "CrossRef server error loading headers for DOI " . echoable($doi . " : " . (string) $response_code); // @codeCoverageIgnore
  bot_debug_log($err);   // @codeCoverageIgnore
  report_warning($err);  // @codeCoverageIgnore
  return NULL;           // @codeCoverageIgnore
}

function throttle_dx () : void {
  static $last = 0.0;
  $min_time = 40000.0;
  $now = microtime(TRUE);
  $left = (int) ($min_time - ($now - $last));
  if ($left > 0 && $left < $min_time) usleep($left); // less than min_time is paranoia, but do not want an inifinite delay
  $last = $now;
}

function throttle_archive () : void {
  static $last = 0.0;
  $min_time = 1000000.0; // One second
  $now = microtime(TRUE);
  $left = (int) ($min_time - ($now - $last));
  if ($left > 0 && $left < $min_time) usleep($left); // less than min_time is paranoia, but do not want an inifinite delay
  $last = $now;
}

function is_doi_works(string $doi) : ?bool {
  $doi = trim($doi);
  // And now some obvious fails
  if (strpos($doi, '/') === FALSE) return FALSE;
  if (strpos($doi, 'CITATION_BOT_PLACEHOLDER') !== FALSE) return FALSE;
  if (preg_match('~^10\.1007/springerreference~', $doi)) return FALSE;
  if (!preg_match('~^([^\/]+)\/~', $doi, $matches)) return FALSE;
  $registrant = $matches[1];
  // TODO this will need updated over time.  See registrant_err_patterns on https://en.wikipedia.org/wiki/Module:Citation/CS1/Identifiers
  // 14:43, January 14, 2023 version is last check
  if (strpos($registrant, '10.') === 0) { // We have to deal with valid handles in the DOI field - very rare, so only check actual DOIs
    $registrant = substr($registrant,3);
    if (preg_match('~^[^1-3]\d\d\d\d\.\d\d*$~', $registrant)) return FALSE; // 5 digits with subcode (0xxxx, 40000+); accepts: 10000–39999
    if (preg_match('~^[^1-6]\d\d\d\d$~', $registrant)) return FALSE;        // 5 digits without subcode (0xxxx, 60000+); accepts: 10000–59999
    if (preg_match('~^[^1-9]\d\d\d\.\d\d*$~', $registrant)) return FALSE;   // 4 digits with subcode (0xxx); accepts: 1000–9999
    if (preg_match('~^[^1-9]\d\d\d$~', $registrant)) return FALSE;          // 4 digits without subcode (0xxx); accepts: 1000–9999
    if (preg_match('~^\d\d\d\d\d\d+~', $registrant)) return FALSE;          // 6 or more digits
    if (preg_match('~^\d\d?\d?$~', $registrant)) return FALSE;              // less than 4 digits without subcode (3 digits with subcode is legitimate)
    if (preg_match('~^\d\d?\.[\d\.]+~', $registrant)) return FALSE;         // 1 or 2 digits with subcode
    if ($registrant === '5555') return FALSE;                               // test registrant will never resolve
    if (preg_match('~[^\d\.]~', $registrant)) return FALSE;                 // any character that isn't a digit or a dot
  }
  throttle_dx();

  $url = "https://doi.org/" . doi_encode($doi);
  $headers_test = get_headers_array($url);
  if ($headers_test === FALSE) {
     if (strpos($doi, '10.2277/') === 0) return FALSE; // Rogue
     if (preg_match('~^10\.1038/nature\d{5}$~i', $doi)) return FALSE; // Nature dropped the ball
     if (stripos($doi, '10.17312/harringtonparkpress/') === 0) return FALSE;
     if (stripos($doi, '10.3149/csm.') === 0) return FALSE;
     if (stripos($doi, '10.5047/meep.') === 0) return FALSE;
     if (stripos($doi, '10.4435/BSPI.') === 0) return FALSE;
     $headers_test = get_headers_array($url);  // @codeCoverageIgnore
  }
  if ($headers_test === FALSE) {
     $headers_test = get_headers_array($url);  // @codeCoverageIgnore
  }
  if ($headers_test === FALSE) {
     if (!in_array($doi, NULL_DOI_LIST)) bot_debug_log('Got NULL for DOI: ' . echoable($doi));
     return NULL; // most likely bad, but will recheck again and again - note that NULL means do not add or remove doi-broken-date from pages
  }
  if (interpret_doi_header($headers_test) !== FALSE) {
       return interpret_doi_header($headers_test);
  }
  // Got 404 - try again, since we cache this and add doi-broken-date to pages, we should be double sure
  $headers_test = get_headers_array($url);
  /** We trust previous failure, so fail and null are both false **/
  if ($headers_test === FALSE) return FALSE;
  return (bool) interpret_doi_header($headers_test);
}

/** @param array<mixed> $headers_test **/
function interpret_doi_header(array $headers_test) : ?bool {
  if (empty($headers_test['Location']) && empty($headers_test['location'])) return FALSE; // leads nowhere
  /** @psalm-suppress InvalidArrayOffset */
  $resp0 = (string) @$headers_test['0'];
  /** @psalm-suppress InvalidArrayOffset */
  $resp1 = (string) @$headers_test['1'];
  /** @psalm-suppress InvalidArrayOffset */
  $resp2 = (string) @$headers_test['2'];
  if (stripos($resp0 . $resp1 . $resp2, '404 Not Found') !== FALSE || stripos($resp0 . $resp1 . $resp2, 'HTTP/1.1 404') !== FALSE) return FALSE; // Bad
  if (stripos($resp0, '302 Found') !== FALSE || stripos($resp0, 'HTTP/1.1 302') !== FALSE) return TRUE;  // Good
  if (stripos((string) @json_encode($headers_test), 'dtic.mil') !== FALSE) return TRUE; // grumpy
  if (stripos($resp0, '301 Moved Permanently') !== FALSE || stripos($resp0, 'HTTP/1.1 301') !== FALSE) { // Could be DOI change or bad prefix
      if (stripos($resp1, '302 Found') !== FALSE         || stripos($resp1, 'HTTP/1.1 302') !== FALSE) {
	return TRUE;  // Good
      } elseif (stripos($resp1, '301 Moved Permanently') !== FALSE || stripos($resp1, 'HTTP/1.1 301') !== FALSE) { // Just in case code.
	if (stripos($resp2, '200 OK') !== FALSE         || stripos($resp2, 'HTTP/1.1 200') !== FALSE) {    // @codeCoverageIgnoreStart
	  return TRUE;
	} else {
	  return FALSE;
	}                                                                                                  // @codeCoverageIgnoreEnd
      } else {
	return FALSE;
      }
  }
  report_minor_error("Unexpected response in is_doi_works " . echoable($resp0)); // @codeCoverageIgnore
  return NULL; // @codeCoverageIgnore
}

/** @psalm-suppress UnusedParam
    @param array<string> $ids
    @param array<Template> $templates **/
function query_jstor_api(array $ids, array &$templates) : bool { // $ids not used yet   // Pointer to save memory
  $return = FALSE;
  foreach ($templates as $template) {
    if (expand_by_jstor($template)) $return = TRUE;
  }
  return $return;
}

function sanitize_doi(string $doi) : string {
  if (substr($doi, -1) === '.') {
    $try_doi = substr($doi, 0, -1);
    if (doi_works($try_doi)) { // If it works without dot, then remove it
      $doi = $try_doi;
    } elseif (doi_works($try_doi . '.x')) { // Missing the very common ending .x
      $doi = $try_doi . '.x';
    } elseif (!doi_works($doi)) { // It does not work, so just remove it to remove wikipedia error.  It's messed up
      $doi = $try_doi;
    }
  }
  $doi = safe_preg_replace('~^https?://d?x?\.?doi\.org/~i', '', $doi); // Strip URL part if present
  $doi = safe_preg_replace('~^/?d?x?\.?doi\.org/~i', '', $doi);
  $doi = safe_preg_replace('~^doi:~i', '', $doi); // Strip doi: part if present
  $doi = str_replace("+" , "%2B", $doi); // plus signs are valid DOI characters, but in URLs are "spaces"
  $doi = str_replace(HTML_ENCODE_DOI, HTML_DECODE_DOI, trim(urldecode($doi)));
  if ($pos = (int) strrpos($doi, '.')) {
   $extension = (string) substr($doi, $pos);
   if (in_array(strtolower($extension), array('.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml', '.full'))) {
      $doi = (string) substr($doi, 0, $pos);
   }
  }
  if ($pos = (int) strrpos($doi, '#')) {
   $extension = (string) substr($doi, $pos);
   if (strpos(strtolower($extension), '#page_scan_tab_contents') === 0) {
      $doi = (string) substr($doi, 0, $pos);
   }
  }
  if ($pos = (int) strrpos($doi, ';')) {
   $extension = (string) substr($doi, $pos);
   if (strpos(strtolower($extension), ';jsessionid') === 0) {
      $doi = (string) substr($doi, 0, $pos);
   }
  }
  if ($pos = (int) strrpos($doi, '/')) {
   $extension = (string) substr($doi, $pos);
   if (in_array(strtolower($extension), array('/abstract', '/full', '/pdf', '/epdf', '/asset/', '/summary', '/short', '/meta', '/html', '/'))) {
      $doi = (string) substr($doi, 0, $pos);
   }
  }
  $new_doi = str_replace('//', '/', $doi);
  if ($new_doi !== $doi) {
    if (doi_works($new_doi) || !doi_works($doi)) {
      $doi = $new_doi; // Double slash DOIs do exist
    }
  }
  // And now for 10.1093 URLs
  // The add chapter/page stuff after the DOI in the URL and it looks like part of the DOI to us
  // Things like 10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-003 and 10.1093/acprof:oso/9780195304923.001.0001/acprof-9780195304923-chapter-7
  if (strpos($doi, '10.1093') === 0 && doi_works($doi) === FALSE) {
    if (preg_match('~^(10\.1093/oxfordhb.+)(?:/oxfordhb.+)$~', $doi, $match) ||
	preg_match('~^(10\.1093/acprof.+)(?:/acprof.+)$~', $doi, $match) ||
	preg_match('~^(10\.1093/acref.+)(?:/acref.+)$~', $doi, $match) ||
	preg_match('~^(10\.1093/ref:odnb.+)(?:/odnb.+)$~', $doi, $match) ||
	preg_match('~^(10\.1093/ww.+)(?:/ww.+)$~', $doi, $match) ||
	preg_match('~^(10\.1093/anb.+)(?:/anb.+)$~', $doi, $match)) {
       $new_doi = $match[1];
       if (doi_works($new_doi)) $doi = $new_doi;
    }
  }
  return $doi;
}

/* extract_doi
 * Returns an array containing:
 * 0 => text containing a DOI, possibly encoded, possibly with additional text
 * 1 => the decoded DOI
 */
/** @return array<string> */
function extract_doi(string $text) : array {
  if (preg_match(
	"~(10\.\d{4}\d?(/|%2[fF])..([^\s\|\"\?&>]|&l?g?t;|<[^\s\|\"\?&]*>)+)~",
	$text, $match)) {
    $doi = $match[1];
    if (preg_match(
	  "~^(.*?)(/abstract|/e?pdf|/full|/figure|/default|</span>|[\s\|\"\?]|</).*+$~",
	  $doi, $new_match)
	) {
      $doi = $new_match[1];
    }
    $doi_candidate = sanitize_doi($doi);
    while (preg_match(REGEXP_DOI, $doi_candidate) && !doi_works($doi_candidate)) {
      $last_delimiter = 0;
      foreach (array('/', '.', '#', '?') as $delimiter) {
	$delimiter_position = (int) strrpos($doi_candidate, $delimiter);
	$last_delimiter = ($delimiter_position > $last_delimiter) ? $delimiter_position : $last_delimiter;
      }
      $doi_candidate = substr($doi_candidate, 0, $last_delimiter);
    }
    if (doi_works($doi_candidate)) $doi = $doi_candidate;
    if (!doi_works($doi) && !doi_works(sanitize_doi($doi))) { // Reject URLS like ...../25.10.2015/2137303/default.htm
      if (preg_match('~^10\.([12]\d{3})~', $doi, $new_match)) {
	if (preg_match("~[0-3][0-9]\.10\." . $new_match[1] . "~", $text)) {
	  return array('', '');
	}
      }
    }
    return array($match[0], sanitize_doi($doi));
  }
  return array('', '');
}

// ============================================= String/Text functions ======================================
function wikify_external_text(string $title) : string {
  $replacement = [];
  $placeholder = [];
  $title = safe_preg_replace_callback('~(?:\$\$)([^\$]+)(?:\$\$)~iu',
      function (array $matches) : string {return ("<math>" . $matches[1] . "</math>");},
      $title);
  if (preg_match_all("~<(?:mml:)?math[^>]*>(.*?)</(?:mml:)?math>~", $title, $matches)) {
    for ($i = 0; $i < count($matches[0]); $i++) {
      $replacement[$i] = '<math>' .
	str_replace(array_keys(MML_TAGS), array_values(MML_TAGS),
	  str_replace(['<mml:', '</mml:'], ['<', '</'], $matches[1][$i]))
	. '</math>';
      $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i);
      // Need to use a placeholder to protect contents from URL-safening
      $title = str_replace($matches[0][$i], $placeholder[$i], $title);
    }
    $title = str_replace(['<mo stretchy="false">', "<mo stretchy='false'>"], '', $title);
  }
  // Sometimes stuff is encoded more than once
  $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
  $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
  $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
  $title = safe_preg_replace("~\s+~"," ", $title);  // Remove all white spaces before
  if (mb_substr($title, -6) === "&nbsp;") $title = mb_substr($title, 0, -6);
  // Special code for ending periods
  while (mb_substr($title, -2) === "..") {
    $title = mb_substr($title, 0, -1);
  }
  if (mb_substr($title, -1) === ".") { // Ends with a period
   if (mb_substr_count($title, '.') === 1) { // Only one period
      $title = mb_substr($title, 0, -1);
   } elseif (mb_substr_count($title, ' ') === 0) { // No spaces at all and multiple periods
      ;
   } else { // Multiple periods and at least one space
    $last_word_start = (int) mb_strrpos(' ' . $title, ' ');
    $last_word = mb_substr($title, $last_word_start);
    if (mb_substr_count($last_word, '.') === 1 && // Do not remove if something like D.C. or D. C.
	mb_substr($title, $last_word_start-2, 1) !== '.') {
      $title = mb_substr($title, 0, -1);
    }
   }
  }
  $title = safe_preg_replace('~[\*]$~', '', $title);
  $title = title_capitalization($title, TRUE);

  $htmlBraces  = array("&lt;", "&gt;");
  $angleBraces = array("<", ">");
  $title = str_ireplace($htmlBraces, $angleBraces, $title);

  $originalTags = array('<title>', '</title>', '</ title>', 'From the Cover: ');
  $wikiTags = array('','','','');
  $title = str_ireplace($originalTags, $wikiTags, $title);
  $originalTags = array('<inf>', '</inf>');
  $wikiTags = array('<sub>', '</sub>');
  $title = str_ireplace($originalTags, $wikiTags, $title);
  $originalTags = array('.<br>', '.</br>', '.</ br>', '.<p>', '.</p>', '.</ p>', '.<strong>', '.</strong>', '.</ strong>');
  $wikiTags = array('. ','. ','. ','. ','. ','. ','. ','. ','. ');
  $title = str_ireplace($originalTags, $wikiTags, $title);
  $originalTags = array('<br>', '</br>', '</ br>', '<p>', '</p>', '</ p>', '<strong>', '</strong>', '</ strong>');
  $wikiTags = array('. ','. ','. ','. ','. ','. ', ' ',' ',' ');
  $title = trim(str_ireplace($originalTags, $wikiTags, $title));
  if (preg_match("~^\. (.+)$~", $title, $matches)) {
    $title = trim($matches[1]);
  }
 if (preg_match("~^(.+)(\.\s+)\.$~s", $title, $matches)) {
    $title = trim($matches[1] . ".");
  }
  $title_orig = '';
  while ($title !== $title_orig) {
    $title_orig = $title;  // Might have to do more than once.   The following do not allow < within the inner match since the end tag is the same :-( and they might nest or who knows what
    $title = safe_preg_replace_callback('~(?:<Emphasis Type="Italic">)([^<]+)(?:</Emphasis>)~iu',
      function (array $matches) : string {return ("''" . $matches[1] . "''");},
      $title);
    $title = safe_preg_replace_callback('~(?:<Emphasis Type="Bold">)([^<]+)(?:</Emphasis>)~iu',
      function (array $matches) : string {return ("'''" . $matches[1] . "'''");},
      $title);
    $title = safe_preg_replace_callback('~(?:<em>)([^<]+)(?:</em>)~iu',
      function (array $matches) : string {return ("''" . $matches[1] . "''");},
      $title);
    $title = safe_preg_replace_callback('~(?:<i>)([^<]+)(?:</i>)~iu',
      function (array $matches) : string {return ("''" . $matches[1] . "''");},
      $title);
    $title = safe_preg_replace_callback('~(?:<italics>)([^<]+)(?:</italics>)~iu',
      function (array $matches) : string {return ("''" . $matches[1] . "''");},
      $title);
  }

  if (mb_substr($title, -1) === '.') {
    $title = sanitize_string($title) . '.';
  } else {
    $title = sanitize_string($title);
  }

  $title = str_replace(['​'],[' '], $title); // Funky spaces

  $title = str_ireplace('<p class="HeadingRun \'\'In\'\'">', ' ', $title);

  $title = str_ireplace(['    ', '   ', '  '], [' ', ' ', ' '], $title);
  if (mb_strlen($title) === strlen($title)) {
     $title = trim($title," \t\n\r\0\x0B\xc2\xa0");
  } else {
     $title = trim($title," \t\n\r\0");
  }

  for ($i = 0; $i < count($replacement); $i++) {
    $title = str_replace($placeholder[$i], $replacement[$i], $title); // @phan-suppress-current-line PhanTypePossiblyInvalidDimOffset
  }

  foreach (array('<msup>', '<msub>', '<mroot>', '<msubsup>', '<munderover>', '<mrow>', '<munder>', '<mtable>', '<mtr>', '<mtd>') as $mathy) {
    if (strpos($title, $mathy) !== FALSE) {
      return '<nowiki>' . $title . '</nowiki>';
    }
  }
  return $title;
}

function restore_italics (string $text) : string {
  $text = trim(str_replace(['        ', '      ', '    ', '   ', '  '], [' ', ' ', ' ', ' ', ' '], $text));
  // <em> tags often go missing around species names in CrossRef
  /** $old = $text; **/
  $text = str_replace(ITALICS_HARDCODE_IN, ITALICS_HARDCODE_OUT, $text); // Ones to always do, since they keep popping up in our logs
  $text = str_replace("xAzathioprine therapy for patients with systemic lupus erythematosus", "Azathioprine therapy for patients with systemic lupus erythematosus", $text); // Annoying stupid bad data
  $text = trim(str_replace(['        ', '      ', '    ', '   ', '  '], [' ', ' ', ' ', ' ', ' '], $text));
  while (preg_match('~([a-z])(' . ITALICS_LIST . ')([A-Z\-\?\:\.\)\,]|species|genus| in| the|$)~', $text, $matches)) {
     if (in_array($matches[3], [':', '.', '-', ','])) {
       $pad = "";
     } else {
       $pad = " ";
     }
     $text = str_replace($matches[0], $matches[1] . " ''" . $matches[2] . "''" . $pad . $matches[3], $text);
  }
  $text = trim(str_replace(['        ', '      ', '    ', '   ', '  '], [' ', ' ', ' ', ' ', ' '], $text));
  /** if ($old !== $text) bot_debug_log('restore_italics: ' . $old . '    FORCED TO BE     ' . $text); **/
  $padded = ' '. $text . ' ';
  if (str_replace(CAMEL_CASE, '', $padded) !== $padded) return $text; // Words with capitals in the middle, but not the first character
  $new = safe_preg_replace('~([a-z]+)([A-Z][a-z]+\b)~', "$1 ''$2''", $text);
  if ($new === $text) {
    return $text;
  }
  bot_debug_log('restore_italics: ' . $text . '       SHOULD BE     ' . $new);
  return $text; // NOT $new, since we are wrong much more often than wrong with new CrossRef Code
}

function sanitize_string(string $str) : string {
  // ought only be applied to newly-found data.
  if ($str === '') return '';
  if (strtolower(trim($str)) === 'science (new york, n.y.)') return 'Science';
  if (preg_match('~^\[http.+\]$~', $str)) return $str; // It is a link out
  $replacement = [];
  $placeholder = [];
  $math_templates_present = preg_match_all("~<\s*math\s*>.*<\s*/\s*math\s*>~", $str, $math_hits);
  if ($math_templates_present) {
    for ($i = 0; $i < count($math_hits[0]); $i++) {
      $replacement[$i] = $math_hits[0][$i];
      $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i);
    }
    $str = str_replace($replacement, $placeholder, $str);
  }
  $dirty = array ('[', ']', '|', '{', '}', " what�s ");
  $clean = array ('&#91;', '&#93;', '&#124;', '&#123;', '&#125;', " what's ");
  $str = trim(str_replace($dirty, $clean, safe_preg_replace('~[;.,]+$~', '', $str)));
  if ($math_templates_present) {
    $str = str_replace($placeholder, $replacement, $str);
  }
  return $str;
}

function truncate_publisher(string $p) : string {
  return safe_preg_replace("~\s+(group|inc|ltd|publishing)\.?\s*$~i", "", $p);
}

function str_remove_irrelevant_bits(string $str) : string {
  if ($str === '') return '';
  $str = trim($str);
  $str = str_replace('�', 'X', $str);
  $str = safe_preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $str);   // Convert [[X]] wikilinks into X
  $str = safe_preg_replace(REGEXP_PIPED_WIKILINK, "$2", $str);   // Convert [[Y|X]] wikilinks into X
  $str = trim($str);
  $str = safe_preg_replace("~^the\s+~i", "", $str);  // Ignore leading "the" so "New York Times" == "The New York Times"
  $str = safe_preg_replace("~\s~u", ' ', $str);
  // punctuation
  $str = str_replace(array('.', ',', ';', ': ', "…"), array(' ', ' ', ' ', ' ', ' '), $str);
  $str = str_replace(array(':', '-', '&mdash;', '&ndash;', '—', '–'), array('', '', '', '', '', ''), $str);
  $str = str_replace(array('   ', '  '), array(' ', ' '), $str);
  $str = str_replace(" & ", " and ", $str);
  $str = str_replace(" / ", " and ", $str);
  $str = trim($str);
  $str = str_ireplace(array('Proceedings', 'Proceeding', 'Symposium', 'Huffington ', 'the Journal of ', 'nytimes.com'   , '& '  , '(Clifton, N.J.)'),
		      array('Proc',        'Proc',       'Sym',       'Huff ',       'journal of ',     'New York Times', 'and ', ''), $str);
  $str = str_ireplace(array('<sub>', '<sup>', '<i>', '<b>', '</sub>', '</sup>', '</i>', '</b>', '<p>', '</p>', '<title>', '</title>'), '', $str);
  $str = str_ireplace(array('SpringerVerlag', 'Springer Verlag Springer', 'Springer Verlag', 'Springer Springer'),
		      array('Springer',       'Springer',                 'Springer',        'Springer'         ), $str);
  $str = straighten_quotes($str, TRUE);
  $str = str_replace("′","'", $str);
  $str = safe_preg_replace('~\(Incorporating .*\)$~i', '', $str);  // Physical Chemistry Chemical Physics (Incorporating Faraday Transactions)
  $str = safe_preg_replace('~\d+ Volume Set$~i', '', $str);  // Ullmann's Encyclopedia of Industrial Chemistry, 40 Volume Set
  $str = safe_preg_replace('~^Retracted~i', '', $str);
  $str = safe_preg_replace('~\d?\d? ?The ?sequence ?of ?\S+ ?has ?been ?deposited ?in ?the ?GenBank ?database ?under ?accession ?number ?\S+ ?\d?~i', '', $str);
  $str = safe_preg_replace('~(?:\:\.\,)? ?(?:an|the) official publication of the.+$~i', '', $str);
  $str = trim($str);
  $str = strip_diacritics($str);
  return $str;
}

// See also titles_are_similar()
function str_equivalent(string $str1, string $str2) : bool {
  return str_i_same(str_remove_irrelevant_bits($str1), str_remove_irrelevant_bits($str2));
}

// See also str_equivalent()
function titles_are_similar(string $title1, string $title2) : bool {
  if (!titles_are_dissimilar($title1, $title2)) return TRUE;
  // Try again but with funky stuff mapped out of existence
  $title1 = str_replace('�', '', str_replace(array_keys(MAP_DIACRITICS), '', $title1));
  $title2 = str_replace('�', '', str_replace(array_keys(MAP_DIACRITICS), '', $title2));
  if (!titles_are_dissimilar($title1, $title2)) return TRUE;
  return FALSE;
}


function de_wikify(string $string) : string {
  return str_replace(Array("[", "]", "'''", "''", "&"), Array("", "", "'", "'", ""), preg_replace(Array("~<[^>]*>~", "~\&[\w\d]{2,7};~", "~\[\[[^\|\]]*\|([^\]]*)\]\]~"), Array("", "", "$1"),  $string));
}

function titles_are_dissimilar(string $inTitle, string $dbTitle) : bool {
	// Blow away junk from OLD stuff
	if (stripos($inTitle, 'CITATION_BOT_PLACEHOLDER_') !== FALSE) {
	  $possible = preg_replace("~# # # CITATION_BOT_PLACEHOLDER_[A-Z]+ \d+ # # #~isu", ' ' , $inTitle);
	  if ($possible !== NULL) {
	     $inTitle = $possible;
	  } else { // When PHP fails with unicode, try without it
	    $inTitle = preg_replace("~# # # CITATION_BOT_PLACEHOLDER_[A-Z]+ \d+ # # #~i", ' ' , $inTitle);  // @codeCoverageIgnore
	    if ($inTitle === NULL) return TRUE;                                                             // @codeCoverageIgnore
	  }
	}
	// Strip diacritics before decode
	$inTitle = strip_diacritics($inTitle);
	$dbTitle = strip_diacritics($dbTitle);
	// always decode new data
	$dbTitle = titles_simple(htmlentities(html_entity_decode($dbTitle)));
	// old data both decoded and not
	$inTitle2 = titles_simple($inTitle);
	$inTitle = titles_simple(htmlentities(html_entity_decode($inTitle)));
	$dbTitle = strip_diacritics($dbTitle);
	$inTitle = strip_diacritics($inTitle);
	$inTitle2 = strip_diacritics($inTitle2);
	$dbTitle = mb_strtolower($dbTitle);
	$inTitle = mb_strtolower($inTitle);
	$inTitle2 = mb_strtolower($inTitle2);
	$drops = [" ", "<strong>", "</strong>", "<em>", "</em>", "&nbsp", "&ensp", "&emsp", "&thinsp", "&zwnj", "&#45", "&#8208", "&#700", "&", "'", ",", ".", ";", '"', "\n", "\r", "\t", "\v", "\e", "‐", "-", "ʼ", "`"];
	$inTitle  = str_replace($drops, "", $inTitle);
	$inTitle2 = str_replace($drops, "", $inTitle2);
	$dbTitle  = str_replace($drops, "", $dbTitle);
  // This will convert &delta into delta
	return ((strlen($inTitle) > 254 || strlen($dbTitle) > 254)
	      ? (strlen($inTitle) !== strlen($dbTitle)
		|| similar_text($inTitle, $dbTitle) / strlen($inTitle) < 0.98)
	      : (levenshtein($inTitle, $dbTitle) > 3)
	)
	&&
	((strlen($inTitle2) > 254 || strlen($dbTitle) > 254)
	      ? (strlen($inTitle2) !== strlen($dbTitle)
		|| similar_text($inTitle2, $dbTitle) / strlen($inTitle2) < 0.98)
	      : (levenshtein($inTitle2, $dbTitle) > 3)
	);
}

function titles_simple(string $inTitle) : string {
	// Failure leads to null or empty strings!!!!
	// Leading Chapter # -   Use callback to make sure there are a few characters after this
	$inTitle2 = safe_preg_replace_callback('~^(?:Chapter \d+ \- )(.....+)~iu',
	    function (array $matches) : string {return ($matches[1]);}, trim($inTitle));
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Chapter number at start
	$inTitle2 = safe_preg_replace('~^\[\d+\]\s*~iu', '', trim($inTitle));
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Trailing "a review"
	$inTitle2 = safe_preg_replace('~(?:\: | |\:)a review$~iu', '', trim($inTitle));
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Strip trailing Online
	$inTitle2 = safe_preg_replace('~ Online$~iu', '', $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Strip trailing (Third Edition)
	$inTitle2 = safe_preg_replace('~\([^\s\(\)]+ Edition\)^~iu', '', $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Strip leading International Symposium on
	$inTitle2 = safe_preg_replace('~^International Symposium on ~iu', '', $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Strip leading the
	$inTitle2 = safe_preg_replace('~^The ~iu', '', $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Strip trailing
	$inTitle2 = safe_preg_replace('~ A literature review$~iu', '', $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Reduce punctuation
	$inTitle = straighten_quotes(mb_strtolower($inTitle), TRUE);
	$inTitle2 = safe_preg_replace("~(?: |‐|−|-|—|–|â€™|â€”|â€“)~u", "", $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	$inTitle = str_replace(array("\n", "\r", "\t", "&#8208;", ":", "&ndash;", "&mdash;", "&ndash", "&mdash"), "", $inTitle);
	// Retracted
	$inTitle2 = safe_preg_replace("~\[RETRACTED\]~ui", "", $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	$inTitle2 = safe_preg_replace("~\(RETRACTED\)~ui", "", $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	$inTitle2 = safe_preg_replace("~RETRACTED~ui", "", $inTitle);
	if ($inTitle2 !== "") $inTitle = $inTitle2;
	// Drop normal quotes
	$inTitle = str_replace(array("'", '"'), "", $inTitle);
	// Strip trailing periods
	$inTitle = trim(rtrim($inTitle, '.'));
	// &
	$inTitle = str_replace(" & ", " and ", $inTitle);
	$inTitle = str_replace(" / ", " and ", $inTitle);
	// greek
	$inTitle = strip_diacritics($inTitle);
	$inTitle = str_remove_irrelevant_bits($inTitle);
	return $inTitle;
}

function strip_diacritics (string $input) : string {
    return str_replace(array_keys(MAP_DIACRITICS), array_values(MAP_DIACRITICS), $input);
}

function straighten_quotes(string $str, bool $do_more) : string { // (?<!\') and (?!\') means that it cannot have a single quote right before or after it
  // These Regex can die on Unicode because of backward looking
  if ($str === '') return '';
  $str = str_replace('Hawaiʻi', 'CITATION_BOT_PLACEHOLDER_HAWAII', $str);
  $str = str_replace('Ha‘apai', 'CITATION_BOT_PLACEHOLDER_HAAPAI', $str);
  $str = safe_preg_replace('~(?<!\')&#821[679];|&#39;|&#x201[89];|[\x{FF07}\x{2018}-\x{201B}`]|&[rl]s?[b]?quo;(?!\')~u', "'", $str);
  if((mb_strpos($str, '&rsaquo;') !== FALSE && mb_strpos($str, '&[lsaquo;')  !== FALSE) ||
     (mb_strpos($str, '\x{2039}') !== FALSE && mb_strpos($str, '\x{203A}') !== FALSE) ||
     (mb_strpos($str, '‹')        !== FALSE && mb_strpos($str, '›')        !== FALSE)) { // Only replace single angle quotes if some of both
     $str = safe_preg_replace('~&[lr]saquo;|[\x{2039}\x{203A}]|[‹›]~u', "'", $str);           // Websites tiles: Jobs ›› Iowa ›› Cows ›› Ames
  }
  $str = safe_preg_replace('~&#822[013];|[\x{201C}-\x{201F}]|&[rlb][d]?quo;~u', '"', $str);
  if((mb_strpos($str, '&raquo;')  !== FALSE && mb_strpos($str, '&laquo;')  !== FALSE) ||
     (mb_strpos($str, '\x{00AB}') !== FALSE && mb_strpos($str, '\x{00AB}') !== FALSE) ||
     (mb_strpos($str, '«')        !== FALSE && mb_strpos($str, '»')        !== FALSE)) { // Only replace double angle quotes if some of both // Websites tiles: Jobs » Iowa » Cows » Ames
     if ($do_more){
       $str = safe_preg_replace('~&[lr]aquo;|[\x{00AB}\x{00BB}]|[«»]~u', '"', $str);
     } else { // Only outer funky quotes, not inner quotes
       if (preg_match('~^(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)~u', $str) &&
	   preg_match( '~(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)$~u', $str) // Only if there is an outer quote on both ends
       ) {
	 $str = safe_preg_replace('~^(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)~u' , '"', $str);
	 $str = safe_preg_replace( '~(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)$~u', '"', $str);
       } else {
	 ; // No change
       }
     }
  }
  $str = str_ireplace('CITATION_BOT_PLACEHOLDER_HAAPAI', 'Ha‘apai', $str);
  $str = str_ireplace('CITATION_BOT_PLACEHOLDER_HAWAII', 'Hawaiʻi', $str);
  return $str;
}

// ============================================= Capitalization functions ======================================

function title_case(string $text) : string {
  if (stripos($text, 'www.') !== FALSE || stripos($text, 'www-') !== FALSE || stripos($text, 'http://') !== FALSE) {
     return $text; // Who knows - duplicate code below
  }
  return mb_convert_case($text, MB_CASE_TITLE, "UTF-8");
}

/** Returns a properly capitalized title.
 *      If $caps_after_punctuation is TRUE (or there is an abundance of periods), it allows the
 *      letter after colons and other punctuation marks to remain capitalized.
 *      If not, it won't capitalize after : etc.
 */
function title_capitalization(string $in, bool $caps_after_punctuation) : string {
  // Use 'straight quotes' per WP:MOS
  $new_case = straighten_quotes(trim($in), FALSE);
  if (mb_substr($new_case, 0, 1) === "[" && mb_substr($new_case, -1) === "]") {
     return $new_case; // We ignore wikilinked names and URL linked since who knows what's going on there.
		       // Changing case may break links (e.g. [[Journal YZ|J. YZ]] etc.)
  }

  if (stripos($new_case, 'www.') !== FALSE || stripos($new_case, 'www-') !== FALSE || stripos($new_case, 'http://') !== FALSE) {
     return $new_case; // Who knows - duplicate code above
  }

  if ($new_case === mb_strtoupper($new_case)
     && mb_strlen(str_replace(array("[", "]"), "", trim($in))) > 6
     ) {
    // ALL CAPS to Title Case
    $new_case = mb_convert_case($new_case, MB_CASE_TITLE, "UTF-8");
  }

  // Implicit acronyms
  $new_case = ' ' . $new_case . ' ';
  $new_case = safe_preg_replace_callback("~[^\w&][b-df-hj-np-tv-xz]{3,}(?=\W)~ui",
      function (array $matches) : string {return mb_strtoupper($matches[0]);}, // Three or more consonants.  NOT Y
      $new_case);
  $new_case = safe_preg_replace_callback("~[^\w&][aeiou]{3,}(?=\W)~ui",
      function (array $matches) : string {return mb_strtoupper($matches[0]);}, // Three or more vowels.  NOT Y
      $new_case);
  $new_case = mb_substr($new_case, 1, -1); // Remove added spaces

  $new_case = mb_substr(str_replace(UC_SMALL_WORDS, LC_SMALL_WORDS, " " . $new_case . " "), 1, -1);
  foreach(UC_SMALL_WORDS as $key=>$_value) {
    $upper = UC_SMALL_WORDS[$key];
    $lower = LC_SMALL_WORDS[$key];
    foreach ([': ', ', ', '. ', '; '] as $char) {
       $new_case = str_replace(mb_substr($upper, 0, -1) . $char, mb_substr($lower, 0, -1) . $char, $new_case);
    }
  }

  if ($caps_after_punctuation || (substr_count($in, '.') / strlen($in)) > .07) {
    // When there are lots of periods, then they probably mark abbreviations, not sentence ends
    // We should therefore capitalize after each punctuation character.
    $new_case = safe_preg_replace_callback("~[?.:!/]\s+[a-z]~u" /* Capitalize after punctuation */,
      function (array $matches) : string {return mb_strtoupper($matches[0]);},
      $new_case);
    $new_case = safe_preg_replace_callback("~(?<!<)/[a-z]~u" /* Capitalize after slash unless part of ending html tag */,
      function (array $matches) : string {return mb_strtoupper($matches[0]);},
      $new_case);
    // But not "Ann. Of...." which seems to be common in journal titles
    $new_case = str_replace("Ann. Of ", "Ann. of ", $new_case);
  }

  $new_case = safe_preg_replace_callback(
    "~ \([a-z]~u" /* uppercase after parenthesis */,
    function (array $matches) : string {return mb_strtoupper($matches[0]);},
    trim($new_case)
  );

  $new_case = safe_preg_replace_callback(
    "~\w{2}'[A-Z]\b~u" /* Lowercase after apostrophes */,
    function (array $matches) : string {return mb_strtolower($matches[0]);},
    trim($new_case)
  );
  /** French l'Words and d'Words  **/
  $new_case = safe_preg_replace_callback(
    "~(\s[LD][\'\x{00B4}])([a-zA-ZÀ-ÿ]+)~u",
    function (array $matches) : string {return mb_strtolower($matches[1]) . mb_ucfirst_force($matches[2]);},
    ' ' . $new_case
  );

  /** Italian dell'xxx words **/
  $new_case = safe_preg_replace_callback(
    "~(\s)(Dell|Degli|Delle)([\'\x{00B4}][a-zA-ZÀ-ÿ]{3})~u",
    function (array $matches) : string {return $matches[1] . mb_strtolower($matches[2]) . $matches[3];},
    $new_case
  );

  $new_case = mb_ucfirst(trim($new_case));

  // Solitary 'a' should be lowercase
  $new_case = safe_preg_replace("~(\w\s+)A(\s+\w)~u", "$1a$2", $new_case);
  // but not in "U S A"
  $new_case = trim(str_replace(" U S a ", " U S A ", ' ' . $new_case . ' '));

  // This should be capitalized
  $new_case = str_replace(['(new Series)', '(new series)'] , ['(New Series)', '(New Series)'], $new_case);

  // Catch some specific epithets, which should be lowercase
  $new_case = safe_preg_replace_callback(
    "~(?:'')?(?P<taxon>\p{L}+\s+\p{L}+)(?:'')?\s+(?P<nova>(?:(?:gen\.? no?v?|sp\.? no?v?|no?v?\.? sp|no?v?\.? gen)\b[\.,\s]*)+)~ui" /* Species names to lowercase */,
    function (array $matches) : string {return "''" . mb_ucfirst(mb_strtolower($matches['taxon'])) . "'' " . mb_strtolower($matches["nova"]);},
    $new_case);

  // "des" at end is "Des" for Design not german "The"
  if (mb_substr($new_case, -4, 4) === ' des') $new_case = mb_substr($new_case, 0, -4)  . ' Des';

  // Capitalization exceptions, e.g. Elife -> eLife
  $new_case = str_replace(UCFIRST_JOURNAL_ACRONYMS, JOURNAL_ACRONYMS, " " .  $new_case . " ");
  $new_case = mb_substr($new_case, 1, mb_strlen($new_case) - 2); // remove spaces, needed for matching in LC_SMALL_WORDS

  // Single letter at end should be capitalized  J Chem Phys E for example.  Obviously not the spanish word "e".
  if (mb_substr($new_case, -2, 1) === ' ') $new_case = mb_strrev(mb_ucfirst(mb_strrev($new_case)));

  if ($new_case === 'Now and then') $new_case = 'Now and Then'; // Odd journal name

  // Trust existing "ITS", "its", ...
  $its_in = preg_match_all('~ its(?= )~iu', ' ' . trim($in) . ' ', $matches_in, PREG_OFFSET_CAPTURE);
  $new_case = trim($new_case);
  $its_out = preg_match_all('~ its(?= )~iu', ' ' . $new_case . ' ', $matches_out, PREG_OFFSET_CAPTURE);
  if ($its_in === $its_out && $its_in !== 0 && $its_in !== FALSE) {
    $matches_in = $matches_in[0];
    $matches_out = $matches_out[0];
    foreach ($matches_in as $key => $_value) {
      if ($matches_in[$key][0] !== $matches_out[$key][0]  &&
	  $matches_in[$key][1] === $matches_out[$key][1]) {
	$new_case = substr_replace($new_case, trim($matches_in[$key][0]), $matches_out[$key][1], 3); // PREG_OFFSET_CAPTURE is ALWAYS in BYTES, even for unicode
      }
    }
  }
  // Trust existing "DOS", "dos", ...
  $its_in = preg_match_all('~ dos(?= )~iu', ' ' . trim($in) . ' ', $matches_in, PREG_OFFSET_CAPTURE);
  $new_case = trim($new_case);
  $its_out = preg_match_all('~ dos(?= )~iu', ' ' . $new_case . ' ', $matches_out, PREG_OFFSET_CAPTURE);
  if ($its_in === $its_out && $its_in !== 0 && $its_in !== FALSE) {
    $matches_in = $matches_in[0];
    $matches_out = $matches_out[0];
    foreach ($matches_in as $key => $_value) {
      if ($matches_in[$key][0] !== $matches_out[$key][0]  &&
	  $matches_in[$key][1] === $matches_out[$key][1]) {
	$new_case = substr_replace($new_case, trim($matches_in[$key][0]), $matches_out[$key][1], 3); ; // PREG_OFFSET_CAPTURE is ALWAYS in BYTES, even for unicode
      }
    }
  }

  if (preg_match('~Series ([a-zA-Z] )(\&|and)( [a-zA-Z] )~', $new_case . ' ', $matches)) {
    $replace_me = 'Series ' . $matches[1] . $matches[2] . $matches[3];
    $replace    = 'Series ' . strtoupper($matches[1]) . $matches[2] . strtoupper($matches[3]);
    $new_case = trim(str_replace($replace_me, $replace, $new_case . ' '));
  }

  // 42th, 33rd, 1st, ...
  if(preg_match('~\s\d+(?:st|nd|rd|th)[\s\,\;\:\.]~i', ' ' . $new_case . ' ', $matches)) {
    $replace_me = $matches[0];
    $replace    = strtolower($matches[0]);
    $new_case = trim(str_replace($replace_me, $replace, ' ' .$new_case . ' '));
  }

  // Part XII: Roman numerals
  $new_case = safe_preg_replace_callback(
    "~ part ([xvil]+): ~iu",
    function (array $matches) : string {return " Part " . strtoupper($matches[1]) . ": ";},
    $new_case);
  $new_case = safe_preg_replace_callback(
    "~ part ([xvi]+) ~iu",
    function (array $matches) : string {return " Part " . strtoupper($matches[1]) . " ";},
    $new_case);
  $new_case = safe_preg_replace_callback(
    "~ (?:Ii|Iii|Iv|Vi|Vii|Vii|Ix)$~u",
    function (array $matches) : string {return strtoupper($matches[0]);},
    $new_case);
  $new_case = safe_preg_replace_callback(
    "~^(?:Ii|Iii|Iv|Vi|Vii|Vii|Ix):~u",
    function (array $matches) : string {return strtoupper($matches[0]);},
    $new_case);
  $new_case = trim($new_case);
  // Special cases - Only if the full title
  if ($new_case === 'Bioscience') {
    $new_case = 'BioScience';
  } elseif ($new_case === 'Aids') {
    $new_case = 'AIDS';
  } elseif ($new_case === 'Biomedical Engineering Online') {
    $new_case = 'BioMedical Engineering OnLine';
  } elseif ($new_case === 'Sage Open') {
    $new_case = 'SAGE Open';
  } elseif ($new_case === 'Ca') {
    $new_case = 'CA';
  } elseif ($new_case === 'Pen International') {
    $new_case = 'PEN International';
  } elseif ($new_case === 'Time off') {
    $new_case = 'Time Off';
  } elseif ($new_case === 'It Professional') {
    $new_case = 'IT Professional';
  } elseif ($new_case === 'Jom') {
    $new_case = 'JOM';
  }
  return $new_case;
}

function mb_ucfirst(string $string) : string
{
    $first = mb_substr($string, 0, 1);
    if (mb_strlen($first) !== strlen($first)) {
      return $string;
    } else {
      return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1, NULL);
    }
}

function mb_ucfirst_force(string $string) : string
{
    return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1, NULL);
}

function mb_strrev(string $string, string $encode = null) : string
{
    $chars = mb_str_split($string, 1, $encode ?: mb_internal_encoding());
    return implode('', array_reverse($chars));
}

function mb_ucwords(string $string) : string
{
   if (mb_ereg_search_init($string, '(\S)(\S*\s*)|(\s+)')) {
      $output = '';
      while ($match = mb_ereg_search_regs()) {
	 $output .= $match[3] ? $match[3] : mb_strtoupper($match[1]) . $match[2];
      }
      return $output;
   } else {
      return $string;  // @codeCoverageIgnore
   }
}

function mb_substr_replace(string $string, string $replacement, int $start, int $length) : string {
    return mb_substr($string, 0, $start).$replacement.mb_substr($string, $start+$length);
}

function remove_brackets(string $string) : string {
  return str_replace(['(', ')', '{', '}', '[', ']'], '' , $string);
}


// ============================================= Wikipedia functions ======================================

function throttle (int $min_interval) : void {
  static $last_write_time = 0;
  static $phase = 0;
  $cycles = intdiv(180, $min_interval); // average over three minutes
  $phase = $phase + 1;

  if ($last_write_time === 0) $last_write_time = time();

  if ($phase < $cycles) {
    return;
  } else {
    // @codeCoverageIgnoreStart
    $phase = 0;
    $min_interval = $min_interval * $cycles;
  }

  $time_since_last_write = time() - $last_write_time;
  if ($time_since_last_write < 0) $time_since_last_write = 0; // Super paranoid, this would be a freeze point
  if ($time_since_last_write < $min_interval) {
    $time_to_pause = floor($min_interval - $time_since_last_write);
    report_warning("Throttling: waiting $time_to_pause seconds...");
    for ($i = 0; $i < $time_to_pause; $i++) {
      sleep(1);
      report_inline(' .');
    }
  }
  $last_write_time = time();
  // @codeCoverageIgnoreEnd
}

// ============================================= Data processing functions ======================================

function tidy_date(string $string) : string {
  $string=trim($string);
  if (stripos($string, 'Invalid') !== FALSE) return '';
  if (strpos($string, '1/1/0001') !== FALSE) return '';
  if (strpos($string, '0001-01-01') !== FALSE) return '';
  if (!preg_match('~\d{2}~', $string)) return ''; // Not two numbers next to each other
  if (preg_match('~^\d{2}\-\-$~', $string)) return '';
  // Google sends ranges
  if (preg_match('~^(\d{4})(\-\d{2}\-\d{2})\s+\-\s+(\d{4})(\-\d{2}\-\d{2})$~', $string, $matches)) { // Date range
     if ($matches[1] === $matches[3]) {
       return date('j F', strtotime($matches[1].$matches[2])) . ' – ' . date('j F Y', strtotime($matches[3].$matches[4]));
     } else {
       return date('j F Y', strtotime($matches[1].$matches[2])) . ' – ' . date('j F Y', strtotime($matches[3].$matches[4]));
     }
  }
  // Huge amount of character cleaning
  if (strlen($string) !== mb_strlen($string)) {  // Convert all multi-byte characters to dashes
    $cleaned = '';
    for ($i = 0; $i < mb_strlen($string); $i++) {
       $char = mb_substr($string,$i,1);
       if (mb_strlen($char) === strlen($char)) {
	  $cleaned .= $char;
       } else {
	  $cleaned .= '-';
       }
    }
    $string = $cleaned;
  }
  $string = safe_preg_replace("~[^\x01-\x7F]~","-", $string); // Convert any non-ASCII Characters to dashes
  $string = safe_preg_replace('~[\s\-]*\-[\s\-]*~', '-',$string); // Combine dash with any following or preceding white space and other dash
  $string = safe_preg_replace('~^\-*(.+?)\-*$~', '\1', $string);  // Remove trailing/leading dashes
  $string = trim($string);
  // End of character clean-up
  $string = safe_preg_replace('~[^0-9]+\d{2}:\d{2}:\d{2}$~', '', $string); //trailing time
  $string = safe_preg_replace('~^Date published \(~', '', $string); // seen this
  // https://stackoverflow.com/questions/29917598/why-does-0000-00-00-000000-return-0001-11-30-000000
  if (strpos($string, '0001-11-30') !== FALSE) return '';
  if (strpos($string, '1969-12-31') !== FALSE) return '';
  if (str_i_same('19xx', $string)) return ''; //archive.org gives this if unknown
  if (preg_match('~^\d{4} \d{4}\-\d{4}$~', $string)) return ''; // si.edu
  if (preg_match('~^(\d\d?)/(\d\d?)/(\d{4})$~', $string, $matches)) { // dates with slashes
    if (intval($matches[1]) < 13 && intval($matches[2]) > 12) {
      if (strlen($matches[1]) === 1) $matches[1] = '0' . $matches[1];
      return $matches[3] . '-' . $matches[1] . '-' . $matches[2];
    } elseif (intval($matches[2]) < 13 && intval($matches[1]) > 12) {
      if (strlen($matches[2]) === 1) $matches[2] = '0' . $matches[2];
      return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    } elseif (intval($matches[2]) > 12 && intval($matches[1]) > 12) {
      return '';
    } elseif ($matches[1] === $matches[2]) {
      if (strlen($matches[2]) === 1) $matches[2] = '0' . $matches[2];
      return $matches[3] . '-' . $matches[2] . '-' . $matches[2];
    } else {
      return $matches[3];// do not know. just give year
    }
  }
  $string = trim($string);
  if (preg_match('~^(\d{4}\-\d{2}\-\d{2})T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$~', $string, $matches)) return tidy_date($matches[1]); // Remove time zone stuff from standard date format
  if (preg_match('~^\-?\d+$~', $string)) {
    $string = intval($string);
    if ($string < -2000 || $string > (int)date("Y") + 10) return ''; // A number that is not a year; probably garbage
    if ($string > -2 && $string < 2) return ''; // reject -1,0,1
    return (string) $string; // year
  }
  if (preg_match('~^(\d{1,2}) ([A-Za-z]+\.?), ?(\d{4})$~', $string, $matches)) { // strtotime('3 October, 2016') gives 2019-10-03.  The comma is evil and strtotime is stupid
    $string = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];   // Remove comma
  }
  $time = strtotime($string);
  if ($time) {
    $day = date('d', $time);
    $year = intval(date('Y', $time));
    if ($year < -2000 || $year > (int)date("Y") + 10) return ''; // We got an invalid year
    if ($year < 100 && $year > -100) return '';
    if ($day === '01') { // Probably just got month and year
      $string = date('F Y', $time);
    } else {
      $string = date('Y-m-d', $time);
    }
    if (stripos($string, 'Invalid') !== FALSE) return '';
    return $string;
  }
  if (preg_match( '~^(\d{4}\-\d{1,2}\-\d{1,2})[^0-9]~', $string, $matches)) return tidy_date($matches[1]); // Starts with date
  if (preg_match('~\s(\d{4}\-\d{1,2}\-\d{1,2})$~',     $string, $matches)) return tidy_date($matches[1]);  // Ends with a date
  if (preg_match('~^(\d{1,2}/\d{1,2}/\d{4})[^0-9]~', $string, $matches)) return tidy_date($matches[1]); // Recursion to clean up 3/27/2000
  if (preg_match('~[^0-9](\d{1,2}/\d{1,2}/\d{4})$~', $string, $matches)) return tidy_date($matches[1]);

  // Dates with dots -- convert to slashes and try again.
  if (preg_match('~(\d\d?)\.(\d\d?)\.(\d{2}(?:\d{2})?)$~', $string, $matches) || preg_match('~^(\d\d?)\.(\d\d?)\.(\d{2}(?:\d{2})?)~', $string, $matches)) {
    if (intval($matches[3]) < ((int) date("y")+2))  $matches[3] = (int) $matches[3] + 2000;
    if (intval($matches[3]) < 100)  $matches[3] = (int) $matches[3] + 1900;
    return tidy_date((string) $matches[1] . '/' . (string) $matches[2] . '/' . (string) $matches[3]);
  }

  if (preg_match('~\s(\d{4})$~', $string, $matches)) return $matches[1]; // Last ditch effort - ends in a year
  return ''; // And we give up
}

function not_bad_10_1093_doi(string $url) : bool { // We assume DOIs are bad, unless on good list
  if ($url === '') return TRUE;
  if(!preg_match('~10.1093/([^/]+)/~u', $url, $match)) return TRUE;
  $test = strtolower($match[1]);
  // March 2019 Good list
  if (in_array($test, GOOD_10_1093_DOIS)) return TRUE;
  return FALSE;
}

function bad_10_1093_doi(string $url) :bool {
  return !not_bad_10_1093_doi($url);
}

// ============================================= Other functions ======================================

function remove_comments(string $string) : string {
  // See Comment::PLACEHOLDER_TEXT for syntax
  $string = preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~isu', "", $string);
  return preg_replace("~<!--.*?-->~us", "", $string);
}

/** @param array<string> $list
    @return array<string> **/
function prior_parameters(string $par, array $list=array()) : array {
  array_unshift($list, $par);
  if (preg_match('~(\D+)(\d+)~', $par, $match) && stripos($par, 's2cid') === FALSE) {
    $before = (string) ((int) $match[2] - 1);
    switch ($match[1]) {
      case 'first': case 'initials': case 'forename':
	return array('last' . $match[2], 'surname' . $match[2], 'author' . $before);
      case 'last': case 'surname': case 'author':
	return array('first' . $before, 'forename' . $before, 'initials' . $before, 'author' . $before);
      default:
	$base = $match[1] . $before;
	return array_merge(FLATTENED_AUTHOR_PARAMETERS, array($base, $base . '-last', $base . '-first'));
    }
  }
  switch ($par) {
    case 'author': case 'authors':    return $list;
    case 'dummy':                     return $list;
    case 'title': case 'others': case 'display-editors': case 'displayeditors': case 'display-authors': case 'displayauthors':
      return prior_parameters('dummy', array_merge(FLATTENED_AUTHOR_PARAMETERS, $list));
    case 'title-link':case 'titlelink':return prior_parameters('title', $list);
    case 'chapter':                   return prior_parameters('title-link', array_merge(['titlelink'], $list));
    case 'journal': case 'work': case 'newspaper': case 'website': case 'magazine': case 'periodical': case 'encyclopedia': case 'encyclopaedia':
      return prior_parameters('chapter', $list);
    case 'series':                    return prior_parameters('journal', array_merge(['work', 'newspaper', 'magazine', 'periodical', 'website', 'encyclopedia', 'encyclopaedia'], $list));
    case 'year': case 'date':         return prior_parameters('series', $list);
    case 'volume':                    return prior_parameters('year', array_merge(['date'], $list));
    case 'issue': case 'number':      return prior_parameters('volume', $list);
    case 'page' : case 'pages':       return prior_parameters('issue', array_merge(['number'], $list));
    case 'location': case 'publisher': case 'edition': return prior_parameters('page', array_merge(['pages'], $list));
    case 'doi':                       return prior_parameters('location', array_merge(['publisher', 'edition'], $list));
    case 'doi-broken-date':           return prior_parameters('doi', $list);
    case 'doi-access':                return prior_parameters('doi-broken-date', $list);
    case 'jstor':                     return prior_parameters('doi-access', $list);
    case 'pmid':                      return prior_parameters('jstor', $list);
    case 'pmc':                       return prior_parameters('pmid', $list);
    case 'pmc-embargo-date':          return prior_parameters('pmc', $list);
    case 'arxiv': case 'eprint': case 'class' : return prior_parameters('pmc-embargo-date', $list);
    case 'bibcode':                   return prior_parameters('arxiv', array_merge(['eprint', 'class'], $list));
    case 'hdl':                       return prior_parameters('bibcode', $list);
    case 'isbn': case 'biorxiv': case 'citeseerx': case 'jfm': case 'zbl': case 'mr': case 'osti': case 'ssrn': case 'rfc':
       return prior_parameters('hdl', $list);
    case 'lccn': case 'issn': case 'ol': case 'oclc': case 'asin': case 's2cid':
       return prior_parameters('isbn', array_merge(['biorxiv', 'citeseerx', 'jfm', 'zbl', 'mr', 'osti', 'ssrn', 'rfc'], $list));
    case 'url':
	return prior_parameters('lccn', array_merge(['issn', 'ol', 'oclc', 'asin', 's2cid'], $list));
    case 'chapter-url': case 'article-url': case 'chapterurl': case 'conference-url': case 'conferenceurl':
    case 'contribution-url': case 'contributionurl': case 'entry-url': case 'event-url': case 'eventurl': case 'lay-url':
    case 'layurl': case 'map-url': case 'mapurl': case 'section-url': case 'sectionurl': case 'transcript-url':
    case 'transcripturl': case 'URL':
	return prior_parameters('url', $list);
    case 'archive-url': case 'archiveurl': case 'accessdate': case 'access-date':
	return prior_parameters('chapter-url', array_merge(['article-url', 'chapterurl', 'conference-url', 'conferenceurl',
	'contribution-url', 'contributionurl', 'entry-url', 'event-url', 'eventurl', 'lay-url',
	'layurl', 'map-url', 'mapurl', 'section-url', 'sectionurl', 'transcript-url',
	'transcripturl', 'URL'],$list));
    case 'archive-date': case 'archivedate': return prior_parameters('archive-url', array_merge(['archiveurl', 'accessdate', 'access-date'], $list));
    case 'id': case 'type': case 'via':return prior_parameters('archive-date', array_merge(['archivedate'], $list));
    default:
      bot_debug_log("prior_parameters missed: " . $par);
      return $list;
  }
}

/** @return array<string> **/
function equivalent_parameters(string $par) : array {
  switch ($par) {
    case 'author': case 'authors': case 'author1': case 'last1':
      return FLATTENED_AUTHOR_PARAMETERS;
    case 'pmid': case 'pmc':
      return array('pmc', 'pmid');
    case 'page_range': case 'start_page': case 'end_page': # From doi_crossref
    case 'pages': case 'page':
      return array('page_range', 'pages', 'page', 'end_page', 'start_page');
    default: return array($par);
  }
}

function check_doi_for_jstor(string $doi, Template $template) : void {
  if ($template->has('jstor')) return;
  $doi = trim($doi);
  if ($doi === '') return;
  if (strpos($doi, '10.2307') === 0) { // special case
    $doi = substr($doi, 8);
  }
  $ch = curl_init_array(1.0,
	  [CURLOPT_URL => "https://www.jstor.org/citation/ris/" . $doi]);
  $ris = (string) @curl_exec($ch);
  $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($httpCode === 200 &&
      stripos($ris, $doi) !== FALSE &&
      strpos ($ris, 'Provider') !== FALSE &&
      stripos($ris, 'No RIS data found for') === FALSE &&
      stripos($ris, 'Block Reference') === FALSE &&
      stripos($ris, 'A problem occurred trying to deliver RIS data') === FALSE &&
      substr_count($ris, '-') > 3) { // It is actually a working JSTOR
      $template->add_if_new('jstor', $doi);
  } elseif ($pos = strpos($doi, '?')) {
      $doi = substr($doi, 0, $pos);
      check_doi_for_jstor($doi, $template);
  }
}

function can_safely_modify_dashes(string $value) : bool {
   return((stripos($value, "http") === FALSE)
       && (strpos($value, "[//") === FALSE)
       && (substr_count($value, "<") === 0) // <span></span> stuff
       && (stripos($value, 'CITATION_BOT_PLACEHOLDER') === FALSE)
       && (strpos($value, "(") === FALSE)
       && (preg_match('~(?:[a-zA-Z].*\s|\s.*[a-zA-Z])~u', trim($value)) !== 1) // Spaces and letters
       && ((substr_count($value, '-') + substr_count($value, '–') + substr_count($value, ',') + substr_count($value, 'dash')) < 3) // This line helps us ignore with 1-5–1-6 stuff
       && (preg_match('~^[a-zA-Z]+[0-9]*.[0-9]+$~u',$value) !== 1) // A-3, A3-5 etc.  Use "." for generic dash
       && (preg_match('~^\d{4}\-[a-zA-Z]+$~u',$value) !== 1)); // 2005-A used in {{sfn}} junk
}

function str_i_same(string $str1, string $str2) : bool {
   if (0 === strcasecmp($str1, $str2)) return TRUE; // Quick non-multi-byte compare short cut
   return (0 === strcmp(mb_strtoupper($str1), mb_strtoupper($str2)));
}

function doi_encode (string $doi) : string {
   /** @psalm-taint-escape html
       @psalm-taint-escape has_quotes
       @psalm-taint-escape ssrf */
    $doi = urlencode($doi);
    $doi = str_replace('%2F', '/', $doi);
    return $doi;
}

function hdl_decode(string $hdl) : string {
    $hdl = urldecode($hdl);
    $hdl = str_replace(';', '%3B', $hdl);
    $hdl = str_replace('#', '%23', $hdl);
    $hdl = str_replace(' ', '%20', $hdl);
    return $hdl;
}

/**
 * Only on webpage
 * @codeCoverageIgnore
 */
/** @param array<string> $pages_in_category **/
function edit_a_list_of_pages(array $pages_in_category, WikipediaBot $api, string $edit_summary_end) : void {
  $final_edit_overview = "";
  // Remove pages with blank as the name, if present
  if (($key = array_search("", $pages_in_category)) !== FALSE) {
    unset($pages_in_category[$key]);
  }
  if (empty($pages_in_category)) {
    report_warning('No links to expand found');
    bot_html_footer();
    return;
  }
  $total = count($pages_in_category);
  if ($total > MAX_PAGES) {
    report_warning('Number of links is huge (' . (string) $total . ')  Cancelling run (maximum size is ' . (string) MAX_PAGES . ').  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.');
    bot_html_footer();
    return;
  }
  big_jobs_check_overused($total);

  $page = new Page();
  $done = 0;

  foreach ($pages_in_category as $page_title) {
    big_jobs_check_killed();
    $done++;
    if ($page->get_text_from($page_title) && $page->expand_text()) {
      if (SAVETOFILES_MODE)
      {
	// Sanitize file name by replacing characters that are not allowed on most file systems to underscores, and also replace path characters
	// And add .md extension to avoid troubles with devices such as 'con' or 'aux'
	$filename = preg_replace('~[\/\\:*?"<>|\s]~', '_', $page_title) . '.md';
	report_phase("Saving to file " . echoable($filename));
	$body = $page->parsed_text();
	$bodylen = strlen($body);
	if (file_put_contents($filename, $body)===$bodylen)
	{
	  report_phase("Saved to file " . echoable($filename));
	} else {
	  report_warning("Save to file failed.");
	}
	unset($body);
      } else {
	report_phase("Writing to " . echoable($page_title) . '... ');
	$attempts = 0;
	if ($total === 1) {
	  $edit_sum = $edit_summary_end;
	} else {
	  $edit_sum = $edit_summary_end . (string) $done . '/' . (string) $total . ' ';
	}
	while (!$page->write($api, $edit_sum) && $attempts < MAX_TRIES) ++$attempts;
	if ($attempts < MAX_TRIES) {
	  $last_rev = WikipediaBot::get_last_revision($page_title);
	  html_echo(
	  "\n  <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
	  . $last_rev . ">diff</a>" .
	  " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a>",
	  "\n" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid=". $last_rev . "\n");
	  $final_edit_overview .=
	    "\n [ <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
	  . $last_rev . ">diff</a>" .
	  " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a> ] " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
	} else {
	  report_warning("Write failed.");
	  $final_edit_overview .= "\n Write failed.      " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
	}
      }
    } else {
      report_phase($page->parsed_text() ? "No changes required. \n\n    # # # " : "Blank page. \n\n    # # # ");
       $final_edit_overview .= "\n No changes needed. " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
    }
    echo "\n";
    // Clear variables before doing GC - PHP 8.2 seems to need the GC
    $page->parse_text("");
    gc_collect_cycles();
  }
  if ($total > 1) {
    if (!HTML_OUTPUT) $final_edit_overview = '';
    echo "\n Done all " . (string) $total . " pages. \n  # # # \n" . $final_edit_overview;
  } else {
    echo "\n Done with page.";
  }
  bot_html_footer();
}


/**
 * Only on webpage
 * @codeCoverageIgnore
 */
function bot_html_header() : void {
  echo('<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
  <title>Citation Bot: running</title>
  <link rel="copyright" type="text/html" href="https://www.gnu.org/licenses/gpl-3.0" />
  <link rel="stylesheet" type="text/css" href="results.css" />
  </head>
<body>
  <header>
    <p>Follow Citation bots progress below.</p>
    <p>
      <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" title="Using Citation Bot">How&nbsp;to&nbsp;Use&nbsp;/&nbsp;Tips&nbsp;and&nbsp;Tricks</a> |
      <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank">Report&nbsp;bugs</a> |
      <a href="https://github.com/ms609/citation-bot" target="_blank" title="GitHub repository">Source&nbsp;code</a>
    </p>
  </header>

  <pre id="botOutput">
   ');
  if (ini_get('pcre.jit') === '0') {
    report_warning('PCRE JIT Disabled');
  }
}

/**
 * Only on webpage
 * @codeCoverageIgnore
 */
function bot_html_footer() : void {
   if (HTML_OUTPUT) echo '</pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
   echo "\n";
}

  /**
   * NULL/FALSE/String of location
   **/
function hdl_works(string $hdl) : string|null|false {
  $hdl = trim($hdl);
  // And now some obvious fails
  if (strpos($hdl, '/') === FALSE) return FALSE;
  if (strpos($hdl, 'CITATION_BOT_PLACEHOLDER') !== FALSE) return FALSE;
  if (strpos($hdl, '123456789') === 0) return FALSE;
  if (strlen($hdl) > HandleCache::MAX_HDL_SIZE) return NULL;
  if (isset(HandleCache::$cache_hdl_loc[$hdl])) return HandleCache::$cache_hdl_loc[$hdl];
  if (isset(HandleCache::$cache_hdl_bad[$hdl])) return FALSE;
  if (isset(HandleCache::$cache_hdl_null[$hdl])) return NULL;
  if (strpos($hdl, '10.') === 0 && doi_works($hdl) === FALSE) return FALSE;
  $start_time = time();
  $works = is_hdl_works($hdl);
  if ($works === NULL) {
    if (abs(time()-$start_time) < max(BOT_HTTP_TIMEOUT, BOT_CONNECTION_TIMEOUT))
    {
      return NULL;
    } else {
      HandleCache::$cache_hdl_null[$hdl] = TRUE;
      return NULL;
    }
  }
  if ($works === FALSE) {
    HandleCache::$cache_hdl_bad[$hdl] = TRUE;
    return FALSE;
  }
  HandleCache::$cache_hdl_loc[$hdl] = $works;
  return $works;
}

  /**
   * Returns NULL/FALSE/String of location
   **/
function is_hdl_works(string $hdl) : string|null|false {
  $hdl = trim($hdl);
  usleep(100000);
  $url = "https://hdl.handle.net/" . $hdl;
  $headers_test = get_headers_array($url);
  if ($headers_test === FALSE) {
      $headers_test = get_headers_array($url); // @codeCoverageIgnore
  }
  if ($headers_test === FALSE) return NULL; // most likely bad, but will recheck again and again
  if (empty($headers_test['Location']) && empty($headers_test['location'])) return FALSE; // leads nowhere
  if (interpret_doi_header($headers_test) === NULL) return NULL;
  if (interpret_doi_header($headers_test) === FALSE) return FALSE;
  if (!is_null(@$headers_test['Location'][0])) {
      $the_header_loc = (string) $headers_test['Location'][0];
  } elseif (!is_null(@$headers_test['location'][0])) {
      $the_header_loc = (string) $headers_test['location'][0];
  } else {
      $the_header_loc = (string) @$headers_test['Location'] . (string) @$headers_test['location'];
  }
  return $the_header_loc;
}

// Sometimes (UTF-8 non-english characters) preg_replace fails, and we would rather have the original string than a null
function safe_preg_replace(string $regex, string $replace, string $old) : string {
  if ($old === "") return "";
  $new = preg_replace($regex, $replace, $old);
  if ($new === NULL) return $old;
  return $new;
}
function safe_preg_replace_callback(string $regex, callable $replace, string $old) : string {
  if ($old === "") return "";
  $new = preg_replace_callback($regex, $replace, $old);
  if ($new === NULL) return $old;
  return $new;
}

function wikifyURL(string $url) : string {
   $in  = array(' '  , '"'  , "'"  , '<'  ,'>'   , '['  , ']'  , '{'  , '|'  , '}');
   $out = array('%20', '%22', '%27', '%3C', '%3E', '%5B', '%5D', '%7B', '%7C', '%7D');
   return str_replace($in, $out, $url);
}

function numberToRomanRepresentation(int $number) : string { // https://stackoverflow.com/questions/14994941/numbers-to-roman-numbers-with-php
    $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
    $returnValue = '';
    while ($number > 0) {
	foreach ($map as $roman => $int) {
	    if($number >= $int) {
		$number -= $int;
		$returnValue .= $roman;
		break;
	    }
	}
    }
    return $returnValue;
}

function convert_to_utf8(string $value) : string {
    $encode1 =  mb_detect_encoding($value, ["UTF-8", "EUC-KR", "EUC-CN", "ISO-2022-JP", "Windows-1252", "iso-8859-1"], TRUE);
    if ($encode1 === FALSE || $encode1 === 'UTF-8' || $encode1 === 'Windows-1252') return $value;
    $encode2 =  mb_detect_encoding($value, ["UTF-8", "EUC-CN", "EUC-KR", "ISO-2022-JP", "Windows-1252", "iso-8859-1"], TRUE);
    if ($encode1 !== $encode2) return $value;
    $encode3 =  mb_detect_encoding($value, ["UTF-8", "ISO-2022-JP", "EUC-CN", "EUC-KR", "Windows-1252", "iso-8859-1"], TRUE);
    if ($encode1 !== $encode3) return $value;
    $encode4 =  mb_detect_encoding($value, ["iso-8859-1", "UTF-8", "Windows-1252", "ISO-2022-JP", "EUC-CN", "EUC-KR"], TRUE);
    if ($encode1 !== $encode4) return $value;
    $new_value = (string) @mb_convert_encoding($value, "UTF-8", $encode1);
    if ($new_value == "") return $value;
    return $new_value;
}

function is_encoding_reasonable(string $encode) : bool { // common "default" ones that are often wrong
  $encode = strtolower($encode);
  return !in_array($encode, ['utf-8', 'iso-8859-1', 'windows-1252', 'unicode', 'us-ascii', 'none', 'iso-8859-7', 'latin1']);
}

function smart_decode(string $title, string $encode, string $archive_url) : string {
  if ($title === "") return "";
  if ($encode === 'maccentraleurope') $encode = 'mac-centraleurope';
  if (in_array($encode, ['utf-8-sig', 'x-user-defined'])) { // Known wonky ones
     return "";
  }
  $master_list = mb_list_encodings();
  $valid = [];
  foreach ($master_list as $enc) {
    $valid[] = strtolower($enc);
  }
  try {
   if (in_array(strtolower($encode), ["windows-1255", "maccyrillic", "windows-1253", "windows-1256", "tis-620", "windows-874", "iso-8859-11", "big5", "windows-1250"]) ||
     !in_array(strtolower($encode), $valid)) {
    $try = (string) @iconv($encode, "UTF-8", $title);
   } else {
    $try = (string) @mb_convert_encoding($title, "UTF-8", $encode);
   }
  } catch (Exception $e) {
       $try = "";
  } catch (ValueError $v) {
       $try = "";
  }
  if ($try == "") {
       bot_debug_log('Bad Encoding: ' . $encode . ' for ' . echoable($archive_url)); // @codeCoverageIgnore
  }
  return $try;
}

/** @param array<string> $gid **/
function normalize_google_books(string &$url, int &$removed_redundant, string &$removed_parts, array &$gid) : void { // PASS BY REFERENCE!!!!!!
      $removed_redundant = 0;
      $hash = '';
      $removed_parts ='';
      $url = str_replace('&quot;', '"', $url);

      if (strpos($url, "#")) {
	$url_parts = explode("#", $url, 2);
	$url = $url_parts[0];
	$hash = $url_parts[1];
      }
      // And symbol in a search quote
      $url = str_replace("+&+", "+%26+", $url);
      $url = str_replace("+&,+", "+%26,+", $url);
      $url_parts = explode("&", str_replace("&&", "&", str_replace("?", "&", $url)));
      $url = "https://books.google.com/books?id=" . $gid[1];
      $book_array = array();
      foreach ($url_parts as $part) {
	$part_start = explode("=", $part, 2);
	if ($part_start[0] === 'text')     $part_start[0] = 'dq';
	if ($part_start[0] === 'keywords') $part_start[0] = 'q';
	if ($part_start[0] === 'page')     $part_start[0] = 'pg';
	switch ($part_start[0]) {
	  case "dq": case "pg": case "lpg": case "q": case "printsec": case "cd": case "vq": case "jtp": case "sitesec": case "article_id": case "bsq":
	    if (empty($part_start[1])) {
		$removed_redundant++;
		$removed_parts .= $part;
	    } else {
		$book_array[$part_start[0]] = $part_start[1];
	    }
	    break;
	  case "id":
	    break; // Don't "remove redundant"
	  case "as": case "useragent": case "as_brr": case "hl":
	  case "ei": case "ots": case "sig": case "source": case "lr": case "ved":
	  case "gs_lcp": case "sxsrf": case "gfe_rd": case "gws_rd":
	  case "sa": case "oi": case "ct": case "client": case "redir_esc":
	  case "callback": case "jscmd": case "bibkeys": case "newbks": case "gbpv":
	  case "newbks_redir": case "resnum": case "ci": case "surl": case "safe":
	  case "as_maxm_is": case "as_maxy_is": case "f": case "as_minm_is": case "pccc":
	  case "as_miny_is": case "authuser": case "cad": case "focus": case "pjf":
	  case "gl": case "ovdme": case "sqi": case "w": case "rview": case "": case "kptab":
	  case "pgis": case "ppis": case "output": case "gboemv": case "ie": case "nbsp;":
	  case "fbclid": case "num": case "oe": case "pli": case "prev": case "vid": case "view":
	  case "as_drrb_is": case "sourceid": case "btnG": case "rls": case "ov2":
	  case "buy": case "edge": case "zoom": case "img": case "as_pt": // Safe to remove - many are how you searched for the book
	    $removed_parts .= $part;
	    $removed_redundant++;
	    break;
	  default:
	    if ($removed_redundant !== 0) {
	      $removed_parts .= $part; // http://blah-blah is first parameter and it is not actually dropped
	      bot_debug_log("Unexpected dropping from Google Books " . $part);
	    }
	    $removed_redundant++;
	}
      }
      // Clean up hash first
      $hash = '&' . trim($hash) . '&';
      $hash = str_replace(['&f=false', '&f=true', 'v=onepage'], ['','',''], $hash); // onepage is default
      $hash = str_replace(['&q&', '&q=&', '&&&&', '&&&', '&&', '%20&%20'], ['&', '&', '&', '&', '&', '%20%26%20'], $hash);
      if (preg_match('~(&q=[^&]+)&~', $hash, $matcher)) {
	  $hash = str_replace($matcher[1], '', $hash);
	  if (isset($book_array['q'])) {
	    $removed_parts .= '&q=' . $book_array['q'];
	    $book_array['q'] = urlencode(urldecode(substr($matcher[1], 3))); // #q= wins over &q= before # sign
	  } elseif (isset($book_array['dq'])) {
	    $removed_parts .= '&dq=' . $book_array['dq'];
	    $dum_dq = str_replace('+', ' ', urldecode($book_array['dq']));
	    $dum_q  = str_replace('+', ' ', urldecode(substr($matcher[1], 3)));
	    if ($dum_dq !== $dum_q) {
	      $book_array['q'] = urlencode(urldecode(substr($matcher[1], 3)));
	      unset($book_array['dq']);
	    } else {
	      $book_array['dq'] = urlencode(urldecode(substr($matcher[1], 3)));
	    }
	  } else {
	    $book_array['q'] = urlencode(urldecode(substr($matcher[1], 3)));
	  }
      }
      if (preg_match('~(&dq=[^&]+)&~', $hash, $matcher)) {
	  $hash = str_replace($matcher[1], '', $hash);
	  if (isset($book_array['dq'])) $removed_parts .= '&dq=' . $book_array['dq'];
	  $book_array['dq'] = urlencode(urldecode(substr($matcher[1], 3))); // #dq= wins over &dq= before # sign
      }
      if (isset($book_array['vq']) && !isset($book_array['q']) && !isset($book_array['dq'])) {
	  $book_array['q'] = $book_array['vq'];
	  unset($book_array['vq']);
      }
      if (isset($book_array['vq']) && isset($book_array['pg'])) { // VQ wins if and only if a page is set
	  unset($book_array['q']);
	  unset($book_array['dq']);
	  $book_array['q'] = $book_array['vq'];
	  unset($book_array['vq']);
      }
      if (isset($book_array['bsq'])) {
	if (!isset($book_array['q']) && !isset($book_array['dq'])) {
	  $book_array['q'] = $book_array['bsq'];
	}
	unset($book_array['bsq']);
      }
      if (isset($book_array['q']) && isset($book_array['dq'])) { // Q wins over DQ
	  $removed_redundant++;
	  $removed_parts .= '&dq=' . $book_array['dq'];
	  unset($book_array['dq']);
      } elseif (isset($book_array['dq'])) {      // Prefer Q parameters to DQ
	if (!isset($book_array['pg']) && !isset($book_array['lpg'])) { // DQ requires that a page be set
	  $book_array['q'] = $book_array['dq'];
	  unset($book_array['dq']);
	}
      }
      if (isset($book_array['pg']) && isset($book_array['lpg'])) { // PG wins over LPG
	  $removed_redundant++;
	  $removed_parts .= '&lpg=' . $book_array['lpg'];
	  unset($book_array['lpg']);
      }
      if (!isset($book_array['pg']) && isset($book_array['lpg'])) { // LPG by itself does not work
	  $book_array['pg'] = $book_array['lpg'];
	  unset($book_array['lpg']);
      }
      if (preg_match('~^&(.*)$~', $hash, $matcher) ){
	  $hash = $matcher[1];
      }
      if (preg_match('~^(.*)&$~', $hash, $matcher) ){
	  $hash = $matcher[1];
      }
      if (preg_match('~^P*(PA\d+),M1$~', $hash, $matcher)){
	  $book_array['pg'] = $matcher[1];
	  $hash = '';
      }
      if (preg_match('~^P*(PP\d+),M1$~', $hash, $matcher)){
	  $book_array['pg'] = $matcher[1];
	  $hash = '';
      }
      if (preg_match('~^P*(PT\d+),M1$~', $hash, $matcher)){
	  $book_array['pg'] = $matcher[1];
	  $hash = '';
      }
      if (preg_match('~^P*(PR\d+),M1$~', $hash, $matcher)){
	  $book_array['pg'] = $matcher[1];
	  $hash = '';
      }

      if (isset($book_array['q'])){
	if (((stripos($book_array['q'], 'isbn') === 0) && ($book_array['q'] !=='ISBN') && ($book_array['q'] !== 'isbn')) || // Sometimes the search is for the term isbn
	    stripos($book_array['q'], 'subject:') === 0 ||
	    stripos($book_array['q'], 'inauthor:') === 0 ||
	    stripos($book_array['q'], 'inpublisher:') === 0) {
	  unset($book_array['q']);
	}
      }
      if (isset($book_array['dq'])){
	if (((stripos($book_array['dq'], 'isbn') === 0) && ($book_array['dq'] !=='ISBN') && ($book_array['dq'] !== 'isbn')) || // Sometimes the search is for the term isbn
	    stripos($book_array['dq'], 'subject:') === 0 ||
	    stripos($book_array['dq'], 'inauthor:') === 0 ||
	    stripos($book_array['dq'], 'inpublisher:') === 0) {
	  unset($book_array['dq']);
	}
      }
      if (isset($book_array['sitesec'])) { // Overrides all other setting
	if (strtolower($book_array['sitesec']) === 'reviews') {
	  $url .= '&sitesec=reviews';
	  unset($book_array['q']);
	  unset($book_array['pg']);
	  unset($book_array['lpg']);
	  unset($book_array['article_id']);
	}
      }
      if (isset($book_array['q'])){
	  $url .= '&q=' . $book_array['q'];
      }
      if (isset($book_array['dq'])){
	  $url .= '&dq=' . $book_array['dq'];
      }
      if (isset($book_array['pg'])){
	  if (preg_match('~^[pra]+\d~i', $book_array['pg'])) $book_array['pg'] = mb_strtoupper($book_array['pg']);
	  $url .= '&pg=' . $book_array['pg'];
      }
      if (isset($book_array['lpg'])){ // Currently NOT POSSIBLE - failsafe code for changes
	  $url .= '&lpg=' . $book_array['lpg']; // @codeCoverageIgnore
      }
      if (isset($book_array['article_id'])){
	  $url .= '&article_id=' . $book_array['article_id'];
	  if (!isset($book_array['dq']) && isset($book_array['q'])) {
	    $url .= '#v=onepage'; // Explicit onepage needed for these
	  }
      }
      if ($hash) {
	 $hash = "#" . $hash;
	 $removed_parts .= $hash;
	 $removed_redundant++;
      }     // CLEANED UP, so do not add $url = $url . $hash;
      if (preg_match('~^(https://books\.google\.com/books\?id=[^#^&]+)(?:&printsec=frontcover|)(?:#v=onepage|v=snippet|)$~', $url, $matches)) {
	 $url = $matches[1]; // URL Just wants the landing page
      }
}

function doi_is_bad (string $doi) : bool {
	$doi = strtolower($doi);
	if ($doi === '10.5284/1000184') return TRUE; // DOI for the entire database
	if ($doi === '10.1267/science.040579197') return TRUE; // PMID test doi
	if ($doi === '10.2307/3511692') return TRUE; // common review
	if ($doi === '10.1377/forefront') return TRUE; // over-truncated
	if ($doi === '10.1126/science') return TRUE; // over-truncated
	if (strpos($doi, '10.5779/hypothesis') === 0) return TRUE; // SPAM took over
	if (strpos($doi, '10.5555/') === 0) return TRUE; // Test DOI prefix
	if (strpos($doi, '10.5860/choice.') === 0) return TRUE; // Paywalled book review
	if (strpos($doi, '10.1093/law:epil') === 0) return TRUE; // Those do not work
	if (strpos($doi, '10.1093/oi/authority') === 0) return TRUE; // Those do not work
	if (strpos($doi, '10.10520/') === 0 && !doi_works($doi)) return TRUE; // Has doi in the URL, but is not a doi
	if (strpos($doi, '10.1967/') === 0 && !doi_works($doi)) return TRUE; // Retired DOIs
	if (strpos($doi, '10.1043/0003-3219(') === 0 && !doi_works($doi)) return TRUE; // Per-email.  The Angle Orthodontist will NEVER do these, since they have <> and [] in them
	if (strpos($doi, '10.3316/') === 0 && !doi_works($doi)) return TRUE; // These do not work - https://search.informit.org/doi/10.3316/aeipt.207729 etc.
	if (strpos($doi, '10.1002/was.') === 0 && !doi_works($doi)) return TRUE; // do's not doi's
	if (strpos($doi, '10.48550/arxiv') === 0) return TRUE;
	return FALSE;
}

/** @return array<string> **/
function get_possible_dois(string $doi) : array {
    $trial = array();
    $trial[] = $doi;
    // DOI not correctly formatted
    switch (substr($doi, -1)) {
      case ".":
	// Missing a terminal 'x'?
	$trial[] = $doi . "x";
      case ",": case ";": case "\"":
	// Or is this extra punctuation copied in?
	$trial[] = substr($doi, 0, -1);
    }
    if (substr($doi, -4) === '</a>' || substr($doi, -4) === '</A>') {
      $trial[] = substr($doi, 0, -4);
    }
    if (substr($doi, 0, 3) !== "10.") {
      if (substr($doi, 0, 2) === "0.") {
	$trial[] = "1" . $doi;
      } elseif (substr($doi, 0, 1) === ".") {
	$trial[] = "10" . $doi;
      } else {
	$trial[] = "10." . $doi;
      }
    }
    if (preg_match("~^(.+)(10\.\d{4,6}/.+)~", trim($doi), $match)) {
      $trial[] = $match[1];
      $trial[] = $match[2];
    }
    if (strpos($doi, '10.1093') === 0 && doi_works($doi) !== TRUE) {
	  if (preg_match('~^10\.1093/(?:ref:|)odnb/9780198614128\.001\.0001/odnb\-9780198614128\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/ref:odnb/' . $matches[1];
	      $trial[] = '10.1093/odnb/9780198614128.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/odnb/(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/ref:odnb/' . $matches[1];
	      $trial[] = '10.1093/odnb/9780198614128.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/ref:odnb/(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/odnb/9780198614128.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/9780198614128.013.(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/odnb/9780198614128.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/anb/9780198606697\.001\.0001/anb\-9780198606697\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/anb/9780198606697.article.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/benz/9780199773787\.001\.0001/acref-9780199773787\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/benz/9780199773787.article.B' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-7000(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/gao/9781884446054.article.T' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-700(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/gao/9781884446054.article.T' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acref/9780195301731\.001\.0001/acref\-9780195301731\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acref/9780195301731.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/ww/(9780199540891|9780199540884)\.001\.0001/ww\-9780199540884\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/ww/9780199540884.013.U' . $matches[2];
	  }
	  if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-00000(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/gmo/9781561592630.article.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-100(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/gmo/9781561592630.article.A' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-5000(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/gmo/9781561592630.article.O' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-400(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/gmo/9781561592630.article.L' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-2000(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/gmo/9781561592630.article.J' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780199366439\.001\.0001/acrefore\-9780199366439\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780199366439.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780190228613\.001\.0001/acrefore\-9780190228613\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780190228613.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780199389414\.001\.0001/acrefore\-9780199389414\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780199389414.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780199329175\.001\.0001/acrefore\-9780199329175\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780199329175.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780190277734\.001\.0001/acrefore\-9780190277734\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780190277734.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780190846626\.001\.0001/acrefore\-9780190846626\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780190846626.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780190228620\.001\.0001/acrefore\-9780190228620\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780190228620.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780199340378\.001\.0001/acrefore\-9780199340378\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780199340378.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780190854584\.001\.0001/acrefore\-9780190854584\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780190854584.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780199381135\.001\.0001/acrefore\-9780199381135\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780199381135.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780190236557\.001\.0001/acrefore\-9780190236557\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780190236557.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780190228637\.001\.0001/acrefore\-9780190228637\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780190228637.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/acrefore/9780190201098\.001\.0001/acrefore\-9780190201098\-e\-(\d+)$~', $doi, $matches)) {
	      $trial[] = '10.1093/acrefore/9780190201098.013.' . $matches[1];
	  }
	  if (preg_match('~^10\.1093/oso/(\d{13})\.001\.0001/oso\-(\d{13})\-chapter\-(\d+)$~', $doi, $matches)) {
	    if ($matches[1] === $matches[2]) {
	      $trial[] = '10.1093/oso/' . $matches[1] . '.003.' . str_pad($matches[3], 4, "0", STR_PAD_LEFT);
	    }
	  }
	  if (preg_match('~^10\.1093/med/9780199592548\.001\.0001/med\-9780199592548-chapter-(\d+)$~', $doi, $matches)) {
	    $trial[] = '10.1093/med/9780199592548.003.' . str_pad($matches[1], 4, "0", STR_PAD_LEFT);
	  }
	  if (preg_match('~^10\.1093/oso/(\d{13})\.001\.0001/oso\-(\d{13})$~', $doi, $matches)) {
	    if ($matches[1] === $matches[2]) {
	      $trial[] = '10.1093/oso/' . $matches[1] . '.001.0001';
	    }
	  }
	  if (preg_match('~^10\.1093/oxfordhb/(\d{13})\.001\.0001/oxfordhb\-(\d{13})-e-(\d+)$~', $doi, $matches)) {
	    if ($matches[1] === $matches[2]) {
	      $trial[] = '10.1093/oxfordhb/' . $matches[1] . '.013.'  . $matches[3];
	      $trial[] = '10.1093/oxfordhb/' . $matches[1] . '.013.0' . $matches[3];
	      $trial[] = '10.1093/oxfordhb/' . $matches[1] . '.003.'  . $matches[3];
	      $trial[] = '10.1093/oxfordhb/' . $matches[1] . '.003.0' . $matches[3];
	    }
	  }
    }
    $replacements = array ("&lt;" => "<", "&gt;" => ">");
    if (preg_match("~&[lg]t;~", $doi)) {
      $trial[] = str_replace(array_keys($replacements), $replacements, $doi);
    }
    $changed = TRUE;
    $try = $doi;
    while ($changed) {
      $changed = FALSE;
      if ($pos = strrpos($try, '.')) {
       $extension = substr($try, $pos);
       if (in_array(strtolower($extension), array('.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml', '.full'))) {
	 $try = substr($try, 0, $pos);
	 $trial[] = $try;
	 $changed = TRUE;
       }
      }
      if ($pos = strrpos($try, '#')) {
       $extension = substr($try, $pos);
       if (strpos(strtolower($extension), '#page_scan_tab_contents') === 0) {
	 $try = substr($try, 0, $pos);
	 $trial[] = $try;
	 $changed = TRUE;
       }
      }
      if ($pos = strrpos($try, ';')) {
       $extension = substr($try, $pos);
       if (strpos(strtolower($extension), ';jsessionid') === 0) {
	 $try = substr($try, 0, $pos);
	 $trial[] = $try;
	 $changed = TRUE;
       }
      }
      if ($pos = strrpos($try, '/')) {
       $extension = substr($try, $pos);
       if (in_array(strtolower($extension), array('/abstract', '/full', '/pdf', '/epdf', '/asset/', '/summary', '/short'))) {
	 $try = substr($try, 0, $pos);
	 $trial[] = $try;
	 $changed = TRUE;
       }
      }
      if (preg_match('~^(.+)v\d{1,2}$~', $try, $matches)) { // Versions
	 $try = $matches[1];
	 $trial[] = $try;
	 $changed = TRUE;
      }
    }
    return $trial;
}

function clean_up_oxford_stuff(Template $template, string $param) : void {
	  if (preg_match('~^https?://(latinamericanhistory|classics|psychology|americanhistory|africanhistory|internationalstudies|climatescience|religion|environmentalscience|politics)\.oxfordre\.com(/.+)$~', $template->get($param), $matches)) {
	       $template->set($param, 'https://oxfordre.com/' . $matches[1] . $matches[2]);
	  }

	  if (preg_match('~^(https?://(?:[\.+]|)oxfordre\.com)/([^/]+)/([^/]+)/([^/]+)/(.+)$~', $template->get($param), $matches)) {
	    if ($matches[2] === $matches[3] && $matches[2] === $matches[4]) {
	      $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[5]);
	    } elseif ($matches[2] === $matches[3]) {
	      $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[4] . '/' . $matches[5]);
	    }
	  }
	  if (preg_match('~^(https?://(?:[\.+]|)oxfordmusiconline\.com)/([^/]+)/([^/]+)/([^/]+)/(.+)$~', $template->get($param), $matches)) {
	    if ($matches[2] === $matches[3] && $matches[2] === $matches[4]) {
	      $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[5]);
	    } elseif ($matches[2] === $matches[3]) {
	      $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[4] . '/' . $matches[5]);
	    }
	  }

	  while (preg_match('~^(https?://www\.oxforddnb\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  while (preg_match('~^(https?://www\.anb\.org/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  while (preg_match('~^(https?://www\.oxfordartonline\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  while (preg_match('~^(https?://www\.ukwhoswho\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  while (preg_match('~^(https?://www\.oxfordmusiconline\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  while (preg_match('~^(https?://oxfordre\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  while (preg_match('~^(https?://oxfordaasc\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  while (preg_match('~^(https?://oxford\.universitypressscholarship\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  while (preg_match('~^(https?://oxfordreference\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
	       $template->set($param, $matches[1]);
	  }
	  if (preg_match('~^https?://www\.oxforddnb\.com/view/10\.1093/(?:ref:|)odnb/9780198614128\.001\.0001/odnb\-9780198614128\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/ref:odnb/' . $matches[1];
	      if (!doi_works($new_doi)) {
		$new_doi = '10.1093/odnb/9780198614128.013.' . $matches[1];
	      }
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-861412-8');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	      $the_title = $template->get('title');
	      if (preg_match('~^(.+) \- Oxford Dictionary of National Biography$~', $the_title, $matches) ||
		  preg_match('~^(.+) # # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # # Oxford Dictionary of National Biography$~', $the_title, $matches) ||
		  preg_match('~^(.+)  Oxford Dictionary of National Biography$~', $the_title, $matches) ||
		  preg_match('~^(.+) &#\d+; Oxford Dictionary of National Biography$~', $the_title, $matches)) {
		$template->set('title', trim($matches[1]));
	      }
	  }

	  if (preg_match('~^https?://www\.anb\.org/(?:view|abstract)/10\.1093/anb/9780198606697\.001\.0001/anb\-9780198606697\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/anb/9780198606697.article.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-860669-7');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://www\.oxfordartonline\.com/(?:benezit/|)(?:view|abstract)/10\.1093/benz/9780199773787\.001\.0001/acref-9780199773787\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/benz/9780199773787.article.B' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-977378-7');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }
	  if (preg_match('~^https?://www\.oxfordartonline\.com/(?:groveart/|)(?:view|abstract)/10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-7000(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/gao/9781884446054.article.T' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-1-884446-05-4');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }
	  if (preg_match('~^https?://www\.oxfordartonline\.com/(?:groveart/|)(?:view|abstract)/10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-700(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/gao/9781884446054.article.T' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-1-884446-05-4');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordaasc\.com/view/10\.1093/acref/9780195301731\.001\.0001/acref\-9780195301731\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acref/9780195301731.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-530173-1');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://www\.ukwhoswho\.com/(?:view|abstract)/10\.1093/ww/(9780199540891|9780199540884)\.001\.0001/ww\-9780199540884\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/ww/9780199540884.013.U' . $matches[2];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', $matches[1]);
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-00000(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/gmo/9781561592630.article.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-1-56159-263-0');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-100(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/gmo/9781561592630.article.A' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-1-56159-263-0');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-5000(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/gmo/9781561592630.article.O' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-1-56159-263-0');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-400(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/gmo/9781561592630.article.L' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-1-56159-263-0');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-2000(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/gmo/9781561592630.article.J' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-1-56159-263-0');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|latinamericanhistory/)(?:view|abstract)/10\.1093/acrefore/9780199366439\.001\.0001/acrefore\-9780199366439\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780199366439.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-936643-9');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|communication/)(?:view|abstract)/10\.1093/acrefore/9780190228613\.001\.0001/acrefore\-9780190228613\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780190228613.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-022861-3');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|environmentalscience/)(?:view|abstract)/10\.1093/acrefore/9780199389414\.001\.0001/acrefore\-9780199389414\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780199389414.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-938941-4');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|americanhistory/)(?:view|abstract)/10\.1093/acrefore/9780199329175\.001\.0001/acrefore\-9780199329175\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780199329175.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-932917-5');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|africanhistory/)(?:view|abstract)/10\.1093/acrefore/9780190277734\.001\.0001/acrefore\-9780190277734\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780190277734.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-027773-4');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|internationalstudies/)(?:view|abstract)/10\.1093/acrefore/9780190846626\.001\.0001/acrefore\-9780190846626\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780190846626.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-084662-6');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|climatescience/)(?:view|abstract)/10\.1093/acrefore/9780190228620\.001\.0001/acrefore\-9780190228620\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780190228620.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-022862-0');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|religion/)(?:view|abstract)/10\.1093/acrefore/9780199340378\.001\.0001/acrefore\-9780199340378\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780199340378.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-934037-8');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|anthropology/)(?:view|abstract)/10\.1093/acrefore/9780190854584\.001\.0001/acrefore\-9780190854584\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780190854584.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-085458-4');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|classics/)(?:view|abstract)/10\.1093/acrefore/9780199381135\.001\.0001/acrefore\-9780199381135\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780199381135.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-938113-5');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|psychology/)(?:view|abstract)/10\.1093/acrefore/9780190236557\.001\.0001/acrefore\-9780190236557\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780190236557.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-023655-7');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|politics/)(?:view|abstract)/10\.1093/acrefore/9780190228637\.001\.0001/acrefore\-9780190228637\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780190228637.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-022863-7');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxfordre\.com/(?:|literature/)(?:view|abstract)/10\.1093/acrefore/9780190201098\.001\.0001/acrefore\-9780190201098\-e\-(\d+)$~', $template->get($param), $matches)) {
	      $new_doi = '10.1093/acrefore/9780190201098.013.' . $matches[1];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', '978-0-19-020109-8');
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	  }

	  if (preg_match('~^https?://oxford\.universitypressscholarship\.com/(?:view|abstract)/10\.1093/(oso|acprof:oso)/(\d{13})\.001\.0001/oso\-(\d{13})\-chapter\-(\d+)$~', $template->get($param), $matches)) {
	    if ($matches[2] === $matches[3]) {
	      $template->add_if_new('isbn', $matches[2]);
	      $new_doi = '10.1093/' . $matches[1] . '/' . $matches[2] . '.003.' . str_pad($matches[4], 4, "0", STR_PAD_LEFT);
	      if (doi_works($new_doi)) {
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	    }
	  }

	  if (preg_match('~^https?://(?:www\.|)oxfordmedicine\.com/(?:view|abstract)/10\.1093/med/9780199592548\.001\.0001/med\-9780199592548-chapter-(\d+)$~', $template->get($param), $matches)) {
	    $new_doi = '10.1093/med/9780199592548.003.' . str_pad($matches[1], 4, "0", STR_PAD_LEFT);
	    if (doi_works($new_doi)) {
	      $template->add_if_new('isbn', '978-0-19-959254-8');
	      if ($template->has('doi') && ($template->has('doi-broken-date') || $template->get('doi') === '10.1093/med/9780199592548.001.0001')) {
		  $template->set('doi', '');
		  $template->forget('doi-broken-date');
		  $template->add_if_new('doi', $new_doi);
	       } elseif ($template->blank('doi')) {
		  $template->add_if_new('doi', $new_doi);
	      }
	    }
	  }

	  if (preg_match('~^https?://oxford\.universitypressscholarship\.com/(?:view|abstract)/10\.1093/oso/(\d{13})\.001\.0001/oso\-(\d{13})$~', $template->get($param), $matches)) {
	    if ($matches[1] === $matches[2]) {
	      $template->add_if_new('isbn', $matches[1]);
	      $new_doi = '10.1093/oso/' . $matches[1] . '.001.0001';
	      if (doi_works($new_doi)) {
		if ($template->has('doi') && $template->has('doi-broken-date')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	    }
	  }

	  if (preg_match('~^https?://(?:www\.|)oxfordhandbooks\.com/(?:view|abstract)/10\.1093/oxfordhb/(\d{13})\.001\.0001/oxfordhb\-(\d{13})-e-(\d+)$~', $template->get($param), $matches)) {
	    if ($matches[1] === $matches[2]) {
	      $new_doi = '10.1093/oxfordhb/' . $matches[1] . '.013.' . $matches[3];
	      if (doi_works($new_doi)) {
		$template->add_if_new('isbn', $matches[1]);
		if (($template->has('doi') && $template->has('doi-broken-date')) || ($template->get('doi') === '10.1093/oxfordhb/9780199552238.001.0001')) {
		    $template->set('doi', '');
		    $template->forget('doi-broken-date');
		    $template->add_if_new('doi', $new_doi);
		 } elseif ($template->blank('doi')) {
		    $template->add_if_new('doi', $new_doi);
		}
	      }
	    }
	  }
}

function conference_doi(string $doi) : bool {
  if (stripos($doi, '10.1007/978-3-662-44777') === 0) return FALSE; // Manual override of stuff
  if (strpos($doi, '10.1109/') === 0 ||
      strpos($doi, '10.1145/') === 0 ||
      strpos($doi, '10.1117/') === 0 ||
      strpos($doi, '10.2991/') === 0 ||
      stripos($doi, '10.21437/Eurospeech') === 0 ||
      stripos($doi, '10.21437/interspeech') === 0 ||
      stripos($doi, '10.21437/SLTU') === 0 ||
      stripos($doi, '10.21437/TAL') === 0 ||
      (strpos($doi, '10.1007/978-') === 0 && strpos($doi, '_') !== FALSE) ||
      stripos($doi, '10.2991/erss') === 0 ||
      stripos($doi, '10.2991/jahp') === 0) {
	 return TRUE;
  }
  return FALSE;
}

function clean_dates(string $input) : string { // See https://en.wikipedia.org/wiki/Help:CS1_errors#bad_date
    if ($input === '0001-11-30') return '';
    $days_of_week = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Mony', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun');
    $months_seasons = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Winter', 'Spring', 'Summer', 'Fall', 'Autumn');
    $input = str_ireplace($months_seasons, $months_seasons, $input); // capitalization
    if (preg_match('~^(\d{4})[\-\/](\d{4})$~', $input, $matches)) { // Hyphen or slash in year range (use en dash)
      return $matches[1] . '–' . $matches[2];
    }
    if (preg_match('~^(\d{4})\/ed$~i', $input, $matches)) { // 2002/ed
      return $matches[1];
    }
    if (preg_match('~^First published(?: |\: | in | in\: | in\:)(\d{4})$~i', $input, $matches)) { // First published: 2002
      return $matches[1];
    }
    if (preg_match('~^([A-Z][a-z]+)[\-\/]([A-Z][a-z]+) (\d{4})$~', $input, $matches)) { // Slash or hyphen in date range (use en dash)
      return $matches[1] . '–' . $matches[2] . ' ' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+ \d{4})[\-\–]([A-Z][a-z]+ \d{4})$~', $input, $matches)) { // Missing space around en dash for range of full dates
      return $matches[1] . ' – ' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+), (\d{4})$~', $input, $matches)) { // Comma with month/season and year
      return $matches[1] . ' ' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+), (\d{4})[\-\–](\d{4})$~', $input, $matches)) { // Comma with month/season and years
      return $matches[1] . ' ' . $matches[2] . '–' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+) 0(\d),? (\d{4})$~', $input, $matches)) { // Zero-padding
      return $matches[1] . ' ' . $matches[2] . ', ' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+ \d{1,2})( \d{4})$~', $input, $matches)) { // Missing comma in format which requires it
      return $matches[1] . ',' . $matches[2];
    }
    if (preg_match('~^Collected[\s\:]+((?:|[A-Z][a-z]+ )\d{4})$~', $input, $matches)) { // Collected 1999 stuff
      return $matches[1];
    }
    if (preg_match('~^Effective[\s\:]+((?:|[A-Z][a-z]+ )\d{4})$~', $input, $matches)) { // Effective 1999 stuff
      return $matches[1];
    }
    if (preg_match('~^(\d+ [A-Z][a-z]+ \d{4})\.$~', $input, $matches)) { // 8 December 2022. (period on end)
      return $matches[1];
    }
    if (preg_match('~^0(\d [A-Z][a-z]+ \d{4})$~', $input, $matches)) { // 08 December 2022 - leading zero
      return $matches[1];
    }
    if (preg_match('~^([A-Z][a-z]+)\, ([A-Z][a-z]+ \d+,* \d{4})$~', $input, $matches)) { // Monday, November 2, 1981
      if (in_array($matches[1], $days_of_week)) {
	return $matches[2];
      }
    }
    if (preg_match('~^(\d{4})\s*(?:&|and)\s*(\d{4})$~', $input, $matches)) { // &/and between years
      $first = (int) $matches[1];
      $second = (int) $matches[2];
      if ($second === $first+1) {
	return $matches[1] . '–' . $matches[2];
      }
    }
    if (preg_match('~^([A-Z][a-z]+)\-([A-Z][a-z]+ \d{4})$~', $input, $matches)) { // April-May 1995 to April–May 1995
      return $matches[1] . '–' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+) (\d\d*) (\d{4})$~', $input, $matches)) { // December 7 2023 to December 7, 2023
      if (in_array($matches[1], $months_seasons)) {
	return $matches[1] . ' ' . $matches[2] . ', ' . $matches[3];
      }
    }

    if (preg_match('~^(\d{4})\-(\d{2})$~', $input, $matches)) { // 2020-12 i.e. backwards
      $year = $matches[1];
      $month = (int) $matches[2];
      if ($month > 0 && $month < 13) {
	return $months_seasons[$month-1] . ' ' . $year;
      }
    }
    return $input;
}

/** @return false|array<mixed> **/
function get_headers_array(string $url) : false|array {
  static $last_url = "none yet";
  // Allow cheap journals to work
  static $context_insecure;
  if (!isset($context_insecure)) {
    $context_insecure = stream_context_create(array(
      'ssl' => ['verify_peer' => FALSE, 'verify_peer_name' => FALSE, 'allow_self_signed' => TRUE, 'security_level' => 0, 'verify_depth' => 0],
      'http' => ['ignore_errors' => TRUE, 'max_redirects' => 40, 'timeout' => BOT_HTTP_TIMEOUT * 1.0, 'follow_location' => 1, 'header'=> ['Connection: close'], "user_agent" => BOT_USER_AGENT]));
  }
  set_time_limit(120);
  if ($last_url === $url) {
     sleep(5);
     report_inline(' .');
  }
  $last_url = $url;
  return @get_headers($url, TRUE, $context_insecure);
}
