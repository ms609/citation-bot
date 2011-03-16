<?
// $Id: $
define ("template_regexp", "~\{\{\s*([^\|\}]+)([^\{]|\{[^\{])*?\}\}~");
define ("BRACESPACE", "!BOTCODE-spaceBeforeTheBrace");

function categoryMembers($cat){
  $vars = Array(
    "cmtitle" => "Category:$cat", // Don't URLencode.
    "action" => "query",
    "cmlimit" => "500",
    "format" => "xml",
    "list" => "categorymembers",
  );
  $qc = "query-continue";

	do {
		set_time_limit(40);
    $res = load_xml_via_bot($vars);
  	if ($res) {
      foreach ($res->query->categorymembers->cm as $page) {
          $list[] = (string) $page["title"];
        }
    } else {
      echo 'Error reading API from ' . $url . "\n\n";
    }
	} while ($vars["cmcontinue"] = (string) $res->$qc->categorymembers["cmcontinue"]);
  return $list?$list:Array(" ");
}

// Returns an array; Array ("title1", "title2" ... );
function whatTranscludes($template, $namespace=99){
	$titles = whatTranscludes2($template, $namespace);
	return $titles["title"];
}

function wikititle_encode($in) {
  global $dotDecode, $dotEncode;
  return str_replace($dotDecode, $dotEncode, $in);
}

function getLastRev($page){
  $xml = load_xml_via_bot(Array(
      "action" => "query",
      "prop" => "revisions",
      "format" => "xml",
      "titles" => $page,
    ));
  return $xml->query->pages->page->revisions->rev["revid"];
}

function getPrefixIndex($prefix, $namespace = 0, $start = "") {
  global $bot;
  $continue = urlencode($start);
  $vars = Array ("action" => "query",
    "list" => "allpages",
    "format" => "xml",
    "apnamespace" => $namespace,
    "apprefix" => $prefix,
    "aplimit" => "5000",
  );
  do {
		set_time_limit(10);
    $res = load_xml_via_bot($vars);
    if ($res) {
      foreach ($res->query->allpages->p as $page) {
        $page_titles[] = (string) $page["title"];
        $page_ids[] = (integer) $page["pageid"];
      }
    } else {
      echo 'Error reading API from ' . $url;
    }
	} while ($vars["apfrom"] = (string) $res->{"query-continue"}->allpages["apfrom"]);
  set_time_limit(45);
  return $page_titles;
}

function getArticleId($page) {
  $xml = load_xml_via_bot(Array(
      "action" => "query",
      "format" => "xml",
      "prop" => "info",
      "titles" => $page,
      ));
  return $xml->query->pages->page["pageid"];
}

function getNamespace($page) {
	$xml = load_xml_via_bot(Array("action" => "query",
      "format" => "xml",
      "prop" => "info",
      "titles" => $page,
      ));
  return $xml->query->pages->page["ns"];
}

function isRedirect($page) {
  $url = Array(
      "action" => "query",
      "format" => "xml",
      "prop" => "info",
      "titles" => $page,
      );
  $xml = load_xml_via_bot($url);
	if ($xml->query->pages->page["pageid"]) {
    // Page exists
    return array ((($xml->query->pages->page["redirect"])?1:0),
                    $xml->query->pages->page["pageid"]);
    } else {
      return array (-1, null);
   }
}

function redirect_target($page) {
  $url = Array(
      "action" => "query",
      "format" => "xml",
      "redirects" => "1",
      "titles" => $page,
      );
  $xml = load_xml_via_bot($url);
  print_r($xml->query);
  return $xml->pages->page["title"];
}

function parse_wikitext($text, $title = "API") {
  $bot = new Snoopy();
  $bot->httpmethod="POST";
  $vars = array(
        'format' => 'json',
        'action' => 'parse',
        'text'   => $text,
        'title'  => $title,
    );
  $bot->submit(api, $vars);
  $a = json_decode($bot->results);
  if (!$a) {
    // Wait a sec and try again
    sleep(2);
    $bot->submit(api, $vars);
    $a = json_decode($bot->results);
  }
  return $a->parse->text->{"*"};
}

