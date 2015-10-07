<?php

/*
 * Tests for Parameter.php.
 */

class ParameterTest extends PHPUnit_Framework_TestCase {

  protected function setUp() {
    if (!defined("PIPE_PLACEHOLDER")) {
// this is usually done elsewhere in the code
      define("PIPE_PLACEHOLDER", '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
    }
  }

  protected function tearDown() {
  }

  protected function parameter_parse_text_helper($text) {
    $parameter = new Parameter();
    $parameter->parse_text($text);
    return $parameter;
  }

/*
 * FIXME: these tests have too many assertions. Probably will require some refactoring of Parameter::parse_text().
 */
  public function testValueWithPipeAndTrailingNewline() {
    $text = "last1 = [[:en:Bigwig%%CITATION_BOT_PIPE_PLACEHOLDER%%SomeoneFamous]]\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'last1');
    $this->assertEquals($parameter->eq, ' = ');
    $this->assertEquals($parameter->val, '[[:en:Bigwig|SomeoneFamous]]');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueWithSpacesLeadingSpaceTrailingNewline() {
    $text = " first1 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, ' ');
    $this->assertEquals($parameter->param, 'first1');
    $this->assertEquals($parameter->eq, ' = ');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueWithSpacesAndTrailingNewline() {
    $text = "first2 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'first2');
    $this->assertEquals($parameter->eq, ' = ');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueWithPreEqSpaceAndTrailingNewline() {
    $text = "first3 =\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'first3');
    $this->assertEquals($parameter->eq, ' =');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueWithPostEqSpaceAndTrailingNewline() {
    $text = "first4= \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'first4');
    $this->assertEquals($parameter->eq, '= ');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueNoSpacesTrailingNewline() {
    $text = "first5=\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'first5');
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueNoEquals() {
    $text = "first6 \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, '');
    $this->assertEquals($parameter->eq, '');
    $this->assertEquals($parameter->val, 'first6');
    $this->assertEquals($parameter->post, " \n");
  }

  public function testBlankValueNonBreakingSpaces() {
    $text = " first7 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, ' first7 ');  //These are non-breaking spaces
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, ' ');  //This is a non-breaking space
    $this->assertEquals($parameter->post, "\n");
  }

  public function testMultilineParamTrailingNewline() {
    $text = "multiline\nparam=\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, "multiline\nparam");
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testTwoBracketsInValue() {
    $text = "title=Synthetic studies on β-lactam antibiotics. Part 10. Synthesis of 7β-[2-carboxy-2-(4-hydroxyphenyl)acetamido]-7.alpha.-methoxy-3-[[(1-methyl-1H-tetrazol-5-yl)thio]methyl]-1-oxa-1-dethia-3-cephem-4-carboxylic acid disodium salt (6059-S) and its related 1-oxacephems";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'title');
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, "Synthetic studies on β-lactam antibiotics. Part 10. Synthesis of 7β-[2-carboxy-2-(4-hydroxyphenyl)acetamido]-7.alpha.-methoxy-3-[[(1-methyl-1H-tetrazol-5-yl)thio]methyl]-1-oxa-1-dethia-3-cephem-4-carboxylic acid disodium salt (6059-S) and its related 1-oxacephems");
    $this->assertEquals($parameter->post, "");
  }

  public function testHasProtectedCommentInValue() {
    $text = "archivedate= 24 April 2008 # # # Citation bot : comment placeholder 0 # # #";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'archivedate');
    $this->assertEquals($parameter->eq, '= ');
    $this->assertEquals($parameter->val, "24 April 2008 # # # Citation bot : comment placeholder 0 # # #");
    $this->assertEquals($parameter->post, "");
  }
  public function testHasUnreplacedCommentInValue() {
    $text = "archivedate= 9 August 2006 <!--DASHBot-->";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'archivedate');
    $this->assertEquals($parameter->eq, '= ');
    $this->assertEquals($parameter->val, "9 August 2006 <!--DASHBot-->");
    $this->assertEquals($parameter->post, "");
  }
}
