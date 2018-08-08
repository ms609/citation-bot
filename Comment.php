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
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_NOWIKI %s # # #';  // Have space in nowiki so that it does not through some crazy bug match itself recursively
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
