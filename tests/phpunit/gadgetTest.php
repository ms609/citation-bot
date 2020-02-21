<?php
/*
 * Tests for gadgetapi.php
 */
require_once __DIR__ . '/../testBaseClass.php';
 
final class gadgetTest extends testBaseClass {
  public function testGadget() {
   $text="{{cite arxiv|eprint=1412.6572}}";
   $this->process_citation($text);
   $this->assertNull($template->expanded_text());
  }
}
