<?php

/*
 * Tests for Parameter.php.
 */
 
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

if (!function_exists(arxiv_callable_error_handler)) {
   function arxiv_callable_error_handler($errno,$errstr,$errfile,$errline) {
      if ($errno === 1024 && $errstr === "API Error in query_adsabs: Unauthorized" && getenv('TRAVIS')) {
          echo "\n -API Error in query_adsabs: Unauthorized";
          return TRUE;
      } elseif ($errno === 1024 && $errstr === "Error in query_adsabs: Could not decode AdsAbs response" && getenv('TRAVIS')) {
          echo "\n -Error in query_adsabs: Could not decode AdsAbs response";
          return TRUE;
      } else {
          echo "\n STRING IS " . $errstr ;
          echo "\n ERRNUM IS " . $errno ;
          return FALSE;
      }
   }
}

final class ParameterTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
    if (!defined("PIPE_PLACEHOLDER")) {
// this is usually done elsewhere in the code
      define("PIPE_PLACEHOLDER", '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #');
    }
        set_error_handler("arxiv_callable_error_handler");
  }

  protected function tearDown() {
     set_error_handler(NULL);
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
    $text = "last1 = [[:en:Bigwig# # # CITATION_BOT_PLACEHOLDER_PIPE # # #SomeoneFamous]]\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'last1');
    $this->assertEquals($parameter->eq, ' = ');
    $this->assertEquals($parameter->val, '[[:en:Bigwig|SomeoneFamous]]');
    $this->assertEquals($parameter->post, "\n");
  }
  public function testParameterWithNoParamName() {
    $text = " = no param name";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertEquals(' = ', $parameter->eq);
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

  // This test may not work, depending on your test environment.
  // Works on Tool Labs with PHP 5.5.9-1ubuntu4.13 (cli), PHPUnit 3.7.28
  public function testBlankValueNonBreakingSpaces() {
    $text = " first7 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, ' first7 ');  //These are non-breaking spaces
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, ' ');  //This is a non-breaking space
    $this->assertEquals($parameter->post, "\n");
  }

  public function testMultilinevalueTrailingNewline() {
    $text = "param=multiline\nvalue\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, "param");
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, "multiline\nvalue");
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
