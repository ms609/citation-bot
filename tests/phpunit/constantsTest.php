<?php

/*
 * Tests for constants.php.
 */

class constantsTest extends PHPUnit_Framework_TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }

  public function testConstantsDefined() {
    $this->assertEquals(PIPE_PLACEHOLDER, '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
    $this->assertEquals(dontCap, mb_convert_case(unCapped, MB_CASE_TITLE, "UTF-8"));
  }

}
