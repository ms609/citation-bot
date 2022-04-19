<?php
declare(strict_types=1);

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testBotExpandWrite() : void {
      $api = new WikipediaBot();
      $page = new TestPage();
      $writeTestPage = 'User:Blocked Testing Account/writetest';
      $page->get_text_from($writeTestPage);
      $origText = $page->parsed_text();
      $trialCitation = '{{Cite journal | title Bot Testing | ' .
        'doi_broken_date=1986-01-01 | doi = 10.1038/nature09068}}';
      $page->overwrite_text($trialCitation);
      $page_result = $page->write($api, "Testing bot write function");
      if (TRAVIS && !$page_result) {
        // ! API call failed: '''Your IP address is in a range which has been blocked on all wikis.''' The block was made by [//meta.wikimedia.org/wiki/User:Jon_Kolbert Jon Kolbert] (meta.wikimedia.org). The reason given is ''[[m:NOP|Open Proxy]]: Colocation webhost - Contact [[m:Special:Contact/stewards|stewards]] if you are affected ''. * Start of block: 02:23, 27 October 2019 * Expiration of block: 02:23, 27 October 2021
        $page->get_text_from($writeTestPage);
        $this->assertSame($origText, $page->parsed_text());
      } else {
        // Double check we can read it back
        $page->get_text_from($writeTestPage);
        $this->assertSame($trialCitation, $page->parsed_text());
      }
      $this->requires_secrets(function() : void {
       $this->assertTrue(TRAVIS || $page_result); // If we have tokens and are not in TRAVIS, then should have worked
      });
      $page->overwrite_text($trialCitation);
      $page->expand_text();
      $this->assertTrue(strpos($page->edit_summary(), 'journal, ') > 3);
      $this->assertTrue(strpos($page->edit_summary(), ' Removed ') > 3);
      if ($page_result) {
         $this->assertTrue($page->write($api));
      } else {
         $this->assertFalse($page->write($api));
      }
      $page->get_text_from($writeTestPage);
      $this->assertTrue(strpos($page->parsed_text(), 'Nature') > 5);
  }
}
