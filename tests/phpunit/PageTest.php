<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {


   public function testUrlReferencesWithText0() {
      $text = "{{Cite journal | url=http://iopscience.iop.org/1367-2630/11/6/063043/ | doi=10.1088/1367-2630/11/6/063043| title=High error-rate quantum key distribution for long-distance communication| journal=New Journal of Physics| volume=11| issue=6| pages=063043| year=2009| last1=Mubashir Khan| first1=Muhammad| last2=Murphy| first2=Michael| last3=Beige| first3=Almut| bibcode=2009NJPh...11f3043M| arxiv=0901.3909}}";
      $page = $this->prepare_citation($text);
      $this->assertNull($page->parsed_text());
  }

}
