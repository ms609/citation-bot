<?php
function categoryMembers($cat){
	$url="http://en.wikipedia.org/w/api.php?cmtitle=Category:$cat&action=query&cmlimit=500&format=xml&list=categorymembers";
	$qc = "query-continue";
	do{
		set_time_limit(40);
		if ($_GET["debug"]) print "$continue<br>\n";
		if (!$res=simplexml_load_file($url.($continue?("&cmcontinue=" . urlencode($continue)):""))) echo 'Error reading API from '.$url.$continue?"&cmcontinue=$continue":"";
		else foreach($res->query->categorymembers->cm as $page) $list[]=$page["title"];
	} while ($continue = $res->$qc->categorymembers["cmcontinue"]);
	return $list?$list:Array(null);
}

function whatTranscludes($template, $namespace=99){
	$titles= whatTranscludes2($template, $namespace);
	return $titles["title"];
}

function getArticleId($page){
	$xml = simplexml_load_file("http://en.wikipedia.org/w/api.php?action=query&format=xml&prop=info&titles=" . urlencode($page));
	return $xml->query->pages->page["pageid"]; 
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