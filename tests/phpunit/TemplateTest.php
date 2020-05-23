<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
  public function testLotsOfFloaters() {
    $text_in = "{{citation|doi=10.1007/s00373-007-0720-5}}";
    $prepared = $this->process_citation($text_in);
    $this->assertSame('huh', $prepared->parsed_text());
  }
  
 
 
}
