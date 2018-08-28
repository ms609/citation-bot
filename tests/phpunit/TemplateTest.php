<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

// Initialize bot configuration
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
final class TemplateTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  protected function requires_secrets($function) {
    if (getenv('TRAVIS_PULL_REQUEST')) {
      echo 'S'; // Skipping test: Risks exposing secret keys
      $this->assertNull(NULL); // Make Travis think we tested something
    } else {
      $function();
    }
  }
  
  protected function prepare_citation($text) {
    $template = new Template();
    $template->parse_text($text);
    $template->prepare();
    return $template;
  }
  
  protected function process_citation($text) {
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    $expanded_text = $page->parsed_text();
    $template = new Template();
    $template->parse_text($expanded_text);
    return $template;
  }
  
  protected function process_page($text) {  // Only used if more than just a citation template
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  protected function getDateAndYear($input){
    // Generates string that makes debugging easy and will throw error
    if (is_null($input->get('year'))) return $input->get('date') ; // Might be null too
    if (is_null($input->get('date'))) return $input->get('year') ;
    return 'Date is ' . $input->get('date') . ' and year is ' . $input->get('year');
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
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('cite web',          $prepared->wikiname());
    $this->assertEquals('http://google.com', $prepared->get('url'));
    $this->assertEquals('I am a title',      $prepared->get('title')); 
    $this->assertEquals('Other, A. N.',      $prepared->get('author'));
    $this->assertEquals('9'           ,      $prepared->get('issue'));
    $this->assertEquals('22'          ,      $prepared->get('volume'));
    $this->assertEquals('5–6'         ,      $prepared->get('pages'));
  }
  
  public function testJstorExpansion() {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $prepared->wikiname());
    $this->assertEquals('1701972'     , $prepared->get('jstor'));

    $text = "{{Cite web | url = http://www.jstor.org/stable/10.1017/s0022381613000030}}";
    $prepared = $this->prepare_citation($text);
    $this->assertNull($prepared->get('jstor'));
    
    $text = '{{cite web | via = UTF8 characters from JSTOR | url = https://www.jstor.org/stable/27695659}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Mórdha', $expanded->get('last1'));
    
  }
    
   public function testRISJstorExpansion() {
    $text = "{{Cite journal|jstor=3073767}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('Are Helionitronium Trications Stable?', $expanded->get('title'));
    $this->assertEquals('99', $expanded->get('volume'));
    $this->assertEquals('24', $expanded->get('issue'));
    $this->assertEquals('Francisco', $expanded->get('last2')); 
    $this->assertEquals('Eisfeld', $expanded->get('last1')); 
    $this->assertEquals('Proceedings of the National Academy of Sciences of the United States of America', $expanded->get('journal')); 
    $this->assertEquals('15303–15307', $expanded->get('pages'));
    // JSTOR gives up these, but we do not add since we get journal title and URL is simply jstor stable
    $this->assertNull($expanded->get('publisher'));
    $this->assertNull($expanded->get('issn'));
    $this->assertNull($expanded->get('url'));
  }
  
  public function testBrokenDoiUrlRetention() {
    $text = '{{cite journal|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1301|title=Israel, Occupied Territories|publisher=|doi=10.1093/law:epil/9780199231690/law-9780199231690-e1301|doi-broken-date=2018-07-07}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('doi-broken-date'));
    $this->assertNotNull($expanded->get('url'));
  }
  
  public function testPmidExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
    $expanded = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('1941451', $expanded->get('pmid'));
  }
    
  public function testPmidIsZero() {
      $text = '{{cite journal|pmc=2676591}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('pmid'));
  }
  
  public function testPMCExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
    $expanded = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('154623', $expanded->get('pmc'));
  }
  
  public function testPMC2PMID() {
    $text = '{{cite journal|pmc=58796}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('11573006', $expanded->get('pmid'));
  }
  
  public function testArxivExpansion() {    
    $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}"
          . "{{Cite arxiv | eprint = 0806.0013 | class=forgetit|publisher=uk.arxiv}}"
          . '{{Cite arxiv |eprint=1609.01689 | title = Accelerating Nuclear Configuration Interaction Calculations through a Preconditioned Block Iterative Eigensolver|class=cs.NA | year = 2016| last1 = Shao| first1 = Meiyue | display-authors = etal}}'
          . '{{cite arXiv|eprint=hep-th/0303241}}' // tests line feeds
          ;
    $expanded = $this->process_page($text);
    $templates = $expanded->extract_object('Template');
    
    $this->assertEquals('cite journal', $templates[0]->wikiname());
    $this->assertEquals('0806.0013', $templates[0]->get('arxiv'));
    
    $this->assertEquals('cite journal', $templates[1]->wikiname());
    $this->assertEquals('0806.0013', $templates[1]->get('arxiv'));
    $this->assertNull($templates[1]->get('class'));
    $this->assertNull($templates[1]->get('eprint'));
    $this->assertNull($templates[1]->get('publisher'));
      
    $this->assertEquals('2018', $templates[2]->get('year'));
  
    $this->assertEquals('Pascual Jordan, his contributions to quantum mechanics and his legacy in contemporary local quantum physics', $templates[3]->get('title'));
  
  }
  
  public function testAmazonExpansion() {
    $text = "{{Cite web | url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn=}}";
    $expanded = $this->prepare_citation($text);
    $this->assertEquals('cite book', $expanded->wikiname());
    $this->assertEquals('978-0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
      
    $text = "{{Cite web | url=https://www.amazon.com/Gold-Toe-Metropolitan-Dress-Three/dp/B0002TV0K8 | accessdate=2012-04-20}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());  // We do not touch this kind of URL
  }

  public function testRemoveASIN() {
    $text = "{{Cite book | asin=B0002TV0K8 |isbn=}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('B0002TV0K8', $expanded->get('asin'));
    $this->assertEquals('', $expanded->get('isbn'));
      
    $text = "{{Cite book | asin=0226845494 |isbn=0226845494}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('978-0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
  }
  
  public function testTemplateRenaming() {
    $text = "{{cite web|url=https://books.google.com/books?id=ecrwrKCRr7YC&pg=PA85&lpg=PA85&dq=vestibular+testing+lab+gianoli|title=Practical Management of the Dizzy Patient|first=Joel A.|last=Goebel|date=6 December 2017|publisher=Lippincott Williams & Wilkins|via=Google Books}}";
    // Should add ISBN and thus convert to Cite book
    $expanded = $this->process_citation($text);
    $this->assertEquals('9780781765626', $expanded->get('isbn'));
    $this->assertEquals('cite book', $expanded->wikiname());
  }
  
  public function testDoiExpansion() {
    $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $prepared->wikiname());
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $prepared->get('doi'));
  }

  public function testDoiExpansionBook() {
    $text = "{{cite book|doi=10.1007/978-981-10-3180-9_1}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite book', $expanded->wikiname());
    $this->assertEquals('978-981-10-3179-3', $expanded->get('isbn'));
  }
    
  public function testSeriesIsJournal() {
    $text = '{{citation | series = Annals of the New York Academy of Sciences| doi = 10.1111/j.1749-6632.1979.tb32775.x}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('journal')); // Doi returns exact same name for journal as series
  }
  
  public function testEmptyCoauthor() {
    $text = '{{Cite journal|pages=2| coauthor= |coauthors= }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('{{Cite journal|pages=2}}', $prepared->parsed_text());
  }

  public function testGarbageRemovalAndSpacing() {
    // Also tests handling of upper-case parameters
    $text = "{{Cite web | title=Ellipsis... | pages=10-11| Edition = 3rd ed. |journal=My Journal| issn=1234-4321 | publisher=Unwarranted |issue=0|accessdate=2013-01-01}}";
    $prepared = $this->prepare_citation($text);
    // ISSN should be retained when journal is originally present
    $this->assertEquals('{{Cite journal | title=Ellipsis… | pages=10–11| edition = 3rd |journal=My Journal| issn=1234-4321 }}', $prepared->parsed_text());
    
    $text = "{{Cite web | Journal=My Journal| issn=1357-4321 | publisher=Unwarranted }}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('{{Cite journal | journal=My Journal| issn=1357-4321 }}', $prepared->parsed_text());
  }
    
  public function testPublisherRemoval() {
    foreach (array('Google News Archive', '[[Google]]', 'Google News',
                   'Google.com', '[[Google News]]') as $publisher) {
      $text = "{{cite journal | publisher = $publisher}}";
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('publisher'));
    }
  }

  public function testRemoveWikilinks() {
    $expanded = $this->process_citation("{{Cite journal|journal=[[Pure Evil]]}}");
    $this->assertEquals('[[Pure Evil]]', $expanded->get('journal')); // leave fully linked journals
    $expanded = $this->process_citation("{{Cite journal|journal=[[Pure]] and [[Evil]]}}");
    $this->assertEquals('Pure and Evil', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|journal=Dark Lord of the Sith [[Pure Evil]]}}");
    $this->assertEquals('Dark Lord of the Sith Pure Evil', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil]]}}");
    $this->assertEquals('Pure Evil', $expanded->get('title'));
    $this->assertEquals('Pure Evil', $expanded->get('title-link'));
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil|Approximate Physics]]}}");
    $this->assertEquals('Approximate Physics', $expanded->get('title'));
    $this->assertEquals('Pure Evil', $expanded->get('title-link'));
    $expanded = $this->process_citation("{{Cite journal|title=[[Dark]] Lord of the [[Sith (Star Wars)|Sith]] [[Pure Evil]]}}");
    $this->assertEquals('Dark Lord of the Sith Pure Evil', $expanded->get('title'));
    $expanded = $this->process_citation("{{Cite journal|title=Dark Lord of the [[Sith (Star Wars)|Sith]] Pure Evil}}");
    $this->assertEquals('Dark Lord of the Sith Pure Evil', $expanded->get('title'));
    $this->assertEquals('Sith (Star Wars)', $expanded->get('title-link'));
  }
  
  public function testJournalCapitalization() {
    $expanded = $this->process_citation("{{Cite journal|pmid=9858585}}");
    $this->assertEquals('Molecular and Cellular Biology', $expanded->get('journal'));
  }
    
  public function testWebsiteAsJournal() {
    $text = '{{Cite journal | journal=www.foobar.com}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('www.foobar.com', $expanded->get('website'));
    $this->assertNull($expanded->get('journal'));
    $text = '{{Cite journal | journal=https://www.foobar.com}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('https://www.foobar.com', $expanded->get('url'));
    $this->assertNull($expanded->get('journal'));
    $text = '{{Cite journal | journal=[www.foobar.com]}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
  }
  
  public function testPageDuplication() {
     global $SLOW_MODE;
     $SLOW_MODE = FALSE; // Otherwise we'll find a bibcode
     $text = '{{cite journal| p=546 |doi=10.1103/PhysRev.57.546|title=Nuclear Fission of Separated Uranium Isotopes |journal=Physical Review |volume=57 |issue=6 |year=1940 |last1=Nier |first1=Alfred O. |last2=Booth |first2=E. T. |last3=Dunning |first3=J. R. |last4=Grosse |first4=A. V. }}';
     $expanded = $this->process_citation($text);
     $SLOW_MODE = TRUE;  // Reset it
     $this->assertEquals($text, $expanded->parsed_text());
   }

  public function testLastVersusAuthor() {
    $text = "{{cite journal|pmid=12858711}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('author1'));
    $this->assertEquals('Lovallo', $expanded->get('last1'));
  }
    
  public function testUnknownJournal() {
    $text = '{{cite journal }}';
    $expanded = $this->process_citation($text);
    $expanded->add_if_new('journal','Unknown');
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
    $this->assertNull($expanded->get('doi-broken-date'));
    
    $text = '{{cite journal|doi=10.3265/Nefrologia.NOTAREALDOI.broken|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('doi-broken-date'));
    
    $text = '{{cite journal|doi= <!-- MC Hammer says to not touch this -->}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi-broken-date'));
    $this->assertEquals('<!-- MC Hammer says to not touch this -->', $expanded->get('doi'));
      
    $text = '{{cite journal|doi= {{MC Hammer says to not touch this}} }}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi-broken-date'));
    // $this->assertEquals('{{MC Hammer says to not touch this}}', $expanded->get('doi')); This does not work right because we are not doing a "PAGE"
    $text = '{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}', $expanded->parsed_text());
  }

  public function testOpenAccessLookup() {
    $text = '{{cite journal|doi=10.1206/0003-0082(2008)3610[1:nrofwf]2.0.co;2}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.1.1.1001.5321', $expanded->get('citeseerx'));
    $this->assertEquals('2008', $expanded->get('year')); // DOI does work though
      
    $text = '{{cite journal | vauthors = Bjelakovic G, Nikolova D, Gluud LL, Simonetti RG, Gluud C | title = Antioxidant supplements for prevention of mortality in healthy participants and patients with various diseases | journal = The Cochrane Database of Systematic Reviews | volume = 3 | issue = 3 | pages = CD007176 | date = 14 March 2012 | pmid = 22419320 | doi = 10.1002/14651858.CD007176.pub2 }}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('url')); // currently gives a url 
      
    $text = '{{cite journal|doi=10.1136/bmj.327.7429.1459}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('300808', $expanded->get('pmc'));
    
    $text = '{{cite journal|doi=10.1038/nature08244}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('0904.1532', $expanded->get('arxiv'));
      
    $text = '{{cite journal | vauthors = Shekelle PG, Morton SC, Jungvig LK, Udani J, Spar M, Tu W, J Suttorp M, Coulter I, Newberry SJ, Hardy M | title = Effect of supplemental vitamin E for the prevention and treatment of cardiovascular disease | journal = Journal of General Internal Medicine | volume = 19 | issue = 4 | pages = 380–9 | date = April 2004 | pmid = 15061748 | pmc = 1492195 | doi = 10.1111/j.1525-1497.2004.30090.x }}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('url')); // Do not add PMC URL if already has PMC
      
    $text = '{{Cite journal | doi = 10.1063/1.4962420| title = Calculating vibrational spectra of molecules using tensor train decomposition| journal = J. Chem. Phys. | volume = 145| year = 2016| issue = 145| pages = 124101| last1 = Rakhuba| first1 = Maxim | last2 = Oseledets | first2 = Ivan| bibcode = 2016JChPh.145l4101R| arxiv =1605.08422}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('url')); // Do not add Arxiv URL if already has Arxiv

    $text = '{{Cite journal|url=bogus| author1 = Marius Junge | author2 = Carlos Palazuelos |title =  Large violation of Bell inequalities with low entanglement | journal = Communications in Mathematical Physics | volume = 306 | issue = 3 | pages = 695–746 |arxiv=1007.3043v2 | year = 2010| doi = 10.1007/s00220-011-1296-8 |bibcode = 2011CMaPh.306..695J}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.1.1.752.4896', $expanded->get('citeseerx')); // get it even with a url
    
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
  
  public function testDoi2PMID() {
    $text = "{{cite journal|doi=10.1073/pnas.171325998}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('11573006', $expanded->get('pmid'));
    $this->assertEquals('58796', $expanded->get('pmc'));
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
    $this->assertEquals('1–5', $expanded->get('pages'));
  }
       
  public function testId2Param() {
      $text = '{{cite book |id=ISBN 978-1234-9583-068, DOI 10.1234/bashifbjaksn.ch2, {{arxiv|1234.5678}} {{oclc|12354|4567}} {{oclc|1234}} {{ol|12345}} }}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('978-1234-9583-068', $expanded->get('isbn'));
      $this->assertEquals('1234.5678', $expanded->get('arxiv'));
      $this->assertEquals('10.1234/bashifbjaksn.ch2', $expanded->get('doi'));
      $this->assertEquals('1234', $expanded->get('oclc'));
      $this->assertEquals('12345', $expanded->get('ol'));
      $this->assertNotNull($expanded->get('doi-broken-date'));
      $this->assertEquals(0, preg_match('~' . sprintf(Template::PLACEHOLDER_TEXT, '\d+') . '~i', $expanded->get('id')));
      
      $text = '{{cite book | id={{arxiv|id=1234.5678}}}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('1234.5678', $expanded->get('arxiv'));
      
      $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} }}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('astr.ph/1234.5678', $expanded->get('arxiv'));     
      
      $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} {{arxiv|astr.ph|1234.5678}} }}'; // Two of the same thing
      $expanded = $this->process_citation($text);
      $this->assertEquals('astr.ph/1234.5678', $expanded->get('arxiv'));
      $this->assertEquals('{{cite book | arxiv=astr.ph/1234.5678 }}', $expanded->parsed_text());
      
      $text = '{{cite book|pages=1–2|id={{arxiv|astr.ph|1234.5678}}}}{{cite book|pages=1–3|id={{arxiv|astr.ph|1234.5678}}}}'; // Two of the same sub-template, but in different tempalates
      $expanded = $this->process_page($text);
      $this->assertEquals('{{cite book|pages=1–2|arxiv=astr.ph/1234.5678}}{{cite book|pages=1–3|arxiv=astr.ph/1234.5678}}', $expanded->parsed_text());
  }
  
  public function testNestedTemplates() {
      $text = '{{cite book|pages=1-2| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} | id={{cite book|pages=1-2| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} }}  }} |  cool stuff | not cool}}';
      $expanded = $this->process_citation($text);
      $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
      $this->assertEquals($text, $expanded->parsed_text());
      
      $text = '{{cite book|quote=See {{cite book|pages=1-2|quote=See {{cite book|pages=1-4}}}}|pages=1-3}}';
      $expanded = $this->process_citation($text);
      $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
      $this->assertEquals($text, $expanded->parsed_text());
  }
  
  public function testWorkParamter() {
      $text = '{{citation|work=RUBBISH|title=Rubbish|chapter=Dog}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{citation|title=Rubbish|chapter=Dog}}', $prepared->parsed_text());
      
      $text = '{{cite book|series=Keep Series, Lose Work|work=Keep Series, Lose Work}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{cite book|series=Keep Series, Lose Work}}', $prepared->parsed_text());
      
      $text = '{{cite journal|chapter=A book chapter|work=A book chapter}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{Cite book|chapter=A book chapter}}', $prepared->parsed_text());
      
      $text = '{{citation|work=I Live}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals($text, $prepared->parsed_text());
      
      $text = '{{not cite|work=xyz|chapter=xzy}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{not cite|work=xyz|chapter=xzy}}', $prepared->parsed_text());
      
      $text = '{{citation|work=xyz|journal=xyz}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{citation|journal=Xyz}}', $prepared->parsed_text());
      
      $text = '{{citation|work=|chapter=Keep work in Citation template}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{citation|work=|chapter=Keep work in Citation template}}', $prepared->parsed_text());
      
      $text = '{{cite journal|work=work should become journal}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{cite journal|journal=Work Should Become Journal}}', $prepared->parsed_text());
      
      $text = '{{cite magazine|work=abc}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{cite magazine|magazine=abc}}', $prepared->parsed_text());
      
      $text = '{{cite journal|work=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{cite journal|journal=}}', $prepared->parsed_text());
  }
  
  public function testOrigYearHandling() {
      $text = '{{cite book |year=2009 | origyear = 2000 }}';
      $prepared = $this->process_citation($text);
      $this->assertEquals('2000', $prepared->get('origyear'));
      $this->assertEquals('2009', $this->getDateAndYear($prepared));
      
      $text = '{{cite book | origyear = 2000 }}';
      $prepared = $this->process_citation($text);
      $this->assertEquals('2000', $this->getDateAndYear($prepared));
      $this->assertNull($prepared->get('origyear'));
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
    $this->assertNull($expanded->get('pages')); // Do not expand pages.  Google might give total pages to us
  }
  
  public function testGoogleDates() {
    $text = "{{cite book|url=https://books.google.com/books?id=yN8DAAAAMBAJ&pg=PA253}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('February 1935', $expanded->get('date'));
  }
  
  public function testErrantAuthor() {
    $text = '{{cite journal|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=onepage&q&f=true |title=The Passing of the Carrier Pigeon|journal=Popular Mechanics |date=February 1930|pages= 340}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Hearst Magazines', $expanded->get('publisher'));
  }
  
  public function testLongAuthorLists() {
    $text = '{{cite web | https://arxiv.org/PS_cache/arxiv/pdf/1003/1003.3124v2.pdf}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('The ATLAS Collaboration', $expanded->first_author());
    $this->assertNull($expanded->get('class'));
    
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
    $this->assertEquals('1999', $this->getDateAndYear($expanded));
  }
  
 public function testISODates() {
    $text = '{{cite book |author=Me |title=Title |year=2007-08-01 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('2007-08-01', $prepared->get('date'));
    $this->assertNull($prepared->get('year'));
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
     $prepared = $this->prepare_citation($text);
     $this->assertEquals('A Mathematical Theory of Communication', $prepared->get('title'));
     $this->assertEquals('1948-07', $prepared->get('date'));
     $this->assertEquals('Bell System Technical Journal', $prepared->get('journal'));
     $this->assertEquals('Shannon, Claude E', $prepared->first_author());
     $this->assertEquals('Shannon', $prepared->get('last1'));
     $this->assertEquals('Claude E', $prepared->get('first1'));
     $this->assertEquals('379–423', $prepared->get('pages'));
     $this->assertEquals('27', $prepared->get('volume'));   
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
       $prepared = $this->prepare_citation($book);
       $this->assertEquals('Chaucer, Geoffrey', $prepared->first_author());
       $this->assertEquals('The Works of Geoffrey Chaucer', $prepared->get('title'));
       $this->assertEquals('1957', $this->getDateAndYear($prepared));
       $this->assertEquals('Houghton', $prepared->get('publisher'));
       $this->assertEquals('Boston', $prepared->get('location'));
       
       $prepared = $this->process_citation($article);
       $this->assertEquals('Clark, Herbert H', $prepared->first_author());
       $this->assertEquals('1982', $this->getDateAndYear($prepared));
       $this->assertEquals('Hearers and Speech Acts', $prepared->get('title'));
       $this->assertEquals('58', $prepared->get('volume'));
       $this->assertEquals('332–373', $prepared->get('pages'));
       
       
       $prepared = $this->process_citation($thesis);
       $this->assertEquals('Cantucci, Elena', $prepared->first_author());
       $this->assertEquals('Permian strata in South-East Asia', $prepared->get('title'));
       $this->assertEquals('1990', $this->getDateAndYear($prepared));
       $this->assertEquals('University of California, Berkeley', $prepared->get('publisher'));
       $this->assertEquals('10.1038/ntheses.01928', $prepared->get('doi'));  
  }
   
  public function testConvertingISBN10intoISBN13() { // URLS present just to speed up tests
    $text = "{{cite book|isbn=0-9749009-0-7|url=https://books.google.com/books?id=to0yXzq_EkQC}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('978-0-9749009-0-2', $prepared->get('isbn'));  // Convert with dashes
    
    $text = "{{cite book|isbn=978-0-9749009-0-2|url=https://books.google.com/books?id=to0yXzq_EkQC}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('978-0-9749009-0-2', $prepared->get('isbn'));  // Unchanged with dashes
    
    $text = "{{cite book|isbn=9780974900902|url=https://books.google.com/books?id=to0yXzq_EkQC}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('9780974900902', $prepared->get('isbn'));   // Unchanged without dashes
    
    $text = "{{cite book|isbn=0974900907|url=https://books.google.com/books?id=to0yXzq_EkQC}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('978-0974900902', $prepared->get('isbn'));   // Convert without dashes
    
    $text = "{{cite book|isbn=1-84309-164-X|url=https://books.google.com/books?id=GvjwAQAACAAJ}}";
    $prepared = $this->prepare_citation($text);  
    $this->assertEquals('978-1-84309-164-6', $prepared->get('isbn'));  // Convert with dashes and a big X
    
    $text = "{{cite book|isbn=184309164x|url=https://books.google.com/books?id=GvjwAQAACAAJ}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('978-1843091646', $prepared->get('isbn'));  // Convert without dashes and a tiny x
    
    $text = "{{cite book|isbn=Hello Brother}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('Hello Brother', $prepared->get('isbn')); // Rubbish unchanged
  }
   
  public function testEtAl() {
    $text = '{{cite book |auths=Alfred A Albertstein, Bertie B Benchmark, Charlie C. Chapman et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('Albertstein, Alfred A', $prepared->first_author());
    $this->assertEquals('Charlie C', $prepared->get('first3'));
    $this->assertEquals('etal', $prepared->get('displayauthors'));
  }
       
  public function testWebsite2Url() {
      $text = '{{cite book |website=ttp://example.org }}';
      $prepared = $this->prepare_citation($text);
      $this->assertEquals('http://example.org', $prepared->get('url'));
      
      $text = '{{cite book |website=example.org }}';
      $prepared = $this->prepare_citation($text);
      $this->assertEquals('http://example.org', $prepared->get('url'));
      
      $text = '{{cite book |website=ttp://jstor.org/pdf/123456 | jstor=123456 }}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      
      $text = '{{cite book |website=ABC}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      $this->assertEquals('ABC', $prepared->get('website'));
      
      $text = '{{cite book |website=ABC XYZ}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      $this->assertEquals('ABC XYZ', $prepared->get('website'));
      
      $text = '{{cite book |website=http://ABC/ I have Spaces in Me}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      $this->assertEquals('http://ABC/ I have Spaces in Me', $prepared->get('website'));
  }
  
  public function testHearst () {
    $text = '{{cite book|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=onepage&q&f=true}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Hearst Magazines', $expanded->get('publisher'));
    $this->assertNull($expanded->get('last1'));
    $this->assertNull($expanded->get('last'));
    $this->assertNull($expanded->get('author'));
    $this->assertNull($expanded->get('author1'));
    $this->assertNull($expanded->get('authors'));
  }
       
  public function testInternalCaps() { // checks for title formating in tidy() not breaking things
    $text = '{{cite journal|journal=ZooTimeKids}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('ZooTimeKids', $prepared->get('journal'));
  }
  
  public function testCapsAfterColonAndPeriodJournalTidy() {
    $text = '{{Cite journal |journal=In Journal Titles: a word following punctuation needs capitals. Of course.}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('In Journal Titles: A Word Following Punctuation Needs Capitals. Of Course.', 
                        $prepared->get('journal'));
  }      

  public function testExistingWikiText() { // checks for formating in tidy() not breaking things
    $text = '{{cite journal|title=[[Zootimeboys]] and Girls|journal=[[Zootimeboys]] and Girls}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('Zootimeboys and Girls', $prepared->get('journal'));
    $this->assertEquals('Zootimeboys and Girls', $prepared->get('title'));
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
    $text = '{{Cite journal|doi=10.3897/zookeys.445.7778|journal=[[Zookeys]]}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('[[Zookeys]]', $expanded->get('journal'));  // This is wrong capitalization, but because of [[ ]], we leave alone, not wanting to break links
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
   $url = "https://www.jstor.org/sici?sici=0003-0279(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
   $text = "{{Cite journal|url=$url}}";
   $expanded = $this->process_citation($text);
     
   $this->assertEquals('594900', $expanded->get('jstor'));
   $this->assertEquals('1961', $expanded->get('year'));
   $this->assertEquals('81', $expanded->get('volume'));
   $this->assertEquals('1', $expanded->get('issue'));
   $this->assertEquals('43', $expanded->get('pages'));
  }
      
  public function testJstorSICIEncoded() {
    $text = '{{Cite journal|url=https://www.jstor.org/sici?sici=0003-0279(196101%2F03)81%3A1%3C43%3AWLIMP%3E2.0.CO%3B2-9}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('594900', $expanded->get('jstor'));
  }
    
  public function testOverwriteBlanks() {
    $text = '{{cite journal|url=http://www.jstor.org/stable/1234567890|jstor=}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('{{cite journal|jstor=1234567890}}', $expanded->parsed_text());
  }

  public function testIgnoreJstorPlants() {
    $text='{{Cite journal| url=http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972 |title=Holotype of Persoonia terminalis L.A.S.Johnson & P.H.Weston [family PROTEACEAE]}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972', $expanded->get('url'));
    $this->assertNull($expanded->get('jstor'));
  }
  
  public function testBibcodeDotEnding() {
    $this->requires_secrets(function() {
      $text='{{cite journal|title=Electric Equipment of the Dolomites Railway|journal=Nature|date=2 January 1932|volume=129|issue=3244|page=18|doi=10.1038/129018a0}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('1932Natur.129Q..18.', $expanded->get('bibcode'));
    });
  }

  public function testConvertJournalToBook() {
    $text = '{{Cite journal|doi=10.1007/978-3-540-74735-2_15}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite book', $expanded->wikiname());
  }

  public function testRenameToJournal() {
    $text = "{{cite arxiv | bibcode = 2013natur1305.7450M}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $prepared->wikiname());
    $text = "{{cite arxiv | bibcode = 2013arXiv1305.7450M}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('cite arxiv', $prepared->wikiname());
    $text = "{{cite arxiv | bibcode = 2013physics305.7450M}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('cite arxiv', $prepared->wikiname());    
  }
  
  public function testPagesDash() {
    $text = '{{cite journal|pages=1-2|title=do change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('1–2', $prepared->get('pages'));
    $text = '{{cite journal|at=1-2|title=do not change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('1-2', $prepared->get('at'));
    $text = '{{cite journal|pages=[http://bogus.bogus/1–2/ 1–2]|title=do not change }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('[http://bogus.bogus/1–2/ 1–2]', $prepared->get('pages'));
  }
    
  public function testCollapseRanges() {
    $text = '{{cite journal|pages=1233-1233|year=1999-1999}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('1233', $prepared->get('pages'));
    $this->assertEquals('1999', $prepared->get('year'));
  }
    
  public function testSmallWords() {
    $text = '{{cite journal|journal=A Word in ny and n y About cow And Then boys the U S A and y and z}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('A Word in NY and N Y About Cow and then Boys the U S A and y and Z', $prepared->get('journal')); 
    $text = '{{cite journal|journal=Ann of Math}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('Ann of Math', $prepared->get('journal')); 
    $text = '{{cite journal|journal=Ann. of Math.}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('Ann. of Math.', $prepared->get('journal')); 
    $text = '{{cite journal|journal=Ann. of Math}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('Ann. of Math', $prepared->get('journal')); 
  }
    
  public function testDoNotAddYearIfDate() {
    $text = '{{cite journal|date=2002|doi=10.1635/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('year'));
  }
                         
  public function testAccessDates() {
    $text = '{{cite book |last1=Tanimoto |first1=Toshiro |editor=Thomas J. Ahrens |date=1995 |chapter=Crustal Structure of the Earth |title=Global Earth Physics: A Handbook of Physical Constants |chapter-url=http://www.agu.org/reference/gephys/15_tanimoto.pdf |accessdate=16 October 2006}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('accessdate'));
  }

  public function testIgnoreUnkownCiteTemplates() {
    $text = "{{Cite imaginary source | http://google.com | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6|doi=10.bad/bad }}";
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
  }
  
  public function testJustAnISBN() {
     $text = '{{cite book |isbn=0471186368}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('Explosives engineering', $expanded->get('title'));
     $this->assertNull($expanded->get('url'));
  }
    
  public function testJustAnOCLC() {
    $this->requires_secrets(function() {
      $text = '{{cite book | oclc=9334453}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('The Shreveport Plan: A Long-range Guide for the Future Development of Metropolitan Shreveport', $expanded->get('title'));
    });
  }

  public function testJustAnLCCN() {
    $this->requires_secrets(function() {
      $text = '{{cite book | lccn=2009925036}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('Alternative Energy for Dummies', $expanded->get('title'));
    });
  }
    
  public function testEmptyCitations() {
    $text = 'bad things like {{cite journal}}{{cite book|||}} should not crash bot'; // bot removed pipes
    $expanded = $this->process_page($text);
    $this->assertEquals('bad things like {{cite journal}}{{cite book}} should not crash bot', $expanded->parsed_text());
  }
 
  public function testBadBibcodeARXIVPages() { // Some bibcodes have pages set to arXiv:1711.02260
    $text = '{{cite journal|bibcode=2017arXiv171102260L}}';
    $expanded = $this->process_citation($text);
    $pages = $expanded->get('pages');
    $volume = $expanded->get('volume');
    $this->assertEquals(FALSE, stripos($pages, 'arxiv'));
    $this->assertEquals(FALSE, stripos('1711', $volume));
    $this->assertNull($expanded->get('journal'));  // if we get a journal, the data is updated and test probably no longer gets bad data
  }
    
  public function testCitationTemplateWithoutJournal() {
    $text = '{{citation|url=http://www.word-detective.com/2011/03/mexican-standoff/|title=Mexican standoff|work=The Word Detective|accessdate=2013-03-21}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('isbn')); // This citation used to crash code in ISBN search.  Mostly checking "something" to make Travis CI happy
  }

  public function testLatexMathInTitle() { // This contains Math stuff that should be z~10, but we just verify that we do not make it worse at this time.  See https://tex.stackexchange.com/questions/55701/how-do-i-write-sim-approximately-with-the-correct-spacing
    $text = "{{Cite arxiv|eprint=1801.03103}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('A Candidate $z\sim10$ Galaxy Strongly Lensed into a Spatially Resolved Arc', $expanded->get('title'));
  }


  public function testHornorificInTitle() { // compaints about this
    $text = "{{cite book|title=Letter from Sir Frederick Trench to the Viscount Duncannon on his proposal for a quay on the north bank of the Thames|url=https://books.google.com/books?id=oNBbAAAAQAAJ|year=1841}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('Trench', $expanded->get('last1'));
    $this->assertEquals('Frederick William', $expanded->get('first1')); 
  }

  public function testPageRange() {
    $text = '{{Citation|doi=10.3406/befeo.1954.5607}}' ;
    $expanded = $this->process_citation($text);
    $this->assertEquals('405–554', $expanded->get('pages'));
  }
    
  public function testFindHDL() {
    $text = '{{cite journal | pmid = 14527634 | doi = 10.1016/S1095-6433(02)00368-9 }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('10397/34754', $expanded->get('hdl'));
  }

  public function testUrlConversions() {
    $text = '{{cite journal | url= https://mathscinet.ams.org/mathscinet-getitem?mr=0012343 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('0012343', $prepared->get('mr'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234231}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('1234231', $prepared->get('ssrn'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url=https://www.osti.gov/biblio/2341}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('2341', $prepared->get('osti'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url=https://www.osti.gov/energycitations/product.biblio.jsp?osti_id=2341}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('2341', $prepared->get('osti'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url=https://zbmath.org/?format=complete&q=an:1111.22222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('1111.22222', $prepared->get('zbl'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url=https://zbmath.org/?format=complete&q=an:11.2222.44}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('11.2222.44', $prepared->get('jfm'));
    $this->assertNull($prepared->get('url'));
  }
    
  public function testStripPDF() {
    $text = '{{cite journal |url=https://link.springer.com/content/pdf/10.1007/BF00428580.pdf}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('10.1007/BF00428580', $prepared->get('doi'));
  }
  
  public function testRemovePublisherWithWork() {
    $text = '{{cite journal|jstor=1148172|title=Strategic Acupuncture|work=Foreign Policy|issue=Winter 1980|pages=44–61|publisher=Washingtonpost.Newsweek Interactive, LLC|year=1980}}';
    $expanded = $this->prepare_citation($text);
    $this->assertNull($expanded->get('publisher'));  
  }
    
  public function testRemoveQuotes() {
    $text = '{{cite journal|title="Strategic Acupuncture"}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('Strategic Acupuncture', $prepared->get('title'));  
  }
  
  public function testTrimResearchGateETC() {
    $want = 'https://www.researchgate.net/publication/320041870';
    $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals($want, $prepared->get('url'));
    $text = '{{cite journal|url=https://www.researchgate.net/profile/hello_user-person/publication/320041870_EXTRA_STUFF_ON_EN}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals($want, $prepared->get('url'));

    $text = '{{cite web|url=http://acADemia.EDU/123456/extra_stuff}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('https://www.academia.edu/123456', $prepared->get('url')); 
  }
 
  public function testDoiValidation() {
    $text = '{{cite web|last=Daintith|first=John|title=tar|url=http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022|work=Oxford University Press|publisher=A dictionary of chemistry|edition=6th|accessdate=14 March 2013}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('10.1093/acref/9780199204632.001.0001', $prepared->get('doi'));
  }
  /* TODO 
  Test adding a paper with > 4 editors; this should trigger displayeditors
  Test finding a DOI and using it to expand a paper [See testLongAuthorLists - Arxiv example?]
  */    
}
