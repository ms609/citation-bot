<?php
/*$Id$*/
/* Treats comments, templates and references as objects */

/* 
# TODO # 
 - Associate initials with surnames: don't put them on a new line
*/

// Include classes
require_once("Template.php");
require_once("Parameter.php");

#define ('ref_regexp', '~<ref.*</ref>~u'); // #TODO DELETE
#define ('refref_regexp', '~<ref.*/>~u'); // #TODO DELETE

// The following section is about SVN revision IDs: FIXME, not using SVN any more.
$file_revision_id = str_replace(array("Revision: ", "$", " "), "", '$Revision$');
$doitools_revision_id = revisionID();
global $last_revision_id, $edit_initiator;
$edit_initiator = "[$doitools_revision_id]";
if ($file_revision_id < $doitools_revision_id) {
  $last_revision_id = $doitools_revision_id;
} else {
  $edit_initiator = str_replace($doitools_revision_id, $file_revision_id, $edit_initiator);
  $last_revision_id = $file_revision_id;
}

quiet_echo ("\nRevision #$last_revision_id");
// end SVN revision ID section

global $author_parameters;
$author_parameters = array(
    1  => array('surname'  , 'forename'  , 'initials'  , 'first'  , 'last'  , 'author',
                'surname1' , 'forename1' , 'initials1' , 'first1' , 'last1' , 'author1', 'authors', 'vauthors'),
    2  => array('surname2' , 'forename2' , 'initials2' , 'first2' , 'last2' , 'author2' , 'coauthors', 'coauthor'),
    3  => array('surname3' , 'forename3' , 'initials3' , 'first3' , 'last3' , 'author3' ),
    4  => array('surname4' , 'forename4' , 'initials4' , 'first4' , 'last4' , 'author4' ),
    5  => array('surname5' , 'forename5' , 'initials5' , 'first5' , 'last5' , 'author5' ),
    6  => array('surname6' , 'forename6' , 'initials6' , 'first6' , 'last6' , 'author6' ),
    7  => array('surname7' , 'forename7' , 'initials7' , 'first7' , 'last7' , 'author7' ),
    8  => array('surname8' , 'forename8' , 'initials8' , 'first8' , 'last8' , 'author8' ),
    9  => array('surname9' , 'forename9' , 'initials9' , 'first9' , 'last9' , 'author9' ),
    10 => array('surname10', 'forename10', 'initials10', 'first10', 'last10', 'author10'),
    11 => array('surname11', 'forename11', 'initials11', 'first11', 'last11', 'author11'),
    12 => array('surname12', 'forename12', 'initials12', 'first12', 'last12', 'author12'),
    13 => array('surname13', 'forename13', 'initials13', 'first13', 'last13', 'author13'),
    14 => array('surname14', 'forename14', 'initials14', 'first14', 'last14', 'author14'),
    15 => array('surname15', 'forename15', 'initials15', 'first15', 'last15', 'author15'),
    16 => array('surname16', 'forename16', 'initials16', 'first16', 'last16', 'author16'),
    17 => array('surname17', 'forename17', 'initials17', 'first17', 'last17', 'author17'),
    18 => array('surname18', 'forename18', 'initials18', 'first18', 'last18', 'author18'),
    19 => array('surname19', 'forename19', 'initials19', 'first19', 'last19', 'author19'),
    20 => array('surname20', 'forename20', 'initials20', 'first20', 'last20', 'author20'),
    21 => array('surname21', 'forename21', 'initials21', 'first21', 'last21', 'author21'),
    22 => array('surname22', 'forename22', 'initials22', 'first22', 'last22', 'author22'),
    23 => array('surname23', 'forename23', 'initials23', 'first23', 'last23', 'author23'),
    24 => array('surname24', 'forename24', 'initials24', 'first24', 'last24', 'author24'),
    25 => array('surname25', 'forename25', 'initials25', 'first25', 'last25', 'author25'),
    26 => array('surname26', 'forename26', 'initials26', 'first26', 'last26', 'author26'),
    27 => array('surname27', 'forename27', 'initials27', 'first27', 'last27', 'author27'),
    28 => array('surname28', 'forename28', 'initials28', 'first28', 'last28', 'author28'),
    29 => array('surname29', 'forename29', 'initials29', 'first29', 'last29', 'author29'),
    30 => array('surname30', 'forename30', 'initials30', 'first30', 'last30', 'author30'),
    31 => array('surname31', 'forename31', 'initials31', 'first31', 'last31', 'author31'),
    32 => array('surname32', 'forename32', 'initials32', 'first32', 'last32', 'author32'),
    33 => array('surname33', 'forename33', 'initials33', 'first33', 'last33', 'author33'),
    34 => array('surname34', 'forename34', 'initials34', 'first34', 'last34', 'author34'),
    35 => array('surname35', 'forename35', 'initials35', 'first35', 'last35', 'author35'),
    36 => array('surname36', 'forename36', 'initials36', 'first36', 'last36', 'author36'),
    37 => array('surname37', 'forename37', 'initials37', 'first37', 'last37', 'author37'),
    38 => array('surname38', 'forename38', 'initials38', 'first38', 'last38', 'author38'),
    39 => array('surname39', 'forename39', 'initials39', 'first39', 'last39', 'author39'),
    40 => array('surname40', 'forename40', 'initials40', 'first40', 'last40', 'author40'),
    41 => array('surname41', 'forename41', 'initials41', 'first41', 'last41', 'author41'),
    42 => array('surname42', 'forename42', 'initials42', 'first42', 'last42', 'author42'),
    43 => array('surname43', 'forename43', 'initials43', 'first43', 'last43', 'author43'),
    44 => array('surname44', 'forename44', 'initials44', 'first44', 'last44', 'author44'),
    45 => array('surname45', 'forename45', 'initials45', 'first45', 'last45', 'author45'),
    46 => array('surname46', 'forename46', 'initials46', 'first46', 'last46', 'author46'),
    47 => array('surname47', 'forename47', 'initials47', 'first47', 'last47', 'author47'),
    48 => array('surname48', 'forename48', 'initials48', 'first48', 'last48', 'author48'),
    49 => array('surname49', 'forename49', 'initials49', 'first49', 'last49', 'author49'),
    50 => array('surname50', 'forename50', 'initials50', 'first50', 'last50', 'author50'),
    51 => array('surname51', 'forename51', 'initials51', 'first51', 'last51', 'author51'),
    52 => array('surname52', 'forename52', 'initials52', 'first52', 'last52', 'author52'),
    53 => array('surname53', 'forename53', 'initials53', 'first53', 'last53', 'author53'),
    54 => array('surname54', 'forename54', 'initials54', 'first54', 'last54', 'author54'),
    55 => array('surname55', 'forename55', 'initials55', 'first55', 'last55', 'author55'),
    56 => array('surname56', 'forename56', 'initials56', 'first56', 'last56', 'author56'),
    57 => array('surname57', 'forename57', 'initials57', 'first57', 'last57', 'author57'),
    58 => array('surname58', 'forename58', 'initials58', 'first58', 'last58', 'author58'),
    59 => array('surname59', 'forename59', 'initials59', 'first59', 'last59', 'author59'),
    60 => array('surname60', 'forename60', 'initials60', 'first60', 'last60', 'author60'),
    61 => array('surname61', 'forename61', 'initials61', 'first61', 'last61', 'author61'),
    62 => array('surname62', 'forename62', 'initials62', 'first62', 'last62', 'author62'),
    63 => array('surname63', 'forename63', 'initials63', 'first63', 'last63', 'author63'),
    64 => array('surname64', 'forename64', 'initials64', 'first64', 'last64', 'author64'),
    65 => array('surname65', 'forename65', 'initials65', 'first65', 'last65', 'author65'),
    66 => array('surname66', 'forename66', 'initials66', 'first66', 'last66', 'author66'),
    67 => array('surname67', 'forename67', 'initials67', 'first67', 'last67', 'author67'),
    68 => array('surname68', 'forename68', 'initials68', 'first68', 'last68', 'author68'),
    69 => array('surname69', 'forename69', 'initials69', 'first69', 'last69', 'author69'),
    70 => array('surname70', 'forename70', 'initials70', 'first70', 'last70', 'author70'),
    71 => array('surname71', 'forename71', 'initials71', 'first71', 'last71', 'author71'),
    72 => array('surname72', 'forename72', 'initials72', 'first72', 'last72', 'author72'),
    73 => array('surname73', 'forename73', 'initials73', 'first73', 'last73', 'author73'),
    74 => array('surname74', 'forename74', 'initials74', 'first74', 'last74', 'author74'),
    75 => array('surname75', 'forename75', 'initials75', 'first75', 'last75', 'author75'),
    76 => array('surname76', 'forename76', 'initials76', 'first76', 'last76', 'author76'),
    77 => array('surname77', 'forename77', 'initials77', 'first77', 'last77', 'author77'),
    78 => array('surname78', 'forename78', 'initials78', 'first78', 'last78', 'author78'),
    79 => array('surname79', 'forename79', 'initials79', 'first79', 'last79', 'author79'),
    80 => array('surname80', 'forename80', 'initials80', 'first80', 'last80', 'author80'),
    81 => array('surname81', 'forename81', 'initials81', 'first81', 'last81', 'author81'),
    82 => array('surname82', 'forename82', 'initials82', 'first82', 'last82', 'author82'),
    83 => array('surname83', 'forename83', 'initials83', 'first83', 'last83', 'author83'),
    84 => array('surname84', 'forename84', 'initials84', 'first84', 'last84', 'author84'),
    85 => array('surname85', 'forename85', 'initials85', 'first85', 'last85', 'author85'),
    86 => array('surname86', 'forename86', 'initials86', 'first86', 'last86', 'author86'),
    87 => array('surname87', 'forename87', 'initials87', 'first87', 'last87', 'author87'),
    88 => array('surname88', 'forename88', 'initials88', 'first88', 'last88', 'author88'),
    89 => array('surname89', 'forename89', 'initials89', 'first89', 'last89', 'author89'),
    90 => array('surname90', 'forename90', 'initials90', 'first90', 'last90', 'author90'),
    91 => array('surname91', 'forename91', 'initials91', 'first91', 'last91', 'author91'),
    92 => array('surname92', 'forename92', 'initials92', 'first92', 'last92', 'author92'),
    93 => array('surname93', 'forename93', 'initials93', 'first93', 'last93', 'author93'),
    94 => array('surname94', 'forename94', 'initials94', 'first94', 'last94', 'author94'),
    95 => array('surname95', 'forename95', 'initials95', 'first95', 'last95', 'author95'),
    96 => array('surname96', 'forename96', 'initials96', 'first96', 'last96', 'author96'),
    97 => array('surname97', 'forename97', 'initials97', 'first97', 'last97', 'author97'),
    98 => array('surname98', 'forename98', 'initials98', 'first98', 'last98', 'author98'),
    99 => array('surname99', 'forename99', 'initials99', 'first99', 'last99', 'author99'),
);

