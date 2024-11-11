<?php
declare(strict_types=1);

/*
 * Tests for Parameter.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class ParameterTest extends testBaseClass {

    protected function setUp(): void {
        if (BAD_PAGE_API !== '') {
            $this->markTestSkipped();
        }
    }

    public function testFillCache(): void {
        $this->fill_cache();
        $this->assertTrue(true);
    }

    public function testValueWithPipeAndTrailingNewline(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
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
        $this->assertSame('{{cite web|doggiesandcats | doggiesandcats=joker }}', $template->parsed_text());
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
}

