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
 
  
  public function testHugePage() {
    $text = file_get_contents('https://en.wikipedia.org/w/index.php?title=Vietnam_War&action=raw');
    $page = new TestPage();
    $page->parse_text($text);
    // We do get to here
    $page->expand_text();
  }

}
