<?

// $Id$

session_start();
ini_set("user_agent", "Citation_bot; verisimilus@toolserver.org");

function includeIfNew($file) {
  // include missing files
  $alreadyIn = get_included_files();
  foreach ($alreadyIn as $include) {
    if (strstr($include, $file))
      return false;
  }
  if ($GLOBALS["linkto2"])
    echo "\n// including $file";
  require_once($file . $GLOBALS["linkto2"] . ".php");
  return true;
}

function quiet_echo($text) {
  global $html_output;
  if ($html_output >= 0)
    echo $text;
}

quiet_echo(" \n Getting login details ... ");
require_once("/home/verisimilus/public_html/Bot/DOI_bot/doiBot$linkto2.login");
quiet_echo(" done.");
# Snoopy should be set so the host name is en.wikipedia.org.
includeIfNew('Snoopy.class');
includeIfNew("wikiFunctions");
includeIfNew("DOItools");
require_once("expand.php");
#includeIfNew("expand_it");
if (!$abort_mysql_connection) {
  quiet_echo("\n Initializing MYSQL database ... ");
  require_once("/home/verisimilus/public_html/res/mysql_connect.php");
  //$db = udbconnect("yarrow");
  quiet_echo(" loaded connect script.  Will connect when necessary.");
}
if (!true && !myIP()) {
  echo "Sorry, the Citation bot is temporarily unavilable while bugs are fixed.  Please try back later.";
  exit;
}

