<?
$bot = new Snoopy();
define("wikiroot", "http://en.wikipedia.org/w/index.php?");
define("api", "http://en.wikipedia.org/w/api.php");
if ($linkto2) print "\n// included DOItools2 & initialised \$bot\n";
define("doiRegexp", "(10\.\d{4}(/|%2F)..([^\s\|\"\?&>]|&l?g?t;|<[^\s\|\"\?&]*>))(?=[\s\|\"\?]|</)"); //Note: if a DOI is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.
define("timelimit", $fastMode?4:($slowMode?15:10));
define("early", 8000);//Characters into the literated text of an article in which a DOI is considered "early".
define("siciRegExp", "~(\d{4}-\d{4})\((\d{4})(\d\d)?(\d\d)?\)(\d+):?([+\d]*)[<\[](\d+)::?\w+[>\]]2\.0\.CO;2~");

function list_parameters () { // Lists the parameters in order.
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
     "editor", "editor1",
     "editor-last", "editor1-last",
     "editor-first", "editor1-first",
     "editor-link", "editor1-link",
     "editor2", "editor2-author", "editor2-first", "editor2-link",
     "editor3", "editor3-author", "editor3-first", "editor3-link",
     "editor4", "editor4-author", "editor4-first", "editor4-link",
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
     "doi_brokendate", "doi_inactivedate",
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

global $dontCap, $unCapped;
// Remember to enclose any word in spaces.
// $dontCap is a global array of strings that should not be capitalized in their titlecase format; $unCapped is their correct capitalization
$dontCap  = array(' and Then ', ' Of ',' The ',' And ',' An ',' Or ',' Nor ',' But ',' Is ',' If ',' Then ',' Else ',' When', 'At ',' From ',' By ',' On ',' Off ',' For ',' In ',' Over ',' To ',' Into ',' With ',' U S A ',' Usa ',' Et ');
$unCapped = array(' and then ', ' of ',' the ',' and ',' an ',' or ',' nor ',' but ',' is ',' if ',' then ',' else ',' when', 'at ',' from ',' by ',' on ',' off ',' for ',' in ',' over ',' to ',' into ',' with ',' U S A ',' USA ',' et ');

// journal acrynyms which should be capitilised are downloaded from User:Citation_bot/capitalisation_exclusions
$bot->fetch(wikiroot . "title=" . urlencode('User:Citation_bot/capitalisation_exclusions') . "&action=raw");
if (preg_match_all('~\n\*\s*(.+)~', $bot->results, $dontCaps)) {
	foreach ($dontCaps[1] as $o) {
		$unCapped[] = ' ' . trim($o) . ' ';
    // dontCap is a global array of strings that should not be capitalized in their titlecase format; $unCapped is their correct capitalization
    $dontCap[] = ' '
               . trim(
                      (strlen(str_replace(array("[", "]"), "", trim($o))) > 6)
                      ? mb_convert_case($o, MB_CASE_TITLE, "UTF-8")
                      :$o
                 )
               . ' ';
	}
}

/** Returns revision number */
function revisionID() {
    global $last_revision_id;
    if ($last_revision_id) return $last_revision_id;
    $svnid = '$Rev$';
    $scid = substr($svnid, 6);
    $thisRevId = intval(substr($scid, 0, strlen($scid) - 2));
    return $thisRevId;
    $repos_handle = svn_repos_open('~/citation-bot');
    return svn_fs_youngest_rev($repos_handle);
}

function bubble_p ($a, $b) {
  return ($a["weight"] > $b["weight"]) ? 1 : -1;
}

function is($key){
	global $p;
  return ("" != trim($p[$key][0]))?true:false;
}

function set($key, $value) {
	global $p;
  // Dud DOI in PMID database
  if ($key == "doi" && $value == "10.1267/science.040579197") {return false;}
  
  $parameter_order = list_parameters();
  if (trim($value) != "") {
    $p[$key][0] = (string) $value;
    echo "\n    + $key: $value";
    if (!$p[$key]["weight"]) {
      // Calculate the appropriate weight:
      #print "-$key-" . array_search($key, $parameter_order) . array_search("year", $parameter_order);
      $key_position = array_search($key, $parameter_order);
      if (!$key_position) {
        $p[$key]["weight"] = 16383;
      } else {
        $lightest_weight = 16383; // (2^14)-1, arbritarily large
        for ($i = count($parameter_order); $i >= $key_position && $i > 0; $i--) {
          if ($p[$parameter_order[$i]]["weight"] > 0) {
            $lightest_weight = $p[$parameter_order[$i]]["weight"];
            $lightest_param = $parameter_order[$i];
          }
        }

        for ($i = $key_position; $i >= 0; $i--) {
          if ($p[$parameter_order[$i]]["weight"] > 0) {
            $heaviest_weight = $p[$parameter_order[$i]]["weight"];
            $heaviest_param = $parameter_order[$i];
            break;
          }
        }
        $p[$key]["weight"] = ($lightest_weight + $heaviest_weight) / 2;
        #echo " ($lightest_param, $lightest_weight + $heaviest_param, $heaviest_weight / 2 = {$p[$key]["weight"]})";
        echo " ({$p[$key]["weight"]})";
      }
    }
  }
}

function dbg($array, $key = false) {
if(myIP())
	echo "<pre>" . str_replace("<", "&lt;", $key?print_r(array($key=>$array),1):print_r($array,1)), "</pre>";
else echo "<p>Debug mode active</p>";
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

/*underTwoAuthors
  * Return true if 0 or 1 author in $author; false otherwise
 */
function underTwoAuthors($author) {
  $chars = count_chars(trim($author));
  if ($chars[ord(";")] > 0 || $chars[ord(" ")] > 2 || $chars[ord(",")] > 1) {
    return false;
  }
  return true;
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
function ifNullSet($param, $value) {
	global $p;
  if (substr($param, strlen($param)-3, 1) > 0 || substr($param, strlen($param)-2) > 9) {
      // The parameter is of 'fisrt101' or 'last10' format and adds nothing but clutter
      return false;
  }
	switch ($param) {
		case "editor": case "editor-last": case "editor-first":
			$param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
			if (trim($p["editor"][0]) == "" && trim($p["editor-last"][0]) == "" && trim($p["editor-first"][0]) == "" && trim($value)!="") {
        set ($param, $value);
        return true;
      }
			break;
		case "author": case "last1": case "last": case "authors": // "authors" is automatically corrected by the bot to "author"; include to avoid a false positive.
			$param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
			if (trim($p["last1"][0]) == ""
          && trim($p["last"][0]) == ""
          && trim($p["author"][0]) == ""
          && trim($p["author1"][0]) == ""
          && trim($p["editor"][0]) == ""
          && trim($p["editor-last"][0]) == ""
          && trim($p["editor-first"][0]) == ""
					&& trim($value) != ""
         ) {
        set ($param, $value);
        return true;
      }
			break;
		case "first": case "first1": case "author1":
      if (trim($p["first"][0]) == "" && trim($p["first1"][0]) == ""
        && trim($p["author"][0]) == "" && trim ($p['author1'][0]) == "") {
        set ($param, $value);
        return true;
      }
      break;
		case "coauthor": case "coauthors":
			$param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
			if (trim($p["last2"][0]) == "" && trim($p["coauthor"][0]) == "" &&trim($p["coauthors"][0]) == "" && trim($p["author"][0]) == "" && trim($value)!="") {
        // Note; we shouldn't be using this parameter ever....
        set ($param, $value);
        return true;
      }
			break;
		case "last2": case "last3": case "last4": case "last5": case "last6": case "last7": case "last8": case "last9":
		case "author2": case "author3": case "author4": case "author5": case "author6": case "author7": case "author8": case "author9":
			$param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
      if (trim($p["last" . substr($param, -1)][0]) == "" && trim($p["author" . substr($param, -1)][0]) == ""
          && trim($p["coauthor"][0]) == "" && trim($p["coauthors"][0]) == ""
          && underTwoAuthors($p['author'][0]))  {
        set ($param, $value);
        return true;
      }
			break;
    case "first2": case "first3": case "first4": case "first5": case "first6": case "first7": case "first8": case "first9": case "first10":
			if (trim($p[$param][0]) == ""
        && underTwoAuthors($p['author'][0]) && trim($p["author" . substr($param, strlen($param)-1)][0]) == ""
        && trim($p["coauthor"][0]) == "" && trim($p["coauthors"][0]) == ""
        && trim($value) != "")  {
        set ($param, $value);
        return true;
      }
			break;
    case "date":
      if (preg_match("~^\d{4}$~", trim($value))) {
        // Not adding any date data beyond the year, so 'year' parameter is more suitable
        $param = "year";
      }
      // Don't break here; we want to go straight in to year;
		case "year":
			if (trim($p["date"][0]) == "" && trim($p["year"][0]) == "" && trim($value)!="")  {
        set ($param, $value);
        return true;
      }
			break;
		case "periodical": case "journal":
			if (trim($p["journal"][0]) == "" && trim($p["periodical"][0]) == "" && trim($value)!="") {
        set ($param, $value);
        return true;
      }
			break;
    case "page": case "pages":
			if (trim($p["pages"][0]) == "" && trim($p["page"][0]) == "" && trim($value) != "") {
        set ($param, $value);
        return true;
      }
      break;
		default: if (trim($p[$param][0]) == "" && trim($value) != "") {
        set ($param, $value);
        return true;
      }
	}
  return false;
}

function nothingMissing($journal){
  global $authors_missing;
  return ( is($journal)
        && is("volume")
        && is("issue")
        && (is("pages") || is("page"))
        && is("title")
        && (is("date") || is("year"))
        && (is("author2") || is("author2"))
        && !$authors_missing
  );
}


function expand_from_pubmed() {
  global $p;
  echo "\n - Checking PMID {$p['pmid'][0]} for more details";
  $details = pmArticleDetails($p['pmid'][0]);
  foreach ($details as $key => $value) {
    ifNullSet($key, $value);
  }
  if (false && !is("url")) { // TODO:  BUGGY - CHECK PMID DATABASES, and see other occurrence above
    if (!is('pmc')) {
    $url = pmFullTextUrl($p["pmid"][0]);
    } else {
      unset ($p['url']);
    }
    if ($url) {
      set ("url", $url);
    }
  }
}

function expand_from_doi($crossRef, $editing_cite_doi_template) {
  global $p, $doiCrossRef, $jstor_redirect;
  if (substr($p["doi"][0], 3, 4) == "2307") {
    echo "\n - Populating from JSTOR database: ";
    if (get_data_from_jstor($p["doi"][0])) {
      $crossRef = crossRefData($p["doi"][0]);
      if (!$crossRef) {
        // JSTOR's UID is not registered as a DOI, meaning that there is another (correct - DOIs should be unique) DOI, issued by the publisher.
        if ($editing_cite_doi_template) {
          $jstor_redirect = $p["doi"][0];
          }
        unset ($p["doi"][0]);
        preg_match("~(\w?\w?\d+\w?\w?)(\D+(\w?\w?\d+\w?\w?))?~", $p["pages"][0], $pagenos);
        $crossRef = crossRefDoi($p["title"][0], $p["journal"][0], is("author1")?$p["author1"][0]:$p["author"][0]
                               , $p["year"][0], $p["volume"][0], $pagenos[1], $pagenos[3], $p["issn"][0], null);
      }
    } else {
      echo "not found in JSTOR?";
    }
  } else {
    // Not a JSTOR doi, use CrossRef
    $crossRef = $crossRef?$crossRef:crossRefData(urlencode(trim($p["doi"][0])));
  }

  if ($crossRef) {
    echo "\n - Checking CrossRef for more details";
    if ($editing_cite_doi_template) {
      $doiCrossRef = $crossRef;
    }
    ifNullSet("title", $crossRef->article_title);
    ifNullSet("year", $crossRef->year);
    if (!is("editor") && !is("editor1") && !is("editor-last") && !is("editor1-last")
        && $crossRef->contributors->contributor) {
      foreach ($crossRef->contributors->contributor as $author) {
        $au_i++;
        if ($au_i < 10) {
          ifNullSet("last$au_i", formatSurname($author->surname));
          ifNullSet("first$au_i", formatForename($author->given_name));
        }
      }
    }
    ifNullSet("doi", $crossRef->doi);
    if ($jstor_redirect) {
      global $jstor_redirect_target;
      $jstor_redirect_target = $crossRef->doi;
    }
    ifNullSet($journal, $crossRef->journal_title);
    ifNullSet("volume", $crossRef->volume);
    if (!is("page")) ifNullSet("pages", $crossRef->first_page);
  } else {
    echo "\n - No CrossRef record found :-(";
  }
  return $crossRef;
}

function getDataFromArxiv($a) {
  $xml = simplexml_load_file( "http://export.arxiv.org/api/query?start=0&max_results=1&id_list=$a");
	if ($xml) {
		global $p;
		foreach ($xml->entry->author as $auth) {
			$i++;
      if ($i<10) {
        $name = $auth->name;
        if (preg_match("~(.+\.)(.+?)$~", $name, $names)){
          ifNullSet("author$i", $names[2]);
          ifNullSet("first$i", $names[1]);
          // If there's a newline before the forename,, remove it so it displays alongside the surname.
          if (strpos($p["first$i"], "\n" !== false)) {
            $p["first$i"][1] = " | ";
          }
        }
        elseif (trim($p['author'][0]) == "") {
            ifNullSet("author$i", $name);
        }
      }
		}
		ifNullSet("title", (string)$xml->entry->title);
		ifNullSet("class", (string)$xml->entry->category["term"]);
		ifNullSet("author", substr($authors, 2));
		ifNullSet("year", date("Y", strtotime((string)$xml->entry->published)));
		return true;
	}
	return false;
}

function get_data_from_jstor($jid) {
  $jstor_url = "http://dfr.jstor.org/sru/?operation=searchRetrieve&query=dc.identifier%3D%22$jid%22&version=1.1";
  $data = @file_get_contents ($jstor_url);
  $xml = simplexml_load_string(str_replace(":", "___", $data));
  if ($xml->srw___numberOfRecords == 1) {
    $data = $xml->srw___records->srw___record->srw___recordData;
    global $p;
    if (trim(substr($p["doi"][0], 0, 7)) == "10.2307") {
      if (!ifNullSet("url", "http://jstor.org/stable/" . substr($jid, 8)) && !strpos($p["url"][0], "jstor")) {
        ifNullSet("jstor", substr($jid, 8)); // TODO -- untested.
      }
    }
    if (preg_match("~(pp\. )?(\w*\d+.*)~", $data->dc___coverage, $match)) {
      ifNullSet("pages", str_replace("___", ":", $match[2]));
    }
    foreach ($data->dc___creator as $author) {
      $i++;
      $oAuthor = formatAuthor(str_replace("___", ":", $author));
      $oAuthor = explode(", ", $oAuthor);
      $first = str_replace(" .", "",
                 preg_replace("~(\w)\w*\W*((\w)\w*\W+)?((\w)\w*\W+)?((\w)\w*)?~",
                              "$1. $3. $5. $7.", $oAuthor[1]));
      ifNullSet("last$i", $oAuthor[0]);
      ifNullSet("first$i", $first);
    }
    ifNullSet("title", (string) str_replace("___", ":", $data->dc___title)) ;
    if (preg_match("~(.*),\s+Vol\.\s+([^,]+)(, No\. (\S+))?~", str_replace("___", ":", $data->dc___relation), $match)) {
      ifNullSet("journal", $match[1]);
      ifNullSet("volume", $match[2]);
      ifNullSet("issue", $match[4]);
    } else {
      echo "unhandled data: $data->dc___relation";
    }
    /* -- JSTOR's publisher field is often dodgily formatted.
    if (preg_match("~[^/;]*~", $data->dc___publisher, str_replace("___", ":", $match))) {
      ifNullSet("publisher", $match[0]);
    }*/
    if (preg_match ("~\d{4}~", $data->dc___date[0], $match)) {
      ifNullSet("year", str_replace("___", ":", $match[0]));
    }
    return true;
  } else {
    echo $xml->srw___numberOfRecords . " records obtained. ";
    return false;
  }
}

function crossRefData($doi){
	global $crossRefId;
  $url = "http://www.crossref.org/openurl/?pid=$crossRefId&id=doi:$doi&noredirect=true";
  $xml = @simplexml_load_file($url);
  if ($xml) {
    $result = $xml->query_result->body->query;
  } else {
     echo "Error loading CrossRef file from DOI $doi!  <br>\n";
     return false;
  }
	return ($result["status"]=="resolved")?$result:false;
}

function crossRefDoi($title, $journal, $author, $year, $volume, $startpage, $endpage, $issn, $url1, $debug = false ){
	global $crossRefId;
	if ($journal || $issn) {
		$url = "http://www.crossref.org/openurl/?noredirect=true&pid=$crossRefId";
		if ($title) $url .= "&atitle=" . urlencode(deWikify($title));
		if ($issn) $url .= "&issn=$issn"; elseif ($journal) $url .= "&title=" . urlencode(deWikify($journal));
		if ($author) $url .= "&auauthor=" . urlencode($author);
		if ($year) $url .= "&date=" . urlencode($year);
		if ($volume) $url .= "&volume=" . urlencode($volume);
		if ($startpage) $url .= "&spage=" . urlencode($startpage);
		if ($endpage > $startpage) $url .= "&epage=" . urlencode($endpage);
    if (!($result = @simplexml_load_file($url)->query_result->body->query)) echo "\n xxx Error loading simpleXML file from CrossRef. ";
		if ($result["status"] == "resolved") {
      return $result;
    }
  }
	if ($url1) {
		$url = "http://www.crossref.org/openurl/?url_ver=Z39.88-2004&req_dat=$crossRefId&rft_id=info:http://" . urlencode(str_replace(Array("http://", "&noredirect=true"), Array("", ""), urldecode($url1)));
		if (!($result = @simplexml_load_file($url)->query_result->body->query)) echo "\n xxx Error loading simpleXML file from CrossRef via URL. ";
		if ($debug) print $url . "<BR>";
		if ($result["status"]=="resolved") return $result;
		print "URL search failed.  Trying other parameters... ";
	}
	global $fastMode;
	if ($fastMode || !$author || !($journal || $issn) ) return;
	// If fail, try again with fewer constraints...
	echo "Full search failed. Dropping author & endpage... ";
	$url = "http://www.crossref.org/openurl/?noredirect=true&pid=$crossRefId";
	if ($title) $url .= "&atitle=" . urlencode(deWikify($title));
	if ($issn) $url .= "&issn=$issn"; elseif ($journal) $url .= "&title=" . urlencode(deWikify($journal));
	if ($year) $url .= "&date=" . urlencode($year);
	if ($volume) $url .= "&volume=" . urlencode($volume);
	if ($startpage) $url .= "&spage=" . urlencode($startpage);
	if (!($result = @simplexml_load_file($url)->query_result->body->query)) echo "\n xxx Error loading simpleXML file from CrossRef.";
	if ($result["status"]=="resolved") {print " Successful! - $url; -"; return $result;}
}

function textToSearchKey($key){
	switch (strtolower($key)){
		case "doi": return "AID";
		case "author": case "author1": return "Author";
		case "author": case "author1": return "Author";
		case "issue": return "Issue";
		case "journal": return "Journal";
		case "pages": case "page": return "Pagination";
		case "date": case "year": return "Publication Date";
## Formatting: YYY/MM/DD Publication Date [DP]
		case "title": return "Title";
		case "pmid": return "PMID";
		case "volume": return "Volume";
		##Text Words [TW] ; Title/Abstract [TIAB]
	}
	return false;
}

/* pmSearch
 *
 * Searches pubmed based on terms provided in an array.
 * Provide an array of wikipedia parameters which exist in $p, and this function will construct a Pubmed seach query and
 * return the results as array (first result, # of results)
 * If $check_for_errors is true, it will return 'fasle' on errors returned by pubmed
 */
function pmSearch($p, $terms, $check_for_errors = false) {
  foreach ($terms as $term) {
    $key = textToSearchKey($term);
    if ($key && trim($p[$term][0]) != "") {
      $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($p[$term][0])) . "[$key])";
    }
  }
  $query = substr($query, 5);
  $url = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&tool=DOIbot&email=martins+pubmed@gmail.com&term=$query";
  $xml = simplexml_load_file($url);
  if ($check_for_errors && $xml->ErrorList) {
    print "\n - Errors detected in PMID search; abandoned.";
    return array(null, 0);
  }

  return $xml?array((string)$xml->IdList->Id[0], (string)$xml->Count):array(null, 0);// first results; number of results
}

/* pmSearchResults
 *
 * Performs a search based on article data, using the DOI preferentially, and failing that, the rest of the article details.
 * Returns an array:
 *   [0] => PMID of first matching result
 *   [1] => total number of results
 *
 */
function pmSearchResults($p){
	if ($p) {
    if ($p['doi'][0]) {
      $results = pmSearch($p, array("doi"), true);
      if ($results[1] == 1) return $results;
    }
    // If we've got this far, the DOI was unproductive or there was no DOI.

    if (is("journal") && is("volume") && is("pages")) {
      $results = pmSearch($p, array("journal", "volume", "issue", "pages"));
      if ($results[1] == 1) return $results;
    }

    if (is("title") && (is("author") || is("author") || is("author1") || is("author1"))) {
      $results = pmSearch($p, array("title", "author", "author", "author1", "author1"));
      if ($results[1] == 1) return $results;
      if ($results[1] > 1) {
        $results = pmSearch($p, array("title", "author", "author", "author1", "author1", "year", "date"));
        if ($results[1] == 1) return $results;
        if ($results[1] > 1) {
          $results = pmSearch($p, array("title", "author", "author", "author1", "author1", "year", "date", "volume", "issue"));
          if ($results[1] == 1) return $results;
        }
      }
    }
  }
}

function pmArticleDetails($pmid, $id = "pmid"){
	$result = Array();
	$xml = simplexml_load_file("http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?tool=DOIbot&email=martins@gmail.com&db=" . (($id == "pmid")?"pubmed":"pmc") . "&id=$pmid");
  // Debugging URL : view-source:http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&tool=DOIbot&email=martins@gmail.com&id=

  foreach($xml->DocSum->Item as $item) {
    if (preg_match("~10\.\d{4}/[^\s\"']*~", $item, $match)) $result["doi"] = $match[0];
		switch ($item["Name"]) {
							case "Title": $result["title"] = str_replace(array("[", "]"), "",(string) $item);
			break; 	case "PubDate": preg_match("~(\d+)\s*(\w*)~", $item, $match);
																$result["year"] = (string) $match[1];
															//	$result["month"] = (string) $match[2]; DISABLED BY EUBLIDES
			break; 	case "FullJournalName": $result["journal"] = (string) $item;
			break; 	case "Volume": $result["volume"] = (string) $item;
			break; 	case "Issue": $result["issue"] = (string) $item;
			break; 	case "Pages": $result["pages"] = (string) $item;
			break; 	case "PmId": $result["pmid"] = (string) $item;
			break; 	case "ISSN": // $result["issn"] = (string) $item; DISABLED BY EUBLIDES
			break; 	case "AuthorList":
        $i = 0;
				foreach ($item->Item as $subItem) {
          $i++;
          if (authorIsHuman((string) $subItem)) {
            $jr_test = jrTest($subItem);
            $subItem = $jr_test[0];
            $junior = $jr_test[1];
            if (preg_match("~(.*) (\w+)$~", $subItem, $names)) {
              $result["last$i"] = formatSurname($names[1]) . $junior;
              $result["first$i"] = formatForename($names[2]);
            }
          } else {
            // We probably have a committee or similar.  Just use 'author$i'.
            $result["author$i"] = (string) $subItem;
          }
        }
      break; case "LangList":
        break; // Disabled at the request of EUBULIDES
      /*
        foreach ($item->Item as $subItem) {
            if ($subItem["Name"] == "Lang" && $subItem != "English" && $subItem != "Undetermined") {
              $result ["language"] = (string) $subItem;
              if ($result['title']) {
                $result['trans_title'] = $result['title'];
                unset ($result['title']);
              }
            }
          }
			break; */case "ArticleIds":
				foreach ($item->Item as $subItem) {
					switch ($subItem["Name"]) {
						case "pubmed":
                preg_match("~\d+~", (string) $subItem, $match);
                $result["pmid"] = $match[0];
                break;
						case "pmc":
              preg_match("~\d+~", (string) $subItem, $match);
              $result["pmc"] = $match[0];
              break;
						case "doi": case "pii":
              if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $subItem, $match)) {
                $result["doi"] = $match[0];
              }
              break;
            default:
              if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $subItem, $match)) {
                $result["doi"] = $match[0];
              }
              break;
					}
				}
      break;
		}
	}
	return $result;
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

