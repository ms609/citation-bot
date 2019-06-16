<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testParameterWithNoParameters() {
    $text = "{{cite journal|pmid=43446}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->parsed_text());
  }
    
}
