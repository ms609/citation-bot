<?php
// $Id: $

function categoryMembers($cat){
  //print "Category: $cat\n";
  // rm restore 5 to 500.

  $url="http://en.wikipedia.org/w/api.php?cmtitle=Category:$cat&action=query&cmlimit=5&format=xml&list=categorymembers";
	$qc = "query-continue";

	do {
		set_time_limit(40);
    $res = simplexml_load_file($url . ($continue?("&cmcontinue=" . urlencode($continue)):""));
  	if ($res) {
      foreach ($res->query->categorymembers->cm as $page) {
          $list[] = (string) $page["title"];
        }
    } else {
      echo 'Error reading API from ' . $url . ($continue?"&cmcontinue=$continue":"") . "\n\n";
    }
	} while ($continue = $res->$qc->categorymembers["cmcontinue"]);
	return $list?$list:Array(" ");
}

function whatTranscludes($template, $namespace=99){
	$titles= whatTranscludes2($template, $namespace);
	return $titles["title"];
}

function wikititle_encode($in) {
  global $dotDecode, $dotEncode;
  return str_replace($dotDecode, $dotEncode, $in);
}

function getLastRev($page){
  $xml = simplexml_load_file("http://en.wikipedia.org/w/api.php?action=query&prop=revisions&format=xml&titles=" . urlencode($page));
  return $xml->query->pages->page->revisions->rev["revid"];
}

function getArticleId($page){
	$xml = simplexml_load_file("http://en.wikipedia.org/w/api.php?action=query&format=xml&prop=info&titles=" . urlencode($page));
  return $xml->query->pages->page["pageid"];
}

function getNamespace($page){
	$xml = simplexml_load_file("http://en.wikipedia.org/w/api.php?action=query&format=xml&prop=info&titles=" . urlencode($page));
  return $xml->query->pages->page["ns"];
}

function isRedirect($page){
	$xml = simplexml_load_file("http://en.wikipedia.org/w/api.php?action=query&format=xml&prop=info&titles=" . urlencode($page));
	if ($xml->query->pages->page["pageid"]) {
    // Page exists
    return ($xml->query->pages->page["redirect"])?1:0;
    }
    else {
      return -1;
   }
}

function articleID($page, $namespace = 0) {
  if (substr(strtolower($page), 0, 9) == 'template:'){
    $page = substr($page, 9);
    $namespace = 10;
  }
  if (strpos($page, ':')) {
    // I'm too lazy to deduce the correct namespace prefix.
    return getArticleId($page);
  }
  $page = addslashes(str_replace(' ', '_', strtoupper($page[0]) . substr($page,1)));
  #$enwiki_db = udbconnect('enwiki_p', 'sql-s1');
  $enwiki_db = udbconnect('enwiki_p', 'sql-s1-fast');
  $result = mysql_query("SELECT page_id FROM page WHERE page_namespace='$namespace' && page_title='$page'") or die (mysql_error());
  $results = mysql_fetch_array($result, MYSQL_ASSOC);
  mysql_close($enwiki_db);
  return $results['page_id'];
}

function citation_is_redirect ($type, $id) {
  return (isRedirect("Template:Cite $type/$id")); ## TODO
  $db = udbconnect("yarrow");
  $result = mysql_query("SELECT $type, redirect FROM cite_$type WHERE $type='$id'");
  $results = mysql_fetch_array($result, MYSQL_ASSOC);
  mysql_close();
  if ($result) {
    if ($results) {
      return $results["redirect"]?1:0;
    } else {
      return -1;
    }
  } else {
    // On error consult wikipedia API
    return (isRedirect("Template:Cite $type/$id"));
  }
}

function log_citation ($type, $source, $target = false) {
  return false; // TODO
  $db = udbconnect("yarrow");
  $sql = "INSERT INTO cite_$type SET $type='$source'" . ($type=="doi"?"":", redirect='$target'");
  print $sql . "\n";
  $result = mysql_query($sql) or die (mysql_error());
  mysql_close();
  die (mysql_query($sql) or die (mysql_error()));
  return mysql_query($sql) or die (mysql_error());
}

function getRawWikiText($page) {
  return file_get_contents("http://toolserver.org/~daniel/WikiSense/WikiProxy.php?wiki=en&title=$page&rev=&go=Fetch&token=");
}

function whatTranscludes2($template, $namespace=99){
	$url = "http://en.wikipedia.org/w/api.php?action=query&list=embeddedin&eilimit=500&format=xml&eititle=Template:$template" . (($namespace==99)?"":"&einamespace=$namespace");
	$qc = "query-continue";
	if ($_GET["debug"]) print_r($res);
	do{
		set_time_limit(20);
		if (!$res=simplexml_load_file($url.($continue?"&eicontinue=$continue":""))) echo 'Error reading API from' .$url.($continue?"&cmcontinue=$continue":"");
		foreach($res->query->embeddedin->ei as $page) {
			$list["title"][]=$page["title"];
			$list["id"][]=$page["pageid"];
		}
	} while ($continue = $res->$qc->embeddedin["eicontinue"]);
	return $list;
}
/*  The following function appears in ~/public_html/res/mysql_connect.php and is reproduced here for SVN safekeeping.
function udbconnect($dbName, $server = "sql") {
        // fix redundant error-reporting
        $errorlevel = ini_set('error_reporting','0');

        // connect
        $mycnf = parse_ini_file("/home/".get_current_user()."/.my.cnf");
        $username = $mycnf['user'];
        $password = $mycnf['password'];
        unset($mycnf);
        $db = mysql_connect($server, $username, $password) or die("\n!!! * Database server login failed.\n This is probably a temporary problem with the server and will hopefully be fixed soon.  The server returned: \"" . mysql_error() . "\"  \nError message generated by /res/mysql_connect.php\n");
        unset($username);
        unset($password);

        // select database
        if($db && $server == "sql") {
           mysql_select_db(str_replace('-','_',"u_verisimilus_$dbName")) or print "\nDatabase connection failed: " . mysql_error() . "";
        } else if ($db) {
           mysql_select_db($dbName) or die(mysql_error());
        } else {
          die ("\nNo DB selected!\n");
        }

        // restore error-reporting
        ini_set('error-reporting',$errorlevel);

				return ($db);
}
*/