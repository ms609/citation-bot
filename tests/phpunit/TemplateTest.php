<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
  
 
  public function testGroveMusic1() {
    $text = '{{cite journal | title = NOPE NOPE NOPE Molecular cloning and biochemical characterization of a truncated, secreted member of the human family of Ca2+-activated Cl- channels| pmid = <!-- NOPE -->|bibcode=<!-- nope --> | doi = 10.1016/S0167-4781(99)00008-1 }}';
    $template = $this->process_citation($text);
    $this->assertSame('NOPE', $template->parsed_text());
  }
 
  
 
}
