<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
  public function testLotsOfFloaters() {
    $text_in = "{{cite journal|issue 3 volume 5 | title Love|journal Dog|series Not mine today|chapter cows|this is random stuff | 123-4567-890 }}";
    $text_out= "{{cite book|this is random stuff | issue = 3| volume = 5| title = Love| journal = Dog| series = Not mine today| chapter = Cows| isbn = 123-4567-890}}";
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_out, $prepared->parsed_text());
  }
  
  public function testLotsOfFloaters2() {
    $text_in = "{{cite journal|isssue 3 volumee 5 | tittle Love|journall Dog|series Not mine today|chapte cows|this is random stuff | zauthor Joe }}";
    $text_out= "{{cite journal|isssue 3 volumee 5 | tittle Love|chapte cows|this is random stuff | zauthor Joe | journal = L Dog| series = Not mine today}}";
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_out, $prepared->parsed_text());
  }
 
  public function testLotsOfFloaters3() {
    $text_in = "{{cite journal| 123-4567-890-123 }}";
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame('123-4567-890-123', $prepared->get('isbn'));
  }
 
  public function testLotsOfFloaters4() {
    $text_in = "{{cite journal| 123-4567-8901123 }}"; // 13 numbers
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_in, $prepared->parsed_text());
  }
 
  public function testLotsOfFloaters5() {
    $text_in = "{{cite journal| 12345678901 }}"; // 11 numbers
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_in, $prepared->parsed_text());
  }

  public function testParameterWithNoParameters() {
    $text = "{{Cite web | text without equals sign  }}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
    $text = "{{  No pipe  }}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }

  public function testNoGoonUTF8() {
    $text = "{{cite news |date=びっくり１位　白鴎|title=阪神びっくり１位　白鴎大・大山、鉄人魂の持ち主だ|journal=鉄人魂}}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
 
  public function testTitleOfNone() {
    $text = "{{Cite web|title=none}}";// none is magic flag
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('url', 'https://www.apple.com/'));
    $this->assertNull($expanded->get('url'));             

    $text = "{{Cite web|title=None}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('url', 'https://www.apple.com/'));
    $this->assertSame('https://www.apple.com/', $expanded->get('url'));  
  }
 
  public function testJournal2Web() {
    $text = "{{Cite journal|journal=www.cnn.com}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('www.cnn.com', $expanded->get('website'));  
  }

  public function testCleanUpTemplates() {
    $text = "{{Citeweb}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Cite web}}", $expanded->parsed_text());
    $text = "{{citeweb}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{cite web}}", $expanded->parsed_text());
    $text = "{{cite}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{citation}}", $expanded->parsed_text());
    $text = "{{Cite}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Citation}}", $expanded->parsed_text());
   
    $text = "{{Citeweb|page=2}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Cite web|page=2}}", $expanded->parsed_text());
    $text = "{{citeweb|page=2}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{cite web|page=2}}", $expanded->parsed_text());
    $text = "{{cite|page=2}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{citation|page=2}}", $expanded->parsed_text());
    $text = "{{Cite|page=2}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Citation|page=2}}", $expanded->parsed_text());
   
    $text = "{{Citeweb |page=2}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Cite web |page=2}}", $expanded->parsed_text());
    $text = "{{citeweb |page=2}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{cite web |page=2}}", $expanded->parsed_text());
    $text = "{{cite |page=2}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{citation |page=2}}", $expanded->parsed_text());
    $text = "{{Cite |page=2}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Citation |page=2}}", $expanded->parsed_text());
  }

  public function testUseUnusedData() {
    $text = "{{Cite web | http://google.com | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6 }}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite web',          $prepared->wikiname());
    $this->assertSame('http://google.com', $prepared->get('url'));
    $this->assertSame('I am a title',      $prepared->get('title')); 
    $this->assertSame('Other, A. N.',      $prepared->get('author'));
    $this->assertSame('9'           ,      $prepared->get('issue'));
    $this->assertSame('22'          ,      $prepared->get('volume'));
    $this->assertSame('5–6'         ,      $prepared->get('pages'));
  }
 
  public function testGetDoiFromCrossref() {
     $text = '{{Cite journal | last1 = Glaesemann | first1 = K. R. | last2 = Fried | first2 = L. E. | doi = | title = Improved wood–kirkwood detonation chemical kinetics | journal = Theoretical Chemistry Accounts | volume = 120 | pages = 37–43 | year = 2007 |issue=1–3}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('10.1007/s00214-007-0303-9', $expanded->get('doi'));
     $this->assertNull($expanded->get('pmid'));  // do not want reference where pmid leads to doi
     $this->assertNull($expanded->get('bibcode'));
     $this->assertNull($expanded->get('pmc'));
  }
 
  public function testJstorExpansion() {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true|website=i found this online}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite journal', $prepared->wikiname());
    $this->assertSame('1701972'     , $prepared->get('jstor'));
    $this->assertNull($prepared->get('website'));

    $text = "{{Cite journal | url=http://www.jstor.org/stable/10.2307/40237667|jstor=}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('40237667', $prepared->get('jstor'));
    $this->assertNull($prepared->get('doi'));
    $this->assertSame(1, substr_count($prepared->parsed_text(), 'jstor'));  // Verify that we do not have both jstor= and jstor=40237667.  Formerly testOverwriteBlanks()

    $text = "{{Cite web | url = http://www.jstor.org/stable/10.1017/s0022381613000030}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('10.1017/s0022381613000030', $prepared->get('jstor'));
    
    $text = '{{cite web | via = UTF8 characters from JSTOR | url = https://www.jstor.org/stable/27695659}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Mórdha', $expanded->get('last1'));
   
    $text = '{{cite journal | url = https://www-jstor-org.school.edu/stable/10.7249/mg1078a.10?seq=1#metadata_info_tab_contents }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.7249/mg1078a.10', $expanded->get('jstor'));
   
  }
 
  public function testDrop10_2307() {
    $text = "{{Cite journal | jstor=10.2307/40237667}}";  // This should get cleaned up in tidy
    $prepared = $this->prepare_citation($text);
    $this->assertSame('40237667', $prepared->get('jstor'));
  }
    
   public function testRISJstorExpansion() {
    $text = "<ref name='jstor'>{{jstor|3073767}}</ref>"; // Check Page expansion too
    $page = $this->process_page($text);
    $expanded = $this->reference_to_template($page->parsed_text());
    $this->assertSame('Are Helionitronium Trications Stable?', $expanded->get('title'));
    $this->assertSame('99', $expanded->get('volume'));
    $this->assertSame('24', $expanded->get('issue'));
    $this->assertSame('Francisco', $expanded->get('last2')); 
    $this->assertSame('Eisfeld', $expanded->get('last1')); 
    $this->assertSame('Proceedings of the National Academy of Sciences of the United States of America', $expanded->get('journal')); 
    $this->assertSame('15303–15307', $expanded->get('pages'));
    // JSTOR gives up these, but we do not add since we get journal title and URL is simply jstor stable
    $this->assertNull($expanded->get('publisher'));
    $this->assertNull($expanded->get('issn'));
    $this->assertNull($expanded->get('url'));
  }
  
  public function testBrokenDoiUrlRetention1() {
    $text = '{{cite journal|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1301|title=Israel, Occupied Territories|publisher=|doi=10.1093/law:epil/9780199231690/law-9780199231690-e1301|doi-broken-date=2018-07-07}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('doi-broken-date'));
    $this->assertNotNull($expanded->get('url'));
  }
 
   public function testBrokenDoiUrlRetention2() {
    // Newer code does not even add it
    $text = '{{cite journal|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1301}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi'));
    $this->assertNotNull($expanded->get('url'));
  }
 
   public function testBrokenDoiUrlRetention3() {
    // valid 10.1098 DOI in contrast to evil ones
    $text = '{{cite journal|url=https://academic.oup.com/zoolinnean/advance-article-abstract/doi/10.1093/zoolinnean/zly047/5049994}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1093/zoolinnean/zly047', $expanded->get('doi'));
    $this->assertNull($expanded->get('url'));
  }
 
   public function testBrokenDoiUrlRetention4() {
    // This is an ISSN only doi: it is valid, but leave url too
    $text = '{{cite journal|url=http://onlinelibrary.wiley.com/journal/10.1111/(ISSN)1601-183X/issues }}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('doi'));
    $this->assertNotNull($expanded->get('url'));
  }
 
 public function testCrazyDoubleDOI() {
    $doi = '10.1126/science.10.1126/SCIENCE.291.5501.24';
    $text = '{{cite journal|doi=' . $doi . '}}';
    $expanded = $this->process_citation($text);
    $this->assertSame($doi, $expanded->get('doi'));
 }

 public function testBrokenDoiUrlChanges1() {
     $text = '{{cite journal|url=http://dx.doi.org/10.1111/j.1471-0528.1995.tb09132.x|doi=10.00/broken_and_invalid|doi-broken-date=12-31-1999}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $expanded->get('doi'));
     $this->assertNull($expanded->get('url'));
 }
 
  public function testBrokenDoiUrlChanges2() {
    // The following URL is "broken" since it is not escaped properly.  The cite template displays and links it wrong too.
     $text = '{{cite journal|doi=10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2|url=https://dx.doi.org/10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }
   
  public function testBrokenDoiUrlChanges3() {
     $text = '{{cite journal|url=http://doi.org/10.14928/amstec.23.1_1|doi=10.14928/amstec.23.1_1}}';  // This also troublesome DOI
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }
  
  public function testPmidExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('1941451', $expanded->get('pmid'));
  }
   
  public function testPoundDOI() {
    $text = "{{cite book |url=https://link.springer.com/chapter/10.1007%2F978-3-642-75924-6_15#page-1}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1007/978-3-642-75924-6_15', $expanded->get('doi'));
  }
 
  public function testPlusDOI() {
    $doi = "10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U#page_scan_tab_contents=342342"; // Also check #page_scan_tab_contents stuff too
    $text = "{{cite journal|doi = $doi }}";
    $expanded = $this->process_citation($text);
    $this->assertSame("10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U", $expanded->get('doi'));
  }
 
  public function testNewsdDOI() {
    $text = "{{cite news|url=http://doi.org/10.1021/cen-v076n048.p024;jsessionid=222}}"; // Also check jsesssion removal
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1021/cen-v076n048.p024', $expanded->get('doi'));
  }
 
  public function testChangeNothing1() {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x|pages=<!-- -->|title=<!-- -->|journal=<!-- -->|volume=<!-- -->|issue=<!-- -->|year=<!-- -->|authors=<!-- -->|pmid=<!-- -->|url=<!-- -->}}';
     $expanded = $this->process_page($text);
     $this->assertSame($text, $expanded->parsed_text());
  }
    
  public function testChangeNothing2() {
     $text = '{{cite journal | doi=10.000/broken_real_bad_and_tests_know_it | doi-broken-date = <!-- not broken and the bot is wrong --> }}';
     $expanded = $this->process_page($text);
     $this->assertSame($text, $expanded->parsed_text());
  }
    
  public function testChangeNothing3() {
     $text = '{{cite journal |title=The tumbling rotational state of 1I/‘Oumuamua<!-- do not change odd punctuation--> |journal=Nature title without caps <!-- Deny Citation Bot-->  |pages=383-386 <!-- do not change the dash--> }}';
     $expanded = $this->process_page($text);
     $this->assertSame($text, $expanded->parsed_text());
  }
  
  public function testNoLoseUrl() {
     $text = '{{cite book |last=Söderström |first=Ulrika |date=2015 |title=Sandby Borg: Unveiling the Sandby Borg Massacre |url= |location= |publisher=Kalmar lāns museum |isbn=9789198236620 |language=Swedish }}';
     $expanded = $this->process_page($text);
     $this->assertSame($text, $expanded->parsed_text());
  }
 
  public function testDotsAndVia() {
     $text = '{{cite journal|pmid=4957203|via=Pubmed}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('M. M.', $expanded->get('first3'));
     $this->assertNull($expanded->get('via'));
  }
 
  public function testLoseViaDup() {
     $text = '{{citation|work=Some Journal|via=Some Journal}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('via'));
     $text = '{{citation|publisher=Some Journal|via=Some Journal}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('via'));
     $text = '{{citation|newspaper=Some Journal|via=Some Journal}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('via'));
  }
    
  public function testJustBrackets() {
     $text = '{{cite book|title=[[W|12px|alt=W]]}}';
     $expanded = $this->process_citation($text);
     $this->assertSame($text, $expanded->parsed_text());
     $text = '{{cite book|title=[[File:Example.png|thumb|upright|alt=Example alt text|Example caption]]}}';
     $expanded = $this->process_citation($text);
     $this->assertSame($text, $expanded->parsed_text());
  }

  public function testBadAuthor2() {
      $text = '{{cite journal|title=Guidelines for the management of adults with hospital-acquired, ventilator-associated, and healthcare-associated pneumonia |journal=Am. J. Respir. Crit. Care Med. |volume=171 |issue=4 |pages=388–416 |year=2005 |pmid=15699079 |doi=10.1164/rccm.200405-644ST}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('American Thoracic Society', $expanded->get('author1'));
  }
 
  public function testPmidIsZero() {
      $text = '{{cite journal|pmc=2676591}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('pmid'));
  }
  
  public function testPMCExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('154623', $expanded->get('pmc'));
    $this->assertNull($expanded->get('url'));
    $text = "{{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('cite document', $expanded->wikiname());
    $this->assertSame('https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf', $expanded->get('url'));
    $this->assertSame('2491514', $expanded->get('pmc'));
  }
  
  public function testPMC2PMID() {
    $text = '{{cite journal|pmc=58796}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('11573006', $expanded->get('pmid'));
  }
  
  public function testArxivExpansion() {
    $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}"
          . "{{Cite arxiv | eprint = 0806.0013 | class=forgetit|publisher=uk.arxiv}}"
          . '{{Cite arxiv |eprint=1609.01689 | title = Accelerating Nuclear Configuration Interaction Calculations through a Preconditioned Block Iterative Eigensolver|class=cs.NA | year = 2016| last1 = Shao| first1 = Meiyue | display-authors = etal}}'
          . '{{cite arXiv|eprint=hep-th/0303241}}' // tests line feeds
          ;
    $expanded = $this->process_page($text);
    $templates = $expanded->extract_object('Template');
    
    $this->assertSame('cite journal', $templates[0]->wikiname());
    $this->assertSame('0806.0013', $templates[0]->get('arxiv'));
    
    $this->assertSame('cite journal', $templates[1]->wikiname());
    $this->assertSame('0806.0013', $templates[1]->get('arxiv'));
    $this->assertNull($templates[1]->get('class'));
    $this->assertNull($templates[1]->get('eprint'));
    $this->assertNull($templates[1]->get('publisher'));
      
    $this->assertSame('2018', $templates[2]->get('year'));
  
    $this->assertSame('Pascual Jordan, his contributions to quantum mechanics and his legacy in contemporary local quantum physics', $templates[3]->get('title'));
  
  }
  
  public function testAmazonExpansion() {
    $text = "{{Cite web | url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
    $this->assertNull($expanded->get('publisher'));
    $this->assertNull($expanded->get('url'));

    $text = "{{Cite web | chapter-url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
    $this->assertNull($expanded->get('publisher'));
    $this->assertNull($expanded->get('chapter-url'));

    $text = "{{Cite web | url=https://www.amazon.com/Gold-Toe-Metropolitan-Dress-Three/dp/B0002TV0K8 | accessdate=2012-04-20 | title=Gold Toe Men's Metropolitan Dress Sock (Pack of Three Pairs) at Amazon Men's Clothing store}}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());  // We do not touch this kind of URL
   
    $text = "{{Cite web | chapter-url=http://www.amazon.eu/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
    $this->assertNull($expanded->get('publisher'));
    $this->assertNull($expanded->get('chapter-url'));
    $this->assertSame('{{ASIN|0226845494|country=eu}}', $expanded->get('id'));
   
    $text = "{{Cite book | chapter-url=http://www.amazon.eu/On-Origin-Phyla-James-Valentine/dp/0226845494 |isbn=exists}}";
    $expanded = $this->prepare_citation($text);;
    $this->assertNull($expanded->get('asin'));
    $this->assertNull($expanded->get('chapter-url'));
    $this->assertSame('exists', $expanded->get('isbn'));
  }

  public function testRemoveASIN() {
    $text = "{{Cite book | asin=B0002TV0K8 |isbn=}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('B0002TV0K8', $expanded->get('asin'));
    $this->assertSame('', $expanded->get('isbn')); // Empty, not non-existent
      
    $text = "{{Cite book | asin=0226845494 |isbn=0226845494}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
  }
 
  public function testAddASIN() {
    $text = "{{Cite book |isbn=0226845494}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('asin', 'X'));
    $this->assertSame('0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
                       
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', '630000000')); //63.... code
    $this->assertSame('630000000', $expanded->get('asin'));
   
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', 'BNXXXXXXXX')); // Not an ISBN at all
    $this->assertSame('BNXXXXXXXX', $expanded->get('asin'));

    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', '0781765625'));
    $this->assertSame('0781765625', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
   
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', 'ABC'));
    $this->assertSame('ABC', $expanded->get('asin'));
    $this->assertNull($expanded->get('isbn'));
   
    $text = "{{Cite book|asin=xxxxxx}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('asin', 'ABC'));
    $this->assertSame('xxxxxx', $expanded->get('asin'));
    $this->assertNull($expanded->get('isbn'));
   
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', '12345'));
    $this->assertSame('12345', $expanded->get('asin'));
    $this->assertNull($expanded->get('isbn'));
  }
 
  public function testTemplateRenaming() {
    $text = "{{cite web|url=https://books.google.com/books?id=ecrwrKCRr7YC&pg=PA85&lpg=PA85&dq=vestibular+testing+lab+gianoli&keywords=lab&text=vestibular+testing+lab+gianoli|title=Practical Management of the Dizzy Patient|first=Joel A.|last=Goebel|date=6 December 2017|publisher=Lippincott Williams & Wilkins|via=Google Books}}";
    // Should add ISBN and thus convert to Cite book
    $expanded = $this->process_citation($text);
    $this->assertSame('9780781765626', $expanded->get('isbn'));
    $this->assertSame('cite book', $expanded->wikiname());
  }
  
  public function testTemplateRenamingURLConvert() {
    $text='{{Cite book|url=http://www.sciencedirect.com/science/article/pii/B9780123864543000129|last=Roberts|first=L.|date=2014|publisher=Academic Press|isbn=978-0-12-386455-0|editor-last=Wexler|editor-first=Philip|location=Oxford|pages=993–995|doi=10.1016/b978-0-12-386454-3.00012-9}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('http://www.sciencedirect.com/science/article/pii/B9780123864543000129', $expanded->get('chapter-url'));
    $this->assertNull($expanded->get('url'));
  }

  public function testDoiExpansion1() {
    $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite journal', $prepared->wikiname());
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $prepared->get('doi'));
  }
 
  public function testDoiExpansion2() {
    $text = "{{Cite web | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));
    $this->assertNull($expanded->get('url'));
  }
 
  public function testDoiExpansion3() {
    // Recognize official DOI targets in URL with extra fragments - fall back to
    // S2
    $text = '{{cite journal | url = https://link.springer.com/article/10.1007/BF00233701#page-1 | doi = 10.1007/BF00233701}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('url'));
  }
 
  public function testDoiExpansion4() {
    // Replace this test with a real URL (if one exists)
    $text = "{{Cite web | url = http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf}}"; // Fake URL, real DOI
    $expanded= $this->prepare_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));
    // Do not drop PDF files, in case they are open access and the DOI points to a paywall
    $this->assertSame('http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf', $expanded->get('url'));
  }

  public function testAddStuff() {
    $text = "{{cite book|publisher=exist}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('publisher', 'A new publisher to replace it'));
    
    $this->assertTrue($template->add_if_new('type', 'A description of this'));
    $this->assertSame('A description of this', $template->get('type'));
   
    $this->assertTrue($template->add_if_new('id', 'A description of this thing'));
    $this->assertFalse($template->add_if_new('id', 'Another description of this'));
  }
 
  public function testURLCleanUp() {
    $text = "{{cite book|url=ttps://junk}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://junk', $template->get('url'));

    $text = "{{cite book|url=http://orbit.dtu.dk/en/publications/33333|doi=1234}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNull($template->get('url'));

    $text = "{{cite book|url=https://ieeexplore.ieee.org/arnumber=1}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://ieeexplore.ieee.org/document/1', $template->get('url'));
   
    $text = "{{cite book|url=https://ieeexplore.ieee.org/document/01}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://ieeexplore.ieee.org/document/1', $template->get('url'));

    $text = "{{cite book|url=https://jstor.org/stuffy-Stuff/?refreqid=124}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://jstor.org/stuffy-Stuff/', $template->get('url'));

    $text = "{{cite book|url=https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('10.7249/j.ctt4cgd90.10', $template->get('jstor'));
    $this->assertNull($template->get('url'));
   
    $text = "{{cite book|url=https://www.jstor.org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('10.7249/j.ctt4cgd90.10', $template->get('jstor'));
    $this->assertNull($template->get('url'));

    $text = "{{cite book|url=https://jstor.org/stable/pdfplus/12345.pdf|jstor=12345}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNull($template->get('url'));
    $this->assertSame('12345', $template->get('jstor'));
   
    $text = "{{cite book|url=https://jstor.org/discover/12345.pdf|jstor=12345}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNull($template->get('url'));
    $this->assertSame('12345', $template->get('jstor'));

    $text = "{{cite book|url=https://archive.org/detail/jstor-12345}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNull($template->get('url'));
    $this->assertSame('12345', $template->get('jstor'));
   
    $text = "{{cite book|url=https://jstor.org/stable/pdfplus/12345.pdf}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNull($template->get('url'));
    $this->assertSame('12345', $template->get('jstor'));
  }
 
  public function testURLCleanUp2() {
    $text = "{{cite journal|url=https://dx.doi.org/10.0000/BOGUS}}"; // Add bogus
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get('url'));
    $this->assertSame('10.0000/BOGUS', $template->get('doi'));
  }
 
  public function testURLCleanUp3() {
    $text = "{{cite journal|url=https://dx.doi.org/10.0000/BOGUS|doi=10.0000/THIS_IS_JUNK_DATA}}"; // Fail to add bogus
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('https://dx.doi.org/10.0000/BOGUS', $template->get('url'));
    $this->assertSame('10.0000/THIS_IS_JUNK_DATA', $template->get('doi'));
  }
 
  public function testURLCleanUp4() {
    $text = "{{cite journal|url=https://dx.doi.org/10.1093/oi/authority.x}}"; // A particularly semi-valid DOI
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNull($template->get('doi'));
    $this->assertSame('https://dx.doi.org/10.1093/oi/authority.x', $template->get('url'));
  }
 
  public function testURLCleanUp5() {
    $text = "{{cite journal|doi=10.5284/1000184|url=https://dx.doi.org/10.5284/1000184XXXXXXXXXX}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNull($template->get('url'));
    $this->assertSame('10.5284/1000184', $template->get('doi'));
  }
 
  public function testURLCleanUp6() {
    $text = "{{cite journal|doi= 10.1093/oi/authority.x|url=https://dx.doi.org/10.1093/oi/authority.xXXXXXXXXXX.pdf}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('https://dx.doi.org/10.1093/oi/authority.xXXXXXXXXXX.pdf', $template->get('url'));
    $this->assertSame('10.1093/oi/authority.x', $template->get('doi'));
  }
 
  public function testURLCleanUp7() {
    $text = "{{cite journal|url=https://SomeRandomWeb.com/10.5284/1000184}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('https://SomeRandomWeb.com/10.5284/1000184', $template->get('url'));
    $this->assertNull($template->get('doi'));
  }
 
  public function testHDLasDOIThing1() {
    $text='{{Cite journal | doi=20.1000/100|url=http://www.stuff.com/20.1000/100}}';
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('20.1000/100', $template->get('doi'));
    $this->assertNull($template->get('url'));
  }
 
  public function testHDLasDOIThing2() {
    $text='{{Cite journal | doi=20.1000/100|url=http://www.stuff.com/20.1000/100.pdf}}';
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('20.1000/100', $template->get('doi'));
    $this->assertSame('http://www.stuff.com/20.1000/100.pdf', $template->get('url'));
  }
 
  public function testDoiExpansionBook() {
    $text = "{{cite book|doi=10.1007/978-981-10-3180-9_1}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('978-981-10-3179-3', $expanded->get('isbn'));
  }
  
  public function testDoiEndings() {
    $text = '{{cite journal | doi=10.1111/j.1475-4983.2012.01203.x/full}}';
    $expanded = $this->process_citation($text);   
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));
    
    $text = '{{cite journal| url=http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));  
  }

  public function testSeriesIsJournal() {
    $text = '{{citation | series = Annals of the New York Academy of Sciences| doi = 10.1111/j.1749-6632.1979.tb32775.x}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('journal')); // Doi returns exact same name for journal as series
  }
  
  public function testEmptyCoauthor() {
    $text = '{{Cite journal|pages=2| coauthor= |coauthors= }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('{{Cite journal|pages=2}}', $prepared->parsed_text());
    $text = '{{Cite journal|pages=2| coauthor=}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('{{Cite journal|pages=2}}', $prepared->parsed_text());
    $text = '{{Cite journal|pages=2| coauthors=}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('{{Cite journal|pages=2}}', $prepared->parsed_text());
  }

  public function testExpansionJstorBook() {
    $text = '{{Cite journal|url=https://www.jstor.org/stable/j.ctt6wp6td.10}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Verstraete', $expanded->get('last1'));
  }
 
  public function testAP_zotero() {
    $text = '{{cite web|author=Associated Press |url=https://www.theguardian.com/science/2018/feb/03/scientists-discover-ancient-mayan-city-hidden-under-guatemalan-jungle}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('author'));
    $this->assertNull($expanded->get('publisher'));
    $this->assertSame('Associated Press', $expanded->get('agency'));
  }
    
  public function testPublisherRemoval() {
    foreach (array('Google News Archive', '[[Google]]', 'Google News',
                   'Google.com', '[[Google News]]') as $publisher) {
      $text = "{{cite journal | publisher = $publisher}}";
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('publisher'));
    }
  }

  public function testPublisherCoversion() {
    $text = '{{cite web|publisher=New york TiMES}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('publisher'));
    $this->assertSame('New york TiMES', $expanded->get('work'));
  }
 
  public function testRemoveWikilinks() {
    $expanded = $this->process_citation("{{Cite journal|author1=[[Pure Evil]]}}");
    $this->assertSame('Pure Evil', $expanded->get('author1'));
    $this->assertSame('Pure Evil', $expanded->get('author1-link'));
    $expanded = $this->process_citation("{{Cite journal|author1=[[Pure]] and [[Evil]]}}");
    $this->assertSame('[[Pure]] and [[Evil]]', $expanded->get('author1'));
    $expanded = $this->process_citation("{{Cite journal|author1=[[Pure Evil|Approximate Physics]]}}");
    $this->assertSame('Approximate Physics', $expanded->get('author1'));
    $this->assertSame('Pure Evil', $expanded->get('author1-link'));
   
    $expanded = $this->process_citation("{{Cite journal|journal=[[Pure Evil]]}}");
    $this->assertSame('[[Pure Evil]]', $expanded->get('journal')); // leave fully linked journals
    $expanded = $this->process_citation("{{Cite journal|journal=[[Pure]] and [[Evil]]}}");
    $this->assertSame('Pure and Evil', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|journal=Dark Lord of the Sith [[Pure Evil]]}}");
    $this->assertSame('Dark Lord of the Sith Pure Evil', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil]]}}");
    $this->assertSame('Pure Evil', $expanded->get('title'));
    $this->assertSame('Pure Evil', $expanded->get('title-link'));
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil|Approximate Physics]]}}");
    $this->assertSame('Approximate Physics', $expanded->get('title'));
    $this->assertSame('Pure Evil', $expanded->get('title-link'));
    $expanded = $this->process_citation("{{Cite journal|title=[[Dark]] Lord of the [[Sith (Star Wars)|Sith]] [[Pure Evil]]}}");
    $this->assertSame('Dark Lord of the Sith Pure Evil', $expanded->get('title'));
    $expanded = $this->process_citation("{{Cite journal|title=Dark Lord of the [[Sith (Star Wars)|Sith]] Pure Evil}}");
    $this->assertSame('Dark Lord of the Sith Pure Evil', $expanded->get('title'));
    $this->assertSame('Sith (Star Wars)', $expanded->get('title-link'));
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil] }}");
    $this->assertSame('Pure Evil', $expanded->get('title'));
    $this->assertNull($expanded->get('title-link'));
    $expanded = $this->process_citation("{{Cite journal|title=[Pure Evil]]}}");
    $this->assertSame('Pure Evil', $expanded->get('title'));
    $expanded = $this->process_citation("{{Cite journal|title=Dark Lord of the [[Sith]] Pure Evil}}");
    $this->assertSame('Dark Lord of the Sith Pure Evil', $expanded->get('title'));
    $this->assertNull($expanded->get('title-link'));
    $expanded = $this->process_citation("{{cite journal|journal=[[Bulletin du Muséum national d’Histoire naturelle, Paris]]}}");
    $this->assertSame("[[Bulletin du Muséum national d'Histoire naturelle, Paris]]", $expanded->get('journal'));
    $expanded = $this->process_citation("{{cite journal|journal=[[Bulletin du Muséum national d’Histoire naturelle, Paris|Hose]]}}");
    $this->assertSame("[[Bulletin du Muséum national d'Histoire naturelle, Paris|Hose]]", $expanded->get('journal'));
  }
 
  public function testRemoveWikilinks2() {
    $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil]]}}");
    $this->assertSame('Pure Evil', $expanded->get('last1'));
    $this->assertSame('Pure Evil', $expanded->get('author1-link'));
   
    $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil|Approximate Physics]]}}");
    $this->assertSame('Approximate Physics', $expanded->get('last1'));
    $this->assertSame('Pure Evil', $expanded->get('author1-link'));
   
    $expanded = $this->process_citation("{{Cite journal|last2=[[Pure Evil]]}}");
    $this->assertSame('Pure Evil', $expanded->get('last2'));
    $this->assertSame('Pure Evil', $expanded->get('author2-link'));
   
    $expanded = $this->process_citation("{{Cite journal|last2=[[Pure Evil|Approximate Physics]]}}");
    $this->assertSame('Approximate Physics', $expanded->get('last2'));
    $this->assertSame('Pure Evil', $expanded->get('author2-link'));
   
    $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil]] and [[Hoser]]}}");
    $this->assertSame('[[Pure Evil]] and [[Hoser]]', $expanded->get('last1'));
    $this->assertNull($expanded->get('author1-link'));
   
    $expanded = $this->process_citation("{{Cite journal|last1=[[Pure {{!}} Evil]]}}");
    $this->assertNull($expanded->get('author1-link'));
    $this->assertSame('[[Pure {{!}} Evil]]', $expanded->get('last1'));
  }
 
  public function testJournalCapitalization() {
    $expanded = $this->process_citation("{{Cite journal|pmid=9858585}}");
    $this->assertSame('Molecular and Cellular Biology', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|journal=eJournal}}");
    $this->assertSame('eJournal', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|journal=EJournal}}");
    $this->assertSame('eJournal', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|journal=ejournal}}");
    $this->assertSame('eJournal', $expanded->get('journal'));
  }
    
  public function testWebsiteAsJournal() {
    $text = '{{Cite journal | journal=www.foobar.com}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('www.foobar.com', $expanded->get('website'));
    $this->assertNull($expanded->get('journal'));
    $text = '{{Cite journal | journal=https://www.foobar.com}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('https://www.foobar.com', $expanded->get('url'));
    $this->assertNull($expanded->get('journal'));
    $text = '{{Cite journal | journal=[www.foobar.com]}}';
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
  
  public function testDropArchiveDotOrg() {
    $text = '{{Cite journal | publisher=archive.org}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('publisher'));
      
    $text = '{{Cite journal | website=archive.org|url=http://fake.url/NOT_REAL}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('http://fake.url/NOT_REAL', $expanded->get('url'));
    $this->assertNull($expanded->get('website'));
  }
 
  public function testPreferLinkedPublisher() {
    $text = "{{cite journal| journal=The History Teacher| publisher=''[[The History Teacher]]'' }}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('publisher'));
    $this->assertSame("[[The History Teacher]]", $expanded->get('journal')); // Quotes do get dropped
  }
 
  public function testLeaveArchiveURL() {
    $text = '{{cite book |chapterurl=http://faculty.haas.berkeley.edu/shapiro/thicket.pdf|isbn=978-0-262-60041-5|archiveurl=https://web.archive.org/web/20070704074830/http://faculty.haas.berkeley.edu/shapiro/thicket.pdf }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('https://web.archive.org/web/20070704074830/http://faculty.haas.berkeley.edu/shapiro/thicket.pdf', $expanded->get('archiveurl'));
  }

  public function testScriptTitle() {
    $text = "{{cite book |author={{noitalic|{{lang|zh-hans|国务院人口普查办公室、国家统计局人口和社会科技统计司编}}}} |date=2012 |script-title=zh:中国2010年人口普查分县资料 |location=Beijing |publisher={{noitalic|{{lang|zh-hans|中国统计出版社}}}} [China Statistics Press] |page= |isbn=978-7-5037-6659-6 }}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('title')); // Already have script-title that matches what google books gives us
    $this->assertTrue($expanded->add_if_new('title', 'This English Only'));
    $this->assertSame('This English Only', $expanded->get('title'));              
  }
    
  public function testPageDuplication() {
     // Fake bibcoce otherwise we'll find a bibcode
     $text = '{{cite journal| p=546 |doi=10.1103/PhysRev.57.546|title=Nuclear Fission of Separated Uranium Isotopes |journal=Physical Review |volume=57 |issue=6 |year=1940 |last1=Nier |first1=Alfred O. |last2=Booth |first2=E. T. |last3=Dunning |first3=J. R. |last4=Grosse |first4=A. V. |bibcode=XXXXXXXXXXXXX}}';
     $expanded = $this->process_citation($text);
     $this->assertSame($text, $expanded->parsed_text());
   }

  public function testLastVersusAuthor() {
    $text = "{{cite journal|pmid=12858711}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('author1'));
    $this->assertSame('Lovallo', $expanded->get('last1'));
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
    $this->assertSame('Cite arXiv', $expanded->name());
  }
 
  public function testTwoUrls() {
    $text = '{{citation|url=http://jstor.org/stable/333111333|chapter-url=http://adsabs.harvard.edu/abs/2222NatSR...814768S}}'; // Both fake
    $expanded = $this->process_citation($text);
    $this->assertSame('333111333', $expanded->get('jstor'));
    $this->assertSame('2222NatSR...814768S', $expanded->get('bibcode'));
    $this->assertNull($expanded->get('url'));
    $this->assertNull($expanded->get('chapter-url'));
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
    $this->assertSame('<!-- MC Hammer says to not touch this -->', $expanded->get('doi'));
      
    $text = '{{cite journal|doi= {{MC Hammer says to not touch this}} }}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi-broken-date'));
    $this->assertSame('{{MC Hammer says to not touch this}}', $expanded->get('doi'));
    $text = '{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}', $expanded->parsed_text());
  }
    
  public function testCrossRefEvilDoi() {
    $text = '{{cite journal | doi = 10.1002/(SICI)1097-0134(20000515)39:3<216::AID-PROT40>3.0.CO;2-#}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi-broken-date'));
    $this->assertSame('39', $expanded->get('volume'));
  }

  public function testOpenAccessLookup() {    
    $text = '{{cite journal|doi=10.1136/bmj.327.7429.1459}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('300808', $expanded->get('pmc'));
    
    $text = '{{cite journal|doi=10.1038/nature08244}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('0904.1532', $expanded->get('arxiv'));
      
    $text = '{{cite journal | vauthors = Shekelle PG, Morton SC, Jungvig LK, Udani J, Spar M, Tu W, J Suttorp M, Coulter I, Newberry SJ, Hardy M | title = Effect of supplemental vitamin E for the prevention and treatment of cardiovascular disease | journal = Journal of General Internal Medicine | volume = 19 | issue = 4 | pages = 380–9 | date = April 2004 | pmid = 15061748 | pmc = 1492195 | doi = 10.1111/j.1525-1497.2004.30090.x }}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('url'));
      
    $text = '{{Cite journal | doi = 10.1063/1.4962420| title = Calculating vibrational spectra of molecules using tensor train decomposition| journal = J. Chem. Phys. | volume = 145| year = 2016| issue = 145| pages = 124101| last1 = Rakhuba| first1 = Maxim | last2 = Oseledets | first2 = Ivan| bibcode = 2016JChPh.145l4101R| arxiv =1605.08422}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('url'));
  }
 
  public function testSemanticScholar() {
   $text = "{{cite journal|doi=10.5555/555555}}";
   $template = $this->make_citation($text);
   $return = $template->get_unpaywall_url($template->get('doi'));
   $this->assertSame('nothing', $return);
   $this->assertNull($template->get('url'));
  }
  
  public function testUnPaywall() {
   $text = "{{cite journal|doi=10.1145/358589.358596}}";
   $template = $this->make_citation($text);
   $template->get_semanticscholar_url($template->get('doi'));
   $this->assertSame('https://www.semanticscholar.org/paper/46c0955a810b4a3777e4251e2df7954488df196d', $template->get('url'));
   
   $text = "{{cite journal|doi=10.1145/358589.358596|doi-access=free}}";
   $template = $this->make_citation($text);
   $template->get_semanticscholar_url($template->get('doi'));
   $this->assertNull($template->get('url'));
  }
 
  public function testCommentHandling() {
    $text = "{{cite book|pages=3333 <!-- yes --> }} {{cite book <!-- no --> | pages=3<nowiki>-</nowiki>6}} {{cite book | pages=3<pre>-</pre>6}} {{cite book | pages=3<math>-</math>6}} {{cite book | pages=3<score>-</score>6}} {{cite book | pages=3<chem>-</chem>6}}";
    $expanded_page = $this->process_page($text);
    $this->assertSame($text, $expanded_page->parsed_text());
  }
  
  public function testDoi2PMID() {
    $text = "{{cite journal|doi=10.1073/pnas.171325998}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('11573006', $expanded->get('pmid'));
    $this->assertSame('58796', $expanded->get('pmc'));
  }
 
  public function testSiciExtraction() {
    $text='{{cite journal|url=http://fake.url/9999-9999(2002)152[0215:XXXXXX]2.0.CO;2}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('9999-9999', $expanded->get('issn')); // Fake to avoid cross-ref search
    $this->assertSame('2002', $this->getDateAndYear($expanded));
    $this->assertSame('152', $expanded->get('volume'));
    $this->assertSame('215', $expanded->get('pages'));
    $expanded = NULL;
    
    // Now check that parameters are NOT extracted when certain parameters exist
    $text = "{{cite journal|date=2002|journal=SET|url=http:/1/fake.url/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('issn'));
    $this->assertSame('2002', $this->getDateAndYear($expanded));
    $this->assertSame('152', $expanded->get('volume'));
    $this->assertSame('215', $expanded->get('pages'));
  }
 
  public function testUseISSN() {
      $text = "{{Cite book|issn=0031-0603}}";
      $expanded = $this->process_citation($text);
      $this->assertTrue(stripos($expanded->get('journal'), 'Entomologist') !== FALSE);
  }
 
  public function testParameterAlias() {
    $text = '{{cite journal |author-last1=Knops |author-first1=J.M. |author-last2=Nash III |author-first2=T.H.
    |date=1991 |title=Mineral cycling and epiphytic lichens: Implications at the ecosystem level 
    |journal=Lichenologist |volume=23 |pages=309–321 |doi=10.1017/S0024282991000452 |issue=3}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('last1'));
    $this->assertNull($expanded->get('last2'));
    $this->assertNull($expanded->get('first1'));
    $this->assertNull($expanded->get('first2'));
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
    $this->assertSame($album_link, $expanded->get('titlelink'));    
     
    // Double-check pages expansion
    $text = "{{Cite journal|pp. 1-5}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('1–5', $expanded->get('pages'));
      
    $text = "{{cite book|authorlinux=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('{{cite book|authorlink=X}}', $expanded->parsed_text());
      
    $text = "{{cite book|authorlinks33=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('{{cite book|authorlink33=X}}', $expanded->parsed_text());
  }
       
  public function testId2Param() {
      $text = '{{cite book |id=ISBN 978-1234-9583-068, DOI 10.1234/bashifbjaksn.ch2, {{arxiv|1234.5678}} {{oclc|12354|4567}} {{oclc|1234}} {{ol|12345}} }}';
      $expanded = $this->process_citation($text);
      $this->assertSame('978-1234-9583-068', $expanded->get('isbn'));
      $this->assertSame('1234.5678', $expanded->get('arxiv'));
      $this->assertSame('10.1234/bashifbjaksn.ch2', $expanded->get('doi'));
      $this->assertSame('1234', $expanded->get('oclc'));
      $this->assertSame('12345', $expanded->get('ol'));
      $this->assertNotNull($expanded->get('doi-broken-date'));
      $this->assertSame(0, preg_match('~' . sprintf(Template::PLACEHOLDER_TEXT, '\d+') . '~i', $expanded->get('id')));
      
      $text = '{{cite book | id={{arxiv|id=1234.5678}}}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('1234.5678', $expanded->get('arxiv'));
      
      $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} }}';
      $expanded = $this->process_citation($text);
      $this->assertSame('astr.ph/1234.5678', $expanded->get('arxiv'));     
      
      $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} {{arxiv|astr.ph|1234.5678}} }}'; // Two of the same thing
      $expanded = $this->process_citation($text);
      $this->assertSame('astr.ph/1234.5678', $expanded->get('arxiv'));
      $this->assertSame('{{cite book | arxiv=astr.ph/1234.5678 }}', $expanded->parsed_text());
      
      $text = '{{cite book|pages=1–2|id={{arxiv|astr.ph|1234.5678}}}}{{cite book|pages=1–3|id={{arxiv|astr.ph|1234.5678}}}}'; // Two of the same sub-template, but in different tempalates
      $expanded = $this->process_page($text);
      $this->assertSame('{{cite book|pages=1–2|arxiv=astr.ph/1234.5678}}{{cite book|pages=1–3|arxiv=astr.ph/1234.5678}}', $expanded->parsed_text());
  }
  
  public function testNestedTemplates() {
      $text = '{{cite book|pages=1-2| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} | id={{cite book|pages=1-3| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} }}  }} |  cool stuff | not cool}}}}';
      $expanded = $this->process_citation($text);
      $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
      $this->assertSame($text, $expanded->parsed_text());
      
      $text = '{{cite book|quote=See {{cite book|pages=1-2|quote=See {{cite book|pages=1-4}}}}|pages=1-3}}';
      $expanded = $this->process_citation($text);
      $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
      $this->assertSame($text, $expanded->parsed_text());
  }
    
  public function testDropPostscript() {
      $text = '{{citation|postscript=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());
      
      $text = '{{citation|postscript=.}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());
      
      $text = '{{cite journal|postscript=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite journal}}', $prepared->parsed_text());
      
      $text = '{{cite journal|postscript=.}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite journal}}', $prepared->parsed_text());
      
      $text = '{{cite journal|postscript=none}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());
  }
    
  public function testChangeParamaters() {
      // publicationplace
      $text = '{{citation|publicationplace=Home}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|location=Home}}', $prepared->parsed_text());
      
      $text = '{{citation|publication-place=Home|location=Away}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());

      // publicationdate
      $text = '{{citation|publicationdate=2000}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|date=2000}}', $prepared->parsed_text());
      
      $text = '{{citation|publicationdate=2000|date=1999}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());

      // origyear
      $text = '{{citation|origyear=2000}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
      
      $text = '{{citation|origyear=2000|date=1999}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text()); 
 }

  public function testDropDuplicates() {
      $text = '{{citation|work=Work|journal=|magazine=|website=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|work=Work}}', $prepared->parsed_text());
      
      $text = '{{citation|work=Work|journal=Journal|magazine=Magazine|website=Website}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());
  }

  public function testBadPunctuation() {
      $text = '{{citation|title=:: Huh ::}}';
      $prepared = $this->make_citation($text);
      $prepared->tidy_parameter('title');
      $this->assertSame(':: Huh ::', $prepared->get('title'));
   
      $text = '{{citation|title=: Huh :}}';
      $prepared = $this->make_citation($text);
      $prepared->tidy_parameter('title');
      $this->assertSame('Huh', $prepared->get('title'));

      $text = '{{citation|title=; Huh ;;}}';
      $prepared = $this->make_citation($text);
      $prepared->tidy_parameter('title');
      $this->assertSame('Huh ;;', $prepared->get('title'));
  }
 
  public function testWorkParamter() {
      $text = '{{citation|work=RUBBISH|title=Rubbish|chapter=Dog}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|title=Rubbish|chapter=Dog}}', $prepared->parsed_text());
      
      $text = '{{cite book|series=Keep Series, Lose Work|work=Keep Series, Lose Work}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite book|series=Keep Series, Lose Work}}', $prepared->parsed_text());
      
      $text = '{{cite journal|chapter=A book chapter|work=A book chapter}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite book|chapter=A book chapter}}', $prepared->parsed_text());
      
      $text = '{{citation|work=I Live}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());
      
      $text = '{{not cite|work=xyz|chapter=xzy}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{not cite|work=xyz|chapter=xzy}}', $prepared->parsed_text());
      
      $text = '{{citation|work=xyz|journal=xyz}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|journal=Xyz}}', $prepared->parsed_text());
      
      $text = '{{citation|work=|chapter=Keep work in Citation template}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|work=|chapter=Keep work in Citation template}}', $prepared->parsed_text());
      
      $text = '{{cite journal|work=work should become journal}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite journal|journal=Work Should Become Journal}}', $prepared->parsed_text());
      
      $text = '{{cite magazine|work=abc}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite magazine|magazine=abc}}', $prepared->parsed_text());
      
      $text = '{{cite journal|work=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite journal|journal=}}', $prepared->parsed_text());
  }
  
  public function testOrigYearHandling() {
      $text = '{{cite book |year=2009 | origyear = 2000 }}';
      $prepared = $this->process_citation($text);
      $this->assertSame('2000', $prepared->get('origyear'));
      $this->assertSame('2009', $this->getDateAndYear($prepared));
      
      $text = '{{cite book | origyear = 2000 }}';
      $prepared = $this->process_citation($text);
      $this->assertSame('2000', $this->getDateAndYear($prepared));
      $this->assertNull($prepared->get('origyear'));
  }
    
  public function testDropAmazon() {
    $text = '{{Cite journal | publisher=amazon.com}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('publisher'));
  }
    
  public function testGoogleBooksExpansion() {
    $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html?id=SjpSkzjIzfsC&redir_esc=y}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('https://books.google.com/?id=SjpSkzjIzfsC', $expanded->get('url'));
    $this->assertSame('Wonderful Life: The Burgess Shale and the Nature of History',
      $expanded->get('title'));
    $this->assertSame('9780393307009', $expanded->get('isbn')   );
    $this->assertSame('Gould'        , $expanded->get('last1'));
    $this->assertSame('Stephen Jay'  , $expanded->get('first1') );
    $this->assertSame('1990-09-17'   , $expanded->get('date'));
    $this->assertNull($expanded->get('pages')); // Do not expand pages.  Google might give total pages to us
  }
  
  public function testGoogleDates() {
    $text = "{{cite book|url=https://books.google.com/books?id=yN8DAAAAMBAJ&pg=PA253}}";
    $expanded = $this->process_citation($text);
    $this->assertTrue(in_array($expanded->get('date'), ['February 1935', '1935-02']));
    // Google recovers Feb 1935; Zotero returns 1935-02.
  }
  
  public function testLongAuthorLists() {
    $text = '{{cite web | https://arxiv.org/PS_cache/arxiv/pdf/1003/1003.3124v2.pdf}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Aad, G.', $expanded->first_author());
    $this->assertNull($expanded->get('class'));
    
    // Same paper, but CrossRef records full list of authors instead of collaboration name
    $text = '{{cite web | 10.1016/j.physletb.2010.03.064}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('29', $expanded->get('displayauthors'));
    $this->assertSame('Aielli', $expanded->get('last30'));
    $this->assertSame("Charged-particle multiplicities in pp interactions at <math>"
      . '\sqrt{s}=900\text{ GeV}' .
      "</math> measured with the ATLAS detector at the LHC", $expanded->get('title'));
    $this->assertNull($expanded->get('last31'));
  }
  
  public function testInPress() {  
    $text = '{{Cite journal|pmid=9858585|date =in press}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('1999', $this->getDateAndYear($expanded));
  }
  
 public function testISODates() {
    $text = '{{cite book |author=Me |title=Title |year=2007-08-01 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('2007-08-01', $prepared->get('date'));
    $this->assertNull($prepared->get('year'));
  }
  
  public function testND() {  // n.d. is special case that template recognize.  Must protect final period.
    $text = '{{Cite journal|date =n.d.}}';
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
    
    $text = '{{Cite journal|year=n.d.}}';
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
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
     $this->assertSame('A Mathematical Theory of Communication', $prepared->get('title'));
     $this->assertSame('1948-07', $prepared->get('date'));
     $this->assertSame('Bell System Technical Journal', $prepared->get('journal'));
     $this->assertSame('Shannon, Claude E.', $prepared->first_author());
     $this->assertSame('Shannon', $prepared->get('last1'));
     $this->assertSame('Claude E.', $prepared->get('first1'));
     $this->assertSame('379–423', $prepared->get('pages'));
     $this->assertSame('27', $prepared->get('volume'));   
     // This is the exact same reference, but with an invalid title, that flags this data to be rejected
     // We check everything is null, to verify that bad title stops everything from being added, not just title
      $text = '{{Cite journal  | TY - JOUR
AU - Shannon, Claude E.
PY - 1948/07//
TI - oup accepted manuscript
T2 - Bell System Technical Journal
SP - 379
EP - 423
VL - 27
ER -  }}';
     $prepared = $this->prepare_citation($text);
     $this->assertNull($prepared->get('title'));
     $this->assertNull($prepared->get('date'));
     $this->assertNull($prepared->get('journal'));
     $this->assertNull($prepared->first_author());
     $this->assertNull($prepared->get('last1'));
     $this->assertNull($prepared->get('first1'));
     $this->assertNull($prepared->get('pages'));
     $this->assertNull($prepared->get('volume'));
   
      $text = '{{Cite journal  | TY - BOOK
