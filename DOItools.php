<?php
global $bot;
$bot = new Snoopy();

function list_parameters() { // Lists the parameters in order.
    return Array(
     "null",
     "author", "author1", "last", "last1", "first", "first1", "authorlink", "authorlink1", "author1-link",
     "coauthors", "author2", "last2", "first2", "authorlink2", "author2-link",
     "author3", "last3", "first3", "authorlink3", "author3-link",
     "author4", "last4", "first4", "authorlink4", "author4-link",
     "author5", "last5", "first5", "authorlink5", "author5-link",
     "author6", "last6", "first6", "authorlink6", "author6-link",
     "author7", "last7", "first7", "authorlink7", "author7-link",
     "author8", "last8", "first8", "authorlink8", "author8-link",
     "author9", "last9", "first9", "authorlink9", "author9-link",
     "author10", "last10", "first10", "authorlink10", "author10-link",
     "author11", "last11", "first11", "authorlink11", "author11-link",
     "author12", "last12", "first12", "authorlink12", "author12-link",
     "author13", "last13", "first13", "authorlink13", "author13-link",
     "author14", "last14", "first14", "authorlink14", "author14-link",
     "author15", "last15", "first15", "authorlink15", "author15-link",
     "author16", "last16", "first16", "authorlink16", "author16-link",
     "author17", "last17", "first17", "authorlink17", "author17-link",
     "author18", "last18", "first18", "authorlink18", "author18-link",
     "author19", "last19", "first19", "authorlink19", "author19-link",
     "author20", "last20", "first20", "authorlink20", "author20-link",
     "author21", "last21", "first21", "authorlink21", "author21-link",
     "author22", "last22", "first22", "authorlink22", "author22-link",
     "author23", "last23", "first23", "authorlink23", "author23-link",
     "author24", "last24", "first24", "authorlink24", "author24-link",
     "author25", "last25", "first25", "authorlink25", "author25-link",
     "author26", "last26", "first26", "authorlink26", "author26-link",
     "author27", "last27", "first27", "authorlink27", "author27-link",
     "author28", "last28", "first28", "authorlink28", "author28-link",
     "author29", "last29", "first29", "authorlink29", "author29-link",
     "author30", "last30", "first30", "authorlink30", "author30-link",
     "author31", "last31", "first31", "authorlink31", "author31-link",
     "author32", "last32", "first32", "authorlink32", "author32-link",
     "author33", "last33", "first33", "authorlink33", "author33-link",
     "author34", "last34", "first34", "authorlink34", "author34-link",
     "author35", "last35", "first35", "authorlink35", "author35-link",
     "author36", "last36", "first36", "authorlink36", "author36-link",
     "author37", "last37", "first37", "authorlink37", "author37-link",
     "author38", "last38", "first38", "authorlink38", "author38-link",
     "author39", "last39", "first39", "authorlink39", "author39-link",
     "author40", "last40", "first40", "authorlink40", "author40-link",
     "author41", "last41", "first41", "authorlink41", "author41-link",
     "author42", "last42", "first42", "authorlink42", "author42-link",
     "author43", "last43", "first43", "authorlink43", "author43-link",
     "author44", "last44", "first44", "authorlink44", "author44-link",
     "author45", "last45", "first45", "authorlink45", "author45-link",
     "author46", "last46", "first46", "authorlink46", "author46-link",
     "author47", "last47", "first47", "authorlink47", "author47-link",
     "author48", "last48", "first48", "authorlink48", "author48-link",
     "author49", "last49", "first49", "authorlink49", "author49-link",
     "author50", "last50", "first50", "authorlink50", "author50-link",
     "author51", "last51", "first51", "authorlink51", "author51-link",
     "author52", "last52", "first52", "authorlink52", "author52-link",
     "author53", "last53", "first53", "authorlink53", "author53-link",
     "author54", "last54", "first54", "authorlink54", "author54-link",
     "author55", "last55", "first55", "authorlink55", "author55-link",
     "author56", "last56", "first56", "authorlink56", "author56-link",
     "author57", "last57", "first57", "authorlink57", "author57-link",
     "author58", "last58", "first58", "authorlink58", "author58-link",
     "author59", "last59", "first59", "authorlink59", "author59-link",
     "author60", "last60", "first60", "authorlink60", "author60-link",
     "author61", "last61", "first61", "authorlink61", "author61-link",
     "author62", "last62", "first62", "authorlink62", "author62-link",
     "author63", "last63", "first63", "authorlink63", "author63-link",
     "author64", "last64", "first64", "authorlink64", "author64-link",
     "author65", "last65", "first65", "authorlink65", "author65-link",
     "author66", "last66", "first66", "authorlink66", "author66-link",
     "author67", "last67", "first67", "authorlink67", "author67-link",
     "author68", "last68", "first68", "authorlink68", "author68-link",
     "author69", "last69", "first69", "authorlink69", "author69-link",
     "author70", "last70", "first70", "authorlink70", "author70-link",
     "author71", "last71", "first71", "authorlink71", "author71-link",
     "author72", "last72", "first72", "authorlink72", "author72-link",
     "author73", "last73", "first73", "authorlink73", "author73-link",
     "author74", "last74", "first74", "authorlink74", "author74-link",
     "author75", "last75", "first75", "authorlink75", "author75-link",
     "author76", "last76", "first76", "authorlink76", "author76-link",
     "author77", "last77", "first77", "authorlink77", "author77-link",
     "author78", "last78", "first78", "authorlink78", "author78-link",
     "author79", "last79", "first79", "authorlink79", "author79-link",
     "author80", "last80", "first80", "authorlink80", "author80-link",
     "author81", "last81", "first81", "authorlink81", "author81-link",
     "author82", "last82", "first82", "authorlink82", "author82-link",
     "author83", "last83", "first83", "authorlink83", "author83-link",
     "author84", "last84", "first84", "authorlink84", "author84-link",
     "author85", "last85", "first85", "authorlink85", "author85-link",
     "author86", "last86", "first86", "authorlink86", "author86-link",
     "author87", "last87", "first87", "authorlink87", "author87-link",
     "author88", "last88", "first88", "authorlink88", "author88-link",
     "author89", "last89", "first89", "authorlink89", "author89-link",
     "author90", "last90", "first90", "authorlink90", "author90-link",
     "author91", "last91", "first91", "authorlink91", "author91-link",
     "author92", "last92", "first92", "authorlink92", "author92-link",
     "author93", "last93", "first93", "authorlink93", "author93-link",
     "author94", "last94", "first94", "authorlink94", "author94-link",
     "author95", "last95", "first95", "authorlink95", "author95-link",
     "author96", "last96", "first96", "authorlink96", "author96-link",
     "author97", "last97", "first97", "authorlink97", "author97-link",
     "author98", "last98", "first98", "authorlink98", "author98-link",
     "author99", "last99", "first99", "authorlink99", "author99-link",
     "editor", "editor1",
     "editor-last", "editor1-last",
     "editor-first", "editor1-first",
     "editor-link", "editor1-link",
     "editor2", "editor2-author", "editor2-first", "editor2-link",
     "editor3", "editor3-author", "editor3-first", "editor3-link",
     "editor4", "editor4-author", "editor4-first", "editor4-link",
     "editor5", "editor5-author", "editor5-first", "editor5-link",
     "editor6", "editor6-author", "editor6-first", "editor6-link",
     "editor7", "editor7-author", "editor7-first", "editor7-link",
     "editor8", "editor8-author", "editor8-first", "editor8-link",
     "editor9", "editor9-author", "editor9-first", "editor9-link",
     "editor10", "editor10-author", "editor10-first", "editor10-link",
     "editor11", "editor11-author", "editor11-first", "editor11-link",
     "editor12", "editor12-author", "editor12-first", "editor12-link",
     "editor13", "editor13-author", "editor13-first", "editor13-link",
     "editor14", "editor14-author", "editor14-first", "editor14-link",
     "others",
     "chapter", "trans_chapter",  "chapterurl",
     "title", "trans_title", "language",
     "url",
     "archiveurl",
     "archivedate",
     "format",
     "accessdate",
     "edition",
     "series",
     "journal",
     "volume",
     "issue",
     "page",
     "pages",
     "nopp",
     "publisher",
     "location",
     "date",
     "origyear",
     "year",
     "month",
     "location",
     "language",
     "isbn",
     "issn",
     "oclc",
     "pmid", "pmc",
     "doi",
     "doi_brokendate",
     "bibcode",
     "id",
     "quote",
     "ref",
     "laysummary",
     "laydate",
     "separator",
     "postscript",
     "authorauthoramp",
   );
}

