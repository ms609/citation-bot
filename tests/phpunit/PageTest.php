<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {


  
  public function testBotExpandWrite() {
   $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $page = new TestPage();
      $writeTestPage = 'User:Blocked Testing Account/writetest';
      $page->get_text_from($writeTestPage, $api);
      $trialCitation = '{{Cite journal | title Bot Testing | ' .
        'doi_broken_date=1986-01-01 | doi = 10.1038/nature09068}}';
      $page->overwrite_text($trialCitation);
      $this->assertTrue($page->write($api, "Testing bot write function"));
      
      $page->get_text_from($writeTestPage, $api);
      $this->assertSame($trialCitation, $page->parsed_text());
      $page->expand_text();
      $this->assertTrue(strpos($page->edit_summary(), 'journal, ') > 3);
      $this->assertTrue(strpos($page->edit_summary(), ' Removed ') > 3);
      $this->assertTrue($page->write($api));
      
      $page->get_text_from($writeTestPage, $api);
      $this->assertTrue(strpos($page->parsed_text(), 'Nature') > 5);
   });
  }
 
  
}
