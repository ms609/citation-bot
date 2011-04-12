<?
// $Id$

// Returns pagecode if the calling script should continue; false otherwise
function expand($page, // Title of WP page
        $commit_edits = false,
        $editing_cite_doi_template = false, //If $editing_cite_doi_template = true, certain formatting changes will be applied for consistency.
        $cite_doi_start_code = null // $cite_doi_start_code is wikicode specified if creating a cite doi template.  (Possibly redundant now?)
        ) {
  global $bot, $editInitiator, $html_output, $modifications;
  if ($html_output === -1) {
    ob_start();
  }

  $file_revision_id = str_replace(array("Revision: ", "$", " "), "", '$Revision$');
  $doitools_revision_id = revisionID();
  if ($file_revision_id < $doitools_revision_id) {
    $last_revision_id = $doitools_revision_id;
  } else {
    $editInitiator = str_replace($doitools_revision_id, $file_revision_id, $editInitiator);
    $last_revision_id = $file_revision_id;
  }
  echo "\nRevision #$last_revision_id";

  echo $html_output > 0 ? ("\n<hr>[" . date("H:i:s", $started_page_at) . "] Processing page '<a href='http://en.wikipedia.org/wiki/$page' style='text-weight:bold;'>$page</a>' &mdash; <a href='http://en.wikipedia.org/?title=". urlencode($page)."&action=edit' style='text-weight:bold;'>edit</a>&mdash;<a href='http://en.wikipedia.org/?title=".urlencode($page)."&action=history' style='text-weight:bold;'>history</a> <script type='text/javascript'>document.title=\"Citation bot: '" . str_replace("+", " ", urlencode($page)) ."'\";</script>"):("\n\n\n*** Processing page '$page' : " . date("H:i:s"));

  $bot->fetch(wikiroot . "title=" . urlencode($page) . "&action=raw");
  $original_code = $bot->results;
  if (stripos($original_code, "#redirect") !== FALSE) {
    echo "Page is a redirect.";
    updateBacklog($page);
    return $original_code;
  }
  if (strpos($page, "Template:Cite") !== FALSE) {
    $editing_cite_doi_template = true;
  }
  if ($editing_cite_doi_template && !$original_code) {
    $original_code = $cite_doi_start_code;
  }

  if (preg_match("/\{\{nobots\}\}|\{\{bots\s*\|\s*deny\s*=[^}]*(Citation[ _]bot|DOI[ _]bot|all)[^}]*\}\}|\{\{bots\s*\|\s*allow=none\}\}/i", $original_code, $denyMsg)) {
    echo "**** Bot forbidden by bots / nobots tag: $denyMsg[0]";
    updateBacklog($page);
    $commit_edits = false;
  }
  $new_code = expand_text($original_code, $commit_edits, $editing_cite_doi_template,
          $cite_doi_start_code);


  if (strtolower($new_code) == strtolower($original_code)) {
    echo trim($new_code) ? "\n ** No changes required." : "\n ** Blank page retrieved.";
    echo "\n # # # \n";
    updateBacklog($page);
    if ($html_output === -1) {
      ob_end_clean();
    }
    return $original_code;
    // If no changes are necessary, we don't need to do anything.
  }


  ##### Generate edit summary #####
  if ($modifications["additions"]) {
    $auto_summary = "+: ";
    foreach ($modifications["additions"] as $param=>$v)	{
      $auto_summary .= "$param, ";
      unset($modifications["additions"][$param]);
    }
    $auto_summary = substr($auto_summary, 0, strlen($auto_summary)-2);
    $auto_summary .= ". ";
  }
  if ($modifications["additions"]["accessdate"]) {
    $auto_summary .= "Removed accessdate with no specified URL. ";
    unset($modifications["additions"]["accessdate"]);
  }
  if ($modifications["additions"]) {
    $auto_summary .= "Tweaked: ";
    foreach ($modifications["additions"] as $param=>$v)	$auto_summary .= 				"$param, ";
    $auto_summary = substr($auto_summary,0, strlen($auto_summary)-2);
    $auto_summary.=". ";
  }
  $auto_summary .= (($modifications["removed"])
          ? "Removed redundant parameters. "
          : ""
          ) . (($modifications["cite_type"] || $unify_citation_templates)
          ? "Unified citation types. "
          : ""
          ) . (($modifications["combine_references"])
          ? "Combined duplicate references. "
          : ""
          ) . (($modifications["dashes"])
          ? "Formatted [[WP:ENDASH|dashes]]. "
          : ""
          ) . (($modifications["arxiv_upgrade"])
          ? "Updated published arXiv refs. "
          : ""
  );
  if (!$auto_summary) {
    $auto_summary = "Misc citation tidying. ";
  }
  $modifications = null;
  echo $auto_summary;
  $edit_summary = $editInitiator . $auto_summary . $edit_summary_end;
  
  if ($commit_edits) {
    if (strpos($page, "andbox") > 1) {
       echo $html_output?"<br><i style='color:red'>Writing to <a href=\"http://en.wikipedia.org/w/index.php?title="
           . urlencode($page) . "\">$page</a> <small><a href=http://en.wikipedia.org/w/index.php?title="
           . urlencode($page) . "&action=history>history</a></small></i>\n\n</br><br>":"\n*** Writing to $page";
       write($page . $_GET["subpage"], $new_code, $editInitiator . "Citation maintenance: Fixing/testing bugs. "
         .	"Problems? [[User_talk:Smith609|Contact the bot's operator]]. ");
    } else {

      echo "<br><i style='color:red'>Writing to <a href=\"http://en.wikipedia.org/w/index.php?title=".urlencode($page)."\">$page</a> ... ";
      if (write($page . $_GET["subpage"], $new_code, $edit_summary) == "Success") {
        updateBacklog($page);
        echo "Success.";
      } else {
        echo "Edit may have failed. Retrying: <span style='font-size:1px'>xxx</span> ";
        if (write($page . $_GET["subpage"], $new_code, $edit_summary) == "Success") {
          updateBacklog($page);
          echo "Success.";
        } else {
          echo "Still no good. One last try: ";
          $status = write($page . $_GET["subpage"], $new_code, $edit_summary);
          if ($status == "Success") {
            updateBacklog($page);
            echo "Success. Phew!";
          } else {
            echo "Failed.  Error code:  $status. " . ($html_output?$outputText:"Pagecode displayed in HTML output only");
          }
        }
      }
      echo $html_output ?
             " <small><a href=http://en.wikipedia.org/w/index.php?title=" . urlencode($page) . "&action=history>history</a> / "
             . "<a href=http://en.wikipedia.org/w/index.php?title=" . urlencode($page) . "&diff=prev&oldid="
             . getLastRev($page) . ">last edit</a></small></i>\n\n<br>"
             : ".";
    }

    ##### Handle problems with cite doi templates #####
    if ($editing_cite_doi_template) {
      if (!articleID($page) && !$doiCrossRef && $oDoi) {
        leave_broken_doi_message($page, $article_in_progress, $oDoi);
      }
      $doiCrossRef = null;
    }
  }
  if ($html_output === -1) {
    ob_end_clean();
  }

  // These variables should change after the first edit
  $isbnKey = "3TUCZUGQ"; //This way we shouldn't exhaust theISBN key for on-demand users.
  #$isbnKey2 = "RISPMHTS"; //This way we shouldn't exhaust theISBN key for on-demand users.
  $edit_summary_end = " You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].";
  return $new_code;
}

