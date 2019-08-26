<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testParameterWithNoParameters() {
    $text = "{{cite journal|vauthors=Overbosch D, Schilthuis H, Bienzle U, etal |title=Atovaquone-proguanil versus mefloquine for malaria prophylaxis in nonimmune travelers: results from a randomized, double-blind study |journal=Clin. Infect. Dis. |volume=33 |issue=7 |pages=1015–21 | date=October 2001 |pmid=11528574 |doi=10.1086/322694}}";
    $expanded = $this->process_citation($text);
    $this->assertNull( $expanded->parsed_text());
  }

 
}