function google_book_expansion() {
  global $p;
  if (is("url") && preg_match("~books\.google\.[\w\.]+/.*\bid=([\w\d\-]+)~", $p["url"][0], $gid)) {
    $url = $p["url"][0];
    if (strpos($url, "#")) {
      $url_parts = explode("#", $url);
      $url = $url_parts[0];
      $hash = "#" . $url_parts[1];
    }
    $url_parts = explode("&", str_replace("?", "&", $url));
    $url = "http://books.google.com/?id=" . $gid[1];
    foreach ($url_parts as $part) {
      $part_start = explode("=", $part);
      switch ($part_start[0]) {
        case "dq": case "pg": case "lpg": case "q": case "printsec": case "cd": case "vq":
          $url .= "&" . $part;
        // TODO: vq takes precedence over dq > q.  Only use one of the above.
        case "id":
          break; // Don't "remove redundant"
        case "as": case "useragent": case "as_brr": case "source":  case "hl":
        case "ei": case "ots": case "sig": case "source": case "lr":
        case "as_brr": case "sa": case "oi": case "ct": case "client": // List of parameters known to be safe to remove
        default:
          echo "\n - $part";
          $removed_redundant++;
      }
    }
    if ($removed_redundant > 1) { // http:// is counted as 1 parameter
      $p["url"][0] = $url . $hash;
    }
    google_book_details($gid[1]);
    return true;
  }
  return false;
}