// This function, given $original_code, returns the $text with any citation templates expanded as far as possible.

function expand_text ($original_code,
        $commit_edits = false,
        $editing_cite_doi_template = false, //If $editing_cite_doi_template = true, certain formatting changes will be applied for consistency.
        $cite_doi_start_code = null // $cite_doi_start_code is wikicode specified if creating a cite doi template.  (Possibly redundant now?)
        ) {
  global $p, $pStart, $editInitiator, $edit_summaryStart, $initiatedBy, $edit_summary_end,  $slowMode, $html_output;

  if ($html_output === -1) {
    ob_start();
  } 
  
  
  // Which template family is dominant?
  if (!$editing_cite_doi_template) {
    preg_match_all("~\{\{\s*[Cc]ite[ _](\w+)~", $original_code, $cite_x);
    preg_match_all("~\{\{\s*cite[ _](doi|pm|jstor|arx)~i", $original_code, $cite_id);
    preg_match_all("~\{\{\s*[Cc]itation\b(?! \w)~", $original_code, $citation);
    $cite_x_count = count ($cite_x[0]);
    $citation_count = count ($citation[0]);
    $cite_id_count = count ($cite_id[0]);
    if ($cite_id_count > 3 || $cite_id_count + 1 >= ($cite_x_count + $citation_count - $cite_id_count)) {
      echo "\n - switch to cite id format is supported.";
    }
    $harv_template_present = (stripos($original_code, "{{harv") === false)?false:true;
    if ($cite_x_count * $citation_count > 0) {
      // Two types are present
      $unify_citation_templates = true;
      $citation_template_dominant = ($cite_x_count < $citation_count);
      echo "\n * " . (($citation_template_dominant)?"\"Citation\"":'"Cite xxx"') . " format is dominant on this page: " .
           $cite_x_count . " cite / " . $citation_count . " citation." ;
    } else {
       $unify_citation_templates = false;
       $citation_template_dominant = false;
    }
  }
  // Start by fixing any sloppy wikicode:
  echo "\n Bare references:";    
  $new_code = preg_replace_callback("~(?P<open><ref[^>]*>)\[?(?P<url>http://(?:[^\s\]<]|<(?!ref))+) ?\]?\s*(?P<close></\s*ref>)~",
          create_function('$matches',
                  'return $matches["open"] . url2template($matches["url"], $citation_template_dominant) . $matches["close"];'
                  ),
          $original_code);
  
  // Check for baggage in a "Cite doi" template:
  $cite_doi_baggage_regexp = "~({{[cC]ite doi\s*\|\s*)d?o?i?\s*[:.,;>]?\s*~";
  if (preg_match($cite_doi_baggage_regexp, $new_code)) {
    echo "\n Correcting broken Cite doi template";
    $new_code = preg_replace($cite_doi_baggage_regexp, "$1", $new_code);
  }
  echo "\n Reference tags:";
  $new_code = rename_references(combine_duplicate_references(combine_duplicate_references(ref_templates(ref_templates(ref_templates(ref_templates($new_code, "doi"), "pmid"), "jstor"), "pmc"))));
  if (mb_ereg("p(p|ages)([\t ]*=[\t ]*[0-9A-Z]+)[\t ]*(" . to_en_dash . ")[\t ]*([0-9A-Z])", $new_code)) {
    $new_code = mb_ereg_replace("p(p|ages)([\t ]*=[\t ]*[0-9A-Z]+)[\t ]*(" . to_en_dash . ")[\t ]*([0-9A-Z])", "p\\1\\2" . en_dash . "\\4", $new_code);
    $modifications["dashes"] = true;
    echo "Converted dashes in all page parameters to en-dashes.";
  }
###################################  Cite web ######################################
  // Convert Cite webs to Cite arXivs, etc, if necessary
  if (false !== ($citation = preg_split("~{{((\s*[Cc]ite[_ ]?[wW]eb(?=\s*\|))([^{}]|{{.*}})*)([\n\s]*)}}~U", $new_code, -1, PREG_SPLIT_DELIM_CAPTURE))) {
    $new_code = null;
    $iLimit = (count($citation) - 1);
    for ($cit_i = 0; $cit_i < $iLimit; $cit_i += 5) {//Number of brackets in cite arXiv regexp + 1
      $started_citation_at = time();
      $p = parameters_from_citation($citation[$cit_i + 1]);
            
      //Make a note of how things started so we can give an intelligent edit summary
      foreach ($p as $param => $value)	{
        if (is($param)) {
          $pStart[$param] = $value[0];
        }
      }
      // See if we can use any of the parameters lacking equals signs:
      useUnusedData();
      if (trim(str_replace("|", "", $p["unused_data"][0])) == "") {
        unset($p["unused_data"]);
      }
      else if (substr(trim($p["unused_data"][0]), 0, 1) == "|") {
        $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
      }

      echo "\n* Cite web: {$p["title"][0]}";

      // Fix typos in parameter names
      //Authors
      if (isset($p["authors"]) && !isset($p["author"][0])) {$p["author"] = $p["authors"]; unset($p["authors"]);}
      preg_match("~[^.,;\s]{2,}~", $p["author"][0], $firstauthor);
      if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last"][0], $firstauthor);
      if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last1"][0], $firstauthor);

      // Delete any parameters >10, which won't be displayed anyway
      for ($au_i = 10; isset($p["last$au_i"]) || isset($p["author$au_i"]); $au_i++) {
        unset($p["last$au_i"]);
        unset($p["first$au_i"]);
        unset($p["author$au_i"]);
      }
      
      // Get identifiers from URL
      get_identifiers_from_url();

      // Now wikify some common formatting errors - i.e. tidy up!
      tidy_citation();

      // Now: Citation bot task 5.  If there's a journal parameter switch the citation to 'cite journal'.
      $change_to_journal = is('journal') || is('bibcode');
      $change_to_arxiv = is('arxiv');
      if (($change_to_arxiv || $change_to_journal) && is('eprint')) {
        rename_parameter('eprint', 'arxiv');
        $modifications["cite_type"] = true;
      } else if (is('arxiv') && !is('class')) {
        rename_parameter('arxiv', 'eprint');
      }

      //And we're done!
      $endtime = time();
      $timetaken = $endtime - $started_citation_at;
      echo "* Citation assessed in $timetaken secs. "
          . ($change_to_journal
              ?"Changing to Cite Journal. "
              :($change_to_arxiv
                ?"Changing to Arxiv. "
                :"Keeping as cite web. ")
              ) . "\n";
      $cText .= reassemble_citation($p); // This also populates $modifications["additions"] and $modifications["additions"]
      $last_p = $p;
      $p = null;
      
      $new_code .=  $citation[$cit_i] . ($cText?"{{" . ($change_to_journal?"cite journal":($change_to_arxiv?"cite arxiv":$citation[$cit_i+2])) . "$cText{$citation[$cit_i+4]}}}":"");
#				$new_code .=  $citation[$cit_i] . ($cText?"{{{$citation[$cit_i+2]}$cText{$citation[$cit_i+4]}}}":"");
      $cText = null;
      $crossRef = null;
    }

    $new_code .= $citation[$cit_i]; // Adds any text that comes after the last citation
  }



###################################  Cite arXiv ######################################
  // Makes sense to do this first as it might add DOIs, changing the citation type.
  if (false !== ($citation = preg_split("~{{((\s*[Cc]ite[_ ]?[aA]r[xX]iv(?=\s*\|))([^{}]|{{.*}})*)([\n\s]*)}}~U", $new_code, -1, PREG_SPLIT_DELIM_CAPTURE))) {
    $new_code = null;
    $iLimit = (count($citation)-1);
    for ($cit_i=0; $cit_i<$iLimit; $cit_i+=5) {//Number of brackets in cite arXiv regexp + 1
      $started_citation_at = time();
      $p = parameters_from_citation($citation[$cit_i+1]);
      //Make a note of how things started so we can give an intelligent edit summary
      foreach($p as $param=>$value)	{
        if (is($param)) {
          $pStart[$param] = $value[0];
        }
      }
      // See if we can use any of the parameters lacking equals signs:
      $freeDat = explode("|", trim($p["unused_data"][0]));
      useUnusedData();
      if (trim(str_replace("|", "", $p["unused_data"][0])) == "") unset($p["unused_data"]);
      else if (substr(trim($p["unused_data"][0]), 0, 1) == "|") $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);

      echo "\n* {$p["title"][0]}";

      // Fix typos in parameter names
      //Authors
      if (isset($p["authors"]) && !isset($p["author"][0])) {$p["author"] = $p["authors"]; unset($p["authors"]);}
      preg_match("~[^.,;\s]{2,}~", $p["author"][0], $firstauthor);
      if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last"][0], $firstauthor);
      if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last1"][0], $firstauthor);

      // Delete any parameters >10, which won't be displayed anyway
      for ($au_i = 10; isset($p["last$au_i"]) || isset($p["author$au_i"]); $au_i++) {
        unset($p["last$au_i"]);
        unset($p["first$au_i"]);
        unset($p["author$au_i"]);
      }

      // Is there already a date parameter?
      $dateToStartWith = (isset($p["date"][0]) && !isset($p["year"][0])) ;
      echo $p["eprint"][0] . "\n";
      if (is("eprint")
          && !(is("title") && is("author") && is("year") && is("version"))) {
          $p["eprint"][0] = str_ireplace("arXiv:", "", $p["eprint"][0]);
          echo " * Getting data from arXiv " . $p["eprint"][0];
          if (!get_data_from_arxiv($p["eprint"][0]) && is("class")) {
            get_data_from_arxiv($p["class"][0] . "/" . $p["eprint"][0]);
          }
      }

      if (is ("doi") && !is("journal")) {
        echo "\n    * Fill in journal from CrossRef?";
        get_data_from_doi($p["doi"][0]);
      }

      tidy_citation();

      // Now: Citation bot task 5.  If there's a journal parameter switch the citation to 'cite journal'.
      $change_to_journal = is('journal');
      if ($change_to_journal && is('eprint')) {
        rename_parameter('eprint', 'arxiv');
        unset($p['class']);
        $modifications["arxiv_upgrade"] = true;
      } else {
        $modifications["cite_type"] = false;
      }

      //And we're done!
      $endtime = time();
      $timetaken = $endtime - $started_citation_at;
      echo "* Citation assessed in $timetaken secs. " . ($change_to_journal?"Changing to Cite Journal. ":"Keeping as cite arXiv") . "\n";
      $cText .= reassemble_citation($p); // This also populates $modifications["additions"] and $modifications["additions"]

      $last_p = $p;
      $p = null;
      $new_code .=  $citation[$cit_i] . ($cText?"{{" . ($change_to_journal?"cite journal":$citation[$cit_i+2]) . "$cText{$citation[$cit_i+4]}}}":"");
      $cText = null;
      $crossRef = null;
    }

    $new_code .= $citation[$cit_i]; // Adds any text that comes after the last citation
  }

