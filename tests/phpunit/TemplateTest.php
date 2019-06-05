<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testEmptyCitations() {
    $text = '{{cite book|||}}'; // bot removed pipes
    $expanded = $this->process_page($text);
    $this->assertEquals('{{cite book|||}}', $expanded->parsed_text());
  }
    
}