function articleID($page, $namespace = 0) {
  if (substr(strtolower($page), 0, 9) == 'template:'){
    $page = substr($page, 9);
    $namespace = 10;
  } else if (strpos($page, ':')) {
    // I'm too lazy to deduce the correct namespace prefix.
    return getArticleId($page);
  }
  $page = addslashes(str_replace(' ', '_', strtoupper($page[0]) . substr($page,1)));
  #$enwiki_db = udbconnect('enwiki_p', 'sql-s1');
  $enwiki_db = udbconnect('enwiki_p', 'sql-s1-fast');
  $result = mysql_query("SELECT page_id FROM page WHERE page_namespace='" . addslashes($namespace)
          . "' && page_title='$page'") or die (mysql_error());
  $results = mysql_fetch_array($result, MYSQL_ASSOC);
  mysql_close($enwiki_db);
  return $results['page_id'];
}

function getRawWikiText($page, $wait = false, $verbose = false, $use_daniel = true) {
  $encode_page = urlencode($page);
  print $verbose ? "\n scraping... " : "";
    // Get the text by scraping edit page
    $url = wikiroot . "title=" . $encode_page . "&action=raw";
    $contents = (string) @file_get_contents($url);
  if (!$contents && $use_daniel) {
    $url = "http://toolserver.org/~daniel/WikiSense/WikiProxy.php?wiki=en&title="
        . $encode_page . "&rev=&go=Fetch&token=";
    $contents = (string) file_get_contents($url);
    if (!$contents) {
      print $verbose ? "\n <br />Couldn't fetch $page; retrying" : "";
      // Retry if no response
      $contents = (string) @file_get_contents($url);
    }
    if ($wait && !$contents) {
      print $verbose ? "\n . " : "";
      // If still no response, wait & retry
      sleep(1);
      $contents = (string) @file_get_contents($url);
    }
    if (!$contents && $wait) {
      // If still no response, wait & retry
      print $verbose ? "\n ..... " : "";
      sleep(3);
      $contents = (string) @file_get_contents($url);
    }
  }
  return $contents;
}

function is_valid_user($user) {
  return ($user && getArticleId("User:$user"));
}

function whatTranscludes2($template, $namespace = 99) {
	$vars = Array (
      "action" => "query",
      "list" => "embeddedin",
      "eilimit" => "5000",
      "format" => "xml",
      "eititle" => "Template:" . $template,
      "einamespace" => ($namespace==99)?"":$namespace,
  );
	do {
		set_time_limit(20);
    $res = load_xml_via_bot($vars);
    print_r($res->query);
		if (!$res) {
      echo 'Error reading API from ' . $url . "\n";
    } else foreach($res->query->embeddedin->ei as $page) {
			$list["title"][] = (string) $page["title"];
			$list["id"][] = (integer) $page["pageid"];
		}
	} while ($vars["eicontinue"] = (string) $res->{"query-continue"}->embeddedin["eicontinue"]);
	return $list;
}

#### Functions below were written offline so need testing & debgging

// Extract template
// Pass the code to find the template in, and the name of the template (with spaces, not underscores, if appropriate)
function extract_template($code, $target) {
  $placeholder = "!-TEMPLATE PLACEHOLDER TP%s-!";
  $placeholder_regexp = "~$placeholder~";
  while (preg_match(template_regexp, $code, $match)) {
    ++$i;
    $template[$i] = $match[0];
    $template_name = str_replace("_", " ", trim($match[1]));

    if (strtolower($template_name) == strtolower($target)) {
      $return = $template[$i];
      while (preg_match(sprintf($placeholder_regexp, "(\d+)"), $return, $match)) {
        $template_n = $match[1];
        $return = preg_replace(sprintf($placeholder_regexp, $template_n), $template[$template_n], $return);
      }
      return $return;
    }

    $code = str_replace($template[$i], sprintf($placeholder, $i), $code);
  }
  return false;
}

// Extracts parameters in a Wikipedia template.
// Returns the parameters as an array (
// "parameter_name" => Array (value, equals sign, pipe)
// )
// Test cases should include comments with multiple pipes spanning multiple lines and including wikilinks