###################################  START ASSESSING BOOKS ######################################

  if (false !== ($citation = preg_split("~{{((\s*[Cc]ite[_ ]?[bB]ook(?=\s*\|))([^{}]|{{.*}})*)([\n\s]*)}}~U", $new_code, -1, PREG_SPLIT_DELIM_CAPTURE))) {
    $new_code = null;
    $iLimit = (count($citation)-1);

    for ($cit_i = 0; $cit_i < $iLimit; $cit_i += 5) {//Number of brackets in cite book regexp +1
      $started_citation_at = time();

      // Remove any comments so they don't confuse any regexps.
      if (preg_match_all("~<!--[\s\S]+-->~U", $citation[$cit_i+1], $comments)) {
        $countComments = count($comments[0]);
        for ($j = 0; $j < $countComments; $j++) {
          $citation[$cit_i+1] = str_replace($comments[0][$j]
                                    , "<!-- Citation bot : comment placeholder b$j -->"
                                    , $citation[$cit_i+1]);
        }
      } else $countComments = null;
   
      $p = parameters_from_citation($citation[$cit_i+1]);

      //Make a note of how things started so we can give an intelligent edit summary
      foreach ($p as $param=>$value) {
        if (is($param)) {
          $pStart[$param] = $value[0];
        }
      }

      //Check for the doi-inline template in the title
      if (preg_match("~\{\{\s*doi-inline\s*\|\s*(10\.\d{4}/[^\|]+)\s*\|\s*([^}]+)}}~",
                      str_replace(pipePlaceholder, "|", $p['title'][0]), $match)) {
        set('title', $match[2]);
        set('doi', $match[1]);
      }

      useUnusedData();

      if (trim(str_replace("|", "", $p["unused_data"][0])) == "") {
        unset($p["unused_data"]);
      } else if (substr(trim($p["unused_data"][0]), 0, 1) == "|") {
        $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
      }
      echo "\n* {$p["title"][0]}";

      // Now, check for typos
      $p = correct_parameter_spelling($p);

      if (google_book_expansion()) {
        echo "\n * Expanded from Google Books API.";
      }

      // Having expanded all that we can expand, tidy things up.

      // edition -- remove 'edition' from parameter value
      if (is("edition"))
      {
        $p["edition"][0] = preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $p["edition"][0]);
      }

      if ($p["doi"][0] == "10.1267/science.040579197") {
        // This is a bogus DOI from the PMID example file
        unset ($p["doi"]);
      }

      //page nos
      preg_match("~(\w?\w?\d+\w?\w?)(\D+(\w?\w?\d+\w?\w?))?~", $p["pages"][0], $pagenos);

      //Authors
      if (isset($p["authors"]) && !isset($p["author"][0])) {
        $p["author"] = $p["authors"];
        unset($p["authors"]);
      }
      preg_match("~[^.,;\s]{2,}~", $p["author"][0], $firstauthor);
      if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last"][0], $firstauthor);
      if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last1"][0], $firstauthor);

      // Is there already a date parameter?
      $dateToStartWith = (isset($p["date"][0]) && !isset($p["year"][0]));

      if (!isset($p["date"][0]) && !isset($p["year"][0]) && is('origyear')) {
        rename_parameter('origyear', 'year');
      }

      $isbnToStartWith = isset($p["isbn"]);
      if (!isset($p["isbn"][0]) && is("title")) set("isbn", findISBN( $p["title"][0], $p["author"][0] . " " . $p["last"][0] . $p["last1"][0]));
      else {
        echo "\n  Already has an ISBN. ";
      }
      if (!$isbnToStartWith && !$p["isbn"][0]) unset($p["isbn"]);

      /*  ISBN lookup disabled -- too buggy.
      if (	(is("pages") || is("page"))
            && is("title")
            && is("publisher")
            && (is("date") || is("year"))
            && (
                is("author") || is("coauthors") || is("others")
                || is("author1")
                || is("author1-last")
                || is("last") || is("last1")
                || is("editor1-first") || is("editor1-last") || is("editor1")
                || is("editor") || is("editors")
              )
          )
       echo "All details present - no need to look up ISBN. ";
      else {
        if (is("isbn")) getInfoFromISBN();
      }
      */

      ##############################
      # Finished with citation and retrieved ISBN data #
      #############################

      // Now wikify some common formatting errors - i.e. tidy up!
      if (isset($p["title"][0]) && !trim($pStart["title"])) $p["title"][0] = niceTitle($p["title"][0]);
      if (isset($p[$journal][0])) $p[$journal][0] = niceTitle($p[$journal][0], false);
      if (isset($p["periodical"][0])) $p["periodical"][0] = niceTitle($p["periodical"][0], false);
      if (isset($p["pages"][0]) && mb_ereg("([0-9A-Z])[\t ]*(-|\&mdash;|\xe2\x80\x94|\?\?\?)[\t ]*([0-9A-Z])", $p["pages"][0])) {
        $p["pages"][0] = mb_ereg_replace("([0-9A-Z])[\t ]*(-|\&mdash;|\xe2\x80\x94|\?\?\?)[\t ]*([0-9A-Z])", "\\1\xe2\x80\x93\\3", $p["pages"][0]);
        $modifications["dashes"] = true;
      }
      #if (isset($p["year"][0]) && trim($p["year"][0]) == trim($p["origyear"][0])) unset($p['origyear']);
      #if (isset($p["publisher"][0])) $p["publisher"][0] = truncatePublisher($p["publisher"][0]);

      if ($dateToStartWith) unset($p["year"]); // If there was a date parameter to start with, don't add a year too!

      // If we have any unused data, check to see if any is redundant!
      if (is("unused_data")) {
        $freeDat = explode("|", trim($p["unused_data"][0]));
        unset($p["unused_data"]);
        foreach ($freeDat as $dat) {
          $eraseThis = false;
          foreach ($p as $oP) {
            similar_text(strtolower($oP[0]), strtolower($dat), $percentSim);
            if ($percentSim >= 85)
              $eraseThis = true;
          }
          if (!$eraseThis) $p["unused_data"][0] .= "|" . $dat;
        }
        if (trim(str_replace("|", "", $p["unused_data"][0])) == "") unset($p["unused_data"]);
        else {
          if (substr(trim($p["unused_data"][0]), 0, 1) == "|") {
            $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
          }
          echo "\n* Unused data in following book citation: {$p["unused_data"][0]}";
        }
      }

      //And we're done!
      $endtime = time();
      $timetaken = $endtime - $started_citation_at;
      echo "\n  Book reference assessed in $timetaken secs.";

      $cText .= reassemble_citation($p); // This also populates $modifications["additions"] and $modifications["additions"]
      $last_p = $p;
      $p = null;
      
      // Convert into citation or cite journal, as appropriate
      if ($citation_template_dominant) {
        $citation[$cit_i+2] = preg_replace("~[cC]ite[ _]\w+~", "Citation", $citation[$cit_i+2]);
      }
      // Restore comments we hid earlier
      for ($j = 0; $j < $countComments; $j++) {
        $cText = str_replace("<!-- Citation bot : comment placeholder b$j -->"
                                      , $comments[0][$j]
                                      , $cText);
      }
      $new_code .=  $citation[$cit_i] . ($cText?"{{{$citation[$cit_i+2]}$cText{$citation[$cit_i+4]}}}":"");
      $cText = null;
      $crossRef = null;
    }
    $new_code .= $citation[$cit_i]; // Adds any text that comes after the last citation
  }
