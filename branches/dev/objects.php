<?php
/*$Id$*/
/* Treats comments, templates and references as objects */

define ('ref_regexp', '~<ref.*</ref>~u');
define ('refref_regexp', '~<ref.*/>~u');

class Item {
  protected $rawtext;
  public $occurrences;
}

class Comment extends Item {
  const placeholder_text = '# # # Citation bot : comment placeholder %s # # #';
  const regexp = '~<!--.*-->~us';
  
  public function parse_text($text) {
    $this->rawtext = $text;
  }
  
  public function parsed_text() {
    return $this->rawtext;
  } 
}

class Template extends Item {
  const placeholder_text = '# # # Citation bot : template placeholder %s # # #';
  const regexp = '~\{\{(?:[^\{]|\{[^\{])+?\}\}~s';
  
  protected $name;
  protected $parameters;
  
  public function parse_text($text) {
    $this->rawtext = $text;
    $pipe_pos <- strpos($text, '|');
    if ($pipe_pos) {
      $this->name = substr($text, 2, $pipe_pos-3);
      $this->parameters = this$split_params(substr($text, $pipe_pos));
    } else {
      $this->name = substr($text, 2, -2);
      $this->parameters = NULL;
    }   
  }
  
  private function join_params() {
    
  }
  
  private function split_params($text) {
    preg_match_all('~(?P<s1>\s*)' . '\|' . '(?:(?P<s2>\s*)(?P<param>[\w\d_-]+)(?P<s3>\s*)=)?' . '(?P<s4>\s*)(?P<value>[^|]+?)(?P<s5>\s*)~', $text, $matches);
    print_r($matches);
  }
  
  public function parsed_text() {
    return '{{' . $this->name . this->join_params . '}}';
  }
}

function extract_object ($text, $class) {
  $i = 0;
  $regexp = $class::regexp;
  $placeholder_text = $class::placeholder_text;
  while(preg_match($regexp, $text, $match)) {
  print_r($match);
    $obj = new $class();
    $obj->parse_text($match[0]);
    $objects[] = $obj;
    $text = str_replace($match[0], sprintf($placeholder_text, $i++), $text, $matches);
    $obj->occurrences = $matches;
    print "\n$placeholder_text\n$text\n == \n";
  }
  return array($text, $objects);
}