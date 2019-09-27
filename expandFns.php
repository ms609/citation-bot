<?php

function sanitize_doi($doi) {
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
  $doi = preg_replace('~^https?://d?x?\.?doi\.org/~i', '', $doi); // Strip URL part if present
  $doi = preg_replace('~^doi:~i', '', $doi); // Strip doi: part if present
  $doi = str_replace("+" , "%2B", $doi); // plus signs are valid DOI characters, but in URLs are "spaces"
  $doi = str_replace(HTML_ENCODE_DOI, HTML_DECODE_DOI, trim(urldecode($doi)));
  $extension = substr($doi, strrpos($doi, '.'));
  if (in_array(strtolower($extension), array('.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml'))) {
      $doi = substr($doi, 0, (strrpos($doi, $extension)));
  }
  $extension = substr($doi, strrpos($doi, '/'));
  if (in_array(strtolower($extension), array('/abstract', '/full', '/pdf', '/epdf', '/asset/', '/summary', '/short', ';jsessionid'))) {
      $doi = substr($doi, 0, (strrpos($doi, $extension)));
  }
  // And now for 10.1093 URLs
  // The add chapter/page stuff after the DOI in the URL and it looks like part of the DOI to us
  // Things like 10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-003 and 10.1093/acprof:oso/9780195304923.001.0001/acprof-9780195304923-chapter-7
  if (strpos($doi, '10.1093') === 0) {
    if (preg_match('~^(10\.1093/oxfordhb.+)(?:/oxfordhb.+)$~', $doi, $match)) {
       $doi = $match[1];
    }
    if (preg_match('~^(10\.1093/acprof.+)(?:/acprof.+)$~', $doi, $match)) {
       $doi = $match[1];
    }
    if (preg_match('~^(10\.1093/acref.+)(?:/acref.+)$~', $doi, $match)) {
       $doi = $match[1];
    }
    if (preg_match('~^(10\.1093/ref:odnb.+)(?:/odnb.+)$~', $doi, $match)) {
       $doi = $match[1];
    }
    if (preg_match('~^(10\.1093/ww.+)(?:/ww.+)$~', $doi, $match)) { // Who's who of all things
       $doi = $match[1];
    }
    if (preg_match('~^(10\.1093/anb.+)(?:/anb.+)$~', $doi, $match)) {
       $doi = $match[1];
    } 
  }
  return $doi;
}

/* extract_doi
 * Returns an array containing:
 * 0 => text containing a DOI, possibly encoded, possibly with additional text
 * 1 => the decoded DOI
 */
function extract_doi($text) {
  if (preg_match(
        "~(10\.\d{4}\d?(/|%2[fF])..([^\s\|\"\?&>]|&l?g?t;|<[^\s\|\"\?&]*>)+)~",
        $text, $match)) {
    $doi = $match[1];
    if (preg_match(
          "~^(.*?)(/abstract|/e?pdf|/full|/figure|</span>|[\s\|\"\?]|</).*+$~",
          $doi, $new_match)
        ) {
      $doi = $new_match[1];
    }
    $doi_candidate = sanitize_doi($doi);
    while (preg_match(REGEXP_DOI, $doi_candidate) && !doi_works($doi_candidate)) {
      $last_delimiter = 0;
      foreach (array('/', '.', '#', '?') as $delimiter) {
        $delimiter_position = strrpos($doi_candidate, $delimiter);
        $last_delimiter = ($delimiter_position > $last_delimiter) ? $delimiter_position : $last_delimiter;
      }
      $doi_candidate = substr($doi_candidate, 0, $last_delimiter);
    }
    if (doi_works($doi_candidate)) $doi = $doi_candidate;
    return array($match[0], sanitize_doi($doi));
  }
  return NULL;
}

