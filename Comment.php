<?php
declare(strict_types=1);

/*
 * Contains constants and text-parsing functions for wikitext comments.
 */

// Do not ever remove CITATION_BOT_PLACEHOLDER from strings, there are lots of REGEX and stripos() calls in the code

abstract readonly class WikiThings {
  public const TREAT_IDENTICAL_SEPARATELY = FALSE;  // The contents of theses items never get edited, so this is safe
  private string $rawtext; // Uninitialized.  Will crash if read before set; which is good.

  public function parse_text(string $text) : void {
    $this->rawtext = $text;
  }

  public function parsed_text() : string {
    return $this->rawtext;
  }
}

final readonly class Comment extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_COMMENT %s # # #';
  public const REGEXP = ['~<!--[^\<\>\-]*?-->~us', '~<!--[\s\S]*?-->~us'];
}

final readonly class Nowiki extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_NOWIKI %s # # #';
  public const REGEXP = ['~<nowiki>[^\<\>]*?</nowiki>~us', '~<nowiki>[\s\S]*?</nowiki>~us'];
}

final readonly class Chemistry extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_CHEMISTRY %s # # #';
  public const REGEXP = ['~<chem>[\s\S]*?</chem>~us'];
}

final readonly class Mathematics extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MATHEMATICS %s # # #';
  public const REGEXP = ['~<math(?:| chem)(?:| display=.inline.|display=.block.)>[\s\S]*?</math>~us'];
}

final readonly class Musicscores extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MUSIC %s # # #';
  public const REGEXP = ['~<score>[\s\S]*?</score>~us'];
}

final readonly class Preformated extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_PREFORMAT %s # # #';
  public const REGEXP = ['~<pre>[\s\S]*?</pre>~us'];
}

final readonly class SingleBracket extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_SINGLE_BRACKET %s # # #';
  public const REGEXP = ['~(?<!\{)\{[^\{\}]+\}(?!\})~us'];
}

final readonly class TripleBracket extends WikiThings {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_TRIPLE_BRACKET %s # # #';
  public const REGEXP = ['~(?<!\{)\{\{\{[^\{\}]+\}\}\}(?!\})~us'];
}
