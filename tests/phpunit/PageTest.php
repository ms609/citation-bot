<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {


  public function testRespectDates() {
      $text = '{{cite journal |doi=10.1002/1097-0142(19920315)69:6+<1578::AID-CNCR2820691312>3.0.CO;2-K |title=Molecular marker test standardization |year=1992 |last1=Koepke |first1=John A. |journal=Cancer |volume=69 |pages=1578–81 |pmid=1540898 |issue=6 Suppl}}';
      $page = $this->process_page($text);
      $this->assertNull($page->parsed_text());
  }
}
