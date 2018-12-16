<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testArxivDateUpgradeSeesDate() {
      $text = '{{cite journal|url=https://www.gbif.org/dataset/0938172b-2086-439c-a1dd-c21cb0109ed5}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->parsed_text()); 
  }
}
