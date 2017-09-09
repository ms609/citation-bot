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

function get_data_from_doi($doi, $silence) {
  global $editing_cite_doi_template;
  $crossRef = crossRefData($doi);
  if ($crossRef) 
    return expand_from_crossref($crossRef, $editing_cite_doi_template, $silence);
  else if (substr(trim($doi), 0, 8) == '10.2307/')
    return get_data_from_jstor(substr(trim($doi), 8));
  else return false;
}

function get_data_from_jstor($jid) {
  $jstor_url = "http://dfr.jstor.org/sru/?operation=searchRetrieve&query=dc.identifier%3D%22$jid%22&version=1.1";
  $data = @file_get_contents ($jstor_url);
  $xml = simplexml_load_string(str_replace(":", "___", $data));
  if ($xml->srw___numberOfRecords == 1) {
    $data = $xml->srw___records->srw___record->srw___recordData;
    global $p;
    if (trim(substr($p["doi"][0], 0, 7)) == "10.2307" || is("jstor")) {
      if (strpos($p["url"][0], "jstor.org")) {
        unset($p["url"]);
        if_null_set("jstor", substr($jid, 8));
      }
    }
    if (preg_match("~(pp\. )?(\w*\d+.*)~", $data->dc___coverage, $match)) {
      if_null_set("pages", str_replace("___", ":", $match[2]));
    }
    foreach ($data->dc___creator as $author) {
      $i++;
      $oAuthor = formatAuthor(str_replace("___", ":", $author));
      $oAuthor = explode(", ", $oAuthor);
      $first = str_replace(" .", "",
                 preg_replace("~(\w)\w*\W*((\w)\w*\W+)?((\w)\w*\W+)?((\w)\w*)?~",
                              "$1. $3. $5. $7.", $oAuthor[1]));
      if_null_set("last$i", $oAuthor[0]);
      if_null_set("first$i", $first);
    }
    if_null_set("title", (string) str_replace("___", ":", $data->dc___title)) ;
    if (preg_match("~(.*),\s+Vol\.\s+([^,]+)(, No\. (\S+))?~", str_replace("___", ":", $data->dc___relation), $match)) {
      if_null_set("journal", str_replace("___", ":", $match[1]));
      if_null_set("volume", $match[2]);
      if_null_set("issue", $match[4]);
      $handled_data = true;
    } else {
      if (preg_match("~Vol\.___\s*([\w\d]+)~", $data->dc___relation, $match)) {
        if_null_set("volume", $match[1]);
        $handled_data = true;
      }
      if (preg_match("~No\.___\s*([\w\d]+)~", $data->dc___relation, $match)) {
        if_null_set("issue", $match[1]);
        $handled_data = true;
      }
      if (preg_match("~JOURNAL___\s*([\w\d\s]+)~", $data->dc___relation, $match)) {
        if_null_set("journal", str_replace("___", ":", $match[1]));
        $handled_data = true;
      }
    }
    if (!$handled_data) {
      echo "unhandled data: " . htmlspecialchars($data->dc___relation);
    }
    /* -- JSTOR's publisher field is often dodgily formatted.
    if (preg_match("~[^/;]*~", $data->dc___publisher, str_replace("___", ":", $match))) {
      if_null_set("publisher", $match[0]);
    }*/
    if (preg_match ("~\d{4}~", $data->dc___date[0], $match)) {
      if_null_set("year", str_replace("___", ":", $match[0]));
    }
    return true;
  } else {
    echo htmlspecialchars($xml->srw___numberOfRecords) . " records obtained. ";
    return false;
  }
}

function pmExpand($p, $id){
	$details = pmArticleDetails($p[$id][0], $id);
	foreach($details as $key=>$value) $p[$key][0] = $value;
}

function pmFullTextUrl($pmid){
  $xml = simplexml_load_file("http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dbfrom=pubmed&id=$pmid&cmd=llinks&tool=DOIbot&email=martins+pubmed@gmail.com");
	if ($xml) {
		foreach ($xml->LinkSet->IdUrlList->IdUrlSet->ObjUrl as $url){
			foreach ($url->Attribute as $attrib) $requiredFound = strpos($url->Attribute, "required")?true:$requiredFound;
			if (!$requiredFound) return (string) $url->Url;
		}
	}
}

