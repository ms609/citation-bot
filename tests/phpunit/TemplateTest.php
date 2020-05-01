<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 

  public function testJournal2Web() {
    $text = "{{Cite journal|journal=www.cnn.com}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('www.cnn.com', $expanded->get('website'));  
  }

 
 
}