###################################  START ASSESSING JOURNAL/OTHER CITATIONS ######################################

    if (false !== ($citation = preg_split("~\{\{((\s*[Cc]ite[_ ]?[jJ]ournal(?=\s*\|)|\s*[Cc]ite[_ ]?[dD]ocument(?=\s*\|)|\s*[Cc]ite[_ ]?[Ee]ncyclopa?edia(?=\s*\|)|[cCite[ _]web(?=\s*\|)|\s*[cC]itation(?=\s*\|))([^\{\}]|\{\{.*\}\})*)([\n\s]*)\}\}~U", $new_code, -1, PREG_SPLIT_DELIM_CAPTURE))) {
    $new_code = null;
    $iLimit = (count($citation) - 1);
    for ($cit_i = 0; $cit_i < $iLimit; $cit_i += 5) { //Number of brackets in cite journal regexp + 1
      $started_citation_at = time();

      // Strip comments, which may contain misleading pipes etc
      if (preg_match_all("~<!--[\s\S]+-->~U", $citation[$cit_i+1], $comments)) {
        $countComments = count($comments[0]);
        for ($j = 0; $j < $countComments; $j++) {
          $citation[$cit_i+1] = str_replace($comments[0][$j]
                                    , "<!-- Citation bot : comment placeholder c$j -->"
                                    , $citation[$cit_i+1]);
        }
      } else {
        // Comments will be replaced in the cText variable later
        $countComments = null;
      }
      $p = parameters_from_citation($citation[$cit_i+1]); 
      
      if ($p["doix"]) {
        $p["doi"][0] = str_replace($dotEncode, $dotDecode, $p["doix"][0]);
        unset($p["doix"]);
      }
      //Make a note of how things started so we can give an intelligent edit summary
      foreach ($p as $param=>$value)	if (is($param)) {
        $pStart[$param] = $value[0];
      }

      if (is("inventor") ||
          is("inventor-last") ||
          is("patent-number")) {
        echo "<p>Citation bot does not handle patent citations.</p>";
      } else {
      //Check for the doi-inline template in the title
      if (preg_match("~\{\{\s*doi-inline\s*\|\s*(10\.\d{4}/[^\|]+)\s*\|\s*([^}]+)}}~"
          , str_replace(pipePlaceholder, "|", $p['title'][0])
          , $match
          )
      ) {
        set('title', $match[2]);
        set('doi', $match[1]);
      }

      id_to_parameters();


###########################
//  JOURNALS
//
echo "
*-> {$p["title"][0]}
1: Tidy citation and try to expand
";
//  See if we can get any 'free' metadata from:
//  * mis-labelled parameters
//  * ISBN
// * SICI
//  * Tidying up existing parameters (and we'll do more tidying here too)
//
###########################

        $journal = is("periodical") ? "periodical" : "journal";
        // See if we can use any of the parameters lacking equals signs:
        useUnusedData();

        if (google_book_expansion()) {
          echo "\n * Expanded from Google Books API.";
        }

        /*if (is("url") && !is("journal") && !is("periodical") && !is("magazine") && !is("newspaper")) {
    SpencerK's API; disabled until I check whether it is ever a source of errors
          if_null_set("publisher", trim(file_get_contents("http://referee.freebaseapps.com/?url=" . $p["url"][0])));
        }*/

        /*  ISBN lookup removed - too buggy.  TODO (also commented out above)
        if (is("isbn")) getInfoFromISBN();
*/

        // If the page has been created manually from a cite doi link, it will have an encoded 'doix' parameter - decode this.
        if (preg_match("~^10.\d~", $p['doix'][0])) {
          $p['doi'][0] = str_replace($dotEncode, $dotDecode, $p['doix'][0]);
          unset($p['doix']);
        }
        get_identifiers_from_url();
        id_to_parameters();


        if (trim(str_replace("|", "", $p["unused_data"][0])) == "") {
          unset($p["unused_data"]);
        } else {
          if (substr(trim($p["unused_data"][0]), 0, 1) == "|") {
            $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
          }
        }


        if (trim ($p["quotes"][0]) == "yes" || trim ($p["quotes"][0]) == "no") {
          unset ($p["quotes"]);
        }

        // Load missing parameters from SICI, if we found one...
        get_data_from_sici($citation[$cit_i+1]);
        
        // Fix typos in parameter names
        $p = correct_parameter_spelling($p);
        // DOI - urldecode
        if (isset($p["doi"][0])) {
          $p["doi"][0] = str_replace($pcEncode, $pcDecode,
                           str_replace(' ', '+', trim(urldecode($p["doi"][0]))));
          $doi_with_comments_removed = preg_replace("~<!--[\s\S]*-->~U", "", $p["doi"][0]);
          if (preg_match("~10\.\d{4}/\S+~", $doi_with_comments_removed, $match) && $p["doi"][0] != $match[0]) {
            set("doi", $match[0]);
          }
        } elseif (preg_match("~10\.\d{4}/[^&\s\|]*~", $p["url"][0], $match)) {
          // Search the URL for anything in a DOI format.
          $p["doi"]= $p["url"];
          $p["doi"][0] = preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]);
          unset ($p["url"]);
        } elseif (preg_match("~10\.\d{4}/[^&\s\|]*~", urldecode($c), $match)) {
            // Search the entire citation text for anything in a DOI format.
            // This is quite a broad match, so we need to ensure that no baggage has been tagged on to the end of the URL.
            // Wiley have a habit of using the DOI as part of the URL, so we ought to trim any /abstract or /pdf that's following it.
            $p["doi"][0] = preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]);
        }
        $doiToStartWith = isset($p["doi"]);
        // Check that the DOI works; if not, fix it.
        verify_doi($p["doi"][0]);
        // co-authors
        if (is('co-author') && !is('coauthors') && !is('coauthor')) {
          $p['coauthor'] = $p['co-author'];
          unset($p['co-author']);
        }
        if (is('co-authors') && !is('coauthors') && !is('coauthor')) {
          $p['coauthors'] = $p['co-authors'];
          unset($p['co-authors']);
        }

        // pmid = PMID 1234 can produce pmpmid = 1234
        if (isset($p["pmpmid"])) {
          $p["pmid"] = $p["pmpmid"];
          unset($p["pmpmid"]);
        }

        //pages
        preg_match("~(\w?\w?\d+\w?\w?)(\D+(\w?\w?\d+\w?\w?))?~", $p["pages"][0], $pagenos);

        //Authors
        // Move authors -> author
        if (isset($p["authors"]) && !isset($p["author"][0])) {
          $p["author"] = $p["authors"];
          unset($p["authors"]);
        }

        // Replace "volume = B 120" with "series=VB, volume = 120
        if (preg_match("~^([A-J])(?!\w)\d*\d+~u", $p["volume"][0], $match)) {
          if (trim($p["journal"][0]) && mb_substr(trim($p["journal"][0]), -2) != " $match[1]") {
            $p["journal"][0] .= " $match[1]";
            $p["volume"][0] = trim(mb_substr($p["volume"][0], mb_strlen($match[1])));
          }
        }

        $authors_missing = false; // reset
        // The phrase 'et al' should not be included in the authors parameter.
        // It is discouraged and may be mistaken for an author by the bot.
        // If it is present, we will search for more authors when we get the chance - set $authors_missing = true
