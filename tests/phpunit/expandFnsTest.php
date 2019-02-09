<?php

/*
 * Current tests that are failing.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class expandFnsTest extends testBaseClass {

  public function testBogusPageRanges() {  // At some point this test will age out (perhaps add special TRAVIS code to template.php
    $text = '{{Cite journal| doi = 10.1017/jpa.2018.43|title = New well-preserved scleritomes of Chancelloriida from early Cambrian Guanshan Biota, eastern Yunnan, China|journal = Journal of Paleontology|volume = 92|issue = 6|pages = 960|year = 2018|last1 = Zhao|first1 = Jun|last2 = Li|first2 = Guo-Biao|last3 = Selden|first3 = Paul A}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('960', $expanded->get('at')); // Existing page number was within existing range
  }
}    
