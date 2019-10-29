<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  

  public function testExpansion_doi_not_from_crossrefRG() {
     $text = '{{Cite journal| doi= 10.13140/RG.2.1.1002.9609}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('Lesson Study as a form of in-School Professional Development', $expanded->get('title'));
  }
  

  public function testUrlReferences() {
       $page = $this->process_page("<ref>http://doi.org/10.1007/s12668-011-0022-5< / ref>");
       $this->assertSame('huh', $page->parsed_text());
  }

    
}