function get_data_from_isbn() {
	global $p, $isbnKey;
	$params = array("author"=>"author", "year"=>"year", "publisher"=>"publisher", "location"=>"city", "title"=>"title"/*, "oclc"=>"oclcnum"*/);
	if (is("author") || is("first") || is("author1") ||
			is("editor") || is("editor-last") || is("editor-last1") || is("editor1-last")
			|| is("author") || is("author1")) unset($params["author"]);
	if (is("publisher")) {
    unset($params["location"]);
    unset($params["publisher"]);
  }
	if (is("title")) unset ($params["title"]);
	if (is("year")||is("date")) unset ($params["year"]);
	if (is("location")) unset ($params["location"]);
	foreach ($params as $null) $missingInfo = true;
	if ($missingInfo) $xml = simplexml_load_file("http://xisbn.worldcat.org/webservices/xid/isbn/" . str_replace(array("-", " "), "", $p["isbn"][0]) . "?method=getMetadata&fl=*&format=xml");#&ai=Wikipedia_doibot");
	if ($xml["stat"] == "ok") {
		foreach ($params as $key => $value)	{
			if (preg_match("~[^\[\]<>]+~", $xml->isbn[$value], $match)) {
        if_null_set($key, $match[0]);
      }
		}
		if (substr($p["author"][0], 0, 3) == "by ") $p["author"][0] =substr( $p["author"][0], 3);
		if (preg_match("~\d+~", $p["oclc"][0], $match)) $p["oclc"][0] = $match[0];
	} else {
		$xml = simplexml_load_file("http://isbndb.com/api/books.xml?access_key=$isbnKey&index1=isbn&value1=". urlencode($p["isbn"][0]));
		if ($xml->BookList["total_results"] >0){
			$params = array("title"=>"Title", "author"=>"AuthorsText", "publisher"=>"PublisherText");
			if (is("author") || is("first") || is("author1") || is("author1")) unset($params["author"]);
			foreach ($params as $key => $value)	if (!is($key)) $p[$key][0] = capitalize_title((string) $xml->BookList->BookData->$value);
		} else if ($xml->ErrorMessage) {
      echo "<p class=warning>Error: Daily limit of ISBN queries has been exceeded!</p>";
    }
		else $p["ISBN-status"][0] = "May be invalid - please double check";
	}
}

function file_size($url, $redirects = 0){

//Copied from WWW.
  $parsed = parse_url($url);
  $host = $parsed["host"];
  $fp = @fsockopen($host, 80, $errno, $errstr, 20);
  if(!$fp) return 9999999;
   else {
       @fputs($fp, "HEAD $url HTTP/1.1\r\n");
       @fputs($fp, "HOST: $host\r\n");
       @fputs($fp, "Connection: close\r\n\r\n");
       $headers = "";
			 $startTime = time();
       while(!@feof($fp) && strlen($headers) < 2500 && $startTime +2 > time()) $headers .= fgets ($fp, 128);
   }
   @fclose ($fp);
   $return = 999999;
   $arr_headers = explode("\n", $headers);
   foreach($arr_headers as $header) {
			// follow redirect
			$s = 'Location: ';
			if($redirects < 3 && substr(mb_strtolower ($header), 0, strlen($s)) == mb_strtolower($s)) {
				$url = trim(substr($header, strlen($s)));
				return file_size($url, $redirects + 1);
			}
			// parse for content length
       $s = "Content-Length: ";
       if(substr(mb_strtolower ($header), 0, strlen($s)) == mb_strtolower($s)) {
           $return = trim(substr($header, strlen($s)));
           break;
       }
   }
   if($return) {
			$size = round(($return / 1024) / 1024, 2);
			$return = "$size"; // in MB
   }
   return $return;
}

function deWikify($string){
	return str_replace(Array("[", "]", "'''", "''", "&"), Array("", "", "'", "'", ""), preg_replace(Array("~<[^>]*>~", "~\&[\w\d]{2,7};~", "~\[\[[^\|\]]*\|([^\]]*)\]\]~"), Array("", "", "$1"),  $string));
}

