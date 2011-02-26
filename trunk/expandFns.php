<?
// $Id$

session_start();
ini_set ("user_agent", "Citation_bot; verisimilus@toolserver.org");

function includeIfNew($file){
        // include missing files
        $alreadyIn = get_included_files();
        foreach ($alreadyIn as $include){
                if (strstr($include, $file)) return false;
        }
        if ($GLOBALS["linkto2"]) echo "\n// including $file";
        require_once($file . $GLOBALS["linkto2"] . ".php");
        return true;
} 

echo " \n Getting login details ... ";
require_once("/home/verisimilus/public_html/Bot/DOI_bot/doiBot$linkto2.login");
echo " done.";
# Snoopy should be set so the host name is en.wikipedia.org.
includeIfNew('Snoopy.class');
includeIfNew("wikiFunctions");
includeIfNew("DOItools");
includeIfNew("expand_it");
if (!$abort_mysql_connection) {
  echo "\n Initializing MYSQL database ... ";
  require_once("/home/verisimilus/public_html/res/mysql_connect.php");
  //$db = udbconnect("yarrow");
  echo " loaded connect script.  Will connect when necessary.";
}
if(!true && !myIP()) {
        echo "Sorry, the Citation bot is temporarily unavilable while bugs are fixed.  Please try back later."; exit;
}

echo "\n Initializing ...  ";
require_once("/home/verisimilus/public_html/crossref.login");
echo "...";
$crossRefId = CROSSREFUSERNAME;
$isbnKey = "268OHQMW";
$isbnKey2 = "268OHQMW";
$bot = new Snoopy();
$alphabet = array("", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
mb_internal_encoding( 'UTF-8' ); // Avoid ??s

define("editinterval", 10);
define("pipePlaceholder", "doi_bot_pipe_placeholder"); #4 when online...
define("wikiroot", "http://en.wikipedia.org/w/index.php?");
//define("doiRegexp", "(10\.\d{4}/([^\s;\"\?&<])*)(?=[\s;\"\?&]|</)");
#define("doiRegexp", "(10\.\d{4}(/|%2F)[^\s\"\?&]*)(?=[\s\"\?&]|</)"); //Note: if a DO I is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.

//Common replacements
$doiIn = array("[",                     "]",                    "<",                    ">"     ,                       "&#60;!",       "-&#62;",               "%2F"   );
$doiOut = array("&#x5B;", "&#x5D;", "&#60;",  "&#62;",  "<!",                   "->",                   "/"     );

$pcDecode = array("[",                  "]",                    "<",                    ">");
$pcEncode = array("&#x5B;", "&#x5D;", "&#60;",  "&#62;");

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

//Optimisation
#ob_start(); //Faster, but output is saved until page finshed.
ini_set("memory_limit", "256M");

$fastMode = $_REQUEST["fast"];
$slowMode = $_REQUEST["slow"];
$user = $_REQUEST["user"];
$bugFix = $_REQUEST["bugfix"];
$crossRefOnly = $_REQUEST["crossrefonly"]?true:$_REQUEST["turbo"];

if ($_REQUEST["edit"] || $_GET["doi"] || $_GET["pmid"]) $ON = true;

$editSummaryStart = ($bugFix?"Double-checking that a [[User:DOI_bot/bugs|bug]] has been fixed. ":"Citations: ");

ob_end_flush();


################ Functions ##############

function updateBacklog($page) {
  $sPage = addslashes($page);
  $id = addslashes(articleId($page));
  $db = udbconnect("yarrow");
  $result = mysql_query("SELECT page FROM citation WHERE id = '$id'") or print (mysql_error());
  $result = mysql_fetch_row($result);
  $sql = $result?"UPDATE citation SET fast = '" . date ("c") . "', revision = '" . revisionID()
          . "' WHERE page = '$sPage'"
          : "INSERT INTO citation VALUES ('"
          . $id . "', '$sPage', '" . date ("c") . "', '0000-00-00', '" . revisionID() ."')";
  $result = mysql_query($sql) or print (mysql_error());
  mysql_close($db);
}

function countMainLinks($title) {
        // Counts the links to the mainpage
        global $bot;
        if(preg_match("/\w*:(.*)/", $title, $title)) $title = $title[1]; //Gets {{PAGENAME}}
        $url = "http://en.wikipedia.org/w/api.php?action=query&bltitle=" . urlencode($title) . "&list=backlinks&bllimit=500&format=yaml";
        $bot->fetch($url);
        $page = $bot->results;
        if (preg_match("~\n\s*blcontinue~", $page)) return 501;
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
    if (substr($header, 0,10) == "Set-Cookie") {
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
    echo "\n Using account " . $login_result->login->lgusername . ".";
    // Add other cookies, which are necessary to remain logged in.
    $cookie_prefix = "enwiki";
    $bot->cookies[$cookie_prefix . "UserName"] = $login_result->login->lgusername;
    $bot->cookies[$cookie_prefix . "UserID"] = $login_result->login->lguserid;
    $bot->cookies[$cookie_prefix . "Token"] = $login_result->login->lgtoken;
    return true;
  } else {
    die( "\nCould not log in to Wikipedia servers.  Edits will not be committed.\n"); // Will not display to user
    global $ON; $ON = false;
    return false;
  }
}

function inputValue($tag, $form) {
        //Gets the value of an input, if the input's in the right format.
        preg_match("~value=\"([^\"]*)\" name=\"$tag\"~", $form, $name);
        if ($name) return $name[1];
        preg_match("~name=\"$tag\" value=\"([^\"]*)\"~", $form, $name);
        if ($name) return $name[1];
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

        $submit_vars = array (
    "action"    => "edit",
    "title"     => $my_page->title,
    "text"      => $data,
    "token"     => $my_page->edittoken,
    "summary"   => $edit_summary,
    "minor"     => "1",
    "bot"       => "1",
    #"basetimestamp" => $my_page->touched,
    #"starttimestamp" => $my_page->starttimestamp,
    #"md5"       => md5($data),
    "watchlist" => "nochange",
    "format"    => "json",
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

function mark_broken_doi_template($article_in_progress, $oDoi) {
  if (getRawWikiText($article_in_progress)) {
    global $editInitiator;
    return write ($article_in_progress
      , preg_replace("~\{\{\s*cite doi\s*\|\s*" . preg_quote($oDoi) . "\s*\}\}~i",
                                            "{{broken doi|$oDoi}}", getRawWikiText($article_in_progress))
      , "$editInitiator Reference to broken [[doi:$oDoi]] using [[Template:Cite doi]]: please fix!"
    );
  } else {
    die ("Could not retrieve getRawWikiText($article_in_progress) at expand.php#1q537");
  }
}

function noteDoi($doi, $src){
        echo "<h3 style='color:coral;'>Found <a href='http://dx.doi.org/$doi'>DOI</a> $doi from $src.</h3>";
}

function isDoiBroken ($doi, $p = false, $slow_mode = false) {

  $doi = verify_doi($doi);

  if (crossRefData($doi)) {
    if ($slow_mode) {
      echo "\"";
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
                        } else return 999; // DOI could not be found in URL - or URL is a wiki mirror
                }       else return 100; // No URL to check for DOI
      }
    } else {
      return false;
    }
  }
  return true;
}

