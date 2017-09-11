<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */

class expandFnsTest extends PHPUnit_Framework_TestCase {

  protected function setUp() {
    if (!defined("PIPE_PLACEHOLDER")) {
      // this is usually done elsewhere in the code
      define("PIPE_PLACEHOLDER", '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
    }
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
    $this->assertEquals($expanded_citation->wikiname(), 'Cite_web');
    $this->assertEquals($expanded_citation->param['url'], 'http://google.com');
    $this->assertEquals($expanded_citation->param['editor1-link'], 'test');
    $this->assertEquals($expanded_citation->param['title'], 'I am a title'); 
    $this->assertEquals($expanded_citation->param['author'], 'Other, A. N.');
    $this->assertEquals($expanded_citation->param['issue'], '9');
    $this->assertEquals($expanded_citation->param['volume'], '22');
    $this->assertEquals($expanded_citation->param['pages'], '5-6');
  }
  
  public function testJstorExpansion() {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'Cite_journal');
    $this->assertEquals($expanded_citation->param['jstor'], '1701972');
  }
  
  public function testPmidExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'Cite_journal');
    $this->assertEquals($expanded_citation->param['pmid'], '1941451');
  }
  
  public function testPMCExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'Cite_journal');
    $this->assertEquals($expanded_citation->param['pmc'], '154623');
  }
  
  public function testArxivExpansion() {
    $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'Cite_journal');
    $this->assertEquals($expanded_citation->param['arxiv'], '0806.0013');
  }
  
  public function testAmazonExpansion() {
    $text = "{{Cite web | http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'Cite_journal');
    $this->assertEquals($expanded_citation->param['asin'], '0226845494');
  }
  
  public function testDoiExpansion() {
    $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'Cite_journal');
    $this->assertEquals($expanded_citation->param['doi'], '10.1111/j.1475-4983.2012.01203.x');
  }
  
  public function testGarbageRemoval() {
    $text = "{{Cite web | pages=10-11| edition = 3rd ed. |journal=My Journal|issn=1234-4321 | publisher=Unwarranted |issue=0|accessdate=2013-01-01|quotes=no}}";
    $parsed_text = $this->process_citation($text);
    $this->assertEquals($parsed_text->parsed_text(), '{{Cite journal| pages=10–11| edition = 3rd | journal=My Journal | issn=1234-4321 }}');
  }
  
  public function testEtAlHandlingAndSpaceRetention() {
    $text = "{{Cite book | authors=Smith, A; Jones, B; Western, C., et al.}}";
    $parsed_text = $this->process_citation($text);
    $this->assertEquals($parsed_text->parsed_text(), '{{Cite book | last1=Smith| first1=A|last2 = Jones|first2 = B|last3 = Western|first3 = C.|author4 = and others|displayauthors = 3}}'); 
  }
  
  public function testGoogleBooksExpansion() {
    $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html?id=SjpSkzjIzfsC&redir_esc=y}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals($expanded_citation->wikiname(), 'Cite_book');
    $this->assertEquals($expanded_citation->param['url'], 
      'http://books.google.com/?id=SjpSkzjIzfsC');
    $this->assertEquals($expanded_citation->param['title'], 
      'Wonderful Life: The Burgess Shale and the Nature of History');
    $this->assertEquals($expanded_citation->param['isbn'], '9780393307009');
    $this->assertEquals($expanded_citation->param['author1'], 'Gould');
    $this->assertEquals($expanded_citation->param['first1'], 'Stephen Jay');
    $this->assertEquals($expanded_citation->param['year'], '1990');
  }
}