function wikify_external_text($title) {
  $replacement = [];
  if (preg_match_all("~<(?:mml:)?math[^>]*>(.*?)</(?:mml:)?math>~", $title, $matches)) {
    $placeholder = [];
    for ($i = 0; $i < count($matches[0]); $i++) {
      $replacement[$i] = '<math>' . 
        str_replace(array_keys(MML_TAGS), array_values(MML_TAGS), 
          str_replace(['<mml:', '</mml:'], ['<', '</'], $matches[1][$i]))
        . '</math>';
      $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i); 
      // Need to use a placeholder to protect contents from URL-safening
      $title = str_replace($matches[0][$i], $placeholder[$i], $title);
    }
  }
  $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
  $title = preg_replace("~\s+~"," ", $title);  // Remove all white spaces before
  if (mb_substr($title, -6) == "&nbsp;") $title = mb_substr($title, 0, -6);
  if (mb_substr($title, -1) == ".") {
    $last_word = mb_substr($title, mb_strpos($title, ' ') + 1);
    if (mb_substr_count($last_word, '.') === 1) $last_word = mb_substr($title, 0, -1); // Do not remove if something like D.C.  (will not catch D. C. though)
  }
  $title = preg_replace('~[\*]$~', '', $title);
  $title = title_capitalization($title, TRUE);

  $htmlBraces  = array("&lt;", "&gt;");
  $angleBraces = array("<", ">");
  $title = str_ireplace($htmlBraces, $angleBraces, $title);

  $originalTags = array('<title>', '</title>', '</ title>', 'From the Cover: ');
  $wikiTags = array('','','','');
  $title = str_ireplace($originalTags, $wikiTags, $title);
  $originalTags = array('.<br>', '.</br>', '.</ br>', '.<p>', '.</p>', '.</ p>');
  $wikiTags = array('. ','. ','. ','. ','. ','. ');
  $title = str_ireplace($originalTags, $wikiTags, $title);
  $originalTags = array('<br>', '</br>', '</ br>', '<p>', '</p>', '</ p>');
  $wikiTags = array('. ','. ','. ','. ','. ','. ');
  $title = str_ireplace($originalTags, $wikiTags, $title);

  $title_orig = '';
  while ($title != $title_orig) {
    $title_orig = $title;  // Might have to do more than once.   The following do not allow < within the inner match since the end tag is the same :-( and they might nest or who knows what
    $title = preg_replace_callback('~(?:<Emphasis Type="Italic">)([^<]+)(?:</Emphasis>)~iu',
      function ($matches) {return ("''" . $matches[1] . "''");},
      $title);
    $title = preg_replace_callback('~(?:<Emphasis Type="Bold">)([^<]+)(?:</Emphasis>)~iu',
      function ($matches) {return ("'''" . $matches[1] . "'''");},
      $title);
    $title = preg_replace_callback('~(?:<em>)([^<]+)(?:</em>)~iu',
      function ($matches) {return ("''" . $matches[1] . "''");},
      $title);
    $title = preg_replace_callback('~(?:<i>)([^<]+)(?:</i>)~iu',
      function ($matches) {return ("''" . $matches[1] . "''");},
      $title);
  }

  $title = sanitize_string($title);
  
  for ($i = 0; $i < count($replacement); $i++) {
    $title = str_replace($placeholder[$i], $replacement[$i], $title);
  }
  return($title); 
}

function under_two_authors($text) {
  return !(strpos($text, ';') !== FALSE  //if there is a semicolon
          || substr_count($text, ',') > 1  //if there is more than one comma
          || substr_count($text, ',') < substr_count(trim($text), ' ')  //if the number of commas is less than the number of spaces in the trimmed string
          );
}

/* split_authors
 * Assumes that there is more than one author to start with; 
 * check this using under_two_authors()
 */
function split_authors($str) {
  if (stripos($str, ';')) return explode(';', $str);
  return explode(',', $str);
}

function title_case($text) {
  return mb_convert_case($text, MB_CASE_TITLE, "UTF-8");
}

function restore_italics ($text) {
  // <em> tags often go missing around species names in CrossRef
  return preg_replace('~([a-z]+)([A-Z][a-z]+\b)~', "$1 ''$2''", $text);
}

/** Returns a properly capitalised title.
 *      If $caps_after_punctuation is TRUE (or there is an abundance of periods), it allows the 
 *      letter after colons and other punctuation marks to remain capitalized.
 *      If not, it won't capitalise after : etc.
 */