function bubble_p ($a, $b) {
  return ($a["weight"] > $b["weight"]) ? 1 : -1;
}

function is($key){
	global $p;
  return ("" != trim($p[$key][0])) ? true : false;
}

function dbg($array, $key = false) {
  if(myIP()) {
    if($key) {
      echo "<pre>" . htmlspecialchars(print_r(array($key=>$array),1)) . "</pre>";
    } else {
      echo "<pre>" . htmlspecialchars(print_r($array,1)) . "</pre>";
    }
  } else {
    echo "<p>Debug mode active</p>";
  }
}

function myIP() {
	switch ($_SERVER["REMOTE_ADDR"]){
		case "1":
    case "":
		case "86.6.164.132":
		case "99.232.120.132":
		case "192.75.204.31":
		return true;
		default: return false;
	}
}

/* jrTest - tests a name for a Junior appelation
 *  Input: $name - the name to be tested
 * Output: array ($name without Jr, if $name ends in Jr, Jr)
 */
function jrTest($name) {
  $junior = (substr($name, -3) == " Jr")?" Jr":false;
  if ($junior) {
    $name = substr($name, 0, -3);
  } else {
    $junior = (substr($name, -4) == " Jr.")?" Jr.":false;
    if ($junior) {
      $name = substr($name, 0, -4);
    }
  }
  if (substr($name, -1) == ",") {
    $name = substr($name, 0, -1);
  }
  return array($name, $junior);
}

