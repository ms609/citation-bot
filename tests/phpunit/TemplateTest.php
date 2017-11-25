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
  
  protected function process_citation($text) {
    $template = new Template();
    $template->parse_text($text);
    $template->process();
    return $template;
  }
  
  protected function process_page($text) {
    $page = new Page();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  public function testParameterWithNoParameters() {
    $text = "{{Cite jorunal|pmid= 28120229  }}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('Nope', $expanded->parsed_text());
  }
   
}
