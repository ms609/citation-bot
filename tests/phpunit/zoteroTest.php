<?php

/*
 * Tests for api_handlers/zotero.php, called from expandFns.php.
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
 
class ZoteroTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
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
  
  public function testZoteroExpansion() {
    $text = '{{Cite journal|url =https://www.nytimes.com/2018/06/11/technology/net-neutrality-repeal.html}}';
    $expanded = $this->process_citation($text);
    expand_by_zotero($expanded);
    $this->assertEquals("Net Neutrality Has Officially Been Repealed. Here's How That Could Affect You", $expanded->get('title'));
    $this->assertEquals('cite newspaper', $expanded->wikiname());
        
    $text = '<ref>http://rspb.royalsocietypublishing.org/content/285/1887/20181780</ref>';
    $expanded = $this->process_page($text);
    $this->assertTrue(strpos($expanded->parsed_text(), 'Hyoliths with pedicles illuminate the origin of the brachiopod body plan') !== FALSE);
  }

}