function extract_parameters($template) {
  // First, replace pipes that don't mark parameter boundaries with !-PIPE PLACEHOLDER-!
  $pipe_placeholder = "!-PIPE PLACEHOLDER pp-!";
  // This will include pipes in [[Wikilinks|]]:
  $wikilink_regexp = "~(\[\[[^\]]+)\|([^\]]+\]\])~";
  //  and in <!-- comments -->
  $comment_regexp = "~(<!--.*?)\|(.*?-->)~";

  // Remove whitespace and braces from template
  $template = trim($template);
  $template = substr($template, 2, strlen($template) - 4);
  if (preg_match ("~\s*$~", $template, $space_before_the_brace)) {
    $template = preg_replace("~\s*$~", "", $template);
    $parameters[BRACESPACE] = $space_before_the_brace;
  }

  // Replace pipes with placeholders in comments and links
  $template = preg_replace($wikilink_regexp, "$1$pipe_placeholder$2", $template);
  while (preg_match($comment_regexp, $template)) {
    $template = preg_replace($comment_regexp, "$1$pipe_placeholder$2", $template);
  }

  // Replace templates with placeholders
  $template_placeholder = "!-TEMPLATE PLACEHOLDER TP%s-!";
  $template_placeholder_regexp = "~$template_placeholder~";
  #$template_regexp = "~\{\{\s*[^\|\}]+([^\{]|\{[^\{]|\{\{[^/}]+\}\})*?\}\}~";

  while (preg_match(template_regexp, $template, $match)) {
    $subtemplate[++$i] = $match[0];
    $template = str_replace($subtemplate[$i], sprintf($template_placeholder, $i), $template);
  }
  $splits = preg_split("~(\s*\|\s*)~", $template, -1, PREG_SPLIT_DELIM_CAPTURE);

  // The first line doesn't contain a parameter; it's the template name
  $i = 0;
  foreach ($splits as $split) {
    ++$i;
    if ($i % 2) {
      $lines[$i / 2] = $split;
    } else {
      $pipe[($i+1) / 2] = $split;
    }
  }
  unset($lines[0]);

  $unnamed_parameter_count = 0;
  foreach ($lines as $i => $line) {
    preg_match("~^([^=]*)\b(\s*=\s*)?([\s\S]*)$~", $line, $match);
    if ($match[2]) {
      // then an equals sign is present; i.e. we have a named parameter
      $value = $match[3];
      $parameter_name = $match[1];
    } else {
      $value = $match[1];
      $parameter_name = "unnamed_parameter_" . ++$unnamed_parameter_count;
    }
    // Restore templates that were replaced with placeholders
    while (preg_match(sprintf($template_placeholder_regexp, "(\d+)"), $value, $sub_match)) {
      $template_n = $sub_match[1];
      $value = preg_replace(sprintf($template_placeholder_regexp, $template_n), $subtemplate[$template_n], $value);
    }
    $parameters[$parameter_name] = Array(str_replace($pipe_placeholder, "|", $value), $pipe[$i], $match[2]);
  }
  return $parameters;
}

// Transforms an array in "$p format" back into a template
function generate_template ($name, $parameters) {
  $output = '{{' . $name;
  $space_before_the_brace = $parameters[BRACESPACE][0];
  unset($parameters[BRACESPACE]);
  foreach ($parameters as $key => $value) {
    // Array (value, equals, pipe[, weight] )
    $output .= $value[1] . (substr($key, 0, 18) == "unnamed_parameter_" || $key=="0"?"":$key) . $value[2] . $value[0];
  }
  return $output . $space_before_the_brace . '}}';
}

function wikiLink($page, $style = "#036;", $target = null) {
  if (!$target) $target = $page;
  $css = $style?" style='color:$style !important'":"";
  return "<a href='" . wikiroot . "title=" . urlencode($target) . "' title='$page ($target) on Wikipedia'$css>$page</a>";
}

function geo_range_ok ($template) {
  $text = parse_wikitext ($template); // TODO check that this function returns the expected output
  return strpos($text, "Expression error:") ? false : true;
}

function load_xml_via_bot($vars) {
  $bot = new Snoopy();
  $bot->httpmethod = "POST";
  $bot->submit(api, $vars);
  return simplexml_load_string($bot->results);
}

function touch_page($page) {
  $text = getRawWikiText($page);
  if ($text) {
    global $editInitiator;
    write ($page, $text, $editInitiator . " Touching page to update categories.  This edit should not affect the page content.");
    return true;
  } else {
    return false;
  }
}