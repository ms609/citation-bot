<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testArxivDateUpgradeSeesDate() {
      $text = '{{cite journal|doi=10.17816/uroved513-6}}';
      $expanded = $this->process_citation($text);
     // $this->assertEquals('', $expanded->get(''));
     $this->assertNull($expanded->get('last1')); 
  } 
}
