<?php
/*
 * Template extends Item. Template has methods to handle most aspects of citation template
 * parsing, handling, and expansion.
 *
 * Of particular note:
 *     process() is what handles the different cite/Cite templates differently.
 *     add_if_new() is generally called to add or sometimes overwrite parameters. The central
 *       switch statement handles various parameters differently.
 *     tidy() cleans up citations and the templates, but it includes various other functions
 *       and side effects as well. Beware!
 *
 * A range of functions will search CrossRef/adsabs/Google Books/other online databases
 * to find information that can be added to existing citations.
 */

require_once("Item.php");
require_once("Page.php");
require_once("Parameter.php");

class Template extends Item {
  const placeholder_text = '# # # Citation bot : template placeholder %s # # #';
  const regexp = '~\{\{(?:[^\{]|\{[^\{])+?\}\}~s';
  const treat_identical_separately = FALSE;

  protected $name, $param, $initial_param, $initial_author_params, $citation_template, $mod_dashes;

  public function parse_text($text) {
    
    $this->rawtext = $text;
    $pipe_pos = strpos($text, '|');
    if ($pipe_pos) {
      $this->name = substr($text, 2, $pipe_pos - 2); # Remove {{ and }}
      $this->split_params(substr($text, $pipe_pos + 1, -2));
    } else {
      $this->name = substr($text, 2, -2);
      $this->param = NULL;
    }

    // extract initial parameters/values from Parameters in $this->param
    if ($this->param) foreach ($this->param as $p) {
      $this->initial_param[$p->param] = $p->val;

      // Save author params for special handling
      if (in_array($p->param, FLATTENED_AUTHOR_PARAMETERS) && $p->val) {
        $this->initial_author_params[$p->param] = $p->val;
      }
    }
  }

  // Parts of each param: | [pre] [param] [eq] [value] [post]
  protected function split_params($text) {
    // Replace | characters that are inside template parameter/value pairs
    $text = preg_replace('~(\[\[[^\[\]]+)\|([^\[\]]+\]\])~', "$1" . PIPE_PLACEHOLDER . "$2", $text);
    $params = explode('|', $text);

    // TODO: this naming is confusing, distinguish between $text above and
    //       $text in the loop (derived from $text above via $params)
    foreach ($params as $i => $text) {
      $this->param[$i] = new Parameter();
      $this->param[$i]->parse_text($text);
    }
  }

  public function parameter_names_to_lowercase() {
    if (is_array($this->param)) {
      $keys = array_keys($this->param);
      for ($i=0; $i < count($keys); $i++) {
        $this->param[$keys[$i]]->param = strtolower($this->param[$keys[$i]]->param);
      }
    } else {
      $this->param = strtolower($this->param);
    }
  }

  public function process() {
    switch ($this->wikiname()) {
      case 'cite web':
        $this->use_unnamed_params();
        $this->get_identifiers_from_url();
        $this->tidy();
        if ($this->has('journal') || $this->has('bibcode') || $this->has('jstor') || $this->has('arxiv')) {
          $this->name = 'Cite journal';
          $this->process();
        } elseif ($this->has('eprint')) {
          $this->name = 'Cite arxiv';
          $this->process();
        }
        $this->citation_template = TRUE;
      break;
      case 'cite arxiv':
        $this->citation_template = TRUE;
        $this->use_unnamed_params();
        $this->expand_by_arxiv();
        $this->expand_by_doi();
        $this->tidy();
        if ($this->has('journal')) {
          $this->name = 'Cite journal';
          $this->rename('eprint', 'arxiv');
          $this->forget('class');
          $this->forget('publisher');
        }
      break;
      case 'cite book':
        $this->citation_template = TRUE;

        // If the et al. is from added parameters, go ahead and handle
        # if (!$this->initial_author_parameters) { // This property does not seem to be sent anywhere
          $this->handle_et_al();
        #}

        $this->use_unnamed_params();
        $this->get_identifiers_from_url();
        $this->id_to_param();
        echo "\n* " . htmlspecialchars($this->get('title'));
        $this->correct_param_spelling();
        if ($this->expand_by_google_books()) {
          echo "\n * Expanded from Google Books API";
        }
        $this->tidy();
        if ($this->find_isbn()) {
          echo "\n * Found ISBN " . htmlspecialchars($this->get('isbn'));
        }
      break;
      case 'cite journal': case 'cite document': case 'cite encyclopaedia': case 'cite encyclopedia': case 'citation':
        $this->citation_template = TRUE;
        echo "\n\n* Expand citation: " . htmlspecialchars($this->get('title'));
        $this->use_unnamed_params();
        $this->get_identifiers_from_url();

        if ($this->use_sici()) {
          echo "\n * Found and used SICI";
        }

        $this->id_to_param();
        $this->get_doi_from_text();
        $this->correct_param_spelling();
        // TODO: Check for the doi-inline template in the title

        // If the et al. is from added parameters, go ahead and handle
        #if (!$this->initial_author_params) { // This parameter seems not to be set anywhere
          $this->handle_et_al();
        #}

        $this->expand_by_pubmed(); //partly to try to find DOI

        if ($this->has("periodical") ) {
          $journal_type = "periodical";
        } else {
          $journal_type = "journal";
        }

        if ($this->expand_by_google_books()) {
          echo "\n * Expanded from Google Books API";
        }
        $this->sanitize_doi();
        if ($this->verify_doi()) {
          $this->expand_by_doi();
        }
        $this->tidy(); // Do now to maximize quality of metadata for DOI searches, etc
        $this->expand_by_adsabs(); //Primarily to try to find DOI
        $this->get_doi_from_crossref();
        $this->get_open_access_url();
        $this->find_pmid();
        $this->tidy();
    }
    if ($this->citation_template) {
      $this->correct_param_spelling();
      $this->check_url();
    }
  }

  protected function incomplete() {
    if ($this->blank('pages', 'page') || (preg_match('~no.+no|n/a|in press|none~', $this->get('pages') . $this->get('page')))) {
      return TRUE;
    }
    if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
    return (!(
             ($this->has('journal') || $this->has('periodical'))
          &&  $this->has("volume")
          &&  ($this->has("issue") || $this->has('number'))
          &&  $this->has("title")
          && ($this->has("date") || $this->has("year"))
          && ($this->has("author2") || $this->has("last2") || $this->has('surname2'))
    ));
  }

  public function blank($param) {
    if (!$param) return ;
    if (empty($this->param)) return true;
    if (!is_array($param)) $param = array($param);
    foreach ($this->param as $p) {
      if (in_array($p->param, $param) && trim($p->val) != '') return FALSE;
    }
    return TRUE;
  }

  public function add_if_new($param_name, $value) {
    if (array_key_exists($param_name, COMMON_MISTAKES)) {
      $param_name = COMMON_MISTAKES[$param_name];
    }

    if (trim($value) == "") {
      return false;
    }

    // If we already have name parameters for author, don't add more
    if ($this->initial_author_params && in_array($param_name, FLATTENED_AUTHOR_PARAMETERS)) {
      return false;
    }

    if (substr($param_name, -4) > 0 || substr($param_name, -3) > 0 || substr($param_name, -2) > 30) {
      // Stop at 30 authors - or page codes will become cluttered! 
      if ($this->get('last29') || $this->get('author29') || $this->get('surname29')) $this->add_if_new('display-authors', 29);
      return false;
    }

    $auNo = preg_match('~\d+$~', $param_name, $auNo) ? $auNo[0] : null;        

    switch ($param_name) {
      case "editor": case "editor-last": case "editor-first":
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);
        if ($this->blank('editor') && $this->blank("editor-last") && $this->blank("editor-first")) {
          return $this->add($param_name, sanitize_string($value));
        } else {
          return false;
        }
      case 'editor4': case 'editor4-last': case 'editor4-first':
        $this->add_if_new('displayeditors', 29);
        return $this->add($param_name, sanitize_string($value));
      break;
      case "author": case "author1": case "last1": case "last": case "authors":
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);
        $value = straighten_quotes($value);

