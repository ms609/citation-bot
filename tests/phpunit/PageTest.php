<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

// Initialize bot configuration
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;

class TestPage extends Page {
  
  public function overwrite_text($text) {
    // Use in testing context only
    $this->text = $text;
  }

  public function parse_text($text) { // used in testing context.
    $this->text = $text;
    $this->start_text = $this->text;
    $this->modifications = array();
  }
  
}
 
class PageTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  protected function process_page($text) {
    $page = new Page();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  
}
