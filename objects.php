<?php
/*$Id$*/
/* Treats comments, templates and references as objects */

/* 
# TODO # 
 - Associate initials with surnames: don't put them on a new line
*/

#define ('ref_regexp', '~<ref.*</ref>~u'); // #TODO DELETE
#define ('refref_regexp', '~<ref.*/>~u'); // #TODO DELETE
$file_revision_id = str_replace(array("Revision: ", "$", " "), "", '$Revision$');
$doitools_revision_id = revisionID();
global $last_revision_id, $edit_initiator;
$edit_initiator = "[dev$doitools_revision_id]";
if ($file_revision_id < $doitools_revision_id) {
  $last_revision_id = $doitools_revision_id;
} else {
  $edit_initiator = str_replace($doitools_revision_id, $file_revision_id, $edit_initiator);
  $last_revision_id = $file_revision_id;
}
quiet_echo ("\nRevision #$last_revision_id");

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
    foreach ($details->query->pages as $p) $my_details = $p;
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
    if (strpos($title, "Template:Cite") !== FALSE) $this->cite_template = TRUE;
    if ($this->cite_template && !$this->text) $this->text = $cite_doi_start_code;
    if ($this->text) return TRUE;
  }
  
  public function expand_text() {
    global $html_output;
    quiet_echo ("\n<hr>[" . date("H:i:s") . "] Processing page '<a href='http://en.wikipedia.org/wiki/" . addslashes($this->title) . "' style='text-weight:bold;'>{$this->title}</a>' &mdash; <a href='http://en.wikipedia.org/?title=". addslashes(urlencode($this->title))."&action=edit' style='text-weight:bold;'>edit</a>&mdash;<a href='http://en.wikipedia.org/?title=" . addslashes(urlencode($this->title)) . "&action=history' style='text-weight:bold;'>history</a> <script type='text/javascript'>document.title=\"Citation bot: '" . str_replace("+", " ", urlencode($this->title)) ."'\";</script>");
    $text = $this->text;
    $this->modifications = array();
    if (!$text) {echo "\n\n  ! No text retrieved.\n"; return false;}
    if ($html_output === -1) ob_start();   
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
    if ($templates) foreach ($templates as $template) {
      if ($template->wikiname() == 'citation') $citation_templates++;
      elseif (preg_match("~[cC]ite[ _]\w+~", $template->wikiname())) $cite_templates++;
      elseif (stripos($template->wikiname(), 'harv') === 0) $harvard_templates++;
    }
    $citation_template_dominant = $citation_templates > $cite_templates;
    echo "\n * $citation_templates {{Citation}} templates and $cite_templates {{Cite XXX}} templates identified.  Using dominant template {{" . ($citation_template_dominant?'Citation':'Cite XXX') . '}}.';
    for ($i = 0; $i < count($templates); $i++) {
      $templates[$i]->process();
      $citation_template_dominant ? $templates[$i]->cite2citation() : $templates[$i]->citation2cite($harvard_templates);
      
      $template_mods = $templates[$i]->modifications();
      foreach (array_keys($template_mods) as $key) {
        if (!$this->modifications[$key]) $this->modifications[$key] = $template_mods[$key];
        else if ($template_mods[$key]) $this->modifications[$key] = array_unique(array_merge($this->modifications[$key], $template_mods[$key]));
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
          if ($name === NULL) continue;
          if (strcasecmp($name, $old_name) === 0) $to_rename[] = $key;
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
    if ($html_output === -1) ob_end_clean();
    return strcasecmp($this->text, $this->start_text) != 0;
  }
  
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
          "token" => $my_page->edittoken,
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
 
// TEMPLATE //
class Template extends Item {
  const placeholder_text = '# # # Citation bot : template placeholder %s # # #';
  const regexp = '~\{\{(?:[^\{]|\{[^\{])+?\}\}~s';
  const treat_identical_separately = FALSE;
  
  protected $name, $param, $initial_param, $citation_template, $mod_dashes;
  
  public function parse_text($text) {
    $this->rawtext = $text;
    $pipe_pos = strpos($text, '|');
    if ($pipe_pos) {
      $this->name = substr($text, 2, $pipe_pos - 2);
      $this->split_params(substr($text, $pipe_pos + 1, -2));
    } else {
      $this->name = substr($text, 2, -2);
      $this->param = NULL;
    }
    if ($this->param) foreach ($this->param as $p) $this->initial_param[$p->param] = $p->val;
  }
  
  protected function split_params($text) {
    // | [pre] [param] [eq] [value] [post]
    $text = preg_replace('~(\[\[[^\[\]]+)\|([^\[\]]+\]\])~', "$1" . PIPE_PLACEHOLDER . "$2", $text);
    if ($this->wikiname() == 'cite doi')
      $text = preg_replace('~d?o?i?\s*[:.,;>]*\s*(10\.\S+).*?(\s*)$~', "$1$2", $text);
    $params = explode('|', $text);
    foreach ($params as $i => $text) {
      $this->param[$i] = new Parameter();
      $this->param[$i]->parse_text($text);
    }
  }
  
  public function lowercase_parameters() {
    for ($i=0; $i < count($this->param); $i++)
      $this->param[$i]->param = strtolower($this->param[$i]->param);
  }
  
  public function process() {
    switch ($this->wikiname()) {
      case 'reflist': $this->page->has_reflist = TRUE; break;
      case 'cite web':
        $this->use_unnamed_params();
        $this->get_identifiers_from_url();
        $this->tidy();
        if ($this->has('journal') || $this->has('bibcode') || $this->has('jstor') || $this->has('arxiv')) {
          if ($this->has('arxiv') && $this->blank('class')) $this->rename('arxiv', 'eprint'); #TODO test arXiv handling
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
        }
      break;
      case 'cite book':
        $this->citation_template = TRUE;
        $this->handle_et_al();
        $this->use_unnamed_params();
        $this->get_identifiers_from_url();
        $this->id_to_param();
        echo "\n* " . $this->get('title');
        $this->correct_param_spelling();
        if ($this->expand_by_google_books()) echo "\n * Expanded from Google Books API";
        $this->tidy();
        if ($this->find_isbn()) echo "\n * Found ISBN " . $this->get('isbn');
      break;
      case 'cite journal': case 'cite document': case 'cite encyclopaedia': case 'cite encyclopedia': case 'citation':
        $this->citation_template = TRUE;
        echo "\n\n* Expand citation: " . $this->get('title');
        $this->use_unnamed_params();
        $this->get_identifiers_from_url();
        if ($this->use_sici()) echo "\n * Found and used SICI";
        $this->id_to_param();
        $this->get_doi_from_text();
        // TODO: Check for the doi-inline template in the title
        $this->handle_et_al();
        $this->correct_param_spelling();
        $this->expand_by_pubmed(); //partly to try to find DOI
        $journal_type = $this->has("periodical") ? "periodical" : "journal";
        if ($this->expand_by_google_books()) echo "\n * Expanded from Google Books API";
        $this->sanitize_doi();
        if ($this->verify_doi()) $this->expand_by_doi();
        $this->tidy(); // Do now to maximize quality of metadata for DOI searches, etc
        $this->expand_by_adsabs(); //Primarily to try to find DOI
        $this->get_doi_from_crossref();
        $this->find_pmid();
        $this->tidy();
      break;
      case 'ref doi': case 'ref pmid': case 'ref jstor': case 'ref pmc':
        $this->add_ref_tags = TRUE;
        echo "\n * Added ref tags to {{{$this->name}}}" . tag();
        $this->name = 'Cite ' . substr($this->wikiname(), 4);
      case 'cite doi': case 'cite pmid': case 'cite jstor': case 'cite pmc':
        $type = substr($this->wikiname(), 5);
        $id = trim_identifier($this->param[0]->val);
        $linked_page = "Template:Cite $type/" . wikititle_encode($id);
        if (!getArticleId($linked_page)) expand_cite_page($linked_page);
    }
    if ($this->citation_tempate) {
      if (!$this->blank('authors') && $this->blank('author')) $this->rename('authors', 'author');
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
  
  public function add_if_new($param, $value) {
    if ($corrected_spelling = $common_mistakes[$param]) $param = $corrected_spelling;
    if (trim($value) == "") return false;
    if (substr($param, -4) > 0 || substr($param, -3) > 0 || substr($param, -2) > 30) {
      // Stop at 30 authors - or page codes will become cluttered! 
      if ($this->get('last29') || $this->get('author29') || $this->get('surname29')) $this->add_if_new('display-authors', 29);
      return false;
    }
    preg_match('~\d+$~', $param, $auNo); $auNo = $auNo[0];
    switch ($param) {
      case "editor": case "editor-last": case "editor-first":
        $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
        if ($this->blank('editor') && $this->blank("editor-last") && $this->blank("editor-first"))
          return $this->add($param, $value); 
        else return false;
      case 'editor4': case 'editor4-last': case 'editor4-first':
        $this->add_if_new('displayeditors', 29);
        return $this->add($param, $value);
      break;
      case "author": case "author1": case "last1": case "last": case "authors": // "authors" is automatically corrected by the bot to "author"; include to avoid a false positive.
        $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
        if ($this->blank("last1") && $this->blank("last") && $this->blank("author") && $this->blank("author1") && $this->blank("editor") && $this->blank("editor-last") && $this->blank("editor-first")) {
          if (strpos($value, ',')) {
            $au = explode(',', $value);
            $this->add($param, formatSurname($au[0]));
            return $this->add('first' . (substr($param, -1) == '1' ? '1' : ''), formatForename(trim($au[1])));
          } else {
            return $this->add($param, $value);
          }
        }
      return false;
      case "first": case "first1":
        if ($this->blank("first") && $this->blank("first1") && $this->blank("author") && $this->blank('author1'))
          return $this->add($param, $value);
      return false;
      case "coauthor": case "coauthors":
        $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
        if ($this->blank("last2") && $this->blank("coauthor") && $this->blank("coauthors") && $this->blank("author"))
          return $this->add($param, $value);
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
        if (strpos($value, ',') && substr($param, 0, 3) == 'aut' && $this->blank("last$auNo") && $this->blank("author$auNo") && $this->blank("coauthors") && strpos($this->get('author') . $this->get('authors'), ' and ') === FALSE && strpos($this->get('author') . $this->get('authors'), ' et al') === FALSE) {
          $au = explode(',', $value);
          $this->add('last' . $auNo, formatSurname($au[0]));
          return $this->add_if_new('first' . $auNo, formatForename(trim($au[1])));
        }
        if ($this->blank("last$auNo") && $this->blank("author$auNo")
                && $this->blank("coauthor") && $this->blank("coauthors")
                && under_two_authors($this->get('author'))) {
          return $this->add($param, $value);
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
        if ($this->blank($param)
                && under_two_authors($this->get('author')) && $this->blank("author" . $auNo)
                && $this->blank("coauthor") && $this->blank("coauthors")) {
          return $this->add($param, $value);
        }
        return false;
      case "date":
        if (preg_match("~^\d{4}$~", sanitize_string($value))) {
          // Not adding any date data beyond the year, so 'year' parameter is more suitable
          $param = "year";
        }
      // Don't break here; we want to go straight in to year;
      case "year":
        if (   ($this->blank("date") || trim(strtolower($this->get('date'))) == "in press")
            && ($this->blank("year") || trim(strtolower($this->get('year'))) == "in press") 
          ) {
          return $this->add($param, $value);
        }
        return false;
      case "periodical": case "journal":
        if ($this->blank("journal") && $this->blank("periodical") && $this->blank("work")) {
          return $this->add($param, sanitize_string($value));
        }
        return false;
      case 'chapter': case 'contribution':
        if ($this->blank("chapter") && $this->blank("contribution")) {
          return $this->add($param, format_title_text($value));
        }
        return false;
      case "page": case "pages":
        if (( $this->blank("pages") && $this->blank("page"))
                || strpos(strtolower($this->get('pages') . $this->get('page')), 'no') !== FALSE
                || (strpos($value, chr(2013)) || (strpos($value, '-'))
                  && !strpos($this->get('pages'), chr(2013))
                  && !strpos($this->get('pages'), chr(150)) // Also en-dash
                  && !strpos($this->get('pages'), chr(226)) // Also en-dash
                  && !strpos($this->get('pages'), '-')
                  && !strpos($this->get('pages'), '&ndash;'))
        ) return $this->add($param, sanitize_string($value));
        return false;
      case 'title': 
        if ($this->blank($param)) {
          return $this->format_title(sanitize_string($value));
        }
        return false;
      case 'class':
        if ($this->blank($param) && strpos($this->get('eprint'), '/') === FALSE ) {        
          return $this->add($param, sanitize_string($value));
        }
        return false;
      case 'doi':
        if ($this->blank($param) &&  preg_match('~(10\..+)$~', $value, $match)) { 
          $this->add('doi', $match[0]);
          $this->verify_doi();
          $this->expand_by_doi();
          return true;
        }
        return false;
      case 'display-authors': case 'displayauthors':
        if ($this->blank('display-authors') && $this->blank('displayauthors')) {
          return $this->add($param, $value);
        }
      return false;
      case 'display-editors': case 'displayeditors':
        if ($this->blank('display-editors') && $this->blank('displayeditors')) {
          return $this->add($param, $value);
        }
      return false;
      case 'doi_brokendate': case 'doi_inactivedate':
        if ($this->blank('doi_brokendate') && $this->blank('doi_inactivedate')) {
          return $this->add($param, $value);
        }
      return false;
      case 'pmid':
        if ($this->blank($param)) {        
          $this->add($param, sanitize_string($value));
          $this->expand_by_pubmed();
          if ($this->blank('doi')) $this->get_doi_from_crossref();
          return true;
        }
      case 'author_separator': case 'author-separator': 
        if ($this->blank($param)) {        
          return $this->add($param, $value);
        }        
      default:
        if ($this->blank($param)) {        
          return $this->add($param, sanitize_string($value));
        }
    }
  }
  
  protected function get_identifiers_from_url() {
    $url = $this->get('url');
    // JSTOR
    if (strpos($url, "jstor.org") !== FALSE) {
      if (strpos($url, "sici")) {
        #Skip.  We can't do anything more with the SICI, unfortunately.
      } elseif (preg_match("~(?|(\d{6,})$|(\d{6,})[^\d%\-])~", $url, $match)) {
        if ($this->get('jstor')) {
          $this->forget('url');
        } else {
          $this->rename("url", "jstor", $match[1]);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
      }
    } else {
      if (preg_match(bibcode_regexp, urldecode($url), $bibcode)) {
        if ($this->get('bibcode')) {
          $this->forget('url');
        } else {
          $this->rename("url", "bibcode", urldecode($bibcode[1]));
        }
      } else if (preg_match("~^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d+)"
                      . "|^http://www\.ncbi\.nlm\.nih\.gov/pmc/articles/PMC(\d+)~", $url, $match)) {
        if ($this->get('pmc')) {
          $this->forget('url');
        } else {
          $this->rename("url", "pmc", $match[1] . $match[2]);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
      } else if (preg_match("~^https?://d?x?\.?doi\.org/([^\?]*)~", $url, $match)) {
        quiet_echo("\n   ~ URL is hard-coded DOI; converting to use DOI parameter.");
        if ($this->get('doi')) {
          $this->forget('url');
        } else {
          $this->rename("url", "doi", urldecode($match[1]));
          $this->expand_by_doi(1);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
      } elseif (preg_match("~10\.\d{4}/[^&\s\|\?]*~", $url, $match)) {
        quiet_echo("\n   ~ Recognized DOI in URL; dropping URL");
        if ($this->get('doi')) {
          $this->forget('url');
        } else {
          $this->rename('url', 'doi', preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]));
          $this->expand_by_doi(1);
        }
      } elseif (preg_match("~\barxiv.org/(?:pdf|abs)/(.+)$~", $url, $match)) {
        //ARXIV
        if ($this->get('eprint')) {
          $this->forget('url');
        } else {
          $this->rename("url", "eprint", $match[1]);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite arxiv';
      } else if (preg_match("~https?://www.ncbi.nlm.nih.gov/pubmed/.*?=?(\d{6,})~", $url, $match)) {
        if ($this->get('pmid')) {
          $this->forget('pmid');
        } else {
          $this->rename('url', 'pmid', $match[1]);
        }
        if (strpos($this->name, 'web')) $this->name = 'Cite journal';
      } else if (preg_match("~^https?://www\.amazon(?P<domain>\.[\w\.]{1,7})/.*dp/(?P<id>\d+X?)~", $url, $match)) {
        if ($match['domain'] == ".com") {
          if ($this->get('asin')) {
            $this->forget('url');
          } else {
            $this->rename('url', 'asin', $match['id']);
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
    if ($doi = $this->get('doi')) return $doi;
    echo "\n - Checking CrossRef database for doi. " . tag();
    $title = $this->get('title');
    $journal = $this->get('journal');
    $author = $this->first_author();
    $year = $this->get('year');
    $volume = $this->get('volume');
    $page_range = $this->page_range();
    $start_page = $page_range[1];
    $end_page = $page_range[2];
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
          echo "\n   * Cannot search CrossRef: " . $result->msg;
        }
        else if ($result["status"] == "resolved") {
          return $result;
        }
      }
      global $fastMode;
      if ($fastMode || !$author || !($journal || $issn) || !$start_page ) return;
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
        echo "\n   * Cannot search CrossRef: " . $result->msg;
      } else if ($result["status"]=="resolved") {
        echo " Successful!"; 
        return $result;
      }
    }
  }
 
  protected function get_doi_from_webpage() { #TODO test 
    if ($doi = $this->has('doi')) return $doi;
    if ($url = trim($this->get('url')) && (strpos($url, "http://") !== false || strpos($url, "https://") !== false)) {
      $url = explode(" ", trim($url));
      $url = $url[0];
      $url = preg_replace("~\.full(\.pdf)?$~", ".abstract", $url);
      $url = preg_replace("~<!--.*-->~", '', $url);
      if (substr($url, -4) == ".pdf") {
        global $html_output;
        echo $html_output
              ? ("\n - Avoiding <a href=\"$url\">PDF URL</a>. <br>")
              : "\n - Avoiding PDF URL $url";
      } else {
        // Try using URL parameter
        global $urlsTried, $slow_mode;
        echo $html_output
              ? ("\n - Trying <a href=\"$url\">URL</a>. <br>")
              : "\n - Trying URL $url";
        // Metas might be hidden if we don't have access the the page, so try the abstract:

        if (@in_array($url, $urlsTried)) {
          echo "URL has been scraped already - and scrapped.<br>";
          return null;
        }
        //Check that it's not in the URL to start with
        if (preg_match("|/(10\.\d{4}/[^?]*)|i", urldecode($url), $doi)) {
          echo "Found DOI in URL." . tag();
          return $this->set('doi', $doi[1]);
        }

        //Try meta tags first.
        $meta = @get_meta_tags($url);
        if ($meta) {
          $this->add_if_new("pmid", $meta["citation_pmid"]);
          foreach ($meta as $oTag) if (preg_match("~^\s*10\.\d{4}/\S*\s*~", $oTag)) {
              echo "Found DOI in meta tags" . tag();
              return $this->set('doi', $oTag);
          }
        }
        if (!$slow_mode) {
          echo "\n -- Aborted: not running in 'slow_mode'!";
        } else if ($size[1] > 0 &&  $size[1] < 100000) { // TODO. The bot seems to keep crashing here; let's evaluate whether it's worth doing.  For now, restrict to slow mode.
          echo "\n -- Querying URL with reported file size of ", $size[1], "b...", $htmlOutput?"<br>":"\n";
          //Initiate cURL resource
          $ch = curl_init();
          curlSetup($ch, $url);
          $source = curl_exec($ch);
          if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
            echo " -- 404 returned from URL.", $htmlOutput?"<br>":"\n";
            // Try anyway.  There may still be metas.
          } else if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 501) {
            echo " -- 501 returned from URL.", $htmlOutput?"<br>":"\n";
            return false;
          }
          curl_close($ch);
          if (strlen($source) < 100000) {
            $doi = getDoiFromText($source, true);
            if (!$doi) {
              checkTextForMetas($source);
            }
          } else {
            echo "\n -- File size was too large. Abandoned.";
          }
        } else {
          echo $htmlOutput
               ? ("\n\n ** ERROR: PDF may have been too large to open.  File size: ". $size[1]. "b<br>")
               : "\n -- PDF too large ({$size[1]}b)";
        }
        if ($doi){
          if (!preg_match("/>\d\.\d\.\w\w;\d/", $doi))
          { //If the DOI contains a tag but doesn't conform to the usual syntax with square brackes, it's probably picked up an HTML entity.
            echo " -- DOI may have picked up some tags. ";
            $content = strip_tags(str_replace("<", " <", $source)); // if doi is superceded by a <tag>, any ensuing text would run into it when we removed tags unless we added a space before it!
            preg_match("~" . doiRegexp . "~Ui", $content, $dois); // What comes after doi, then any nonword, but before whitespace
            if ($dois[1]) {$doi = trim($dois[1]); echo " Removing them.<br>";} else {
              echo "More probably, the DOI was itself in a tag. CHECK it's right!<br>";
              //If we can't find it when tags have been removed, it might be in a <a> tag, for example.  Use it "neat"...
            }
          }
          $urlsTried[] = $url;
          $this->set('doi', urldecode($doi));
        } else {
          $urlsTried[] = $url;
          return false;
        }
        if ($doi) {
          echo " found doi $doi";
          $this->set('doi', $doi);
        } else {
          $urlsTried[] = $url; //Log barren urls so we don't search them again. 
          echo " no doi found.";
        }
      }
    } else {
      echo "No valid URL specified.  ";
    }
  }
 
  protected function find_pmid() {  
    echo "\n - Searching PubMed... " . tag();
    $results = ($this->query_pubmed());
    if ($results[1] == 1) {
      $this->add_if_new('pmid', $results[0]);
    } else {
      echo " nothing found.";
      if (mb_strtolower(substr($citation[$cit_i+2], 0, 8)) == "citation" && $this->blank('journal')) {
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
              : "\n - Errors detected in PMID search (" . print_r($xml->ErrorList, 1) . "); abandoned.";
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
    if ($class && substr($eprint, 0, strlen($class) + 1) == $class . '/')
      $eprint = substr($eprint, strlen($class) + 1);
    $this->set($arxiv_param, $eprint);
    
    if ($eprint) {
      echo "\n * Getting data from arXiv " . $eprint;
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
      $this->add_if_new("title", (string) $xml->entry->title);
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
          $this->add_if_new("pages", str_replace("--", en_dash, $match[0]));
        }
        if (preg_match("~(\d+)(?:\D+(\d+))?~", $journal_data, $match)) {
          $this->add_if_new("volume", $match[1]);
          $this->add_if_new("issue", $match[2]);
          $journal_data = preg_replace("~[\s:,;]*$~", "", 
                  str_replace(array($match[1], $match[2]), "", $journal_data));
        }
        $this->add_if_new("journal", $journal_data);
      } else {
        $this->add_if_new("year", date("Y", strtotime((string)$xml->entry->published)));
      } 
      return true;
    }
    return false;
  }
 
  public function expand_by_adsabs() {
    global $slow_mode;
    if ($slow_mode || $this->has('bibcode')) {
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
          echo "\n   Match for pagination but database journal \"{$journal_string[0]}\" didn't match \"journal = $journal\"." . tag();
          return false;
        }
      }
      if ($xml["retrieved"] == 1) {
        echo tag();
        $this->add_if_new("bibcode", (string) $xml->record->bibcode);
        $this->add_if_new("title", (string) $xml->record->title);
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
            $this->appendto('id', ' ' . substr($journal_start, 13));
          }
        } else {
          $this->add_if_new('journal', $journal_string[0]);
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
    global $editing_cite_doi_template;
    $doi = $this->get('doi');
    if ($doi && ($force || $this->incomplete())) {
      if (preg_match('~^10\.2307/(\d+)$~', $doi)) $this->add_if_new('jstor', substr($doi, 8));
      $crossRef = $this->query_crossref($doi);
      if ($crossRef) {
        echo "\n - Expanding from crossRef record" . tag();
        
        if ($crossRef->volume_title && $this->blank('journal')) {
          $this->add_if_new('chapter', $crossRef->article_title);
          if (strtolower($this->get('title')) == strtolower($crossRef->article_title)) {
            $this->forget('title');
          }
          $this->add_if_new('title', $crossRef->volume_title);
        } else {
          $this->add_if_new('title', $crossRef->article_title);
        }
        $this->add_if_new('series', $crossRef->series_title);
        $this->add_if_new("year", $crossRef->year);
        if ($this->blank(array('editor', 'editor1', 'editor-last', 'editor1-last')) && $crossRef->contributors->contributor) {
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
        $this->add_if_new('journal', $crossRef->journal_title);
        if ($crossRef->volume > 0) $this->add_if_new('volume', $crossRef->volume);
        if ((integer) $crossRef->issue > 1) {
        // "1" may refer to a journal without issue numbers,
        //  e.g. 10.1146/annurev.fl.23.010191.001111, as well as a genuine issue 1.  Best ignore.
          $this->add_if_new('issue', $crossRef->issue);
        }
        if ($this->blank("page")) $this->add_if_new("pages", $crossRef->first_page
                  . ($crossRef->last_page && ($crossRef->first_page != $crossRef->last_page)
                  ? "-" . $crossRef->last_page //replaced by an endash later in script
                  : "") );
        echo " (ok)";
      } else {
        echo "\n - No CrossRef record found for doi '$doi'; marking as broken";
        $this->add_if_new('doi_brokendate', date('Y-m-d'));
      }
    }
  }
 
  public function expand_by_pubmed($force = FALSE) {
    if (!$force && !$this->incomplete()) return;
    if ($pm = $this->get('pmid')) $identifier = 'pmid';
    else if ($pm = $this->get('pmc')) $identifier = 'pmc';
    else return false;
    global $html_output;
    echo "\n - Checking " . ($html_output?'<a href="https://www.ncbi.nlm.nih.gov/pubmed/' . $pm . '" target="_blank">':'') . strtoupper($identifier) . ' ' . $pm . ($html_output ? "</a>" : '') . ' for more details' . tag();
    $xml = simplexml_load_file("http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?tool=DOIbot&email=martins@gmail.com&db=" . (($identifier == "pmid")?"pubmed":"pmc") . "&id=$pm");
    // Debugging URL : view-source:http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&tool=DOIbot&email=martins@gmail.com&id=
    if (count($xml->DocSum->Item) > 0) foreach($xml->DocSum->Item as $item) {
      if (preg_match("~10\.\d{4}/[^\s\"']*~", $item, $match)) $this->add_if_new('doi', $match[0]);
      switch ($item["Name"]) {
                case "Title":   $this->add_if_new('title', str_replace(array("[", "]"), "",(string) $item));
        break; 	case "PubDate": preg_match("~(\d+)\s*(\w*)~", $item, $match);
                                $this->add_if_new('year', (string) $match[1]);
        break; 	case "FullJournalName": $this->add_if_new('journal', (string) $item);
        break; 	case "Volume":  $this->add_if_new('volume', (string) $item);
        break; 	case "Issue":   $this->add_if_new('issue', (string) $item);
        break; 	case "Pages":   $this->add_if_new('pages', (string) $item);
        break; 	case "PmId":    $this->add_if_new('pmid', (string) $item);
        break; 	case "AuthorList":
          $i = 0;
          foreach ($item->Item as $subItem) {
            $i++;
            if (authorIsHuman((string) $subItem)) {
              $jr_test = jrTest($subItem);
              $subItem = $jr_test[0];
              $junior = $jr_test[1];
              if (preg_match("~(.*) (\w+)$~", $subItem, $names)) {
                $first = trim(preg_replace('~(?<=[A-Z])([A-Z])~', ". $1", $names[2]));
                if (strpos($first, '.') && substr($first, -1) != '.') $first = $first . '.';
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
                  if ($this->add_if_new('doi', $match[0])) $this->expand_by_doi();
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
    if (preg_match(siciRegExp, urldecode($this->parsed_text()), $sici)) {
      if ($this->blank($journal, "issn")) $this->set("issn", $sici[1]);
      //if ($this->blank ("year") && $this->blank("month") && $sici[3]) $this->set("month", date("M", mktime(0, 0, 0, $sici[3], 1, 2005)));
      if ($this->blank("year")) $this->set("year", $sici[2]);
      //if ($this->blank("day") && is("month") && $sici[4]) set ("day", $sici[4]);
      if ($this->blank("volume")) $this->set("volume", 1*$sici[5]);
      if ($this->blank("issue") && $this->blank('number') && $sici[6]) $this->set("issue", 1*$sici[6]);
      if ($this->blank("pages", "page")) $this->set("pages", 1*$sici[7]);
      return true;
    } else return false;
  }
 
  protected function query_crossref($doi = FALSE) {
	global $crossRefId;
  if (!$doi) $doi = $this->get('doi');
  if (!$doi) warn('#TODO: crossref lookup with no doi');
  $url = "http://www.crossref.org/openurl/?pid=$crossRefId&id=doi:$doi&noredirect=true";
  $xml = @simplexml_load_file($url);
  if ($xml) {
    $result = $xml->query_result->body->query;
    return ($result["status"]=="resolved")?$result:false;
  } else {
     echo "\n   ! Error loading CrossRef file from DOI $doi!";
     return false;
  }
} 
  
  protected function expand_by_google_books() {
    $url = $this->get('url');
    if ($url && preg_match("~books\.google\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid)) {
      if (strpos($url, "#")) {
        $url_parts = explode("#", $url);
        $url = $url_parts[0];
        $hash = "#" . $url_parts[1];
      }
      $url_parts = explode("&", str_replace("?", "&", $url));
      $url = "http://books.google.com/?id=" . $gid[1];
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
            echo "\n - $part";
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
      $this->add_if_new("title", str_replace("___", ":", $xml->dc___title[0] . ": " . $xml->dc___title[1]));
    } else {
      $this->add_if_new("title", str_replace("___", ":", $xml->title));
    }
    // Possibly contains dud information on occasion
    // $this->add_if_new("publisher", str_replace("___", ":", $xml->dc___publisher)); 
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
        $this->add_if_new("author" . ++$i, formatAuthor(str_replace("___", ":", $author)));
      }
    }
    $this->add_if_new("date", $xml->dc___date);
  }
 
  protected function find_isbn() {
    return FALSE; #TODO restore this service.
    if ($this->blank('isbn') && $this->has('title')) {
      $title = trim($this->get('title'));
      $auth = trim($this->get('author') . $this->get('author1') . ' ' . $this->get('last') . $this->get('last1'));
      global $isbnKey, $over_isbn_limit;
      // TODO: implement over_isbn_limit based on &results=keystats in API
      if ($title && !$over_isbn_limit) {
        $xml = simplexml_load_file("http://isbndb.com/api/books.xml?access_key=$isbnKey&index1=combined&value1=" . urlencode($title . " " . $auth));
        print "\n\nhttp://isbndb.com/api/books.xml?access_key=$isbnKey&index1=combined&value1=" . urlencode($title . " " . $auth . "\n\n");
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
    if ($doi = $this->get('doi')) $this->expand_by_doi(TRUE);
    if ($this->get('pmid')) $this->expand_by_pubmed(TRUE);
    $pages = $this->page_range();
    $pages = $pages[0];
    if (preg_match("~\d\D+\d~", $pages)) $new_pages = $pages;
    if ($doi) $url = "http://dx.doi.org/$doi"; else $url = $this->get('url');
    $stopRegexp = "[\n\(:]|\bAff"; // Not used currently - aff may not be necessary.
    if (!$url) return NULL;
    echo "\n  * Looking for more authors @ $url:";
    echo "\n   - Using meta tags...";
    $meta_tags = get_meta_tags($url);
    if ($meta_tags["citation_authors"]) $new_authors = formatAuthors($meta_tags["citation_authors"], true);
    global $slow_mode;
    if ($slow_mode && !$new_pages && !$new_authors) {
      echo "\n   - Now scraping web-page.";
      //Initiate cURL resource
      $ch = curl_init();
      curlSetup($ch, $url);
      curl_setopt($ch, CURLOPT_MAXREDIRS, 7);  //This means we can't get stuck.
      if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) echo "404 returned from URL.<br>";
      elseif (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 501) echo "501 returned from URL.<br>";
      else {
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
        } else echo "\n   x File size was too large. Abandoned.";
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
    // Load list of parameters used in citation templates.
    //We generated this earlier in expandFns.php.  It is sorted from longest to shortest.
    global $parameter_list;
    if ($this->param) {
      $this->lowercase_parameters();
      $param_occurrences = array();
      $duplicated_parameters = array();
      $duplicate_identical = array();
      foreach ($this->param as $pointer => $par) {
        if ($par->param && ($duplicate_pos = $param_occurrences[$par->param]) !== NULL) {
          array_unshift($duplicated_parameters, $duplicate_pos);
          array_unshift($duplicate_identical, ($par->val == $this->param[$duplicate_pos]->val));
        }
        $param_occurrences[$par->param] = $pointer;
      }
      $n_dup_params = count($duplicated_parameters);
      for ($i = 0; $i < $n_dup_params; $i++) {
        if ($duplicate_identical[$i]) {
          echo "\n * Deleting identical duplicate of parameter: {$this->param[$duplicated_parameters[$i]]->param}\n";
          unset($this->param[$duplicated_parameters[$i]]);
        }
        else {
          $this->param[$duplicated_parameters[$i]]->param = str_replace('DUPLICATE_DUPLICATE_', 'DUPLICATE_', 'DUPLICATE_' . $this->param[$duplicated_parameters[$i]]->param);
          echo "\n * Marking duplicate parameter: {$duplicated_parameters[$i]->param}\n";
        }
      }
      foreach ($this->param as $iP => $p) {
        if (!empty($p->param)) {
          if (preg_match('~^\s*(https?://|www\.)\S+~', $p->param)) { # URL ending ~ xxx.com/?para=val
            $this->param[$iP]->val = $p->param . '=' . $p->val;
            $this->param[$iP]->param = 'url';
            if (stripos($p->val, 'books.google.') !== FALSE) {
              $this->name = 'Cite book';
              $this->process();
            }
          } elseif ($p->param == 'doix') {
            global $dotEncode, $dotDecode;
            $this->param[$iP]->param = 'doi';
            $this->param[$iP]->val = str_replace($dotEncode, $dotDecode, $p->val);
          }
          continue;
        }
        $dat = $p->val;
        $endnote_test = explode("\n%", "\n" . $dat);
        if ($endnote_test[1]) {
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
                if_null_set("pages", $start_page . "-" . $end_page);
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
                    && if_null_set($ris_parameter, trim(implode($ris_part)))
                ) {
              global $auto_summary;
              if (!strpos("Converted RIS citation to WP format", $auto_summary)) {
                $auto_summary .= "Converted RIS citation to WP format. ";
              }
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
            }
          }

        }
        if (preg_match('~^(https?://|www\.)\S+~', $dat, $match)) { #Takes priority over more tenative matches
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
              if ($i) $this->add_if_new($matched_parameter, $match[2][$i]);
              else {
                $this->param[$i]->param = $matched_parameter;
                $this->param[$i]->val = $match[2][0];
              }
            }
          }
        }
        if (preg_match("~(\d+)\s*(?:\((\d+)\))?\s*:\s*(\d+(?:\d\s*-\s*\d+))~", $dat, $match)) { //Vol(is):pp
          $this->add_if_new('volume', $match[1]);
          $this->add_if_new('issue' , $match[2]);
          $this->add_if_new('pages' , $match[3]);
          $dat = trim(str_replace($match[0], '', $dat));
        }
        if (preg_match("~\(?(1[89]\d\d|20\d\d)[.,;\)]*~", $dat, $match)) { #YYYY
          if ($this->blank('year')) {
            $this->set('year', $match[1]);
            $dat = trim(str_replace($match[0], '', $dat));
          }
        }

        $shortest = -1;
        foreach ($parameter_list as $parameter) {
          $para_len = strlen($parameter);
          if (substr(strtolower($dat), 0, $para_len) == $parameter) {
            $character_after_parameter = substr(trim(substr($dat, $para_len)), 0, 1);
            $parameter_value = ($character_after_parameter == "-" || $character_after_parameter == ":")
              ? substr(trim(substr($dat, $para_len)), 1) : substr($dat, $para_len);
            $this->param[$iP]->param = $parameter;
            $this->param[$iP]->val = $parameter_value;
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
              $this->param[$iP]->param = $p1;
              $this->param[$iP]->val = implode(" ", $pAll);
            }
            break;
            case "issues":
            if ($this->blank($p1)) {
              unset($pAll[0]);
              $this->param[$iP]->param = 'issue';
              $this->param[$iP]->val = implode(" ", $pAll);
            }
            break;
            case "access date":
            if ($this->blank($p1)) {
              unset($pAll[0]);
              $this->param[$iP]->param = 'accessdate';
              $this->param[$iP]->val = implode(" ", $pAll);
            }
            break;
          }
        }
        if (!trim($dat)) unset($this->param[$iP]);
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
          echo "\n    - No match found for $content[0].";
      }
    }
    if (trim($id)) $this->set('id', $id); else $this->forget('id');
  }
  
  protected function correct_param_spelling() {
  // check each parameter name against the list of accepted names (loaded in expand.php).
  // It will correct any that appear to be mistyped.
  // TODO replace coauthors with author2, author3, etc.
  global $parameter_list, $common_mistakes;
  $mistake_corrections = array_values($common_mistakes);
  $mistake_keys = array_keys($common_mistakes);
  if ($this->param) foreach ($this->param as $p) {
    $parameters_used[] = $p->param;
  }
  $unused_parameters = ($parameters_used ? array_diff($parameter_list, $parameters_used) : $parameter_list);
  
  $i = 0;
  foreach ($this->param as $p) {
    ++$i;
    if ((strlen($p->param) > 0) && !in_array($p->param, $parameter_list)) {
      if (substr($p->param, 0, 8) == "coauthor") {
        echo "\n  ! The coauthor parameter is deprecated";
        if ($this->has('last2') || $this->has('author2')) {
          echo " please replace this manually.";
        } else {
          $p->param = 'author2';
        }      
      } else {
        echo "\n  *  Unrecognised parameter {$p->param} ";
        $mistake_id = array_search($p->param, $mistake_keys);
        if ($mistake_id) {
          // Check for common mistakes.  This will over-ride anything found by levenshtein: important for "editor1link" !-> "editor-link".
          $p->param = $mistake_corrections[$mistake_id];
          echo 'replaced with ' . $mistake_corrections[$mistake_id] . ' (common mistakes list)';
          continue;
        }
        $p->param = preg_replace('~author(\d+)-(la|fir)st~', "$2st$1", $p->param);
        $p->param = preg_replace('~surname\-?_?(\d+)~', "last$1", $p->param);
        $p->param = preg_replace('~(?:forename|initials?)\-?_?(\d+)~', "first$1", $p->param);
        
        // Check the parameter list to find a likely replacement
        $shortest = -1;
        foreach ($unused_parameters as $parameter)
        {
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
          echo "replaced with $closest (likelihood " . (12 - $shortest) . "/12)";
        } else {
          $similarity = similar_text($p->param, $closest) / strlen($p->param);
          if ($similarity > 0.6) {
            $p->param = $closest;
            echo "replaced with $closest (similarity " . round(12 * $similarity, 1) . "/12)";
          } else {
            echo "could not be replaced with confidence.  Please check the citation yourself.";
          }
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
    if ($this->param) foreach($this->param as $p) {
      $ret .= '|' . $p->parsed_text();
    }
    return $ret;
  }
  
  public function wikiname() {
    return trim(mb_strtolower(str_replace('_', ' ', $this->name)));
  }
  
  ### Tidying and formatting
  protected function tidy() {
    if ($this->added('title')) {
      $this->format_title();
    } else if ($this->is_modified() && $this->get('title')) {
      $this->set('title', straighten_quotes((mb_substr($this->get('title'), -1) == ".") ? mb_substr($this->get('title'), 0, -1) : $this->get('title')));
    }
       
    if ($this->blank(array('date', 'year')) && $this->has('origyear')) $this->rename('origyear', 'year');
    
    if (!($authors = $this->get('authors'))) $authors = $this->get('author'); # Order _should_ be irrelevant as only one will be set... but prefer 'authors' if not.
    if (preg_match('~([,;])\s+\[\[|\]\]([;,])~', $authors, $match)) {
      $this->add_if_new('author-separator', $match[1] ? $match[1] : $match[2]);
      $new_authors = explode($match[1] . $match[2], $authors);
      $this->forget('author'); $this->forget('authors');
      for ($i = 0; $i < count($new_authors); $i++) {
        $this->add_if_new("author" . ($i + 1), trim($new_authors[$i]));
      }
    }
    
    if ($this->param) foreach ($this->param as $p) {
      preg_match('~(\D+)(\d*)~', $p->param, $pmatch);
      switch ($pmatch[1]) { 
        case 'author': case 'authors': case 'last': case 'surname':
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
          break;
        case 'journal': case 'periodical': $p->val = capitalize_title($p->val, FALSE, FALSE); break;
        case 'edition': $p->val = preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $p->val);break; // Don't want 'Edition ed.'
        case 'pages': case 'page': case 'issue': case 'year': 
          if (!preg_match("~^[A-Za-z ]+\-~", $p->val) && mb_ereg(to_en_dash, $p->val)) {
            $this->mod_dashes = TRUE;
            echo ( "\n   ~ Upgrading to en-dash in" . $p->param . tag());
            $p->val = mb_ereg_replace(to_en_dash, en_dash, $p->val);
          }
          break;
      }
    }
    if ($to_add) foreach ($to_add as $key => $val) {
      $this->add_if_new($key, $val);
    }
    
    if ($others) {
      if ($this->has('others')) $this->appendto('others', '; ' . $others);
      else $this->set('others', $others);
    }
    
    if ($this->number_of_authors() == 9 && $this->display_authors() == FALSE) {
      $this->display_authors(8); // So that displayed output does not change
      echo "\n * Exactly 9 authors; look for more [... tidy]:";
      $this->find_more_authors();
      echo "now we have ". $this->number_of_authors() ."\n";
      if ($this->number_of_authors() == 9) $this->display_authors(9); // Better display an author's name than 'et al' when the et al only hides 1 author!
    }
    
    if ($this->added('journal') || $journal && $this->added('issn')) $this->forget('issn');    
    
    if ($journal) {
      $volume = $this->get('volume');
      if (($this->has('doi') || $this->has('issn'))) $this->forget('publisher', 'tidy');
      // Replace "volume = B 120" with "series=VB, volume = 120
      if (preg_match("~^([A-J])(?!\w)\d*\d+~u", $volume, $match) && mb_substr(trim($journal), -2) != " $match[1]") {
        $journal .= " $match[1]";
        $this->set('volume', trim(mb_substr($volume, mb_strlen($match[1]))));
      }
      $this->set('journal', $journal);
        // Clean up after errors in publishers' databases
      if (0 === strpos(trim($journal), "BMC ") && $this->page_range()) {
        $this->forget('issue');
        echo "\n   - dropping issue number (BMC journals only have page numbers)";
      }
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
    if ($this->has('accessdate') && $this->lacks('url')) $this->forget('accessdate');
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
    global $pcEncode, $pcDecode, $spurious_whitespace;
    $this->set('doi', str_replace($spurious_whitespace, '', str_replace($pcEncode, $pcDecode, str_replace(' ', '+', trim(urldecode($doi))))));
    return true;
  }
  
  protected function verify_doi () {
    $doi = $this->get('doi');
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
    if (substr($doi, 0, 3) != "10.") $trial[] = $doi;
    if (preg_match("~^(.+)(10\.\d{4}/.+)~", trim($doi), $match)) {
      $trial[] = $match[1];
      $trial[] = $match[2];
    }
    $replacements = array (      "&lt;" => "<",      "&gt;" => ">",    );
    if (preg_match("~&[lg]t;~", $doi)) $trial[] = str_replace(array_keys($replacements), $replacements, $doi);
    if ($trial) foreach ($trial as $try) {
      // Check that it begins with 10.
      if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) $try = "10." . $match[1];
      if ($this->expand_by_doi($try)) {$this->set('doi', $try); $doi = $try;}
    }    
    echo "\n   . Checking that DOI $doi is operational..." . tag();
    if ($this->query_crossref() === FALSE) {
      $this->set("doi_brokendate", date("Y-m-d"));
      echo "\n   ! Broken doi: $doi";
      return FALSE;
    } else {
      $this->forget('doi_brokendate');
      $this->forget('doi_inactivedate');
      echo ' DOI ok.';
      return TRUE;
    }
  }
  
  public function check_url() {
    // Check that the URL functions, and mark as dead if not.
    /*  Disable; to re-enable, we should log possible 404s and check back later.
     * Also, dead-link notifications should be placed ''after'', not within, the template.

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
    global $author_parameters;
    foreach ($author_parameters as $i => $group) {
      foreach ($group as $param) {
        if (strpos($this->get($param), 'et al')) {
          $authors_missing = true;
          $val_base = preg_replace("~,?\s*'*et al['.]*~", '', $this->get($param));
          if ($i == 1) {
            // then there's scope for "Smith, AB; Peters, Q.R. et al"
            $coauthor_parameter = strpos($param, 'co') === FALSE ? 0 : 1;
            // then we (probably) have a list of authors joined by commas in our first parameter
            if (under_two_authors($val_base)) {
              if ($param == 'author') $this->rename('author', 'authors');
            }
            if (FALSE) { # Some Wikipedians objected to this feature
              foreach ($authors as $au) {
                if ($i == 1) {
                  if ($coauthor_parameter) {
                    $this->forget($param);
                    $this->set('author2', $au);
                  } elseif ($param == 'authors') {
                    $this->add_if_new('last1', $au); // add_if_new will separate initials into first1
                    $this->forget('authors');
                  } else {
                    $this->set($param, $au);
                  }
                  $i = 2;
                }
                else {
                  $this->add_if_new('author' . ($i++ + $coauthor_parameter), $au);
                }
              }
              $i--;
            } else {
              $this->set($param, $val_base);
            }
          }
          if (trim($val_base) == "") {
            $this->forget($param);
          }
          $this->add_if_new('author' . ($i + 1), 'and others');
          $this->add_if_new('displayauthors', $i);
        }
      }
    }
  }
  
  public function cite_doi_format() {
    global $dotEncode, $dotDecode;
    echo "\n   * Cite Doi formatting... " . tag();
    $this->tidy();
    $doi = $this->get('doi');
    
    // If we only have the first author, look for more!
    if ($this->blank('coauthors')
       && $this->blank('author2')
       && $this->blank('last2')
       && $doi) {
      echo "\n     - Looking for co-authors & page numbers...";
      $this->find_more_authors();
    }
    for ($i = 1; $i < 100; $i ++) {
      foreach (array("author", "last", "first", 'editor') as $param) {
        if ($this->get($param . $i) == "") {
          $this->forget($param . $i);
        }
      }
    }
    // Check that DOI hasn't been urlencoded.  Note that the doix parameter is decoded and used in step 1.
    if (strpos($doi, ".2F") && !strpos($doi, "/")) {
      $this->set('doi', str_replace($dotEncode, $dotDecode, $doi));
    }

    // Cycle through authors
    for ($i = null; $i < 100; $i++) {
      if (strpos(($au = $this->get("author$i")), ', ')) {
        // $au is an array with two parameters: the surname [0] and forename [1].
        $au = explode(', ', $au);
        $this->forget("author$i");
        $this->set("author$i", formatSurname($au[0])); // i.e. drop the forename; this is safe in $au[1]
      } else if ($this->get("first$i")) {
        $au[1] = $this->get("first$i");
      } else {
         unset($au);
      }
      if ($au[1]) {
        if ($au[1] == mb_strtoupper($au[1]) && mb_strlen($au[1]) < 4) {
          // Try to separate Smith, LE for Smith, Le.
          $au[1] = preg_replace("~([A-Z])[\s\.]*~u", "$1.", $au[1]);
        }
        if (trim(mb_strtoupper(preg_replace("~(\w)[a-z]*.? ?~u", "$1. ", trim($au[1]))))
                != trim($this->get("first$i"))) {
          // Don't try to modify if we don't need to change
          $this->set("first$i", mb_strtoupper(preg_replace("~(\w)[a-z]*.? ?~u", "$1. ", trim($au[1])))); // Replace names with initials; beware hyphenated names!
        }
        $para_pos = $this->get_param_position("first$i");
        if ($para_pos > 1) {
          $this->param[$this->get_param_position("first$i") - 1]->post = str_replace(array("\r", "\n"), " ", $this->param[$this->get_param_position("first$i") - 1]->post); // Save space by including on same line as previous parameter
        }
      }
    }
    if ($pp_start = $this->get('pages')) {
      // Format pages to R100-R102 format
      if (preg_match("~([A-Za-z0-9]+)[^A-Za-z0-9]+([A-Za-z0-9]+)~", $pp_start, $pp)) {
         if (strlen($pp[1]) > strlen($pp[2])) {
            // The end page range must be truncated
            $this->set('pages', str_replace("!!!DELETE!!!", "", preg_replace("~([A-Za-z0-9]+[^A-Za-z0-9]+)[A-Za-z0-9]+~",
            ("$1!!!DELETE!!!" . substr($pp[1], 0, strlen($pp[1]) - strlen($pp[2])) . $pp[2])
            , $pp_start)));
         }
      }
    }
  }
  
  public function citation2cite ($harvard_style = false) {
    if ($this->wikiname() != 'citation') return ;
    if ($harvard_style) $this->add_if_new("ref", "harv");
    $this->add_if_new("postscript", "<!-- Bot inserted parameter. Either remove it; or change its value to \".\" for the cite to end in a \".\", as necessary. -->{{inconsistent citations}}");
  
    if ($this->has('inventor-last') || $this->has('inventor-surname') || $this->has('inventor1-surname')
            || $this->has('inventor1-last') || is ('inventor')) $this->name = "Cite patent";
    elseif ($this->has('journal')) $this->name = "Cite journal";
    elseif ($this->has('agency') || $this->has('newspaper') || $this->has('magazine') || $this->has('periodical')) $this->name = "Cite news";
    elseif ($this->has('encyclopedia')) $this->name = "Cite encyclopedia";
    elseif ($this->has('conference') || $this->has('conferenceurl')) $this->name = "Cite conference";

    // Straightforward cases now out of the way... now for the trickier ones
    elseif ($this->has('chapter') || $this->has('editor') || $this->has('editor-last') || $this->has('editor1') || $this->has('editor1-last')) $this->name = "Cite book";
     // Books usually catalogued by year; no month expected
    elseif ($this->blank('date', 'month') && ($this->has('isbn') || $this->has('oclc') || $this->has('series'))) $this->name = "Cite book";
    elseif ($this->has('publisher')) {
      // This should be after we've checked for a journal parameter
      if (preg_match("~\w\.\w\w~", $this->get('publisher'))) {
       // it's a fair bet the publisher is a web address
        $this->name = "Cite web";
      } else {
        $this->name = "Cite document";
      }
    }
    elseif ($this->has('url')) $this->name = "Cite web"; // fall back to this if URL
    else $this->name = "Cite document"; // If no URL, cite journal ought to handle it okay
    $this->modifications['cite_type'] = TRUE;
    echo "\n    Converting to dominant {{Cite XXX}} template";
  }
  
  public function cite2citation() {
    if (!preg_match("~[cC]ite[ _]\w+~", $this->wikiname())) return ;
    $this->add_if_new("postscript", ".");
    $this->modifications['cite_type'] = TRUE;
    $this->name = 'Citation';
    echo "\n    Converting to dominant {{Citation}} template";
  }
  
  
  // Retrieve parameters 
  public function display_authors($newval = FALSE) {
    if ($newval && is_int($newval)) {
      $this->forget('displayauthors');
      echo "\n ~ Seting display-authors to $newval" . tag();
      $this->set('display-authors', $newval);
    }
    if (($da = $this->get('display-authors')) === NULL) $da = $this->get('displayauthors');
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
    preg_match("~[^.,;\s]{2,}~u", implode(' ', 
            array($this->get('author'), $this->get('author1'), $this->get('last'), $this->get('last1')))
            , $first_author);
    return $first_author[0];
  }

  public function page() {return ($page = $this->get('pages') ? $page : $this->get('page'));}
  
  public function name() {return trim($this->name);}
  
  public function page_range() {
    preg_match("~(\w?\w?\d+\w?\w?)(?:\D+(\w?\w?\d+\w?\w?))?~", $this->page(), $pagenos);
    return $pagenos;
  }
  
  // Amend parameters
  public function rename($old, $new, $new_value = FALSE) {
    foreach ($this->param as $p) {
      if ($p->param == $old) {
        $p->param = $new;
        if ($new_value) $p->val = $new_value;
      }
    }
  }
  
  public function get($name) {
    if ($this->param) foreach ($this->param as $p) {
      if ($p->param == $name) return $p->val;
    }
    return NULL;
  }
  
  protected function get_param_position ($needle) {
    if ($this->param) foreach ($this->param as $i => $p) {
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
    if (($pos = $this->get_param_position($par)) !== NULL) return $this->param[$pos]->val = $val;
    if ($this->param[0]) {
      $p = new Parameter;
      $p->parse_text($this->param[$this->param[1] ? 1 : 0]->parsed_text()); // Use second param if present, in case first pair is last1 = Smith | first1 = J.\n
    } else {
      $p = new Parameter;
      $p->parse_text('| param = val');
    }
    $p->param = $par;
    $p->val = $val;
    $insert_after = prior_parameters($par);
    foreach (array_reverse($insert_after) as $after) {
      if (($insert_pos = $this->get_param_position($after)) !== NULL) {
        $this->param = array_merge(array_slice($this->param, 0, $insert_pos + 1), array($p), array_slice($this->param,$insert_pos + 1));
        return true;
      }
    }
    $this->param[] = $p;
    return true;
  }
  
  public function appendto($par, $val) {
    if ($pos=$this->get_param_position($par)) return $this->param[$pos]->val = $this->param[$pos]->val . $val;
    else return $this->set($par, $val);
  } 
  
  public function forget ($par) {
    $pos = $this->get_param_position($par);
    if ($pos !== NULL) {
      echo "\n   - Dropping redundant parameter $par" . tag();
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
    if ($this->param) foreach ($this->param as $p) $new[$p->param] = $p->val; else $new = array();
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
    return ($this->add_ref_tags ? '<ref>' : '') . '{{' . $this->name . $this->join_params() . '}}' . ($this->add_ref_tags ? '</ref>' : '');
  }
}

# PARAMETERS #
class Parameter {
  public $pre, $param, $eq, $val, $post;
  
  public function parse_text($text) {
    $text = str_replace(PIPE_PLACEHOLDER, '|', $text);
    $split = explode('=', $text, 2);
    preg_match('~^(\s*?)(\S[\s\S]*?)(\s*+)$~m', $split[0], $pre_eq);
    if (count($split) == 2) {
      preg_match('~^(\s*)([\s\S]*?)(\s*+)$~', $split[1], $post_eq);
      $this->pre   = $pre_eq[1];
      $this->param = $pre_eq[2];
      $this->eq    = $pre_eq[3] . '=' . $post_eq[1];
      $this->post  = $post_eq[3];
      $this->parse_val($post_eq[2]);
    } else if ($pre_eq) {
      $this->pre  = $pre_eq[1];
      $this->val  = $pre_eq[2];
      $this->post = $pre_eq[3];
    } else {
      $this->val  = $text;
    }
  }
  
  protected function parse_val($value) {
    switch ($this->param) {
      case 'pages':
        $this->val = mb_ereg_replace(to_en_dash, en_dash, $value);
      break;
      default: $this->val = $value;
    }
  }
  
  public function parsed_text() {
    return $this->pre . $this->param . (($this->param && empty($this->eq))?' = ':$this->eq) . $this->val . $this->post;
  }
}

/** Returns a properly capitalsied title.
 *  	If sents is true (or there is an abundance of periods), it assumes it is dealing with a title made up of sentences, and capitalises the letter after any period.
  *		If not, it will assume it is a journal abbreviation and won't capitalise after periods.
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

global $author_parameters;
$author_parameters = array(
    1  => array('surname'  , 'forename'  , 'initials'  , 'first'  , 'last'  , 'author', 
                'surname1' , 'forename1' , 'initials1' , 'first1' , 'last1' , 'author1', 'authors'),
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