<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  

  public function testComplexCrossRef() {
     $text = '{{citation | bibcode = 2018MNRAS.tmp.2192I}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->parsed_text());
  }
}