/*
        if (is('author')) {
          // Analyse the author parameter.  If there's an 'et al', can we remove it?
          if (preg_match("~([,.; ]+)'*et al['.]*(?!\w)$~", $p['author'][0], $match)) {
            $chars = count_chars($p['author'][0]);
            // Try splitting at semi-colons
            if ($chars[ord(";")] > 0) {
              $truncate_after = $chars[ord(";")];
              if (strpos($match[0], ';') === false) {
                $truncate_after++;
              }
              // No luck? Try splitting on commas?
            } elseif ($chars[ord(",")] > 0) {
              $truncate_after = $chars[ord(",")];
              if (strpos($match[0], ',') === false) {
                $truncate_after++;
              }
            }
            // Observe an 'et al', and remove it.
            $p['author'][0] = preg_replace("~[,.; ]+'*et al['.]*(?!\w)$~", "", $p['author'][0]);
            echo " - $truncate_after authors then <i>et al</i>. Will grow list later.";
            $authors_missing = true;
            //if_null_set('display-authors', $truncate_after);
          }
        }
*/

        $author_param = trim($p['author'][0]);
        //print "\n" . $author_param;
        /*  REMOVED THIS SECTION IN R61
        // Replace 'and' with an appropriate punctuation 'signpost'
        if (preg_match("~ ([Aa]nd|\&) ([\w\W]+)$~U", $author_param, $match)){
          if (strpos($author_param, ';')  // Already includes a ;
            || !strpos($author_param, ',') // No commas - can't hurt to divide with ;
            || strpos($match[2], ',') // Commas after the and - commas can't be used to divide authors
          ) {
            $author_param = str_replace(" " . $match[1], ";", $author_param);
          } else {
            $author_param = str_replace(" " . $match[1], ",", $author_param);
          }
        }
        */
/* REMOVED THIS IN R70 - Coauthors not restored because author_param is not used.
        // Check to see if there is a translator in the authors list
        if (is('coauthors') || is('coauthor')) {
          $coauthor_param = $p['coauthors'][0]?'coauthors':'coauthor';
          $coauthor_value = $p[$coauthor_param];
          $coauth = $coauthor_value[0];
          if (strpos($coauth, ';') || strpos($author_param, ',')) {
            $author_param .= "; " . $coauth;
          } else {
            $author_param .= ", " . $coauth;
          }
          unset($p['coauthors']);
          unset($p['coauthor']);
        } else {
          $coauth = null;
        }
*/
        // Check for translator in author_param and remove if necessary.
        $translator_regexp = "~\b([Tt]r(ans(lat...?(by)?)?)?\.)\s([\w\p{L}\p{M}\s]+)$~u";
        if (preg_match($translator_regexp, $author_param, $match)) {
          if (is('others')) {
            $p['others'][0] .= "; {$match[1]} {$match[5]}";
          } else {
            set ("others", "{$match[1]} {$match[5]}");
          }
          // Remove translator from both author_parm and $p
          $author_param = preg_replace($translator_regexp, "", $author_param);
          $p['author'][0] = $author_param;
        }

        /* REMOVED IN REVISION 61
        // Split author list into individual authors using semi-colon.
        if (strpos($author_param, ';') && !is('author2') && !is('last2')) {
          $auths = explode(';', $author_param);
          unset($p['author']);
          foreach ($auths as $au_i => $auth) {
            if (preg_match("~\[\[(([^\|]+)\|)?([^\]]+)\]?\]?~", $auth, $match)) {
              if_null_set("authorlink$au_i", ucfirst($match[2]?$match[2]:$match[3]));
              $auth = $match[3];
            }
            $jr_test = jrTest($auth);
            $auth = $jr_test[0];
            if (strpos($auth, ',')) {
              $au_bits = explode(',', $auth);
              set('last' . ($au_i+1), $au_bits[0] . $jr_test[1]);
              set('first' . ($au_i+1), $au_bits[1]);
            } else {
              set('author' . ($au_i+1), $auth . $jr_test[1]);
            }
          }
        }
        // Try using commas to split authors
        elseif (preg_match_all("~([\w\p{L}\p{M}\-. ]+\s+[\w\p{L}\p{M}. ]+),~u", $author_param, $matches)) {
          // \p{L} matches any letter, including special characters.  \p{M} matches diacritical marks, etc.  Remember the u flag at the end of the expression!
          $last_author = preg_replace("~[\w\p{L}\p{M}\-. ]+\s+[\w\p{L}\p{M}. ]+,~u", "", $author_param);
          $matches[1][] = $last_author;
          unset($p['author']);
          $au_i = 0;
          foreach ($matches[1] as $author) {
            $au_i++;
            set ("author" . $au_i, $author);
          }
          set('author-separator', ',');
          if (is('last2')) {
            $p['author-name-separator'][0] = "";
          }
        }
        // Detect first author.
        preg_match("~[^.,;\s]{2,}~", $author_param, $firstauthor);
        if (!$firstauthor[0]) {
          preg_match("~[^.,;\s]{2,}~", $p["author1"][0], $firstauthor);
        }
        if (!$firstauthor[0]) {
          preg_match("~[^.,;\s]{2,}~", $p["last"][0], $firstauthor);
        }
        if (!$firstauthor[0]) {
          preg_match("~[^.,;\s]{2,}~", $p["last1"][0], $firstauthor);
        }

        // If we had no luck extracting authors from the coauthors parameter, we'd better restore it.
        if ($coauth && !is('author2') && !is('last2')) {
          $p[$coauthor_param] = $coauthor_value;
        }

/* END OF AUTHOR SEPARATION
*/
        // Is there already a date parameter?
        $dateToStartWith = (isset($p["date"][0]) && !isset($p["year"][0])) ;

        // By this point we'll have recovered any DOI or PMID that is hidden in the citation data itself.


