<?php
/*
 * Page contains methods that will do most of the higher-level work of expanding citations
 * on the wikipage associated with the Page object.
 * Provides functions to read, parse, expand text (using Template and Comment)
 * handle collected page modifications, and save the edited page text
 * to the wiki.
 */

require_once('Comment.php');
require_once('Template.php');
require_once('apiFunctions.php');
require_once('WikipediaBot.php');

class Page {

  protected $text, $title, $modifications;
  protected $read_at, $api, $namespace, $touched, $start_text, $last_write_time;
  public $lastrevid;

  function __construct() {
    $this->api = new WikipediaBot();
  }
    
  public function get_text_from($title, $api) {    
    $details = $api->fetch(['action'=>'query', 
      'prop'=>'info', 'titles'=> $title, 'curtimestamp'=>'true']);
    
    if (!isset($details->query)) {
      report_warning("Error: Could not fetch page.");
      if (isset($details->error)) report_info($details->error->info);
      return FALSE;
    }
    foreach ($details->query->pages as $p) {
      $my_details = $p;
    }
    $this->read_at = isset($details->curtimestamp) ? $details->curtimestamp : NULL;
    
    $details = $my_details;
    if (isset($details->invalid)) {
      report_warning("Page invalid: ". $details->invalidreason);
      return FALSE;
    }
    if ( !isset($details->touched) || !isset($details->lastrevid)) {
       report_warning("Could not even get the page.  Perhaps non-existent?");
       return FALSE;
    }
    
    $this->title = $details->title;
    $this->namespace = $details->ns;
    $this->touched = isset($details->touched) ? $details->touched : NULL;
    $this->lastrevid = isset($details->lastrevid) ? $details->lastrevid : NULL;

    $this->text = @file_get_contents(WIKI_ROOT . '?' . http_build_query(['title' => $title, 'action' =>'raw']));
    $this->start_text = $this->text;
    $this->modifications = array();

    if (stripos($this->text, '#redirect') !== FALSE) {
      echo "Page is a redirect.";
      return FALSE;
    }

    if ($this->text) {
      return TRUE;
    } else{
      return FALSE;
    }
  }
  
  // Called from gadgetapi.php
  public function parse_text($text) {
    $this->text = $text;
    $this->start_text = $this->text;
    $this->modifications = array();
  }  

  public function parsed_text() {
    return $this->text;
  }
  
