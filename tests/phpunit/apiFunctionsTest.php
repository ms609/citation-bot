<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testComplexCrossRef() {
     $text = '{{cite journal |author=Fleming L |title=Ciguatera Fish Poisoning}}';
     $expanded = $this->process_citation($text);
  }
}
