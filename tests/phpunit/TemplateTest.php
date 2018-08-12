<?php

/*
 * Tests for Template.php, called from expandFns.php.
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
 
final class TemplateTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  protected function requires_secrets($function) {
    if (getenv('TRAVIS_PULL_REQUEST')) {
      echo "\n - Skipping test: Risks exposing secret keys";
      $this->assertNull(NULL); // Make Travis think we tested something
    } else {
      $function();
    }
  }
  
  protected function process_citation($text) {
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    $expanded_text = $page->parsed_text();
    $template = new Template();
    $template->parse_text($expanded_text);
    return $template;
  }
  
  protected function process_page($text) {  // Only used if more than just a citation template
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  public function testParameterWithNoParameters() {
    $text = "{{Cite web | testy1= | testy2=}}';
    $expanded = $this->process_citation($text);
    $expanded->rename('testy2','testy1');
    $this->assetNull($expanded->parsed_text());
  }
  
  
}