#####################################
//
        
if (is('doi')) {
if (!nothingMissing($journal)) {
  expand_from_crossref($crossRef, $editing_cite_doi_template);
}
echo "
2: DOI already present";
// TODO: Use DOI to expand citation
} else {
echo "
2: Find DOI";
//  Now we have got the citation ship-shape, let's try to find a DOI.
//
#####################################

          
          // Try AdsAbs
          if ($slow_mode || is('bibcode')) {
            echo "\n - Checking AdsAbs database";
            get_data_from_adsabs();
          } else {
             echo "\n - Skipping AdsAbs database: not in slow mode.";
          }
          
          if (!isset($p["doi"][0])) {
            //Try CrossRef
            echo "\n - Checking CrossRef database... ";
            $crossRef = crossRefDoi(trim($p["title"][0]), trim($p[$journal][0]),
                                    trim($firstauthor[0]), trim($p["year"][0]), trim($p["volume"][0]),
                                    $pagenos[1], $pagenos[3], trim($p["issn"][0]), trim($p["url"][0]));
            if ($crossRef) {
              $p["doi"][0] = $crossRef->doi;
              echo "Match found: " . $p["doi"][0];
            } else {
              echo "no match.";
            }
          }


          //Try URL param
          if (!isset($p["doi"][0])) {
            if (is("jstor")) {
              echo "\n - Checking JSTOR database";
              if (get_data_from_jstor("10.2307/" . $p["jstor"][0])) {
                echo $html_output
                      ? "\n - Got data from JSTOR.<br />"
                      : "\n - Got data from JSTOR.";
              }
            } else {
              if (strpos($p["url"][0], "http://") !== false) {
                if (preg_match("~jstor\D+(\d+)\D*$~i", $p['url'][0], $jid)) {
                  echo $html_output
                        ? ("\n - Getting data from <a href=\"" . $p["url"][0] . "\">JSTOR record</a>.")
                        : "\n - Querying JSTOR record from URL " . $jid[0];
                  get_data_from_jstor("10.2307/$jid[1]");
                } elseif (substr(preg_replace("~<!--.*-->~", "", $p["url"][0]), -4) == ".pdf") {
                  echo $html_output
                        ? ("\n - Avoiding <a href=\"" . $p["url"][0] . "\">PDF URL</a>. <br>")
                        : "\n - Avoiding PDF URL {$p["url"][0]}";
                } else {
                  //Try using URL parameter
                  echo $html_output
                        ? ("\n - Trying <a href=\"" . $p["url"][0] . "\">URL</a>. <br>")
                        : "\n - Trying URL {$p["url"][0]}";
                  $doi = findDoi(preg_replace("~<!--.*-->~", "", $p["url"][0]));
                  if ($doi) {
                    echo " found doi $doi";
                    $p["doi"][0] = $doi;
                  } else {
                    echo " no doi found.";
                  }
                }
              } else {
                echo "No valid URL specified.  ";
              }
            }
          }
        }

        if (!$doiToStartWith && !is("doi")) unset($p["doi"]);

#####################################
//
        if (is ('pmid')) {
          echo "\n3: PMID already present";
        } else {
          echo "\n3: Find PMID & expand";
          searchForPmid();
        }
        if (!nothingMissing($journal) && is('pmid')) {
          get_data_from_pubmed();
        }

#####################################
        if (nothingMissing($journal)) {
          echo "\n4: Citation complete :-)";
        } else {
          echo "\n4: Expand citation";
          if (is("doi")) {
            $crossRef = expand_from_crossref($crossRef, $editing_cite_doi_template);
          } else {
            echo "\n - No DOI; can't check CrossRef";
            $crossRef = null;
          }
        }
#####################################
// We have now recovered all possible information from CrossRef.
//If we're using a Cite Doi subpage and there's a doi present, check for a second author. Only do this on first visit (i.e. when citedoi = true)
        echo "\n5: Formatting and other tweaks";
        if ($editing_cite_doi_template || preg_match("~[cC]ite[ _](?:doi|pmid|jstor|pmc)~", $page)) {
          echo "\n   First: Cite Doi formatting";
          // Get the surname of the first author. (We [apparently] found this earlier, but it might have changed since then)
          preg_match("~[^.,;\s]{2,}~u", $p["author"][0], $firstauthor);
          if (!$firstauthor[0]) {
            preg_match("~[^.,;\s]{2,}~u", $p["author1"][0], $firstauthor);
          }
          if (!$firstauthor[0]) {
            preg_match("~[^.,;\s]{2,}~u", $p["last"][0], $firstauthor);
          }
          if (!$firstauthor[0]) {
            preg_match("~[^.,;\s]{2,}~u", $p["last1"][0], $firstauthor);
          }

          // If we only have the first author, look for more!
          if (!is('coauthors')
             && !is('author2')
             && !is('last2')
               && is('doi')
            ) {
            echo "\n - Looking for co-authors & page numbers...";
            $moreAuthors = findMoreAuthors($p['doi'][0], $firstauthor[0], $p['pages'][0]);
            $count_new_authors = count($moreAuthors['authors']);
            if ($count_new_authors) {
              echo " Found more authors! ";
              for ($j = 0; $j < $count_new_authors; $j++) {
                $au = explode(', ', $moreAuthors['authors'][$j]);
                if ($au[1]) {
                  set ('last' . ($j+1), $au[0]);
                  set ('first' . ($j+1), preg_replace("~(\p{L})\p{L}*\.? ?~", "$1.", $au[1]));
                  unset($p['author' . ($j+1)]);
                } else {
                  if ($au[0]) {
                    set ('author' . ($j+1), $au[0]);
                  }
                }
              }
              unset($p['author']);
            }
            if ($moreAuthors['pages']) {
              set('pages', $moreAuthors['pages']);
              echo " Completed page range! (" . $p['pages'][0]  . ')';
            }
          }
          for ($i = 1; $i < 9; $i ++) {
            foreach (array("author", "last", "first") as $param) {
              if (trim($p[$param . $i][0]) == "") {
                unset ($p[$param . $i]);
              }
            }
          }
          citeDoiOutputFormat();
        }

