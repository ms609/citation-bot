<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  

  public function testDropUrlCode2() { // URL redirects to URL with the same DOI
      $text = '{{cite journal | last = De Vivo | first = B. | title = New constraints on the pyroclastic eruptive history of the Campanian volcanic Plain (Italy) | url = http://www.springerlink.com/content/8r046aa9t4lmjwxj/ | doi = 10.1007/s007100170010 }}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('url'));
   }
   public function testDropUrlCode3() { // url is same as one doi points to, except for http vs. https
      $text = "{{cite journal | first = Luca | last = D'Auria | year = 2015 | title = Magma injection beneath the urban area of Naples | url = http://www.nature.com/articles/srep13100 | doi=10.1038/srep13100 }}";
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('url'));
   }
   public function testDropUrlCode4() {
     $text = '{{cite journal | url = https://www.cell.com/trends/genetics/fulltext/S0168-9525(18)30054-4 | doi = 10.1016/j.tig.2018.03.001 }}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url')); // Recognize canonical publisher URL as duplicate of valid doi
   }
     
       public function testZoteroExpansionPII2() {
     $text = '{{cite journal | url = https://www.thelancet.com/journals/laneur/article/PIIS1474-4422(17)30401-5/fulltext | doi = 10.1016/S1474-4422(17)30401-5 }}';
     $expanded = $this->expand_via_zotero($text);
     $this->assertNull($expanded->get('url')); // Recognize canonical publisher URL as duplicate of valid doi
   }
 
}
