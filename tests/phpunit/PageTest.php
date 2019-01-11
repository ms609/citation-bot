<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testUrlReferences() {
   $text = '{{cite journal |last=Zhang |first=Ningxin |last2=Magee |first2=Beatrice B. |last3=Magee |first3=Paul T. |last4=Holland |first4=Barbara R. |last5=Rodrigues |first5=Ely |last6=Holmes |first6=Ann R. |last7=Cannon |first7=Richard D. |last8=Schmid |first8=Jan |date=2017-04-17 |title=Selective Advantages of a Parasexual Cycle for the Yeast Candida albicans |journal=Genetics |volume=200 |issue=4 |pages=1117–1132 |doi=10.1534/genetics.115.177170 |pmc=4574235 |pmid=26063661}}}';
   $expanded = $this->process_citation($text);
   $this->assertNull($expanded->get('bincode'));
   $this->assertTrue(FALSE);
  }
}