print $p["title"][0];
        // Check that the URL functions, and mark as dead if not.
        /*  Disable; to re-enable, we should log possible 404s and check back later.
         * Also, dead-link notifications should be placed ''after'', not within, the template.

         if (!is("format") && is("url") && !is("accessdate") && !is("archivedate") && !is("archiveurl"))
        {
          echo "\n - Checking that URL is live...";
          $formatSet = isset($p["format"]);
          $p["format"][0] = assessUrl($p["url"][0]);
          if (!$formatSet && trim($p["format"][0]) == "") {
            unset($p["format"]);
          }
          echo "Done" , is("format")?" ({$p["format"][0]})":"" , ".</p>";
        }*/
      }

      // Neaten capitalisation for journal
      if (isset($p[$journal][0])) {
        $p[$journal][0] = niceTitle($p[$journal][0], false);
      }
      // If there was a date parameter to start with, don't add a year too.  This will be created by the template.
      if ($dateToStartWith) {
        unset($p["year"]);
      }

      // Check each author for embedded author links
      for ($au_i = 1; $au_i < 10; $au_i++) {
        if (preg_match("~\[\[(([^\|]+)\|)?([^\]]+)\]?\]?~", $p["author$au_i"][0], $match)) {
          if_null_set("authorlink$au_i", ucfirst($match[2]?$match[2]:$match[3]));
          set("author$au_i", $match[3]); // Replace author with unlinked version
          echo "Dissecting authorlink";
        }
      }

      // Unset authors above 'author9' - the template won't render them.
      for ($au_i = 10; is("author$au_i") || is("last$au_i"); $au_i++){
        unset($p["author$au_i"]);
        unset($p["first$au_i"]);
        unset($p["last$au_i"]);
      }

      // Check that the DOI functions.
      if (trim($p["doi"][0]) != "" && trim($p["doi"][0]) != "|" && $slowMode) {
        echo "\nChecking that DOI {$p["doi"][0]} is operational...";
        $brokenDoi = isDoiBroken($p["doi"][0], $p, $slowMode);
        if ($brokenDoi && !is("doi_brokendate") && !is("doi_inactivedate")) {
          set("doi_inactivedate", date("Y-m-d"));
          echo "\n\n $doi \n\n";
        }
        ELSE if (!$brokenDoi) unset($p["doi_brokendate"]); unset($p["doi_inactivedate"]);
        echo $brokenDoi?" It isn't.":" It is.", "</p>";
      }
