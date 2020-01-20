<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testAdsabsApi() {
    $this->assertNull('do not commit');
  }
  
    public function testThesisDOI() {
     $doi = '10.17077/etd.g638o927';
     $text = "{{cite journal|doi=$doi}}";
     $template = $this->make_citation($text);
     expand_doi_with_dx($template, $doi);
     $this->assertSame('10.17077/etd.g638o927', $template->get('doi'));
     $this->assertSame("The caregiver's journey", $template->get('title'));
     $this->assertSame('The University of Iowa', $template->get('publisher'));
     $this->assertSame('2018', $template->get('year'));
     $this->assertSame('Schumacher', $template->get('last1')); 
     $this->assertSame('Lisa Anne', $template->get('first1'));
  }
}
