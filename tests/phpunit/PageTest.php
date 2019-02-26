<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testPageChangeSummary() {
      $page = $this->process_citation('{{cite journal|chapter=chapter name|title=book name}}');
      $this->assertEquals('Alterhere',$page->get('ss'));
  }
 
 
}
