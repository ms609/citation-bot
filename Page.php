<?php
/*
 * Page contains methods that will do most of the higher-level work of expanding citations
 * and handling references on the wikipage associated with the Page object.
 * Provides functions to read, parse, expand text (using Template, Comment, Long_Reference,
 * and Short_Reference), handle collected page modifications, and save the edited page text
 * to the wiki.
 */

require_once('Comment.php');
require_once('Short_Reference.php');
require_once('Long_Reference.php');
require_once('Template.php');

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
