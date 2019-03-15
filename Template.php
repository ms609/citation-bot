<?php
/*
 * Template has methods to handle most aspects of citation template
 * parsing, handling, and expansion.
 *
 * Of particular note:
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
  const REGEXP = ['~\{\{[^\{\}\|]+\}\}~su', '~\{\{[^\{\}]+\}\}~su', '~\{\{(?>[^\{]|\{[^\{])+?\}\}~su'];  // Please see https://stackoverflow.com/questions/1722453/need-to-prevent-php-regex-segfault for discussion of atomic regex
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  const MAGIC_STRING = 'CITATION_BOT_PLACEHOLDER_URL_POINTER_'; 
  public $all_templates;  // Points to list of all the Template() on the Page() including this one
  public $date_style = DATES_WHATEVER;  // Will get from the page
  protected $rawtext;
  public $last_searched_doi = '';
  protected $example_param;

  protected $name, $param, $initial_param, $initial_author_params, $initial_name,
            $used_by_api, $doi_valid = FALSE,
            $mod_dashes;

  public function parse_text($text) {
    $this->initial_author_params = NULL; // Will be populated later if there are any
    $this->used_by_api = array(
      'adsabs'   => array(),
      'arxiv'    => array(), 
      'crossref' => array(), 
      'entrez'   => array(),
      'jstor'    => array(),
      'zotero'   => array(),
    );
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
    // Clean up outdated redirects
    if ($this->name === 'cite') $this->name = 'citation';
    if ($this->name === 'Cite') $this->name = 'Citation';

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
      if ($this->blank(['title', 'chapter'])) {
        return base64_decode($this->get(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL')));
      } else {
        report_action("Converted Bare reference to template: " . trim(base64_decode($this->get(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL')))));
        $this->forget(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
      }
    }
    return '{{' . $this->name . $this->join_params() . '}}';
  }

  // Parts of each param: | [pre] [param] [eq] [value] [post]
  protected function split_params($text) {
    // Replace | characters that are inside template parameter/value pairs
    $PIPE_REGEX = "~(\[\[[^\[\]]*)(?:\|)([^\[\]]*\]\])~u";
    while (preg_match($PIPE_REGEX, $text)) {
      $text = preg_replace_callback($PIPE_REGEX,
          function($matches) {
             return($matches[1] . PIPE_PLACEHOLDER . $matches[2]);     
          },
          $text);
    }
    $params = explode('|', $text);

    // TODO: this naming is confusing, distinguish between $text above and
    //       $text in the loop (derived from $text above via $params)
    foreach ($params as $i => $text) {
      $this->param[$i] = new Parameter();
      $this->param[$i]->parse_text($text);
    }
  }

  public function prepare() {
    if ($this->should_be_processed()) {
      $this->get_inline_doi_from_title();
      $this->use_unnamed_params();
      $this->get_identifiers_from_url();
      $this->id_to_param();
      $this->correct_param_spelling();
      $this->get_doi_from_text();
      $this->fix_rogue_etal();
      $this->tidy();
      
      switch ($this->wikiname()) {
        case "cite arxiv":
           // Forget dates so that DOI can update with publication date, not ARXIV date
          $this->rename('date', 'CITATION_BOT_PLACEHOLDER_date');
          $this->rename('year', 'CITATION_BOT_PLACEHOLDER_year');
          expand_by_doi($this);
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
    } elseif ($this->wikiname() == 'cite magazine' &&  $this->blank('magazine') && $this->has('work')) { 
      // This is all we do with cite magazine
      $this->rename('work', 'magazine');
    }
  }
  
  public function fix_rogue_etal() {
    if ($this->blank(DISPLAY_AUTHORS)) {
      $i = 2;
      while (!$this->blank(['author' . $i, 'last' . $i])) {
        $i = $i + 1;
      }
      $i = $i - 1;
      if (preg_match('~^et\.? ?al\.?$~i', $this->get('author' . $i))) $this->rename('author' . $i, 'display-authors', 'etal');
      if (preg_match('~^et\.? ?al\.?$~i', $this->get('last'   . $i))) $this->rename('last'   . $i, 'display-authors', 'etal');
    }
  }
  
  public function record_api_usage($api, $param) {
    if (!is_array($param)) $param = array($param);
    foreach ($param as $p) if (!in_array($p, $this->used_by_api[$api])) $this->used_by_api[$api][] = $p;
  }
  
  public function api_has_used($api, $param) {
    if (!isset($this->used_by_api[$api])) report_error("Invalid API: $api");
    return count(array_intersect($param, $this->used_by_api[$api]));
  }
  
  public function api_has_not_used($api, $param) {
    return !$this->api_has_used($api, $param);
  }
  
  public function incomplete() {
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
    if ($this->blank('pages', 'page') ||
        preg_match('~no.+no|n/a|in press|none~', $this->get('pages') . $this->get('page')) ||
        (preg_match('~^1[^0-9]~', $this->get('pages') . $this->get('page') . '-') && ($this->blank('year') || 2 > (date("Y") - $this->get('year')))) // It claims to be on page one
       ) {
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

  public function profoundly_incomplete($url = '') {
    // Zotero translation server often returns bad data, which is worth having if we have no data,
    // but we don't want to fill a single missing field with garbage if a reference is otherwise well formed.
    $has_date = $this->has("date") || $this->has("year") ;
    foreach (NO_DATE_WEBSITES as $bad_website) {
      if (stripos($url, $bad_website) !== FALSE) {
        $has_date = TRUE;
        break;
      }
    }
  
    if (strtolower($this->wikiname()) =='cite book' || (strtolower($this->wikiname()) =='citation' && $this->has('isbn'))) { // Assume book
      if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
      return (!(
              $this->has("isbn")
          &&  $this->has("title")
          &&  $has_date
      ));
    }

    if (str_ireplace(NON_JOURNAL_WEBSITES, '', $url) !== $url) { // A website that will never give a volume
          return (!(
             ($this->has('journal') || $this->has('periodical') || $this->has('work') ||
              $this->has('website') || $this->has('publisher') || $this->has('newspaper') ||
              $this->has('magazine')|| $this->has('encyclopedia') || $this->has('contribution'))
          &&  $this->has("title")
          &&  $has_date
    ));
    }
    return (!(
             ($this->has('journal') || $this->has('periodical'))
          &&  $this->has("volume")
          &&  $this->has("title")
          &&  $has_date
    ));
  }

  public function blank($param) {
    if (!$param) return NULL;
    if (empty($this->param)) return TRUE;
    if (!is_array($param)) $param = array($param);
    foreach ($this->param as $p) {
      if (in_array($p->param, $param) && trim($p->val) != '') return FALSE;
    }
    return TRUE;
  }

  /* function add_if_new
   * Adds a parameter to a template if the parameter and its equivalents are blank
   * $api (string) specifies the API route by which a parameter was found; this will log the 
   *      parameter so it is not used to trigger a new search via the same API.
   *
   */
  public function add_if_new($param_name, $value, $api = NULL) {
    $value = trim($value);
    if ($value == '') {
      return FALSE;
    }
    
    if (strcasecmp((string) $value, 'null') === 0) {
      return FALSE; // We hope that name is not actually NULL
    }
    
    if (mb_stripos($this->get($param_name), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
      return FALSE;  // We let comments block the bot
    }
    
    if (array_key_exists($param_name, COMMON_MISTAKES)) {
      $param_name = COMMON_MISTAKES[$param_name];
    }
    
    if (!is_null($api)) $this->record_api_usage($api, $param_name);
    
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
        if ($this->blank(['editor', 'editor-last', 'editor-first'])) {
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
        $value = trim(straighten_quotes($value));

        if ($this->blank(AUTHOR1_ALIASES)) {
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
       $value = trim(straighten_quotes($value));
       if ($this->blank(FORENAME1_ALIASES)) {
          if (mb_substr($value, -1) === '.') { // Do not lose last period
             $value = sanitize_string($value) . '.';
          } else {
             $value = sanitize_string($value);
          }
          if (mb_strlen($value) === 1 || (mb_strlen($value) > 3 && mb_substr($value, -2, 1) === " ")) { // Single character at end
            $value .= '.';
          }
          if (mb_strlen($value) === 3 && mb_substr($value, -2, 1) === " ") { // Special case for "F M" -- add dots to both
            $value = mb_substr($value, 0, 1) . '. ' . mb_substr($value, -1, 1) . '.';
          }
          return $this->add($param_name, $value);
      }
      return FALSE;
      case "coauthors": //FIXME: this should convert "coauthors" to "authors" maybe, if "authors" doesn't exist.
        $value = trim(straighten_quotes($value));
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);

        if ($this->blank(array_merge(COAUTHOR_ALIASES, ["last2", "author"])))
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
        $value = trim(straighten_quotes($value));

        if ($this->blank(array_merge(COAUTHOR_ALIASES, ["last$auNo", "author$auNo"]))
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
        $value = trim(straighten_quotes($value));

        if ($this->blank(array_merge(COAUTHOR_ALIASES, [$param_name, "author" . $auNo]))
                && under_two_authors($this->get('author'))) {
          if (mb_substr($value, -1) === '.') { // Do not lose last period
             $value = sanitize_string($value) . '.';
          } else {
             $value = sanitize_string($value);
          }
          if (mb_strlen($value) === 1 || (mb_strlen($value) > 3 && mb_substr($value, -2, 1) === " ")) { // Single character at end
            $value .= '.';
          }
          if (mb_strlen($value) === 3 && mb_substr($value, -2, 1) === " ") { // Special case for "F M" -- add dots to both
            $value = mb_substr($value, 0, 1) . '. ' . mb_substr($value, -1, 1) . '.';
          }
          return $this->add($param_name, $value);
        }
        return FALSE;
      
      case 'display-authors': case 'displayauthors':
        if ($this->blank(DISPLAY_AUTHORS)) {
          return $this->add($param_name, $value);
        }
        return FALSE;

      case 'display-editors': case 'displayeditors':
        if ($this->blank(DISPLAY_EDITORS)) {
          return $this->add($param_name, $value);
        }
        return FALSE;
      
      case 'author_separator': case 'author-separator':
        report_warning("'author-separator' is deprecated.");
        if(!trim($value)) {
          $this->forget($param_name);
        } else {
          report_warning(" Please fix manually.");
        }
        return FALSE;
      
      ### DATE AND YEAR ###
      
      case "date":
        if (preg_match("~^\d{4}$~", sanitize_string($value))) {
          // Not adding any date data beyond the year, so 'year' parameter is more suitable
          // TODO does this still match the current usage practice?
          $param_name = "year";
        } elseif ($this->date_style) {
          $time = strtotime($value);
          if ($time) {
            $day = date('d', $time);
            if ($day !== '01') { // Probably just got month and year if day=1
              if ($this->date_style === DATES_MDY) {
                 $value = date('m-d-Y', $time);
              } elseif ($this->date_style === DATES_DMY) {
                 $value = date('d-m-Y', $time);
              }
            }
          }
        }
      // Don't break here; we want to go straight in to year;
      case "year":
        if (   ($this->blank('date')
               || in_array(trim(strtolower($this->get_without_comments_and_placeholders('date'))), IN_PRESS_ALIASES))
            && ($this->blank('year') 
               || in_array(trim(strtolower($this->get_without_comments_and_placeholders('year'))), IN_PRESS_ALIASES))
          ) {
          if ($param_name != 'date') $this->forget('date'); // Delete any "in press" dates.
          if ($param_name != 'year') $this->forget('year'); // We only unset the other one so that parameters stay in order as much as possible
          return $this->add($param_name, $value);
        }
        return FALSE;
      
      ### JOURNAL IDENTIFIERS ###
      
      case 'issn':
        if ($this->blank(["journal", "periodical", "work", $param_name]) &&
            preg_match('~^\d{4}-\d{3}[\dxX]$~', $value)) {
          // Only add ISSN if journal is unspecified
          return $this->add($param_name, $value);
        }
        return FALSE;
        
      case 'periodical': case 'journal': case 'newspaper':
      
        if (in_array(strtolower(sanitize_string($this->get('journal'))), BAD_TITLES ) === TRUE) $this->forget('journal'); // Update to real data
        if ($this->wikiname() === 'cite book' && $this->has('chapter') && $this->has('title') && $this->has('series')) return FALSE;
        if ($this->blank(["journal", "periodical", "encyclopedia", "newspaper", "magazine", "contribution"])) {
          if (in_array(strtolower(sanitize_string($value)), HAS_NO_VOLUME) === TRUE) $this->forget("volume") ; // No volumes, just issues.
          if (in_array(strtolower(sanitize_string($value)), BAD_TITLES ) === TRUE) return FALSE;
          $value = wikify_external_text(title_case($value));
          if ($this->has('series') && str_equivalent($this->get('series'), $value)) return FALSE ;
          if ($this->has('work')) {
            if (str_equivalent($this->get('work'), $value)) {
              $this->rename('work', $param_name);
              $this->forget('issn');
              return TRUE;
            } else {
              return FALSE;  // Cannot have both work and journal
            }
          }
          if ($this->has('via')) {
            if (str_equivalent($this->get('via'), $value)) {
              $this->rename('via', $param_name);
              $this->forget('issn');
              return TRUE;
            }
          }
          $this->forget('issn');
          $this->forget('class');
          
          if ($param_name === 'newspaper' && in_array(strtolower($value), WEB_NEWSPAPERS)) {
             if ($this->has('publisher') && str_equivalent($this->get('publisher'), $value)) return FALSE;
             if($this->blank('work')) {
               $this->add('work', $value);
               $this->quietly_forget('website');
               return TRUE;
             }
            return FALSE;
          } 
          if ($param_name === 'newspaper' && $this->has('via')) {
             if (stripos($value, 'times') !== FALSE && stripos($this->get('via'), 'times') !== FALSE) {
               $this->forget('via'); // eliminate via= that matches newspaper mostly
             }
             if (stripos($value, ' post') !== FALSE && stripos($this->get('via'), 'post') !== FALSE) {
               $this->forget('via'); // eliminate via= that matches newspaper mostly
             }
          }
          if ($param_name === 'newspaper' && $this->has('publisher') && str_equivalent($this->get('publisher'), $value)
                  && $this->blank('website')) { // Website is an alias for newspaper/work/journal, and did not check above
             $this->rename('publisher', $param_name);
             return TRUE;
          }
          if ($this->has('website')) { // alias for journal
             if (str_equivalent($this->get('website'), $value)) {
               $this->rename('website', $param_name);
             } elseif (preg_match('~^\[.+\]$~', $this->get('website'))) {
               $this->rename('website', $param_name); // existing data is linked
             } else {
               $this->rename('website', $param_name, $value);
             }
             return TRUE;
          } else {   
             return $this->add($param_name, $value);
          }
        }
        return FALSE;
        
      case 'series':
        if ($this->blank($param_name)) {
          $value = wikify_external_text($value);
          if ($this->has('journal') && str_equivalent($this->get('journal'), $value)) return FALSE;
          if ($this->has('title') && str_equivalent($this->get('title'), $value)) return FALSE;
          return $this->add($param_name, $value);
        }
        return FALSE;

      case 'chapter': case 'contribution':
        if ($this->blank(CHAPTER_ALIASES)) {
          return $this->add($param_name, wikify_external_text($value));
        }
        return FALSE;
      
      
      ###  ARTICLE LOCATORS  ###
      ### (page, volume etc) ###
      
      case 'title':
        if (in_array(strtolower(sanitize_string($value)), BAD_TITLES ) === TRUE) return FALSE;
        if ($this->blank($param_name) || ($this->get($param_name) === 'Archived copy')
                                      || ($this->get($param_name) === "{title}")) {
          if (str_equivalent($this->get('encyclopedia'), sanitize_string($value))) {
            return FALSE;
          }
          if ($this->blank('script-title')) {
            return $this->add($param_name, wikify_external_text($value));
          } else {
            $value = trim($value);
            $script_value = $this->get('script-title');
            if (preg_match('~^[a-zA-Z0-9\.\,\-\; ]+$~u', $value) &&
                  mb_stripos($script_value, $value) === FALSE &&
                  mb_stripos($value, $script_value) === FALSE &&
                  !preg_match('~^[a-zA-Z0-9\.\,\-\; ]+$~u', $script_value)) {
              {// Neither one is part of the other and script is not all ascii and new title is all ascii
                 return $this->add($param_name, wikify_external_text($value));
              }
            }
          }
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
        if ($this->blank(ISSUE_ALIASES)) {        
          return $this->add($param_name, $value);
        } 
        return FALSE;
      
      case "page": case "pages":
        if (in_array((string) $value, ['0', '0-0', '0–0'], TRUE)) return FALSE;  // Reject bogus zero page number
        if ($this->has('at')) return FALSE;  // Leave at= alone.  People often use that for at=See figure 17 on page......
        $pages_value = $this->get('pages');
        $all_page_values = $pages_value . $this->get("page") . $this->get("pp") . $this->get("p") . $this->get('at');
        $en_dash = [chr(2013), chr(150), chr(226), '-', '&ndash;'];
        $en_dash_X = ['X', 'X', 'X', 'X', 'X'];
        if (  mb_stripos($all_page_values, 'see ')  !== FALSE   // Someone is pointing to a specific part
           || mb_stripos($all_page_values, 'table') !== FALSE // Someone is pointing to a specific table
           || mb_stripos($all_page_values, 'CITATION_BOT_PLACEHOLDER') !== FALSE) { // A comment or template will block the bot
           return FALSE;  
        }
        if ($this->blank(PAGE_ALIASES) // no page yet set
           || $all_page_values == ""
           || (strcasecmp($all_page_values,'no') === 0 || strcasecmp($all_page_values,'none') === 0) // Is exactly "no" or "none"
           || (strpos(strtolower($all_page_values), 'no') !== FALSE && $this->blank('at')) // "None" or "no" contained within something other than "at"
           || (
                (  str_replace($en_dash, $en_dash_X, $value) != $value) // dash in new `pages`
                && str_replace($en_dash, $en_dash_X, $pages_value) == $pages_value // No dash already
              )
           || (   // Document with bogus pre-print page ranges
                   ($value           !== '1' && substr(str_replace($en_dash, $en_dash_X, $value), 0, 2)           !== '1X') // New is not 1-
                && ($all_page_values === '1' || substr(str_replace($en_dash, $en_dash_X, $all_page_values), 0, 2) === '1X') // Old is 1-
                && ($this->blank('year') || 2 > (date("Y") - $this->get('year'))) // Less than two years old
              )
        ) {
            // One last check to see if old template had a specific page listed
            if ($all_page_values != '' &&
                preg_match("~^[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?[-–—‒]+[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?$~u", $value, $newpagenos) && // Adding a range
                preg_match("~^[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?~u", $all_page_values, $oldpagenos)) { // Just had a single number before
                $first_page = (int) $newpagenos[1];
                $last_page  = (int) $newpagenos[2];
                $old_page   = (int) $oldpagenos[1];
                if ($old_page > $first_page && $old_page <= $last_page) {
                  foreach (['pages', 'page', 'pp', 'p'] as $forget_blank) {
                    if ($this->blank($forget_blank)) {
                      $this->forget($forget_blank);
                    }
                  }
                  return FALSE;
                }
            }
            if ($param_name !== "pages") $this->forget("pages"); // Forget others -- sometimes we upgrade page=123 to pages=123-456
            if ($param_name !== "page")  $this->forget("page");
            if ($param_name !== "pp")    $this->forget("pp");
            if ($param_name !== "p")     $this->forget("p");
            if ($param_name !== "at")    $this->forget("at");

            $param_key = $this->get_param_key($param_name);
            if (!is_null($param_key)) {
              $this->param[$param_key]->val = sanitize_string($value); // Minimize template changes (i.e. location) when upgrading from page=123 to pages=123-456
            } else {
              $this->add($param_name, sanitize_string($value));
            }
            $this->tidy_parameter($param_name); // Clean up dashes etc
            return TRUE;
        }
        return FALSE;
        
        
      ###  ARTICLE IDENTIFIERS  ###
      ### arXiv, DOI, PMID etc. ###
      
      case 'url': 
        // look for identifiers in URL - might be better to add a PMC parameter, say
        if (!$this->get_identifiers_from_url($value) && $this->blank(array_merge([$param_name], TITLE_LINK_ALIASES))) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;
        
      case 'title-link':
        if ($this->blank(array_merge(TITLE_LINK_ALIASES, ['url']))) {
          return $this->add($param_name, $value); // We do not sanitize this, since it is not new data
        }
        return FALSE;
        
      case 'class':
        if ($this->blank($param_name) && strpos($this->get('eprint') . $this->get('arxiv'), '/') === FALSE ) { // Old eprints include class in the ID
          if ($this->wikiname() === 'cite arxiv') {  // Only relevent for cite arxiv
            return $this->add($param_name, sanitize_string($value));
          }
        }
        return FALSE;
        
      case 'doi':
        if (stripos($value, '10.1093/law:epil') === 0) return FALSE; // Those do not work
        if (stripos($value, '10.1093/oi/authority') === 0) return FALSE; // Those do not work
        if (preg_match(REGEXP_DOI, $value, $match)) {
          if ($this->blank($param_name)) {
            $this->add('doi', $match[0]);          
            return TRUE;
          } elseif (strcasecmp($this->get('doi'), $match[0]) !=0 && !$this->blank(DOI_BROKEN_ALIASES) && doi_active($match[0])) {
            report_action("Replacing non-functional DOI with a functional one");
            $this->set('doi', $match[0]);
            $this->tidy_parameter('doi');
            return TRUE;
          } elseif (strcasecmp($this->get('doi'), $match[0]) != 0 
                    && strpos($this->get('doi'), '10.13140/') === 0 
                    && doi_active($match[0])) {
            report_action("Replacing ResearchGate DOI with publisher's");
            $this->set('doi', $match[0]);
            $this->tidy_parameter('doi');
            return TRUE;
          }
        }
        return FALSE;
      
      case 'eprint':
      case 'arxiv':
        if ($this->blank(ARXIV_ALIASES)) {
          $this->add($param_name, $value);
          return TRUE;
        }
        return FALSE;
        
      case 'doi-broken-date':
        if ($this->blank(DOI_BROKEN_ALIASES)) {
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
          if ($this->blank('pmid')) {
            $this->expand_by_pubmed(TRUE); // Almost always can get a PMID (it is rare not too)
          }
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
      
      case 'publisher':
        if (stripos($value, 'Springer') === 0) $value = 'Springer'; // they add locations often
        if (stripos($value, '[s.n.]') !== FALSE) return FALSE; 
        if ($this->has('journal') && ($this->wikiname() === 'cite journal')) return FALSE;
        $value = truncate_publisher($value);
        if ($this->has('via') && str_equivalent($this->get('via'), $value))  $this->rename('via', $param_name);
        if ($this->blank($param_name)) {
          return $this->add($param_name, $value);
        }
        return FALSE;

      default:
        if ($this->blank($param_name)) {
          return $this->add($param_name, sanitize_string($value));
        }
    }
  }

  public function validate_and_add($author_param, $author, $forename = '', $check_against = '') {
    if (in_array(strtolower($author), BAD_AUTHORS) === FALSE) {
      $author_parts  = explode(" ", $author);
      $author_ending = end($author_parts);
      $name_as_publisher = trim($forename . ' ' . $author);
      // var_dump($name_as_publisher);
      // var_dump($check_against);
      if (in_array(strtolower($author_ending), PUBLISHER_ENDINGS) === TRUE
          || stripos($check_against, $name_as_publisher) !== FALSE) {
        $this->add_if_new('publisher' , $name_as_publisher);
      } else {
        $this->add_if_new($author_param, format_author($author . ($forename ? ", $forename" : '')));
      }
    }
  }
  
  public function mark_inactive_doi($doi = NULL) {
    // Only call if doi_broken.
    // Before we mark the doi inactive, we'll additionally check that dx.doi.org fails to resolve.
    if (is_null($doi)) $doi = $this->get_without_comments_and_placeholders('doi');
    $url_test = "https://dx.doi.org/" . urlencode($doi);
    $headers_test = @get_headers($url_test, 1);
    if ($headers_test !== FALSE && empty($headers_test['Location'])) {
      $this->add_if_new('doi-broken-date', date('Y-m-d'));  
    }
  }
  
  // This is also called when adding a URL with add_if_new, in which case
  // it looks for a parameter before adding the url.
  public function get_identifiers_from_url($url_sent = NULL) {
    if (is_null($url_sent)) {
       // Chapter URLs are generally better than URLs for the whole book.
        if ($this->has('url') && $this->has('chapterurl')) {
           $return_code = FALSE;
           $return_code += $this->get_identifiers_from_url(Template::MAGIC_STRING . 'chapterurl ');
           $return_code += $this->get_identifiers_from_url(Template::MAGIC_STRING . 'url ');
           return (boolean) $return_code;
        } elseif ($this->has('url') && $this->has('chapter-url')) {
           $return_code = FALSE;
           $return_code += $this->get_identifiers_from_url(Template::MAGIC_STRING . 'chapter-url ');
           $return_code += $this->get_identifiers_from_url(Template::MAGIC_STRING . 'url ');
           return (boolean) $return_code;
        } elseif ($this->has('url')) {        
           $url = $this->get('url');
           $url_type = 'url';
        } elseif ($this->has('chapter-url')) {
           $url = $this->get('chapter-url');
           $url_type = 'chapter-url';
        } elseif ($this->has('chapterurl')) {
           $url = $this->get('chapterurl');
           $url_type = 'chapterurl';
        } elseif ($this->has('conference-url')) {
           $url = $this->get('conference-url');
           $url_type = 'conference-url';
        } elseif ($this->has('conferenceurl')) {
           $url = $this->get('conferenceurl');
           $url_type = 'conferenceurl';
        } elseif ($this->has('contribution-url')) {
           $url = $this->get('contribution-url');
           $url_type = 'contribution-url';
        } elseif ($this->has('contributionurl')) {
           $url = $this->get('contributionurl');
           $url_type = 'contributionurl';
        } elseif ($this->has('article-url')) {
           $url = $this->get('article-url');
           $url_type = 'article-url';
        } elseif ($this->has('website')) { // No URL, but a website
          $url = trim($this->get('website'));
          if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
            $url = "h" . $url;
          }
          if (strtolower(substr( $url, 0, 4 )) !== "http" ) {
            $url = "http://" . $url; // Try it with http
          }
          if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return FALSE; // PHP does not like it
          if (preg_match (REGEXP_IS_URL, $url) !== 1) return FALSE;  // See https://mathiasbynens.be/demo/url-regex/  This regex is more exact than validator.  We only spend time on this after quick and dirty check is passed
          $this->rename('website', 'url'); // Rename it first, so that parameters stay in same order
          $this->set('url', $url);
          $url_type = 'url'; 
          quietly('report_modification', "website is actually HTTP URL; converting to use url parameter.");
        } else {
          // If no URL or website, nothing to worth with.
          return FALSE;
        }
    } elseif (preg_match('~^' . Template::MAGIC_STRING . '(\S+) $~', $url_sent, $matches)) {
      $url_sent = NULL;
      $url_type = $matches[1];
      $url      = $this->get($matches[1]);
    } else {
      $url = $url_sent;
      $url_type = NULL;
    }
    
    if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
      $url = "h" . $url;
      if (is_null($url_sent)) {
        $this->set($url_type, $url); // Save it
      }
    }
    // https://www.jstor.org.stuff/proxy/stuff/stable/3347357 and such
    if (preg_match('~^(https?://(?:www\.|)jstor\.org)(?:\S*proxy\S*/|/)(?:stable|discover)/10.2307/(.+)$~i', $url, $matches)) {
       $url = $matches[1] . '/stable/' . $matches[2] ; // that is default.  This also means we get jstor not doi
       if (!is_null($url_sent)) {
         $this->set($url_type, $url); // Update URL with cleaner one.  Will probably call forget on it below
       }
    }
    // https://www.jstor.org.libweb.lib.utsa.edu/stable/3347357 and such
    if (preg_match('~^https?://(?:www\.|)jstor\.org\.[^/]+/(?:stable|discover)/(.+)$~i', $url, $matches)) {
       $url = 'https://www.jstor.org/stable/' . $matches[1] ;
       if (!is_null($url_sent)) {
         $this->set($url_type, $url); // Update URL with cleaner one
       }
    }
    // https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10 and such
    if (preg_match('~^https?://(?:www-|)jstor-org[-\.]\S+/(?:stable|discover)/(.+)$~i', $url, $matches)) {
       $url = 'https://www.jstor.org/stable/' . $matches[1] ;
       if (!is_null($url_sent)) {
         $this->set($url_type, $url); // Update URL with cleaner one
       }
    }
    
    if (preg_match("~^https?://(?:d?x?\.?doi\.org|doi\.library\.ubc\.ca)/([^\?]*)~i", $url, $match)) {
        quietly('report_modification', "URL is hard-coded DOI; converting to use DOI parameter.");
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        if (is_null($url_sent)) {
          $this->forget($url_type);
        }
        return $this->add_if_new('doi', urldecode($match[1])); // Will expand from DOI when added
    }
    
    if ($doi = extract_doi($url)[1]) {
      if (stripos($url, 'jstor')) check_doi_for_jstor($doi, $this);
      $this->tidy_parameter('doi'); // Sanitize DOI before comparing
      if ($this->has('doi') && mb_stripos($doi, $this->get('doi')) === 0) { // DOIs are case-insensitive
        if (doi_active($doi) && is_null($url_sent) && mb_strpos(strtolower($url), ".pdf") === FALSE && check_10_1093_doi($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
          report_forget("Recognized existing DOI in URL; dropping URL");
          $this->forget($url_type);
        }
        return FALSE;  // URL matched existing DOI, so we did not use it
      }
      if (preg_match('~(.*)(?:#[^#]+)$~', $doi, $match_pound)) {
        if(!doi_active($doi) && doi_active($match_pound[1])) $doi = $match_pound[1]; // lose #pages and such
      }
      if ($this->add_if_new('doi', $doi)) {
        if (doi_active($doi)) {
          if (is_null($url_sent)) {
            if (mb_strpos(strtolower($url), ".pdf") === FALSE && check_10_1093_doi($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
              report_forget("Recognized DOI in URL; dropping URL");
              $this->forget($url_type);
            } else {
              report_info("Recognized DOI in URL.  Leaving *.pdf URL.");
            }
          }
        } else {
          // Even if the DOI is broken, still drop URL if URL was dx.doi.org URL
          if (is_null($url_sent) && strpos(strtolower($url), "doi.org/") !== FALSE) {
            report_forget("Recognized doi.org URL; dropping URL");
            $this->forget($url_type);
          }
          $this->mark_inactive_doi();
        }
        return TRUE; // Added new DOI
      }
      return FALSE; // Did not add it
    } elseif ($this->has('doi')) { // Did not find a doi, perhaps we were wrong
      $this->tidy_parameter('doi'); // Sanitize DOI before comparing
      if (mb_stripos($url, $this->get('doi')) !== FALSE) { // DOIs are case-insensitive
        if (doi_active($this->get('doi')) && is_null($url_sent) && mb_strpos(strtolower($url), ".pdf") === FALSE && check_10_1093_doi($this->get('doi')) && !preg_match(REGEXP_DOI_ISSN_ONLY, $this->get('doi'))) {
          report_forget("Recognized existing DOI in URL; dropping URL");
          $this->forget($url_type);
        }
        return FALSE;  // URL matched existing DOI, so we did not use it
      }
    }
  
    // JSTOR
    if (stripos($url, "jstor.org") !== FALSE) {
      $sici_pos = stripos($url, "sici");
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
              $this->set($url_type, $url); // Save it
            }
          } else {
            return FALSE;  // We do not want this URL incorrectly parsed below, or even waste time trying.
          }
        }
      }
      if (stripos($url, "plants.jstor.org")) {
        return FALSE; # Plants database, not journal
      } elseif (preg_match("~^(?:\w+/)*(\d{5,})[^\d%\-]*(?:\?|$)~", substr($url, stripos($url, 'jstor.org/') + 10), $match) ||
                preg_match("~^https?://(?:www\.)?jstor\.org\S+proxy\S+(?:stable|discovery)/(\d{5,}|[jJ]\.[a-zA-Z]+)$~", $url, $match)) {
        if (is_null($url_sent)) {
          $this->forget($url_type);
        }
        if ($this->get('jstor')) {
          quietly('report_inaction', "Not using redundant URL (jstor parameter set)");
        } else {
          quietly('report_modification', "Converting URL to JSTOR parameter " . jstor_link(urldecode($match[1])));
          $this->set('jstor', urldecode($match[1]));
        }
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        return TRUE;
      } else {
        return FALSE; // Jstor URL yielded nothing
      }
    } else {
      if (preg_match(REGEXP_BIBCODE, urldecode($url), $bibcode)) {
        if ($this->blank('bibcode')) {
          quietly('report_modification', "Converting url to bibcode parameter");
          if (is_null($url_sent)) {
            $this->forget($url_type);
          }
          return $this->add_if_new('bibcode', urldecode($bibcode[1]));
        } elseif (is_null($url_sent) && urldecode($bibcode[1]) === $this->get('bibcode')) {
          $this->forget($url_type);
        }
        
      } elseif (preg_match("~^https?://(?:www\.|)pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                      . "|^https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?pmc/articles/PMC(\d+)~i", $url, $match)) {
        if (preg_match("~https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?pmc/\?term~i", $url)) return FALSE; // A search such as https://www.ncbi.nlm.nih.gov/pmc/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        if ($this->blank('pmc')) {
          quietly('report_modification', "Converting URL to PMC parameter");
        }
        if (is_null($url_sent)) {
          if (stripos($url, ".pdf") !== FALSE) {
            $test_url = "https://www.ncbi.nlm.nih.gov/pmc/articles/PMC" . $match[1] . $match[2] . "/";
            $ch = curl_init($test_url);
            curl_setopt($ch,  CURLOPT_RETURNTRANSFER, TRUE);
            @curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode == 404) { // Some PMCs do NOT resolve.  So leave URL
              return $this->add_if_new('pmc', $match[1] . $match[2]);
            }
          }
          $this->forget($url_type);
        } 
        return $this->add_if_new('pmc', $match[1] . $match[2]);
      } elseif (preg_match("~^https?://(?:www\.|)europepmc\.org/articles/pmc(\d+)~i", $url, $match)  ||
                preg_match("~^https?://(?:www\.|)europepmc\.org/scanned\?pageindex=(?:\d+)\&articles=pmc(\d+)~i", $url, $match)) {
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        if ($this->blank('pmc')) {
          quietly('report_modification', "Converting Europe URL to PMC parameter");
          if (is_null($url_sent)) {
            $this->forget($url_type);
          }
          return $this->add_if_new('pmc', $match[1]);
        }
      } elseif(preg_match("~^https?://citeseerx\.ist\.psu\.edu/viewdoc/(?:summary|download)\?doi=([0-9.]*)(&.+)?~", $url, $match)) {
        quietly('report_modification', "URL is hard-coded citeseerx; converting to use citeseerx parameter.");
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        if (is_null($url_sent)) {
          $this->forget($url_type);
        }
        return $this->add_if_new('citeseerx', urldecode($match[1])); // We cannot parse these at this time
        
      } elseif (preg_match("~\barxiv\.org/.*(?:pdf|abs|ftp/arxiv/papers/\d{4})/(.+?)(?:\.pdf)?$~i", $url, $match)) {
        
        /* ARXIV
         * See https://arxiv.org/help/arxiv_identifier for identifier formats
         */
        if (   preg_match("~[A-z\-\.]+/\d{7}~", $match[1], $arxiv_id) // pre-2007
            || preg_match("~\d{4}\.\d{4,5}(?:v\d+)?~", $match[1], $arxiv_id) // post-2007
            ) {
          quietly('report_modification', "Converting URL to arXiv parameter");
          if (is_null($url_sent)) {
            $this->forget($url_type);
          }
          return $this->add_if_new('arxiv', $arxiv_id[0]);
        }
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite arxiv');
        
      } elseif (preg_match("~https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?(?:pubmed|entrez/eutils/elink\.fcgi\S+dbfrom=pubmed\S+)/.*?=?(\d+)~i", $url, $match)) {
        if (preg_match("~https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?/pubmed/\?term~i", $url)) return FALSE; // A search such as https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
        quietly('report_modification', "Converting URL to PMID parameter");
        if (is_null($url_sent)) {
          $this->forget($url_type);
        }
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        return $this->add_if_new('pmid', $match[1]);
        
      } elseif (preg_match("~^https?://(?:www\.|)amazon(?P<domain>\.[\w\.]{1,7})/.*dp/(?P<id>\d+X?)~i", $url, $match)) {
        
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite book');
        if ($match['domain'] == ".com") {
          if (is_null($url_sent)) {
            $this->forget($url_type);
            if (stripos($this->get('publisher'), 'amazon') !== FALSE) {
              $this->forget('publisher');
            }
          }
          if ($this->blank('asin')) {
            quietly('report_modification', "Converting URL to ASIN parameter");
            return $this->add_if_new('asin', $match['id']);
          }
        } else {
          if ($this->has('isbn')) { // Already have ISBN
            quietly('report_inaction', "Not converting ASIN URL: redundant to existing ISBN.");
          } else {
            quietly('report_modification', "Converting URL to ASIN template");
            $this->set('id', $this->get('id') . " {{ASIN|{$match['id']}|country=" . str_replace(array(".co.", ".com.", "."), "", $match['domain']) . "}}");
          }
          if (is_null($url_sent)) {
            $this->forget($url_type); // will forget accessdate too
            if (stripos($this->get('publisher'), 'amazon') !== FALSE) {
              $this->forget('publisher');
            }
          }
        }
      } elseif (preg_match(REGEXP_HANDLES, $url, $match)) {
          $url_test = "https://hdl.handle.net/" . urlencode($match[1]);
          $headers_test = @get_headers($url_test, 1);  // verify that data is registered
          if ($headers_test !== FALSE && empty($headers_test['Location'])) {  // If we get FALSE, that means that hdl.handle.net is currently down.  In that case we optimisticly assume the HDL resolves, since they almost always do. 
               return FALSE; // does not resolve.
          }
          quietly('report_modification', "Converting URL to HDL parameter");
          if (is_null($url_sent)) {
             $this->forget($url_type);
          }
          if ($this->wikiname() === 'cite web') {
            if ($this->has('journal')) {
              $this->change_name_to('cite journal');
            } else {
              $this->change_name_to('cite document');
            }
          }
          return $this->add_if_new('hdl', $match[1]);
      } elseif (preg_match("~^https?://zbmath\.org/\?format=complete&q=an:([0-9][0-9][0-9][0-9]\.[0-9][0-9][0-9][0-9][0-9])~i", $url, $match)) {
          quietly('report_modification', "Converting URL to ZBL parameter");
          if (is_null($url_sent)) {
             $this->forget($url_type);
          }
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          return $this->add_if_new('zbl', $match[1]);
      } elseif (preg_match("~^https?://zbmath\.org/\?format=complete&q=an:([0-9][0-9]\.[0-9][0-9][0-9][0-9]\.[0-9][0-9])~i", $url, $match)) {
          quietly('report_modification', "Converting URL to JFM parameter");
          if (is_null($url_sent)) {
             $this->forget($url_type);
          }
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          return $this->add_if_new('jfm', $match[1]);
      } elseif (preg_match("~^https?://mathscinet\.ams\.org/mathscinet-getitem\?mr=([0-9]+)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to MR parameter");
          if (is_null($url_sent)) {
             $this->forget($url_type);
          }
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          return $this->add_if_new('mr', $match[1]);
      } elseif (preg_match("~^https?://papers\.ssrn\.com/sol3/papers\.cfm\?abstract_id=([0-9]+)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to SSRN parameter");
          if (is_null($url_sent)) {
             $this->forget($url_type);
          }
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal'); // Better template choice.  Often journal/paper
          return $this->add_if_new('ssrn', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)osti\.gov/(?:scitech/|)(?:biblio/|)(?:purl/|)([0-9]+)(?:\.pdf|)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to OSTI parameter");
          if (is_null($url_sent)) {
             $this->forget($url_type);
          }
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          return $this->add_if_new('osti', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)osti\.gov/energycitations/product\.biblio\.jsp\?osti_id=([0-9]+)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to OSTI parameter");
          if (is_null($url_sent)) {
             $this->forget($url_type);
          }
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          return $this->add_if_new('osti', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)worldcat\.org(?:/title/\S+)?/oclc/([0-9]+)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to OCLC parameter");
          if (is_null($url_sent)) {
             $this->forget($url_type);
          }
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite book');  // Better template choice
          return $this->add_if_new('oclc', $match[1]); 
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

  public function get_doi_from_crossref() {
    if ($this->has('doi')) {
      return TRUE;
    }
    report_action("Checking CrossRef database for doi. ");
    $page_range = $this->page_range();
    $data = [
      'title'      => $this->get('title'),
      'journal'    => $this->get('journal'),
      'author'     => $this->first_surname(),
      'year'       => $this->get('year'),
      'volume'     => $this->get('volume'),
      'start_page' => isset($page_range[1]) ? $page_range[1] : NULL,
      'end_page'   => isset($page_range[2]) ? $page_range[2] : NULL,
      'issn'       => $this->get('issn'),
      'url'        => trim($this->get('url')),
    ];
    
    $novel_data = FALSE;
    foreach ($data as $key => $value) if ($value) {
      if ($this->api_has_not_used('crossref', equivalent_parameters($key))) $novel_data = TRUE;
      $this->record_api_usage('crossref', $key);    
    }

    if (!$novel_data) {
      report_info("No new data since last CrossRef search.");
      return FALSE;
    } 
  
    if ($data['journal'] || $data['issn']) {
      $url = "https://www.crossref.org/openurl/?noredirect=TRUE&pid=" . CROSSREFUSERNAME
           . ($data['title'] ? "&atitle=" . urlencode(de_wikify($data['title'])) : "")
           . ($data['author'] ? "&aulast=" . urlencode($data['author']) : '')
           . ($data['start_page'] ? "&spage=" . urlencode($data['start_page']) : '')
           . ($data['end_page'] > $data['start_page'] ? "&epage=" . urlencode($data['end_page']) : '')
           . ($data['year'] ? "&date=" . urlencode(preg_replace("~([12]\d{3}).*~", "$1", $data['year'])) : '')
           . ($data['volume'] ? "&volume=" . urlencode($data['volume']) : '')
           . ($data['issn'] ? ("&issn=" . $data['issn'])
                            : ($data['journal'] ? "&title=" . urlencode(de_wikify($data['journal'])) : ''));
      if (!($result = @simplexml_load_file($url)->query_result->body->query)){
        report_warning("Error loading simpleXML file from CrossRef.");
      }
      elseif ($result['status'] == 'malformed') {
        report_warning("Cannot search CrossRef: " . echoable($result->msg));
      }
      elseif ($result["status"] == "resolved") {
        if (!isset($result->doi) || is_array($result->doi)) return FALSE; // Never seen array, but pays to be paranoid
        report_info(" Successful!");
        return $this->add_if_new('doi', $result->doi);
      }
    }
    
    if ( !$data['author'] || !($data['journal'] || $data['issn']) || !$data['start_page'] ) return FALSE;
    
    // If fail, try again with fewer constraints...
    report_info("Full search failed. Dropping author & end_page... ");
    $url = "https://www.crossref.org/openurl/?noredirect=TRUE&pid=" . CROSSREFUSERNAME
           . ($data['title'] ? "&atitle=" . urlencode(de_wikify($data['title'])) : "")
           . ($data['issn'] ? "&issn=$issn" 
                            : ($data['journal'] ? "&title=" . urlencode(de_wikify($data['journal'])) : ''))
           . ($data['year'] ? "&date=" . urlencode(preg_replace("~([12]\d{3}).*~", "$1", $data['year'])) : '')
           . ($data['volume'] ? "&volume=" . urlencode($data['volume']) : '')
           . ($data['start_page'] ? "&spage=" . urlencode($data['start_page']) : '');
    
    if (!($result = @simplexml_load_file($url)->query_result->body->query)) {
      report_warning("Error loading simpleXML file from CrossRef.");
    }
    elseif ($result['status'] == 'malformed') {
      report_warning("Cannot search CrossRef: " . echoable($result->msg));
    } elseif ($result["status"]=="resolved") {
      if (!isset($result->doi) || is_array($result->doi)) return FALSE; // Never seen array, but pays to be paranoid
      report_info(" Successful!");
      return $this->add_if_new('doi', $result->doi);
    }
    return FALSE;
  }

  public function find_pmid() {
    if (!$this->blank('pmid')) return;
    report_action("Searching PubMed... ");
    $results = $this->query_pubmed();
    if ($results[1] == 1) {
      $this->add_if_new('pmid', $results[0]);
    } else {
      report_inline("nothing found.");
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
      if (!strpos($doi, "[") && !strpos($doi, "<")) { // Doi's with square brackets and less/greater than cannot search PUBMED (yes, we asked).
        $results = $this->do_pumbed_query(array("doi"), TRUE);
        if ($results[1] == 1) return $results;
      }
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
           $query .= " AND (" . "\"" . str_replace(array("%E2%80%93", ';'), array("-", '%3B'), $val) . "\"" . "[$key])"; // PMID does not like escaped /s in DOIs, but other characters seem problematic.
        } else {
           $query .= " AND (" . "\"" . str_replace("%E2%80%93", "-", urlencode($val)) . "\"" . "[$key])";
        }
      }
    }
    $query = substr($query, 5); // Chop off initial " AND "
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&tool=DOIbot&email=martins+pubmed@gmail.com&term=$query";
    $xml = @simplexml_load_file($url);
    if ($xml === FALSE) {
      report_warning("Unable to do PMID search");
      return array(NULL, 0);
    }
    if ($check_for_errors && $xml->ErrorList) {
      if (isset($xml->ErrorList->PhraseNotFound)) {
        report_warning("Phrase not found in PMID search with query $query: "
        . echoable(print_r($xml->ErrorList, 1)));
      } else {
        report_inline('no results.');
      }
      return array(NULL, 0);
    }

    return $xml ? array((string)$xml->IdList->Id[0], (string)$xml->Count) : array(NULL, 0);// first results; number of results
  }

  public function expand_by_arxiv() {
    expand_arxiv_templates(array($this));
  }

  public function expand_by_adsabs() {
    // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/search.md
    global $SLOW_MODE;
    if (!$SLOW_MODE && $this->lacks('bibcode')) {
     report_info("Skipping AdsAbs API: not in slow mode");
     return FALSE;
    }
    if ($this->has('bibcode') && strpos($this->get('bibcode'), 'book') !== FALSE) {
      return $this->expand_book_adsabs();
    }
    if ($this->api_has_used('adsabs', equivalent_parameters('bibcode'))) {
      report_info("No need to repeat AdsAbs search for " . bibcode_link($this->get('bibcode')));
      return FALSE;
    }
  
    report_action("Checking AdsAbs database");
    if ($bibcode = $this->has('bibcode')) {
      $result = $this->query_adsabs("bibcode:" . urlencode('"' . $this->get("bibcode") . '"'));
    } elseif ($this->has('doi') 
              && preg_match(REGEXP_DOI, $this->get_without_comments_and_placeholders('doi'), $doi)) {
      $result = $this->query_adsabs("doi:" . urlencode('"' . $doi[0] . '"'));
      if ($result->numFound == 0) { // there's a slew of citations, mostly in mathematics, that never get anything but an arxiv bibcode
        if ($this->has('eprint')) {
          $result = $this->query_adsabs("arXiv:" . urlencode('"' .$this->get('eprint') . '"'));
        } elseif ($this->has('arxiv')) {
          $result = $this->query_adsabs("arXiv:" . urlencode('"' .$this->get('arxiv') . '"'));
        }
      }
    } elseif ($this->has('title') || $this->has('eprint') || $this->has('arxiv')) {
      if ($this->has('eprint')) {
        $result = $this->query_adsabs("arXiv:" . urlencode('"' .$this->get('eprint') . '"'));
      } elseif ($this->has('arxiv')) {
        $result = $this->query_adsabs("arXiv:" . urlencode('"' .$this->get('arxiv') . '"'));
      } else {
        $result = (object) array("numFound" => 0);
      }
      if (($result->numFound != 1) && $this->has('title')) { // Do assume failure to find arXiv means that it is not there
        $result = $this->query_adsabs("title:" . urlencode('"' .  trim(str_replace('"', ' ', $this->get_without_comments_and_placeholders("title"))) . '"'));
        if ($result->numFound == 0) return FALSE;
        $record = $result->docs[0];
        if (titles_are_dissimilar($record->title[0], $this->get('title'))) {
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
        . ($this->year() ? ("&year:" . urlencode($this->year())) : '')
        . ($this->has('issn') ? ("&issn:" . urlencode($this->get('issn'))) : '')
        . ($this->has('volume') ? ("&volume:" . urlencode('"' . $this->get('volume') . '"')) : '')
        . ($this->page() ? ("&page:" . urlencode('"' . str_replace(['&mdash;', '--', '&ndash;', '—', '–'], ['-','-','-','-','-'], $this->page()) . '"')) : '')
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
          echoable($journal) . "\".");
        return FALSE;
      }
    }
    if ($result->numFound == 1) {
      $record = $result->docs[0];
      if (isset($record->year) && $this->year()) {
        if (abs((int)$record->year - (int)$this->year()) > 2) {
          return FALSE;  // Probably a book review or something with same title, etc.  have to be fuzzy if arXiv year does not match published year
        }
        if ($this->has('doi') && ((int)$record->year !== (int)$this->year())) {
          return FALSE;  // require exact match if we have doi
        }
      }
      
      if ($this->has('title') && titles_are_dissimilar($record->title[0],$this->get('title')) ) { // Verify the title matches.  We get some strange mis-matches {
        report_info("Similar title not found in database");
        return FALSE;
      }
      
      if (strpos((string) $record->bibcode, 'book') !== FALSE) {  // Found a book.  Need special code
         $this->add('bibcode', (string) $record->bibcode); // not add_if_new or we'll repeat this search!
         return $this->expand_book_adsabs();
      }
      
      if ($this->wikiname() === 'cite book' || $this->wikiname() === 'citation') { // Possible book and we found book review in journal
        $book_count = 0;
        if($this->has('publisher')) $book_count += 1;
        if($this->has('isbn'))      $book_count += 2;
        if($this->has('location'))  $book_count += 1;
        if($this->has('chapter'))   $book_count += 2;
        if($this->has('oclc'))      $book_count += 1;
        if($this->has('lccn'))      $book_count += 2;
        if($this->has('journal'))   $book_count -= 2;
        if($this->wikiname() === 'cite book') $book_count += 3;
        if($book_count > 3) {
          report_info("Suspect that BibCode " . bibcode_link((string) $record->bibcode) . " is book review.  Rejecting.");
          return FALSE;
        }
      }
      
      if ($this->blank('bibcode')) $this->add('bibcode', (string) $record->bibcode); // not add_if_new or we'll repeat this search!
      $this->add_if_new('title', (string) $record->title[0]); // add_if_new will format the title text and check for unknown
      $i = 0;
      if (isset($record->author)) {
       foreach ($record->author as $author) {
        $this->add_if_new('author' . ++$i, $author);
       }
      }
      if (isset($record->pub)) {
        $journal_string = explode(",", (string) $record->pub);
        $journal_start = mb_strtolower($journal_string[0]);
        if (preg_match("~\bthesis\b~ui", $journal_start)) {
          // Do nothing
        } elseif (substr($journal_start, 0, 6) == "eprint") {
          if (substr($journal_start, 7, 6) == "arxiv:") {
            if (isset($record->arxivclass)) $this->add_if_new('class', $record->arxivclass);
            $this->add_if_new('arxiv', substr($journal_start, 13));
          } else {
            $this->append_to('id', ' ' . substr($journal_start, 13));
          }
        } else {
          $this->add_if_new('journal', $journal_string[0]);
        }          
      }
      if (isset($record->page)) {
         if ((stripos(implode('–', $record->page), 'arxiv') !== FALSE) || (stripos(implode('–', $record->page), '/') !== FALSE)) {  // Bad data
          unset($record->page);
          unset($record->volume);
          unset($record->issue);
         }
       }
      if (isset($record->volume)) {
        $this->add_if_new('volume', (string) $record->volume);
      }
      if (isset($record->issue)) {
        $this->add_if_new('issue', (string) $record->issue);
      }
      if (isset($record->year)) {
        $this->add_if_new('year', preg_replace("~\D~", "", (string) $record->year));
      }
      if (isset($record->page)) {
        $this->add_if_new('pages', implode('–', $record->page));
      }
      if (isset($record->identifier)) { // Sometimes arXiv is in journal (see above), sometimes here in identifier
        foreach ($record->identifier as $recid) {
          if(strtolower(substr($recid, 0, 6)) === 'arxiv:') {
             if (isset($record->arxivclass)) $this->add_if_new('class', $record->arxivclass);
             $this->add_if_new('arxiv', substr($recid, 6));
          }
        }
      }
      if (isset($record->doi)) {
        $this->add_if_new('doi', (string) $record->doi[0]);          
      }
      return TRUE;
    } else {
      report_inline('no record retrieved.');
      return FALSE;
    }
  }
  
  protected function expand_book_adsabs() {
    $result = $this->query_adsabs("bibcode:" . urlencode('"' . $this->get("bibcode") . '"'));
    if ($result->numFound == 1) {
      $record = $result->docs[0];
      if (isset($record->year)) $this->add_if_new('year', preg_replace("~\D~", "", (string) $record->year));
      if (isset($record->title)) $this->add_if_new('title', (string) $record->title[0]);
      if ($this->blank(array_merge(EDITOR1_ALIASES, AUTHOR1_ALIASES, ['publisher']))) { // Avoid re-adding editors as authors, etc.
       $i = 0;
       if (isset($record->author)) {
        foreach ($record->author as $author) {
         $this->add_if_new('author' . ++$i, $author);
        }
       }
      }
    }
    if ($this->blank(['year', 'date']) && preg_match('~^(\d{4}).*book.*$~', $this->get("bibcode"), $matches)) {
      $this->add_if_new('year', $matches[1]); // Fail safe code to grab a year directly from the bibcode itself
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
      $return = curl_exec($ch);
      if (502 === curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
        sleep(4);
        $return = curl_exec($ch);
        if (502 === curl_getinfo($ch, CURLINFO_HTTP_CODE) && getenv('TRAVIS')) {
           sleep(20); // better slow than not at all in TRAVIS
           $return = curl_exec($ch);
        }
      }
      if ($return === FALSE) {
        $exception = curl_error($ch);
        $number = curl_errno($ch);
        curl_close($ch);
        throw new Exception($exception, $number);
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
        report_warning(sprintf("API Error in query_adsabs: %s",
                      $e->getMessage()));
      } else if (strpos($e->getMessage(), 'HTTP') === 0) {
        report_warning(sprintf("HTTP Error %d in query_adsabs: %s",
                      $e->getCode(), $e->getMessage()));
      } else {
        report_warning(sprintf("Error %d in query_adsabs: %s",
                      $e->getCode(), $e->getMessage()));
      }
      return (object) array('numFound' => 0);
    }
  }
  
  public function expand_by_RIS(&$dat, $add_url) { // Pass by pointer to wipe this data when called from use_unnamed_params()
    $ris_review    = FALSE;
    $ris_issn      = FALSE;
    $ris_publisher = FALSE;
    // Convert &#x__; to characters
    $ris = explode("\n", html_entity_decode($dat, NULL, 'UTF-8'));
    $ris_authors = 0;
    
    if(preg_match('~(?:T[I1]).*-(.*)$~m', $dat,  $match)) {
        if(in_array(strtolower(trim($match[1])), BAD_ACCEPTED_MANUSCRIPT_TITLES)) return FALSE ;
    }
    
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
          $ris_parameter = doi_active($ris_part[1]) ? "doi" : FALSE;
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
              && (($ris_parameter=='url' && !$add_url) || $this->add_if_new($ris_parameter, trim(implode($ris_part))))
          ) {
        $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
      }
    }
    if ($ris_review) $this->add_if_new('title', trim($ris_review));  // Do at end in case we have real title
    if (isset($start_page)) { // Have to do at end since might get end pages before start pages
      if (isset($end_page) && ($start_page != $end_page)) {
         $this->add_if_new('pages', $start_page . '–' . $end_page);
      } else {
         $this->add_if_new('pages', $start_page);
      }
    }
    if ($this->blank('journal')) { // doing at end avoids adding if we have journal title
      if ($ris_issn) $this->add_if_new('issn', $ris_issn);
      if ($ris_publisher) $this->add_if_new('publisher', $ris_publisher);
    }
  }
 
  public function expand_by_pubmed($force = FALSE) {
    if (!$force && !$this->incomplete()) return;
    if ($pm = $this->get('pmid')) {
      report_action('Checking ' . pubmed_link('pmid', $pm) . ' for more details');
      query_pmid_api(array($pm), array($this));
    } elseif ($pm = $this->get('pmc')) {
      report_action('Checking ' . pubmed_link('pmc', $pm) . ' for more details');
      query_pmc_api(array($pm), array($this));
    }
  }

  protected function use_sici() {
    if (preg_match(REGEXP_SICI, urldecode($this->parsed_text()), $sici)) {
      quietly('report_action', "Extracting information from SICI");
      $this->add_if_new('issn', $sici[1]); // Check whether journal is set in add_if_new
      //if ($this->blank ("year") && $this->blank('month') && $sici[3]) $this->set('month', date("M", mktime(0, 0, 0, $sici[3], 1, 2005)));
      $this->add_if_new('year', $sici[2]);
      //if ($this->blank('day') && is("month") && $sici[4]) set ("day", $sici[4]);
      $this->add_if_new('volume', 1*$sici[5]);
      if ($sici[6]) $this->add_if_new('issue', 1*$sici[6]);
      $this->add_if_new('pages', 1*$sici[7]);
      return TRUE;
    } else return FALSE;
  }

  public function get_open_access_url() {
    if (!$this->blank(DOI_BROKEN_ALIASES)) return;
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
        if (@$best_location->evidence == 'oa repository (via OAI-PMH title and first author match)') {
          // false positives are too common
          return FALSE;
        }  
        // sometimes url_for_landing_page = null, eg http://api.oadoi.org/v2/10.1145/3238147.3240474?email=m@f
        if ($best_location->url_for_landing_page != null) {
          $oa_url = $best_location->url_for_landing_page;
        } elseif ($best_location->url_for_pdf != null) {
          $oa_url = $best_location->url_for_pdf;
        } elseif ($best_location->url != null) {
          $oa_url = $best_location->url;
        } else {
          return FALSE;
        }
        if ($this->get('url')) {
            if ($this->get('url') !== $oa_url) $this->get_identifiers_from_url($oa_url);  // Maybe we can get a new link type
            return TRUE;
        }
        if (stripos($oa_url, 'bioone.org/doi') !== FALSE) return TRUE;
        if (stripos($oa_url, 'gateway.isiknowledge.com') !== FALSE) return TRUE;
        if (stripos($oa_url, 'zenodo.org') !== FALSE) return TRUE;   //is currently blacklisted due to copyright concerns https://en.wikipedia.org/w/index.php?oldid=867438103#zenodo.org
        // Check if best location is already linked -- avoid double linki
        if (preg_match("~^https?://europepmc\.org/articles/pmc(\d+)~", $oa_url, $match) || preg_match("~^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                      . "|^https?://www\.ncbi\.nlm\.nih\.gov/(?:m/)?pmc/articles/PMC(\d+)~", $oa_url, $match)) {
          if ($this->has('pmc') ) {
             return TRUE;
          }
        }
        if (preg_match("~\barxiv\.org/.*(?:pdf|abs)/(.+)$~", $oa_url, $match)) {
          if ($this->has('arxiv') || $this->has('eprint')) {
             return TRUE;
          }
        }
        if ($this->has('hdl') ) {
          if (stripos($oa_url, $this->get('hdl')) !== FALSE) return TRUE;
          if (preg_match(REGEXP_HANDLES, $oa_url)) return TRUE;
        }
        if (strpos($oa_url, 'citeseerx.ist.psu.edu') !== false) {
          if ($this->has('citeseerx') ) {
             return TRUE;
          }
        }
        if (preg_match(REGEXP_BIBCODE, urldecode($oa_url), $bibcode)) {
           if ($this->has('bibcode')) {
             return TRUE;
          }
        }
        if (preg_match("~https?://www.ncbi.nlm.nih.gov/(?:m/)?pubmed/.*?=?(\d{5,})~", $oa_url, $match)) {
          if ($this->has('pmid')) {
             return TRUE;
          }
        }
        if (preg_match("~^https?://d?x?\.?doi\.org/*~", $oa_url, $match)) {
          if ($this->has('doi')) {
             return TRUE;
          }
        }
        if (preg_match("~^https?://doi\.library\.ubc\.ca/*~", $oa_url, $match)) {
          if ($this->has('doi')) {
             return TRUE;
          }
        }

        if ($this->has('arxiv') ||
            $this->has('biorxiv') ||
            $this->has('citeseerx') ||
            $this->has('pmc') ||
            $this->has('rfc') ||
            $this->has('ssrn') ||
            ($this->has('doi') && $this->get('doi-access') === 'free') ||
            ($this->has('jstor') && $this->get('jstor-access') === 'free') ||
            ($this->has('osti') && $this->get('osti-access') === 'free') ||
            ($this->has('ol') && $this->get('ol-access') === 'free')
           ) return TRUE; // do not add url if have OA already
        
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
            // if ($this->blank('url')) $this->add('url', $url); // This pissed off a lot of people.
            // And blank url does not mean not linked in title, etc.
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
            report_warning("Did not receive results from Google API search $url_token");
            return FALSE;
        }
        $result = @json_decode($string, FALSE);
        if (isset($result)) {
          if (isset($result->totalItems)) {
            if ($result->totalItems === 1 && isset($result->items[0]) && isset($result->items[0]->id) ) {
              $gid=$result->items[0]->id;
              $url = 'https://books.google.com/books?id=' . $gid;
            } else {
              report_info("No results for Google API search $url_token");
            }
          } elseif (isset($result->error)) {
            report_warning("Google Books API reported error: " . print_r($result->error->errors, TRUE));
          } else {
            report_warning("Could not parse Google API results for $url_token");
            return FALSE;
          }
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
      $this->add_if_new('title',  
               wikify_external_text(
                 str_replace("___", ":", $xml->dc___title[0] . ": " . $xml->dc___title[1]),
                 TRUE // $caps_after_punctuation
               )
             );
    } else {
      $this->add_if_new('title',  wikify_external_text(str_replace("___", ":", $xml->title)));
    }
    // Possibly contains dud information on occasion
    // $this->add_if_new('publisher', str_replace("___", ":", $xml->dc___publisher)); 
    $isbn = NULL;
    foreach ($xml->dc___identifier as $ident) {
      if (preg_match("~isbn.*?([\d\-]{9}[\d\-]+)~i", (string) $ident, $match)) {
        $isbn = $match[1];
      }
    }
    $this->add_if_new('isbn', $isbn);
    
    $i = 0;
    if ($this->blank(array_merge(EDITOR1_ALIASES, AUTHOR1_ALIASES, ['publisher']))) { // Too many errors in gBook database to add to existing data.   Only add if blank.
      foreach ($xml->dc___creator as $author) {
        $this->validate_and_add('author' . ++$i, str_replace("___", ":", $author));
      }
    }
    
    $google_date = sanitize_string(trim( (string) $xml->dc___date )); // Google often sends us YYYY-MM
    if (substr_count($google_date, "-") === 1) {
        $date = @date_create($google_date);
        if ($date !== FALSE) {
          $date = @date_format($date, "F Y");
          if ($date !== FALSE) {
            $google_date = $date; // only now change data
          }
        }
    }
    $this->add_if_new('date', $google_date);
    // Don't set 'pages' parameter, as this refers to the CITED pages, not the page count of the book.
    // foreach ($xml->dc___format as $format) {
    //   if (preg_match("~([\d\-]+)~", $format, $matches)) {
    //      $this->add_if_new('pages', '1–' . (string) $matches[0]); // If we did add the total pages, then we should include the whole range
    //   }
    // }
  }

  ### parameter processing
  protected function parameter_names_to_lowercase() {
    if (is_array($this->param)) {
      $keys = array_keys($this->param);
      $to_tidy = array();
      for ($i = 0; $i < count($keys); $i++) {
        if (!ctype_lower($this->param[$keys[$i]]->param)) {
          $this->param[$keys[$i]]->param = strtolower($this->param[$keys[$i]]->param);
          array_push($to_tidy, $this->param[$keys[$i]]->param);
        }
      }
    } else {
      $this->param = strtolower($this->param);
      $to_tidy = array($this->param);
    }
    // Tidy afterwards, to avoid modifying array index
    foreach ($to_tidy as $param) $this->tidy_parameter($param);
  }

  protected function use_unnamed_params() {
    if (empty($this->param)) return;
    
    $this->parameter_names_to_lowercase();
    $param_occurrences = array();
    $duplicated_parameters = array();
    $duplicate_identical = array();
    
    foreach ($this->param as $pointer => $par) {
      if ($par->param && isset($param_occurrences[$par->param])) {
        $duplicate_pos = $param_occurrences[$par->param];
        if ($par->val === '') {
          $par->val = $this->param[$duplicate_pos]->val;
        } elseif ($this->param[$duplicate_pos]->val === '') {
          $this->param[$duplicate_pos]->val = $par->val;
        }
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
          echoable($this->param[$duplicated_parameters[$i]]->param));
      }
    }
    
    foreach ($this->param as $param_key => $p) {
      if (!empty($p->param)) {
        if (preg_match('~^\s*(https?://|www\.)\S+~', $p->param)) { # URL ending ~ xxx.com/?para=val
          $this->param[$param_key]->val = $p->param . '=' . $p->val;
          $this->param[$param_key]->param = 'url';
          if (stripos($p->val, 'books.google.') !== FALSE) {
            $this->change_name_to('cite book');
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
              $this->add_if_new('author' . ++$endnote_authors, format_author($endnote_datum));
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
        $this->expand_by_RIS($dat, TRUE);
      }
      
      $doi = extract_doi($dat);
      if (!is_null($doi)) {
        $this->add_if_new('doi', $doi[1]); 
        $this->change_name_to('cite journal');
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
      // Deal with # values
      if(preg_match('~\d+~', $dat, $match)) {
        $closest = str_replace('#', $match[0], $closest);
        $comp    = str_replace('#', $match[0], $comp);
      } else {
        $closest = str_replace('#', "", $closest);
        $comp    = str_replace('#', "", $comp);
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
        if ($this->blank(['year', 'date'])) {
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
              $this->add_if_new('arxiv', $archive_parameter . $subtemplate->get('id'));
            } elseif (!is_null($subtemplate->param_with_index(1))) {
              $this->add_if_new('arxiv', trim($subtemplate->param_value(0)) .
                                "/" . trim($subtemplate->param_value(1)));
            } else {
              $this->add_if_new('arxiv', $subtemplate->param_value(0));
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
              report_info($subtemplate->parsed_text());
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

    if ((strlen($p->param) > 0) && !in_array(preg_replace('~\d+~', '#', $p->param), $parameter_list) && stripos($p->param, 'CITATION_BOT')===FALSE) {
     
      if (trim($p->val) === '') {
        if (strpos($p->param, 'DUPLICATE_') === 0) {
          report_forget("Dropping empty left-over duplicate parameter " . echoable($p->param) . " ");
        } else {
          report_forget("Dropping empty unrecognised parameter " . echoable($p->param) . " ");
        }
        $this->quietly_forget($p->param);
        continue;
      }
      
      if (strpos($p->param, 'DUPLICATE_') === 0) {
        report_modification("Left-over duplicate parameter " . echoable($p->param) . " ");
      } else {
        report_modification("Unrecognised parameter " . echoable($p->param) . " ");
      }
      $mistake_id = array_search($p->param, $mistake_keys);
      if ($mistake_id) {
        // Check for common mistakes.  This will over-ride anything found by levenshtein: important for "editor1link" !-> "editor-link" (though this example is no longer relevant as of 2017)
        $p->param = $mistake_corrections[$mistake_id];
        report_modification('replaced with ' . $mistake_corrections[$mistake_id] . ' (common mistakes list)');
        continue;
      }
      
      $p->param = preg_replace('~author(\d+)-(la|fir)st~', "$2st$1", $p->param);
      $p->param = preg_replace('~surname\-?_?(\d+)~', "last$1", $p->param);
      $p->param = preg_replace('~(?:forename|initials?)\-?_?(\d+)~', "first$1", $p->param);

      // Check the parameter list to find a likely replacement
      $shortest = -1;
      $closest = 0;
      
      if (preg_match('~\d+~', $p->param, $match)) { // Deal with # values
         $param_number = $match[0];
      } else {
         $param_number = '#';
      }
      foreach ($unused_parameters as $parameter) {
        $parameter = str_replace('#', $param_number, $parameter);
        if (strpos($parameter, '#') !== FALSE) continue; // Do no use # items unless we have a number
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
        report_inline("replaced with $closest (likelihood " . (24 - $shortest) . "/24)"); // Scale arbitrarily re-based by adding 12 so users are more impressed by size of similarity
      } else {
        $similarity = similar_text($p->param, $closest) / strlen($p->param);
        if ($similarity > 0.6) {
          $p->param = $closest;
          report_inline("replaced with $closest (similarity " . (round(2 * 12 * $similarity, 1)) . "/24)"); // Scale arbitrarily re-based by multiplying by 2 so users are more impressed by size of similarity
        } else {
          report_inline("could not be replaced with confidence.  Please check the citation yourself.");
        }
      }
    }
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

  public function change_name_to($new_name, $rename_cite_book = TRUE) {
    $new_name = strtolower(trim($new_name)); // Match wikiname() output and cite book below
    if (in_array($this->wikiname(), TEMPLATES_WE_RENAME)
    && ($rename_cite_book || $this->wikiname() != 'cite book')
    && $new_name != $this->wikiname()
    ) {
      if ($new_name === 'cite arxiv') {
        if (!$this->blank(['website','displayauthors','display-authors','access-date','accessdate'])) return; // Unsupported parameters
        $new_name = 'cite arXiv';  // Without the capital X is the alias
      }
      preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
      if (substr($this->name,0,1) === 'c') {
        $this->name = $spacing[1] . $new_name . $spacing[2];
      } else {
        $this->name = $spacing[1] . ucfirst($new_name) . $spacing[2];
      }
      switch (strtolower($new_name)) {
        case 'cite journal': 
          $this->rename('eprint', 'arxiv'); 
          $this->forget('class'); 
          break;
      }
    }
    if ($new_name === 'cite book') {
      // all open-access versions of conference papers point to the paper itself
      // not to the whole proceedings
      // so we use chapter-url so that the template is well rendered afterwards
      if ($this->blank(['chapter-url','chapterurl']) && $this->has('chapter')) {
        $this->rename('url', 'chapter-url');
        $this->rename('format', 'chapter-format');
      } elseif (!$this->blank(['chapter-url','chapterurl']) && (0 === strcasecmp($this->get('chapter-url'), $this->get('url')))) {
        $this->forget('url');
      }  // otherwise they are differnt urls
    }
  }
  
  public function wikiname() {
    return trim(mb_strtolower(str_replace('_', ' ', $this->name)));
  }
  
  public function should_be_processed() {
    return in_array($this->wikiname(), TEMPLATES_WE_PROCESS);
  }
  
  public function tidy_parameter($param) {
    // Note: Parameters are treated in alphabetical order, except where one
    // case necessarily continues from the previous (without a return).
    
    if (!$param) return FALSE;
    if (mb_stripos($this->get($param), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
      return FALSE;  // We let comments block the bot
    }
    
    if($this->has($param)) {
      if (stripos($param, 'separator') === FALSE &&  // lone punctuation valid
          stripos($param, 'postscript') === FALSE &&  // periods valid
          stripos($param, 'url') === FALSE &&  // all characters are valid
          stripos($param, 'quot') === FALSE) { // someone might have formatted the quote
        $this->set($param, preg_replace('~[\x{2000}-\x{200A}]~u', ' ', $this->get($param))); // Non-standard spaces
        $this->set($param, preg_replace('~[\t\n\r\0\x0B]~u', ' ', $this->get($param))); // tabs, linefeeds, null bytes
        $this->set($param, preg_replace('~  +~u', ' ', $this->get($param))); // multiple spaces
        $this->set($param, preg_replace('~[:,]+$~u', '', $this->get($param)));  // Remove trailing commas, colons, but not semi-colons--They are HTML encoding stuff
      }
    }
    if (preg_match("~^[\'\"]+([^\'\"]+)[\'\"]+$~u", $this->get($param), $matches)) {
      $this->set($param, $matches[1]); // Remove quotes, if only at start and end
    }
        
    if (!preg_match('~(\D+)(\d*)~', $param, $pmatch)) {
      report_warning("Unrecognized parameter name format in $param");
      return FALSE;
    } else {
      switch ($pmatch[1]) {
        // Parameters are listed alphabetically, though those with numerical content are grouped under "year"

        case 'accessdate':
        case 'access-date':
          if ($this->has($pmatch[1]) && $this->blank(['url', 'chapter-url', 'chapterurl', 'contribution-url', 'contributionurl']))
          {
            $this->forget($pmatch[1]);
          }
          return;

        case 'arxiv':
          if ($this->has($param) && $this->wikiname() == 'cite web') {
            $this->change_name_to('cite arxiv');
          }
          return;
          
        case 'author': case 'authors':
          if (!$pmatch[2]) {
            if ($this->has('author') && $this->has('authors')) {
              $this->rename('author', 'DUPLICATE_authors');
              $authors = $this->get('authors');
            } else {
              $authors = $this->get($param);
            }
            if (!$this->initial_author_params) {
              $this->handle_et_al();
            }
          }
          // Continue from authors without break
          case 'last': case 'surname':
            if (!$this->initial_author_params) {
              if ($pmatch[2]) {
                if (preg_match("~\[\[(([^\|]+)\|)?([^\]]+)\]?\]?~", $this->get($param), $match)) {
                  $this->add_if_new('authorlink' . $pmatch[2], ucfirst($match[2] ? $match[2] : $match[3]));
                  $this->set($param, $match[3]);
                  report_modification("Dissecting authorlink");
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
            }
            return;

        case 'bibcode':
          $bibcode_journal = substr($this->get($param), 4);
          foreach (NON_JOURNAL_BIBCODES as $exception) {
            if (substr($bibcode_journal, 0, strlen($exception)) == $exception) return;
          }
          if (strpos($this->get($param), 'book') !== FALSE) {
            $this->change_name_to('cite book', FALSE);
          } else {
            $this->change_name_to('cite journal', FALSE);
          }
          return;
          
        case 'chapter': 
          if ($this->has('chapter')) {
            if (str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');
            if (str_equivalent($this->get('chapter'), $this->get('title'))) {
              $this->forget('chapter'); 
              return; // Nonsense to have both.
            }
          }
          if ($this->has('chapter') && $this->blank(['journal', 'bibcode', 'jstor', 'pmid'])) {
            $this->change_name_to('cite book');
          }
          return;
    
        case 'coauthor': case 'coauthors':  // Commonly left there and empty and deprecated
          if ($this->blank($param)) $this->forget($param);
          return;
          
        case 'doi':
          $doi = $this->get($param);
          if (!$doi) return;
          if ($doi == "10.1267/science.040579197") {
            // This is a bogus DOI from the PMID example file
            $this->forget('doi'); 
            return;
          }
          $this->set($param, sanitize_doi($doi));
          if (!preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) $this->change_name_to('cite journal', FALSE);
          if (preg_match('~^10\.2307/(\d+)$~', $this->get_without_comments_and_placeholders('doi'))) {
            $this->add_if_new('jstor', substr($this->get_without_comments_and_placeholders('doi'), 8));
          }
          return;
          
        case 'edition': 
          $this->set($param, preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $this->get($param)));
          return; // Don't want 'Edition ed.'
        
        case 'eprint':
          if ($this->wikiname() == 'cite web') $this->change_name_to('cite arxiv');
          return;
          
        case 'format': // clean up bot's old (pre-2018-09-18) edits
          if ($this->get($param) === 'Accepted manuscript' ||
              $this->get($param) === 'Submitted manuscript' ||
              $this->get($param) === 'Full text') {
            $this->forget($param);
          }
          // Citation templates do this automatically -- also remove if there is no url, which is template error
          if (in_array(strtolower($this->get($param)), ['pdf', 'portable document format', '[[portable document format|pdf]]', '[[portable document format]]', '[[pdf]]'])) {
            if ($this->blank('url') || strtolower(substr($this->get('url'), -4)) === '.pdf') {
               $this->forget($param);
            }
          }
          return;
          
        case 'chapter-format':
        // clean up bot's old (pre-2018-09-18) edits
          if ($this->get($param) === 'Accepted manuscript' ||
              $this->get($param) === 'Submitted manuscript' ||
              $this->get($param) === 'Full text') {
            $this->forget($param);
          }
          // Citation templates do this automatically -- also remove if there is no url, which is template error
          if (in_array(strtolower($this->get($param)), ['pdf', 'portable document format', '[[portable document format|pdf]]', '[[portable document format]]'])) {
             if ($this->has('chapter-url')) {
               if (substr($this->get('chapter-url'), -4) === '.pdf' || substr($this->get('chapter-url'), -4) === '.PDF') {
                 $this->forget($param);
               }
             } elseif ($this->has('chapterurl')) {
               if (substr($this->get('chapterurl'), -4) === '.pdf' || substr($this->get('chapterurl'), -4) === '.PDF') {
                 $this->forget($param);
               }
             } else {
               $this->forget($param); // Has no chapter URL at all
             }
          }
          return;
          
        case 'isbn':
          if ($this->lacks('isbn')) return;
          $this->set('isbn', $this->isbn10Toisbn13($this->get('isbn')));
          if ($this->blank('journal') || $this->has('chapter') || $this->wikiname() === 'cite web') {
            $this->change_name_to('cite book');
          }
          $this->forget('asin');
          return;
          
        case 'journal':
          if ($this->lacks($param)) return;
          if ($this->blank(['chapter', 'isbn'])) {
            // Avoid renaming between cite journal and cite book
            $this->change_name_to('cite journal');
          }
          if (str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');
          // No break here: Continue on from journal into periodical
        case 'periodical':
          $periodical = $this->get($param);
          if (mb_substr($periodical, -1) === "," ) {
            $periodical = mb_substr($periodical, 0, -1);
            $this->set($param, $periodical);  // Remove comma
          }
          if (substr(strtolower($periodical), 0, 7) === 'http://' || substr(strtolower($periodical), 0, 8) === 'https://') {
             if ($this->blank('url')) $this->rename($param, 'url');
             return;
          } elseif (substr(strtolower($periodical), 0, 4) === 'www.') {
             if ($this->blank('website')) $this->rename($param, 'website');
             return;
          } elseif ( mb_substr($periodical, 0, 2) !== "[["   // Only remove partial wikilinks
                    || mb_substr($periodical, -2) !== "]]"
                    || mb_substr_count($periodical, '[[') !== 1 
                    || mb_substr_count($periodical, ']]') !== 1
                    )
          {
              $this->set($param, preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $periodical));
              $this->set($param, preg_replace(REGEXP_PIPED_WIKILINK, "$2", $this->get($param)));
          }
          $periodical = $this->get($param);
          if (substr($periodical, 0, 1) !== "[" && substr($periodical, -1) !== "]") { 
             $this->set($param, title_capitalization(ucwords($periodical), TRUE));
          }
          return;
        
        case 'jstor':
          $this->change_name_to('cite journal', FALSE);
          return;
        
        case 'magazine':
          // Remember, we don't process cite magazine.
          if ($this->wikiname() == 'cite journal' && !$this->has('journal')) {
            $this->rename('magazine', 'journal');
          }
          return;
        
        case 'origyear':
          if ($this->has('origyear') && $this->blank(['date', 'year'])) {
            $this->rename('origyear', 'year');
          }
          return;
        
        case 'pmc':
          if (preg_match("~pmc(\d+)$~i", $this->get($param), $matches)) {
             $this->set($param, $matches[1]);
          }
          // No break; continue from pmc to pmid:
        case 'pmid':
          $this->change_name_to('cite journal', FALSE);
          return;
          
        case 'publisher':
          $publisher = strtolower($this->get($param));
          if (stripos($this->get('url'), 'maps.google') !== FALSE && stripos($publisher, 'google') !== FALSE)  {
            $this->set($param, 'Google Maps');  // Case when Google actually IS a publisher
            return;
          }
          foreach (NON_PUBLISHERS as $not_publisher) {
            if (strpos($publisher, $not_publisher) !== FALSE) {
              $this->forget($param);
              return;
            }
          }
          if (str_replace(array('[', ' ', ']'), '', $publisher) == 'google') {
            $this->forget($param);
          }
          if (strtolower($this->get('journal')) === $publisher) {
            $this->forget($param);
          }
          if (strtolower($this->get('newspaper')) === $publisher) {
            $this->forget($param);
          }
          return;
          
        case 'quotes':
          switch(strtolower(trim($this->get($param)))) {
            case 'yes': case 'y': case 'true': case 'no': case 'n': case 'false': $this->forget($param);
          }
          return;

        case 'series':
          if (str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');
          return;
          
        case 'title':
          $title = $this->get($param);
          $title = straighten_quotes($title);
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
             $title = preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $title);   // Convert [[X]] wikilinks into X
             $title = preg_replace(REGEXP_PIPED_WIKILINK, "$2", $title);   // Convert [[Y|X]] wikilinks into X
             $title = preg_replace("~\[\[~", "", $title); // Remove any extra [[ or ]] that should not be there
             $title = preg_replace("~\]\]~", "", $title);
          } else { // Convert a single link to a title-link
             if (preg_match(REGEXP_PLAIN_WIKILINK, $title, $matches)) {
               $this->add_if_new('title-link', $matches[1]);
               $title = str_replace(array("[[", "]]"), "", $title);
             } elseif (preg_match(REGEXP_PIPED_WIKILINK, $title, $matches)) {
               $this->add_if_new('title-link', $matches[1]);
               $title = preg_replace(REGEXP_PIPED_WIKILINK, "$2", $title);
             }
          }
          if (mb_substr($title, mb_strlen($title) - 3) == '...') {
            // MOS:ELLIPSIS says do not do
            // $title = mb_substr($title, 0, mb_strlen($title) - 3) 
            //        . html_entity_decode("&hellip;", NULL, 'UTF-8');
          } elseif (in_array(mb_substr($title, -1), array(',', ':'))) { 
              // Do not remove periods, which legitimately occur at the end of abreviations
              $title = mb_substr($title, 0, -1);
          }
          $this->set($param, $title);
          if ($title && str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');
          if ($title && str_equivalent($this->get($param), $this->get('encyclopedia'))) $this->forget('$param');
          if (preg_match('~^(.+)\{\{!\}\} Request PDF$~i', trim($this->get($param)), $match)) {
                 $this->set($param, trim($match[1]));
          }
          return;
     
        case 'chapter-url':
        case 'chapterurl':
          if ($this->blank(['url', 'chapter'])) {
            $this->rename($param, 'url');
            $param = 'url'; // passes down to next area
          }
        case 'url':
          if (preg_match("~^https?://(?:www\.|)researchgate\.net/[^\s]*publication/([0-9]+)_*~i", $this->get($param), $matches)) {
              $this->set($param, 'https://www.researchgate.net/publication/' . $matches[1]);
              if (preg_match('~^\(PDF\)(.+)$~i', trim($this->get('title')), $match)) {
                 $this->set('title', trim($match[1]));
              }
          } elseif (preg_match("~^https?://(?:www\.|)academia\.edu/([0-9]+)/*~i", $this->get($param), $matches)) {
              $this->set($param, 'https://www.academia.edu/' . $matches[1]);
          //} elseif (preg_match("~^https?://(?:www\.|)zenodo\.org/record/([0-9]+)(?:#|/files/)~i", $this->get($param), $matches)) {
          //    $this->set($param, 'https://zenodo.org/record/' . $matches[1]);
          } elseif (preg_match("~^https?://(?:www\.|)google\.com/search~i", $this->get($param))) {
              $this->set($param, $this->simplify_google_search($this->get($param)));
          } elseif (preg_match("~^(https?://(?:www\.|)sciencedirect\.com/\S+)\?via(?:%3d|=)\S*$~i", $this->get($param), $matches)) {
              $this->set($param, $matches[1]);
          }
          if ($param === 'url' && $this->blank(['chapterurl', 'chapter-url']) && $this->has('chapter') && $this->wikiname() === 'cite book') {
            $this->rename($param, 'chapter-url');
            $this->rename('format', 'chapter-format');
            $param = 'chapter-url';
          }
          return;
        
        case 'work':
          if ($this->has('work')
          && (  str_equivalent($this->get('work'), $this->get('series'))
             || str_equivalent($this->get('work'), $this->get('title'))
             || str_equivalent($this->get('work'), $this->get('journal'))
             || str_equivalent($this->get('work'), $this->get('website'))
             )
          ) {
            $this->forget('work');
            return;
          }
          switch ($this->wikiname()) {
            case 'cite book': $work_becomes = 'title'; break;
            case 'cite journal': $work_becomes = 'journal'; break;
            // case 'cite web': $work_becomes = 'website'; break;  this change should correct, but way too much crap gets put in work that does not belong there.  Secondly this make no change to the what the user sees
            default: $work_becomes = 'work';
          }
          if ($this->get($param) !== NULL && $this->blank($work_becomes)) {
            $this->rename('work', $work_becomes);
          }
          if ($this->wikiname() === 'cite book') {
            $publisher = strtolower($this->get($param));
            foreach (NON_PUBLISHERS as $not_publisher) {
              if (strpos($publisher, $not_publisher) !== FALSE) {
                $this->forget($param);
                return;
              }
            }
          }
          return;
          
        case 'via':   // Should just remove all 'via' with no url, but do not want to make people angry
          if ($this->has('via') && $this->blank(['url', 'chapter-url', 'chapterurl', 'contribution-url', 'contributionurl'])) {
            if (stripos($this->get('via'), 'PubMed') !== FALSE && ($this->has('pmc') || $this->has('pmid'))) {
              $this->forget('via');
            } elseif (stripos($this->get('via'), 'JSTOR') !== FALSE && $this->has('jstor')) {
              $this->forget('via');
            } elseif ($this->has('pmc') || $this->has('pmid') || ($this->has('doi') && $this->blank(DOI_BROKEN_ALIASES))) {
              if ((stripos($this->get('via'), 'Project MUSE') !== FALSE) ||
                  (stripos($this->get('via'), 'Wiley') !== FALSE) ||
                  (stripos($this->get('via'), 'springer') !== FALSE) ||
                  (stripos($this->get('via'), 'elsevier') !== FALSE)
              ) { 
                $this->forget('via');
              }
            } 
          }
          return;
        case 'volume':
          $temp_string = strtolower($this->get('journal')) ;
          if(substr($temp_string, 0, 2) === "[[" && substr($temp_string, -2) === "]]") {  // Wikilinked journal title 
               $temp_string = substr(substr($temp_string, 2), 0, -2); // Remove [[ and ]]
          }
          if (in_array($temp_string, HAS_NO_VOLUME) === TRUE ) {
            if ($this->blank(ISSUE_ALIASES)) {
              $this->rename('volume', 'issue');
            } else {
              $this->forget('volume');
            }
          }
          if (preg_match("~^(\d+)\s*\((\d+(-|–|\–|\{\{ndash\}\})?\d*)\)$~", trim($this->get('volume')), $matches) ||
              preg_match("~^(?:vol. |)(\d+),\s*(?:no\.|number|issue)\s*(\d+(-|–|\–|\{\{ndash\}\})?\d*)$~i", trim($this->get('volume')), $matches) ||
              preg_match("~^(\d+)\.(\d+)$~i", trim($this->get('volume')), $matches)
             ) {
            $possible_volume=$matches[1];
            $possible_issue=$matches[2];
            if ($this->blank(ISSUE_ALIASES)) {
              $this->add_if_new('issue', $possible_issue);
              $this->set('volume',$possible_volume); 
            } elseif ($this->get('issue') === $possible_issue || $this->get('number') === $possible_issue) {
              $this->set('volume', $possible_volume);
            }               
          }
          return;
          
        case 'year':
          if (preg_match("~\d\d*\-\d\d*\-\d\d*~", $this->get('year'))) { // We have more than one dash, must not be range of years.
             if ($this->blank('date')) $this->rename('year', 'date');
             $this->forget('year');
             return;
          }
          if ($this->get($param) === 'n.d.') return; // Special no-date code that citation template recognize.
          // Issue should follow year with no break.  [A bit of redundant execution but simpler.]
        case 'issue':
        case 'number':
          $value = trim($this->get($param));
          if ($param === 'issue' || $param === 'number') {
            if (preg_match('~^No\.? *(\d+)$~i', $value, $matches)) {
              $value = $matches[1];
            }
          }
          // Remove leading zeroes
          $value = preg_replace('~^0+~', '', $value);
          if ($value) {
            $this->set($param, $value);
          } else {
            if(!$this->blank($param)) $this->forget($param);
            return;
          }
          // No break here: pages, issue and year (the previous case) should be treated in this fashion.
        case 'pages': case 'page': case 'pp': # And case 'year': case 'issue':, following from previous
          $value = $this->get($param);
          if (strpos($value, "[//")  === 0) { // We can fix them, if they are the very first item
            $value = "[https://" . substr($value, 3);
            $this->set($param, $value);
          }
          if (!preg_match("~^[A-Za-z ]+\-~", $value) && mb_ereg(REGEXP_TO_EN_DASH, $value)
              && can_safely_modify_dashes($value)) {
            $this->mod_dashes = TRUE;
            report_modification("Upgrading to en-dash in " . echoable($param) .
                  " parameter");
            $value =  mb_ereg_replace(REGEXP_TO_EN_DASH, REGEXP_EN_DASH, $value);
            $this->set($param, $value);
          }
          if (   (mb_substr_count($value, "–") === 1) // Exactly one EN_DASH.  
              && can_safely_modify_dashes($value)) { 
            $the_dash = mb_strpos($value, "–"); // ALL must be mb_ functions because of long dash
            $part1 = trim(mb_substr($value, 0, $the_dash));
            $part2 = trim(mb_substr($value, $the_dash + 1));
            if ($part1 === $part2) {
              $this->set($param, $part1);
            } elseif (is_numeric($part1) && is_numeric($part2)) {
              $this->set($param, $part1 . "–" . $part2); // Remove any extra spaces
            }
          }
          if (strpos($this->get($param), '&') === FALSE) {
            $this->set($param, preg_replace("~^[.,;]*\s*(.*?)\s*[,.;]*$~", "$1", $this->get($param)));
          } else {
            $this->set($param, preg_replace("~^[.,;]*\s*(.*?)\s*[,.]*$~", "$1", $this->get($param))); // Not trailing ;
          }
          return;
          
        case 'postscript':  // postscript=. is the default in CS1 templates.  It literally does nothing.
          if ($this->wikiname() !== 'citation') {
            if ($this->get($param) === '.') $this->forget($param); // Default action does not need specified
            if ($this->blank($param)) $this->forget($param);  // Misleading -- blank means period!!!!
          }
          return;
          
        case 'website':
          if (($this->wikiname() === 'cite book') && (strcasecmp((string)$this->get($param), 'google.com') === 0 ||
                                                      strcasecmp((string)$this->get($param), 'Google Books') === 0 ||
                                                         stripos((string)$this->get($param), 'Books.google.') === 0)) {
            $this->forget($param);
          }
          if (stripos($this->get($param), 'archive.org') !== FALSE &&
              stripos($this->get('url') . $this->get('chapter-url') . $this->get('chapterurl'), 'archive.org') === FALSE) {
            $this->forget($param);
          }
          return;
         
        case 'publicationplace': case 'publication-place':
          if ($this->blank(['location', 'place'])) {
            $this->rename($param, 'location'); // This should only be used when 'location'/'place' is being used to describe where is was physically written, i.e. location=Vacationing in France|publication-place=New York
          }
          return;
          
        case 'publication-date': case 'publicationdate':
          if ($this->blank(['year', 'date'])) {
            $this->rename($param, 'date'); // When date & year are blank, this is displayed as date.  So convert
          }
          return;
          
        case 'orig-year': case 'origyear':
          if ($this->blank(['year', 'date'])) { // Will not show unless one of these is set, so convert
            if (preg_match('~^\d\d\d\d$~', $this->get($param))) { // Only if a year, might contain text like "originally was...."
              $this->rename($param, 'year');
            }
          }
          return; 
      }
    }
  }
  
  public function tidy() {
    // Should only be run once (perhaps when template is first loaded)
    // Future tidying should occur when parameters are added using tidy_parameter.
    if (!$this->param) return TRUE;
    foreach ($this->param as $param) $this->tidy_parameter($param->param);
  }
  
  public function final_tidy() {
    if ($this->should_be_processed()) {
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
          (str_equivalent($this->get('series'), $this->get('journal')))) {  // Leave only one
        if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
            $this->forget('journal');
        } elseif ($this->wikiname() === 'cite journal'|| $this->wikiname() === 'citation') {
          $this->forget('series');
        }
      }
      // "Work is a troublesome parameter
      if ($this->get('work') !== NULL && $this->blank('work')) { // Have work=, but it is blank
         if ($this->has('journal') ||
             $this->has('newspaper') ||
             $this->has('magazine') ||
             $this->has('periodical') ||
             $this->has('website')) {
              $this->forget('work'); // Delete if we have alias
         } elseif ($this->wikiname() === 'cite web' && !$this->blank(['url', 'article-url', 'chapter-url', 'chapterurl', 'conference-url', 'conferenceurl', 'contribution-url', 'contributionurl', 'entry-url', 'event-url', 'eventurl', 'lay-url', 'layurl', 'map-url', 'mapurl', 'section-url', 'sectionurl', 'transcript-url', 'transcripturl'])) {
            $this->rename('work', 'website');
         } elseif ($this->wikiname() === 'cite journal') {
            $this->rename('work', 'journal');
         } elseif ($this->wikiname() === 'cite magazine') {
            $this->rename('work', 'magazine');
         }
      }
      if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
        if ($this->has('title') || $this->has('chapter')) {
          $this->forget(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
        }
      }
      $this->tidy('url'); // depending upon end state, convert to chapter-url
    }
    if ($this->wikiname() === 'cite arxiv' && $this->has('bibcode')) {
      $this->forget('bibcode'); // Not supported and 99% of the time just a arxiv bibcode anyway
    }
    if ($this->wikiname() === 'citation') { // Special CS2 code goes here
      if ($this->has('title') && $this->has('chapter') && !$this->blank(WORK_ALIASES)) { // Invalid combination
          report_info('CS2 template has incompatible parameters.  Changing to CS1 cite book. Please verify.');
          $this->change_name_to('cite book');
      }
    }
    if (!$this->blank(DOI_BROKEN_ALIASES) && $this->has('jstor') && strpos($this->get('doi'), '10.2307') === 0) {
      $this->forget('doi'); // Forget DOI that is really jstor, if it is broken
      foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
    }
    if ($this->has('journal')) {  // Do this at the very end of work in case we change type/etc during expansion
          if ($this->blank(['chapter', 'isbn'])) {
            // Avoid renaming between cite journal and cite book
            $this->change_name_to('cite journal');
          } else {
            report_warning('Citation should probably not have journal = ' . $this->get('journal')
            . ' as well as chapter / ISBN ' . $this->get('chapter') . ' ' .  $this->get('isbn'));
          }
    }
    foreach (ALL_ALIASES as $alias_list) {
      if (!$this->blank($alias_list)) { // At least one is set
        foreach ($alias_list as $alias) {
          if ($this->blank($alias)) $this->forget($alias); // Delete all the other ones
        }
      }
    }
  }
  
  public function verify_doi() {
    $doi = $this->get_without_comments_and_placeholders('doi');
    if (!$doi) return FALSE;
    if ($this->doi_valid) return TRUE;
    
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
    if (preg_match("~^(.+)(10\.\d{4,6}/.+)~", trim($doi), $match)) {
      $trial[] = $match[1];
      $trial[] = $match[2];
    }
    $replacements = array (      "&lt;" => "<",      "&gt;" => ">",    );
    if (preg_match("~&[lg]t;~", $doi)) {
      $trial[] = str_replace(array_keys($replacements), $replacements, $doi);
    }
    if (isset($trial) && !in_array($doi, $trial) && preg_match("~^10\.\d{4,6}/.~", trim($doi))) {
      array_unshift($trial, $doi); // doi:10.1126/science.10.1126/SCIENCE.291.5501.24 is valid, not the subparts
    }
    if (isset($trial)) foreach ($trial as $try) {
      // Check that it begins with 10.
      if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) $try = "10." . $match[1];
      if (doi_active($try)) {
        $this->set('doi', $try);
        $this->doi_valid = TRUE;
        $doi = $try;
        break;
      }
    } else {
      report_info("Checking that DOI " . echoable($doi) . " is operational...");
      if (doi_active($this->get_without_comments_and_placeholders('doi')) === FALSE) {
        report_inline("It's not; checking for user input error...");
        // Replace old "doi_inactivedate" and/or other broken/inactive-date parameters,
        // if present, with new "doi-broken-date"
        $url_test = "https://dx.doi.org/" . urlencode($doi);
        $headers_test = @get_headers($url_test, 1);
        if ($headers_test === FALSE) {
          report_warning("DOI status unknown.  dx.doi.org failed to respond at all to: " . echoable($doi));
          return FALSE;
        }
        foreach (DOI_BROKEN_ALIASES as $alias) {
          if (mb_stripos($this->get($alias), 'CITATION_BOT_PLACEHOLDER_COMMENT') === FALSE) { // Might have <!-- Not broken --> to block bot
               $this->forget($alias);
          }
        }
        if(empty($headers_test['Location'])) {
           if ($this->blank(DOI_BROKEN_ALIASES)) $this->set('doi-broken-date', date("Y-m-d"));  // dx.doi.org might work, even if CrossRef fails
           report_inline("Broken doi: " . echoable($doi));
           return FALSE;
        } else {
           foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias); // Blow them away even if commented
           return TRUE;
        }
      } else {
        foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
        $this->doi_valid = TRUE;
        report_inline('DOI ok.');
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
                $this->add_if_new('author' . ($i + 1), format_author($author_name));
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
      report_modification("Setting display-authors to $newval");
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
  
  protected function year() {
    if ($this->has('year')) {
      return $this->get('year');
    }
    if ($this->has('date')) {
       $date = $this->get('date');
       if (preg_match("~^(\d{4})$~", $date)) {
         return $date; // Just a year
       } elseif (preg_match("~^(\d{4})[^0-9]~", $date, $matches)) {
         return $matches[1]; // Start with year
       } elseif (preg_match("~[^0-9](\d{4})$~", $date, $matches)) {
         return $matches[1]; // Ends with year
       }
    }
    return '';
  }

  public function name() {return trim($this->name);}

  protected function page_range() {
    preg_match("~(\w?\w?\d+\w?\w?)(?:\D+(\w?\w?\d+\w?\w?))?~", $this->page(), $pagenos);
    return $pagenos;
  }

  // Amend parameters
  public function rename($old_param, $new_param, $new_value = FALSE) {
    if ($old_param == $new_param) return FALSE;
    if ($this->blank($new_param)) $this->forget($new_param); // Forget empty old copies, if they exist
    if (!isset($this->param)) return FALSE;
    foreach ($this->param as $p) {
      if ($p->param == $old_param) {
        $p->param = $new_param;
        if ($new_value) {
          $p->val = $new_value;
        }
        report_modification("Renamed \"$old_param\" -> \"$new_param\"");
        $this->tidy_parameter($new_param);
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
    $ret = str_replace("\xc2\xa0", ' ', $ret); // Replace non-breaking with breaking spaces, which are trimmable
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
    report_add("Adding $par: $val");
    $could_set = $this->set($par, $val);
    $this->tidy_parameter($par);
    return $could_set;
  }
  
  public function set($par, $val) {
    if (mb_stripos($this->get((string) $par), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
      return FALSE;
    }
    if (($pos = $this->get_param_key((string) $par)) !== NULL) {
      return $this->param[$pos]->val = (string) $val;
    }
    if (!isset($this->example_param)) {
      if (isset($this->param[0])) {
        // Use second param as a template if present, in case first pair 
        // is last1 = Smith | first1 = J.\n
        $example = $this->param[isset($this->param[1]) ? 1 : 0]->parsed_text();
        $example = preg_replace('~[^\s=][^=]*[^\s=]~u', 'X', $example); // Collapse strings
        $example = preg_replace('~ +~u', ' ', $example); // Collapse spaces
        // Check if messed up
        if (substr_count($example, '=') !== 1) $example = '| param = val';
        if (substr_count($example, "\n") > 1 ) $example = '| param = val';
      } else {
        $example = '| param = val';
      }
      $this->example_param = $example;
    }
    $p = new Parameter();
    $p->parse_text($this->example_param);
    $p->param = (string) $par;
    $p->val = (string) $val;
    
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
    if (mb_stripos($this->get($par), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
      return FALSE;
    }
    $pos = $this->get_param_key($par);
    if ($pos) {
      return $this->param[$pos]->val = $this->param[$pos]->val . $val;
    } else {
      return $this->set($par, $val);
    }
  }

    
  public function quietly_forget($par) {
    $this->forgetter($par, FALSE);
  }
  public function forget($par) {
    $this->forgetter($par, TRUE);
  }
  private function forgetter($par, $echo_forgetting) { // Do not call this function directly
   if (!$this->blank($par)) { // do not remove all this other stuff if blank
    if ($par == 'url') {
      $this->forgetter('accessdate', $echo_forgetting);
      $this->forgetter('access-date', $echo_forgetting);
      if ($this->blank(['chapter-url', 'chapterurl', 'contribution-url', 'contributionurl'])) {
        $this->forgetter('archive-url', $echo_forgetting);
        $this->forgetter('archiveurl', $echo_forgetting);
      }
      $this->forgetter('archive-date', $echo_forgetting);
      $this->forgetter('archivedate', $echo_forgetting);
      $this->forgetter('dead-url', $echo_forgetting);
      $this->forgetter('format', $echo_forgetting);
      $this->forgetter('registration', $echo_forgetting);
      $this->forgetter('subscription', $echo_forgetting);
      $this->forgetter('url-access', $echo_forgetting);
      $this->forgetter('via', $echo_forgetting);
      $this->forgetter('website', $echo_forgetting);
      $this->forgetter('deadurl', $echo_forgetting);
      if ($this->has('work') && stripos($this->get('work'), 'www.') === 0) {
         $this->forgetter('work', $echo_forgetting);
      }
    }
    if ($par == 'chapter' && $this->blank('url')) {
      if($this->has('chapter-url')) {
        $this->rename('chapter-url', 'url');
        $this->rename('chapter-format', 'format');
      } elseif ($this->has('chapterurl')) {
        $this->rename('chapterurl', 'url');
        $this->rename('chapter-format', 'format');
      }
    }
    if ($par == 'chapter-url' || $par == 'chapterurl') {
       $this->forgetter('chapter-format', $echo_forgetting);
    }
   }  // even if blank try to remove
    $pos = $this->get_param_key($par);
    if ($pos !== NULL) {
      if ($echo_forgetting && $this->has($par) && stripos($par, 'CITATION_BOT_PLACEHOLDER') === FALSE) {
        // Do not mention forgetting empty parameters or internal temporary parameters
        report_forget("Dropping parameter \"" . echoable($par) . '"');
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
  
  public function inline_doi_information() {
    if ($this->name !== "doi-inline") return FALSE;
    if (count($this->param) !==2) return FALSE;
    $vals   = array();
    $vals[] = $this->param[0]->parsed_text();
    $vals[] = $this->param[1]->parsed_text();
    return $vals;
  }
  
  protected function get_inline_doi_from_title() {
     if (preg_match("~(?:\s)*(?:# # # CITATION_BOT_PLACEHOLDER_TEMPLATE )(\d+)(?: # # #)(?:\s)*~", $this->get('title'), $match)) {
       if ($inline_doi = $this->all_templates[$match[1]]->inline_doi_information()) {
         if ($this->add_if_new('doi', trim($inline_doi[0]))) { // Add doi
           $this->set('title', trim($inline_doi[1]));
           quietly('report_modification', "Converting inline DOI to DOI parameter");
         } elseif ($this->get('doi') === trim($inline_doi[0])) { // Already added by someone else
           $this->set('title', trim($inline_doi[1]));
           quietly('report_modification', "Remove duplicate inline DOI ");
         }
       }
     }
  }
                         
  protected function simplify_google_search($url) {
      $hash = '';
      if (strpos($url, "#")) {
        $url_parts = explode("#", $url);
        $url = $url_parts[0];
        $hash = "#" . $url_parts[1];
      }

      $url_parts = explode("&", str_replace("?", "&", $url));
      array_shift($url_parts);
      $url = "https://www.google.com/search?";

      foreach ($url_parts as $part) {
        $part_start = explode("=", $part);
        switch ($part_start[0]) {
          case "aq": case "aqi": case "bih": case "biw": case "client": 
          case "as": case "useragent": case "as_brr": case "source":
          case "ei": case "ots": case "sig": case "source": case "lr":
          case "as_brr": case "sa": case "oi": case "ct": case "id":
          case "oq": case "rls": case "sourceid": case "tbm": case "ved":
          case "aqs":
             break;
          case "ie":
             if (strcasecmp($part_start[1], 'utf-8') === 0) break;  // UTF-8 is the default
          case "hl": case "safe": case "q":
             $url .=  $part . "&" ;
             break;
          default:
             report_minor_error("Unexpected Google URL component:  " . $part);
             $url .=  $part . "&" ;
             break;
        }
      }

      if (substr($url, -1) === "&") $url = substr($url, 0, -1);  //remove trailing &
      $url= $url . $hash;
      return $url;
  }
}
