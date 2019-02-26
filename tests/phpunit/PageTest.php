<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testPageChangeSummary() {
   $text = "{{cite journal | first = Peter | last = Frohling | title = Bonaparte's Gull Feeding on Walnut Meat | journal = The Wilson Bulletin | volume = 79 | issue = 3 | page = 341 | date = September 1967 | jstor = 4159631 }}";
      $page = $this->process_citation($text);
      $this->assertEquals('341',$page->get('page'));
  }
 
 
}
