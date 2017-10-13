<?php
global $bot;
$bot = new Snoopy();

/* jrTest - tests a name for a Junior appelation
 *  Input: $name - the name to be tested
 * Output: array ($name without Jr, if $name ends in Jr, Jr)
 */
function jrTest($name) {
  $junior = (substr($name, -3) == " Jr")?" Jr":FALSE;
  if ($junior) {
    $name = substr($name, 0, -3);
  } else {
    $junior = (substr($name, -4) == " Jr.")?" Jr.":FALSE;
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
 * @codeCoverageIgnore
 *
 * Returns a string of initals, formatted for Cite Doi output
 *
 * $str: A series of initials, in any format.  NOTE! Do not pass a forename here!
 *
 */
function formatInitials($str) {
  $str = trim($str);
	if ($str == "") return FALSE;
	$end = (substr($str, strlen($str)-1) == ";") ? ";" : '';
	preg_match_all("~\w~", $str, $match);
	return mb_strtoupper(implode(".",$match[0]) . ".") . $end;
}
/*
 * @codeCoverageIgnore
 */
function isInitials($str){
	if (!$str) return FALSE;
	if (strlen(str_replace(array("-", ".", ";"), "", $str)) >3) return FALSE;
	if (strlen(str_replace(array("-", ".", ";"), "", $str)) ==1) return TRUE;
	if (mb_strtoupper($str) != $str) return FALSE;
	return TRUE;
}

/*
 * authorIsHuman
 * Runs some tests to see if the full name of a single author is unlikely to be the name of a person.
 */
function authorIsHuman($author) {
  $author = trim($author);
  $chars = count_chars($author);
  if ($chars[ord(":")] > 0 || $chars[ord(" ")] > 3 || strlen($author) > 33
    || substr(strtolower($author), 0, 4) == "the " 
    || stripos($author, 'collaborat') !== NULL
    || preg_match("~[A-Z]{3}~", $author)
  ) {
    return FALSE;
  }
  return TRUE;
}

// Returns the author's name formated as Surname, F.I.
function formatAuthor($author){

	// Requires an author who is formatted as SURNAME, FORENAME or SURNAME FORENAME or FORENAME SURNAME. Substitute initials for forenames if nec.
  $surname = NULL;
  
	$author = preg_replace("~(^[;,.\s]+|[;,.\s]+$)~", "", trim($author)); //Housekeeping
  $author = preg_replace("~^[aA]nd ~", "", trim($author)); // Just in case it has been split from a Smith; Jones; and Western
	if ($author == "") {
      return FALSE;
  }

	$auth = explode(",", $author);
	if (isset($auth[1])) {
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

function formatAuthors($authors, $returnAsArray = FALSE){
	$authors = html_entity_decode($authors, NULL, "UTF-8");

	$return = array();
	## Split the citation into an author by author account
	$authors = preg_replace(array("~\band\b~i", "~[\d\+\*]+~"), ";", $authors); //Remove "and" and affiliation symbols

	$authors = str_replace(array("&nbsp;", "(", ")"), array(" "), $authors); //Remove spaces and weird puntcuation
	$authors = str_replace(array(".,", "&", "  "), ";", $authors); //Remove "and"
	if (preg_match("~[,;]$~", trim($authors))) $authors = substr(trim($authors), 0, strlen(trim($authors))-1); // remove trailing punctuation

	$authors = trim($authors);
	if ($authors == "") {
    return FALSE;
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
				$savedChunk = NULL;
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

function curlSetUp($ch, $url){
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //This means we can get stuck.
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);  //This means we can't get stuck.
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
	curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
	curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
}

function query_adsabs ($options) {  
  // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/search.md
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer:' . ADSABSAPIKEY));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_URL, "http://api.adsabs.harvard.edu/v1/search/query"
    . "?data_type=XML&q=$options&fl="
    . "arxiv_class,author,bibcode,doi,doctype,identifier,issue,page,pub,pubdate,title,volume,year");
  $return = json_decode(curl_exec($ch));
  curl_close($ch);
  return $return->response;
}

function equivUrl ($u){
	$db = preg_replace("~;jsessionid=[A-Z0-9]*~", "", str_replace("%2F", "/", str_replace("?journalCode=pdi", "",
	str_replace("sci;", "", str_replace("/full?cookieSet=1", "", str_replace("scienceonline", "sciencemag", str_replace("/fulltext/", "/abstract/",
	str_replace("/links/doi/", "/doi/abs/", str_replace("/citation/", "/abstract/", str_replace("/extract/", "/abstract/", $u))))))))));
	if (preg_match("~(.*&doi=.*)&~Ui", $db, $db2)) $db = $db2[1];
	return $db;
}

?>
