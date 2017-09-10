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
    for ($i = 0; $i < length(dontCap); $i++) {
      $this->assertEquals(dontCap[$i], mb_convert_case(unCapped[$i], MB_CASE_TITLE, "UTF-8"));
    }
  }

}
