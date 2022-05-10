<?php
declare(strict_types=1);
/*
 * Page contains methods that will do most of the higher-level work of expanding citations
 * on the wikipage associated with the Page object.
 * Provides functions to read, parse, expand text (using Template and Comment)
 * handle collected page modifications, and save the edited page text
 * to the wiki.
 */

require_once 'Comment.php';       // @codeCoverageIgnore
require_once 'Template.php';      // @codeCoverageIgnore
require_once 'apiFunctions.php';  // @codeCoverageIgnore
require_once 'expandFns.php';     // @codeCoverageIgnore
require_once 'user_messages.php'; // @codeCoverageIgnore
require_once 'Zotero.php';        // @codeCoverageIgnore
require_once 'constants.php';     // @codeCoverageIgnore

class Page {

  protected string $text = '';
  protected string $title = '';
  protected array $modifications = array();
  protected int $date_style = DATES_WHATEVER;
  protected string $read_at = '';
  protected string $start_text = '';
  protected int $lastrevid = 0;
  protected bool $page_error = FALSE;

  function __construct() { 
      $this->construct_modifications_array();
  }

  public function get_text_from(string $title) : bool {
    $this->construct_modifications_array(); // Could be new page

    $details = WikipediaBot::ReadDetails($title);

    if (!isset($details->query)) {
      // @codeCoverageIgnoreStart
      $message = "Error: Could not fetch page.";
      if (isset($details->error)) $message .= "  " . $details->error->info;
      report_warning($message);
      return FALSE;
      // @codeCoverageIgnoreEnd
    }
    foreach ($details->query->pages as $p) {
      $my_details = $p;
    }
    if (!isset($my_details)) {
      report_warning("Page fetch error - could not even get details"); // @codeCoverageIgnore
      return FALSE;                                                    // @codeCoverageIgnore
    }
    $this->read_at = isset($details->curtimestamp) ? $details->curtimestamp : NULL;
    
    $details = $my_details;
    if (isset($details->invalid)) {
      report_warning("Page invalid: " . (isset($details->invalidreason) ? (string) $details->invalidreason : ''));
      return FALSE;
    }
    if ( !isset($details->touched) || !isset($details->lastrevid)) {
       report_warning("Could not even get the page.  Perhaps non-existent?");
       return FALSE;
    }
    
    if (!isset($details->title)) {
       report_warning("Could not even get the page title.");  // @codeCoverageIgnore
       return FALSE;                                          // @codeCoverageIgnore
    }
    
    if (isset($details->protection) && !empty($details->protection)) {
       $the_protections = (array) $details->protection;
       foreach ($the_protections as $protects) {
         if (isset($protects->type) && (string) $protects->type === "edit" && isset($protects->level)) {
           $the_level = (string) $protects->level;
           if (in_array($the_level, ["autoconfirmed", "extendedconfirmed"])) {
             ;  // We are good
           } elseif (in_array($the_level, ["sysop", "templateeditor"])) {
             report_warning("Page is protected.");
             return FALSE;
           } else {
             report_minor_error("Unexpected protection status: " . $the_level);  // @codeCoverageIgnore
           }
         }
       }
    }

    $this->title = (string) $details->title;
    $this->lastrevid = (int) $details->lastrevid ;

    $this->text = WikipediaBot::GetAPage($title);

    if ($this->text == '') {
       report_warning('Page '  . $title . ' from ' . str_replace(['/w/index.php', 'https://'], ['',''], WIKI_ROOT) . ' appears to be empty '); // @codeCoverageIgnore
       return FALSE;                                                                          // @codeCoverageIgnore
    }
    $this->start_text = $this->text;
    $this->set_date_pattern();

    if (preg_match('~\#redirect *\[\[~i', $this->text)) {
      report_warning("Page is a redirect.");
      return FALSE;
    }
    return TRUE;
  }
  
  public function parse_text(string $text) : void {
    $this->construct_modifications_array(); // Could be new page
    $this->text = $text;
    $this->start_text = $this->text;
    $this->set_date_pattern();
    $this->title = '';
    $this->read_at = '';
    $this->lastrevid = 0;
  }
 
  public function parsed_text() : string {
    return (string) $this->text;
  }
  
