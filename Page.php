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
require_once('WikipediaBot.php');

class Page {

  protected $text, $title, $modifications;

  function __construct() {
    $this->api = new WikipediaBot();
  }
    
  public function get_text_from($title, $api) {    
    $details = $api->fetch(['action'=>'query', 
      'prop'=>'info', 'titles'=> $title, 'curtimestamp'=>'true']);
    
    if (!isset($details->query)) {
      echo "\n ! Error: Could not fetch page. \n";
      if (isset($details->error)) echo "   - " . $details->error->info;
      return FALSE;
    }
    foreach ($details->query->pages as $p) {
      $my_details = $p;
    }
    $this->read_at = isset($details->curtimestamp) ? $details->curtimestamp : NULL;
    
    $details = $my_details;
    if (isset($details->invalid)) {
      echo "\n ! Page invalid: ". $details->invalidreason;
      return FALSE;
    }
    if ( !isset($details->touched) || !isset($details->lastrevid)) {
       echo "\n ! Could not even get the page.  Perhaps non-existent? ";
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

  public function parsed_text() {
    return $this->text;
  }
  
  public function expand_text() {
    $safetitle = htmlspecialchars($this->title);
    date_default_timezone_set('UTC');
    html_echo ("\n<hr>[" . date("H:i:s") . "] Processing page '<a href='https://en.wikipedia.org/w/index.php?title=" 
      . urlencode($this->title) 
      . "' style='text-weight:bold;'>{$safetitle}</a>' &mdash; <a href='https://en.wikipedia.org/w/index.php?title="
      . urlencode($this->title)
      . "&action=edit' style='text-weight:bold;'>edit</a>&mdash;<a href='https://en.wikipedia.org/w/index.php?title="
      . urlencode($this->title)
      . "&action=history' style='text-weight:bold;'>history</a> <script type='text/javascript'>"
      . "document.title=\"Citation bot: '"
      . str_replace("+", " ", urlencode($this->title)) ."'\";</script>", 
      "\n[" . date("H:i:s") . "] Processing page " . $this->title . "...\n");
    $text = $this->text;
    $this->modifications = array();
    if (!$text) {
      echo "\n\n  ! No text retrieved.\n";
      return FALSE;
    }

    // COMMENTS AND NOWIKI //
    $comments = $this->extract_object('Comment');
    $nowiki   = $this->extract_object('Nowiki');
    if (!$this->allow_bots()) {
      echo "\n ! Page marked with {{nobots}} template.  Skipping.";
      return FALSE;
    }

    // TEMPLATES //
    $templates = $this->extract_object('Template');
    for ($i = 0; $i < count($templates); $i++) {
       $templates[$i]->all_templates = &$templates ; // Has to be pointer
    }
    for ($i = 0; $i < count($templates); $i++) {
      $templates[$i]->process();
      $template_mods = $templates[$i]->modifications();
      foreach (array_keys($template_mods) as $key) {
        if (!isset($this->modifications[$key])) {
          $this->modifications[$key] = $template_mods[$key];
        } else {
          $this->modifications[$key] = array_unique(array_merge($this->modifications[$key], $template_mods[$key]));
        }
      }
    }
    $text = $this->replace_object($templates);

    $this->replace_object($comments);
    $this->replace_object($nowiki);

    return strcasecmp($this->text, $this->start_text) != 0;
  }

  public function edit_summary() {
    $auto_summary = "";
    if (isset($this->modifications["changeonly"])) {
      $auto_summary .= "Alter: " . implode(", ", $this->modifications["changeonly"]) . ". ";
    }
    if (isset($this->modifications['additions'])) {
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
    if (isset($this->modifications["deletions"])
    && ($pos = array_search('accessdate', $this->modifications["deletions"])) !== FALSE
    ) {
      $auto_summary .= "Removed accessdate with no specified URL. ";
      unset($this->modifications["deletions"][$pos]);
    }
    $auto_summary .= (($this->modifications["deletions"])
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

  public function write($api, $edit_summary = NULL) {
    if ($this->allow_bots()) {
      return $api->write_page($this->title, $this->text,
              $edit_summary ? $edit_summary : $this->edit_summary(),
              $this->lastrevid, $this->read_at);
    } else {
      trigger_error("Can't write to " . htmlspecialchars($this->title) . 
        " - prohibited by {{bots}} template.", E_USER_NOTICE);
      return FALSE;
    }
  }
  
  protected function extract_object ($class) {
    $i = 0;
    $text = $this->text;
    $regexp = $class::REGEXP;
    $placeholder_text = $class::PLACEHOLDER_TEXT;
    $treat_identical_separately = $class::TREAT_IDENTICAL_SEPARATELY;
    $objects = array();
    while(preg_match($regexp, $text, $match)) {
      $obj = new $class();
      $obj->parse_text($match[0]);
      $exploded = $treat_identical_separately ? explode($match[0], $text, 2) : explode($match[0], $text);
      $text = implode(sprintf($placeholder_text, $i++), $exploded);
      $objects[] = $obj;
    }
    $this->text = $text;
    return $objects;
  }

  protected function replace_object ($objects) {
    $i = count($objects);
    if ($objects) foreach (array_reverse($objects) as $obj)
      $this->text = str_ireplace(sprintf($obj::PLACEHOLDER_TEXT, --$i), $obj->parsed_text(), $this->text); // Case insensitive, since comment placeholder might get title case, etc.
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

  public function parse_text($text) {
    $this->text = $text;
    $this->start_text = $this->text;
    $this->modifications = array();
  }  
}