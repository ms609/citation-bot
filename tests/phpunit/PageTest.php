<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

   public function testDuplicateParametersFlagging() {
      $text = '{{cite web|year=2010|year=2011}}';
      $expanded = $this->process_citation($text);
      $text = '{{cite web|year=|year=2011}}';
      $expanded = $this->process_citation($text);
      $text = '{{cite web|year=2011|year=}}';
      $expanded = $this->process_citation($text);
      $text = '{{cite web|year=|year=|year=2011|year=|year=}}';
      $expanded = $this->process_citation($text);
      $text = '{{cite web|year=|year=|year=|year=|year=}}';
      $expanded = $this->process_citation($text);
      $this->assertNull(NULL);
    }
}