/* FUNCTIONS */

/* Return a flat numerically indexed array containing all the parameters in
 * $author_parameters, and set the global variable $flattened_author_params
 * to that flat array.
 */
function flatten_author_parameters($author_parameters) {
  global $flattened_author_params;
  $flattened_author_params = array();

  foreach ($author_parameters as $i => $group) {
    $flattened_author_params = array_merge($flattened_author_params, $group);
  }

  return $flattened_author_params;
}

/** Returns a properly capitalised title.
 *      If sents is true (or there is an abundance of periods), it assumes it is dealing with a title made up of sentences, and capitalises the letter after any period.
  *             If not, it will assume it is a journal abbreviation and won't capitalise after periods.
 */
function capitalize_title($in, $sents = TRUE, $could_be_italics = TRUE) {
        global $dontCap, $unCapped;
        if ($in == mb_strtoupper($in) && mb_strlen(str_replace(array("[", "]"), "", trim($in))) > 6) {
                $in = mb_convert_case($in, MB_CASE_TITLE, "UTF-8");
        }
  $in = str_ireplace(" (New York, N.Y.)", "", $in); // Pubmed likes to include this after "Science", for some reason
  if ($could_be_italics) $in = preg_replace('~([a-z]+)([A-Z][a-z]+\b)~', "$1 ''$2''", $in); // <em> tags often go missing around species namesin CrossRef
  $captIn = str_replace($dontCap, $unCapped, " " .  $in . " ");
        if ($sents || (substr_count($in, '.') / strlen($in)) > .07) { // If there are lots of periods, then they probably mark abbrev.s, not sentance ends
    $newcase = preg_replace("~(\w\s+)A(\s+\w)~u", "$1a$2",
                                        preg_replace_callback("~\w{2}'[A-Z]\b~u" /*Apostrophes*/, create_function(
                    '$matches',
                    'return mb_strtolower($matches[0]);'
                ), preg_replace_callback("~[?.:!]\s+[a-z]~u" /*Capitalise after punctuation*/, create_function(
                    '$matches',
                    'return mb_strtoupper($matches[0]);'
                ), trim($captIn))));
        } else {
                $newcase = preg_replace("~(\w\s+)A(\s+\w)~u", "$1a$2",
                                        preg_replace_callback("~\w{2}'[A-Z]\b~u" /*Apostrophes*/, create_function(
                    '$matches',
                    'return mb_strtolower($matches[0]);'
                ), trim(($captIn))));
        }
  $newcase = preg_replace_callback("~(?:'')?(?P<taxon>\p{L}+\s+\p{L}+)(?:'')?\s+(?P<nova>(?:(?:gen\.? no?v?|sp\.? no?v?|no?v?\.? sp|no?v?\.? gen)\b[\.,\s]*)+)~ui", create_function('$matches',
          'return "\'\'" . ucfirst(strtolower($matches[\'taxon\'])) . "\'\' " . strtolower($matches["nova"]);'), $newcase);
  // Use 'straight quotes' per WP:MOS
  $newcase = straighten_quotes($newcase);
  if (in_array(" " . trim($newcase) . " ", $unCapped)) {
    // Keep "z/Journal" with lcfirst
    return $newcase;
  } else {
    // Catch "the Journal" --> "The Journal"
    $newcase = mb_convert_case(mb_substr($newcase, 0, 1), MB_CASE_TITLE, "UTF-8") . mb_substr($newcase, 1);
     return $newcase;
  }
}

