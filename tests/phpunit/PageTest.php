<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

 
  public function testNobots() {
      $text = '{{cite journal |last1=Bouwmeester |first1=Dik |last2=Pan |first2=Jian-Wei|last3=Mattle |first3=Klaus|last4=Eibl |first4=Manfred |last5=Weinfurter |first5=Harald|last6=Zeilinger |first6=Anton|year=1997 |title=Experimental Quantum Teleportation |journal=Nature |volume=390 |issue=6660 |pages=575–579 |last-author-amp=yes |url=http://qudev.ethz.ch/content/courses/QSIT06/pdfs/Bouwmeester97.pdf |doi=10.1038/37539|bibcode = 1997Natur.390..575B |arxiv=1901.11004 }}';
      template = $this->process_citation($text);
      $this->assertNull($page->parsed_text());
  }
 

}
