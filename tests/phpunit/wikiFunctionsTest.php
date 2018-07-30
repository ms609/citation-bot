<?php

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
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
final class wikiFunctionsTest extends PHPUnit\Framework\TestCase {
  
  protected function setUp() {
     set_error_handler("arxiv_callable_error_handler");
  }
  protected function tearDown() {
     set_error_handler(NULL);
  }
  
  
  public function testIsValidUser() {
    $result = is_valid_user('Smith609');
    $this->assertEquals(TRUE, $result);
    $result = is_valid_user('Stanlha'); // Random user who exists but does not have page as of Nov 2017
    $this->assertEquals(TRUE, $result);
    $result = is_valid_user('Not_a_valid_user_at_Dec_2017'); 
    $this->assertEquals(FALSE, $result);
  }
  
}
