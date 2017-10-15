<?php

/*
 * Current tests that are failing.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
$SLOW_MODE = TRUE;
 

class WikipediaBotTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
    
  public function testLogIn() {
    $test_bot = new WikipediaBot();
    $this->assertFalse($test_bot->logged_in());
    $test_bot->log_in();
    $this->assertTrue($test_bot->logged_in());
  }
    
}