function deWikify($string){
	return str_replace(Array("[", "]", "'''", "''", "&"), Array("", "", "'", "'", ""), preg_replace(Array("~<[^>]*>~", "~\&[\w\d]{2,7};~", "~\[\[[^\|\]]*\|([^\]]*)\]\]~"), Array("", "", "$1"),  $string));
}

function truncatePublisher($p){
	return preg_replace("~\s+(group|inc|ltd|publishing)\.?\s*$~i", "", $p);
}

function formatSurname($surname) {
  $surname = mb_convert_case(trim(mb_ereg_replace("-", " - ", $surname)), MB_CASE_LOWER);
  if (mb_substr($surname, 0, 2) == "o'") return "O'" . fmtSurname2(mb_substr($surname, 2));
	else if (mb_substr($surname, 0, 2) == "mc") return "Mc" . fmtSurname2(mb_substr($surname, 2));
	else if (mb_substr($surname, 0, 3) == "mac" && strlen($surname) > 5 && !mb_strpos($surname, "-") && mb_substr($surname, 3, 1) != "h") return "Mac" . fmtSurname2(mb_substr($surname, 3));
	else if (mb_substr($surname, 0, 1) == "&") return "&" . fmtSurname2(mb_substr($surname, 1));
	else return fmtSurname2($surname); // Case of surname
}
function fmtSurname2($surname) {
  $ret = preg_replace_callback("~(\p{L})(\p{L}+)~u", 
          create_function('$matches',
                  'return mb_strtoupper($matches[1]) . mb_strtolower($matches[2]);'
          ),
          mb_ereg_replace(" - ", "-", $surname));
  $ret = str_ireplace(array('Von ', 'Und ', 'De La '), array('von ', 'und ', 'de la '), $ret);
  $ret = preg_replace_callback('~;\w~', create_function('$matches', 'return strtolower($matches[0]);'), $ret);
  return $ret;
}

function formatForename($forename){
  return str_replace(array(" ."), "", trim(preg_replace_callback("~(\p{L})(\p{L}{3,})~u",  create_function(
            '$matches',
            'return mb_strtoupper($matches[1]) . mb_strtolower($matches[2]);'
        ), $forename)));
}

/* formatInitials
 *
 * Returns a string of initals, formatted for Cite Doi output
 *
 * $str: A series of initials, in any format.  NOTE! Do not pass a forename here!
 *
 */