function title_capitalization($in, $caps_after_punctuation) {
  // Use 'straight quotes' per WP:MOS
  $new_case = straighten_quotes(trim($in));
  if (mb_substr($new_case, 0, 1) === "[" && mb_substr($new_case, -1) === "]") {
     return $new_case; // We ignore wikilinked names and URL linked since who knows what's going on there.
                       // Changing case may break links (e.g. [[Journal YZ|J. YZ]] etc.)
  }
  
  if ($new_case == mb_strtoupper($new_case) 
     && mb_strlen(str_replace(array("[", "]"), "", trim($in))) > 6
     ) {
    // ALL CAPS to Title Case
    $new_case = mb_convert_case($new_case, MB_CASE_TITLE, "UTF-8");
  }

  // Implicit acronyms
  $new_case = ' ' . $new_case . ' ';
  $new_case = preg_replace_callback("~[^\w&][b-df-hj-np-tv-xz]{3,}(?=\W)~ui", 
      function ($matches) {return mb_strtoupper($matches[0]);}, // Three or more consonants.  NOT Y
      $new_case);
  $new_case = preg_replace_callback("~[^\w&][aeiou]{3,}(?=\W)~ui", 
      function ($matches) {return mb_strtoupper($matches[0]);}, // Three or more vowels.  NOT Y
      $new_case);
  $new_case = mb_substr($new_case, 1, -1); // Remove added spaces

  $new_case = mb_substr(str_replace(UC_SMALL_WORDS, LC_SMALL_WORDS, " " . $new_case . " "), 1, -1);
  
  if ($caps_after_punctuation || (substr_count($in, '.') / strlen($in)) > .07) {
    // When there are lots of periods, then they probably mark abbrev.s, not sentence ends
    // We should therefore capitalize after each punctuation character.
    $new_case = preg_replace_callback("~[?.:!/]\s+[a-z]~u" /* Capitalise after punctuation */,
      function ($matches) {return mb_strtoupper($matches[0]);},
      $new_case);
    $new_case = preg_replace_callback("~(?<!<)/[a-z]~u" /* Capitalise after slash unless part of ending html tag */,
      function ($matches) {return mb_strtoupper($matches[0]);},
      $new_case);
    // But not "Ann. Of...." which seems to be common in journal titles
    $new_case = str_replace("Ann. Of ", "Ann. of ", $new_case);
  }
  
  $new_case = preg_replace_callback(
    "~ \([a-z]~u" /* uppercase after parenthesis */, 
    function($matches) {return mb_strtoupper($matches[0]);},
    trim($new_case)
  );
  
  $new_case = preg_replace_callback(
    "~\w{2}'[A-Z]\b~u" /* Lowercase after apostrophes */, 
    function($matches) {return mb_strtolower($matches[0]);},
    trim($new_case)
  );
  /** French l'Words and d'Words  **/
  $new_case = preg_replace_callback(
    "~(\s[LD][\'\x{00B4}])([a-zA-ZÀ-ÿ]+)~u",
    function($matches) {return mb_strtolower($matches[1]) . mb_ucfirst($matches[2]);},
    ' ' . $new_case
  );
  
  /** Italian dell'xxx words **/
  $new_case = preg_replace_callback(
    "~(\s)(Dell|Degli|Delle)([\'\x{00B4}][a-zA-ZÀ-ÿ]{3})~u",
    function($matches) {return $matches[1] . strtolower($matches[2]) . $matches[3];},
    $new_case
  );
  
  $new_case = mb_ucfirst(trim($new_case));

  // Solitary 'a' should be lowercase
  $new_case = preg_replace("~(\w\s+)A(\s+\w)~u", "$1a$2", $new_case);
  // but not in "U S A"
  $new_case = trim(str_replace(" U S a ", " U S A ", ' ' . $new_case . ' '));

  // This should be capitalized
  $new_case = str_replace(['(new Series)', '(new series)'] , ['(New Series)', '(New Series)'], $new_case);
  
  // Catch some specific epithets, which should be lowercase
  $new_case = preg_replace_callback(
    "~(?:'')?(?P<taxon>\p{L}+\s+\p{L}+)(?:'')?\s+(?P<nova>(?:(?:gen\.? no?v?|sp\.? no?v?|no?v?\.? sp|no?v?\.? gen)\b[\.,\s]*)+)~ui" /* Species names to lowercase */,
    function($matches) {return "''" . ucfirst(strtolower($matches['taxon'])) . "'' " . strtolower($matches["nova"]);},
    $new_case);
  
  // "des" at end is "Des" for Design not german "The"
  if (mb_substr($new_case, -4, 4) == ' des') $new_case = mb_substr($new_case, 0, -4)  . ' Des';
  
  // Capitalization exceptions, e.g. Elife -> eLife
  $new_case = str_replace(UCFIRST_JOURNAL_ACRONYMS, JOURNAL_ACRONYMS, " " .  $new_case . " ");
  $new_case = mb_substr($new_case, 1, mb_strlen($new_case) - 2); // remove spaces, needed for matching in LC_SMALL_WORDS
  
  // Single letter at end should be capitalized  J Chem Phys E for example.  Obviously not the spanish word "e".
  if (mb_substr($new_case, -2, 1) == ' ') $new_case = strrev(ucfirst(strrev($new_case)));

  // Trust existing "ITS", "its", ... 
  $its_in = preg_match_all('~ its(?= )~iu', ' ' . trim($in) . ' ', $matches_in, PREG_OFFSET_CAPTURE);
  $new_case = trim($new_case);
  $its_out = preg_match_all('~ its(?= )~iu', ' ' . $new_case . ' ', $matches_out, PREG_OFFSET_CAPTURE);
  if ($its_in === $its_out && $its_in != 0) {
    $matches_in = $matches_in[0];
    $matches_out = $matches_out[0];
    foreach ($matches_in as $key => $value) {
      if ($matches_in[$key][0] != $matches_out[$key][0]  &&
          $matches_in[$key][1] == $matches_out[$key][1]) {
        $new_case = mb_substr_replace($new_case, trim($matches_in[$key][0]), $matches_out[$key][1], 3);
      }
    }
  }
  return $new_case;
}