  // $parameter: parameter to send to api_function, e.g. "pmid"
  // $templates: Array of pointers to the templates
  // $api_function: string naming a function (specified in apiFunctions.php) 
  //                that takes the value of $templates->get($identifier) as an array;
  //                returns key-value array of items to be set, if new, in each template.
  public function expand_templates_from_identifier(string $identifier, array &$templates) : void { // Pointer to save memory
    $ids = array();
    set_time_limit(120);
    switch ($identifier) {
      case 'pmid': 
      case 'pmc':     $api = 'entrez';   break;
      case 'bibcode': $api = 'adsabs';   break;
      case 'doi':     $api = 'crossref'; break;
      case 'url':     $api = 'zotero';   break;
      default:        $api = $identifier;
    }
    for ($i = 0; $i < count($templates); $i++) {
      if (in_array($templates[$i]->wikiname(), TEMPLATES_WE_PROCESS)) {
        if ($templates[$i]->has($identifier)
        && !$templates[$i]->api_has_used($api, equivalent_parameters($identifier))) {
          if (($api !== 'adsabs') || $templates[$i]->incomplete() || $templates[$i]->blank('doi')) { // Avoid some bibcode searches
            $ids[$i] = $templates[$i]->get_without_comments_and_placeholders($identifier);
          }
        }
      }
    }
    $api_function = 'query_' . $identifier . '_api';
    $api_function($ids, $templates);
    
    foreach (array_keys($ids) as $i) {
      // Record this afterwards so we don't block the api_function itself
      $templates[$i]->record_api_usage($api, $identifier);
    }
  }
  
