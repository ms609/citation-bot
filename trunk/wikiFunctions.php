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

function getLastRev($page){
  $xml = simplexml_load_file("http://en.wikipedia.org/w/api.php?action=query&prop=revisions&format=xml&titles=" . urlencode($page));
  return $xml->query->pages->page->revisions->rev["revid"];
}

function getArticleId($page){
	$xml = simplexml_load_file("http://en.wikipedia.org/w/api.php?action=query&format=xml&prop=info&titles=" . urlencode($page));
	return $xml->query->pages->page["pageid"];
}

function isRedirect($page){
	$xml = simplexml_load_file("http://en.wikipedia.org/w/api.php?action=query&format=xml&prop=info&titles=" . urlencode($page));
	return $xml->query->pages->page["redirect"];
}

function articleID($page, $namespace = 0){
  if (substr(strtolower($page), 0, 9) == 'template:'){
    $page = substr($page, 9);
    $namespace = 10;
  }
  if (strpos($page, ':')) {
    // I'm too lazy to deduce the correct namespace prefix.
    return getArticleId($page);
  }
  $page = addslashes(str_replace(' ', '_', strtoupper($page[0]) . substr($page,1)));
  $enwiki_db = udbconnect('enwiki_p', 'sql-s1');
  $result = mysql_query("SELECT page_id FROM page WHERE page_namespace='$namespace' && page_title='$page'") or die (mysql_error());
  $results = mysql_fetch_array($result, MYSQL_ASSOC);
  return $results['page_id'];
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