function mb_ucfirst($string)
{
    return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1, NULL);
}
  
function mb_substr_replace($string, $replacement, $start, $length) {
    return mb_substr($string, 0, $start).$replacement.mb_substr($string, $start+$length);
}

/**
 * Cannot really test in TRAVIS
 * @codeCoverageIgnore
 */
function throttle ($min_interval) {
  static $last_write_time = 0;
  $time_since_last_write = time() - $last_write_time;
  if ($time_since_last_write < $min_interval) {
    $time_to_pause = floor($min_interval - $time_since_last_write);
    report_warning("Throttling: waiting $time_to_pause seconds...");
    for ($i = 0; $i < $time_to_pause; $i++) {
      sleep(1); 
      report_inline(' .');
    }
  }
  $last_write_time = time();
}

function sanitize_string($str) {
  // ought only be applied to newly-found data.
  if (strtolower(trim($str)) == 'science (new york, n.y.)') return 'Science';
  $math_templates_present = preg_match_all("~<\s*math\s*>.*<\s*/\s*math\s*>~", $str, $math_hits);
  if ($math_templates_present) {
    $replacement = [];
    $placeholder = [];
    for ($i = 0; $i < count($math_hits[0]); $i++) {
      $replacement[$i] = $math_hits[0][$i];
      $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i);
    }
    $str = str_replace($replacement, $placeholder, $str);
  }
  $dirty = array ('[', ']', '|', '{', '}');
  $clean = array ('&#91;', '&#93;', '&#124;', '&#123;', '&#125;');
  $str = trim(str_replace($dirty, $clean, preg_replace('~[;.,]+$~', '', $str)));
  if ($math_templates_present) {
    $str = str_replace($placeholder, $replacement, $str);
  }
  return $str;
}

