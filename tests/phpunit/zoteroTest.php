<?php

/*
 * Tests for api_handlers/zotero.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
class ZoteroTest extends testBaseClass {

// TODO - seems to want a login or cookie   
//public function testZoteroExpansionRG() {
//    $text = '{{Cite journal|url =https://www.researchgate.net/publication/23445361}}';
//    $expanded = $this->expand_via_zotero($text);
//    $this->assertEquals('10.1136/jnnp.2008.144360', $expanded->get('doi'));
//  }
      
  public function testZoteroExpansionPII() {
    $text = '{{Cite journal|url = https://www.sciencedirect.com/science/article/pii/S0024379512004405}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals('10.1016/j.laa.2012.05.036', $expanded->get('doi'));
    $this->assertNull($expanded->get('url')); // Recognize canonical publisher URL as duplicate of valid doi
  }

  public function testZoteroExpansionNBK() {
    $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24662/|access-date=2099-12-12}}';  // Date is before access-date so will expand
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals('Continuing Efforts to More Efficiently Use Laboratory Animals', $expanded->get('title'));
    $this->assertEquals('2004', $expanded->get('year'));
    $this->assertEquals('National Academies Press (US)', $expanded->get('publisher'));
  }
 
  public function testZoteroExpansionAccessDates() {
    $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24663/|access-date=1978-12-12}}';  // Access date is too far in past, will not expand
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals($text, $expanded->parsed_text());
  }

  public function testZoteroExpansionNYT() {
    $text = '{{Cite journal|url =https://www.nytimes.com/2018/06/11/technology/net-neutrality-repeal.html}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals("Net Neutrality Has Officially Been Repealed. Here's How That Could Affect You", $expanded->get('title'));
    $this->assertEquals('Keith', $expanded->get('first1')); // Would be tidied to 'first' in final_parameter_tudy
    $this->assertEquals('Collins', $expanded->get('last1'));
    $this->assertEquals('cite news', $expanded->wikiname());
  }
  public function testZoteroExpansionRSRef() {
    $text = '<ref>http://rspb.royalsocietypublishing.org/content/285/1887/20181780</ref>';
    $expanded = $this->process_page($text);
    $this->assertTrue(mb_stripos($expanded->parsed_text(), 'Hyoliths with pedicles illuminate the origin of the brachiopod body plan') !== FALSE);
  }
    
  public function testZoteroExpansionNRM() {
    $text = '{{cite journal | url = http://www.nrm.se/download/18.4e32c81078a8d9249800021554/Bengtson2004ESF.pdf}}';
    $expanded = $this->process_page($text);
    $this->assertTrue(TRUE); // Gives one fuzzy match.  For now we just check that this doesn't crash PHP.
    // In future we should use this match to expand citation.
  }

  public function testNoneAdditionOfAuthor() {
    // Rubbish author listed in page metadata; do not add. 
    $text = "{{cite web |url=http://www.westminster-abbey.org/our-history/people/sir-isaac-newton}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('last1'));
  }
  
  public function testDateTidiness() {
    $text = "{{cite web|title= Gelada| website= nationalgeographic.com |url= http://animals.nationalgeographic.com/animals/mammals/gelada/ |publisher=[[National Geographic Society]]|accessdate=7 March 2012}}";
    $expanded = $this->expand_via_zotero($text);
    $date = $expanded->get('date');
    $date = str_replace('2011-05-10', '', $date); // Sometimes we get no date
    $this->assertEquals('', $date);
    $text = "{{cite web | url = http://www.avru.org/compendium/biogs/A000060b.htm }}";
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals('2018-06-05', $expanded->get('date'));
  }

  public function testZoteroExpansion_citeseerx() {
    $text = '{{Cite journal| citeseerx=10.1.1.483.8892 }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Chemical Kinetics Models for the Fatigue Behavior of Fused Silica Optical Fiber', $expanded->get('title'));
  }

  public function testZoteroExpansion_hdl() {
    $text = '{{Cite journal| hdl=10411/OF7UCA }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Replication Data for: Perceiving emotion in non-social targets: The effect of trait empathy on emotional through art', $expanded->get('title'));
  }

  public function testZoteroExpansion_osti() {
    $text = '{{Cite journal| osti=1406676 }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.1016/j.ifacol.2017.08.010', $expanded->get('doi'));
  }
    
  public function testZoteroExpansion_rfc() {
    $text = '{{Cite journal| rfc=6679 }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Explicit Congestion Notification (ECN) for RTP over UDP', $expanded->get('title'));
  }
     
  public function testZoteroExpansion_ssrn() {
    $text = '{{Cite journal| ssrn=195630 }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('The Pricing of Internet Stocks', $expanded->get('title'));
    $this->assertEquals('September 1999', $expanded->get('date'));
  }    
  public function testZoteroExpansion_doi_not_from_crossref() {
    $text = '{{Cite journal|doi=10.3233/PRM-140291}}'; // mEDRA DOI - they do not provide RIS information from dx.doi.org
    $expanded = $this->process_citation($text);
    $this->assertTrue(strpos($expanded->get('journal'), 'Journal of Pediatric Rehabilitation Medicine') === 0);// Sometimes includes a journal of....
  }
  public function testZoteroExpansion_biorxiv() {
    $text = '{{Cite journal| biorxiv=326363 }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Sunbeam: An extensible pipeline for analyzing metagenomic sequencing experiments', $expanded->get('title'));
  }
 
  public function testZoteroBadVolumes() { // has ( and such in it
    $text = '{{cite journal|url=https://biodiversitylibrary.org/page/32550604}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('volume'));
  }
 
   public function testZoteroKoreanLanguage() {
    $text = '{{cite journal|url=http://www.newsen.com/news_view.php?uid=201606131737570410}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('title')); // Hopefully will work some day and not give � character
  }
 
  public function testDropUrlCode() {  // url is same as one doi points to
     $text = '{{cite journal |url=https://pubs.rsc.org/en/Content/ArticleLanding/1999/CP/a808518h|doi=10.1039/A808518H|title=A study of FeCO+ with correlated wavefunctions|journal=Physical Chemistry Chemical Physics|volume=1|issue=6|pages=967–975|year=1999|last1=Glaesemann|first1=Kurt R.|last2=Gordon|first2=Mark S.|last3=Nakano|first3=Haruyuki|bibcode=1999PCCP....1..967G}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }
  public function testDropUrlCode2() { // URL redirects to URL with the same DOI
     $text = '{{cite journal | last = De Vivo | first = B. | title = New constraints on the pyroclastic eruptive history of the Campanian volcanic Plain (Italy) | url = http://www.springerlink.com/content/8r046aa9t4lmjwxj/ | doi = 10.1007/s007100170010 }}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }
  public function testDropUrlCode3() { // url is same as one doi points to, except for http vs. https
     $text = "{{cite journal | first = Luca | last = D'Auria | year = 2015 | title = Magma injection beneath the urban area of Naples | url = http://www.nature.com/articles/srep13100 | doi=10.1038/srep13100 }}";
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }
}
