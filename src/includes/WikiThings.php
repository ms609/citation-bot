<?php

declare(strict_types=1);

/*
 * Contains constants and text-parsing functions for wikitext comments.
 */

// Do not ever remove CITATION_BOT_PLACEHOLDER from strings, there are lots of REGEX and stripos() calls in the code

abstract class WikiThings {
    public const bool TREAT_IDENTICAL_SEPARATELY = false;  // The contents of these items never get edited, so this is safe
    private string $rawtext; // Uninitialized.  Will crash if read before set; which is good.

    public function parse_text(string $text): void {
        $this->rawtext = $text;
    }

    public function parsed_text(): string {
        return $this->rawtext;
    }
}

final class Comment extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_COMMENT %s # # #';
    public const array REGEXP = ['~<!--[^\<\>\-]*?-->~us', '~<!--[\s\S]*?-->~us'];
}

final class Nowiki extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_NOWIKI %s # # #';
    public const array REGEXP = ['~<nowiki(?:\s[^>]*)?\s*/>|<nowiki(?:\s[^>]*)?>(?:[\s\S]*?</nowiki\s*>|[\s\S]*\z)~usi'];
}

final class Chemistry extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_CHEMISTRY %s # # #';
    // Both the <chem> and <ce> extension tags contain non-editable source markup.
    public const array REGEXP = ['~<(chem|ce)(?:\s[^>]*)?>[\s\S]*?</\1\s*>~usi'];
}

final class Mathematics extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MATHEMATICS %s # # #';
    public const array REGEXP = ['~<math(?:\s[^>]*)?>[\s\S]*?</math\s*>~usi'];
}

final class Musicscores extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_MUSIC %s # # #';
    public const array REGEXP = ['~<score(?:\s[^>]*)?>[\s\S]*?</score\s*>~usi'];
}

final class Preformated extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_PREFORMAT %s # # #';
    public const array REGEXP = ['~<pre(?:\s[^>]*)?>[\s\S]*?</pre\s*>~usi'];
}

final class SyntaxHighlight extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_SYNTAXHIGHLIGHT %s # # #';
    public const array REGEXP = ['~<syntaxhighlight[^>]*>[\s\S]*?</syntaxhighlight>~usi'];
}

final class SourceCode extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_SOURCE %s # # #';
    public const array REGEXP = ['~<source(?:\s[^>]*)?>[\s\S]*?</source\s*>~usi'];
}

final class SingleBracket extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_SINGLE_BRACKET %s # # #';
    public const array REGEXP = ['~(?<!\{)\{[^\{\}]+\}(?!\})~us'];
}

final class TripleBracket extends WikiThings {
    public const string PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_TRIPLE_BRACKET %s # # #';
    public const array REGEXP = ['~(?<!\{)\{\{\{[^\{\}]+\}\}\}(?!\})~us'];
}
