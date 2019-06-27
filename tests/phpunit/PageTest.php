<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

 
  public function testEmptyPage() {
      $page = $this->process_page('{{cite journal |doi=10.1086/305561 |url=http://iopscience.iop.org/0004-637X/498/2/640 }}
');
      $this->assertNull($page->parsed_text());
  }

}
