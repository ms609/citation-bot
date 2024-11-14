<?php

declare(strict_types=1);

/*
 * Parameter includes parsing functions to extract parameters, values, and metadata
 * from templates.
 */

require_once 'user_messages.php';  // @codeCoverageIgnore
require_once 'constants.php';      // @codeCoverageIgnore

final class Parameter {
    public string $pre = '';
    public string $param = '';
    public string $eq = '';
    public string $val = '';
    public string $post = '';

/*
 * Breaks a citation template down to component parts.
 * Expects that any instances of "|" in $text will have been replaced with
 * PIPE_PLACEHOLDER (usually '%%CITATION_BOT_PIPE_PLACEHOLDER%%') before this is called.
 */
    public function parse_text(string $text): void {
        $text = str_replace(PIPE_PLACEHOLDER, '|', $text);
        $split = explode('=', $text, 2);
        // Split the text before the '=' into constituent parts:
        // $pre_eq[1]: any whitespace before the parameter name (including newlines)
        // $pre_eq[2]: the parameter name itself (which can span multiple lines)
        // $pre_eq[3]: any whitespace after the parameter name (including newlines)
        if (preg_match('~^(\s*?)(\S[\s\S]*?)(\s*+)$~u', $split[0], $pre_eq) === false) { // Try non-unicode if this fails
            preg_match('~^(\s*?)(\S[\s\S]*?)(\s*+)$~', $split[0], $pre_eq); // @codeCoverageIgnore
        }
        if (count($split) === 2) {
            // Split the text after the '=' into constituent parts:
            // $post_eq[1]: any whitespace before the parameter value (including newlines)
            // $post_eq[2]: the parameter value itself (which can span multiple lines)
            // $post_eq[3]: any whitespace after the parameter value (including newlines)
            if (preg_match('~^([ \n\r\t\p{Zs}]*)([\s\S]*?)(\s*+)$~u', $split[1], $post_eq) === false) { // Try non-unicode if this fails
                preg_match('~^([ \n\r\t\p{Zs}]*)([\s\S]*?)(\s*+)$~', $split[1], $post_eq);  // @codeCoverageIgnore
            }
            if (count($pre_eq) === 0) {
                $this->eq    = $split[0] . '=' . $post_eq[1];
            } else {
                $this->pre   = $pre_eq[1];
                $this->param = $pre_eq[2];
                $this->eq    = $pre_eq[3] . '=' . $post_eq[1];
            }
            $this->post  = $post_eq[3];
            $this->val   = $post_eq[2];
        } elseif ($pre_eq) {
            $this->pre  = $pre_eq[1];
            $this->val  = $pre_eq[2];
            $this->post = $pre_eq[3];
        } else {
            $this->val  = $text;
        }
        // Comments before parameter names
        while (preg_match('~^# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #(?:\s*)~isu', $this->param, $match)) {
            $this->pre .= $match[0];
            $this->param = str_replace($match[0], '', $this->param);
        }
        // Comments after parameter names
        while (preg_match('~(?:\s*)# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #$~isu', $this->param, $match)) {
            $this->eq = $match[0] . $this->eq;
            $this->param = str_replace($match[0], '', $this->param);
        }
        // Clean up line feeds
        if ($this->val === '' && $this->post === '') {
            if (preg_match('~^([^=]*=[^\n\r]*)([\n\r][\s\S]*)$~u', $this->eq, $match)) {
                $this->eq = $match[1];
                $this->post = $match[2];
            }
        }
    }
/*
 * Returns a string with, for example, 'param1 = value1 | param2 = value2, etc.'
 */
    public function parsed_text(): string {
        return $this->pre . $this->param . $this->eq . $this->val . $this->post;
    }
}
