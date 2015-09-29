<?php
/*
 * Extends Item. Contains constants and text-parsing functions for wikitext comments.
 */

require_once('Item.php');

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
