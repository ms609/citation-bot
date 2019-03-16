<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testPageChangeSummary() {
      $page = $this->process_citation('{{Cite journal |bibcode=2009AGUFM.H34D..04G}}');
  }
}
