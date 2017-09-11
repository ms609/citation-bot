<?php

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

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
    $text = "{{Cite web | http://google.com | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6 }}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite web',          $expanded_citation->wikiname());
    $this->assertEquals('http://google.com', $expanded_citation->get('url'));
    $this->assertEquals('I am a title',      $expanded_citation->get('title')); 
    $this->assertEquals('Other, A. N.',      $expanded_citation->get('author'));
    $this->assertEquals('9'           ,      $expanded_citation->get('issue'));
    $this->assertEquals('22'          ,      $expanded_citation->get('volume'));
    $this->assertEquals('5-6'         ,      $expanded_citation->get('pages'));
  }
  
  public function testJstorExpansion() {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded_citation->wikiname());
    $this->assertEquals('1701972'     , $expanded_citation->get('jstor'));
  }
  
  public function testPmidExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded_citation->wikiname());
    $this->assertEquals('1941451', $expanded_citation->get('pmid'));
  }
  
  public function testPMCExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded_citation->wikiname());
    $this->assertEquals('154623', $expanded_citation->get('pmc'));
  }
  
  public function testArxivExpansion() {
    $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded_citation->wikiname());
    $this->assertEquals('0806.0013', $expanded_citation->get('arxiv'));
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
    $this->assertEquals('cite journal', $expanded_citation->wikiname());
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $expanded_citation->get('doi'));
  }
  
  public function testGarbageRemovalAndSpacing() {
    $text = "{{Cite web | pages=10-11| edition = 3rd ed. |journal=My Journal| issn=1234-4321 | publisher=Unwarranted |issue=0|accessdate=2013-01-01|quotes=no}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('{{Cite journal| pages=10–11| edition = 3rd |journal=My Journal| issn=1234-4321 }}', $expanded_citation->parsed_text());
  }
  
  public function testEtAlHandlingAndSpaceRetention() {
    $text = "{{Cite book | authors=Smith, A; Jones, B; Western, C., et al.}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('{{Cite book | last1=Smith| first1=A|last2 = Jones|first2 = B|last3 = Western|first3 = C.|author4 = and others|displayauthors = 3}}', $expanded_citation->parsed_text()); 
  }
  
  public function testGoogleBooksExpansion() {
    $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html?id=SjpSkzjIzfsC&redir_esc=y}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('cite book', $expanded_citation->wikiname());
    $this->assertEquals('http://books.google.com/?id=SjpSkzjIzfsC', $expanded_citation->get('url'));
    $this->assertEquals('Wonderful Life: The Burgess Shale and the Nature of History',
      $expanded_citation->get('title'));
    $this->assertEquals('9780393307009', $expanded_citation->get('isbn')   );
    $this->assertEquals('Gould'        , $expanded_citation->get('author1'));
    $this->assertEquals('Stephen Jay'  , $expanded_citation->get('first1') );
    $this->assertEquals('1990'         , $expanded_citation->get('year')   );
  }
}
