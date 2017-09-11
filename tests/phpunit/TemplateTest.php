<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */

class expandFnsTest extends PHPUnit_Framework_TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  protected function process_citation($text) {
    $template = new Template();
    $template->parse_text($text);
    $template->process();
    return $template;
  }
  
  public function testUseUnusedData() {
    $text = "{{Cite web | http://google.com | editor1link=test | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6 }}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite web', $expanded_citation->wikiname());
    $this->assertEquals($expanded_citation->get('url'), 'http://google.com');
    $this->assertEquals($expanded_citation->get('editor1-link'), 'test');
    $this->assertEquals($expanded_citation->get('title'), 'I am a title'); 
    $this->assertEquals($expanded_citation->get('author'), 'Other, A. N.');
    $this->assertEquals($expanded_citation->get('issue'), '9');
    $this->assertEquals($expanded_citation->get('volume'), '22');
    $this->assertEquals($expanded_citation->get('pages'), '5-6');
  }
  
  public function testJstorExpansion() {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'cite journal');
    $this->assertEquals($expanded_citation->get('jstor'), '1701972');
  }
  
  public function testPmidExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'cite journal');
    $this->assertEquals($expanded_citation->get('pmid'), '1941451');
  }
  
  public function testPMCExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'cite journal');
    $this->assertEquals($expanded_citation->get('pmc'), '154623');
  }
  
  public function testArxivExpansion() {
    $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded_citation->wikiname());
    $this->assertEquals($expanded_citation->get('arxiv'), '0806.0013');
  }
  
  public function testAmazonExpansion() {
    $text = "{{Cite web | http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite book', $expanded_citation->wikiname());
    $this->assertEquals('0226845494', $expanded_citation->get('asin'));
  }
  
  public function testDoiExpansion() {
    $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'cite journal');
    $this->assertEquals($expanded_citation->get('doi'), '10.1111/j.1475-4983.2012.01203.x');
  }
  
  public function testGarbageRemovalAndSpacing() {
    $text = "{{Cite web | pages=10-11| edition = 3rd ed. |journal=My Journal| issn=1234-4321 | publisher=Unwarranted |issue=0|accessdate=2013-01-01|quotes=no}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('{{Cite journal| pages=10–11| edition = 3rd |journal=My Journal| issn=1234-4321 }}', $expanded_citation->parsed_text());
  }
  
  public function testEtAlHandlingAndSpaceRetention() {
    $text = "{{Cite book | authors=Smith, A; Jones, B; Western, C., et al.}}";
    $parsed_text = $this->process_citation($text);
    $this->assertEquals('{{Cite book | last1=Smith| first1=A|last2 = Jones|first2 = B|last3 = Western|first3 = C.|author4 = and others|displayauthors = 3}}', $parsed_text->parsed_text()); 
  }
  
  public function testGoogleBooksExpansion() {
    $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html?id=SjpSkzjIzfsC&redir_esc=y}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'cite book');
    $this->assertEquals($expanded_citation->get('url'), 
      'http://books.google.com/?id=SjpSkzjIzfsC');
    $this->assertEquals($expanded_citation->get('title'), 
      'Wonderful Life: The Burgess Shale and the Nature of History');
    $this->assertEquals($expanded_citation->get('isbn'), '9780393307009');
    $this->assertEquals($expanded_citation->get('author1'), 'Gould');
    $this->assertEquals($expanded_citation->get('first1'), 'Stephen Jay');
    $this->assertEquals($expanded_citation->get('year'), '1990');
  }
}