function formatInitials($str){
	$str = trim($str);
	if ($str == "") return false;
	if (substr($str, strlen($str)-1) == ";") $end = ";";
	preg_match_all("~\w~", $str, $match);
	return mb_strtoupper(implode(".",$match[0]) . ".") . $end;
}
function isInitials($str){
	if (!$str) return false;
	if (strlen(str_replace(array("-", ".", ";"), "", $str)) >3) return false;
	if (strlen(str_replace(array("-", ".", ";"), "", $str)) ==1) return true;
	if (mb_strtoupper($str) != $str) return false;
	return true;
}

// Runs some tests to see if the full  name of a single author is unlikely to be the name of a person.
function authorIsHuman($author) {
  $author = trim($author);
  $chars = count_chars($author);
  if ($chars[ord(":")] > 0 || $chars[ord(" ")] > 3 || strlen($author) > 33) {
    return false;
  }
  return true;
}

// Returns the author's name formated as Surname, F.I.
function formatAuthor($author){

	// Requires an author who is formatted as SURNAME, FORENAME or SURNAME FORENAME or FORENAME SURNAME. Substitute initials for forenames if nec.

	$author = preg_replace("~(^[;,.\s]+|[;,.\s]+$)~", "", trim($author)); //Housekeeping
  $author = preg_replace("~^[aA]nd ~", "", trim($author)); // Just in case it has been split from a Smith; Jones; and Western
	if ($author == "") {
      return false;
  }

	$auth = explode(",", $author);
	if ($auth[1]){
		/* Possibilities:
		Smith, A. B.
		*/
		$surname = $auth[0];
		$fore = $auth[1];
	}
	//Otherwise we've got no handy comma to separate; we'll have to use spaces and periods.
	else {
		$auth = explode(".", $author);
		if (isset($auth[1])){
			/* Possibilities are:
			M.A. Smith
			Smith M.A.
			Smith MA.
			Martin A. Smith
			MA Smith.
			Martin Smith.
			*/
			$countAuth = count($auth);
			if (!$auth[$countAuth-1]) {
				$i = array();
				// it ends in a .
				if (isInitials($auth[$countAuth-1])) {
					// it's Conway Morris S.C.
					foreach (explode(" ", $auth[0]) as $bit){
						if (isInitials($bit)) $i[] = formatInitials($bit); else $surname .= "$bit ";
					}
					unset($auth[0]);
					foreach ($auth as $bit){
						if (isInitials($bit)) $i[] = formatInitials($bit);
					}
				} else {
					foreach ($auth as $A){
						if (isInitials($A)) $i[] = formatInitials($A);
					}
				}
				$fore = mb_strtoupper(implode(".", $i));
			} else {
				// it ends with the surname
				$surname = $auth[$countAuth-1];
				unset($auth[$countAuth-1]);
				$fore = implode(".", $auth);
			}
		} else {
			// We have no punctuation! Let's delimit with spaces.
			$chunks = array_reverse(explode(" ", $author));
			$i = array();
			foreach ($chunks as $chunk){
				if (!$surname && !isInitials($chunk)) $surname = $chunk;
				else array_unshift($i, isInitials($chunk)?formatInitials($chunk):$chunk);
			}
			$fore = implode(" ", $i);
		}
	}
	return formatSurname($surname) . ", " . formatForename($fore);
}

function formatAuthors($authors, $returnAsArray = false){
	$authors = html_entity_decode($authors, null, "UTF-8");

	$return = array();
	## Split the citation into an author by author account
	$authors = preg_replace(array("~\band\b~i", "~[\d\+\*]+~"), ";", $authors); //Remove "and" and affiliation symbols

	$authors = str_replace(array("&nbsp;", "(", ")"), array(" "), $authors); //Remove spaces and weird puntcuation
	$authors = str_replace(array(".,", "&", "  "), ";", $authors); //Remove "and"
	if (preg_match("~[,;]$~", trim($authors))) $authors = substr(trim($authors), 0, strlen(trim($authors))-1); // remove trailing punctuation

	$authors = trim($authors);
	if ($authors == "") {
    return false;
  }

	$authors = explode(";", $authors);
	#dbg(array("IN"=>$authors));
	if (isset($authors[1])) {
		foreach ($authors as $A){
			if (trim($A) != "")	$return[] = formatAuthor($A);
		}
	} else {
		//Use commas as delimiters
		$chunks = explode(",", $authors[0]);
		foreach ($chunks as $chunk){
			$bits = explode(" ", $chunk);
			foreach ($bits as $bit){
				if ($bit) $bitts[] = $bit;
			}
			$bits = $bitts; unset($bitts);
			#dbg($bits, '$BITS');
			if ($bits[1] || $savedChunk) {
				$return[] = formatAuthor($savedChunk .  ($savedChunk?", ":"") . $chunk);
				$savedChunk = null;
			} else $savedChunk = $chunk;// could be first author, or an author with no initials, or a surname with initials to follow.
		}
	}
	if ($savedChunk) $return[0] = $bits[0];
	$return = implode("; ", $return);
	$frags = explode(" ", $return);
	$return = array();
	foreach ($frags as $frag){
		$return[] = isInitials($frag)?formatInitials($frag):$frag;
	}
		$returnString = preg_replace("~;$~", "", trim(implode(" ", $return)));
	if ($returnAsArray){
		$authors = explode ( "; ", $returnString);
		return $authors;
	} else {
		return $returnString;
	}
}