function tidy_date($string) {
  $string=trim($string);
  if (stripos($string, 'Invalid') !== FALSE) return '';
  if (!preg_match('~\d{2}~', $string)) return ''; // If there are not two numbers next to each other, reject
  // Google sends ranges
  if (preg_match('~^(\d{4})(\-\d{2}\-\d{2})\s+\-\s+(\d{4})(\-\d{2}\-\d{2})$~', $string, $matches)) { // Date range
     if ($matches[1] == $matches[3]) {
       return date('j F', strtotime($matches[1].$matches[2])) . ' – ' . date('j F Y', strtotime($matches[3].$matches[4]));
     } else {
       return date('j F Y', strtotime($matches[1].$matches[2])) . ' – ' . date('j F Y', strtotime($matches[3].$matches[4])); 
     }
  }
  // Huge amout of character cleaning
  if (strlen($string) != mb_strlen($string)) {  // Convert all multi-byte characters to dashes
    $cleaned = '';
    for ($i = 0; $i < mb_strlen($string); $i++) {
       $char = mb_substr($string,$i,1);
       if (mb_strlen($char) == strlen($char)) {
          $cleaned .= $char;
       } else {
          $cleaned .= '-';
       }
    }
    $string = $cleaned;
  }
  $string = preg_replace("~[^\x01-\x7F]~","-", $string); // Convert any non-ASCII Characters to dashes
  $string = preg_replace('~[\s\-]*\-[\s\-]*~', '-',$string); // Combine dash with any following or preceeding white space and other dash
  $string = preg_replace('~^\-*(.+?)\-*$~', '\1', $string);  // Remove trailing/leading dashes
  $string = trim($string);
  // End of character clean-up
  $string = preg_replace('~[^0-9]+\d{2}:\d{2}:\d{2}$~', '', $string); //trailing time
  $string = preg_replace('~^Date published \(~', '', $string); // seen this
  // https://stackoverflow.com/questions/29917598/why-does-0000-00-00-000000-return-0001-11-30-000000
  if (strpos($string, '0001-11-30') !== FALSE) return '';
  if (strcasecmp('19xx', $string) === 0) return ''; //archive.org gives this if unknown
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
  if (preg_match('~^(\d{4}\-\d{2}\-\d{2})T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$~', $string, $matches)) return tidy_date($matches[1]); // Remove time zone stuff from standard date format
  if (is_numeric($string) && is_int(1*$string)) {
    $string = intval($string);
    if ($string < -2000 || $string > date("Y") + 10) return ''; // A number that is not a year; probably garbage 
    if ($string > -2 && $string < 2) return ''; // reject -1,0,1
    return $string; // year
  }
  if (preg_match('~^(\d{1,2}) ([A-Za-z]+\.?), ?(\d{4})$~', $string, $matches)) { // strtotime('3 October, 2016') gives 2019-10-03.  The comma is evil and strtotime is stupid
    $string = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];   // Remove comma
  }
  $time = strtotime($string);
  if ($time) {
    $day = date('d', $time);
    $year = intval(date('Y', $time));
    if ($year < -2000 || $year > date("Y") + 10) return ''; // We got an invalid year
    if ($year < 3 && $year > -3) return '';
    if ($day == '01') { // Probably just got month and year
      $string = date('F Y', $time);
    } else {
      $string = date('Y-m-d', $time);
    }
    if (stripos($string, 'Invalid') !== FALSE) return '';
    return $string;
  }
  if (preg_match( '~^(\d{4}\-\d{1,2}\-\d{1,2})[^0-9]~', $string, $matches)) return tidy_date($matches[1]); // Starts with date
  if (preg_match('~\s(\d{4}\-\d{1,2}\-\d{1,2})$~',     $string, $matches)) return tidy_date($matches[1]);  // Ends with a date
  if (preg_match('~^(\d{1,2}/\d{1,2}/\d{4})[^0-9]~', $string, $matches)) return tidy_date($matches[1]); //Recusion to clean up 3/27/2000
  if (preg_match('~[^0-9](\d{1,2}/\d{1,2}/\d{4})$~', $string, $matches)) return tidy_date($matches[1]);
  
  // Dates with dots -- convert to slashes and try again.
  if (preg_match('~(\d\d?)\.(\d\d?)\.(\d{2}(?:\d{2})?)$~', $string, $matches) || preg_match('~^(\d\d?)\.(\d\d?)\.(\d{2}(?:\d{2})?)~', $string, $matches)) {
    if (intval($matches[3]) < (date("y")+2))  $matches[3] = $matches[3] + 2000;
    if (intval($matches[3]) < 100)  $matches[3] = $matches[3] + 1900;
    return tidy_date($matches[1] . '/' .  $matches[2] . '/' . $matches[3]);
  }
  
  if (preg_match('~\s(\d{4})$~', $string, $matches)) return $matches[1]; // Last ditch effort - ends in a year
  return ''; // And we give up
}

