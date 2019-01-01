<?php

/*
 * Current tests that are failing.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class expandFnsTest extends testBaseClass {

  public function testCapitalization() {
    $text='{{cite web|url=https://www.nature.com/articles/546031a#bk4}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->parsed_text());
  }
    
}
