<?php
/*
 * Contains constants and text-parsing functions for wikitext comments.
 */

final class Comment {
  // If you ever change PLACEHOLDER_TEXT, please update expandFns.php::remove_comments
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_COMMENT %s # # #';
  const REGEXP = '~<!--.*?-->~us';
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  protected $rawtext;

  public function parse_text($text) {
    $this->rawtext = $text;
  }

  public function parsed_text() {
    return $this->rawtext;
  }
}

final class Nowiki {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_NOWIKI %s # # #';
  const REGEXP = '~<nowiki>.*?</nowiki>~us'; 
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  protected $rawtext;
  
  public function parse_text($text) {
    $this->rawtext = $text;
  }
  
  public function parsed_text() {
    return $this->rawtext;
  }
}

final class Chemistry {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_CHEMISTRY %s # # #';
  const REGEXP = '~<chem>.*?</chem>~us'; 
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  protected $rawtext;
  
  public function parse_text($text) {
    $this->rawtext = $text;
  }
  
  public function parsed_text() {
    return $this->rawtext;
  }
}

final class Mathematics {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MATHEMATICS %s # # #';
  const REGEXP = '~<math>.*?</math>~us'; 
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  protected $rawtext;
  
  public function parse_text($text) {
    $this->rawtext = $text;
  }
  
  public function parsed_text() {
    return $this->rawtext;
  }
}

final class Musicscores {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MUSIC %s # # #';
  const REGEXP = '~<score>.*?</score>~us'; 
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  protected $rawtext;
  
  public function parse_text($text) {
    $this->rawtext = $text;
  }
  
  public function parsed_text() {
    return $this->rawtext;
  }
}

final class Preformated {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_PREFORMAT %s # # #';
  const REGEXP = '~<pre>.*?</pre>~us'; 
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  protected $rawtext;
  
  public function parse_text($text) {
    $this->rawtext = $text;
  }
  
  public function parsed_text() {
    return $this->rawtext;
  }
}
