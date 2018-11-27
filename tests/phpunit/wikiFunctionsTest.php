<?php

require_once __DIR__ . '/../testBaseClass.php';
 
final class wikiFunctionsTest extends testBaseClass {
  
  public function testBadAuthor() {
    $text = '{{Cite journal|doi=10.17816/uroved513-6}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('last1'));
    $this->assertNull($expanded->get('first1'));
  }
  
}