function scrapeDoi($url){
	global $urlsTried, $p;
	$title = $p["title"][0];
	if (substr($url, -3) == "pdf") {
		echo "<br>PDF detected.  <small>It is too resource intensive to check that the PDF refers to the correct article.</small><br>";
	} else if (substr($url, strlen($url)-3) == "doc") {
		echo "<br>DOC detected.  <small>DOCs cannot be scraped.</small><br>";
	} else {
		if (!@array_search($url, $urlsTried)
			&& strpos($url, "www.answers.com") === FALSE
			&& strpos($url, "destinationscience") === FALSE
			&& strpos($url, "pedia/") === false
			&& strpos($url, "wiki") === false
			&& strpos($url, "pedia.") === false
			){ // Should we look at this URL? Exclude those we've already marked as barren, and those which appear to be wiki mirrors.
			set_time_limit(time_limit);
			//Initiate cURL resource
			echo " (..";
			$ch = curl_init();
			curlSetup($ch, $url);
			$source = curl_exec($ch);
			if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
				echo "404 returned from URL.<br>";
				return false;
                        }
			if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 501) {
				echo "501 returned from URL.<br>";
				return false;
                        }
			curl_close($ch);
			if (!$source) {
				echo "Page appears to be blank! <br>";
				return false;
                        }
			echo "..)<br>";
			if (strlen($title) < 15) {
				echo "Title (" . htmlspecialchars($title) . ") is under 15 characters: too short to use.";
				return false;
                        }
			$lSource = literate($source);
			$lTitle = literate($title);
			if (preg_match("|[=/](10\.\d{4}/[^?&]*)|", str_replace("%2F", "/", $url), $doiX)){
				//If there is a DOI in the URL
				if (preg_match("~spanclasstitle".$lTitle."~", $lSource)) {
					echo "DOI found in URL. Web page has correct title.<br>";
					return $doiX[1];
				} else {
					echo "URL contains DOI, but may not refer to the correct article.<br>";
				}
			}
			// Now check the META tags
			preg_match('~<meta name="citation_doi" content="' . doiRegexp . '">~iU', $source, $meta)?"":preg_match('~<meta name="dc.Identifier" content="' . doiRegexp . '">~iU', $source, $meta);
			if ($meta) {
				$lsTitle = literate(strip_tags($title));
				preg_match('~<meta name="citation_title" content="(.*)">~iU', $source, $metaTitle)?"":preg_match('~<meta name="dc.Title" content="(.*)">~iU', $source, $metaTitle);
				if (literate($metaTitle[1]) == $lsTitle) {
					echo "DOI found in meta tags: " . htmlspecialchars($meta[1]) . "<br>";
					return $meta[1];
				}
				echo "Meta info found but title does not match.<br>" . htmlspecialchars(literate($metaTitle[1])) . " != <br>" . htmlspecialchars($lsTitle) . "</i><br>";
			}
			//No luck? Find the DOIs in the source.
			$doi = getDoiFromText($source);
			if (!$doi) {
				$urlsTried[] = $url;
				echo "URL is barren.<br>";
				return false;
			}
			echo "A DOI has been found. " ;
			return $doi;
		} else {
			echo " - Cancelled. URL blacklisted.<br>";
			global $searchLimit;
			$searchLimit++;
		}
	}
	if (!$doi) {
		$urlsTried[] = $url; //Log barren urls so we don't search them again.  We may want to search
	}
}

function getDoiFromText($source, $testDoi = false){
	global $p;
	$title = $p["title"][0];
	$lSource = literate($source);
	$lTitle = literate($title);
	if (preg_match("~" . $lTitle . "~", $lSource, $titleOffset, PREG_OFFSET_CAPTURE)){

		$titleOffset = $titleOffset[1];
		//Search for anything formatted like a DOI
		if (preg_match_all("~doi.*" . doiRegexp . "~Ui", $source, $dois)){ // What comes after "doi", then any nonword, but before whitespace
			if ($dois[1][2]){
				echo "Multiple DOIs found: ";
				return false ; //We can't be sure that we've found the right one.
			} elseif (!$dois[1][1] || $dois[1][1] == $dois[1][0]) {
				//DOI is unique.  If it appears early in the document, use it.
				if ($testDoi) {$doi = testDoi(trimEnd($dois[1][0])); if ($doi) return $doi;} // DOI redirects to our URL so MUST be correct
				preg_match("~" . literate($dois[1][0]) . "~", $lSource, $thisDoi, PREG_OFFSET_CAPTURE);
				if ($thisDoi[0][1] < early){
					if (preg_match("~" . $lTitle . "~", substr($lSource, 0, early + 500))){
						//if DO I AND Title are found near the start of the article...
						echo "Unique DOI found, early in document (Offset = " . htmlspecialchars($thisDoi[0][1]) . "). " . htmlspecialchars($doi) . "<br>";
						return $dois[1][0];
					} else {echo "A unique DOI found early in document (Offset = " . htmlspecialchars($thisDoi[0][1]) . "), but without the required title.<br>"; return false;}
				} else {
					echo "A unique DOI was found, but late in the document (offset = " . htmlspecialchars($thisDoi[0][1]) . "). Removing HTML to get a clearer picture...<br>";
					$position = strpos(strip_tags($source), $dois[1][0]);
					if ($position < (early/2)) {
						echo "Nice and early: " . htmlspecialchars($position) . ". Accepting DOI.<br>"; return $dois[1][0];
					} else{
						echo "Still too late (" . htmlspecialchars($position) . ").  Rejecting.";
						return false;
					}
				}
			} else {echo "\n2 different DOIs were found. Abandoned.<br>"; return false;}
		} else {echo "\nNo DOIs found in page."; $urlsTried[] = $url; RETURN false;}
	}
	echo "Searched body text.<br>";
}

