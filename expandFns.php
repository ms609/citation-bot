<?php
/*
 * expandFns.php sets up most of the page expansion. 
 * Most of the page expansion depends on the classes in objects.php, 
 * particularly Template and Page.
*/

ini_set("user_agent", "Citation_bot; citations@tools.wmflabs.org");
include_once("./vendor/autoload.php");

if (!defined("HTML_OUTPUT") || getenv('TRAVIS')) {  // Fail safe code
  define("HTML_OUTPUT", FALSE);
}
if (!defined("FLUSHING_OKAY")) {  // Default when not gadget API
  define("FLUSHING_OKAY", TRUE);
}
require_once("constants.php");
require_once("DOItools.php");
require_once("Page.php");
require_once("Template.php");
require_once("Parameter.php");
require_once("Comment.php");
require_once("wikiFunctions.php");
require_once("user_messages.php");

$api_files = glob('api_handlers/*.php');
foreach ($api_files as $file) {
    require_once($file);
}

const CROSSREFUSERNAME = 'martins@gmail.com';
// Use putenv to set PHP_ADSABSAPIKEY, PHP_GOOGLE_KEY and PHP_BOTUSERNAME environment variables

mb_internal_encoding('UTF-8'); // Avoid ??s

//Optimisation
ob_implicit_flush();
if (!getenv('TRAVIS')) {
    ob_start();
}
ini_set("memory_limit", "256M");

define("FAST_MODE", isset($_REQUEST["fast"]) ? $_REQUEST["fast"] : FALSE);
if (!isset($SLOW_MODE)) $SLOW_MODE = isset($_REQUEST["slow"]) ? $_REQUEST["slow"] : FALSE;

if (isset($_REQUEST["crossrefonly"])) {
  $crossRefOnly = TRUE;
} elseif (isset($_REQUEST["turbo"])) {
  $crossRefOnly = $_REQUEST["turbo"];
} else {
  $crossRefOnly = FALSE;
}
$edit = isset($_REQUEST["edit"]) ? $_REQUEST["edit"] : NULL;

if ($edit || isset($_GET["doi"]) || isset($_GET["pmid"])) {
  $ON = TRUE;
}

################ Functions ##############
/**
 * @codeCoverageIgnore
 */
function udbconnect($dbName = MYSQL_DBNAME, $server = MYSQL_SERVER) {
  // if the bot is trying to connect to the defunct toolserver
  if ($dbName == 'yarrow') {
    exit ('\r\n # The maintainers have disabled yarrow database support.  This action will not be logged.');
  }

  // fix redundant error-reporting
  $errorlevel = ini_set('error_reporting','0');
  // connect
  $db = mysql_connect($server, MYSQL_USERNAME, MYSQL_PASSWORD);
  if (!$db) {
    exit("\n!!! * Database server login failed.\n This is probably a temporary problem with the server and will hopefully be fixed soon.  The server returned: \"" . mysql_error() . "\"  \nError message generated by /res/mysql_connect.php\n");
  } elseif ($server == "sql") { // select database
     if (!mysql_select_db(str_replace('-','_',MYSQL_PREFIX . $dbName))) exit("\nDatabase connection failed: " . mysql_error() . "");
  } else {
     if(!mysql_select_db($dbName)) exit(mysql_error());
  }
  // restore error-reporting
  ini_set('error-reporting',$errorlevel);
  return ($db);
}

