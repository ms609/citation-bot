<?php

/*
 * Tests for api_handlers/citoid.php, called from expandFns.php.
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

  // Keep tests to a minimum: we have a budget of 200 calls to the public API per day, and cannot access
  // our own implementation of the server
  public function testCitoidExpansion() {
    $text = '{{Cite journal|url =https://www.nytimes.com/2018/06/11/technology/net-neutrality-repeal.html}}';
    $expanded = $this->process_citation($text);
    expand_by_citoid($expanded);
    $this->assertEquals('Net Neutrality Has Officially Been Repealed. Here’s How That Could Affect You', $expanded->get('title'));
    $this->assertEquals('cite newspaper', $expanded->wikiname());
  }
   
}
