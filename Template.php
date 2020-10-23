<?php
declare(strict_types=1);
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

require_once('Parameter.php');
require_once('expandFns.php');
require_once('user_messages.php');
require_once('apiFunctions.php');
require_once("constants.php");
require_once("NameTools.php");

final class Template {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_TEMPLATE %s # # #';
  public const REGEXP = ['~\{\{[^\{\}\|]+\}\}~su', '~\{\{[^\{\}]+\}\}~su', '~\{\{(?>[^\{]|\{[^\{])+?\}\}~su'];  // Please see https://stackoverflow.com/questions/1722453/need-to-prevent-php-regex-segfault for discussion of atomic regex
  public const TREAT_IDENTICAL_SEPARATELY = FALSE;
  private const MAGIC_STRING = 'CITATION_BOT_PLACEHOLDER_URL_POINTER_';
  /** @psalm-suppress PropertyNotSetInConstructor */
  public $all_templates;  // Points to list of all the Template() on the Page() including this one.  It can only be set by the page class after all templates are made
  public $date_style = DATES_WHATEVER;  // Will get from the page
  /** @psalm-suppress PropertyNotSetInConstructor */
  protected $rawtext;  // Must start out as unset
  public $last_searched_doi = '';
  protected $example_param = '';
  protected $name = '';
  protected $param = array();
  protected $initial_param = array();
  protected $initial_author_params = array();
  protected $initial_name = '';
  protected $doi_valid = FALSE;
  protected $had_initial_editor = FALSE;
  protected $mod_dashes = FALSE;
  protected $mod_names = FALSE;
  protected $no_initial_doi = FALSE;
  protected $used_by_api = array(
               'adsabs'   => array(),
               'arxiv'    => array(),
               'crossref' => array(),
               'dx'       => array(),
               'entrez'   => array(),
               'jstor'    => array(),
               'zotero'   => array(),
            );

  function __construct() {
     ;  // All the real construction is done in parse_text() and above in variable initialization
  }
  
  public function parse_text(string $text) : void {
    $spacing = ['', '']; // prevent memory leak in some PHP versions
    if (isset($this->rawtext)) {
        report_error("Template already initialized; call new Template() before calling Template::parse_text()"); // @codeCoverageIgnore
    }
    $this->rawtext = $text;
    $pipe_pos = strpos($text, '|');
    if ($pipe_pos) {
      $this->name = substr($text, 2, $pipe_pos - 2); # Remove {{ and }}
      $this->split_params(substr($text, $pipe_pos + 1, -2));
    } else {
      $this->name = substr($text, 2, -2);
    }
    $this->initial_name = $this->name;
    // Clean up outdated redirects
    preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
    $trim_name = trim($this->name);
    if (strpos($trim_name, "_") !== FALSE) {
      $tmp_name = str_replace("_", " ", $trim_name);
      if (in_array(strtolower($tmp_name), array_merge(TEMPLATES_WE_PROCESS, TEMPLATES_WE_SLIGHTLY_PROCESS, TEMPLATES_WE_BARELY_PROCESS, TEMPLATES_WE_RENAME))) {
         $this->name = $spacing[1] . str_replace("_", " ", $trim_name) . $spacing[2];
         $trim_name = str_replace("_", " ", $trim_name);
      }
    }
    if ($trim_name === 'cite') $this->name = $spacing[1] . 'citation' . $spacing[2];
    if ($trim_name === 'Cite') $this->name = $spacing[1] . 'Citation' . $spacing[2];
    if ($trim_name === 'citebook') $this->name = $spacing[1] . 'cite book' . $spacing[2];
    if ($trim_name === 'Citebook') $this->name = $spacing[1] . 'Cite book' . $spacing[2];
    if ($trim_name === 'cit book') $this->name = $spacing[1] . 'cite book' . $spacing[2];
    if ($trim_name === 'Cit book') $this->name = $spacing[1] . 'Cite book' . $spacing[2];
    if ($trim_name === 'cite books') $this->name = $spacing[1] . 'cite book' . $spacing[2];
    if ($trim_name === 'Cite books') $this->name = $spacing[1] . 'Cite book' . $spacing[2];
    if ($trim_name === 'book reference') $this->name = $spacing[1] . 'cite book' . $spacing[2];
    if ($trim_name === 'Book reference') $this->name = $spacing[1] . 'Cite book' . $spacing[2];
    if ($trim_name === 'citejournal') $this->name = $spacing[1] . 'cite journal' . $spacing[2];
    if ($trim_name === 'Citejournal') $this->name = $spacing[1] . 'Cite journal' . $spacing[2];
    if ($trim_name === 'citeweb') $this->name = $spacing[1] . 'cite web' . $spacing[2];
    if ($trim_name === 'Citeweb') $this->name = $spacing[1] . 'Cite web' . $spacing[2];
    if ($trim_name === 'cite-web') $this->name = $spacing[1] . 'cite web' . $spacing[2];
    if ($trim_name === 'Cite-web') $this->name = $spacing[1] . 'Cite web' . $spacing[2];
    if ($trim_name === 'cit web') $this->name = $spacing[1] . 'cite web' . $spacing[2];
    if ($trim_name === 'Cit web') $this->name = $spacing[1] . 'Cite web' . $spacing[2];
    if ($trim_name === 'cit journal') $this->name = $spacing[1] . 'cite journal' . $spacing[2];
    if ($trim_name === 'Cit journal') $this->name = $spacing[1] . 'Cite journal' . $spacing[2];
    if ($trim_name === 'cit news') $this->name = $spacing[1] . 'cite news' . $spacing[2];
    if ($trim_name === 'Cit news') $this->name = $spacing[1] . 'Cite news' . $spacing[2];
    if ($trim_name === 'cite url') $this->name = $spacing[1] . 'cite web' . $spacing[2];
    if ($trim_name === 'Cite url') $this->name = $spacing[1] . 'Cite web' . $spacing[2];
    if ($trim_name === 'web cite') $this->name = $spacing[1] . 'cite web' . $spacing[2];
    if ($trim_name === 'Web cite') $this->name = $spacing[1] . 'Cite web' . $spacing[2];
    if ($trim_name === 'book cite') $this->name = $spacing[1] . 'cite book' . $spacing[2];
    if ($trim_name === 'Book cite') $this->name = $spacing[1] . 'Cite book' . $spacing[2];
    if ($trim_name === 'cite-book') $this->name = $spacing[1] . 'cite book' . $spacing[2];
    if ($trim_name === 'Cite-book') $this->name = $spacing[1] . 'Cite book' . $spacing[2];
    if ($trim_name === 'citenews') $this->name = $spacing[1] . 'cite news' . $spacing[2];
    if ($trim_name === 'Citenews') $this->name = $spacing[1] . 'Cite news' . $spacing[2];
    if ($trim_name === 'citepaper') $this->name = $spacing[1] . 'cite paper' . $spacing[2];
    if ($trim_name === 'Citepaper') $this->name = $spacing[1] . 'Cite paper' . $spacing[2];
    if ($trim_name === 'citation journal') $this->name = $spacing[1] . 'cite journal' . $spacing[2];
    if ($trim_name === 'Citation journal') $this->name = $spacing[1] . 'Cite journal' . $spacing[2];
    if ($trim_name === 'cite new') $this->name = $spacing[1] . 'cite news' . $spacing[2];
    if ($trim_name === 'Cite new') $this->name = $spacing[1] . 'Cite news' . $spacing[2];

    // Cite article is actually cite news, but often used for journal by mistake - fix
    if ($trim_name === 'cite article') {
      if ($this->blank(['journal', 'pmid', 'pmc', 'doi', 's2cid', 'citeseerx'])) {
        $this->name = $spacing[1] . 'cite news' . $spacing[2];
      } else {
        $this->name = $spacing[1] . 'cite journal' . $spacing[2];
      }
    }
    if ($trim_name === 'Cite article') {
      if ($this->blank(['journal', 'pmid', 'pmc', 'doi', 's2cid', 'citeseerx'])) {
        $this->name = $spacing[1] . 'Cite news' . $spacing[2];
      } else {
        $this->name = $spacing[1] . 'Cite journal' . $spacing[2];
      }
    }
    // Cite article is actually cite journal, but often used for other things by mistake - fix what we can
    if ($trim_name === 'cite document') {
      if (strpos($this->get('doi'), '/978-') !== FALSE) {
        $this->name = $spacing[1] . 'cite book' . $spacing[2];
      } elseif (!$this->blank(['journal', 'pmid', 'pmc', 'doi', 's2cid', 'citeseerx'])) {
        $this->name = $spacing[1] . 'cite journal' . $spacing[2];
      } elseif (!$this->blank(['newspaper'])) {
        $this->name = $spacing[1] . 'cite news' . $spacing[2];
      } elseif ($this->has('chapter') || $this->has('isbn')) {
        $this->name = $spacing[1] . 'cite book' . $spacing[2];
      }
    }
    if ($trim_name === 'Cite document') {
      if (strpos($this->get('doi'), '/978-') !== FALSE) {
        $this->name = $spacing[1] . 'Cite book' . $spacing[2];
      } elseif (!$this->blank(['journal', 'pmid', 'pmc', 'doi', 's2cid', 'citeseerx'])) {
        $this->name = $spacing[1] . 'Cite journal' . $spacing[2];
      } elseif (!$this->blank(['newspaper'])) {
        $this->name = $spacing[1] . 'Cite news' . $spacing[2];
      } elseif ($this->has('chapter') || $this->has('isbn')) {
        $this->name = $spacing[1] . 'Cite book' . $spacing[2];
      }
    } 
    
    if (substr($this->wikiname(),0,5) === 'cite ' || $this->wikiname() === 'citation') {
      if (preg_match('~< */? *ref *>~i', $this->rawtext)) {
         report_warning('reference within citation template: most likely unclosed template.  ' . "\n" . $this->rawtext . "\n");
         throw new Exception('page_error');
      }
    }

    // extract initial parameters/values from Parameters in $this->param
    foreach ($this->param as $p) {
      $this->initial_param[$p->param] = $p->val;

      // Save author params for special handling
      if (in_array($p->param, FLATTENED_AUTHOR_PARAMETERS) && $p->val) {
        $this->initial_author_params[$p->param] = $p->val;
      }
      
      // Save editor information for special handling
      if (in_array($p->param, FIRST_EDITOR_ALIASES) && $p->val) {
        $this->had_initial_editor = TRUE;
      }
      if ($p->param === 'veditors' && $p->val) $this->had_initial_editor = TRUE;
    }
    $this->no_initial_doi = $this->blank('doi');

    $example = 'param = val';
    if (isset($this->param[0])) {
        // Use second param as a template if present, in case first pair 
        // is last1 = Smith | first1 = J.\n
        $example = $this->param[isset($this->param[1]) ? 1 : 0]->parsed_text();
        $example = preg_replace('~[^\s=][^=]*[^\s=]~u', 'X', $example); // Collapse strings
        $example = preg_replace('~ +~u', ' ', $example); // Collapse spaces
        // Check if messed up
        if (substr_count($example, '=') !== 1) $example = 'param = val';
        if (substr_count($example, "\n") > 1 ) $example = 'param = val';
    }
    $this->example_param = (string) $example;
  }

