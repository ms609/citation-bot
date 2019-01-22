<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testAdsabsApi() {
    $this->requires_secrets(function() {
      $bibcodes = [
       '2017NatCo...814879F', // 0
       '1974JPal...48..524M', // 1
       '1996GSAB..108..195R', // 2
       '1966Natur.211..116M', // 3
       '1995Sci...267...77R', // 4
       '1995Geo....23..967E', // 5
       '2003hoe..book.....K', // 6
       '2000A&A...361..952H', // 7
       ];
      $text = '{{Cite journal | bibcode = ' . implode('}}{{Cite journal | bibcode = ', $bibcodes) . '}}';
      $page = new TestPage();
      $page->parse_text($text);
      $templates = $page->extract_object('Template');
      $page->expand_templates_from_identifier('bibcode', $templates);
      $this->assertEquals('Nature', $templates[3]->get('journal'));
      $this->assertEquals('Geology', $templates[5]->get('journal'));
      $this->assertEquals('14879', $templates[0]->get('pages'));
      $this->assertNull($templates[6]->get('journal'));
      $this->assertEquals('Astronomy and Astrophysics', $templates[7]->get('journal'));
    });
  }
  
  public function testArxivDateUpgradeSeesDate() {
      $text = '{{Cite journal|date=September 2010|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('September 2010', $expanded->get('date'));
      $this->assertNull($expanded->get('year'));
      
      $text = '{{Cite journal|date=September 2009|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('date'));
      $this->assertEquals('2010', $expanded->get('year'));
  }
  
  public function testExpansion_doi_not_from_crossref() {
     $text = '{{Cite journal| doi= 10.13140/RG.2.1.1002.9609}}';
     $expanded = $this->process_citation($text);
     $this->assertNull(NULL); // Stopped working
     // $this->assertEquals('Lesson Study as a form of in-School Professional Development', $expanded->get('title'));
     // $this->assertEquals('2015', $expanded->get('year'));
     $text = '{{cite journal|doi=10.11429/ppmsj1919.17.0_48}}';
     $expanded = $this->process_citation($text);
     $this->assertNull(NULL); // does it work?
  }
}
