<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

// Initialize bot configuration
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
class TemplateTest extends PHPUnit\Framework\TestCase {

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
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  public function testParameterWithNoParameters() {
    $text = "{{Cite web | text without equals sign  }}";
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
    $text = "{{  No pipe  }}";
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
  }
  
  public function testUseUnusedData() {
    $text = "{{Cite web | http://google.com | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6 }}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite web',          $expanded->wikiname());
    $this->assertEquals('http://google.com', $expanded->get('url'));
    $this->assertEquals('I am a title',      $expanded->get('title')); 
    $this->assertEquals('Other, A. N.',      $expanded->get('author'));
    $this->assertEquals('9'           ,      $expanded->get('issue'));
    $this->assertEquals('22'          ,      $expanded->get('volume'));
    $this->assertEquals('5–6'         ,      $expanded->get('pages'));
  }
  
  public function testJstorExpansion() {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('1701972'     , $expanded->get('jstor'));
  }
  
  public function testPmidExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('1941451', $expanded->get('pmid'));
  }
  
  public function testPMCExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('154623', $expanded->get('pmc'));
  }
  
  public function testArxivExpansion() {
    $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('0806.0013', $expanded->get('arxiv'));
    
    $text = "{{Cite arxiv | eprint = 0806.0013 | class=forgetit|publisher=uk.arxiv}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('0806.0013', $expanded->get('arxiv'));
    $this->assertNull($expanded->get('class'));
    $this->assertNull($expanded->get('eprint'));
    $this->assertNull($expanded->get('publisher'));
  }
  
  public function testAmazonExpansion() {
    $text = "{{Cite web | http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite book', $expanded->wikiname());
    $this->assertEquals('0226845494', $expanded->get('asin'));
  }
  
  public function testDoiExpansion() {
    $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));
  }
  
  public function testGarbageRemovalAndSpacing() {
    // Also tests handling of upper-case parameters
    $text = "{{Cite web | pages=10-11| Edition = 3rd ed. |journal=My Journal| issn=1234-4321 | publisher=Unwarranted |issue=0|accessdate=2013-01-01|quotes=no}}";
    $expanded = $this->process_citation($text);
    // ISSN should be retained when journal is originally present
    $this->assertEquals('{{Cite journal| pages=10–11| edition = 3rd |journal=My Journal| issn=1234-4321 }}', $expanded->parsed_text());
    
    $text = "{{Cite web | Journal=My Journal| issn=1234-4321 | publisher=Unwarranted }}";
    $expanded = $this->process_citation($text);
    // ISSN is removed when journal is added.  Is this the desired behaviour? ##TODO!
    $this->assertEquals('{{Cite journal| journal=My Journal}}', $expanded->parsed_text());
  }
  
  public function testJournalCapitalization() {
    $expanded = $this->process_citation("{{Cite journal|pmid=9858585}}");
    $this->assertEquals('Molecular and Cellular Biology', $expanded->get('journal'));
  }
  
  public function testPageDuplication() {
     global $SLOW_MODE;
     $SLOW_MODE = FALSE; // Otherwise we'll find a bibcode
     $text = '{{cite journal| p=546 |doi=10.1103/PhysRev.57.546|title=Nuclear Fission of Separated Uranium Isotopes |journal=Physical Review |volume=57 |issue=6 |year=1940 |last1=Nier |first1=Alfred O. |last2=Booth |first2=E. T. |last3=Dunning |first3=J. R. |last4=Grosse |first4=A. V. }}';
     $expanded = $this->process_citation($text);
     $this->assertEquals($text, $expanded->parsed_text());
   }
  
  public function testUnknownJournal() {
    $text = '{{cite journal|bibcode= 1975STIN...7615344H |title= Development of a transmission error model and an error control model  |volume= 76 |author1= Hammond |first1= J. L. |last2= Brown |first2= J. E. |last3= Liu |first3= S. S. S. |year= 1975}}';
    $expanded = $this->process_citation($text);
    $this->assertTrue($expanded->blank('journal'));
  }

  public function testCiteArxivRecognition() {
    $text = '{{Cite web | eprint=1203.0149}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Cite arxiv', $expanded->name());
  }
  
  public function testBrokenDoiDetection() {
    $text = '{{cite journal|doi=10.3265/Nefrologia.pre2010.May.10269|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
    $expanded = $this->process_citation($text);
    var_dump($expanded->get('doi-brokendate'));
    $this->assertNull($expanded->get('doi-broken-date'));
    
    $text = '{{cite journal|doi=10.3265/Nefrologia.NOTAREALDOI.broken|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('doi-broken-date'));
  }

  public function testOpenAccessLookup() {
    $text = '{{cite journal|doi=10.1038/nature12373}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('4221854', $expanded->get('pmc'));
    
    $text = '{{cite journal|doi=10.1038/nature08244}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('0904.1532', $expanded->get('arxiv'));
    
    $text = '{{cite journal|doi=10.1038//TODO}}';
    /*
    $this->assertEquals('http://some.url', $expanded->get('url'));
    $this->assertEquals('Accepted manuscript', $expanded->get('format'));
    */
  }
    
  /* Don't run test until I check the consensus on how such citations should be handled
  public function testEtAlHandlingAndSpaceRetention() {
    $text = "{{Cite book | authors=Smith, A; Jones, B; Western, C., et al.}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('{{Cite book | last1=Smith| first1=A|last2 = Jones|first2 = B|last3 = Western|first3 = C.|author4 = and others|displayauthors = 3}}', $expanded->parsed_text()); 
  }
  */
  
  public function testCommentHandling() {
    $text = "{{cite book|pages=3333 <!-- yes --> }} {{cite book <!-- no --> | pages=3<nowiki>-</nowiki>6}}";
    $expanded_page = $this->process_page($text);
    $this->assertEquals($text, $expanded_page->parsed_text());
  }
  
  public function testSiciExtraction() {
    $text = "{{cite journal|url=http://fake.url/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('0097-3157', $expanded->get('issn'));
    $this->assertEquals('2002', $this->getDateAndYear($expanded));
    $this->assertEquals('152', $expanded->get('volume'));
    $this->assertEquals('215', $expanded->get('pages'));
    $expanded = NULL;
    
    // Now check that parameters are NOT extracted when certain parameters exist
    $text = "{{cite journal|date=2002|journal=SET|url=http:/1/fake.url/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('issn'));
    $this->assertEquals('2002', $this->getDateAndYear($expanded));
    $this->assertEquals('152', $expanded->get('volume'));
    $this->assertEquals('215', $expanded->get('pages'));
  }
  
  public function testMisspeltParameters() {
    $text = "{{Cite journal | ahtour=S.-X. HU, M.-Y. ZHU, F.-C. ZHAO, and M. STEINER|tutle=A crown group priapulid from the early Cambrian Guanshan Lagerstätte,|jrounal=Geol. Mag.|pp. 1–5|year= 2017.}}";
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('author')); ## Check: the parameter might be broken down into last1, first1 etc
    $this->assertNotNull($expanded->get('title'));
    $this->assertNotNull($expanded->get('journal'));
    $this->assertNotNull($expanded->get('pages'));
    $this->assertNotNull($this->getDateAndYear($expanded));
    
    $text = "{{Cite journal | ahtour=S.-X. HU, M.-Y. ZHU, F.-C. ZHAO, and M. STEINER|tutel=A crown group priapulid from the early Cambrian Guanshan Lagerstätte,|jrounal=Geol. Mag.|pp. 1–5|year= 2017.}}";
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('author')); ## Check: the parameter might be broken down into last1, first1 etc
    $this->assertNotNull($expanded->get('tutel'));
    $this->assertNotNull($expanded->get('journal'));
    $this->assertNotNull($expanded->get('pages'));
    $this->assertNotNull($this->getDateAndYear($expanded));
  
    // test attempt to add a parameter listed in COMMON_MISTAKES
    $album_link = 'http://album.com';
    $expanded->add_if_new('albumlink', $album_link);
    $this->assertEquals($album_link, $expanded->get('titlelink'));    
     
    // Double-check pages expansion
    $text = "{{Cite journal|pp. 1-5}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('1–5',$expanded->get('pages'));
  }
       
  public function testId2Param() {
      $text = '{{cite book |id=ISBN 978-1234-9583-068, DOI 10.1234/bashifbjaksn.ch2, {{arxiv|1234.5678}} 
        {{oclc|12354|4567}} {{oclc|1234}} {{ol|12345}} }}';
      $expanded = $this->process_citation($text); // Not process_citation as there's an embedded template
      $this->assertEquals('978-1234-9583-068', $expanded->get('isbn'));
      $this->assertEquals('1234.5678', $expanded->get('arxiv'));
      $this->assertEquals('10.1234/bashifbjaksn.ch2', $expanded->get('doi'));
      $this->assertEquals('1234', $expanded->get('oclc'));
      $this->assertEquals('12345', $expanded->get('ol'));
      $this->assertNotNull($expanded->get('doi-broken-date'));
      $this->assertEquals(1, preg_match('~' . sprintf(Template::PLACEHOLDER_TEXT, '\d+') . '~i', $expanded->get('id')));
      
      $text = '{{cite book | id={{arxiv|id=1234.5678}}}}';
      $expanded = $this->process_citation($text); // Not process_citation as there's an embedded template
      $this->assertEquals('1234.5678', $expanded->get('arxiv'));
      
      $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} }}';
      $expanded = $this->process_citation($text); // Not process_citation as there's an embedded template
      $this->assertEquals('astr.ph/1234.5678', $expanded->get('arxiv'));     
  }
  
  
  public function testOrigYearHandling() {
      $text = '{{cite book |year=2009 | origyear = 2000 }}';
      $expanded = $this->process_citation($text); // Not process_citation as there's an embedded template
      $this->assertEquals('2000', $expanded->get('origyear'));
      $this->assertEquals('2009', $this->getDateAndYear($expanded));
      
      $text = '{{cite book | origyear = 2000 }}';
      $expanded = $this->process_citation($text); // Not process_citation as there's an embedded template
      $this->assertEquals('2000', $this->getDateAndYear($expanded));
      $this->assertNull($expanded->get('origyear'));
  }
  
  public function testGoogleBooksExpansion() {
    $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html?id=SjpSkzjIzfsC&redir_esc=y}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite book', $expanded->wikiname());
    $this->assertEquals('https://books.google.com/?id=SjpSkzjIzfsC', $expanded->get('url'));
    $this->assertEquals('Wonderful Life: The Burgess Shale and the Nature of History',
      $expanded->get('title'));
    $this->assertEquals('9780393307009', $expanded->get('isbn')   );
    $this->assertEquals('Gould'        , $expanded->get('last1'));
    $this->assertEquals('Stephen Jay'  , $expanded->get('first1') );
    $this->assertEquals('1990-09-17'   , $expanded->get('date'));
  }
  
  
  public function testErrantAuthor() {
    $text = '{{cite journal|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=onepage&q&f=true |title=The Passing of the Carrier Pigeon|journal=Popular Mechanics |date=February 1930|pages= 340}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
  }
  
  public function testLongAuthorLists() {
    $text = '{{cite web | https://arxiv.org/PS_cache/arxiv/pdf/1003/1003.3124v2.pdf}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('The ATLAS Collaboration', $expanded->first_author());
    $this->assertEquals('hep-ex', $expanded->get('class'));
    
    // Same paper, but CrossRef records full list of authors instead of collaboration name
    $text = '{{cite web | 10.1016/j.physletb.2010.03.064}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('29', $expanded->get('displayauthors'));
    $this->assertEquals('Aielli', $expanded->get('last30'));
    $this->assertEquals("Charged-particle multiplicities in pp interactions at <math>"
      . '\sqrt{s}=900\text{ GeV}' .
      "</math> measured with the ATLAS detector at the LHC", $expanded->get('title'));
    $this->assertNull($expanded->get('last31'));
  }
  

  public function testInPress() {
    $text = '{{Cite journal|pmid=9858585|date =in press}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('1999',$this->getDateAndYear($expanded));

    $text = '{{cite journal|pmid=9858585|year=in press}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('1999',$this->getDateAndYear($expanded));
  }
  
 public function testISODates() {
      $text = '{{cite book |author=Me |title=Title |year=2007-08-01 }}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('2007-08-01', $expanded->get('date'));
      $this->assertNull($expanded->get('year'));
  }
  
  public function testRIS() {
      $text = '{{Cite journal  | TY - JOUR
AU - Shannon, Claude E.
PY - 1948/07//
TI - A Mathematical Theory of Communication
T2 - Bell System Technical Journal
SP - 379
EP - 423
VL - 27
ER -  }}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('A Mathematical Theory of Communication', $expanded->get('title'));
     $this->assertEquals('1948-07', $expanded->get('date'));
     $this->assertEquals('Bell System Technical Journal', $expanded->get('journal'));
     $this->assertEquals('Shannon, Claude E', $expanded->first_author());
     $this->assertEquals('Shannon', $expanded->get('last1'));
     $this->assertEquals('Claude E', $expanded->get('first1'));
     $this->assertEquals('379–423', $expanded->get('pages'));
     $this->assertEquals('27', $expanded->get('volume'));   
  }
    

  public function testEndNote() {
      $book = '{{Cite book |
%0 Book
%A Geoffrey Chaucer
%D 1957
%T The Works of Geoffrey Chaucer
%E F.
%I Houghton
%C Boston
%N 2nd
      }}'; // Not quite clear how %E and %N should be handled here. Needs an assertion.
      $article = '{{Cite journal |
