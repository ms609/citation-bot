<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testUrlReferencesWithText14() {
      $text = "{{cite journal|pages = &ndash; }}";
      $page = $this->process_citation($text);
      $this->assertEquals($text, $page->parsed_text());
  }

}

