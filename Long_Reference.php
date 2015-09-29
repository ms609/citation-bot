<?php
/*
 * Long_Reference extends Item.
 * It contains methods to process long references (parse, generate names, return parsed text).
 */

require_once('Item.php');

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
    ) {
      echo "\n * Generating name for anonymous reference [" . $this->attr['name'] . ']: ' . $this->generate_name();
    } else {
      print "\n * No name for ". $this->attr['name'];
    }
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
