<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testParameterWithNoParameters() {
    $text = "{{cite journal|doi=10.1353/mec.0.0026}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('not right title', $expanded->parsed_text());
  }

  
}
