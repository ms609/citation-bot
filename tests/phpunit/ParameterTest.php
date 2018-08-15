<?php

/*
 * Tests for Parameter.php.
 */
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

final class ParameterTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
    if (!defined("PIPE_PLACEHOLDER")) {
// this is usually done elsewhere in the code
      define("PIPE_PLACEHOLDER", '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #');
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
    $text = "last1 = [[:en:Bigwig# # # CITATION_BOT_PLACEHOLDER_PIPE # # #SomeoneFamous]]\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'last1');
    $this->assertEquals($parameter->eq, ' = ');
    $this->assertEquals($parameter->val, '[[:en:Bigwig|SomeoneFamous]]');
    $this->assertEquals($parameter->post, "\n");
  }

}