print $p["title"][0];

      // Clean up after errors in publishers' databases
      if (0 === strpos(trim($p["journal"][0]), "BMC ") && $p["pages"][0]) {
        unset ($p["issue"]);
        echo "\n - dropping issue number (BMC journals only have page numbers)";
      }

      if ($p["doi"][0] == "10.1267/science.040579197") {
        // This is a bogus DOI from the PMID example file
        unset ($p["doi"]);
      }

      tidy_citation();

      if ($unify_citation_templates) {
        if ($citation_template_dominant) {
          if (preg_match("~[cC]ite[ _]\w+~", $citation[$cit_i+2])) {
            // Switching FROM cite xx TO citation; cite xx has a trailing period by default
            if_null_set("postscript", ".");
            $citation[$cit_i+2] = preg_replace("~[cC]ite[ _]\w+~", "Citation", $citation[$cit_i+2]);
          }
        } else {
          if ($harv_template_present) {
            if_null_set("ref", "harv");
          }
          if (preg_match("~[cC]itation~", $citation[$cit_i+2])) {
            // Switching FROM cite xx TO citation; citation has no trailing period by default
            if_null_set("postscript", "<!-- Bot inserted parameter. Either remove it; or change its value to \".\" for the cite to end in a \".\", as necessary. -->{{inconsistent citations}}");
          }
          if (is('inventor-last') || is('inventor-surname') || is('inventor1-surname')
                  || is('inventor1-last') || is ('inventor')) {
            $citeTemplate = "Cite patent";
          }
          elseif (is('journal')) {$citeTemplate = "Cite journal";}
          elseif (is('agency') || is('newspaper') || is('magazine') || is('periodical')) {
            $citeTemplate = "Cite news";
          }
          elseif (is('encyclopedia')) {
            $citeTemplate = "Cite encyclopedia";
          }
          elseif (is('conference') || is('conferenceurl')) {$citeTemplate = "Cite conference";}

          // Straightforward cases now out of the way... now for the trickier ones
          elseif (is('chapter') || is('editor') || is('editor-last') || is('editor1') || is('editor1-last')) {
            $citeTemplate = "Cite book";
          }
          elseif (!is('date') && !is('month') && (is('isbn') || is("oclc" || is("series")))) {
           // Books usually catalogued by year; no month expected
            $citeTemplate = "Cite book";
          }
          elseif (is('publisher')) {
            // This should be after we've checked for a journal parameter
            if (preg_match("~\w\.\w\w~", $p['publisher'][0])) {
             // it's a fair bet the publisher is a web address
              $citeTemplate = "Cite web";
            } else {
              $citeTemplate = "Cite document";
            }
          }
          elseif (is('url')) {$citeTemplate = "Cite web";} // fall back to this if URL
          else {$citeTemplate = "Cite document";} // If no URL, cite journal ought to handle it okay
          $citation[$cit_i+2] = preg_replace("~[cC]itation~", $citeTemplate, $citation[$cit_i+2]);
        }
      }


      $cText .= reassemble_citation($p, $editing_cite_doi_template); // This also populates $modifications["additions"] and $modifications["additions"]

      //And we're done!
      $endtime = time();
      $timetaken = $endtime - $started_citation_at;
      echo "\n*** Complete. Citation assessed in $timetaken secs.\n\n\n";

      // Restore comments we hid earlier
      for ($j = 0; $j < $countComments; $j++) {
        $cText = str_replace(array("<!-- Citation bot : comment placeholder c$j -->",
                                   str_replace($dotEncode, $dotDecode, "<!-- Citation bot : comment placeholder c$j -->"),
                                   str_replace($pcEncode, $pcDecode,
                                               str_replace(' ', '+',
                                                 trim(urldecode("<!-- Citation bot : comment placeholder c$j -->")))),

                                  )
                                      , $comments[0][$j]
                                      , $cText);
      }
      $new_code .=  $citation[$cit_i] . ($cText?"{{{$citation[$cit_i+2]}$cText{$citation[$cit_i+4]}}}":"");
      $cText = null;
      $crossRef = null;
      $last_p = $p;
      $p = null;
    }

    $new_code .= $citation[$cit_i]; // Adds any text that comes after the last citation
  }

  /*
    if ($unify_citation_templates && $citation_template_dominant) {
    $new_code = preg_replace("~\b[cC]ite[ _](web|conference|encyclopedia|news)~", "Citation", $new_code);
  }  */

  ############################### Finished with all citations ##############################
  #########################  code is now complete and ready for prcessing.   #######################

  if (trim($new_code)) {
      $edit_summary = $edit_summaryStart . $editInitiator . $auto_summary . $initiatedBy . $edit_summary_end;
      $outputText = "\n\n\n<h5>Output</h5>\n\n\n<!--New code:--><textarea rows=50>" 
      . ($html_output ? htmlentities(mb_convert_encoding($new_code, "UTF-8")) : $new_code)
      . "</textarea><!--DONE!-->\n\n\n<p><b>Bot switched off</b> &rArr; no edit made to"
              . " $page.<br><b>Changes:</b> <i>$auto_summary</i></p>";
      if ($editing_cite_doi_template && strtolower(substr(trim($new_code), 0, 5)) != "{{cit") {
        if (substr($new_code, 0, 15) == "HTTP/1.0 200 OK") {
          echo "Headers included in pagecode; removing...\n";
          $new_code = preg_replace("~$[\s\S]+\{\{~", "{{", $new_code);
        } else {
          mail ("MartinS+citewatch@gmail.com"
                , "Citewatch ERROR"
                , "Output does not begin with {{Cit, but [" . strtolower(substr(trim($new_code), 0, 5)) . "]
                . \n\n[Page = $page]\n[SmartSum = $auto_summary ]\n[\$citation = ". print_r($citation, 1)
                . "]\n[Request variables = ".print_r($_REQUEST, 1) . "]\n [p = "
                . print_r($p,1)
                . "] \n[pagecode =$new_code]\n\n[freshcode =$cite_doi_start_code]\n\n> Error message generated by expand.php.");
          exit ("$new_code"); // make the next if an elseif if you remove this, and think of some way of the above line not skipping the elseif
        }
      }
      if ($commit_edits) {
        if ($jstor_redirect && $jstor_redirect_target) {
          $page = "Template:Cite doi/" . wikititle_encode($jstor_redirect_target);
          write ("Template:Cite doi/" . wikititle_encode($jstor_redirect), "#REDIRECT [[$page]]"
            , $editInitiator . "Redirecting from JSTOR UID to official unique DOI, to avoid duplication");
          echo "\n * Redirected " . wikititle_encode($jstor_redirect) . " to $page. ";
        }
        if ($editing_cite_doi_template) {
          $p = $last_p; // temporary
          // Create any necessary redirects
          $pmid_page = "Template:Cite pmc/" . $p["pmc"][0];
          $pmid_page = "Template:Cite pmid/" . $p["pmid"][0];
          $doi_page = "Template:Cite doi/" . wikititle_encode($p["doi"][0]);
          $jstor_page = "Template:Cite doi/10.2307" . wikititle_encode("/" . $p["jstor"][0]);
          if (is("doi")) {
            $redirect_target = $doi_page;
          } else if (is("pmid")) {
            $redirect_target = $pmid_page;
          } else if (is("pmc")) {
            $redirect_target = $pmc_page;
          } else if (is("jstor")) {
            $redirect_target = $jstor_page;
          }
          // Check for re-directs and create if necessary
          if (is("pmc") && $redirect_target != $pmc_page && !getArticleId($pmc_page)) {
            write ($pmc_page, "#REDIRECT [[$redirect_target]]"
              , $editInitiator . "Redirecting to avoid duplication");
            $page = $redirect_target;
          }
          if (is("pmid") && $redirect_target != $pmid_page && !getArticleId($pmid_page)) {
            write ($pmid_page, "#REDIRECT [[$redirect_target]]"
              , $editInitiator . "Redirecting to avoid duplication");
            $page = $redirect_target;
          }
          if (is("jstor") && $redirect_target != $jstor_page && !getArticleId($jstor_page)) {
            write ($jstor_page, "#REDIRECT [[$redirect_target]]"
              , $editInitiator . "Redirecting from JSTOR UID to official unique DOI, to avoid duplication");
            $page = $redirect_target;
          }
          if (stripos($page, "plate:Cite jstor") && $p["jstor"][0]) {
            $page = $jstor_page;
          }
          $p = null; // re-reset
        }
      } else {
        echo $outputText;

        if ($html_output === -1) {
          ob_end_clean();
        }
        return $new_code;
      }

      //Unset smart edit summary parameters.  Some of these are globals modified by other functions.
      $pStart = null;
      $pEnd = null;
  } else {
    if (trim($original_code)=='') {
      echo "<b>Blank page.</b> Perhaps it's been deleted?";
      if (!$editing_cite_doi_template) {
        updateBacklog($page);
      }
      if ($html_output === -1) {
        ob_end_clean();
      }
      return false;
    } else {
      echo "<b>Error:</b> Blank page produced. This bug has been reported. Page content: $original_code";
      mail ("MartinS+doibot@gmail.com", "DOI BOT ERROR", "Blank page produced.\n[Page = $page]\n[SmartSum = $auto_summary ]\n[\$citation = ". print_r($citation, 1) . "]\n[Request variables = ".print_r($_REQUEST, 1) . "]\n\nError message generated by expand.php.");
      exit;
    }
  }

  if ($html_output == -1) {
    ob_end_clean();
  }
  return $new_code;
}

function leave_broken_doi_message($id, $page, $doi) {
  echo "\n\n* Non-functional identifier $id found in article [[$page]]";
  if (getNamespace($page) == 0) {
    $talkPage = "Talk:$page";
    $talkMessage = "== Reference to broken DOI ==\n"
                 . "A reference was recently added to this article using the [[Template:Cite doi|Cite DOI template]]. "
                 . "The [[User:Citation bot|citation bot]] tried to expand the citation, but could not access the specified DOI. "
                 . "Please check that the [[Digital object identifier|DOI]] [[doi:$doi]] has been correctly entered.  If the DOI is correct, it is possible that it "
                 . "has not yet been entered into the [[CrossRef]] database.  Please  "
                 . "[http://en.wikipedia.org/w/index.php?title=" . urlencode($id)
                 . "&preload=Template:Cite_doi/preload/nodoi&action=edit complete the reference by hand here]. "
                 . "\nThe script that left this message was unable to track down the user who added the citation; "
                 . "it may be prudent to alert them to this message.  Thanks, ";
    $talkId = articleId($page, 1);

    if ($talkId) {
      $text = getRawWikiText($talkPage);
      echo "\nTALK PAGE EXISTS " . strlen($text) . "\n\n";
    } else {
      $text = '';
      echo "\nTALK PAGE DOES NOT EXIST\n\n";
    }
    if (strpos($text, "|DOI]] [[doi:" . $doi) || strpos($text, "d/nodoi&a")) {
      echo "\n - Message already on talk page.  Zzz.\n";
    } else if ($text && $talkId || !$text && !$talkId) {
      echo "\n * Writing message on talk page..." . $talkPage . "\n\n";
      echo "\n\n Talk page $talkPage has ID $talkId; text was: [$text].  Our page was $id and " .
              "the article in progress was $page.\n";
      write($talkPage,
              ($text . "\n" . $talkMessage . "~~~~"),
              "Reference to broken [[doi:$doi]] using [[Template:Cite doi]]: please fix!");
      echo " Message left.\n";
    } else {
      echo "\n *  Talk page exists, but no text could be attributed to it. \n ?????????????????????????";
    }
    mark_broken_doi_template($page, $doi);
  } else {
    echo "\n * Article in question is not in article space.  Switched to use 'Template:Broken DOI'." ;
    mark_broken_doi_template($page, $doi);
  }
}