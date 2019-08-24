<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testParameterWithNoParameters() {
    $text = "{{cite journal|vauthors=Overbosch D, Schilthuis H, Bienzle U, etal |title=Atovaquone-proguanil versus mefloquine for malaria prophylaxis in nonimmune travelers: results from a randomized, double-blind study |journal=Clin. Infect. Dis. |volume=33 |issue=7 |pages=1015–21 | date=October 2001 |pmid=11528574 |doi=10.1086/322694}}";
    $expanded = $this->process_citation($text);
    $text = "{{Cite journal|url=https://www.jstor.org/stable/pdf/10.2979/jewisocistud.19.3.1.pdf?refreqid=excelsior:bc29ef6a23a86329a20f15f4ef6e30d2|language=en|doi=10.2979/jewisocistud.19.3.1|jstor = 10.2979/jewisocistud.19.3.1|title = Did the Khazars Convert to Judaism?|journal = Jewish Social Studies|volume=19|issue=3|pages=1–72|year=2013|last1=Stampfer}}";
    $expanded = $this->process_citation($text);
    $this->assrtNull($text, $expanded->parsed_text());
  }

  
  
}
