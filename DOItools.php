<?php

/* junior_test - tests a name for a Junior appellation
 *  Input: $name - the name to be tested
 * Output: array ($name without Jr, if $name ends in Jr, Jr)
 */
function junior_test($name) {
  $junior = (substr($name, -3) == " Jr")?" Jr":FALSE;
  if ($junior) {
    $name = substr($name, 0, -3);
  } else {
    $junior = (substr($name, -4) == " Jr.")?" Jr.":FALSE;
    if ($junior) {
      $name = substr($name, 0, -4);
    }
  }
  if (substr($name, -1) == ",") {
    $name = substr($name, 0, -1);
  }
  return array($name, $junior);
}

function de_wikify($string){
  return str_replace(Array("[", "]", "'''", "''", "&"), Array("", "", "'", "'", ""), preg_replace(Array("~<[^>]*>~", "~\&[\w\d]{2,7};~", "~\[\[[^\|\]]*\|([^\]]*)\]\]~"), Array("", "", "$1"),  $string));
}

function truncate_publisher($p){
  return preg_replace("~\s+(group|inc|ltd|publishing)\.?\s*$~i", "", $p);
}

function format_surname($surname) {
  if ($surname == '-') return '';
  if (preg_match('~^\S\.?$~u', $surname)) return mb_strtoupper($surname); // Just a single initial, with or without period
  $surname = mb_convert_case(trim(mb_ereg_replace("-", " - ", $surname)), MB_CASE_LOWER);
  if (mb_substr($surname, 0, 2) == "o'") {
        return "O'" . format_surname_2(mb_substr($surname, 2));
  } elseif (mb_substr($surname, 0, 2) == "mc") {
        return "Mc" . format_surname_2(mb_substr($surname, 2));
  } elseif (mb_substr($surname, 0, 3) == "mac" && strlen($surname) > 5 && !mb_strpos($surname, "-") && mb_substr($surname, 3, 1) != "h") {
        return "Mac" . format_surname_2(mb_substr($surname, 3));
  } elseif (mb_substr($surname, 0, 1) == "&") {
        return "&" . format_surname_2(mb_substr($surname, 1));
  } else {
        return format_surname_2($surname); // Case of surname
  }
}
function format_surname_2($surname) {
  $ret = preg_replace_callback("~(\p{L})(\p{L}+)~u", 
        function($matches) {
                return mb_strtoupper($matches[1]) . mb_strtolower($matches[2]);
        },
    mb_ereg_replace(" - ", "-", $surname));
  $ret = str_ireplace(array('Von ', 'Und ', 'De La '), array('von ', 'und ', 'de la '), $ret);
  $ret = preg_replace_callback('~;\w~', function($matches) {return strtolower($matches[0]);}, $ret);
  return $ret;
}

function format_forename($forename){
  if ($forename == '-') return '';
  return str_replace(array(" ."), "", trim(preg_replace_callback("~(\p{L})(\p{L}{3,})~u",  function(
            $matches) {
            return mb_strtoupper($matches[1]) . mb_strtolower($matches[2]);}
         , $forename)));
}

/* format_initials
 * Returns a string of initals, formatted for Cite Doi output
 *
 * $str: A series of initials, in any format.  NOTE! Do not pass a forename here!
 *
 */
function format_initials($str) {
  $str = trim($str);
        if ($str == "") return FALSE;
        $end = (substr($str, strlen($str)-1) == ";") ? ";" : '';
        preg_match_all("~\w~", $str, $match);
        return mb_strtoupper(implode(".",$match[0]) . ".") . $end;
}

function is_initials($str){
        $str = trim($str);
        if (!$str) return FALSE;
        if (strlen(str_replace(array("-", ".", ";"), "", $str)) >3) return FALSE;
        if (strlen(str_replace(array("-", ".", ";"), "", $str)) ==1) return TRUE;
        if (mb_strtoupper($str) != $str) return FALSE;
        return TRUE;
}

/*
 * author_is_human
 * Runs some tests to see if the full name of a single author is unlikely to be the name of a person.
 */
function author_is_human($author) {
  $author = trim($author);
  $chars = count_chars($author);
  if ($chars[ord(":")] > 0 || $chars[ord(" ")] > 3 || strlen($author) > 33
    || substr(strtolower($author), 0, 4) === "the " 
    || (str_ireplace(NON_HUMAN_AUTHORS, '', $author) != $author)  // This is the use a replace to see if a substring is present trick
    || preg_match("~[A-Z]{3}~", $author)
    || substr(strtolower($author),-4) === " inc"
    || substr(strtolower($author),-5) === " inc."
    || substr_count($author, ' ') > 3 // Even if human, hard to format
  ) {
    return FALSE;
  }
  return TRUE;
}

