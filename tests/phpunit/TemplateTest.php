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

   
  public function testBibcodeDotEnding() {
     $text='{{cite journal|title=Electric Equipment of the Dolomites Railway|journal=Nature|date=2 January 1932|volume=129|issue=3244|page=18|doi=10.1038/129018a0}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('1932Natur.129Q..18.', $expanded->get('bibcode'));
  }
   

 public function testBadBibcodeARXIVPages() { // Some bibcodes have pages set to arXiv:1711.02260
    $text = '{{cite journal|bibcode=2017arXiv171102260L}}';
    $expanded = $this->process_citation($text);
    $pages = $expanded->get('pages');
    $volume = $expanded->get('volume');
    $this->assertEquals(FALSE, stripos($pages, 'arxiv'));
    $this->assertEquals(FALSE, stripos('1711', $volume));
    $this->assertNull($expanded->get('journal'));  // if we get a journal, the the data is updated and test probably no longer gets bad data
 }
    


  /* TODO 
  Test adding a paper with > 4 editors; this should trigger displayeditors
  Test finding a DOI and using it to expand a paper [See testLongAuthorLists - Arxiv example?]
  Test adding a doi-is-broken modifier to a broken DOI.
  */    
}
