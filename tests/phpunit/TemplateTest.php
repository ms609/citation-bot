<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
 
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
  
  protected function process_page($text) {
    $page = new Page();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
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
    $this->assertEquals('5–6'         ,      $expanded_citation->get('pages'));
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
  
  public function testJournalCapitalization() {
    $expanded = $this->process_citation("{{Cite journal|pmid=9858585}}");
    $this->assertEquals('Molecular and Cellular Biology', $expanded->get('journal'));
  }
  
  public function testPageDuplication() {
     $text = '{{cite journal| p=546 |doi=10.1103/PhysRev.57.546|title=Nuclear Fission of Separated Uranium Isotopes |journal=Physical Review |volume=57 |issue=6 |year=1940 |last1=Nier |first1=Alfred O. |last2=Booth |first2=E. T. |last3=Dunning |first3=J. R. |last4=Grosse |first4=A. V. }}';
     $expanded = $this->process_citation($text);
     $this->assertEquals($text, $expanded->parsed_text());
   }
  
  public function testUnknownJournal() {
    $text = '{{cite journal|bibcode= 1975STIN...7615344H |title= Development of a transmission error model and an error control model  |volume= 76 |author1= Hammond |first1= J. L. |last2= Brown |first2= J. E. |last3= Liu |first3= S. S. S. |year= 1975}}';

    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
  }

  
  public function testBrokenDoiDetection() {
    $text = '{{cite journal|doi=10.3265/Nefrologia.pre2010.May.10269|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
    $expanded = $this->process_citation($text);
    var_dump($expanded->get('doi-brokendate'));
    $this->assertNull($expanded->get('doi-brokendate'));
    
    $text = '{{cite journal|doi=10.3265/Nefrologia.NOTAREALDOI.broken|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
    $expanded = $this->process_citation($text);
    var_dump($expanded->get('doi-brokendate'));
    var_dump($expanded);
    $this->assertNotNull($expanded->get('doi-brokendate'));
  }
  
  public function testOpenAccessLookup() {
    $text = '{{cite journal|doi=10.1038/nature12373}}';
    $expanded = $this->process_citation($text);
    var_dump($expanded->get('doi-brokendate'));
    $this->assertEquals('http://www.ncbi.nlm.nih.gov/pmc/articles/PMC4221854', $expanded->get('url'));
    $this->assertEquals('Accepted manuscript', $expanded->get('format'));
  }
  
  /* Don't run test until I check the consensus on how such citations should be handled
  public function testEtAlHandlingAndSpaceRetention() {
    $text = "{{Cite book | authors=Smith, A; Jones, B; Western, C., et al.}}";
    $expanded_citation = $this->process_citation($text);
    $this->assertEquals('{{Cite book | last1=Smith| first1=A|last2 = Jones|first2 = B|last3 = Western|first3 = C.|author4 = and others|displayauthors = 3}}', $expanded_citation->parsed_text()); 
  }
  */
  
  public function testCommentHandling() {
    $text = "{{cite book|pages=3333 <!-- yes --> }} {{cite book <!-- no --> | pages=3}}";
    $expanded_page = $this->process_page($text);
    $this->assertEquals($text, $expanded_page->parsed_text());
  }
  
  /* Commented out whilst Google server are inaccessible
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
  */
}
