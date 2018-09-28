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
  
include_once("./TestHeader.php");
  
  public function testIsValidUser() {
    $result = is_valid_user('Smith609');
    $this->assertEquals(TRUE, $result);
    $result = is_valid_user('Stanlha'); // Random user who exists but does not have page as of Nov 2017
    $this->assertEquals(TRUE, $result);
    $result = is_valid_user('Not_a_valid_user_at_Dec_2017'); 
    $this->assertEquals(FALSE, $result);
  }
  
}
