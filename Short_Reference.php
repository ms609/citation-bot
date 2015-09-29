<?php
/*
 * Short_Reference extends Item. It contains constants and parsing functions for
 * short ref tags.
 */

require_once('Item.php');

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
