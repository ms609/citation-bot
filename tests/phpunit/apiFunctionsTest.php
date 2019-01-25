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
  
  public function testExpansion_doi_not_from_crossrefRG() {
     $text = '{{Cite journal| doi= 10.13140/RG.2.1.1002.9609}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('Lesson Study as a form of in-School Professional Development', $expanded->get('title'));
     $this->assertEquals('2015', $expanded->get('year'));
     $this->assertEquals('Aoibhinn Ni Shuilleabhain', $expanded->get('author1'));
  }
  
    public function testExpansion_doi_not_from_crossrefJapanJournal() {
     $text = '{{cite journal|doi=10.11429/ppmsj1919.17.0_48}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('On the Interaction of Elementary Particles. I', $expanded->get('title'));
     $this->assertEquals('1935', $expanded->get('year'));
     $this->assertEquals('Proceedings of the Physico-Mathematical Society of Japan. 3Rd Series', $expanded->get('journal'));
     $this->assertEquals('17', $expanded->get('volume'));
     $this->assertEquals('YUKAWA', $expanded->get('last1'));
     $this->assertEquals('Hideki', $expanded->get('first1'));
    }
  // See https://www.doi.org/demos.html
  public function testExpansion_doi_not_from_crossrefBook() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1017/CBO9780511983658');  // This is cross-ref doi, so for DX DOI expansion
     $this->assertNull($expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefBookChapter() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1002/0470841559.ch1');  // This is cross-ref doi, so for DX DOI expansion
     $this->assertNull($expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefDataCiteSubsets() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/PANGAEA.726855');
     $this->assertNull($expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossrefDataCiteEarthquake() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/GFZ.GEOFON.gfz2009kciu');
     $this->assertNull($expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefDataCiteMappedVisualization() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/PANGAEA.667386');
     $this->assertNull($expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossrefDataCitevideo() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3207/2959859860');
     $this->assertNull($expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossrefISTIC_Journal() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3866/PKU.WHXB201112303');
     $this->assertNull($expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefISTIC_Data() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3972/water973.0145.db');
     $this->assertNull($expanded->parsed_text());
  }
 
  public function testExpansion_doi_not_from_crossrefISTIC_Thesis() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.7666/d.y351065');
     $this->assertNull($expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossrefJaLC_Journal() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.11467/isss2003.7.1_11');
     $this->assertNull($expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossrefJaLC_Journal2() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.7875/leading.author.2.e008');
     $this->assertNull($expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossrefmEDRA_Journal() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1430/8105');
     $this->assertNull($expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefmEDRA_Monograph() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1392/BC1.0');
     $this->assertNull($expanded->parsed_text());
  }     

}
