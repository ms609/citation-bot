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
     $this->assertSame('what', $expanded->parsed_text());
  }
}
