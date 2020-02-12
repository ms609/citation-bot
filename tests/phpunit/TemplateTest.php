<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
  public function testFindBibcodeNoTitle() {
   $this->requires_bibcode(function() {
    $text = "{{Cite journal | last1 = Glaesemann | first1 = K. R. | last2 = Gordon | first2 = M. S. | last3 = Nakano | first3 = H. | journal = Physical Chemistry Chemical Physics | volume = 1 | issue = 6 | pages = 967–975| year = 1999 |issn = 1463-9076}}";
    $expanded = $this->make_citation($text);
    $expanded->expand_by_adsabs();
    $this->assertSame('1999PCCP....1..967G', $expanded->get('bibcode'));
   });
   $this->assertTrue(FALSE);
  }

 
}