        if ($this->blank("last1") && $this->blank("last") && $this->blank("author") && $this->blank("author1")) {
          if (strpos($value, ',')) {
            $au = explode(',', $value);
            $this->add('last' . (substr($param_name, -1) == '1' ? '1' : ''), sanitize_string(formatSurname($au[0])));
            return $this->add('first' . (substr($param_name, -1) == '1' ? '1' : ''), sanitize_string(formatForename(trim($au[1]))));
          } else {
            return $this->add($param_name,sanitize_string($value));
          }
        }
      return false;
      case "first": case "first1":
       $value = straighten_quotes($value);
       if ($this->blank("first") && $this->blank("first1") && $this->blank("author") && $this->blank('author1'))
          return $this->add($param_name, sanitize_string($value));
      return false;
      case "coauthor":
        echo "\n ! The \"coauthor\" parameter is deprecated. Please replace manually.";
      return false;
      case "coauthors"://FIXME: this should convert "coauthors" to "authors" maybe, if "authors" doesn't exist.
        $value = straighten_quotes($value);
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);

        if ($this->blank("last2") && $this->blank("coauthor") && $this->blank("coauthors") && $this->blank("author"))
          return $this->add($param_name,sanitize_string($value));
          // Note; we shouldn't be using this parameter ever....
      return false;
      case "last2": case "last3": case "last4": case "last5": case "last6": case "last7": case "last8": case "last9":
      case "last10": case "last20": case "last30": case "last40": case "last50": case "last60": case "last70": case "last80": case "last90":
      case "last11": case "last21": case "last31": case "last41": case "last51": case "last61": case "last71": case "last81": case "last91":
      case "last12": case "last22": case "last32": case "last42": case "last52": case "last62": case "last72": case "last82": case "last92":
      case "last13": case "last23": case "last33": case "last43": case "last53": case "last63": case "last73": case "last83": case "last93":
      case "last14": case "last24": case "last34": case "last44": case "last54": case "last64": case "last74": case "last84": case "last94":
      case "last15": case "last25": case "last35": case "last45": case "last55": case "last65": case "last75": case "last85": case "last95":
      case "last16": case "last26": case "last36": case "last46": case "last56": case "last66": case "last76": case "last86": case "last96":
      case "last17": case "last27": case "last37": case "last47": case "last57": case "last67": case "last77": case "last87": case "last97":
      case "last18": case "last28": case "last38": case "last48": case "last58": case "last68": case "last78": case "last88": case "last98":
      case "last19": case "last29": case "last39": case "last49": case "last59": case "last69": case "last79": case "last89": case "last99":
      case "author2": case "author3": case "author4": case "author5": case "author6": case "author7": case "author8": case "author9":
      case "author10": case "author20": case "author30": case "author40": case "author50": case "author60": case "author70": case "author80": case "author90":
      case "author11": case "author21": case "author31": case "author41": case "author51": case "author61": case "author71": case "author81": case "author91":
      case "author12": case "author22": case "author32": case "author42": case "author52": case "author62": case "author72": case "author82": case "author92":
      case "author13": case "author23": case "author33": case "author43": case "author53": case "author63": case "author73": case "author83": case "author93":
      case "author14": case "author24": case "author34": case "author44": case "author54": case "author64": case "author74": case "author84": case "author94":
      case "author15": case "author25": case "author35": case "author45": case "author55": case "author65": case "author75": case "author85": case "author95":
      case "author16": case "author26": case "author36": case "author46": case "author56": case "author66": case "author76": case "author86": case "author96":
      case "author17": case "author27": case "author37": case "author47": case "author57": case "author67": case "author77": case "author87": case "author97":
      case "author18": case "author28": case "author38": case "author48": case "author58": case "author68": case "author78": case "author88": case "author98":
      case "author19": case "author29": case "author39": case "author49": case "author59": case "author69": case "author79": case "author89": case "author99":
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);
        $value = straighten_quotes($value);

        if ($this->blank("last$auNo") && $this->blank("author$auNo")
          && $this->blank("coauthor") && $this->blank("coauthors")
          && strpos($this->get('author') . $this->get('authors'), ' and ') === FALSE
          && strpos($this->get('author') . $this->get('authors'), '; ') === FALSE
          && strpos($this->get('author') . $this->get('authors'), ' et al') === FALSE
        ) {
          if (strpos($value, ',') && substr($param_name, 0, 3) == 'aut') {
            $au = explode(',', $value);
            $this->add('last' . $auNo, formatSurname($au[0]));
            return $this->add_if_new('first' . $auNo, formatForename(trim($au[1])));
          } else {
            return $this->add($param_name,sanitize_string($value));
          }
        }
        return false;
      case "first2": case "first3": case "first4": case "first5": case "first6": case "first7": case "first8": case "first9":
      case "first10": case "first11": case "first12": case "first13": case "first14": case "first15": case "first16": case "first17": case "first18": case "first19":
      case "first20": case "first21": case "first22": case "first23": case "first24": case "first25": case "first26": case "first27": case "first28": case "first29":
      case "first30": case "first31": case "first32": case "first33": case "first34": case "first35": case "first36": case "first37": case "first38": case "first39":
      case "first40": case "first41": case "first42": case "first43": case "first44": case "first45": case "first46": case "first47": case "first48": case "first49":
      case "first50": case "first51": case "first52": case "first53": case "first54": case "first55": case "first56": case "first57": case "first58": case "first59":
      case "first60": case "first61": case "first62": case "first63": case "first64": case "first65": case "first66": case "first67": case "first68": case "first69":
      case "first70": case "first71": case "first72": case "first73": case "first74": case "first75": case "first76": case "first77": case "first78": case "first79":
      case "first80": case "first81": case "first82": case "first83": case "first84": case "first85": case "first86": case "first87": case "first88": case "first89":
      case "first90": case "first91": case "first92": case "first93": case "first94": case "first95": case "first96": case "first97": case "first98": case "first99":
        $value = straighten_quotes($value);

        if ($this->blank($param_name)
                && under_two_authors($this->get('author')) && $this->blank("author" . $auNo)
                && $this->blank("coauthor") && $this->blank("coauthors")) {
          return $this->add($param_name,sanitize_string($value));
        }
        return false;
      case "date":
        if (preg_match("~^\d{4}$~", sanitize_string($value))) {
          // Not adding any date data beyond the year, so 'year' parameter is more suitable
          $param_name = "year";
        }
      // Don't break here; we want to go straight in to year;
      case "year":
        if (   ($this->blank("date") || in_array(trim(strtolower($this->get('date'))), IN_PRESS_ALIASES))
            && ($this->blank("year") || in_array(trim(strtolower($this->get('year'))), IN_PRESS_ALIASES))
          ) {
          if ($param_name != 'date') $this->forget('date'); // Delete any "in press" dates.
          if ($param_name != 'year') $this->forget('year'); // We only unset the other one so that parameters stay in order as much as possible
          return $this->add($param_name, $value);
        }
        return false;
      case "issn":
        if ($this->blank("journal") && $this->blank("periodical") && $this->blank("work")) {
          // Only add ISSN if journal is unspecified
          return $this->add($param_name, $value);
        }
        return false;
      case "periodical": case "journal":
        if ($this->blank("journal") && $this->blank("periodical") && $this->blank("work")) {
          if (sanitize_string($value) == "ZooKeys" ) $this->blank("volume") ; // No volumes, just issues.
          if (strcasecmp( (string) $value, "unknown") == 0 ) return false;
          return $this->add($param_name, format_title_text(title_case($value)));
        }
        return false;
      case 'chapter': case 'contribution':
        if ($this->blank("chapter") && $this->blank("contribution")) {
          return $this->add($param_name, format_title_text($value));
        }
        return false;
      case "page": case "pages":
        if (( $this->blank("pages") && $this->blank("page") && $this->blank("pp")  && $this->blank("p"))
                || strpos(strtolower($this->get('pages') . $this->get('page')), 'no') !== FALSE
                || (strpos($value, chr(2013)) || (strpos($value, '-'))
                  && !strpos($this->get('pages'), chr(2013))
                  && !strpos($this->get('pages'), chr(150)) // Also en-dash
                  && !strpos($this->get('pages'), chr(226)) // Also en-dash
                  && !strpos($this->get('pages'), '-')
                  && !strpos($this->get('pages'), '&ndash;'))
        ) return $this->add($param_name, sanitize_string($value));
        return false;
      case 'title':
        if ($this->blank($param_name)) {
          return $this->format_title(sanitize_string($value));
        }
        return false;
      case 'class':
        if ($this->blank($param_name) && strpos($this->get('eprint'), '/') === FALSE ) {
          return $this->add($param_name, sanitize_string($value));
        }
        return false;
      case 'doi':
        if ($this->blank($param_name) &&  preg_match('~(10\..+)$~', $value, $match)) {
          $this->add('doi', $match[0]);
          $this->verify_doi();
          $this->expand_by_doi();
          return true;
        }
        return false;
      case 'display-authors': case 'displayauthors':
        if ($this->blank('display-authors') && $this->blank('displayauthors')) {
          return $this->add($param_name, $value);
        }
      return false;
      case 'display-editors': case 'displayeditors':
        if ($this->blank('display-editors') && $this->blank('displayeditors')) {
          return $this->add($param_name, $value);
        }
      return false;
      case 'doi-broken-date':
        if ($this->blank('doi_brokendate') &&
            $this->blank('doi-broken-date') &&
            $this->blank('doi_inactivedate') &&
            $this->blank('doi-inactive-date')) {
          return $this->add($param_name, $value);
        }
      return false;
      case 'pmid':
        if ($this->blank($param_name)) {
          $this->add($param_name, sanitize_string($value));
          $this->expand_by_pubmed();
          if ($this->blank('doi')) {
            $this->get_doi_from_crossref();
          }
          return true;
        }
      return false;
      case 'author_separator': case 'author-separator':
        echo "\n ! 'author-separator' is deprecated.";
        if(!trim($value)) {
          $this->forget($param_name);
        } else {
          echo " Please fix manually.";
        }
      return false;
      case 'postscript':
        if ($this->blank($param_name)) {
          return $this->add($param_name, $value);
        }
      return false;
      case 'issue':
        if ($this->blank("issue") && $this->blank("number")) {        
          return $this->add($param_name, $value);
        } 
      return false;
      case 'volume':
        if ($this->blank($param_name)) {
          if ($this->get('journal') == "ZooKeys" ) {
            // This journal has no volume.  This is really the issue number
            return $this->add_if_new('issue', $value);
          } else {
            return $this->add($param_name, $value);
          }
        }
      return false;
      case 'bibcode':
        if ($this->blank($param_name)) { 
          $bibcode_pad = 19 - strlen($value);
          if ($bibcode_pad > 0) {  // Paranoid, don't want a negative value, if bibcodes get longer
            $value = $value . str_repeat( ".", $bibcode_pad);  // Add back on trailing periods
          }
          return $this->add($param_name, $value);
        } 
      return false;
      default:
        if ($this->blank($param_name)) {
          return $this->add($param_name, sanitize_string($value));
        }
    }
  }

  protected function get_identifiers_from_url() {
    if ($this->blank('url') && $this->has('website')) {  // No URL, but a website
      $url = trim($this->get('website'));
      if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
        $url = "h" . $url;
      }
      if (strtolower(substr( $url, 0, 4 )) !== "http" ) {
        $url = "http://" . $url; // Try it with http
      }
      if (filter_var($url, FILTER_VALIDATE_URL,FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_PATH_REQUIRED ) === FALSE ) return ; // PHP does not like it
      $pattern = '_^(?:(?:https?|ftp)://)(?:\\S+(?::\\S*)?@)?(?:(?!10(?:\\.\\d{1,3}){3})(?!127(?:\\.\\d{1,3}){3})(?!169\\.254(?:\\.\\d{1,3}){2})(?!192\\.168(?:\\.\\d{1,3}){2})(?!172\\.(?:1[6-9]|2\\d|3[0-1])(?:\\.\\d{1,3}){2})(?:[1-9]\\d?|1\\d\\d|2[01]\\d|22[0-3])(?:\\.(?:1?\\d{1,2}|2[0-4]\\d|25[0-5])){2}(?:\\.(?:[1-9]\\d?|1\\d\\d|2[0-4]\\d|25[0-4]))|(?:(?:[a-z\\x{00a1}-\\x{ffff}0-9]+-?)*[a-z\\x{00a1}-\\x{ffff}0-9]+)(?:\\.(?:[a-z\\x{00a1}-\\x{ffff}0-9]+-?)*[a-z\\x{00a1}-\\x{ffff}0-9]+)*(?:\\.(?:[a-z\\x{00a1}-\\x{ffff}]{2,})))(?::\\d{2,5})?(?:/[^\\s]*)?$_iuS';
      if (preg_match ($pattern, $url) !== 1) return ;  // See https://mathiasbynens.be/demo/url-regex/  This regex is more exact than validator.  We only spend time on this after quick and dirty check is passed
      $this->rename('website', 'url'); // Rename it first, so that parameters stay in same order
      $this->set('url',$url);
      quiet_echo("\n   ~ website is actually HTTP URL; converting to use url parameter.");
    }
    $url = $this->get('url');
    if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
      $url = "h" . $url;
      $this->set('url',$url); // Save it
    }
    // JSTOR
    if (strpos($url, "jstor.org") !== FALSE) {
      if (strpos($url, "sici")) {
        #Skip.  We can't do anything more with the SICI, unfortunately.
      } elseif (strpos($url, "plants.jstor.org")) {
        #Skip.  We can't do anything more with the plants, unfortunately.
      } elseif (preg_match("~(?|(\d{6,})$|(\d{6,})[^\d%\-])~", $url, $match)) {
        if ($this->get('jstor')) {
          $this->forget('url');
        } else {
          $this->forget('url');
          $this->set("jstor", urldecode($match[1]));
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
      }
    } else {
      if (preg_match(BIBCODE_REGEXP, urldecode($url), $bibcode)) {
        if ($this->blank('bibcode')) {
          $this->forget('url');
          $this->set("bibcode", urldecode($bibcode[1]));
        }
      } elseif (preg_match("~^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                      . "|^http://www\.ncbi\.nlm\.nih\.gov/pmc/articles/PMC(\d+)~", $url, $match)) {
        if ($this->blank('pmc')) {
          $this->forget('url');
          $this->set("pmc", $match[1] . $match[2]);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
      } else if (preg_match("~^https?://d?x?\.?doi\.org/([^\?]*)~", $url, $match)) {
        quiet_echo("\n   ~ URL is hard-coded DOI; converting to use DOI parameter.");
        if ($this->blank('doi')) {
          $this->set("doi", urldecode($match[1]));
          $this->expand_by_doi(1);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
      } elseif (preg_match("~10\.\d{4}/[^&\s\|\?]*~", $url, $match)) {
        quiet_echo("\n   ~ Recognized DOI in URL; dropping URL");
        if ($this->blank('doi')) {
          $this->set('doi', preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]));
          $this->expand_by_doi(1);
        }
      } elseif (preg_match("~\barxiv.org/(?:pdf|abs)/(.+)$~", $url, $match)) {
        //ARXIV
        $match[1] = str_replace ( ".pdf" , "" , $match[1] ); // Catch PDFs
        $this->add_if_new("arxiv", $match[1]);
        if (strpos($this->name, 'web')) $this->name = 'Cite arxiv';
      } else if (preg_match("~https?://www.ncbi.nlm.nih.gov/pubmed/.*?=?(\d{6,})~", $url, $match)) {
        if ($this->blank('pmid')) {
          $this->set('pmid', $match[1]);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
      } else if (preg_match("~^https?://www\.amazon(?P<domain>\.[\w\.]{1,7})/.*dp/(?P<id>\d+X?)~", $url, $match)) {
        if ($match['domain'] == ".com") {
          if ($this->get('asin')) {
            $this->forget('url');
          } else {
            $this->forget('url');
            $this->set('asin', $match['id']);
          }
        } else {
          $this->set('id', $this->get('id') . " {{ASIN|{$match['id']}|country=" . str_replace(array(".co.", ".com.", "."), "", $match['domain']) . "}}");
          $this->forget('url');
          $this->forget('accessdate');
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite book';
      }
    }
  }

  protected function get_doi_from_text() {
    if ($this->blank('doi') && preg_match('~10\.\d{4}/[^&\s\|\}\{]*~', urldecode($this->parsed_text()), $match))
      // Search the entire citation text for anything in a DOI format.
      // This is quite a broad match, so we need to ensure that no baggage has been tagged on to the end of the URL.
      $this->add_if_new('doi', preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]));
  }

  protected function get_doi_from_crossref() { #TODO test
    if ($doi = $this->get('doi')) {
      return $doi;
    }
    echo "\n - Checking CrossRef database for doi. " . tag();
    $title = $this->get('title');
    $journal = $this->get('journal');
    $author = $this->first_author();
    $year = $this->get('year');
    $volume = $this->get('volume');
    $page_range = $this->page_range();
    $start_page = isset($page_range[1]) ? $page_range[1] : null;
    $end_page   = isset($page_range[2]) ? $page_range[2] : null;
    $issn = $this->get('issn');
    $url1 = trim($this->get('url'));
    $input = array($title, $journal, $author, $year, $volume, $start_page, $end_page, $issn, $url1);
    global $priorP;
    if ($input == $priorP['crossref']) {
      echo "\n   * Data not changed since last CrossRef search." . tag();
      return false;
    } else {
      $priorP['crossref'] = $input;
      global $crossRefId;
      if ($journal || $issn) {
        $url = "http://www.crossref.org/openurl/?noredirect=true&pid=$crossRefId"
             . ($title ? "&atitle=" . urlencode(deWikify($title)) : "")
             . ($author ? "&aulast=" . urlencode($author) : '')
             . ($start_page ? "&spage=" . urlencode($start_page) : '')
             . ($end_page > $start_page ? "&epage=" . urlencode($end_page) : '')
             . ($year ? "&date=" . urlencode(preg_replace("~([12]\d{3}).*~", "$1", $year)) : '')
             . ($volume ? "&volume=" . urlencode($volume) : '')
             . ($issn ? "&issn=$issn" : ($journal ? "&title=" . urlencode(deWikify($journal)) : ''));
        if (!($result = @simplexml_load_file($url)->query_result->body->query)){
          echo "\n   * Error loading simpleXML file from CrossRef.";
        }
        else if ($result['status'] == 'malformed') {
          echo "\n   * Cannot search CrossRef: " . htmlspecialchars($result->msg);
        }
        else if ($result["status"] == "resolved") {
          return $result;
        }
      }
      if (FAST_MODE || !$author || !($journal || $issn) || !$start_page ) return;
      // If fail, try again with fewer constraints...
      echo "\n   x Full search failed. Dropping author & end_page... ";
      $url = "http://www.crossref.org/openurl/?noredirect=true&pid=$crossRefId";
      if ($title) $url .= "&atitle=" . urlencode(deWikify($title));
      if ($issn) $url .= "&issn=$issn"; elseif ($journal) $url .= "&title=" . urlencode(deWikify($journal));
      if ($year) $url .= "&date=" . urlencode($year);
      if ($volume) $url .= "&volume=" . urlencode($volume);
      if ($start_page) $url .= "&spage=" . urlencode($start_page);
      if (!($result = @simplexml_load_file($url)->query_result->body->query)) {
        echo "\n   * Error loading simpleXML file from CrossRef." . tag();
      }
      else if ($result['status'] == 'malformed') {
        echo "\n   * Cannot search CrossRef: " . htmlspecialchars($result->msg);
      } else if ($result["status"]=="resolved") {
        echo " Successful!";
        return $result;
      }
    }
  }

  protected function find_pmid() {
    echo "\n - Searching PubMed... " . tag();
    $results = ($this->query_pubmed());
    if ($results[1] == 1) {
      $this->add_if_new('pmid', $results[0]);
    } else {
      echo " nothing found.";
      if (mb_strtolower($this->name) == "citation" && $this->blank('journal')) {
        // Check for ISBN, but only if it's a citation.  We should not risk a false positive by searching for an ISBN for a journal article!
        echo "\n - Checking for ISBN";
        if ($this->blank('isbn') && $title = $this->get("title")) $this->set("isbn", findISBN( $title, $this->first_author()));
        else echo "\n  Already has an ISBN. ";
      }
    }
  }

  protected function query_pubmed() {
/* 
 *
 * Performs a search based on article data, using the DOI preferentially, and failing that, the rest of the article details.
 * Returns an array:
 *   [0] => PMID of first matching result
 *   [1] => total number of results
 *
 */
    if ($doi = $this->get('doi')) {
      $results = $this->do_pumbed_query(array("doi"), true);
      if ($results[1] == 1) return $results;
    }
    // If we've got this far, the DOI was unproductive or there was no DOI.

    if ($this->has("journal") && $this->has("volume") && $this->has("pages")) {
      $results = $this->do_pumbed_query(array("journal", "volume", "issue", "pages"));
      if ($results[1] == 1) return $results;
    }
    if ($this->has("title") && ($this->has("author") || $this->has("author") || $this->has("author1") || $this->has("author1"))) {
      $results = $this->do_pumbed_query(array("title", "author", "author", "author1", "author1"));
      if ($results[1] == 1) return $results;
      if ($results[1] > 1) {
        $results = $this->do_pumbed_query(array("title", "author", "author", "author1", "author1", "year", "date"));
        if ($results[1] == 1) return $results;
        if ($results[1] > 1) {
          $results = $this->do_pumbed_query(array("title", "author", "author", "author1", "author1", "year", "date", "volume", "issue"));
          if ($results[1] == 1) return $results;
        }
      }
    }
  }

  protected function do_pumbed_query($terms, $check_for_errors = false) {
  /* do_query
   *
   * Searches pubmed based on terms provided in an array.
   * Provide an array of wikipedia parameters which exist in $p, and this function will construct a Pubmed seach query and
   * return the results as array (first result, # of results)
   * If $check_for_errors is true, it will return 'fasle' on errors returned by pubmed
   */
    $query = '';
    foreach ($terms as $term) {
      $key_index = array(
        'doi' =>  'AID',
        'author1' =>  'Author',
        'author' =>  'Author',
        'issue' =>  'Issue',
        'journal' =>  'Journal',
        'pages' =>  'Pagination',
        'page' =>  'Pagination',
        'date' =>  'Publication Date',
        'year' =>  'Publication Date',
        'title' =>  'Title',
        'pmid' =>  'PMID',
        'volume' =>  'Volume',
        ##Text Words [TW] , Title/Abstract [TIAB]
          ## Formatting: YYY/MM/DD Publication Date [DP]
      );
      $key = $key_index[mb_strtolower($term)];
      if ($key && $term && $val = $this->get($term)) {
        $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
      }
    }
    $query = substr($query, 5);
    $url = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&tool=DOIbot&email=martins+pubmed@gmail.com&term=$query";
    $xml = simplexml_load_file($url);
    if ($check_for_errors && $xml->ErrorList) {
      echo $xml->ErrorList->PhraseNotFound
              ? " no results."
              : "\n - Errors detected in PMID search (" . htmlspecialchars(print_r($xml->ErrorList, 1)) . "); abandoned.";
      return array(null, 0);
    }

    return $xml?array((string)$xml->IdList->Id[0], (string)$xml->Count):array(null, 0);// first results; number of results
  }

  ### Obtain data from external database
  public function expand_by_arxiv() {
    if ($this->wikiname() == 'cite arxiv') {
      $arxiv_param = 'eprint';
      $this->rename('arxiv', 'eprint');
    } else {
      $arxiv_param = 'arxiv';
      $this->rename('eprint', 'arxiv');
    }
    $class = $this->get('class');
    $eprint = str_ireplace("arXiv:", "", $this->get('eprint') . $this->get('arxiv'));
    //if ($class && substr($eprint, 0, strlen($class) + 1) == $class . '/')
    //  $eprint = substr($eprint, strlen($class) + 1);
    $this->set($arxiv_param, $eprint);

    if ($eprint) {
      echo "\n * Getting data from arXiv " . htmlspecialchars($eprint);
      $xml = simplexml_load_string(
        preg_replace("~(</?)(\w+):([^>]*>)~", "$1$2$3", file_get_contents("http://export.arxiv.org/api/query?start=0&max_results=1&id_list=$eprint"))
      );
    }
    if ($xml) {
      foreach ($xml->entry->author as $auth) {
        $i++;
        $name = $auth->name;
        if (preg_match("~(.+\.)(.+?)$~", $name, $names) || preg_match('~^\s*(\S+) (\S+)\s*$~', $name, $names)) {
          $this->add_if_new("last$i", $names[2]);
          $this->add_if_new("first$i", $names[1]);
        } else {
          $this->add_if_new("author$i", $name);
        }
      }
      $this->add_if_new("title", format_title_text((string) $xml->entry->title));
      $this->add_if_new("class", (string) $xml->entry->category["term"]);
      $this->add_if_new("author", substr($authors, 2));
      $this->add_if_new("year", substr($xml->entry->published, 0, 4));
      $this->add_if_new("doi", (string) $xml->entry->arxivdoi);

      if ($xml->entry->arxivjournal_ref) {
        $journal_data = (string) $xml->entry->arxivjournal_ref;
        if (preg_match("~,(\(?([12]\d{3})\)?).*?$~u", $journal_data, $match)) {
          $journal_data = str_replace($match[1], "", $journal_data);
          $this->add_if_new("year", $match[1]);
        }
        if (preg_match("~\w?\d+-\w?\d+~", $journal_data, $match)) {
          $journal_data = str_replace($match[0], "", $journal_data);
          $this->add_if_new("pages", str_replace("--", EN_DASH, $match[0]));
        }
        if (preg_match("~(\d+)(?:\D+(\d+))?~", $journal_data, $match)) {
          $this->add_if_new("volume", $match[1]);
          $this->add_if_new("issue", $match[2]);
          $journal_data = preg_replace("~[\s:,;]*$~", "",
                  str_replace(array($match[1], $match[2]), "", $journal_data));
        }
        if ( strcasecmp( (string) $journal_data, "unknown") !=0 ) $this->add_if_new("journal", format_title_text($journal_data));
      } else {
        $this->add_if_new("year", date("Y", strtotime((string)$xml->entry->published)));
      }
      return true;
    }
    return false;
  }

  public function expand_by_adsabs() {
    if (SLOW_MODE || $this->has('bibcode')) {
      echo "\n - Checking AdsAbs database";
      $url_root = "http://adsabs.harvard.edu/cgi-bin/abs_connect?data_type=XML&";
      if ($bibcode = $this->get("bibcode")) {
        $xml = simplexml_load_file($url_root . "bibcode=" . urlencode($bibcode));
      } elseif ($doi = $this->get('doi')) {
        $xml = simplexml_load_file($url_root . "doi=" . urlencode($doi));
      } elseif ($title = $this->get("title")) {
        $xml = simplexml_load_file($url_root . "title=" . urlencode('"' . $title . '"'));
        $inTitle = str_replace(array(" ", "\n", "\r"), "", (mb_strtolower($xml->record->title)));
        $dbTitle = str_replace(array(" ", "\n", "\r"), "", (mb_strtolower($title)));
        if (
             (strlen($inTitle) > 254 || strlen(dbTitle) > 254)
                ? strlen($inTitle) != strlen($dbTitle) || similar_text($inTitle, $dbTitle)/strlen($inTitle) < 0.98
                : levenshtein($inTitle, $dbTitle) > 3
            ) {
          echo "\n   Similar title not found in database";
          return false;
        }
      }
      if ($xml["retrieved"] != 1 && $journal = $this->get('journal')) {
        // try partial search using bibcode components:
        $xml = simplexml_load_file($url_root
                . "year=" . $this->get('year')
                . "&volume=" . $this->get('volume')
                . "&page=" . ($pages = $this->get('pages') ? $pages : $this->get('page'))
                );
        $journal_string = explode(",", (string) $xml->record->journal);
        $journal_fuzzyer = "~\bof\b|\bthe\b|\ba\beedings\b|\W~";
        if (strpos(mb_strtolower(preg_replace($journal_fuzzyer, "", $journal)),
                mb_strtolower(preg_replace($journal_fuzzyer, "", $journal_string[0]))) === FALSE) {
          echo "\n   Match for pagination but database journal \"" .
            htmlspecialchars($journal_string[0]) . "\" didn't match \"journal = " .
            htmlspecialchars($journal) . "\"." . tag();
          return false;
        }
      }
      if ($xml["retrieved"] == 1) {
        echo tag();
        $this->add_if_new("bibcode", (string) $xml->record->bibcode);
        if (strcasecmp( (string) $xml->record->title, "unknown") != 0) {  // Returns zero if the same.  Bibcode titles as sometimes "unknown"
            $this->add_if_new("title", format_title_text( (string) $xml->record->title));
        }
        $i = null;
        foreach ($xml->record->author as $author) {
          $this->add_if_new("author" . ++$i, $author);
        }
        $journal_string = explode(",", (string) $xml->record->journal);
        $journal_start = mb_strtolower($journal_string[0]);
        $this->add_if_new("volume", (string) $xml->record->volume);
        $this->add_if_new("issue", (string) $xml->record->issue);
        $this->add_if_new("year", preg_replace("~\D~", "", (string) $xml->record->pubdate));
        $this->add_if_new("pages", (string) $xml->record->page);
        if (preg_match("~\bthesis\b~ui", $journal_start)) {}
        elseif (substr($journal_start, 0, 6) == "eprint") {
          if (substr($journal_start, 7, 6) == "arxiv:") {
            if ($this->add_if_new("arxiv", substr($journal_start, 13))) $this->expand_by_arxiv();
          } else {
            $this->append_to('id', ' ' . substr($journal_start, 13));
          }
        } else {
          if (strcasecmp($journal_string[0], "unknown") != 0) $this->add_if_new('journal', format_title_text($journal_string[0])); // Bibcodes titles are sometimes unknown
        }
        if ($this->add_if_new('doi', (string) $xml->record->DOI)) {
          $this->expand_by_doi();
        }
        return true;
      } else {
        echo ": no record retrieved." . tag();
        return false;
      }
    } else {
       echo "\n - Skipping AdsAbs database: not in slow mode" . tag();
       return false;
    }
  }

  public function expand_by_doi($force = FALSE) {
    $doi = $this->get_without_comments('doi');
    if ($doi && ($force || $this->incomplete())) {
      if (preg_match('~^10\.2307/(\d+)$~', $doi)) {
        $this->add_if_new('jstor', substr($doi, 8));
      }
      $crossRef = $this->query_crossref($doi);
      if ($crossRef) {
        echo "\n - Expanding from crossRef record" . tag();

        if ($crossRef->volume_title && $this->blank('journal')) {
          $this->add_if_new('chapter', format_title_text($crossRef->article_title));
          if (strtolower($this->get('title')) == strtolower($crossRef->article_title)) {
            $this->forget('title');
          }
          $this->add_if_new('title',  format_title_text($crossRef->volume_title));
        } else {
          $this->add_if_new('title',  format_title_text($crossRef->article_title));
        }
        $this->add_if_new('series',  format_title_text($crossRef->series_title));
        $this->add_if_new("year", $crossRef->year);
        if ($this->blank(array('editor', 'editor1', 'editor-last', 'editor1-last')) && $crossRef->contributors->contributor) {
          $au_i = 0;
          $ed_i = 0;
          foreach ($crossRef->contributors->contributor as $author) {
            if ($author["contributor_role"] == 'editor') {
              ++$ed_i;
              if ($ed_i < 31 && $crossRef->journal_title === NULL) {
                $this->add_if_new("editor$ed_i-last", formatSurname($author->surname));
                $this->add_if_new("editor$ed_i-first", formatForename($author->given_name));
              }
            } elseif ($author['contributor_role'] == 'author') {
              ++$au_i;
              $this->add_if_new("last$au_i", formatSurname($author->surname));
              $this->add_if_new("first$au_i", formatForename($author->given_name));
            }
          }
        }
        $this->add_if_new('isbn', $crossRef->isbn);
        $this->add_if_new('journal',  format_title_text($crossRef->journal_title));
        if ($crossRef->volume > 0) $this->add_if_new('volume', $crossRef->volume);
        if ((integer) $crossRef->issue > 1) {
        // "1" may refer to a journal without issue numbers,
        //  e.g. 10.1146/annurev.fl.23.010191.001111, as well as a genuine issue 1.  Best ignore.
          $this->add_if_new('issue', $crossRef->issue);
        }
        if ($this->blank("page")) {
          if ($crossRef->last_page && ($crossRef->first_page != $crossRef->last_page)) {
            $this->add_if_new("pages", $crossRef->first_page . "-" . $crossRef->last_page); //replaced by an endash later in script
          } else {
            $this->add_if_new("pages", $crossRef->first_page);
          }
        }
        echo " (ok)";
      } else {
        echo "\n - No CrossRef record found for doi '" . htmlspecialchars($doi) ."'; marking as broken";
        $url_test = "http://dx.doi.org/".$doi ;
        $headers_test = get_headers($url_test, 1);
        if(empty($headers_test['Location']))
                $this->add_if_new('doi-broken-date', date('Y-m-d'));  // Only mark as broken if dx.doi.org also fails to resolve
      }
    }
  }

  public function expand_by_pubmed($force = FALSE) {
    if (!$force && !$this->incomplete()) return;
    if ($pm = $this->get('pmid')) $identifier = 'pmid';
    else if ($pm = $this->get('pmc')) $identifier = 'pmc';
    else return false;
    if (html_output) {
      echo "\n - Checking " . '<a href="https://www.ncbi.nlm.nih.gov/pubmed/' .
        urlencode($pm) . '" target="_blank">' .
        htmlspecialchars(strtoupper($identifier) . ' ' . $pm) . "</a> for more details" .
        tag();
    } else {
      echo "\n - Checking " . htmlspecialchars(strtoupper($identifier) . ' ' . $pm)
        . ' for more details' . tag();
    }
    $xml = simplexml_load_file("http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?tool=DOIbot&email=martins@gmail.com&db=" . (($identifier == "pmid")?"pubmed":"pmc") . "&id=" . urlencode($pm));
    // Debugging URL : view-source:http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&tool=DOIbot&email=martins@gmail.com&id=
    if (count($xml->DocSum->Item) > 0) foreach($xml->DocSum->Item as $item) {
      if (preg_match("~10\.\d{4}/[^\s\"']*~", $item, $match)) {
        $this->add_if_new('doi', $match[0]);
      }
      switch ($item["Name"]) {
                case "Title":   $this->add_if_new('title',  format_title_text(str_replace(array("[", "]"), "",(string) $item)));
        break;  case "PubDate": preg_match("~(\d+)\s*(\w*)~", $item, $match);
                                $this->add_if_new('year', (string) $match[1]);
        break;  case "FullJournalName": $this->add_if_new('journal',  format_title_text(ucwords((string) $item)));
        break;  case "Volume":  $this->add_if_new('volume', (string) $item);
        break;  case "Issue":   $this->add_if_new('issue', (string) $item);
        break;  case "Pages":   $this->add_if_new('pages', (string) $item);
        break;  case "PmId":    $this->add_if_new('pmid', (string) $item);
        break;  case "AuthorList":
          $i = 0;
          foreach ($item->Item as $subItem) {
            $i++;
            if (authorIsHuman((string) $subItem)) {
              $jr_test = jrTest($subItem);
              $subItem = $jr_test[0];
              $junior = $jr_test[1];
              if (preg_match("~(.*) (\w+)$~", $subItem, $names)) {
                $first = trim(preg_replace('~(?<=[A-Z])([A-Z])~', ". $1", $names[2]));
                if (strpos($first, '.') && substr($first, -1) != '.') {
                  $first = $first . '.';
                }
                $this->add_if_new("author$i", $names[1] . $junior . ',' . $first);
              }
            } else {
              // We probably have a committee or similar.  Just use 'author$i'.
              $this->add_if_new("author$i", (string) $subItem);
            }
          }
        break; case "LangList": case 'ISSN':
        break; case "ArticleIds":
          foreach ($item->Item as $subItem) {
            switch ($subItem["Name"]) {
              case "pubmed":
                  preg_match("~\d+~", (string) $subItem, $match);
                  if ($this->add_if_new("pmid", $match[0])) $this->expand_by_pubmed();
                  break; ### TODO PLACEHOLDER YOU ARE HERE CONTINUATION POINT ###
              case "pmc":
                preg_match("~\d+~", (string) $subItem, $match);
                $this->add_if_new('pmc', $match[0]);
                break;
              case "doi": case "pii":
              default:
                if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $subItem, $match)) {
                  if ($this->add_if_new('doi', $match[0])) {
                    $this->expand_by_doi();
                  }
                }
                break;
            }
          }
        break;
      }
    }
    if ($xml && $this->blank('doi')) $this->get_doi_from_crossref();
  }

  protected function use_sici() {
    if (preg_match(SICI_REGEXP, urldecode($this->parsed_text()), $sici)) {
      $this->add_if_new("issn", $sici[1]); // Check whether journal is set in add_if_new
      //if ($this->blank ("year") && $this->blank("month") && $sici[3]) $this->set("month", date("M", mktime(0, 0, 0, $sici[3], 1, 2005)));
      $this->add_if_new("year", $sici[2]);
      //if ($this->blank("day") && is("month") && $sici[4]) set ("day", $sici[4]);
      $this->add_if_new("volume", 1*$sici[5]);
      if ($sici[6]) $this->add_if_new("issue", 1*$sici[6]);
      $this->add_if_new("pages", 1*$sici[7]);
      return true;
    } else return false;
  }

  protected function query_crossref($doi = FALSE) {
    global $crossRefId;
    if (!$doi) {
      $doi = $this->get('doi');
    }
    if (!$doi) {
      warn('#TODO: crossref lookup with no doi');
    }
    $url = "http://www.crossref.org/openurl/?pid=$crossRefId&id=doi:$doi&noredirect=true";
    $xml = @simplexml_load_file($url);
    if ($xml) {
      $result = $xml->query_result->body->query;
      if ($result["status"] == "resolved") {
        return $result;
      } else {
        return false;
      }
    } else {
       echo "\n   ! Error loading CrossRef file from DOI " . htmlspecialchars($doi) ."!";
       return false;
    }
  }

  protected function get_open_access_url() {
    $doi = $this->get('doi');
    if (!$doi || $this->get('url')) return;
    $url = "https://api.oadoi.org/v2/$doi?email=" . CROSSREFUSERNAME;
    $json = @file_get_contents($url);
    if ($json) {
      $oa = json_decode($json);
      if (isset($oa->best_oa_location)) {
        $best_location = $oa->best_oa_location;
        if ($best_location->host_type == 'publisher') {
          // The best location is already linked to by the doi link
          return true;
        }
        $this->add('url', $best_location->url);
        switch ($best_location->version) {
            case 'acceptedVersion': $format = 'Accepted manuscript'; break;
            case 'submittedVersion': $format = 'Submitted manuscript'; break;
            case 'publishedVersion': $format = 'Full text'; break;
            default: $format = null;
        }
        if ($format) $this->add('format', $format);
        return true;
      }
    } else {
       echo "\n   ! Could not retrieve open access details from oiDOI API for doi: " . htmlspecialchars($doi);
       return false;
    }
  }
  
  protected function expand_by_google_books() {
    $url = $this->get('url');
    if ($url && preg_match("~books\.google\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid)) {
      $removed_redundant = 0;
      $hash = '';
      
      if (strpos($url, "#")) {
        $url_parts = explode("#", $url);
        $url = $url_parts[0];
        $hash = "#" . $url_parts[1];
      }
      $url_parts = explode("&", str_replace("?", "&", $url));
      $url = "https://books.google.com/?id=" . $gid[1];
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
            echo "\n - " . htmlspecialchars($part);
            $removed_redundant++;
        }
      }
      if ($removed_redundant > 1) { // http:// is counted as 1 parameter
        $this->set('url', $url . $hash);
      }
      $this->google_book_details($gid[1]);
      return true;
    }
    return false;
  }

  protected function google_book_details ($gid) {
    $google_book_url = "http://books.google.com/books/feeds/volumes/$gid";
    $simplified_xml = str_replace('http___//www.w3.org/2005/Atom', 'http://www.w3.org/2005/Atom',
      str_replace(":", "___", file_get_contents($google_book_url))
    );
    $xml = simplexml_load_string($simplified_xml);
    if ($xml->dc___title[1]) {
      $this->add_if_new("title",  
               format_title_text(
                 str_replace("___", ":", $xml->dc___title[0] . ": " . $xml->dc___title[1]),
                 TRUE // $caps_after_punctuation
               )
             );
    } else {
      $this->add_if_new("title",  format_title_text(str_replace("___", ":", $xml->title)));
    }
    // Possibly contains dud information on occasion
    // $this->add_if_new("publisher", str_replace("___", ":", $xml->dc___publisher)); 
    $isbn = NULL;
    foreach ($xml->dc___identifier as $ident) {
      if (preg_match("~isbn.*?([\d\-]{9}[\d\-]+)~i", (string) $ident, $match)) {
        $isbn = $match[1];
      }
    }
    $this->add_if_new("isbn", $isbn);
    // Don't set 'pages' parameter, as this refers to the CITED pages, not the page count of the book.
    $i = null;
    if ($this->blank("editor") && $this->blank("editor1") && $this->blank("editor1-last") && $this->blank("editor-last") && $this->blank("author") && $this->blank("author1") && $this->blank("last") && $this->blank("last1") && $this->blank("publisher")) { // Too many errors in gBook database to add to existing data.   Only add if blank.
      foreach ($xml->dc___creator as $author) {
        if( $author != "Hearst Magazines" && $author != "Time Inc") {  // Catch common google bad authors
           $this->add_if_new("author" . ++$i, formatAuthor(str_replace("___", ":", $author)));
        }
      }
    }
    $this->add_if_new("date", $xml->dc___date);
    foreach ($xml->dc___format as $format) {
      if (preg_match("~([\d\-]+)~", $format, $matches)) {
        $this->add_if_new("pages", $matches[0]);
      }
    }
  }

  protected function find_isbn() {
    return FALSE; #TODO restore this service.
    if ($this->blank('isbn') && $this->has('title')) {
      $title = trim($this->get('title'));
      $auth = trim($this->get('author') . $this->get('author1') . ' ' . $this->get('last') . $this->get('last1'));
      global $over_isbn_limit;
      // TODO: implement over_isbn_limit based on &results=keystats in API
      if ($title && !$over_isbn_limit) {
        $xml = simplexml_load_file("http://isbndb.com/api/books.xml?access_key=" . ISBN_KEY . "index1=combined&value1=" . urlencode($title . " " . $auth));
        print "\n\nhttp://isbndb.com/api/books.xml?access_key=$ISBN_KEY&index1=combined&value1=" . urlencode($title . " " . $auth . "\n\n");
        if ($xml->BookList["total_results"] == 1) return $this->set('isbn', (string) $xml->BookList->BookData["isbn"]);
        if ($auth && $xml->BookList["total_results"] > 0) return $this->set('isbn', (string) $xml->BookList->BookData["isbn"]);
        else return false;
      }
    }
  }

  protected function find_more_authors() {
  /** If crossRef has only sent us one author, perhaps we can find their surname in association with other authors on the URL
   *   Send the URL and the first author's SURNAME ONLY as $a1
   *  The function will return an array of authors in the form $new_authors[3] = Author, The Third
   */
    if ($doi = $this->get_without_comments('doi')) {
      $this->expand_by_doi(TRUE);
    }
    if ($this->get('pmid')) {
      $this->expand_by_pubmed(TRUE);
    }
    $pages = $this->page_range();
    $pages = $pages[0];
    if (preg_match("~\d\D+\d~", $pages)) {
      $new_pages = $pages;
    }
    if ($doi) {
      $url = "http://dx.doi.org/" . urlencode($doi);
    } else {
      $url = urlencode($this->get('url'));
    }
    $stopRegexp = "[\n\(:]|\bAff"; // Not used currently - aff may not be necessary.
    if (!$url) {
      return NULL;
    }
    echo "\n  * Looking for more authors @ " . htmlspecialchars($url) . ":";
    echo "\n   - Using meta tags...";
    $meta_tags = get_meta_tags($url);
    if ($meta_tags["citation_authors"]) {
      $new_authors = formatAuthors($meta_tags["citation_authors"], true);
    }
    if (SLOW_MODE && !$new_pages && !$new_authors) {
      echo "\n   - Now scraping web-page.";
      //Initiate cURL resource
      $ch = curl_init();
      curlSetup($ch, $url);

      curl_setopt($ch, CURLOPT_MAXREDIRS, 7);  //This means we can't get stuck.
      if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
        echo "404 returned from URL.<br>";
      } elseif (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 501) {
        echo "501 returned from URL.<br>";
      } else {
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
          if (!$new_pages && preg_match("~^[\d\w]+$~", trim($pages), $page)) {
            // find an end page number first
            $firstPageForm = preg_replace('~d\?([^?]*)$~U', "d$1", preg_replace('~\d~', '\d?', preg_replace('~[a-z]~i', '[a-zA-Z]?', $page[0])));
            #echo "\n Searching for page number with form $firstPageForm:";
            if (preg_match("~{$page[0]}[^\d\w\.]{1,5}?(\d?$firstPageForm)~", trim($source), $pages)) { // 13 leaves enough to catch &nbsp;
              $new_pages = $page[0] . '-' . $pages[1];
             # echo " found range [$page[0] to $pages[1]]";
            } #else echo " not found.";
          }

          // Authors
          if (true || !$new_authors) {
            // Check dc.contributor, which isn't correctly handled by get_meta_tags
            if (preg_match_all("~\<meta name=\"dc.Contributor\" +content=\"([^\"]+)\"\>~U", $source, $authors)){
              $new_authors=$authors[1];
            }
          }
        } else {
          echo "\n   x File size was too large. Abandoned.";
        }
      }
    }

    $count_new_authors = count($new_authors) - 1;
    if ($count_new_authors > 0) {
      $this->forget('author');
      for ($j = 0; $j < $count_new_authors; ++$j) {
        $au = explode(', ', $new_authors[$j - 1]);
        if ($au[1]) {
          $this->add_if_new('last' . $j, $au[0]);
          $this->add_if_new('first' . $j, preg_replace("~(\p{L})\p{L}*\.? ?~", "$1.", $au[1]));
          $this->forget('author' . $j);
        } else {
          if ($au[0]) {
            $this->add_if_new ("author$j", $au[0]);
          }
        }
      }
    }
    if ($new_pages) {
      $this->set('pages', $new_pages);
      echo " [completed page range]";
    }
  }


  ### parameter processing
  protected function use_unnamed_params() {
    if (empty($this->param)) return;
    
    $this->parameter_names_to_lowercase();
    $param_occurrences = array();
    $duplicated_parameters = array();
    $duplicate_identical = array();
    
    foreach ($this->param as $pointer => $par) {
      if ($par->param && isset($param_occurrences[$par->param])) {
        $duplicate_pos = $param_occurrences[$par->param];
        array_unshift($duplicated_parameters, $duplicate_pos);
        array_unshift($duplicate_identical, ($par->val == $this->param[$duplicate_pos]->val));
      }
      $param_occurrences[$par->param] = $pointer;
    }
    
    $n_dup_params = count($duplicated_parameters);
    
    for ($i = 0; $i < $n_dup_params; $i++) {
      if ($duplicate_identical[$i]) {
        echo "\n * Deleting identical duplicate of parameter: " .
          htmlspecialchars($this->param[$duplicated_parameters[$i]]->param) . "\n";
        unset($this->param[$duplicated_parameters[$i]]);
      }
      else {
        $this->param[$duplicated_parameters[$i]]->param = str_replace('DUPLICATE_DUPLICATE_', 'DUPLICATE_', 'DUPLICATE_' . $this->param[$duplicated_parameters[$i]]->param);
        echo "\n * Marking duplicate parameter: " .
          htmlspecialchars($duplicated_parameters[$i]->param) . "\n";
      }
    }
    
    foreach ($this->param as $param_key => $p) {
      if (!empty($p->param)) {
        if (preg_match('~^\s*(https?://|www\.)\S+~', $p->param)) { # URL ending ~ xxx.com/?para=val
          $this->param[$param_key]->val = $p->param . '=' . $p->val;
          $this->param[$param_key]->param = 'url';
          if (stripos($p->val, 'books.google.') !== FALSE) {
            $this->name = 'Cite book';
            $this->process();
          }
        } elseif ($p->param == 'doix') {
          $this->param[$param_key]->param = 'doi';
          $this->param[$param_key]->val = str_replace(DOT_ENCODE, DOT_DECODE, $p->val);
        }
        continue;
      }
      $dat = $p->val;
      $param_recycled = FALSE;
      $endnote_test = explode("\n%", "\n" . $dat);
      if (isset($endnote_test[1])) {
        foreach ($endnote_test as $endnote_line) {
          switch ($endnote_line[0]) {
            case "A": $endnote_authors++; $endnote_parameter = "author$endnote_authors";        break;
            case "D": $endnote_parameter = "date";       break;
            case "I": $endnote_parameter = "publisher";  break;
            case "C": $endnote_parameter = "location";   break;
            case "J": $endnote_parameter = "journal";    break;
            case "N": $endnote_parameter = "issue";      break;
            case "P": $endnote_parameter = "pages";      break;
            case "T": $endnote_parameter = "title";      break;
            case "U": $endnote_parameter = "url";        break;
            case "V": $endnote_parameter = "volume";     break;
            case "@": // ISSN / ISBN
              if (preg_match("~@\s*[\d\-]{10,}~", $endnote_line)) {
                $endnote_parameter = "isbn";
                break;
              } else if (preg_match("~@\s*\d{4}\-?\d{4}~", $endnote_line)) {
                $endnote_parameter = "issn";
                break;
              } else {
                $endnote_parameter = false;
              }
            case "R": // Resource identifier... *may* be DOI but probably isn't always.
            case "8": // Date
            case "0":// Citation type
            case "X": // Abstract
            case "M": // Object identifier
              $dat = trim(str_replace("\n%$endnote_line", "", "\n" . $dat));
            default:
              $endnote_parameter = false;
          }
          if ($endnote_parameter && $this->blank($endnote_parameter)) {
            $to_add[$endnote_parameter] = substr($endnote_line, 1);
            $dat = trim(str_replace("\n%$endnote_line", "", "\n$dat"));
          }
        }
      }

      if (preg_match("~^TY\s+-\s+[A-Z]+~", $dat)) { // RIS formatted data:
        $ris = explode("\n", $dat);
        foreach ($ris as $ris_line) {
          $ris_part = explode(" - ", $ris_line . " ");
          switch (trim($ris_part[0])) {
            case "T1":
            case "TI":
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
              add_if_new("pages", $start_page . "-" . $end_page);
              break;
            case "DO":
              $ris_parameter = "doi";
              break;
            case "JO":
            case "JF":
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
            case "M3": case "PY": case "N1": case "N2": case "ER": case "TY": case "KW":
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
            default:
              $ris_parameter = false;
          }
          unset($ris_part[0]);
          if ($ris_parameter
                  && add_if_new($ris_parameter, trim(implode($ris_part)))
              ) {
            global $auto_summary;
            if (!strpos("Converted RIS citation to WP format", $auto_summary)) {
              $auto_summary .= "Converted RIS citation to WP format. ";
            }
            $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
          }
        }
      }
      
      if (preg_match('~^(https?://|www\.)\S+~', $dat, $match)) { # Takes priority over more tenative matches
        $this->set('url', $match[0]);
        $dat = str_replace($match[0], '', $dat);
      }
      
      if (preg_match_all("~(\w+)\.?[:\-\s]*([^\s;:,.]+)[;.,]*~", $dat, $match)) { #vol/page abbrev.
        foreach ($match[0] as $i => $oMatch) {
          switch (strtolower($match[1][$i])) {
            case "vol": case "v": case 'volume':
              $matched_parameter = "volume";
              break;
            case "no": case "number": case 'issue': case 'n':
              $matched_parameter = "issue";
              break;
            case 'pages': case 'pp': case 'pg': case 'pgs': case 'pag':
              $matched_parameter = "pages";
              break;
            case 'p':
              $matched_parameter = "page";
              break;
            default:
              $matched_parameter = null;
          }
          if ($matched_parameter) {
            $dat = trim(str_replace($oMatch, "", $dat));
            if ($i == 0 && !$param_recycled) { // Use existing parameter slot in first instance
              $this->param[$param_key]->param = $matched_parameter;
              $this->param[$param_key]->val = $match[2][0];
              $param_recycled = TRUE;
            } else {
              $this->add_if_new($matched_parameter, $match[2][$i]);
            }
          }
        }
      }
      
      // Match vol(iss):pp
      if (preg_match("~(\d+)\s*(?:\((\d+)\))?\s*:\s*(\d+(?:\d\s*-\s*\d+))~", $dat, $match)) {
        $this->add_if_new('volume', $match[1]);
        $this->add_if_new('issue' , $match[2]);
        $this->add_if_new('pages' , $match[3]);
        $dat = trim(str_replace($match[0], '', $dat));
      }

      $shortest = -1;
      $parameter_list = PARAMETER_LIST;
      foreach ($parameter_list as $parameter) {
        $para_len = strlen($parameter);
        if (substr(strtolower($dat), 0, $para_len) == $parameter) {
          $character_after_parameter = substr(trim(substr($dat, $para_len)), 0, 1);
          $parameter_value = ($character_after_parameter == "-" || $character_after_parameter == ":")
            ? substr(trim(substr($dat, $para_len)), 1) : substr($dat, $para_len);
          if (!$param_recycled) {
            $this->param[$param_key]->param = $parameter;
            $this->param[$param_key]->val = $parameter_value;
            $param_recycled = TRUE;
          } else {
            $this->add($parameter,$parameter_value);
          }
          break;
        }
        $test_dat = preg_replace("~\d~", "_$0",
                    preg_replace("~[ -+].*$~", "", substr(mb_strtolower($dat), 0, $para_len)));
        if ($para_len < 3) break; // minimum length to avoid false positives
        if (preg_match("~\d~", $parameter)) {
          $lev = levenshtein($test_dat, preg_replace("~\d~", "_$0", $parameter));
          $para_len++;
        } else {
          $lev = levenshtein($test_dat, $parameter);
        }
        if ($lev == 0) {
          $closest = $parameter;
          $shortest = 0;
          break;
        } else {
          $closest = null;
        }
        // Strict inequality as we want to favour the longest match possible
        if ($lev < $shortest || $shortest < 0) {
          $comp = $closest;
          $closest = $parameter;
          $shortish = $shortest;
          $shortest = $lev;
        } elseif ($lev < $shortish) {
          // Keep track of the second shortest result, to ensure that our chosen parameter is an out and out winner
          $shortish = $lev;
          $comp = $parameter;
        }

      }

      if (  $shortest < 3
         && strlen($test_dat > 0)
         && similar_text($shortest, $test_dat) / strlen($test_dat) > 0.4
         && ($shortest + 1 < $shortish  // No close competitor
             || $shortest / $shortish <= 2/3
             || strlen($closest) > strlen($comp)
            )
      ) {
        // remove leading spaces or hyphens (which may have been typoed for an equals)
        if (preg_match("~^[ -+]*(.+)~", substr($dat, strlen($closest)), $match)) {
          $this->add($closest, $match[1]/* . " [$shortest / $comp = $shortish]"*/);
        }
      } elseif (preg_match("~(?!<\d)(\d{10}|\d{13})(?!\d)~", str_replace(Array(" ", "-"), "", $dat), $match)) {
        // Is it a number formatted like an ISBN?
        $this->add('isbn', $match[1]);
        $pAll = "";
      } else {
        // Extract whatever appears before the first space, and compare it to common parameters
        $pAll = explode(" ", trim($dat));
        $p1 = mb_strtolower($pAll[0]);
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
          if ($this->blank($p1)) {
            unset($pAll[0]);
            if (!$param_recycled) {
              $this->param[$param_key]->param = $p1;
              $this->param[$param_key]->val = implode(" ", $pAll);
              $param_recycled = TRUE; 
            } else {
              $this->add($p1,implode(" ", $pAll));
            }
          }
          break;
          case "issues":
          if ($this->blank($p1)) {
            unset($pAll[0]);
            if (!$param_recycled) {
              $this->param[$param_key]->param = 'issue';
              $this->param[$param_key]->val = implode(" ", $pAll);
              $param_recycled = TRUE;
            } else {
              $this->add('issue',implode(" ", $pAll));
            }
          }
          break;
          case "access date":
          if ($this->blank($p1)) {
            unset($pAll[0]);
            if (!$param_recycled) {
              $this->param[$param_key]->param = 'accessdate';
              $this->param[$param_key]->val = implode(" ", $pAll);
              $param_recycled = TRUE;
            } else {
              $this->add('accessdate',implode(" ", $pAll));
            }
          }
          break;
        }
      }
      if (preg_match("~\(?(1[89]\d\d|20\d\d)[.,;\)]*~", $dat, $match)) { #YYYY
        if ($this->blank('year') && $this->blank('date')) {
          $this->set('year', $match[1]);
          $dat = trim(str_replace($match[0], '', $dat));
        }
      }
      if (!trim($dat) && !$param_recycled) {
        unset($this->param[$param_key]);
      }
    }
  
  }

  protected function id_to_param() {
    $id = $this->get('id');
    if (trim($id)) {
      echo ("\n - Trying to convert ID parameter to parameterized identifiers.");
    } else {
      return false;
    }
    if (preg_match("~\b(PMID|DOI|ISBN|ISSN|ARXIV|LCCN)[\s:]*(\d[\d\s\-]*[^\s\}\{\|]*)~iu", $id, $match)) {
      $this->add_if_new(strtolower($match[1]), $match[2]);
      $id = str_replace($match[0], '', $id);
    }
    preg_match_all("~\{\{(?P<content>(?:[^\}]|\}[^\}])+?)\}\}[,. ]*~", $id, $match);
    foreach ($match["content"] as $i => $content) {
      $content = explode(PIPE_PLACEHOLDER, $content);
      unset($parameters);
      $j = 0;
      foreach ($content as $fragment) {
        $content[$j++] = $fragment;
        $para = explode("=", $fragment);
        if (trim($para[1])) {
          $parameters[trim($para[0])] = trim($para[1]);
        }
      }
      switch (strtolower(trim($content[0]))) {
        case "arxiv":
          array_shift($content);
          if ($parameters["id"]) {
            $this->add_if_new("arxiv", ($parameters["archive"] ? trim($parameters["archive"]) . "/" : "") . trim($parameters["id"]));
          } else if ($content[1]) {
            $this->add_if_new("arxiv", trim($content[0]) . "/" . trim($content[1]));
          } else {
            $this->add_if_new("arxiv", implode(PIPE_PLACEHOLDER, $content));
          }
          $id = str_replace($match[0][$i], "", $id);
          break;
        case "lccn":
          $this->add_if_new("lccn", trim($content[1]) . $content[3]);
          $id = str_replace($match[0][$i], "", $id);
          break;
        case "rfcurl":
          $identifier_parameter = "rfc";
        case "asin":
          if ($parameters["country"]) {
            echo "\n    - {{ASIN}} country parameter not supported: can't convert.";
            break;
          }
        case "oclc":
          if ($content[2]) {
            echo "\n    - {{OCLC}} has multiple parameters: can't convert.";
            break;
          }
        case "ol":
          if ($parameters["author"]) {
            echo "\n    - {{OL}} author parameter not supported: can't convert.";
            break;
          }
        case "bibcode":
        case "doi":
        case "isbn":
        case "issn":
        case "jfm":
        case "jstor":
          if ($parameters["sici"] || $parameters["issn"]) {
            echo "\n    - {{JSTOR}} named parameters are not supported: can't convert.";
            break;
          }
        case "mr":
        case "osti":
        case "pmid":
        case "pmc":
        case "ssrn":
        case "zbl":
          if ($identifier_parameter) {
            array_shift($content);
          }
          $this->add_if_new($identifier_parameter ? $identifier_parameter : strtolower(trim(array_shift($content))), $parameters["id"] ? $parameters["id"] : $content[0]);
          $identifier_parameter = null;
          $id = str_replace($match[0][$i], "", $id);
          break;
        default:
          echo "\n    - No match found for " . htmlspecialchars($content[0]);
      }
    }
    if (trim($id)) $this->set('id', $id); else $this->forget('id');
  }

  protected function correct_param_spelling() {
  // check each parameter name against the list of accepted names (loaded in expand.php).
  // It will correct any that appear to be mistyped.
  $mistake_corrections = array_values(COMMON_MISTAKES);
  $mistake_keys = array_keys(COMMON_MISTAKES);
  if ($this->param) {
    foreach ($this->param as $p) {
      $parameters_used[] = $p->param;
    }
  }
  $parameter_list = PARAMETER_LIST;
  $unused_parameters = ($parameters_used ? array_diff($parameter_list, $parameters_used) : $parameter_list);

  $i = 0; // FIXME: this would be better as a proper for loop rather than foreach with counter
  foreach ($this->param as $p) {
    ++$i;

    if ((strlen($p->param) > 0) && !in_array($p->param, PARAMETER_LIST)) {
     
      echo "\n  *  Unrecognised parameter " . htmlspecialchars($p->param) . " ";
      $mistake_id = array_search($p->param, $mistake_keys);
      if ($mistake_id) {
        // Check for common mistakes.  This will over-ride anything found by levenshtein: important for "editor1link" !-> "editor-link" (though this example is no longer relevant as of 2017)
        $p->param = $mistake_corrections[$mistake_id];
        echo 'replaced with ' . $mistake_corrections[$mistake_id] . ' (common mistakes list)';
        continue;
      }
      
      /* Not clear why this exception exists.
       * If it is valid, it should apply only when $p->param relates to authors,
       * not when it applies to e.g. pages, title.
      if ($this->initial_author_params) {
        echo "\n * initial authors exist, not correcting " . htmlspecialchars($p->param);
        continue;
      }
      */

      $p->param = preg_replace('~author(\d+)-(la|fir)st~', "$2st$1", $p->param);
      $p->param = preg_replace('~surname\-?_?(\d+)~', "last$1", $p->param);
      $p->param = preg_replace('~(?:forename|initials?)\-?_?(\d+)~', "first$1", $p->param);

      // Check the parameter list to find a likely replacement
      $shortest = -1;
      $closest = null;
      foreach ($unused_parameters as $parameter) {
        $lev = levenshtein($p->param, $parameter, 5, 4, 6);
        // Strict inequality as we want to favour the longest match possible
        if ($lev < $shortest || $shortest < 0) {
          $comp = $closest;
          $closest = $parameter;
          $shortish = $shortest;
          $shortest = $lev;
        }
        // Keep track of the second-shortest result, to ensure that our chosen parameter is an out and out winner
        else if ($lev < $shortish) {
          $shortish = $lev;
          $comp = $parameter;
        }
      }
      $str_len = strlen($p->param);

      // Account for short words...
      if ($str_len < 4) {
        $shortest *= ($str_len / (similar_text($p->param, $closest) ? similar_text($p->param, $closest) : 0.001));
        $shortish *= ($str_len / (similar_text($p->param, $comp) ? similar_text($p->param, $comp) : 0.001));
      }
      
      if ($shortest < 12 && $shortest < $shortish) {
        $p->param = $closest;
        echo " replaced with $closest (likelihood " . (12 - $shortest) . "/12)";
      } else {
        $similarity = similar_text($p->param, $closest) / strlen($p->param);
        if ($similarity > 0.6) {
          $p->param = $closest;
          echo " replaced with $closest (similarity " . round(12 * $similarity, 1) . "/12)";
        } else {
          echo " could not be replaced with confidence.  Please check the citation yourself.";
        }
      }
    }
  }
}

  public function remove_non_ascii() {
    for ($i = 0; $i < count($this->param); $i++) {
      $this->param[$i]->val = preg_replace('/[^\x20-\x7e]/', '', $this->param[$i]->val); // Remove illegal non-ASCII characters such as invisible spaces
    }
  }

  protected function join_params() {
    $ret = '';
    if ($this->param) {
      foreach($this->param as $p) {
        $ret .= '|' . $p->parsed_text();
      }
    }
    return $ret;
  }

  public function wikiname() {
    return trim(mb_strtolower(str_replace('_', ' ', $this->name)));
  }

  ### Tidying and formatting
  protected function tidy() {
    $to_add = array();
    $others = '';
    if ($this->added('title')) {
      $this->format_title();
    } else if ($this->is_modified() && $this->get('title')) {
      $this->set('title', format_title_text(straighten_quotes((mb_substr($this->get('title'), -1) == ".") ? mb_substr($this->get('title'), 0, -1) : $this->get('title'))));
    }

    if ($this->blank(array('date', 'year')) && $this->has('origyear')) {
      $this->rename('origyear', 'year');
    }

    $authors = $this->get('authors');
    if (!$authors) {
      $authors = $this->get('author'); # Order _should_ be irrelevant as only one will be set... but prefer 'authors' if not.
    }

    if(!$this->initial_author_params) {
      if (preg_match('~([,;])\s+\[\[|\]\]([;,])~', $authors, $match)) {
        $this->add_if_new('author-separator', $match[1] ? $match[1] : $match[2]);
        $new_authors = explode($match[1] . $match[2], $authors);
        $this->forget('author');
        $this->forget('authors');

        for ($i = 0; $i < count($new_authors); $i++) {
          $this->add_if_new("author" . ($i + 1), trim($new_authors[$i]));
        }
      }
    }

    if ($this->param) foreach ($this->param as $p) {
      if (preg_match('~(\D+)(\d*)~', $p->param, $pmatch)) {
        switch ($pmatch[1]) {
          case 'author': case 'authors': case 'last': case 'surname':
            if (!$this->initial_author_params) {
              if ($pmatch[2]) {
                if (preg_match("~\[\[(([^\|]+)\|)?([^\]]+)\]?\]?~", $p->val, $match)) {
                  $to_add['authorlink' . $pmatch[2]] = ucfirst($match[2]?$match[2]:$match[3]);
                  $p->val = $match[3];
                  echo "\n   ~ Dissecting authorlink" . tag();
                }
                $translator_regexp = "~\b([Tt]r(ans(lat...?(by)?)?)?\.)\s([\w\p{L}\p{M}\s]+)$~u";
                if (preg_match($translator_regexp, trim($p->val), $match)) {
                  $others = "{$match[1]} {$match[5]}";
                  $p->val = preg_replace($translator_regexp, "", $p->val);
                }
              }
            } else {
              echo "\n * Initial authors exist, skipping authorlink in tidy";
            }
            break;
          case 'journal': 
            $this->forget('publisher');
          case 'periodical': 
            $p->val = format_title_text(title_capitalization($p->val, FALSE, FALSE));
            break;
          case 'edition': 
            $p->val = preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $p->val);
            break; // Don't want 'Edition ed.'
          case 'year':
            if (preg_match ("~\d\d*\-\d\d*\-\d\d*~", $p->val)) { // We have more than one dash, must not be range of years.
               if ($this->blank('date')) $this->set('date', $p->val);
               $this->forget('year');
               break; 
            }
            // No break here
          case 'pages': case 'page': case 'issue': case 'year':
            if (!preg_match("~^[A-Za-z ]+\-~", $p->val) && mb_ereg(TO_EN_DASH, $p->val) && (stripos($p->val, "http") === FALSE)) {
              $this->mod_dashes = TRUE;
              echo ( "\n   ~ Upgrading to en-dash in " . htmlspecialchars($p->param) .
                    " parameter" . tag());
              $p->val = mb_ereg_replace(TO_EN_DASH, EN_DASH, $p->val);
            }
            break;
        }
      }
    }

    if ($to_add) foreach ($to_add as $key => $val) {
      $this->add_if_new($key, $val);
    }

    if ($others) {
      if ($this->has('others')) $this->append_to('others', '; ' . $others);
      else $this->set('others', $others);
    }

    if ($this->added('journal')) {
      $this->forget('issn');
    }

    // Remove leading zeroes
    if (!$this->blank('issue') && $this->blank('number')) {
      $new_issue =  preg_replace('~^0+~', '', $this->get('issue'));
      if ($new_issue) $this->set('issue', $new_issue);
      else $this->forget('issue');
    }
    switch(strtolower(trim($this->get('quotes')))) {
      case 'yes': case 'y': case 'true': case 'no': case 'n': case 'false': $this->forget('quotes');
    }

    if ($this->get('doi') == "10.1267/science.040579197") $this->forget('doi'); // This is a bogus DOI from the PMID example file

    /*/ If we have any unused data, check to see if any is redundant!
    if (is("unused_data")) {
      $freeDat = explode("|", trim($this->get('unused_data')));
      unset($this->get('unused_data');
      foreach ($freeDat as $dat) {
        $eraseThis = false;
        foreach ($p as $oP) {
          similar_text(mb_strtolower($oP[0]), mb_strtolower($dat), $percentSim);
          if ($percentSim >= 85)
            $eraseThis = true;
        }
        if (!$eraseThis)
          $this->!et('unused_data') .= "|" . $dat;
      }
      if (trim(str_replace("|", "", $this->!et('unused_data'))) == "")
        unset($this->!et('unused_data');
      else {
        if (substr(trim($this->!et('unused_data')), 0, 1) == "|")
          $this->!et('unused_data') = substr(trim($this->!et('unused_data')), 1);
      }
    }*/
    if ($this->has('accessdate') && $this->lacks('url') && $this->lacks('chapter-url') && $this->lacks('chapterurl') && $this->lacks('contribution-url') && $this->lacks('contributionurl')) $this->forget('accessdate');
  }

  protected function format_title($title = FALSE) {
    if (!$title) $title = $this->get('title');
    $this->set('title', format_title_text($title)); // order IS important!
  }

  protected function sanitize_doi($doi = FALSE) {
    if (!$doi) {
      $doi = $this->get('doi');
      if (!$doi) return false;
    }
    $this->set('doi', str_replace(PERCENT_ENCODE, PERCENT_DECODE, str_replace(' ', '+', trim(urldecode($doi)))));
    return true;
  }

  protected function verify_doi () {
    $doi = $this->get_without_comments('doi');
    if (!$doi) return NULL;
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
      $trial[] = $doi;
    }
    if (preg_match("~^(.+)(10\.\d{4}/.+)~", trim($doi), $match)) {
      $trial[] = $match[1];
      $trial[] = $match[2];
    }
    $replacements = array (      "&lt;" => "<",      "&gt;" => ">",    );
    if (preg_match("~&[lg]t;~", $doi)) {
      $trial[] = str_replace(array_keys($replacements), $replacements, $doi);
    }
    if (isset($trial)) foreach ($trial as $try) {
      // Check that it begins with 10.
      if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) $try = "10." . $match[1];
      if ($this->expand_by_doi($try)) {
        $this->set('doi', $try);
        $doi = $try;
      }
    }
    echo "\n   . Checking that DOI " . htmlspecialchars($doi) . " is operational..." . tag();
    if ($this->query_crossref() === FALSE) {
      // Replace old "doi_inactivedate" and/or other broken/inactive-date parameters,
      // if present, with new "doi-broken-date"
      $this->forget("doi_inactivedate");
      $this->forget("doi-inactive-date");
      $this->forget("doi_brokendate");
      $url_test = "http://dx.doi.org/".$doi ;
      $headers_test = get_headers($url_test, 1);
      if(empty($headers_test['Location']))
         $this->set("doi-broken-date", date("Y-m-d"));  // dx.doi.org might work, even if cross-ref fails
      echo "\n   ! Broken doi: " . htmlspecialchars($doi);
      return FALSE;
    } else {
      $this->forget('doi_brokendate');
      $this->forget('doi_inactivedate');
      $this->forget('doi-broken-date');
      $this->forget('doi-inactive-date');
      echo ' DOI ok.';
      return TRUE;
    }
  }


  public function check_url() {
    // Check that the URL functions, and mark as dead if not.
    /*  Disable; to re-enable, we should log possible 404s and check back later.
     * Also, dead-link notifications should be placed ''after'', not within, the template.

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

  protected function handle_et_al() {
    foreach (AUTHOR_PARAMETERS as $i => $group) {
      foreach ($group as $param) {
        if (strpos($this->get($param), 'et al')) {
          // remove 'et al' from the parameter value if present
          $val_base = preg_replace("~,?\s*'*et al['.]*~", '', $this->get($param));
          if ($i == 1) {
            // then we (probably) have a list of authors joined by commas in our first parameter
            if (under_two_authors($val_base)) {
              if ($param == 'authors' && $this->blank('author')) {
                $this->rename('authors', 'author');
              }
            }
            $this->set($param, $val_base);
          }
          if (trim($val_base) == "") {
            $this->forget($param);
          }

// These two lines are most likely a hack to get "et al." to display automatically. Don't do this.
// If you need to, use "displayauthors = etal" in the template.
//          $this->add_if_new('author' . ($i + 1), 'and others'); //FIXME: this may overwrite author parameters.
          $this->add_if_new('displayauthors', $i); //FIXME: doesn't overwrite but may not be a good idea
        }
      }
    }
  }

  // Retrieve parameters 
  public function display_authors($newval = FALSE) {
    if ($newval && is_int($newval)) {
      $this->forget('displayauthors');
      echo "\n ~ Setting display-authors to $newval" . tag();
      $this->set('display-authors', $newval);
    }

    if (($da = $this->get('display-authors')) === NULL) {
      $da = $this->get('displayauthors');
    }
    return is_int(1 * $da) ? $da : FALSE;
  }

  public function number_of_authors() {
    $max = 0;
    if ($this->param) foreach ($this->param as $p) {
      if (preg_match('~(?:author|last|first|forename|initials|surname)(\d+)~', $p->param, $matches))
        $max = max($matches[1], $max);
    }
    return $max;
  }

  public function first_author() {
    // Fetch the surname of the first author only
    if (preg_match("~[^.,;\s]{2,}~u", implode(' ',
            array($this->get('author'), $this->get('author1'), $this->get('last'), $this->get('last1')))
            , $first_author)) {
      return $first_author[0];
    } else {
      return null;
    }        
  }

  public function page() {
    $page = $this->get('pages');
    return ($page ? $page : $this->get('page'));
  }

  public function name() {return trim($this->name);}

  public function page_range() {
    preg_match("~(\w?\w?\d+\w?\w?)(?:\D+(\w?\w?\d+\w?\w?))?~", $this->page(), $pagenos);
    return $pagenos;
  }

  // Amend parameters
  public function rename($old_param, $new_param, $new_value = FALSE) {
    foreach ($this->param as $p) {
      if ($p->param == $old_param) {
        $p->param = $new_param;
        if ($new_value) {
          $p->val = $new_value;
        }
      }
    }
  }

  public function get($name) {
    // NOTE $this->param and $p->param are different and refer to different types!
    // $this->param is an array of Parameter objects
    // $parameter_i->param is the parameter name within the Parameter object
    if ($this->param) {
      foreach ($this->param as $parameter_i) {
        if ($parameter_i->param == $name) {
          return $parameter_i->val;
        }
      }
    }
    return NULL;
  }
  
  public function get_without_comments($name) {
    $ret = preg_replace('~<!--.*?-->~su', '', $this->get($name));
    return (trim($ret) ? $ret : FALSE);
  }

  protected function get_param_key ($needle) {
    if (empty($this->param)) return NULL;
    if (!is_array($this->param)) return NULL; // Maybe the wrong thing to do?
    
    foreach ($this->param as $i => $p) {
      if ($p->param == $needle) return $i;
    }
    
    return NULL;
  }

  public function has($par) {return (bool) strlen($this->get($par));}
  public function lacks($par) {return !$this->has($par);}

  public function add($par, $val) {
    echo "\n   + Adding $par" .tag();
    return $this->set($par, $val);
  }
  
  public function set($par, $val) {
    if (($pos = $this->get_param_key($par)) !== NULL) {
      return $this->param[$pos]->val = $val;
    }
    if (isset($this->param[0])) {
      $p = new Parameter;
      // Use second param as a template if present, in case first pair 
      // is last1 = Smith | first1 = J.\n
      $p->parse_text($this->param[isset($this->param[1]) ? 1 : 0]->parsed_text()); 
    } else {
      $p = new Parameter;
      $p->parse_text('| param = val');
    }
    $p->param = $par;
    $p->val = $val;
    
    $insert_after = prior_parameters($par);
    foreach (array_reverse($insert_after) as $after) {
      if (($after_key = $this->get_param_key($after)) !== NULL) {
        $keys = array_keys($this->param);
        for ($prior_pos = 0; $prior_pos < count($keys); $prior_pos++) {
          if ($keys[$prior_pos] == $after_key) break;
        }
        $this->param = array_merge(
          array_slice($this->param, 0, $prior_pos + 1), 
          array($p),
          array_slice($this->param, $prior_pos + 1));
        return true;
      }
    }
    $this->param[] = $p;
    return true;
  }

  public function append_to($par, $val) {
    $pos = $this->get_param_key($par);
    if ($pos) {
      return $this->param[$pos]->val = $this->param[$pos]->val . $val;
    } else {
      return $this->set($par, $val);
    }
  }

  public function forget ($par) {
    $pos = $this->get_param_key($par);
    if ($pos !== NULL) {
      echo "\n   - Dropping parameter " . htmlspecialchars($par) . tag();
      unset($this->param[$pos]);
    }
  }

  // Record modifications
  protected function modified ($param, $type='modifications') {
    switch ($type) {
      case '+': $type='additions'; break;
      case '-': $type='deletions'; break;
      case '~': $type='changeonly'; break;
      default: $type='modifications';
    }
    return in_array($param, $this->modifications($type));
  }
  protected function added($param) {return $this->modified($param, '+');}

  public function modifications ($type='all') {
    if ($this->param) {
      foreach ($this->param as $p) {
        $new[$p->param] = $p->val;
      }
    } else {
      $new = array();
    }

    $old = ($this->initial_param) ? $this->initial_param : array();
    if ($new) {
      if ($old) {
        $ret['modifications'] = array_keys(array_diff_assoc ($new, $old));
        $ret['additions'] = array_diff(array_keys($new), array_keys($old));
        $ret['deletions'] = array_diff(array_keys($old), array_keys($new));
        $ret['changeonly'] = array_diff($ret['modifications'], $ret['additions']);
      } else {
        $ret['additions'] = array_keys($new);
        $ret['modifications'] = array_keys($new);
      }
    }
    $ret['dashes'] = $this->mod_dashes;
    if (in_array($type, array_keys($ret))) return $ret[$type];
    return $ret;
  }

  public function is_modified () {
    return (bool) count($this->modifications('modifications'));
  }

  // Parse initial text
  public function parsed_text() {
    return '{{' . $this->name . $this->join_params() . '}}';
  }
}