Y1 - 1990
T1 - This will be a subtitle }}';
     $prepared = $this->prepare_citation($text);
     $this->assertSame('1990', $prepared->get('year')); 
     $this->assertNull($prepared->get('title'));
     $this->assertNull($prepared->get('chapter'));
     $this->assertNull($prepared->get('journal'));
     $this->assertNull($prepared->get('series'));

     $text = '{{Cite journal  | TY - JOUR
Y1 - 1990
JF - This is the Journal
T1 - This is the Title }}';
     $prepared = $this->prepare_citation($text);
     $this->assertSame('1990', $prepared->get('year')); 
     $this->assertSame('This is the Journal', $prepared->get('journal'));
     $this->assertSame('This is the Title', $prepared->get('title'));
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
%@ Ignore
%9 Dissertation}}';
      $code_coverage1  = '{{Citation |
%0 Journal Article
%T This Title
%@ 9999-9999}}';
   
      $code_coverage2  = '{{Citation |
%0 Book
%T This Title
%@ 000-000-000-0X}}';

       $prepared = $this->prepare_citation($book);
       $this->assertSame('Chaucer, Geoffrey', $prepared->first_author());
       $this->assertSame('The Works of Geoffrey Chaucer', $prepared->get('title'));
       $this->assertSame('1957', $this->getDateAndYear($prepared));
       $this->assertSame('Houghton', $prepared->get('publisher'));
       $this->assertSame('Boston', $prepared->get('location'));
       
       $prepared = $this->process_citation($article);
       $this->assertSame('Clark, Herbert H.', $prepared->first_author());
       $this->assertSame('1982', $this->getDateAndYear($prepared));
       $this->assertSame('Hearers and Speech Acts', $prepared->get('title'));
       $this->assertSame('58', $prepared->get('volume'));
       $this->assertSame('332–373', $prepared->get('pages'));
       
       
       $prepared = $this->process_citation($thesis);
       $this->assertSame('Cantucci, Elena', $prepared->first_author());
       $this->assertSame('Permian strata in South-East Asia', $prepared->get('title'));
       $this->assertSame('1990', $this->getDateAndYear($prepared));
       $this->assertSame('University of California, Berkeley', $prepared->get('publisher'));
       $this->assertSame('10.1038/ntheses.01928', $prepared->get('doi'));
   
       $prepared = $this->process_citation($code_coverage1);
       $this->assertSame('This Title', $prepared->get('title'));;
       $this->assertSame('9999-9999', $prepared->get('issn'));
   
       $prepared = $this->process_citation($code_coverage2);
       $this->assertSame('This Title', $prepared->get('title'));;
       $this->assertSame('000-000-000-0X', $prepared->get('isbn'));  
  }
   
  public function testConvertingISBN10intoISBN13() { // URLS present just to speed up tests.  Fake years to trick date check
    $text = "{{cite book|isbn=0-9749009-0-7|url=https://books.google.com/books?id=to0yXzq_EkQC|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('978-0-9749009-0-2', $prepared->get('isbn'));  // Convert with dashes
    
    $text = "{{cite book|isbn=978-0-9749009-0-2|url=https://books.google.com/books?id=to0yXzq_EkQC|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('978-0-9749009-0-2', $prepared->get('isbn'));  // Unchanged with dashes
    
    $text = "{{cite book|isbn=9780974900902|url=https://books.google.com/books?id=to0yXzq_EkQC|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('9780974900902', $prepared->get('isbn'));   // Unchanged without dashes
    
    $text = "{{cite book|isbn=0974900907|url=https://books.google.com/books?id=to0yXzq_EkQC|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('978-0974900902', $prepared->get('isbn'));   // Convert without dashes
    
    $text = "{{cite book|isbn=1-84309-164-X|url=https://books.google.com/books?id=GvjwAQAACAAJ|year=2019}}";
    $prepared = $this->prepare_citation($text);  
    $this->assertSame('978-1-84309-164-6', $prepared->get('isbn'));  // Convert with dashes and a big X
    
    $text = "{{cite book|isbn=184309164x|url=https://books.google.com/books?id=GvjwAQAACAAJ|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('978-1843091646', $prepared->get('isbn'));  // Convert without dashes and a tiny x
    
    $text = "{{cite book|isbn=Hello Brother}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Hello Brother', $prepared->get('isbn')); // Rubbish unchanged
  }
   
  public function testEtAl() {
    $text = '{{cite book |auths=Alfred A Albertstein, Bertie B Benchmark, Charlie C. Chapman et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Albertstein, Alfred A.', $prepared->first_author());
    $this->assertSame('Charlie C.', $prepared->get('first3'));
    $this->assertSame('etal', $prepared->get('displayauthors'));
  }
 
  public function testEtAlAsAuthor() {
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('author3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('last3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = etal. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('author3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = etal }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('last3'));
    $text = '{{cite book|last1=etal}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('last1'));
    $text = '{{cite book|last1=et al}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('last1'));
    $text = '{{cite book|last1=et al}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('last1'));
  }
       
  public function testWebsite2Url() {
      $text = '{{cite book |website=ttp://example.org }}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      
      $text = '{{cite book |website=example.org }}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      
      $text = '{{cite book |website=ttp://jstor.org/pdf/123456 | jstor=123456 }}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      
      $text = '{{cite book |website=ABC}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      $this->assertSame('ABC', $prepared->get('website'));
      
      $text = '{{cite book |website=ABC XYZ}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      $this->assertSame('ABC XYZ', $prepared->get('website'));
      
      $text = '{{cite book |website=http://ABC/ I have Spaces in Me}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get('url'));
      $this->assertSame('http://ABC/ I have Spaces in Me', $prepared->get('website'));
  }
  
  public function testHearst () {
    $text = '{{cite book|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=onepage&q&f=true}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Hearst Magazines', $expanded->get('publisher'));
    $this->assertNull($expanded->get('last1'));
    $this->assertNull($expanded->get('last'));
    $this->assertNull($expanded->get('author'));
    $this->assertNull($expanded->get('author1'));
    $this->assertNull($expanded->get('authors'));
  }
       
  public function testInternalCaps() { // checks for title formating in tidy() not breaking things
    $text = '{{cite journal|journal=ZooTimeKids}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('ZooTimeKids', $prepared->get('journal'));
  }
  
  public function testCapsAfterColonAndPeriodJournalTidy() {
    $text = '{{Cite journal |journal=In Journal Titles: a word following punctuation needs capitals. Of course.}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('In Journal Titles: A Word Following Punctuation Needs Capitals. Of Course.', 
                        $prepared->get('journal'));
  }      

  public function testExistingWikiText() { // checks for formating in tidy() not breaking things
    $text = '{{cite journal|title=[[Zootimeboys]] and Girls|journal=[[Zootimeboys]] and Girls}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Zootimeboys and Girls', $prepared->get('journal'));
    $this->assertSame('Zootimeboys and Girls', $prepared->get('title'));
  }
  
  public function testNewWikiText() { // checks for new information that looks like wiki text and needs escaped
    $text = '{{Cite journal|doi=10.1021/jm00193a001}}';  // This has greek letters, [, ], (, and ).
    $expanded = $this->process_citation($text);
    $this->assertSame('Synthetic studies on β-lactam antibiotics. Part 10. Synthesis of 7β-&#91;2-carboxy-2-(4-hydroxyphenyl)acetamido&#93;-7.alpha.-methoxy-3-&#91;&#91;(1-methyl-1H-tetrazol-5-yl)thio&#93;methyl&#93;-1-oxa-1-dethia-3-cephem-4-carboxylic acid disodium salt (6059-S) and its related 1-oxacephems', $expanded->get('title'));
  }
  
  public function testZooKeys() {
    $text = '{{Cite journal|doi=10.3897/zookeys.445.7778}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('ZooKeys', $expanded->get('journal'));
    $this->assertSame('445', $expanded->get('issue'));
    $this->assertNull($expanded->get('volume'));
    $text = '{{Cite journal|doi=10.3897/zookeys.445.7778|journal=[[Zookeys]]}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('445', $expanded->get('issue'));
    $this->assertNull($expanded->get('volume'));
    $text = "{{cite journal|last1=Bharti|first1=H.|last2=Guénard|first2=B.|last3=Bharti|first3=M.|last4=Economo|first4=E.P.|title=An updated checklist of the ants of India with their specific distributions in Indian states (Hymenoptera, Formicidae)|journal=ZooKeys|date=2016|volume=551|pages=1–83|doi=10.3897/zookeys.551.6767|pmid=26877665|pmc=4741291}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('551', $expanded->get('issue'));
    $this->assertNull($expanded->get('volume'));
  }
 
  public function testZooKeysDoiTidy() {
      $text = '{{Cite journal|doi=10.3897/zookeys.123.322222}}';
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->assertNull($expanded->get('journal'));
      $this->assertSame('123', $expanded->get('issue'));
   
      $text = '{{Cite journal|doi=10.3897/zookeys.123.322222|issue=2323323}}';
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->assertNull($expanded->get('journal'));
      $this->assertSame('123', $expanded->get('issue'));
   
      $text = '{{Cite journal|doi=10.3897/zookeys.123.322222|number=2323323}}';
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->assertNull($expanded->get('journal'));
      $this->assertSame('123', $expanded->get('issue'));
   
      $text = '{{Cite journal|doi=10.3897/zookeys.123.322222X}}';
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->assertNull($expanded->get('journal'));
      $this->assertNull($expanded->get('issue'));
  }
 
  public function testTitleItalics(){
    $text = '{{cite journal|doi=10.1111/pala.12168}}';
    $expanded = $this->process_citation($text);
    $this->assertSame("The macro- and microfossil record of the Cambrian priapulid ''Ottoia''", $expanded->get('title'));
  }
  
  public function testSpeciesCaps() {
    $text = '{{Cite journal | doi = 10.1007%2Fs001140100225}}';
    $expanded = $this->process_citation($text);
    $this->assertSame(str_replace(' ', '', "Crypticmammalianspecies:Anewspeciesofwhiskeredbat(''Myotisalcathoe''n.sp.)inEurope"), 
                        str_replace(' ', '', $expanded->get('title')));
    $text = '{{Cite journal | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1550-7408.2002.tb00224.x/full}}';
    // Should be able to drop /full from DOI in URL
    $expanded = $this->process_citation($text);
    $this->assertSame(str_replace(' ', '', "''Cryptosporidiumhominis''n.sp.(Apicomplexa:Cryptosporidiidae)fromHomosapiens"),
                        str_replace(' ', '', $expanded->get('title'))); // Can't get Homo sapiens, can get nsp.
  }   
    
  public function testSICI() {
    $url = "https://fake.url/sici?sici=9999-9999(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
    $text = "{{Cite journal|url=$url}}";  // We use a rubbish ISSN and website so that this does not expand any more -- only test SICI code
    $expanded = $this->process_citation($text);
      
    $this->assertSame('1961', $expanded->get('year'));
    $this->assertSame('81', $expanded->get('volume'));
    $this->assertSame('1', $expanded->get('issue'));
    $this->assertSame('43', $expanded->get('pages'));
  }
  
  public function testJstorSICI() {
    $url = "https://www.jstor.org/sici?sici=0003-0279(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
    $text = "{{Cite journal|url=$url}}";
    $expanded = $this->process_citation($text);
      
    $this->assertSame('594900', $expanded->get('jstor'));
    $this->assertSame('1961', $expanded->get('year'));
    $this->assertSame('81', $expanded->get('volume'));
    $this->assertSame('1', $expanded->get('issue'));
    $this->assertSame('43–52', $expanded->get('pages'));  // The jstor expansion add the page ending
  }
  
  public function testJstorSICIEncoded() {
    $text = '{{Cite journal|url=https://www.jstor.org/sici?sici=0003-0279(196101%2F03)81%3A1%3C43%3AWLIMP%3E2.0.CO%3B2-9}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('594900', $expanded->get('jstor'));
  }

  public function testIgnoreJstorPlants() {
    $text='{{Cite journal| url=http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972 |title=Holotype of Persoonia terminalis L.A.S.Johnson & P.H.Weston [family PROTEACEAE]}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972', $expanded->get('url'));
    $this->assertNull($expanded->get('jstor'));
    $this->assertNull($expanded->get('doi'));
  }

  public function testConvertJournalToBook() {
    $text = '{{Cite journal|doi=10.1007/978-3-540-74735-2_15}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
  }

  public function testRenameToJournal() {
    $text = "{{cite arxiv | bibcode = 2013natur1305.7450M}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite journal', $prepared->wikiname());
    $text = "{{cite arxiv | bibcode = 2013arXiv1305.7450M}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite arxiv', $prepared->wikiname());
    $text = "{{cite arxiv | bibcode = 2013physics305.7450M}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite arxiv', $prepared->wikiname());
  }
 
  public function testArxivDocumentBibcodeCode() {
    $text = "{{cite arxiv| arxiv=1234|bibcode=abc}}";
    $template = $this->make_citation($text);
    $template->change_name_to('cite journal');
    $template->final_tidy();
    $this->assertSame('cite arxiv', $template->wikiname());
    $this->assertNull($template->get('bibcode'));    
   
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite journal', $template->wikiname());
   
    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->change_name_to('cite journal');
    $template->final_tidy();
    $this->assertSame('cite document', $template->wikiname());
   
    $text = "{{cite web|eprint=xxx}}";
    $template = $this->make_citation($text);
    $template->change_name_to('cite journal');
    $template->final_tidy();
    $this->assertSame('cite arxiv', $template->wikiname());
  }
 
  public function testRenameToExisting() {
    $text = "{{cite journal|issue=1|volume=2|doi=3}}";
    $template = $this->make_citation($text);
    $this->assertSame('{{cite journal|issue=1|volume=2|doi=3}}', $template->parsed_text());
    $template->rename('doi', 'issue');
    $this->assertSame('{{cite journal|volume=2|issue=3}}', $template->parsed_text());
    $template->rename('volume', 'issue');
    $this->assertSame('{{cite journal|issue=2}}', $template->parsed_text());
    $template->forget('issue');
    $this->assertSame('{{cite journal}}', $template->parsed_text());
    $this->assertNull($template->get('issue'));
    $this->assertNull($template->get('doi'));
    $this->assertNull($template->get('volume'));
  }
    
  public function testArxivMore1() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. Lett. 117, 211101 (2016)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2016', $expanded->get('year'));
    $this->assertSame('211101', $expanded->get('pages'));
  }
    
  public function testArxivMore2() {
    $text = "{{cite arxiv}}" ;
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. B 79, 115202 (2009)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2009', $expanded->get('year'));
    $this->assertSame('115202', $expanded->get('pages'));
  }
    
  public function testArxivMore3() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Acta Phys. Polon. B41 (2010), 2325-2333", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2010', $expanded->get('year'));
    $this->assertSame('2325–2333', $expanded->get('pages'));
  }
    
  public function testArxivMore4() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. B 78, 245315 (2008)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2008', $expanded->get('year'));
    $this->assertSame('245315', $expanded->get('pages'));
  }
    
  public function testArxivMore5() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal of Noses37:1234,2012", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2012', $expanded->get('year'));
    $this->assertSame('1234', $expanded->get('pages'));
    $this->assertSame('37', $expanded->get('volume'));
  }

  public function testArxivMore6() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("", $expanded, TRUE);  // Make sure that empty string does not crash
    $this->assertSame('cite arxiv', $expanded->wikiname());
  }
   
  public function testArxivMore7() {
    $text = "{{cite arxiv|date=1999}}"; // verify date update
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal 78 (2011) 888-999", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2011', $expanded->get('year'));
    $this->assertSame('888–999', $expanded->get('pages'));
  }

  public function testArxivMore8() {
    $text = "{{cite arxiv|year=1999}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal, 11, 62 (2001)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2001', $expanded->get('year'));
    $this->assertSame('62', $expanded->get('pages'));
  }
    
  public function testArxivMore9() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal, 83:13232, 2018", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2018', $expanded->get('year'));
    $this->assertSame('13232', $expanded->get('pages'));
  } 
  public function testArxivMore10() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal 1 (4), 2311 (1980)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('1980', $expanded->get('year'));
    $this->assertSame('2311', $expanded->get('pages'));
  }
    
  public function testArxivMore11() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("ZooKeys 212 (1999), 032412332, 33 pages", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('1999', $expanded->get('year'));
    $this->assertSame('032412332', $expanded->get('pages'));
  }
 
  public function testArxivMore12() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("A&A 619, A49 (2018)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2018', $expanded->get('year'));
    $this->assertSame('Astronomy & Astrophysics', $expanded->get('journal'));
    $this->assertSame('A49', $expanded->get('volume'));
    $this->assertSame('619', $expanded->get('pages'));
  }
 
  public function testArxivMore13() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("ApJ, 767:L7, 2013 April 10", $expanded, TRUE);
    $this->assertSame('The Astrophysical Journal', $expanded->get('journal'));
    $this->assertSame('2013', $expanded->get('year'));
  }
 
  public function testArxivMore14() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Astrophys.J.639:L43-L46,2006F", $expanded, TRUE);
    $this->assertSame('The Astrophysical Journal', $expanded->get('journal'));
    $this->assertSame('2006', $expanded->get('year'));
  }

  public function testArxivMore15() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Information Processing Letters 115 (2015), pp. 633-634", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2015', $expanded->get('year'));
    $this->assertSame('633–634', $expanded->get('pages'));
  }

  public function testArxivMore16() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Theoretical Computer Science, Volume 561, Pages 113-121, 2015", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2015', $expanded->get('year'));
    $this->assertSame('113–121', $expanded->get('pages'));
  }

  public function testArxivMore17() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Scientometrics, volume 69, number 3, pp. 669-687, 2006", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2006', $expanded->get('year'));
    $this->assertSame('669–687', $expanded->get('pages'));
  }

  public function testArxivMore18() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("International Journal of Geographical Information Science, 23(7), 2009, 823-837.", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2009', $expanded->get('year'));
    $this->assertSame('823–837', $expanded->get('pages'));
  }
  
  public function testArxivMore19() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("journal of Statistical Mechanics: Theory and Experiment, 2008 July", $expanded, TRUE);
    $this->assertSame('cite arxiv', $expanded->wikiname());
    $this->assertNull($expanded->get('year'));
  }
 
   public function testDoiInline() {
    $text = '{{citation | title = {{doi-inline|10.1038/nature10000|Funky Paper}} }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Nature', $expanded->get('journal'));
    $this->assertSame('Funky Paper', $expanded->get('title'));
    $this->assertSame('10.1038/nature10000', $expanded->get('doi'));
    
    $text = '{{citation | title = {{doi-inline|10.1038/nature10000|Funky Paper}} | doi=10.1038/nature10000 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Nature', $expanded->get('journal'));
    $this->assertSame('Funky Paper', $expanded->get('title'));
    $this->assertSame('10.1038/nature10000', $expanded->get('doi'));
  } 
  
  public function testPagesDash() {
    $text = '{{cite journal|pages=1-2|title=do change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1–2', $prepared->get('pages'));
    
    $text = '{{cite journal|at=1-2|title=do not change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1-2', $prepared->get('at'));
    
    $text = '{{cite journal|pages=[http://bogus.bogus/1–2/ 1–2]|title=do not change }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('[http://bogus.bogus/1–2/ 1–2]', $prepared->get('pages'));

    $text = '{{Cite journal|pages=15|doi=10.1016/j.biocontrol.2014.06.004}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('15–22', $expanded->get('pages')); // Converted should use long dashes

    $text = '{{Cite journal|doi=10.1007/s11746-998-0245-y|at=pp.425–439, see Table&nbsp;2 p.&nbsp;426 for tempering temperatures}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('pp.425–439, see Table&nbsp;2 p.&nbsp;426 for tempering temperatures', $expanded->get('at')); // Leave complex at=

    $text = '{{cite book|pages=See [//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]}}';
    $expanded = $this->process_citation($text); // Do not change this hidden URL
    $this->assertSame('See [//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]', $expanded->get('pages'));
   
    $text = '{{cite book|pages=[//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]}}';
    $expanded = $this->process_citation($text); // Do not change dashes in this hidden URL, but upgrade URL to real one
    $this->assertSame('[https://books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]', $expanded->get('pages'));
   
    $text = '{{cite journal|pages=AB-2|title=do change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('AB-2', $prepared->get('pages'));
  }
 
  public function testBogusPageRanges() {  // Just keep incrementing year when test ages out
    $text = '{{Cite journal| doi = 10.1017/jpa.2018.43|title = New well-preserved scleritomes of Chancelloriida from early Cambrian Guanshan Biota, eastern Yunnan, China|journal = Journal of Paleontology|volume = 92|issue = 6|pages = 1–17|year = 2020|last1 = Zhao|first1 = Jun|last2 = Li|first2 = Guo-Biao|last3 = Selden|first3 = Paul A}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('955–971', $expanded->get('pages')); // Converted should use long dashes
    $text = '{{Cite journal| doi = 10.1017/jpa.2018.43|pages = 960}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('960', $expanded->get('pages')); // Existing page number was within existing range
  }
    
  public function testCollapseRanges() {
    $text = '{{cite journal|pages=1233-1233|year=1999-1999}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1233', $prepared->get('pages'));
    $this->assertSame('1999', $prepared->get('year'));
  }
    
  public function testSmallWords() {
    $text = '{{cite journal|journal=A Word in ny and n y About cow And Then boys the U S A and y and z}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('A Word in NY and N Y About Cow and then Boys the U S A and y and Z', $prepared->get('journal')); 
    $text = '{{cite journal|journal=Ann of Math}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Ann of Math', $prepared->get('journal')); 
    $text = '{{cite journal|journal=Ann. of Math.}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Ann. of Math.', $prepared->get('journal')); 
    $text = '{{cite journal|journal=Ann. of Math}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Ann. of Math', $prepared->get('journal')); 
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
    $text = '{{cite book |date=March 12, 1913 |title=Session Laws of the State of Washington, 1913 |chapter=Chapter 65: Classifying Public Highways |page=221 |chapterurl=http://leg.wa.gov/CodeReviser/documents/sessionlaw/1913c65.pdf |publisher=Washington State Legislature |accessdate=August 30, 2018}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('accessdate'));
  }

  public function testIgnoreUnkownCiteTemplates() {
    $text = "{{Cite imaginary source | http://google.com | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6|doi=10.bad/bad }}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
  
  public function testJustAnISBN() {
     $text = '{{cite book |isbn=0471186368}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('Explosives engineering', $expanded->get('title'));
     $this->assertNull($expanded->get('url'));
  }
    
  public function testArxivPDf() {
    $text = '{{cite web|url=https://arxiv.org/ftp/arxiv/papers/1312/1312.7288.pdf}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('1312.7288', $expanded->get('arxiv'));  
  }
  
  public function testEmptyCitations() {
    $text = 'bad things like {{cite journal}}{{cite book|||}}{{cite arxiv}}{{cite web}} should not crash bot'; // bot removed pipes
    $expanded = $this->process_page($text);
    $this->assertSame('bad things like {{cite journal}}{{cite book}}{{cite arxiv}}{{cite web}} should not crash bot', $expanded->parsed_text());
  }

  public function testLatexMathInTitle() { // This contains Math stuff that should be z~10, but we just verify that we do not make it worse at this time.  See https://tex.stackexchange.com/questions/55701/how-do-i-write-sim-approximately-with-the-correct-spacing
    $text = "{{Cite arxiv|eprint=1801.03103}}";
    $expanded = $this->process_citation($text);
    $title = $expanded->get('title');
    // For some reason we sometimes get the first one
    $title1 = 'A Candidate $z\sim10$ Galaxy Strongly Lensed into a Spatially Resolved Arc';
    $title2 = 'RELICS: A Candidate z ∼ 10 Galaxy Strongly Lensed into a Spatially Resolved Arc';
    if (in_array($title, [$title1, $title2])) {
       $this->assertTrue(TRUE);
    } else {
       $this->assertTrue($title); // What did we get
    }
  }

  public function testDropGoogleWebsite() {
    $text = "{{Cite book|website=Google.Com|url=http://Invalid.url.not-real.com/}}"; // Include a fake URL so that we are not testing: if (no url) then drop website
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('website'));
  }

  public function testHornorificInTitle() { // compaints about this
    $text = "{{cite book|title=Letter from Sir Frederick Trench to the Viscount Duncannon on his proposal for a quay on the north bank of the Thames|url=https://books.google.com/books?id=oNBbAAAAQAAJ|year=1841}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('Trench', $expanded->get('last1'));
    $this->assertSame('Frederick William', $expanded->get('first1')); 
  }

  public function testPageRange() {
    $text = '{{Citation|doi=10.3406/befeo.1954.5607}}' ;
    $expanded = $this->process_citation($text);
    $this->assertSame('405–554', $expanded->get('pages'));
  }

  public function testUrlConversions() {
    $text = '{{cite journal | url= https://mathscinet.ams.org/mathscinet-getitem?mr=0012343 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('0012343', $prepared->get('mr'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234231}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1234231', $prepared->get('ssrn'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url=https://www.osti.gov/biblio/2341}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('2341', $prepared->get('osti'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url=https://www.osti.gov/energycitations/product.biblio.jsp?osti_id=2341}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('2341', $prepared->get('osti'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url=https://zbmath.org/?format=complete&q=an:1111.22222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1111.22222', $prepared->get('zbl'));
    $this->assertNull($prepared->get('url'));
    
    $text = '{{cite journal | url=https://zbmath.org/?format=complete&q=an:11.2222.44}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('11.2222.44', $prepared->get('jfm'));
    $this->assertNull($prepared->get('url'));
      
    $text = '{{cite journal |url=http://citeseerx.ist.psu.edu/viewdoc/download?doi=10.1.1.923.345&rep=rep1&type=pdf}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('10.1.1.923.345', $prepared->get('citeseerx'));
    $text = '{{cite journal |url=http://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.923.345}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('10.1.1.923.345', $prepared->get('citeseerx'));
 
    $text = '{{cite journal | archiveurl= https://mathscinet.ams.org/mathscinet-getitem?mr=0012343 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('0012343', $prepared->get('mr'));
    $this->assertNull($prepared->get('archiveurl'));
  }
    
  public function testStripPDF() {
    $text = '{{cite journal |url=https://link.springer.com/content/pdf/10.1007/BF00428580.pdf}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('10.1007/BF00428580', $prepared->get('doi'));
  }
    
  public function testRemoveQuotes() {
    $text = '{{cite journal|title="Strategic Acupuncture"}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Strategic Acupuncture', $prepared->get('title'));  
  }
  
  public function testTrimResearchGateETC() {
    $want = 'https://www.researchgate.net/publication/320041870';
    $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame($want, $prepared->get('url'));
    $text = '{{cite journal|url=https://www.researchgate.net/profile/hello_user-person/publication/320041870_EXTRA_STUFF_ON_EN}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame($want, $prepared->get('url'));

    $text = '{{cite web|url=http://acADemia.EDU/123456/extra_stuff}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('https://www.academia.edu/123456', $prepared->get('url'));
   
    $text = '{{cite web|url=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8#The_hash#The_second_hash}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22#The_hash', $prepared->get('url'));
  }
 
  public function testCleanRGTitles() {
    $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup|title=Hello {{!}} Request PDF}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Hello', $prepared->get('title'));
    $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup|title=(PDF) Hello}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Hello', $prepared->get('title'));
  }
 
  public function testHTMLNotLost() {
    $text = '{{cite journal|last=&ndash;|first=&ndash;|title=&ndash;|journal=&ndash;|edition=&ndash;|pages=&ndash;}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame($text, $prepared->parsed_text());
  }
 
  public function testDoiValidation() {
    $text = '{{cite web|last=Daintith|first=John|title=tar|url=http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022|work=Oxford University Press|publisher=A dictionary of chemistry|edition=6th|accessdate=14 March 2013}}';
    $prepared = $this->prepare_citation($text);
    $this->assertNull($prepared->get('doi'));
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi'));
  }
    
  public function testVolumeIssueDemixing() {
    $text = '{{cite journal|volume = 12(44)}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('44', $prepared->get('issue'));
    $this->assertSame('12', $prepared->get('volume'));
    $text = '{{cite journal|volume = 12(44-33)}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('44–33', $prepared->get('issue'));
    $this->assertSame('12', $prepared->get('volume'));
    $text = '{{cite journal|volume = 12(44-33)| number=222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('222', $prepared->get('number'));
    $this->assertSame('12(44-33)', $prepared->get('volume'));
   
    $text = '{{cite journal|volume = 12, no. 44-33}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('44–33', $prepared->get('issue'));
    $this->assertSame('12', $prepared->get('volume'));
    $text = '{{cite journal|volume = 12, no. 44-33| number=222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('222', $prepared->get('number'));
    $this->assertSame('12, no. 44-33', $prepared->get('volume'));

    $text = '{{cite journal|volume = 12.33}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('33', $prepared->get('issue'));
    $this->assertSame('12', $prepared->get('volume'));
    $text = '{{cite journal|volume = 12.33| number=222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('222', $prepared->get('number'));
    $this->assertSame('12.33', $prepared->get('volume'));

    $text = '{{cite journal|volume = Volume 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get('volume'));
   
    $text = '{{cite book|volume = Volume 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Volume 12', $prepared->get('volume'));
  }
 
   public function testVolumeIssueDemixing2() {
    $text = '{{cite journal|volume = number 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('number 12', $prepared->get('volume'));
    $this->assertNull($prepared->get('issue'));
  }
 
   public function testVolumeIssueDemixing3() {
    $text = '{{cite journal|volume = number 12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get('issue'));
    $this->assertNull($prepared->get('volume'));
  }
 
   public function testVolumeIssueDemixing4() {
    $text = '{{cite journal|volume = number 12|issue=12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertNull($prepared->get('volume'));
    $this->assertSame('12', $prepared->get('issue'));
  }
 
   public function testVolumeIssueDemixing6() {
    $text = '{{cite journal|volume = number 12|issue=12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertNull($prepared->get('volume'));
    $this->assertSame('12', $prepared->get('issue'));
  }
 
   public function testVolumeIssueDemixing7() {
    $text = '{{cite journal|issue = number 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get('issue'));
  }
 
   public function testVolumeIssueDemixing8() {
    $text = '{{cite journal|volume = v. 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get('volume'));
  }
 
   public function testVolumeIssueDemixing9() {
    $text = '{{cite journal|issue =(12)}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get('issue'));
  }
 
   public function testVolumeIssueDemixing10() {
    $text = '{{cite journal|issue = volume 8, issue 7}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('7', $prepared->get('issue'));
    $this->assertSame('8', $prepared->get('volume'));
  }
 
   public function testVolumeIssueDemixing11() {
    $text = '{{cite journal|issue = volume 8, issue 7|volume=8}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('7', $prepared->get('issue'));
    $this->assertSame('8', $prepared->get('volume'));
  }
 
   public function testVolumeIssueDemixing12() {
    $text = '{{cite journal|issue = volume 8, issue 7|volume=9}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('volume 8, issue 7', $prepared->get('issue'));
    $this->assertSame('9', $prepared->get('volume'));
  }

   public function testVolumeIssueDemixing13() {
    $text = '{{cite journal|issue = number 333XV }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('333XV', $prepared->get('issue'));
    $this->assertNull($prepared->get('volume'));
  }
 
  public function testCleanUpPages() {
    $text = '{{cite journal|pages=p.p. 20-23}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('20–23', $prepared->get('pages')); // Drop p.p. and upgraded dashes
  }
 
  public function testSpaces() {
      // None of the "spaces" in $text are normal spaces.  They are U+2000 to U+200A
      $text     = "{{cite book|title=X X X X X X X X X X X X}}";
      $text_out = '{{cite book|title=X X X X X X X X X X X X}}';
      $expanded = $this->process_citation($text);
      $this->assertSame($text_out, $expanded->parsed_text());
      $this->assertTrue($text != $text_out); // Verify test is valid -- We want to make sure that the spaces in $text are not normal spaces
  }
 
  public function testMultipleYears() {
    $text = '{{cite journal|doi=10.1080/1323238x.2006.11910818}}'; // Crossref has <year media_type="online">2017</year><year media_type="print">2006</year>
    $expanded = $this->process_citation($text);
    $this->assertSame('2006', $expanded->get('year'));
  }
 
  public function testDuplicateParametersFlagging() {
    $text = '{{cite web|year=2010|year=2011}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('2011', $expanded->get('year'));
    $this->assertSame('2010', $expanded->get('DUPLICATE_year'));
    $text = '{{cite web|year=|year=2011}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('2011', $expanded->get('year'));
    $this->assertNull($expanded->get('DUPLICATE_year'));
    $text = '{{cite web|year=2011|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('2011', $expanded->get('year'));
    $this->assertNull($expanded->get('DUPLICATE_year'));
    $text = '{{cite web|year=|year=|year=2011|year=|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('2011', $expanded->get('year'));
    $this->assertNull($expanded->get('DUPLICATE_year'));
    $text = '{{cite web|year=|year=|year=|year=|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('{{cite web|year=}}', $expanded->parsed_text());
  }
 
  public function testBadPMIDSearch() { // Matches a PMID search
    $text = '{{cite journal |author=Fleming L |title=Ciguatera Fish Poisoning}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('pmid'));
    $this->assertNull($expanded->get('doi'));
  }
 
  public function testDoiThatIsJustAnISSN() {
    $text = '{{cite web |url=http://onlinelibrary.wiley.com/journal/10.1002/(ISSN)1099-0739/homepage/EditorialBoard.html}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1002/(ISSN)1099-0739', $expanded->get('doi'));
    $this->assertSame('http://onlinelibrary.wiley.com/journal/10.1002/(ISSN)1099-0739/homepage/EditorialBoard.html', $expanded->get('url'));
    $this->assertSame('cite web', $expanded->wikiname());
  }
 
  public function testEditors() {
    $text = '{{cite journal|editor3=Set}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('editor3-last', 'SetItL');
    $prepared->add_if_new('editor3-first', 'SetItF');
    $prepared->add_if_new('editor3', 'SetItN');
    $this->assertSame('Set', $prepared->get('editor3'));
    $this->assertNull($prepared->get('editor3-last'));
    $this->assertNull($prepared->get('editor3-first'));
   
    $text = '{{cite journal}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('editor3-last', 'SetItL');
    $prepared->add_if_new('editor3-first', 'SetItF');
    $prepared->add_if_new('editor3', 'SetItN'); // Should not get set
    $this->assertSame('SetItL', $prepared->get('editor3-last'));
    $this->assertSame('SetItF', $prepared->get('editor3-first'));
    $this->assertNull($prepared->get('editor3'));
   
    $text = '{{cite journal}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('editor33333-last', 'SetIt'); // Huge number
    $this->assertSame('SetIt', $prepared->get('editor33333-last'));
  }
 
  public function testFixRubbishVolumeWithDoi() {
    $text = '{{Cite journal|doi= 10.1136/bmj.2.3798.759-a |volume=3798 |issue=3798}}';
    $template = $this->prepare_citation($text);
    $template->final_tidy(); 
    $this->assertSame('3798', $template->get('issue'));
    $this->assertSame('2', $template->get('volume'));
  }
  
  public function testHandles1() {
    $template = $this->make_citation('{{Cite web|url=http://hdl.handle.net/10125/20269////|journal=X}}');
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('10125/20269', $template->get('hdl'));
    $this->assertSame('cite journal', $template->wikiname());
    $this->assertNull($template->get('url'));
  }
 
  public function testHandles2() {
    $template = $this->make_citation('{{Cite web|url=https://hdl.handle.net/handle////10125/20269}}');
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('cite document', $template->wikiname());
    $this->assertSame('10125/20269', $template->get('hdl'));
    $this->assertNull($template->get('url'));
  }
 
  public function testHandles3() {
    $template = $this->make_citation('{{Cite journal|url=http://hdl.handle.net/handle/10125/dfsjladsflhdsfaewfsdfjhasjdfhldsaflkdshkafjhsdjkfhdaskljfhdsjklfahsdafjkldashafldsfhjdsa_TEST_DATA_FOR_BOT_TO_FAIL_ON}}');
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('http://hdl.handle.net/handle/10125/dfsjladsflhdsfaewfsdfjhasjdfhldsaflkdshkafjhsdjkfhdaskljfhdsjklfahsdafjkldashafldsfhjdsa_TEST_DATA_FOR_BOT_TO_FAIL_ON', $template->get('url'));
    $this->assertNull($template->get('hdl'));
  }
 
  public function testHandles4() {
    $template = $this->make_citation('{{Cite journal|url=http://digitallibrary.amnh.org/dataset.xhtml?persistentId=hdl:10125/20269;jsessionid=EE3BA49390611FCE0AAAEBB819E777BC?sequence=1}}');
    $template->get_identifiers_from_url();
    $this->assertSame('10125/20269', $template->get('hdl'));
    $this->assertNull($template->get('url'));
  }
 
  public function testAuthorToLast() {
    $text = '{{Cite journal|author1=Last|first1=First}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('Last', $template->get('last1'));
    $this->assertSame('First', $template->get('first1'));
    $this->assertNull($template->get('author1'));
   
    $text = '{{Cite journal|author1=Last|first2=First}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('Last', $template->get('author1'));
    $this->assertSame('First', $template->get('first2'));
    $this->assertNull($template->get('last1'));
  }
 
  public function testAddArchiveDate() {
    $text = '{{Cite web|archive-url=https://web.archive.org/web/20190521084631/https://johncarlosbaez.wordpress.com/2018/09/20/patterns-that-eventually-fail/|archive-date=}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('2019-05-21', $template->get('archive-date'));
  }
 
  public function testAddWebCiteDate() {
    $text = '{{Cite web|archive-url=https://www.webcitation.org/6klgx4ZPE}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('2016-09-24', $template->get('archive-date'));
  }
 
  public function testJunkData() {
    $text = "{{Cite web | title=JSTOR THIS IS A LONG TITLE IN ALL CAPPS AND IT IS BAD|pmid=1974135}} " . 
            "{{Cite web | title=JSTOR This is bad data|journal=JSTOR This is bad data|jstor=1974136}}" .
            "{{Cite web | title=JSTOR This is a title on JSTOR|pmc=1974137}}" .
            "{{Cite web | title=JSTOR This is a title with IEEE Xplore Document|pmid=1974138}}" .
            "{{Cite web | title=JSTOR This is a title document with Volume 3 and page 5|doi= 10.1021/jp101758y}}";
    $page = $this->process_page($text);
    $this->assertSame(0, substr_count($page->parsed_text(), 'JSTOR'));
  }
 
  public function testISSN(){
    $text = '{{Cite journal|journal=Yes}}';
    $template = $this->prepare_citation($text);
    $template->add_if_new('issn', '1111-2222');
    $this->assertNull($template->get('issn'));
    $template->add_if_new('issn_force', '1111-2222');
    $this->assertSame('1111-2222', $template->get('issn'));
    $text = '{{Cite journal|journal=Yes}}';
    $template = $this->prepare_citation($text);
    $template->add_if_new('issn_force', 'EEEE-3333'); // Won't happen
    $this->assertNull($template->get('issn'));
  }
 
  public function testURLS() {
    $text='{{cite journal|conference-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get('mr'));
    $text='{{cite journal|conferenceurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get('mr'));                
    $text='{{cite journal|contribution-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get('mr'));
    $text='{{cite journal|contributionurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get('mr'));
    $text='{{cite journal|article-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get('mr'));
  }
 
  public function testTidy1() {
    $text = '{{cite web|postscript = <!-- A comment only --> }}';
    $template = $this->process_citation($text);
    $this->assertNull($template->get('postscript'));
  }
 
  public function testTidy1a() {
    $text = '{{cite web|postscript = <!-- A comment only --> {{Some Template}} }}';
    $template = $this->process_citation($text);
    $this->assertNull($template->get('postscript'));
  }
 
  public function testTidy2() {
    $text = '{{citation|issue="Something Special"}}';
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertSame('Something Special', $template->get('issue'));
  }
 
  public function testTidy3() {
    $text = "{{citation|issue=Dog \t\n\r\0\x0B }}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertSame('Dog', $template->get('issue'));
  }

   public function testTidy4() {
    $text = "{{citation|issue=Dog &nbsp;}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertSame('Dog', $template->get('issue'));
  }
 
  public function testTidy5() {
    $text = '{{citation|issue=&nbsp; Dog }}';
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertSame('Dog', $template->get('issue'));
  }
 
  public function testTidy5b() {
    $text = "{{citation|agency=California Department of Public Health|publisher=California Tobacco Control Program}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('agency');
    $this->assertSame('California Department of Public Health', $template->get('publisher'));
    $this->assertNull($template->get('agency'));
  }

  public function testTidy6() {
    $text = "{{cite web|arxiv=xxxxxxxxxxxx}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('arxiv');
    $this->assertSame('cite arxiv', $template->wikiname());
  }
 
  public function testTidy6b() {
    $text = "{{cite web|author=X|authors=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('author');
    $this->assertSame('X', $template->get('DUPLICATE_authors'));
  }

  public function testTidy7() {
    $text = "{{cite web|author1=[[Hoser|Yoser]]}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('author1');
    $this->assertSame('Yoser', $template->get('author1'));
    $this->assertSame('Hoser', $template->get('author1-link'));
  }

  public function testTidy8() {
    $text = "{{cite web|bibcode=abookthisis}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('bibcode');
    $this->assertSame('cite book', $template->wikiname());
  }

  public function testTidy9() {
    $text = "{{cite web|title=XXX|chapter=XXX}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter');
    $this->assertNull($template->get('chapter'));
  }

  public function testTidy10() {
    $text = "{{cite web|doi=10.1267/science.040579197}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('doi');
    $this->assertNull($template->get('doi'));
  }

  public function testTidy11() {
    $text = "{{cite web|doi=10.5284/1000184}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('doi');
    $this->assertNull($template->get('doi'));
  }

  public function testTidy12() {
    $text = "{{cite web|doi=10.5555/TEST_DATA}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('doi');
    $this->assertNull($template->get('doi'));
    $this->assertNull($template->get('url'));
  }

  public function testTidy13() {
    $text = "{{cite web|format=Accepted manuscript}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('format');
    $this->assertNull($template->get('format'));
  }

  public function testTidy14() {
    $text = "{{cite web|format=Submitted manuscript}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('format');
    $this->assertNull($template->get('format'));
  }
 
  public function testTidy15() {
    $text = "{{cite web|format=Full text}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('format');
    $this->assertNull($template->get('format'));
  }           
           
  public function testTidy16() {
    $text = "{{cite web|chapter-format=Accepted manuscript}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get('chapter-format'));
  }

  public function testTidy17() {
    $text = "{{cite web|chapter-format=Submitted manuscript}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get('chapter-format'));
  }
 
  public function testTidy18() {
    $text = "{{cite web|chapter-format=Full text}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get('chapter-format'));
  }
  
  public function testTidy19() {
    $text = "{{cite web|chapter-format=portable document format|chapter-url=http://www.x.com/stuff.pdf}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get('chapter-format'));
    $text = "{{cite web|format=portable document format|url=http://www.x.com/stuff.pdf}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('format');
    $this->assertNull($template->get('format'));
  }
           
  public function testTidy20() {
    $text = "{{cite web|chapter-format=portable document format|chapterurl=http://www.x.com/stuff.pdf}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get('chapter-format'));
  }
 
  public function testTidy21() {
    $text = "{{cite web|chapter-format=portable document format}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get('chapter-format'));
  }

  public function testTidy22() {
    $text = "{{cite web|periodical=X,}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('periodical');
    $this->assertSame('X', $template->get('periodical'));
  }
           
  public function testTidy23() {
    $text = "{{cite journal|magazine=Xyz}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('magazine');
    $this->assertSame('Xyz', $template->get('journal'));
  }
       
  public function testTidy24() {
    $text = "{{cite journal|others=|day=|month=}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('others');
    $template->tidy_parameter('day');
    $template->tidy_parameter('month');
    $this->assertSame('{{cite journal}}', $template->parsed_text());
  }
         
  public function testTidy25() {
    $text = "{{cite journal|archivedate=X|archive-date=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archivedate');
    $this->assertNull($template->get('archivedate'));
  }
 
  public function testTidy26() {
    $text = "{{cite journal|newspaper=X|publisher=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get('publisher'));
  }
               
  public function testTidy27() {
    $text = "{{cite journal|publisher=Proquest|thesisurl=proquest}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get('publisher'));
    $this->assertSame('ProQuest', $template->get('via'));
  }

  public function testTidy28() {
    $text = "{{cite journal|url=stuff.maps.google.stuff|publisher=something from google land}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Google Maps', $template->get('publisher'));
  }

  public function testTidy29() {
    $text = "{{cite journal|journal=X|publisher=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get('publisher'));
  }

  public function testTidy30() {
    $text = "{{cite journal|series=Methods of Molecular Biology|journal=biomaas}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('series');
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('biomaas', $template->get('journal'));
  }
           
  public function testTidy31() {
    $text = "{{cite journal|series=Methods of Molecular Biology|journal=Methods of Molecular Biology}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('series');
    $this->assertSame('cite book', $template->wikiname());
    $this->assertNull($template->get('journal'));
  }

  public function testTidy32() {
    $text = "{{cite journal|title=A title (PDF)|pmc=1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('title');
    $this->assertSame('A title', $template->get('title'));
  }
                      
  public function testTidy34() {
    $text = "{{cite journal|archive-url=http://web.archive.org/web/save/some_website}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archive-url');
    $this->assertNull($template->get('archive-url'));
  }
      
  public function testTidy35() {
    $text = "{{cite journal|archive-url=XYZ|url=XYZ}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archive-url');
    $this->assertNull($template->get('archive-url'));
  }
    
  public function testTidy36() {
    $text = "{{cite journal|series=|periodical=Methods of Molecular Biology}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('periodical');
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Methods of Molecular Biology', $template->get('series'));
  }
            
  public function testTidy37() {
    $text = "{{cite journal|series=Methods of Molecular Biology|periodical=Methods of Molecular Biology}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('periodical');
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Methods of Molecular Biology', $template->get('series'));
    $this->assertNull($template->get('periodical'));
  } 

  public function testTidy38() {
    $text = "{{cite journal|archiveurl=http://researchgate.net/publication/1234_feasdfafdsfsd|title=(PDF) abc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://www.researchgate.net/publication/1234', $template->get('archiveurl'));
    $this->assertSame('abc', $template->get('title'));
  }

  public function testTidy39() {
    $text = "{{cite journal|archiveurl=http://academia.edu/documents/1234_feasdfafdsfsd}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://www.academia.edu/1234', $template->get('archiveurl'));
  }
 
  public function testTidy40() {
    $text = "{{cite journal|archiveurl=https://zenodo.org/record/1234/files/dsafsd}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://zenodo.org/record/1234', $template->get('archiveurl'));
  }

  public function testTidy42() {
    $text = "{{cite journal|archiveurl=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22', $template->get('archiveurl'));
  }
 
  public function testTidy43() {
    $text = "{{cite journal|archiveurl=https://sciencedirect.com/stuff_stuff?via=more_stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://sciencedirect.com/stuff_stuff', $template->get('archiveurl'));
  }
 
  public function testTidy44() {
    $text = "{{cite journal|archiveurl=https://bloomberg.com/stuff_stuff?utm_=more_stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://bloomberg.com/stuff_stuff', $template->get('archiveurl'));
  }

  public function testTidy45() {
    $text = "{{cite journal|url=http://researchgate.net/publication/1234_feasdfafdsfsd|title=(PDF) abc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.researchgate.net/publication/1234', $template->get('url'));
    $this->assertSame('abc', $template->get('title'));
  }

  public function testTidy46() {
    $text = "{{cite journal|url=http://academia.edu/documents/1234_feasdfafdsfsd}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.academia.edu/1234', $template->get('url'));
  }
 
  public function testTidy47() {
    $text = "{{cite journal|url=https://zenodo.org/record/1234/files/dfasd}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://zenodo.org/record/1234', $template->get('url'));
  }

  public function testTidy48() {
    $text = "{{cite journal|url=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22', $template->get('url'));
  }
 
  public function testTidy49() {
    $text = "{{cite journal|url=https://sciencedirect.com/stuff_stuff?via=more_stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://sciencedirect.com/stuff_stuff', $template->get('url'));
  }
 
  public function testTidy50() {
    $text = "{{cite journal|url=https://bloomberg.com/stuff_stuff?utm_=more_stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://bloomberg.com/stuff_stuff', $template->get('url'));
  }
    
  public function testTidy51() {
    $text = "{{cite journal|url=https://watermark.silverchair.com/rubbish}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertNull($template->get('url'));
  }

  public function testTidy52() {
    $text = "{{cite journal|url=https://watermark.silverchair.com/rubbish|archiveurl=has_one}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://watermark.silverchair.com/rubbish', $template->get('url'));
  }
 
  public function testTidy53() {
    $text = "{{cite journal|archiveurl=https://watermark.silverchair.com/rubbish}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertNull($template->get('archiveurl'));
  }
 
  public function testTidy53b() {
    $text = "{{cite journal|url=https://s3.amazonaws.com/academia.edu/stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertNull($template->get('url'));
  }
 
  public function testTidy53c() {
    $text = "{{cite journal|archiveurl=https://s3.amazonaws.com/academia.edu/stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertNull($template->get('archiveurl'));
  }
 
  public function testTidy54() {
    $text = "{{cite journal|url=https://ieeexplore.ieee.org.proxy/document/1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://ieeexplore.ieee.org/document/1234', $template->get('url'));
  }
 
  public function testTidy55() {
    $text = "{{cite journal|url=https://www.oxfordhandbooks.com.proxy/view/1234|via=Library}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.oxfordhandbooks.com/view/1234', $template->get('url'));
    $this->assertNull($template->get('via'));
  }

  public function testTidy56() {
    $text = "{{cite journal|url=https://www.oxfordartonline.com.proxy/view/1234|via=me}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.oxfordartonline.com/view/1234', $template->get('url'));
    $this->assertSame('me', $template->get('via'));
  }

  public function testTidy57() {
    $text = "{{cite journal|url=https://sciencedirect.com.proxy/stuff_stuff|via=the via}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.sciencedirect.com/stuff_stuff', $template->get('url'));
  }
 
  public function testTidy58() {
    $text = "{{cite journal|url=https://www.random.com.mutex.gmu/stuff_stuff|via=the via}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.random.com/stuff_stuff', $template->get('url'));
    $this->assertNull($template->get('via'));
  }
 
  public function testTidy59() {
    $text = "{{cite journal|url=https://www-random-com.mutex.gmu/stuff_stuff|via=the via}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.random.com/stuff_stuff', $template->get('url'));
    $this->assertNull($template->get('via'));
  }

  public function testTidy60() {
    $text = "{{cite journal|url=http://proxy/url=https://go.galegroup.com%2fpsSTUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://go.galegroup.com/psSTUFF', $template->get('url'));
  }
 
  public function testTidy61() {
    $text = "{{cite journal|url=http://proxy/url=https://go.galegroup.com/STUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://go.galegroup.com/STUFF', $template->get('url'));
  }

  public function testTidy62() {
    $text = "{{cite journal|url=http://proxy/url=https://link.galegroup.com%2fpsSTUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://link.galegroup.com/psSTUFF', $template->get('url'));
  }
 
  public function testTidy63() {
    $text = "{{cite journal|url=http://proxy/url=https://link.galegroup.com/STUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://link.galegroup.com/STUFF', $template->get('url'));
  }
 
  public function testTidy64() {
    $text = "{{cite journal|url=https://go.galegroup.com/STUFF&u=UNIV&date=1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://go.galegroup.com/STUFF&date=1234', $template->get('url'));
  }

  public function testTidy65() {
    $text = "{{cite journal|url=https://link.galegroup.com/STUFF&u=UNIV&date=1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://link.galegroup.com/STUFF&date=1234', $template->get('url'));
  }
 
  public function testTidy66() {
    $text = "{{cite journal|url=https://search.proquest.com/STUFF/docview/1234/STUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234/STUFF', $template->get('url'));
  }
 
 public function testTidy66b() {
    $text = "{{cite journal|url=http://host.com/login?url=https://search-proquest-com-stuff/STUFF/docview/1234/34123/342}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234', $template->get('url'));
  }
 
 public function testTidy67() {
    $text = "{{cite journal|url=https://0-search-proquest-com.schoo.org/STUFF/docview/1234/2314/3214}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234', $template->get('url'));
  }
 
  public function testTidy68() {
    $text = "{{cite journal|url=http://proxy-proquest.umi.com-org/pqd1234}}"; // Bogus, so deleted
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertNull($template->get('url'));
  }
 
  public function testTidy69() {
    $text = "{{cite journal|url=https://search.proquest.com/dissertations/docview/1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/dissertations/docview/1234', $template->get('url'));
  }
 
  public function testTidy70() {
    $text = "{{cite journal|url=https://search.proquest.com/docview/1234/fulltext}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234', $template->get('url'));
  }
 
  public function testTidy70b() {
    $text = "{{cite journal|url=https://search.proquest.com/docview/1234?account=XXXXX}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234', $template->get('url'));
  }
 
  public function testTidy71() {
    $text = "{{cite journal|pmc = pMC12341234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('pmc');
    $this->assertSame('12341234', $template->get('pmc'));
  }
 
  public function testTidy72() {
    $text = "{{cite journal|quotes=false}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('quotes');
    $this->assertNull($template->get('quotes'));
    $text = "{{cite journal|quotes=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('quotes');
    $this->assertNull($template->get('quotes'));
    $text = "{{cite journal|quotes=Hello There}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('quotes');
    $this->assertSame('Hello There', $template->get('quotes'));
  }
 
   public function testTidy73() {
    $text = "{{cite web|journal=www.cnn.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('journal');
    $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    
    $text = "{{cite web|journal=cnn.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('journal');
    $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    
    $text = "{{cite web|journal=theweb.org}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('journal');
    $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    
    $text = "{{cite web|journal=theweb.net}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('journal');
    $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    
    $text = "{{cite web|journal=the web.net}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('journal');
    $this->assertNull($template->get('website'));
  }
 
  public function testIncomplete() {
    $text = "{{cite book|url=http://perma-archives.org/pqd1234|isbn=Xxxx|title=xxx|issue=a|volume=x}}"; // Non-date website
    $template = $this->make_citation($text);
    $this->assertTrue($template->profoundly_incomplete());
    $this->assertTrue($template->profoundly_incomplete('http://perma-archives.org/pqd1234'));
    $text = "{{cite book|url=http://a_perfectly_acceptable_website/pqd1234|isbn=Xxxx|issue=hh|volume=rrfff|title=xxx}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->profoundly_incomplete());
    $this->assertTrue($template->profoundly_incomplete('http://a_perfectly_acceptable_website/pqd1234'));
    $this->assertTrue($template->profoundly_incomplete('http://perma-archives.org/pqd1234'));
    $text = "{{cite book|url=http://perma-archives.org/pqd1234|isbn=Xxxx|title=xxx|issue=a|volume=x|author1=Yes}}"; // Non-date website
    $template = $this->make_citation($text);
    $this->assertTrue($template->profoundly_incomplete());
    $this->assertFalse($template->profoundly_incomplete('http://perma-archives.org/pqd1234'));
    $text = "{{cite web|url=http://foxnews.com/x|website=Fox|title=xxx|issue=a|year=2000}}"; // Non-journal website
    $template = $this->make_citation($text);
    $this->assertTrue($template->profoundly_incomplete());
    $this->assertFalse($template->profoundly_incomplete('http://foxnews.com/x'));
    $text = "{{cite web|url=http://foxnews.com/x|contribution=Fox|title=xxx|issue=a|year=2000}}"; // Non-journal website
    $template = $this->make_citation($text);
    $this->assertTrue($template->profoundly_incomplete());
    $this->assertFalse($template->profoundly_incomplete('http://foxnews.com/x'));
    $text = "{{cite web|url=http://foxnews.com/x|encyclopedia=Fox|title=xxx|issue=a|year=2000}}"; // Non-journal website
    $template = $this->make_citation($text);
    $this->assertTrue($template->profoundly_incomplete());
    $this->assertFalse($template->profoundly_incomplete('http://foxnews.com/x'));
  }
 
  public function testAddEditor() {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('editor1-last', 'Phil'));
    $this->assertSame('Phil', $template->get('editor1-last'));
    $text = "{{cite journal|editor-last=Junk}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('editor1-last', 'Phil'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('editor1', 'Phil'));
    $this->assertSame('Phil', $template->get('editor1'));
    $text = "{{cite journal|editor-last=Junk}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('editor1', 'Phil'));
  }

  public function testAddFirst() {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('first1', 'X M'));
    $this->assertSame('X. M.', $template->get('first1'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('first2', 'X M'));
    $this->assertSame('X. M.', $template->get('first2'));
  }
 
  public function testDisplayEd() {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('display-editors', '3'));
    $this->assertSame('3', $template->get('display-editors'));
    $this->assertFalse($template->add_if_new('displayeditors', '2'));
    $this->assertNull($template->get('displayeditors'));
  }

  public function testArchiveDate() {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_MDY;
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('January 20, 2010', $template->get('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_DMY;       
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('20 January 2010', $template->get('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_WHATEVER;   
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('20 JAN 2010', $template->get('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('archive-date', 'SDAFEWFEWW#F#WFWEFESFEFSDFDFD'));
    $this->assertNull($template->get('archive-date'));
  }
 
  public function testAccessDate1() {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_MDY;
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
    $this->assertSame('January 20, 2010', $template->get('access-date'));
  }
 
  public function testAccessDate2() {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_DMY;       
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
    $this->assertSame('20 January 2010', $template->get('access-date'));
  }
 
  public function testAccessDate3() {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_WHATEVER;   
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
    $this->assertSame('20 JAN 2010', $template->get('access-date'));
  }
 
  public function testAccessDate4() {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('access-date', 'SDAFEWFEWW#F#WFWEFESFEFSDFDFD'));
    $this->assertNull($template->get('access-date'));
  }

  public function testAccessDate5() {
    $text = "{{cite journal}}"; // NO url
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010')); // Pretty bogus return value
    $this->assertNull($template->get('access-date'));
  }
 
  public function testWorkStuff() {
    $text = "{{cite journal|work=Yes Indeed}}";
    $template = $this->make_citation($text);
    $template->add_if_new('journal', 'Yes indeed');
    $this->assertSame('Yes Indeed', $template->get('journal'));
    $this->assertNull($template->get('work'));
    $text = "{{cite journal|work=Yes Indeed}}";
    $template = $this->make_citation($text);
    $template->add_if_new('journal', 'No way sir');
    $this->assertSame('Yes Indeed', $template->get('work'));
    $this->assertNull($template->get('journal'));
  }
 
  public function testViaStuff() {
    $text = "{{cite journal|via=Yes Indeed}}";
    $template = $this->make_citation($text);
    $template->add_if_new('journal', 'Yes indeed');
    $this->assertSame('Yes Indeed', $template->get('journal'));
    $this->assertNull($template->get('via'));
  }
 
  public function testNewspaperJournal() {
    $text = "{{cite journal|publisher=news.bbc.co.uk}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get('newspaper'));
  }
 
  public function testNewspaperJournalBBC() {
    $text = "{{cite journal|publisher=Bbc.com}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'BBC News'));
    $this->assertNull($template->get('newspaper'));
    $this->assertSame('BBC News', $template->get('work'));
    $this->assertNull($template->get('publisher'));
  }
 
  public function testNewspaperJournaXl() {
    $text = "{{cite journal|work=exists}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get('newspaper'));
    $this->assertSame('exists', $template->get('work'));
  }
 
  public function testNewspaperJournaXk() {
    $text = "{{cite journal|via=This is from the times}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'Times'));
    $this->assertNull($template->get('via'));
    $this->assertSame('Times', $template->get('newspaper'));
  }

  public function testNewspaperJournal100() {
    $text = "{{cite journal|work=A work}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get('newspaper'));
  }
 
  public function testNewspaperJournal101() {
    $text = "{{cite web|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get('website'));
    $this->assertSame('News.BBC.co.uk', $template->get('work'));
  }
 
   public function testNewspaperJournal102() {
    $text = "{{cite journal|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'Junk and stuff'));
    $this->assertNull($template->get('website'));
    $this->assertSame('Junk and Stuff', $template->get('newspaper'));
  }
 
  public function testNewspaperJournal2() {
    $text = "{{cite journal|via=Something}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'A newspaper'));
    
    $text = "{{cite journal|via=Times}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'The Times'));
    $this->assertNull($template->get('via'));
    $this->assertSame('Times', $template->get('newspaper'));
    
    $text = "{{cite journal|via=A Post website}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'The Sun Post'));
    $this->assertNull($template->get('via'));
    $this->assertSame('The Sun Post', $template->get('newspaper'));
  }

  public function testNewspaperJournal3() {
    $text = "{{cite journal|publisher=A Big Company}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'A Big Company'));
    $this->assertNull($template->get('publisher'));
    $this->assertSame('A Big Company', $template->get('newspaper'));
  }
 
  public function testNewspaperJournal4() {
    $text = "{{cite journal|website=A Big Company}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('journal', 'A Big Company'));
    $this->assertSame('A Big Company', $template->get('journal'));
    $this->assertNull($template->get('website'));
    
    $text = "{{cite journal|website=A Big Company}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('journal', 'A Small Little Company'));
    $this->assertSame('A Small Little Company', $template->get('journal'));
    $this->assertNull($template->get('website'));
    
    $text = "{{cite journal|website=[[A Big Company]]}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('journal', 'A Small Little Company'));
    $this->assertSame('[[A Big Company]]', $template->get('journal'));
    $this->assertNull($template->get('website'));
  }
 
  public function testAddTwice() {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('series', 'The Sun Post'));
    $this->assertFalse($template->add_if_new('series', 'The Dog'));
    $this->assertSame('The Sun Post', $template->get('series'));
  }

  public function testExistingIsTitle() {
    $text = "{{cite journal|encyclopedia=Existing Data}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('title', 'Existing Data'));
    $this->assertNull($template->get('title'));
   
    $text = "{{cite journal|dictionary=Existing Data}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('title', 'Existing Data'));
    $this->assertNull($template->get('title'));
   
    $text = "{{cite journal|journal=Existing Data}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('title', 'Existing Data'));
    $this->assertNull($template->get('title'));
  }
                      
  public function testUpdateIssue() {
    $text = "{{cite journal|issue=1|volume=}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('issue', '7'));
    $this->assertFalse($template->add_if_new('issue', '8'));
    $this->assertSame('7', $template->get('issue'));
  }
 
  public function testExistingCustomPage() {
    $text = "{{cite journal|pages=footnote 7}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('pages', '3-22'));
    $this->assertSame('footnote 7', $template->get('pages'));
  }
  
  public function testPagesIsArticle() {
    $text = "{{cite journal|pages=431234}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('pages', '1-34'));
    $this->assertSame('431234', $template->get('pages'));
  }

  public function testExitingURL() {
    $text = "{{cite journal|conferenceurl=http://XXXX-TEST.COM}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('url', 'http://XXXX-TEST.COM'));
    $this->assertNull($template->get('url'));
   
    $text = "{{cite journal|url=xyz}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('title-link', 'abc'));
    $this->assertNull($template->get('title-lin'));
  }

  public function testResearchGateDOI() {
    $text = "{{cite journal|doi=10.13140/RG.2.2.26099.32807}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('doi', '10.1002/jcc.21074'));  // Not the same article, random
    $this->assertSame('10.1002/jcc.21074', $template->get('doi'));
  }

  public function testResearchJstorDOI() {
    $text = "{{cite journal|doi=10.2307/1974136}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
    $this->assertNull($template->get('doi'));
  }

  public function testNotBrokenDOI() {
    $text = "{{cite journal|doi-broken-date = # # # CITATION_BOT_PLACEHOLDER_COMMENT # # # }}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('doi-broken-date', '1 DEC 2019'));
  }
 
   public function testForgettersChangeType() {
    $text = "{{cite web|id=x}}";
    $template = $this->make_citation($text);
    $template->forget('url');
    $this->assertSame('cite document', $template->wikiname());

    $text = "{{cite web|journal=X}}";
    $template = $this->make_citation($text);
    $template->forget('url');
    $this->assertSame('cite journal', $template->wikiname());

    $text = "{{cite web|newspaper=X}}";
    $template = $this->make_citation($text);
    $template->forget('url');
    $this->assertSame('cite news', $template->wikiname());

    $text = "{{cite web|chapter=X}}";
    $template = $this->make_citation($text);
    $template->forget('url');
    $this->assertSame('cite book', $template->wikiname());
  }
 
  public function testForgettersChangeOtherURLS() {
    $text = "{{cite web|chapter-url=Y|chapter=X}}";
    $template = $this->make_citation($text);
    $template->forget('chapter');
    $this->assertSame('Y', $template->get('url'));

    $text = "{{cite web|chapterurl=Y|chapter=X}}";
    $template = $this->make_citation($text);
    $template->forget('chapter');
    $this->assertSame('Y', $template->get('url'));
  }
      
  public function testForgettersChangeWWWWork() {
    $text = "{{cite web|url=X|work=www.apple.com}}";
    $template = $this->make_citation($text);
    $template->forget('url');
    $this->assertNull($template->get('work'));
  }
      
  public function testCommentShields() {
    $text = "{{cite web|work = # # CITATION_BOT_PLACEHOLDER_COMMENT # #}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->set('work', 'new'));
    $this->assertSame('# # CITATION_BOT_PLACEHOLDER_COMMENT # #', $template->get('work'));
  }
      
  public function testRenameSpecialCases() {
    $text = "{{cite web|id=x}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->rename('work', 'work'));
    $this->assertTrue($template->rename('work', 'work', 'new'));
    $this->assertSame('new', $template->get('work'));
   
    $text = "{{cite web|id=x}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->rename('work', 'journal'));
    $this->assertTrue($template->rename('work', 'journal', 'new'));
    $this->assertSame('new', $template->get('journal'));

 
    $text = "{{cite web}}"; // param will be null
    $template = $this->make_citation($text);
    $this->assertFalse($template->rename('work', 'journal'));
    $this->assertFalse($template->rename('work', 'journal', 'new'));
    $this->assertNull($template->get('journal'));
  }
 
  public function testModificationsOdd() {
    $text = "{{cite web}}"; // param will be null to start
    $template = $this->make_citation($text);
    $this->assertTrue($template->add('work', 'The Journal'));
    $this->assertSame('The Journal', $template->get('work'));
    $this->assertNull($template->get('journal'));
    $ret = $template->modifications();
    $this->assertTrue(isset($ret['deletions']));
    $this->assertTrue(isset($ret['changeonly']));
    $this->assertTrue(isset($ret['additions']));
    $this->assertTrue(isset($ret['dashes']));
    $this->assertTrue(isset($ret['names']));
  }

  public function testAuthors1() {
    $text = "{{cite web|title=X}}";
    $template = $this->make_citation($text);
    $template->set('author3', '[[Joe|Joes]]'); // Must use set
    $template->tidy_parameter('author3');
    $this->assertSame('Joes', $template->get('author3'));
    $this->assertSame('Joe', $template->get('author3-link'));
  }

  public function testMoreEtAl() {
    $text = "{{cite web|authors=John, et al.}}";
    $template = $this->make_citation($text);
    $template->handle_et_al();
    $this->assertSame('John', $template->get('author'));
    $this->assertSame('etal', $template->get('displayauthors'));
  }
 
  public function testAddingEtAl() {
    $text = "{{cite web}}";
    $template = $this->process_citation($text);
    $template->set('authors', 'et al');
    $template->tidy_parameter('authors');
    $this->assertNull($template->get('authors'));
    $this->assertSame('etal', $template->get('displayauthors'));
    $this->assertNull($template->get('author'));
  }
 
   public function testAddingEtAl2() {
    $text = "{{cite web}}";
    $template = $this->process_citation($text);
    $template->set('author','et al');
    $template->tidy_parameter('author');
    $this->assertNull($template->get('author'));
    $this->assertNull($template->get('authors'));
    $this->assertSame('etal', $template->get('displayauthors'));
  }
 
  public function testCiteTypeWarnings1() {
    $text = "{{cite web|journal=X|chapter=|publisher=}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite journal', $template->wikiname());
    $this->assertNull($template->get('chapter'));
    $this->assertNull($template->get('publisher'));
   
    $text = "{{cite web|journal=X|chapter=Y|}}"; // Will warn user
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite web', $template->wikiname());
    $this->assertSame('Y', $template->get('chapter'));
  }

  public function testCiteTypeWarnings2() {
    $text = "{{cite arxiv|eprint=XYZ|bibcode=XXXX}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get('bibcode'));
  }

  public function testTidyPublisher() {
    $text = "{{citation|publisher='''''''''X'''''''''}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('X', $template->get('work'));
  }
                      
  public function testTidyWork() {
    $text = "{{citation|work=|website=X}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get('work'));

    $text = "{{cite web|work=}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get('work'));
   
    $text = "{{cite journal|work=}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame( "{{cite journal|journal=}}", $template->parsed_text());
  }
                      
  public function testTidyChapterTitleSeries() {
    $text = "{{cite book|chapter=X|title=X}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get('title'));
  }
 
  public function testTidyChapterTitleSeries2() {              
    $text = "{{cite journal|chapter=X|title=X}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get('chapter'));
  }
 
  public function testETCTidy() {
    $text = "{{cite web|pages=342 etc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('pages');
    $this->assertSame('342 etc.', $template->get('pages'));
  }
                      
  public function testZOOKEYStidy() {
    $text = "{{cite journal|journal=[[zOOkeys]]|volume=333|issue=22}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('volume');
    $this->assertNull($template->get('volume'));
    $this->assertSame('22', $template->get('issue'));
  }
                      
  public function testTidyViaStuff() {
    $text = "{{cite journal|via=A jstor|jstor=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('via');
    $this->assertNull($template->get('via'));

    $text = "{{cite journal|via=google books etc|isbn=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('via');
    $this->assertNull($template->get('via'));
   
    $text = "{{cite journal|via=questia etc|isbn=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('via');
    $this->assertNull($template->get('via'));
   
    $text = "{{cite journal|via=library}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('via');
    $this->assertNull($template->get('via'));
  }

  public function testConversionOfURL1() {
    $text = "{{cite journal|url=https://mathscinet.ams.org/mathscinet-getitem?mr=0012343|chapterurl=https://mathscinet.ams.org/mathscinet-getitem?mr=0012343}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('0012343', $template->get('mr'));
  }
 
  public function testConversionOfURL3() {
    $text = "{{cite web|url=http://worldcat.org/issn/1234-1234}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234-1234', $template->get('issn'));
    $this->assertNull($template->get('url'));
  }
 
  public function testConversionOfURL4() {
    $text = "{{cite web|url=http://lccn.loc.gov/1234}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234', $template->get('lccn'));
    $this->assertNull($template->get('url'));
  }
 
  public function testConversionOfURL5() {
    $text = "{{cite web|url=http://openlibrary.org/books/OL/1234W}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234W', $template->get('ol'));
    $this->assertNull($template->get('url'));
  }
 
  public function testTidyJSTOR() {
    $text = "{{cite web|jstor=https://www.jstor.org/stable/123456}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('jstor');
    $this->assertSame('123456', $template->get('jstor'));
    $this->assertSame('cite journal', $template->wikiname());
  }
  
  public function testAuthor2() {
    $text = "{{cite web|title=X}}";
    $template = $this->make_citation($text);
    $template->set('author1', 'Joe Jones Translated by John Smith');
    $template->tidy_parameter('author1');
    $this->assertSame('Joe Jones', $template->get('author1'));
    $this->assertSame('Translated by John Smith', $template->get('others'));
  }
  
  public function testAuthorsAndAppend3() {
    $text = "{{cite web|title=X}}";
    $template = $this->make_citation($text);
    $template->set('others', 'Kim Bill'); // Must use set
    $template->set('author1', 'Joe Jones Translated by John Smith');
    $template->tidy_parameter('author1');
    $this->assertSame('Joe Jones', $template->get('author1'));
    $this->assertSame('Kim Bill; Translated by John Smith', $template->get('others'));
  }

  public function testAuthorsAndAppend4() {
    $text = "{{cite web|title=X}}";
    $template = $this->make_citation($text);
    $template->set('others', 'CITATION_BOT_PLACEHOLDER_COMMENT');
    $template->set('author1', 'Joe Jones Translated by John Smith');
    $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get('others'));
    $this->assertSame('Joe Jones Translated by John Smith', $template->get('author1'));
  }
 
  public function testConversionOfURL6() {
    $text = "{{cite web|url=http://search.proquest.com/docview/12341234|title=X}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get('url'));
    $this->assertSame('{{ProQuest|12341234}}', $template->get('id'));
   
    $text = "{{cite web|url=http://search.proquest.com/docview/12341234}}";  // No title
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
  }
 
  public function testConversionOfURL7() {
    $text = "{{cite web|url=https://search.proquest.com/docview/12341234|id=CITATION_BOT_PLACEHOLDER_COMMENT}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get('id'));
    $this->assertSame('https://search.proquest.com/docview/12341234', $template->get('url'));
  }

  public function testVolumeIssueDemixing5() {
    $text = '{{cite journal|issue = volume 12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get('volume'));
    $this->assertNull($prepared->get('issue'));
  }
 
  public function testVolumeIssueDemixing14() {
    $text = '{{cite journal|issue = volume 12XX|volume=12XX|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12XX', $prepared->get('volume'));
    $this->assertNull($prepared->get('issue'));
  }
 
   public function testNewspaperJournal111() {
    $text = "{{cite journal|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get('website'));
    $this->assertSame('News.BBC.co.uk', $template->get('work'));
    $this->assertNull($template->get('journal'));
    $this->assertSame('cite journal', $template->wikiname());  // Unchanged
    // This could all change in final_tidy()
  }
 
  public function testMoreEtAl2() {
    $text = "{{cite web|authors=Joe et al.}}";
    $template = $this->make_citation($text);
    $this->assertSame('Joe et al.', $template->get('authors'));
    $template->handle_et_al();
    $this->assertSame('Joe', $template->get('author'));
    $this->assertNull($template->get('authors'));
    $this->assertSame('etal', $template->get('displayauthors'));
  }
 
   public function testCiteTypeWarnings3() {
    $text = "{{citation|title=XYZsadfdsfsdfdsafsd|chapter=DSRGgbgfbxdzfdfsXXXX|journal=adsfsd}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite book', $template->wikiname());

    $text = "{{Citation|title=XYZsadfdsfsdfdsafsd|chapter=DSRGgbgfbxdzfdfsXXXX|journal=adsfsd}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite book', $template->wikiname()); // Wikiname does not return the actual value, but the normalized one
  }

  public function testTidyWork2() {
    $text = "{{cite magazine|work=}}";
    $template = $this->make_citation($text);
    $template->prepare();
    $this->assertSame( "{{cite magazine|magazine=}}", $template->parsed_text());  
  }
 
  public function testTidyChapterTitleSeries3() {
    $text = "{{cite journal|title=XYZ}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $this->assertSame('XYZ', $template->get('title'));
    $this->assertNull($template->get('series'));
   
    $text = "{{cite journal|journal=XYZ}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $this->assertSame('XYZ', $template->get('journal'));
    $this->assertNull($template->get('series'));
  }
  
  public function testTidyChapterTitleSeries4() {
    $text = "{{cite book|journal=X}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $template->tidy_parameter('series');
    $this->assertSame('XYZ', $template->get('series'));
    $this->assertSame('X', $template->get('journal'));
   
    $text = "{{cite book|title=X}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $template->tidy_parameter('series');
    $this->assertSame('XYZ', $template->get('series'));
    $this->assertSame('X', $template->get('title'));
  }
 
  public function testAllZeroesTidy() {
    $text = "{{cite web|issue=000000000}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertNull($template->get('issue'));
  }
 
  public function testConversionOfURL2() {
    $text = "{{cite web|url=http://worldcat.org/title/stuff/oclc/1234}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234', $template->get('oclc'));
    $this->assertNull($template->get('url'));
    $this->assertSame('cite book', $template->wikiname());           
  }
 
  public function testAddDupNewsPaper() {
    $text = "{{cite web|work=I exist and submit}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('newspaper', 'bbc sports'));
    $this->assertSame('I exist and submit', $template->get('work'));
    $this->assertNull($template->get('newspaper'));
  }
 
 
  public function testAddBogusBibcode() {
    $text = "{{cite web|bibcode=Exists}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('bibcode', 'xyz')); 
    $this->assertSame('Exists', $template->get('bibcode'));

    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('bibcode', 'Z')); 
    $this->assertSame('Z..................', $template->get('bibcode'));
  }

  public function testvalidate_and_add() {
    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->validate_and_add('author1', 'George @Hashtags Billy@hotmail.com', 'Sam @Hashtags Billy@hotmail.com', '', FALSE);
    $this->assertSame("{{cite web}}", $template->parsed_text());

    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->validate_and_add('author1', 'George @Hashtags', '', '', FALSE);
    $this->assertSame("{{cite web|author1 = George}}", $template->parsed_text());

    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->validate_and_add('author1', 'George Billy@hotmail.com', 'Sam @Hashtag', '', FALSE);
    $this->assertSame("{{cite web|last1 = George|first1 = Sam}}", $template->parsed_text());

    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->validate_and_add('author1', 'com', 'Sam', '', FALSE);
    $this->assertSame("{{cite web|last1 = Com|first1 = Sam}}", $template->parsed_text());
   
    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->validate_and_add('author1', '',  'George @Hashtags', '', FALSE);
    $this->assertSame("{{cite web|author1 = George}}", $template->parsed_text());
  }
 
  public function testDateYearRedundancyEtc() {
    $text = "{{cite web|year=2004|date=}}";
    $template = $this->make_citation($text);
    $template->tidy();
    $this->assertSame("2004", $template->get('year'));
    $this->assertNull($template->get('date')); // Not an empty string anymore
   
    $text = "{{cite web|date=November 2004|year=}}";
    $template = $this->make_citation($text);
    $template->tidy();
    $this->assertSame("November 2004", $template->get('date'));
    $this->assertNull($template->get('year')); // Not an empty string anymore
   
    $text = "{{cite web|date=November 2004|year=Octorberish 2004}}";
    $template = $this->make_citation($text);
    $template->tidy();
    $this->assertSame("November 2004", $template->get('date'));
    $this->assertNull($template->get('year'));
   
    $text = "{{cite web|date=|year=Sometimes around 2004}}";
    $template = $this->make_citation($text);
    $template->tidy();
    $this->assertSame("Sometimes around 2004", $template->get('date'));
    $this->assertNull($template->get('year'));
  }
 
   public function testOddThing() {
     $text='{{journal=capitalization is Good}}';
     $template = $this->process_citation($text);
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testTranslator() {
     $text='{{cite web}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->add_if_new('translator1', 'John'));
     $text='{{cite web|translator=Existing bad data}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->add_if_new('translator1', 'John'));
     $text='{{cite web}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->add_if_new('translator1', 'John'));
     $this->assertTrue($template->add_if_new('translator2', 'Jill'));
     $this->assertFalse($template->add_if_new('translator2', 'Rob'));  // Add same one again
   }
 
   public function testAddDuplicateBibcode() {
     $text='{{cite web|url=https://ui.adsabs.harvard.edu/abs/1924MNRAS..84..308E/abstract|bibcode=1924MNRAS..84..308E}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->get_identifiers_from_url());
     $this->assertNull($template->get('url'));
   }
 
   public function testNonUSAPubMedMore() {
     $text='{{cite web|url=https://europepmc.org/abstract/med/342432/pdf}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->get_identifiers_from_url());
     $this->assertNull($template->get('url'));
     $this->assertSame('342432', $template->get('pmid'));
     $this->assertSame('cite journal', $template->wikiname());
   }
 
   public function testNonUSAPubMedMore2() {
     $text='{{cite web|url=https://europepmc.org/scanned?pageindex=1234&articles=pmc43871}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->get_identifiers_from_url());
     $this->assertNull($template->get('url'));
     $this->assertSame('43871', $template->get('pmc'));
     $this->assertSame('cite journal', $template->wikiname());
   }

   public function testNonUSAPubMedMore3() {
     $text='{{cite web|url=https://pubmedcentralcanada.ca/pmcc/articles/PMC324123/pdf}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->get_identifiers_from_url());
     $this->assertNull($template->get('url'));
     $this->assertSame('324123', $template->get('pmc'));
     $this->assertSame('cite journal', $template->wikiname());
   }
 
   public function testRubbishArxiv() { // Something we do not understand, other than where it is from
     $text='{{cite web|url=http://arxiv.org/X/abs/3XXX41222342343242}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->get_identifiers_from_url());
     $this->assertSame('cite arxiv', $template->wikiname());
     $this->assertNull($template->get('arxiv'));
     $this->assertNull($template->get('eprint'));
     $this->assertSame('http://arxiv.org/X/abs/3XXX41222342343242', $template->get('url'));
   }
 
   public function testArchiveAsURL() {
     $text='{{Cite web | url=https://web.archive.org/web/20111030210210/http://www.cap.ca/en/}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->get_identifiers_from_url()); // FALSE because we add no parameters or such
     $this->assertSame('http://www.cap.ca/en/', $template->get('url'));
     $this->assertSame('https://web.archive.org/web/20111030210210/http://www.cap.ca/en/', $template->get('archive-url'));
     $this->assertSame('2011-10-30', $template->get('archive-date'));
   }
 
   public function testCAPSGoingAway1() {
     $text='{{Cite journal | doi=10.1016/j.ifacol.2017.08.010|title=THIS IS A VERY BAD ALL CAPS TITLE|journal=THIS IS A VERY BAD ALL CAPS JOURNAL}}';
     $template = $this->process_citation($text);
     $this->assertSame('Contingency Analysis Post-Processing with Advanced Computing and Visualization', $template->get('title'));
     $this->assertSame('IFAC-PapersOnLine', $template->get('journal'));   
   }
 
   public function testCAPSGoingAway2() {
     $text='{{Cite book | doi=10.1109/PESGM.2015.7285996|title=THIS IS A VERY BAD ALL CAPS TITLE|chapter=THIS IS A VERY BAD ALL CAPS CHAPTER}}';
     $template = $this->process_citation($text);
     $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get('chapter'));
     $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get('title')); 
   }
 
   public function testCAPSGoingAway3() {
     $text='{{Cite book | doi=10.1109/PESGM.2015.7285996|title=Same|chapter=Same}}';
     $template = $this->process_citation($text);
     $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get('chapter'));
     $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get('title')); 
   }
 
   public function testCAPSGoingAway4() {
     $text='{{Cite book | doi=10.1109/PESGM.2015.7285996|title=Same|chapter=Same|journal=Same}}';
     $template = $this->process_citation($text);
     $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get('chapter'));
     $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get('title')); 
     $this->assertSame('Same', $template->get('journal'));
   }
 
   public function testCAPSGoingAway5() {
     $text='{{Cite book | jstor=TEST_DATA_IGNORE |title=Same|chapter=Same|journal=Same}}';
     $template = $this->process_citation($text);
     $this->assertSame('Same', $template->get('journal'));
     $this->assertSame('Same', $template->get('title'));
     $this->assertNull($template->get('chapter'));
   }
 
   public function testAddDuplicateArchive() {
     $text='{{Cite book | archiveurl=XXX}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->add_if_new('archive-url', 'YYY'));
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testReplaceBadDOI() {
     $text='{{Cite journal | doi=10.0000/broken|doi-broken-date=1999}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->add_if_new('doi', '10.1063/1.2263373'));
     $this->assertSame('10.1063/1.2263373', $template->get('doi'));
   }
 
   public function testEmptyJunk() {
     $text='{{Cite journal| dsfasfdfasdfsdafsdafd = | issue = | issue = 33}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get('issue'));
     $this->assertNull($template->get('dsfasfdfasdfsdafsdafd'));
     $this->assertSame('{{Cite journal| issue = 33}}', $template->parsed_text());
   }
 
   public function testFloaters() {
     $text='{{Cite journal| p 33 }}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get('page'));
     $this->assertSame('{{Cite journal| page = 33 }}', $template->parsed_text());

     $text='{{Cite journal | p 33 |page=}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get('page'));
    
     $text='{{Cite journal |33(22):11-12 }}';
     $template = $this->process_citation($text);
     $this->assertSame('22', $template->get('issue'));
     $this->assertSame('33', $template->get('volume'));
     $this->assertSame('11–12', $template->get('pages'));
   }
 
    public function testFloaters2() {
     $text='{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 }}';
     $template = $this->process_citation($text);
     $this->assertSame('12 December 1990', $template->get('accessdate'));
   }
 
    public function testFloaters3() {
     $text='{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 |accessdate=}}';
     $template = $this->process_citation($text);
     $this->assertSame('12 December 1990', $template->get('accessdate'));
   }
 
    public function testFloaters4() {
     $text='{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 | accessdate = 3 May 1999 }}';
     $template = $this->process_citation($text);
     $this->assertSame('3 May 1999', $template->get('accessdate'));
   }
 
    public function testFloaters5() {
     $text='{{Cite journal | issue 33 }}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get('issue'));
   }
 
    public function testFloaters6() {
     $text='{{Cite journal | issue 33 |issue=}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get('issue'));
   }
 
    public function testFloaters7() {
     $text='{{Cite journal | issue 33 | issue=22 }}';
     $template = $this->process_citation($text);
     $this->assertSame('22', $template->get('issue'));
   }
 
    public function testFloaters8() {
     $text='{{Cite journal |  p 33 junk}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get('page'));
   }
 
    public function testFloaters9() {
     $text='{{Cite journal |  p 33 junk|page=}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get('page'));
   }
 
   public function testIDconvert1() {
     $text='{{Cite journal | id = {{ASIN|3333|country=eu}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame($text, $template->parsed_text());
   }    

   public function testIDconvert2() {
     $text = '{{Cite journal | id = {{jstor|33333|issn=xxxx}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testIDconvert3() {
     $text = '{{Cite journal | id = {{ol|44444|author=xxxx}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testIDconvert4() {
     $text = '{{Cite journal | id = {{howdy|44444}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testIDconvert5() {
     $text='{{Cite journal | id = {{oclc|02268454}} {{ol|1234}} {{bibcode|2018arXiv}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame('02268454', $template->get('oclc'));
     $this->assertSame('1234', $template->get('ol'));
     $this->assertSame('2018arXiv..........', $template->get('bibcode'));
     $this->assertNull($template->get('id'));
   }
 
   public function testIDconvert6() {
     $text='{{Cite journal | id = {{jfm|02268454}} {{lccn|1234}} {{mr|222}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame('02268454', $template->get('jfm'));
     $this->assertSame('1234', $template->get('lccn'));
     $this->assertSame('222', $template->get('mr'));
     $this->assertNull($template->get('id'));
   }
 
   public function testIDconvert6b() {
     $text='{{Cite journal | id = {{mr|id=222}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame('222', $template->get('mr'));
     $this->assertNull($template->get('id'));
   }
 
   public function testIDconvert7() {
     $text='{{Cite journal | id = {{osti|02268454}} {{ssrn|1234}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame('02268454', $template->get('osti'));
     $this->assertSame('1234', $template->get('ssrn'));
     $this->assertNull($template->get('id'));
   }

   public function testIDconvert8() {
     $text='{{Cite journal | id = {{ASIN|0226845494|country=eu}} }}';
     $template = $this->process_citation($text);
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testIDconvert9() {
     $text = '{{Cite journal | id = {{howdy|0226845494}} }}';
     $template = $this->process_citation($text);
     $this->assertSame($text, $template->parsed_text());
    }
 
   public function testCAPS() {
     $text = '{{Cite journal | URL = }}';
     $template = $this->process_citation($text);
     $this->assertSame('', $template->get('url'));
     $this->assertNull($template->get('URL'));
    
     $text = '{{Cite journal | QWERTYUIOPASDFGHJKL = ABC}}';
     $template = $this->process_citation($text);
     $this->assertSame('ABC', $template->get('qwertyuiopasdfghjkl'));
     $this->assertNull($template->get('QWERTYUIOPASDFGHJKL'));
   }
 
   public function testDups() {
     $text = '{{Cite journal | DUPLICATE_URL = }}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get('duplicate_url'));
     $this->assertNull($template->get('DUPLICATE_URL'));
     $text = '{{Cite journal | duplicate_url = }}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get('duplicate_url'));
     $this->assertNull($template->get('DUPLICATE_URL'));
     $text = '{{Cite journal|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=}}';
     $template = $this->process_citation($text);
     $this->assertSame('{{Cite journal|id=}}', $template->parsed_text());
   }
 
   public function testDropSep() {
     $text = '{{Cite journal | author_separator = }}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get('author_separator'));
     $this->assertNull($template->get('author-separator'));
     $text = '{{Cite journal | author-separator = Something}}';
     $template = $this->process_citation($text);
     $this->assertSame('Something', $template->get('author-separator'));
   }

   public function testCommonMistakes() {
     $text = '{{Cite journal | origmonth = X}}';
     $template = $this->process_citation($text);
     $this->assertSame('X', $template->get('month'));
     $this->assertNull($template->get('origmonth'));
   }
 
   public function testRoman() { // No roman and then wrong roman
     $text = '{{Cite journal | title=On q-Functions and a certain Difference Operator|doi=10.1017/S0080456800002751}}';
     $template = $this->process_citation($text);
     $this->assertSame('Transactions of the Royal Society of Edinburgh', $template->get('journal'));
     $text = '{{Cite journal | title=XXI.—On q-Functions and a certain Difference Operator|doi=10.1017/S0080456800002751}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get('journal'));
   }
 
   public function testRoman2() { // Bogus roman to start with
     $text = '{{Cite journal | title=Improved heat capacity estimator for path integral simulations. XXXI. part of many|doi=10.1063/1.1493184}}';
     $template = $this->process_citation($text);
     $this->assertSame('The Journal of Chemical Physics', $template->get('journal'));
   }
 
   public function testRoman3() { // Bogus roman in the middle
     $text = "{{Cite journal | doi = 10.1016/0301-0104(82)87006-7|title=Are atoms intrinsic to molecular electronic wavefunctions? IIII. Analysis of FORS configurations}}";
     $template = $this->process_citation($text);
     $this->assertNull($template->get('journal'));
   }
   
   public function testRoman4() { // Right roman in the middle
     $text = "{{Cite journal | doi = 10.1016/0301-0104(82)87006-7|title=Are atoms intrinsic to molecular electronic wavefunctions? III. Analysis of FORS configurations}}";
     $template = $this->process_citation($text);
     $this->assertSame('Chemical Physics', $template->get('journal'));
   }
 
   public function testAppendToComment() {
     $text = '{{cite web}}';
     $template = $this->make_citation($text);
     $template->set('id', 'CITATION_BOT_PLACEHOLDER_COMMENT');
     $this->assertFalse($template->append_to('id', 'joe'));
     $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get('id'));
   }
 
   public function testAppendEmpty() {
     $text = '{{cite web|id=}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->append_to('id', 'joe'));
     $this->assertSame('joe', $template->get('id'));
   }

   public function testAppendNull() {
     $text = '{{cite web}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->append_to('id', 'joe'));
     $this->assertSame('joe', $template->get('id'));
   }

   public function testAppendEmpty2() {
     $text = '{{cite web|last=|id=}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->append_to('id', 'joe'));
     $this->assertSame('joe', $template->get('id'));
   }
 
   public function testAppendAppend() {
     $text = '{{cite web|id=X}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->append_to('id', 'joe'));
     $this->assertSame('Xjoe', $template->get('id'));
   }
 
   public function testDateStyles() {
     $text = '{{cite web}}';
     $template = $this->make_citation($text);
     $template->date_style = DATES_MDY;
     $template->add_if_new('date', '12-02-2019');
     $this->assertSame('February 12, 2019', $template->get('date'));
     $template = $this->make_citation($text);
     $template->date_style = DATES_DMY;
     $template->add_if_new('date', '12-02-2019');
     $this->assertSame('12 February 2019', $template->get('date'));
     $template = $this->make_citation($text);
     $template->date_style = FALSE;
     $template->add_if_new('date', '12-02-2019');
     $this->assertSame('12-02-2019', $template->get('date'));
   }
 
    public function testFinalTidyComplicated() {
     $text = '{{cite book|series=A|journal=A}}';
     $template = $this->make_citation($text);
     $template->final_tidy();
     $this->assertSame('A', $template->get('series'));
     $this->assertNull($template->get('journal'));
     
     $text = '{{cite journal|series=A|journal=A}}';
     $template = $this->make_citation($text);
     $template->final_tidy();
     $this->assertSame('A', $template->get('journal'));
     $this->assertNull($template->get('series')); 
   }
 
   public function testFindDOIBadAuthorAndFinalPage() { // Testing this code:   If fail, try again with fewer constraints...
     $text = '{{cite journal|last=THIS_IS_BOGUS_TEST_DATA|pages=4346–43563413241234|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|year=2019}}';
     $template = $this->make_citation($text);
     $template->get_doi_from_crossref();
     $this->assertSame('10.1021/acs.analchem.8b04567', $template->get('doi'));
   }
 
   public function testCAPSParams() {
     $text = '{{cite journal|ARXIV=|TITLE=|LAST1=|JOURNAL=}}';
     $template = $this->process_citation($text);
     $this->assertSame(strtolower($text), $template->parsed_text());
   }
 
   public function testRemoveBadPublisher() {
     $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=X-Y|pmc=1234123|publisher=u.s. National Library of medicine}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertNull($template->get('publisher'));
   }
 
   public function testShortSpelling() {
     $text = '{{cite journal|list=X}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('X', $template->get('last'));
    
     $text = '{{cite journal|las=X}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('X', $template->get('last'));
    
     $text = '{{cite journal|lis=X}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('X', $template->get('lis'));
   }
 
   public function testSpellingLots() {
     $text = '{{cite journal|totle=X|journul=X|serias=X|auther=X|lust=X|cows=X|pigs=X|contrubution-url=X|controbution-urls=X|chupter-url=X|orl=X}}';
     $template = $this->prepare_citation($text); 
     $this->assertSame('{{cite journal|title=X|journal=X|series=X|author=X|last=X|cows=X|page=X|contribution-url=X|contribution-url=X|chapter-url=X|url=X}}', $template->parsed_text());
   }
 
   public function testAlmostSame() {
     $text = '{{cite journal|publisher=[[Abc|Abc]]|journal=Abc}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertNull($template->get('publisher'));
     $this->assertSame('[[abc|abc]]', strtolower($template->get('journal'))); // Might "fix" Abc redirect to ABC
   }

   public function testRemoveAuthorLinks() {
     $text = '{{cite journal|author3-link=}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get('author3-link'));

     $text = '{{cite journal|author3-link=|author3=X}}';
     $template = $this->process_citation($text);
     $this->assertSame('', $template->get('author3-link'));
   }
 
   public function testBogusArxivPub() {
     $text = '{{cite journal|publisher=arXiv|arxiv=1234}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertNull($template->get('publisher'));
    
     $text = '{{cite journal|publisher=arXiv}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertSame('arXiv', $template->get('publisher'));
   }
 
   public function testBloombergConvert() {
     $text = '{{cite journal|url=https://www.bloomberg.com/tosv2.html?vid=&uuid=367763b0-e798-11e9-9c67-c5e97d1f3156&url=L25ld3MvYXJ0aWNsZXMvMjAxOS0wNi0xMC9ob25nLWtvbmctdm93cy10by1wdXJzdWUtZXh0cmFkaXRpb24tYmlsbC1kZXNwaXRlLWh1Z2UtcHJvdGVzdA==}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('url');
     $this->assertSame('https://www.bloomberg.com/news/articles/2019-06-10/hong-kong-vows-to-pursue-extradition-bill-despite-huge-protest', $template->get('url'));
   }
 
   public function testWork2Enc() {
     $text = '{{cite journal|url=plato.stanford.edu|work=X}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get('work'));
     $this->assertSame('X', $template->get('encyclopedia'));
    
     $text = '{{cite journal|work=X from encyclopædia}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get('work'));
     $this->assertSame('X from encyclopædia', $template->get('encyclopedia'));
   }
 
   public function testNonPubs() {
     $text = '{{cite book|work=citeseerx.ist.psu.edu}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get('work'));
     $this->assertSame('citeseerx.ist.psu.edu', $template->get('title'));
    
     $text = '{{cite book|work=citeseerx.ist.psu.edu|title=Exists}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get('work'));
     $this->assertSame('Exists', $template->get('title'));
   }
 
   public function testNullPages() {
     $text = '{{cite book|pages=null}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('pages');
     $this->assertNull($template->get('pages'));
     $template->add_if_new('work', 'null');
     $template->add_if_new('pages', 'null');
     $template->add_if_new('author', 'null');
     $template->add_if_new('journal', 'null');
     $this->assertSame('{{cite book}}', $template->parsed_text());
   }
 
  public function testVerifyDOI() {
     $text = '{{cite journal|doi=1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get('doi'));
   
     $text = '{{cite journal|doi=.1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get('doi'));
   
     $text = '{{cite journal|doi=0.1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x;}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get('doi'));
   
     $text = '{{cite journal|doi=10.1175/1525-7541(2003)004&lt;1147:TVGPCP&gt;2.0.CO;2}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2', $template->get('doi'));
  }
 
  public function testOxfordTemplate() {
     $text = '{{cite web |last1=Courtney |first1=W. P. |last2=Hinings |first2=Jessica |title=Woodley, George (bap. 1786, d. 1846) |url=https://doi.org/10.1093/ref:odnb/29929 |website=Oxford Dictionary of National Biography |publisher=Oxford University Press |accessdate=12 September 2019}}';
     $template = $this->process_citation($text);
     $this->assertSame('cite odnb', $template->wikiname());
     $this->assertSame('Woodley, George (bap. 1786, d. 1846)', $template->get('title'));
     $this->assertNull($template->get('url'));
     $this->assertSame('10.1093/ref:odnb/29929', $template->get('doi'));
     $this->assertNull($template->get('publisher'));
    // Now with caps in wikiname
     $text = '{{Cite web |last1=Courtney |first1=W. P. |last2=Hinings |first2=Jessica |title=Woodley, George (bap. 1786, d. 1846) |url=https://doi.org/10.1093/ref:odnb/29929 |website=Oxford Dictionary of National Biography |publisher=Oxford University Press |accessdate=12 September 2019}}';
     $template = $this->process_citation($text);
     $this->assertSame('cite odnb', $template->wikiname());
     $this->assertSame('Woodley, George (bap. 1786, d. 1846)', $template->get('title'));
     $this->assertNull($template->get('url'));
     $this->assertSame('10.1093/ref:odnb/29929', $template->get('doi'));
     $this->assertNull($template->get('publisher'));
  }
 
  public function testSaveAccessType() {
     $text = '{{cite web|url=http://doi.org/10.1063/1.2833100 |url-access=Tested}}';
     $template = $this->make_citation($text);
     $template->get_identifiers_from_url();
     $this->assertNull($template->get('doi-access'));
     $this->assertNull($template->get('url-access'));
  }
 
   public function testDontDoIt() { // "complete" already
     $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=X|bibcode=X|last1=X|first1=X}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->incomplete());
     $text = '{{cite journal|title=X|periodical=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=X|bibcode=X|last1=X|first1=X}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->incomplete());
  
     $this->requires_bibcode(function() {
      $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=X|bibcode=X|last1=X|first1=X}}';
      $template = $this->make_citation($text);
      $this->assertFalse($template->expand_by_adsabs());
      $text = '{{cite journal|title=X|periodical=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=X|bibcode=X|last1=X|first1=X}}';
      $template = $this->make_citation($text);
      $this->assertFalse($template->expand_by_adsabs());
     });
   }
 
  public function testBibcodeDotEnding() {
    $this->requires_bibcode(function() {
      $text='{{cite journal|title=Electric Equipment of the Dolomites Railway|journal=Nature|date=2 January 1932|volume=129|issue=3244|page=18|doi=10.1038/129018a0}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('1932Natur.129Q..18.', $expanded->get('bibcode'));
    });
  }

  public function testBibcodesBooks() {
    $this->requires_bibcode(function() {
      $text = "{{Cite book|bibcode=1982mcts.book.....H}}";
      $expanded = $this->process_citation($text);
      $this->assertSame('1982', $expanded->get('year'));
      $this->assertSame('Houk', $expanded->get('last1'));
      $this->assertSame('N.', $expanded->get('first1'));
      $this->assertNotNull($expanded->get('title'));
    });
  }
  
  public function testBadBibcodeARXIVPages() {
   $this->requires_bibcode(function() {
    $text = "{{cite journal|bibcode=1995astro.ph..8159B|pages=8159}}"; // Pages from bibcode have slash in it astro-ph/8159B
    $expanded = $this->process_citation($text);
    $pages = (string) $expanded->get('pages');
    $this->assertSame(FALSE, stripos($pages, 'astro'));
    $this->assertNull($expanded->get('journal'));  // if we get a journal, the data is updated and test probably no longer gets bad data
   });
  }
 
  public function testNoBibcodesForArxiv() {
   $this->requires_bibcode(function() {
    $text = "{{Cite arxiv|last=Sussillo|first=David|last2=Abbott|first2=L. F.|date=2014-12-19|title=Random Walk Initialization for Training Very Deep Feedforward Networks|eprint=1412.6558 |class=cs.NE}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('bibcode'));  // If this eventually gets a journal, we will have to change the test
   });
  }

  public function testNoBibcodesForBookReview() {
   $this->requires_bibcode(function() {  // don't add isbn. It causes early exit
    $text = "{{cite book |title=Churchill's Bomb: How the United States Overtook Britain in the First Nuclear Arms Race |publisher=X|location=X|lccn=X|olcn=X}}";
    $expanded = $this->make_citation($text);
    $expanded->expand_by_adsabs(); // Won't expand because of bookish stuff
    $this->assertNull($expanded->get('bibcode'));
   });
  }
 
  public function testZooKeys2() {
     $this->requires_secrets(function() { // this only works if we can query wikipedia and see if page exists
      $text = '{{Cite journal|journal=[[Zookeys]]}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('[[ZooKeys]]', $expanded->get('journal'));
     });
  }
 
  public function testJustAnLCCN() {
    $this->requires_google(function() {
      $text = '{{cite book | lccn=2009925036}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('Alternative Energy for Dummies', $expanded->get('title'));
    });
  }
 
   public function testRedirectFixing() {
    $this->requires_secrets(function() {
     $text = '{{cite journal|journal=[[Journal Of Polymer Science]]}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('[[Journal of Polymer Science]]', $template->get('journal'));
    });
   }
 
    public function testRedirectFixing2() {
    $this->requires_secrets(function() {
     $text = '{{cite journal|journal=[[Journal Of Polymer Science|"J Poly Sci"]]}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('[[Journal of Polymer Science|J Poly Sci]]', $template->get('journal'));
    });
   }
 
}
