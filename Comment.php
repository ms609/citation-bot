<?php
declare(strict_types=1);

/*
 * Contains constants and text-parsing functions for wikitext comments.
 */

// Do not ever remove CITATION_BOT_PLACEHOLDER from strings, there are lots of REGEX and stripos() calls in the code

abstract class WikiThings {
  public const TREAT_IDENTICAL_SEPARATELY = FALSE;  // These contents of theses items never get edited, so this is safe
  private $rawtext; // Unidsaftialized.  Will crash if read before set; which is good.

  public function parse_text(string $text) : void {
    $this->rawtext = $text;
  }

  public function parsed_text() : string {
    return $this->rawtext;
  }
}

final class Comment extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_COMMENT %s # # #';
  public const REGEXP = ['~<!--.*?-->~us'];
}

final class Nowiki extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_NOWIKI %s # # #';
  public const REGEXP = ['~<nowiki>.*?</nowiki>~us'];
}

final class Chemistry extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_CHEMISTRY %s # # #';
  public const REGEXP = ['~<chem>.*?</chem>~us'];
}

final class Mathematics extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MATHEMATICS %s # # #';
  public const REGEXP = ['~<math>.*?</math>~us'];
}

final class Musicscores extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MUSIC %s # # #';
  public const REGEXP = ['~<score>.*?</score>~us'];
}

final class Preformated extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_PREFORMAT %s # # #';
  public const REGEXP = ['~<pre>.*?</pre>~us'];
}

final class SingleBracket extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_SINGLE_BRACKET %s # # #';
  public const REGEXP = ['~(?<!\{)\{[^\{\}]+\}(?!\})~us'];
}