function google_book_details ($gid) {
  $google_book_url = "http://books.google.com/books/feeds/volumes/$gid";
  $simplified_xml = str_replace(":", "___", file_get_contents($google_book_url));
  $xml = simplexml_load_string($simplified_xml);
  if ($xml->dc___title[1]) {
    ifNullSet("title", str_replace("___", ":", $xml->dc___title[0] . ": " . $xml->dc___title[1]));
  } else {
    ifNullSet("title", str_replace("___", ":", $xml->title));
  }
  /*  Possibly contains dud information on occasion
  ifNullSet("publisher", str_replace("___", ":", $xml->dc___publisher));
    */
  foreach ($xml->dc___identifier as $ident) {
    if (preg_match("~isbn.*?([\d\-]{9}[\d\-]+)~i", (string) $ident, $match)) {
      $isbn = $match[1];
    }
  }
  ifNullSet("isbn", $isbn);
  // Don't set 'pages' parameter, as this refers to the CITED pages, not the page count of the book.
  $i = null;
  if (!is("editor") && !is("editor1") && !is("editor1-last") && !is("editor-last")
          && !is("author") && !is("author1") && !is("last") && !is("last1")
          && !is("publisher")) { // Too many errors in gBook database to add to existing data.   Only add if blank.

    foreach ($xml->dc___creator as $author) {
      $i++;
      ifNullSet("author$i", formatAuthor(str_replace("___", ":", $author)));
    }
  }
  ifNullSet("date", $xml->dc___date);
}

