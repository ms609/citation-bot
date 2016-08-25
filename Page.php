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

class Page {

  public $text, $title, $modifications;

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
      return FALSE;
    }

    if ($this->text) {
      return TRUE;
    } else{
      return NULL;
    }
  }

  public function expand_text() {
    global $html_output;
    $safetitle = htmlspecialchars($this->title);
    date_default_timezone_set('UTC');
    quiet_echo ("\n<hr>[" . date("H:i:s") . "] Processing page '<a href='http://en.wikipedia.org/?title=" . urlencode($this->title) . "' style='text-weight:bold;'>{$safetitle}</a>' &mdash; <a href='http://en.wikipedia.org/?title=". urlencode($this->title)."&action=edit' style='text-weight:bold;'>edit</a>&mdash;<a href='http://en.wikipedia.org/?title=" . urlencode($this->title) . "&action=history' style='text-weight:bold;'>history</a> <script type='text/javascript'>document.title=\"Citation bot: '" . str_replace("+", " ", urlencode($this->title)) ."'\";</script>");
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
    $comments = $this->extract_object('Comment');
    if (!$this->allow_bots()) {
      echo "\n ! Page marked with {{nobots}} template.  Skipping.";
      return FALSE;
    }

    // TEMPLATES //
    $templates = $this->extract_object('Template');
    $start_templates = $templates;
    $citation_templates = 0;

    if ($templates) {
      foreach ($templates as $template) {
        if ($template->wikiname() == 'citation') {
          $citation_templates++;
        } elseif (stripos($template->wikiname(), 'harv') === 0) {
          $harvard_templates++;
        }
      }
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
    // seems to be set as -1  in text.php and then re-set
    if ($html_output === -1) {
      ob_end_clean();
    }

    return strcasecmp($this->text, $this->start_text) != 0;
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
      ? "Removed parameters. "
      : ""
      ) . (($this->modifications["cite_type"])
      ? "Unified citation types. "
      : ""
      ) . (($this->modifications["dashes"])
      ? "Formatted [[WP:ENDASH|dashes]]. "
      : ""
      ) . (($this->modifications["arxiv_upgrade"])
      ? "Updated published arXiv refs. "
      : ""
    );
    if (!$auto_summary) {
      $auto_summary = "Misc citation tidying. ";
    }
    return $auto_summary . "You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].";
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
      if ( stripos($this->text,"Citation bot : comment placeholder") != false )  {
        echo "\n ! Comment placeholder left escaped. Aborting.";
        return FALSE;
      }
      $submit_vars = array(
          "action" => "edit",
          "title" => $my_page->title,
          "text" => $this->text,
          "token" => $my_page->edittoken, // from $result above
          "summary" => $edit_summary ? $edit_summary : $this->edit_summary(),
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
        if ($html_output) echo "\n <span style='color: #e21'>Written to <a href='" . wikiroot . "title=" . urlencode($my_page->title) . "'>" . htmlspecialchars($my_page->title) . '</a></span>';
        else echo "\n Written to " . htmlspecialchars($my_page->title) . '.  ';
        return TRUE;
      } else if ($result->edit->result) {
        echo htmlspecialchars($result->edit->result);
        return TRUE;
      } else if ($result->error->code) {
        // Return error code
        echo "\n ! " . htmlspecialchars(strtoupper($result->error->code)) . ": " . str_replace(array("You ", " have "), array("This bot ", " has "), htmlspecialchars($result->error->info));
        return FALSE;
      } else {
        echo "\n ! Unhandled error.  Please copy this output and <a href=http://code.google.com/p/citation-bot/issues/list>report a bug.</a>";
        return FALSE;
      }
    } else {
      echo "\n - Can't write to " . htmlspecialchars($this->title) . " - prohibited by {{bots]} template.";
    }
  }

  protected function extract_object ($class) {
    $i = 0;
    $text = $this->text;
    $regexp = $class::regexp;
    $placeholder_text = $class::placeholder_text;
    $treat_identical_separately = $class::treat_identical_separately;
    $objects = array();
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
      $this->text = str_ireplace(sprintf($obj::placeholder_text, --$i), $obj->parsed_text(), $this->text); // Case insensitive, since comment placeholder might get title case, etc.
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
}
