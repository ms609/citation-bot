<?php
if ($linkto2) print "\n// included expandFns2";
function includeIfNew($path, $file){
	// include missing files
	$alreadyIn = get_included_files();
	foreach ($alreadyIn as $include){
		if (strstr($include, $file)) return false;
	}
	if ($GLOBALS["linkto2"]) echo "\n// including $file";
	require_once($path . $file . $GLOBALS["linkto2"] . ".php");
	return true;
}
require_once("/home/verisimilus/public_html/Bot/DOI_bot/doiBot$linkto2.login");
# Snoopy should be set so the host name is en.wikipedia.org.
includeIfNew("/home/verisimilus/public_html/Bot/", "wikiFunctions");
includeIfNew("/home/verisimilus/public_html/", "DOItools");
require_once("/home/verisimilus/public_html/res/mysql_connect.php");
$db = udbconnect("yarrow");
if(!true && !myIP()) {
	print "Sorry, the Citation bot is temporarily unavilable while bugs are fixed.  Please try back later."; exit;
}

#Yahoo Application ID
$yAppId = "wLWQRfDV34GGTxHoNZjroF_m94yRvVD_eGRA9KKFhPZsE4rAXNGOih3eCrI9Eh3ewBa6Ccqg";

//Google AppId
#$gAppId = "ABQIAAAAsqKZCEjzSKO3mjAh0efRehT5mbzX3Oi5P88WWtRyN9u9YXZnqRT56kmFtGXDpeNI_FTpsOOoAuCoFA";
# Above ID is for /~ms609; below is for /Wiki/Bot
$gAppId = "ABQIAAAAsqKZCEjzSKO3mjAh0efRehQrFKyE8YGyge8HxpDYaz1oDCwgkBTqu-eqTpVxlupEyuIYijuXU6B-aw";
$crossRefId=CROSSREFUSERNAME.":".CROSSREFPASSWORD;
$isbnKey = "268OHQMW";
$isbnKey2 = "268OHQMW";
$bot = new Snoopy();

mb_internal_encoding( 'UTF-8' ); // Avoid ??s

define("debugon", $_GET["debug"]);
define("restrictedDuties", !true);
define("editinterval", 10);
define("pipePlaceholder", "doi_bot_pipe_placeholder"); #4 when online...
define("wikiroot", "http://en.wikipedia.org/w/index.php?");
//define("doiRegexp", "(10\.\d{4}/([^\s;\"\?&<])*)(?=[\s;\"\?&]|</)");
#define("doiRegexp", "(10\.\d{4}(/|%2F)[^\s\"\?&]*)(?=[\s\"\?&]|</)"); //Note: if a DO I is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.

//Common replacements
$doiIn = array("[", 			"]", 			"<", 			">"	,			"&#60;!", 	"-&#62;",		"%2F"	);
$doiOut = array("&#x5B;", "&#x5D;", "&#60;",  "&#62;", 	"<!",  			"->", 			"/"	);

$pcDecode = array("[", 			"]", 			"<", 			">");
$pcEncode = array("&#x5B;", "&#x5D;", "&#60;",  "&#62;");

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

//Optimisation
#ob_start(); //Faster, but output is saved until page finshed.
ini_set("memory_limit","256M");

$searchGoogle=$_REQUEST["google"];
$searchYahoo=$_REQUEST["yahoo"];
$searchDepth=$_REQUEST["depth"];
$fastMode=$_REQUEST["fast"];
$slowMode=$_REQUEST["slow"];
//$user = $_REQUEST["user"];
$bugFix = $_REQUEST["bugfix"];
$crossRefOnly = $_REQUEST["crossrefonly"]?true:$_REQUEST["turbo"];

if ($_REQUEST["edit"] || $_GET["doi"] || $_GET["pmid"]) $ON = true;

$editSummaryStart = ($bugFix?"Double-checking that a [[User:DOI_bot/bugs|bug]] has been fixed. ":"Citation maintenance. ");
$editSummaryEnd = (isset($user)?" Initiated by [[User:$user|$user]].":"")
						.	" You can [[WP:UCB|use this bot]] yourself! Please [[User:DOI_bot/bugs|report any bugs]].";

