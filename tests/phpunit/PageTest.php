<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function test1() {
      $text = "{{cite journal | first = Peter | last = Frohling | title = Bonaparte's Gull Feeding on Walnut Meat | journal = The Wilson Bulletin | volume = 79 | issue = 3 | page = 341 | date = September 1967 | jstor = 4159631 }}";
      $page = $this->process_citation($text);
      $this->assertNull($page->parsed_text());
  }
  public function test2() {
      $text = "{{cite journal |title=The Old Straight Track - Alfred Watkins |journal=The Geographical Journal, published by The Royal Geographical Society (with the Institute of British Geographers |date=August 1926 |volume=68 |issue=2 |pages=152-153 |doi=10.2307/1782454 |url=https://www.jstor.org/stable/1782454 |accessdate=1 January 2019}}";
      $page = $this->process_citation($text);
      $this->assertNull($page->parsed_text());
  }
}
