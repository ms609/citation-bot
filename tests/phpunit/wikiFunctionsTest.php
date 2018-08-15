<?php
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
final class wikiFunctionsTest extends PHPUnit\Framework\TestCase {
  
  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  public function testIsValidUser() {

    $this->assertEquals(FALSE, FALSE);
  }
  
}
