<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 

  public function testNeedToEditTwiceToGetPMID() {
    $text = "{{Cite arXiv |arxiv=1907.02565 |class=cs.DL |first=Giovanni |last=Colavizza |first2=Iain |last2=Hrynaszkiewicz |title=The citation advantage of linking publications to research data |first3=Isla |last3=Staden |first4=Kirstie |last4=Whitaker |first5=Barbara |last5=McGillivray |year=2019}}";
    $expanded = $this->process_citation($text);
    $this->assertNull$expanded->parsed_text());  
  }

  public function testWierdNONBookMetaData() {
    $text = "{{Cite journal|doi=10.5061/dryad.cf493|url=http://datadryad.org/resource/doi:10.5061/dryad.cf493|hdl=10255/dryad.92916 |last1=Smith|first1=Martin R.|title=Data from: A palaeoscolecid worm from the Burgess Shale. Dryad Digital Repository|year=2015}}
    $expanded = $this->process_citation($text);
    $this->assertNull$expanded->parsed_text());  
  }
  
  
}
