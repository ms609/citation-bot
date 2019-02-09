<?php

/*
 * Current tests that are failing.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class expandFnsTest extends testBaseClass {

  public function testBogusPageRanges() {  // At some point this test will age out (perhaps add special TRAVIS code to template.php
    $text = '{{Cite journal| doi = 10.1017/jpa.2018.43|pages = 960}}';
    $expanded = $this->process_citation($text);
    echo $expanded->parsed_text();
    $this->assertEquals('960', $expanded->get('at')); // Existing page number was within existing range
  }
}    