function checkTextForMetas($text){
  // Find all meta tags using preg_match
	preg_match_all("~<meta\s+name=['\"]([^'\"]+)['\"]\s+content=[\"']([^>]+)[\"']\s*/?>~", $text, $match);
	preg_match_all("~<meta\s+content=[\"']([^>]+)[\"']\s+name=['\"]([^'\"]+)['\"]\s*/?>~", $text, $match2);
  $matched_names = array_merge($match[1], $match2[2]);
  $matched_content = array_merge($match[2], $match2[1]);
  // Define translation from meta tag names to wiki parameters
	$m2p = array(
						"citation_journal_title" => "journal",
            "citation_title"  => "title",
            "citation_date"  => "date",
            "citation_volume"  => "volume",
            "citation_issue" => "issue",
            "citation_firstpage"  => "pages",
            "dc.Contributor" => "author",
            "dc.Title" => "title",
            "dc.Date" => "date"
	);

  // Transform matches into an array:  pairs (meta name => value)
	$stopI = count($matched_content);
	for ($i = 0; $i < $stopI; $i++) {
     $pairs[] = array($matched_names[$i], $matched_content[$i]);
  }

  // Rename each pair to $newp  (wiki name => value)
	foreach ($pairs as $pair) {
    $i = 1;
		foreach ($m2p as $metaKey => $metaValue) {
			if (mb_strtolower($pair[0]) == mb_strtolower($metaKey)) {
        if ($metaValue == "author") {
          $metaValue = "author" . $i++;
        }
        if_null_set($metaValue, $pair[1]);
      }
    }
  }

  // Now $newp contains the wiki name and the meta tag's value.  We may have multiple values, especially for the author field.
  // We only want to fiddle with authors if there are none specified.
	if (!is('author') && !is('author1') && !is('author') && !is('author1')) {
    foreach ($newp['author'] as $auth) {
      $author_list .= "$auth;";
    }
    $authors = formatAuthors($author_list, true);
    foreach ($authors as $no => $auth) {
      $names = explode (', ', $auth);
      $newp["last" . ($no + 1)] = $names[0];
      $newp["first" . ($no + 1)] = $names[1];
    }
  }
	if (isset($newp["date"])) {
		$newp["year"][0] = date("Y", strtotime($newp["date"][0]));
		//$newp["month"][0] = date("M", strtotime($newp["date"][0])); DISABLED BY EUBLIDES
		unset($newp["date"]);
	}
	foreach ($newp as $p => $p0) if_null_set($p, $p0[0]);
}

function literate($string){
	//remove all html, spaces and &eacute; things  from text, only leaving letters and digits
	preg_match_all("~(&[\w\d]*;)?(\w+)~", $string, $letters);
		foreach ($letters[2] as $letter) $return .= $letter;
	return mb_strtolower($return);
}


