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
/*
  public function testReadExpandWrite() {
    $page = new TestPage();
    $page->get_text_from('User:Blocked Testing Account/readtest');
    $this->assertEquals('This page tests bots', $page->parsed_text());
    
    $writeTestPage = 'User talk:Blocked Testing Account';
    $trialCitation = '{{Cite journal | title Bot Testing | doi_broken_date=1986-01-01 | doi = 10.1038/nature09068}}';
    
    $page = new TestPage();
    $page->get_text_from($writeTestPage);
    $page->overwrite_text($trialCitation);
    $page->write("Testing bot write function");
    $page->get_text_from($writeTestPage);
    $this->assertEquals($trialCitation, $page->parsed_text());
    $page->expand_text();
    $page->write();
    $this->assertTrue(strpos($page->parsed_text(), 'Nature') > 5);
  }
  */
  public function testRedirects() {
    $page = new Page();
    $page->get_text_from('NoSuchPage:ThereCan-tBe');
    $this->assertEquals(-1, $page->is_redirect()[0]);
    $page->get_text_from('User:Citation_bot/use');
    $cbu = $page->is_redirect();
    $this->assertEquals(0, $cbu[0]);
    $page->get_text_from('WP:UCB');
    $this->assertEquals(1,  $page->is_redirect()[0]);
  }
  
}
