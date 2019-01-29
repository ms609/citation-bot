<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testParameterWithNoParameters() {
    $text = "{{Cite web | text without equals sign  }}";
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
  }
  
    
}
