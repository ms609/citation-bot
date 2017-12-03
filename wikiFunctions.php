<?php
require_once('WikipediaBot.php');
global $api;
$api = new WikipediaBot();

function category_members($cat){
  global $api;
  
  $list = [];
  $vars = [
    "cmtitle" => "Category:$cat", // Don't URLencode.
    "action" => "query",
    "cmlimit" => "500",
    "format" => "xml",
    "list" => "categorymembers",
  ];
  
  do {
    set_time_limit(8);
    $res = $api->fetch($vars);
    if (isset($res->query->categorymembers)) {
      foreach ($res->query->categorymembers as $page) {
        $list[] = (string) $page->title;
      }
    } else {
      trigger_error('Error reading API from ' . htmlspecialchars($url) . "\n\n", E_USER_WARNING);
    }
    $vars["cmcontinue"] = isset($res->continue) ? $res->continue->cmcontinue : FALSE;
  } while ($vars["cmcontinue"]);
  return $list;
}

// Returns an array; Array ("title1", "title2" ... );
function what_transcludes($template, $namespace = 99){
	$titles = what_transcludes_2($template, $namespace);
	return $titles["title"];
}

function what_transcludes_2($template, $namespace = 99) {
  global $api;
  
  $vars = Array (
    "action" => "query",
    "list" => "embeddedin",
    "eilimit" => "5000",
    "format" => "xml",
    "eititle" => "Template:" . $template,
    "einamespace" => ($namespace==99)?"":$namespace,
  );
  $list = ['title' => NULL];
  
  do {
    set_time_limit(20);
    $res = $api->fetch($vars, 'POST');
    if (isset($res->query->embeddedin->ei)) {
      trigger_error('Error reading API from ' . htmlspecialchars($url), E_USER_NOTICE);
    } else {
      foreach($res->query->embeddedin as $page) {
        $list["title"][] = $page->title;
        $list["id"][] = $page->pageid;
      }
    }
    $vars["eicontinue"] = isset($res->continue) ? (string) $res->continue->eicontinue : FALSE;
  } while ($vars["eicontinue"]);
  return $list;
}

/**
 * Unused
 * @codeCoverageIgnore
 */
function wikititle_encode($in) {
  return str_replace(DOT_DECODE, DOT_ENCODE, $in);
}

function get_last_revision($page) {
  global $api;
  $res = $api->fetch(Array(
      "action" => "query",
      "prop" => "revisions",
      "format" => "xml",
      "titles" => $page,
    ));
  if (!isset($api->query->pages->page->revisions->rev)) {
      echo "\n Failed to get article last revision \n";
      return FALSE;
  }
  return $api->query->pages->page->revisions->rev["revid"];
}

function get_prefix_index($prefix, $namespace = 0, $start = "") {
  global $api;
  $page_titles = [];
  # $page_ids = [];
  $vars["apfrom"] = $start;
  $vars = ["action" => "query",
    "list" => "allpages",
    "format" => "xml",
    "apnamespace" => $namespace,
    "apprefix" => $prefix,
    "aplimit" => "500",
  ];
  
  do {
    set_time_limit(10);
    $res = $api->fetch($vars, 'POST');
    if ($res && !isset($res->error) && isset($res->query->allpages)) {
      foreach ($res->query->allpages as $page) {
        $page_titles[] = $page->title;
        # $page_ids[] = $page->pageid;
      }
    } else {
      trigger_error('Error reading API with vars ' . http_build_query($vars), E_USER_NOTICE);
      if (isset($res->error)) echo $res->error;
    }
    $vars["apfrom"] = isset($res->continue) ? $res->continue->apcontinue : FALSE;
  } while ($vars["apfrom"]);
  set_time_limit(45);
  return $page_titles;
}

/**
 * Unused
 * @codeCoverageIgnore
 */
function get_article_id($page) {
  global $api;
  $res = $api->fetch([
      "action" => "query",
      "format" => "xml",
      "prop" => "info",
      "titles" => $page,
      ], 'POST');
  var_dump($res); die;
  if (!isset($res->query->pages->page)) {
      echo "\n Failed to get article ID \n";
      return FALSE;
  }
  return $res->query->pages->page["pageid"];
}

function get_namespace($page) {
  global $api;
  $res = $api->fetch([
      "action" => "query",
      "format" => "xml",
      "prop" => "info",
      "titles" => $page,
      ]); 
  if (!isset($res->query->pages)) {
      echo "\n Failed to get article namespace \n";
      return FALSE;
  }
  return (int) reset($res->query->pages)->ns;
}