function findISBN($title, $auth=false){
	global $isbnKey2;
	$title = trim($title); $auth=trim($auth);
	$xml = simplexml_load_file("http://isbndb.com/api/books.xml?access_key=$isbnKey2&index1=combined&value1=" . urlencode($title . " " . $auth));
	if (false) dbg(array(
		$title  => $author,
		(string) $xml->BookList->BookData[$i]->Title => similar_text($xml->BookList->BookData[$i]->Title, $title),
		(string) $xml->BookList->BookData[$i]->Title . "au" => similar_text($xml->BookList->BookData[$i]->Title, $author),
		(string) $xml->BookList->BookData[$i]->TitleLong => similar_text($xml->BookList->BookData[$i]->TitleLong, $title),
		(string) $xml->BookList->BookData[$i]->TitleLong  . "au"=> similar_text($xml->BookList->BookData[$i]->TitleLong, $author),
		(string) $xml->BookList->BookData[$i]->AuthorsText => similar_text($xml->BookList->BookData[$i]->AuthorsText, $title),
		(string) $xml->BookList->BookData[$i]->AuthorsText ."au"=> similar_text($xml->BookList->BookData[$i]->AuthorsText, $author)
	));
	if ($xml->BookList["total_results"] == 1) return (string) $xml->BookList->BookData["isbn"];
	if ($auth && $title) return ($xml->BookList["total_results"] > 0)?(string) $xml->BookList->BookData["isbn"]:false;
}

function getInfoFromISBN(){
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
	if ($xml["stat"]=="ok") {
		foreach ($params as $key => $value)	{
			if (preg_match("~[^\[\]<>]+~", $xml->isbn[$value], $match)) {
        ifNullSet($key, $match[0]);
      }
		}
		if (substr($p["author"][0], 0, 3) == "by ") $p["author"][0] =substr( $p["author"][0], 3);
		if (preg_match("~\d+~", $p["oclc"][0], $match)) $p["oclc"][0] = $match[0];
	} else {
		$xml = simplexml_load_file("http://isbndb.com/api/books.xml?access_key=$isbnKey&index1=isbn&value1=". urlencode($p["isbn"][0]));
		if ($xml->BookList["total_results"] >0){
			$params = array("title"=>"Title", "author"=>"AuthorsText", "publisher"=>"PublisherText");
			if (is("author") || is("first") || is("author1") || is("author1")) unset($params["author"]);
			foreach ($params as $key => $value)	if (!is($key)) $p[$key][0] = niceTitle((string) $xml->BookList->BookData->$value);
		} else if ($xml->ErrorMessage) print "<p class=warning>Error: Daily limit of ISBN queries has been exceeded!</p>";
		else $p["ISBN-status"][0] = "May be invalid - please double check";
	}
}

