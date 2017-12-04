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

  public function testBotRead() {
    $page = new TestPage();
    $api = new WikipediaBot();
    $page->get_text_from('User:Blocked Testing Account/readtest', $api);
    $this->assertEquals('This page tests bots', $page->parsed_text());
  }
  
    /*
     * This test is commented out as Travis CI servers are blocked.
     * I've asked User:Slakr whether we can get them unblocked for logged-in users.
  public function testBotExpandWrite() {
    $api = new WikipediaBot();
    $page = new TestPage();
    $writeTestPage = 'User:Blocked Testing Account/writetest';
    $page->get_text_from($writeTestPage, $api);
    $trialCitation = '{{Cite journal | title Bot Testing | ' .
      'doi_broken_date=1986-01-01 | doi = 10.1038/nature09068}}';
    $page->overwrite_text($trialCitation);
    #$this->assertFalse($page->write($api, "Testing bot write function")); // Travis CI servers blocked on Wikipedia.
    
    $page->get_text_from($writeTestPage, $api);
    $this->assertEquals($trialCitation, $page->parsed_text());
    $page->expand_text();
    $this->assertTrue(strpos($page->edit_summary(), 'journal, ') > 3);
    $this->assertTrue(strpos($page->edit_summary(), ' Removed ') > 3);
    #$this->assertFalse($page->write($api)); // Travis can't write as its IPs are blocked.
    
    $page->get_text_from($writeTestPage, $api);
    $this->assertTrue(strpos($page->parsed_text(), 'Nature') > 5);
  }
    */
  
}
