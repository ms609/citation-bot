<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testExpansion_doi_not_from_crossref_eidr_Black_Panther_Movie() {
     $expanded = $this->process_citation('{{Cite journal | last1 = Glaesemann | first1 = K. R. | last2 = Fried | first2 = L. E. | doi = | title = Improved wood–kirkwood detonation chemical kinetics | journal = Theoretical Chemistry Accounts | volume = 120 | pages = 37–43 | year = 2007 |issue=1–3}}');
     $this->assertEquals('SHOULD HAVE DOI', $expanded->parsed_text());
  }  
  
}