function useUnusedData()
{
	// See if we can use any of the parameters lacking equals signs:
	global $p;

  // Separate up the unused data by pipes, into "$freeDat"
  $freeDat = explode("|", trim($p["unused_data"][0]));

  // Empty the parameter.  We'll put back anything we don't manage to assign to a parameter.
	unset($p["unused_data"]);

  if (isset($freeDat[0]))
  {
		foreach ($freeDat as $dat)
    {
      // If the unused data starts with a pipe, the first dat will be blank, so there's no point in checking it.
      if ($dat)
      {
        $dat = trim($dat);

        $endnote_test = explode("\n%", "\n" . $dat);
        if ($endnote_test[1]) {
          foreach ($endnote_test as $endnote_line) {
            switch ($endnote_line[0]) {
              case "A":
                $endnote_authors++;
                $endnote_parameter = "author$endnote_authors";
                break;
              case "D":
                $endnote_parameter = "date";
                break;
              case "I":
                $endnote_parameter = "publisher";
                break;
                break;
              case "J":
                $endnote_parameter = "journal";
                break;
              case "N":
                $endnote_parameter = "issue";
                break;
              case "P":
                $endnote_parameter = "pages";
                break;
              case "T":
                $endnote_parameter = "title";
                break;
              case "8":
                $dat = trim(str_replace("\n%$endnote_line", "", "\n$dat"));
              case "U":
                $endnote_parameter = "url";
                break;
              case "V":
                $endnote_parameter = "volume";
                break;
              case "0":// Citation type
              case "X": // Abstract
              case "@": // ISSN
              case "M": // Object identifier
                $dat = trim(str_replace("\n%$endnote_line", "", "\n" . $dat));
              default:
                $endnote_parameter = false;
            }
            if ($endnote_parameter && ifNullSet($endnote_parameter, substr($endnote_line, 1))) {
              global $smartSum;
              if (!strpos("Converted Endnote citation to WP format", $smartSum)) {
                $smartSum .= "Converted Endnote citation to WP format. ";
              }
              $dat = trim(str_replace("\n%$endnote_line", "", "\n$dat"));
            }
          }
        }
        if (preg_match("~^TY  - [A-Z]+~", $dat)) {
          // RIS formatted data:
          $ris = explode("\n", $dat);
          foreach ($ris as $ris_line) {
            $ris_part = explode(" - ", $ris_line . " ");
            switch (trim($ris_part[0])) {
              case "T1":
                $ris_parameter = "title";
                break;
              case "AU":
                $ris_authors++;
                $ris_parameter = "author$ris_authors";
                $ris_part[1] = formatAuthor($ris_part[1]);
                break;
              case "Y1":
                $ris_parameter = "date";
                break;
              case "SP":
                $start_page = trim($ris_part[1]);
                $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
                break;
              case "EP":
                $end_page = trim($ris_part[1]);
                $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
                ifNullSet("pages", $start_page . "-" . $end_page);
                break;
              case "DO":
                $ris_parameter = "doi";
                break;
              case "JO":
                $ris_parameter = "journal";
                break;
              case "VL":
                $ris_parameter = "volume";
                break;
              case "IS":
                $ris_parameter = "issue";
                break;
              case "SN":
                $ris_parameter = "issn";
                break;
              case "UR":
                $ris_parameter = "url";
                break;
              case "PB":
                $ris_parameter = "publisher";
                break;
              case "M3": case "PY": case "N1": case "N2": case "ER": case "TY":
                $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
              default:
                $ris_parameter = false;
            }
            unset($ris_part[0]);
            if ($ris_parameter
                    && ifNullSet($ris_parameter, trim(implode($ris_part)))
                ) {
              global $smartSum;
              if (!strpos("Converted RIS citation to WP format", $smartSum)) {
                $smartSum .= "Converted RIS citation to WP format. ";
              }
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
            }
          }

        }

        // Load list of parameters used in citation templates.
        //We generated this earlier in expandFns.php.  It is sorted from longest to shortest.
        global $parameter_list;

        $shortest = -1;
        foreach ($parameter_list as $parameter)
        {
          $para_len = strlen($parameter);
          if (substr($dat, 0, $para_len) == $parameter) {
            $character_after_parameter = substr(trim(substr($dat, $para_len)), 0, 1);
            $parameter_value = ($character_after_parameter == "-")?substr(trim(substr($dat, $para_len)), 1):substr($dat, $para_len);
            ifNullSet($parameter, $parameter_value);
            break;
          }
          $test_dat = preg_replace("~\d~", "_$0",
                      preg_replace("~[ -+].*$~", "", substr(strtolower($dat), 0, $para_len)));
          if ($para_len < 3)
          {
            break; // minimum length to avoid false positives
          }

          if (preg_match("~\d~", $parameter))
          {
            $lev = levenshtein($test_dat, preg_replace("~\d~", "_$0", $parameter));
            $para_len++;
          }
          else
          {
            $lev = levenshtein($test_dat, $parameter);
          }
          if ($lev == 0)
          {
            $closest = $parameter;
            $shortest = 0;
            break;
          }
          // Strict inequality as we want to favour the longest match possible
          if ($lev < $shortest || $shortest < 0)
          {
            $comp = $closest;
            $closest = $parameter;
            $shortish = $shortest;
            $shortest = $lev;
          }
          // Keep track of the second shortest result, to ensure that our chosen parameter is an out and out winner
          else if ($lev < $shortish)
          {
            $shortish = $lev;
            $comp = $parameter;
          }
        }

        if ($shortest < 3
           && (similar_text($shortest, $test_dat) / strlen($test_dat) > 0.4)
           &&  ($shortest + 1 < $shortish  // No close competitor
               || $shortest / $shortish <= 2/3
               || strlen($closest) > strlen($comp)
               )
           )
        {
            // remove leading spaces or hyphens (which may have been typoed for an equals)
            if (preg_match("~^[ -+]*(.+)~", substr($dat, strlen($closest)), $match))
            {
              set ($closest, $match[1]/* . " [$shortest / $comp = $shortish]"*/);
            }

        }
        // Is the data a URL, and is the URL parameter blank?
        else if (substr(trim($dat), 0, 7) == 'http://' && !isset($p['url']))
        {
          set ("url", $dat);
        }
        // Is it a number formatted like an ISBN?
        elseif (preg_match("~(?!<\d)(\d{10}|\d{13})(?!\d)~", str_replace(Array(" ", "-"), "", $dat), $match))
        {
          set("isbn", $match[1]);
          $pAll = "";
        }
        else
        {
          // Extract whatever appears before the first space, and compare it to common parameters
          $pAll = explode(" ", trim($dat));
          $p1 = strtolower($pAll[0]);
          switch ($p1) {
          case "volume": case "vol":
          case "pages": case "page":
          case "year": case "date":
          case "title":
          case "authors": case "author":
          case "issue":
          case "journal":
          case "accessdate":
          case "archiveurl":
          case "archivedate":
          case "format":
          case "url":
          if (!is($p1)) {
            unset($pAll[0]);
            $p[$p1][0] = implode(" ", $pAll);
          }
          break;
          case "issues":
          if (!is($p1)) {
            unset($pAll[0]);
            $p['issue'][0] = implode(" ", $pAll);
          }
          break;
          case "access date":
          if (!is($p1)) {
            unset($pAll[0]);
            $p['accessdate'][0] = implode(" ", $pAll);
          }
          break;
          default:
            // No good; we'll have to return it to the unused data parameter
            $i++;
            $p["unused_data"][0] .= "|" . implode(" ", $pAll);
          }
        }
      }
		}
	}
}


