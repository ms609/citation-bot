<?php
/*
 * Item is the base class for:
 *     Comment
 *     Page
 *     Template
 *
 * It defines variables and functions but doesn't offer much other structure. 
 * Implementation details of its child classes vary significantly.
 *
 */

class Item {
  protected $rawtext;
  public $occurrences, $page;
  
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
      $this->text = str_ireplace(sprintf($obj::PLACEHOLDER_TEXT, --$i), $obj->parsed_text(), $this->text); // Case insensitive, since comment placeholder might get title case, etc.
  }

}