function tag($long = FALSE) {
  $dbg = array_reverse(debug_backtrace());
  echo ' [..';
  array_pop($dbg); array_shift($dbg);
  foreach ($dbg as $d) {
    echo '> ' . ($long
      ? $d['function']
      : substr(preg_replace('~_(\w)~', strtoupper("$1"), $d['function']), -7)
    );
  }
  echo ']';
}

function url2template($url, $citation) {
  if (preg_match("~jstor\.org/(?!sici).*[/=](\d+)~", $url, $match)) {
    return "{{Cite doi | 10.2307/$match[1] }}";
  } else if (preg_match("~//dx\.doi\.org/(.+)$~", $url, $match)) {
    return "{{Cite doi | " . urldecode($match[1]) . " }}";
  } else if (preg_match("~^https?://www\.amazon(?P<domain>\.[\w\.]{1,7})/dp/(?P<id>\d+X?)~", $url, $match)) {
    return ($match['domain'] == ".com") ? "{{ASIN | {$match['id']} }}" : " {{ASIN|{$match['id']}|country=" . str_replace(array(".co.", ".com.", "."), "", $match['domain']) . "}}";
  } else if (preg_match("~^https?://books\.google(?:\.\w{2,3}\b)+/~", $url, $match)) {
    return "{{" . ($citation ? 'Cite book' : 'Cite journal') . ' | url = ' . $url . '}}';
  } else if (preg_match("~^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                  . "|^https?://www\.ncbi\.nlm\.nih\.gov/pmc/articles/PMC(\d+)~", $url, $match)) {
    return "{{Cite pmc | {$match[1]}{$match[2]} }}";
  } elseif (preg_match(bibcode_regexp, urldecode($url), $bibcode)) {
    return "{{Cite journal | bibcode = " . urldecode($bibcode[1]) . "}}";
  } else if (preg_match("~https?://www.ncbi.nlm.nih.gov/pubmed/.*=(\d{6,})~", $url, $match)) {
    return "{{Cite pmid | {$match[1]} }}";
  } else if (preg_match("~\barxiv.org/(?:pdf|abs)/(.+)$~", $url, $match)) {
    return "{{Cite arxiv | eprint={$match[1]} }}";
  } else {
    return $url;
  }
}

function expand_cite_page ($title) {
  $page = new Page();
  $attempts = 0;
  if ($page->get_text_from($title) && $page->expand_text()) {
    echo "\n # Writing to " . $page->title;
    while (!$page->write() && $attempts < 3) $attempts++;
    if (!articleID($page) && !$doiCrossRef && $oDoi) { #TODO!
      leave_broken_doi_message($page, $article_in_progress, $oDoi);
    }
  } else {
    echo "\n # " . ($page->text ? 'No changes required.' : 'Blank page') . "\n # # # ";
    updateBacklog($page->title);
  }
}


/* OBJECTS */

class Page {

  public $text, $title, $modifications;
  protected $ref_names;
  
  public function is_redirect() {
    $url = Array(
        "action" => "query",
        "format" => "xml",
        "prop" => "info",
        "titles" => $this->title,
        );
    $xml = load_xml_via_bot($url);
    if ($xml->query->pages->page["pageid"]) {
      // Page exists
      return array ((($xml->query->pages->page["redirect"])?1:0),
                      $xml->query->pages->page["pageid"]);
      } else {
        return array (-1, null);
     }
  }
  
  public function get_text_from($title) {
    global $bot;
    $bot->fetch(wikiroot . "title=" . urlencode($title) . "&action=raw");
    $this->text = $bot->results;
    $this->start_text = $this->text;
    $this->modifications = array();
    
    $bot->fetch(api . "?action=query&prop=info&format=json&titles=" . urlencode($title));
    $details = json_decode($bot->results);
    foreach ($details->query->pages as $p) {
      $my_details = $p;
    }
    $details = $my_details;
    $this->title = $details->title;
    $this->namespace = $details->ns;
    $this->touched = $details->touched;
    $this->lastrevid = $details->lastrevid;

    if (stripos($this->text, '#redirect') !== FALSE) {
      echo "Page is a redirect.";
      updateBacklog($title);
      return FALSE;
    }

    // FIXME: take out cite template abilities/references.
    if (strpos($title, "Template:Cite") !== FALSE) $this->cite_template = TRUE;
    if ($this->cite_template && !$this->text) $this->text = $cite_doi_start_code;

    if ($this->text) {
      return TRUE;
    } else{
      return NULL;
    }
  }
  
  public function expand_text() {
    global $html_output;
    quiet_echo ("\n<hr>[" . date("H:i:s") . "] Processing page '<a href='http://en.wikipedia.org/wiki/" . addslashes($this->title) . "' style='text-weight:bold;'>{$this->title}</a>' &mdash; <a href='http://en.wikipedia.org/?title=". addslashes(urlencode($this->title))."&action=edit' style='text-weight:bold;'>edit</a>&mdash;<a href='http://en.wikipedia.org/?title=" . addslashes(urlencode($this->title)) . "&action=history' style='text-weight:bold;'>history</a> <script type='text/javascript'>document.title=\"Citation bot: '" . str_replace("+", " ", urlencode($this->title)) ."'\";</script>");
    $text = $this->text;
    $this->modifications = array();
    if (!$text) {
      echo "\n\n  ! No text retrieved.\n";
      return false;
    }

    //this is set to -1 only in text.php, because there's no need to output
    // a buffer of text for the citation-expander gadget
    if ($html_output === -1) {
      ob_start();
    }

    // COMMENTS //
    $comments = $this->extract_object(Comment);
    if ($bot_exclusion_compliant && !$this->allow_bots()) {
      echo "\n ! Page marked with {{nobots}} template.  Skipping.";
      updateBacklog($this->title);
      return FALSE;
    }

    // TEMPLATES //
    $templates = $this->extract_object(Template);
    $start_templates = $templates;
    $citation_templates = 0; $cite_templates = 0;

    if ($templates) {
      foreach ($templates as $template) {
        if ($template->wikiname() == 'citation') {
          $citation_templates++;
        } elseif (preg_match("~[cC]ite[ _]\w+~", $template->wikiname())) {
          $cite_templates++;
        } elseif (stripos($template->wikiname(), 'harv') === 0) {
          $harvard_templates++;
        }
      }
    }

    for ($i = 0; $i < count($templates); $i++) {
      $templates[$i]->process();
      $template_mods = $templates[$i]->modifications();
      foreach (array_keys($template_mods) as $key) {
        if (!$this->modifications[$key]) {
          $this->modifications[$key] = $template_mods[$key];
        } else {
          if ($template_mods[$key]) {
            $this->modifications[$key] = array_unique(array_merge($this->modifications[$key], $template_mods[$key]));
          }
        }
      }
    }
    $text = $this->replace_object($templates);

    // REFERENCE TAGS //
    if (FALSE && $reference_support_debugged) { #todo
      if ($this->has_reflist) {
        # TODO! Handle reflists.
        /*TODO - once all refs are done, swap short refs in reflists with their long equivalents elsewhere.
        if (preg_match(reflist_regexp, $page_code, $match) &&
          preg_match_all('~[\r\n\*]*<ref name=(?P<quote>[\'"]?)(?P<name>.+?)(?P=quote)\s*!!!dontstopthecomment!!!/\s*>~i', $match[1], $empty_refs)) {
          // If <ref name=Bla /> appears in the reference list, it'll break things.  It needs to be replaced with <ref name=Bla>Content</ref>
          // which ought to exist earlier in the page.  It's important to check that this doesn't exist elsewhere in the reflist, though.

          print_r($match[1]);die('--');
          $temp_reflist = $match[1];
          foreach ($empty_refs['name'] as $i => $ref_name) {
            echo "\n   - Found an empty ref in the reflist; switching with occurrence in article text."
                ."\n     Reference #$i name: $ref_name";
            $this_regexp = '~<ref name=(?P<quote>[\'"]?)' . preg_quote($ref_name)
                    . '(?P=quote)\s*>[\s\S]+?<\s*!!dontstopthecomment!!/\s*ref>~';
            if (preg_match($this_regexp, $temp_reflist, $full_ref)) {
              // A full-text reference exists elsewhere in the reflist.  The duplicate can be safely deleted from the reflist.
              $temp_reflist = str_replace($empty_refs[0][$i], '', $temp_reflist);
            } elseif (preg_match($this_regexp, $page_code, $full_ref)) {
              // Remove all full-text references from the page code.  We'll add an updated reflist later.
              $page_code = str_replace($full_ref[0], $empty_refs[0][$i], $page_code);
              $temp_reflist = str_replace($empty_refs[0][$i], $full_ref[0], $temp_reflist);
            }
          }
          // Add the updated reflist, which should now contain no empty references.
          $page_code = str_replace($match[1], $temp_reflist, $page_code);
        }*/
        print "\n ! Not addressing reference tags: unsupported template {{reflist}} present.";
      } else {
        $short_refs = $this->extract_object(Short_Reference);
        $long_refs = $this->extract_object(Long_Reference);
        // note: all the action happens in the increment section. Would be better as a foreach, FIXME
        for ($i = 0; $i < count($long_refs); $long_refs[$i++]->process($citation_template_dominant)) {}

        foreach ($long_refs as $i=>$ref) {
          $ref_contents[$i] = str_replace(' ', '', $ref->content);
          $this->ref_names[$i] = $ref->attr['name'];
        }
        $duplicate_names = array();
        if ($this->ref_names) {
          natcasesort($this->ref_names);
          reset($this->ref_names);
        }

        $old_name = NULL;

        foreach ($this->ref_names as $key => $name) {
          if ($name === NULL) {
            continue;
          }

          if (strcasecmp($name, $old_name) === 0) {
            $to_rename[] = $key;
          }
          $old_name = $name;
        }

        if ($to_rename) foreach ($to_rename as $ref) {
          $new_name = $this->generate_template_name($this->ref_names[$ref]);
          $this->ref_names[$ref] = $new_name;
          $long_refs[$ref]->name($new_name);
        }
        
        $duplicate_refs = array();
        natcasesort($ref_contents);
        reset($ref_contents);
        $old_key = NULL; $old_val = NULL;

        foreach ($ref_contents as $key => $val) {
          if ($val === NULL) continue;
          if (strcasecmp($old_val, $val) === 0) {
            $duplicate_refs[$val][] = $old_key;
            $duplicate_refs[$val][] = $key;
          }
          $old_val = $val; $old_key = $key;
        }

        foreach ($duplicate_refs as $dup) {
          $dup_name = NULL;
          $name_giver = NULL;
          natsort($dup);
          foreach ($dup as $instance) {
            if ($this_name = $long_refs[$instance]->attr['name']) {
              $dup_name = $this_name;
              $name_giver = $instance;
            }
          }
          foreach ($dup as $instance) {
            if ($name_giver === NULL) $name_giver = $instance;
            if (!$dup_name) $dup_name = get_name_for_reference($long_refs[$name_giver]);
            if ($instance != $name_giver) $long_refs[$instance]->shorten($dup_name);
          }
        }

        $this->replace_object($long_refs);
        $this->replace_object($short_refs);
      }
    }

    $this->replace_object($comments);

    // seems to be set as -1  in text.php and then re-set
    if ($html_output === -1) {
      ob_end_clean();
    }

    return strcasecmp($this->text, $this->start_text) != 0;
  }

  // FIXME: this is only used in the pmid and doi parts of doibot.php and is probably not at all useful anymore. 
  public function expand_remote_templates() {
    $doc_footer = "<noinclude>{{Documentation|Template:cite_%s/subpage}}</noinclude>";
    $templates = $this->extract_object(Template);
    if (count($templates) == 0) return NULL;
    $pmid_to_do = array();
    $doi_to_do = array();
    foreach ($templates as $template) {
      switch (strtolower($template->wikiname())) {
        case 'ref doi':
          #TODO derefify
        case 'cite doi':
          $template->remove_non_ascii();
          array_push($doi_to_do, $template->get(0));
        break;
        case 'ref jstor': 
          #TODO derefify
        case 'cite jstor':
          $template->remove_non_ascii();
          array_push($doi_to_do, '10.2307/' . $template->get(0));
        case 'ref pmid':
          #TODO derefify
        case 'cite pmid':
          $template->remove_non_ascii();
          array_push($pmid_to_do, $template->get(0));
        break;
      }
    }
    $doi_to_do = array_unique($doi_to_do);
    $pmid_to_do = array_unique($pmid_to_do);
    if (count($doi_to_do) == 0 && count($pmid_to_do) == 0) {
      $this->replace_object($templates);
      return NULL;
    }
    if ($pmid_to_do) foreach ($pmid_to_do as $pmid) {
      echo "\n   > PMID $pmid: ";
      $template_page = new Page();
      $template_page->title = "Template:Cite pmid/$pmid";
      $template = new Template();
      $template->parse_text("{{Cite journal\n | pmid = $pmid\n}}");
      $is_redirect = $template_page->is_redirect();
      switch($is_redirect[0]) {
        case -1:
          echo "\r\n   * Expanding template from PMID";
          $template->expand_by_pubmed();
          // Page has not yet been created for this PMID.
          if ($doi = $template->get('doi')) {
            // redirect to a Cite Doi page, to avoid duplication
            $encoded_doi = anchorencode($doi);
            $template_page->text = "#REDIRECT[[Template:Cite doi/$encoded_doi]]";
            echo "\n * Creating redirect to DOI $doi";
            echo ($template_page->write(" Redirecting to DOI citation") ? " - success" : " - failed.");
            $template_page->title = "Template:Cite doi/$encoded_doi";
            $type = 'doi';
            echo "\n * Creating new page for DOI $doi... ";
          } else {
            echo  "\n * No DOI found; creating new page for PMID $pmid... ";
            $type = 'pmid';
          }
          $template_page->text = $template->parsed_text() . sprintf($doc_footer, $type);
          $template_page->write("New page, from {{Cite pmid}} template in [[" . $this->title . ']].');
        break;
        case 0:
          #TODO: log_citation("pmid", $pmid);
          // Save to database
          echo "Citation OK";
        break;
        case 1:    // Page exists; we need to check that the redirect has been created.
          // Check that redirect leads to a cite doi:
          $redirect_page = new Page();
          $redirect_page->get_text_from('Template:Cite pmid/' . $pmid);
          
          global $dotDecode, $dotEncode;
          if (preg_match("~/(10\..*)]]~",
                str_replace($dotEncode, $dotDecode, $redirect_page->text), $redirect_target_doi)) {
            $encoded_doi = anchorencode(trim($redirect_target_doi[1]));
            echo "Redirects to ";
            if (getArticleId("Template:Cite doi/" . $encoded_doi)) {
              // Destination page exists
              log_citation("pmid", $oPmid, $redirect_target_doi[1]);
              echo $redirect_target_doi[1] . ".";
            } else {
              // Create it if it doesn't
              echo "nonexistent page. Creating > ";
              $template_page->title = 'Template:Cite doi/' . $encoded_doi;
              $template->add_if_new('doi', $redirect_target_doi[1]);
              $template->expand_by_pubmed();
              $template->cite_doi_format();
              $template_page->text = $template->parsed_text() . sprintf($doc_footer, 'doi');
              $template_page->write("New page from Cite pmid redirect in [[" . $this->title . "]]");
            }
          } else {
            exit ($redirect_page->title . " redirects to " . $redirect_page->text);
          }
        break;
        case 2: echo "Database lists page as a redirect"; break;
      }    
    }
    if ($doi_to_do) foreach ($doi_to_do as $doi) {
      if (preg_match("~^[\s,\.:;>]*(?:d?o?i?[:.,>\s]+|(?:http://)?dx\.doi\.org/)(?P<doi>.+)~i", $doi, $match)
        || preg_match('~^0?(?P<end>\.\d{4}/.+)~', $doi, $match)) {
        $doi = $match['doi'] ? $match['doi'] : '10' . $match['end'];
        if ($this->text) {
          echo "\n   > Fixing prefixes in {{cite doi}} templates, in [[$this->title]]: ";
          $this->text = str_replace("1$doi", $doi, str_replace($match[0], $doi, $this->text));
        }
      }
      $doi_citation_exists = doi_citation_exists($doi); // Checks in our database
      if ($doi_citation_exists) {
        quiet_echo("\n   . Citation exists at $doi");
        if ($doi_citation_exists > 1) log_citation("doi", $doi);
      } else {
        echo "\n   > Creating new page for DOI $doi: ";       
        $template_page = new Page();
        $encoded_doi = anchorencode($doi);
        $template_page->title = "Template:Cite doi/$encoded_doi";
        $template = new Template();
        $template->parse_text("{{Cite journal\n | doi = $doi\n}}");
        $template->expand_by_doi();
        $template->cite_doi_format();
        $template_page->text = $template->parsed_text() . sprintf($doc_footer, 'doi');
        $template_page->write("New page from {{Cite doi}} template in [[" . $this->title . "]]");   
      }  
    }
    $this->replace_object($templates);
    return TRUE;
  }
    
  public function edit_summary() {
    $auto_summary = "";
    if ($this->modifications["changeonly"]) $auto_summary .= "Alter: " . implode(", ", $this->modifications["changeonly"]) . ". ";
    if ($addns = $this->modifications["additions"]) {
      $auto_summary .= "Add: ";
      $min_au = 9999;
      $max_au = 0;
      while ($add = array_pop($addns)) {
        if (preg_match('~(?:author|last|first)(\d+)~', $add, $match)) {
          if ($match[1] < $min_au) $min_au = $match[1];
          if ($match[1] > $max_au) $max_au = $match[1];
        } else $auto_summary .= $add . ', ';
      }
      if ($max_au) $auto_summary .= "author pars. $min_au-$max_au. ";
      else $auto_summary = substr($auto_summary, 0, -2) . '. ';
    }
    if ($this->modifications["deletions"] && ($pos = array_search('accessdate', $this->modifications["deletions"])) !== FALSE) {
      $auto_summary .= "Removed accessdate with no specified URL. ";
      unset($this->modifications["deletions"][$pos]);
    }    
    $auto_summary .= (($this->modifications["deletions"])
      ? "Removed redundant parameters. "
      : ""
      ) . (($this->modifications["cite_type"])
      ? "Unified citation types. "
      : ""
      ) . (($this->modifications["combine_references"])
      ? "Combined duplicate references. "
      : ""
      ) . (($this->modifications["dashes"])
      ? "Formatted [[WP:ENDASH|dashes]]. "
      : ""
      ) . (($this->modifications["arxiv_upgrade"])
      ? "Updated published arXiv refs. "
      : ""
    );
    if ($this->modifications['ref_names']) $auto_summary .= 'Named references. ';
    if (!$auto_summary) $auto_summary = "Misc citation tidying. ";
    global $edit_initiator;
    return $edit_initiator . $auto_summary . "You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].";
  }
  
  public function write($edit_summary = NULL) {
    if ($this->allow_bots()) {
      global $bot;
      // Check that bot is logged in:
      $bot->fetch(api . "?action=query&prop=info&meta=userinfo&format=json");
      $result = json_decode($bot->results);
      if ($result->query->userinfo->id == 0) {
        echo "\n ! LOGGED OUT:  The bot has been logged out from Wikipedia servers";
        return FALSE;
      }

      // FIXME: this is very deprecated, use ?action=query&meta=tokens to get a 'csrf' type token (the default)
      $bot->fetch(api . "?action=query&prop=info&format=json&intoken=edit&titles=" . urlencode($this->title));
      $result = json_decode($bot->results);
      foreach ($result->query->pages as $i_page) $my_page = $i_page;
      if ($my_page->lastrevid != $this->lastrevid) {
        echo "\n ! Possible edit conflict detected. Aborting.";
        return FALSE;
      }
      global $edit_initiator;
      $submit_vars = array(
          "action" => "edit",
          "title" => $my_page->title,
          "text" => $this->text,
          "token" => $my_page->edittoken, // from $result above
          "summary" => $edit_summary ? ($edit_initiator . $edit_summary) : $this->edit_summary(),
          "minor" => "1",
          "bot" => "1",
          "basetimestamp" => $my_page->touched,
          "starttimestamp" => $my_page->starttimestamp,
          #"md5"       => hash('md5', $data), // removed because I can't figure out how to make the hash of the UTF-8 encoded string that I send match that generated by the server.
          "watchlist" => "nochange",
          "format" => "json",
      );
      $bot->submit(api, $submit_vars);
      $result = json_decode($bot->results);
      if ($result->edit->result == "Success") {
        // Need to check for this string whereever our behaviour is dependant on the success or failure of the write operation
        global $html_output;
        if ($html_output) echo "\n <span style='color: #e21'>Written to <a href='" . wikiroot . "title=" . urlencode($my_page->title) . "'>" . $my_page->title . '</a></span>';
        else echo "\n Written to " . $my_page->title . '.  ';
        return TRUE;
      } else if ($result->edit->result) {
        echo $result->edit->result;
        return TRUE;
      } else if ($result->error->code) {
        // Return error code
        echo "\n ! " . strtoupper($result->error->code) . ": " . str_replace(array("You ", " have "), array("This bot ", " has "), $result->error->info);
        return FALSE;
      } else {
        echo "\n ! Unhandled error.  Please copy this output and <a href=http://code.google.com/p/citation-bot/issues/list>report a bug.</a>";
        return FALSE;
      }
      updateBacklog($page);
    } else {
      echo "\n - Can't write to " . $this->title . " - prohibited by {{bots]} template.";
      updateBacklog($page);
    }
  }
  
  protected function extract_object ($class) {
    $i = 0;
    $text = $this->text;
    $regexp = $class::regexp;
    $placeholder_text = $class::placeholder_text;
    $treat_identical_separately = $class::treat_identical_separately;
    while(preg_match($regexp, $text, $match)) {
      $obj = new $class();
      $obj->parse_text($match[0]);
      $exploded = $treat_identical_separately ? explode($match[0], $text, 2) : explode($match[0], $text);
      $text = implode(sprintf($placeholder_text, $i++), $exploded);
      $obj->occurrences = count($exploded) - 1;
      $obj->page = $this;
      $objects[] = $obj;
    }
    $this->text = $text;
    return $objects;
  }

  protected function replace_object ($objects) {
    $i = count($objects);
    if ($objects) foreach (array_reverse($objects) as $obj) 
      $this->text = str_replace(sprintf($obj::placeholder_text, --$i), $obj->parsed_text(), $this->text);
  }

  public function allow_bots() {
    // from http://en.wikipedia.org/wiki/Template:Nobots example implementation
    $user = '(?:Citation|DOI)[ _]bot';
    if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.$user.'.*?)\}\}/iS',$this->text))
      return false;
    if (preg_match('/\{\{(bots\|allow=all|bots\|allow=.*?'.$user.'.*?)\}\}/iS', $this->text))
      return true;
    if (preg_match('/\{\{(bots\|allow=.*?)\}\}/iS', $this->text))
      return false;
    return true;
  }
    
  public function generate_template_name($replacement_name) {
    // Strips special characters from reference name,
    // then does a check against $this->ref_names to generate a unique name for the reference
    // (by suffixing _a, etc, as necessary)
    $replacement_name = remove_accents($replacement_name);
    if (preg_match("~^[\d\s]*$~", $replacement_name)) $replacement_name = "ref" . $replacement_name;
    if (!$this->ref_names || !in_array($replacement_name, $this->ref_names)) return $replacement_name;
    global $alphabet;
    $die_length = count($alphabet);
    $underscore = (preg_match("~[\d_]$~", $replacement_name) ? "" : "_");
    $i = 1;
    while (in_array($replacement_name . $underscore . $alphabet[$i], $this->ref_names)) {
      if (++$i >= $die_length) {
        if ($j) {
          $replacement_name = substr($replacement_name, -1) . $alphabet[++$j];
          if ($j == $die_length) $j = 0;
        } else {
          $replacement_name .= $underscore . $alphabet[++$j];
          $underscore = "";
        }
        $i = 1;
      }
    }
    return $replacement_name . ($i < 1 ? '' : $underscore) . $alphabet[$i];
  }
}