  public function expand_text() : bool {
    set_time_limit(120);
    $this->page_error = FALSE;
    $this->announce_page();
    if (!$this->text) {
      report_warning("No text retrieved.\n");
      return FALSE;
    }

    // COMMENTS AND NOWIKI ETC. //
    $comments    = $this->extract_object('Comment');
    $nowiki      = $this->extract_object('Nowiki');
    $chemistry   = $this->extract_object('Chemistry');
    $mathematics = $this->extract_object('Mathematics');
    $musicality  = $this->extract_object('Musicscores');
    $preformated = $this->extract_object('Preformated');
    set_time_limit(120);
    if (!$this->allow_bots()) {
      report_warning("Page marked with {{nobots}} template.  Skipping.");
      $this->text = $this->start_text;
      return FALSE;
    }
    $citation_count = substr_count($this->text, '{{cite ') +
                      substr_count($this->text, '{{Cite ') +
                      substr_count($this->text, '{{citation') +
                      substr_count($this->text, '{{Citation');
    $ref_count = substr_count($this->text, '<ref') + substr_count($this->text, '<Ref');
    // PLAIN URLS Converted to Templates
    // Ones like <ref>http://www.../....{{full|date=April 2016}}</ref> (?:full) so we can add others easily
    $this->text = preg_replace_callback(
                      "~(<(?:\s*)ref[^>]*?>)(\s*\[?(https?:\/\/[^ >}{\]\[]+?)\]?\s*{{(?:full|Full citation needed)(?:|\|date=[a-zA-Z0-9 ]+)}})(<\s*?\/\s*?ref(?:\s*)>)~i",
                      function(array $matches) : string {return $matches[1] . '{{cite web | url=' . $matches[3] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[4] ;},
                      $this->text
                      );
    // Ones like <ref>http://www.../....{{Bare URL inline|date=April 2016}}</ref>
    $this->text = preg_replace_callback(
                      "~(<(?:\s*)ref[^>]*?>)(\s*\[?(https?:\/\/[^ >}{\]\[]+?)\]?\s*{{Bare URL inline(?:|\|date=[a-zA-Z0-9 ]+)}})(<\s*?\/\s*?ref(?:\s*)>)~i",
                      function(array $matches) : string {return $matches[1] . '{{cite web | url=' . $matches[3] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[4] ;},
                      $this->text
                      );
    // Ones like <ref>http://www.../....</ref>; <ref>[http://www.../....]</ref>
    $this->text = preg_replace_callback(
                      "~(<(?:\s*)ref[^>]*?>)(\s*\[?(https?:\/\/[^ >}{\]\[]+?)\]?\s*)(<\s*?\/\s*?ref(?:\s*)>)~i",
                      function(array $matches) : string {return $matches[1] . '{{cite web | url=' . $matches[3] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[4] ;},
                      $this->text
                      );
    // Ones like <ref>[http://www... http://www...]</ref>
    $this->text = preg_replace_callback(   
                      "~(<(?:\s*)ref[^>]*?>)((\s*\[)(https?:\/\/[^\s>\}\{\]\[]+?)(\s+)(https?:\/\/[^\s>\}\{\]\[]+?)(\s*\]\s*))(<\s*?\/\s*?ref(?:\s*)>)~i",
                      function(array $matches) : string  {
                        if ($matches[4] === $matches[6]) {
                            return $matches[1] . '{{cite web | url=' . $matches[4] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[8] ;
                        }
                        return $matches[0];
                      },
                      $this->text
                      ); 
     // PLAIN {{DOI}}, {{PMID}}, {{PMC}} {{isbn}} {{oclc}} {{bibcode}} {{arxiv}} Converted to templates
     $this->text = preg_replace_callback(   // like <ref>{{doi|10.1244/abc}}</ref>
                      "~(<(?:\s*)ref[^>]*?>)(\s*\{\{(?:doi\|10\.\d{4,6}\/[^\s\}\{\|]+?|pmid\|\d{4,9}|pmc\|\d{4,9}|oclc\|\d{4,9}|isbn\|[0-9\-xX]+?|arxiv\|\d{4}\.\d{4,5}(?:|v\d+)|arxiv\|[a-z\.\-]{2,12}\/\d{7,8}(?:|v\d+)|bibcode\|[12]\d{3}[\w\d\.&]{15}|jstor\|[^\s\}\{\|]+?)\}\}\s*)(<\s*?\/\s*?ref(?:\s*)>)~i",
                      function(array $matches) : string  {
                        if (stripos($matches[2], 'arxiv')) {
                          $type = 'arxiv';
                        } elseif (stripos($matches[2], 'isbn') || stripos($matches[2], 'oclc')) {
                          $type = 'book';
                        } else {
                          $type = 'journal';
                        }
                        return $matches[1] . '{{cite ' . $type . ' | id=' . $matches[2] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[3] ;
                      },
                      $this->text
                      );
     // PLAIN DOIS Converted to templates
     $this->text = preg_replace_callback(   // like <ref>10.1244/abc</ref>
                      "~(<(?:\s*)ref[^>]*?>)(\s*10\.[0-9]{4,6}\/\S+?\s*)(<\s*?\/\s*?ref(?:\s*)>)~i",
                      function(array $matches) : string  {return $matches[1] . '{{cite journal | doi=' . $matches[2] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . ' }}' . $matches[3] ;},
                      $this->text
                      );
     if (
        ($ref_count < 2) ||
        (($citation_count/$ref_count) >= 0.5)
     ) {
     $this->text = preg_replace_callback(   // like <ref>John Doe, [https://doi.org/10.1244/abc Foo], Bar 1789.</ref>
                                            // also without titles on the urls
                      "~(<(?:\s*)ref[^>]*?>)([^\{\}<\[\]]+\[)(https?://\S+?/10\.[0-9]{4,6}\/[^\[\]\{\}\s]+?)( [^\]\[\{\}]+?\]|\])(\s*[^<\]\[]+?)(<\s*?\/\s*?ref(?:\s*)>)~i",
                      function(array $matches) : string  {
                        if (substr_count(strtoupper($matches[2].$matches[3].$matches[4].$matches[5]), 'HTTP') !== 1) return $matches[0]; // more than one url
                        if (substr_count(strtoupper($matches[2].$matches[3].$matches[4].$matches[5]), 'SEE ALSO') !== 0) return $matches[0];
                        return $matches[1] . '{{cite journal | url=' . $matches[3] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2] . $matches[3] . $matches[4] . $matches[5]) . ' }}' . $matches[6] ;},
                      $this->text
                      );
     }
    // TEMPLATES
    set_time_limit(120);
    $singlebrack = $this->extract_object('SingleBracket');
    $all_templates = $this->extract_object('Template');
    set_time_limit(120);
    if ($this->page_error) {
      $this->text = $this->start_text;
      return FALSE;
    }
    Template::$all_templates = &$all_templates; // Pointer to save memory
    Template::$date_style = $this->date_style;
    for ($i = 0; $i < count($all_templates); $i++) {
      if ($all_templates[$i]->wikiname() === 'void') {
        $all_templates[$i]->block_modifications();
      }
    }
    $our_templates = array();
    $our_templates_slight = array();
    $our_templates_conferences = array();
    $our_templates_ieee = array();
    report_phase('Remedial work to prepare citations');
    for ($i = 0; $i < count($all_templates); $i++) {
      set_time_limit(120);
      $this_template = $all_templates[$i];
      if (in_array($this_template->wikiname(), TEMPLATES_WE_PROCESS)) {
        $our_templates[] = $this_template;
        $this_template->prepare();
      } elseif (in_array($this_template->wikiname(), TEMPLATES_WE_SLIGHTLY_PROCESS)) {
        $our_templates_slight[] = $this_template;
        $this_template->correct_param_mistakes();
        $this_template->prepare(); // does very little
        $this_template->get_identifiers_from_url();
        $this_template->expand_by_google_books();
        $this_template->tidy();
        if ($this_template->wikiname() === 'cite conference') $our_templates_conferences[] = $this_template;
        $our_templates_ieee[] = $this_template;
      } elseif (in_array($this_template->wikiname(), TEMPLATES_WE_BARELY_PROCESS)) { // No capitalization of thesis, etc.
        $our_templates_slight[] = $this_template;
        $this_template->clean_google_books();
        $this_template->correct_param_mistakes();
        $this_template->get_identifiers_from_url();
        $this_template->tidy();
      } elseif (in_array($this_template->wikiname(), TEMPLATES_WE_CHAPTER_URL)) {
        $our_templates_slight[] = $this_template;
        $this_template->rename('chapterurl', 'chapter-url');
      } elseif ($this_template->wikiname() == 'cite magazine' || $this_template->wikiname() == 'cite periodical') {
        $our_templates_slight[] = $this_template;
        if ($this_template->blank('magazine') && $this_template->has('work')) {
            $this_template->rename('work', 'magazine');
        }
        if ($this_template->has('magazine')) {
          $this_template->set('magazine', straighten_quotes(trim($this_template->get('magazine')), TRUE));
        }
        $this_template->correct_param_mistakes();
        $this_template->prepare(); // does very little
        $this_template->get_identifiers_from_url();
        $this_template->expand_by_google_books();
        $this_template->tidy();
      } elseif ($this_template->wikiname() === 'cite lsa') {
        $this_template->clean_google_books();
        $this_template->forget('ref'); // Common parameter that does not actually work
      } elseif ($this_template->wikiname() === 'cite odnb') {
        $this_template->clean_cite_odnb();
        $this_template->clean_google_books();
      } elseif ($this_template->wikiname() === 'cite episode' || $this_template->wikiname() === 'cite interview') {
        $this_template->clean_google_books();
        $this_template->correct_param_mistakes();
      } elseif ((strpos($this_template->wikiname(), 'cite ') === 0)  || (strpos($this_template->wikiname(), 'vcite ') === 0)) {
        $this_template->clean_google_books();
        // THIS CATCH ALL NEEDS TO BE LAST IN THE LIST!!!!!!
      }
    }
    // BATCH API CALLS
    report_phase('Consult APIs to expand templates');
    set_time_limit(120);
    $this->expand_templates_from_identifier('doi',     $our_templates);  // Do DOIs first!  Try again later for added DOIs
    $this->expand_templates_from_identifier('doi',     $our_templates_slight);
    for ($i = 0; $i < count($our_templates_slight); $i++) { // Is is really a journal, after expanding DOI
      $this_template = $our_templates_slight[$i];
      if ($this_template->has('journal') &&
          $this_template->has('doi') &&
          ($this_template->has('volume') || $this_template->has('issue')) &&
          ($this_template->has('year') || $this_template->has('date')) &&
          ($this_template->has('page') || $this_template->has('pages')) &&
          $this_template->has('title')) {
        $this_template->change_name_to('cite journal', TRUE, TRUE);
      }
    }
    $this->expand_templates_from_identifier('pmid',    $our_templates);
    $this->expand_templates_from_identifier('pmc',     $our_templates);
    $this->expand_templates_from_identifier('bibcode', $our_templates);
    $this->expand_templates_from_identifier('jstor',   $our_templates);
    $this->expand_templates_from_identifier('doi',     $our_templates);
    expand_arxiv_templates($our_templates);
    $this->expand_templates_from_identifier('url',     $our_templates);
    Zotero::query_ieee_webpages($our_templates_ieee);
    Zotero::query_ieee_webpages($our_templates);
    
    report_phase('Expand individual templates by API calls');
    for ($i = 0; $i < count($our_templates); $i++) {
      set_time_limit(120);
      $this_template = $our_templates[$i];
      $this_template->expand_by_google_books();
      $this_template->get_doi_from_crossref();
      $this_template->get_doi_from_semanticscholar();
      $this_template->find_pmid();
      if ($this_template->blank('bibcode')) {
        $no_arxiv = $this_template->blank('arxiv');
        $this_template->expand_by_adsabs(); // Try to get a bibcode
        if (!$this_template->blank('arxiv') && $no_arxiv) {  // Added an arXiv.  Stuff to learn and sometimes even find a DOI -- VERY RARE
          $tmp_array = [$this_template];          // @codeCoverageIgnore
          expand_arxiv_templates($tmp_array);     // @codeCoverageIgnore
        }
      }
      $this_template->get_open_access_url();
    }
    $this->expand_templates_from_identifier('doi',     $our_templates);
    set_time_limit(120);
    Zotero::drop_urls_that_match_dois($our_templates);
    Zotero::drop_urls_that_match_dois($our_templates_conferences);
    
    // Last ditch usage of ISSN - This could mean running the bot again will add more things
    $issn_templates = array_merge(TEMPLATES_WE_PROCESS, TEMPLATES_WE_SLIGHTLY_PROCESS, ['cite magazine']);
    for ($i = 0; $i < count($all_templates); $i++) {
      $this_template = $all_templates[$i];
      if (in_array($this_template->wikiname(), $issn_templates)) {
        $this_template->use_issn();
      }
    }
    expand_templates_from_archives($our_templates);

    report_phase('Remedial work to clean up templates');
    for ($i = 0; $i < count($our_templates); $i++) {
      $this_template = $our_templates[$i];
      // Clean up:
      if (!$this_template->initial_author_params()) {
        $this_template->handle_et_al();
      }
      $this_template->final_tidy();

      // Record any modifications that have been made:
      $template_mods = $this_template->modifications();
      foreach (array_keys($template_mods) as $key) {
        if (!isset($this->modifications[$key])) {
          $this->modifications[$key] = $template_mods[$key];                     // @codeCoverageIgnore
          report_minor_error('unexpected modifications key: ' . (string) $key);  // @codeCoverageIgnore
        } elseif (is_array($this->modifications[$key])) {
          $this->modifications[$key] = array_unique(array_merge($this->modifications[$key], $template_mods[$key]));
        } else {
          $this->modifications[$key] = $this->modifications[$key] || $template_mods[$key]; // bool like mod_dashes
        }
      }
    }
    for ($i = 0; $i < count($our_templates_slight); $i++) {
      $this_template = $our_templates_slight[$i];
      // Record any modifications that have been made:
      $template_mods = $this_template->modifications();
      foreach (array_keys($template_mods) as $key) {
        if (!isset($this->modifications[$key])) {
          $this->modifications[$key] = $template_mods[$key];                     // @codeCoverageIgnore
          report_minor_error('unexpected modifications key: ' . (string) $key);  // @codeCoverageIgnore
        } elseif (is_array($this->modifications[$key])) {
          $this->modifications[$key] = array_unique(array_merge($this->modifications[$key], $template_mods[$key]));
        } else {
          $this->modifications[$key] = $this->modifications[$key] || $template_mods[$key]; // bool like mod_dashes
        }
      }
    }
    set_time_limit(120);
    // Release memory ASAP
    unset($our_templates);
    unset($our_templates_slight);
    unset($our_templates_conferences);
    unset($our_templates_ieee);
    
    $this->replace_object($all_templates);
    // remove circular memory reference that makes garbage collection harder and reset
    Template::$all_templates = array();
    Template::$date_style = DATES_WHATEVER;
    unset($all_templates);

    $this->text = preg_replace('~(\{\{[Cc]ite ODNB\s*\|[^\{\}\_]+_?[^\{\}\_]+\}\}\s*)\{\{ODNBsub\}\}~u', '$1', $this->text); // Allow only one underscore to shield us from MATH etc.
    $this->text = preg_replace('~(\{\{[Cc]ite ODNB\s*\|[^\{\}\_]*ref ?= ?\{\{sfn[^\{\}\_]+\}\}[^\{\}\_]*\}\}\s*)\{\{ODNBsub\}\}~u', '$1', $this->text); // Allow a ref={{sfn in the template
    
    set_time_limit(120);
    $this->replace_object($singlebrack); unset($singlebrack);
    $this->replace_object($preformated); unset($preformated);
    $this->replace_object($musicality); unset($musicality);
    $this->replace_object($mathematics); unset($mathematics);
    $this->replace_object($chemistry); unset($chemistry);
    $this->replace_object($nowiki); unset($nowiki);
    $this->replace_object($comments); unset($comments);
    set_time_limit(120);
    
    if (stripos($this->text, 'CITATION_BOT_PLACEHOLDER') !== FALSE) {
      $this->text = $this->start_text;                                  // @codeCoverageIgnore
      report_error('CITATION_BOT_PLACEHOLDER found after processing');  // @codeCoverageIgnore
    }

    // we often just fix Journal caps, so must be case sensitive compare
    // Avoid minor edits - gadget API will make these changes, since it does not check return code
    $caps_ok = array('lccn', 'isbn', 'doi');
    $last_first_in  = array(' last=',  ' last =',  '|last=',  '|last =',  ' first=',  ' first =',  '|first=',  '|first =', 'cite newspaper', 'Cite newspaper', '| format=PDF ', '| format = PDF ', '|format=PDF ', '|format = PDF ', '| format=PDF', '| format = PDF', '|format=PDF', '|format = PDF', 'Cite ', 'cite ', 'proquest');
    $last_first_out = array(' last1=', ' last1 =', '|last1=', '|last1 =', ' first1=', ' first1 =', '|first1=', '|first1 =','cite news',      'Cite news',      '',              '',                '',              '',              '',             '',               '',            '',              'Cite' , 'cite' , 'proquest');
    // @codeCoverageIgnoreStart
    if (WIKI_ROOT === 'https://simple.wikipedia.org/w/index.php') { // Backload clean-up
       $caps_ok = array();
       $last_first_in  = array();
       $last_first_out = array();
    } // @codeCoverageIgnoreEnd
    return strcmp(str_replace($last_first_in, $last_first_out, str_ireplace($caps_ok, $caps_ok, $this->text)),
                  str_replace($last_first_in, $last_first_out, str_ireplace($caps_ok, $caps_ok, $this->start_text))) != 0;
  }

  public function edit_summary() : string {
    $match = ['', '']; // prevent memory leak in some PHP versions
    $auto_summary = "";
    if (count($this->modifications["changeonly"]) !== 0) {
      $auto_summary .= "Alter: " . implode(", ", $this->modifications["changeonly"]) . ". ";
    }
    if (strpos(implode(" ", $this->modifications["changeonly"]), 'url') !== FALSE) {
      $auto_summary .= "URLs might have been anonymized. ";
    }
    if (count($this->modifications['additions']) !== 0) {
      $addns = $this->modifications["additions"];
      $auto_summary .= "Add: ";
      $min_au = 9999;
      $max_au = 0;
      $min_ed = 9999;
      $max_ed = 0;
      while ($add = array_pop($addns)) {
        if (preg_match('~editor[^\d]*(\d+)~', $add, $match)) {
          if ($match[1] < $min_ed) $min_ed = $match[1];
          if ($match[1] > $max_ed) $max_ed = $match[1];
        } elseif (preg_match('~(?:author|last|first)(\d+)~', $add, $match)) {
          if ($match[1] < $min_au) $min_au = $match[1];
          if ($match[1] > $max_au) $max_au = $match[1];
        } else {
          $auto_summary .= $add . ', ';
        }
      }
      if ($max_au) {
        $auto_summary .= "authors $min_au-$max_au. ";
      }
      if ($max_ed) {
        $auto_summary .= "editors $min_ed-$max_ed. ";
      }
      if (!$max_ed && !$max_au) {
        $auto_summary = substr($auto_summary, 0, -2) . '. ';
      }
    }

    if ((count($this->modifications["deletions"]) !== 0)
    && (
        (($pos = array_search('url', $this->modifications["deletions"])) !== FALSE)
     || (($pos = array_search('chapter-url', $this->modifications["deletions"])) !== FALSE)
     || (($pos = array_search('chapterurl', $this->modifications["deletions"])) !== FALSE)
        )
    ) {
        if (strpos($auto_summary, 'chapter-url') !== FALSE) {
          $auto_summary .= "Removed or converted URL. ";
        } else {
          $auto_summary .= "Removed proxy/dead URL that duplicated identifier. ";
        }
        unset($this->modifications["deletions"][$pos]);
    }
    if ((count($this->modifications["deletions"]) !== 0)
    && (($pos = array_search('accessdate', $this->modifications["deletions"])) !== FALSE || ($pos = array_search('access-date', $this->modifications["deletions"])) !== FALSE)
    ) {
      $auto_summary .= "Removed access-date with no URL. ";
      unset($this->modifications["deletions"][$pos]);
    }
    if ((count($this->modifications["deletions"]) !== 0)
    && ($pos = array_search(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'), $this->modifications["deletions"])) !== FALSE
    ) {
      $auto_summary .= "Changed bare reference to CS1/2. ";
      unset($this->modifications["deletions"][$pos]);
    }
    $auto_summary .= ((count($this->modifications["deletions"]) !==0)
      ? "Removed parameters. "
      : ""
      ) . (($this->modifications["dashes"])
      ? "Formatted [[WP:ENDASH|dashes]]. "
      : ""
    );
    if (count($this->modifications["deletions"]) !== 0 && count($this->modifications["additions"]) !== 0 && $this->modifications["names"]) {
      $auto_summary .= 'Some additions/deletions were parameter name changes. ';
    }
    $isbn978_added = substr_count($this->text, '978') - substr_count($this->start_text, '978');
    $isbn_added = (substr_count($this->text, 'isbn') + substr_count($this->text, 'ISBN')) -
                  (substr_count($this->start_text, 'isbn') + substr_count($this->start_text, 'ISBN'));
    if (($isbn978_added > 0) && ($isbn978_added > $isbn_added)) { // Still will get false positives for isbn=blank converted to isbn=978......
      $auto_summary .= 'Upgrade ISBN10 to 13. ';
    }
    if (stripos($auto_summary, 'template') !== FALSE) {
      foreach (['cite|', 'Cite|', 'citebook', 'Citebook', 'cit book', 'Cit book', 'cite books', 'Cite books',
                'book reference', 'Book reference', 'citejournal', 'Citejournal', 'citeweb', 'Citeweb',
                'cite-web', 'Cite-web', 'cit web', 'Cit web', 'cit journal', 'Cit journal',
                'cit news', 'Cit news', 'cite url', 'Cite url', 'web cite', 'Web cite',
                'book cite', 'Book cite', 'cite-book', 'Cite-book', 'citenews', 'Citenews',
                'citepaper', 'Citepaper', 'cite new|', 'cite new|', 'citation journal', 'Citation journal',
                'cite new |', 'cite new |', 'cite |', 'Cite |'] as $try_me) {
         if (substr_count($this->text, $try_me) < substr_count($this->start_text, $try_me)) {
            $auto_summary .= 'Removed Template redirect. ';
            break;
         }
      }
    }
    if (!$auto_summary) {
      $auto_summary = "Misc citation tidying. ";
    }
    $auto_summary = $auto_summary . "| [[WP:UCB|Use this bot]]. [[WP:DBUG|Report bugs]]. ";
    if (WIKI_ROOT !== 'https://en.wikipedia.org/w/index.php') {
      $auto_summary = str_replace('[[WP:', '[[en:WP:', $auto_summary);  // @codeCoverageIgnore
    }
    return $auto_summary;
  }

  public function write(WikipediaBot $api, string $edit_summary_end = '') : bool {
    static $failures = array(FALSE, FALSE, FALSE, FALSE, FALSE);
    if ($this->allow_bots()) {
      $failures[0] = $failures[1];
      $failures[1] = $failures[2];
      $failures[2] = $failures[3];
      $failures[3] = $failures[4];
      $failures[4] = FALSE;
      throttle(2); // This is only writing.  Not pages that are left unchanged
      if ($api->write_page($this->title, $this->text,
              $this->edit_summary() . $edit_summary_end,
              $this->lastrevid, $this->read_at)) {
        return TRUE;          // @codeCoverageIgnore
      } elseif (!TRAVIS) {
        // @codeCoverageIgnoreStart
        sleep(9);  // could be database being locked
        report_info("Trying to write again after waiting");
        $return = $api->write_page($this->title, $this->text,
              $this->edit_summary() . $edit_summary_end,
              $this->lastrevid, $this->read_at);
         if ($return) {
           return TRUE;
         } else {
           $failures[4] = TRUE;
           if ($failures[0] && $failures[1] && $failures[2] && $failures[3]) {
              report_error("Five failures in a row -- shutting down the bot");
           }
           return FALSE;
         }
        // @codeCoverageIgnoreEnd
      } else {
        return FALSE;
      }
    } else {
      report_warning("Can't write to " . echoable($this->title) . 
        " - prohibited by {{bots}} template.");
      return FALSE;
    }
  }
  
  public function extract_object(string $class) : array {
    $match = ['', '']; // prevent memory leak in some PHP versions
    $i = 0;
    $text = $this->text;
    $regexp_in = $class::REGEXP;
    $placeholder_text = $class::PLACEHOLDER_TEXT;
    $treat_identical_separately = $class::TREAT_IDENTICAL_SEPARATELY;
    $objects = array();
    
    $preg_ok = TRUE;
    foreach ($regexp_in as $regexp) {
      while ($preg_ok = preg_match($regexp, $text, $match)) {
        $obj = new $class();
        try {
          $obj->parse_text($match[0]);
        } catch (Exception $e) {
          $this->page_error = TRUE;
          $this->text = $text;
          return $objects;
        }
        $exploded = $treat_identical_separately ? explode($match[0], $text, 2) : explode($match[0], $text);
        $text = implode(sprintf($placeholder_text, $i++), $exploded);
        $objects[] = $obj;
      }
    }
    /** @psalm-suppress TypeDoesNotContainType */
    if ($preg_ok === FALSE) { // Something went wrong.  Often from bad wiki-text.  Generally, preg_match() cannot return FALSE, so supress psalm
        // PHP 5 segmentation faults. PHP 7.0 returns FALSE
        // @codeCoverageIgnoreStart
        $this->page_error = TRUE;
        report_warning('Regular expression failure in ' . echoable($this->title) . ' when extracting ' . $class . 's');
        if ($class === "Template") {
          echo "<p>\n\n The following text might help you figure out where the <b>error on the page</b> is (Look for lone { and } characters)</h1>\n\n" . echoable($text) . "\n\n<p>";
        }
        report_minor_error("Report this problem please");
        // @codeCoverageIgnoreEnd
    }
    $this->text = $text;
    return $objects;
  }

  protected function replace_object (array &$objects) : void {  // Pointer to save memory
    $i = count($objects);
    if ($objects) foreach (array_reverse($objects) as $obj)
      $this->text = str_ireplace(sprintf($obj::PLACEHOLDER_TEXT, --$i), $obj->parsed_text(), $this->text); // Case insensitive, since comment placeholder might get title case, etc.
  }

  protected function announce_page() : void {
    $url_encoded_title =  urlencode($this->title);
    if ($url_encoded_title == '') return;
    html_echo ("\n<hr>[" . date("H:i:s") . "] Processing page '<a href='" . WIKI_ROOT . "?title=$url_encoded_title' style='font-weight:bold;'>" 
        . echoable($this->title)
        . "</a>' &mdash; <a href='" . WIKI_ROOT . "?title=$url_encoded_title"
        . "&action=edit' style='font-weight:bold;'>edit</a>&mdash;<a href='" . WIKI_ROOT . "?title=$url_encoded_title"
        . "&action=history' style='font-weight:bold;'>history</a> ", 
        "\n[" . date("H:i:s") . "] Processing page " . $this->title . "...\n");
  }
  
  protected function allow_bots() : bool {
    if (defined("BAD_PAGE_API") && BAD_PAGE_API !== "") {  // When testing the bot on a specific page, allow "editing"
      return TRUE; // @codeCoverageIgnore
    }
    // see {{bots}} and {{nobots}}
    $bot_username = 'Citation[ _]bot';
    if (preg_match('~\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.$bot_username.'.*?)\}\}~iS',$this->text)) {
      return FALSE;
    }
    if (preg_match('~\{\{(bots\|allow=all|bots\|allow=.*?'.$bot_username.'.*?)\}\}~iS', $this->text)) {
      return TRUE;
    }
    if (preg_match('~\{\{(bots\|allow=.*?)\}\}~iS', $this->text)) {
      return FALSE;
    }
    return TRUE;
  }
  
  protected function set_date_pattern() : void {
    // see {{use_mdy_dates}} and {{use_dmy_dates}}
    $date_style = DATES_WHATEVER;
    if (preg_match('~\{\{Use mdy dates[^\}\{]*\}\}~i',$this->text)) {
      $date_style = DATES_MDY;
    }
    if (preg_match('~\{\{Use mdy[^\}\{]*\}\}~i',$this->text)) {
      $date_style = DATES_MDY;
    }
    if (preg_match('~\{\{mdy[^\}\{]*\}\}~i',$this->text)) {
      $date_style = DATES_MDY;
    }
    if (preg_match('~\{\{Use dmy dates[^\}\{]*\}\}~i',$this->text)) {
      $date_style = DATES_DMY;
    }
    if (preg_match('~\{\{Use dmy[^\}\{]*\}\}~i',$this->text)) {
      $date_style = DATES_DMY;
    }
    if (preg_match('~\{\{dmy[^\}\{]*\}\}~i',$this->text)) {
      $date_style = DATES_DMY;
    }
    $this->date_style = $date_style;
  }
  
  protected function construct_modifications_array() : void {
    $this->modifications['changeonly'] = array();
    $this->modifications['additions'] = array();
    $this->modifications['deletions'] = array();
    $this->modifications['modifications'] = array();
    $this->modifications['dashes'] = FALSE;
    $this->modifications['names'] = FALSE;
  }
}

final class TestPage extends Page {
  // Functions for use in testing context only
  
  function __construct() {
    $trace = debug_backtrace();
    $name = $trace[2]['function'];
    $this->title = empty($name) ? 'Test Page' : $name;
    parent::__construct();
  }
  
  public function overwrite_text(string $text) : void {
    $this->text = $text;
  }
  
}
