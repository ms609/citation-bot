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

  public function testValueWithPipeAndTrailingNewline() : void {
    $text = "last1 = [[:en:Bigwig# # # CITATION_BOT_PLACEHOLDER_PIPE # # #SomeoneFamous]]\n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame('last1', $parameter->param);
    $this->assertSame( ' = ', $parameter->eq);
    $this->assertSame('[[:en:Bigwig|SomeoneFamous]]', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }
  public function testParameterWithNoParamName() : void {
    $text = " = no param name";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame(' = ', $parameter->eq);
  }

  public function testBlankValueWithSpacesLeadingSpaceTrailingNewline() : void {
    $text = " first1 = \n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame(' ', $parameter->pre);
    $this->assertSame('first1', $parameter->param);
    $this->assertSame(' = ', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueWithSpacesAndTrailingNewline() : void {
    $text = "first2 = \n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame('first2', $parameter->param);
    $this->assertSame(' = ', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueWithPreEqSpaceAndTrailingNewline() : void {
    $text = "first3 =\n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame('first3', $parameter->param);
    $this->assertSame(' =', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueWithPostEqSpaceAndTrailingNewline() : void {
    $text = "first4= \n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame('first4', $parameter->param);
    $this->assertSame('= ', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueNoSpacesTrailingNewline() : void {
    $text = "first5=\n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame('first5', $parameter->param);
    $this->assertSame('=', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueNoEquals() : void {
    $text = "first6 \n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame('', $parameter->param);
    $this->assertSame('', $parameter->eq);
    $this->assertSame('first6', $parameter->val);
    $this->assertSame(" \n", $parameter->post);
  }
  
  public function testNoEqualsAddStuff() : void {
    $text = "{{cite web|cnn}}";
    $template = $this->make_citation($text);
    $this->assertSame('{{cite web|cnn}}', $template->parsed_text());
    $template->set('cnn', 'joker');
    $this->assertSame('{{cite web|cnn|cnn = joker}}', $template->parsed_text());
  }

  public function testBlankValueNonBreakingSpaces() : void {
    $text = " first7 = \n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame(' first7 ', $parameter->param);  //These are non-breaking spaces
    $this->assertSame('=', $parameter->eq);
    $this->assertSame(' ', $parameter->val);  //This is a non-breaking space
    $this->assertSame("\n", $parameter->post);
  }

  public function testMultilinevalueTrailingNewline() : void {
    $text = "param=multiline\nvalue\n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame("param", $parameter->param);
    $this->assertSame('=', $parameter->eq);
    $this->assertSame("multiline\nvalue", $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testMultilineParamTrailingNewline() : void {
    $text = "multiline\nparam=\n";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame("multiline\nparam", $parameter->param);
    $this->assertSame('=', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testHasProtectedCommentInValue() : void {
    $text = "archivedate= 24 April 2008 # # # Citation bot : comment placeholder 0 # # #";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame('archivedate', $parameter->param);
    $this->assertSame('= ', $parameter->eq);
    $this->assertSame("24 April 2008 # # # Citation bot : comment placeholder 0 # # #", $parameter->val);
    $this->assertSame("", $parameter->post);
  }
  
  public function testHasUnreplacedCommentInValue() : void {
    $text = "archivedate= 9 August 2006 <!--DASHBot-->";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame('', $parameter->pre);
    $this->assertSame('archivedate', $parameter->param);
    $this->assertSame('= ', $parameter->eq);
    $this->assertSame("9 August 2006 <!--DASHBot-->", $parameter->val);
    $this->assertSame("", $parameter->post);
  }

  public function testMistakeWithSpaceAndAccent() : void {
    $text = "{{citation|format électronique=Joe}}";
    $template = $this->process_citation($text);
    $this->assertSame('{{citation|format=Joe}}', $template->parsed_text());
  }
  
}

