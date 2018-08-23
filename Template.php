<?php
/*
 * Template has methods to handle most aspects of citation template
 * parsing, handling, and expansion.
 *
 * Of particular note:
 *     process() is what handles the different cite/Cite templates differently.
 *     add_if_new() is generally called to add or sometimes overwrite parameters. The central
 *       switch statement handles various parameters differently.
 *     
 * A range of functions will search CrossRef/adsabs/Google Books/other online databases
 * to find information that can be added to existing citations.
 */

require_once("Page.php");
require_once("Parameter.php");

final class Template {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_TEMPLATE %s # # #';
  const REGEXP = '~\{\{(?:[^\{]|\{[^\{])+?\}\}~s';
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  public $all_templates;  // Points to list of all the Template() on the Page() including this one
  protected $rawtext;

  protected $name, $param, $initial_param, $initial_author_params, $initial_name,
            $citation_template, $mod_dashes;

  public function parse_text($text) {
    $this->initial_author_params = NULL; // Will be populated later if there are any
    if ($this->rawtext) {
        warning("Template already initialized; call new Template() before calling Template::parse_text()");
    }
    $this->rawtext = $text;
    $pipe_pos = strpos($text, '|');
    if ($pipe_pos) {
      $this->name = substr($text, 2, $pipe_pos - 2); # Remove {{ and }}
      $this->split_params(substr($text, $pipe_pos + 1, -2));
    } else {
      $this->name = substr($text, 2, -2);
      $this->param = NULL;
    }
    $this->initial_name = $this->name;

    // extract initial parameters/values from Parameters in $this->param
    if ($this->param) foreach ($this->param as $p) {
      $this->initial_param[$p->param] = $p->val;

      // Save author params for special handling
      if (in_array($p->param, FLATTENED_AUTHOR_PARAMETERS) && $p->val) {
        $this->initial_author_params[$p->param] = $p->val;
      }
    }
  }

  // Re-assemble parsed template into string
  public function parsed_text() {
    if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
      if ($this->blank('title')) {
        return base64_decode($this->get(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL')));
      } else {
        $this->forget(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
      }
    }
    return '{{' . $this->name . $this->join_params() . '}}';
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

  protected function parameter_names_to_lowercase() {
    if (is_array($this->param)) {
      $keys = array_keys($this->param);
      for ($i=0; $i < count($keys); $i++) {
        $this->param[$keys[$i]]->param = strtolower($this->param[$keys[$i]]->param);
      }
    } else {
      $this->param = strtolower($this->param);
    }
  }

  public function prepare() {
    $this->use_unnamed_params();
    $this->get_identifiers_from_url();
    $this->tidy();
    $this->id_to_param();
    $this->correct_param_spelling();
    $this->get_doi_from_text();

    
    switch ($this->wikiname()) {
      case "cite arxiv":
         // Forget dates so that DOI can update with publication date, not ARXIV date
        $this->rename('date', 'CITATION_BOT_PLACEHOLDER_date');
        $this->rename('year', 'CITATION_BOT_PLACEHOLDER_year');
        $this->expand_by_doi();
        if ($this->blank('year') && $this->blank('date')) {
            $this->rename('CITATION_BOT_PLACEHOLDER_date', 'date');
            $this->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
        } else {
            $this->forget('CITATION_BOT_PLACEHOLDER_year');
            $this->forget('CITATION_BOT_PLACEHOLDER_date');        
        }
        break;
      case "cite journal":       
        if ($this->use_sici()) {
          report_action("Found and used SICI");
        }

    }
  }
  
  public function api_calls() {
   switch ($this->wikiname()) {
      case 'cite web':
        if (preg_match("~^https?://books\.google\.~", $this->get('url')) && $this->expand_by_google_books()) { // Could be any country's google
          report_action("Expanded from Google Books API");
          $this->name = 'Cite book'; // Better than cite web, but magazine or journal might be better which is why we do not "elseif" after here
        }
      break;
      case 'cite arxiv':
        $this->expand_by_arxiv();

      break;
      case 'cite book':
        if ($this->expand_by_google_books()) {
          report_action("Expanded from Google Books API");
        }
        $no_isbn_before_doi = $this->blank("isbn");
        $this->expand_by_doi();
        if ($no_isbn_before_doi && $this->has("isbn")) {
          $this->expand_by_google_books();
        }
      break;
      case 'cite journal': case 'cite document': case 'cite encyclopaedia': case 'cite encyclopedia': case 'citation':
        $this->expand_by_pubmed(); //partly to try to find DOI
        $this->expand_by_google_books();
        $this->expand_by_jstor();
        $this->expand_by_doi();
        $this->expand_by_adsabs(); //Primarily to try to find DOI
        $this->get_doi_from_crossref();
        $this->get_open_access_url();
        $this->find_pmid();
      break;
    }
  }
  
  public function update_template_name() {
    switch ($this->wikiname()) {
      case 'cite web':
        if ($this->has('journal') || $this->has('bibcode') 
           || $this->has('jstor') || $this->has('doi') 
           || $this->has('pmid') || $this->has('pmc')
            ) {
          $this->name = 'Cite journal';
        } elseif ($this->has('arxiv')) {
          $this->name = 'Cite arxiv';
          $this->rename('arxiv', 'eprint');
        } elseif ($this->has('eprint')) {
          $this->name = 'Cite arxiv';
        }
        $this->citation_template = TRUE;
      break;
      case 'cite arxiv':
        $this->citation_template = TRUE;
        if ($this->has('journal')) {
          $this->name = 'Cite journal';
          $this->rename('eprint', 'arxiv');
          $this->forget('class');
          $this->forget('publisher');  // This is either bad data, or refers to ARXIV preprint, not the journal that we have just added.
                                       // Therefore remove incorrect data
        } else if ($this->has('doi')) { // cite arxiv does not support DOI's
          $this->name = 'Cite journal';
          $this->rename('eprint', 'arxiv');
          // $this->forget('class');      Leave this for now since no journal title
          $this->forget('publisher');  // Since we have no journal, we cannot have a publisher
        }
      break;
      case "cite journal":
        // Convert from journal to book, if there is a unique chapter name or has an ISBN
        if ($this->has('chapter') && ($this->get('chapter') != $this->get('title') || $this->has('isbn'))) { 
          $this->name = 'Cite book';
        }
      break;
    }
  }
  
  public function process() {
    if (in_array($this->wikiname(), TEMPLATES_WE_PROCESS)) {
      $this->use_unnamed_params();
      $this->get_identifiers_from_url();
      $this->prepare();
      switch ($this->wikiname()) {
        case 'cite web':
          if (preg_match("~^https?://books\.google\.~", $this->get('url')) && $this->expand_by_google_books()) { // Could be any countries google
            report_action("Expanded from Google Books API");
            $this->name = 'Cite book'; // Better than cite web, but magazine or journal might be better which is why we do not "elseif" after here
          }
          if ($this->has('journal') || $this->has('bibcode') 
             || $this->has('jstor') || $this->has('doi') 
             || $this->has('pmid') || $this->has('pmc')
              ) {
            $this->name = 'Cite journal';
            $this->process();
          } elseif ($this->has('arxiv')) {
            $this->name = 'Cite arxiv';
            $this->rename('arxiv', 'eprint');
            $this->process();
          } elseif ($this->has('eprint')) {
            $this->name = 'Cite arxiv';
            $this->process();
          }
          $this->citation_template = TRUE;
        break;
        case 'cite arxiv':
          $this->citation_template = TRUE;
          $this->expand_by_arxiv();

          // Forget dates so that DOI can update with publication date, not ARXIV date
          $this->rename('date', 'CITATION_BOT_PLACEHOLDER_date');
          $this->rename('year', 'CITATION_BOT_PLACEHOLDER_year');
          $this->expand_by_doi();
          if ($this->blank('year') && $this->blank('date')) {
              $this->rename('CITATION_BOT_PLACEHOLDER_date', 'date');
              $this->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
          } else {
              $this->forget('CITATION_BOT_PLACEHOLDER_year');
              $this->forget('CITATION_BOT_PLACEHOLDER_date');        
          }

          if ($this->has('journal')) {
            $this->name = 'Cite journal';
            $this->rename('eprint', 'arxiv');
            $this->forget('class');
            $this->forget('publisher');  // This is either bad data, or refers to ARXIV preprint, not the journal that we have just added.
                                         // Therefore remove incorrect data
          } else if ($this->has('doi')) { // cite arxiv does not support DOI's
            $this->name = 'Cite journal';
            $this->rename('eprint', 'arxiv');
            // $this->forget('class');      Leave this for now since no journal title
            $this->forget('publisher');  // Since we have no journal, we cannot have a publisher
          }
        break;
        case 'cite book':
          $this->citation_template = TRUE;

          $this->get_identifiers_from_url();
          $this->id_to_param();
          echo "\n* " . echoable($this->get('title'));
          $this->correct_param_spelling();
          if ($this->expand_by_google_books()) {
            report_action("Expanded from Google Books API");
          }
          $no_isbn_before_doi = $this->blank("isbn");
          $this->expand_by_doi();
          if ($no_isbn_before_doi && $this->has("isbn")) {
            if ($this->expand_by_google_books()) {
               report_action("Expanded from Google Books API");
            }
          }

          // If the et al. is from added parameters, go ahead and handle
          if (!$this->initial_author_params) {
            $this->handle_et_al();
          }
        break;
        case 'cite journal': case 'cite document': case 'cite encyclopaedia': case 'cite encyclopedia': case 'citation':
          $this->citation_template = TRUE;
          echo "\n\n* Expand citation: " . echoable($this->get('title'));


          if ($this->use_sici()) {
            report_action("Found and used SICI");
          }

          $this->id_to_param();
          $this->get_doi_from_text();
          $this->correct_param_spelling();
          // TODO: Check for the doi-inline template in the title

          // If the et al. is from added parameters, go ahead and handle
          if (!$this->initial_author_params) {
            $this->handle_et_al();
          }

          $this->expand_by_pubmed(); //partly to try to find DOI

          if ($this->expand_by_google_books()) {
            report_action("Expanded from Google Books API");
          }
          if ($this->expand_by_jstor()) {
            report_action("Expanded from JSTOR API");
          }
          $this->expand_by_doi();
          $this->expand_by_adsabs(); //Primarily to try to find DOI
          $this->get_doi_from_crossref();
          $this->get_open_access_url();
          $this->find_pmid();
          
          // Convert from journal to book, if there is a unique chapter name or has an ISBN
          if ($this->has('chapter') && ($this->wikiname() == 'cite journal') && ($this->get('chapter') != $this->get('title') || $this->has('isbn'))) { 
            $this->name = 'Cite book';
          }
          if ($this->wikiname() === 'cite journal' && $this->has('work') && $this->blank('journal')) { // Never did get a journal name....
            $this->rename('work', 'journal');
          }
          break;
        case 'cite magazine':
          if ($this->blank('magazine') && $this->has('work')) { // This is all we do with cite magazine
            $this->rename('work', 'magazine');
          }
          break;
      }
    }
    if ($this->citation_template) {
      // Sometimes title and chapter come from different databases
      if ($this->has('chapter') && ($this->get('chapter') === $this->get('title'))) {  // Leave only one
        if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
            $this->forget('title');
        } elseif ($this->wikiname() === 'cite journal' || $this->wikiname() === 'citation') {
          $this->forget('chapter');
        }
      }
      // Sometimes series and journal come from different databases
      if ($this->has('series') && $this->has('journal') &&
          (strcasecmp($this->get('series'), $this->get('journal')) === 0)) {  // Leave only one
        if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
            $this->forget('journal');
        } elseif ($this->wikiname() === 'cite journal'|| $this->wikiname() === 'citation') {
          $this->forget('series');
        }
      }
      // "Work is a troublesome parameter
      if ($this->has('work')) {
         if (($this->has('journal') && (strcasecmp($this->get('work'), $this->get('journal')) === 0)) ||
            ($this->has('title') && (strcasecmp($this->get('work'), $this->get('title')) === 0))     ||
            ($this->has('series') && (strcasecmp($this->get('work'), $this->get('series')) === 0))   || 
            ($this->has('chapter') && (strcasecmp($this->get('work'), $this->get('chapter')) === 0))) {
           $this->forget('work');
         }
      } elseif ($this->get('work') !== NULL && $this->blank('work')) { // Have work=, but it is blank
         if ($this->has('journal') ||
             $this->has('newspaper') ||
             $this->has('magazine') ||
             $this->has('periodical') ||
             $this->has('website')) {
              $this->forget('work'); // Delete if we have alias
         } elseif ($this->wikiname() === 'cite web') {
            $this->rename('work', 'website');
         } elseif ($this->wikiname() === 'cite journal') {
            $this->rename('work', 'journal');
         } elseif ($this->wikiname() === 'cite magazine') {
            $this->rename('work', 'magazine');
         }
      }
      $this->correct_param_spelling();
      // $this->check_url(); // Function currently disabled
    }
  }

  protected function incomplete() {
    if (strtolower($this->wikiname()) =='cite book' || (strtolower($this->wikiname()) =='citation' && $this->has('isbn'))) { // Assume book
      if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
      return (!(
              $this->has("isbn")
          &&  $this->has("title")
          && ($this->has("date") || $this->has("year"))
          && ($this->has("author2") || $this->has("last2") || $this->has('surname2'))
      ));
    }
    // And now everything else
    if ($this->blank('pages', 'page') || (preg_match('~no.+no|n/a|in press|none~', $this->get('pages') . $this->get('page')))) {
      return TRUE;
    }
    if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
    return (!(
             ($this->has('journal') || $this->has('periodical'))
          &&  $this->has("volume")
          && ($this->has("issue") || $this->has('number'))
          &&  $this->has("title")
          && ($this->has("date") || $this->has("year"))
          && ($this->has("author2") || $this->has("last2") || $this->has('surname2'))
    ));
  }

  public function blank($param) {
    if (!$param) return ;
    if (empty($this->param)) return TRUE;
    if (!is_array($param)) $param = array($param);
    foreach ($this->param as $p) {
      if (in_array($p->param, $param) && trim($p->val) != '') return FALSE;
    }
    return TRUE;
  }

  /* function add_if_new
   * Adds a parameter to a template if the parameter and its equivalents are blank
   * If the parameter is useful for expansion (e.g. a doi), immediately uses the new
   * data to further expand the citation
   */
  public function add_if_new($param_name, $value) {
    if (trim($value) == '') {
      return FALSE;
    }
    
    if (array_key_exists($param_name, COMMON_MISTAKES)) {
      $param_name = COMMON_MISTAKES[$param_name];
    }
    
    // If we already have name parameters for author, don't add more
    if ($this->initial_author_params && in_array($param_name, FLATTENED_AUTHOR_PARAMETERS)) {
      return FALSE;
    }

    if (substr($param_name, -4) > 0 || substr($param_name, -3) > 0 || substr($param_name, -2) > 30) {
      // Stop at 30 authors - or page codes will become cluttered! 
      if ($this->get('last29') || $this->get('author29') || $this->get('surname29')) $this->add_if_new('display-authors', 29);
      return FALSE;
    }

    $auNo = preg_match('~\d+$~', $param_name, $auNo) ? $auNo[0] : NULL;        

    switch ($param_name) {
      ### EDITORS
      case "editor": case "editor-last": case "editor-first":
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);
        if ($this->blank('editor') && $this->blank("editor-last") && $this->blank("editor-first")) {
          return $this->add($param_name, sanitize_string($value));
        } else {
          return FALSE;
        }
      case 'editor4': case 'editor4-last': case 'editor4-first':
        $this->add_if_new('displayeditors', 29);
        return $this->add($param_name, sanitize_string($value));
      break;
      
      ### AUTHORS
      case "author": case "author1": case "last1": case "last": case "authors":
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);
        $value = straighten_quotes($value);

        if ($this->blank("last1") && $this->blank("last") && $this->blank("author") && $this->blank("author1")) {
          if (strpos($value, ',')) {
            $au = explode(',', $value);
            $this->add('last' . (substr($param_name, -1) == '1' ? '1' : ''), sanitize_string(format_Surname($au[0])));
            return $this->add_if_new('first' . (substr($param_name, -1) == '1' ? '1' : ''), sanitize_string(format_forename(trim($au[1]))));
          } else {
            return $this->add($param_name, sanitize_string($value));
          }
        }
      return FALSE;
      case "first": case "first1":
       $value = straighten_quotes($value);
       if ($this->blank("first") && $this->blank("first1") && $this->blank("author") && $this->blank('author1'))
          return $this->add($param_name, sanitize_string($value));
      return FALSE;
      case "coauthors": //FIXME: this should convert "coauthors" to "authors" maybe, if "authors" doesn't exist.
        $value = straighten_quotes($value);
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);