function straighten_quotes($str) {
  $str = preg_replace('~&#821[679];|[\x{2039}\x{203A}\x{2018}-\x{201B}`]|&[rl]s?[ab]?quo;~u', "'", $str);
  $str = preg_replace('~&#822[013];|[\x{00AB}\x{00BB}\x{201C}-\x{201F}]|&[rlb][ad]?quo;~u', '"', $str);
  return $str;
}

/** Format authors according to author = Surname; first= N.E.
 * Requires the global $p
**/
function curlSetUp($ch, $url){
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //This means we can get stuck.
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);  //This means we can't get stuck.
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
	curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
	curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
}

function equivUrl ($u){
	$db = preg_replace("~;jsessionid=[A-Z0-9]*~", "", str_replace("%2F", "/", str_replace("?journalCode=pdi", "",
	str_replace("sci;", "", str_replace("/full?cookieSet=1", "", str_replace("scienceonline", "sciencemag", str_replace("/fulltext/", "/abstract/",
	str_replace("/links/doi/", "/doi/abs/", str_replace("/citation/", "/abstract/", str_replace("/extract/", "/abstract/", $u))))))))));
	if (preg_match("~(.*&doi=.*)&~Ui", $db, $db2)) $db = $db2[1];
	return $db;
}

function assessUrl($url){
  echo "assessing URL ";
	#if (strpos($url, "abstract") >0 || (strpos($url, "/abs") >0 && strpos($url, "adsabs.") === false)) return "abstract page";
	$ch = curl_init();
	curlSetUp($ch, str_replace("&amp;", "&", $url));
	curl_setopt($ch, CURLOPT_NOBODY, 1);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_exec($ch);
	switch(curl_getinfo($ch, CURLINFO_HTTP_CODE)){
		case "404":
			global $p;
			return "{{dead link|date=" . date("F Y") . "}}";
		#case "403": case "401": return "subscription required"; DOesn't work for, e.g. http://arxiv.org/abs/cond-mat/9909293
	}
	curl_close($ch);
	return null;
}

function testDoi($doi) {
  # TODO: this function doesn't seem to be used any more.  Where was it called before, and where can it be used now?
	global $p;
	echo "<div style='font-size:small; color:#888'>[";
	if ($p["url"][0]){
	if (strpos($p["url"][0], "http://dx.doi.org/")===0) return $doi;
	$ch = curl_init();
	curlSetup($ch, "http://dx.doi.org/$doi");
	curl_setopt($ch, CURLOPT_NOBODY, 1);
	$source = curl_exec($ch);
	$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);
	echo htmlspecialchars(equivUrl($effectiveUrl)), "] DOI resolves to equivalent of this.<br>",
	"[", equivUrl($p["url"][0]),"] URL was equivalent to this.</div>";
	if (equivUrl($effectiveUrl) == equivUrl($p["url"][0]))  return $doi; else return false;
	}
	echo "No URL specified]</div>";
	return false;
}

function parameterOrder($first, $author) {
  $order = list_parameters();
  $first_pos = array_search($first, $order);
  $author_pos = array_search($author, $order);
  if ($first_pos && $author_pos) {
     return array_search($first, $order) - array_search($author, $order);
  }
  else return true;
}

?>
