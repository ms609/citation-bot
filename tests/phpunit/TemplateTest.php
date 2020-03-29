<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 

  public function testParameterWithNoParameters() {
    $text = "{{cite book |last1=Baldwin |first1=AL |chapter=Mast cell activation by stress |title=Methods in Molecular Biology |date=2006 |volume=315 |pages=349–60 |doi=10.1385/1-59259-967-2:349 |pmid=16110169 |isbn=1-59259-967-2 }}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->parsed_text());
  }

 
 
}