        if ($this->blank("last2") && $this->blank("coauthor") && $this->blank("coauthors") && $this->blank("author"))
          return $this->add($param_name, sanitize_string($value));
          // Note; we shouldn't be using this parameter ever....
      return FALSE;
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
            $this->add('last' . $auNo, format_surname($au[0]));
            return $this->add_if_new('first' . $auNo, format_forename(trim($au[1])));
          } else {
            return $this->add($param_name, sanitize_string($value));
          }
        }
        return FALSE;
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
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;
      
      case 'display-authors': case 'displayauthors':
        if ($this->blank('display-authors') && $this->blank('displayauthors')) {
          return $this->add($param_name, $value);
        }
      return FALSE;
      case 'display-editors': case 'displayeditors':
        if ($this->blank('display-editors') && $this->blank('displayeditors')) {
          return $this->add($param_name, $value);
        }
      return FALSE;
      
      case 'author_separator': case 'author-separator':
        report_warning("'author-separator' is deprecated.");
        if(!trim($value)) {
          $this->forget($param_name);
        } else {
          echo " Please fix manually.";
        }
      return FALSE;
      
      ### DATE AND YEAR ###
      
      case "date":
        if (preg_match("~^\d{4}$~", sanitize_string($value))) {
          // Not adding any date data beyond the year, so 'year' parameter is more suitable
          // TODO does this still match the current usage practice?
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
        return FALSE;
      
      ### JOURNAL IDENTIFIERS ###
      
      case "issn":
        if ($this->blank("journal") && $this->blank("periodical") && $this->blank("work") && $this->blank($param_name)) {
          // Only add ISSN if journal is unspecified
          return $this->add($param_name, $value);
        }
        return FALSE;
        
      case "periodical": case "journal":
        if (in_array(strtolower(sanitize_string($this->get('journal'))), BAD_TITLES ) === TRUE) $this->forget('journal'); // Update to real data
        if ($this->blank("journal") && $this->blank("periodical")) {
          if (in_array(strtolower(sanitize_string($value)), HAS_NO_VOLUME) === TRUE) $this->forget("volume") ; // No volumes, just issues.
          if (in_array(strtolower(sanitize_string($value)), BAD_TITLES ) === TRUE) return FALSE;
          $value = wikify_external_text(title_case($value));
          if ($this->has('series') && (strcasecmp($this->get('series'), $value) === 0)) return FALSE ;
          if ($this->has('work')) {
            if (strcasecmp($this->get('work'), $value) === 0) {
              $this->rename('work', $param_name);
              return TRUE;
            } else {
              return FALSE;  // Cannot have both work and journal
            }
          }
          $this->forget('issn');
          return $this->add($param_name, $value);
        }
        return FALSE;
        
      case 'series':
        if ($this->blank($param_name)) {
          $value = wikify_external_text($value);
          if ($this->has('journal') && (strcasecmp($this->get('journal'), $value) === 0)) return FALSE;
          return $this->add($param_name, $value);
        }
        return FALSE;

      case 'chapter': case 'contribution':
        if ($this->blank("chapter") && $this->blank("contribution")) {
          return $this->add($param_name, wikify_external_text($value));
        }
        return FALSE;
      
      
      ###  ARTICLE LOCATORS  ###
      ### (page, volume etc) ###
      
      case 'title':
        if (in_array(strtolower(sanitize_string($value)), BAD_TITLES ) === TRUE) return FALSE;
        if ($this->blank($param_name)) {
          return $this->add($param_name, wikify_external_text($value));
        }
        return FALSE;
      
      case 'volume':
        if ($this->blank($param_name)) {
          $temp_string = strtolower($this->get('journal')) ;
          if(substr($temp_string, 0, 2) === "[[" && substr($temp_string, -2) === "]]") {  // Wikilinked journal title 
               $temp_string = substr(substr($temp_string, 2), 0, -2); // Remove [[ and ]]
          }
          if (in_array($temp_string, HAS_NO_VOLUME) === TRUE ) {
            // This journal has no volume.  This is really the issue number
            return $this->add_if_new('issue', $value);
          } else {
            return $this->add($param_name, $value);
          }
        }
      return FALSE;      
      
      case 'issue':
        if ($this->blank("issue") && $this->blank("number")) {        
          return $this->add($param_name, $value);
        } 
      return FALSE;
      
      case "page": case "pages":
        if (( $this->blank("pages") && $this->blank("page") && $this->blank("pp")  && $this->blank("p"))
                || strpos(strtolower($this->get('pages') . $this->get('page')), 'no') !== FALSE
                || (strpos($value, chr(2013)) || (strpos($value, '-'))
                  && !strpos($this->get('pages'), chr(2013))
                  && !strpos($this->get('pages'), chr(150)) // Also en-dash
                  && !strpos($this->get('pages'), chr(226)) // Also en-dash
                  && !strpos($this->get('pages'), '-')
                  && !strpos($this->get('pages'), '&ndash;'))
        ) {
            if ($param_name !== "pages") $this->forget("pages"); // Forget others -- sometimes we upgrade page=123 to pages=123-456
            if ($param_name !== "page")$this->forget("page");
            if ($param_name !== "pp")$this->forget("pp");
            if ($param_name !== "p")$this->forget("p");
            $param_key = $this->get_param_key($param_name);
            if (!is_null($param_key)) {
              $this->param[$param_key]->val = sanitize_string($value); // Minimize template changes (i.e. location) when upgrading from page=123 to pages=123-456
              return TRUE;
            } else {
              return $this->add($param_name, sanitize_string($value));
            }
        }
        return FALSE;
        
        
      ###  ARTICLE IDENTIFIERS  ###
      ### arXiv, DOI, PMID etc. ###
      
      case 'url': 
        // look for identifiers in URL - might be better to add a PMC parameter, say
        if (!$this->get_identifiers_from_url($value) && $this->blank($param_name) && $this->blank('title-link') && $this->blank('titlelink')) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;
        
      case 'title-link':
        if ($this->blank('title-link') && $this->blank('titlelink') && $this->blank('url')) {
          return $this->add($param_name, $value); // We do not sanitize this, since it is not new data
        }
        return FALSE;
        
      case 'class':
        if ($this->blank($param_name) && strpos($this->get('eprint') . $this->get('arxiv'), '/') === FALSE ) { // Old eprints include class in the ID
          if ($this->wikiname() === 'citation' || $this->wikiname() === 'cite arxiv') {  // Only relevent for cite arxiv
            return $this->add($param_name, sanitize_string($value));
          }
        }
        return FALSE;
        
      case 'doi':
        if ($this->blank($param_name) && preg_match(DOI_REGEXP, $value, $match)) {
          $this->add('doi', $match[0]);
          $this->expand_by_doi();
          
          return TRUE;
        }
        return FALSE;
      
      case 'arxiv':
        if ($this->blank($param_name)) {
          $this->add('arxiv', $value);
          $this->expand_by_arxiv();
          return TRUE;
        }
        return FALSE;
        
      case 'doi-broken-date':
        if ($this->blank('doi_brokendate') &&
            $this->blank('doi-broken-date') &&
            $this->blank('doi_inactivedate') &&
            $this->blank('doi-inactive-date')) {
          return $this->add($param_name, $value);
        }
      return FALSE;
      
      case 'pmid':
        if ($value === 0 || $value === "0" ) return FALSE;  // Got PMID of zero once from pubmed
        if ($this->blank($param_name)) {
          $this->add($param_name, sanitize_string($value));
          $this->expand_by_pubmed($this->blank('pmc') || $this->blank('doi'));  //Force = TRUE if missing DOI or PMC
          $this->get_doi_from_crossref();
          return TRUE;
        }
      return FALSE;

      case 'pmc':
        if ($value === 0 || $value === "PMC0" || $value === "0" ) return FALSE;  // Got PMID of zero once from pubmed
        if ($this->blank($param_name)) {
          $this->add($param_name, sanitize_string($value));
          return TRUE;
        }
      return FALSE;
      
      case 'bibcode':
        if ($this->blank($param_name)) { 
          $bibcode_pad = 19 - strlen($value);
          if ($bibcode_pad > 0) {  // Paranoid, don't want a negative value, if bibcodes get longer
            $value = $value . str_repeat( ".", $bibcode_pad);  // Add back on trailing periods
          }
          $this->add($param_name, $value);
          $this->expand_by_adsabs();
          return TRUE;
        } 
      return FALSE;
      
      case 'isbn';
        if ($this->blank($param_name)) { 
          $value = $this->isbn10Toisbn13($value);
          return $this->add($param_name, $value);
        }
      return FALSE;
      
      ### POSTSCRIPT... ###
      case 'postscript':
        if ($this->blank($param_name)) {
          return $this->add($param_name, $value);
        }
      return FALSE;

      case 'asin':
        if ($this->blank($param_name)) {
          if($this->has('isbn')) { // Already have ISBN
            quietly('report_inaction', "Not adding ASIN: redundant to existing ISBN.");
            return FALSE;
          } elseif (preg_match("~^\d~", $value) && substr($value, 0, 3) !== '630') { // 630 ones are not ISBNs
            $possible_isbn = sanitize_string($value);
            $possible_isbn13 = $this->isbn10Toisbn13($possible_isbn);
            if ($possible_isbn === $possible_isbn13) {
              return $this->add('asin', $possible_isbn); // Something went wrong, add as ASIN
            } else {
              return $this->add('isbn', $possible_isbn13);
            }
          } else {  // NOT ISBN
            return $this->add($param_name, sanitize_string($value));
          }
        }
        return FALSE;
      
      default:
        if ($this->blank($param_name)) {
          return $this->add($param_name, sanitize_string($value));
        }
    }
  }

  // This is also called when adding a URL with add_if_new, in which case
  // it looks for a parameter before adding the url.
  protected function get_identifiers_from_url($url_sent = NULL) {
    if (is_null($url_sent)) {
      if ($this->blank('url')) {
        if ($this->has('website')) { // No URL, but a website
          $url = trim($this->get('website'));
          if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
            $url = "h" . $url;
          }
          if (strtolower(substr( $url, 0, 4 )) !== "http" ) {
            $url = "http://" . $url; // Try it with http
          }
          if (filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED) === FALSE) return FALSE; // PHP does not like it
          $pattern = '_^(?:(?:https?|ftp)://)(?:\\S+(?::\\S*)?@)?(?:(?!10(?:\\.\\d{1,3}){3})(?!127(?:\\.\\d{1,3}){3})(?!169\\.254(?:\\.\\d{1,3}){2})(?!192\\.168(?:\\.\\d{1,3}){2})(?!172\\.(?:1[6-9]|2\\d|3[0-1])(?:\\.\\d{1,3}){2})(?:[1-9]\\d?|1\\d\\d|2[01]\\d|22[0-3])(?:\\.(?:1?\\d{1,2}|2[0-4]\\d|25[0-5])){2}(?:\\.(?:[1-9]\\d?|1\\d\\d|2[0-4]\\d|25[0-4]))|(?:(?:[a-z\\x{00a1}-\\x{ffff}0-9]+-?)*[a-z\\x{00a1}-\\x{ffff}0-9]+)(?:\\.(?:[a-z\\x{00a1}-\\x{ffff}0-9]+-?)*[a-z\\x{00a1}-\\x{ffff}0-9]+)*(?:\\.(?:[a-z\\x{00a1}-\\x{ffff}]{2,})))(?::\\d{2,5})?(?:/[^\\s]*)?$_iuS';
          if (preg_match ($pattern, $url) !== 1) return FALSE;  // See https://mathiasbynens.be/demo/url-regex/  This regex is more exact than validator.  We only spend time on this after quick and dirty check is passed
          $this->rename('website', 'url'); // Rename it first, so that parameters stay in same order
          $this->set('url', $url);
          quietly('report_modification', "website is actually HTTP URL; converting to use url parameter.");
        } else {
          // If no URL or website, nothing to worth with.
          return FALSE;
        }
      }
      
      $url = $this->get('url'); // If URL was blank, we would have returned already.
    } else {
      $url = $url_sent;
    }
    
    if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
      $url = "h" . $url;
      if (is_null($url_sent)) {
        $this->set('url', $url); // Save it
      }
    }
    
    // JSTOR
    if (strpos($url, "jstor.org") !== FALSE) {
      $sici_pos = strpos($url, "sici");
      if ($sici_pos) {  //  Outdated url style
        $this->use_sici(); // Grab what we can before getting rid off it
        // Need to encode the sici bit that follows sici?sici= [10 characters]
        $encoded_url = substr($url, 0, $sici_pos + 10) . urlencode(urldecode(substr($url, $sici_pos + 10)));
        $ch = curl_init($encoded_url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);      
        if (curl_exec($ch) !== FALSE) {
          $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
          if (strpos($redirect_url, "jstor.org/stable/")) {
            $url = $redirect_url; 
            if (is_null($url_sent)) {
              $this->set('url', $url); // Save it
            }
          } else {
            return FALSE;  // We do not want this URL incorrectly parsed below, or even waste time trying.
          }
        }
      }
      if (strpos($url, "plants.jstor.org")) {
        return FALSE; # Plants database, not journal
      } elseif (preg_match("~(?|(\d{6,})$|(\d{6,})[^\d%\-])~", $url, $match)) {
        if (is_null($url_sent)) {
          $this->forget('url');
        }
        if ($this->get('jstor')) {
          quietly('report_inaction', "Not using redundant URL (jstor parameter set)");
        } else {
          quietly('report_modification', "Converting URL to JSTOR parameter");
          $this->set("jstor", urldecode($match[1]));
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
        return TRUE;
      } else {
        return FALSE; // Jstor URL yielded nothing
      }
    } else {
      if (preg_match(BIBCODE_REGEXP, urldecode($url), $bibcode)) {
        if ($this->blank('bibcode')) {
          quietly('report_modification', "Converting url to bibcode parameter");
          if (is_null($url_sent)) {
            $this->forget('url');
          }
          return $this->add_if_new("bibcode", urldecode($bibcode[1]));
        }
        
      } elseif (preg_match("~^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                      . "|^https?://www\.ncbi\.nlm\.nih\.gov/pmc/articles/PMC(\d+)~", $url, $match)) {
                        
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
        if ($this->blank('pmc')) {
          quietly('report_modification', "Converting URL to PMC parameter");
          if (is_null($url_sent)) {
            $this->forget('url');
          }
          return $this->add_if_new("pmc", $match[1] . $match[2]);
        }
      } elseif (preg_match("~^https?://europepmc\.org/articles/pmc(\d+)~", $url, $match)) {
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
        if ($this->blank('pmc')) {
          quietly('report_modification', "Converting Europe URL to PMC parameter");
          if (is_null($url_sent)) {
            $this->forget('url');
          }
          return $this->add_if_new("pmc", $match[1]);
        }
      } elseif (preg_match("~^https?://d?x?\.?doi\.org/([^\?]*)~", $url, $match)) {
        quietly('report_modification', "URL is hard-coded DOI; converting to use DOI parameter.");
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
        if (is_null($url_sent)) {
          $this->forget('url');
        }
        return $this->add_if_new("doi", urldecode($match[1])); // Will expand from DOI when added
      } elseif(preg_match("~^https?://citeseerx\.ist\.psu\.edu/viewdoc/summary\?doi=([0-9.]*)~", $url, $match)) {
        quietly('report_modification', "URL is hard-coded citeseerx; converting to use citeseerx parameter.");
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
        if (is_null($url_sent)) {
          $this->forget('url');
        }
        return $this->add_if_new("citeseerx", urldecode($match[1])); // We cannot parse these at this time
        
      } elseif (extract_doi($url)[1]) {
        if (is_null($url_sent)) {
          quietly('report_forget', "Recognized DOI in URL; dropping URL");
          $this->forget('url');
        }
        return $this->add_if_new('doi', extract_doi($url)[1]);
        
      } elseif (preg_match("~\barxiv\.org/.*(?:pdf|abs)/(.+)$~", $url, $match)) {
        
        /* ARXIV
         * See https://arxiv.org/help/arxiv_identifier for identifier formats
         */
        if (   preg_match("~[A-z\-\.]+/\d{7}~", $match[1], $arxiv_id) // pre-2007
            || preg_match("~\d{4}\.\d{4,5}(?:v\d+)?~", $match[1], $arxiv_id) // post-2007
            ) {
          quietly('report_modification', "Converting URL to arXiv parameter");
          if (is_null($url_sent)) {
            $this->forget('url');
          }
          return $this->add_if_new("arxiv", $arxiv_id[0]);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite arxiv';
        
      } elseif (preg_match("~https?://www.ncbi.nlm.nih.gov/pubmed/.*?=?(\d{6,})~", $url, $match)) {
        quietly('report_modification', "Converting URL to PMID parameter");
        if (is_null($url_sent)) {
          $this->forget('url');
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
        return $this->add_if_new('pmid', $match[1]);
        
      } elseif (preg_match("~^https?://www\.amazon(?P<domain>\.[\w\.]{1,7})/.*dp/(?P<id>\d+X?)~", $url, $match)) {
        
        if (strpos($this->name, 'web')) $this->name = 'Cite book';
        if ($match['domain'] == ".com") {
          if (is_null($url_sent)) {
            $this->forget('url');
          }
          if ($this->blank('asin')) {
            quietly('report_modification', "Converting URL to ASIN parameter");
            return $this->add_if_new('asin', $match['id']);
          }
        } else {
          quietly('report_modification', "Converting URL to ASIN template");
          $this->set('id', $this->get('id') . " {{ASIN|{$match['id']}|country=" . str_replace(array(".co.", ".com.", "."), "", $match['domain']) . "}}");
          if (is_null($url_sent)) {
            $this->forget('url'); // will forget accessdate too
          }
        }
      } elseif (preg_match("~^https?://hdl\.handle\.net/([^\?]*)~", $url, $match)) {
          quietly('report_modification', "Converting URL to HDL parameter");
          if (is_null($url_sent)) {
             $this->forget('url');
          }
          if (preg_match("~\bweb\b~", $this->name)) $this->name = 'Cite journal';  // Better template choice.  Often journal/paper
          return $this->add_if_new('hdl', $match[1]);
      } elseif (preg_match("~^https?://zbmath\.org/\?format=complete&q=an:([0-9][0-9][0-9][0-9]\.[0-9][0-9][0-9][0-9][0-9])~", $url, $match)) {
          quietly('report_modification', "Converting URL to ZBL parameter");
          if (is_null($url_sent)) {
             $this->forget('url');
          }
          if (preg_match("~\bweb\b~", $this->name)) $this->name = 'Cite journal';  // Better template choice.  Often journal/paper
          return $this->add_if_new('zbl', $match[1]);
      } elseif (preg_match("~^https?://zbmath\.org/\?format=complete&q=an:([0-9][0-9]\.[0-9][0-9][0-9][0-9]\.[0-9][0-9])~", $url, $match)) {
          quietly('report_modification', "Converting URL to JFM parameter");
          if (is_null($url_sent)) {
             $this->forget('url');
          }
          if (preg_match("~\bweb\b~", $this->name)) $this->name = 'Cite journal';  // Better template choice.  Often journal/paper
          return $this->add_if_new('jfm', $match[1]);
      } elseif (preg_match("~^https?://mathscinet\.ams\.org/mathscinet-getitem\?mr=([0-9]+)~", $url, $match)) {
          quietly('report_modification', "Converting URL to MR parameter");
          if (is_null($url_sent)) {
             $this->forget('url');
          }
          if (preg_match("~\bweb\b~", $this->name)) $this->name = 'Cite journal';  // Better template choice.  Often journal/paper
          return $this->add_if_new('mr', $match[1]);
      } elseif (preg_match("~^https?://papers\.ssrn\.com/sol3/papers\.cfm\?abstract_id=([0-9]+)~", $url, $match)) {
          quietly('report_modification', "Converting URL to SSRN parameter");
          if (is_null($url_sent)) {
             $this->forget('url');
          }
          if (preg_match("~\bweb\b~", $this->name)) $this->name = 'Cite journal';  // Better template choice.  Often journal/paper
          return $this->add_if_new('ssrn', $match[1]);
      } elseif (preg_match("~^https?://www\.osti\.gov/biblio/([0-9]+)~", $url, $match)) {
          quietly('report_modification', "Converting URL to OSTI parameter");
          if (is_null($url_sent)) {
             $this->forget('url');
          }
          if (preg_match("~\bweb\b~", $this->name)) $this->name = 'Cite journal';  // Better template choice.  Often journal/paper
          return $this->add_if_new('osti', $match[1]);
      } elseif (preg_match("~^https?://www\.osti\.gov/energycitations/product\.biblio\.jsp\?osti_id=([0-9]+)~", $url, $match)) {
          quietly('report_modification', "Converting URL to OSTI parameter");
          if (is_null($url_sent)) {
             $this->forget('url');
          }
          if (preg_match("~\bweb\b~", $this->name)) $this->name = 'Cite journal';  // Better template choice.  Often journal/paper
          return $this->add_if_new('osti', $match[1]);
      }
    }
    return FALSE ;
  }

  protected function get_doi_from_text() {
    if ($this->blank('doi') && preg_match('~10\.\d{4}/[^&\s\|\}\{]*~', urldecode($this->parsed_text()), $match))
      // Search the entire citation text for anything in a DOI format.
      // This is quite a broad match, so we need to ensure that no baggage has been tagged on to the end of the URL.
      $this->add_if_new('doi', preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]));
  }

  public function get_doi_from_crossref() { #TODO test
    if ($doi = $this->get('doi')) {
      return $doi;
    }
    report_action("Checking CrossRef database for doi. " . tag());
    $title = $this->get('title');
    $journal = $this->get('journal');
    $author = $this->first_surname();
    $year = $this->get('year');
    $volume = $this->get('volume');
    $page_range = $this->page_range();
    $start_page = isset($page_range[1]) ? $page_range[1] : NULL;
    $end_page   = isset($page_range[2]) ? $page_range[2] : NULL;
    $issn = $this->get('issn');
    $url1 = trim($this->get('url'));
    $input = array($title, $journal, $author, $year, $volume, $start_page, $end_page, $issn, $url1);
    global $priorP;
    if ($input == $priorP['crossref']) {
      report_info("Data not changed since last CrossRef search." . tag());
      return FALSE;
    } else {
      $priorP['crossref'] = $input;
      if ($journal || $issn) {
        $url = "https://www.crossref.org/openurl/?noredirect=TRUE&pid=" . CROSSREFUSERNAME
             . ($title ? "&atitle=" . urlencode(de_wikify($title)) : "")
             . ($author ? "&aulast=" . urlencode($author) : '')
             . ($start_page ? "&spage=" . urlencode($start_page) : '')
             . ($end_page > $start_page ? "&epage=" . urlencode($end_page) : '')
             . ($year ? "&date=" . urlencode(preg_replace("~([12]\d{3}).*~", "$1", $year)) : '')
             . ($volume ? "&volume=" . urlencode($volume) : '')
             . ($issn ? "&issn=$issn" : ($journal ? "&title=" . urlencode(de_wikify($journal)) : ''));
        if (!($result = @simplexml_load_file($url)->query_result->body->query)){
          report_warning("Error loading simpleXML file from CrossRef.");
        }
        elseif ($result['status'] == 'malformed') {
          report_warning("Cannot search CrossRef: " . echoable($result->msg));
        }
        elseif ($result["status"] == "resolved") {
          return $result;
        }
      }
      if (FAST_MODE || !$author || !($journal || $issn) || !$start_page ) return;
      // If fail, try again with fewer constraints...
      report_info("Full search failed. Dropping author & end_page... ");
      $url = "https://www.crossref.org/openurl/?noredirect=TRUE&pid=" . CROSSREFUSERNAME;
      if ($title) $url .= "&atitle=" . urlencode(de_wikify($title));
      if ($issn) $url .= "&issn=$issn"; elseif ($journal) $url .= "&title=" . urlencode(de_wikify($journal));
      if ($year) $url .= "&date=" . urlencode($year);
      if ($volume) $url .= "&volume=" . urlencode($volume);
      if ($start_page) $url .= "&spage=" . urlencode($start_page);
      if (!($result = @simplexml_load_file($url)->query_result->body->query)) {
        report_warning("Error loading simpleXML file from CrossRef." . tag());
      }
      elseif ($result['status'] == 'malformed') {
        report_warning("Cannot search CrossRef: " . echoable($result->msg));
      } elseif ($result["status"]=="resolved") {
        echo " Successful!";
        return $result;
      }
    }
  }

  public function find_pmid() {
    if (!$this->blank('pmid')) return;
    report_action("Searching PubMed... " . tag());
    $results = ($this->query_pubmed());
    if ($results[1] == 1) {
      $this->add_if_new('pmid', $results[0]);
    } else {
      echo " nothing found.";
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
    if ($doi = $this->get_without_comments_and_placeholders('doi')) {
      $results = $this->do_pumbed_query(array("doi"), TRUE);
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

  protected function do_pumbed_query($terms, $check_for_errors = FALSE) {
  /* do_query
   *
   * Searches pubmed based on terms provided in an array.
   * Provide an array of wikipedia parameters which exist in $p, and this function will construct a Pubmed seach query and
   * return the results as array (first result, # of results)
   * If $check_for_errors is TRUE, it will return 'false' on errors returned by pubmed
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
        if ($key === "AID") {
           $query .= " AND (" . "\"" . str_replace("%E2%80%93", "-", ($val)) . "\"" . "[$key])"; // Do not escape DOIs
        } else {
           $query .= " AND (" . "\"" . str_replace("%E2%80%93", "-", urlencode($val)) . "\"" . "[$key])";
        }
      }
    }
    $query = substr($query, 5);
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&tool=DOIbot&email=martins+pubmed@gmail.com&term=$query";
    $xml = @simplexml_load_file($url);
    if ($xml === FALSE) {
      report_warning("Unable to do PMID search");
      return array(NULL, 0);
    }
    if ($check_for_errors && $xml->ErrorList) {
      echo $xml->ErrorList->PhraseNotFound
              ? " no results."
              : "\n - Errors detected in PMID search (" . echoable(print_r($xml->ErrorList, 1)) . "); abandoned.";
      return array(NULL, 0);
    }

    return $xml ? array((string)$xml->IdList->Id[0], (string)$xml->Count) : array(NULL, 0);// first results; number of results
  }

  public function expand_by_arxiv() {
    if ($this->wikiname() == 'cite arxiv') {
      $arxiv_param = 'eprint';
      $this->rename('arxiv', 'eprint');
    } else {
      $arxiv_param = 'arxiv';
      $this->rename('eprint', 'arxiv');
    }
    $eprint = str_ireplace("arXiv:", "", $this->get('eprint') . $this->get('arxiv'));
    $class = $this->get('class');
    $this->set($arxiv_param, $eprint);
    if ($class && stripos($eprint, '/') === FALSE) $eprint = $class . '/' . $eprint;

    if ($eprint) {
      report_action("Getting data from arXiv " . echoable($eprint));
      $context = stream_context_create(array(
        'http' => array('ignore_errors' => true),
      ));
      $arxiv_request = "https://export.arxiv.org/api/query?start=0&max_results=1&id_list=$eprint";
      $arxiv_response = @file_get_contents($arxiv_request, FALSE, $context);
      if ($arxiv_response) {
        $xml = @simplexml_load_string(
          preg_replace("~(</?)(\w+):([^>]*>)~", "$1$2$3", $arxiv_response)
        ); // TODO Explore why this is often failing
      } else {
        report_warning("No response from arXiv.");
        return FALSE;
      }
    }
    
    if ($xml) {
      if ((string)$xml->entry->title === "Error") {
        report_warning("arXiv search failed; please report error: " . (string)$xml->entry->summary);
        return FALSE;
      }
      $i = 0;
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
      $this->add_if_new("title", (string) $xml->entry->title); // Formatted by add_if_new
      $this->add_if_new("class", (string) $xml->entry->category["term"]);
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
          if (isset($match[2])) {
            $this->add_if_new("issue", $match[2]);
          }
          $journal_data = preg_replace("~[\s:,;]*$~", "",
                  str_replace($match[-0], "", $journal_data));
        }
        $this->add_if_new("journal", wikify_external_text($journal_data));
      } else {
        $this->add_if_new("year", date("Y", strtotime((string)$xml->entry->published)));
      }
      return TRUE;
    }
    return FALSE;
  }

  public function expand_by_adsabs() {
    // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/search.md
    global $SLOW_MODE;
    if ($SLOW_MODE || $this->has('bibcode')) {
      report_action("Checking AdsAbs database");
      if ($bibcode = $this->has('bibcode')) {
        $result = $this->query_adsabs("bibcode:" . urlencode('"' . $this->get("bibcode") . '"'));
      } elseif ($this->has('doi') 
                && preg_match(DOI_REGEXP, $this->get_without_comments_and_placeholders('doi'), $doi)) {
        $result = $this->query_adsabs("doi:" . urlencode('"' . $doi[0] . '"'));
      } elseif ($this->has('title') || $this->has('eprint') || $this->has('arxiv')) {
        if ($this->has('eprint')) {
          $result = $this->query_adsabs("arXiv:" . urlencode('"' .$this->get('eprint') . '"'));
        } elseif ($this->has('arxiv')) {
          $result = $this->query_adsabs("arXiv:" . urlencode('"' .$this->get('arxiv') . '"'));
        } else {
          $result = (object) array("numFound" => 0);
        }
        if (($result->numFound != 1) && $this->has('title')) { // Do assume failure to find arXiv means that it is not there
          $result = $this->query_adsabs("title:" . urlencode('"' .  $this->get("title") . '"'));
          if ($result->numFound == 0) return FALSE;
          $record = $result->docs[0];
          $inTitle = str_replace(array(" ", "\n", "\r"), "", (mb_strtolower((string) $record->title[0])));
          $dbTitle = str_replace(array(" ", "\n", "\r"), "", (mb_strtolower($this->get('title'))));
          if (
             (strlen($inTitle) > 254 || strlen($dbTitle) > 254)
                ? (strlen($inTitle) != strlen($dbTitle)
                  || similar_text($inTitle, $dbTitle) / strlen($inTitle) < 0.98)
                : levenshtein($inTitle, $dbTitle) > 3
            ) {
            report_info("Similar title not found in database");
            return FALSE;
          }
        }
      } else {
        $result = (object) array("numFound" => 0);
      }
      if ($result->numFound != 1 && $this->has('journal')) {
        $journal = $this->get('journal');
        // try partial search using bibcode components:
        $result = $this->query_adsabs("pub:" . urlencode('"' . remove_brackets($journal) . '"')
          . ($this->has('year') ? ("&year:" . urlencode($this->get('year'))) : '')
          . ($this->has('issn') ? ("&issn:" . urlencode($this->get('issn'))) : '')
          . ($this->has('volume') ? ("&volume:" . urlencode('"' . $this->get('volume') . '"')) : '')
          . ($this->page() ? ("&page:" . urlencode('"' . $this->page() . '"')) : '')
        );
        if ($result->numFound == 0) return FALSE;
        if (!isset($result->docs[0]->pub)) return FALSE;
        $journal_string = explode(",", (string) $result->docs[0]->pub);
        $journal_fuzzyer = "~\bof\b|\bthe\b|\ba\beedings\b|\W~";
        if (strlen($journal_string[0]) 
        &&  strpos(mb_strtolower(preg_replace($journal_fuzzyer, "", $journal)),
                   mb_strtolower(preg_replace($journal_fuzzyer, "", $journal_string[0]))
                   ) === FALSE
        ) {
          report_info("Match for pagination but database journal \"" .
            echoable($journal_string[0]) . "\" didn't match \"" .
            echoable($journal) . "\"." . tag());
          return FALSE;
        }
      }
      if ($result->numFound == 1) {
        $record = $result->docs[0];
        echo tag();
        if ($this->blank('bibcode')) $this->add('bibcode', (string) $record->bibcode); // not add_if_new or we'll repeat this search!
        $this->add_if_new("title", (string) $record->title[0]); // add_if_new will format the title text and check for unknown
        $i = 0;
        if (isset($record->author)) {
         foreach ($record->author as $author) {
          $this->add_if_new("author" . ++$i, $author);
         }
        }
        if (isset($record->pub)) {
          $journal_string = explode(",", (string) $record->pub);
          $journal_start = mb_strtolower($journal_string[0]);
          if (preg_match("~\bthesis\b~ui", $journal_start)) {
            // Do nothing
          } elseif (substr($journal_start, 0, 6) == "eprint") {
            if (substr($journal_start, 7, 6) == "arxiv:") {
              if (isset($record->arxivclass)) $this->add_if_new("class", $record->arxivclass);
              if ($this->add_if_new("arxiv", substr($journal_start, 13))) $this->expand_by_arxiv();
            } else {
              $this->append_to('id', ' ' . substr($journal_start, 13));
            }
          } else {
            $this->add_if_new('journal', $journal_string[0]);
          }          
        }
        if (isset($record->page) && (stripos(implode('–', $record->page), 'arxiv') !== FALSE)) {  // Bad data
           unset($record->page);
           unset($record->volume);
           unset($record->issue);
        }
        if (isset($record->volume)) {
          $this->add_if_new("volume", (string) $record->volume);
        }
        if (isset($record->issue)) {
          $this->add_if_new("issue", (string) $record->issue);
        }
        if (isset($record->year)) {
          $this->add_if_new("year", preg_replace("~\D~", "", (string) $record->year));
        }
        if (isset($record->page)) {
          $this->add_if_new("pages", implode('–', $record->page));
        }
        if (isset($record->identifier)) { // Sometimes arXiv is in journal (see above), sometimes here in identifier
          foreach ($record->identifier as $recid) {
            if(strtolower(substr($recid, 0, 6)) === 'arxiv:') {
               if (isset($record->arxivclass)) $this->add_if_new("class", $record->arxivclass);
               if ($this->add_if_new("arxiv", substr($recid, 6))) $this->expand_by_arxiv();
            }
          }
        }
        if (isset($record->doi) && $this->add_if_new('doi', (string) $record->doi[0])) {
          $this->expand_by_doi();
        }
        return TRUE;
      } else {
        echo ": no record retrieved." . tag();
        return FALSE;
      }
    } else {
       report_info("Skipping AdsAbs database: not in slow mode" . tag());
       return FALSE;
    }
  }
  
  // $options should be a series of field names, colons (optionally urlencoded), and
  // URL-ENCODED search strings, separated by (unencoded) ampersands.
  // Surround search terms in (url-encoded) ""s, i.e. doi:"10.1038/bla(bla)bla"
  protected function query_adsabs($options) {  
    // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/Search_API.ipynb
    
    if (!getenv('PHP_ADSABSAPIKEY')) {
      report_warning("PHP_ADSABSAPIKEY environment variable not set. Cannot query AdsAbs.");
      return (object) array('numFound' => 0);
    }
    
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . getenv('PHP_ADSABSAPIKEY')));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, TRUE);
      $adsabs_url = "https://api.adsabs.harvard.edu/v1/search/query"
                  . "?q=$options&fl=arxiv_class,author,bibcode,doi,doctype,identifier,"
                  . "issue,page,pub,pubdate,title,volume,year";
      curl_setopt($ch, CURLOPT_URL, $adsabs_url);
      if (getenv('TRAVIS')) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Delete once Travis CI recompile their PHP binaries
      }
      $return = curl_exec($ch);
      if ($return === FALSE) {
        throw new Exception(curl_error($ch), curl_errno($ch));
      }
      $http_response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $header_length = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
      curl_close($ch);
      $header = substr($return, 0, $header_length);
      $body = substr($return, $header_length);
      $decoded = @json_decode($body);
      
      if (is_object($decoded) && isset($decoded->error)) {
        if (is_object($decoded) && isset($decoded->error)) {
          throw new Exception(
          ((isset($decoded->error->msg)) ? $decoded->error->msg : $decoded->error)
          . "\n - URL was:  " . $adsabs_url,
          (isset($decoded->error->code) ? $decoded->error->code : 999));
        }
      }
      if ($http_response != 200) {
        throw new Exception(strtok($header, "\n"), $http_response);
      }
      
      if (preg_match_all('~\nX\-RateLimit\-(\w+):\s*(\d+)\r~i', $header, $rate_limit)) {
        if ($rate_limit[2][2]) {
          report_info("AdsAbs search " . ($rate_limit[2][0] - $rate_limit[2][1]) . "/" . $rate_limit[2][0] .
               ":\n       " . str_replace("&", "\n       ", urldecode($options)));
               // "; reset at " . date('r', $rate_limit[2][2]);
        } else {
          report_warning("AdsAbs daily search limit exceeded. Retry at " . date('r', $rate_limit[2][2]) . "\n");
          return (object) array('numFound' => 0);
        }
      } else {
        throw new Exception("Headers do not contain rate limit information:\n" . $header, 5000);
      }
      if (!is_object($decoded)) {
        throw new Exception("Could not decode API response:\n" . $body, 5000);
      }
      
      if (isset($decoded->response)) {
        $response = $decoded->response;
      } else {
        if ($decoded->error) throw new Exception("" . $decoded->error, 5000); // "". to force string
        throw new Exception("Could not decode AdsAbs response", 5000);
      }
      return $response;
    } catch (Exception $e) {
      if ($e->getCode() == 5000) { // made up code for AdsAbs error
        trigger_error(sprintf("API Error in query_adsabs: %s",
                      $e->getMessage()), E_USER_NOTICE);
      } else if (strpos($e->getMessage(), 'HTTP') === 0) {
        trigger_error(sprintf("HTTP Error %d in query_adsabs: %s",
                      $e->getCode(), $e->getMessage()), E_USER_NOTICE);
      } else {
        trigger_error(sprintf("Error %d in query_adsabs: %s",
                      $e->getCode(), $e->getMessage()), E_USER_WARNING);
        curl_close($ch);
      }
      return (object) array('numFound' => 0);
    }
  }
  
  public function expand_by_doi($force = FALSE) {
    $doi = $this->get_without_comments_and_placeholders('doi');
    if (!$this->verify_doi()) return FALSE;
    if ($doi && preg_match('~^10\.2307/(\d+)$~', $doi)) {
        $this->add_if_new('jstor', substr($doi, 8));
    }
    if ($doi && ($force || $this->incomplete())) {
      $crossRef = $this->query_crossref($doi);
      if ($crossRef) {
        if (in_array(strtolower($crossRef->article_title), BAD_ACCEPTED_MANUSCRIPT_TITLES)) return FALSE ;
        report_action("Expanding from crossRef record" . tag());

        if ($crossRef->volume_title && $this->blank('journal')) {
          $this->add_if_new('chapter', $crossRef->article_title); // add_if_new formats this value as a title
          if (strtolower($this->get('title')) == strtolower($crossRef->article_title)) {
            $this->forget('title');
          }
          $this->add_if_new('title', restore_italics($crossRef->volume_title)); // add_if_new will wikify title and sanitize the string
        } else {
          $this->add_if_new('title', restore_italics($crossRef->article_title)); // add_if_new will wikify title and sanitize the string
        }
        $this->add_if_new('series', $crossRef->series_title); // add_if_new will format the title for a series?
        $this->add_if_new("year", $crossRef->year);
        if (   $this->blank(array('editor', 'editor1', 'editor-last', 'editor1-last')) // If editors present, authors may not be desired
            && $crossRef->contributors->contributor
          ) {
          $au_i = 0;
          $ed_i = 0;
          // Check to see whether a single author is already set
          // This might be, for example, a collaboration
          $existing_author = $this->first_author();
          $add_authors = is_null($existing_author)
                      || $existing_author = ''
                      || author_is_human($existing_author);
          
          foreach ($crossRef->contributors->contributor as $author) {
            if ($author["contributor_role"] == 'editor') {
              ++$ed_i;
              if ($ed_i < 31 && $crossRef->journal_title === NULL) {
                $this->add_if_new("editor$ed_i-last", format_surname($author->surname));
                $this->add_if_new("editor$ed_i-first", format_forename($author->given_name));
              }
            } elseif ($author['contributor_role'] == 'author' && $add_authors) {
              ++$au_i;
              $this->add_if_new("last$au_i", format_surname($author->surname));
              $this->add_if_new("first$au_i", format_forename($author->given_name));
            }
          }
        }
        $this->add_if_new('isbn', $crossRef->isbn);
        $this->add_if_new('journal', $crossRef->journal_title); // add_if_new will format the title
        if ($crossRef->volume > 0) $this->add_if_new('volume', $crossRef->volume);
        if ((integer) $crossRef->issue > 1) {
        // "1" may refer to a journal without issue numbers,
        //  e.g. 10.1146/annurev.fl.23.010191.001111, as well as a genuine issue 1.  Best ignore.
          $this->add_if_new('issue', $crossRef->issue);
        }
        if ($this->blank("page")) {
          if ($crossRef->last_page && (strcmp($crossRef->first_page, $crossRef->last_page) !== 0)) {
            $this->add_if_new("pages", $crossRef->first_page . "-" . $crossRef->last_page); //replaced by an endash later in script
          } else {
            $this->add_if_new("pages", $crossRef->first_page);
          }
        }
        echo " (ok)";
      } else {
        report_warning("No CrossRef record found for doi '" . echoable($doi) ."'; marking as broken");
        $url_test = "https://dx.doi.org/".$doi ;
        $headers_test = @get_headers($url_test, 1);
        if($headers_test !==FALSE && empty($headers_test['Location']))
                $this->add_if_new('doi-broken-date', date('Y-m-d'));  // Only mark as broken if dx.doi.org also fails to resolve
      }
    }
  }
  
  public function expand_by_jstor() {
    if ($this->incomplete() === FALSE) return FALSE;
    if ($this->blank('jstor')) return FALSE;
    $jstor = trim($this->get('jstor'));
    if (preg_match("~[^0-9]~", $jstor) === 1) return FALSE ; // Only numbers in stable jstors.  We do not want i12342 kind
    $dat=@file_get_contents('https://www.jstor.org/citation/ris/' . $jstor) ;
    if ($dat === FALSE) {
      report_info("JSTOR API returned nothing for JSTOR ". $jstor);
      return FALSE;
    }
    if (stripos($dat, 'No RIS data found for') !== FALSE) {
      report_info("JSTOR API found nothing for JSTOR ". $jstor);
      return FALSE;
    }
    $has_a_url = $this->has('url');
    $this->expand_by_RIS($dat);
    if ($this->has('url') && !$has_a_url) { // added http://www.jstor.org/stable/12345, so remove (do not use forget, since that echos)
        $pos = $this->get_param_key('url');
        unset($this->param[$pos]);
    }
    return TRUE;
  }
  
  protected function expand_by_RIS(&$dat) { // Pass by pointer to wipe this data when called from use_unnamed_params()
        $ris_review    = FALSE;
        $ris_issn      = FALSE;
        $ris_publisher = FALSE;
        $ris = explode("\n", $dat);
        $ris_authors = 0;
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
              $ris_part[1] = format_author($ris_part[1]);
              break;
            case "Y1":
              $ris_parameter = "date";
              break;
            case "PY":
              $ris_parameter = "date";
              $ris_part[1] = (preg_replace("~([\-\s]+)$~", '', str_replace('/', '-', $ris_part[1])));
              break;
            case "SP":
              $start_page = trim($ris_part[1]);
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
              $ris_parameter = FALSE; // Deal with start pages later
              break;
            case "EP":
              $end_page = trim($ris_part[1]);
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
              $ris_parameter = FALSE; // Deal with end pages later
              break;
            case "DO":
              $ris_parameter = "doi";
              break;
            case "JO":
            case "JF":
            case "T2":
              $ris_parameter = "journal";
              break;
            case "VL":
              $ris_parameter = "volume";
              break;
            case "IS":
              $ris_parameter = "issue";
              break;
            case "RI":
              $ris_review = "Reviewed work: " . trim($ris_part[1]);  // Get these from JSTOR
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
              $ris_parameter = FALSE; // Deal with review titles later
              break;
            case "SN":
              $ris_parameter = "issn";
              $ris_issn = trim($ris_part[1]);
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
              $ris_parameter = FALSE; // Deal with ISSN later
              break;
            case "UR":
              $ris_parameter = "url";
              break;
            case "PB":
              $ris_publisher = trim($ris_part[1]);  // Get these from JSTOR
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
              $ris_parameter = FALSE; // Deal with publisher later
              break;
            case "M3": case "PY": case "N1": case "N2": case "ER": case "TY": case "KW":
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat")); // Ignore these completely
            default:
              $ris_parameter = FALSE;
          }
          unset($ris_part[0]);
          if ($ris_parameter
                  && $this->add_if_new($ris_parameter, trim(implode($ris_part)))
              ) {
            $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
          }
        }
        if ($ris_review) $this->add_if_new('title', trim($ris_review));  // Do at end in case we have real title
        if (isset($start_page)) { // Have to do at end since might get end pages before start pages
          if (isset($end_page)) {
             $this->add_if_new("pages", $start_page . EN_DASH . $end_page);
          } else {
             $this->add_if_new("pages", $start_page);
          }
        }
        if($this->blank('journal')) { // doing at end avoids adding if we have journal title
          if ($ris_issn) $this->add_if_new('issn', $ris_issn);
          if ($ris_publisher) $this->add_if_new('publisher', $ris_publisher);
        }
  }
  // For information about Citoid, look at https://www.mediawiki.org/wiki/Citoid
  // For the specific implementation that we use, search fot citoid on https://en.wikipedia.org/api/rest_v1/#!/Citation/getCitation
  // This is just an API that calls the JSTOR RIS system above
  // Leave this code here, since Citoid can be used for many many things.
 /**
 * Unused
 * @codeCoverageIgnore
 */
  protected function expand_by_jstor_citoid() {
    if ($this->blank('jstor')) return FALSE;
    $jstor = $this->get('jstor');
    if (preg_match("~[^0-9]~", $jstor) === 1) return FALSE ; // Only numbers in stable jstors
    if ( !$this->incomplete()) return FALSE; // Do not hassle Citoid, if we have nothing to gain
    $json=@file_get_contents('https://en.wikipedia.org/api/rest_v1/data/citation/mediawiki/' . urlencode('http://www.jstor.org/stable/') . $jstor);
    if ($json === FALSE) {
      report_info("Citoid API returned nothing for JSTOR ". $jstor);
      return FALSE;
    }
    $data = @json_decode($json, FALSE);
    if (!isset($data) || !isset($data[0]) || !isset($data[0]->{'title'})) {
      report_info("Citoid API returned invalid json for JSTOR ". $jstor);
      return FALSE;
    }
    if (strtolower(trim($data[0]->{'title'})) === 'not found.' || strtolower(trim($data[0]->{'title'})) === 'not found') {
      report_info("Citoid API could not resolve JSTOR ". $jstor);
      return FALSE;
    }
    // Verify that Citoid did not think that this was a website and not a journal
    if (strtolower(substr(trim($data[0]->{'title'}), -9)) === ' on jstor') {
         $this->add_if_new('title', substr(trim($data[0]->{'title'}), 0, -9)); // Add the title without " on jstor"
         return FALSE; // Not really "expanded"
    }
    if ( isset($data[0]->{'title'}))            $this->add_if_new('title'  , $data[0]->{'title'});
    if ( isset($data[0]->{'issue'}))            $this->add_if_new('issue'  , $data[0]->{'issue'});
    if ( isset($data[0]->{'pages'}))            $this->add_if_new('pages'  , $data[0]->{'pages'});
    if ( isset($data[0]->{'publicationTitle'})) $this->add_if_new('journal', $data[0]->{'publicationTitle'});
    if ( isset($data[0]->{'volume'}))           $this->add_if_new('volume' , $data[0]->{'volume'});
    if ( isset($data[0]->{'date'}))             $this->add_if_new('date'   , $data[0]->{'date'});
    if ( isset($data[0]->{'DOI'}))              $this->add_if_new('doi'    , $data[0]->{'DOI'});
    $i = 0;
    while (isset($data[0]->{'author'}[$i])) {
        if ( isset($data[0]->{'author'}[$i][0])) $this->add_if_new('first' . ($i+1), $data[0]->{'author'}[$i][0]);
        if ( isset($data[0]->{'author'}[$i][1])) $this->add_if_new('last'  . ($i+1), $data[0]->{'author'}[$i][1]);
        $i++;
    }
    return TRUE;
  }

  public function expand_by_pubmed($force = FALSE) {
    if (!$force && !$this->incomplete()) return;
    if ($pm = $this->get('pmid')) {
      $identifier = 'pmid';
    } elseif ($pm = $this->get('pmc')) {
      $identifier = 'pmc';
    } else {
      return FALSE;
    }
    html_echo ("\n - Checking " . '<a href="https://www.ncbi.nlm.nih.gov/pubmed/' .
        urlencode($pm) . '" target="_blank">' .
        echoable(strtoupper($identifier) . ' ' . $pm) . "</a> for more details" .
        tag(),
        "\n - Checking " . echoable(strtoupper($identifier) . ' ' . $pm)
        . ' for more details' . tag());
    $xml = @simplexml_load_file("https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?tool=DOIbot&email=martins@gmail.com&db=" . (($identifier == "pmid")?"pubmed":"pmc") . "&id=" . urlencode($pm));
    if ($xml === FALSE) {
      report_warning("Unable to do PubMed search");
      return;
    }
    // Debugging URL : view-source:http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&tool=DOIbot&email=martins@gmail.com&id=
    if (count($xml->DocSum->Item) > 0) foreach($xml->DocSum->Item as $item) {
      if (preg_match("~10\.\d{4}/[^\s\"']*~", $item, $match)) {
        $this->add_if_new('doi', $match[0]);
      }
      switch ($item["Name"]) {
                case "Title":   $this->add_if_new('title',  str_replace(array("[", "]"), "", (string) $item)); // add_if_new will format the title
        break;  case "PubDate": preg_match("~(\d+)\s*(\w*)~", $item, $match);
                                $this->add_if_new('year', (string) $match[1]);
        break;  case "FullJournalName": $this->add_if_new('journal',  ucwords((string) $item)); // add_if_new will format the title
        break;  case "Volume":  $this->add_if_new('volume', (string) $item);
        break;  case "Issue":   $this->add_if_new('issue', (string) $item);
        break;  case "Pages":   $this->add_if_new('pages', (string) $item);
        break;  case "PmId":    $this->add_if_new('pmid', (string) $item);
        break;  case "AuthorList":
          $i = 0;
          foreach ($item->Item as $subItem) {
            $i++;
            if (author_is_human((string) $subItem)) {
              $jr_test = junior_test($subItem);
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
              case "pubmed": case "pmid":
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
                if (preg_match("~PMC\d+~", (string) $subItem, $match)) {
                  $this->add_if_new('pmc', substr($match[0], 3));
                }
                break;
            }
          }
        break;
      }
    }
    if ($xml) $this->get_doi_from_crossref();
  }

  protected function use_sici() {
    if (preg_match(SICI_REGEXP, urldecode($this->parsed_text()), $sici)) {
      quietly('report_action', "Extracting information from SICI");
      $this->add_if_new("issn", $sici[1]); // Check whether journal is set in add_if_new
      //if ($this->blank ("year") && $this->blank("month") && $sici[3]) $this->set("month", date("M", mktime(0, 0, 0, $sici[3], 1, 2005)));
      $this->add_if_new("year", $sici[2]);
      //if ($this->blank("day") && is("month") && $sici[4]) set ("day", $sici[4]);
      $this->add_if_new("volume", 1*$sici[5]);
      if ($sici[6]) $this->add_if_new("issue", 1*$sici[6]);
      $this->add_if_new("pages", 1*$sici[7]);
      return TRUE;
    } else return FALSE;
  }

  protected function query_crossref($doi = FALSE) {
    if (!$doi) {
      $doi = $this->get_without_comments_and_placeholders('doi');
    }
    if (!$doi) {
      warn('query_crossref called with with no doi');
      return FALSE;
    }
    $url = "https://www.crossref.org/openurl/?pid=" . CROSSREFUSERNAME ."&id=doi:$doi&noredirect=TRUE";
    for ($i = 0; $i < 2; $i++) {
      $xml = @simplexml_load_file($url);
      if ($xml) {
        $result = $xml->query_result->body->query;
        if ($result["status"] == "resolved") {
          return $result;
        } else {
          return FALSE;
        }
      } else {
        sleep(1);
        // Keep trying...
      }
    }
    report_warning("Error loading CrossRef file from DOI " . echoable($doi) ."!");
    return FALSE;
  }

  protected function doi_active($doi = FALSE) {
    if (!$doi) {
      $doi = $this->get_without_comments_and_placeholders('doi');
    }
    if (!$doi) {
      warn('doi_active called with with no doi');
      return FALSE;
    }
    $response = get_headers("https://api.crossref.org/works/$doi")[0];
    if (stripos($response, '200 OK') !== FALSE) return TRUE;
    if (stripos($response, '404 Not Found') !== FALSE) return FALSE;
    report_warning("Crossref server error loading headers for DOI " . echoable($doi) . ": $response");
    return FALSE;
  }

  
  public function get_open_access_url() {
    $doi = $this->get_without_comments_and_placeholders('doi');
    if (!$doi) return;
    $url = "https://api.oadoi.org/v2/$doi?email=" . CROSSREFUSERNAME;
    $json = @file_get_contents($url);
    if ($json) {
      $oa = @json_decode($json);
      if ($oa !== FALSE && isset($oa->best_oa_location)) {
        $best_location = $oa->best_oa_location;
        if ($best_location->host_type == 'publisher') {
          // The best location is already linked to by the doi link
          return TRUE;
        }
        $oa_url = $best_location->url_for_landing_page;
        if ($this->get('url')) {
            $this->get_identifiers_from_url($oa_url);  // Maybe we can get a new link type
            return TRUE;
        }
        // Check if best location is already linked -- avoid double linki
        if (preg_match("~^https?://europepmc\.org/articles/pmc(\d+)~", $oa_url, $match) || preg_match("~^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                      . "|^https?://www\.ncbi\.nlm\.nih\.gov/pmc/articles/PMC(\d+)~", $oa_url, $match)) {
          if ($this->has('pmc') ) {
             return TRUE;
          }
        }
        if (preg_match("~\barxiv\.org/.*(?:pdf|abs)/(.+)$~", $oa_url, $match)) {
          if ($this->has('arxiv') || $this->has('eprint')) {
             return TRUE;
          }
        }
        if (strpos($oa_url, 'hdl.handle.net') !== false) {
          if ($this->has('hdl') ) {
             return TRUE;
          }
        }
        if (strpos($oa_url, 'citeseerx.ist.psu.edu') !== false) {
          if ($this->has('citeseerx') ) {
             return TRUE;
          }
        }
        if (preg_match(BIBCODE_REGEXP, urldecode($oa_url), $bibcode)) {
           if ($this->has('bibcode')) {
             return TRUE;
          }
        }
        if (preg_match("~https?://www.ncbi.nlm.nih.gov/pubmed/.*?=?(\d{6,})~", $oa_url, $match)) {
          if ($this->has('pmid')) {
             return TRUE;
          }
        }
        if (preg_match("~^https?://d?x?\.?doi\.org/*~", $oa_url, $match)) {
          if ($this->has('doi')) {
             return TRUE;
          }
        }

        $this->add_if_new('url', $oa_url);  // Will check for PMCs etc hidden in URL
        if ($this->has('url')) {  // The above line might have eaten the URL and upgraded it
          $headers_test = @get_headers($this->get('url'), 1);
          if($headers_test ===FALSE) {
            $this->forget('url');
            report_warning("Open access URL was was unreachable from oiDOI API for doi: " . echoable($doi));
            return FALSE;
          }
          $response_code = intval(substr($headers_test[0], 9, 3)); 
          if($response_code > 400) {  // Generally 400 and below are okay, includes redirects too though
            $this->forget('url');
            report_warning("Open access URL gave response code " . $response_code . " from oiDOI API for doi: " . echoable($doi));
            return FALSE;
          }
          switch ($best_location->version) {
            case 'acceptedVersion': $format = 'Accepted manuscript'; break;
            case 'submittedVersion': $format = 'Submitted manuscript'; break;
            // case 'publishedVersion': $format = 'Full text'; break; // This is the assumed default
            default: $format = NULL;
          }
          if ($format) $this->add('format', $format);
        }
        return TRUE;
      }
    } else {
       report_warning("Could not retrieve open access details from oiDOI API for doi: " . echoable($doi));
       return FALSE;
    }
  }
  
  public function expand_by_google_books() {
    $url = $this->get('url');
    if (!$url || !preg_match("~books\.google\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid)) { // No Google URL yet.
      $google_books_worked = FALSE ;
      $isbn = $this->get('isbn');
      $lccn = $this->get('lccn');
      $oclc = $this->get('oclc');
      if ($isbn) {
        $isbn = str_replace(array(" ", "-"), "", $isbn);
        if (preg_match("~[^0-9Xx]~", $isbn) === 1) $isbn='' ;
        if (strlen($isbn) !== 13 && strlen($isbn) !== 10) $isbn='' ;
      }
      if ($lccn) {
        $lccn = str_replace(array(" ", "-"), "", $lccn);
        if (preg_match("~[^0-9]~", $lccn) === 1) $lccn='' ;
      }
      if ($oclc) {
        if ( !ctype_alnum($oclc) ) $oclc='' ;
      }
      if ($isbn) {  // Try Books.Google.Com
        $google_book_url='https://books.google.com/books?isbn='.$isbn;
        $google_content = @file_get_contents($google_book_url);
        if ($google_content !== FALSE) {
          preg_match_all('~books.google.com/books\?id=............&amp~', $google_content, $google_results);
          $google_results = $google_results[0];
          $google_results = array_unique($google_results);
          if (count($google_results) === 1) {
            $google_results = $google_results[0];
            $gid = substr($google_results, 26, -4);
            $url = 'https://books.google.com/books?id=' . $gid;
            // if ($this->blank('url')) $this->add('url', $url); // This pissed off a lot of people.  And blank url does not mean not linked in title, etc.
            $google_books_worked = TRUE;
          }
        }
      }
      if ( !$google_books_worked ) { // Try Google API instead 
        if ($isbn) {
          $url_token = "isbn:" . $isbn;
        } elseif ($oclc) {
          $url_token = "oclc:" . $oclc;
        } elseif ($lccn) {
          $url_token = "lccn:" . $lccn;
        } else {
          return FALSE; // No data to use
        }
        $string = @file_get_contents("https://www.googleapis.com/books/v1/volumes?q=" . $url_token . "&key=" . getenv('PHP_GOOGLEKEY'));
        if ($string === FALSE) {
            report_warning("Google API search failed for $url_token");
            return FALSE;
        }
        $result = @json_decode($string, FALSE);
        if (isset($result) && isset($result->totalItems) && $result->totalItems === 1 && isset($result->items[0]) && isset($result->items[0]->id) ) {
          $gid=$result->items[0]->id;
          $url = 'https://books.google.com/books?id=' . $gid;
          // if ($this->blank('url')) $this->add('url', $url); // This pissed off a lot of people.  And blank url does not mean not linked in title, etc.
        } else {
          report_warning("Google API search failed with $url_token");
          return FALSE;
        }
      }
    }
    // Now we parse a Google Books URL
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
            if ($removed_redundant !== 0) report_forget(echoable($part)); // http://blah-blah is first parameter and it is not actually dropped
            $removed_redundant++;
        }
      }
      if ($removed_redundant > 1) { // http:// is counted as 1 parameter
        $this->set('url', $url . $hash);
      }
      $this->google_book_details($gid[1]);
      return TRUE;
    }
  }

  protected function google_book_details($gid) {
    $google_book_url = "https://books.google.com/books/feeds/volumes/$gid";
    $simplified_xml = str_replace('http___//www.w3.org/2005/Atom', 'http://www.w3.org/2005/Atom',
      str_replace(":", "___", @file_get_contents($google_book_url))
    );
    $xml = @simplexml_load_string($simplified_xml);
    if ($xml === FALSE) return FALSE;
    if ($xml->dc___title[1]) {
      $this->add_if_new("title",  
               wikify_external_text(
                 str_replace("___", ":", $xml->dc___title[0] . ": " . $xml->dc___title[1]),
                 TRUE // $caps_after_punctuation
               )
             );
    } else {
      $this->add_if_new("title",  wikify_external_text(str_replace("___", ":", $xml->title)));
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
    $i = 0;
    if ($this->blank("editor") && $this->blank("editor1") && $this->blank("editor1-last") && $this->blank("editor-last") && $this->blank("author") && $this->blank("author1") && $this->blank("last") && $this->blank("last1") && $this->blank("publisher")) { // Too many errors in gBook database to add to existing data.   Only add if blank.
      foreach ($xml->dc___creator as $author) {
        if( in_array(strtolower($author), BAD_AUTHORS) === FALSE) {
          $author_parts  = explode(" ", $author);
          $author_ending = end($author_parts);
          if( in_array(strtolower($author),        AUTHORS_ARE_PUBLISHERS        ) === TRUE ||
              in_array(strtolower($author_ending), AUTHORS_ARE_PUBLISHERS_ENDINGS) === TRUE) {
            $this->add_if_new("publisher" , (str_replace("___", ":", $author)));
          } else {
            $this->add_if_new("author" . ++$i, format_author(str_replace("___", ":", $author)));
          }
        }
      }
    }
    $google_date=sanitize_string(trim( (string) $xml->dc___date )); // Google often sends us YYYY-MM
    if (substr_count($google_date, "-") === 1) {
        $date=@date_create($google_date);
        if ($date !== FALSE) {
          $date = @date_format($date, "F Y");
          if ($date !== FALSE) {
            $google_date = $date; // only now change data
          }
        }
    }
    $this->add_if_new("date", $google_date);
    // Don't set 'pages' parameter, as this refers to the CITED pages, not the page count of the book.
    // foreach ($xml->dc___format as $format) {
    //   if (preg_match("~([\d\-]+)~", $format, $matches)) {
    //      $this->add_if_new("pages", '1–' . (string) $matches[0]); // If we did add the total pages, then we should include the whole range
    //   }
    // }
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
        report_forget("Deleting identical duplicate of parameter: " .
          echoable($this->param[$duplicated_parameters[$i]]->param));
        unset($this->param[$duplicated_parameters[$i]]);
      } else {
        $this->param[$duplicated_parameters[$i]]->param = str_replace('DUPLICATE_DUPLICATE_', 'DUPLICATE_', 'DUPLICATE_' . $this->param[$duplicated_parameters[$i]]->param);
        report_modification("Marking duplicate parameter: " .
          echoable($duplicated_parameters[$i]->param));
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
          report_add("Found unincorporated DOI parameter");
          $this->param[$param_key]->param = 'doi';
          $this->param[$param_key]->val = str_replace(DOT_ENCODE, DOT_DECODE, $p->val);
        }
        continue;
      }
      $dat = $p->val;
      $param_recycled = FALSE;
      $endnote_test = explode("\n%", "\n" . $dat);
      if (isset($endnote_test[1])) {
        $endnote_authors = 0;
        foreach ($endnote_test as $endnote_line) {
          $endnote_linetype = substr($endnote_line, 0, 1);
          $endnote_datum = substr($endnote_line, 2); // cut line type and leading space
          switch ($endnote_linetype) {
            case "A": 
              $this->add_if_new("author" . ++$endnote_authors, format_author($endnote_datum));
              $dat = trim(str_replace("\n%$endnote_line", "", "\n" . $dat));
              $endnote_parameter = FALSE;
              break;
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
              } elseif (preg_match("~@\s*\d{4}\-?\d{4}~", $endnote_line)) {
                $endnote_parameter = "issn";
                break;
              } else {
                $endnote_parameter = FALSE;
              }
            case "R": // Resource identifier... *may* be DOI but probably isn't always.
              if (extract_doi($endnote_datum)) {
                $endnote_parameter = 'doi';
                break;
              }
            case "8": // Date
            case "0":// Citation type
            case "X": // Abstract
            case "M": // Object identifier
              $dat = trim(str_replace("\n%$endnote_line", "", "\n" . $dat));
            default:
              $endnote_parameter = FALSE;
          }
          if ($endnote_parameter && $this->blank($endnote_parameter)) {
            $this->add_if_new($endnote_parameter, trim(substr($endnote_line, 2)));
            $dat = trim(str_replace("\n%$endnote_line", "", "\n$dat"));
          }
        }
      }

      if (preg_match("~^TY\s+-\s+[A-Z]+~", $dat)) { // RIS formatted data:
        $this->expand_by_RIS($dat);
      }
      
      $doi = extract_doi($dat);
      if (!is_null($doi)) {
        $this->add_if_new('doi', $doi[1]); 
        $this->name = "Cite journal";
        $dat = str_replace($doi[0], '', $dat);
      }
      
      if (preg_match('~^(https?://|www\.)\S+~', $dat, $match)) { # Takes priority over more tentative matches
        report_add("Found URL floating in template; setting url");
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
              $matched_parameter = NULL;
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
        if($match[2] > 2100 || $match[2] < 1500) { // if between 1500 and 2100, might be year or issue
             $this->add_if_new('issue' , $match[2]);
        }
        $this->add_if_new('pages' , $match[3]);
        $dat = trim(str_replace($match[0], '', $dat));
      }

      $shortest = -1;
      $parameter_list = PARAMETER_LIST;
      
      foreach ($parameter_list as $parameter) {
        if (preg_match('~^(' . preg_quote($parameter) . '[ \-:]\s*)~', strtolower($dat), $match)) {
          $parameter_value = trim(substr($dat, strlen($match[1])));
          report_add("Found $parameter floating around in template; converted to parameter");
          if (!$param_recycled) {
            $this->param[$param_key]->param = $parameter;
            $this->param[$param_key]->val = $parameter_value;
            $param_recycled = TRUE;
          } else {
            $this->add($parameter, $parameter_value);
          }
          break;
        }
        $para_len = strlen($parameter);
        if ($para_len < 3) continue; // minimum length to avoid FALSE positives
        $test_dat = preg_replace("~\d~", "_$0",
                    preg_replace("~[ -+].*$~", "", substr(mb_strtolower($dat), 0, $para_len)));
        if (preg_match("~\d~", $parameter)) {
          $lev = levenshtein($test_dat, preg_replace("~\d~", "_$0", $parameter));
        } else {
          $lev = levenshtein($test_dat, $parameter);
        }
        if ($lev == 0) {
          $closest = $parameter;
          $shortest = 0;
          break;
        } else {
          $closest = NULL;
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
        $this->add_if_new('isbn', $match[1]);
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
              $this->add($p1, implode(" ", $pAll));
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
              $this->add('issue', implode(" ", $pAll));
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
              $this->add('accessdate', implode(" ", $pAll));
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
      report_action("Trying to convert ID parameter to parameterized identifiers.");
    } else {
      return FALSE;
    }
    while (preg_match("~\b(PMID|DOI|ISBN|ISSN|ARXIV|LCCN)[\s:]*(\d[\d\s\-]*+[^\s\}\{\|,;]*)(?:[,;] )?~iu", $id, $match)) {
      $this->add_if_new(strtolower($match[1]), $match[2]);
      $id = str_replace($match[0], '', $id);
    }
    if (preg_match_all('~' . sprintf(Template::PLACEHOLDER_TEXT, '(\d+)') . '~', $id, $matches)) {
      for ($i = 0; $i < count($matches[1]); $i++) {
        $subtemplate = $this->all_templates[$matches[1][$i]];
        $subtemplate_name = $subtemplate->wikiname();
        switch($subtemplate_name) {            
          case "arxiv":
            if ($subtemplate->get('id')) {
              $archive_parameter = trim($subtemplate->get('archive') ? $subtemplate->get('archive') . '/' : '');
              $this->add_if_new("arxiv", $archive_parameter . $subtemplate->get('id'));
            } elseif (!is_null($subtemplate->param_with_index(1))) {
              $this->add_if_new("arxiv", trim($subtemplate->param_value(0)) .
                                "/" . trim($subtemplate->param_value(1)));
            } else {
              $this->add_if_new("arxiv", $subtemplate->param_value(0));
            }
            $id = str_replace($matches[0][$i], '', $id);
            break;
          case "asin":
          case "oclc":
          case "ol":
          case "bibcode":
          case "doi":
          case "isbn":
          case "issn":
          case "jfm":
          case "jstor":
          case "lccn":
          case "mr":
          case "osti":
          case "pmid":
          case "pmc":
          case "ssrn":
          case "zbl":
          
            // Specific checks for particular templates:
            if ($subtemplate_name == 'asin' && $subtemplate->has("country")) {
              report_info("{{ASIN}} country parameter not supported: cannot convert.");
              break;
            }
            if ($subtemplate_name == 'ol' && $subtemplate->has('author')) {
              report_info("{{OL}} author parameter not supported: cannot convert.");
              break;
            }
            if ($subtemplate_name == 'jstor' && $subtemplate->has('sici') || $subtemplate->has('issn')) {
              report_info("{{JSTOR}} named parameters are not supported: cannot convert.");
              break;
            }
            if ($subtemplate_name == 'oclc' && !is_null($subtemplate->param_with_index(1))) {
              
              report_info("{{OCLC}} has multiple parameters: cannot convert.");
              echo "\n    " . $subtemplate->parsed_text();
              break;
            }
          
            // All tests okay; move identifier to suitable parameter
            $subtemplate_identifier = $subtemplate->has('id') ?
                                      $subtemplate->get('id') :
                                      $subtemplate->param_value(0);
                                      
            $this->add_if_new($subtemplate_name, $subtemplate_identifier);
            $id = str_replace($matches[0][$i], '', $id); // Could only do this if previous line evaluated to TRUE, but let's be aggressive here.
            break;
          default:
            report_info("No match found for " . $subtemplate_name);
        }
      }
    }
    if (trim($id)) $this->set('id', $id); else $this->forget('id');
  }

  protected function correct_param_spelling() {
  // check each parameter name against the list of accepted names (loaded in expand.php).
  // It will correct any that appear to be mistyped.
  if (!isset($this->param)) return ;
  $parameters_used=array();
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

    if ((strlen($p->param) > 0) && !in_array(preg_replace('~\d+~', '#', $p->param), $parameter_list)) {
     
      report_modification("Unrecognised parameter " . echoable($p->param) . " ");
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
        echo "\n   . initial authors exist, not correcting " . echoable($p->param);
        continue;
      }
      */

      $p->param = preg_replace('~author(\d+)-(la|fir)st~', "$2st$1", $p->param);
      $p->param = preg_replace('~surname\-?_?(\d+)~', "last$1", $p->param);
      $p->param = preg_replace('~(?:forename|initials?)\-?_?(\d+)~', "first$1", $p->param);

      // Check the parameter list to find a likely replacement
      $shortest = -1;
      $closest = 0;
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
        elseif ($lev < $shortish) {
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
        echo " replaced with $closest (likelihood " . (24 - $shortest) . "/24)"; // Scale arbitrarily re-based by adding 12 so users are more impressed by size of similarity
      } else {
        $similarity = similar_text($p->param, $closest) / strlen($p->param);
        if ($similarity > 0.6) {
          $p->param = $closest;
          echo " replaced with $closest (similarity " . (round(2 * 12 * $similarity, 1)) . "/24)"; // Scale arbitrarily re-based by multiplying by 2 so users are more impressed by size of similarity
        } else {
          echo " could not be replaced with confidence.  Please check the citation yourself.";
        }
      }
    }
  }
}

  // TODO this is not called from anywhere - it used to be.  Where is it useful?
  protected function remove_non_ascii() {
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
  
  public function tidy_parameter($param) {
    if ($this->lacks($param)) return NULL;
    if (!preg_match('~(\D+)(\d*)~', $param, $pmatch)) {
      report_warning("Unrecognized parameter name format in $param");
      return FALSE;
    } else {
      switch ($pmatch[1]) {
        // Parameters are listed alphabetically, though those with numerical content are grouped under "year"

        case 'accessdate':
          if ($this->lacks('url') && $this->lacks('chapter-url') && $this->lacks('chapterurl') 
            && $this->lacks('contribution-url') && $this->lacks('contributionurl')) {
            $this->forget('accessdate');
          }
          break;

        case 'author': case 'authors':
          if (!$pmatch[2]) {
            if ($this->has('author') && $this->has('authors')) {
              $this->rename('author', 'DUPLICATE_authors');
              $authors = $this->get('authors');
            } else {
              $authors = $this->get($param);
            }
          
            if (!$this->initial_author_params) {
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
          }
          // Continue from authors without break
          case 'last': case 'surname':
            if (!$this->initial_author_params) {
              if ($pmatch[2]) {
                if (preg_match("~\[\[(([^\|]+)\|)?([^\]]+)\]?\]?~", $this->get($param), $match)) {
                  $this->add_if_new('authorlink' . $pmatch[2], ucfirst($match[2] ? $match[2] : $match[3]));
                  $this->set($param, $match[3]);
                  report_modification("Dissecting authorlink" . tag());
                }
                $translator_regexp = "~\b([Tt]r(ans(lat...?(by)?)?)?\.)\s([\w\p{L}\p{M}\s]+)$~u";
                if (preg_match($translator_regexp, trim($this->get($param)), $match)) {
                  $others = "{$match[1]} {$match[5]}";
                  if ($this->has('others')) {
                    $this->append_to('others', '; ' . $others);
                  } else {
                    $this->set('others', $others);
                  }
                  $this->set($param, preg_replace($translator_regexp, "", $this->get($param)));
                }
              }
            } else {
              report_inaction("Initial authors exist: skipping authorlink whlist tidying citation");
            }
            break;


    
        case 'coauthor': case 'coauthors':  // Commonly left there and empty and deprecated
          if ($this->blank($param)) $this->forget($param);
          break;
          
        case 'doi':
          $doi = $this->get($param);
          if ($doi == "10.1267/science.040579197") {
            // This is a bogus DOI from the PMID example file
            $this->forget('doi'); 
            break;
          }
          $this->set($param, sanitize_doi($doi));              
          if (preg_match('~^10\.2307/(\d+)$~', $this->get_without_comments_and_placeholders('doi'))) {
            $this->add_if_new('jstor', substr($this->get_without_comments_and_placeholders('doi'), 8));
          }
          break;
          
        case 'edition': 
          $this->set($param, preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $this->get('param')));
          break; // Don't want 'Edition ed.'
        
        case 'isbn':
          $this->set('isbn', $this->isbn10Toisbn13($this->get('isbn')));
          $this->forget('asin');
          break;
          
        case 'journal': 
          $this->forget('publisher');
          $this->forget('location');
          // No break here: Continue on from journal into periodical
        case 'periodical':
          $periodical = $this->get($param);
          if (mb_substr($periodical, -1) === "," ) {
            $periodical = mb_substr($periodical, 0, -1);
            $this->set($param, $periodical);  // Remove comma
          }
          if (substr(strtolower($periodical), 0, 7) === 'http://' || substr(strtolower($periodical), 0, 8) === 'https://') {
             if ($this->blank('url')) $this->rename($param, 'url');
             break;
          } elseif (substr(strtolower($periodical), 0, 4) === 'www.') {
             if ($this->blank('website')) $this->rename($param, 'website');
             break;
          } elseif ( mb_substr($periodical, 0, 2) !== "[["   // Only remove partial wikilinks
                    || mb_substr($periodical, -2) !== "]]"
                    || mb_substr_count($periodical, '[[') !== 1 
                    || mb_substr_count($periodical, ']]') !== 1
                    )
          {
              // Convert [[X]] wikilinks into X
              $this->set($param, preg_replace("~\[\[([^|]+?)\]\]~", "$1", $periodical));
              // Convert [[Y|X]] wikilinks into X
              $this->set($param, preg_replace("~\[\[[^|]+?\|([^|]+?)\]\]~", "$1", $this->get($param)));
          }
          $periodical = $this->get($param);
          if (substr($periodical, 0, 1) !== "[" && substr($periodical, -1) !== "]") { 
             $this->set($param, title_capitalization(ucwords($periodical), TRUE));
          }
          break;
        
        case 'origyear':
          if ($this->blank(array('date', 'year'))) {
            $this->rename('origyear', 'year');
          }
          break;
        
        case 'pmc':
          if (preg_match("~pmc(\d+)$~i", $this->get($param), $matches)) {
             $this->set($param, $matches[1]);
          }
          break;
          
        case 'quotes':
          switch(strtolower(trim($this->get('quotes')))) {
            case 'yes': case 'y': case 'TRUE': case 'no': case 'n': case 'FALSE': $this->forget('quotes');
          }
          break;

  
        case 'title':
          $title = $this->get($param);
          $title = straighten_quotes(in_array(mb_substr($title, -1), array('.', ',')) ? mb_substr($title, 0, -1) : $title);
          if ((   mb_substr($title, 0, 1) === '"'
               && mb_substr($title, -1)   === '"'
               && mb_substr_count($title, '"') == 2)
               || 
               (   mb_substr($title, 0, 1) === "'"
                && mb_substr($title, -1)   === "'"
                && mb_substr_count($title, "'") == 2)
          ) {
            $title = mb_substr($title, 1, -1);   // Remove quotes -- if only one set that wraps entire title
          }
          if (mb_substr_count($title, '[[') !== 1 ||  // Completely remove multiple wikilinks
              mb_substr_count($title, ']]') !== 1) {
             $title = preg_replace("~\[\[([^|]+?)\]\]~", "$1", $title);   // Convert [[X]] wikilinks into X
             $title = preg_replace("~\[\[[^|]+?\|([^|]+?)\]\]~", "$4", $title);   // Convert [[Y|X]] wikilinks into X
             $title = preg_replace("~\[\[~", "", $title); // Remove any extra [[ or ]] that should not be there
             $title = preg_replace("~\]\]~", "", $title);
          } else { // Convert a single link to a title-link
             if (preg_match('~\[\[([^|]+?)\]\]~', $title, $matches)) { // Convert [[X]] wikilinks into X
               $this->add_if_new('title-link', $matches[1]);
               $title = str_replace(array("[[", "]]"), "", $title);
             } elseif (preg_match('~\[\[([^|]+?)\|([^|]+?)\]\]~', $title, $matches)) { // Convert [[Y|X]] wikilinks into X
               $this->add_if_new('title-link', $matches[1]);
               $title = preg_replace("~\[\[([^|]+?)(\|)[^|]+?\]\]~", "$1", $title);
             }
          }
          $this->set($param, $title);
          break;
     
        case 'url':
          if (preg_match("~^https?://(?:www.|)researchgate.net/publication/([0-9]+)_*~i", $this->get($param), $matches)) {
              $this->set($param, 'https://www.researchgate.net/publication/' . $matches[1]);
          } elseif (preg_match("~^https?://(?:www.|)academia.edu/([0-9]+)/*~i", $this->get($param), $matches)) {
              $this->set($param, 'https://www.academia.edu/' . $matches[1]);
          }
          break;
        
        case 'year':
          if (preg_match("~\d\d*\-\d\d*\-\d\d*~", $this->get('year'))) { // We have more than one dash, must not be range of years.
             if ($this->blank('date')) $this->rename('year', 'date');
             $this->forget('year');
             break;
          }
          // Issue should follow year with no break.  [A bit of redundant execution but simpler.]
        case 'issue':
          // Remove leading zeroes
          if (!$this->blank('issue') && $this->blank('number')) {
            $new_issue = preg_replace('~^0+~', '', $this->get('issue'));
            if ($new_issue) {
              $this->set('issue', $new_issue);
            } else {
              $this->forget('issue');
            }
          }
          // No break here: pages, issue and year (the previous case) should be treated in this fashion.
        case 'pages': case 'page': # And case 'year': case 'issue':, following from previous
          $value = $this->get($param);
          if (!preg_match("~^[A-Za-z ]+\-~", $value) && mb_ereg(TO_EN_DASH, $value) && (stripos($value, "http") === FALSE)) {
            $this->mod_dashes = TRUE;
            report_modification("Upgrading to en-dash in " . echoable($param) .
                  " parameter" . tag());
            $value =  mb_ereg_replace(TO_EN_DASH, EN_DASH, $value);
            $this->set($param, $value);
          }
          if (   (mb_substr_count($value, "–") === 1) // Exactly one EN_DASH.  
              && (mb_stripos($value, "http") === FALSE)) { 
            $the_dash = mb_strpos($value, "–"); // ALL must be mb_ functions because of long dash
            $part1 = mb_substr($value, 0, $the_dash);
            $part2 = mb_substr($value, $the_dash + 1);
            if ($part1 === $part2) {
              $this->set($param, $part1);
            }
          }
          break;
      }
    }
  }
  
  public function tidy() {
    // Should only be run once (perhaps when template is first loaded)
    // Future tidying should occur when parameters are added using tidy_parameter.
    if (!$this->param) return TRUE;
    foreach ($this->param as $param) $this->tidy_parameter($param->param);
  }
  
  public function verify_doi() {
    $doi = $this->get_without_comments_and_placeholders('doi');
    if (!$doi) return FALSE;
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
      if ($this->doi_active($try)) {
        $this->expand_by_doi($try);
        $this->set('doi', $try);
        $doi = $try;
        break;
      }
    } else {    
      report_action("Checking that DOI " . echoable($doi) . " is operational..." . tag());
      if ($this->doi_active() === FALSE) {
        // Replace old "doi_inactivedate" and/or other broken/inactive-date parameters,
        // if present, with new "doi-broken-date"
        $url_test = "https://dx.doi.org/" . $doi;
        $headers_test = @get_headers($url_test, 1);
        if ($headers_test === FALSE) {
          report_warning("DOI status unkown.  dx.doi.org failed to respond at all to: " . echoable($doi));
          return FALSE;
        }
        $this->forget("doi_inactivedate");
        $this->forget("doi-inactive-date");
        $this->forget("doi_brokendate");
        if(empty($headers_test['Location']))
           $this->set("doi-broken-date", date("Y-m-d"));  // dx.doi.org might work, even if CrossRef fails
        report_warning("Broken doi: " . echoable($doi));
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
  }

  protected function check_url() {
    // Check that the URL functions, and mark as dead if not.
    /*  Disable; to re-enable, we should log possible 404s and check back later.
     * Also, dead-link notifications should be placed ''after'', not within, the template.

     function assessUrl($url){
        echo "assessing URL ";
        #if (strpos($url, "abstract") >0 || (strpos($url, "/abs") >0 && strpos($url, "adsabs.") === FALSE)) return "abstract page";
        $ch = curl_init();
        curl_setup($ch, str_replace("&amp;", "&", $url));
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_exec($ch);
        switch(curl_getinfo($ch, CURLINFO_HTTP_CODE)){
          case "404":
            global $p;
            return "{{dead link|date=" . date("F Y") . "}}";
          #case "403": case "401": return "subscription required"; Does not work for, e.g. http://arxiv.org/abs/cond-mat/9909293
        }
        curl_close($ch);
        return NULL;
      }
     
     if (!is("format") && is("url") && !is("accessdate") && !is("archivedate") && !is("archiveurl"))
    {
      report_action("Checking that URL is live...");
      $formatSet = isset($p["format"]);
      $p["format"][0] = assessUrl($p["url"][0]);
      if (!$formatSet && trim($p["format"][0]) == "") {
        unset($p["format"]);
      }
      echo "Done" , is("format")?" ({$p["format"][0]})":"" , ".</p>";
    }*/
  }
  
  /* function handle_et_al
   * To preserve user-input data, this function will only be called
   * if no author parameters were specified at the start of the 
   * expansion process.
  */
  public function handle_et_al() {
    foreach (AUTHOR_PARAMETERS as $author_cardinality => $group) {
      foreach ($group as $param) {
        if (strpos($this->get($param), 'et al')) {
          // remove 'et al' from the parameter value if present
          $val_base = preg_replace("~,?\s*'*et al['.]*~", '', $this->get($param));
          if ($author_cardinality == 1) {
            // then we (probably) have a list of authors joined by commas in our first parameter
            if (under_two_authors($val_base)) {
              $this->set($param, $val_base);
              if ($param == 'authors' && $this->blank('author')) {
                $this->rename('authors', 'author');
              }
            } else {
              $this->forget($param);
              $authors = split_authors($val_base);
              foreach ($authors as $i => $author_name) {
                $this->add_if_new('author' . ($i + 1), format_author($author_name)); // 
              }
            }
          }
          if (trim($val_base) == "") {
            $this->forget($param);
          }
          $this->add_if_new('displayauthors', 'etal');
        }
      }
    }
  }
/********************************************************
 *   Functions to retrieve values that may be specified 
 *   in various ways
 ********************************************************/
  protected function display_authors($newval = FALSE) {
    if ($newval && is_int($newval)) {
      $this->forget('displayauthors');
      report_modification("Setting display-authors to $newval" . tag());
      $this->set('display-authors', $newval);
    }

    if (($da = $this->get('display-authors')) === NULL) {
      $da = $this->get('displayauthors');
    }
    return ctype_digit($da) ? $da : FALSE;
  }

  protected function number_of_authors() {
    $max = 0;
    if ($this->param) foreach ($this->param as $p) {
      if (preg_match('~(?:author|last|first|forename|initials|surname)(\d+)~', $p->param, $matches))
        $max = max($matches[1], $max);
    }
    return $max;
  }
  
  // Retrieve properties of template
  public function first_author() {
    foreach (array('author', 'author1', 'authors', 'vauthors') as $auth_param) {
      $author = $this->get($auth_param);
      if ($author) return $author;
    }
    $forenames = $this->get('first') . $this->get('forename') . $this->get('initials') .
      $this->get('first1') . $this->get('forename1') . $this->get('initials1');
    foreach (array('last', 'surname', 'last1') as $surname_param) {
      $surname = $this->get($surname_param);
      if ($surname) {
        return ($surname . ', ' . $forenames);
      }
    }
    return NULL;
  }

  public function initial_author_params() { return $this->initial_author_params; }
  
  protected function first_surname() {
    // Fetch the surname of the first author only
    if (preg_match("~[^.,;\s]{2,}~u", $this->first_author(), $first_author)) {
      return $first_author[0];
    } else {
      return NULL;
    }
  }

  protected function page() {
    $page = $this->get('pages');
    return ($page ? $page : $this->get('page'));
  }

  public function name() {return trim($this->name);}

  protected function page_range() {
    preg_match("~(\w?\w?\d+\w?\w?)(?:\D+(\w?\w?\d+\w?\w?))?~", $this->page(), $pagenos);
    return $pagenos;
  }

  // Amend parameters
  public function rename($old_param, $new_param, $new_value = FALSE) {
    if($this->blank($new_param)) $this->forget($new_param); // Forget empty old copies, if they exist
    if (!isset($this->param)) return FALSE;
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
  
  protected function param_with_index($i) {
    return (isset($this->param[$i])) ? $this->param[$i] : NULL;
  }
  
  protected function param_value($i) { // May return error if no param with index $i
    return $this->param_with_index($i)->val;
  }
  
  public function get_without_comments_and_placeholders($name) {
    $ret = $this->get($name);
    $ret = preg_replace('~<!--.*?-->~su', '', $ret); // Comments
    $ret = preg_replace('~# # # CITATION_BOT_PLACEHOLDER.*?# # #~sui', '', $ret); // Other place holders already escaped.  Case insensitive
    $ret = trim($ret);
    return ($ret ? $ret : FALSE);
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
    report_add("Adding $par: $val" .tag());
    $could_set = $this->set($par, $val);
    $this->tidy_parameter($par);
    return $could_set;
  }
  
  protected function set($par, $val) {
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
        return TRUE;
      }
    }
    $this->param[] = $p;
    return TRUE;
  }

  protected function append_to($par, $val) {
    $pos = $this->get_param_key($par);
    if ($pos) {
      return $this->param[$pos]->val = $this->param[$pos]->val . $val;
    } else {
      return $this->set($par, $val);
    }
  }

  protected function forget($par) {
    if ($par == 'url') {
      $this->forget('format');
      $this->forget('accessdate');
      $this->forget('access-date');
      $this->forget('archive-url');
      $this->forget('archiveurl');
      $this->forget('archive-date');
      $this->forget('archivedate');
    }
    $pos = $this->get_param_key($par);
    if ($pos !== NULL) {
      if ($this->has($par) && strpos($par, 'CITATION_BOT_PLACEHOLDER') === FALSE) {
        // Do not mention forgetting empty parameters
        report_forget("Dropping parameter " . echoable($par) . tag());
      }
      unset($this->param[$pos]);
    }
  }

  // Record modifications
  protected function modified($param, $type='modifications') {
    switch ($type) {
      case '+': $type = 'additions'; break;
      case '-': $type = 'deletions'; break;
      case '~': $type = 'changeonly'; break;
      default: $type = 'modifications';
    }
    return in_array($param, $this->modifications($type));
  }
  protected function added($param) {return $this->modified($param, '+');}

  public function modifications($type='all') {
    if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) return array();
    if ($this->param) {
      foreach ($this->param as $p) {
        $new[$p->param] = $p->val;
      }
    } else {
      $new = array();
    }

    $old = ($this->initial_param) ? $this->initial_param : array();
    
    $old['template type'] = trim($this->initial_name);
    $new['template type'] = trim($this->name);

    if ($new) {
      if ($old) {
        $ret['modifications'] = array_keys(array_diff_assoc ($new, $old));
        $ret['additions'] = array_diff(array_keys($new), array_keys($old));
        $ret['deletions'] = array_diff(array_keys($old), array_keys($new));
        $ret['changeonly'] = array_diff($ret['modifications'], $ret['additions']);
        foreach ($ret['deletions'] as $inds=>$vals) {
          if ($vals === '') unset($ret['deletions'][$inds]); // If we get rid of double pipe that appears as a deletion, not misc.
        }
      } else {
        $ret['additions'] = array_keys($new);
        $ret['modifications'] = array_keys($new);
      }
    }
    $ret['dashes'] = $this->mod_dashes;
    if (in_array($type, array_keys($ret))) return $ret[$type];
    return $ret;
  }

  public function is_modified() {
    return (bool) count($this->modifications('modifications'));
  }
  
  protected function isbn10Toisbn13($isbn10) {
       $isbn10 = trim($isbn10);  // Remove leading and trailing spaces
       $isbn10 = str_replace(array('—', '?', '–', '-', '?'), '-', $isbn10); // Standardize dahses : en dash, horizontal bar, em dash, minus sign, figure dash, to hyphen.
       if (preg_match("~[^0-9Xx\-]~", $isbn10) === 1)  return $isbn10;  // Contains invalid characters
       if (substr($isbn10, -1) === "-" || substr($isbn10, 0, 1) === "-") return $isbn10;  // Ends or starts with a dash
       $isbn13 = str_replace('-', '', $isbn10);  // Remove dashes to do math
       if (strlen($isbn13) !== 10) return $isbn10;  // Might be an ISBN 13 already, or rubbish
       $isbn13 = '978' . substr($isbn13, 0, -1);  // Convert without check digit - do not need and might be X
       if (preg_match("~[^0123456789]~", $isbn13) === 1)  return $isbn10;  // Not just numbers
       $sum = 0;
       for ($count=0; $count<12; $count++ ) {
          $sum = $sum + $isbn13[$count]*($count%2?3:1);  // Depending upon even or odd, we multiply by 3 or 1 (strange but true)
       }
       $sum = ((10-$sum%10)%10) ;
       $isbn13 = '978' . '-' . substr($isbn10, 0, -1) . (string) $sum; // Assume existing dashes (if any) are right
       quietly('report_modification', "Converted ISBN10 to ISBN13");
       return $isbn13;
  }
}
