<?php
/*
 * Parameter includes parsing functions to extract parameters, values, and metadata
 * from templates.
 */

class Parameter {
  public $pre, $param, $eq, $val, $post;

/*
 * Breaks a citation template down to component parts.
 * Expects that any instances of "|" in $text will have been replaced with
 * PIPE_PLACEHOLDER (usually '%%CITATION_BOT_PIPE_PLACEHOLDER%%' and set
 * in expandFns.php) before this function is called.
 */
  public function parse_text($text) {
    $text = str_replace(PIPE_PLACEHOLDER, '|', $text);
    $split = explode('=', $text, 2);
    // Split the text before the '=' into constituent parts:
    // $pre_eq[1]: any whitespace before the parameter name (including newlines)
    // $pre_eq[2]: the parameter name itself (which can span multiple lines)
    // $pre_eq[3]: any whitespace after the parameter name (including newlines)
    preg_match('~^(\s*?)(\S[\s\S]*?)(\s*+)$~', $split[0], $pre_eq);
    if (count($split) == 2) {
      // Split the text after the '=' into constituent parts:
      // $post_eq[1]: any whitespace before the parameter value (not including newlines)
      // $post_eq[2]: the parameter value itself (which can span multiple lines)
      // $post_eq[3]: any whitespace after the parameter value (including newlines)
      preg_match('~^([ \t\p{Zs}]*)([\s\S]*?)(\s*+)$~', $split[1], $post_eq);
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

  // FIXME: this does not appear to actually parse values, should be renamed.
  protected function parse_val($value) {
    switch ($this->param) {
      case 'pages':
        if (stripos($value, "http") === FALSE) $value = mb_ereg_replace(TO_EN_DASH, EN_DASH, $value);
        $this->val = $value;
      break;
      default: $this->val = $value;
    }
  }

/*
 * Returns a string with, for example, 'param1 = value1 | param2 = value2, etc.'
 * FIXME: might be better named "join_parsed_text" or similar to make it clear what
 * this function does to the parsed text.
 */
  public function parsed_text() {
    if ($this->param && empty($this->eq)) {
      $this->eq = ' = ';
    }
    return $this->pre . $this->param . $this->eq . $this->val . $this->post;
  }
}