ob_flush();


################ Functions ##############

function updateBacklog($page){
	$sPage = addslashes($page);
	$id = getArticleId($page);
	global $db;
	$result = mysql_query("SELECT page FROM citation WHERE id = '$id'") or die (mysql_error());
	$result = mysql_fetch_row($result);
	$sql = $result?"UPDATE citation SET fast = '" . date ("c") . "' WHERE page = '$sPage'":"INSERT INTO citation VALUES ('".
		getArticleId($page) . "', '$sPage', '" . date ("c") . "', '0000-00-00')";
	#print "<p>$sql</p>";
	$result = mysql_query ($sql) or die(mysql_error());
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

function logIn($username, $password) {
	
	global $bot;	
	$loginUrl = wikiroot . "title=Special:Userlogin&action=submitlogin&type=login";
	$submit_vars["wpName"] = $username;
	$submit_vars["wpPassword"] = $password;
	$submit_vars["wpRemember"] = 1;
	$submit_vars["wpLoginattempt"] = "Log+in";
	
	$bot->submit($loginUrl, $submit_vars);
	if (ereg("You have successfully signed in to Wikipedia", $bot->results)) return true;
	return;
}

function inputValue($tag, $form) {
	//Gets the value of an input, if the input's in the right format.
	preg_match("~value=\"([^\"]*)\" name=\"$tag\"~", $form, $name);
	if ($name) return $name[1];
	preg_match("~name=\"$tag\" value=\"([^\"]*)\"~", $form, $name);
	if ($name) return $name[1];
	return false;
}

function write($page, $data, $editsummary = "Bot edit") {

	global $bot;

	//Load edit page so we can scrape starttimes
	$editUrl = wikiroot . "title=" . urlencode($page) . "&action=edit";

	$bot->fetchform($editUrl);
	$form = $bot->results;
		
	//Set our post vars to the values of the inputs:
	$submit_vars["wpEdittime"] = inputValue("wpEdittime", $form);
	$submit_vars["wpStarttime"] = inputValue("wpStarttime", $form);
	$submit_vars["wpEditToken"] = inputValue("wpEditToken", $form);
	
	
	if (!$submit_vars["wpEditToken"]) return false; // Couldn't obtain input value.  Is the page protected?
	
	// The less glamorous post vars also need setting:
	$submit_vars["wpScrollTop"] = "0";
	$submit_vars["wpSection"] = "";
	$submit_vars["wpTextbox1"] = $data;
	$submit_vars["wpSummary"] = $editsummary;
	$submit_vars["wpMinoredit"] = 1;
	$submit_vars["wpWatchthis"] = 0;
	
	// Set this var to determine the action - Save page!
	$submit_vars["wpSave"] = "Save+page";
	
	$submitUrl = wikiroot . "title=" . urlencode($page) . "&action=submit";
	
	return $bot->submit($submitUrl, $submit_vars);
}

function noteDoi($doi, $src){
	echo "<h3 style='color:coral;'>Found <a href='http://dx.doi.org/$doi'>DOI</a> $doi from $src.</h3>";
}

function isDoiBroken ($doi, $p = false){
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
	switch ($code[0]){
		case false: 
			$parsed = parse_url("http://dx.doi.org/$doi");
			$host = $parsed["host"];
			$fp = @fsockopen($host, 80, $errno, $errstr, 20);
			if ($fp) return false; // Page exists, but had timed out when we first tried.
			logBrokenDoi($doi, $p, 404); 
			return 404; // DOI is correct but points to a dead page
		case 200: 
			if ($p["url"][0]) {
				$ch = curl_init();
				curlSetup($ch, $p["url"][0]);
				$content = curl_exec($ch);
				if (!preg_match("~\Wwiki(\W|pedia)~", $content)	&& preg_match("~" . preg_quote(urlencode($doi)) . "~", urlencode($content))) {
					logBrokenDoi($doi, $p, 200); 
					return 200; // DOI is present in page, so probably correct
				} else return 999; // DOI could not be found in URL - or URL is a wiki mirror
			}	else return 100; // No URL to check for DOI
	}
	return false;
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

logIn(USERNAME, PASSWORD);
?>