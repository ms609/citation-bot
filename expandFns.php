<?php
/*
 * expandFns.php sets up most of the page expansion. A number of constants and variables
 * are set here. HTTP handing takes place using an instance of the Snoopy class. Most of
 * the page expansion depends on the classes in objects.php, particularly Template and Page.
 */

ini_set("user_agent", "Citation_bot; citations@tools.wmflabs.org");
define('HOME', dirname(__FILE__) . '/');

function quiet_echo($text, $alternate_text = '') {
  global $html_output;
  if ($html_output >= 0)
    echo $text;
  else
    echo $alternate_text;
}

define("editinterval", 10);
define("PIPE_PLACEHOLDER", '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
define("comment_placeholder", "### Citation bot : comment placeholder %s ###");
define("to_en_dash", "--?|\&mdash;|\xe2\x80\x94|\?\?\?"); // regexp for replacing to ndashes using mb_ereg_replace
define("en_dash", "\xe2\x80\x93"); // regexp for replacing to ndashes using mb_ereg_replace
define("wikiroot", "https://en.wikipedia.org/w/index.php?");
define("api", "https://en.wikipedia.org/w/api.php"); // wiki's API endpoint
define("bibcode_regexp", "~^(?:" . str_replace(".", "\.", implode("|", Array(
                    "http://(?:\w+.)?adsabs.harvard.edu",
                    "http://ads.ari.uni-heidelberg.de",
                    "http://ads.inasan.ru",
                    "http://ads.mao.kiev.ua",
                    "http://ads.astro.puc.cl",
                    "http://ads.on.br",
                    "http://ads.nao.ac.jp",
                    "http://ads.bao.ac.cn",
                    "http://ads.iucaa.ernet.in",
                    "http://ads.lipi.go.id",
                    "http://cdsads.u-strasbg.fr",
                    "http://esoads.eso.org",
                    "http://ukads.nottingham.ac.uk",
                    "http://www.ads.lipi.go.id",
                ))) . ")/.*(?:abs/|bibcode=|query\?|full/)([12]\d{3}[\w\d\.&]{15})~");
//define("doiRegexp", "(10\.\d{4}/([^\s;\"\?&<])*)(?=[\s;\"\?&]|</)");
#define("doiRegexp", "(10\.\d{4}(/|%2F)[^\s\"\?&]*)(?=[\s\"\?&]|</)"); //Note: if a DOI is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.

require_once(HOME . "credentials/doiBot.login");
# Snoopy's ini files should be modified so the host name is en.wikipedia.org.
require_once('Snoopy.class.php');
require_once("DOItools.php");
require_once("objects.php");
require_once("wikiFunctions.php");

//require_once(HOME . "credentials/mysql.login");
/* mysql.login is a php file containing:
  define('MYSQL_DBNAME', ...);
  define('MYSQL_SERVER', ...);
  define('MYSQL_PREFIX', ...);
  define('MYSQL_USERNAME', ...);
  define('MYSQL_PASSWORD', ...);
*/

require_once(HOME . "credentials/crossref.login");
/* crossref.login is a PHP file containing:
  <?php
  define('CROSSREFUSERNAME','martins@gmail.com');
  define('JSTORPASSWORD', ...);
  define('GLOBALPASSWORD', ...);
  define('JSTORUSERNAME', 'citation_bot');
  define('NYTUSERNAME', 'citation_bot');
*/

$crossRefId = CROSSREFUSERNAME;
$isbnKey = "268OHQMW";
$alphabet = array("", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
mb_internal_encoding('UTF-8'); // Avoid ??s

//Common replacements
global $doiIn, $doiOut, $pcDecode, $pcEncode, $dotDecode, $dotEncode;
$doiIn = array("[", "]", "<", ">", "&#60;!", "-&#62;", "%2F");
$doiOut = array("&#x5B;", "&#x5D;", "&#60;", "&#62;", "<!", "->", "/");

$pcDecode = array("[", "]", "<", ">");
$pcEncode = array("&#x5B;", "&#x5D;", "&#60;", "&#62;");

$spurious_whitespace= array(""); // regexp for replacing spurious custom whitespace

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

// Common mistakes that aren't picked up by the levenshtein approach
$common_mistakes = array
(
  "albumlink"       =>  "titlelink",
  "artist"          =>  "others",
  "authorurl"       =>  "authorlink",
  "co-author"       =>  "coauthor",
  "co-authors"      =>  "coauthors",
  "dio"             =>  "doi",
  "director"        =>  "others",
  "display-authors" =>  "displayauthors",
  "display_authors" =>  "displayauthors",
  "doi_brokendate"  =>  "doi-broken-date",
  "doi_inactivedate"=>  "doi-broken-date",
  "doi-inactive-date"   =>  "doi-broken-date",
  "ed"              =>  "editor",
  "ed2"             =>  "editor2",
  "ed3"             =>  "editor3",
  "editorlink1"     =>  "editor1-link",
  "editorlink2"     =>  "editor2-link",
  "editorlink3"     =>  "editor3-link",
  "editorlink4"     =>  "editor4-link",
  "editor1link"     =>  "editor1-link",
  "editor2link"     =>  "editor2-link",
  "editor3link"     =>  "editor3-link",
  "editor4link"     =>  "editor4-link",
  "editor-first1"   =>  "editor1-first",
  "editor-first2"   =>  "editor2-first",
  "editor-first3"   =>  "editor3-first",
  "editor-first4"   =>  "editor4-first",
  "editor-last1"    =>  "editor1-last",
  "editor-last2"    =>  "editor2-last",
  "editor-last3"    =>  "editor3-last",
  "editor-last4"    =>  "editor4-last",
  "editorn"         =>  "editor2",
  "editorn-link"    =>  "editor2-link",
  "editorn-last"    =>  "editor2-last",
  "editorn-first"   =>  "editor2-first",
  "firstn"          =>  "first2",
  "ibsn"            =>  "isbn",
  "ibsn2"           =>  "isbn",
  "lastn"           =>  "last2",
  "part"            =>  "issue",
  "no"              =>  "issue",
  "No"              =>  "issue",
  "No."             =>  "issue",
  "notestitle"      =>  "chapter",
  "nurl"            =>  "url",
  "origmonth"       =>  "month",
  "p"               =>  "page",
  "p."              =>  "page",
  "pmpmid"          =>  "pmid",
  "pp"              =>  "pages",
  "pp."             =>  "pages",
  "publisherid"     =>  "id",
  "titleyear"       =>  "origyear",
  "translator"      =>  "others",
  "translators"     =>  "others",
  "vol"             =>  "volume",
  "Vol"             =>  "volume",
  "Vol."            =>  "volume",
  "website"         =>  "url",
);

//Optimisation
#ob_start(); //Faster, but output is saved until page finshed.
ini_set("memory_limit", "256M");

$fastMode = isset($_REQUEST["fast"]) ? $_REQUEST["fast"] : false;
$slow_mode = isset($_REQUEST["slow"]) ? $_REQUEST["slow"] : false;
$user = isset($_REQUEST["user"]) ? $_REQUEST["user"] : null;
$bugFix = isset($_REQUEST["bugfix"]) ? $_REQUEST["bugfix"] : null;
if (isset($_REQUEST["crossrefonly"])) {
  $crossRefOnly = true;
} elseif (isset($_REQUEST["turbo"])) {
  $crossRefOnly = $_REQUEST["turbo"];
} else {
  $crossRefOnly = false;
}
$edit = isset($_REQUEST["edit"]) ? $_REQUEST["edit"] : null;

if ($edit || $_GET["doi"] || $_GET["pmid"])
  $ON = true;

$editSummaryStart = ($bugFix ? "Double-checking that a [[User talk:Citation bot|bug]] has been fixed. " : "Citations: ");

ob_end_flush();

quiet_echo("\n Establishing connection to Wikipedia servers with username " . USERNAME . "... ");
logIn(USERNAME, PASSWORD);
quiet_echo("\n Fetching parameter list ... ");
// Get a current list of parameters used in citations from WP
$page = $bot->fetch(api . "?action=query&prop=revisions&rvprop=content&titles=User:Citation_bot/parameters|Module:Citation/CS1/Whitelist&format=json");
$json = json_decode($bot->results, true);
$parameter_list = (explode("\n", $json["query"]["pages"][26899494]["revisions"][0]["*"]));
preg_match_all("~\['([^']+)'\] = true~", $json["query"]["pages"][39013723]["revisions"][0]["*"], $match);
foreach($match[1] as $parameter_name) {
  if (strpos($parameter_name, '#') !== FALSE) {
    for ($i = 1; $i < 100; $i++) {
      $replacement_name = str_replace('#', $i, $parameter_name);
      if (array_search($replacement_name, $parameter_list) === FALSE) {
        $parameter_list[] = $replacement_name;
      }
    }
  } else {
    if (array_search($parameter_name, $parameter_list) === FALSE) {
      $parameter_list[] = $parameter_name;
    }
  }
}

uasort($parameter_list, "ascii_sort");
quiet_echo("done.");

################ Functions ##############

function udbconnect($dbName = MYSQL_DBNAME, $server = MYSQL_SERVER) {
  // if the bot is trying to connect to the defunct toolserver
  if ($dbName == 'yarrow') {
    return ('\r\n # The maintainers have disabled database support.  This action will not be logged.');
  }

  // fix redundant error-reporting
  $errorlevel = ini_set('error_reporting','0');
  // connect
  $db = mysql_connect($server, MYSQL_USERNAME, MYSQL_PASSWORD) or die("\n!!! * Database server login failed.\n This is probably a temporary problem with the server and will hopefully be fixed soon.  The server returned: \"" . mysql_error() . "\"  \nError message generated by /res/mysql_connect.php\n");
  // select database
  if ($db && $server == "sql") {
     mysql_select_db(str_replace('-','_',MYSQL_PREFIX . $dbName)) or print "\nDatabase connection failed: " . mysql_error() . "";
  } else if ($db) {
     mysql_select_db($dbName) or die(mysql_error());
  } else {
    die ("\nNo DB selected!\n");
  }
  // restore error-reporting
  ini_set('error-reporting',$errorlevel);
  return ($db);
}

function countMainLinks($title) {
  // Counts the links to the mainpage
  global $bot;
  if (preg_match("/\w*:(.*)/", $title, $title))
    $title = $title[1]; //Gets {{PAGENAME}}
  $url = "https://en.wikipedia.org/w/api.php?action=query&bltitle=" . urlencode($title) . "&list=backlinks&bllimit=500&format=yaml";
  $bot->fetch($url);
  $page = $bot->results;
  if (preg_match("~\n\s*blcontinue~", $page))
    return 501;
  preg_match_all("~\n\s*pageid:~", $page, $matches);
  return count($matches[0]);
}

function logIn($username, $password) {
  global $bot; // Snoopy class loaded in DOItools.php
  // Set POST variables to retrieve a token
  $submit_vars["format"] = "json";
  $submit_vars["action"] = "login";
  $submit_vars["lgname"] = $username;
  $submit_vars["lgpassword"] = $password;
  // Submit POST variables and retrieve a token
  $bot->submit(api, $submit_vars);
  if (!$bot->results) {
    exit("\n Could not log in to Wikipedia servers.  Edits will not be committed.\n");
  }
  $first_response = json_decode($bot->results);
  $submit_vars["lgtoken"] = $first_response->login->token;
  // Resubmit with new request (which has token added to post vars)
  $bot->submit(api, $submit_vars);
  $login_result = json_decode($bot->results);
  if ($login_result->login->result == "Success") {
    quiet_echo("\n Using account " . htmlspecialchars($login_result->login->lgusername) . ".");
    // Add other cookies, which are necessary to remain logged in.
    $cookie_prefix = "enwiki";
    $bot->cookies[$cookie_prefix . "UserName"] = $login_result->login->lgusername;
    $bot->cookies[$cookie_prefix . "UserID"] = $login_result->login->lguserid;
    $bot->cookies[$cookie_prefix . "Token"] = $login_result->login->lgtoken;
    $bot->cookies[$cookie_prefix . "_session"] = $login_result->login->sessionid;
    return true;
  } else {
    exit("\n Could not log in to Wikipedia servers.  Edits will not be committed.\n");
    global $ON;
    $ON = false;
    return false;
  }
}

function inputValue($tag, $form) {
  //Gets the value of an input, if the input's in the right format.
  preg_match("~value=\"([^\"]*)\" name=\"$tag\"~", $form, $name);
  if ($name)
    return $name[1];
  preg_match("~name=\"$tag\" value=\"([^\"]*)\"~", $form, $name);
  if ($name)
    return $name[1];
  return false;
}

function format_title_text($title) {
  $title = sanitize_string($title)
  $title = capitalize_title($title, TRUE);
  $title = html_entity_decode($title, null, "UTF-8");
  $title = (mb_substr($title, -1) == ".")
            ? mb_substr($title, 0, -1)
            :(
              (mb_substr($title, -6) == "&nbsp;")
              ? mb_substr($title, 0, -6)
              : $title
            );
  $title = preg_replace('~[\*]$~', '', $title);
  $iIn = array("<i>","</i>", '<title>', '</title>',
              "From the Cover: ", "|");
  $iOut = array("''","''",'','',
                "", '{{!}}');
  $in = array("&lt;", "&gt;");
  $out = array("<",		">"			);
  return(str_ireplace($iIn, $iOut, str_ireplace($in, $out, capitalize_title($title)))); // order IS important!
}

function parameters_from_citation($c) {
  // Comments
  global $comments, $comment_placeholders;
  $i = 0;
  while(preg_match("~<!--.*?-->~", $c, $match)) {
    $comments[] = $match[0];
    $comment_placeholders[] = sprintf(comment_placeholder, $i);
    $c = str_replace($match[0], $comment_placeholders[$i++], $c);
  }
  while (preg_match("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", $c)) {
    $c = preg_replace("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", "$1" . PIPE_PLACEHOLDER, $c);
  }
  // Split citation into parameters
  $parts = preg_split("~([\n\s]*\|[\n\s]*)([\w\d-_]*)(\s*= *)~", $c, -1, PREG_SPLIT_DELIM_CAPTURE);
  $partsLimit = count($parts);
  if (strpos($parts[0], "|") > 0
          && strpos($parts[0], "[[") === FALSE
          && strpos($parts[0], "{{") === FALSE
  ) {
    $p["unused_data"][0] = substr($parts[0], strpos($parts[0], "|") + 1);
  }
  for ($partsI = 1; $partsI <= $partsLimit; $partsI += 4) {
    $value = $parts[$partsI + 3];
    $pipePos = strpos($value, "|");
    if ($pipePos > 0 && strpos($value, "[[") === false & strpos($value, "{{") === FALSE) {
      // There are two "parameters" on one line.  One must be missing an equals.
      switch (strtolower($parts[$partsI + 1])) {
        case 'title': 
          $value = str_replace('|', '&#124;', $value);
          break;
        case 'url':
          $value = str_replace('|', '%7C', $value);
          break;
        default:
        $p["unused_data"][0] .= " " . substr($value, $pipePos);
        $value = substr($value, 0, $pipePos);
      }
    }
    // Load each line into $p[param][0123]
    $weight += 32;
    $p[strtolower($parts[$partsI + 1])] = Array($value, $parts[$partsI], $parts[$partsI + 2], "weight" => $weight); // Param = value, pipe, equals
  }
  return $p;
}

function reassemble_citation($p, $sort = false) {
  global $comments, $comment_placeholders, $pStart, $modifications;
  // Load an exemplar pipe and equals symbol to deduce the parameter spacing, so that new parameters match the existing format
  foreach ($p as $oP) {
    $pipe = $oP[1] ? $oP[1] : null;
    $equals = $oP[2] ? $oP[2] : null;
    if ($pipe)
      break;
  }
  if (!$pipe) $pipe = "\n | ";
  if (!$equals) $equals = " = ";
  if ($sort) {
    echo "\n (sorting parameters)";
    uasort($p, "bubble_p");
  }

  foreach ($p as $param => $v) {
    $val = trim(str_replace($comment_placeholders, $comments, $v[0]));
    if ($param == 'unused_data') {
      $cText .= ($v[1] ? $v[1] : $pipe) . $val;
    } elseif ($param) {
      $this_equals = ($v[2] ? $v[2] : $equals);
      if (trim($v[0]) && preg_match("~[\r\n]~", $this_equals)) {
        $this_equals = preg_replace("~[\r\n]+\s*$~", "", $this_equals);
        $nline = "\r\n";
      } else {
        $nline = null;
      }
      $cText .= ( $v[1] ? $v[1] : $pipe)
              . $param
              . $this_equals
              . str_replace(array(PIPE_PLACEHOLDER, "\r", "\n"), array("|", "", " "), $val)
              . $nline;
    }
    if (is($param)) {
      $pEnd[$param] = $v[0];
    }
  }
  if ($pEnd) {
    foreach ($pEnd as $param => $value) {
      if (!$pStart[$param]) {
        $modifications["additions"][$param] = true;
      } elseif ($pStart[$param] != $value) {
        $modifications["changes"][$param] = true;
      }
    }
  }
  return $cText;
}

function loadParam($param, $value, $equals, $pipe, $weight) {
  global $p;
  $param = strtolower(trim(str_replace("DUPLICATE DATA:", "", $param)));
  if ($param == "unused_data") {
    $value = trim(str_replace("DUPLICATE DATA:", "", $value));
  }
  if (is($param)) {
    if (substr($param, strlen($param) - 1) > 0 && trim($value) != trim($p[$param][0])) {
      // Add one to last1 to create last2
      $param = substr($param, 0, strlen($param) - 1) . (substr($param, strlen($param) - 1) + 1);
    } else {
      // Parameter already exists
      if ($param != "unused_data" && $p[$param][0] != $value) {
        // If they have different values, best keep them; if not: discard the exact duplicate!
        $param = "DUPLICATE DATA: $param";
      }
    }
  }
  $p[$param] = Array($value, $equals, $pipe, "weight" => ($weight + 3) / 4 * 10); // weight will be 10, 20, 30, 40 ...
}

// $comments should be an array, with the original comment content.
// $placeholder will be prepended to the comment number in the sprintf to comment_placeholder's %s.
function replace_comments($text, $comments, $placeholder = "") {
  foreach ($comments as $i => $comment) {
    $text = str_replace(sprintf(comment_placeholder, $placeholder . $i), $comment, $text);
  }
  return $text;
}

function trim_identifier($id) {
  $cruft = "[\.,;:><\s]*";
  preg_match("~^$cruft(?:d?o?i?:)?\s*(.*?)$cruft$~", $id, $match);
  return $match[1];
}

function remove_accents($input) {
  $search = explode(",", "ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u");
  $replace = explode(",", "c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
  return str_replace($search, $replace, $input);
}

function under_two_authors($text) {
  return !(strpos($text, ';') !== FALSE  //if there is a semicolon
          || substr_count($text, ',') > 1  //if there is more than one comma
          || substr_count($text, ',') < substr_count(trim($text), ' ')  //if the number of commas is less than the number of spaces in the trimmed string
          );
}

// returns the surname of the authors.
function authorify($author) {
  $author = preg_replace("~[^\s\w]|\b\w\b|[\d\-]|\band\s+~", "", normalize_special_characters(html_entity_decode(urldecode($author), ENT_COMPAT, "UTF-8")));
  $author = preg_match("~[a-z]~", $author) ? preg_replace("~\b[A-Z]+\b~", "", $author) : strtolower($author);
  return $author;
}

function sanitize_string($str) {
  // ought only be applied to newly-found data.
  $dirty = array ('[', ']', '|', '{', '}');
  $clean = array ('&#91;', '&#93;', '&#124;', '&#123;', '&#125;');
  return trim(str_replace($dirty, $clean, preg_replace('~[;.,]+$~', '', $str)));
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

// Function from http://stackoverflow.com/questions/1890854
// Modified to expect utf8-encoded string
function normalize_special_characters($str) {
  $str = utf8_decode($str);
  # Quotes cleanup
  $str = ereg_replace(chr(ord("`")), "'", $str);        # `
  $str = ereg_replace(chr(ord("´")), "'", $str);        # ´
  $str = ereg_replace(chr(ord("„")), ",", $str);        # „
  $str = ereg_replace(chr(ord("`")), "'", $str);        # `
  $str = ereg_replace(chr(ord("´")), "'", $str);        # ´
  $str = ereg_replace(chr(ord("“")), "\"", $str);        # “
  $str = ereg_replace(chr(ord("”")), "\"", $str);        # ”
  $str = ereg_replace(chr(ord("´")), "'", $str);        # ´

  $unwanted_array = array('Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
      'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
      'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
      'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
      'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y');
  $str = strtr($str, $unwanted_array);

# Bullets, dashes, and trademarks
  $str = ereg_replace(chr(149), "&#8226;", $str);    # bullet •
  $str = ereg_replace(chr(150), "&ndash;", $str);    # en dash
  $str = ereg_replace(chr(151), "&mdash;", $str);    # em dash
  $str = ereg_replace(chr(153), "&#8482;", $str);    # trademark
  $str = ereg_replace(chr(169), "&copy;", $str);    # copyright mark
  $str = ereg_replace(chr(174), "&reg;", $str);        # registration mark

  return utf8_encode($str);
}

function ascii_sort($val_1, $val_2) {
  $return = 0;
  $len_1 = strlen($val_1);
  $len_2 = strlen($val_2);

  if ($len_1 > $len_2) {
    $return = -1;
  } else if ($len_1 < $len_2) {
    $return = 1;
  }
  return $return;
}

?>