class Item {
  protected $rawtext;
  public $occurrences, $page;
}

class Comment extends Item {
  const placeholder_text = '# # # Citation bot : comment placeholder %s # # #';
  const regexp = '~<!--.*-->~us';
  const treat_identical_separately = FALSE;
  
  public function parse_text($text) {
    $this->rawtext = $text;
  }
  
  public function parsed_text() {
    return $this->rawtext;
  }
}

class Short_Reference extends Item {
  const placeholder_text = '# # # Citation bot : short ref placeholder %s # # #';
  const regexp = '~<ref\s[^>]+?/>~s';
  const treat_identical_separately = FALSE;
  
  public $start, $end, $attr;
  protected $rawtext;
  
  public function parse_text($text) {
    preg_match('~(<ref\s+)(.*)(\s*/>)~', $text, $bits);
    $this->start = $bits[1];
    $this->end = $bits[3];
    $bits = explode('=', $bits[2]);
    $next_attr = array_shift($bits);
    $last_attr = array_pop($bits);
    foreach ($bits as $bit) {
      preg_match('~(.*\s)(\S+)$~', $bit, $parts);
      $this->attr[$next_attr] = $parts[1];
      $next_attr = $parts[2];
    }
    $this->attr[$next_attr] = $last_attr;
  }
  
