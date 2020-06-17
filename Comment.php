<?php
declare(strict_types=1);

/*
 * Contains constants and text-parsing functions for wikitext comments.
 */

// If you ever change PLACEHOLDER_TEXT, please update expandFns.php::remove_comments

interface WikiStuffInterface
{
  const TREAT_IDENTICAL_SEPARATELY;
  protected $rawtext;
  public function parse_text(string $text) : void ;
  public function parsed_text() : string ;
}


abstract class WikiThings implements WikiStuffInterface {
  const TREAT_IDENTICAL_SEPARATELY = FALSE;
  protected $rawtext;

  public function parse_text(string $text) : void {
    $this->rawtext = $text;
  }

  public function parsed_text() : string {
    if (!isset($this->rawtext)) die('Attempt to access undefined WikiThings');
    return $this->rawtext;
  }
}

final class Comment extends WikiThings {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_COMMENT %s # # #';
  const REGEXP = '~<!--.*?-->~us';
}

final class Nowiki extends WikiThings {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_NOWIKI %s # # #';
  const REGEXP = '~<nowiki>.*?</nowiki>~us';
}

final class Chemistry extends WikiThings {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_CHEMISTRY %s # # #';
  const REGEXP = '~<chem>.*?</chem>~us';
}

final class Mathematics extends WikiThings {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MATHEMATICS %s # # #';
  const REGEXP = '~<math>.*?</math>~us';
}

final class Musicscores extends WikiThings {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MUSIC %s # # #';
  const REGEXP = '~<score>.*?</score>~us';
}

final class Preformated extends WikiThings {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_PREFORMAT %s # # #';
  const REGEXP = '~<pre>.*?</pre>~us';
}

final class SingleBracket extends WikiThings {
  const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_SINGLE_BRACKET %s # # #';
  const REGEXP = '~(?<!\{)\{[^\{\}]+\}(?!\})~us';
}