  // Re-assemble parsed template into string
  public function parsed_text() : string {
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
  protected function split_params(string $text) : void {
    // Replace | characters that are inside template parameter/value pairs
    $PIPE_REGEX = "~(\[\[[^\[\]]*)(?:\|)([^\[\]]*\]\])~u";
    while (preg_match($PIPE_REGEX, $text)) {
      $text = preg_replace_callback($PIPE_REGEX,
          function(array $matches) : string {
             return($matches[1] . PIPE_PLACEHOLDER . $matches[2]);
          },
          $text);
    }
    $params = explode('|', $text);
    foreach ($params as $i => $text_found) {
      $this->param[$i] = new Parameter();
      $this->param[$i]->parse_text($text_found);
    }
  }

  public function prepare() : void {
    if ($this->should_be_processed()) {
      $this->get_inline_doi_from_title();
      $this->parameter_names_to_lowercase();
      $this->use_unnamed_params();
      $this->get_identifiers_from_url();
      $this->id_to_param();
      $this->correct_param_spelling();
      $this->get_doi_from_text();
      $this->fix_rogue_etal();
      
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
              $this->quietly_forget('CITATION_BOT_PLACEHOLDER_year');   // @codeCoverageIgnore
              $this->quietly_forget('CITATION_BOT_PLACEHOLDER_date');   // @codeCoverageIgnore
          }
          break;
        case "cite journal":       
          if ($this->use_sici()) {
            report_action("Found and used SICI");
          }
      }
      // Clean up bad data
      if (!$this->blank(['pmc', 'pmid', 'doi', 'jstor'])) { // Have some good data
          $the_title   = $this->get('title');
          $the_journal = $this->get('journal');
          $the_chapter = $this->get('chapter');
          $bad_data = FALSE;
          if (strlen($the_title) > 15 && strpos($the_title, ' ') !== FALSE &&
              mb_strtoupper($the_title) === $the_title && strpos($the_title, 'CITATION') === FALSE) {
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title'); // ALL UPPER CASE
              $the_title = '';
              $bad_data = TRUE;
          }
          if (strlen($the_journal) > 15 && strpos($the_journal, ' ') !== FALSE &&
              mb_strtoupper($the_journal) === $the_journal && strpos($the_journal, 'CITATION') === FALSE) {
              $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal'); // ALL UPPER CASE
              $the_journal = '';
              $bad_data = TRUE;
          }
          if (strlen($the_chapter) > 15 && strpos($the_chapter, ' ') !== FALSE &&
              mb_strtoupper($the_chapter) === $the_chapter && strpos($the_chapter, 'CITATION') === FALSE) {
              $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter'); // ALL UPPER CASE
              $the_chapter = '';
              $bad_data = TRUE;
          }
          if (str_i_same($the_journal, 'Biochimica et Biophysica Acta')) { // Only part of the journal name
              $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
              $the_journal = '';
              $bad_data = TRUE;
          }
          if ($the_title != '' && stripos($the_title, 'CITATION') === FALSE) {
            if (str_i_same($the_title, $the_journal) &&
                str_i_same($the_title, $the_chapter)) { // Journal === Title === Chapter INSANE!  Never actually seen
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
              $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
              $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
              $bad_data = TRUE;
            } elseif (str_i_same($the_title, $the_journal)) { // Journal === Title
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
              $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
              $bad_data = TRUE;
            } elseif (str_i_same($the_title, $the_chapter)) { // Chapter === Title
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
              $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
              $bad_data = TRUE;
            } elseif (substr($the_title, -9, 9) == ' on JSTOR') {
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title'); // Ends in 'on jstor'
              $bad_data = TRUE;
            } elseif (substr($the_title, -20, 20) == 'IEEE Xplore Document') {
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
              $bad_data = TRUE;
            } elseif (substr($the_title, 0, 12) == 'IEEE Xplore ') {
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
              $bad_data = TRUE; 
            } elseif ($the_title == 'Shibboleth Authentication Request') {
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
              $bad_data = TRUE; 
            } elseif (preg_match('~.+(?: Volume| Vol\.| V. | Number| No\.| Num\.| Issue ).*\d+.*page.*\d+~i', $the_title)) {
              $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
              $bad_data = TRUE;
            }
          }
          if ($bad_data) {
            $this_array = [$this];
            if ($this->has('doi') && doi_active($this->get('doi'))) {
              expand_by_doi($this);
            } elseif ($this->has('pmid')) {
              query_pmid_api(array($this->get('pmid')), $this_array);
            } elseif ($this->has('pmc')) {
              query_pmc_api(array($this->get('pmc')), $this_array);
            } elseif ($this->has('jstor')) {
              expand_by_jstor($this);
            }
            if ($this->has('CITATION_BOT_PLACEHOLDER_journal')) {
              if ($this->has('journal')) {
                $this->forget('CITATION_BOT_PLACEHOLDER_journal');
              } else {
                $this->rename('CITATION_BOT_PLACEHOLDER_journal', 'journal');
              }
            }
            if ($this->has('CITATION_BOT_PLACEHOLDER_title')) {
              if ($this->has('title')) {
                $this->forget('CITATION_BOT_PLACEHOLDER_title');
              } else {
                $this->rename('CITATION_BOT_PLACEHOLDER_title', 'title');
              }
            }
            if ($this->has('CITATION_BOT_PLACEHOLDER_chapter')) {
              if ($this->has('chapter')) {
                $this->forget('CITATION_BOT_PLACEHOLDER_chapter');
              } else {
                $this->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter');
              }
            }
          }
        }
        $this->tidy();
    } elseif ($this->wikiname() == 'cite magazine' &&  $this->blank('magazine') && $this->has_but_maybe_blank('work')) { 
      // This is all we do with cite magazine
      $this->rename('work', 'magazine');
    }
  }
  
  public function fix_rogue_etal() : void {
    if ($this->blank(DISPLAY_AUTHORS)) {
      $i = 2;
      while (!$this->blank(['author' . (string) $i, 'last' . (string) $i])) {
        $i = $i + 1;
      }
      $i = $i - 1;
      if (preg_match('~^et\.? ?al\.?$~i', $this->get('author' . (string) $i))) $this->rename('author' . (string) $i, 'display-authors', 'etal');
      if (preg_match('~^et\.? ?al\.?$~i', $this->get('last'   . (string) $i))) $this->rename('last'   . (string) $i, 'display-authors', 'etal');
    }
  }
  
  public function record_api_usage(string $api, string $param) : void {
    $param = array($param);
    foreach ($param as $p) {
      if (!in_array($p, $this->used_by_api[$api])) $this->used_by_api[$api][] = $p;
    }
  }
  
  public function api_has_used(string $api, array $param) : bool {
    if (!isset($this->used_by_api[$api])) report_error("Invalid API: $api");
    /** @psalm-suppress all */
    return (bool) count(array_intersect($param, $this->used_by_api[$api]));
  }
  
  public function api_has_not_used(string $api, array $param) : bool {
    return !$this->api_has_used($api, $param);
  }
  
  public function incomplete() : bool {   // FYI: some references will never be considered complete
    if ($this->wikiname() =='cite book' || ($this->wikiname() =='citation' && $this->has('isbn'))) { // Assume book
      if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
      return (!(
              $this->has('isbn')
          &&  $this->has('title')
          && ($this->has('date') || $this->has('year'))
          && ($this->has('author2') || $this->has('last2') || $this->has('surname2'))
      ));
    }
    // And now everything else
    if ($this->blank(['pages', 'page', 'at']) ||
        preg_match('~no.+no|n/a|in press|none~', $this->get('pages') . $this->get('page') . $this->get('at')) ||
        (preg_match('~^1[^0-9]~', $this->get('pages') . $this->get('page') . '-') && ($this->blank('year') || 2 > ((int)date("Y") - (int)$this->get('year')))) // It claims to be on page one
       ) {
      return TRUE;
    }
    if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
    return (!(
             ($this->has('journal') || $this->has('periodical'))
          &&  $this->has('volume')
          && ($this->has('issue') || $this->has('number'))
          &&  $this->has('title')
          && ($this->has('date') || $this->has('year'))
          && ($this->has('author2') || $this->has('last2') || $this->has('surname2')
          && ($this->get('journal') !== 'none' && $this->get('title') !== 'none'))
    ));
  }

  public function profoundly_incomplete(string $url = '') : bool {
    // Zotero translation server often returns bad data, which is worth having if we have no data,
    // but we don't want to fill a single missing field with garbage if a reference is otherwise well formed.
    $has_date = $this->has('date') || $this->has('year') ;
    foreach (NO_DATE_WEBSITES as $bad_website) {
      if (stripos($url, $bad_website) !== FALSE) {
        $has_date = TRUE;
        break;
      }
    }
  
    if ($this->wikiname() =='cite book' || ($this->wikiname() =='citation' && $this->has('isbn'))) { // Assume book
      if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
      return (!(
              $this->has('isbn')
          &&  $this->has('title')
          &&  $has_date
      ));
    }

    if (str_ireplace(NON_JOURNAL_WEBSITES, '', $url) !== $url) { // A website that will never give a volume
          return (!(
             ($this->has('journal') || $this->has('periodical') || $this->has('work') ||
              $this->has('website') || $this->has('publisher') || $this->has('newspaper') ||
              $this->has('magazine')|| $this->has('encyclopedia') || $this->has('encyclopaedia') ||
              $this->has('contribution'))
          &&  $this->has('title')
          &&  $has_date
    ));
    }
    return (!(
             ($this->has('journal') || $this->has('periodical'))
          &&  $this->has('volume')
          &&  $this->has('title')
          &&  $has_date
    ));
  }
  
  /**
  * @param string[]|string $param
  */
  public function blank($param) : bool { // Accepts arrays of strings and string
    if (!$param) report_error('NULL passed to blank()');
    if (empty($this->param)) return TRUE;
    if (!is_array($param)) $param = array($param);
    foreach ($this->param as $p) {
      if (in_array($p->param, $param) && trim($p->val) != '' && !str_i_same('Epub ahead of print', $p->val)) return FALSE;
    }
    return TRUE;
  }
  /**
  * @param string[]|string $param
  */
  public function blank_other_than_comments($param) : bool { // Accepts arrays of strings and string
    if (!$param) report_error('NULL passed to blank_other_than_comments()');
    if (empty($this->param)) return TRUE;
    if (!is_array($param)) $param = array($param);
    foreach ($this->param as $p) {
      if (in_array($p->param, $param) && trim(preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT.*?# # #~sui', '', $p->val)) != '') return FALSE;
    }
    return TRUE;
  }

  /* function add_if_new
   * Adds a parameter to a template if the parameter and its equivalents are blank
   * $api (string) specifies the API route by which a parameter was found; this will log the 
   *      parameter so it is not used to trigger a new search via the same API.
   *
   */
  public function add_if_new(string $param_name, string $value, string $api = '') : bool {
    $match = ['', '']; // prevent memory leak in some PHP versions
    $auNo = ['', '']; // prevent memory leak in some PHP versions
    $oldpagenos = ['', '', '']; // prevent memory leak in some PHP versions
    $newpagenos = ['', '', '']; // prevent memory leak in some PHP versions
    $value = trim($value);
    $param_name = trim($param_name); // Pure paranoia
    if ($value == '') {
      return FALSE;
    }
    if ($param_name == '') {
      report_error('invalid param_name passed to add_if_new()'); // @codeCoverageIgnore
    }
    
    if (str_i_same($value, 'null')) { // Hopeully name is not actually null
      return FALSE;
    }
    
    if (mb_stripos($this->get($param_name), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
      return FALSE;  // We let comments block the bot
    }
    
    if (array_key_exists($param_name, COMMON_MISTAKES)) {
      report_error("Attempted to add invalid parameter: " . echoable($param_name)); // @codeCoverageIgnore
    }
    
    if ($api) $this->record_api_usage($api, $param_name);
    
    // If we already have name parameters for author, don't add more
    if ($this->initial_author_params && in_array($param_name, FLATTENED_AUTHOR_PARAMETERS)) {
      return FALSE;
    }

    if ($param_name !== 's2cid') {
     if (substr($param_name, -4) > 0 || substr($param_name, -3) > 0 || substr($param_name, -2) > 30) {
      // Stop at 30 authors - or page codes will become cluttered! 
      if ($this->get('last29') || $this->get('author29') || $this->get('surname29')) $this->add_if_new('display-authors', '29');
      return FALSE;
     }
    }

    $auNo = preg_match('~\d+$~', $param_name, $auNo) ? $auNo[0] : '';

    switch ($param_name) {
      ### EDITORS
      case (bool) preg_match('~^editor(\d{1,})$~', $param_name, $match) :
        if ($this->had_initial_editor) return FALSE;
        if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) return FALSE; // Existing incompatible data
        if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-last', 'editor' . $match[1] . '-first',
                          'editor-last' . $match[1], 'editor-first' . $match[1]])) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;

      case (bool) preg_match('~^editor(\d{1,})-first$~', $param_name, $match) :
        if ($this->had_initial_editor) return FALSE;
        if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) return FALSE; // Existing incompatible data
        if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-first', 'editor-first' . $match[1]])) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;

      case (bool) preg_match('~^editor(\d{1,})-last$~', $param_name, $match) :
        if ($this->had_initial_editor) return FALSE;
        if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) return FALSE; // Existing incompatible data
        if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-last', 'editor-last' . $match[1]])) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;
      
      #TRANSLATOR
      case (bool) preg_match('~^translator(\d{1,})$~', $param_name, $match) :
        if (!$this->blank(['translators', 'translator', 'translator-last', 'translator-first'])) return FALSE; // Existing incompatible data
        if ($this->blank(['translator' . $match[1], 'translator' . $match[1] . '-last', 'translator' . $match[1] . '-first'])) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;
        
      ### AUTHORS
      case "author": case "author1": case "last1": case "last": case "authors":
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);
        $value = trim(straighten_quotes($value));

        if ($this->blank(FIRST_AUTHOR_ALIASES)) {
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
       if ($this->blank(FIRST_FORENAME_ALIASES)) {
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
        
      case 'accessdate':
      case 'access-date':
        if (!$this->blank(['access-date', 'accessdate'])) return FALSE;
        $time = strtotime($value);
        if ($time) { // should come in cleaned up
            if ($this->date_style === DATES_MDY) {
               $value = date('F j, Y', $time);
            } elseif ($this->date_style === DATES_DMY) {
               $value = date('j F Y', $time);
            }
            return $this->add($param_name, $value);
        }
        return FALSE;
        
      case 'archivedate';
      case 'archive-date':
        if (!$this->blank(['archive-date', 'archivedate'])) return FALSE;
        $time = strtotime($value);
        if ($time) { // should come in cleaned up
            if ($this->date_style === DATES_MDY) {
               $value = date('F j, Y', $time);
            } elseif ($this->date_style === DATES_DMY) {
               $value = date('j F Y', $time);
            }
            return $this->add($param_name, $value);
        }
        return FALSE;
      
      ### DATE AND YEAR ###
      
      case "date":
        if (preg_match("~^\d{4}$~", sanitize_string($value))) {
          // Not adding any date data beyond the year, so 'year' parameter is more suitable
          $param_name = "year";
        } elseif ($this->date_style !== DATES_WHATEVER || preg_match('~^\d{4}\-\d{2}\-\d{2}$~', $value)) {
          $time = strtotime($value);
          if ($time) {
            $day = date('d', $time);
            if ($day !== '01') { // Probably just got month and year if day=1
              if ($this->date_style === DATES_MDY) {
                 $value = date('F j, Y', $time);
              } else { // DATES_DMY and make DATES_WHATEVER pretty
                 $value = date('j F Y', $time);
              }
            }
          }
        }
      // Don't break here; we want to go straight in to year;
      case "year":
        if ($value === $this->year()) return FALSE;
        if (   ($this->blank('date')
               || in_array(trim(strtolower($this->get_without_comments_and_placeholders('date'))), IN_PRESS_ALIASES))
            && ($this->blank('year') 
               || in_array(trim(strtolower($this->get_without_comments_and_placeholders('year'))), IN_PRESS_ALIASES))
          ) {
          if ($param_name != 'date') $this->forget('date'); // Delete any "in press" dates.
          if ($param_name != 'year') $this->forget('year'); // We only unset the other one so that parameters stay in order as much as possible
          if ($this->add($param_name, $value)) {
            $this->tidy_parameter('isbn');  // We just added a date, we now know if 2007 or later
            return TRUE;
          }
        }
        // Update Year with CrossRef data in a few limited cases
        if ($param_name === 'year' && $api === 'crossref' && $this->no_initial_doi && ((int) $this->year() < $value) && ((int)date('Y') - 3 < $value)) {
          $this->forget('date');
          $this->set('year', $value);
          $this->tidy_parameter('isbn');
          return TRUE;
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
        
      case 'issn_force': // When dropping URL, force adding it
        if ($this->blank('issn') && preg_match('~^\d{4}-\d{3}[\dxX]$~', $value)) {
          return $this->add('issn', $value);
        }
        return FALSE;
        
      case 'periodical': case 'journal': case 'newspaper': case 'magazine':
        if ($value=='HEP Lib.Web') $value = 'High Energy Physics Libraries Webzine'; // should be array
        if (preg_match('~Conference Proceedings.*IEEE.*IEEE~', $value)) return FALSE;
        if (!$this->blank(['booktitle', 'book-title'])) return FALSE;
        if (in_array(strtolower(sanitize_string($value)), BAD_TITLES )) return FALSE;
        if (in_array(strtolower(sanitize_string($this->get('journal'))), BAD_TITLES)) $this->forget('journal'); // Update to real data
        if (preg_match('~^(?:www\.|)rte.ie$~i', $value)) $value = 'RTÉ News'; // Russian special case code
        if ($this->wikiname() === 'cite book' && $this->has('chapter') && $this->has('title') && $this->has('series')) return FALSE;
        if ($this->has('title') && str_equivalent($this->get('title'), $value)) return FALSE; // Messed up already or in database
        if (!$this->blank(array_merge(['agency','publisher'],WORK_ALIASES)) && in_array(strtolower($value), DUBIOUS_JOURNALS)) return FALSE; // non-journals that are probably same as agency or publisher that come from zotero
        if ($this->get($param_name) === 'none' || $this->blank(["journal", "periodical", "encyclopedia", "encyclopaedia", "newspaper", "magazine", "contribution"])) {
          if (in_array(strtolower(sanitize_string($value)), HAS_NO_VOLUME)) $this->forget("volume") ; // No volumes, just issues.
          $value = wikify_external_text(title_case($value));
          if ($this->has('series') && str_equivalent($this->get('series'), $value)) return FALSE ;
          if ($this->has('work')) {
            if (str_equivalent($this->get('work'), $value)) {
              $this->rename('work', $param_name);
              if (!$this->blank(['pmc', 'doi', 'pmid'])) $this->forget('issn');
              return TRUE;
            } else {
              return FALSE;  // Cannot have both work and journal
            }
          }
          if ($this->has('via')) {
            if (str_equivalent($this->get('via'), $value)) {
              $this->rename('via', $param_name);
              if (!$this->blank(['pmc', 'doi', 'pmid'])) $this->forget('issn');
              return TRUE;
            }
          }
          $this->forget('class');
          if ($this->wikiname() === 'cite arxiv') $this->change_name_to('cite journal');
          if ($param_name === 'newspaper' && in_array(strtolower($value), WEB_NEWSPAPERS)) {
             if ($this->has('publisher') && str_equivalent($this->get('publisher'), $value)) return FALSE;
             if($this->blank('work')) {
               $this->set('work', $value);
               $this->quietly_forget('website');
               if (stripos($this->get('publisher'), 'bbc') !== FALSE && stripos($value, 'bbc') !== FALSE) {
                  $this->quietly_forget('publisher');
               }
               return TRUE;
             }
            report_error('Unreachable code reached in newspaper add'); // @codeCoverageIgnore
          } 
          if ($param_name === 'newspaper' && $this->has('via')) {
             if (stripos($value, 'times') !== FALSE && stripos($this->get('via'), 'times') !== FALSE) {
               $this->forget('via'); // eliminate via= that matches newspaper mostly
             }
             if (stripos($value, ' post') !== FALSE && stripos($this->get('via'), 'post') !== FALSE) {
               $this->forget('via'); // eliminate via= that matches newspaper mostly
             }
          }
          if (($param_name === 'newspaper' || $param_name === 'journal') && $this->has('publisher') && str_equivalent($this->get('publisher'), $value)
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
             $my_return = $this->add($param_name, $value);
             // Avoid running twice
             $this->tidy_parameter('publisher');
             return $my_return;
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
        if (!$this->blank(['booktitle', 'book-title']) && $this->has('title')) return FALSE;
        if ($this->blank(CHAPTER_ALIASES)) {
          return $this->add($param_name, wikify_external_text($value));
        }
        return FALSE;
      
      
      ###  ARTICLE LOCATORS  ###
      ### (page, volume etc) ###
      
      case 'title':
        if (in_array(strtolower(sanitize_string($value)), BAD_TITLES )) return FALSE;
        if ($this->blank($param_name) || in_array($this->get($param_name),
                                           ['Archived copy', "{title}", 'ScienceDirect', 'Google Books', 'None'])
                                      || (stripos($this->get($param_name), 'EZProxy') !== FALSE && stripos($value, 'EZProxy') === FALSE)) {
          if (str_equivalent($this->get('encyclopedia') . $this->get('encyclopaedia') , sanitize_string($value))) {
            return FALSE;
          }
          if (str_equivalent($this->get('work'), sanitize_string($value))) {
            return FALSE;
          }
          if (str_equivalent($this->get('dictionary'), sanitize_string($value))) {
            return FALSE;
          }
          if (str_equivalent($this->get('journal'), sanitize_string($value))) {
            return FALSE;
          }
          if ($this->has('article') && 
                 ($this->wikiname() === 'cite encyclopedia' || $this->wikiname() === 'cite dictionary' || $this->wikiname() === 'cite encyclopaedia')) return FALSE; // Probably the same thing
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
        if ($this->blank($param_name) || str_i_same('in press', $this->get($param_name))) {
          if ($value == '0') return FALSE;
          if ($value == '1') { // dubious
            if (bad_10_1093_doi($this->get('doi'))) return FALSE;
            if (stripos($this->rawtext, 'oxforddnb') !== FALSE) return FALSE;
            if (stripos($this->rawtext, 'escholarship.org') !== FALSE) return FALSE;
          }
          $temp_string = strtolower($this->get('journal')) ;
          if(substr($temp_string, 0, 2) === "[[" && substr($temp_string, -2) === "]]") {  // Wikilinked journal title 
               $temp_string = substr(substr($temp_string, 2), 0, -2); // Remove [[ and ]]
          }
          if (in_array($temp_string, HAS_NO_VOLUME)) {
            // This journal has no volume.  This is really the issue number
            return $this->add_if_new('issue', $value);
          } else {
            return $this->add($param_name, $value);
          }
        }
        return FALSE;
      
      case 'issue':
        if ($value == '0') return FALSE;
        if ($this->blank(ISSUE_ALIASES) || str_i_same('in press', $this->get($param_name))) {
          if ($value == '1') { // dubious
            if (bad_10_1093_doi($this->get('doi'))) return FALSE;
            if (stripos($this->rawtext, 'oxforddnb') !== FALSE) return FALSE;
            if (stripos($this->rawtext, 'escholarship.org') !== FALSE) return FALSE;
          }     
          return $this->add($param_name, $value);
        } elseif ($this->get('issue') . $this->get('number') == '1' && $value != '1' && $this->blank('volume')) {
          $this->set($param_name, $value);  // Updating bad data
          return TRUE;
        }
        return FALSE;
      
      case "page": case "pages":
        if (in_array((string) $value, ['0', '0-0', '0–0'], TRUE)) return FALSE;  // Reject bogus zero page number
        if ($this->has('at')) return FALSE;  // Leave at= alone.  People often use that for at=See figure 17 on page......
        if (preg_match('~^\d+$~', $value) && intval($value) > 1000000) return FALSE;  // Sometimes get HUGE values
        $pages_value = $this->get('pages');
        $all_page_values = $pages_value . $this->get('page') . $this->get('pp') . $this->get('p') . $this->get('at');
        $en_dash = [chr(2013), chr(150), chr(226), '-', '&ndash;'];
        $en_dash_X = ['X', 'X', 'X', 'X', 'X'];
        if (  mb_stripos($all_page_values, 'see ')  !== FALSE      // Someone is pointing to a specific part
           || mb_stripos($all_page_values, 'table') !== FALSE
           || mb_stripos($all_page_values, 'footnote') !== FALSE
           || mb_stripos($all_page_values, 'endnote') !== FALSE
           || mb_stripos($all_page_values, 'CITATION_BOT_PLACEHOLDER') !== FALSE) { // A comment or template will block the bot
           return FALSE;
        }
        if ($this->blank(PAGE_ALIASES) // no page yet set
           || $all_page_values == ""
           || (str_i_same($all_page_values,'no') || str_i_same($all_page_values,'none')) // Is exactly "no" or "none"
           || (strpos(strtolower($all_page_values), 'no') !== FALSE && $this->blank('at')) // "None" or "no" contained within something other than "at"
           || (
                (  str_replace($en_dash, $en_dash_X, $value) != $value) // dash in new `pages`
                && str_replace($en_dash, $en_dash_X, $pages_value) == $pages_value // No dash already
              )
           || (   // Document with bogus pre-print page ranges
                   ($value           !== '1' && substr(str_replace($en_dash, $en_dash_X, $value), 0, 2)           !== '1X') // New is not 1-
                && ($all_page_values === '1' || substr(str_replace($en_dash, $en_dash_X, $all_page_values), 0, 2) === '1X') // Old is 1-
                && ($this->blank('year') || 2 > ((int) date("Y") - (int) $this->get('year'))) // Less than two years old
              )
        ) {
            if ($param_name === "pages" && preg_match('~^\d{1,}$~', $value)) $param_name = 'page'; // No dashes, just numbers
            // One last check to see if old template had a specific page listed
            if ($all_page_values != '' &&
                preg_match("~^[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?[-–—‒]+[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?$~u", $value, $newpagenos) && // Adding a range
                preg_match("~^[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?~u", $all_page_values, $oldpagenos)) { // Just had a single number before
                $first_page = (int) $newpagenos[1];
                $last_page  = (int) $newpagenos[2];
                $old_page   = (int) $oldpagenos[1];
                if ($last_page < $first_page) { // 2342-5 istead of 2342-2345
                   if ($last_page < 10) {
                     $last_page = $last_page + (10 * (int)($first_page/10));
                   } else {
                     $last_page = $last_page + (100 * (int)($first_page/100));
                   }
                }
                if ($old_page > $first_page && $old_page <= $last_page) {
                  foreach (['pages', 'page', 'pp', 'p'] as $forget_blank) {
                    if ($this->blank($forget_blank)) {
                      $this->forget($forget_blank);
                    }
                  }
                  return FALSE;
                }
            }
            // 1-34 vs article 431234 -- Some give range and article ID as page numbers depending upon database - at least 4 characters though.  Prefer article number
            if (preg_match('~^1[-–]\d+$~u', $value) && preg_match('~^[a-zA-Z1-9]\d{3,}$~', $all_page_values)) {
              return FALSE;
            }
            if ($param_name !== "pages") $this->forget('pages'); // Forget others -- sometimes we upgrade page=123 to pages=123-456
            if ($param_name !== "page")  $this->forget('page');
            // Forget ones we never even add
            $this->forget('pp');
            $this->forget('p');
            $this->forget('at');

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
        if ($this->get_identifiers_from_url($value)) return FALSE;
        if (!$this->blank(array_merge([$param_name], TITLE_LINK_ALIASES))) return FALSE;
        if ($this->get('title') === 'none') return FALSE;
        if (strpos($this->get('title'), '[') !== FALSE) return FALSE;  // TITLE_LINK_ALIASES within the title
        $value = sanitize_string($value);
        foreach (ALL_URL_TYPES as $exisiting)  {
          if (str_i_same($value, $this->get($exisiting))) {
            return FALSE;
          }
        }
        return $this->add($param_name, $value);
        
      case 'archive-url':
        if ($this->blank(['archive-url', 'archiveurl'])) {
           $this->add($param_name, $value);
           $this->tidy_parameter($param_name);
           return TRUE;
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
        if ($value == '10.5284/1000184') return FALSE; // This is a DOI for an entire database, not anything within it
        if ($value == '10.1267/science.040579197') return FALSE; // PMID test doi
        if (substr($value, 0, 8) == '10.5555/') return FALSE ; // Test DOI prefix.  NEVER will work
        if (stripos($value, '10.1093/law:epil') === 0) return FALSE; // Those do not work
        if (stripos($value, '10.1093/oi/authority') === 0) return FALSE; // Those do not work
        if (stripos($value, '10.1111/j.1572-0241') === 0 && NATURE_FAILS) return FALSE;
        if (preg_match(REGEXP_DOI, $value, $match)) {
          if ($this->blank($param_name)) {
            if ($this->wikiname() === 'cite arxiv') $this->change_name_to('cite journal');
            $this->add('doi', $match[0]);
            return TRUE;
          } elseif (!str_i_same($this->get('doi'), $match[0]) && !$this->blank(DOI_BROKEN_ALIASES) && doi_active($match[0])) {
            report_action("Replacing non-functional DOI with a functional one");
            $this->set('doi', $match[0]);
            $this->tidy_parameter('doi');
            return TRUE;
          } elseif (!str_i_same($this->get('doi'), $match[0])
                    && strpos($this->get('doi'), '10.13140/') === 0 
                    && doi_active($match[0])) {
            report_action("Replacing ResearchGate DOI with publisher's");
            $this->set('doi', $match[0]);
            $this->tidy_parameter('doi');
            return TRUE;
          }
        }
        return FALSE;
      
      case 's2cid':
        if ($this->blank(['s2cid', 'S2CID'])) {
          $this->add($param_name, $value);
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
          $this->get_doi_from_semanticscholar();
          return TRUE;
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
        if ($this->blank('jstor')) {
          check_doi_for_jstor($this->get('doi'), $this);
          if ($this->has('jstor')) {
            $this->quietly_forget('doi');
            return TRUE;
          }
        }
        // Forget any others that are blank
        foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
          if ($this->blank($alias)) {
             $this->forget($alias);
          }
        }
        // Switch any that are set to doi-broken-date
        if ($this->blank('doi-broken-date')) {
          foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
            $this->rename($alias, 'doi-broken-date');
          }
        } else {
          foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
            $this->forget($alias);
          }
        }
        $time = strtotime($value);
        if ($time) { // paranoid
            if ($this->date_style === DATES_MDY) {
               $value = date('F j, Y', $time);
            } elseif ($this->date_style === DATES_DMY) {
               $value = date('j F Y', $time);
            }
        }
        if ($this->blank(DOI_BROKEN_ALIASES)) {
          return $this->add($param_name, $value);
        }
        $existing = strtotime($this->get('doi-broken-date'));
        $the_new  = strtotime($value);
        if (($existing === FALSE) || ($existing + 2592000 < $the_new) || (2592000 + $the_new < $existing)) { // A month difference
           return $this->add($param_name, $value);
        }
        return FALSE;
      
      case 'pmid':
        if ($value === "0" ) return FALSE;  // Got PMID of zero once from pubmed
        if ($this->blank($param_name)) {
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
          $this->add($param_name, sanitize_string($value));
          $this->expand_by_pubmed($this->blank('pmc') || $this->blank('doi'));  //Force = TRUE if missing DOI or PMC
          $this->get_doi_from_crossref();
          return TRUE;
        }
        return FALSE;

      case 'pmc':
        if ($value === "PMC0" || $value === "0" ) return FALSE;  // Got PMID of zero once from pubmed
        if ($this->blank($param_name)) {
          $this->add($param_name, sanitize_string($value));
          if ($this->blank('pmid')) {
            $this->expand_by_pubmed(TRUE); // Almost always can get a PMID (it is rare not too)
          }
          return TRUE;
        }
        return FALSE;
      
      case 'bibcode_nosearch':  // Avoid recursive loop
      case 'bibcode':
        if (stripos($value, 'arxiv') === FALSE &&
            stripos($this->get('bibcode'), 'arxiv') !== FALSE &&
            strlen(trim($value)) > 16
            ) {
          $this->quietly_forget('bibcode');  // Upgrade bad bibcode
        }
        if ($this->blank('bibcode')) { 
          $bibcode_pad = 19 - strlen($value);
          if ($bibcode_pad > 0) {  // Paranoid, don't want a negative value, if bibcodes get longer
            $value = $value . str_repeat( ".", $bibcode_pad);  // Add back on trailing periods
          }
          if (stripos($value, 'arxiv') !== FALSE) {
            if ($this->has('arxiv') || $this->has('eprint'))return FALSE;
            $low_quality = TRUE;
          } else {
            $low_quality = FALSE;
          }
          $this->add('bibcode', $value);
          if ($param_name === 'bibcode') $this->expand_by_adsabs();
          if ($low_quality) {
            $this->quietly_forget('bibcode');
          }
          return TRUE;
        } 
        return FALSE;
      
      case 'isbn';
        if ($this->blank($param_name)) { 
          $value = $this->isbn10Toisbn13($value);
          if (strlen($value) > 19) return FALSE; // Two ISBNs
          return $this->add($param_name, $value);
        }
        return FALSE;

      case 'asin':
        if ($this->blank($param_name)) {
          if($this->has('isbn')) { // Already have ISBN
            quietly('report_inaction', "Not adding ASIN: redundant to existing ISBN.");
            return FALSE;
          } elseif (preg_match("~^\d~", $value) && substr($value, 0, 2) !== '63') { // 630 and 631 ones are not ISBNs, so block all of 63*
            $possible_isbn = sanitize_string($value);
            $possible_isbn13 = $this->isbn10Toisbn13($possible_isbn, TRUE);
            if ($possible_isbn === $possible_isbn13) {
              return $this->add('asin', $possible_isbn); // Something went wrong, add as ASIN
            } else {
              return $this->add('isbn', $this->isbn10Toisbn13($possible_isbn));
            }
          } else {  // NOT ISBN
            return $this->add($param_name, sanitize_string($value));
          }
        }
        return FALSE;
      
      case 'publisher':
        if (strlen(preg_replace('~[\.\s\d\,]~', '', $value)) < 5) return FALSE; // too few characters 
        if (stripos($value, 'Springer') === 0) $value = 'Springer'; // they add locations often
        if (stripos($value, '[s.n.]') !== FALSE) return FALSE;
        if (preg_match('~^\[([^\|\[\]]*)\]$~', $value, $match)) $value = $match[1]; // usually zotero problem of [data]
        if (preg_match('~^(.+), \d{4}$~', $value, $match)) $value = $match[1]; // remove years from zotero 
        if (strpos(strtolower($value), 'london') !== FALSE) return FALSE; // Common from archive.org
        if (strpos(strtolower($value), 'edinburg') !== FALSE) return FALSE; // Common from archive.org
        if (strpos(strtolower($value), 'privately printed') !== FALSE) return FALSE; // Common from archive.org 
        if (str_equivalent($this->get('location'), $value)) return FALSE; // Catch some bad archive.org data
        if (strpos(strtolower($value), 'impressum') !== FALSE) return FALSE; // Common from archive.org
        if (strpos(strtolower($value), ':') !== FALSE) return FALSE; // Common from archive.org when location is mixed in
        if ($this->has('journal') && ($this->wikiname() === 'cite journal')) return FALSE;
        $value = truncate_publisher($value);
        if (in_array(trim(strtolower($value), " \.\,\[\]\:\;\t\n\r\0\x0B" ), BAD_PUBLISHERS)) return FALSE;
        if ($this->has('via') && str_equivalent($this->get('via'), $value))  $this->rename('via', $param_name);
        if ($this->blank($param_name)) {
          return $this->add($param_name, $value);
        }
        return FALSE;

      case 'type':
        if ($this->blank($param_name) &&
            !in_array(strtolower($value), ['text', 'data set']) &&
            strlen($value) === mb_strlen($value) &&
            strpos($value, 'purl.org') === FALSE &&
            strpos($value, 'dcmitype') === FALSE &&
            strpos($value, 'http') === FALSE
           ) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;
         
      case 'zbl': case 'location': case 'jstor': case 'oclc': case 'mr': case 'lccn': case 'hdl':
      case 'ssrn': case 'ol': case 'jfm': case 'osti': case 'biorxiv': case 'citeseerx':
        if ($this->blank($param_name)) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;
        
      case (bool) preg_match('~author(?:\d{1,}|)-link~', $param_name):
        if ($this->blank($param_name)) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;

      case 'id':
        if ($this->blank($param_name)) {
          return $this->add($param_name, $value); // Do NOT Sanitize.  It includes templates often
        }
        return FALSE;

      case 'edition':
        if ($this->blank($param_name)) {
          $this->add($param_name, $value);
          return TRUE;
        }
        return FALSE;

      default:  // We want to make sure we understand what we are adding
        // @codeCoverageIgnoreStart
        report_error('Unexpected parameter: ' . echoable($param_name) . ' trying to be set to ' . echoable($value));
        if ($this->blank($param_name)) {
          return $this->add($param_name, sanitize_string($value));
        }
        return FALSE;
        // @codeCoverageIgnoreEnd
    }
  }

  public function validate_and_add(string $author_param, string $author, string $forename, string $check_against, bool $add_even_if_existing) : void {
    $match = ['', '']; // prevent memory leak in some PHP versions
    if (!$add_even_if_existing && ($this->initial_author_params || $this->had_initial_editor)) return; // Zotero does not know difference betwee editors and authors often
    if (in_array(strtolower($author), BAD_AUTHORS) === FALSE && author_is_human($author) && author_is_human($forename)) {
      while(preg_match('~^(.*)\s[\S]+@~', ' ' . $author, $match) || // Remove emails 
            preg_match('~^(.*)\s+@~', ' ' . $author, $match)) { // Remove twitter handles
         $author = trim($match[1]);
      }
      while(preg_match('~^(.*)\s[\S]+@~', ' ' . $forename, $match) || // Remove emails 
            preg_match('~^(.*)\s+@~', ' ' . $forename, $match)) { // Remove twitter handles
         $forename = trim($match[1]);
      }
      if (trim($author) == '') {
         $author = trim($forename);
         $forename = '';
      }
      $author_parts  = explode(" ", $author);
      $author_ending = end($author_parts);
      $name_as_publisher = trim($forename . ' ' . $author);
      if (in_array(strtolower($author_ending), PUBLISHER_ENDINGS)
          || stripos($check_against, $name_as_publisher) !== FALSE) {
        $this->add_if_new('publisher' , $name_as_publisher);
      } else {
        $this->add_if_new($author_param, format_author($author . ($forename ? ", $forename" : '')));
      }
    }
  }
  
  public function mark_inactive_doi() : void {
    $doi = $this->get_without_comments_and_placeholders('doi');
    if (doi_works($doi) === FALSE) { // NULL which would cast to FALSE means we don't know, so use ===
      $this->add_if_new('doi-broken-date', date('Y-m-d'));
    }
  }
  
  // This is also called when adding a URL with add_if_new, in which case
  // it looks for a parameter before adding the url.
  public function get_identifiers_from_url(?string $url_sent = NULL) : bool {
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $bibcode = ['', '']; // prevent memory leak in some PHP versions
    $arxiv_id = ['', '']; // prevent memory leak in some PHP versions
    $match = ['', '']; // prevent memory leak in some PHP versions
    if (is_null($url_sent)) {
       // Chapter URLs are generally better than URLs for the whole book.
        if ($this->has('url') && $this->has('chapterurl')) {
           return (bool) ((int) $this->get_identifiers_from_url(Self::MAGIC_STRING . 'chapterurl ') +
                          (int) $this->get_identifiers_from_url(Self::MAGIC_STRING . 'url '));
        } elseif ($this->has('url') && $this->has('chapter-url')) {
           return (bool) ((int) $this->get_identifiers_from_url(Self::MAGIC_STRING . 'chapter-url ') +
                          (int) $this->get_identifiers_from_url(Self::MAGIC_STRING . 'url '));
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
          if (preg_match ('~^https?://[^/]+/?$~', $url) === 1) return FALSE; // Just a host name
          $this->rename('website', 'url'); // Rename it first, so that parameters stay in same order
          $this->set('url', $url);
          $url_type = 'url';
          quietly('report_modification', "website is actually HTTP URL; converting to use url parameter.");
        } else {
          // If no URL or website, nothing to worth with.
          return FALSE;
        }
    } elseif (preg_match('~^' . Self::MAGIC_STRING . '(\S+) $~', $url_sent, $matches)) {
      $url_sent = NULL;
      $url_type = $matches[1];
      $url      = $this->get($matches[1]);
    } else {
      $url = $url_sent;
      $url_type = 'An invalid value';
    }
    
    if (strtolower(substr( $url, 0, 6 )) === "ttp://" || strtolower(substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
      $url = "h" . $url;
      if (is_null($url_sent)) {
        $this->set($url_type, $url); // Save it
      }
    }
    // Abstract only websites
    if (preg_match('~orbit.dtu.dk/en/publications~', $url)) { // This file path only
       if (is_null($url_sent)) {
         if ($this->has('pmc')) {
            $this->forget($url_type); // Remove it to make room for free-link
         } elseif ($this->has('doi') && $this->get('doi-access') === 'free') {
            $this->forget($url_type); // Remove it to make room for free-link
         }
       }
       return FALSE;
    }
    // IEEE
    if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
       $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
       if (is_null($url_sent)) {
         $this->set($url_type, $url); // Update URL with cleaner one
       } 
    }
    if (preg_match('~^https?://ieeexplore\.ieee\.org(?:|\:80)/(?:|abstract/)document/(\d+)/?(?:|\?reload=true)$~', $url, $matches)) {
       $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
       if (is_null($url_sent)) {
         $this->set($url_type, $url); // Normalize to HTTPS and remove abstract and remove trailing slash etc
       }
    }
    if (preg_match('~^https?://ieeexplore\.ieee\.org.*/iel5/\d+/\d+/(\d+).pdf(?:|\?.*)$~', $url, $matches)) {
       $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
       if (is_null($url_sent)) {
         $this->set($url_type, $url); // Normalize 
       }
    }
    if (preg_match('~^https://ieeexplore\.ieee\.org/document/0+(\d+)$~', $url, $matches)) {
       $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
       if (is_null($url_sent)) {
         $this->set($url_type, $url); // Trimming leading zeroes
       }
    }
    
    // semanticscholar
    if (preg_match('~^https?://(?:pdfs?\.|www\.|)semanticscholar\.org/~i', $url)) {
       $s2cid = getS2CID($url);
       if ($s2cid == '') return FALSE;
       if ($this->has('s2cid') && $s2cid != $this->get('s2cid')) {
          report_warning('Existsing URL does not match exisiting S2CID: ' .  echoable($this->get('s2cid')));
          return FALSE;
       }
       if ($this->has('S2CID') && $s2cid != $this->get('S2CID')) {
          report_warning('Existsing URL does not match exisiting S2CID: ' .  echoable($this->get('S2CID')));
          return FALSE;
       }
       $this->add_if_new('s2cid', $s2cid);
       if (is_null($url_sent) && $this->has('pmc')) {
         // SEP 2020 report_info('Removed Converted Semantic Scholar URL that blocked PMC URL');
         // SEP 2020 $this->forget($url_type);
         return TRUE;
       }
       if (is_null($url_sent) && get_semanticscholar_license($s2cid) === FALSE) {
         report_warning('Should probably remove un-licensed Semantic Scholar URL that was converted to S2CID parameter');
         // SEP 2020 $this->forget($url_type);
         return TRUE;
       }
       return TRUE;
    }

    // Trim ?seq=1#page_scan_tab_contents off of jstor urls
    // We do this since not all jstor urls are recognized below
    if (preg_match("~^(https?://\S*jstor.org\S*)\?seq=1#[a-zA-Z_]+$~", $url, $matches)) {
       $url = $matches[1];
       if (is_null($url_sent)) {
         $this->set($url_type, $url); // Update URL with cleaner one
       }     
    }
    if (preg_match("~^(https?://\S*jstor.org\S*)\?refreqid=~", $url, $matches)) {
       $url = $matches[1];
       if (is_null($url_sent)) {
         $this->set($url_type, $url); // Update URL with cleaner one
       }     
    }
    if (stripos($url, 'plants.jstor.org') !== FALSE) {
      return FALSE; # Plants database, not journal
    }
    // https://www.jstor.org.stuff/proxy/stuff/stable/10.2307/3347357 and such
    // Optional 0- at front.
    // DO NOT change www.jstor.org to www\.jstor\.org  -- Many proxies use www-jstor-org
    if (preg_match('~^(https?://(?:0-www.|www.|)jstor.org)(?:\S*proxy\S*/|/)(?:stable|discover)/10.2307/(.+)$~i', $url, $matches)) {
       $url = $matches[1] . '/stable/' . $matches[2] ; // that is default.  This also means we get jstor not doi
       if (is_null($url_sent)) {
         $this->set($url_type, $url); // Update URL with cleaner one.  Will probably call forget on it below
       }
    }
    // https://www.jstor.org.libweb.lib.utsa.edu/stable/3347357 and such
    // Optional 0- at front.
    // DO NOT change www.jstor.org to www\.jstor\.org  -- Many proxies use www-jstor-org
    // https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10 and such
    if (preg_match('~^https?://(?:0-www.|www.|)jstor.org\.[^/]+/(?:stable|discover)/(.+)$~i', $url, $matches)) {
       $url = 'https://www.jstor.org/stable/' . $matches[1] ;
       if (is_null($url_sent)) {
         $this->set($url_type, $url); // Update URL with cleaner one
       }
    }
    
    if (preg_match('~^https?://(?:www\.|)jstor\.org/stable/(?:pdf|pdfplus)/(.+)\.pdf$~i', $url, $matches) ||
        preg_match('~^https?://(?:www\.|)jstor\.org/tc/accept\?origin=(?:\%2F|/)stable(?:\%2F|/)pdf(?:\%2F|/)(\d{3,})\.pdf$~i', $url, $matches)) {
       if ($matches[1] == $this->get('jstor')) {
         if (is_null($url_sent)) {
           // SEP 2020 $this->forget($url_type);
         }
         return FALSE;
       } elseif ($this->blank('jstor')) {
          $ch = curl_init();
          curl_setopt_array($ch,
                   [CURLOPT_HEADER => 0,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_URL => 'https://www.jstor.org/citation/ris/' . $matches[1],
                    CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org']);
          $dat = (string) @curl_exec($ch);
          curl_close($ch);
          if ($dat &&
              stripos($dat, 'No RIS data found for') === FALSE &&
              stripos($dat, 'Block Reference') === FALSE &&
              stripos($dat, 'A problem occurred trying to deliver RIS data') === FALSE &&
              substr_count($dat, '-') > 3) { // It is actually a working JSTOR.  Not sure if all PDF links are done right
            // SEP 2020 if (is_null($url_sent)) $this->forget($url_type);
            return $this->add_if_new('jstor', $matches[1]);
          }
        }
    }
    if ($this->has('jstor') && preg_match('~^https?://(?:www\.|)jstor\.org/(?:stable|discover)/(?:|pdf/)' . $this->get('jstor') . '(?:|\.pdf)$~i', $url)) {
       if (is_null($url_sent)) {
         // SEP 2020 $this->forget($url_type);
       }
       return FALSE;
    }
    if (preg_match('~^https?://(?:www\.|)archive\.org/detail/jstor\-(\d{5,})$~i', $url, $matches)) {
       $this->add_if_new('jstor', $matches[1]);
       if (is_null($url_sent)) {
         // SEP 2020 $this->forget($url_type);
       }
       return FALSE;
    }

    if (preg_match("~^https?://(?:(?:dx\.|www\.|)doi\.org|doi\.library\.ubc\.ca)/([^\?]*)~i", $url, $match)) {
      if ($this->has('doi')) {
        if (str_i_same($this->get('doi'), $match[1])) {
         if (is_null($url_sent) && $this->get('doi-access') === 'free') {
          quietly('report_modification', "URL is hard-coded DOI; removing since we already have free DOI paramter");
          $this->forget($url_type);
         }
         return FALSE;
        }
        // The DOIs do not match
        if (is_null($url_sent)) {
         report_warning('doi.org URL does not match existing DOI paramter, investigating...');
        }
        if (doi_works($match[1]) && !doi_works($this->get('doi'))) {
          $this->set('doi', $match[1]);
          if (is_null($url_sent)) {
            // SEP 2020 $this->forget($url_type);
          }
          return TRUE;
        }
        if (!doi_works($match[1]) && doi_works($this->get('doi'))) {
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return FALSE;
        }
        return FALSE; // Both valid or both invalid (could be legit if chapter and book are different DOIs
      }
      if ($this->add_if_new('doi', urldecode($match[1]))) { // Will expand from DOI when added
        if (is_null($url_sent)) {
          // SEP 2020 quietly('report_modification', "URL is hard-coded DOI; converting to use DOI parameter.");
          // SEP 2020 $this->forget($url_type);
        }
        return TRUE;
      } else {
        return FALSE; // "bad" doi? 
      }
    }
    if (stripos($url, 'oxforddnb.com') !== FALSE) return FALSE; // generally bad, and not helpful
    if ($doi = extract_doi($url)[1]) {
      if (bad_10_1093_doi($doi)) return FALSE;
      $old_jstor = $this->get('jstor');
      if (stripos($url, 'jstor')) check_doi_for_jstor($doi, $this);
      if (is_null($url_sent) && $old_jstor !== $this->get('jstor') && stripos($url, 'pdf') === FALSE) {
         // SEP 2020 $this->forget($url_type);
      }
      $this->tidy_parameter('doi'); // Sanitize DOI before comparing
      if ($this->has('doi') && mb_stripos($doi, $this->get('doi')) === 0) { // DOIs are case-insensitive
        if (doi_works($doi) && is_null($url_sent) && mb_strpos(strtolower($url), ".pdf") === FALSE && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
          // SEP 2020 report_forget("Recognized existing DOI in URL; dropping URL");
          // SEP 2020 $this->forget($url_type);
        }
        return FALSE;  // URL matched existing DOI, so we did not use it
      }
      if ($this->add_if_new('doi', $doi)) {
        if (doi_active($doi)) {
          if (is_null($url_sent)) {
            if (mb_strpos(strtolower($url), ".pdf") === FALSE && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
              // SEP 2020 report_forget("Recognized DOI in URL; dropping URL");
              // SEP 2020 $this->forget($url_type);
            } else {
              report_info("Recognized DOI in URL.  Leaving *.pdf URL.");
            }
          }
        } else {
          $this->mark_inactive_doi();
        }
        return TRUE; // Added new DOI
      }
      return FALSE; // Did not add it
    } elseif ($this->has('doi')) { // Did not find a doi, perhaps we were wrong
      $this->tidy_parameter('doi'); // Sanitize DOI before comparing
      if (mb_stripos($url, $this->get('doi')) !== FALSE) { // DOIs are case-insensitive
        if (doi_works($this->get('doi')) && is_null($url_sent) && mb_strpos(strtolower($url), ".pdf") === FALSE && not_bad_10_1093_doi($this->get('doi')) && !preg_match(REGEXP_DOI_ISSN_ONLY, $this->get('doi'))) {
          // SEP 2020 report_forget("Recognized existing DOI in URL; dropping URL");
          // SEP 2020 $this->forget($url_type);
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
        curl_setopt_array($ch,
              [CURLOPT_HEADER => 1,
               CURLOPT_NOBODY => 1,
               CURLOPT_RETURNTRANSFER => TRUE,
               CURLOPT_TIMEOUT => 15,
               CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org']);
        if (@curl_exec($ch)) {
          $redirect_url = (string) @curl_getinfo($ch, CURLINFO_REDIRECT_URL);
          if (strpos($redirect_url, "jstor.org/stable/")) {
            $url = $redirect_url;
            if (is_null($url_sent)) {
              $this->set($url_type, $url); // Save it
            }
          } else {  // We do not want this URL incorrectly parsed below, or even waste time trying.
            curl_close($ch);  // @codeCoverageIgnore
            return FALSE;     // @codeCoverageIgnore
          }
        }
        curl_close($ch);
      }
      if (preg_match("~^/(?:\w+/)*(\d{5,})[^\d%\-]*(?:\?|$)~", substr($url, (int) stripos($url, 'jstor.org') + 9), $match) ||
                preg_match("~^https?://(?:www\.)?jstor\.org\S+(?:stable|discovery)/(?:10\.7591/|)(\d{5,}|(?:j|J|histirel|jeductechsoci|saoa)\.[a-zA-Z0-9\.]+)$~", $url, $match)) {
        if (is_null($url_sent)) {
          // SEP 2020 $this->forget($url_type);
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
            // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('bibcode', urldecode($bibcode[1]));
        } elseif (is_null($url_sent) && urldecode($bibcode[1]) === $this->get('bibcode')) {
          // SEP 2020 $this->forget($url_type);
        }
        
      } elseif (preg_match("~^https?://(?:www\.|)pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d{4,})"
                      . "|^https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?pmc/articles/(?:PMC|instance)?(\d{4,})~i", $url, $match)) {
        if (preg_match("~\?term~i", $url)) return FALSE; // A search such as https://www.ncbi.nlm.nih.gov/pmc/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        if ($this->blank('pmc')) {
          quietly('report_modification', "Converting URL to PMC parameter");
        }
        if (is_null($url_sent)) {
          if (stripos($url, ".pdf") !== FALSE) {
            $test_url = "https://www.ncbi.nlm.nih.gov/pmc/articles/PMC" . $match[1] . $match[2] . "/";
            $ch = curl_init($test_url);
            curl_setopt_array($ch,
                      [CURLOPT_RETURNTRANSFER => TRUE,
                       CURLOPT_TIMEOUT => 15,
                       CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org']);
            @curl_exec($ch);
            $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode == 404) { // Some PMCs do NOT resolve.  So leave URL
              return $this->add_if_new('pmc', $match[1] . $match[2]);
            }
          }
          if (stripos($url, "table") === FALSE) $this->forget($url_type); // This is the same as PMC auto-link
        } 
        return $this->add_if_new('pmc', $match[1] . $match[2]);
      } elseif (preg_match("~^https?://(?:www\.|)europepmc\.org/articles?/pmc/?(\d{4,})~i", $url, $match)  ||
                preg_match("~^https?://(?:www\.|)europepmc\.org/scanned\?pageindex=(?:\d+)\&articles=pmc(\d{4,})~i", $url, $match)) {
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        if ($this->blank('pmc')) {
          quietly('report_modification', "Converting Europe URL to PMC parameter");
        }
        if (is_null($url_sent) && stripos($url, ".pdf") === FALSE) {
           $this->forget($url_type); // This is same as PMC-auto-link
        }
        return $this->add_if_new('pmc', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)europepmc\.org/(?:abstract|articles?)/med/(\d{4,})~i", $url, $match)) {
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        if ($this->blank('pmid')) {
          quietly('report_modification', "Converting Europe URL to PMID parameter");
        }
        if (is_null($url_sent)) {
            // SEP 2020 $this->forget($url_type);
        }
        return $this->add_if_new('pmid', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)pubmedcentralcanada\.ca/pmcc/articles/PMC(\d{4,})(?:|/.*)$~i", $url, $match)) {
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        quietly('report_modification', "Converting Canadian URL to PMC parameter");
        if (is_null($url_sent)) {
            $this->forget($url_type);  // Always do this conversion, since website is gone!
        }
        return $this->add_if_new('pmc', $match[1]);
      } elseif (preg_match("~^https?://citeseerx\.ist\.psu\.edu/viewdoc/(?:summary|download)(?:\;jsessionid=[^\?]+|)\?doi=([0-9.]*)(?:&.+)?~", $url, $match)) {
        quietly('report_modification', "URL is hard-coded citeseerx; converting to use citeseerx parameter.");
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        if (is_null($url_sent)) {
          // SEP 2020 $this->forget($url_type);
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
          $ret = $this->add_if_new('arxiv', $arxiv_id[0]); // Have to add before forget to get cite type right
          if (is_null($url_sent)) {
            // SEP 2020 $this->forget($url_type);
          }
          return $ret;
        }
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite arxiv');
        
      } elseif (preg_match("~https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?" 
      . "(?:pubmed/|" 
      . "/eutils/elink\.fcgi\S+dbfrom=pubmed\S+/|"
      . "entrez/query\.fcgi\S+db=pubmed\S+|"
      . "pmc/articles/pmid/)"
      . ".*?=?(\d{4,})~i", $url, $match)||
          preg_match("~^https?://pubmed\.ncbi\.nlm\.nih\.gov/(?:|entrez/eutils/elink.fcgi\?dbfrom=pubmed\&retmode=ref\&cmd=prlinks\&id=)(\d{4,})(?:|/|-.+)$~", $url, $match)
        ) {
        if (preg_match("~\?term~i", $url) && !preg_match("~pubmed\.ncbi\.nlm\.nih\.gov/\d{4,}/\?from_term=~", $url)) {
          return FALSE; // A search such as https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
        }
        quietly('report_modification', "Converting URL to PMID parameter");
        if (is_null($url_sent)) {
          // SEP 2020 $this->forget($url_type);
        }
        if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
        return $this->add_if_new('pmid', $match[1]);
        
      } elseif (preg_match('~^http.+ncbi\.nlm\.nih\.gov/entrez/eutils/elink.fcgi\?.+tool=sumsearch\.org.+id=(\d+)$~', $url, $match)) {
        if ($url_sent) return FALSE;   // Many do not work
        if ($this->blank(['doi', 'pmc'])) return FALSE;  // This is a redirect to the publisher, not pubmed
        if ($match[1] == $this->get('pmc')) {
           $this->forget($url_type); // Same as PMC-auto-link
        } elseif ($match[1] == $this->get('pmid')) {
           // SEP 2020 $this->forget($url_type);
        }
        return FALSE;
        
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
      } elseif (stripos($url, 'handle') !== FALSE || stripos($url, 'persistentId=hdl:') !== FALSE) {
          // Special case of hdl.handle.net/123/456
          if (preg_match('~^https?://hdl\.handle\.net/(\d{2,}.*/.+)$~', $url, $matches)) {
            $url = 'https://hdl.handle.net/handle/' . $matches[1];
          }
          // Hostname
          $handle1 = FALSE;
          foreach (HANDLES_HOSTS as $hosts) {
            if (preg_match('~^https?://' . str_replace('.', '\.', $hosts) . '(/.+)$~', $url, $matches)) {
              $handle1 = $matches[1];
              break;
            }
          }
          if (!$handle1) return FALSE;
          // file path
          $handle = FALSE;
          foreach (HANDLES_PATHS as $handle_path) {
            if (preg_match('~^' . $handle_path . '(.+)$~', $handle1, $matches)) {
              $handle = $matches[1];
              break;
            }
          }
          if (!$handle) return FALSE;
          // Trim off session stuff - urlappend seems to be used for page numbers and such
          while (preg_match('~^(.+)(?:/browse\?|;jsessionid|;sequence=|\?sequence=|&isAllowed=|&origin=|&rd=|\?value=|&type=|/browse-title|&submit_browse=|\%3Bui=embed)~',
                                $handle, $matches)) {
            $handle = $matches[1];
          }
          $handle = urldecode($handle);
          // Verify that it works as a hdl - first with urlappend, since that is often page numbers
          if (preg_match('~^(.+)\?urlappend=~', $handle, $matches)) {  // should we shorten it
            usleep(100000);
            $test_url = "https://hdl.handle.net/" . $handle;
            $headers_test = @get_headers($test_url, 1);
            if ($headers_test === FALSE || empty($headers_test['Location'])) {
               $handle = $matches[1];
            }
          }
          while (preg_match('~^(.+)/$~', $handle, $matches)) { // Trailing slash
            $handle = $matches[1];
          }
          while (preg_match('~^/(.+)$~', $handle, $matches)) { // Leading slash
            $handle = $matches[1];
          }
          // Safety check
          if (strlen($handle) < 6 || strpos($handle, '/') === FALSE) return FALSE;

          $the_question = strpos($handle, '?');
          if ($the_question !== FALSE) {
             $handle = substr($handle, 0, $the_question) . '?' . str_replace('%3D', '=', urlencode(substr($handle, $the_question+1)));
          }

          // Verify that it works as a hdl
          $test_url = "https://hdl.handle.net/" . $handle;
          usleep(20000);
          $headers_test = @get_headers($test_url, 1);
          if ($headers_test === FALSE) return FALSE; // hdl.handle.net is down
          if (empty($headers_test['Location'])) return FALSE; // does not resolve
          quietly('report_modification', "Converting URL to HDL parameter");
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          if (is_array(@$headers_test['Location'])) {
            $the_header_loc = (string) $headers_test['Location'][0];
          } else {
            $the_header_loc = (string) @$headers_test['Location'];
          }
          if (preg_match('~^([^/]+/[^/]+)/.*$~', $handle, $matches)   // Might be padded with stuff
            && stripos($the_header_loc, $handle) === FALSE
            && stripos($the_header_loc, $matches[1]) !== FALSE) {  // Too long ones almost never resolve, but I seen at least one
              $handle = $matches[1]; // @codeCoverageIgnore
          }
          return $this->add_if_new('hdl', $handle);
      } elseif (preg_match("~^https?://zbmath\.org/\?format=complete&q=an:([0-9][0-9][0-9][0-9]\.[0-9][0-9][0-9][0-9][0-9])~i", $url, $match)) {
          quietly('report_modification', "Converting URL to ZBL parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('zbl', $match[1]);
      } elseif (preg_match("~^https?://zbmath\.org/\?format=complete&q=an:([0-9][0-9]\.[0-9][0-9][0-9][0-9]\.[0-9][0-9])~i", $url, $match)) {
          quietly('report_modification', "Converting URL to JFM parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('jfm', $match[1]);
      } elseif (preg_match("~^https?://mathscinet\.ams\.org/mathscinet-getitem\?mr=([0-9]+)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to MR parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('mr', $match[1]);
      } elseif (preg_match("~^https?://papers\.ssrn\.com(?:/sol3/papers\.cfm\?abstract_id=|/abstract=)([0-9]+)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to SSRN parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal'); // Better template choice.  Often journal/paper
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('ssrn', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)osti\.gov/(?:scitech/|)(?:biblio/|)(?:purl/|)([0-9]+)(?:\.pdf|)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to OSTI parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('osti', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)osti\.gov/energycitations/product\.biblio\.jsp\?osti_id=([0-9]+)~i", $url, $match)) {
          quietly('report_modification', "Converting URL to OSTI parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice.  Often journal/paper
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('osti', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)worldcat\.org(?:/title/\S+)?/oclc/([0-9]+)~i", $url, $match)) {
          if (strpos($url, 'edition') && ($this->wikiname() !== 'cite book')) {
            report_warning('Not adding OCLC beccause is appears to be a weblink to a list of editions: ' . $match[1]);
            return FALSE;
          }
          quietly('report_modification', "Converting URL to OCLC parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite book');  // Better template choice
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('oclc', $match[1]);
      } elseif (preg_match("~^https?://(?:www\.|)worldcat\.org/issn/(\d{4})(?:|-)(\d{3}[\dxX])$~i", $url, $match)) {
          quietly('report_modification', "Converting URL to ISSN parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');  // Better template choice
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('issn_force', $match[1] . '-' . $match[2]);
      } elseif (preg_match("~^https?://lccn\.loc\.gov/(\d{4,})$~i", $url, $match)  &&
                (stripos($this->parsed_text(), 'library') === FALSE)) { // Sometimes it is web cite to Library of Congress
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite book');  // Better template choice
          quietly('report_modification', "Converting URL to LCCN parameter");
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('lccn', $match[1]);
      } elseif (preg_match("~^https?://openlibrary\.org/books/OL/?(\d{4,}[WM])(?:|/.*)$~i", $url, $match)) { // We do W "work" and M "edition", but not A, which is author
          quietly('report_modification', "Converting URL to OL parameter");
          if ($this->wikiname() === 'cite web') $this->change_name_to('cite book');  // Better template choice
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return $this->add_if_new('ol', $match[1]);
      } elseif (preg_match("~^https?://search\.proquest\.com/docview/(\d{4,})$~i", $url, $match) && $this->has('title') && $this->blank('id')) {
        if ($this->add_if_new('id', '{{ProQuest|' . $match[1] . '}}')) {  
          quietly('report_modification', 'Converting URL to ProQuest parameter');
          if (is_null($url_sent)) {
             // SEP 2020 $this->forget($url_type);
          }
          return TRUE;
        } else {
          return FALSE; // Append blocked by comment
        }
      } elseif (preg_match("~^https?://web\.archive\.org/web/\d{14}/(https?://.*)$~", $url, $match) && $this->blank(['archiveurl', 'archive-url'])) {
          quietly('report_modification', 'Extracting URL from archive');
          if (is_null($url_sent)) {
             $this->set($url_type, $match[1]);
             $this->add_if_new('archive-url', $match[0]);
             return FALSE; // We really got nothing
          }
      }
    }
    return FALSE ;
  }

  protected function get_doi_from_text() : void {
    $match = ['', '']; // prevent memory leak in some PHP versions
    if ($this->blank('doi') && preg_match('~10\.\d{4}/[^&\s\|\}\{]*~', urldecode($this->parsed_text()), $match)) {
      if (stripos($this->rawtext, 'oxforddnb.com') !== FALSE) return; // generally bad, and not helpful
      if (strpos($this->rawtext, '10.1093') !== FALSE) return; // generally bad, and not helpful
      // Search the entire citation text for anything in a DOI format.
      // This is quite a broad match, so we need to ensure that no baggage has been tagged on to the end of the URL.
      $doi = preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]);
      $doi = extract_doi($doi)[1];
      if ($this->has('quote') && strpos($this->get('quote'), $doi) !== FALSE) return;
      if (doi_active($doi)) $this->add_if_new('doi', $doi);
    }
  }

  public function get_doi_from_crossref() : bool {
    if ($this->has('doi')) {
      return TRUE;
    }
    report_action("Checking CrossRef database for doi. ");
    $page_range = $this->page_range();
    $data = [
      'title'      => de_wikify($this->get('title')),
      'journal'    => de_wikify($this->get('journal')),
      'author'     => $this->first_surname(),
      'year'       => (int) preg_replace("~([12]\d{3}).*~", "$1", $this->year()),
      'volume'     => $this->get('volume'),
      'start_page' => isset($page_range[1]) ? $page_range[1] : NULL,
      'end_page'   => isset($page_range[2]) ? $page_range[2] : NULL,
      'issn'       => $this->get('issn')
    ];

    if ($data['year'] < 1900 || $data['year'] > ((int) date("Y") + 3)) {
      $data['year'] = NULL;
    } else {
      $data['year'] = (string) $data['year'];
    }
    if ((int) $data['end_page'] < (int) $data['start_page']) {
      $data['end_page'] = NULL;
    }
    
    $novel_data = FALSE;
    foreach ($data as $key => $value) if ($value) {
      if ($this->api_has_not_used('crossref', equivalent_parameters($key))) $novel_data = TRUE;
      $this->record_api_usage('crossref', $key);
    }

    if (!$novel_data) {
      report_info("No new data since last CrossRef search.");
      return FALSE;
    } 
    // They already allow some fuzziness in matches
    if ($data['journal'] || $data['issn']) {
      $url = "https://www.crossref.org/openurl/?noredirect=TRUE&pid=" . CROSSREFUSERNAME
           . ($data['title']      ? "&atitle=" . urlencode($data['title'])      : '')
           . ($data['author']     ? "&aulast=" . urlencode($data['author'])     : '')
           . ($data['start_page'] ? "&spage="  . urlencode($data['start_page']) : '')
           . ($data['end_page']   ? "&epage="  . urlencode($data['end_page'])   : '')
           . ($data['year']       ? "&date="   . urlencode($data['year'])       : '')
           . ($data['volume']     ? "&volume=" . urlencode($data['volume'])     : '')
           . ($data['issn']       ? "&issn="   . urlencode($data['issn'])       : "&title=" . urlencode($data['journal']));
      $result = @simplexml_load_file($url);
      if ($result === FALSE) {
        report_warning("Error loading simpleXML file from CrossRef.");  // @codeCoverageIgnore
        return FALSE;                                                   // @codeCoverageIgnore
      }
      if (!isset($result->query_result->body->query)) {
        report_warning("Unexpected simpleXML file from CrossRef.");  // @codeCoverageIgnore
        return FALSE;                                                // @codeCoverageIgnore
      }
      $result = $result->query_result->body->query;
      if ($result['status'] == 'malformed') {
        report_warning("Cannot search CrossRef: " . echoable((string) $result->msg));  // @codeCoverageIgnore
      } elseif ($result["status"] == "resolved") {
        if (!isset($result->doi)) return FALSE;
        report_info(" Successful!");
        return $this->add_if_new('doi', (string) $result->doi);
      }
    }
    return FALSE;
  }
  
  public function get_doi_from_semanticscholar() : bool {
    if ($this->has('doi')) {
      return TRUE;
    }
    if ($this->blank(['s2cid', 'S2CID'])) return FALSE;
    if ($this->has('s2cid') && $this->has('S2CID')) return FALSE;
    report_action("Checking semanticscholar database for doi. ");
    $doi = ConvertS2CID_DOI($this->get('s2cid') . $this->get('S2CID'));
    if ($doi) {
      report_info(" Successful!");
      return $this->add_if_new('doi', $doi);
    }
    return FALSE;
  }

  public function find_pmid() : void {
    if (!$this->blank('pmid')) return;
    report_action("Searching PubMed... ");
    $results = $this->query_pubmed();
    if ($results[1] == 1) {
      // Double check title if we did not use DOI
      if ($this->has('title') && !in_array('doi', $results[2])) {
        $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?tool=WikipediaCitationBot&email=martins+pubmed@gmail.com&db=pubmed&id=" . $results[0];
        usleep(100000); // Wait 1/10 of a second since we just tried
        $xml = @simplexml_load_file($url);
        if ($xml === FALSE) {
          sleep(3);                                     // @codeCoverageIgnore
          $xml = @simplexml_load_file($url);            // @codeCoverageIgnore
        }
        if ($xml === FALSE || !is_object($xml->DocSum->Item)) {
          report_inline("Unable to query pubmed.");     // @codeCoverageIgnore
          return;                                       // @codeCoverageIgnore
        }
        $Items = $xml->DocSum->Item;
        foreach ($Items as $item) {
           if ($item['Name'] == 'Title') {
               $new_title = str_replace(array("[", "]"), "", (string) $item);
               foreach (['chapter', 'title', 'series'] as $possible) {
                 if ($this->has($possible) && titles_are_similar($this->get($possible), $new_title)) {
                   $this->add_if_new('pmid', $results[0]);
                   return;
                 }
               }
               // @codeCoverageIgnoreStart
               report_inline("Similar matching pubmed title not similar enough.  Rejected: " . pubmed_link('pmid', $results[0]));
               return;
               // @codeCoverageIgnoreEnd
           }
        }
      }
      $this->add_if_new('pmid', $results[0]);
    } else {
      report_inline("nothing found.");
    }
  }

  protected function query_pubmed() : array {
/* 
 *
 * Performs a search based on article data, using the DOI preferentially, and failing that, the rest of the article details.
 * Returns an array:
 *   [0] => PMID of first matching result
 *   [1] => total number of results
 *   [2] => what was used to find PMID
 *
 */
    if ($doi = $this->get_without_comments_and_placeholders('doi')) {
      if (!strpos($doi, "[") && !strpos($doi, "<") && doi_works($doi)) { // Doi's with square brackets and less/greater than cannot search PUBMED (yes, we asked).
        $results = $this->do_pumbed_query(array("doi"));
        if ($results[1] !== 0) return $results; // If more than one, we are doomed
      }
    }
    // If we've got this far, the DOI was unproductive or there was no DOI.

    if ($this->has('journal') && $this->has('volume') && $this->page_range()) {
      $results = $this->do_pumbed_query(array("journal", "volume", "issue", "page"));
      if ($results[1] == 1) return $results;
    }
    if ($this->has('title') && $this->first_surname()) {
      $results = $this->do_pumbed_query(array("title", "surname"));
      if ($results[1] == 1) return $results;
      if ($results[1] > 1) {
        $results = $this->do_pumbed_query(array("title", "surname", "year"));
        if ($results[1] == 1) return $results;
        if ($results[1] > 1) {
          $results = $this->do_pumbed_query(array("title", "surname", "year", "volume", "issue"));
          if ($results[1] == 1) return $results;
        }
      }
    }
    $results = [];
    $results[1] = 0;
    return $results;
  }

  protected function do_pumbed_query(array $terms) : array {
  $matches = ['', '']; // prevent memory leak in some PHP versions
  /* do_query
   *
   * Searches pubmed based on terms provided in an array.
   * Provide an array of wikipedia parameters which exist in $p, and this function will construct a Pubmed seach query and
   * return the results as array (first result, # of results)
   */
    $key_index = array(
        'issue' =>  'Issue',
        'journal' =>  'Journal',
        'pmid' =>  'PMID',
        'volume' =>  'Volume'
    );
    $query = '';
    foreach ($terms as $term) {
      $term = mb_strtolower($term);
      if ($term === "title") {
       if ($data = $this->get_without_comments_and_placeholders('title')) {
        $key = 'Title';
        $data = straighten_quotes($data);
        $data = str_replace([';', ',', ':', '.', '?', '!', '&', '/', '(', ')', '[', ']', '{', '}', '"', "'", '|', '\\'],
                            [' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '], $data);
        $data = strip_diacritics($data);
        $data_array = explode(" ", $data);
        foreach ($data_array as $val) {
          if (!in_array(strtolower($val), array('the', 'and', 'a', 'for', 'in', 'on', 's', 're', 't',
                                                'an', 'as', 'at', 'and', 'but', 'how',
                                                'why', 'by', 'when', 'with', 'who', 'where', '')) &&
             (mb_strlen($val) > 3)) {  // Small words are NOT indexed
            $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
          }
        }
       }
      } elseif ($term === "page") {
        if ($pages = $this->page_range()) {
          $val = $pages[1];
          $key = 'Pagination';
          $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
        }
      } elseif ($term === "surname") {
        if ($val = $this->first_surname()) {
          $key = 'Author';
          $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
        }
      } elseif ($term === "year") {
        $key = 'Publication Date';
        if ($val = $this->year()) {
          $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
        }
      } elseif ($term === "doi") {
        $key = 'AID';
        if ($val = $this->get_without_comments_and_placeholders($term)) {
           $query .= " AND (" . "\"" . str_replace(array("%E2%80%93", ';'), array("-", '%3B'), $val) . "\"" . "[$key])"; // PubMed does not like escaped /s in DOIs, but other characters seem problematic.
        }
      } else {
        $key = $key_index[$term]; // Will crash if bad data is passed
        if ($val = $this->get_without_comments_and_placeholders($term)) {
          if (preg_match(REGEXP_PLAIN_WIKILINK, $val, $matches)) {
              $val = $matches[1];    // @codeCoverageIgnore
          } elseif (preg_match(REGEXP_PIPED_WIKILINK, $val, $matches)) {
              $val = $matches[2];    // @codeCoverageIgnore
          }
          $val = strip_diacritics($val);
          $val = straighten_quotes($val);
          $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
        }
      }
    }
    $query = substr($query, 5); // Chop off initial " AND "
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&tool=WikipediaCitationBot&email=martins+pubmed@gmail.com&term=$query";
    usleep(20000); // Wait 1/50 of a second since we probably just tried
    $xml = @simplexml_load_file($url);
    // @codeCoverageIgnoreStart
    if ($xml === FALSE) {
      sleep(3);
      $xml = @simplexml_load_file($url);
    }
    if ($xml === FALSE) {
      report_warning("no results.");
      return array('', 0);
    }
    if (isset($xml->ErrorList)) { // Could look at $xml->ErrorList->PhraseNotFound for list of what was not found
      report_inline('no results.');
      return array('', 0);
    }
    // @codeCoverageIgnoreEnd

    if (isset($xml->IdList->Id[0]) && isset($xml->Count)) {
      return array((string)$xml->IdList->Id[0], (int)(string)$xml->Count, $terms);// first results; number of results
    } else {
      return array('', 0);
    }
  }

  public function expand_by_adsabs() : bool {
    $doi = ['', '']; // prevent memory leak in some PHP versions
    // API docs at https://github.com/adsabs/adsabs-dev-api
    if (!SLOW_MODE && $this->blank('bibcode')) {
     report_info("Skipping AdsAbs API: not in slow mode"); // @codeCoverageIgnore
     return FALSE;                                         // @codeCoverageIgnore
    }
    if ($this->has('bibcode') && !$this->incomplete() && $this->has('doi')) {
      return FALSE; // Don't waste a query
    }
    if (stripos($this->get('bibcode'), 'CITATION') !== false) return FALSE;

    if ($this->api_has_used('adsabs', equivalent_parameters('bibcode'))) {
      report_info("No need to repeat AdsAbs search for " . bibcode_link($this->get('bibcode'))); // @codeCoverageIgnore
      return FALSE;                                                                              // @codeCoverageIgnore
    }
    if ($this->has('bibcode')) $this->record_api_usage('adsabs', 'bibcode');
    if ($this->has('bibcode') && strpos($this->get('bibcode'), 'book') !== FALSE) {
      return $this->expand_book_adsabs();
    }
  
    report_action("Checking AdsAbs database");
    if ($this->has('bibcode')) {
      $result = $this->query_adsabs("identifier:" . urlencode('"' . $this->get('bibcode') . '"'));
    } elseif ($this->has('doi') && preg_match(REGEXP_DOI, $this->get_without_comments_and_placeholders('doi'), $doi)) {
      $result = $this->query_adsabs("identifier:" . urlencode('"' .  $doi[0] . '"'));  // In DOI we trust
    } elseif ($this->has('eprint')) {
      $result = $this->query_adsabs("identifier:" . urlencode('"' . $this->get('eprint') . '"'));
    } elseif ($this->has('arxiv')) {
      $result = $this->query_adsabs("identifier:" . urlencode('"' . $this->get('arxiv')  . '"')); // @codeCoverageIgnore
    } else {
      $result = (object) array("numFound" => 0);
    }
 
    if ($result->numFound > 1) {
      report_warning("Multiple articles match identifiers "); // @codeCoverageIgnore
      return FALSE;                                           // @codeCoverageIgnore
    }
    
    if ($result->numFound == 0) {
      // Avoid blowing through our quota
      if ((!in_array($this->wikiname(), ['cite journal', 'citation', 'cite conference', 'cite book', 'cite arxiv', 'cite article'])) ||
          ($this->wikiname() == 'cite book' && $this->has('isbn')) ||
          ($this->wikiname() == 'citation' && $this->has('isbn') && $this->has('chapter')) ||
          ($this->has('bibcode'))) // Must be GIGO
          {
            report_inline('no record retrieved.');                // @codeCoverageIgnore
            return FALSE;                                         // @codeCoverageIgnore
          }
    }
    
    if (($result->numFound != 1) && $this->has('title')) { // Do assume failure to find arXiv means that it is not there
      $result = $this->query_adsabs("title:" . urlencode('"' .  trim(str_replace('"', ' ', $this->get_without_comments_and_placeholders("title"))) . '"'));
      if ($result->numFound == 0) return FALSE;
      $record = $result->docs[0];
      if (titles_are_dissimilar($this->get_without_comments_and_placeholders("title"), $record->title[0])) {  // Considering we searched for title, this is very paranoid
        report_info("Similar title not found in database.");                // @codeCoverageIgnore
        return FALSE;                                                       // @codeCoverageIgnore
      }
      // If we have a match, but other links exists, and we have nothing journal like, then require exact title match
      if (!$this->blank(array_merge(['doi','pmc','pmid','eprint','arxiv'], ALL_URL_TYPES)) &&
          $this->blank(['issn', 'journal', 'volume', 'issue', 'number']) &&
          mb_strtolower($record->title[0]) !=  mb_strtolower($this->get_without_comments_and_placeholders('title'))) {  // Probably not a journal, trust zotero more
          report_info("Exact title match not found in database.");    // @codeCoverageIgnore
          return FALSE;                                               // @codeCoverageIgnore
      }
    }
    
    if ($result->numFound != 1 && ($this->has('journal') || $this->has('issn'))) {
      $journal = $this->get('journal');
      // try partial search using bibcode components:
      $pages = $this->page_range();
      if (!$pages) return FALSE;
      if ($this->blank('volume') && !$this->year()) return FALSE;
      $result = $this->query_adsabs(
          ($this->has('journal') ? "pub:" . urlencode('"' . remove_brackets($journal) . '"') : "&fq=issn:" . urlencode($this->get('issn')))
        . ($this->year() ? ("&fq=year:" . urlencode($this->year())) : '')
        . ($this->has('volume') ? ("&fq=volume:" . urlencode('"' . $this->get('volume') . '"')) : '')
        . ("&fq=page:" . urlencode('"' . $pages[1] . '"'))
      );
      if ($result->numFound == 0 || !isset($result->docs[0]->pub)) {
        report_inline('no record retrieved.');    // @codeCoverageIgnore
        return FALSE;                             // @codeCoverageIgnore
      }
      $journal_string = explode(",", (string) $result->docs[0]->pub);
      $journal_fuzzyer = "~\([iI]ncorporating.+|\bof\b|\bthe\b|\ba|eedings\b|\W~";
      if (strlen($journal_string[0]) 
      &&  strpos(mb_strtolower(preg_replace($journal_fuzzyer, "", $journal)),
                 mb_strtolower(preg_replace($journal_fuzzyer, "", $journal_string[0]))
                 ) === FALSE
      ) {
        report_info("Partial match but database journal \"" .         // @codeCoverageIgnore
          echoable($journal_string[0]) . "\" didn't match \"" .       // @codeCoverageIgnore
          echoable($journal) . "\".");                                // @codeCoverageIgnore
        return FALSE;                                                 // @codeCoverageIgnore
      }
    }
    if ($result->numFound == 1) {
      $record = $result->docs[0];
      if (isset($record->year) && $this->year()) {
        $diff = abs((int)$record->year - (int)$this->year()); // Check for book reviews (fuzzy >2 for arxiv data)
        $today = (int) date("Y");
        if ($diff > 2)                                    return FALSE;
        if (($record->year < $today - 5)  && $diff > 1)   return FALSE;
        if (($record->year < $today - 10) && $diff !== 0) return FALSE;
        if ($this->has('doi')             && $diff !== 0) return FALSE; 
      }
      
      if (!isset($record->title[0]) || !isset($record->bibcode)) {
        report_info("Database entry not complete");       // @codeCoverageIgnore
        return FALSE;                                     // @codeCoverageIgnore
      }
      if ($this->has('title') && titles_are_dissimilar($this->get('title'), $record->title[0]) 
         && !in_array($this->get('title'), ['Archived copy', "{title}", 'ScienceDirect', "Google Books", "None"])) { // Verify the title matches.  We get some strange mis-matches {
        report_info("Similar title not found in database");       // @codeCoverageIgnore
        return FALSE;                                             // @codeCoverageIgnore
      }
      
      if (isset($record->doi) && $this->get_without_comments_and_placeholders('doi')) {
        if (!str_i_same((string) $record->doi[0], $this->get_without_comments_and_placeholders('doi'))) return FALSE; // New DOI does not match
      }
      
      if (strpos((string) $record->bibcode, 'book') !== FALSE) {  // Found a book.  Need special code
         $old_one = $this->get('bibcode');
         $this->add_if_new('bibcode_nosearch', (string) $record->bibcode);
         if ($this->get('bibcode') === $old_one) return FALSE; // Extra paranoid code to 100% guarantee no infinite loop as code evolves
         return $this->expand_by_adsabs(); // @phan-suppress-current-line PhanPossiblyInfiniteRecursionSameParams
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
        if(isset($record->year) && $this->year() && ((int)$record->year !== (int)$this->year())) $book_count += 1;
        if($this->wikiname() === 'cite book') $book_count += 3;
        if($book_count > 3) {
          report_info("Suspect that BibCode " . bibcode_link((string) $record->bibcode) . " is book review.  Rejecting.");
          return FALSE;
        }
      }
      
      if ($this->blank('bibcode')) {
        $this->add_if_new('bibcode_nosearch', (string) $record->bibcode);
      } elseif ($this->get('bibcode') !== (string) $record->bibcode && stripos($this->get('bibcode'), 'CITATION_BOT_PLACEHOLDER') === FALSE) {
        report_info("Updating " . bibcode_link($this->get('bibcode')) . " to " .  bibcode_link((string) $record->bibcode));
        $this->set('bibcode', (string) $record->bibcode); // The bibcode has been updated
      }
      $this->add_if_new('title', (string) $record->title[0]); // add_if_new will format the title text and check for unknown
      $i = 0;
      if (isset($record->author)) {
       foreach ($record->author as $author) {
        $this->add_if_new('author' . (string) ++$i, $author);
       }
      }
      if (isset($record->pub)) {
        $journal_string = explode(",", (string) $record->pub);
        $journal_start = mb_strtolower($journal_string[0]);
        if (preg_match("~\bthesis\b~ui", $journal_start)) {
          // Do nothing
        } elseif (substr($journal_start, 0, 6) == "eprint") {        // This is outdated format.  Seems to not exists now
          if (substr($journal_start, 0, 13) == "eprint arxiv:") {      //@codeCoverageIgnore
            if (isset($record->arxivclass)) $this->add_if_new('class', (string) $record->arxivclass);  //@codeCoverageIgnore
            $this->add_if_new('arxiv', substr($journal_start, 13));                                     //@codeCoverageIgnore
          }
        } else {
          $this->add_if_new('journal', $journal_string[0]);
        }          
      }
      if (isset($record->page)) {
         $tmp = implode($record->page);
         if ((stripos($tmp, 'arxiv') !== FALSE) || (strpos($tmp, '/') !== FALSE)) {  // Bad data
          unset($record->page);
          unset($record->volume);
          unset($record->issue);
         }
       }
      if (isset($record->volume)) $this->add_if_new('volume', (string) $record->volume);
      if (isset($record->issue))  $this->add_if_new('issue', (string) $record->issue);
      if (isset($record->year))   $this->add_if_new('year', preg_replace("~\D~", "", (string) $record->year));
      if (isset($record->page)) {
        $this->add_if_new('pages', implode('–', $record->page));
      }
      if (isset($record->identifier)) { // Sometimes arXiv is in journal (see above), sometimes here in identifier
        foreach ($record->identifier as $recid) {
          if(strtolower(substr($recid, 0, 6)) === 'arxiv:') {
             if (isset($record->arxivclass)) $this->add_if_new('class', (string) $record->arxivclass);
             $this->add_if_new('arxiv', substr($recid, 6));
          }
        }
      }
      if (isset($record->doi)) {
        $this->add_if_new('doi', (string) $record->doi[0]);
      }
      return TRUE;
    } elseif ($result->numFound == 0) {                         // @codeCoverageIgnore
      report_inline('no record retrieved.');                    // @codeCoverageIgnore
      return FALSE;                                             // @codeCoverageIgnore
    } else {                                                    // @codeCoverageIgnore
      report_inline('multiple records retrieved.  Ignoring.');  // @codeCoverageIgnore
      return FALSE;                                             // @codeCoverageIgnore
    }
  }
  
  protected function expand_book_adsabs() : bool {
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $return = FALSE;
    $result = $this->query_adsabs("bibcode:" . urlencode('"' . $this->get('bibcode') . '"'));
    if ($result->numFound == 1) {
      $return = TRUE;
      $record = $result->docs[0];
      if (isset($record->year)) $this->add_if_new('year', preg_replace("~\D~", "", (string) $record->year));
      if (isset($record->title)) $this->add_if_new('title', (string) $record->title[0]);
      if ($this->blank(array_merge(FIRST_EDITOR_ALIASES, FIRST_AUTHOR_ALIASES, ['publisher']))) { // Avoid re-adding editors as authors, etc.
       $i = 0;
       if (isset($record->author)) {
        foreach ($record->author as $author) {
         $this->add_if_new('author' . (string) ++$i, $author);
        }
       }
      }
    }
    if ($this->blank(['year', 'date']) && preg_match('~^(\d{4}).*book.*$~', $this->get('bibcode'), $matches)) {
      $this->add_if_new('year', $matches[1]); // Fail safe code to grab a year directly from the bibcode itself
    }
    return $return;
  }
  
  // $options should be a series of field names, colons (optionally urlencoded), and
  // URL-ENCODED search strings, separated by (unencoded) ampersands.
  // Surround search terms in (url-encoded) ""s, i.e. doi:"10.1038/bla(bla)bla"
  protected function query_adsabs(string $options) : object {
    $rate_limit = [['', '', ''], ['', '', ''], ['', '', '']]; // prevent memory leak in some PHP versions
    // API docs at https://github.com/adsabs/adsabs-dev-api/blob/master/Search_API.ipynb
    if (AdsAbsControl::gave_up_yet()) return (object) array('numFound' => 0);
    if (!PHP_ADSABSAPIKEY) return (object) array('numFound' => 0);
    
    try {
      $ch = curl_init();
      /** @psalm-suppress RedundantCondition */ /* PSALM thinks TRAVIS cannot be FALSE */
      $adsabs_url = "https://" . (TRAVIS ? 'qa' : 'api')
                  . ".adsabs.harvard.edu/v1/search/query"
                  . "?q=$options&fl=arxiv_class,author,bibcode,doi,doctype,identifier,"
                  . "issue,page,pub,pubdate,title,volume,year";
      curl_setopt_array($ch,
               [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PHP_ADSABSAPIKEY],
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HEADER => TRUE,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
                CURLOPT_URL => $adsabs_url]);
      $return = (string) @curl_exec($ch);
      if (502 === curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
        // @codeCoverageIgnoreStart
        sleep(4);
        $return = (string) @curl_exec($ch);
        if (502 === curl_getinfo($ch, CURLINFO_HTTP_CODE) && TRAVIS) {
           sleep(20); // better slow than not at all
           $return = (string) @curl_exec($ch);
        }
        // @codeCoverageIgnoreEnd
      }
      if ($return == "") {
        // @codeCoverageIgnoreStart
        $exception = curl_error($ch);
        $number = curl_errno($ch);
        curl_close($ch);
        throw new Exception($exception, $number);
        // @codeCoverageIgnoreEnd
      }
      $http_response = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $header_length = (int) @curl_getinfo($ch, CURLINFO_HEADER_SIZE);
      if ($http_response == 0 || $header_length == 0) throw new Exception('Size of zero from website');
      curl_close($ch);
      $header = substr($return, 0, $header_length);
      $body = substr($return, $header_length);
      $decoded = @json_decode($body);
      
      if (is_object($decoded) && isset($decoded->error)) {
        // @codeCoverageIgnoreStart
        if (isset($decoded->error->trace)) {
          throw new Exception(
          "ADSABS website returned a stack trace"
          . "\n - URL was:  " . $adsabs_url,
          (isset($decoded->error->code) ? $decoded->error->code : 999));
        } else {
          throw new Exception(
          ((isset($decoded->error->msg)) ? $decoded->error->msg : $decoded->error)
          . "\n - URL was:  " . $adsabs_url,
          (isset($decoded->error->code) ? $decoded->error->code : 999));
        }
        // @codeCoverageIgnoreStart
      }
      if ($http_response != 200) {
        throw new Exception(strtok($header, "\n"), $http_response); // @codeCoverageIgnore
      }
      
      if (preg_match_all('~\nX\-RateLimit\-(\w+):\s*(\d+)\r~i', $header, $rate_limit)) {
        if ($rate_limit[2][2]) {
          report_info("AdsAbs search " . (string)((int) $rate_limit[2][0] - (int) $rate_limit[2][1]) . "/" . $rate_limit[2][0] .
               ":\n       " . str_replace("&", "\n       ", urldecode($options)));
               // "; reset at " . date('r', $rate_limit[2][2]);
        } else {
          report_warning("AdsAbs daily search limit exceeded. Retry in a while\n");  // @codeCoverageIgnore
          return (object) array('numFound' => 0);                                    // @codeCoverageIgnore
        }
      } else {
        throw new Exception("Headers do not contain rate limit information:\n" . $header, 5000); // @codeCoverageIgnore
      }
      if (!is_object($decoded)) {
        throw new Exception("Could not decode API response:\n" . $body, 5000);   // @codeCoverageIgnore
      } elseif (isset($decoded->response)) {
        $response = $decoded->response;
      } elseif (isset($decoded->error)) {                    // @codeCoverageIgnore
        throw new Exception("" . $decoded->error, 5000);     // @codeCoverageIgnore
      } else {
        throw new Exception("Could not decode AdsAbs response", 5000);        // @codeCoverageIgnore
      }
      return $response;
      // @codeCoverageIgnoreStart
    } catch (Exception $e) {
      if ($e->getCode() == 5000) { // made up code for AdsAbs error
        report_warning(sprintf("API Error in query_adsabs: %s", echoable($e->getMessage())));
      } elseif ($e->getCode() == 60) {
        AdsAbsControl::give_up();
        report_warning('Giving up on AdsAbs for a while.  SSL certificate has expired.');
      } elseif (strpos($e->getMessage(), 'org.apache.solr.search.SyntaxError') !== FALSE) {
        report_info(sprintf("Internal Error %d in query_adsabs: %s",
                      $e->getCode(), echoable($e->getMessage())));
      } elseif (strpos($e->getMessage(), 'HTTP') === 0) {
        report_warning(sprintf("HTTP Error %d in query_adsabs: %s",
                      $e->getCode(), echoable($e->getMessage())));
      } elseif (strpos($e->getMessage(), 'Too many requests') !== FALSE) {
          AdsAbsControl::give_up();
          report_warning('Giving up on AdsAbs for a while.  Too many requests.');
      } else {
        report_warning(sprintf("Error %d in query_adsabs: %s",
                      $e->getCode(), echoable($e->getMessage())));
      }
      return (object) array('numFound' => 0);
    }
    // @codeCoverageIgnoreEnd
  }
  
  public function expand_by_RIS(string &$dat, bool $add_url) : void { // Pass by pointer to wipe this data when called from use_unnamed_params()
    $match = ['', '']; // prevent memory leak in some PHP versions
    $ris_review    = FALSE;
    $ris_issn      = FALSE;
    $ris_publisher = FALSE;
    $ris_book      = FALSE;
    $ris_fullbook  = FALSE;
    $has_T2        = FALSE;
    // Convert &#x__; to characters
    $ris = explode("\n", html_entity_decode($dat, ENT_COMPAT | ENT_HTML401, 'UTF-8'));
    $ris_authors = 0;
    
    if(preg_match('~(?:T[I1]).*-(.*)$~m', $dat,  $match)) {
        if(in_array(strtolower(trim($match[1])), BAD_ACCEPTED_MANUSCRIPT_TITLES)) return ;
    }

    foreach ($ris as $ris_line) {
      $ris_part = explode(" - ", $ris_line . " ");
      if (trim($ris_part[0]) == "TY") {
        if (in_array(trim($ris_part[1]), ['CHAP', 'BOOK', 'EBOOK', 'ECHAP', 'EDBOOK', 'DICT', 'ENCYC', 'GOVDOC'])) {
          $ris_book = TRUE; // See https://en.wikipedia.org/wiki/RIS_(file_format)#Type_of_reference
        }
        if (in_array(trim($ris_part[1]), ['BOOK', 'EBOOK', 'EDBOOK'])) {
          $ris_fullbook = TRUE;
        }
      } elseif (trim($ris_part[0]) == "T2") {
        $has_T2 = TRUE;
      }
    }

    foreach ($ris as $ris_line) {
      $ris_part = explode(" - ", $ris_line . " ");
      $ris_parameter = FALSE;
      switch (trim($ris_part[0])) {
        case "T1":
          if ($ris_fullbook) {
            ; // Sub-title of main title most likely
          } elseif ($ris_book) {
             $ris_parameter = "chapter";
          } else {
             $ris_parameter = "title";
          }
          break;
        case "TI":
          $ris_parameter = "title";
          if ($ris_book && $has_T2) $ris_parameter = "chapter";
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
        case "SP": // Deal with start pages later
          $start_page = trim($ris_part[1]);
          $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
          break;
        case "EP": // Deal with end pages later
          $end_page = trim($ris_part[1]);
          $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
          break;
        case "DO":
          $ris_parameter = doi_active($ris_part[1]) ? "doi" : FALSE;
          break;
        case "JO":
        case "JF":
          $ris_parameter = "journal";
          break;
        case "T2":
        case "BT":
          if ($ris_book) {
             $ris_parameter = "title";
          } else {
             $ris_parameter = "journal";
          }
          break;
        case "VL":
          $ris_parameter = "volume";
          break;
        case "IS":
          $ris_parameter = "issue";
          break;
        case "RI": // Deal with review titles later
          $ris_review = "Reviewed work: " . trim($ris_part[1]);  // Get these from JSTOR
          $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
          break;
        case "SN": // Deal with ISSN later
          $ris_issn = trim($ris_part[1]);
          $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
          break;
        case "UR":
          $ris_parameter = "url";
          break;
        case "PB": // Deal with publisher later
          $ris_publisher = trim($ris_part[1]);  // Get these from JSTOR
          $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
          break;
        case "M3": case "N1": case "N2": case "ER": case "TY": case "KW":
        case "C1": case "DB": case "AB": case "Y2": // The following line is from JSTOR RIS (basically the header and blank lines)
        case "": case "Provider: JSTOR http://www.jstor.org": case "Database: JSTOR": case "Content: text/plain; charset=\"UTF-8\"";
          $dat = trim(str_replace("\n$ris_line", "", "\n$dat")); // Ignore these completely
          break;
        default:
          if (isset($ris_part[1])) {
             report_info("Unexpected RIS data type ignored: " . echoable(trim($ris_part[0])) . " set to " . echoable(trim($ris_part[1])));
          };
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
    if ($ris_issn) {
       if (preg_match("~[\d\-]{9,}[\dXx]~", $ris_issn)) {
          $this->add_if_new('isbn', $ris_issn);
       } elseif (preg_match("~\d{4}\-?\d{3}[\dXx]~", $ris_issn)) {
          if ($this->blank('journal')) $this->add_if_new('issn', $ris_issn);
       }
    }
    if ($ris_publisher) {
      if ($ris_book || $this->blank('journal')) {
        $this->add_if_new('publisher', $ris_publisher);
      }
    }
  }
 
  public function expand_by_pubmed(bool $force = FALSE) : void {
    if (!$force && !$this->incomplete()) return;
    $this_array = [$this];
    if ($pm = $this->get('pmid')) {
      report_action('Checking ' . pubmed_link('pmid', $pm) . ' for more details');
      query_pmid_api(array($pm), $this_array);
    } elseif ($pm = $this->get('pmc')) {
      report_action('Checking ' . pubmed_link('pmc', $pm) . ' for more details');
      query_pmc_api(array($pm), $this_array);
    }
  }

  protected function use_sici() : bool {
    $sici = ['', '']; // prevent memory leak in some PHP versions
    if (preg_match(REGEXP_SICI, urldecode($this->parsed_text()), $sici)) {
      quietly('report_action', "Extracting information from SICI");
      $this->add_if_new('issn', $sici[1]); // Check whether journal is set in add_if_new
      //if ($this->blank('year') && $this->blank('month') && $sici[3]) $this->set('month', date("M", mktime(0, 0, 0, $sici[3], 1, 2005)));
      //if ($this->blank('day') && is("month") && $sici[4]) set ("day", $sici[4]);
      $this->add_if_new('year', (string) (int) $sici[2]);
      $this->add_if_new('volume', (string) (int) $sici[5]);
      if ($sici[6]) $this->add_if_new('issue', (string) (int) $sici[6]);
      $this->add_if_new('pages', (string) (int) $sici[7]);
      return TRUE;
    } else return FALSE;
  }

  public function get_open_access_url() : void {
    if (!$this->blank(DOI_BROKEN_ALIASES)) return;
    $doi = $this->get_without_comments_and_placeholders('doi');
    if (!$doi) return;
    $return = $this->get_unpaywall_url($doi);
    $this->get_semanticscholar_url($doi, $return);
  }

  public function get_semanticscholar_url(string $doi, string $unpay) : void { // $unpay is unused right now
   if(      $this->has('pmc') ||
            ($this->has('doi') && $this->get('doi-access') === 'free') ||
            ($this->has('jstor') && $this->get('jstor-access') === 'free')
           ) return; // do not add url if have OA already.  Do indlude preprints in list
    if ($this->has('s2cid') || $this->has('S2CID')) return;
    if (PHP_S2APIKEY) {
      $context = stream_context_create(array('http'=>array('header'=>"x-api-key: " . PHP_S2APIKEY . "\r\n")));
      $json = (string) @file_get_contents('https://partner.semanticscholar.org/v1/paper/' . $doi, FALSE, $context);
    } else {
      $json = (string) @file_get_contents('https://api.semanticscholar.org/v1/paper/' . $doi); // @codeCoverageIgnore
    }
    if ($json) {
      $oa = @json_decode($json);
      if ($oa !== FALSE && isset($oa->url) && isset($oa->is_publisher_licensed) && $oa->is_publisher_licensed) {
        $this->get_identifiers_from_url($oa->url);
      }
    }
  }

  public function get_unpaywall_url(string $doi) : string {
    $match = ['', '']; // prevent memory leak in some PHP versions
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $url = "https://api.unpaywall.org/v2/$doi?email=" . CROSSREFUSERNAME;
    $ch = curl_init();
    curl_setopt_array($ch,
            [CURLOPT_HEADER => 0,
             CURLOPT_RETURNTRANSFER => 1,
             CURLOPT_URL => $url,
             CURLOPT_TIMEOUT => 10,
             CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org']);
    $json = (string) @curl_exec($ch);
    curl_close($ch);
    if ($json) {
      $oa = @json_decode($json);
      if ($oa !== FALSE && isset($oa->best_oa_location)) {
        $best_location = $oa->best_oa_location;
        if ($best_location->host_type == 'publisher') {
          // The best location is already linked to by the doi link
          return 'publisher';
        }
        if (!isset($best_location->evidence)) return 'nothing';
        // This bug report is now closed (https://github.com/Impactstory/oadoi/issues/121)
        // if (@$best_location->evidence == 'oa repository (via OAI-PMH title and first author match)' ) {
        //   report_warning("Ignored a low-quality OA match on a repository via OAI-PMH for DOI: " . echoable($doi)); // @codeCoverageIgnore
        //   return 'unreliable';                                                                                     // @codeCoverageIgnore
        // }
        if (isset($oa->journal_name) && $oa->journal_name == "Cochrane Database of Systematic Reviews" ) {
          report_warning("Ignored a OA from Cochrane Database of Systematic Reviews for DOI: " . echoable($doi)); // @codeCoverageIgnore
          return 'unreliable';                                                                                    // @codeCoverageIgnore
        }
        if (isset($best_location->url_for_landing_page)) {
          $oa_url = (string) $best_location->url_for_landing_page;  // Prefer to PDF
        } elseif (isset($best_location->url)) {   // @codeCoverageIgnore
          $oa_url = (string) $best_location->url; // @codeCoverageIgnore
        } else {                                  // @codeCoverageIgnore
          return 'nothing';                       // @codeCoverageIgnore
        }
        if (!$oa_url) return 'nothing';

        if (stripos($oa_url, 'semanticscholar.org') !== FALSE) return 'semanticscholar';  // Limit semanticscholar to licenced only - use API call instead
        if (stripos($oa_url, 'citeseerx') !== FALSE) return 'citeseerx'; //is currently blacklisted due to copyright concerns
        if ($this->get('url')) {
            if ($this->get('url') !== $oa_url) $this->get_identifiers_from_url($oa_url);  // Maybe we can get a new link type
            return 'have url';
        }
        preg_match("~^https?://([^\/]+)/~", $oa_url, $match);
        $host_name = @$match[1];
        if (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $host_name) !== $host_name) return 'publisher'; // Its the publisher
        if (stripos($oa_url, 'bioone.org/doi') !== FALSE) return 'publisher';
        if (stripos($oa_url, 'gateway.isiknowledge.com') !== FALSE) return 'nothing';
        if (stripos($oa_url, 'biodiversitylibrary') !== FALSE) return 'publisher';
        if (stripos($oa_url, 'orbit.dtu.dk/en/publications') !== FALSE) return 'nothing'; // Abstract only
        // Check if free location is already linked
        if(($this->has('pmc') &&
             preg_match("~^https?://europepmc\.org/articles/pmc\d"
                      . "|^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=\d"
                      . "|^https?://www\.ncbi\.nlm\.nih\.gov/(?:m/)?pmc/articles/PMC\d~", $oa_url))
         ||($this->has('arxiv') &&
            preg_match("~arxiv\.org/~", $oa_url))
         ||($this->has('eprint') &&
            preg_match("~arxiv\.org/~", $oa_url))
         ||($this->has('citeseerx') &&
            preg_match("~citeseerx\.ist\.psu\.edu~", $oa_url))) {
           return 'have free';
        }
        // @codeCoverageIgnoreStart
        // These are not generally full-text.  Will probably never see
        if(($this->has('bibcode') &&
            preg_match(REGEXP_BIBCODE, urldecode($oa_url)))
         ||($this->has('pmid') &&
            preg_match("~^https?://www.ncbi.nlm.nih.gov/.*pubmed/~", $oa_url))) {
           return 'probably not free';
        }
        // This should be found above when listed as location=publisher
        if($this->has('doi') &&
            preg_match("~^https?://doi\.library\.ubc\.ca/|^https?://(?:dx\.|)doi\.org/~", $oa_url)) {
            return 'publisher';
        }
        // @codeCoverageIgnoreEnd
        if (preg_match('~^https?://hdl\.handle\.net/(\d{2,}.*/.+)$~', $oa_url, $matches)) {  // Normalize Handle URLs
            $oa_url = 'https://hdl.handle.net/handle/' . $matches[1];
        }
        if ($this->has('hdl') ) {
          if (stripos($oa_url, $this->get('hdl')) !== FALSE) return 'have free';
          foreach (HANDLES_HOSTS as $hosts) {
            if (preg_match('~^https?://' . str_replace('.', '\.', $hosts) . '(/.+)$~', $oa_url, $matches)) {
              $handle1 = $matches[1];
              foreach (HANDLES_PATHS as $handle_path) {
                if (preg_match('~^' . $handle_path . '(.+)$~', $handle1)) return 'have free';
              }
            }
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
           ) {
          return 'have free'; // do not add url if have OA already
        }
        // Double check URL against existing data
        if (!preg_match('~^(?:https?|ftp):\/\/\/?([^\/\.]+\.[^\/]+)\/~i', $oa_url, $matches)) {
           report_minor_error(' OA database gave invalid URL: ' . echoable($oa_url)); // @codeCoverageIgnore
           return 'nothing';                                                // @codeCoverageIgnore
        }
        $oa_hostname = $matches[1];
        if (($this->has('osti') && stripos($oa_hostname, 'osti.gov') !== FALSE) ||
            ($this->has('ssrn') && stripos($oa_hostname, 'ssrn.com') !== FALSE) ||
            ($this->has('jstor') && stripos($oa_hostname, 'jstor.org') !== FALSE) ||
            ($this->has('pmid') && stripos($oa_hostname, 'nlm.nih.gov') !== FALSE) ||
            ($this->has('pmc') && stripos($oa_hostname, 'nlm.nih.gov') !== FALSE) ||
            ($this->has('arxiv') && stripos($oa_hostname, 'arxiv.org') !== FALSE) ||
            ($this->has('eprint') && stripos($oa_hostname, 'arxiv.org') !== FALSE) ||
            (stripos($oa_hostname, 'doi.org') !== FALSE)) {
          return 'have free';
       }
       preg_match("~^https?://([^\/]+)/~", $oa_url . '/', $match);
       $new_host_name = str_replace('www.', '', strtolower((string) @$match[1]));
       foreach (ALL_URL_TYPES as $old_url) {
            if (preg_match("~^https?://([^\/]+)/~", (string) $this->get($old_url), $match)) {
                $old_host_name = str_replace('www.', '', strtolower($match[1]));
                if ($old_host_name === $new_host_name) return 'have free';
            }
       }
        $has_url_already = $this->has('url');
        $this->add_if_new('url', $oa_url);  // Will check for PMCs etc hidden in URL
        if ($this->has('url') && !$has_url_already) {  // The above line might have eaten the URL and upgraded it
          $headers_test = @get_headers($this->get('url'), 1);
          // @codeCoverageIgnoreStart
          if($headers_test ===FALSE) {
            $this->forget('url');
            report_warning("Open access URL was was unreachable from Unpaywall API for doi: " . echoable($doi));
            return 'nothing';
          }
          // @codeCoverageIgnoreEnd
          $response_code = intval(substr($headers_test[0], 9, 3));
          // @codeCoverageIgnoreStart
          if($response_code > 400) {  // Generally 400 and below are okay, includes redirects too though
            $this->forget('url');
            report_warning("Open access URL gave response code " . (string) $response_code . " from oiDOI API for doi: " . echoable($doi));
            return 'nothing';
          }
          // @codeCoverageIgnoreEnd
        }
        return 'got one';
      }
    }
    report_warning("Could not retrieve open access details from Unpaywall API for doi: " . echoable($doi));
    return 'nothing';
  }
  
  public function clean_google_books() : void {
    $matches = ['', '', '']; // prevent memory leak in some PHP versions
    foreach (ALL_URL_TYPES as $url_type) {
       $this->expand_by_google_books_inner($url_type, FALSE);
       if ($this->has($url_type) && preg_match('~^https?://books\.google\.([^/]+)/books\?((?:isbn|vid)=.+)$~', $this->get($url_type), $matches)) {
         if ($matches[1] !== 'com') {
           $this->set($url_type, 'https://books.google.com/books?' . $matches[2]);
         }
       }
    }
  }
  
  public function expand_by_google_books() : bool {
    // TODO - this is wasteful to normalize twice
    $this->clean_google_books();
    if ($this->has('doi') && doi_active($this->get('doi'))) return FALSE;
    foreach (['url', 'chapterurl', 'chapter-url'] as $url_type) {
       if ($this->expand_by_google_books_inner($url_type, TRUE)) return TRUE;
    }
    return $this->expand_by_google_books_inner('', TRUE);
  }
  
  protected function expand_by_google_books_inner(string $url_type, bool $use_it) : bool {
    $gid = ['', '']; // prevent memory leak in some PHP versions
    $google_results = ['', '']; // prevent memory leak in some PHP versions
    $matcher = ['', '']; // prevent memory leak in some PHP versions
    $matches = ['', '']; // prevent memory leak in some PHP versions
    if ($url_type) {
      $url = $this->get($url_type);
      if (!$url) return FALSE;
      if (!preg_match("~[Bb]ooks\.[Gg]oogle\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid) &&
          !preg_match("~\.[Gg]oogle\.com/books/edition/_/([a-zA-Z0-9]+)(?:\?.+|)$~", $url, $gid)) {
         return FALSE;  // Got nothing usable
      }
    } else {
      $url = '';
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
        $google_book_url = 'https://www.google.com/search?tbo=p&tbm=bks&q=isbn:' . $isbn;
        $ch = curl_init();
        curl_setopt_array($ch,
                   [CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
                    CURLOPT_HEADER => 0,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_URL => $google_book_url]);
        $google_content = (string) @curl_exec($ch);
        curl_close($ch);
        if ($google_content && preg_match_all('~[Bb]ooks\.[Gg]oogle\.com/books\?id=(............)&amp~', $google_content, $google_results)) {
          $google_results = $google_results[1];
          $google_results = array_unique($google_results);
          if (count($google_results) === 1) {
            $gid = $google_results[0];
            $url = 'https://books.google.com/books?id=' . $gid;
            $google_books_worked = TRUE;
          }
        }
      }
      if ( !$google_books_worked && PHP_GOOGLEKEY) { // Try Google API instead 
        if ($isbn) {
          $url_token = "isbn:" . $isbn;
        } elseif ($oclc) {
          $url_token = "oclc:" . $oclc;
        } elseif ($lccn) {
          $url_token = "lccn:" . $lccn;
        } else {
          return FALSE;
        }
        $ch = curl_init();
        curl_setopt_array($ch,
               [CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
                CURLOPT_URL => "https://www.googleapis.com/books/v1/volumes?q=" . $url_token . "&key=" . PHP_GOOGLEKEY]);
        $string = (string) @curl_exec($ch);
        curl_close($ch);
        if ($string == '') {
            report_warning("Did not receive results from Google API search" . echoable($url_token));  // @codeCoverageIgnore
            return FALSE;                                                                 // @codeCoverageIgnore
        }
        $result = @json_decode($string, FALSE);
        if (isset($result)) {
          if (isset($result->totalItems)) {
            if ($result->totalItems === 1 && isset($result->items) && isset($result->items[0]) && isset($result->items[0]->id) ) {
              $gid = (string) $result->items[0]->id;
              $url = 'https://books.google.com/books?id=' . $gid;
            } else {
              report_info("No results for Google API search " . echoable($url_token));
            }
            // @codeCoverageIgnoreStart
          } elseif (isset($result->error->errors[0]->reason) && $result->error->errors[0]->reason === 'rateLimitExceeded') {
            report_warning("Google Books API reported error out of queries for the day");
          } elseif (isset($result->error)) {
            report_warning("Google Books API reported error: " . echoable(print_r($result->error->errors, TRUE)));
          } else {
            report_warning("Could not parse Google API results for " . echoable($url_token));
            return FALSE;
          }
            // @codeCoverageIgnoreEnd
        }
      }
    }
    // Now we parse a Google Books URL
    if ($url && preg_match("~[Bb]ooks\.[Gg]oogle\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid)) {
      $orig_book_url = $url;
      $removed_redundant = 0;
      $hash = '';
      $removed_parts ='';
      
      if (strpos($url, "#")) {
        $url_parts = explode("#", $url);
        $url = $url_parts[0];
        $hash = $url_parts[1];
      }
      $url_parts = explode("&", str_replace("?", "&", $url));
      $url = "https://books.google.com/books?id=" . $gid[1];
      $book_array = array();
      foreach ($url_parts as $part) {
        $part_start = explode("=", $part);
        if ($part_start[0] === 'text')     $part_start[0] = 'dq';
        if ($part_start[0] === 'keywords') $part_start[0] = 'q';
        if ($part_start[0] === 'page')     $part_start[0] = 'pg';
        switch ($part_start[0]) {
          case "dq": case "pg": case "lpg": case "q": case "printsec": case "cd": case "vq": case "jtp": case "sitesec":
            if (!isset($part_start[1]) || $part_start[1] == '') {
                $removed_redundant++;
                $removed_parts .= $part;
            } else {
                $book_array[$part_start[0]] = $part_start[1];
            }
            break;
          case "id":
            break; // Don't "remove redundant"
          case "as": case "useragent": case "as_brr": case "hl":
          case "ei": case "ots": case "sig": case "source": case "lr": case "ved":
          case "gs_lcp": case "sxsrf": case "gfe_rd": case "gws_rd":
          case "sa": case "oi": case "ct": case "client": case "redir_esc":
          case "callback": case "jscmd": case "bibkeys":
          case "buy": case "edge": case "zoom": case "img": // List of parameters known to be safe to remove
          default:
            if ($removed_redundant !== 0) $removed_parts .= $part; // http://blah-blah is first parameter and it is not actually dropped
            $removed_redundant++;
        }
      }
      // Clean up hash first
      $hash = '&' . trim($hash) . '&';
      $hash = str_replace(['&f=false', '&f=true', 'v=onepage'], ['','',''], $hash); // onepage is default
      $hash = str_replace(['&q&', '&q=&', '&&&&', '&&&', '&&'], ['&', '&', '&', '&', '&'], $hash);
      if (preg_match('~(&q=[^&]+)&~', $hash, $matcher)) {
          $hash = str_replace($matcher[1], '', $hash);
          if (isset($book_array['q'])) $removed_parts .= '&q=' . $book_array['q'];
          $book_array['q'] = urlencode(urldecode(substr($matcher[1], 3)));           // #q= wins over &q= before # sign
      }
      if (isset($book_array['vq']) && !isset($book_array['q']) && !isset($book_array['dq'])) { // VQ loses to Q and VQ
          $book_array['q'] = $book_array['vq'];
          unset($book_array['vq']);
      }
      if (isset($book_array['q']) && isset($book_array['dq'])) { // Q wins over DQ
          $removed_redundant++;
          $removed_parts .= '&dq=' . $book_array['dq'];
          unset($book_array['dq']);
      } elseif (isset($book_array['dq'])) {      // Prefer Q parameters to DQ
          $book_array['q'] = $book_array['dq'];
          unset($book_array['dq']);
      }
      if (isset($book_array['pg']) && isset($book_array['lpg'])) { // PG wins over LPG
          $removed_redundant++;
          $removed_parts .= '&lpg=' . $book_array['lpg'];
          unset($book_array['lpg']);
      }
      if (preg_match('~^&(.*)$~', $hash, $matcher) ){
          $hash = $matcher[1];
      }
      if (preg_match('~^(.*)&$~', $hash, $matcher) ){
          $hash = $matcher[1];
      }
      if (isset($book_array['q'])){
        if (stripos($book_array['q'], 'isbn') === 0 ||
            stripos($book_array['q'], 'subject:') === 0 ||
            stripos($book_array['q'], 'inauthor:') === 0 ||
            stripos($book_array['q'], 'inpublisher:') === 0) {
          unset($book_array['q']);
        }
      }
      if (isset($book_array['sitesec'])) { // Overrides all other setting
        if (strtolower($book_array['sitesec']) === 'reviews') {
          $url .= '&sitesec=reviews';
          unset($book_array['q']);
          unset($book_array['pg']);
          unset($book_array['lpg']);
        }
      }
      if (isset($book_array['q'])){
          $url .= '&q=' . $book_array['q'];
      }
      if (isset($book_array['pg'])){
          $url .= '&pg=' . $book_array['pg'];
      }
      if (isset($book_array['lpg'])){
          $url .= '&lpg=' . $book_array['lpg'];
      }
      if ($hash) {
         $hash = "#" . $hash;
         $removed_parts .= $hash;
         $removed_redundant++;
      }     // CLEANED UP, so do not add $url = $url . $hash;
      if (preg_match('~^(https://books\.google\.com/books\?id=[^#^&]+)(?:&printsec=frontcover|)(?:#v=onepage|v=snippet|)$~', $url, $matches)) {
         $url = $matches[1]; // URL Just wants the landing page
      }
      if ($url != $orig_book_url && $url_type && (strpos($url_type, 'url') !== FALSE)) {
        if ($removed_redundant > 1) { // http:// is counted as 1 parameter
          report_forget(echoable($removed_parts));
        } else {
          report_forget('Standardized Google Books URL');
        }
        $this->set($url_type, $url);
      }
      if ($use_it) $this->google_book_details($gid[1]);
      return TRUE;
    }
    if (preg_match("~^(.+\.google\.com/books/edition/_/)([a-zA-Z0-9]+)(\?.+|)$~", (string) $url, $gid)) {
      if ($url_type && $gid[3] === '?hl=en') {
        report_forget('Anonymized/Standardized/Denationalized Google Books URL');
        $this->set($url_type, $gid[1] . $gid[2]);
      }
      if ($use_it) $this->google_book_details($gid[2]);
      return TRUE;
    }
    return FALSE;
  }

  protected function google_book_details(string $gid) : bool {
    $match = ['', '']; // prevent memory leak in some PHP versions
    $google_book_url = "https://books.google.com/books/feeds/volumes/" . $gid;
    $ch = curl_init();
    curl_setopt_array($ch,
           [CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_URL => $google_book_url]);
    $data = (string) @curl_exec($ch);
    curl_close($ch);
    if ($data == '') return FALSE;
    $simplified_xml = str_replace('http___//www.w3.org/2005/Atom', 'http://www.w3.org/2005/Atom',
      str_replace(":", "___", $data));
    $xml = @simplexml_load_string($simplified_xml);
    if ($xml === FALSE) return FALSE;
    if ($xml->dc___title[1]) {
      $this->add_if_new('title',  
               wikify_external_text(str_replace("___", ":", $xml->dc___title[0] . ": " . $xml->dc___title[1])));
    } else {
      $this->add_if_new('title',  wikify_external_text(str_replace("___", ":", (string) $xml->title)));
    }
    // Possibly contains dud information on occasion
    // $this->add_if_new('publisher', str_replace("___", ":", $xml->dc___publisher));
    $isbn = '';
    foreach ($xml->dc___identifier as $ident) {
      if (preg_match("~isbn.*?([\d\-]{9}[\d\-]+)~i", (string) $ident, $match)) {
        $isbn = $match[1];
      }
    }
    $this->add_if_new('isbn', $isbn);
    
    $i = 0;
    if ($this->blank(array_merge(FIRST_EDITOR_ALIASES, FIRST_AUTHOR_ALIASES, ['publisher']))) { // Too many errors in gBook database to add to existing data.   Only add if blank.
      foreach ($xml->dc___creator as $author) {
        $this->validate_and_add('author' . (string) ++$i, str_replace("___", ":", (string) $author), '', '', TRUE);
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
    $google_date = tidy_date($google_date);
    $this->add_if_new('date', $google_date);
    // Don't set 'pages' parameter, as this refers to the CITED pages, not the page count of the book.
    return TRUE;
  }

  ### parameter processing
  protected function parameter_names_to_lowercase() : void {
    if (empty($this->param)) return;
    $keys = array_keys($this->param);
    for ($i = 0; $i < count($keys); $i++) {
      if (!ctype_lower($this->param[$keys[$i]]->param)) {
        $this->param[$keys[$i]]->param = strtolower($this->param[$keys[$i]]->param);
      }
    }
  }

  protected function use_unnamed_params() : void {
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $match = ['', '']; // prevent memory leak in some PHP versions
    if (empty($this->param)) return;
    
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
        array_unshift($duplicate_identical, (mb_strtolower(trim((string) $par->val)) === mb_strtolower(trim((string) $this->param[$duplicate_pos]->val)))); // Drop duplicates that differ only by case
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
    
    if ($this->blank('url')) {
      $need_one = TRUE;
      foreach ($this->param as $param_key => $p) {
        if ($need_one && !empty($p->param)) {
          if (preg_match('~^\s*(https?://|www\.)\S+~', $p->param)) { # URL ending ~ xxx.com/?para=val
            $val = isset($p->val) ? (string) $p->val : '';
            $param = (string) $p->param;
            $this->param[$param_key]->val =  $param . '=' . $val;
            $this->param[$param_key]->param = 'url';
            $this->param[$param_key]->eq = ' = '; // Upgrade it to nicely spread out
            $need_one = FALSE;
            if (stripos($param . $val, 'books.google.') !== FALSE) {
              $this->change_name_to('cite book');
            }
          }
        }
      }
    }
    $blank_count = 0;
    foreach ($this->param as &$p) { // Protect them from being overwritten
      if (empty($p->param)) {
        $p->param = 'CITATION_BOT_PLACEHOLDER_EMPTY_' . (string) $blank_count++;
        $p->eq = ' = ';
      }
    }
    unset ($p); // Destroy pointer to be safe
    foreach ($this->param as &$p) {
      if (stripos($p->param, 'CITATION_BOT_PLACEHOLDER_EMPTY') === FALSE) continue;
      $dat = $p->val;
      $endnote_test = explode("\n%", "\n" . $dat);
      if (isset($endnote_test[1])) {
        $endnote_authors = 0;
        foreach ($endnote_test as $endnote_line) {
          $endnote_linetype = substr($endnote_line, 0, 1);
          $endnote_datum = trim((string) substr($endnote_line, 2)); // cut line type and leading space.  Cast to string in case of FALSE
          switch ($endnote_linetype) {
            case "A": 
              $this->add_if_new('author' . (string) ++$endnote_authors, format_author($endnote_datum));
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
              if (preg_match("~@\s*([\d\-]{9,}[\dxX])~", $endnote_line, $matches)) {
                $endnote_datum = $matches[1];
                $endnote_parameter = "isbn";
              } elseif (preg_match("~@\s*(\d{4}\-?\d{3}[\dxX])~", $endnote_line, $matches)) {
                $endnote_datum = $matches[1];
                $endnote_parameter = "issn";
              } else {
                $endnote_parameter = FALSE;
              }
              break;
            case "R": // Resource identifier... *may* be DOI but probably isn't always.
              if ($matches = extract_doi($endnote_datum)[1]) {
                $endnote_datum = $matches;
                $endnote_parameter = 'doi';
              } else {
                $endnote_parameter = FALSE;
              }
              break;
            case "8": // Date
            case "0": // Citation type
            case "X": // Abstract
            case "M": // Object identifier
              $dat = trim(str_replace("\n%$endnote_line", "", "\n" . $dat));
              $endnote_parameter = FALSE;
              break;
            default:
              $endnote_parameter = FALSE;
          }
          if ($endnote_parameter) {
            $this->add_if_new($endnote_parameter, $endnote_datum);
            $dat = trim(str_replace("\n%$endnote_line", "", "\n$dat"));
          }
        }
      }

      if (preg_match("~^TY\s+-\s+[A-Z]+~", $dat)) { // RIS formatted data:
        $this->expand_by_RIS($dat, TRUE);
      }
      
      $doi = extract_doi($dat);
      if ($doi[1] != FALSE) {
        $this->add_if_new('doi', $doi[1]);
        $this->change_name_to('cite journal');
        $dat = str_replace($doi[0], '', $dat);
      }
      
      if (preg_match('~^(https?://|www\.)\S+~', $dat, $match)) { # Takes priority over more tentative matches
        report_add("Found URL floating in template; setting url");
        $this->add_if_new('url', $match[0]);
        $dat = str_replace($match[0], '', $dat);
      }
      
      if (preg_match_all("~(\w+)\.?[:\-\s]*([^\s;:,.]+)[;.,]*~", $dat, $match)) { #vol/page abbrev.
        foreach ($match[0] as $i => $oMatch) {
          switch (strtolower($match[1][$i])) {
            case "vol": case "v": case 'volume':
              $matched_parameter = "volume";
              break;
            case "no": case "number": case 'issue': case 'n': case 'issues':
              $matched_parameter = "issue";
              break;
            case 'pages': case 'pp': case 'pg': case 'pgs': case 'pag':
              $matched_parameter = "pages";
              break;
            case 'p':
              $matched_parameter = "page";
              break;
            default:
              $matched_parameter = FALSE;
          }
          if ($matched_parameter) {
            $dat = trim(str_replace($oMatch, "", $dat));
            $this->add_if_new($matched_parameter, $match[2][$i]);
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
      $test_dat = '';
      $shortish = -1;
      $comp = '';
      $closest = '';
      
      foreach ($parameter_list as $parameter) {
        if (($parameter === strtolower($parameter)) && preg_match('~^(' . preg_quote($parameter) . '[ \-:]\s*)~iu', $dat, $match)) { // Avoid adding "URL" instead of "url"
          $parameter_value = trim(mb_substr($dat, mb_strlen($match[1])));
          report_add("Found " . echoable($parameter) . " floating around in template; converted to parameter");
          $this->add_if_new($parameter, $parameter_value);
          $numSpaces = preg_match_all('~[\s]+~', $parameter_value);
          if ($numSpaces < 4) {
            $dat = '';
            $p->val = '';
            break;
          }
        }
        $para_len = strlen($parameter);
        if ($para_len < 3) continue; // minimum length to avoid FALSE positives
        $test_dat = preg_replace("~\d~", "_$0",
                    preg_replace("~[ -+].*$~", "", substr(mb_strtolower($dat), 0, $para_len)));
        if (preg_match("~\d~", $parameter)) {
          $lev = (float) levenshtein($test_dat, preg_replace("~\d~", "_$0", $parameter));
        } else {
          $lev = (float) levenshtein($test_dat, $parameter);
        }
        if ($lev == 0) {
          $closest = $parameter;
          $shortest = 0.0;
          break;
        } else {
          $closest = '';
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
         && strlen($test_dat) > 0
         && ((float) similar_text($closest, $test_dat) / (float) strlen($test_dat)) > 0.4
         && ((float) $shortest + 1.0 < $shortish  // No close competitor
             || strlen($closest) > strlen($comp)
            )
      ) {
        // remove leading spaces or hyphens (which may have been typoed for an equals)
        if (preg_match("~^[ -+]*(.+)~", (string) substr($dat, strlen($closest)), $match)) { // Cast to string, in case false is given
          $this->add_if_new($closest, $match[1]/* . " [$shortest / $comp = $shortish]"*/);
          $dat = trim(preg_replace('~^.*' . preg_quote($match[1]) . '~', '', $dat));
        }
      } elseif (preg_match("~(?<!\d)(\d{10})(?!\d)~", str_replace(Array(" ", "-"), "", $dat), $match)) {
        $the_isbn = str_split($match[1]);
        preg_match(              '~' . $the_isbn[0] . '[ -]?' . $the_isbn[1] . '[ -]?'
                                     . $the_isbn[2] . '[ -]?' . $the_isbn[3] . '[ -]?'
                                     . $the_isbn[4] . '[ -]?' . $the_isbn[5] . '[ -]?'
                                     . $the_isbn[6] . '[ -]?' . $the_isbn[7] . '[ -]?'
                                     . $the_isbn[8] . '[ -]?' . $the_isbn[9] .
                                 '~', $dat, $match); // Crazy to deal with dashes and spaces
        $this->add_if_new('isbn', $match[0]);
        $dat = trim(str_replace($match[0], '', $dat));
      } elseif (preg_match("~(?<!\d)(\d{13})(?!\d)~", str_replace(Array(" ", "-"), "", $dat), $match)) {
        $the_isbn = str_split($match[1]);
        preg_match(              '~' . $the_isbn[0] . '[ -]?' . $the_isbn[1] . '[ -]?'
                                     . $the_isbn[2] . '[ -]?' . $the_isbn[3] . '[ -]?'
                                     . $the_isbn[4] . '[ -]?' . $the_isbn[5] . '[ -]?'
                                     . $the_isbn[6] . '[ -]?' . $the_isbn[7] . '[ -]?'
                                     . $the_isbn[8] . '[ -]?' . $the_isbn[9] . '[ -]?'
                                     . $the_isbn[10]. '[ -]?' . $the_isbn[11]. '[ -]?'
                                     . $the_isbn[12].
                                 '~', $dat, $match); // Crazy to deal with dashes and spaces
        $this->add_if_new('isbn', $match[0]);
        $dat = trim(str_replace($match[0], '', $dat));
      }
      if (preg_match("~^access date[ :]+(.+)$~i", $dat, $match)) {
        if ($this->add_if_new('accessdate', $match[1])) {
          $dat = trim(str_replace($match[0], '', $dat));
        }
      }
      if (preg_match("~\(?(1[89]\d\d|20\d\d)[.,;\)]*~", $dat, $match)) { #YYYY
        if ($this->blank(['year', 'date'])) {
          $this->add_if_new('year', $match[1]);
          $dat = trim(str_replace($match[0], '', $dat));
        }
      }
      $p->val = trim($dat, " \t\0\x0B");
    }
    unset ($p); // Destroy pointer to be safe
    foreach ($this->param as $param_key => &$p) {
      if (stripos($p->param, 'CITATION_BOT_PLACEHOLDER_EMPTY') === FALSE) continue;
      $p->param = '';
      $p->eq = '';
      if($p->val == '') unset($this->param[$param_key]);
    }
    unset ($p); // Destroy pointer to be safe
  }

  protected function id_to_param(): void {
    $match = ['', '']; // prevent memory leak in some PHP versions
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $id = $this->get('id');
    if (trim($id)) {
      report_action("Trying to convert ID parameter to parameterized identifiers.");
    } else {
      return;
    }
    while (preg_match("~\b(PMID|DOI|ISBN|ISSN|ARXIV|LCCN)[\s:]*(\d[\d\s\-]*+[^\s\}\{\|,;]*)(?:[,;] )?~iu", $id, $match)) {
      $this->add_if_new(strtolower($match[1]), $match[2]);
      $id = str_replace($match[0], '', $id);
    }
    if (preg_match_all('~' . sprintf(Self::PLACEHOLDER_TEXT, '(\d+)') . '~', $id, $matches)) {
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
            if ($subtemplate_name == 'asin' && $subtemplate->has('country')) {
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
              report_info(echoable($subtemplate->parsed_text()));
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
    if (trim($id)) {
      $this->set('id', $id);
    } else {
      $this->forget('id');
    }
  }

  public function correct_param_mistakes() : void {
  // It will correct any that appear to be mistyped in minor templates
  if (empty($this->param)) return ;
  $mistake_corrections = array_values(COMMON_MISTAKES);
  $mistake_keys = array_keys(COMMON_MISTAKES);

  foreach ($this->param as $p) {
    if (strlen($p->param) > 0) {
      $mistake_id = array_search($p->param, $mistake_keys);
      if ($mistake_id) {
        $new = $mistake_corrections[$mistake_id];
        if ($this->blank($new)) {
           $p->param = $new;
           report_modification('replaced with ' . echoable($new) . ' (common mistakes list)');
        }
        continue;
      }
    }
  }
}


  
  protected function correct_param_spelling() : void {
  // check each parameter name against the list of accepted names (loaded in expand.php).
  // It will correct any that appear to be mistyped.
  $match = ['', '']; // prevent memory leak in some PHP versions
  if (empty($this->param)) return ;
  $parameter_list = PARAMETER_LIST;
  $parameter_dead = DEAD_PARAMETERS;
  $parameters_used=array();
  $mistake_corrections = array_values(COMMON_MISTAKES);
  $mistake_keys = array_keys(COMMON_MISTAKES);
  foreach ($this->param as $p) {
    $parameters_used[] = $p->param;
  }

  $parameter_list = array_diff($parameter_list, $mistake_keys); // This way it does not contain "URL", but only "url"
  $unused_parameters = array_diff($parameter_list, $parameters_used);

  foreach ($this->param as $p) {

    if ((strlen($p->param) > 0) &&
        !(in_array(preg_replace('~\d+~', '#', $p->param), $parameter_list) || in_array($p->param, $parameter_list)) && // Some parameters have actual numbers in them
        stripos($p->param, 'CITATION_BOT')===FALSE) {
      if (trim($p->val) === '') {
        if (stripos($p->param, 'DUPLICATE_') === 0) {
          report_forget("Dropping empty left-over duplicate parameter " . echoable($p->param) . " ");
        } else {
          report_forget("Dropping empty unrecognised parameter " . echoable($p->param) . " ");
        }
        $this->quietly_forget($p->param);
        continue;
      }
      
      if (stripos($p->param, 'DUPLICATE_') === 0) {
        report_modification("Left-over duplicate parameter " . echoable($p->param) . " ");
      } else {
        report_modification("Unrecognised parameter " . echoable($p->param) . " ");
      }
      $mistake_id = array_search($p->param, $mistake_keys);
      if ($mistake_id) {
        // Check for common mistakes.  This will over-ride anything found by levenshtein: important for "editor1link" !-> "editor-link" (though this example is no longer relevant as of 2017)
        $p->param = $mistake_corrections[$mistake_id];
        report_modification('replaced with ' . echoable($mistake_corrections[$mistake_id]) . ' (common mistakes list)');
        continue;
      }
      
      $p->param = preg_replace('~author(\d+)-(la|fir)st~', "$2st$1", $p->param);
      $p->param = preg_replace('~surname\-?_?(\d+)~', "last$1", $p->param);
      $p->param = preg_replace('~(?:forename|initials?)\-?_?(\d+)~', "first$1", $p->param);

      // Check the parameter list to find a likely replacement
      $shortest = -1.0;
      $closest = '';
      $comp = '';
      $shortish = -1.0;
      
      if (preg_match('~\d+~', $p->param, $match)) { // Deal with # values
         $param_number = $match[0];
      } else {
         $param_number = '#';
      }
      foreach ($unused_parameters as $parameter) {
        $parameter = str_replace('#', $param_number, $parameter);
        if (strpos($parameter, '#') !== FALSE) continue; // Do no use # items unless we have a number
        $lev = (float) levenshtein($p->param, $parameter, 5, 4, 6);
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
        $shortest *= ((float) $str_len / (float) (similar_text($p->param, $closest) ? similar_text($p->param, $closest) : 0.001));
        $shortish *= ((float) $str_len / (float) (similar_text($p->param, $comp) ? similar_text($p->param, $comp) : 0.001));
      }
      
      if (in_array($p->param, $parameter_dead)) {
        report_inline("Could not fix outdated " . echoable($p->param));
      } elseif ($shortest < 12 && $shortest < $shortish) {
        $p->param = $closest;
        report_inline("replaced with $closest (likelihood " . (string)round(24.0 - $shortest,1) . "/24)"); // Scale arbitrarily re-based by adding 12 so users are more impressed by size of similarity
      } else {
        $similarity = (float) similar_text($p->param, $closest) / (float) strlen($p->param);
        if ($similarity > 0.6) {
          $p->param = $closest;
          report_inline("replaced with $closest (similarity " . (string)(round(24.0 * $similarity, 1)) . "/24)"); // Scale arbitrarily re-based by multiplying by 2 so users are more impressed by size of similarity
        } else {
          report_inline("could not be replaced with confidence.  Please check the citation yourself.");
        }
      }
    }
  }
}

  protected function join_params() : string {
    $ret = '';
    foreach($this->param as $p) {
      $ret .= '|' . $p->parsed_text();
    }
    return $ret;
  }

  public function change_name_to(string $new_name, bool $rename_cite_book = TRUE) : void {
    $spacing = ['', '']; // prevent memory leak in some PHP versions
    if (strpos($this->get('doi'), '10.1093') !== FALSE && $this->wikiname() !== 'cite web') return;
    if (bad_10_1093_doi($this->get('doi'))) return;
    if ($this->wikiname() === 'cite book' && !$this->blank_other_than_comments(CHAPTER_ALIASES)) {
      return; // Changing away leads to error
    }
    $new_name = strtolower(trim($new_name)); // Match wikiname() output and cite book below
    if ($new_name === $this->wikiname()) return;
    if (in_array($this->wikiname(), TEMPLATES_WE_RENAME)
    && ($rename_cite_book || $this->wikiname() != 'cite book')
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
      if ($this->should_url2chapter(TRUE)) { 
        $this->rename('url', 'chapter-url');
        $this->rename('format', 'chapter-format');
        $this->rename('url-access', 'chapter-url-access');
      } elseif (!$this->blank(['chapter-url','chapterurl']) && (str_i_same($this->get('chapter-url'), $this->get('url')))) {
        $this->forget('url');
      }  // otherwise they are differnt urls
    }
  }
  
  public function wikiname() : string {
    $name = trim(mb_strtolower(str_replace('_', ' ', (string) $this->name)));
     // Treat the same since alias
    if ($name === 'cite work') $name = 'cite book';
    if ($name === 'cite chapter') $name = 'cite book';
    if ($name === 'cite newspaper') $name = 'cite news';
    if ($name === 'cite website') $name = 'cite web';
    if ($name === 'cite paper') $name = 'cite journal';
    return $name ;
  }
  
  public function should_be_processed() : bool {
    return in_array($this->wikiname(), TEMPLATES_WE_PROCESS);
  }
  
  public function tidy_parameter(string $param) : void {
    // Note: Parameters are treated in alphabetical order, except where one
    // case necessarily continues from the previous (without a return).
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $pmatch = ['', '', '', '']; // prevent memory leak in some PHP versions
    $match = ['', '']; // prevent memory leak in some PHP versions

    if (!$param) return;
    
    if ($param === 'postscript' && $this->wikiname() !== 'citation' &&
        preg_match('~^(?:# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #)\s*(?:# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #|)$~i', $this->get('postscript'))) {
       // Remove misleading stuff -- comments of "NONE" etc mean nothing!
       // Cannot call forget, since it will not remove items with comments in it
       $key = $this->get_param_key('postscript');
       /** @psalm-suppress PossiblyNullArrayOffset */
       unset($this->param[$key]); // Key cannot be NULL because of get() call above
       report_forget('Dropping postscript that is only a comment');
       return;
    }
    
    if (mb_stripos($this->get($param), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
      return;  // We let comments block the bot
    }
    
    if($this->has($param)) {
      if (stripos($param, 'separator') === FALSE &&  // lone punctuation valid
          stripos($param, 'postscript') === FALSE &&  // periods valid
          stripos($param, 'url') === FALSE &&  // all characters are valid
          stripos($param, 'quot') === FALSE && // someone might have formatted the quote
          stripos($param, 'link') === FALSE) {  // inter-wiki links
        $this->set($param, preg_replace('~[\x{2000}-\x{200A}\x{00A0}\x{202F}\x{205F}\x{3000}]~u', ' ', $this->get($param))); // Non-standard spaces
        $this->set($param, preg_replace('~[\t\n\r\0\x0B]~u', ' ', $this->get($param))); // tabs, linefeeds, null bytes
        $this->set($param, preg_replace('~  +~u', ' ', $this->get($param))); // multiple spaces
        $this->set($param, preg_replace('~(?<!:)[:,]$~u', '', $this->get($param)));   // Remove trailing commas, colons, but not semi-colons--They are HTML encoding stuff
        $this->set($param, preg_replace('~^[:,;](?!:)~u', '', $this->get($param)));  // Remove leading commas, colons, and semi-colons
        $this->set($param, preg_replace('~&#x2013;~u', '&ndash;', $this->get($param)));
        $this->set($param, preg_replace('~&#x2014;~u', '&mdash;', $this->get($param)));
        $this->set($param, preg_replace('~(?<!\&)&[Aa]mp;(?!&)~u', '&', $this->get($param))); // &Amp; => & but not if next character is & or previous character is ;
      
        // Remove final semi-colon from a few items
        if ((in_array($param, ['date', 'year', 'location', 'publisher', 'issue', 'number', 'page', 'pages', 'pp', 'p', 'volume']) ||
           in_array($param, FLATTENED_AUTHOR_PARAMETERS))
          && strpos($this->get($param), '&') === FALSE) {
         $this->set($param, preg_replace('~;$~u', '', $this->get($param)));
        }
      
        // Remove quotes, if only at start and end -- In the case of title, leave them unless they are messed up
        if (preg_match("~^([\'\"]+)([^\'\"]+)([\'\"]+)$~u", $this->get($param), $matches)) {
          if (($matches[1] !== $matches[3]) || ($param !== 'title' && $param !== 'chapter' && $param !== 'publisher')) {
            $this->set($param, $matches[2]);
         }
        }

        // Non-breaking spaces at ends
        $this->set($param, trim($this->get($param), " \t\n\r\0\x0B"));
        while (preg_match("~^&nbsp;(.+)$~u", $this->get($param), $matches)) {
          $this->set($param, trim($matches[1], " \t\n\r\0\x0B"));
        }
        while (preg_match("~^(.+)&nbsp;$~u", $this->get($param), $matches)) {
          $this->set($param, trim($matches[1], " \t\n\r\0\x0B"));
        }
        $this->set($param, preg_replace('~\x{00AD}~u', '', $this->get($param))); // Remove soft hyphen
      }
    }
 
    if (!preg_match('~^(\D+)(\d*)(\D*)$~', $param, $pmatch)) {
      report_minor_error("Unrecognized parameter name format in " . echoable($param));  // @codeCoverageIgnore
      return;                                                              // @codeCoverageIgnore
    } else {
      // Put "odd ones" in "normalized" order - be careful down below about $param vs $pmatch values
      if (in_array(strtolower($param), ['s2cid','s2cid-access'])) {
        $pmatch = [$param, $param, '', ''];
      }
      if (in_array(strtolower($pmatch[3]), ['-first', '-last', '-surname', '-given', 'given', '-link', 'link', '-mask', 'mask'])) {
        $pmatch = [$param, $pmatch[1] . $pmatch[3], $pmatch[2], ''];
      }
      if ($pmatch[3] != '') {
        report_minor_error("Unrecognized parameter name format in " . echoable($param));  // @codeCoverageIgnore
        return;                                                              // @codeCoverageIgnore
      }
      switch ($pmatch[1]) {
        // Parameters are listed mostly alphabetically, though those with numerical content are grouped under "year"

        case 'accessdate':
        case 'access-date':
          if ($this->has($param) && $this->blank(ALL_URL_TYPES))
          {
            $this->forget($param);
          }
          return;

          
        case 'agency':
          if (in_array($this->get('agency'), ['United States Food and Drug Administration',
                                              'Surgeon General of the United States',
                                              'California Department of Public Health'])
               &&
              in_array($this->get('publisher'), 
                ['United States Department of Health and Human Services', 'California Tobacco Control Program', ''])) {
            $this->forget('publisher');
            $this->rename('agency', 'publisher'); // A single user messed this up on a lot of pages with "agency"
          }
          return;
          
        case 'arxiv':
          if ($this->has($param) && $this->wikiname() == 'cite web') {
            $this->change_name_to('cite arxiv');
          }
          return;
          
        case 'author':
          $the_author = $this->get($param);
          if (substr($the_author, 0, 2) == '[[' &&
              substr($the_author,   -2) == ']]' &&
              mb_substr_count($the_author, '[[') === 1 && 
              mb_substr_count($the_author, ']]') === 1 &&
              strpos($the_author, 'CITATION_BOT') === FALSE &&
              strpos($the_author, '{{!}}') === FALSE) {  // Has a normal wikilink
         //   if (preg_match(REGEXP_PLAIN_WIKILINK, $the_author, $matches)) {
         //     $this->set($param, $matches[1]);
         //     $this->add_if_new($param . '-link', $matches[1]);
         //   } elseif (preg_match(REGEXP_PIPED_WIKILINK, $the_author, $matches)) {
         //     $this->set($param, $matches[2]);
         //     $this->add_if_new($param . '-link', $matches[1]);
         //   }
          }
          if ($this->blank('agency') && in_array(strtolower($the_author), ['associated press', 'reuters'])) {
            $this->rename('author', 'agency');
            if ($pmatch[2] == '1' || $pmatch[2] == '') {
              $this->forget('author-link');
              $this->forget('authorlink');
              $this->forget('author-link1');
              $this->forget('authorlink1');
              $this->forget('author1-link');
            }
            return;
          }
          // Convert authorX to lastX, if firstX is set
          if ($pmatch[2] && $this->has('first' . $pmatch[2]) && $this->blank('last' . $pmatch[2])) {
            $this->rename('author' . $pmatch[2], 'last' . $pmatch[2]);
            $pmatch[1] = 'last';
            // Comment out since "never used"  $param = 'last' . $pmatch[2];
            return;
          }
          // No return here
        case 'authors':
          if ($this->has('author') && $this->has('authors')) $this->rename('author', 'DUPLICATE_authors');
          if (!$this->initial_author_params) $this->handle_et_al();
          // Continue from authors without break
        case 'last': case 'surname':
            if (!$this->initial_author_params) {
              if ($pmatch[2]) {
                $translator_regexp = "~\b([Tt]r(ans(lat...?(by)?)?)?\.?)\s([\w\p{L}\p{M}\s]+)$~u";
                if (preg_match($translator_regexp, trim($this->get($param)), $match)) {
                  $others = trim("$match[1] $match[5]");
                  if ($this->has('others')) {
                    $this->append_to('others', '; ' . $others);
                  } else {
                    $this->set('others', $others);
                  }
                  $this->set($param, trim(preg_replace($translator_regexp, "", $this->get($param))));
                }
              }
            }
            if ($pmatch[2] && $pmatch[1] === 'last') {
              $the_author = $this->get($param);
              if (substr($the_author, 0, 2) == '[[' &&
                 substr($the_author,   -2) == ']]' &&
                 mb_substr_count($the_author, '[[') === 1 && 
                 mb_substr_count($the_author, ']]') === 1 &&
                 strpos($the_author, 'CITATION_BOT') === FALSE &&
                 strpos($the_author, '{{!}}') === FALSE) {  // Has a normal wikilink
                   $did_something = FALSE;
                   if (preg_match(REGEXP_PLAIN_WIKILINK, $the_author, $matches)) {
                    $this->set($param, $matches[1]);
                    $this->add_if_new('author' . $pmatch[2] . '-link', $matches[1]);
                    $did_something = TRUE;
                   } elseif (preg_match(REGEXP_PIPED_WIKILINK, $the_author, $matches)) {
                    $this->set($param, $matches[2]);
                    $this->add_if_new('author' . $pmatch[2] . '-link', $matches[1]);
                    $did_something = TRUE;
                  }
                  if ($pmatch[2] === '1' && $this->has('first')) {
                    $this->rename('first', 'first1');
                  }
                  if ($did_something && strpos($this->get('first' . $pmatch[2]), '[') !==FALSE) { // Clean up links in first names
                    $the_author = $this->get('first' . $pmatch[2]);
                    if (preg_match(REGEXP_PLAIN_WIKILINK, $the_author, $matches)) {
                      $this->set('first' . $pmatch[2], $matches[1]);
                    } elseif (preg_match(REGEXP_PIPED_WIKILINK, $the_author, $matches)) {
                      $this->set('first' . $pmatch[2], $matches[2]);
                    }
                  }
              }
            }
            if (!$pmatch[2] && $pmatch[1] === 'last' && !$this->blank(['first1', 'first2', 'last2'])) {
              $this->rename('last', 'last1');
              if ($this->blank('first1')) $this->rename('first', 'first1');
            }
            return;

        case 'bibcode':
          if ($this->blank($param)) return;
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
    
        case 'date':
          if ($this->blank('date') && $this->has('year')) $this->forget('date');
          return;
          
        case 'doi':
          $doi = $this->get($param);
          if (!$doi) return;
          if ($doi == '10.1267/science.040579197') {
            // This is a bogus DOI from the PMID example file
            $this->forget('doi');
            return;
          }
          if ($doi == '10.5284/1000184') {
            // This is a DOI for an entire database, not anything within it
            $this->forget('doi');
            return;
          }
          if (substr($doi, 0, 8) == '10.5555/') { // Test DOI prefix.  NEVER will work
            $this->forget('doi');
            if ($this->blank('url')) {
              $test_url = 'https://plants.jstor.org/stable/' . $doi;
              $ch = curl_init($test_url);
              curl_setopt_array($ch,
                       [CURLOPT_RETURNTRANSFER => TRUE,
                        CURLOPT_TIMEOUT => 25,
                        CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org']);
              @curl_exec($ch);
              $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
              curl_close($ch);
              if ($httpCode == 200) $this->add_if_new('url', $test_url);
            }
            return;
          }
          if (stripos($doi, '10.1111/j.1572-0241') === 0 && NATURE_FAILS) {
            if (!$this->blank(['pmid', 'pmc', 'jstor'])) {
               $this->forget('doi');
               return;
            }
          }
          $this->set($param, sanitize_doi($doi));
          if (!preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) $this->change_name_to('cite journal', FALSE);
          if (preg_match('~^10\.2307/(\d+)$~', $this->get_without_comments_and_placeholders('doi'))) {
            $this->add_if_new('jstor', substr($this->get_without_comments_and_placeholders('doi'), 8));
          }
          if ($this->wikiname() === 'cite arxiv') $this->change_name_to('cite journal');
          if (preg_match('~^10\.3897/zookeys\.(\d+)\.\d+$~', $doi, $matches)) {
            if ($this->blank(ISSUE_ALIASES)) {
              $this->add_if_new('issue', $matches[1]);
            } elseif ($this->has('number')) {
              $this->rename('number', 'issue', $matches[1]);
            } else {
              $this->set('issue', $matches[1]);
            }
          }
          return;
          
        case 'doi-broken': case 'doi_brokendate': case 'doi-broken-date': case 'doi_inactivedate': case 'doi-inactive-date':
          if ($this->blank('doi')) $this->forget($param);
          return;
          
        case 'edition':
          if ($this->blank($param)) return;
          $this->set($param, preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $this->get($param)));
          return; // Don't want 'Edition ed.'
        
        case 'eprint':
          if ($this->blank($param)) return;
          if ($this->wikiname() == 'cite web') $this->change_name_to('cite arxiv');
          return;

        case 'encyclopedia': case 'encyclopeadia':
          if ($this->blank($param)) return;
          if ($this->wikiname() == 'cite web') $this->change_name_to('cite encyclopedia');
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
          if ($this->blank('isbn')) return;
          $this->set('isbn', preg_replace('~\s?-\s?~', '-', $this->get('isbn'))); // a White space next to a dash
          $this->set('isbn', $this->isbn10Toisbn13($this->get('isbn')));
          if ($this->blank('journal') || $this->has('chapter') || $this->wikiname() === 'cite web') {
            $this->change_name_to('cite book');
          }
          $this->forget('asin');
          return;
          
        case 'journal':
        case 'periodical':
          if ($this->blank($param)) return;
          if (preg_match('~^(|[a-zA-Z0-9][a-zA-Z0-9]+\.)([a-zA-Z0-9][a-zA-Z0-9][a-zA-Z0-9]+)\.(org|net|com)$~', $this->get($param))) {
            $this->rename($param, 'website');
            return;
          }
          if (str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');

          $periodical = trim($this->get($param));
          if (substr(strtolower($periodical), 0, 7) === 'http://' || substr(strtolower($periodical), 0, 8) === 'https://') {
             if ($this->blank('url')) $this->rename($param, 'url');
             return;
          } elseif (substr(strtolower($periodical), 0, 4) === 'www.') {
             if ($this->blank('website')) $this->rename($param, 'website');
             return;
          }
          if ($this->blank(['chapter', 'isbn']) && $param === 'journal') {
            // Avoid renaming between cite journal and cite book
            $this->change_name_to('cite journal');
          }
          
          if (( mb_substr($periodical, 0, 2) !== "[["   // Only remove partial wikilinks
                    || mb_substr($periodical, -2) !== "]]"
                    || mb_substr_count($periodical, '[[') !== 1 
                    || mb_substr_count($periodical, ']]') !== 1)
                    && !preg_match('~^(?:the |)(?:Publications|Publication|journal|Transactions|letters|annals|Bulletin|reports|history) of the ~i', $periodical)
                    )
          {
              $this->set($param, preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $periodical));
              $this->set($param, preg_replace(REGEXP_PIPED_WIKILINK, "$2", $this->get($param)));
          }
          $periodical = trim($this->get($param));
          if (substr($periodical, 0, 1) !== "[" && substr($periodical, -1) !== "]") {  
            if ((strlen($periodical) - mb_strlen($periodical)) < 9 ) { // eight or fewer UTF-8 stuff
               if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $periodical . ' ') == ' ' . $periodical . ' ' &&
                   strip_diacritics($periodical) === $periodical) {
                      $periodical = ucwords($periodical); // Found NO foreign words/phrase
               }
               $this->set($param, title_capitalization($periodical, TRUE));
            }
          } else {
            if (preg_match(REGEXP_PLAIN_WIKILINK, $periodical, $matches)) {
              $periodical = $matches[1];
              $periodical = str_replace("’", "'", $periodical); // Fix quotes for links
              $this->set($param, '[[' . $periodical . ']]');
              $new_periodical = title_capitalization(ucwords($periodical), TRUE);
              if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $periodical . ' ') == ' ' . $periodical . ' ' &&
                  str_replace(['(', ')'], '', $periodical) == $periodical &&
                  $new_periodical != $periodical) {
                 $now = WikipediaBot::is_redirect($periodical);
                 if ($now === -1) { // Dead link
                   $this->set($param, '[[' . $new_periodical . ']]');
                 } elseif ($now === 1) { // Redirect
                   if (WikipediaBot::is_redirect($new_periodical) === 0) {
                     $this->set($param, '[[' . $new_periodical . ']]');
                   }
                 }
              }
            } elseif (preg_match(REGEXP_PIPED_WIKILINK, $periodical, $matches)) {
              $linked_text = str_replace("’", "'", $matches[1]); // Fix quotes for links
              $human_text  = $matches[2];
              if (preg_match("~^[\'\"]+([^\'\"]+)[\'\"]+$~", $human_text, $matches)) { // Remove quotes
                $human_text = $matches[1];
              }
              $new_linked_text = title_capitalization(ucwords($linked_text), TRUE);
              if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $linked_text . ' ') == ' ' . $linked_text . ' ' &&
                str_replace(['(', ')'], '', $linked_text ) == $linked_text &&
                $new_linked_text != $linked_text) {
                  $now = WikipediaBot::is_redirect($linked_text);
                  if ($now === -1) {
                    $linked_text = $new_linked_text; // Dead to something
                  } elseif ($now === 1) {
                    if (WikipediaBot::is_redirect($new_linked_text) === 0) {
                      $linked_text = $new_linked_text; // Redirect to actual page
                    }
                  }
              }
              // We assume that human text is some kind of abreviations that we really don't wan to mess with
              $periodical  = '[[' . $linked_text . '|' . $human_text . ']]';
              $this->set($param, $periodical);
            } elseif (substr_count($periodical, ']') === 0 && substr_count($periodical, '[') === 0) { // No links
             $periodical = straighten_quotes($periodical);
             $this->set($param, $periodical);
            }
          }
          if ($this->wikiname() === 'cite arxiv') $this->change_name_to('cite journal');
          if ($this->is_book_series($param)) {
            $this->change_name_to('cite book');
            if ($this->blank('series')) {
              $this->rename($param, 'series');
            } elseif ($this->is_book_series('series') ||
                     str_equivalent($this->get($param), $this->get('series'))) {
              $this->forget($param);
            }
          }
          return;
        
        case 'jstor':
          if ($this->blank($param)) return;
          if (substr($this->get($param), 0, 8) ===  '10.2307/') {
            $this->set($param, substr($this->get($param), 8));
          } elseif (preg_match('~^https?://www\.jstor\.org/stable/(.*)$~', $this->get($param), $matches)) {
            $this->set($param, $matches[1]);
          }
          $this->change_name_to('cite journal', FALSE);
          return;
        
        case 'magazine':
          if ($this->blank($param)) return;
          // Remember, we don't process cite magazine.
          if ($this->wikiname() == 'cite journal' && !$this->has('journal')) {
            $this->rename('magazine', 'journal');
          }
          return;
        
        case 'orig-year': case 'origyear':
          if ($this->blank($param)) return;
          if ($this->blank(['year', 'date'])) { // Will not show unless one of these is set, so convert
            if (preg_match('~^\d\d\d\d$~', $this->get($param))) { // Only if a year, might contain text like "originally was...."
              $this->rename($param, 'year');
            }
          }
          return;
          
        case 'mr':
          if (preg_match("~mr(\d+)$~i", $this->get($param), $matches)) {
             $this->set($param, $matches[1]);
          }
          return;
        
        case 'others': case 'day': case 'month':  // Bad idea to have in general
          if ($this->blank($param)) $this->forget($param);
          return;

        case 'pmc':
          if (preg_match("~pmc(\d+)$~i", $this->get($param), $matches)) {
             $this->set($param, $matches[1]);
          }
          // No break; continue from pmc to pmid:
        case 'pmid':
          if ($this->blank($param)) return;
          $this->change_name_to('cite journal', FALSE);
          return;
          
        case 'publisher':
          if ($this->wikiname() == 'cite journal' && $this->has('journal') && $this->has('title') && $this->blank($param)) {
            $this->forget($param);  // Not good to encourage adding this
            return;
          }
          if (stripos($this->get($param), 'proquest') !== FALSE && stripos($this->get($param), 'llc') === FALSE) {
            $this->forget($param);
            if ($this->blank('via')) {
              $this_big_url = $this->get('url') . $this->get('thesis-url') . $this->get('thesisurl') . $this->get('chapter-url') . $this->get('chapterurl');
              if (stripos($this_big_url, 'proquest') !== FALSE) $this->add('via', 'ProQuest');
            }
            return;
          }
          if ($this->blank($param)) return;
          $publisher = strtolower($this->get($param));
          if ($this->wikiname() == 'cite journal' && $this->has('journal') && $this->has('title')
              && !$this->blank(['pmc', 'pmid'])
              && (strpos($publisher, 'national center for biotechnology information') !== FALSE ||
                  strpos($publisher, 'u.s. national library of medicine') !== FALSE)) {
              $this->forget($param);
              return;
          }
          if (substr($publisher, 0, 2) == '[[' &&
              substr($publisher,   -2) == ']]' &&
              mb_substr_count($publisher, '[[') === 1 && 
              mb_substr_count($publisher, ']]') === 1) {
            if (preg_match(REGEXP_PLAIN_WIKILINK, $publisher, $matches)) {
              $publisher = $matches[1];
            } elseif (preg_match(REGEXP_PIPED_WIKILINK, $publisher, $matches)) {
              $publisher = $matches[2];
            }
            foreach (['journal', 'newspaper'] as $the_same) { // Prefer wiki-linked
              if (strtolower($this->get($the_same)) === $publisher) {
                $this->forget($the_same);
                $this->rename($param, $the_same);
                return;
              }
            }
          }
          if (stripos($this->get('url'), 'maps.google') !== FALSE && stripos($publisher, 'google') !== FALSE)  {
            $this->set($param, 'Google Maps');  // Case when Google actually IS a publisher
            return;
          }
          if (stripos($this->get('url'), 'developers.google.com') !== FALSE && stripos($publisher, 'google') !== FALSE)  {
            $this->set($param, 'Google Inc.');  // Case when Google actually IS a publisher
            return;
          }
          if (stripos($this->get('url'), 'support.google.com') !== FALSE && stripos($publisher, 'google') !== FALSE)  {
            $this->set($param, 'Google Inc.');  // Case when Google actually IS a publisher
            return;
          }
          foreach (NON_PUBLISHERS as $not_publisher) {
            if (stripos($publisher, $not_publisher) !== FALSE) {
              $this->forget($param);
              return;
            }
          }
          // It might not be a product/book, but a "top 100" list
          if (strtolower(str_replace(array('[', ' ', ']'), '', $publisher)) === 'amazon.com') {
            $all_urls = '';
            foreach (ALL_URL_TYPES as $a_url_type) {
              $all_urls .= $this->get($a_url_type);
            }
            $all_urls = strtolower($all_urls);
            if (strpos($all_urls, '/dp/') !== FALSE && strpos($all_urls, '/feature/') === FALSE && strpos($all_urls, '/exec/obidos/') === FALSE) {
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
          if ($this->blank(WORK_ALIASES)) {
            if (in_array(str_replace(array('[', ']', '"', "'", 'www.'), '', $publisher), PUBLISHERS_ARE_WORKS)) {
               $this->rename($param, 'work'); // Don't think about which work it is
            }
          }
          if (!$this->blank(['eprint', 'arxiv']) &&
              strtolower($publisher) == 'arxiv') {
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
          if ($this->is_book_series('series')) {
            $this->change_name_to('cite book');
            if ($this->has('journal')) {
              if ($this->is_book_series('journal') ||
                     str_equivalent($this->get('series'), $this->get('journal'))) {
                $this->forget('journal');
              }
            }
          }
          return;
          
        case 'title':
          if ($this->blank($param)) return;
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
          // Messed up cases:   [[sdfsad] or [dsfasdf]]
          if (preg_match('~^\[\[([^\]\[\|]+)\]$~', $title, $matches) ||
              preg_match('~^\[([^\]\[\|]+)\]\]$~', $title, $matches)) {
             $title = $matches[1];
          }
          // Only do for cite book, since might be title="A review of the book Bob (Robert Edition)"
          if ($this->wikiname() === 'cite book' && $this->blank('edition') && preg_match('~^(.+)\(([^\(\)]+) edition\)$~i', $title, $matches)) {
             $title = trim($matches[1]);
             $this->add_if_new('edition', trim($matches[2]));
          }
          if (mb_substr_count($title, '[[') !== 1 ||  // Completely remove multiple wikilinks
              mb_substr_count($title, ']]') !== 1) {
             $title = preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $title);   // Convert [[X]] wikilinks into X
             $title = preg_replace(REGEXP_PIPED_WIKILINK, "$2", $title);   // Convert [[Y|X]] wikilinks into X
             $title = preg_replace("~\[\[~", "", $title); // Remove any extra [[ or ]] that should not be there
             $title = preg_replace("~\]\]~", "", $title);
          } elseif (strpos($title, '{{!}}') === FALSE) { // Convert a single link to a title-link
             if (preg_match(REGEXP_PLAIN_WIKILINK, $title, $matches)) {
               if (strlen($matches[1]) > (0.7 * (float) strlen($title)) && ($title != '[[' . $matches[1] . ']]')) {  // Only add as title-link if a large part of title text
                 $title = '[[' . $matches[1] . "|" . str_replace(array("[[", "]]"), "", $title) . ']]';
               }
             } elseif (preg_match(REGEXP_PIPED_WIKILINK, $title, $matches) &&
                       strpos($title, ':') === FALSE) { // Avoid touching inter-wiki links
               if (($matches[1] == $matches[2]) && ($title == $matches[0])) {
                   $title = '[[' . $matches[1]  . ']]'; // Clean up double links
               } elseif (strlen($matches[0]) > (0.7 * (float) strlen($title))) {  // Only add as title-link if a large part of title text
                // TODO - this is not correct  $title = '[[' . $matches[1] . '|' . str_replace(array("[[", "]]"), "", $title) . ']]';
               }
             }
          }
          $this->set($param, $title);
          if ($title && str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');
          if ($title && str_equivalent($this->get($param), $this->get('encyclopedia'))) $this->forget($param);
          if ($title && str_equivalent($this->get($param), $this->get('encyclopaedia'))) $this->forget($param);
          if (preg_match('~^(.+)\{\{!\}\} Request PDF$~i', trim($this->get($param)), $match)) {
                 $this->set($param, trim($match[1]));
          } elseif (!$this->blank(['isbn', 'doi', 'pmc', 'pmid']) && preg_match('~^(.+) \(PDF\)$~i', trim($this->get($param)), $match)) {
                 $this->set($param, trim($match[1])); // Books/journals probably don't end in (PDF)
          }
          return;

        case 'archivedate':
          if ($this->has('archivedate') && $this->get('archive-date') === $this->get('archivedate')) {
            $this->forget('archivedate');
          }
          return;

        case 'archive-url':
        case 'archiveurl':
          if ($this->blank(['archive-date', 'archivedate'])) {
            if (preg_match('~^https?://(?:web\.archive\.org/web/|archive\.today/|archive\.\S\S/|webarchive\.loc\.gov/all/|www\.webarchive\.org\.uk/wayback/archive/)(\d{4})(\d{2})(\d{2})\d{6}~', $this->get($param), $matches)) {
              $this->add_if_new('archive-date', $matches[1] . '-' . $matches[2] . '-' . $matches[3]);
            }
            if (preg_match('~^https?://wayback\.archive\-it\.org/\d{4}/(\d{4})(\d{2})(\d{2})\d{6}~', $this->get($param), $matches)) {
              $this->add_if_new('archive-date', $matches[1] . '-' . $matches[2] . '-' . $matches[3]);
            }
          }
          if (preg_match('~^https?://(?:web\.archive\.org/web|archive\.today|archive\.\S\S|webarchive\.loc\.gov/all|www\.webarchive\.org\.uk/wayback/archive)/(?:save|\*)/~', $this->get($param))) {
              $this->forget($param); // Forget "save it now" archives.  They are rubbish
              return;
          }
          if (preg_match('~^(https?://(?:www\.|)webcitation\.org/)([0-9a-zA-Z]{9})(?:|\?url=.*)$~', $this->get($param), $matches)) {
              // $this->set($param, $matches[1] . $matches[2]); // The url part is actually NOT binding, but other wikipedia bots check it
              if ($this->blank(['archive-date', 'archivedate'])) {
                 $base62='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                 $num62 = str_split($matches[2]);
                 $time = 0;
                 for($i=0;$i<9;$i++) {
                    $time = (62 * $time) + (int) strpos($base62, $num62[$i]);
                 }
                 $this->add_if_new('archive-date', date("Y-m-d", (int) ($time/1000000)));
              }
              return;
          }
          if (stripos($this->get($param), 'archive') === FALSE) {
            if ($this->get($param) == $this->get('url')) {
              $this->forget($param);  // The archive url is the real one
              return;
            }
          }
          // Clean up a bunch on non-archive URLs
          if (stripos($this->get($param), 'archive') === FALSE &&
              stripos($this->get($param), 'webcitation') === FALSE &&
              stripos($this->get($param), 'perma.') === FALSE &&
              stripos($this->get($param), 'wayback') === FALSE &&
              stripos($this->get($param), 'webharvest') === FALSE &&
              stripos($this->get($param), 'freezepage') === FALSE &&
              stripos($this->get($param), 'petabox.bibalex.org') === FALSE) {
             if (preg_match("~^https?://(?:www\.|)researchgate\.net/[^\s]*publication/([0-9]+)_*~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://www.researchgate.net/publication/' . $matches[1]);
                 if (preg_match('~^\(PDF\)(.+)$~i', trim($this->get('title')), $match)) {
                   $this->set('title', trim($match[1]));
                 }
             } elseif (preg_match("~^https?://(?:www\.|)academia\.edu/(?:documents/|)([0-9]+)/*~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://www.academia.edu/' . $matches[1]);
             } elseif (preg_match("~^https?://(?:www\.|)zenodo\.org/record/([0-9]+)(?:#|/files/)~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://zenodo.org/record/' . $matches[1]);
             } elseif (preg_match("~^https?://(?:www\.|)google\.com/search~i", $this->get($param))) {
                 $this->set($param, $this->simplify_google_search($this->get($param)));
             } elseif (preg_match("~^(https?://(?:www\.|)sciencedirect\.com/\S+)\?via(?:%3d|=)\S*$~i", $this->get($param), $matches)) {
                 $this->set($param, $matches[1]);
             } elseif (preg_match("~^(https?://(?:www\.|)bloomberg\.com/\S+)\?(?:utm_|cmpId=)\S*$~i", $this->get($param), $matches)) {
                 $this->set($param, $matches[1]);
             } elseif (preg_match("~^https?://watermark\.silverchair\.com/~", $this->get($param))
                 || preg_match("~^https?://s3\.amazonaws\.com/academia\.edu~", $this->get($param))
                 || preg_match("~^https?://onlinelibrarystatic\.wiley\.com/store/~", $this->get($param))) {
                 $this->forget($param);
                 return;
             }
             if ($this->get_identifiers_from_url($this->get($param))) {
               if (!extract_doi($this->get($param))[1]) { // If it gives a doi, then might want to keep it anyway since many archives have doi in the url string
                 $this->forget($param);
                 return;
               }
             }
          }
          if ($this->blank(ALL_URL_TYPES)) {
             if (preg_match("~^https?://web\.archive\.org/web/\d{14}/(https?://.*)$~", $this->get($param), $match)) {
                quietly('report_modification', 'Extracting URL from archive');
                $this->add_if_new('url', $match[1]);
             }
          }
          return;
 
        case 'chapter-url':
        case 'chapterurl':
          if ($this->blank($param)) return;
          if ($this->blank('url') && $this->blank(CHAPTER_ALIASES)) {
            $this->rename($param, 'url');
            $param = 'url'; // passes down to next area
          }
        case 'url':
          if ($this->blank($param)) return;
          if (preg_match("~^https?://(?:www\.|)researchgate\.net/[^\s]*publication/([0-9]+)_*~i", $this->get($param), $matches)) {
              $this->set($param, 'https://www.researchgate.net/publication/' . $matches[1]);
              if (preg_match('~^\(PDF\)(.+)$~i', trim($this->get('title')), $match)) {
                 $this->set('title', trim($match[1]));
              }
          } elseif (preg_match("~^https?://(?:www\.|)academia\.edu/(?:documents/|)([0-9]+)/*~i", $this->get($param), $matches)) {
              $this->set($param, 'https://www.academia.edu/' . $matches[1]);
          } elseif (preg_match("~^https?://(?:www\.|)zenodo\.org/record/([0-9]+)(?:#|/files/)~i", $this->get($param), $matches)) {
              $this->set($param, 'https://zenodo.org/record/' . $matches[1]);
          } elseif (preg_match("~^https?://(?:www\.|)google\.com/search~i", $this->get($param))) {
              $this->set($param, $this->simplify_google_search($this->get($param)));
          } elseif (preg_match("~^(https?://(?:www\.|)sciencedirect\.com/\S+)\?via(?:%3d|=)\S*$~i", $this->get($param), $matches)) {
              $this->set($param, $matches[1]);
          } elseif (preg_match("~^(https?://(?:www\.|)bloomberg\.com/\S+)\?(?:utm_|cmpId=)\S*$~i", $this->get($param), $matches)) {
              $this->set($param, $matches[1]);
          } elseif (preg_match("~^https?://watermark\.silverchair\.com/~", $this->get($param))
                 || preg_match("~^https?://s3\.amazonaws\.com/academia\.edu~", $this->get($param))
                 || preg_match("~^https?://onlinelibrarystatic\.wiley\.com/store/~", $this->get($param))) {
                 if ($this->blank(['archive-url', 'archiveurl'])) { // Sometimes people grabbed a snap of it
                    $this->forget($param);
                 }
              return;
          } elseif (preg_match("~^https?://(?:www\.|)bloomberg\.com/tosv2\.html\?vid=&uuid=(?:.+)&url=([a-zA-Z0-9/\+]+=*)$~", $this->get($param), $matches)) {
             if (base64_decode($matches[1])) { 
               quietly('report_modification', "Decoding Bloomberg URL.");
               $this->set($param, 'https://www.bloomberg.com' .  base64_decode($matches[1]));
             }
          } elseif (preg_match("~^(https?://news\.google\.com/newspapers\S+)&sjid=[^#&=]+(&\S+)$~i", $this->get($param), $matches)) {
              $this->set($param, $matches[1] . $matches[2]);
          } elseif (preg_match("~^https?://.*ebookcentral.proquest.+/lib/.+docID(?:%3D|=)(\d+)(|#.*|&.*)(?:|\.)$~i", $this->get($param), $matches)) {
              if ($matches[2] === '#' || $matches[2] === '#goto_toc' || $matches[2] === '&' ||
                  $matches[2] === '&query=' || $matches[2] === '&query=#' || preg_match('~^&tm=\d*$~', $matches[2])) {
                $matches[2] = '';
              }
              if (substr($matches[2], -1) === '#' || substr($matches[2], -1) === '.') $matches[2] = substr($matches[2], 0, -1); // Sometime just a trailing # after & part
              quietly('report_modification', "Unmasking Proquest eBook URL.");
              $this->set($param, 'https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=' . $matches[1] . $matches[2]);
          }
          // Proxy stuff
          if (stripos($this->get($param), 'proxy') !== FALSE) { // Look for proxy first for speed, this list will grow and grow
              // Use dots, not \. since it might match dot or dash
              if (preg_match("~^https?://ieeexplore.ieee.org.+proxy.*/document/(.+)$~", $this->get($param), $matches)) {
                 report_info("Remove proxy from IEEE URL");
                 $this->set($param, 'https://ieeexplore.ieee.org/document/' . $matches[1]);
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
              } elseif (preg_match("~^https?://(?:www.|)oxfordhandbooks.com.+proxy.*/view/(.+)$~", $this->get($param), $matches)) {
                 $this->set($param, 'https://www.oxfordhandbooks.com/view/' . $matches[1]);
                 report_info("Remove proxy from Oxford Handbooks URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
              } elseif (preg_match("~^https?://(?:www.|)oxfordartonline.com.+proxy.*/view/(.+)$~", $this->get($param), $matches)) {
                 $this->set($param, 'https://www.oxfordartonline.com/view/' . $matches[1]);
                 report_info("Remove proxy from Oxford Art URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
              } elseif (preg_match("~^https?://(?:www.|)sciencedirect.com[^/]+/(\S+)$~i", $this->get($param), $matches)) {
                 report_info("Remove proxy from ScienceDirect URL");
                 $this->set($param, 'https://www.sciencedirect.com/' . $matches[1]);
                 if ($this->has('via')) { 
                   if (stripos($this->get('via'), 'library') !== FALSE ||
                       stripos($this->get('via'), 'direct') === FALSE) {
                     $this->forget('via');
                   }
                 }
              } elseif (preg_match("~^https?://(?:login\.|)(?:lib|)proxy\.[^\?\/]+\/login\?q?url=(https?://)(.+)$~", $this->get($param), $matches)) {
                 if (strpos($matches[2], '/') === FALSE) {
                    $this->set($param, $matches[1] . urldecode($matches[2]));
                 } else {
                    $this->set($param, $matches[1] . $matches[2]);
                 }
                 report_info("Remove proxy from URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
              } elseif (preg_match("~^https?://(?:login\.|)(?:lib|)proxy\.[^\?\/]+\/login\?q?url=(https?%3A%2F%2F.+)$~i", $this->get($param), $matches)) {
                 $this->set($param, urldecode($matches[1]));
                 report_info("Remove proxy from URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
              }
          }
          // idm.oclc.org Proxy
          if (stripos($this->get($param), 'idm.oclc.org') !== FALSE) {
              $oclc_found = FALSE;
              if (preg_match("~^https://([^\.\-\/]+)-([^\.\-\/]+)-([^\.\-\/]+)\.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '/' . $matches[4]);
                 $oclc_found = TRUE;
              } elseif (preg_match("~^https://([^\.\-\/]+)\.([^\.\-\/]+)\.com.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '.com/' . $matches[3]);
                 $oclc_found = TRUE;
              } elseif (preg_match("~^https://([^\.\-\/]+)-([^\.\-\/]+)\.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '/' . $matches[3]);
                 $oclc_found = TRUE;
              } elseif (preg_match("~^https://(?:login.?|)[^\.\-\/]+\.idm\.oclc\.org/login\?q?url=(https?://[^\.\-\/]+\.[^\.\-\/]+\.[^\.\-\/]+/.*)$~i", $this->get($param), $matches)) {
                 $this->set($param, $matches[1]);
                 $oclc_found = TRUE;
              } elseif (preg_match("~^https://(?:login.?|)[^\.\-\/]+\.idm\.oclc\.org/login\?q?url=(https?://[^\.\-\/\%]+\.[^\.\-\/\%]+\.[^\.\-\/\%]+)(\%2f.*)$~i", $this->get($param), $matches)) {
                 $this->set($param, $matches[1] . urldecode($matches[2]));
                 $oclc_found = TRUE;
              }
              if ($oclc_found) {
                report_info("Remove OCLC proxy from URL");
                if (stripos($this->get('via'), 'wiki') !== FALSE ||
                    stripos($this->get('via'), 'oclc') !== FALSE) {
                  $this->forget('via');
                }
              }
          }
          if (preg_match('~^https?://([^/]+)/~', $this->get($param), $matches)) { 
             $the_host = $matches[1];
          } else {
             $the_host = '';
          }
          if (stripos($the_host, 'proxy') !== FALSE ||
              stripos($the_host, 'lib') !== FALSE ||
              stripos($the_host, 'mutex') !== FALSE) {
                // Generic proxy code www.host.com.proxy-stuff/dsfasfdsfasdfds
              if (preg_match("~^https?://(www\.[^\./\-]+\.com)\.[^/]*(?:proxy|library|\.lib\.|mutex\.gmu)[^/]*/(\S+)$~i", $this->get($param), $matches)) {
                 report_info("Remove proxy from " . echoable($matches[1]) . " URL");
                 $this->set($param, 'https://' . $matches[1] . '/' . $matches[2]);
                 if ($this->has('via')) { 
                     $this->forget('via');
                 }
              // Generic proxy code www-host-com.proxy-stuff/dsfasfdsfasdfds
              } elseif (preg_match("~^https?://www\-([^\./\-]+)\-com[\.\-][^/]*(?:proxy|library|\.lib\.|mutex\.gmu)[^/]*/(\S+)$~i", $this->get($param), $matches)) {
                 $matches[1] = 'www.' . $matches[1] . '.com';
                 report_info("Remove proxy from " . echoable($matches[1]) . " URL");
                 $this->set($param, 'https://' . $matches[1] . '/' . $matches[2]);
                 if ($this->has('via')) { 
                     $this->forget('via');
                 }
              }
          }
          if (stripos($this->get($param), 'galegroup') !== FALSE) {
            if (preg_match("~^(?:http.+url=|)https?://go.galegroup.com(%2fps.+)$~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://go.galegroup.com' . urldecode($matches[1]));
                 report_info("Remove proxy from Gale URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
                 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
            } elseif (preg_match("~^http.+url=https?://go\.galegroup\.com/(.+)$~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://go.galegroup.com/' . $matches[1]);
                 report_info("Remove proxy from Gale URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
                 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
            } elseif (preg_match("~^(?:http.+url=|)https?://link.galegroup.com(%2fps.+)$~i", $this->get($param), $matches)) {
                 $this->set($param, 'https://link.galegroup.com' . urldecode($matches[1]));
                 report_info("Remove proxy from Gale URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
                 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
            } elseif (preg_match("~^http.+url=https?://link\.galegroup\.com/(.+)$~", $this->get($param), $matches)) {
                 $this->set($param, 'https://link.galegroup.com/' . $matches[1]);
                 report_info("Remove proxy from Gale URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
                 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
            }
            if (preg_match("~^(https?://(?:go|link)\.galegroup\.com/.*)&u=[^&]*(&.*|)$~", $this->get($param), $matches)) {
                 $this->set($param, $matches[1] . $matches[2]);
                 report_info("Remove University ID from Gale URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
                 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
            }
          }
          if (stripos($this->get($param), 'proquest') !== FALSE) {
            if (preg_match("~^(?:http.+/login\?url=|)https?://(?:0\-|)search.proquest.com[^/]+(|/[^/]+)/docview/(.+)$~", $this->get($param), $matches)) {
                 $this->set($param, 'https://search.proquest.com' . $matches[1] . '/docview/' . $matches[2]);
                 report_info("Remove proxy from ProQuest URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
                 if ($this->has('via') && stripos($this->get('via'), 'proquest') === FALSE) $this->forget('via');
            } elseif (preg_match('~^https?://(.*)proquest.umi.com(.*)/(pqd.+)$~', $this->get($param), $matches)) {
               if ($matches[1] || $matches[2]) {
                 $this->set($param, 'http://proquest.umi.com/' . $matches[3]);
                 report_info("Remove proxy from ProQuest URL");
                 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
                 if ($this->has('via') && stripos($this->get('via'), 'proquest') === FALSE) $this->forget('via');
               }
            }
            $changed = FALSE;
            if (preg_match("~^https?://search.proquest.com/(.+)/docview/(.+)$~", $this->get($param), $matches)) {
              if ($matches[1] != 'dissertations') {
                 $changed = TRUE;
                 $this->set($param, 'https://search.proquest.com/docview/' . $matches[2]); // Remove specific search engine
              }
            }
            if (preg_match("~^https?://search\.proquest\.com/docview/(.+)/(?:abstract|fulltext|preview|page).*$~i", $this->get($param), $matches)) {
                 $changed = TRUE;
                 $this->set($param, 'https://search.proquest.com/docview/' . $matches[1]); // You have to login to get that
            }
            if (preg_match("~^https?://search\.proquest\.com/docview/(.+)\?.+$~", $this->get($param), $matches)) {
                 $changed = TRUE;
                 $this->set($param, 'https://search.proquest.com/docview/' . $matches[1]); // User specific information
            }
            if (preg_match("~^https?://search\.proquest\.com/docview/([0-9]+)/[0-9A-Z]+/[0-9]+\??$~", $this->get($param), $matches)) {
                 $changed = TRUE;
                 $this->set($param, 'https://search.proquest.com/docview/' . $matches[1]); // User specific information
            }
            if (preg_match("~^https?://proquest\.umi\.com/.*$~", $this->get($param), $matches)) {
                 $ch = curl_init();
                 curl_setopt_array($ch,
                         [CURLOPT_FOLLOWLOCATION => TRUE,
                          CURLOPT_MAXREDIRS => 20,
                          CURLOPT_CONNECTTIMEOUT => 4,
                          CURLOPT_TIMEOUT => 25,
                          CURLOPT_RETURNTRANSFER => TRUE,
                          CURLOPT_COOKIEFILE => "",
                          CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
                          CURLOPT_URL => $matches[0]]);
                 if (@curl_exec($ch)) {
                    $redirectedUrl = (string) @curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);  // Final URL
                    if (preg_match("~^(https?://search\.proquest\.com/docview/\d{4,})(?:|/abstract.*|/fulltext.*|/preview.*)$~", $redirectedUrl, $matches) ||
                        preg_match("~^(https?://search\.proquest\.com/openurl/handler/.+)$~", $redirectedUrl, $matches)) {
                       $changed = TRUE;
                       $this->set($param, $matches[1]);
                       if (stripos($this->get('id'), 'Proquest Document ID') !== FALSE) $this->forget('id');
                    } elseif (preg_match("~^https?://search\.proquest\.com(?:|/)$~", $redirectedUrl)) {
                       $changed = TRUE;
                       report_forget('Proquest.umi.com URL does not work.  Forgetting');
                       $this->forget($param);
                    }
                 }
                 curl_close($ch);
            }
            if ($changed) report_info("Normalized ProQuest URL");
          }
          if ($param === 'url' && $this->wikiname() === 'cite book' && $this->should_url2chapter(FALSE)) {            
            $this->rename('url', 'chapter-url');
            $this->rename('format', 'chapter-format');
            $this->rename('url-access', 'chapter-url-access');
            // Comment out because "never used"  $param = 'chapter-url';
            return;
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
          if ($this->has('url') && str_replace(ENCYCLOPEDIA_WEB, '', $this->get('url')) != $this->get('url')) {
            $work_becomes = 'encyclopedia';
          }
          if ($this->has('work') && str_ireplace(['encyclopedia', 'encyclopædia', 'encyclopaedia'], '', $this->get('work')) != $this->get('work')) {
            $work_becomes = 'encyclopedia';
          }

          if ($this->has_but_maybe_blank($param) && $this->blank($work_becomes)) {
            if ($work_becomes === 'encyclopedia' && $this->wikiname() === 'cite web') {
              $this->change_name_to('cite encyclopedia');
            }
            if ($work_becomes !== 'encyclopedia' || in_array($this->wikiname(), ['cite dictionary', 'cite encyclopedia', 'citation'])) {
              $this->rename('work', $work_becomes); // encyclopedia=XYZ only valid in some citation types
            }
          }
          if ($this->wikiname() === 'cite book') {
            $publisher = strtolower($this->get($param));
            foreach (NON_PUBLISHERS as $not_publisher) {
              if (stripos($publisher, $not_publisher) !== FALSE) {
                $this->forget($param);
                return;
              }
            }
            if (stripos($publisher, 'amazon') !== FALSE) {
              $this->forget($param);
              return;
            }
          }
          return;
          
        case 'via':   // Should just remove all 'via' with no url, but do not want to make people angry
          if ($this->blank(ALL_URL_TYPES)) { // Include blank via
            if (stripos($this->get('via'), 'PubMed') !== FALSE && ($this->has('pmc') || $this->has('pmid'))) {
              $this->forget('via');
            } elseif (stripos($this->get('via'), 'JSTOR') !== FALSE && $this->has('jstor')) {
              $this->forget('via');
            } elseif (stripos($this->get('via'), 'google book') !== FALSE && $this->has('isbn')) {
              $this->forget('via');
            } elseif (stripos($this->get('via'), 'questia') !== FALSE && $this->has('isbn')) {
              $this->forget('via');
            } elseif (stripos($this->get('via'), 'library') !== FALSE) {
              $this->forget('via');
            } elseif (in_array($this->wikiname(), ['cite arxiv', 'cite biorxiv', 'cite citeseerx', 'cite ssrn'])) { 
              $this->forget('via');
            } elseif ($this->has('pmc') || $this->has('pmid') || ($this->has('doi') && $this->blank(DOI_BROKEN_ALIASES)) ||
                      $this->has('jstor') || $this->has('arxiv') || $this->has('isbn') || ($this->has('issn') && $this->has('title')) ||
                      $this->has('oclc') || $this->has('lccn') || $this->has('bibcode')) {
              $via = trim(strtolower($this->get('via')));
              if (in_array($via, ['', 'project muse', 'wiley', 'springer', 'questia', 'elsevier', 'wiley online library',
                                  'wiley interscience', 'interscience', 'sciencedirect', 'science direct', 'ebscohost',
                                  'proquest', 'google scholar', 'google', 'bing', 'yahoo'])) 
              { 
                $this->forget('via');
                return;
              }
            }            
          }
          if ($this->blank('via')) return;
          foreach (array_merge( array('publisher'), WORK_ALIASES) as $others) {
            if ($this->has($others)) {
              if (str_equivalent($this->get($others), $this->get('via')) ||
                  (stripos($this->get($others), 'bbc') !== FALSE && stripos($this->get('via'), 'bbc')) !== FALSE) {
                $this->forget('via');
                return;
              }
            }
          }
          return;
        case 'volume':
          if ($this->blank($param)) return;
          $temp_string = strtolower($this->get('journal')) ;
          if(substr($temp_string, 0, 2) === "[[" && substr($temp_string, -2) === "]]") {  // Wikilinked journal title 
               $temp_string = substr(substr($temp_string, 2), 0, -2); // Remove [[ and ]]
          }
          if (in_array($temp_string, HAS_NO_VOLUME)) {
            if ($this->blank(ISSUE_ALIASES)) {
              $this->rename('volume', 'issue');
            } else {
              $this->forget('volume');
            }
          }
          $this->volume_issue_demix($this->get($param), $param);
          return;
          
        case 'year':
          if ($this->blank($param)) {
            if ($this->has('date')) $this->forget('year');
            return;
          }
          if (preg_match("~\d\d*\-\d\d*\-\d\d*~", $this->get('year'))) { // We have more than one dash, must not be range of years.
             if ($this->blank('date')) $this->rename('year', 'date');
             $this->forget('year');
             return;
          }
          if (preg_match("~[A-Za-z][A-Za-z][A-Za-z]~", $this->get('year'))) { // At least three letters
             if ($this->blank('date')) $this->rename('year', 'date');
             $this->forget('year');
             return;
          }
          if (preg_match("~^(\d{4})\.$~", $this->get($param), $matches)) {
             $this->set($param, $matches[1]); // trailing period
             return;
          }
          if ($this->get($param) === 'n.d.') return; // Special no-date code that citation template recognize.
          // Issue should follow year with no break.  [A bit of redundant execution but simpler.]
        case 'issue':
        case 'number':
          if ($this->blank($param)) return;
          $value = trim($this->get($param));
          if ($param === 'issue' || $param === 'number') {
            if (preg_match('~^(?:iss\.|iss|issue|number|num|num\.|no|no:|no\.)\s*(\d+)$~i', $value, $matches)) {
              $value = $matches[1];
            }
          }
          // Remove leading zeroes
          if ($value && $this->get('journal') != 'Insecta Mundi') {
            $value = preg_replace('~^0+~', '', $value);
            if ($value === '') {
              $this->forget($param); // Was all zeros
            }
          }
          if ($value) {
            $this->set($param, $value);
          } else {
            if (!$this->blank($param)) $this->forget($param);
            return;
          }
          $this->volume_issue_demix($this->get($param), $param);
          if ($this->blank($param)) {
             $this->forget($param);
             return;
          }
          // No break here: pages, issue and year (the previous case) should be treated in this fashion.
        case 'pages': case 'page': case 'pp': # And case 'year': case 'issue':, following from previous
          $value = $this->get($param);
          if (str_i_same('null', $value)) {
            $this->forget($param);
            return;
          }
          if (strpos($value, "[//")  === 0) { // We can fix them, if they are the very first item
            $value = "[https://" . substr($value, 3);
            $this->set($param, $value);
          }
          if (preg_match('~^p\.?p\.?[(?:&nbsp;)\s]*(\d+[–-]?\d+)$~u' , $value, $matches)) {
            $value = $matches[1];
            $this->set($param, $value);
          }
          if (preg_match('~^[Pp]ages?[\.\:]? *(\d+[–-]?\d+)$~u' , $value, $matches)) {
            $value = $matches[1];
            $this->set($param, $value);
          }
          if (preg_match('~^p\.? *(\d+[–-]?\d+)$~u' , $value, $matches)) {
            $value = $matches[1];
            $this->set($param, $value);
          }
          if (!preg_match("~^[A-Za-z ]+\-~", $value) && mb_ereg(REGEXP_TO_EN_DASH, $value)
              && can_safely_modify_dashes($value) && ($pmatch[1] !== 'page')) {
            $this->mod_dashes = TRUE;
            report_modification("Upgrading to en-dash in " . echoable($param) .
                  " parameter");
            $value =  mb_ereg_replace(REGEXP_TO_EN_DASH, REGEXP_EN_DASH, $value);
            $this->set($param, $value);
          }
          if (   (mb_substr_count($value, "–") === 1) // Exactly one EN_DASH.  
              && can_safely_modify_dashes($value)) {
            if ($pmatch[1] === 'page') { 
              report_warning('Perhaps page= of ' . echoable($value) . ' is actually a page range.  If so, change to pages=, otherwise change minus sign to {{endash}}');
            } else {
              $the_dash = (int) mb_strpos($value, "–"); // ALL must be mb_ functions because of long dash
              $part1 = trim(mb_substr($value, 0, $the_dash));
              $part2 = trim(mb_substr($value, $the_dash + 1));
              if ($part1 === $part2) {
                $this->set($param, $part1);
              } elseif (is_numeric($part1) && is_numeric($part2)) {
                $this->set($param, $part1 . "–" . $part2); // Remove any extra spaces
              }
            }
          }
          if (strpos($this->get($param), '&') === FALSE) {
            $this->set($param, preg_replace("~^[.,;]*\s*(.*?)\s*[,.;]*$~", "$1", $this->get($param)));
          } else {
            $this->set($param, preg_replace("~^[.,;]*\s*(.*?)\s*[,.]*$~", "$1", $this->get($param))); // Not trailing ;
          }
          if (mb_substr($this->get($param), -4) === ' etc') {
            $this->set($param, $this->get($param) . '.');
          }
          return;
          
        case 'postscript':  // postscript=. is the default in CS1 templates.  It literally does nothing.
          if ($this->wikiname() !== 'citation') {
            if ($this->get($param) === '.') $this->forget($param); // Default action does not need specified
            if ($this->blank($param)) $this->forget($param);  // Misleading -- blank means period!!!!
          }
          return;
          
        case 'website':
          if (($this->wikiname() === 'cite book') && (str_i_same((string)$this->get($param), 'google.com') ||
                                                      str_i_same((string)$this->get($param), 'Google Books') ||
                                                      str_i_same((string)$this->get($param), 'Google Book') ||
                                                         stripos((string)$this->get($param), 'Books.google.') === 0)) {
            $this->forget($param);
          }
          if (stripos($this->get($param), 'archive.org') !== FALSE &&
              stripos($this->get('url') . $this->get('chapter-url') . $this->get('chapterurl'), 'archive.org') === FALSE) {
            $this->forget($param);
          }
          if (($this->wikiname() === 'cite arxiv') || $this->has('eprint') || $this->has('arxiv')) {
            if (str_i_same($this->get($param), 'arxiv')) {
              $this->forget($param);
            }
          }
          return;
         
        case 'publicationplace': case 'publication-place':
          if ($this->blank(['location', 'place'])) {
            $this->rename($param, 'location'); // This should only be used when 'location'/'place' is being used to describe where is was physically written, i.e. location=Vacationing in France|publication-place=New York
          }
          return;
          
        case 'publication-date': case 'publicationdate':
          if ($this->blank(['year', 'date'])) {
            $this->rename($param, 'date'); // When date and year are blank, this is displayed as date.  So convert
          }
          return;
      }
    }
  }
  
  public function tidy() : void {
    // Should only be run once (perhaps when template is first loaded)
    // Future tidying should occur when parameters are added using tidy_parameter.
    foreach ($this->param as $param) $this->tidy_parameter($param->param);
  }
  
  public function final_tidy() : void {
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $spacing = ['', '']; // prevent memory leak in some PHP versions
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
      // Double check these troublesome "journals"
      if ($this->is_book_series('journal') || $this->is_book_series('series') ||
          $this->is_book_series('chapter') || $this->is_book_series('title')) {
        $this->tidy_parameter('series');
        $this->tidy_parameter('journal');
        $this->tidy_parameter('title');
        $this->tidy_parameter('chapter');
      }
      // "Work is a troublesome parameter
      if ($this->has_but_maybe_blank('work') && $this->blank('work')) { // Have work=, but it is blank
         if ($this->has('journal') ||
             $this->has('newspaper') ||
             $this->has('magazine') ||
             $this->has('periodical') ||
             $this->has('website')) {
              $this->forget('work'); // Delete if we have alias
         } elseif ($this->wikiname() === 'cite web') {
            $this->forget('work'); // The likelihood of this being a good thing to add is very low
         } elseif ($this->wikiname() === 'cite journal') {
            $this->rename('work', 'journal');
         }
      }
      if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
        if ($this->has('title') || $this->has('chapter')) {
          $this->forget(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
        }
      }
      if ($this->has('doi') && $this->has('issue') && ($this->get('issue') == $this->get('volume')) && // Issue = Volume and not NULL
        ($this->get('issue') == $this->get_without_comments_and_placeholders('issue')) &&
        ($this->get('volume') == $this->get_without_comments_and_placeholders('volume'))) { // No comments to flag problems
        $crossRef = query_crossref($this->get_without_comments_and_placeholders('doi'));
        if ($crossRef) {
          $orig_data = trim($this->get('volume'));
           $possible_issue = trim((string) @$crossRef->issue);
           $possible_volume = trim((string) @$crossRef->volume);
           if ($possible_issue != $possible_volume) { // They don't match
             if ((strpos($possible_issue, '-') > 0 || (int) $possible_issue > 1) && (int) $possible_volume > 0) { // Legit data
               if ($possible_issue == $orig_data) {
                 $this->set('volume', $possible_volume);
                 report_action('Citation had volume and issue the same.  Changing volume.');
               } elseif ($possible_volume == $orig_data) {
                 $this->set('issue', $possible_issue);
                 report_action('Citation had volume and issue the same.  Changing issue.');
               } else {
                 report_inaction('Citation has volume and issue set to ' . echoable($orig_data) . ' which disagrees with CrossRef');  // @codeCoverageIgnore
               }
             }
           }
        }
      }
      $this->tidy_parameter('url'); // depending upon end state, convert to chapter-url
      $this->tidy_parameter('via');
      $this->tidy_parameter('publisher');
      if ($this->has('publisher') && preg_match("~^([\'\"]+)([^\'\"]+)([\'\"]+)$~u", $this->get('publisher'), $matches)) {
        if ($this->blank(WORK_ALIASES)) {
          $this->rename('publisher', 'work', $matches[2]);
          $this->tidy_parameter('work');
        } else {
          $this->set('publisher', $matches[2]);
          $this->tidy_parameter('publisher');
        }
      }
      if ($this->wikiname() === 'cite journal' && $this->blank(WORK_ALIASES) &&
          stripos($this->initial_name, 'journal') === FALSE) {
         if ($this->has('arxiv') || $this->has('eprint')) {
            $this->change_name_to('cite arxiv');
         } else {
            $this->change_name_to('cite document');
         }
      }
    }
    if ($this->wikiname() === 'cite arxiv' && $this->get_without_comments_and_placeholders('doi')) {
      $this->change_name_to('cite journal');
    }
    if ($this->wikiname() === 'cite arxiv' && $this->has('bibcode')) {
      $this->forget('bibcode'); // Not supported and 99% of the time just a arxiv bibcode anyway
    }
    if ($this->wikiname() === 'citation') { // Special CS2 code goes here
      if (!$this->blank_other_than_comments('title') && !$this->blank_other_than_comments('chapter') && !$this->blank_other_than_comments(WORK_ALIASES)) { // Invalid combination
          report_info('CS2 template has incompatible parameters.  Changing to CS1 cite book. Please verify.');
          if ($this->name === 'citation') { // Need special code to keep caps the same
            $this->name = 'cite book';
          } else {
            $this->name = 'Cite book';
          }
      }
    }
    if (!$this->blank(DOI_BROKEN_ALIASES) && $this->has('jstor') &&
        (strpos($this->get('doi'), '10.2307') === 0 ||  $this->get('doi') == $this->get('jstor'))) {
      $this->forget('doi'); // Forget DOI that is really jstor, if it is broken
      foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
    }
    if ($this->has('journal')) {  // Do this at the very end of work in case we change type/etc during expansion
          if ($this->blank(['chapter', 'isbn'])) {
            // Avoid renaming between cite journal and cite book
            $this->change_name_to('cite journal');
            // Remove blank stuff that will most likely never get filled in
            $this->forget('isbn');
            $this->forget('chapter');
            foreach (['location', 'place', 'publisher', 'publication-place', 'publicationplace'] as $to_drop) {
              if ($this->blank($to_drop)) $this->forget($to_drop);
            }
          } elseif (in_array(strtolower($this->get('journal')), array_merge(NON_PUBLISHERS, BAD_TITLES, DUBIOUS_JOURNALS, ['amazon.com']))) {
            report_forget('Citation has chapter/ISBN already, dropping dubious Journal title: ' . echoable($this->get('journal')));
            $this->forget('journal');
          } else {
            report_warning(echoable('Citation should probably not have journal = ' . $this->get('journal')
            . ' as well as chapter / ISBN ' . $this->get('chapter') . ' ' .  $this->get('isbn')));
          }
    }
    if ($this->wikiname() === 'cite book' && $this->blank(['issue', 'journal'])) {
       // Remove blank stuff that will most likely never get filled in
       $this->forget('issue');
       $this->forget('journal');
    }
    if (preg_match('~^10\.1093/ref\:odnb/\d+$~', $this->get('doi')) &&
        $this->has('title') &&
        $this->wikiname() !== 'cite encyclopedia' && 
        $this->wikiname() !== 'cite encyclopaedia') {
      preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
      if (substr($this->name,0,1) === 'c') {
        $this->name = $spacing[1] . 'cite ODNB' . $spacing[2];
      } else {
        $this->name = $spacing[1] . 'Cite ODNB' . $spacing[2];
      }
      foreach (array_diff(WORK_ALIASES, array('encyclopedia','encyclopaedia')) as $worker) {
        $this->forget($worker);
      }
      if (stripos($this->get('publisher'), 'oxford') !== FALSE) $this->forget('publisher');
    }
    if (preg_match('~^10\.1093/~', $this->get('doi')) &&
        $this->has('title') &&
        ($this->wikiname() === 'cite web' || $this->wikiname() === 'cite journal') &&
        $this->blank(WORK_ALIASES) && $this->blank('url')) {
      preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
      if (substr($this->name,0,1) === 'c') {
        $this->name = $spacing[1] . 'cite document' . $spacing[2];
      } else {
        $this->name = $spacing[1] . 'Cite document' . $spacing[2];
      }
    }
    if (!empty($this->param)) {
      $drop_me_maybe = array();
      foreach (ALL_ALIASES as $alias_list) {
        if (!$this->blank($alias_list)) { // At least one is set
          $drop_me_maybe = array_merge($drop_me_maybe, $alias_list);
        }
      }
      // Do it this way to avoid massive N*M work load (N=size of $param and M=size of $drop_me_maybe) which happens when checking if each one is blank
      foreach ($this->param as $p) {
        if (@$p->val === '' && in_array(@$p->param, $drop_me_maybe)) {
           $this->forget($p->param);
        }
      }
    }
    if (!empty($this->param)) { // Forget author-link and such that have no such author
      foreach ($this->param as $p) {
        $alias = $p->param;
        if ($alias != NULL && $this->blank($alias)) {
          if (preg_match('~^author(\d+)\-?link$~', $alias, $matches) || preg_match('~^author\-?link(\d+)$~', $alias, $matches)) {
            if ($this->blank(AUTHOR_PARAMETERS[(int) $matches[1]])) {
              $this->forget($alias);
            }
          }
        }
      }
    }
  }
  
  public function verify_doi() : bool {
    $match = ['', '']; // prevent memory leak in some PHP versions
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $doi = $this->get_without_comments_and_placeholders('doi');
    if (!$doi) return FALSE;
    if ($this->doi_valid) return TRUE;
    report_info("Checking that DOI " . echoable($doi) . " is operational...");

    $trial = array();
    $trial[] = $doi;
    // DOI not correctly formatted
    switch (substr($doi, -1)) {
      case ".":
        // Missing a terminal 'x'?
        $trial[] = $doi . "x";
      case ",": case ";": case "\"":
        // Or is this extra punctuation copied in?
        $trial[] = substr($doi, 0, -1);
    }
    if (substr($doi, 0, 3) != "10.") {
      if (substr($doi, 0, 2) === "0.") {
        $trial[] = "1" . $doi;
      } elseif (substr($doi, 0, 1) === ".") {
        $trial[] = "10" . $doi;
      } else {
        $trial[] = "10." . $doi;
      }
    }
    if (preg_match("~^(.+)(10\.\d{4,6}/.+)~", trim($doi), $match)) {
      $trial[] = $match[1];
      $trial[] = $match[2];
    }
    $replacements = array ("&lt;" => "<", "&gt;" => ">");
    if (preg_match("~&[lg]t;~", $doi)) {
      $trial[] = str_replace(array_keys($replacements), $replacements, $doi);
    }
    $changed = TRUE;
    $try = $doi;
    while ($changed) {
      $changed = FALSE;
      if ($pos = strrpos($try, '.')) {
       $extension = substr($try, $pos);
       if (in_array(strtolower($extension), array('.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml', '.full'))) {
         $try = substr($try, 0, $pos);
         $trial[] = $try;
         $changed = TRUE;
       }
      }
      if ($pos = strrpos($try, '#')) {
       $extension = substr($try, $pos);
       if (strpos(strtolower($extension), '#page_scan_tab_contents') === 0) {
         $try = substr($try, 0, $pos);
         $trial[] = $try;
         $changed = TRUE;
       }
      }
      if ($pos = strrpos($try, ';')) {
       $extension = substr($try, $pos);
       if (strpos(strtolower($extension), ';jsessionid') === 0) {
         $try = substr($try, 0, $pos);
         $trial[] = $try;
         $changed = TRUE;
       }
      }
      if ($pos = strrpos($try, '/')) {
       $extension = substr($try, $pos);
       if (in_array(strtolower($extension), array('/abstract', '/full', '/pdf', '/epdf', '/asset/', '/summary', '/short'))) {
         $try = substr($try, 0, $pos);
         $trial[] = $try;
         $changed = TRUE;
       }
      }
      if (preg_match('~^(.+)v\d{1,2}$~', $try, $matches)) { // Versions
         $try = $matches[1];
         $trial[] = $try;
         $changed = TRUE;
      }
    }
    foreach ($trial as $try) {
      // Check that it begins with 10.
      if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) $try = "10." . $match[1];
      if (doi_active($try)) {
        $this->set('doi', $try);
        $this->doi_valid = TRUE;
        foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
        if ($doi == $try) {
           report_inline('DOI ok.');
        } else {
           report_info("Modified DOI:  " . echoable($try) . " is operational...");
        }
        return TRUE;
      }
    }
    foreach ($trial as $try) {
      // Check that it begins with 10.
      if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) $try = "10." . $match[1];
      if (doi_works($try)) {
        $this->set('doi', $try);
        $this->doi_valid = TRUE;
        foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
        if ($doi == $try) {
           report_inline('DOI ok.');
        } else {
           report_info("Modified DOI:  " . echoable($try) . " is operational...");
        }
        return TRUE;
      }
    }
    $doi_status = doi_works($doi);
    if ($doi_status === NULL) {
      report_warning("DOI status unknown.  doi.org failed to respond to: " . doi_link($doi));  // @codeCoverageIgnore
      return FALSE;                                                                            // @codeCoverageIgnore
    } elseif ($doi_status === FALSE) {
      report_inline("It's not...");
      $this->add_if_new('doi-broken-date', date("Y-m-d"));
      return FALSE;
    } else {
      // Only get to this code if we got NULL earlier and now suddenly get OK
      // @codeCoverageIgnoreStart
      foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
      $this->doi_valid = TRUE;
      report_inline('DOI ok.');
      return TRUE;
      // @codeCoverageIgnoreEnd
    }
  }
  
  /* function handle_et_al
   * To preserve user-input data, this function will only be called
   * if no author parameters were specified at the start of the 
   * expansion process.
  */
  public function handle_et_al() : void {
    foreach (AUTHOR_PARAMETERS as $author_cardinality => $group) {
      foreach ($group as $param) {
        if (strpos($this->get($param), 'et al') !== FALSE) { // Have to deal with 0 != FALSE
          // remove 'et al' from the parameter value if present
          $val_base = preg_replace("~,?\s*'*et al['.]*~", '', $this->get($param));
          if ($author_cardinality == 1) {
            // then we (probably) have a list of authors joined by commas in our first parameter
            if (under_two_authors($val_base)) {
              $this->set($param, $val_base);
              if ($param == 'authors' && $this->blank('author')) {
                $this->rename('authors', 'author');
                $param = 'author';
              }
            } else {
              $this->forget($param);
              $authors = split_authors($val_base);
              foreach ($authors as $i => $author_name) {
                $this->add_if_new('author' . (string)((int) $i + 1), format_author($author_name));
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
  protected function display_authors() : int {
    if (($da = $this->get('display-authors')) == '') {
      $da = $this->get('displayauthors');
    }
    return ctype_digit($da) ? (int) $da : 0;
  }

  protected function number_of_authors() : int {
    $matches = ['', '']; // prevent memory leak in some PHP versions
    $max = 0;
    foreach ($this->param as $p) {
      if (preg_match('~(?:author|last|first|forename|initials|surname|given)(\d+)~', $p->param, $matches)) {
        if (stripos($p->param, 'editor') === FALSE) $max = max((int) $matches[1], $max);
      }
    }
    if ($max === 0) {
      foreach ($this->param as $p) {
        if (preg_match('~(?:author|last|first|forename|initials|surname|given)$~', $p->param)) {
          if (stripos($p->param, 'editor') === FALSE) return 1;
        }
      }
    }
    return $max;
  }
  
  // Retrieve properties of template
  public function first_author() : string {
    foreach (array('author', 'author1', 'authors', 'vauthors') as $auth_param) {
      $author = $this->get($auth_param);
      if ($author) return $author;
    }
    $forenames = $this->get('given') . $this->get('first') . $this->get('forename') . $this->get('initials') .
      $this->get('given1') . $this->get('first1') . $this->get('forename1') . $this->get('initials1');
    foreach (array('last', 'surname', 'last1', 'surname1') as $surname_param) {
      $surname = $this->get($surname_param);
      if ($surname) {
        return ($surname . ', ' . $forenames);
      }
    }
    return '';
  }

  public function initial_author_params() : array { return $this->initial_author_params; }
  
  protected function first_surname() : string {
    $first_author = ['', '']; // prevent memory leak in some PHP versions
    // Fetch the surname of the first author only
    if (preg_match("~[^.,;\s]{2,}~u", $this->first_author(), $first_author)) {
      return $first_author[0];
    } else {
      return '';
    }
  }

  protected function page() : string {
    if ($this->has('pages')) {
      $page = $this->get('pages');
    } else {
      $page = $this->get('page');
    }
    $page = str_replace(['&mdash;', '--', '&ndash;', '—', '–'], ['-','-','-','-','-'], $page);
    return $page;
  }
  
  protected function year() : string {
    $matches = ['', '']; // prevent memory leak in some PHP versions
    if ($this->has('year')) {
      return $this->get('year');
    }
    if ($this->has('date')) {
       $date = $this->get('date');
       if (preg_match("~^\d{4}$~", $date)) {
         return $date; // Just a year
       } elseif (preg_match("~^(\d{4})[^0-9]~", $date, $matches)) {
         return $matches[1]; // Start with year
       } elseif (preg_match("~[^0-9](\d{4})$~", $date, $matches)) {
         return $matches[1]; // Ends with year
       }
    }
    return '';
  }

  public function name() : string {return trim($this->name);}

  protected function page_range() : ?array {
    $pagenos = ['', '']; // prevent memory leak in some PHP versions
    preg_match("~(\w?\w?\d+\w?\w?)(?:\D+(\w?\w?\d+\w?\w?))?~", $this->page(), $pagenos);
    return $pagenos;
  }

  // Amend parameters
  public function rename(string $old_param, string $new_param, ?string $new_value = NULL) : void {
    if (empty($this->param)) return;
    if ($old_param == $new_param) {
       if ($new_value !== NULL) {
           $this->set($new_param, $new_value);
           return;
        }
        return;
    }
    $have_nothing = TRUE;
    foreach ($this->param as $p) {
      if ($p->param == $old_param) {
        $have_nothing = FALSE;
        break;
      }
    }
    if ($have_nothing) {
       if ($new_value !== NULL) {
          $this->set($new_param, $new_value);
          return;
       }
       return;
    }
    // Forget old copies
    $pos = $this->get_param_key($new_param);
    while ($pos !== NULL) {
      unset($this->param[$pos]);
      $pos = $this->get_param_key($new_param);
    }
    foreach ($this->param as $p) {
      if ($p->param == $old_param) {
        $p->param = $new_param;
        if ($new_value !== NULL) {
          $p->val = $new_value;
        }
        if (strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_year') === FALSE &&
            strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_date') === FALSE &&
            strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_title') === FALSE &&
            strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_journal') === FALSE) {
          report_modification("Renamed \"" . echoable($old_param) . "\" -> \"" . echoable($new_param) . "\"");
          $this->mod_names = TRUE;
        }
        $this->tidy_parameter($new_param);
      }
    }
  }

  public function get(string $name) : string {
    // NOTE $this->param and $p->param are different and refer to different types!
    // $this->param is an array of Parameter objects
    // $parameter_i->param is the parameter name within the Parameter object
    foreach ($this->param as $parameter_i) {
      if ($parameter_i->param === $name) {
        if ($parameter_i->val === NULL) $parameter_i->val = ''; // Clean up
          return $parameter_i->val;
      }
    }
    return '';
  }
  // This one is used in the test suite to distinguish there-but-blank vs not-there-at-all
  public function get2(string $name) : ?string {
    foreach ($this->param as $parameter_i) {
      if ($parameter_i->param === $name) {
        if ($parameter_i->val === NULL) $parameter_i->val = ''; // Clean up
          return $parameter_i->val;
      }
    }
    return NULL;
  }

  public function has_but_maybe_blank(string $name) : bool {
    foreach ($this->param as $parameter_i) {
      if ($parameter_i->param === $name) {
         return TRUE;
      }
    }
    return FALSE;
  }
  
  protected function param_with_index(int $i) : ?Parameter {
    return (isset($this->param[$i])) ? $this->param[$i] : NULL;
  }
  
  protected function param_value(int $i) : string {
    $item = $this->param_with_index($i);
    if (is_null($item)) return ''; // id={{arxiv}} and other junk
    return (string) $item->val;
  }
  
  public function get_without_comments_and_placeholders(string $name) : string {
    $ret = $this->get($name);
    $ret = preg_replace('~<!--.*?-->~su', '', $ret); // Comments
    $ret = preg_replace('~# # # CITATION_BOT_PLACEHOLDER.*?# # #~sui', '', $ret); // Other place holders already escaped.  Case insensitive
    $ret = str_replace("\xc2\xa0", ' ', $ret); // Replace non-breaking with breaking spaces, which are trimmable
    $ret = trim($ret);
    return $ret;
  }

  protected function get_param_key (string $needle) : ?int {
    if (empty($this->param)) return NULL;
    foreach ($this->param as $i => $p) {
      if ($p->param == $needle) return $i;
    }
    return NULL;
  }

  public function has(string $par) : bool {
    return (bool) strlen($this->get($par));
  }

  public function add(string $par, string $val) : bool {
    report_add(echoable("Adding $par: $val"));
    $could_set = $this->set($par, $val);
    $this->tidy_parameter($par);
    return $could_set;
  }
  
  public function set(string $par, string $val) : bool {
    if ($par === '') report_error('NULL parameter passed to set with value of ' . echoable($val));
    if (mb_stripos($this->get((string) $par), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
      return FALSE;
    }
    if (($pos = $this->get_param_key((string) $par)) !== NULL) {
      $this->param[$pos]->val = (string) $val;
      return TRUE;
    }
    $p = new Parameter();
    $p->parse_text((string) $this->example_param); // cast to make static analysis happy
    $p->param = (string) $par;
    $p->val = (string) $val;
    
    $insert_after = prior_parameters((string) $par);
    $prior_pos_best = -1;
    foreach (array_reverse($insert_after) as $after) {
      if (($after_key = $this->get_param_key($after)) !== NULL) {
        $keys = array_keys($this->param);
        for ($prior_pos = 0; $prior_pos < count($keys); $prior_pos++) {
          if ($keys[$prior_pos] == $after_key) {
            if($prior_pos > $prior_pos_best) $prior_pos_best = $prior_pos;
            break; 
          }
        }
      }
    }
    $prior_pos = $prior_pos_best;
    if ($prior_pos > -1) {
        $this->param = array_merge(
          array_slice($this->param, 0, $prior_pos + 1), 
          array($p),
          array_slice($this->param, $prior_pos + 1));
        return TRUE;
    }
    $this->param[] = $p;
    return TRUE;
  }

  public function append_to(string $par, string $val) : bool {
    if (mb_stripos($this->get($par), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
      return FALSE;
    }
    $pos = $this->get_param_key($par);
    if ($pos !== NULL) { // Could be zero which is "FALSE"
      $this->param[$pos]->val = $this->param[$pos]->val . $val;
      return TRUE;
    } else {
      $this->set($par, $val);
      return TRUE;
    }
  }

  public function quietly_forget(string $par) : void {
    $this->forgetter($par, FALSE);
  }
  public function forget(string $par) : void {
    $this->forgetter($par, TRUE);
  }
  private function forgetter(string $par, bool $echo_forgetting) : void { // Do not call this function directly
   $spacing = ['', '']; // prevent memory leak in some PHP versions
   if (!$this->blank($par)) { // do not remove all this other stuff if blank
    if ($par == 'url') {
      if ($this->blank(array_diff(ALL_URL_TYPES, array($par)))) {
        $this->forgetter('archive-url', $echo_forgetting);
        $this->forgetter('archiveurl', $echo_forgetting);
        $this->forgetter('accessdate', $echo_forgetting);
        $this->forgetter('access-date', $echo_forgetting);
      }
      $this->forgetter('format', $echo_forgetting);
      $this->forgetter('registration', $echo_forgetting);
      $this->forgetter('subscription', $echo_forgetting);
      $this->forgetter('url-access', $echo_forgetting);
      $this->forgetter('deadurl', $echo_forgetting);
      $this->forgetter('url-status', $echo_forgetting);
      if ($this->has('work') && stripos($this->get('work'), 'www.') === 0) {
         $this->forgetter('work', $echo_forgetting);
      }
      if ($this->blank(array_diff(WORK_ALIASES, ['website'])) && bad_10_1093_doi($this->get('doi'))) {
        if ($this->has('via') && $this->blank('website')) {
          $this->rename('via', 'work');
        } elseif ($this->has('website') && $this->blank('via')) {
          $this->rename('website', 'work');
        } elseif ($this->has('website') && $this->has('via')) {
          if (titles_are_similar($this->get('website'), $this->get('via'))) {
            $this->forgetter('via', $echo_forgetting);
            $this->rename('website', 'work');
          } else {
            $tmp = $this->get('website') . ' via ' . $this->get('via');
            $this->forgetter('via', $echo_forgetting);
            $this->rename('website', 'work', $tmp);
          }
        }
        preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
        if (substr($this->name,0,1) === 'c') {
          $this->name = $spacing[1] . 'cite document' . $spacing[2];
        } else {
          $this->name = $spacing[1] . 'Cite document' . $spacing[2];
        }
      }
      $this->forgetter('via', $echo_forgetting);
      $this->forgetter('website', $echo_forgetting);
    }
    if ($par == 'chapter' && $this->blank('url')) {
      if($this->has('chapter-url')) {
        $this->rename('chapter-url', 'url');
        $this->rename('chapter-format', 'format');
        $this->rename('chapter-url-access', 'url-access');
      } elseif ($this->has('chapterurl')) {
        $this->rename('chapterurl', 'url');
        $this->rename('chapter-format', 'format');
        $this->rename('chapter-url-access', 'url-access');
      }
    }
    if ($par == 'chapter-url' || $par == 'chapterurl') {
       $this->forgetter('chapter-format', $echo_forgetting);
       $this->forgetter('chapter-url-access', $echo_forgetting);
       if ($this->blank(array_diff(ALL_URL_TYPES, array($par)))) {
        $this->forgetter('accessdate', $echo_forgetting);
        $this->forgetter('access-date', $echo_forgetting);
        $this->forgetter('archive-url', $echo_forgetting);
        $this->forgetter('archiveurl', $echo_forgetting);
       }
    }
   }  // even if blank try to remove
    if ($par == 'doi') {
      foreach (DOI_BROKEN_ALIASES as $broke) {
        $this->forgetter($broke, FALSE);
      }
    }
    if ($par == 'archive-url' && $this->blank('archiveurl')) {
        $this->forgetter('archive-date', FALSE);
        $this->forgetter('archivedate', FALSE);
        $this->forgetter('dead-url', FALSE);
    }
    if ($par == 'archiveurl' && $this->blank('archive-url')) {
        $this->forgetter('archive-date', FALSE);
        $this->forgetter('archivedate', FALSE);
        $this->forgetter('dead-url', FALSE);
    }
    $pos = $this->get_param_key($par);
    if ($pos !== NULL) {
      if ($echo_forgetting && $this->has($par) && stripos($par, 'CITATION_BOT_PLACEHOLDER') === FALSE) {
        // Do not mention forgetting empty parameters or internal temporary parameters
        report_forget("Dropping parameter \"" . echoable($par) . '"');
      }
      while ($pos !== NULL) { // paranoid
        unset($this->param[$pos]);
        $pos = $this->get_param_key($par);
      }
    }
    if (strpos($par, 'url') !== FALSE && $this->wikiname() === 'cite web' &&
        $this->blank(array_diff(ALL_URL_TYPES, array($par)))) {
       if ($this->has('journal')) {
         $this->change_name_to('cite journal');
       } elseif ($this->has('newspaper')) {
         $this->change_name_to('cite news');
       } elseif (!$this->blank(['isbn', 'lccn', 'oclc', 'ol', 'chapter'])) {
         $this->change_name_to('cite book');
       } elseif ($this->has('arxiv') || $this->has('eprint')) {
         $this->change_name_to('cite arxiv');
       } else {
         $this->change_name_to('cite document');
       }
    }
  }

  public function modifications() : array {
    if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) return array();
    $new = array();
    $ret = array();
    foreach ($this->param as $p) {
      $new[$p->param] = $p->val;
    }

    $old = ($this->initial_param) ? $this->initial_param : array();
    
    $old['template type'] = trim($this->initial_name);
    $new['template type'] = trim($this->name);

    // Do not call ISSN to issn "Added issn, deleted ISSN"
    $old = array_change_key_case($old, CASE_LOWER);
    $new = array_change_key_case($new, CASE_LOWER);
    
    $ret['modifications'] = array_keys(array_diff_assoc($new, $old));
    $ret['additions'] = array_diff(array_keys($new), array_keys($old));
    $ret['deletions'] = array_diff(array_keys($old), array_keys($new));
    $ret['changeonly'] = array_diff($ret['modifications'], $ret['additions']);
    foreach ($ret['deletions'] as $inds=>$vals) {
       if ($vals === '') unset($ret['deletions'][$inds]); // If we get rid of double pipe that appears as a deletion, not misc.
    }

    $ret['dashes'] = $this->mod_dashes;
    $ret['names'] = $this->mod_names;
    return $ret;
  }

  protected function isbn10Toisbn13(string $isbn10, bool $ignore_year = FALSE) : string {
    $isbn10 = trim($isbn10);  // Remove leading and trailing spaces
    $test = str_replace(array('—', '?', '–', '-', '?', ' '), '', $isbn10);
    if (strlen($test) < 10 || strlen ($test) > 13) return $isbn10;
    if (preg_match("~^[0-9Xx ]+$~", $isbn10) === 1) { // Uses spaces
      $isbn10 = str_replace(' ', '-', $isbn10);
    }
    $isbn10 = str_replace(array('—', '?', '–', '-', '?'), '-', $isbn10); // Standardize dahses : en dash, horizontal bar, em dash, minus sign, figure dash, to hyphen.
    if (preg_match("~[^0-9Xx\-]~", $isbn10) === 1)  return $isbn10;  // Contains invalid characters
    if (substr($isbn10, -1) === "-" || substr($isbn10, 0, 1) === "-") return $isbn10;  // Ends or starts with a dash
    if ((intval($this->year()) < 2007) && !$ignore_year) return $isbn10; // Older books does not have ISBN-13, see [[WP:ISBN]]
    $isbn13 = str_replace('-', '', $isbn10);  // Remove dashes to do math
    if (strlen($isbn13) !== 10) return $isbn10;  // Might be an ISBN 13 already, or rubbish
    $isbn13 = '978' . substr($isbn13, 0, -1);  // Convert without check digit - do not need and might be X
    if (preg_match("~[^0123456789]~", $isbn13) === 1)  return $isbn10;  // Not just numbers
    $sum = 0;
    for ($count=0; $count<12; $count++ ) {
      $sum = $sum + intval($isbn13[$count])*($count%2?3:1);  // Depending upon even or odd, we multiply by 3 or 1 (strange but true)
    }
    $sum = ((10-$sum%10)%10) ;
    $isbn13 = '978' . '-' . substr($isbn10, 0, -1) . (string) $sum; // Assume existing dashes (if any) are right
    quietly('report_modification', "Converted ISBN10 to ISBN13");
    return $isbn13;
  }
  
  protected function inline_doi_information() : ?array {
    if ($this->name !== "doi-inline") return NULL;
    if (count($this->param) !==2) return NULL;
    $vals   = array();
    $vals[0] = $this->param[0]->parsed_text();
    $vals[1] = $this->param[1]->parsed_text();
    return $vals;
  }
  
  protected function get_inline_doi_from_title() : void {
     $match = ['', '']; // prevent memory leak in some PHP versions
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
  
  protected function volume_issue_demix(string $data, string $param) : void {
     $matches = ['', '']; // prevent memory leak in some PHP versions
     if ($param === 'year') return;
     if (!in_array($param, ['volume','issue','number'])) {
       report_error('volume_issue_demix ' . echoable($param)); // @codeCoverageIgnore
     }
     if ($param === 'issue') {
         $the_issue = 'issue';
     } elseif ($param === 'number') {
         $the_issue = 'number';
     } elseif ($param === 'volume' && $this->has('number')) {
         $the_issue = 'number';
     } else {
         $the_issue = 'issue';
     }
     $data = trim($data);
     if (preg_match("~^(\d+)\s*\((\d+(-|–|\–|\{\{ndash\}\})?\d*)\)$~", $data, $matches) ||
              preg_match("~^(?:vol\. |Volume |vol |)(\d+)[,\s]\s*(?:no\.|number|issue|Iss.|no )\s*(\d+(-|–|\–|\{\{ndash\}\})?\d*)$~i", $data, $matches) ||
              preg_match("~^(\d+)\.(\d+)$~i", $data, $matches) ||
              preg_match("~^Vol\.?(\d+)\((\d+)\)$~", $data, $matches)
         ) {
         $possible_volume=$matches[1];
         $possible_issue=$matches[2];
         if (preg_match("~^\d{4}.\d{4}$~", $possible_issue)) return; // Range of years
         if ($possible_issue === $this->get('year')) return;
         if ($possible_issue === $this->get('date')) return;
         if ($param == 'volume') {
            if ($this->blank(ISSUE_ALIASES)) {
              $this->add_if_new($the_issue, $possible_issue);
              $this->set('volume', $possible_volume);
            } elseif ($this->get('issue') === $possible_issue || $this->get('number') === $possible_issue) {
              $this->set('volume', $possible_volume);
            }
         } else {
            if ($this->blank('volume')) {
              $this->set($the_issue, $possible_issue);
              $this->add_if_new('volume', $possible_volume);
            } elseif ($this->get('volume') === $possible_volume) {
              $this->set($the_issue, $possible_issue);
            }
         }
     } elseif (preg_match('~^\((\d+)\)\.?$~', $data, $matches)) {
       $this->set($param, $matches[1]);
       return;
     } elseif (preg_match('~^(\d+)\.$~', $data, $matches)) {
       $this->set($param, $matches[1]); // remove period
       return;
     }
// volume misuse seems to be popular in cite book, and we would need to move volume to title
     // Obvious books
     if ($this->wikiname() === 'cite book') return;
     if ($this->wikiname() === 'citation' && ($this->has('chapter') || $this->has('isbn') || strpos($this->rawtext, 'archive.org') !== FALSE)) return;
     // Might not be a journal
     if (!in_array($this->wikiname(), ['citation', 'cite journal', 'cite web', 'cite magazine']) &&
         $this->get_without_comments_and_placeholders('issue') == '' &&
         $this->get_without_comments_and_placeholders('number') == '' &&
         $this->get_without_comments_and_placeholders('journal') == '' &&
         $this->get_without_comments_and_placeholders('magazine') == '') return;
     if ($param === 'volume') {
       if (preg_match("~^(?:vol\.|volume\s+|vol\s+|vol:)\s*([\dLXVI]+)$~i", $data, $matches)) {
         $data = $matches[1];
         $this->set('volume', $data);
       } elseif (preg_match("~^v\.\s+(\d+)$~i", $data, $matches)) {
         $data = $matches[1];
         $this->set('volume', $data);
       }
     }
     if ($param === 'issue' || $param === 'number') {
       if (preg_match("~^(?:num\.|number\s+|num\s+|num:|number:|iss\.|issue\s+|iss\s+|iss:|issue:)\s*([\dLXVI]+)$~i", $data, $matches)) {
         $data = $matches[1];
         $this->set($param, $data);
       }
     }
     if (!$this->blank(['doi', 'jstor', 'pmid', 'pmc'])) { // Have some data to fix it up with
       if ($param === 'issue' || $param === 'number') {
         if (preg_match("~^(?:vol\.|volume\s+|vol\s+|vol:)\s*([\dLXVI]+)$~i", $data, $matches)) {
           $data = $matches[1];
           if ($this->blank('volume')) {
             $this->rename($param, 'volume', $data);
           } elseif (stripos($this->get('volume'), $data) !== FALSE) {
             $this->forget($param); // Duplicate data
           }
         }
       }
       if ($param === 'volume') {
         if (preg_match("~^(?:num\.|number\s+|num\s+|num:|number:|iss\.|issue\s+|iss\s+|iss:|issue:)\s*([\dLXVI]+)$~i", $data, $matches)) {
           $data = $matches[1];
           if ($this->blank(['issue', 'number'])) {
             $this->rename($param, 'issue', $data);
           } elseif (stripos($this->get('issue') . $this->get('number'), $data) !== FALSE) {
             $this->forget($param); // Duplicate data
           }
         }
       }
     }
  }
                         
  protected function simplify_google_search(string $url) : string {
      if (stripos($url, 'q=') === FALSE) return $url;  // Not a search
      if (preg_match('~^https?://.*google.com/search/~', $url)) return $url; // Not a search if the slash is there
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
          case "as": case "useragent": case "as_brr":
          case "ei": case "ots": case "sig": case "source": case "lr":
          case "sa": case "oi": case "ct": case "id":  case "cd":
          case "oq": case "rls": case "sourceid": case "ved":
          case "aqs": case "gs_l": case "uact": case "tbo": case "tbs":
          case "num": case "redir_esc": case "gs_lcp": case "sxsrf":
          case "gfe_rd": case "gws_rd":
             break;
          case "rct":
             if (str_i_same($part_start[1], 'j')) break;  // default
             $url .=  $part . "&" ;
             break;
          case "ie": case "oe":
             if (str_i_same($part_start[1], 'utf-8')) break;  // UTF-8 is the default
             $url .=  $part . "&" ;
             break;
          case "hl": case "safe": case "q": case "tbm":
             $url .=  $part . "&" ;
             break;
          default:
             // @codeCoverageIgnoreStart
             report_minor_error("Unexpected Google URL component:  " . echoable($part));
             $url .=  $part . "&" ;
             break;
             // @codeCoverageIgnoreEnd
        }
      }

      if (substr($url, -1) === "&") $url = substr($url, 0, -1);  //remove trailing &
      $url= $url . $hash;
      return $url;
  }
  
  public function use_issn() : bool {
    $matches = ['', '']; // prevent memory leak in some PHP versions
    if ($this->blank('issn')) return FALSE; // Nothing to use
    if (!$this->blank(WORK_ALIASES)) return FALSE; // Nothing to add
    if ($this->has('series')) return FALSE; // Dangerous risk of duplication and most likely a series of "books"
    if ($this->wikiname() === 'cite book' && $this->has('isbn')) return FALSE; // Probably a series of "books"
    $issn = $this->get('issn');
    if ($issn === '9999-9999') return FALSE; // Fake test suite data
    if (!preg_match('~^\d{4}.?\d{3}[0-9xX]$~u', $issn)) return FALSE;
    $html = @file_get_contents('https://www.worldcat.org/issn/' . $issn);
    if (preg_match('~<title>(.*)\(e?Journal~', $html, $matches)) {
      $the_name = trim($matches[1]);
      if ($issn === '0027-8378') { // Special Cases, better than The Nation : A Weekly Journal Devoted to Politics, Literature, Science, Drama, Music, Art, and Finance
         $the_name = 'The Nation';
      }
      if ($this->wikiname() === 'cite magazine') {
        return $this->add_if_new('magazine', $the_name);  // @codeCoverageIgnore
      } else {   
        return $this->add_if_new('journal', $the_name);   // Might be newspaper, hard to tell.
      }
      // @codeCoverageIgnoreStart
    } elseif (preg_match('~<title>(.*)</title>~', $html, $matches)) {
      $wonky = trim($matches[1]);
      if ($wonky === "[WorldCat.org]") {
        report_info('WorldCat temporarily unresponsive or does not have Title for ISSN ' .  echoable($issn));
      } elseif (preg_match('~^(.+)\. \(e?Newspaper, \d{4}\) \[WorldCat.org\]$~', $wonky, $matches)) {
        return $this->add_if_new('newspaper', trim($matches[1]));
      } else {
        report_minor_error('Unexpected title from WorldCat for ISSN ' . echoable($issn) . ' : ' . echoable($wonky));
      }
    }
    return FALSE;
    // @codeCoverageIgnoreEnd
  }
    
  private function is_book_series(string $param) : bool {
    $simple = trim(str_replace(['-', '.',  '   ', '  '], [' ', ' ', ' ', ' '], strtolower($this->get($param))));
    return in_array($simple, JOURNAL_IS_BOOK_SERIES);
  }
  
  private function should_url2chapter(bool $force) : bool {
    if ($this->has('chapterurl')) return FALSE;
    if ($this->has('chapter-url')) return FALSE;
    if ($this->has('trans-chapter')) return FALSE;
    if ($this->blank('chapter')) return FALSE;
    if (strpos($this->get('chapter'), '[') !== FALSE) return FALSE;
    $url = $this->get('url');
    if (stripos($url, 'google') && !strpos($this->get('url'), 'pg=')) return FALSE; // Do not move books without page numbers
    if (stripos($url, 'archive.org/details/isbn')) return FALSE;
    if (stripos($url, 'page_id=0')) return FALSE;
    if (stripos($url, 'page=0')) return FALSE;
    if (substr($url, -2) === '_0') return FALSE;
    if (preg_match('~archive\.org/details/[^/]+$~', $url)) return FALSE;
    if (stripos($url, 'PA1') && !preg_match('~PA1[0-9]~i', $url)) return FALSE;
    if (stripos($url, 'PA0')) return FALSE;
    if ($this->get_without_comments_and_placeholders('chapter') == '') return FALSE;
    if (stripos($url, 'archive.org')) {
      if (strpos($url, 'chapter')) return TRUE;
      if (strpos($url, 'page')) {
        if (preg_match('~page/?[01]?$~i', $url)) return FALSE;
        return TRUE;
      }
      return FALSE;
    }
    if (stripos($url, 'wp-content')) { // Private websites are hard to judge
      if (stripos($url, 'chapter') || stripos($url, 'section')) return TRUE;
      if (stripos($url, 'pages') && !preg_match('~[^\d]1[-–]~u', $url)) return TRUE;
      return FALSE;
    }
    if ($force) return TRUE;
    // Only do a few select website unless we just converted to cite book from cite journal
    if (strpos($url, 'archive.org')) return TRUE;
    if (strpos($url, 'google.com')) return TRUE;
    if (strpos($url, 'www.sciencedirect.com/science/article')) return TRUE;
    return FALSE;
  }
}
