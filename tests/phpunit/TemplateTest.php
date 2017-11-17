<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

// Initialize bot configuration
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
final class TemplateTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
 
   public function testEmptyCitations() {
       
       $headers_test = get_headers('https://en.wikipedia.org/wiki/User:AManWithNoPlan', 1);
       print_r($headers_test);
       $headers_test = get_headers('https://en.wikipedia.org/wiki/User:Stanlha', 1);
       print_r($headers_test);
       $headers_test = get_headers('https://en.wikipedia.org/wiki/User:AManWithNoPlanXXXXXX', 1);
       print_r($headers_test);
       exit(333);
   }
}