// Returns the author's name formatted as Surname, F.I.
function format_author($author){
  
  // Requires an author who is formatted as SURNAME, FORENAME or SURNAME FORENAME or FORENAME SURNAME. Substitute initials for forenames if nec.
  $surname = '';
  // Google and Zotero sometimes have these
  $author = preg_replace("~ ?\((?i)sir(?-i)\.?\)~", "", html_entity_decode($author, ENT_COMPAT | ENT_HTML401, 'UTF-8'));

  $ends_with_period = (substr(trim($author), -1) === ".");
  
  $author = preg_replace("~(^[;,.\s]+|[;,.\s]+$)~", "", trim($author)); //Housekeeping
  $author = preg_replace("~^[aA]nd ~", "", trim($author)); // Just in case it has been split from a Smith; Jones; and Western
  if ($author == "") {
      return FALSE;
  }

  $auth = explode(",", $author);
  if (isset($auth[1])) {
    /* Possibilities:
    Smith, A. B.
    */
    $surname = $auth[0];
    $fore = $auth[1];
  }
  //Otherwise we've got no handy comma to separate; we'll have to use spaces and periods.
  else {
    $auth = explode(".", $author);
    if (isset($auth[1])){
      /* Possibilities are:
      M.A. Smith
      Smith M.A.
      Smith MA.
      Martin A. Smith
      MA Smith.
      Martin Smith.
      */
      $countAuth = count($auth);
      if ($ends_with_period) {
        $i = array();
        // it ends in a .
        if (is_initials($auth[$countAuth-1])) {
          // it's Conway Morris S.C.
          foreach (explode(" ", $auth[0]) as $bit){
            if (is_initials($bit)) $i[] = format_initials($bit); else $surname .= "$bit ";
          }
          unset($auth[0]);
          foreach ($auth as $bit){
            if (is_initials($bit)) {
              $i[] = format_initials($bit) . '.';
            } else {
              $i[] = $bit;
            }
          }
        } else {
          foreach ($auth as $A) {
            if (is_initials($A)) {
                $i[] = format_initials($A) . '.';
            } else {
                $i[] = $A;
            }
          }
        }
        $fore = mb_strtoupper(implode(" ", $i));
      } else {
        // it ends with the surname
        $surname = $auth[$countAuth-1];
        unset($auth[$countAuth-1]);
        $fore = implode(".", $auth);
      }
    } else {
      // We have no punctuation! Let's delimit with spaces.
      $chunks = array_reverse(explode(" ", $author));
      $i = array();
      foreach ($chunks as $chunk){
        if (!$surname && !is_initials($chunk)) $surname = $chunk;
        else array_unshift($i, is_initials($chunk)?format_initials($chunk):$chunk);
      }
      $fore = implode(" ", $i);
    }
  }
  // Special cases when code cannot fully determine things, or if the name is only Smith
  if (trim($surname) == '') { // get this with A. B. C.
    $full_name = format_forename($fore);
  } elseif (trim($fore) == '') {  // Get this with just Smith
    $full_name = format_surname($surname);
  } else {
    $full_name = format_surname($surname) . ", " . format_forename($fore);
  }
  $full_name = str_replace("..", ".", $full_name);  // Sometimes add period after period
  $full_name = str_replace(".", ". ", $full_name);  // Add spaces after all periods
  $full_name = str_replace(["   ", "  "], [" ", " "], $full_name); // Remove extra spaces
  return trim($full_name);
}

