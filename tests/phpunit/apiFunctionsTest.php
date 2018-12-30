<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  
  public function testExpansion_doi_not_from_crossref() {
     $text = '{{cite journal|title=News Section|journal=Tempo (new series)|issue=169 (50th Anniversary, 1939–89)|date=June 1989|pages=69–70
|publisher=Cambridge University Press|issn=0040-2982|jstor=945334}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('journal'));
   }
}
