<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testEmptyPage() {
      $page = $this->process_page('{{cite journal |last1=Tsegaye |first1=Diress |last2=Moe |first2=Stein R. |last3=Vedeld |first3=Paul |last4=Aynekulu |first4=Ermias |title=Land-use/cover dynamics in Northern Afar rangelands, Ethiopia |journal=Agriculture, Ecosystems & Environment |date=15 October 2010 |volume=139 |issue= |pages=174–180 |doi=10.1016/j.agee.2010.07.017}}');
      $this->assertNull($page->parsed_text());
  }
}