function logBrokenDoi($doi, $p, $error){
        $file = "brokenDois.xml";
        if (file_exists($file)) $xml = simplexml_load_file($file);
        else $xml = new SimpleXMLElement("<errors></errors>");
        $oDoi = $xml->addChild("doi", $doi);
        $oDoi->addAttribute("error_code", $error);
        $oDoi->addAttribute("error_found", date("Y-m-d"));
        unset($p["doi"], $p["unused_data"], $p["accessdate"]);
        foreach ($p as $key => $value) $oDoi->addAttribute($key, $value[0]);
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
    if (substr($param, strlen($param)-1) > 0 && trim($value) != trim($p[$param][0])) {
      // Add one to last1 to create last2
      $param = substr($param, 0, strlen($param)-1) . (substr($param, strlen($param)-1) + 1);
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
        . ($type == "jstor"
        ? ("doi/10.2307" . wikititle_encode("/"))
        : $type . "/");
  // Not sure that this works:
  return "Template: Cite $type/";
  // Do we really need to handle JSTORs differently?
  // The below code errantly produces cite jstor/10.2307/JSTORID, not cite jstor/JSTORID.
  return "Template: Cite "
        . ($type == "jstor"
        ? ("jstor/10.2307" . wikititle_encode("/"))
        : $type . "/");
}

function standardize_reference($reference) {
  $whitespace = Array(" ", "\n", "\r", "\v", "\t");
  return str_replace($whitespace, "", $reference);
}

function combine_duplicate_references($page_code) {

  $original_page_code = $page_code;
  preg_match_all("~<ref\s*name=[\"']?([^\"'>]+)[\"']?\s*/>~", $page_code, $empty_refs);
    // match 1 = ref names
  if (preg_match_all("~<ref(\s*name=(?P<quote>[\"']?)([^>]+)(?P=quote)\s*)?>(([^<]|<(?![Rr]ef))*?)</ref>~i", $page_code, $refs)) {
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
  }
  // Reset
  $full_original = null;
  $duplicate_content = null;
  $standardized_ref = null;

  if (preg_match_all("~<ref(\s*name=(?P<quote>[\"']?)([^>]+)(?P=quote)\s*)?>(([^<]|<(?!ref))*?)</ref>~i", $page_code, $refs)) {
    foreach ($refs[4] as $ref) {
      $standardized_ref[] = standardize_reference($ref);
    }
    foreach ($refs[4] as $i => $content) {
      if (false !== ($key = array_search(standardize_reference($refs[4][$i]), $standardized_ref))
              && $key != $i) {
        $full_original[] = $refs[0][$key];
        $full_duplicate[] = $refs[0][$i];
        $name_of_original[] = $refs[3][$key];
        $name_of_duplicate[] = $refs[3][$i];
        $duplicate_content[] = $content;
      }
    }
    $already_replaced = Array(); // so that we can use FALSE and not NULL in the check...
    if ($full_duplicate) {
      foreach ($full_duplicate as $i => $this_duplicate) {
        if (FALSE === array_search($this_duplicate, $already_replaced)) {
          $already_replaced[] = $full_duplicate[$i]; // So that we only replace the same reference once
          echo "\n - Replacing duplicate reference $this_duplicate"; // . " (original: $full_original[$i])";
          $replacement_template_name = $name_of_original[$i]
                                     ? $name_of_original[$i]
                                     : get_name_for_reference($duplicate_content[$i], $page_code);
          //preg_match("~<ref\s*name=(?P<quote>[\"']?)" . preg_quote($name_of_duplicate[$i])
            //                        . "(?P=quote)(\s*/>)~",
              //                $page_code,
                /*  $match1);*/
          // First replace any <ref name=that'sall/> with the new name
          $ready_to_replace = preg_replace("~<ref\s*name=(?P<quote>[\"']?)" . preg_quote($name_of_duplicate[$i])
                                    . "(?P=quote)(\s*/>)~", "<ref name=\"" . $replacement_template_name . "\"$2",
                              $page_code);
          if ($name_of_original[$i]) {
            // Don't replace the original template!
            $replacement_template_name = $name_of_original[$i];
            $original_ref_end_pos = strpos($ready_to_replace, $full_original[$i]) + strlen($full_original[$i]);
            $code_upto_original_ref = substr($ready_to_replace, 0, $original_ref_end_pos);
          } else {
            // We need add a name to the original template, and not to replace it
            $original_ref_end_pos = strpos($ready_to_replace, $full_original[$i]);
            $code_upto_original_ref = substr($ready_to_replace, 0, $original_ref_end_pos) // Sneak this in to "first_duplicate"
                             . preg_replace("~<ref(\s+name=(?P<quote>[\"']?)" . preg_quote($name_of_original[$i])
                                     . "(?P=quote)\s*)?>~i", "<ref name=\"$replacement_template_name\">", $full_original[$i]);
            $original_ref_end_pos += strlen($full_original[$i]);
          }
          // Then check that the first occurrence won't be replaced
          $page_code = $code_upto_original_ref . str_replace($this_duplicate,
                    "<ref name=\"$replacement_template_name\" />", substr($ready_to_replace, $original_ref_end_pos));
          #print "with  \"<ref name=\"$replacement_template_name\" />\"\n";
        }
      }
    }
  }
  echo ($original_page_code == $page_code)
    ? "\n - No duplicate references to combine"
    : "\n - Combined duplicate references (if any exist).";
  return $page_code;
}

function ref_templates($page_code, $type) {
  while (false !== ($ref_template = extract_template($page_code, "ref $type"))) {
    echo " converted {{ref $type}},";
    $ref_parameters = extract_parameters($ref_template);
    $ref_id = $ref_parameters[1] ? $ref_parameters[1][0] : $ref_parameters["unnamed_parameter_1"][0];

    if (!getArticleId("Template:cite $type/" . wikititle_encode($ref_id))) {
      $citation_code = create_cite_template($type, $ref_id);
      $template = extract_parameters(extract_template($citation_code, "cite journal"));
    } else {
      $template = cite_template_contents($type, $ref_id);
    }
    $replacement_template_name = generate_template_name(
                    (trim($template["last1"][0]) != "" && trim($template["year"][0]) != "")
                    ? trim($template["last1"][0]) . trim($template["year"][0])
                    : "ref_"
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
  if (preg_match_all("~(<ref name=(?P<quote>[\"']?)ref_?[ab]?(?:[a-z]|utogenerated)?(?P=quote)\s*>)[^\{<]*\{\{\s*(?=[cC]it|[rR]ef)[\s\S]*</ref>~U", $page_code, $refs)) {
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
  $parsed = parse_wikitext(strip_tags($text));
  $parsed_plaintext = strip_tags($parsed);
  $date = (preg_match("~rft\.date=[^&]*(\d\d\d\d)~", $parsed, $date)
            ?  $date[1]
            : "" );
  $author = preg_match("~rft\.aulast=([^&]+)~", $parsed, $author)
          ? $author[1]
          : preg_match("~rft\.au=([^&]+)~", $parsed, $author)
          ? $author[1]
          : "ref_";
  $btitle = preg_match("~rft\.[bah]title=([^&]+)~", $parsed, $btitle)
          ? $btitle[1]
          : "";
  if ($author != "ref_") {
    preg_match("~\w+~", authorify($author), $author);
  } else if ($btitle) {
    preg_match("~\w+\s?\w+~", authorify($btitle), $author);
  } else if ($parsed_plaintext) {
    if (!preg_match("~\w+\s?\w+~", authorify($parsed_plaintext), $author)) {
      preg_match("~\w+~", authorify($parsed_plaintext), $author);
    }
  }
  $replacement_template_name = str_replace(" ", "", ucfirst($author[0])) . $date;
  #print "\n Replacement name: $replacement_template_name\n\n";
  return generate_template_name($replacement_template_name, $page_code);
}


// Strips special characters from reference name,
// then does a check against the current page code to generate a unique name for the reference
// (by suffixing _a, etc, as necessary)
function generate_template_name ($replacement_template_name, $page_code) {
  $replacement_template_name = remove_accents($replacement_template_name);
  if (!trim(preg_replace("~\d~", "", $replacement_template_name))) {
    $replacement_template_name = "ref" . $replacement_template_name;
  }
  global $alphabet;
  $die_length = count($alphabet);
  $underscore = (preg_match("~[\d_]$~", $replacement_template_name)
            ? ""
            : "_");
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

function remove_accents ($input) {
  $search = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u");
  $replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
  return str_replace($search, $replace, $input);
}

function authorify ($author) {
  $author = preg_replace("~[^\s\w]|\b\w\b|[\d\-]~", "", normalize_special_characters(html_entity_decode(urldecode($author), ENT_COMPAT, "UTF-8")));
  $author = preg_match("~[a-z]~", $author)
          ? preg_replace("~\b[A-Z]+\b~", "", $author)
          : strtolower($author);
  return $author;
}

// Function from http://stackoverflow.com/questions/1890854
// Modified to expect utf8-encoded string
function normalize_special_characters( $str ) {
  $str = utf8_decode($str);
    # Quotes cleanup
    $str = ereg_replace( chr(ord("`")), "'", $str );        # `
    $str = ereg_replace( chr(ord("´")), "'", $str );        # ´
    $str = ereg_replace( chr(ord("„")), ",", $str );        # „
    $str = ereg_replace( chr(ord("`")), "'", $str );        # `
    $str = ereg_replace( chr(ord("´")), "'", $str );        # ´
    $str = ereg_replace( chr(ord("“")), "\"", $str );        # “
    $str = ereg_replace( chr(ord("”")), "\"", $str );        # ”
    $str = ereg_replace( chr(ord("´")), "'", $str );        # ´

$unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
$str = strtr( $str, $unwanted_array );

# Bullets, dashes, and trademarks
$str = ereg_replace( chr(149), "&#8226;", $str );    # bullet •
$str = ereg_replace( chr(150), "&ndash;", $str );    # en dash
$str = ereg_replace( chr(151), "&mdash;", $str );    # em dash
$str = ereg_replace( chr(153), "&#8482;", $str );    # trademark
$str = ereg_replace( chr(169), "&copy;", $str );    # copyright mark
$str = ereg_replace( chr(174), "&reg;", $str );        # registration mark

    return utf8_encode($str);
}

echo "\n Establishing connection to Wikipedia servers ... ";
// Log in to Wikipedia
logIn(USERNAME, PASSWORD);

echo "\n Fetching parameter list ... ";
// Get a current list of parameters used in citations from WP
$page = $bot->fetch(api . "?action=query&prop=revisions&rvprop=content&titles=User:Citation_bot/parameters&format=json");
$json = json_decode($bot->results, true);
$parameter_list = (explode("\n", $json["query"]["pages"][26899494]["revisions"][0]["*"]));
function ascii_sort($val_1, $val_2)
{
  $return = 0;
  $len_1 = strlen($val_1);
  $len_2 = strlen($val_2);

  if ($len_1 > $len_2)
  {
    $return = -1;
  }
  else if ($len_1 < $len_2)
  {
    $return = 1;
  }
  return $return;
}
uasort($parameter_list, "ascii_sort");
echo ("done.");
?>