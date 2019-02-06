<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
 
  public function testArxivDateUpgradeSeesDate() {
      $text = '{{citation|doi = 10.1007/978-3-642-60408-9_19  }}';
      $expanded = $this->process_citation($text);
  }
  
}