function format_multiple_authors($authors, $returnAsArray = FALSE){
  $authors = html_entity_decode($authors, ENT_COMPAT | ENT_HTML401, "UTF-8");

  $return = array();
  ## Split the citation into an author by author account
  $authors = preg_replace(array("~\band\b~i", "~[\d\+\*]+~"), ";", $authors); //Remove "and" and affiliation symbols

  $authors = str_replace(array("&nbsp;", "(", ")"), array(" "), $authors); //Remove spaces and weird puntcuation
  $authors = str_replace(array(".,", "&", "  "), ";", $authors); //Remove "and"
  if (preg_match("~[,;]$~", trim($authors))) $authors = substr(trim($authors), 0, strlen(trim($authors))-1); // remove trailing punctuation

  $authors = trim($authors);
  if ($authors == "") {
    return FALSE;
  }

  $authors = explode(";", $authors);
  #dbg(array("IN"=>$authors));
  $savedChunk = '';
  if (isset($authors[1])) {
    foreach ($authors as $A){
      if (trim($A) != "") $return[] = format_author($A);
    }
  } else {
    //Use commas as delimiters
    $chunks = explode(",", $authors[0]);
    foreach ($chunks as $chunk){
      $chunk = trim($chunk);
      if ($chunk == '') continue; // Odd things with extra commas
      $bits = explode(" ", $chunk);
      $bitts = array();
      foreach ($bits as $bit){
        if ($bit) $bitts[] = $bit;
      }
      $bits = $bitts; unset($bitts);
      #dbg($bits, '$BITS');
      if ((isset($bits[1]) && $bits[1]) || $savedChunk) {
        $return[] = format_author($savedChunk .  ($savedChunk?", ":"") . $chunk);
        $savedChunk = '';
      } else {
        $savedChunk = $chunk;// could be first author, or an author with no initials, or a surname with initials to follow.
      }
    }
  }
  if ($savedChunk) $return[0] = $bits[0];
  $return = implode("; ", $return);
  $frags = explode(" ", $return);
  $return = array();
  foreach ($frags as $frag){
    $return[] = is_initials($frag)?format_initials($frag):$frag;
  }
  $returnString = preg_replace("~;$~", "", trim(implode(" ", $return)));
  if ($returnAsArray){
    $authors = explode ( "; ", $returnString);
    return $authors;
  } else {
    return $returnString;
  }
}

function straighten_quotes($str) {
  $str = preg_replace('~&#821[679];|&#39;|&#x201[89];|[\x{FF07}\x{2018}-\x{201B}`]|&[rl]s?[b]?quo;~u', "'", $str);
  if((mb_strpos($str, '&rsaquo;') !== FALSE && mb_strpos($str, '&[lsaquo;')  !== FALSE) ||
     (mb_strpos($str, '\x{2039}') !== FALSE && mb_strpos($str, '\x{203A}') !== FALSE) ||
     (mb_strpos($str, '‹')        !== FALSE && mb_strpos($str, '›')        !== FALSE)) { // Only replace single angle quotes if some of both
     $str = preg_replace('~&[lr]saquo;|[\x{2039}\x{203A}]|[‹›]~u', "'", $str);           // Websites tiles: Jobs ›› Iowa ›› Cows ›› Ames
  }	
  $str = preg_replace('~&#822[013];|[\x{201C}-\x{201F}]|&[rlb][d]?quo;~u', '"', $str);
  if((mb_strpos($str, '&raquo;')  !== FALSE && mb_strpos($str, '&laquo;')  !== FALSE) ||
     (mb_strpos($str, '\x{00AB}') !== FALSE && mb_strpos($str, '\x{00AB}') !== FALSE) ||
     (mb_strpos($str, '«')        !== FALSE && mb_strpos($str, '»')        !== FALSE)) { // Only replace double angle quotes if some of both
     $str = preg_replace('~&[lr]aquo;|[\x{00AB}\x{00BB}]|[«»]~u', '"', $str);            // Websites tiles: Jobs » Iowa » Cows » Ames
  }
  return $str;
}

function can_safely_modify_dashes($value) {
   return((stripos($value, "http") === FALSE)
       && (strpos($value, "[//") === FALSE)
       && (stripos($value, 'CITATION_BOT_PLACEHOLDER_COMMENT') === FALSE)
       && (preg_match('~^[a-zA-Z]+[0-9]*.[0-9]+$~u',$value) !== 1)); // A-3, A3-5 etc.  Use "." for generic dash
}

function titles_are_similar($title1, $title2) {
  return !titles_are_dissimilar($title1, $title2);
}

function titles_are_dissimilar($inTitle, $dbTitle) {
        // Reduce punctuation
        $inTitle = straighten_quotes(str_replace(array(" ", "\n", "\r", "-", "—"), "", mb_strtolower((string) $inTitle)));
        $dbTitle = straighten_quotes(str_replace(array(" ", "\n", "\r", "-", "—"), "", mb_strtolower((string) $dbTitle)));
        // Strip trailing periods
        $inTitle = trim(rtrim($inTitle, '.'));
        $dbTitle = trim(rtrim($dbTitle, '.'));
        // Strip trailing (Third Edition)
        $inTitle = preg_replace('~\([^\s\(\)]+ Edition\)^~iu', '', $inTitle);
        $dbTitle = preg_replace('~\([^\s\(\)]+ Edition\)^~iu', '', $dbTitle);
        // Strip trailing Online
        $inTitle = preg_replace('~ Online^~iu', '', $inTitle);
        $dbTitle = preg_replace('~ Online^~iu', '', $dbTitle);
        return ((strlen($inTitle) > 254 || strlen($dbTitle) > 254)
              ? (strlen($inTitle) != strlen($dbTitle)
                || similar_text($inTitle, $dbTitle) / strlen($inTitle) < 0.98)
              : levenshtein($inTitle, $dbTitle) > 3
        );
}


