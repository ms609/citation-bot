<?php

/*
 * Tests for Parameter.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class ParameterTest extends testBaseClass {

/*
 * TODO: most of these tests have the assert arguments backwards
 * TODO: white list test is for parameters file in constants directory
 */
  public function testValueWithPipeAndTrailingNewline() {
    $text = "last1 = [[:en:Bigwig# # # CITATION_BOT_PLACEHOLDER_PIPE # # #SomeoneFamous]]\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, 'last1');
    $this->assertSame($parameter->eq, ' = ');
    $this->assertSame($parameter->val, '[[:en:Bigwig|SomeoneFamous]]');
    $this->assertSame($parameter->post, "\n");
  }
  public function testParameterWithNoParamName() {
    $text = " = no param name";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertSame(' = ', $parameter->eq);
  }

  public function testBlankValueWithSpacesLeadingSpaceTrailingNewline() {
    $text = " first1 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, ' ');
    $this->assertSame($parameter->param, 'first1');
    $this->assertSame($parameter->eq, ' = ');
    $this->assertSame($parameter->val, '');
    $this->assertSame($parameter->post, "\n");
  }

  public function testBlankValueWithSpacesAndTrailingNewline() {
    $text = "first2 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, 'first2');
    $this->assertSame($parameter->eq, ' = ');
    $this->assertSame($parameter->val, '');
    $this->assertSame($parameter->post, "\n");
  }

  public function testBlankValueWithPreEqSpaceAndTrailingNewline() {
    $text = "first3 =\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, 'first3');
    $this->assertSame($parameter->eq, ' =');
    $this->assertSame($parameter->val, '');
    $this->assertSame($parameter->post, "\n");
  }

  public function testBlankValueWithPostEqSpaceAndTrailingNewline() {
    $text = "first4= \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, 'first4');
    $this->assertSame($parameter->eq, '= ');
    $this->assertSame($parameter->val, '');
    $this->assertSame($parameter->post, "\n");
  }

  public function testBlankValueNoSpacesTrailingNewline() {
    $text = "first5=\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, 'first5');
    $this->assertSame($parameter->eq, '=');
    $this->assertSame($parameter->val, '');
    $this->assertSame($parameter->post, "\n");
  }

  public function testBlankValueNoEquals() {
    $text = "first6 \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, '');
    $this->assertSame($parameter->eq, '');
    $this->assertSame($parameter->val, 'first6');
    $this->assertSame($parameter->post, " \n");
  }
  
  public function testNoEqualsAddStuff() {
    $text = "{{cite web|cnn}}";
    $template = $this->make_citation($text);
    $this->assertSame($template->parsed_text(), '{{cite web|cnn}}');
    $template->set('cnn', 'joker');
    $this->assertSame($template->parsed_text(), '{{cite web|cnn|cnn = joker}}'); // Adds an equals sign for us
  }

  public function testBlankValueNonBreakingSpaces() {
    $text = " first7 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, ' first7 ');  //These are non-breaking spaces
    $this->assertSame($parameter->eq, '=');
    $this->assertSame($parameter->val, ' ');  //This is a non-breaking space
    $this->assertSame($parameter->post, "\n");
  }

  public function testMultilinevalueTrailingNewline() {
    $text = "param=multiline\nvalue\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, "param");
    $this->assertSame($parameter->eq, '=');
    $this->assertSame($parameter->val, "multiline\nvalue");
    $this->assertSame($parameter->post, "\n");
  }

  public function testMultilineParamTrailingNewline() {
    $text = "multiline\nparam=\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, "multiline\nparam");
    $this->assertSame($parameter->eq, '=');
    $this->assertSame($parameter->val, '');
    $this->assertSame($parameter->post, "\n");
  }

  public function testHasProtectedCommentInValue() {
    $text = "archivedate= 24 April 2008 # # # Citation bot : comment placeholder 0 # # #";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, 'archivedate');
    $this->assertSame($parameter->eq, '= ');
    $this->assertSame($parameter->val, "24 April 2008 # # # Citation bot : comment placeholder 0 # # #");
    $this->assertSame($parameter->post, "");
  }
  
  public function testHasUnreplacedCommentInValue() {
    $text = "archivedate= 9 August 2006 <!--DASHBot-->";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertSame($parameter->pre, '');
    $this->assertSame($parameter->param, 'archivedate');
    $this->assertSame($parameter->eq, '= ');
    $this->assertSame($parameter->val, "9 August 2006 <!--DASHBot-->");
    $this->assertSame($parameter->post, "");
  }
}