// The following functions "tidy" parameters.
function trimEnd($doi){
	switch (substr($doi, strlen($doi)-1)){
		case ":": case ";": case ")": case ".":	case "'": case ",":case "-":case "#":
		$doi = substr($doi, 0, strlen($doi)-1);
		$doi = trimEnd($doi); //Recursive in case more than one punctuation mark on the end
	}
	return str_replace ("\\", "", $doi);
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
function citeDoiOutputFormat() {
  global $p;
  unset ($p['']);

  // Check that DOI hasn't been urlencoded.  Note that the doix parameter is decoded and used in step 1.
  if (strpos($p['doi'][0], ".2F~") && !strpos($p['doi'][0], "/")) {
    $p['doi'][0] = str_replace(dotEncode, dotDecode, $p['doi'][0]);
  }

  // Cycle through authors
  for ($i = null; $i < 100; $i++) {
    if (strpos($p["author$i"][0], ', ')) {
      // $au is an array with two parameters: the surname [0] and forename [1].
      $au = explode(', ', $p["author$i"][0]);
      unset($p["author$i"]);
      set("author$i", formatSurname($au[0])); // i.e. drop the forename; this is safe in $au[1]
    } else if (is("first$i")) {
      $au[1] = $p["first$i"][0];
    } else {
       unset($au);
    }
    if ($au[1]) {
      if ($au[1] == mb_strtoupper($au[1]) && mb_strlen($au[1]) < 4) {
        // Try to separate Smith, LE for Smith, Le.
        $au[1] = preg_replace("~([A-Z])[\s\.]*~u", "$1.", $au[1]);
      }
      if (trim(mb_strtoupper(preg_replace("~(\w)[a-z]*.? ?~u", "$1. ", trim($au[1]))))
              != trim($p["first$i"][0])) {
        // Don't try to modify if we don't need to change
        set("first$i", mb_strtoupper(preg_replace("~(\w)[a-z]*.? ?~u", "$1. ", trim($au[1])))); // Replace names with initials; beware hyphenated names!
      }
      if (strpos($p["first$i"][1], "\n") !== false || (!$p["first$i"][1] && $p["first$i"][0])) {
        $p["first$i"][1] = " | "; // We don't want a new line for first names, it takes up too much space
      }
      if (is("author$i")) {
        $p["author$i"][1] = "\n| "; // hard-coding first$i will change the default for author$i.
      }
    }
  }
  if ($p['pages'][0]) {
    // Format pages to R100-R102 format
    if (preg_match("~([A-Za-z0-9]+)[^A-Za-z0-9]+([A-Za-z0-9]+)~", $p['pages'][0], $pp)) {
       if (strlen($pp[1]) > strlen($pp[2])) {
          // The end page range must be truncated
          $p['pages'][0] = str_replace("!!!DELETE!!!", "", preg_replace("~([A-Za-z0-9]+[^A-Za-z0-9]+)[A-Za-z0-9]+~",
                                        ("$1!!!DELETE!!!"
                                        . substr($pp[1], 0, strlen($pp[1]) - strlen($pp[2]))
                                        . $pp[2]), $p['pages'][0]));
       }
    }
  }
  //uksort($p, parameterOrder);
}

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

function get_all_meta_tags($url){
	if (preg_match("~http://www\.pubmedcentral\.nih\.gov/articlerender\.fcgi\?artinstid=(\d*)~", $url, $pmc)) $return["pmc"]=$pmc[1];
	$ch = curl_init();
	curlSetUp($ch, $url);
	curl_setopt($ch, CURLOPT_HEADER,true);
	$pageText = curl_exec($ch);
	preg_match_all("~<meta name=\"dc\.Creator\" content=\"([^\"]*)\"~", $pageText, $creators);
	preg_match("~<meta name=['\"]citation_pmid[\"'] content=['\"](\d+)['\"]~", $pageText, $pmid);
	if ($creators[1][1]){
		foreach ($creators[1] as $aut){
			if (mb_strtoupper($aut) == $aut) {
				$return["author"] .= " " . ucwords(str_replace(",", ", ", mb_strtolower($aut))) . ";";
			} else {
				$aut = preg_split("~([A-Z])~", $aut, null,PREG_SPLIT_DELIM_CAPTURE);
				$count = count($aut);
				for ($i = 0; $i <= $count; $i+=2) $return["author"] .= $aut[$i] . (isset($aut[$i+1])?" ":"") . $aut[$i+1];
				$return["author"] .= ";";
			}
		}
		$return["author"] = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), trim(substr($return["author"], 0, strlen($return["author"])-1)));
	}
	if (isset($pmid[1])) $return["pmid"] = $pmid[1];
	curl_close($ch);
	return $return;
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