  // $parameter: parameter to send to api_function, e.g. "pmid"
  // $templates: Array of pointers to the templates
  // $api_function: string naming a function (specified in apiFunctions.php) 
  //                that takes the value of $templates->get($identifier) as an array;
  //                returns key-value array of items to be set, if new, in each template.
  public function expand_templates_from_identifier($identifier, $templates) {
    $ids = array();
    switch ($identifier) {
      case 'pmid': 
      case 'pmc':     $api = 'entrez';   break;
      case 'bibcode': $api = 'adsabs';   break;
      case 'doi':     $api = 'crossref'; break;
      default:        $api = $identifier;
    }
    for ($i = 0; $i < count($templates); $i++) {
      if (in_array($templates[$i]->wikiname(), TEMPLATES_WE_PROCESS)) {
      if ($templates[$i]->has($identifier)
        && !$templates[$i]->api_has_used($api, equivalent_parameters($identifier))) {
          $ids[$i] = $templates[$i]->get_without_comments_and_placeholders($identifier);
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
  
  public function expand_text() {
    date_default_timezone_set('UTC');
    $this->announce_page();
    $this->modifications = array();
    if (!$this->text) {
      report_warning("No text retrieved.\n");
      return FALSE;
    }

    // COMMENTS AND NOWIKI //
    // $comments = $this->extract_object('Comment');
    // $nowiki   = $this->extract_object('Nowiki');
    if (!$this->allow_bots()) {
      report_warning("Page marked with {{nobots}} template.  Skipping.");
      return FALSE;
    }
    // EMPTY URLS Converted to Templates
    // Examples: <ref>http://www.../index.html</ref>; <ref>[http://www.../index.html]</ref>
    $this->text = preg_replace_callback(   // Ones like <ref>http://www.../index.html</ref> or <ref>[http://www.../index.html]</ref>
                      "~(<ref[^>]*?>)(\s*\[?(https?:\/\/[^ >}{\]\[]+)\]?\s*)(<\s*?\/\s*?ref>)~",
                      function($matches) {return $matches[1] . '{{cite web | url=' . $matches[3] . ' | ' . strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL') .'=' . base64_encode($matches[2]) . '}}' . $matches[4] ;},
                      $this->text
                      );

    // TEMPLATES
     // We get this far  DEBUG HUGE PAGE
    $all_templates = $this->extract_object('Template');
    // DEBUG : we do not get to here!
    return FALSE;
    for ($i = 0; $i < count($all_templates); $i++) {
       $all_templates[$i]->all_templates = &$all_templates; // Has to be pointer
    }
    $our_templates = array();
    report_phase('Remedial work to prepare citations');
    for ($i = 0; $i < count($all_templates); $i++) {
      if (in_array($all_templates[$i]->wikiname(), TEMPLATES_WE_PROCESS)) {
        // The objective in breaking this down into stages is to be able to send a single request to each API,
        // rather than a separate request for each template.
        // This is a work in progress...
        $this_template = $all_templates[$i];
        array_push($our_templates, $this_template);
        
        $this_template->prepare();
      } else if ($all_templates[$i]->wikiname() == 'cite magazine' 
                 && $all_templates[$i]->blank('magazine') 
                 && $all_templates[$i]->has('work')) {
        // This is all we do with cite magazine
        $all_templates[$i]->rename('work', 'magazine');
      }
    }
    
    // BATCH API CALLS
    report_phase('Consult APIs to expand templates');
    $this->expand_templates_from_identifier('pmid',    $our_templates);
    $this->expand_templates_from_identifier('pmc',     $our_templates);
    $this->expand_templates_from_identifier('bibcode', $our_templates);
    $this->expand_templates_from_identifier('jstor',   $our_templates);
    $this->expand_templates_from_identifier('doi',     $our_templates);
    expand_arxiv_templates($our_templates);
    
    report_phase('Expand individual templates by API calls');
    for ($i = 0; $i < count($our_templates); $i++) {
      $this_template = $our_templates[$i];
      $this_template->expand_by_google_books();
      expand_by_doi($this_template);
      $this_template->get_doi_from_crossref();
      $this_template->get_open_access_url();
      $this_template->find_pmid();  // #TODO Could probably batch this
    }
    
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
          $this->modifications[$key] = $template_mods[$key];
        } elseif (is_array($this->modifications[$key])) {
          $this->modifications[$key] = array_unique(array_merge($this->modifications[$key], $template_mods[$key]));
        } else {
          $this->modifications[$key] = $this->modifications[$key] || $template_mods[$key]; // Boolean like mod_dashes
        }
      }
    }
    $this->replace_object($all_templates);

    $this->replace_object($comments);
    $this->replace_object($nowiki);

    return strcmp($this->text, $this->start_text) != 0; // we often just fix Journal caps
  }

  public function edit_summary() {
    $auto_summary = "";
    if (count($this->modifications["changeonly"]) !== 0) {
      $auto_summary .= "Alter: " . implode(", ", $this->modifications["changeonly"]) . ". ";
    }
    if (count($this->modifications['additions']) !== 0) {
      $addns = $this->modifications["additions"];
      $auto_summary .= "Add: ";
      $min_au = 9999;
      $max_au = 0;
      while ($add = array_pop($addns)) {
        if (preg_match('~(?:author|last|first)(\d+)~', $add, $match)) {
          if ($match[1] < $min_au) $min_au = $match[1];
          if ($match[1] > $max_au) $max_au = $match[1];
        } else $auto_summary .= $add . ', ';
      }
      if ($max_au) {
        $auto_summary .= "author pars. $min_au-$max_au. ";
      } else {
        $auto_summary = substr($auto_summary, 0, -2) . '. ';
      }
    }
    if ((count($this->modifications["deletions"]) !== 0)
    && ($pos = array_search('accessdate', $this->modifications["deletions"])) !== FALSE
    ) {
      $auto_summary .= "Removed accessdate with no specified URL. ";
      unset($this->modifications["deletions"][$pos]);
    }
    $auto_summary .= ((count($this->modifications["deletions"]) !==0)
      ? "Removed parameters. "
      : ""
      ) . (($this->modifications["dashes"])
      ? "Formatted [[WP:ENDASH|dashes]]. "
      : ""
    );
    if (!$auto_summary) {
      $auto_summary = "Misc citation tidying. ";
    }
    return $auto_summary . "You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].";
  }

