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
      $writeTestPage = 'User:Blocked_Testing_Account/writetest';
      $page->get_text_from($writeTestPage);
      $trialCitation = '{{Cite journal | title Bot Testing | ' .
        'doi_broken_date=1986-01-01 | doi = 10.1038/nature09068}}';
      $page->overwrite_text($trialCitation);
      $page_result = $page->write($api, "Testing bot write function");
   echo "\n\n DEBUG " . $page_result . "\n\n";

      $page->get_text_from($writeTestPage);
      if ($trialCitation === $page->parsed_text()) {
         echo "\n same \n";
      } else {
         echo  " TRIAL : " . $trialCitation . "\n\n";
         echo  " PARCED: " .  $page->parsed_text() . "\n\n";
      }
      $page->expand_text();
   echo "SUMMARY:  " . $page->edit_summary() . "\n\n";
   
   //   $this->assertTrue(strpos($page->edit_summary(), 'journal, ') > 3);
   //   $this->assertTrue(strpos($page->edit_summary(), ' Removed ') > 3);
   // Wrap this in requires secrets?????   $this->assertTrue($page->write($api));
      
      $page->get_text_from($writeTestPage);
   
  echo  " NATURE : " . $page->parsed_text() . "\n\n";

  }
 

}
