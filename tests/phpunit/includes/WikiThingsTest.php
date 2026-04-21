<?php
declare(strict_types=1);

/*
 * Tests for WikiThings.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class WikiThingsTest extends testBaseClass {

    // ======================== WikiThings::parse_text / parsed_text ========================

    public function testCommentParseAndReturn(): void {
        new TestPage();
        $comment = new Comment();
        $comment->parse_text('<!-- This is a comment -->');
        $this->assertSame('<!-- This is a comment -->', $comment->parsed_text());
    }

    public function testNowikiParseAndReturn(): void {
        new TestPage();
        $nowiki = new Nowiki();
        $nowiki->parse_text('<nowiki>some [[markup]]</nowiki>');
        $this->assertSame('<nowiki>some [[markup]]</nowiki>', $nowiki->parsed_text());
    }

    public function testChemistryParseAndReturn(): void {
        new TestPage();
        $chem = new Chemistry();
        $chem->parse_text('<chem>H2O</chem>');
        $this->assertSame('<chem>H2O</chem>', $chem->parsed_text());
    }

    public function testMathematicsParseAndReturn(): void {
        new TestPage();
        $math = new Mathematics();
        $math->parse_text('<math>E = mc^2</math>');
        $this->assertSame('<math>E = mc^2</math>', $math->parsed_text());
    }

    public function testMusicscoresParseAndReturn(): void {
        new TestPage();
        $music = new Musicscores();
        $music->parse_text('<score>notes here</score>');
        $this->assertSame('<score>notes here</score>', $music->parsed_text());
    }

    public function testPreformatedParseAndReturn(): void {
        new TestPage();
        $pre = new Preformated();
        $pre->parse_text('<pre>preformatted text</pre>');
        $this->assertSame('<pre>preformatted text</pre>', $pre->parsed_text());
    }

    public function testSingleBracketParseAndReturn(): void {
        new TestPage();
        $bracket = new SingleBracket();
        $bracket->parse_text('{single}');
        $this->assertSame('{single}', $bracket->parsed_text());
    }

    public function testTripleBracketParseAndReturn(): void {
        new TestPage();
        $bracket = new TripleBracket();
        $bracket->parse_text('{{{triple}}}');
        $this->assertSame('{{{triple}}}', $bracket->parsed_text());
    }

    // ======================== PLACEHOLDER_TEXT constants ========================

    public function testCommentPlaceholderContainsKey(): void {
        new TestPage();
        $this->assertStringContainsString('CITATION_BOT_PLACEHOLDER_COMMENT', Comment::PLACEHOLDER_TEXT);
    }

    public function testNowikiPlaceholderContainsKey(): void {
        new TestPage();
        $this->assertStringContainsString('CITATION_BOT_PLACEHOLDER_NOWIKI', Nowiki::PLACEHOLDER_TEXT);
    }

    public function testChemistryPlaceholderContainsKey(): void {
        new TestPage();
        $this->assertStringContainsString('CITATION_BOT_PLACEHOLDER_CHEMISTRY', Chemistry::PLACEHOLDER_TEXT);
    }

    public function testMathematicsPlaceholderContainsKey(): void {
        new TestPage();
        $this->assertStringContainsString('CITATION_BOT_PLACEHOLDER_MATHEMATICS', Mathematics::PLACEHOLDER_TEXT);
    }

    public function testMusicscoresPlaceholderContainsKey(): void {
        new TestPage();
        $this->assertStringContainsString('CITATION_BOT_PLACEHOLDER_MUSIC', Musicscores::PLACEHOLDER_TEXT);
    }

    public function testPreformatedPlaceholderContainsKey(): void {
        new TestPage();
        $this->assertStringContainsString('CITATION_BOT_PLACEHOLDER_PREFORMAT', Preformated::PLACEHOLDER_TEXT);
    }

    public function testSingleBracketPlaceholderContainsKey(): void {
        new TestPage();
        $this->assertStringContainsString('CITATION_BOT_PLACEHOLDER_SINGLE_BRACKET', SingleBracket::PLACEHOLDER_TEXT);
    }

    public function testTripleBracketPlaceholderContainsKey(): void {
        new TestPage();
        $this->assertStringContainsString('CITATION_BOT_PLACEHOLDER_TRIPLE_BRACKET', TripleBracket::PLACEHOLDER_TEXT);
    }

    // ======================== REGEXP patterns ========================

    public function testCommentRegexpMatchesSimple(): void {
        new TestPage();
        $text = '<!-- simple comment -->';
        $this->assertMatchesRegularExpression(Comment::REGEXP[0], $text);
    }

    public function testCommentRegexpMatchesMultiline(): void {
        new TestPage();
        $text = "<!-- multi\nline\ncomment -->";
        $this->assertMatchesRegularExpression(Comment::REGEXP[1], $text);
    }

    public function testNowikiRegexpMatchesBasic(): void {
        new TestPage();
        $text = '<nowiki>test content</nowiki>';
        $this->assertMatchesRegularExpression(Nowiki::REGEXP[0], $text);
    }

    public function testNowikiRegexpMatchesMultiline(): void {
        new TestPage();
        $text = "<nowiki>multi\nline</nowiki>";
        $this->assertMatchesRegularExpression(Nowiki::REGEXP[1], $text);
    }

    public function testChemistryRegexpMatches(): void {
        new TestPage();
        $text = '<chem>CO2 + H2O</chem>';
        $this->assertMatchesRegularExpression(Chemistry::REGEXP[0], $text);
    }

    public function testMathematicsRegexpMatchesPlain(): void {
        new TestPage();
        $text = '<math>x^2 + y^2 = z^2</math>';
        $this->assertMatchesRegularExpression(Mathematics::REGEXP[0], $text);
    }

    public function testMathematicsRegexpMatchesInlineDisplay(): void {
        new TestPage();
        $text = '<math display="inline">x^2</math>';
        $this->assertMatchesRegularExpression(Mathematics::REGEXP[0], $text);
    }

    public function testMathematicsRegexpMatchesChem(): void {
        new TestPage();
        $text = '<math chem>H_2O</math>';
        $this->assertMatchesRegularExpression(Mathematics::REGEXP[0], $text);
    }

    public function testMusicscoresRegexpMatches(): void {
        new TestPage();
        $text = '<score>some music</score>';
        $this->assertMatchesRegularExpression(Musicscores::REGEXP[0], $text);
    }

    public function testPreformatedRegexpMatches(): void {
        new TestPage();
        $text = '<pre>formatted code</pre>';
        $this->assertMatchesRegularExpression(Preformated::REGEXP[0], $text);
    }

    public function testSingleBracketRegexpMatchesSingle(): void {
        new TestPage();
        $text = '{test content}';
        $this->assertMatchesRegularExpression(SingleBracket::REGEXP[0], $text);
    }

    public function testSingleBracketRegexpDoesNotMatchDouble(): void {
        new TestPage();
        $text = '{{double brackets}}';
        $this->assertDoesNotMatchRegularExpression(SingleBracket::REGEXP[0], $text);
    }

    public function testTripleBracketRegexpMatches(): void {
        new TestPage();
        $text = '{{{parameter}}}';
        $this->assertMatchesRegularExpression(TripleBracket::REGEXP[0], $text);
    }

    // ======================== TREAT_IDENTICAL_SEPARATELY constant ========================

    public function testTreatIdenticalSeparatelyIsFalseForAll(): void {
        new TestPage();
        $this->assertFalse(Comment::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(Nowiki::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(Chemistry::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(Mathematics::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(Musicscores::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(Preformated::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(SingleBracket::TREAT_IDENTICAL_SEPARATELY);
        $this->assertFalse(TripleBracket::TREAT_IDENTICAL_SEPARATELY);
    }

    // ======================== Overwrite / empty string ========================

    public function testCommentOverwrite(): void {
        new TestPage();
        $comment = new Comment();
        $comment->parse_text('<!-- first -->');
        $comment->parse_text('<!-- second -->');
        $this->assertSame('<!-- second -->', $comment->parsed_text());
    }

    public function testParseTextEmptyString(): void {
        new TestPage();
        $comment = new Comment();
        $comment->parse_text('');
        $this->assertSame('', $comment->parsed_text());
    }
}
