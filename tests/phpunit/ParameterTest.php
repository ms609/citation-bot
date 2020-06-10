<?php

/*
 * Tests for Parameter.php.
 */

require_once(__DIR__ . '/../testBaseClass.php');

final class ParameterTest extends testBaseClass {

  public function testValueWithPipeAndTrailingNewline() {
    $text = "last1 = [[:en:Bigwig# # # CITATION_BOT_PLACEHOLDER_PIPE # # #SomeoneFamous]]\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame('last1', $parameter->param);
    $this->assertSame( ' = ', $parameter->eq);
    $this->assertSame('[[:en:Bigwig|SomeoneFamous]]', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }
  public function testParameterWithNoParamName() {
    $text = " = no param name";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame(' = ', $parameter->eq);
  }

  public function testBlankValueWithSpacesLeadingSpaceTrailingNewline() {
    $text = " first1 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame(' ', $parameter->pre);
    $this->assertSame('first1', $parameter->param);
    $this->assertSame(' = ', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueWithSpacesAndTrailingNewline() {
    $text = "first2 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame('first2', $parameter->param);
    $this->assertSame(' = ', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueWithPreEqSpaceAndTrailingNewline() {
    $text = "first3 =\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame('first3', $parameter->param);
    $this->assertSame(' =', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueWithPostEqSpaceAndTrailingNewline() {
    $text = "first4= \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame('first4', $parameter->param);
    $this->assertSame('= ', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueNoSpacesTrailingNewline() {
    $text = "first5=\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame('first5', $parameter->param);
    $this->assertSame('=', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testBlankValueNoEquals() {
    $text = "first6 \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame('', $parameter->param);
    $this->assertSame('', $parameter->eq);
    $this->assertSame('first6', $parameter->val);
    $this->assertSame(" \n", $parameter->post);
  }
  
  public function testNoEqualsAddStuff() {
    $text = "{{cite web|cnn}}";
    $template = $this->make_citation($text);
    $this->assertSame('{{cite web|cnn}}', $template->parsed_text());
    $template->set('cnn', 'joker');
    $this->assertSame('{{cite web|cnn|cnn = joker}}', $template->parsed_text());
  }

  public function testBlankValueNonBreakingSpaces() {
    $text = " first7 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame(' first7 ', $parameter->param);  //These are non-breaking spaces
    $this->assertSame('=', $parameter->eq);
    $this->assertSame(' ', $parameter->val);  //This is a non-breaking space
    $this->assertSame("\n", $parameter->post);
  }

  public function testMultilinevalueTrailingNewline() {
    $text = "param=multiline\nvalue\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame("param", $parameter->param);
    $this->assertSame('=', $parameter->eq);
    $this->assertSame("multiline\nvalue", $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testMultilineParamTrailingNewline() {
    $text = "multiline\nparam=\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame("multiline\nparam", $parameter->param);
    $this->assertSame('=', $parameter->eq);
    $this->assertSame('', $parameter->val);
    $this->assertSame("\n", $parameter->post);
  }

  public function testHasProtectedCommentInValue() {
    $text = "archivedate= 24 April 2008 # # # Citation bot : comment placeholder 0 # # #";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame('archivedate', $parameter->param);
    $this->assertSame('= ', $parameter->eq);
    $this->assertSame("24 April 2008 # # # Citation bot : comment placeholder 0 # # #", $parameter->val);
    $this->assertSame("", $parameter->post);
  }
  
  public function testHasUnreplacedCommentInValue() {
    $text = "archivedate= 9 August 2006 <!--DASHBot-->";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame('', $parameter->pre);
    $this->assertSame('archivedate', $parameter->param);
    $this->assertSame('= ', $parameter->eq);
    $this->assertSame("9 August 2006 <!--DASHBot-->", $parameter->val);
    $this->assertSame("", $parameter->post);
  }
}