function is_redirect($page) {
  global $api;
  $res = $api->fetch(Array(
      "action" => "query",
      "format" => "xml",
      "prop" => "info",
      "titles" => $page,
      ), 'POST');
  
  if (!isset($res->query->pages)) {
      echo "\n Failed to get redirect status \n";
      return -1;
  }
  $res = reset($res->query->pages);
  return (isset($res->pageid)) ? $res->pageid : -1;
}

/**
 * Unused
 * @codeCoverageIgnore
 */
function redirect_target($page) {
  global $api;
  $res = $api->fetch(Array(
      "action" => "query",
      "format" => "xml",
      "redirects" => "1",
      "titles" => $page,
      ), 'POST');
  if (!isset($res->pages->page)) {
      echo "\n Failed to get redirect target \n";
      return FALSE;
  }
  return $xml->pages->page["title"];
}

/**
 * Unused
 * @codeCoverageIgnore
 */
function parse_wikitext($text, $title = "API") {
  global $api;
  $vars = array(
        'format' => 'json',
        'action' => 'parse',
        'text'   => $text,
        'title'  => $title,
    );
  $res = $api->fetch($vars, 'POST');
  if (!$res) {
    // Wait a sec and try again
    sleep(2);
    $res = $api->fetch($vars, 'POST');
  }
  if (!isset($res->parse->text)) {
    trigger_error("Could not parse text of $title.", E_USER_WARNING);
    return FALSE;
  }
  return $res->parse->text->{"*"};
}

function namespace_id($name) {
  $lc_name = strtolower($name);
  return array_key_exists($lc_name, NAMESPACE_ID) ? NAMESPACE_ID[$lc_name] : NULL;
}

function namespace_name($id) {
  return array_key_exists($id, NAMESPACES) ? NAMESPACES[$id] : NULL;
}

// TODO mysql login is failing.
/*
 * unused
 * @codeCoverageIgnore
 */
function article_id($page, $namespace = 0) {
  if (stripos($page, ':')) {
    $bits = explode(':', $page);
    if (isset($bits[2])) return NULL; # Too many colons; improperly formatted page name?
    $namespace = namespace_id($bits[0]);
    if (is_null($namespace)) return NULL; # unrecognized namespace
    $page = $bits[1];
  }
  $page = addslashes(str_replace(' ', '_', strtoupper($page[0]) . substr($page,1)));
  $enwiki_db = udbconnect('enwiki_p', 'enwiki.labsdb');
  if (defined('PHP_VERSION_ID') && (PHP_VERSION_ID >= 50600)) { 
     $result = NULL; // mysql_query does not exist in PHP 7
  } else {
     $result = @mysql_query("SELECT page_id FROM page WHERE page_namespace='" . addslashes($namespace)
          . "' && page_title='$page'");
  }
  if (!$result) {
	  echo @mysql_error();
	  @mysql_close($enwiki_db);
	  return NULL;
  }
  $results = @mysql_fetch_array($result, MYSQL_ASSOC);
  @mysql_close($enwiki_db);
  if (!$results) return NULL;
  return $results['page_id'];
}

function is_valid_user($user) {
  if (!$user) return FALSE;
  $headers_test = @get_headers('https://en.wikipedia.org/wiki/User:' . urlencode($user), 1);
  if ($headers_test === FALSE) return FALSE;
  if (strpos((string) $headers_test[0], '404')) return FALSE;  // Even non-existant pages for valid users do exist.  They redirect, but do exist
  return TRUE;
}

/**
 * Unused
 * @codeCoverageIgnore
 */
function wiki_link($page, $style = "#036;", $target = NULL) {
  if (!$target) $target = $page;
  $css = $style?" style='color:$style !important'":"";
  return "<a href='" . WIKI_ROOT . "?title=" . urlencode($target) . "' title='$page ($target) on Wikipedia'$css>$page</a>";
}

/**
 * Unused
 * @codeCoverageIgnore
 */
function touch_page($page) {
  $text = get_raw_wikitext($page);
  if ($text) {
    write ($page, $text, " Touching page to update categories.  ** THIS EDIT SHOULD PROBABLY BE REVERTED ** as page content will only be changed if there was an edit conflict.");
    return TRUE;
  } else {
    return FALSE;
  }
}
