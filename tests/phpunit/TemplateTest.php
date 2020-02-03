<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
  
  public function testJustAnLCCN() {

      $text = '{{Cite book | doi = 10.1117/12.135408}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->parsed_text());

  }
 
 
}
