<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testUrlReferencesThatFail() {
      $text = '{{cite book|last=Gratzer|first=Walter |title=Eurekas and Euphorias: The Oxford Book of Scientific Anecdotes|url=https://books.google.com/books?id=4eTIxt6sN2oC&pg=PT32|accessdate=1 August 2012|date=28 November 2002|publisher=Oxford University Press|isbn=978-0-19-280403-7|pages=32–|chapter=5. Light on sweetness: the discovery of aspartame}}';
      $page = $this->process_citation($text);
      $this->assertEquals($text, $page->parsed_text());
  }

}
