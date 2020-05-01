<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {


  public function testWierdNONBookMetaData() {
    $text = "{{Cite journal|doi=10.5061/dryad.cf493|url=http://datadryad.org/resource/doi:10.5061/dryad.cf493|hdl=10255/dryad.92916 |last1=Smith|first1=Martin R.|title=Data from: A palaeoscolecid worm from the Burgess Shale. Dryad Digital Repository|year=2015}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->parsed_text());  
  }
  
  
}