  public function parsed_text() {
    foreach ($this->attr as $key => $value) {
      $middle .= $key . '=' . $value;
    }
    return $this->start . $middle . $this->end;
  } 
}

// LONG REFERENECE //
class Long_Reference extends Item {
  const placeholder_text = '# # # Citation bot : long ref placeholder %s # # #';
  const regexp = '~<ref\s?[^/>]*?>.*?<\s*/\s*ref\s*>~s';
  const treat_identical_separately = TRUE;
  
  protected $open_start, $open_attr, $open_end, $close;
  public $content;
  protected $rawtext;
  
  public function name($new_name = FALSE) {
    if (!$new_name) return $this->attr['name'];
    $this->attr['name'] = $new_name;
    if (substr($this->open_start, -1) != ' ') $this->open_start .= ' ';
    return $new_name;
  }
  
  public function process($use_citation_template = FALSE) {
    $this->content = preg_replace_callback('~https?://\S+~',
      function ($matches) {
        return url2template($matches[0], $use_citation_template);
      }, $this->content
    );
    if (!$this->attr['name']
    || preg_match('~ref_?[ab]?(?:..?|utogenerated|erence[a-zA-Z]*)?~i', $this->attr['name'])
    ) echo "\n * Generating name for anonymous reference [" . $this->attr['name'] . ']: ' . $this->generate_name();
    else print "\n * No name for ". $this->attr['name'];
  }
  
