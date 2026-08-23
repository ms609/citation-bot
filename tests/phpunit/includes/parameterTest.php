<?php
declare(strict_types=1);

/*
 * Tests for Parameter.php.
 */

require_once __DIR__ . '/../../testBaseClass.php';
use PHPUnit\Framework\Attributes\DataProvider;

final class parameterTest extends testBaseClass {

    public function testValueWithPipeAndTrailingNewline(): void {
        new TestPage(); // Fill page name with test name for debugging
        $text = "last1 = [[:en:Bigwig# # # CITATION_BOT_PLACEHOLDER_PIPE # # #SomeoneFamous]]\n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame('last1', $parameter->param);
        $this->assertSame( ' = ', $parameter->eq);
        $this->assertSame('[[:en:Bigwig|SomeoneFamous]]', $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testParameterWithNoParamName(): void {
        $text = " = no param name";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame(' = ', $parameter->eq);
    }

    public function testBlankValueWithSpacesLeadingSpaceTrailingNewline(): void {
        $text = " first1 = \n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame(' ', $parameter->pre);
        $this->assertSame('first1', $parameter->param);
        $this->assertSame(' = ', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testBlankValueWithSpacesAndTrailingNewline(): void {
        $text = "first2 = \n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame('first2', $parameter->param);
        $this->assertSame(' = ', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testBlankValueWithPreEqSpaceAndTrailingNewline(): void {
        $text = "first3 =\n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame('first3', $parameter->param);
        $this->assertSame(' =', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testBlankValueWithPostEqSpaceAndTrailingNewline(): void {
        $text = "first4= \n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame('first4', $parameter->param);
        $this->assertSame('= ', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testBlankValueNoSpacesTrailingNewline(): void {
        $text = "first5=\n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame('first5', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testBlankValueNoEquals(): void {
        $text = "first6 \n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame('', $parameter->param);
        $this->assertSame('', $parameter->eq);
        $this->assertSame('first6', $parameter->val);
        $this->assertSame(" \n", $parameter->post);
    }

    public function testNoEqualsAddStuff(): void {
        $text = "{{cite web|doggiesandcats}}";
        $template = $this->make_citation($text);
        $this->assertSame('{{cite web|doggiesandcats}}', $template->parsed_text());
        $template->set('doggiesandcats', 'joker');
        $this->assertSame('{{cite web| doggiesandcats=joker |doggiesandcats}}', $template->parsed_text());
    }

    public function testBlankValueNonBreakingSpaces(): void {   //These are non-breaking spaces
        $text = " first7 = \n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame(' ', $parameter->pre);
        $this->assertSame('first7', $parameter->param);
        $this->assertSame(' = ', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testNonBreakingSpaceNormalization(): void {
        // Test with Unicode non-breaking space (U+00A0) in various positions
        $text = "\u{00A0}publisher=\u{00A0}BBC\u{00A0}";  // Contains non-breaking spaces
        $parameter = $this->parameter_parse_text_helper($text);
        $result = $parameter->parsed_text();

        // Verify non-breaking spaces have been converted to regular spaces
        $this->assertStringNotContainsString("\u{00A0}", $result);
        $this->assertStringContainsString(' publisher', $result);
        $this->assertStringContainsString('BBC ', $result);

        // Test with other Unicode space separators (U+202F, U+2007)
        $text2 = "\u{202F}author=\u{2007}Smith\u{00A0}";  // Mix of space types
        $parameter2 = $this->parameter_parse_text_helper($text2);
        $result2 = $parameter2->parsed_text();

        // Verify all space separators are normalized
        $this->assertStringNotContainsString("\u{202F}", $result2);
        $this->assertStringNotContainsString("\u{2007}", $result2);
        $this->assertStringNotContainsString("\u{00A0}", $result2);
    }

    public function testMultilinevalueTrailingNewline(): void {
        $text = "param=multiline\nvalue\n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame("param", $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame("multiline\nvalue", $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testMultilineParamTrailingNewline(): void {
        $text = "multiline\nparam=\n";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame("multiline\nparam", $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\n", $parameter->post);
    }

    public function testHasProtectedCommentInValue(): void {
        $text = "archivedate= 24 April 2008 # # # Citation bot : comment placeholder 0 # # #";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame('archivedate', $parameter->param);
        $this->assertSame('= ', $parameter->eq);
        $this->assertSame("24 April 2008 # # # Citation bot : comment placeholder 0 # # #", $parameter->val);
        $this->assertSame("", $parameter->post);
    }

    public function testHasCommentInValueMany(): void {
        $text = "# # # CITATION_BOT_PLACEHOLDER_COMMENT 1 # # # # # # CITATION_BOT_PLACEHOLDER_COMMENT 7 # # # archivedate # # # CITATION_BOT_PLACEHOLDER_COMMENT 9 # # #  # # # CITATION_BOT_PLACEHOLDER_COMMENT 2 # # # = # # # CITATION_BOT_PLACEHOLDER_COMMENT 3 # # # 24 April 2008 # # # CITATION_BOT_PLACEHOLDER_COMMENT 4 # # # # # # CITATION_BOT_PLACEHOLDER_COMMENT 5 # # #";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_COMMENT 1 # # # # # # CITATION_BOT_PLACEHOLDER_COMMENT 7 # # # ', $parameter->pre);
        $this->assertSame('archivedate', $parameter->param); // This is the key one
        $this->assertSame(' # # # CITATION_BOT_PLACEHOLDER_COMMENT 9 # # #  # # # CITATION_BOT_PLACEHOLDER_COMMENT 2 # # # = ', $parameter->eq);
        $this->assertSame('# # # CITATION_BOT_PLACEHOLDER_COMMENT 3 # # # 24 April 2008 # # # CITATION_BOT_PLACEHOLDER_COMMENT 4 # # # # # # CITATION_BOT_PLACEHOLDER_COMMENT 5 # # #', $parameter->val);
        $this->assertSame('', $parameter->post);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testHasUnreplacedCommentInValue(): void {
        $text = "archivedate= 9 August 2006 <!--DASHBot-->";
        $parameter = $this->parameter_parse_text_helper($text);
        $this->assertSame('', $parameter->pre);
        $this->assertSame('archivedate', $parameter->param);
        $this->assertSame('= ', $parameter->eq);
        $this->assertSame("9 August 2006 <!--DASHBot-->", $parameter->val);
        $this->assertSame("", $parameter->post);
    }

    public function testMistakeWithSpaceAndAccent(): void {
        $text = "{{citation|format électronique=Joe}}";
        $template = $this->process_citation($text);
        $this->assertSame('{{citation|format=Joe}}', $template->parsed_text());
    }

    public function testOddSpaces(): void {
        $text = "{{Infobox settlement\n| image_skyline            = \n \n| image_caption            = \n}}";
        $template = $this->process_citation($text);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testWhitespaceOnlyWithoutEqualsUsesRawValueFallback(): void {
        // No non-whitespace content means the pre-equals regex cannot match,
        // exercising the final fallback branch.
        $text = " \t\n";
        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('', $parameter->pre);
        $this->assertSame('', $parameter->param);
        $this->assertSame('', $parameter->eq);
        $this->assertSame($text, $parameter->val);
        $this->assertSame('', $parameter->post);
    }

    public function testEmptyStringUsesRawValueFallback(): void {
        $parameter = $this->parameter_parse_text_helper('');

        $this->assertSame('', $parameter->pre);
        $this->assertSame('', $parameter->param);
        $this->assertSame('', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame('', $parameter->post);
    }

    public function testBlankValueWithoutLineEnding(): void {
        // val and post are both blank, so the line-feed cleanup block is entered,
        // but its inner regexp does not match.
        $parameter = $this->parameter_parse_text_helper('param=');

        $this->assertSame('', $parameter->pre);
        $this->assertSame('param', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame('', $parameter->post);
        $this->assertSame('param=', $parameter->parsed_text());
    }

    public function testEmptyParameterNameWithValue(): void {
        // Explicitly exercises count($pre_eq) === 0.
        $parameter = $this->parameter_parse_text_helper('=value');

        $this->assertSame('', $parameter->pre);
        $this->assertSame('', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('value', $parameter->val);
        $this->assertSame('', $parameter->post);
        $this->assertSame('=value', $parameter->parsed_text());
    }

    public function testWhitespaceOnlyParameterNameWithValue(): void {
        $text = '   = value';
        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('', $parameter->pre);
        $this->assertSame('', $parameter->param);
        $this->assertSame('   = ', $parameter->eq);
        $this->assertSame('value', $parameter->val);
        $this->assertSame('', $parameter->post);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testOnlyFirstEqualsSignSplitsParameter(): void {
        // explode(..., 2) must preserve additional equals signs in the value.
        $text = 'url=https://example.com/?a=b=c';
        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('url', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('https://example.com/?a=b=c', $parameter->val);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testBlankValueWithCarriageReturnOnly(): void {
        $text = "param=\r";
        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('param', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\r", $parameter->post);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testBlankValueWithCrLf(): void {
        $text = "param=\r\n";
        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('param', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('', $parameter->val);
        $this->assertSame("\r\n", $parameter->post);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testInvalidUtf8InParameterNameUsesFallbackParser(): void {
        $text = "\xFFname = value";
        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('', $parameter->pre);
        $this->assertSame("\xFFname", $parameter->param);
        $this->assertSame(' = ', $parameter->eq);
        $this->assertSame('value', $parameter->val);
        $this->assertSame('', $parameter->post);
    }

    public function testInvalidUtf8InValueUsesFallbackParser(): void {
        $text = "name = \xFFvalue";
        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('', $parameter->pre);
        $this->assertSame('name', $parameter->param);
        $this->assertSame(' = ', $parameter->eq);
        $this->assertSame("\xFFvalue", $parameter->val);
        $this->assertSame('', $parameter->post);
    }

    public function testParsedTextNormalizesAdditionalUnicodeSpaces(): void {
        $spaces = [
            "\u{1680}", // Ogham space mark
            "\u{2000}", // en quad
            "\u{2001}", // em quad
            "\u{2002}", // en space
            "\u{2003}", // em space
            "\u{2004}",
            "\u{2005}",
            "\u{2006}",
            "\u{2008}",
            "\u{2009}",
            "\u{200A}", // hair space
            "\u{205F}", // medium mathematical space
            "\u{3000}", // ideographic space
        ];

        foreach ($spaces as $space) {
            $parameter = new Parameter();
            $parameter->pre = $space;
            $parameter->param = 'title';
            $parameter->eq = $space . '=' . $space;
            $parameter->val = 'Example';
            $parameter->post = $space;

            $this->assertSame(
                ' title = Example ',
                $parameter->parsed_text(),
                'Failed to normalize U+' .
                    mb_strtoupper(mb_str_pad(dechex(mb_ord($space)), 4, '0', STR_PAD_LEFT))
            );
        }
    }

    #[DataProvider('parameterBoundaryProvider')]
    public function testParameterBoundaryCases(
        string $text,
        string $expectedPre,
        string $expectedParam,
        string $expectedEq,
        string $expectedVal,
        string $expectedPost
    ): void {
        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame($expectedPre, $parameter->pre);
        $this->assertSame($expectedParam, $parameter->param);
        $this->assertSame($expectedEq, $parameter->eq);
        $this->assertSame($expectedVal, $parameter->val);
        $this->assertSame($expectedPost, $parameter->post);

        // For ordinary ASCII input parsing should be lossless.
        $this->assertSame($text, $parameter->parsed_text());
    }

    /**
     * @return array<string, array{string, string, string, string, string, string}>
     */
    public static function parameterBoundaryProvider(): array {
        return [
            'simple positional parameter' => [
                'positional',
                '',
                '',
                '',
                'positional',
                '',
            ],

            'positional parameter with surrounding whitespace' => [
                "\tpositional value\r\n",
                "\t",
                '',
                '',
                'positional value',
                "\r\n",
            ],

            'only equals sign' => [
                '=',
                '',
                '',
                '=',
                '',
                '',
            ],

            'equals sign as value' => [
                '==',
                '',
                '',
                '=',
                '=',
                '',
            ],

            'blank name and blank value with spaces' => [
                ' = ',
                '',
                '',
                ' = ',
                '',
                '',
            ],

            'blank name with value' => [
                "  =\tvalue",
                '',
                '',
                "  =\t",
                'value',
                '',
            ],

            'newline before nonblank value belongs to eq' => [
                "param=\nvalue",
                '',
                'param',
                "=\n",
                'value',
                '',
            ],

            'multiple blank lines after equals moved to post' => [
                "param= \n  \n",
                '',
                'param',
                '= ',
                '',
                "\n  \n",
            ],

            'tabs around every component' => [
                "\tname\t=\tvalue\t",
                "\t",
                'name',
                "\t=\t",
                'value',
                "\t",
            ],

            'crlf after nonblank value' => [
                "name=value\r\n",
                '',
                'name',
                '=',
                'value',
                "\r\n",
            ],

            'mixed trailing whitespace after value' => [
                "name= value \n\t",
                '',
                'name',
                '= ',
                'value',
                " \n\t",
            ],
        ];
    }

    public function testMultipleAdjacentLeadingCommentsAreExtractedInOrder(): void {
        $comment1 = '# # # CITATION_BOT_PLACEHOLDER_COMMENT 1 # # #';
        $comment2 = '# # # CITATION_BOT_PLACEHOLDER_COMMENT 2 # # #';

        $text = $comment1 . $comment2 . 'title=value';

        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame($comment1 . $comment2, $parameter->pre);
        $this->assertSame('title', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('value', $parameter->val);
        $this->assertSame('', $parameter->post);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testMultipleAdjacentTrailingCommentsAreExtractedInOrder(): void {
        $comment1 = '# # # CITATION_BOT_PLACEHOLDER_COMMENT 1 # # #';
        $comment2 = '# # # CITATION_BOT_PLACEHOLDER_COMMENT 2 # # #';

        $text = 'title' . $comment1 . $comment2 . '=value';

        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('', $parameter->pre);
        $this->assertSame('title', $parameter->param);
        $this->assertSame($comment1 . $comment2 . '=', $parameter->eq);
        $this->assertSame('value', $parameter->val);
        $this->assertSame('', $parameter->post);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testCommentPlaceholderMatchingIsCaseInsensitive(): void {
        $comment = '# # # citation_bot_placeholder_comment 42 # # #';
        $text = $comment . 'title=value';

        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame($comment, $parameter->pre);
        $this->assertSame('title', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('value', $parameter->val);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testNonNumericCommentPlaceholderIsNotExtracted(): void {
        $fakeComment = '# # # CITATION_BOT_PLACEHOLDER_COMMENT X # # #';
        $text = 'title' . $fakeComment . '=value';

        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('', $parameter->pre);
        $this->assertSame('title' . $fakeComment, $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('value', $parameter->val);
        $this->assertSame($text, $parameter->parsed_text());
    }

    public function testPipePlaceholderInPositionalValue(): void {
        $text = 'left' . PIPE_PLACEHOLDER . 'right';

        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('', $parameter->param);
        $this->assertSame('', $parameter->eq);
        $this->assertSame('left|right', $parameter->val);
        $this->assertSame('left|right', $parameter->parsed_text());
    }

    public function testMultiplePipePlaceholdersAreRestored(): void {
        $text = 'title=a' . PIPE_PLACEHOLDER . 'b' . PIPE_PLACEHOLDER . 'c';

        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('title', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('a|b|c', $parameter->val);
        $this->assertSame('title=a|b|c', $parameter->parsed_text());
    }

    public function testPipePlaceholderInParameterNameIsRestored(): void {
        $text = 'na' . PIPE_PLACEHOLDER . 'me=value';

        $parameter = $this->parameter_parse_text_helper($text);

        $this->assertSame('na|me', $parameter->param);
        $this->assertSame('=', $parameter->eq);
        $this->assertSame('value', $parameter->val);
        $this->assertSame('na|me=value', $parameter->parsed_text());
    }

    public function testParsedTextDoesNotNormalizeUnicodeSpacesInsideNameOrValue(): void {
        $space = "\u{00A0}";

        $parameter = new Parameter();
        $parameter->pre = $space;
        $parameter->param = 'ti' . $space . 'tle';
        $parameter->eq = $space . '=' . $space;
        $parameter->val = 'Example' . $space . 'Value';
        $parameter->post = $space;

        $this->assertSame(
            ' ti' . $space . 'tle = Example' . $space . 'Value ',
            $parameter->parsed_text()
        );
    }

    public function testParsedTextPreservesAsciiFormattingWhitespace(): void {
        $parameter = new Parameter();
        $parameter->pre = "\t";
        $parameter->param = 'title';
        $parameter->eq = "\t=\t";
        $parameter->val = 'Example';
        $parameter->post = "\r\n";

        $this->assertSame(
            "\ttitle\t=\tExample\r\n",
            $parameter->parsed_text()
        );
    }

    public function testParsedTextDoesNotNormalizeZeroWidthSpace(): void {
        // U+200B is immediately outside the normalized U+2000-U+200A range.
        $space = "\u{200B}";

        $parameter = new Parameter();
        $parameter->pre = $space;
        $parameter->param = 'title';
        $parameter->eq = '=';
        $parameter->val = 'Example';

        $this->assertSame(
            $space . 'title=Example',
            $parameter->parsed_text()
        );
    }
}
