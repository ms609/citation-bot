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

class Short_Reference extends Item {
  const placeholder_text = '# # # Citation bot : short ref placeholder %s # # #';
  const regexp = '~<ref\s[^>]+?/>~s';
  
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

class Long_Reference extends Item {
  const placeholder_text = '# # # Citation bot : long ref placeholder %s # # #';
  const regexp = '~<ref\s[^/>]+?>.*?<\s*/\s*ref\s*>~s';
  
  protected $open_start, $open_attr, $open_end, $content, $close;
  protected $rawtext;
  
  public function parse_text($text) {
    preg_match('~(<ref\s+)(.*)(\s*>)(.*?)(<\s*/\s*ref\s*>)~s', $text, $bits);
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
    foreach ($this->attr as $key => $value) {
      $middle .= $key . '=' . $value;
    }
    return $this->open_start . $middle . $this->open_end . $this->content . $this -> close;
  }
  
}
  
class Template extends Item {
  const placeholder_text = '# # # Citation bot : template placeholder %s # # #';
  const regexp = '~\{\{(?:[^\{]|\{[^\{])+?\}\}~s';
  
  protected $name, $param;
  
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
    if ($this->param) foreach($this->param as $p) {
      $ret .= '|' . $p->parsed_text();
    }
    return $ret;
  }
  
  protected function wikiname() {
    return strtolower(str_replace('_', ' ', $this->name));
  }
  
  protected function split_params($text) {
    # | [pre] [param] [eq] [value] [post]
    if ($this->wikiname() == 'cite doi')
      $text = preg_replace('~d?o?i?\s*[:.,;>]*\s*(10\.\S+).*?(\s*)$~', "$1$2", $text);
    $params = explode('|', $text);
    foreach ($params as $i => $text) {
      $this->param[$i] = new Parameter();
      $this->param[$i]->parse_text($text);
    }
  }
  
  public function get_param_position ($needle) {
    foreach ($this->param as $i => $p) {
      if ($p->param == $needle) return $i;
    }
  }
  
  public function par($name) {
    foreach ($this->param as $p) {
      if ($p->param == $name) return $p->value;
    }
  }
   
  public function add_param($par, $val) {
    if ($this->param[0]) {
      $p = new Parameter;
      $p->parse_text($this->param[0]->parsed_text());
    } else {
      $p = new Parameter;
      $p->parse_text('| param = val');
    }
    $p->param = $par;
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
      $this->parse_val(trim($split[1]));
      $this->post= $post[0];
    } else {
      $this->pre = $pre[0];
      $this->param = trim($split[0]);
      $this->post = $pre_eq[0];
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
    return $this->pre . $this->param . $this->eq . $this->val . $this->post;
  }
}

function extract_object ($text, $class) {
  $i = 0;
  $regexp = $class::regexp;
  $placeholder_text = $class::placeholder_text;
  while(preg_match($regexp, $text, $match)) {
    $obj = new $class();
    $obj->parse_text($match[0]);
    $objects[] = $obj;
    $text = str_replace($match[0], sprintf($placeholder_text, $i++), $text, $matches);
    $obj->occurrences = $matches;
  }
  return array($text, $objects);
}

function replace_object ($text, $objects) {
  $i = count($objects);
  foreach (array_reverse($objects) as $obj) {
    $placeholder_format = $obj::placeholder_text;
    $text = str_replace(sprintf($placeholder_format, --$i), $obj->parsed_text(), $text);
  }
  return $text;
} 