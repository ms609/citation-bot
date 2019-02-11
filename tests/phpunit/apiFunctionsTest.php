<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  

  public function testArxivDateUpgradeSeesDate() {
      $text = '{{cite web |url= https://www.flightglobal.com/news/articles/pacific-aerospace-poised-to-open-final-assembly-line-429988/description}}';
      $expanded = $this->process_citation($text);
      $this->assertNotNull($expanded->get('title'));
      $this->assertEquals('Should be something', $expanded->get('date'));
      
  }
  
 
}