function remove_brackets($string) {
  return str_replace(['(', ')', '{', '}', '[', ']'], '' , $string);
}

function remove_comments($string) {
  // See Comment::PLACEHOLDER_TEXT for syntax
  $string = preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~', "", $string);
  return preg_replace("~<!--.*?-->~us", "", $string);
}

function prior_parameters($par, $list=array()) {
  array_unshift($list, $par);
  if (preg_match('~(\D+)(\d+)~', $par, $match)) {
    switch ($match[1]) {
      case 'first': case 'initials': case 'forename':
        return array('last' . $match[2], 'surname' . $match[2]);
      case 'last': case 'surname': 
        return array('first' . ($match[2]-1), 'forename' . ($match[2]-1), 'initials' . ($match[2]-1));
      default: return array($match[1] . ($match[2]-1));
    }
  }
  switch ($par) {
    case 'title':       return prior_parameters('author', array_merge(array('author', 'authors', 'author1', 'first1', 'initials1'), $list) );
    case 'journal':       return prior_parameters('title', $list);
    case 'volume':       return prior_parameters('journal', $list);
    case 'issue': case 'number':       return prior_parameters('volume', $list);
    case 'page' : case 'pages':       return prior_parameters('issue', $list);

    case 'pmid':       return prior_parameters('doi', $list);
    case 'pmc':       return prior_parameters('pmid', $list);
    default: return $list;
  }
}

function equivalent_parameters($par) {
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

function str_remove_irrelevant_bits($str) {
  $str = trim($str);
  $str = preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $str);   // Convert [[X]] wikilinks into X
  $str = preg_replace(REGEXP_PIPED_WIKILINK, "$2", $str);   // Convert [[Y|X]] wikilinks into X
  $str = trim($str);
  $str = preg_replace("~^the\s+~i", "", $str);  // Ignore leading "the" so "New York Times" == "The New York Times"
  $str = str_replace(array('.', ',', ';', ':', '   ', '  '), ' ', $str); // punctuation and multiple spaces
  $str = trim($str);
  $str = str_ireplace(array('Proceedings', 'Proceeding', 'Symposium', 'Huffington ', 'the Journal of ', 'nytimes.com'   , '& '  , '(Clifton, N.J.)'),
                      array('Proc',        'Proc',       'Sym',       'Huff ',       'journal of ',     'New York Times', 'and ', ''), $str);
  $str = str_ireplace(array('<sub>', '<sup>', '<i>', '<b>', '</sub>', '</sup>', '</i>', '</b>'), '', $str);
  $str = straighten_quotes($str);
  $str = trim($str);
  return $str;
}

// See also titles_are_similar()
function str_equivalent($str1, $str2) {
  return 0 === strcasecmp(str_remove_irrelevant_bits($str1), str_remove_irrelevant_bits($str2));
}
  
function check_doi_for_jstor($doi, &$template) {
  if ($template->has('jstor')) return;
  $doi = trim($doi);
  if ($doi == '') return;
  if (strpos($doi, '10.2307') === 0) { // special case
    $doi = substr($doi, 8);
  }
  $test_url = "https://www.jstor.org/citation/ris/" . $doi;
  $ch = curl_init($test_url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  $ris = @curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($httpCode == 200 &&
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

function good_10_1093_doi($url) { // We assume dois are bad, unless on good list
  if(!preg_match('~10.1093/([^/]+)/~u', $url, $match)) return TRUE;
  $test = strtolower($match[1]);
  // March 2019 Good list
  if (in_array($test, GOOD_10_1093_DOIS)) return TRUE;
  return FALSE;
}

function bad_10_1093_doi($url) {
  return !good_10_1093_doi($url);
}