  public function write($api, $edit_summary_end = NULL) {
    if ($this->allow_bots()) {
      throttle(10);
      return $api->write_page($this->title, $this->text,
              $this->edit_summary() . $edit_summary_end,
              $this->lastrevid, $this->read_at);
    } else {
      trigger_error("Can't write to " . htmlspecialchars($this->title) . 
        " - prohibited by {{bots}} template.", E_USER_NOTICE);
      return FALSE;
    }
  }
  
  public function extract_object ($class) {
    $i = 0;
    $text = $this->text;
    $regexp = $class::REGEXP;
    $placeholder_text = $class::PLACEHOLDER_TEXT;
    $treat_identical_separately = $class::TREAT_IDENTICAL_SEPARATELY;
    $objects = array();
    while(preg_match($regexp, $text, $match) ) {
      $obj = new $class();
      $obj->parse_text($match[0]);
      fwrite(STDERR, "\n replacing text $i \n");
      $exploded = $treat_identical_separately ? explode($match[0], $text, 2) : explode($match[0], $text);
      $text = implode(sprintf($placeholder_text, $i++), $exploded);
      fwrite(STDERR, "\n Made new text $i \n");
      if (137 === $i ) fwrite(STDERR, $text);
      $objects[] = $obj;
    }
    $this->text = trim($text);
    return $objects;
  }

  protected function replace_object ($objects) {
    $i = count($objects);
    if ($objects) foreach (array_reverse($objects) as $obj)
      $this->text = str_ireplace(sprintf($obj::PLACEHOLDER_TEXT, --$i), $obj->parsed_text(), $this->text); // Case insensitive, since comment placeholder might get title case, etc.
  }

  protected function announce_page() {
    $url_encoded_title =  urlencode($this->title);
    html_echo ("\n<hr>[" . date("H:i:s") . "] Processing page '<a href='https://en.wikipedia.org/w/index.php?title=$url_encoded_title' style='text-weight:bold;'>" 
        . htmlspecialchars($this->title)
        . "</a>' &mdash; <a href='https://en.wikipedia.org/w/index.php?title=$url_encoded_title"
        . "&action=edit' style='text-weight:bold;'>edit</a>&mdash;<a href='https://en.wikipedia.org/w/index.php?title=$url_encoded_title"
        . "&action=history' style='text-weight:bold;'>history</a> <script type='text/javascript'>"
        . "document.title=\"Citation bot: '"
        . str_replace("+", " ", $url_encoded_title) ."'\";</script>", 
        "\n[" . date("H:i:s") . "] Processing page " . $this->title . "...\n");
  }
  
  protected function allow_bots() {
    // from https://en.wikipedia.org/wiki/Template:Bots
    $bot_username = '(?:Citation|DOI)[ _]bot';
    if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.$bot_username.'.*?)\}\}/iS',$this->text))
      return FALSE;
    if (preg_match('/\{\{(bots\|allow=all|bots\|allow=.*?'.$bot_username.'.*?)\}\}/iS', $this->text))
      return TRUE;
    if (preg_match('/\{\{(bots\|allow=.*?)\}\}/iS', $this->text))
      return FALSE;
    return TRUE;
  }
}

class TestPage extends Page {
  // Functions for use in testing context only
  
  function __construct() {
    $trace = debug_backtrace();
    $name = $trace[2]['function'];
    $this->title = empty($name) ? 'global' : $name;
  }
  
  public function overwrite_text($text) {
    $this->text = $text;
  }
  
}
