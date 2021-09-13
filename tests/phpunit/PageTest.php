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
      $page = $this->process_page("<ref>{{cite web|url=https://www.pbs.org/wnet/nature/episodes/walking-with-giants-the-grizzlies-of-siberia/introduction/3027/|title=Walking with Giants: The Grizzlies of Siberia|work=Nature|publisher=PBS|accessdate=9 December 2013}}</ref> and ''Bear Man of Kamchatka'' ([[BBC]], 2006).<ref>{{cite web|url=http://www.bbc.co.uk/programmes/b00793rz|title=Bear Man of Kamchatka|work=Natural World|publisher=BBC|accessdate=9 December 2013}}</ref> He died after complications from a surgery at a hospital in Calgary, Alberta on May 7, 2018.<ref>[https://www.nytimes.com/2018/05/10/obituaries/charlie-russell-who-befriended-bears-dies-at-76.html]</ref>");
      $this->assertSame('WHAT?', $page->edit_summary());
  }
 
 
}
