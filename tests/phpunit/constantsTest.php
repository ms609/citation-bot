<?php

/*
 * Tests for constants.php.
 */
 
 // backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}


class constantsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }

  public function testConstantsDefined() {
    $this->assertEquals(PIPE_PLACEHOLDER, '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
    for ($i = 1; $i < sizeof(dontCap); $i++) { ## Not 0, which is "and Then"
      $this->assertEquals(dontCap[$i], mb_convert_case(unCapped[$i], MB_CASE_TITLE, "UTF-8"));
    }
  }

}