// Pass $p and this function will check each parameter name against the list of accepted names (loaded in expand.php).
// It will correct any that appear to be mistyped.
function correct_parameter_spelling($p) {
  global $parameter_list;
  foreach ($p as $key => $value) {
    $parameters_used[] = $key;
  }
  $unused_parameters = array_diff($parameter_list, $parameters_used);

  // Common mistakes that aren't picked up by the levenshtein approach
  $common_mistakes = array (
                            "author2-last"  =>  "last2",
                            "author3-last"  =>  "last3",
                            "author4-last"  =>  "last4",
                            "author5-last"  =>  "last5",
                            "author6-last"  =>  "last6",
                            "author7-last"  =>  "last7",
                            "author8-last"  =>  "last8",
                            "author2-first" =>  "first2",
                            "author3-first" =>  "first3",
                            "author4-first" =>  "first4",
                            "author5-first" =>  "first5",
                            "author6-first" =>  "first6",
                            "author7-first" =>  "first7",
                            "author8-first" =>  "first8",
                            "authorurl"     =>  "authorlink",
                            "authorn"       =>  "author2",
                            "authors"       =>  "author",
                            "ed"            =>  "editor",
                            "ed2"           =>  "editor2",
                            "ed3"           =>  "editor3",
                            "editorlink1"   =>  "editor1-link",
                            "editorlink2"   =>  "editor2-link",
                            "editorlink3"   =>  "editor3-link",
                            "editorlink4"   =>  "editor4-link",
                            "editor1link"   =>  "editor1-link",
                            "editor2link"   =>  "editor2-link",
                            "editor3link"   =>  "editor3-link",
                            "editor4link"   =>  "editor4-link",
                            "editorn"       =>  "editor2",
                            "editorn-link"  =>  "editor2-link",
                            "editorn-last"  =>  "editor2-last",
                            "editorn-first" =>  "editor2-first",
                            "firstn"        =>  "first2",
                            "ibsn"          =>  "isbn",
                            "lastn"         =>  "last2",
                            "number"        =>  "issue",
                            "origmonth"     =>  "month",
                            "translator"    =>  "others",
                            "translators"   =>  "others",
                            "vol"           =>  "volume",
                            );

  unset($p[""]);
  foreach ($p as $key => $value) {
    if (!in_array($key, $parameter_list))
    {
      print "\n  *  Unrecognised parameter $key ";
      $shortest = -1;

      // Check the parameter list to find a likely replacement
      foreach ($unused_parameters as $parameter)
      {
        $lev = levenshtein($key, $parameter, 5, 4, 6);

        // Strict inequality as we want to favour the longest match possible
        if ($lev < $shortest || $shortest < 0)
        {
          $comp = $closest;
          $closest = $parameter;
          $shortish = $shortest;
          $shortest = $lev;
        }
        // Keep track of the second-shortest result, to ensure that our chosen parameter is an out and out winner
        else if ($lev < $shortish)
        {
          $shortish = $lev;
          $comp = $parameter;
        }
      }
      $key_len = strlen($key);

      // Account for short words...
      if ($key_len < 4) {
        $shortest *= ($key_len / similar_text($key, $closest));
        $shortish *= ($key_len / similar_text($key, $comp));
      }
      if ($shortest < 12 && $shortest < $shortish)
      {
        $mod[$key] = $closest;
        print "replaced with $closest (likelihood " . (12 - $shortest) . "/12)";
      }
      else
      {
        $similarity = similar_text($key, $closest) / strlen($key);
        if ($similarity > 0.6)
        {
          $mod[$key] = $closest;
          print "replaced with $closest (similarity " . round(12 * $similarity, 1) . "/12)";
        }
        else
        {
          print "could not be replaced with confidence.  Please check the citation yourself.";
        }
      }
    }
  }
  // Now check for common mistakes.  This will over-ride anything found by levenshtein: important for "editor1link" !-> "editor-link".
  foreach ($common_mistakes as $mistake => $corrected) {
    if (isset($p[$mistake])) {
      $mod[$mistake] = $corrected;
    }
  }
  foreach ($mod as $wrong => $right) {
    if (ifNullSet($right, $p[$wrong][0])) {
      $p[$right] = $p[$wrong];
      unset ($p[$wrong]);
    }
  }
  return $p;
}

// Check that a DOI is correctly formatted and modify it if not
function verify_doi ($doi) {
    // DOI not correctly formatted
    switch (substr($doi, -1)) {
      case ".":
        // Missing a terminal 'x'?
        $trial[] = $doi . "x";
      case ",": case ";":
        // Or is this extra punctuation copied in?
        $trial[] = substr($doi, 0, -1);
    }
    if (substr($doi, 0, 3) != "10.") {
      // missing the start
      $trial[] = $doi;
    }
    if (preg_match("~^(.+)10.\d{4}/~", trim($doi), $match)) {
      $trial[] = $match[1];
    }

    $replacements = array (
      "&lt;" => "<",
      "&gt;" => ">",
    );
    if (preg_match("~&(l|g)t;~", $doi)) {
      $trial[] = str_replace(array_keys($replacements), $replacements, $doi);
    }
    foreach ($trial as $try) {
      // Check that it begins with 10.
      if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) {
        $try = "10." . $match[1];
      }
      if (crossRefData($try)) {
        set("doi", $try);
        return ($try);
      }
    }
    return ($doi);
}

