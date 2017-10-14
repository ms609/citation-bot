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

  public function testReadWrite() {
    $page = new TestPage();
    $page->get_text_from('User:Blocked Testing Account/readtest');
    $this->assertEquals('This page tests bots', $page->parsed_text());
    
    $writeTestPage = 'User talk:Blocked Testing Account';
    $message1 = 'A bot will soon overwrite this page.';
    $message2 = 'Bots overwrite this page frequently.';
    
    $page = new TestPage();
    $page->get_text_from($writeTestPage);
    if ($page->parsed_text() == $message1) {
      $page->overwrite_text($message2);
      $page->write("Testing bot write function");
      $page->get_text_from($writeTestPage);
      $this->assertEquals($message2, $page->parsed_text());
    } else {
      $page->overwrite_text($message1);
      $page->write("Testing bot write function");
      $page->get_text_from($writeTestPage);
      $this->assertEquals($message1, $page->parsed_text());
    }    
  }
   
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