function sanitize_doi($doi) {
  $doi = str_replace(HTML_ENCODE_DOI, HTML_DECODE_DOI, trim(urldecode($doi)));
  $extension = substr($doi, strrpos($doi, '.'));
  if (in_array(strtolower($extension), array('.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml'))) {
      $doi = substr($doi, 0, (strrpos($doi, $extension)));
  }
  $extension = substr($doi, strrpos($doi, '/'));
  if (in_array(strtolower($extension), array('/abstract', '/full', '/pdf', '/epdf'))) {
      $doi = substr($doi, 0, (strrpos($doi, $extension)));
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
      foreach (array('/', '.', '#') as $delimiter) {
        $delimiter_position = strrpos($doi_candidate, '/');
        $last_delimiter = ($delimiter_position > $last_delimiter) ? $delimiter_position : $last_delimiter;
      }
      $doi_candidate = substr($doi_candidate, 0, $last_delimiter);
    }
    if (doi_active($doi_candidate)) $doi = $doi_candidate;
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
  $title = html_entity_decode($title, NULL, "UTF-8");
  $title = preg_replace("/\s+/"," ", $title);  // Remove all white spaces before
  $title = (mb_substr($title, -1) == ".")
            ? mb_substr($title, 0, -1)
            :(
              (mb_substr($title, -6) == "&nbsp;")
              ? mb_substr($title, 0, -6)
              : $title
            );
  $title = preg_replace('~[\*]$~', '', $title);
  $title = title_capitalization($title, TRUE);
  
  $originalTags = array("<i>","</i>", '<title>', '</title>',"From the Cover: ");
  $wikiTags = array("''","''",'','',"");
  $htmlBraces  = array("&lt;", "&gt;");
  $angleBraces = array("<", ">");
  $title = sanitize_string(// order of functions here IS important!
             str_ireplace($originalTags, $wikiTags, 
               str_ireplace($htmlBraces, $angleBraces, $title)
             )
           );
  
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
  $new_case = mb_substr(str_replace(UC_SMALL_WORDS, LC_SMALL_WORDS, " " . $new_case . " "), 1, -1);
  
  if ($caps_after_punctuation || (substr_count($in, '.') / strlen($in)) > .07) {
    // When there are lots of periods, then they probably mark abbrev.s, not sentence ends
    // We should therefore capitalize after each punctuation character.
    $new_case = preg_replace_callback("~[?.:!]\s+[a-z]~u" /* Capitalise after punctuation */,
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

  // Catch some specific epithets, which should be lowercase
  $new_case = preg_replace_callback(
    "~(?:'')?(?P<taxon>\p{L}+\s+\p{L}+)(?:'')?\s+(?P<nova>(?:(?:gen\.? no?v?|sp\.? no?v?|no?v?\.? sp|no?v?\.? gen)\b[\.,\s]*)+)~ui" /* Species names to lowercase */,
    function($matches) {return "''" . ucfirst(strtolower($matches['taxon'])) . "'' " . strtolower($matches["nova"]);},
    $new_case);
  
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

function tag($long = FALSE) {
  return FALSE; // I suggest that this function is no longer useful in the Travis era
  // If it's not been missed by 2018-10-01, I suggest that we delete it and all calls thereto.
  
  $dbg = array_reverse(debug_backtrace());
  array_pop($dbg);
  array_shift($dbg);
  $output = '';
  foreach ($dbg as $d) {
    if ($long) {
      $output = $output . '> ' . $d['function'];
    } else {
      $output = '> ' . substr(preg_replace('~_(\w)~', strtoupper("$1"), $d['function']), -7);
    }
  }
  if (getenv('TRAVIS')) {
     echo ' [..' . $output . ']';
  } else {
     echo ' [..' . htmlspecialchars($output) . ']';
  } 
}

function sanitize_string($str) {
  // ought only be applied to newly-found data.
  if (trim($str) == 'Science (New York, N.Y.)') return 'Science';
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
  if (is_numeric($string) && is_int(1*$string)) {
    $string = intval($string);
    if ($string < -2000 || $string > date("Y") + 10) return ''; // A number that is not a year; probably garbage 
    if ($string > -2 && $string < 2) return ''; // reject -1,0,1
    return $string; // year
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
  if (preg_match('~^(.*?\d{4}\-\d?\d(?:\-?\d\d?))\S*~', $string, $matches)) return $matches[1];
  if (preg_match(  '~\s(\d{4}\-\d?\d(?:\-?\d\d?))$~', $string, $matches)) return $matches[1];
  if (preg_match('~\s(\d{4})$~', $string, $matches)) return $matches[1];
  return $string;
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

function str_almost_the_same($str1, $str2) {  // For comparing strings with forgiveness
  $str1 = mb_strtolower($str1); // Case-insensitive
  $str2 = mb_strtolower($str2);
  $str1 = str_replace(["[", "]"], ["", ""], $str1); // Ignore wiki-links
  $str2 = str_replace(["[", "]"], ["", ""], $str2);
  $str1 = trim($str1);  // Remove spaces on ends
  $str2 = trim($str2);
  $str1 = preg_replace("~^the\s+~", "", $str1);  // Ignore leading "the" so "New York Times" == "The New York Times"
  $str2 = preg_replace("~^the\s+~", "", $str2);
  return ($str1 === $str2);
}
