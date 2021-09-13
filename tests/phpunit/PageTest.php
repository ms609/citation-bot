<?php
declare(strict_types=1);

/*
 * Tests for Page.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class PageTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_HTTP !== '' || BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }


 
  public function testPageChangeSummary14() : void {
   $this->requires_zotero(function() : void {
      $page = $this->process_page("<ref>[https://www.nytimes.com/2018/05/10/obituaries/charlie-russell-who-befriended-bears-dies-at-76.html]</ref>");
      echo "\n" . $page->parsed_text() . "\n" . $page->edit_summary() .  "\n" ;
      $this->assertSame('WHAT?', $page->edit_summary());
   });
  }
 
 
}