function tidy_date($string) {
  $string=trim($string);
  if (stripos($string, 'Invalid') !== FALSE) return '';
  if (!preg_match('~\d{2}~', $string)) return ''; // If there are not two numbers next to each other, reject
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
  $str = str_ireplace(array('Proceedings', 'Proceeding', 'Symposium', 'Huffington ', 'the Journal of ', 'nytimes.com'   ),
                      array('Proc',        'Proc',       'Sym',       'Huff ',       'journal of ',     'New York Times'), $str);
  return $str;
}

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
      strpos($ris, 'Provider') !== FALSE) {
      $template->add_if_new('jstor', $doi);
  } elseif ($pos = strpos($doi, '?')) {
      $doi = substr($doi, 0, $pos);
      check_doi_for_jstor($doi, $template);
  }      
}

function check_10_1093_doi($url) { // We assume dois are bad, unless on good list
  if(!preg_match('~10.1093/([^/]+)/~u', $url, $match)) return TRUE;
  $test = strtolower($match[1]);
  // March 2019 Good list
  if (in_array($test, GOOD_10_1093_DOIS)) return TRUE;
  return FALSE;
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
  $new_case = mb_substr(str_replace(UC_SMALL_WORDS, LC_SMALL_WORDS, " " . $new_case . " "), 1, -1);
  
  if ($caps_after_punctuation || (substr_count($in, '.') / strlen($in)) > .07) {
    // When there are lots of periods, then they probably mark abbrev.s, not sentence ends
    // We should therefore capitalize after each punctuation character.
    $new_case = preg_replace_callback("~[?.:!/]\s+[a-z]~u" /* Capitalise after punctuation */,
      function ($matches) {return mb_strtoupper($matches[0]);},
      $new_case);
    $new_case = preg_replace_callback("~/[a-z]~u" /* Capitalise after slash without space */,
      function ($matches) {return mb_strtoupper($matches[0]);},
      $new_case);
    // But not "Ann. Of...." which seems to be common in journal titles
    $new_case = str_replace("Ann. Of ", "Ann. of ", $new_case);
  }
  
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
  
  return $new_case;
}

function mb_ucfirst($string)
{
    return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1, NULL);
}


function sanitize_doi($doi) {
  $doi = preg_replace('~^https?://d?x?\.?doi\.org/~i', '', $doi); // Strip URL part if present
  $doi = str_replace("+" , "%2B", $doi); // plus signs are valid DOI characters, but in URLs are "spaces"
  $doi = str_replace(HTML_ENCODE_DOI, HTML_DECODE_DOI, trim(urldecode($doi)));
  $extension = substr($doi, strrpos($doi, '.'));
  if (in_array(strtolower($extension), array('.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml'))) {
      $doi = substr($doi, 0, (strrpos($doi, $extension)));
  }
  $extension = substr($doi, strrpos($doi, '/'));
  if (in_array(strtolower($extension), array('/abstract', '/full', '/pdf', '/epdf', '/asset/', '/summary'))) {
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
          "~^(.*?)(/abstract|/e?pdf|/full|</span>|[\s\|\"\?]|</).*+$~",
          $doi, $new_match)
        ) {
      $doi = $new_match[1];
    }
    $doi_candidate = sanitize_doi($doi);
    while (preg_match(REGEXP_DOI, $doi_candidate) && !doi_active($doi_candidate)) {
      $last_delimiter = 0;
      foreach (array('/', '.', '#', '?') as $delimiter) {
        $delimiter_position = strrpos($doi_candidate, $delimiter);
        $last_delimiter = ($delimiter_position > $last_delimiter) ? $delimiter_position : $last_delimiter;
      }
      $doi_candidate = substr($doi_candidate, 0, $last_delimiter);
    }
    if (doi_active($doi_candidate)) $doi = $doi_candidate;
    return array($match[0], sanitize_doi($doi));
  }
  return NULL;
}
