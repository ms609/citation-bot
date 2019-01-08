<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testUrlReferencesThatFail() {
      $text = "{{cite book|last=Gratzer|first=Walter |title=Eurekas and Euphorias: The Oxford Book of Scientific Anecdotes|url=https://books.google.com/books?id=4eTIxt6sN2oC&pg=PT32|accessdate=1 August 2012|date=28 November 2002|publisher=Oxford University Press|isbn=978-0-19-280403-7|pages=32–|chapter=5. Light on sweetness: the discovery of aspartame}} {{cite book |last=Farmelo |first=Graham |author-link=Graham Farmelo |title=Churchill's Bomb: How the United States Overtook Britain in the First Nuclear Arms Race |publisher=Basic Books |year=2013 |isbn=978-0-465-02195-6 |location=New York |ref=harv}}{{cite book |last=Hewlett |first=Richard G. |authorlink=Richard G. Hewlett |last2=Anderson |first2=Oscar E. |url=https://www.governmentattic.org/5docs/TheNewWorld1939-1946.pdf |title=The New World, 1939–1946 |publisher=Pennsylvania State University Press |year=1962 |location=University Park |oclc=493738019 |ref=harv|accessdate=26 March 2013}}";
      $page = $this->process_page($text);
      $this->assertNull(TRUE);
  }

}