%0 Journal Article
%A Herbert H. Clark
%D 1982
%T Hearers and Speech Acts
%B Language
%V 58
%P 332-373
      }}'; // Not sure how %B should be handled; needs an assertion.
      $thesis = '{{Citation | 
%0 Thesis
%A Cantucci, Elena
%T Permian strata in South-East Asia
%D 1990
%I University of California, Berkeley
%R 10.1038/ntheses.01928
%9 Dissertation}}';
       $expanded = $this->process_citation($book);
       $this->assertEquals('Chaucer, Geoffrey', $expanded->first_author());
       $this->assertEquals('The Works of Geoffrey Chaucer', $expanded->get('title'));
       $this->assertEquals('1957', $this->getDateAndYear($expanded));
       $this->assertEquals('Houghton', $expanded->get('publisher'));
       $this->assertEquals('Boston', $expanded->get('location'));
       
       $expanded = $this->process_citation($article);
       $this->assertEquals('Clark, Herbert H', $expanded->first_author());
       $this->assertEquals('1982', $this->getDateAndYear($expanded));
       $this->assertEquals('Hearers and Speech Acts', $expanded->get('title'));
       $this->assertEquals('58', $expanded->get('volume'));
       $this->assertEquals('332–373', $expanded->get('pages'));
       
       
       $expanded = $this->process_citation($thesis);
       $this->assertEquals('Cantucci, Elena', $expanded->first_author());
       $this->assertEquals('Permian strata in South-East Asia', $expanded->get('title'));
       $this->assertEquals('1990', $this->getDateAndYear($expanded));
       $this->assertEquals('University of California, Berkeley', $expanded->get('publisher'));
       $this->assertEquals('10.1038/ntheses.01928', $expanded->get('doi'));  
  }
   
  public function testISBN() {  // Dashes, no dashes, etc.
    $text = "{{cite book|isbn=3-902823-24-0}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('978-3-902823-24-3', $expanded->get('isbn'));  // Convert with dashes
    $text = "{{cite book|isbn=978-3-902823-24-3}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('978-3-902823-24-3', $expanded->get('isbn'));  // Unchanged with dashes
    $text = "{{cite book|isbn=9783902823243}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('9783902823243', $expanded->get('isbn'));   // Unchanged without dashes
    $text = "{{cite book|isbn=3902823240}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('978-3902823243', $expanded->get('isbn'));   // Convert without dashes
    $text = "{{cite book|isbn=1-84309-164-X}}";
    $expanded = $this->process_citation($text);  
    $this->assertEquals('978-1-84309-164-6', $expanded->get('isbn'));  // Convert with dashes and a big X
    $text = "{{cite book|isbn=184309164x}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('978-1843091646', $expanded->get('isbn'));  // Convert without dashes and a tiny x
    $text = "{{cite book|isbn=Hello Brother}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('Hello Brother', $expanded->get('isbn')); // Rubbish unchanged
  }
   
  public function testEtAl() {
      $text = '{{cite book |auths=Alfred A Albertstein, Bertie B Benchmark, Charlie C. Chapman et al. }}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('Albertstein, Alfred A', $expanded->first_author());
      $this->assertEquals('Charlie C', $expanded->get('first3'));
      $this->assertEquals('etal', $expanded->get('displayauthors'));
  }
       
  public function testWebsite2Url() {
      $text = '{{cite book |website=ttp://example.org }}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('http://example.org', $expanded->get('url'));
      
      $text = '{{cite book |website=example.org }}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('http://example.org', $expanded->get('url'));
      
      $text = '{{cite book |website=ttp://jstor.org/pdf/123456 | jstor=123456 }}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('url'));
      
      $text = '{{cite book |website=ABC}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('url'));
      $this->assertEquals('ABC', $expanded->get('website'));
      
      $text = '{{cite book |website=ABC XYZ}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('url'));
      $this->assertEquals('ABC XYZ', $expanded->get('website'));
      
      $text = '{{cite book |website=http://ABC/ I have Spaces in Me}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('url'));
      $this->assertEquals('http://ABC/ I have Spaces in Me', $expanded->get('website'));
  }
  
  public function testHearst () {
       $text = '{{cite book|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=onepage&q&f=true}}';
       $expanded = $this->process_citation($text);
       $this->assertEquals('Hearst Magazines',$expanded->get('publisher'));
       $this->assertNull($expanded->get('last1'));
       $this->assertNull($expanded->get('last'));
       $this->assertNull($expanded->get('author'));
       $this->assertNull($expanded->get('author1'));
       $this->assertNull($expanded->get('authors'));
  }
       
  public function testLinefeeds(){
       $text = '{{cite arXiv|eprint=hep-th/0303241}}';
       $expanded = $this->process_citation($text);
       $this->assertEquals('Pascual Jordan, his contributions to quantum mechanics and his legacy in contemporary local quantum physics',$expanded->get('title'));
  }
   public function testInternalCaps() { // checks for title formating in tidy() not breaking things
      $text = '{{cite journal|journal=ZooTimeKids}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('ZooTimeKids', $expanded->get('journal'));
  }
  public function testExistingWikiText() { // checks for formating in tidy() not breaking things
      $text = '{{cite journal|title=[[Zootimeboys]] and Girls|journal=[[Zootimeboys]] and Girls}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('[[Zootimeboys]] and Girls', $expanded->get('journal'));
      $this->assertEquals('[[Zootimeboys]] and Girls', $expanded->get('title'));
  }
  public function testNewWikiText() { // checks for new information that looks like wiki text and needs escaped
      $text = '{{Cite journal|doi=10.1021/jm00193a001}}';  // This has greek letters, [, ], (, and ).
      $expanded = $this->process_citation($text);
      $this->assertEquals('Synthetic studies on β-lactam antibiotics. Part 10. Synthesis of 7β-&#91;2-carboxy-2-(4-hydroxyphenyl)acetamido&#93;-7.alpha.-methoxy-3-&#91;&#91;(1-methyl-1H-tetrazol-5-yl)thio&#93;methyl&#93;-1-oxa-1-dethia-3-cephem-4-carboxylic acid disodium salt (6059-S) and its related 1-oxacephems', $expanded->get('title'));
  }
  public function testZooKeys() {
      $text = '{{Cite journal|doi=10.3897/zookeys.445.7778}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('ZooKeys', $expanded->get('journal'));
      $this->assertEquals('445', $expanded->get('issue'));
      $this->assertNull($expanded->get('volume'));
  }
  public function testTitleItalics(){
      $text = '{{cite journal|doi=10.1111/pala.12168}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals("The macro- and microfossil record of the Cambrian priapulid ''Ottoia''", $expanded->get('title'));
  }
 
  public function testSpeciesCaps() {
    $text = '{{Cite journal | doi = 10.1007%2Fs001140100225}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals(str_replace(' ', '', "Crypticmammalianspecies:Anewspeciesofwhiskeredbat(''Myotisalcathoe''n.sp.)inEurope"), 
                        str_replace(' ', '', $expanded->get('title')));
    $text = '{{Cite journal | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1550-7408.2002.tb00224.x/full}}';
    // Should be able to drop /full from DOI in URL
    $expanded = $this->process_citation($text);
    $this->assertEquals(str_replace(' ', '', "''Cryptosporidiumhominis''n.sp.(Apicomplexa:Cryptosporidiidae)fromHomosapiens"),
                        str_replace(' ', '', $expanded->get('title'))); // Can't get Homo sapiens, can get nsp.
  }   
  
  public function testJstorSICI() {
       $text = '{{Cite journal|url=https://www.jstor.org/sici?sici=0003-0279(196101%2F03)81%3A1%3C43%3AWLIMP%3E2.0.CO%3B2-9}}';
       $expanded = $this->process_citation($text);
       $this->assertEquals('594900', $expanded->get('jstor'));
   }
    
   public function getDateAndYear($input){
       if (is_null($input->get('year'))) return $input->get('date') ; // Might be null too
       if (is_null($input->get('date'))) return $input->get('year') ;
       return 'Date is ' . $input->get('date') . ' and year is ' . $input->get('year') ;  // Return string that makes debugging easy and will throw error
   }
    
   public function testConvertJournalToBook() {
       $text = '{{Cite journal|doi=10.1007/978-3-540-74735-2_15}}';
       $expanded = $this->process_citation($text);
       $this->assertEquals('cite book', $expanded->wikiname());
   }
  /* TODO 
  Test adding a paper with > 4 editors; this should trigger displayeditors
  Test finding a DOI and using it to expand a paper [See testLongAuthorLists - Arxiv example?]
  Test adding a doi-is-broken modifier to a broken DOI.
  */    
}