quiet_echo("\n Initializing ...  ");
require_once("/home/verisimilus/public_html/crossref.login");
quiet_echo("...");
$crossRefId = CROSSREFUSERNAME;
$isbnKey = "268OHQMW";
$bot = new Snoopy();
$alphabet = array("", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
mb_internal_encoding('UTF-8'); // Avoid ??s

define("editinterval", 10);
define("pipePlaceholder", "doi_bot_pipe_placeholder"); #4 when online...
define("comment_placeholder", "### Citation bot : comment placeholder %s ###"); #4 when online...
define("to_en_dash", "-|\&mdash;|\xe2\x80\x94|\?\?\?"); // regexp for replacing to ndashes using mb_ereg_replace
define("blank_ref", "<ref name=\"%s\" />");
define("en_dash", "\xe2\x80\x93"); // regexp for replacing to ndashes using mb_ereg_replace
define("wikiroot", "http://en.wikipedia.org/w/index.php?");
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
#define("doiRegexp", "(10\.\d{4}(/|%2F)[^\s\"\?&]*)(?=[\s\"\?&]|</)"); //Note: if a DO I is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.
//Common replacements
$doiIn = array("[", "]", "<", ">", "&#60;!", "-&#62;", "%2F");
$doiOut = array("&#x5B;", "&#x5D;", "&#60;", "&#62;", "<!", "->", "/");

$pcDecode = array("[", "]", "<", ">");
$pcEncode = array("&#x5B;", "&#x5D;", "&#60;", "&#62;");

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

//Optimisation
#ob_start(); //Faster, but output is saved until page finshed.
ini_set("memory_limit", "256M");

$fastMode = $_REQUEST["fast"];
$slowMode = $_REQUEST["slow"];
$user = $_REQUEST["user"];
$bugFix = $_REQUEST["bugfix"];
$crossRefOnly = $_REQUEST["crossrefonly"] ? true : $_REQUEST["turbo"];

if ($_REQUEST["edit"] || $_GET["doi"] || $_GET["pmid"])
  $ON = true;

$editSummaryStart = ($bugFix ? "Double-checking that a [[User:DOI_bot/bugs|bug]] has been fixed. " : "Citations: ");

ob_end_flush();


################ Functions ##############

function updateBacklog($page) {
  $sPage = addslashes($page);
  $id = addslashes(articleId($page));
  $db = udbconnect("yarrow");
  $result = mysql_query("SELECT page FROM citation WHERE id = '$id'") or print (mysql_error());
  $result = mysql_fetch_row($result);
  $sql = $result ? "UPDATE citation SET fast = '" . date("c") . "', revision = '" . revisionID()
          . "' WHERE page = '$sPage'" : "INSERT INTO citation VALUES ('"
          . $id . "', '$sPage', '" . date("c") . "', '0000-00-00', '" . revisionID() . "')";
  $result = mysql_query($sql) or print (mysql_error());
  mysql_close($db);
}

function countMainLinks($title) {
  // Counts the links to the mainpage
  global $bot;
  if (preg_match("/\w*:(.*)/", $title, $title))
    $title = $title[1]; //Gets {{PAGENAME}}
  $url = "http://en.wikipedia.org/w/api.php?action=query&bltitle=" . urlencode($title) . "&list=backlinks&bllimit=500&format=yaml";
  $bot->fetch($url);
  $page = $bot->results;
  if (preg_match("~\n\s*blcontinue~", $page))
    return 501;
  preg_match_all("~\n\s*pageid:~", $page, $matches);
  return count($matches[0]);
}

// This function is called from the end of this page.
function logIn($username, $password) {
  global $bot; // Snoopy class loaded elsewhere
  // Set POST variables to retrieve a token
  $submit_vars["format"] = "json";
  $submit_vars["action"] = "login";
  $submit_vars["lgname"] = $username;
  $submit_vars["lgpassword"] = $password;
  // Submit POST variables and retrieve a token
  $bot->submit(api, $submit_vars);
  $first_response = json_decode($bot->results);
  $submit_vars["lgtoken"] = $first_response->login->token;
  // Store cookies; resubmit with new request (which hast token added to post vars)
  foreach ($bot->headers as $header) {
    if (substr($header, 0, 10) == "Set-Cookie") {
      $cookies = explode(";", substr($header, 12));
      foreach ($cookies as $oCook) {
        $cookie = explode("=", $oCook);
        $bot->cookies[trim($cookie[0])] = $cookie[1];
      }
    }
  }

  $bot->submit(api, $submit_vars);
  $login_result = json_decode($bot->results);
  if ($login_result->login->result == "Success") {
    quiet_echo("\n Using account " . $login_result->login->lgusername . ".");
    // Add other cookies, which are necessary to remain logged in.
    $cookie_prefix = "enwiki";
    $bot->cookies[$cookie_prefix . "UserName"] = $login_result->login->lgusername;
    $bot->cookies[$cookie_prefix . "UserID"] = $login_result->login->lguserid;
    $bot->cookies[$cookie_prefix . "Token"] = $login_result->login->lgtoken;
    return true;
  } else {
    exit("\nCould not log in to Wikipedia servers.  Edits will not be committed.\n"); // Will not display to user
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

function write($page, $data, $edit_summary = "Bot edit") {

  global $bot;

  // Check that bot is logged in:
  $bot->fetch(api . "?action=query&prop=info&meta=userinfo&format=json");
  $result = json_decode($bot->results);

  if ($result->query->userinfo->id == 0) {
    return "LOGGED OUT:  The bot has been logged out from Wikipedia servers";
  }

  $bot->fetch(api . "?action=query&prop=info&format=json&intoken=edit&titles=" . urlencode($page));
  $result = json_decode($bot->results);

  foreach ($result->query->pages as $i_page) {
    $my_page = $i_page;
  }

  $submit_vars = array(
      "action" => "edit",
      "title" => $my_page->title,
      "text" => $data,
      "token" => $my_page->edittoken,
      "summary" => $edit_summary,
      "minor" => "1",
      "bot" => "1",
      #"basetimestamp" => $my_page->touched,
      #"starttimestamp" => $my_page->starttimestamp,
      #"md5"       => md5($data),
      "watchlist" => "nochange",
      "format" => "json",
  );

  $bot->submit(api, $submit_vars);
  $result = json_decode($bot->results);
  if ($result->edit->result == "Success") {
    // Need to check for this string whereever our behaviour is dependant on the success or failure of the write operation
    return "Success";
  } else if ($result->edit->result) {
    return $result->edit->result;
  } else if ($result->error->code) {
    // Return error code
    return strtoupper($result->error->code) . ": " . str_replace(array("You ", " have "), array("This bot ", " has "), $result->error->info);
  } else {
    return "Unhandled error.  Please copy this output and <a href=http://code.google.com/p/citation-bot/issues/list>report a bug.</a>";
  }
}

function parameters_from_citation($c) {
  while (preg_match("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", $c)) {
    $c = preg_replace("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", "$1" . pipePlaceholder, $c);
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
      $p["unused_data"][0] .= " " . substr($value, $pipePos);
      print "\n----" . substr($value, $pipePos);
      $value = substr($value, 0, $pipePos);
    }
    // Load each line into $p[param][0123]
    $weight += 32;
    $p[strtolower($parts[$partsI + 1])] = Array($value, $parts[$partsI], $parts[$partsI + 2], "weight" => $weight); // Param = value, pipe, equals
  }
  return $p;
}

function reassemble_citation($p, $sort = false) {
  // Load an exemplar pipe and equals symbol to deduce the parameter spacing, so that new parameters match the existing format
  foreach ($p as $oP) {
    $pipe = $oP[1] ? $oP[1] : null;
    $equals = $oP[2] ? $oP[2] : null;
    if ($pipe)
      break;
  }
  if (!$pipe) {
    $pipe = "\n | ";
  }
  if (!$equals) {
    $equals = " = ";
  }
#  var_dump($pipe); var_dump($equals); var_dump(preg_replace("~[\r\n]+$~", "", $equals)); die();
  if ($sort) {
    echo "\n (sorting parameters)";
    uasort($p, "bubble_p");
  }

  foreach ($p as $param => $v) {
    if ($param) {
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
              . str_replace(array(pipePlaceholder, "\r", "\n"), array("|", "", " "), trim($v[0]))
              . $nline;
    }
    if (is($param)) {
      $pEnd[$param] = $v[0];
    }
  }
  global $pStart, $modifications;
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

function mark_broken_doi_template($article_in_progress, $oDoi) {
  if (getRawWikiText($article_in_progress)) {
    global $editInitiator;
    return write($article_in_progress
            , preg_replace("~\{\{\s*cite doi\s*\|\s*" . preg_quote($oDoi) . "\s*\}\}~i", "{{broken doi|$oDoi}}", getRawWikiText($article_in_progress))
            , "$editInitiator Reference to broken [[doi:$oDoi]] using [[Template:Cite doi]]: please fix!"
    );
  } else {
    exit("Could not retrieve getRawWikiText($article_in_progress) at expand.php#1q537");
  }
}

function noteDoi($doi, $src) {
  quiet_echo("<h3 style='color:coral;'>Found <a href='http://dx.doi.org/$doi'>DOI</a> $doi from $src.</h3>");
}

function isDoiBroken($doi, $p = false, $slow_mode = false) {

  $doi = verify_doi($doi);

  if (crossRefData($doi)) {
    if ($slow_mode) {
      quiet_echo("\"");
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_NOBODY, 1);
      curl_setopt($ch, CURLOPT_URL, "http://dx.doi.org/$doi");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //This means we can get stuck.
      curl_setopt($ch, CURLOPT_MAXREDIRS, 5);  //This means we can't get stuck.
      curl_setopt($ch, CURLOPT_TIMEOUT, 1);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
      $result = curl_exec($ch);
      curl_close($ch);
      preg_match("~\d{3}~", $result, $code);
      switch ($code[0]) {
        case false:
          $parsed = parse_url("http://dx.doi.org/$doi");
          $host = $parsed["host"];
          $fp = @fsockopen($host, 80, $errno, $errstr, 20);
          if ($fp) {
            return false; // Page exists, but had timed out when we first tried.
          } else {
            logBrokenDoi($doi, $p, 404);
            return 404; // DOI is correct but points to a dead page
          }
        case 302: // Moved temporarily
        case 303: // See other
          return false;
        case 200:
          if ($p["url"][0]) {
            $ch = curl_init();
            curlSetup($ch, $p["url"][0]);
            $content = curl_exec($ch);
            if (!preg_match("~\Wwiki(\W|pedia)~", $content) && preg_match("~" . preg_quote(urlencode($doi)) . "~", urlencode($content))) {
              logBrokenDoi($doi, $p, 200);
              return 200; // DOI is present in page, so probably correct
            } else
              return 999; // DOI could not be found in URL - or URL is a wiki mirror
          } else
            return 100; // No URL to check for DOI
      }
    } else {
      return false;
    }
  }
  return true;
}

function logBrokenDoi($doi, $p, $error) {
  $file = "brokenDois.xml";
  if (file_exists($file))
    $xml = simplexml_load_file($file);
  else
    $xml = new SimpleXMLElement("<errors></errors>");
  $oDoi = $xml->addChild("doi", $doi);
  $oDoi->addAttribute("error_code", $error);
  $oDoi->addAttribute("error_found", date("Y-m-d"));
  unset($p["doi"], $p["unused_data"], $p["accessdate"]);
  foreach ($p as $key => $value)
    $oDoi->addAttribute($key, $value[0]);
  $xml->asXML($file);
  chmod($file, 0644);
}

// Error codes:
// 404 is a working DOI pointing to a page not found;
// 200 is a broken DOI, found in the source of the URL
// Broken DOIs are only logged if they can be spotted in the URL page specified.

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

function rename_parameter($old_name, $new_name, $new_value = null) {
  global $p;
  if (is($new_name)) {
    return false;
  } else {
    $p[$new_name] = $p[$old_name];
    if ($new_value !== null) {
      $p[$new_name][0] = $new_value;
    }
    unset($p[$old_name]);
    if ($old_name == "url") {
      unset($p["accessdate"]);
    }
    return true;
  }
}

function cite_template_contents($type, $id) {
  $page = get_template_prefix($type);
  $replacement_template_name = $page . wikititle_encode($id);
  $text = getRawWikiText($replacement_template_name);
  if (!$text) {
    return false;
  } else {
    return extract_parameters(extract_template($text, "cite journal"));
  }
}

function create_cite_template($type, $id) {
  $page = get_template_prefix($type);
  return expand($page . wikititle_encode($id), true, true, "{{Cite journal\n | $type = $id \n}}<noinclude>{{Documentation|Template:cite_$type/subpage}}</noinclude>");
}

function get_template_prefix($type) {
  return "Template: Cite "
  . ($type == "jstor" ? ("doi/10.2307" . wikititle_encode("/")) : $type . "/");
  // Not sure that this works:
  return "Template: Cite $type/";
  // Do we really need to handle JSTORs differently?
  // The below code errantly produces cite jstor/10.2307/JSTORID, not cite jstor/JSTORID.
  return "Template: Cite "
  . ($type == "jstor" ? ("jstor/10.2307" . wikititle_encode("/")) : $type . "/");
}

//TODO:
/*
  // Replace ids with appropriately formatted parameters
  $c = preg_replace("~\bid(\s*=\s*)(isbn\s*)?(\d[\-\dX ]{9,})~i","isbn$1$3",
  preg_replace("~(isbn\s*=\s*)isbn\s?=?\s?(\d\d)~i","$1$2",
  preg_replace("~(?<![\?&]id=)isbn\s?:(\s?)(\d\d)~i","isbn$1=$1$2", $citation[$cit_i+1]))); // Replaces isbn: with isbn = */
function id_to_parameters() {
  global $p, $modifications;
  $id = $p["id"][0];
  if (trim($id)) {
    echo ("\n - Trying to convert ID parameter to parameterized identifiers.");
  } else {
    return false;
  }
  if (preg_match("~\b(PMID|DOI|ISBN|ISSN|ARVIV|LCCN)[\s:]*(\d[^\s\}\{\|]*)~iu", $id, $match)) {
    if_null_set(strtolower($match[1]), $match[2]);
    $id = str_replace($match[0], "", $id);
  }
  preg_match_all("~\{\{(?P<content>(?:[^\}]|\}[^\}])+?)\}\}[,. ]*~", $id, $match);
  foreach ($match["content"] as $i => $content) {
    $content = explode(pipePlaceholder, $content);
    unset($parameters);
    $j = 0;
    foreach ($content as $fragment) {
      $content[$j++] = $fragment;
      $para = explode("=", $fragment);
      if (trim($para[1])) {
        $parameters[trim($para[0])] = trim($para[1]);
      }
    }
    switch (strtolower(trim($content[0]))) {
      case "arxiv":
        array_shift($content);
        if ($parameters["id"]) {
          if_null_set("arxiv", ($parameters["archive"] ? trim($parameters["archive"]) . "/" : "") . trim($parameters["id"]));
        } else if ($content[1]) {
          if_null_set("arxiv", trim($content[0]) . "/" . trim($content[1]));
        } else {
          if_null_set("arxiv", implode(pipePlaceholder, $content));
        }
        $id = str_replace($match[0][$i], "", $id);
        break;
      case "lccn":
        if_null_set("lccn", trim($content[1]) . $content[3]);
        $id = str_replace($match[0][$i], "", $id);
        break;
      case "rfcurl":
        $identifier_parameter = "rfc";
      case "asin":
        if ($parameters["country"]) {
          print "\n    - {{ASIN}} country parameter not supported: can't convert.";
          break;
        }
      case "oclc":
        if ($content[2]) {
          print "\n    - {{OCLC}} has multiple parameters: can't convert.";
          break;
        }
      case "ol":
        if ($parameters["author"]) {
          print "\n    - {{OL}} author parameter not supported: can't convert.";
          break;
        }
      case "bibcode":
      case "doi":
      case "isbn":
      case "issn":
      case "jfm":
      case "jstor":
        if ($parameters["sici"] || $parameters["issn"]) {
          print "\n    - {{JSTOR}} named parameters are not supported: can't convert.";
          break;
        }
      case "mr":
      case "osti":
      case "pmid":
      case "pmc":
      case "ssrn":
      case "zbl":
        if ($identifier_parameter) {
          array_shift($content);
        }
        if (!if_null_set($identifier_parameter ? $identifier_parameter : strtolower(trim(array_shift($content))), $parameters["id"] ? $parameters["id"] : $content[0]
        )) {
          $modifications["removed"] = true;
        }
        $identifier_parameter = null;
        $id = str_replace($match[0][$i], "", $id);
        break;
      default:
        print "\n    - No match found for $content[0].";
    }
  }
  if (trim($id)) {
    $p["id"][0] = $id;
  } else {
    unset($p["id"]);
  }
}

function get_identifiers_from_url() {
  // Convert URLs to article identifiers:
  global $p;
  $url = $p["url"][0];
  // JSTOR
  if (strpos($url, "jstor.org") !== FALSE) {
    if (strpos($url, "sici")) {
      #print get_final_url($url);
      #print_r(get_headers($url));
      #print_r(get_meta_tags($url));
      #TODO!! get redirect destination of sici URL
      #die();
    } elseif (preg_match("~(\d{6,})$|(\d{6,})[^\d%\-]~", $url, $match)) {
      rename_parameter("url", "jstor", $match[0]);
    }
  } else {
    if (preg_match(bibcode_regexp, urldecode($url), $bibcode)) {
      rename_parameter("url", "bibcode", urldecode($bibcode[1]));
    } else if (preg_match("~^http://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                    . "|^http://www\.ncbi\.nlm\.nih\.gov/pmc/articles/PMC(\d+)~", $url, $match)) {
      rename_parameter("url", "pmc", $match[1] . $match[2]);
      get_data_from_pubmed('pmc');
    } else if (preg_match("~^http://dx\.doi\.org/(.*)", $url, $match)) {
      rename_parameter("url", "doi", urldecode($match[1]));
      get_data_from_doi();
    } else if (preg_match("~\barxiv.org/(?:pdf|abs)/(.+)$~", $url, $match)) {
      //ARXIV
      rename_parameter("url", "arxiv", $match[1]);
      get_data_from_arxiv();
    } else if (preg_match("~http://www.ncbi.nlm.nih.gov/pubmed/.*=(\d{6,})~", $url, $match)) {
      rename_parameter('url', 'pmid', $match[1]);
      get_data_from_pubmed('pmid');
    } else if (preg_match("~^http://www\.amazon(?P<domain>\.[\w\.]{1,7})/dp/(?P<id>\d+)~", $url, $match)) {
      if ($match['domain'] == ".com") {
        rename_parameter('url', 'asin', $match['id']);
      } else {
        $p["id"][0] .= " {{ASIN|{$match['id']}|country=" . str_replace(array(".co.", ".com.", "."), "", $match['domain']) . "}}";
        unset($p["url"]);
        unset($p["accessdate"]);
      }
    }
  }
}

function url2template($url, $citation) {
  print "\n - $url";
  if (preg_match("~jstor\.org/.*[/=](\d+)~", $url, $match)) {
    return "{{Cite doi | 10.2307/$match[1] }}";
  } else if (preg_match("~//dx\.doi\.org/(.+)$~", $url, $match)) {
    return "{{Cite doi | " . urldecode($match[1]) . " }}";
  } else if (preg_match("~^http://www\.amazon(?P<domain>\.[\w\.]{1,7})/dp/(?P<id>\d+)~", $url, $match)) {
    return ($match['domain'] == ".com") ? "{{ASIN | {$match['id']} }}" : " {{ASIN|{$match['id']}|country=" . str_replace(array(".co.", ".com.", "."), "", $match['domain']) . "}}";
  } else if (preg_match("~^http://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                  . "|^http://www\.ncbi\.nlm\.nih\.gov/pmc/articles/PMC(\d+)~", $url, $match)) {
    return "{{Cite pmc | {$match[1]} }}";
  } elseif (preg_match(bibcode_regexp, urldecode($url), $bibcode)) {
    return "{{Cite journal | bibcode = " . urldecode($bibcode[1]) . "}}";
  } else if (preg_match("~http://www.ncbi.nlm.nih.gov/pubmed/.*=(\d{6,})~", $url, $match)) {
    return "{{Cite pmid | {$match[1]} }}";
  } else if (preg_match("~\barxiv.org/(?:pdf|abs)/(.+)$~", $url, $match)) {
    return "{{Cite arxiv | eprint={$match[1]} }}";
  } else {
    return $url;
  }
}

function tidy_citation() {
  global $p, $pStart, $modifications;
  if (!trim($pStart["title"]) && isset($p["title"][0])) {
    $p["title"][0] = formatTitle($p["title"][0]);
  } else if ($modifications && is("title")) {
    $p["title"][0] = (mb_substr($p["title"][0], -1) == ".") ? mb_substr($p["title"][0], 0, -1) : $p["title"][0];
    $p['title'][0] = straighten_quotes($p['title'][0]);
  }
  foreach (array("pages", "page", "issue", "year") as $oParameter) {
    if (is($oParameter)) {
      if (mb_ereg(to_en_dash, $p[$oParameter][0])) {
        $modifications["dashes"] = true;
        echo ( "\n - Upgrading to en-dash in $oParameter");
        $p[$oParameter][0] = mb_ereg_replace(to_en_dash, en_dash, $p[$oParameter][0]);
      }
    }
  }
  //Edition - don't want 'Edition ed.'
  if (is("edition")) {
    $p["edition"][0] = preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $p["edition"][0]);
  }

  // Remove publisher if [cite journal/doc] warrants it
  if (is($p["journal"]) && (is("doi") || is("issn"))) {
    unset($p["publisher"]);
  }


  // If we have any unused data, check to see if any is redundant!
  if (is("unused_data")) {
    $freeDat = explode("|", trim($p["unused_data"][0]));
    unset($p["unused_data"]);
    foreach ($freeDat as $dat) {
      $eraseThis = false;
      foreach ($p as $oP) {
        similar_text(mb_strtolower($oP[0]), mb_strtolower($dat), $percentSim);
        if ($percentSim >= 85)
          $eraseThis = true;
      }
      if (!$eraseThis)
        $p["unused_data"][0] .= "|" . $dat;
    }
    if (trim(str_replace("|", "", $p["unused_data"][0])) == "")
      unset($p["unused_data"]);
    else {
      if (substr(trim($p["unused_data"][0]), 0, 1) == "|")
        $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
      echo "\nXXX Unused data in following citation: {$p["unused_data"][0]}";
    }
  }
}

function standardize_reference($reference) {
  $whitespace = Array(" ", "\n", "\r", "\v", "\t");
  return str_replace($whitespace, "", $reference);
}

// $comments should be an array, with the original comment content.
// $placeholder will be prepended to the comment number in the sprintf to comment_placeholder's %s.
function replace_comments($text, $comments, $placeholder = "") {
  foreach ($comments as $i => $comment) {
    $text = str_replace(sprintf(comment_placeholder, $placeholder . $i), $comment, $text);
  }
  return $text;
}

// This function may need to be called twice; the second pass will combine <ref name="Name" /> with <ref name=Name />.
function combine_duplicate_references($page_code) {

  $original_page_code = $page_code;
  if (preg_match_all("~<!--[\s\S]*?-->~", $page_code, $match)) {
    $removed_comments = $match[0];
    foreach ($removed_comments as $i => $content) {
      $page_code = str_replace($content, sprintf(comment_placeholder, "sr$i"), $page_code);
    }
  }
  preg_match_all("~<ref\s*name\s*=\s*[\"']?([^\"'>]+)[\"']?\s*/>~", $page_code, $empty_refs);
  // match 1 = ref names
  if (preg_match_all("~<ref(\s*name\s*=\s*(?P<quote>[\"']?)([^>]+)(?P=quote)\s*)?>"
                  . "(([^<]|<(?![Rr]ef))+?)</ref>~i", $page_code, $refs)) {
    // match 0 = full ref; 1 = redundant; 2= used in regexp for backreference;
    // 3 = ref name; 4 = ref content; 5 = redundant
    foreach ($refs[4] as $ref) {
      $standardized_ref[] = standardize_reference($ref);
    }
    // Turn essentially-identical references into exactly-identical references
    foreach ($refs[4] as $i => $this_ref) {
      if (false !== ($key = array_search(standardize_reference($this_ref), $standardized_ref))
              && $key != $i) {
        $full_original[] = ">" . $refs[4][$key] . "<"; // be careful; I hope that this is specific enough.
        $duplicate_content[] = ">" . $this_ref . "<";
      }
      $page_code = str_replace($duplicate_content, $full_original, $page_code);
    }
  } else {
    // no matches, return input
    print "\n - No references found.";
    return replace_comments($page_code, $removed_comments, 'sr');
  }

  // Now all references that need merging will have identical content.  Proceed to do the replacements...
  // Reset
  $full_original = null;
  $duplicate_content = null;
  $standardized_ref = null;

  if (preg_match_all("~<ref(\s*name\s*=\s*(?P<quote>[\"']?)([^>]+)(?P=quote)\s*)?>"
                  . "(([^<]|<(?!ref))+?)</ref>~i", $page_code, $refs)) {
    $standardized_ref = $refs[4]; // They were standardized above.

    foreach ($refs[4] as $i => $content) {
      if (false !== ($key = array_search($refs[4][$i], $standardized_ref))
              && $key != $i) {
        $full_original[] = $refs[0][$key];
        $full_duplicate[] = $refs[0][$i];
        $name_of_original[] = $refs[3][$key];
        $name_of_duplicate[] = $refs[3][$i];
        $duplicate_content[] = $content;
        $name_for[$content] = $name_for[$content] ? $name_for[$content] : ($refs[3][$key] ? $refs[3][$key] : ($refs[3][$i] ? $refs[3][$i] : null));
      }
    }
    $already_replaced = Array(); // so that we can use FALSE and not NULL in the check...
    if ($full_duplicate) {
      foreach ($full_duplicate as $i => $this_duplicate) {
        if (FALSE === array_search($this_duplicate, $already_replaced)) {
          $already_replaced[] = $full_duplicate[$i]; // So that we only replace the same reference once
          echo "\n - Replacing duplicate reference $this_duplicate. \n - * Reference name: "
          . ( $name_for[$duplicate_content[$i]] ? $name_for[$duplicate_content[$i]] : "Autogenerating." ); // . " (original: $full_original[$i])";
          $replacement_template_name = $name_for[$duplicate_content[$i]] ? $name_for[$duplicate_content[$i]] : get_name_for_reference($duplicate_content[$i], $page_code);
          // First replace any empty <ref name=Blah content=none /> or <ref name=Blah></ref> with the new name
          $ready_to_replace = preg_replace("~<ref\s*name\s*=\s*(?P<quote>[\"']?)"
                  . preg_quote($name_of_duplicate[$i])
                  . "(?P=quote)(\s*/>|\s*>\s*</\s*ref>)~"
                  , "<ref name=\"" . $replacement_template_name . "\"$2"
                  , $page_code);
          if ($name_of_original[$i]) {
            // Don't replace the original template!
            $original_ref_end_pos = strpos($ready_to_replace, $full_original[$i]) + strlen($full_original[$i]);
            $code_upto_original_ref = substr($ready_to_replace, 0, $original_ref_end_pos);
          } elseif ($name_of_duplicate[$i]) {
            // This is an odd case; in a fashion the simplest.
            // In effect, we switch the original and duplicate over,..
            $original_ref_end_pos = 0;
            $code_upto_original_ref = "";
            $already_replaced[] = $full_original[$i];
            $this_duplicate = $full_original[$i];
          } else {
            // We need add a name to the original template, and not to replace it
            $original_ref_end_pos = strpos($ready_to_replace, $full_original[$i]);
            $code_upto_original_ref = substr($ready_to_replace, 0, $original_ref_end_pos) // Sneak this in to "first_duplicate"
                    . preg_replace("~<ref(\s+name\s*=\s*(?P<quote>[\"']?)" . preg_quote($name_of_original[$i])
                            . "(?P=quote)\s*)?>~i", "<ref name=\"$replacement_template_name\">", $full_original[$i]);
            $original_ref_end_pos += strlen($full_original[$i]);
          }
          // Then check that the first occurrence won't be replaced
          $page_code = $code_upto_original_ref . str_replace($this_duplicate, sprintf(blank_ref, $replacement_template_name), substr($ready_to_replace, $original_ref_end_pos));
          // If references are specified in a {{reflist}} then we need to leave the full text there.
          print "\n Current PAGECODE: $page_code\n";
          $check_no_empty_ref_in_list = '~(\{\{\s*reflist(?:[^\}]|\}[^\}]|\{\{(?:[^\}]|\{\{[^\}]\}\})*\}\})*)'
                  . sprintf(blank_ref, preg_quote($replacement_template_name)) . '~';
          print "\n \n Searching for: " . $check_no_empty_ref_in_list . "\n";
          if (preg_match($check_no_empty_ref_in_list, $page_code)) {
            // replace our full reference with an empty one where it's defined in the text...
            $page_code = str_replace($full_original[$i], sprintf(blank_ref, $replacement_template_name), $page_code);
            // ... then put the text in the reflist section
            $page_code = preg_replace($check_no_empty_ref_in_list, "$1" . $this_duplicate, $page_code);
          }
          global $modifications;
          $modifications["combine_references"] = true;
        }
      }
    }
  }

  $page_code = replace_comments($page_code, $removed_comments, 'sr');
  echo ($original_page_code == $page_code) ? "\n - No duplicate references to combine" : "\n - Combined duplicate references (if any exist).";
  return $page_code;
}

function ref_templates($page_code, $type) {
  while (false !== ($ref_template = extract_template($page_code, "ref $type"))) {
    echo "  Converted {{ref $type}}.";
    $ref_parameters = extract_parameters($ref_template);
    $ref_id = $ref_parameters[1] ? $ref_parameters[1][0] : $ref_parameters["unnamed_parameter_1"][0];

    if (!getArticleId("Template:cite $type/" . wikititle_encode($ref_id))) {
      $citation_code = create_cite_template($type, $ref_id);
      $template = extract_parameters(extract_template($citation_code, "cite journal"));
    } else {
      $template = cite_template_contents($type, $ref_id);
    }
    $replacement_template_name = generate_template_name(
            (trim($template["last1"][0]) != "" && trim($template["year"][0]) != "") ? trim($template["last1"][0]) . trim($template["year"][0]) : "ref_"
            , $page_code);
    $ref_content = "<ref name=\"$replacement_template_name\">"
            . $ref_template
            . "</ref>";
    $page_code = str_replace($ref_template, str_ireplace("ref $type", "cite $type", $ref_content), $page_code);
  }
  return $page_code;
}

function name_references($page_code) {
  echo " naming";
  if (preg_match_all("~<ref>[^\{<]*\{\{\s*(?=[cC]it|[rR]ef).*</ref>~U", $page_code, $refs)) {
    foreach ($refs[0] as $ref) {
      $ref_name = get_name_for_reference($ref, $page_code);
      if (substr($ref_name, 0, 4) != "ref_") {
        // i.e. we have used an interesting reference name
        $page_code = str_replace($ref, str_replace("<ref>", "<ref name=\"$ref_name\">", $ref), $page_code);
      }
      echo ".";
    }
  }
  return $page_code;
}

function rename_references($page_code) {
  if (preg_match_all("~(<ref name=(?P<quote>[\"']?)[Rr]ef_?[ab]?(?:[a-z]|utogenerated|erence[a-Z])?(?P=quote)\s*>)"
                  . "[^\{<]*\{\{\s*(?=[cC]it|[rR]ef)[\s\S]*</ref>~U", $page_code, $refs)) {
    $countRefs = count($refs[0]);
    for ($i = 0; $i < $countRefs; ++$i) {
      $ref_name = get_name_for_reference($refs[0][$i], $page_code);
      if (substr($ref_name, 0, 4) != "ref_") {
        // i.e. we have used an interesting reference name
        echo " renaming references with meaningless names";
        $page_code = str_replace($refs[1][$i], "<ref name=\"$ref_name\">", $page_code);
      }
      echo ".";
    }
  }
  return $page_code;
}

function get_name_for_reference($text, $page_code) {
  if (strpos($text, "://")) {
    if (preg_match("~\w+://(?:www\.)([^/]+?)(?:\.\w{2,3}\b)+~i", $text, $match)) {
      $replacement_template_name = $match[1];
    } else {
      $replacement_template_name = "bare_url"; // just in case there's some bizarre way that the URL doesn't match the regexp
    }
  } else {
    if (stripos($text, "{{harv") !== FALSE && preg_match("~\|([\s\w\-]+)\|\s*([12]\d{3})\D~", $text, $match)) {
      $author = $match[1];
      $date = $match[2];
    } else {
      $parsed = parse_wikitext(strip_tags($text));
      $parsed_plaintext = strip_tags($parsed);
      $date = (preg_match("~rft\.date=[^&]*(\d\d\d\d)~", $parsed, $date) ? $date[1] : "" );
      $author = preg_match("~rft\.aulast=([^&]+)~", $parsed, $author) ? $author[1] : preg_match("~rft\.au=([^&]+)~", $parsed, $author) ? $author[1] : "ref_";
      $btitle = preg_match("~rft\.[bah]title=([^&]+)~", $parsed, $btitle) ? $btitle[1] : "";
    }
    if ($author != "ref_") {
      preg_match("~\w+~", authorify($author), $author);
    } else if ($btitle) {
      preg_match("~\w+\s?\w+~", authorify($btitle), $author);
    } else if ($parsed_plaintext) {
      if (!preg_match("~\w+\s?\w+~", authorify($parsed_plaintext), $author)) {
        preg_match("~\w+~", authorify($parsed_plaintext), $author);
      }
    }
    $replacement_template_name = str_replace(Array("\n", "\r", "\t", " "), "", ucfirst($author[0])) . $date;
    #print "\n Replacement name: $replacement_template_name\n\n";
  }
  return generate_template_name($replacement_template_name, $page_code);
}

// Strips special characters from reference name,
// then does a check against the current page code to generate a unique name for the reference
// (by suffixing _a, etc, as necessary)
function generate_template_name($replacement_template_name, $page_code) {
  $replacement_template_name = remove_accents($replacement_template_name);
  if (!trim(preg_replace("~\d~", "", $replacement_template_name))) {
    $replacement_template_name = "ref" . $replacement_template_name;
  }
  global $alphabet;
  $die_length = count($alphabet);
  $underscore = (preg_match("~[\d_]$~", $replacement_template_name) ? "" : "_");
  while (preg_match("~<ref name=(?P<quote>['\"]?)"
          . preg_quote($replacement_template_name) . "_?" . $alphabet[$i++]
          . "(?P=quote)[/\s]*>~i", $page_code, $match)) {
    if ($i >= $die_length) {
      $replacement_template_name .= $underscore . $alphabet[++$j];
      $underscore = "";
      $i = 0;
    }
  }
  if ($i < 2) {
    $underscore = "";
  }
  return $replacement_template_name
  . $underscore
  . $alphabet[--$i];
}

function remove_accents($input) {
  $search = explode(",", "ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u");
  $replace = explode(",", "c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
  return str_replace($search, $replace, $input);
}

function handle_et_al() {
  global $p;
  $parameter_group = array(
     array('last', 'author', 'first', 'coauthors', 'coauthor', 'authors', 'first1', 'last1', 'author1'),
     array('first2', 'last2', 'author2'),
     array('first3', 'last3', 'author3'),
     array('first4', 'last4', 'author4'),
     array('first5', 'last5', 'author5'),
     array('first6', 'last6', 'author6'),
     array('first7', 'last7', 'author7'),
     array('first8', 'last8', 'author8'),
     array('first9', 'last9', 'author9'),
   );
      
  foreach ($parameter_group as $group) {
    foreach ($group as $i=>$param) {
      if (strpos($p[$param][0], 'et al')) {
        if_null_set('display-authors', $i + 1);
        $p[$param][0] = preg_replace("~\s*'*et al['.]*~", '', $p[$param][0]);
      }
    }
  }
}

function authorify($author) {
  $author = preg_replace("~[^\s\w]|\b\w\b|[\d\-]~", "", normalize_special_characters(html_entity_decode(urldecode($author), ENT_COMPAT, "UTF-8")));
  $author = preg_match("~[a-z]~", $author) ? preg_replace("~\b[A-Z]+\b~", "", $author) : strtolower($author);
  return $author;
}

function ifNullSet($a, $b, $DEPRECATED = TRUE) {
  print "\n\n Call to deprecated function ifNullSet in expandFns.php";
  if_null_set($a, $b);
}

function if_null_set($param, $value) {
  global $p;
  if (substr($param, strlen($param) - 3, 1) > 0 || substr($param, strlen($param) - 2) > 9) {
    // The parameter is of 'first101' or 'last10' format and adds nothing but clutter
    return false;
  }
  switch ($param) {
    case "editor": case "editor-last": case "editor-first":
      $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
      if (trim($p["editor"][0]) == "" && trim($p["editor-last"][0]) == "" && trim($p["editor-first"][0]) == "" && trim($value) != "") {
        set($param, $value);
        return true;
      }
      break;
    case "author": case "author1": case "last1": case "last": case "authors": // "authors" is automatically corrected by the bot to "author"; include to avoid a false positive.
      $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
      if (trim($p["last1"][0]) == ""
              && trim($p["last"][0]) == ""
              && trim($p["author"][0]) == ""
              && trim($p["author1"][0]) == ""
              && trim($p["editor"][0]) == ""
              && trim($p["editor-last"][0]) == ""
              && trim($p["editor-first"][0]) == ""
              && trim($value) != ""
      ) {
        if (strpos($value, ',')) {
          $au = explode(',', $value);
          set($param, formatSurname($au[0]));
          set('first' . (substr($param, 1) ? 1 : ''), formatForename($trim($au[1])));
        }
        set($param, $value);
        return true;
      }
      break;
    case "first": case "first1":
      if (trim($p["first"][0]) == ""
              && trim($p["first1"][0]) == ""
              && trim($p["author"][0]) == ""
              && trim($p['author1'][0]) == "") {
        set($param, $value);
        return true;
      }
      break;
    case "coauthor": case "coauthors":
      $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
      if (trim($p["last2"][0]) == "" && trim($p["coauthor"][0]) == "" && trim($p["coauthors"][0]) == "" && trim($p["author"][0]) == "" && trim($value) != "") {
        // Note; we shouldn't be using this parameter ever....
        set($param, $value);
        return true;
      }
      break;
    case "last2": case "last3": case "last4": case "last5": case "last6": case "last7": case "last8": case "last9":
    case "author2": case "author3": case "author4": case "author5": case "author6": case "author7": case "author8": case "author9":
      $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"),
                array(";", ";", " ", ";", " ", "", ""),
                $value);      
      if (strpos($value, ',')) {
            $au = explode(',', $value);
            set('last' . substr($param, -1), formatSurname($au[0]));
            if_null_set('first' . substr($param, -1), formatForename(trim($au[1])));
      }
      if (trim($p["last" . substr($param, -1)][0]) == "" && trim($p["author" . substr($param, -1)][0]) == ""
              && trim($p["coauthor"][0]) == "" && trim($p["coauthors"][0]) == ""
              && underTwoAuthors($p['author'][0])) {
        set($param, $value);
        return true;
      }
      break;
    case "first2": case "first3": case "first4": case "first5": case "first6": case "first7": case "first8": case "first9": case "first10":
      if (trim($p[$param][0]) == ""
              && underTwoAuthors($p['author'][0]) && trim($p["author" . substr($param, strlen($param) - 1)][0]) == ""
              && trim($p["coauthor"][0]) == "" && trim($p["coauthors"][0]) == ""
              && trim($value) != "") {
        set($param, $value);
        return true;
      }
      break;
    case "date":
      if (preg_match("~^\d{4}$~", trim($value))) {
        // Not adding any date data beyond the year, so 'year' parameter is more suitable
        $param = "year";
      }
    // Don't break here; we want to go straight in to year;
    case "year":
      if (trim($p["date"][0]) == "" && trim($p["year"][0]) == "" && trim($value) != "") {
        set($param, $value);
        return true;
      }
      break;
    case "periodical": case "journal":
      if (trim($p["journal"][0]) == "" && trim($p["periodical"][0]) == "" && trim($value) != "") {
        set($param, $value);
        return true;
      }
      break;
    case "page": case "pages":
      if (( trim($p["pages"][0]) == ""
              && trim($p["page"][0]) == ""
              && trim($value) != "" ) 
          || strpos(strtolower($p["pages"][0] . $p['page'][0]), 'no') !== FALSE
          || (strpos($value, en_dash) || (strpos($value, '-'))
                  && !strpos($p['pages'][0], en_dash) && !strpos($p['pages'][0], '-'))
      ) {
        set($param, $value);
        return true;
      }
      break;
    default: if (trim($p[$param][0]) == "" && trim($value) != "") {
        set($param, $value);
        return true;
      }
  }
  return false;
}

function set($key, $value) {
  // Dud DOI in PMID database
  if ($key == "doi") {
    if ($value == "10.1267/science.040579197") {
      return false;
    } else {
      $value = str_replace(array("?cookieset=1",), "", $value);
    }
  }

  $parameter_order = list_parameters();
  if (trim($value) != "") {
    global $p, $modifications;
    $modifications[$p[$key][0] ? 'changes' : 'additions'][$key] = true;
    $p[$key][0] = (string) $value;
    echo "\n    + $key: $value";
    if (!$p[$key]["weight"]) {
      // Calculate the appropriate weight:
      #print "-$key-" . array_search($key, $parameter_order) . array_search("year", $parameter_order);
      $key_position = array_search($key, $parameter_order);
      if (!$key_position) {
        $p[$key]["weight"] = 16383;
      } else {
        $lightest_weight = 16383; // (2^14)-1, arbritarily large
        for ($i = count($parameter_order); $i >= $key_position && $i > 0; $i--) {
          if ($p[$parameter_order[$i]]["weight"] > 0) {
            $lightest_weight = $p[$parameter_order[$i]]["weight"];
            $lightest_param = $parameter_order[$i];
          }
        }

        for ($i = $key_position; $i >= 0; $i--) {
          if ($p[$parameter_order[$i]]["weight"] > 0) {
            $heaviest_weight = $p[$parameter_order[$i]]["weight"];
            $heaviest_param = $parameter_order[$i];
            break;
          }
        }
        $p[$key]["weight"] = ($lightest_weight + $heaviest_weight) / 2;
        # echo " ({$p[$key]["weight"]})";
      }
    }
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

quiet_echo("\n Establishing connection to Wikipedia servers ... ");
// Log in to Wikipedia
logIn(USERNAME, PASSWORD);

quiet_echo("\n Fetching parameter list ... ");
// Get a current list of parameters used in citations from WP
$page = $bot->fetch(api . "?action=query&prop=revisions&rvprop=content&titles=User:Citation_bot/parameters&format=json");
$json = json_decode($bot->results, true);
$parameter_list = (explode("\n", $json["query"]["pages"][26899494]["revisions"][0]["*"]));

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

uasort($parameter_list, "ascii_sort");
quiet_echo("done.");
?>