  public function generate_name() {
    $text = $this->content;
    if (stripos($text, '{{harv') !== FALSE && preg_match("~\|([\s\w\-]+)\|\s*([12]\d{3})\D~", $text, $match)) {
      $author = $match[1];
      $date = $match[2];
    } else {
      $parsed = parse_wikitext(strip_tags($text));
      $parsed_plaintext = strip_tags($parsed);
      $date = (preg_match("~rft\.date=[^&]*(\d\d\d\d)~", $parsed, $date) ? $date[1] : "" );
      $author = preg_match("~rft\.aulast=([^&]+)~", $parsed, $author) 
              ? urldecode($author[1])
              : preg_match("~rft\.au=([^&]+)~", $parsed, $author) ? urldecode($author[1]) : "ref_";
      $btitle = preg_match("~rft\.[bah]title=([^&]+)~", $parsed, $btitle) ? urldecode($btitle[1]) : "";
    }
    if ($author != "ref_") {
      preg_match("~\w+~", authorify($author), $author);
    } else if ($btitle) {
      preg_match("~\w+\s?\w+~", authorify($btitle), $author);
    } else if ($parsed_plaintext) {
      if (!preg_match("~\w+\s?\w+~", authorify($parsed_plaintext), $author)) {
        preg_match("~\w+~", authorify($parsed_plaintext), $author);
      }
    }
    if (strpos($text, "://")) {
      if (preg_match("~\w+://(?:www\.)?([^/]+?)(?:\.\w{2,3}\b)+~i", $text, $match)) {
        $replacement_template_name = $match[1];
      } else {
        $replacement_template_name = "bare_url"; // just in case there's some bizarre way that the URL doesn't match the regexp
      }
    } else {
      $replacement_template_name = str_replace(Array("\n", "\r", "\t", " "), "", ucfirst($author[0])) . $date;
    }
    $this->name($this->page->generate_template_name($replacement_template_name));
    $this->page->modifications['ref_names'] = TRUE;
    $this->page->ref_names[$this->name] = TRUE;
    return $this->name();
  }
  
  public function shorten($name) {
    $this->attr['name'] = $name;
    $this->open_start = trim($this->open_start) . ' ';
    $this->open_end = '';
    $this->content = '';
    $this->close = ' />';
  }
  
  public function parse_text($text) {
    preg_match('~(<ref\s?)(.*)(\s*>)(.*?)(<\s*/\s*ref\s*>)~s', $text, $bits);
    $this->rawtext = $text;
    $this->open_start = $bits[1];
    $this->open_end = $bits[3];
    $this->content = $bits[4];
    $this->close = $bits[5];
    $bits = explode('=', $bits[2]);
    $next_attr = array_shift($bits);
    $last_attr = array_pop($bits);
    foreach ($bits as $bit) {
      preg_match('~(.*\s)(\S+)$~', $bit, $parts);
      $this->attr[$next_attr] = $parts[1];
      $next_attr = $parts[2];
    }
    $this->attr[$next_attr] = $last_attr;
  }
  
  public function parsed_text() {
    if ($this->attr) foreach ($this->attr as $key => $value) {
      $middle .= $key . ($key && $value ? '=' : '') . $value;
    }
    return $this->open_start . $middle . $this->open_end . $this->content . $this -> close;
  }
}