function file_size($url, $redirects=0){

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
			if($redirects<3 && substr(strtolower ($header), 0, strlen($s)) == strtolower($s)) {
				$url = trim(substr($header, strlen($s)));
				return file_size($url, $redirects+1);
			}
			// parse for content length
       $s = "Content-Length: ";
       if(substr(strtolower ($header), 0, strlen($s)) == strtolower($s)) {
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
	return str_replace(Array("[", "]", "'''", "''", "&"), Array("", "", "'", "'", ""), preg_replace(Array("~<[^>]*>~", "~\&[\w\d]{2,7};~", "~\[\[[^\|]*\|([^\]]*)\]\]~"), Array("", "", "$1"),  $string));
}

function findDoi($url){
	global $urlsTried, $slow_mode;

	if (!@array_search($url, $urlsTried)){

		//Copied from Cite.php.  We should really use a common function?
		//Check that it's not in the URL to start with
		if (preg_match("|/(10\.\d{4}/[^?]*)|i", urldecode($url), $doi)) {echo "Found DOI in URL.<br>"; $urlsTried[] = $url;  return $doi[1];}

		//Try meta tags first.
    $meta = @get_meta_tags($url);
		if ($meta) {
			ifNullSet("pmid", $meta["citation_pmid"]);
      foreach ($meta as $oTag){
				if (preg_match("~^\s*10\.\d{4}/\S*\s*~", $oTag)) {
          $doi = $oTag;
          break;
        }
			}
		}

		if (!$doi) {//If we've not scraped the DOI, we'll have to hope that it's mentioned somewhere in the text!
			if (substr($url, -4) == ".pdf") {
				//Check file isn't going to overload our memory
				$ch = curl_init();
				curlSetup($ch, $url);
				curl_setopt($ch, CURLOPT_HEADER, 1);
				curl_setopt($ch, CURLOPT_NOBODY, 1);
				preg_match ("~Content-Length: ([\d,]+)~", curl_exec($ch), $size);
				curl_close($ch);
			} else $size[1] = 1; // Temporary measure; return to 1!
			if ($slow_mode && $size[1] > 0 &&  $size[1] < 100000) { // TODO. The bot seems to keep crashing here; let's evaluate whether it's worth doing.  For now, restrict to slow mode.
				echo "\nQuerying URL with reported file size of ", $size[1], "b...<br>\n";
				//Initiate cURL resource
				$ch = curl_init();
				curlSetup($ch, $url);
				$source = curl_exec($ch);
				if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {echo "404 returned from URL.<br>"; return false;}
				if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 501) {echo "501 returned from URL.<br>"; return false;}
				curl_close($ch);
				if (strlen($source) < 100000) {
					$doi = getDoiFromText($source, true);
					if (!$doi) {
            checkTextForMetas($source);
          }
				} else echo "\nFile size was too large. Abandoned.";
			} else echo $htmlOutput?("\n\n **ERROR: PDF may have been too large to open.  File size: ". $size[1]. "b<br>"):"\nPDF too large ({$size[1]}b)";
		} else print "DOI found from meta tags.<br>";
		if ($doi){
			if (!preg_match("/>\d\.\d\.\w\w;\d/", $doi))
			{ //If the DOI contains a tag but doesn't conform to the usual suntax with square brackes, it's probably picked up an HTML entity.
				echo "DOI may have picked up some tags. ";
				$content = strip_tags(str_replace("<", " <", $source)); // if doi is superceded by a <tag>, any ensuing text would run into it when we removed tags unless we added a space before it!
				preg_match("~" . doiRegexp . "~Ui", $content, $dois); // What comes after doi, then any nonword, but before whitespace
				if ($dois[1]) {$doi = trim($dois[1]); print " Removing them.<br>";} else {
					print "More probably, the DOI was itself in a tag. CHECK it's right!<br>";
					//If we can't find it when tags have been removed, it might be in a <a> tag, for example.  Use it "neat"...
				}
			}
			$urlsTried[] = $url;
			return urldecode($doi);
		} else {
			$urlsTried[] = $url;
			return false;
		}
	} else echo "URL has been scraped already - and scrapped.<br>";
	if (!$doi) $urlsTried[] = $url; //Log barren urls so we don't search them again.  We may want to search
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
			if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {echo "404 returned from URL.<br>"; return false;}
			if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 501) {echo "501 returned from URL.<br>"; return false;}
			curl_close($ch);
			if (!$source) {echo "Page appears to be blank! <br>"; return false;}
			echo "..)<br>";
			if (strlen($title) < 15) {echo "Title ($title) is under 15 characters: too short to use."; return false;}
			$lSource = literate($source);
			$lTitle = literate($title);
			if (preg_match("|[=/](10\.\d{4}/[^?&]*)|", str_replace("%2F", "/", $url), $doiX)){
				//If there is a DOI in the URL
				if (preg_match("~spanclasstitle".$lTitle."~", $lSource)) {echo "DOI found in URL. Web page has correct title.<br>"; return $doiX[1];				}
				else echo "URL contains DOI, but may not refer to the correct article.<br>";
			}
			// Now check the META tags
			preg_match('~<meta name="citation_doi" content="' . doiRegexp . '">~iU', $source, $meta)?"":preg_match('~<meta name="dc.Identifier" content="' . doiRegexp . '">~iU', $source, $meta);
			if ($meta) {
				$lsTitle = literate(strip_tags($title));
				preg_match('~<meta name="citation_title" content="(.*)">~iU', $source, $metaTitle)?"":preg_match('~<meta name="dc.Title" content="(.*)">~iU', $source, $metaTitle);
				if (literate($metaTitle[1]) == $lsTitle) {echo "DOI found in meta tags: $meta[1]<br>"; return $meta[1];}
				echo "Meta info found but title does not match.<br>" . literate($metaTitle[1]) . " != <br>$lsTitle</i><br>";
			}
			//No luck? Find the DOIs in the source.
			$doi = getDoiFromText($source);
			if (!$doi) {$urlsTried[] = $url; echo "URL is barren.<br>"; return false;}
			echo "A DOI has been found. " ;
			return $doi;
		}	else {
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
						echo "Unique DOI found, early in document (Offset = " . $thisDoi[0][1] . "). $doi<br>";
						return $dois[1][0];
					} else {echo "A unique DOI found early in document (Offset = " . $thisDoi[0][1] . "), but without the required title.<br>"; return false;}
				} else {
					echo "A unique DOI was found, but late in the document (offset = {$thisDoi[0][1]}). Removing HTML to get a clearer picture...<br>";
					$position = strpos(strip_tags($source), $dois[1][0]);
					if ($position < (early/2)) {
						echo "Nice and early: $position . Accepting DOI.<br>"; return $dois[1][0];
					} else{
						echo "Still too late ($position).  Rejecting."; return false;
					}
				}
			} else {echo "\n2 different DOIs were found. Abandoned.<br>"; return false;}
		} else {echo "\nNo DOIs found in page."; $urlsTried[] = $url; RETURN false;}
	}
	echo "Searched body text.<br>";
}

