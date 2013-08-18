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
    $pipe_pos = strpos($text, '|');
    if ($pipe_pos) {
      $this->name = substr($text, 2, $pipe_pos-2);
      $this->split_params(substr($text, $pipe_pos + 1, -2));
    } else {
      $this->name = substr($text, 2, -2);
      $this->param = NULL;
    }
  }
  
  protected function join_params() {
    foreach($this->param as $p) {
      $ret .= '|' . $p->parsed_text();
    }
    return $ret;
  }
    
  protected function split_params($text) {
    # | [pre] [param] [eq] [value] [post]
    $params = explode('|', $text);
    foreach ($params as $i => $text) {
      $this->param[$i] = new Parameter();
      $this->param[$i]->parse_text($text);
    }
  }
  
  public function add_param($param, $val) {
    if ($this->param[0]) {
      $p = $this->param[0];
    } else {
      $p = new Parameter;
      $p->parse_text('| param = val');
    }
    $p->param = $param;
    $p->val = $val;
    $this->param[] = $p;
  }
  
  public function parsed_text() {
    return '{{' . $this->name . $this->join_params() . '}}';
  }
}

class Parameter {
  public $pre, $param, $eq, $val, $post;
  
  public function parse_text($text) {
    $split = explode('=', $text, 2);
    preg_match('~^\s+~', $split[0], $pre);
    preg_match('~\s+$~', $split[0], $pre_eq);
    if ($split[1]) {
      preg_match('~^\s+~', $split[1], $post_eq);
      preg_match('~\s+$~', $split[1], $post);
      $this->pre = $pre[0];
      $this->param = trim($split[0]);
      $this->eq = $pre_eq[0] . '=' . $post_eq[0];
      $this->val = trim($split[1]);
      $this->post= $post[0];
    } else {
      $this->pre = $pre[0];
      $this->param = trim($split[0]);
      $this->post = $pre_eq[0];
    }
  }
  
  public function parsed_text() {
    return $this->pre . $this->param . $this->eq . $this->val . $this->post;
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
  }
  return array($text, $objects);
}