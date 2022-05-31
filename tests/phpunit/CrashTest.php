<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $text = '{{cite journal |last1=Délot |first1=Emmanuèle C |last2=Vilain |first2=Eric J |title=Nonsyndromic 46,XX Testicular Disorders of Sex Development |chapter=Nonsyndromic 46,XX Testicular Disorders/Differences of Sex Development |journal=GeneReviews |date=2003 |url=https://www.ncbi.nlm.nih.gov/books/NBK1416/ |access-date=6 December 2018 |archive-date=23 June 2020 |archive-url=https://web.archive.org/web/20200623171901/https://www.ncbi.nlm.nih.gov/books/NBK1416/ |url-status=live }}';
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite book', $template->wikiname());
  }


  public function testblockCI() : void {
    $this->assertTrue(FALSE);
  }
}