function checkTextForMetas($text){
  // Find all meta tags using preg_match
	preg_match_all("~<meta\s+name=['\"]([^'\"]+)['\"] content=[\"']([^>]+)[\"']>~", $text, $match);

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
	$stopI = count($match[1]);
	for ($i = 0; $i < $stopI; $i++) {
     $pairs[] = array($match[1][$i], $match[2][$i]);
  }

  // Rename each pair to $newp  (wiki name => value)
	foreach ($pairs as $pair) {
		foreach ($m2p as $mk=>$mv) {
			if ($pair[0] == $mk) {
        $newp[$mv][] = $pair[1];
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
      $newp["author" . ($no + 1)] = $names[0];
      $newp["first" . ($no + 1)] = $names[1];
    }
  }
	if (isset($newp["date"])) {
		$newp["year"][0] = date("Y", strtotime($newp["date"][0]));
		//$newp["month"][0] = date("M", strtotime($newp["date"][0])); DISABLED BY EUBLIDES
		unset($newp["date"]);
	}
	foreach ($newp as $p=>$p0) ifNullSet($p, $p0[0]);
}

function literate($string){
	//remove all html, spaces and &eacute; things  from text, only leaving letters and digits
	preg_match_all("~(&[\w\d]*;)?(\w+)~", $string, $letters);
		foreach ($letters[2] as $letter) $return .= $letter;
	return strtolower($return);
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

/** Returns a properly capitalsied title.
 *  	If sents is true (or there is an abundance of periods), it assumes it is dealing with a title made up of sentences, and capitalises the letter after any period.
  *		If not, it will assume it is a journal abbreviation and won't capitalise after periods.
 */

function niceTitle($in, $sents = true){
	global $dontCap, $unCapped;

	if ($in == strtoupper($in) && strlen(str_replace(array("[", "]"), "", trim($in))) > 6) {
		$in = mb_convert_case($in, MB_CASE_TITLE, "UTF-8");
	}
  $in = str_ireplace(" (New York, N.Y.)", "", $in); // Pubmed likes to include this after "Science", for some reason
  $captIn = str_replace($dontCap, $unCapped, " " .  $in . " ");
	if ($sents || (substr_count($in, '.') / strlen($in)) > .07) { // If there are lots of periods, then they probably mark abbrev.s, not sentance ends
		$newcase = preg_replace("~(\w\s+)A(\s+\w)~", "$1a$2",
					preg_replace_callback("~\w{2}'[A-Z]\b~" /*Apostrophes*/, create_function(
	            '$matches',
	            'return strtolower($matches[0]);'
	        ), preg_replace_callback("~[?.!]\s+[a-z]~" /*Capitalise after punctuation*/, create_function(
	            '$matches',
	            'return strtoupper($matches[0]);'
	        ), trim($captIn))));
	} else {
		$newcase = preg_replace("~(\w\s+)A(\s+\w)~", "$1a$2",
					preg_replace_callback("~\w{2}'[A-Z]\b~" /*Apostrophes*/, create_function(
	            '$matches',
	            'return strtolower($matches[0]);'
	        ), trim(($captIn))));
	}
  if (in_array(" " . trim($newcase) . " ", $unCapped)) {
    // Keep "z/Journal" with lcfirst
    return $newcase;
  } else {
    // Catch "the Journal" --> "The Journal"
    return ucfirst($newcase);
  }
}

/** If crossRef has only sent us one author, perhaps we can find their surname in association with other authors on the URL
 *   Send the URL and the first author's SURNAME ONLY as $a1
 *  The function will return an array of authors in the form $return['authors'][3] = Author, The Third
 */
function findMoreAuthors($doi, $a1, $pages) {

  // If $pages is already interrupted by a non-digit, then it probably represents a range, so we can return it as is.
  if (preg_match("~\d\D+\d", $pages)) {
    $return['pages'] = $pages;
  }

  $stopRegexp = "[\n\(:]|\bAff"; // Not used currently - aff may not be necessary.
	$url = "http://dx.doi.org/$doi";
	echo "\n -*Looking for more authors @ $url:";

  echo "\n  - Using meta tags...";

  $meta_tags = get_meta_tags($url);
  if ($meta_tags["citation_authors"]) {
    $return['authors'] = formatAuthors($meta_tags["citation_authors"], true);
  }
  if (!$return['pages'] && !$return['authors']) {
    echo "\n  - Now scraping web-page.";
    //Initiate cURL resource
    $ch = curl_init();
    curlSetup($ch, $url);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 7);  //This means we can't get stuck.
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {echo "404 returned from URL.<br>"; return false;}
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 501) {echo "501 returned from URL.<br>"; return false;}
    $source = str_ireplace(
                array('&nbsp;', '<p ',          '<DIV '),
                array(' ',     "\r\n    <p ", "\r\n    <DIV "),
                curl_exec($ch)
               ); // Spaces before '<p ' fix cases like 'Title' <p>authors</p> - otherwise 'Title' gets picked up as an author's initial.
    $source = preg_replace(
                "~<sup>.*</sup>~U",
                "",
                str_replace("\n", "\n  ", $source)
              );
    curl_close($ch);
    if (strlen($source)<1280000) {

      // Pages - only check if we don't already have a range
      if (!$return['pages'] && preg_match("~^[\d\w]+$~", trim($pages), $page)) {
        // find an end page number first
        $firstPageForm = preg_replace('~d\?([^?]*)$~U', "d$1", preg_replace('~\d~', '\d?', preg_replace('~[a-z]~i', '[a-zA-Z]?', $page[0])));
        echo "\n Searching for page number with form $firstPageForm:";
        if (preg_match("~{$page[0]}\D{0,13}?($firstPageForm)~", trim($source), $pages)) { // 13 leaves enough to catch &nbsp;
          $return['pages'] = $page[0] . '-' . $pages[1];
          echo "found range $page[0] to $pages[1]";
        } else echo "not found.";
      }

      // Authors
      if (true || !$return['authors']) {
        // Check dc.contributor, which isn't correctly handled by get_meta_tags
        if (preg_match_all("~\<meta name=\"dc.Contributor\" +content=\"([^\"]+)\"\>~U", $source, $authors)){
          $return['authors']=$authors[1];
        } else if (true) {
          print "\n  - Text search for surname not yet robustly coded: Skipped.";
          // Delete this clause when we get a robust scraping algorithm.
        } elseif ($a1) {
          print "\n Searching $url for $a1 \n\n";
          $spaceAuth = "[^ ]* ?[^ ]* ?" . preg_quote($a1) . "[^\(:\n]*";

          if (preg_match("~<td[^>]*>($spaceAuth)</td~Ui", $source, $authorLine)) {
            print 'table cell:'; print_r($authorLine);
            $return['authors'] = formatAuthors(strip_tags($authorLine[1]), true);
          } elseif (preg_match("~$spaceAuth~i", strip_tags($source), $authorLine)) {
            print 'wholeline:' ; print_r($authorLine);
            $return['authors'] = formatAuthors($authorLine[0], true);
          } else {
            echo "\nAuthor $a1 could not be identified.<hr>\n";
            print $source; exit;
          }
        } else {
          echo "\nNo author specified";
        }
      }
    } else echo "\nFile size was too large. Abandoned.";
  }
	return $return;
}

function formatSurname($surname){
	$surname = strtolower(trim(str_replace("-", " - ", $surname)));
	if (substr($surname, 0, 2) == "o'") return "O'" . fmtSurname2(substr($surname, 2));
	else if (substr($surname, 0, 2) == "mc") return "Mc" . fmtSurname2(substr($surname, 2));
	else if (substr($surname, 0, 3) == "mac" && strlen($surname) > 5 && !strpos($surname, "-")) return "Mac" . fmtSurname2(substr($surname, 3));
	else if (substr($surname, 0, 1) == "&") return "&" . fmtSurname2(substr($surname, 1));
	else return fmtSurname2($surname); // Case of surname
}

function fmtSurname2($surname){
  return str_replace(" - ", "-", ucwords($surname));
}
function formatForename($forename){
	return str_replace(array(" ."), "", trim(preg_replace_callback("~\w{4,}~",  create_function(
            '$matches',
            'return ucfirst(strtolower($matches[0]));'
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
	return strtoupper(implode(".",$match[0]) . ".") . $end;
}
function isInitials($str){
	if (!$str) return false;
	if (strlen(str_replace(array("-", ".", ";"), "", $str)) >3) return false;
	if (strlen(str_replace(array("-", ".", ";"), "", $str)) ==1) return true;
	if (strtoupper($str) != $str) return false;
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

	#dbg($author, "formatAuthor");

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
				$fore = strtoupper(implode(".", $i));
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
	dbg(array("IN"=>$authors));
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

function formatTitle($title){
	$title = html_entity_decode($title,null,"UTF-8");
	if (substr($title, strlen($title)-1) == ".") $title = substr($title, 0, strlen($title)-1);
	if (substr($title, strlen($title)-6) == "&nbsp;") $title = substr($title, 0, strlen($title)-6);
	$iIn = array("<i>",
							"</i>",
							"From the Cover: ");
	$iOut = array("''",
								"''",
								"");
	$in = array("&lt;", "&gt;"	);
	$out = array("<",		">"			);
	return	str_ireplace($iIn, $iOut, str_replace($in, $out, niceTitle($title))); // order IS important!
}

/** Format authors according to author = Surname; first= N.E.
 * Requires the global $p
**/
function citeDoiOutputFormat() {
  global $p;
  unset ($p['']);
  // Cycle through authors
  for ($i = null; $i < 10; $i++) {
    if (strpos($p["author$i"][0], ', ')) {
      // $au is an array with two parameters: the surname [0] and forename [1].
      $au = explode(', ', $p["author$i"][0]);
      unset($p['author' . ($i)]);
      set("author$i", $au[0]); // i.e. drop the forename; this is safe in $au[1]
    } else if (is("first$i")) {
      $au[1] = $p["first$i"][0];
    } else {
       unset($au);
    }
    if ($au[1]) {
      if (trim(strtoupper(preg_replace("~(\w)\w*.? ?~", "$1. ", trim($au[1])))) != trim($p["first$i"][0])) {
        // Don't try to modify if we don't need to change
        set("first$i", strtoupper(preg_replace("~(\w)\w*.? ?~", "$1. ", trim($au[1])))); // Replace names with initials; beware hyphenated names!
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
			if (strtoupper($aut) == $aut) {
				$return["author"] .= " " . ucwords(str_replace(",", ", ", strtolower($aut))) . ";";
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
  print "assessing URL ";
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
	global $p;
	echo "<div style='font-size:small; color:#888'>[";
	if ($p["url"][0]){
	if (strpos($p["url"][0], "ttp://dx.doi.org/")==1) return $doi;
	$ch = curl_init();
	curlSetup($ch, "http://dx.doi.org/$doi");
	curl_setopt($ch, CURLOPT_NOBODY, 1);
	$source = curl_exec($ch);
	$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);
	echo equivUrl($effectiveUrl), "] DOI resolves to equivalent of this.<br>",
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