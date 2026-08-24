<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';
/* @phan-suppress-begin PhanParamSuspiciousOrder */
final class WikiThingsCoverageTest extends testBaseClass {

    public function testBaseClassRoundTripsParsedText(): void {
        $comment = new Comment();
        $comment->parse_text('<!-- citation bot -->');
        $this->assertSame('<!-- citation bot -->', $comment->parsed_text());
    }

    public function testEachWikiThingUsesExpectedPlaceholderPrefix(): void {
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_COMMENT %s # # #', Comment::PLACEHOLDER_TEXT);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_NOWIKI %s # # #', Nowiki::PLACEHOLDER_TEXT);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_CHEMISTRY %s # # #', Chemistry::PLACEHOLDER_TEXT);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_MATHEMATICS %s # # #', Mathematics::PLACEHOLDER_TEXT);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_MUSIC %s # # #', Musicscores::PLACEHOLDER_TEXT);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_PREFORMAT %s # # #', Preformated::PLACEHOLDER_TEXT);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_SYNTAXHIGHLIGHT %s # # #', SyntaxHighlight::PLACEHOLDER_TEXT);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_SINGLE_BRACKET %s # # #', SingleBracket::PLACEHOLDER_TEXT);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_TRIPLE_BRACKET %s # # #', TripleBracket::PLACEHOLDER_TEXT);
    }

    public function testCommentFastRegexpMatchesSimpleComment(): void {
        $this->assertSame(1, preg_match(Comment::REGEXP[0], '<!-- simple comment -->'));
    }

    public function testCommentFallbackRegexpMatchesMultilineComment(): void {
        $text = "<!-- first line\nsecond <tag> line -->";
        $this->assertSame(1, preg_match(Comment::REGEXP[1], $text));
    }

    public function testCommentRegexpDoesNotMatchUnclosedComment(): void {
        $this->assertSame(0, preg_match(Comment::REGEXP[1], '<!-- unfinished'));
    }

    public function testNowikiFastRegexpMatchesSimpleContent(): void {
        $this->assertSame(1, preg_match(Nowiki::REGEXP[0], '<nowiki>{{cite web}}</nowiki>'));
    }

    public function testNowikiFallbackRegexpMatchesNestedAngleBrackets(): void {
        $text = '<nowiki><ref>{{cite web}}</ref></nowiki>';
        $this->assertSame(1, preg_match(Nowiki::REGEXP[1], $text));
    }

    public function testChemistryRegexpMatchesMultilineContent(): void {
        $text = "<chem>H2O +\nCO2</chem>";
        $this->assertSame(1, preg_match(Chemistry::REGEXP[0], $text));
    }

    public function testMathematicsRegexpMatchesPlainMath(): void {
        $this->assertSame(1, preg_match(Mathematics::REGEXP[0], '<math>x^2</math>'));
    }

    public function testMathematicsRegexpMatchesMathChemVariant(): void {
        $this->assertSame(1, preg_match(Mathematics::REGEXP[0], '<math chem>H_2O</math>'));
    }

    public function testMathematicsRegexpMatchesInlineDisplayAttribute(): void {
        $this->assertSame(1, preg_match(Mathematics::REGEXP[0], '<math display="inline">x</math>'));
    }

    public function testMathematicsRegexpMatchesBlockDisplayAttribute(): void {
        $this->assertSame(1, preg_match(Mathematics::REGEXP[0], '<math display="block">x</math>'));
    }

    public function testMusicScoreRegexpMatchesContent(): void {
        $this->assertSame(1, preg_match(Musicscores::REGEXP[0], '<score>c d e f</score>'));
    }

    public function testPreformattedRegexpMatchesMultilineContent(): void {
        $text = "<pre>line 1\n{{cite journal}}\nline 3</pre>";
        $this->assertSame(1, preg_match(Preformated::REGEXP[0], $text));
    }

    public function testSyntaxHighlightRegexpIsCaseInsensitive(): void {
        $text = '<SyntaxHighlight lang="php"><?php echo 1; ?></SyntaxHighlight>';
        $this->assertSame(1, preg_match(SyntaxHighlight::REGEXP[0], $text));
    }

    public function testSyntaxHighlightRegexpAcceptsAttributes(): void {
        $text = '<syntaxhighlight lang="php" line>echo "x";</syntaxhighlight>';
        $this->assertSame(1, preg_match(SyntaxHighlight::REGEXP[0], $text));
    }

    public function testSingleBracketRegexpMatchesExactlyOneBracePair(): void {
        $this->assertSame(1, preg_match(SingleBracket::REGEXP[0], '{value}'));
        $this->assertSame(0, preg_match(SingleBracket::REGEXP[0], '{{value}}'));
        $this->assertSame(0, preg_match(SingleBracket::REGEXP[0], '{{{value}}}'));
    }

    public function testTripleBracketRegexpMatchesExactlyThreeBracePairs(): void {
        $this->assertSame(1, preg_match(TripleBracket::REGEXP[0], '{{{value}}}'));
        $this->assertSame(0, preg_match(TripleBracket::REGEXP[0], '{{value}}'));
        $this->assertSame(0, preg_match(TripleBracket::REGEXP[0], '{{{{value}}}}'));
    }

    public function testTreatIdenticalSeparatelyIsFalseForImmutableWikiThings(): void {
        $this->assertFalse(Comment::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(Nowiki::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(Mathematics::TREAT_IDENTICAL_SEPARATELY);
    }
}
/* @phan-suppress-end PhanParamSuspiciousOrder */
