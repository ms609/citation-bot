<?php
class Parameter {
  public $pre, $param, $eq, $val, $post;

  public function parse_text($text) {
    $text = str_replace(PIPE_PLACEHOLDER, '|', $text);
    $split = explode('=', $text, 2);
    preg_match('~^(\s*?)(\S[\s\S]*?)(\s*+)$~m', $split[0], $pre_eq);
    if (count($split) == 2) {
      preg_match('~^(\s*)([\s\S]*?)(\s*+)$~', $split[1], $post_eq);
      if (count($pre_eq) == 0) {
        $this->eq    = $split[0] . '=' . $post_eq[1];
      } else {
        $this->pre   = $pre_eq[1];
        $this->param = $pre_eq[2];
        $this->eq    = $pre_eq[3] . '=' . $post_eq[1];
      }
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
