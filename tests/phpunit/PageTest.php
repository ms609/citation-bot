<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

// Initialize bot configuration
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
class PageTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  protected function process_page($text) {
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }


  
  public function testBadPage() {
    $text = file_get_contents('https://en.wikipedia.org/w/index.php?title=Andragogy&oldid=859058657&action=raw');
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    $page->parsed_text();
  }

}
