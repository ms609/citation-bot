<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

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
 
  public function testGetDoiFromCrossref() {
     $text = '{{Cite journal | last1 = Glaesemann | first1 = K. R. | last2 = Fried | first2 = L. E. | doi = | title = Improved wood–kirkwood detonation chemical kinetics | journal = Theoretical Chemistry Accounts | volume = 120 | pages = 37–43 | year = 2007 |issue=1–3}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('10.1007/s00214-007-0303-9', $expanded->get('doi'));
     $this->assertNull($expanded->get('pmid'));  // do not want reference where pmid leads to doi
     $this->assertNull($expanded->get('bibcode'));
     $this->assertNull($expanded->get('pmc'));
  }
  
  public function testJstorExpansion() {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true|website=i found this online}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $prepared->wikiname());
    $this->assertEquals('1701972'     , $prepared->get('jstor'));
    $this->assertNull($prepared->get('website'));

    $text = "{{Cite journal | url=http://www.jstor.org/stable/10.2307/40237667|jstor=}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('40237667', $prepared->get('jstor'));
    $this->assertNull($prepared->get('doi'));
    $this->assertEquals(1, substr_count($prepared->parsed_text(), 'jstor'));  // Verify that we do not have both jstor= and jstor=40237667.  Formerly testOverwriteBlanks()

    $text = "{{Cite web | url = http://www.jstor.org/stable/10.1017/s0022381613000030}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('10.1017/s0022381613000030', $prepared->get('jstor'));
    
    $text = '{{cite web | via = UTF8 characters from JSTOR | url = https://www.jstor.org/stable/27695659}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Mórdha', $expanded->get('last1'));
   
    $text = '{{cite journal | url = https://www-jstor-org.school.edu/stable/10.7249/mg1078a.10?seq=1#metadata_info_tab_contents }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.7249/mg1078a.10', $expanded->get('jstor'));
   
  }
    
   public function testRISJstorExpansion() {
    $text = "<ref name='jstor'>{{jstor|3073767}}</ref>"; // Check Page expansion too
    $page = $this->process_page($text);
    $expanded = $this->reference_to_template($page->parsed_text());
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
   // Newer code does not even add it
    $text = '{{cite journal|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1301}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi'));
    $this->assertNotNull($expanded->get('url'));
    // This is an ISSN only doi: it is valid, but leave url too
    $text = '{{cite journal|url=http://onlinelibrary.wiley.com/journal/10.1111/(ISSN)1601-183X/issues }}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get('doi'));
    $this->assertNotNull($expanded->get('url'));
  }
  
 public function testTruncateDOI() {
    $text = '{{cite journal|url=http://www.oxfordhandbooks.com/view/10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi-broken-date'));
    $this->assertEquals('http://www.oxfordhandbooks.com/view/10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023', $expanded->get('url'));
    $this->assertEquals('10.1093/oxfordhb/9780199552238.001.0001', $expanded->get('doi'));
 }
 
 public function testCrazyDoubleDOI() {
    $doi = '10.1126/science.10.1126/SCIENCE.291.5501.24';
    $text = '{{cite journal|doi=' . $doi . '}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals($doi, $expanded->get('doi'));
 }

 public function testBrokenDoiUrlChanges() {
     $text = '{{cite journal|url=http://dx.doi.org/10.1111/j.1471-0528.1995.tb09132.x|doi=10.00/broken_and_invalid|doi-broken-date=12-31-1999}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('10.1111/j.1471-0528.1995.tb09132.x', $expanded->get('doi'));
     $this->assertNull($expanded->get('url'));
    // The following URL is "broken" since it is not escaped properly.  The cite template displays and links it wrong too.
     $text = '{{cite journal|doi=10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2|url=https://dx.doi.org/10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
     $text = '{{cite journal|url=http://doi.org/10.14928/amstec.23.1_1|doi=10.14928/amstec.23.1_1}}';  // This also troublesome DOI
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }
  
  public function testPmidExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
    $expanded = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('1941451', $expanded->get('pmid'));
  }
   
  public function testPoundDOI() {
    $text = "{{cite book |url=https://link.springer.com/chapter/10.1007%2F978-3-642-75924-6_15#page-1}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.1007/978-3-642-75924-6_15', $expanded->get('doi'));
  }
 
  public function testPlusDOI() {
    $doi = "10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U";
    $text = "{{cite journal|doi = $doi }}";
    $expanded = $this->process_citation($text);
    $this->assertEquals($doi, $expanded->get('doi'));
  }
 
  public function testNewsdDOI() {
    $text = "{{cite news|url=http://doi.org/10.1021/cen-v076n048.p024}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.1021/cen-v076n048.p024', $expanded->get('doi'));
  }
 
  public function testChangeNothing1() {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x|pages=<!-- -->|title=<!-- -->|journal=<!-- -->|volume=<!-- -->|issue=<!-- -->|year=<!-- -->|authors=<!-- -->}}';
     $expanded = $this->process_page($text);
     $this->assertEquals($text, $expanded->parsed_text());
  }
    
  public function testChangeNothing2() {
     $text = '{{cite journal | doi=10.000/broken_real_bad_and_tests_know_it | doi-broken-date = <!-- not broken and the bot is wrong --> }}';
     $expanded = $this->process_page($text);
     $this->assertEquals($text, $expanded->parsed_text());
  }
    
  public function testChangeNothing3() {
     $text = '{{cite journal |title=The tumbling rotational state of 1I/‘Oumuamua<!-- do not change odd punctuation--> |journal=Nature title without caps <!-- Deny Citation Bot-->  |pages=383-386 <!-- do not change the dash--> }}';
     $expanded = $this->process_page($text);
     $this->assertEquals($text, $expanded->parsed_text());
  }
  
  public function testNoLoseUrl() {
     $text = '{{cite book |last=Söderström |first=Ulrika |date=2015 |title=Sandby Borg: Unveiling the Sandby Borg Massacre |url= |location= |publisher=Kalmar lāns museum |isbn=9789198236620 |language=Swedish }}';
     $expanded = $this->process_page($text);
     $this->assertEquals($text, $expanded->parsed_text());
  }
 
  public function testDotsAndVia() {
     $text = '{{cite journal|pmid=4957203|via=Pubmed}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('M. M.', $expanded->get('first3'));
     $this->assertNull($expanded->get('via'));
  }
    
  public function testJustBrackets() {
     $text = '{{cite book|title=[[W|12px|alt=W]]}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals($text, $expanded->parsed_text());
     $text = '{{cite book|title=[[File:Example.png|thumb|upright|alt=Example alt text|Example caption]]}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals($text, $expanded->parsed_text());
  }

  public function testBadAuthor2() {
      $text = '{{cite journal|title=Guidelines for the management of adults with hospital-acquired, ventilator-associated, and healthcare-associated pneumonia |journal=Am. J. Respir. Crit. Care Med. |volume=171 |issue=4 |pages=388–416 |year=2005 |pmid=15699079 |doi=10.1164/rccm.200405-644ST}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('Infectious Diseases Society of America', $expanded->get('author1'));
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
    $this->assertNull($expanded->get('url'));
    $text = "{{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf', $expanded->get('url'));
    $this->assertEquals('2491514', $expanded->get('pmc'));
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
    $text = "{{Cite web | url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
    $expanded = $this->prepare_citation($text);
    $this->assertEquals('cite book', $expanded->wikiname());
    $this->assertEquals('978-0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
    $this->assertNull($expanded->get('publisher'));
    $this->assertNull($expanded->get('url'));

    $text = "{{Cite web | chapter-url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
    $expanded = $this->prepare_citation($text);
    $this->assertEquals('cite book', $expanded->wikiname());
    $this->assertEquals('978-0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
    $this->assertNull($expanded->get('publisher'));
    $this->assertNull($expanded->get('chapter-url'));

    $text = "{{Cite web | url=https://www.amazon.com/Gold-Toe-Metropolitan-Dress-Three/dp/B0002TV0K8 | accessdate=2012-04-20 | title=Gold Toe Men's Metropolitan Dress Sock (Pack of Three Pairs) at Amazon Men's Clothing store}}";
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
  
  public function testTemplateRenamingURLConvert() {
    $text='{{cite journal |url=http://www.paulselden.net/uploads/7/5/3/2/7532217/elsterrestrialization.pdf |title=Terrestrialization (Precambrian–Devonian) |last=Selden |first=Paul A. |year=2005 |encyclopedia=[[Encyclopedia of Life Sciences]] |publisher=[[John Wiley & Sons, Ltd.]] |doi=10.1038/npg.els.0004145 |format=DUDE}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('978-0470016176', $expanded->get('isbn'));
    $this->assertEquals('cite book', $expanded->wikiname());
    $this->assertEquals('http://www.paulselden.net/uploads/7/5/3/2/7532217/elsterrestrialization.pdf', $expanded->get('chapter-url'));
    $this->assertNull($expanded->get('url'));
    $this->assertNull($expanded->get('format'));
    $this->assertEquals('DUDE', $expanded->get('chapter-format'));
    $text='{{Cite book|url=http://www.sciencedirect.com/science/article/pii/B9780123864543000129|title=Encyclopedia of Toxicology (Third Edition)|last=Roberts|first=L.|date=2014|publisher=Academic Press|isbn=978-0-12-386455-0|editor-last=Wexler|editor-first=Philip|location=Oxford|pages=993–995|doi=10.1016/b978-0-12-386454-3.00012-9}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('http://www.sciencedirect.com/science/article/pii/B9780123864543000129', $expanded->get('chapter-url'));
    $this->assertNull($expanded->get('url'));
  }

  public function testDoiExpansion() {
    $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $prepared->wikiname());
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $prepared->get('doi'));
    
    $text = "{{Cite web | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $expanded = $this->prepare_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));
    $this->assertNull($expanded->get('url'));
    
    // Recognize official DOI targets in URL with extra fragments
    $text = '{{cite journal | url = https://link.springer.com/article/10.1007/BF00233701#page-1 | doi = 10.1007/BF00233701}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('url'));
    
    // Replace this test with a real URL (if one exists)
    $text = "{{Cite web | url = http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf}}"; // Fake URL, real DOI
    $expanded= $this->prepare_citation($text);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));
    // Do not drop PDF files, in case they are open access and the DOI points to a paywall
    $this->assertEquals('http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf', $expanded->get('url'));
  }

  public function testDoiExpansionBook() {
    $text = "{{cite book|doi=10.1007/978-981-10-3180-9_1}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('cite book', $expanded->wikiname());
    $this->assertEquals('978-981-10-3179-3', $expanded->get('isbn'));
  }
  
  public function testDoiEndings() {
    $text = '{{cite journal | doi=10.1111/j.1475-4983.2012.01203.x/full}}';
    $expanded = $this->process_citation($text);   
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));
    
    $text = '{{cite journal| url=http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $expanded->get('doi'));  
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

  public function testExpansionJstorBook() {
    $text = '{{Cite journal|url=https://www.jstor.org/stable/j.ctt6wp6td.10}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Verstraete', $expanded->get('last1'));
  }
 
  public function testAP_zotero() {
    $text = '{{cite web|author=Associated Press |url=https://www.theguardian.com/science/2018/feb/03/scientists-discover-ancient-mayan-city-hidden-under-guatemalan-jungle}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('author'));
    $this->assertNull($expanded->get('publisher'));
    $this->assertEquals('Associated Press', $expanded->get('agency'));
  }
 
  public function testGarbageRemovalAndSpacing() {
    // Also tests handling of upper-case parameters
    $text = "{{Cite web | title=Ellipsis... | pages=10-11| Edition = 3rd ed. |journal=My Journal| issn=1234-4321 | publisher=Unwarranted |issue=0|accessdate=2013-01-01}}";
    $prepared = $this->process_citation($text);
    // ISSN should be retained when journal is originally present
    $this->assertEquals('{{Cite journal | title=Ellipsis... | pages=10–11| edition = 3rd |journal=My Journal| issn=1234-4321 }}', $prepared->parsed_text());
    
    $text = "{{Cite web | Journal=My Journal| issn=1357-4321 | publisher=Unwarranted }}";
    $prepared = $this->prepare_citation($text); // Do not drop publisher at start
    $this->assertEquals('{{Cite journal | journal=My Journal| issn=1357-4321 | publisher=Unwarranted }}', $prepared->parsed_text());
    $expanded = $this->process_citation($text);  // Drop it at end
    $this->assertEquals('{{Cite journal | journal=My Journal| issn=1357-4321 }}', $expanded->parsed_text());
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
    $expanded = $this->process_citation("{{Cite journal|journal=eJournal}}");
    $this->assertEquals('eJournal', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|journal=EJournal}}");
    $this->assertEquals('eJournal', $expanded->get('journal'));
    $expanded = $this->process_citation("{{Cite journal|journal=ejournal}}");
    $this->assertEquals('eJournal', $expanded->get('journal'));
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
  
  public function testDropArchiveDotOrg() {
    $text = '{{Cite journal | publisher=archive.org}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('publisher'));
      
    $text = '{{Cite journal | website=archive.org|url=http://www.fake-url.com/NOT_REAL}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('http://www.fake-url.com/NOT_REAL', $expanded->get('url'));
    $this->assertNull($expanded->get('website'));
  }
 
  public function testLeaveArchiveURL() {
    $text = '{{cite book |chapterurl=http://faculty.haas.berkeley.edu/shapiro/thicket.pdf|isbn=978-0-262-60041-5|archiveurl=https://web.archive.org/web/20070704074830/http://faculty.haas.berkeley.edu/shapiro/thicket.pdf }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('https://web.archive.org/web/20070704074830/http://faculty.haas.berkeley.edu/shapiro/thicket.pdf', $expanded->get('archiveurl'));
  }

  public function testScriptTitle() {
    $text = "{{cite book |author={{noitalic|{{lang|zh-hans|国务院人口普查办公室、国家统计局人口和社会科技统计司编}}}} |date=2012 |script-title=zh:中国2010年人口普查分县资料 |location=Beijing |publisher={{noitalic|{{lang|zh-hans|中国统计出版社}}}} [China Statistics Press] |page= |isbn=978-7-5037-6659-6 }}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('title')); // Already have script-title that matches what google books gives us
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
    $this->assertEquals('Cite arXiv', $expanded->name());
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
    $this->assertEquals('{{MC Hammer says to not touch this}}', $expanded->get('doi'));
    $text = '{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}', $expanded->parsed_text());
  }
    
  public function testCrossRefEvilDoi() {
    $text = '{{cite journal | doi = 10.1002/(SICI)1097-0134(20000515)39:3<216::AID-PROT40>3.0.CO;2-#}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi-broken-date'));
    $this->assertEquals('39', $expanded->get('volume'));
  }

  public function testOpenAccessLookup() {
    $text = '{{cite journal|doi=10.1206/0003-0082(2008)3610[1:nrofwf]2.0.co;2}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.1.1.1001.5321', $expanded->get('citeseerx'));
    $this->assertEquals('2008', $expanded->get('year')); // DOI does work though
      
   // $text = '{{cite journal | vauthors = Bjelakovic G, Nikolova D, Gluud LL, Simonetti RG, Gluud C | title = Antioxidant supplements for prevention of mortality in healthy participants and patients with various diseases | journal = The Cochrane Database of Systematic Reviews | volume = 3 | issue = 3 | pages = CD007176 | date = 14 March 2012 | pmid = 22419320 | doi = 10.1002/14651858.CD007176.pub2 }}';
   // $expanded = $this->process_citation($text);
   // $this->assertNotNull($expanded->get('url')); // currently gives a url 
      
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
    
    $text = '{{citation|doi = 10.1007/978-3-642-60408-9_19}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('citeseerx')); // detect bad OA data

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
    $text = "{{cite book|pages=3333 <!-- yes --> }} {{cite book <!-- no --> | pages=3<nowiki>-</nowiki>6}} {{cite book | pages=3<pre>-</pre>6}} {{cite book | pages=3<math>-</math>6}} {{cite book | pages=3<score>-</score>6}} {{cite book | pages=3<chem>-</chem>6}}";
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
    $text='{{cite journal|url=http://fake.url/9999-9999(2002)152[0215:XXXXXX]2.0.CO;2}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('9999-9999', $expanded->get('issn')); // Fake to avoid cross-ref search
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
  
  public function testNoBibcodesForArxiv() {
    $text = "{{Cite arxiv|last=Sussillo|first=David|last2=Abbott|first2=L. F.|date=2014-12-19|title=Random Walk Initialization for Training Very Deep Feedforward Networks|eprint=1412.6558 |class=cs.NE}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('bibcode'));  // If this eventually gets a journal, we will have to change the test
  }

  public function testNoBibcodesForBookReview() {
    $text = '{{cite book|last1=Klein|first1=Edward |title=Elements of histology|url=https://books.google.com/books?id=08m1UWAKyEAC&pg=PA124|accessdate=January 29, 2017|year=1785|publisher=Lea|page=124}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('bibcode'));
    $this->assertNull($expanded->get('doi'));
  }
  
  public function testBibcodesBooks() {
    $this->requires_secrets(function() {
      $text = "{{Cite book|bibcode=1982mcts.book.....H}}";
      $expanded = $this->process_citation($text);
      $this->assertEquals('1982', $expanded->get('year'));
      $this->assertEquals('Houk', $expanded->get('last1'));
      $this->assertEquals('N.', $expanded->get('first1'));
      $this->assertNotNull($expanded->get('title'));
    });
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
    $this->assertEquals($album_link, $expanded->get('titlelink'));    
     
    // Double-check pages expansion
    $text = "{{Cite journal|pp. 1-5}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('1–5', $expanded->get('pages'));
      
    $text = "{{cite book|authorlinux=X}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('{{cite book|authorlink=X}}', $expanded->parsed_text());
      
    $text = "{{cite book|authorlinks33=X}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('{{cite book|authorlink33=X}}', $expanded->parsed_text());
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
    
  public function testDropPostscript() {
      $text = '{{citation|postscript=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals($text, $prepared->parsed_text());
      
      $text = '{{citation|postscript=.}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals($text, $prepared->parsed_text());
      
      $text = '{{cite journal|postscript=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{cite journal}}', $prepared->parsed_text());
      
      $text = '{{cite journal|postscript=.}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{cite journal}}', $prepared->parsed_text());
      
      $text = '{{cite journal|postscript=none}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals($text, $prepared->parsed_text());
  }
    
  public function testChangeParamaters() {
      // publicationplace
      $text = '{{citation|publicationplace=Home}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{citation|location=Home}}', $prepared->parsed_text());
      
      $text = '{{citation|publication-place=Home|location=Away}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals($text, $prepared->parsed_text());

      // publicationdate
      $text = '{{citation|publicationdate=2000}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{citation|date=2000}}', $prepared->parsed_text());
      
      $text = '{{citation|publicationdate=2000|date=1999}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals($text, $prepared->parsed_text());

      // origyear
      $text = '{{citation|origyear=2000}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{citation|year=2000}}', $prepared->parsed_text());
      
      $text = '{{citation|origyear=2000|date=1999}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals($text, $prepared->parsed_text()); 
 }

  public function testDropDuplicates() {
      $text = '{{citation|work=Work|journal=|magazine=|website=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals('{{citation|work=Work}}', $prepared->parsed_text());
      
      $text = '{{citation|work=Work|journal=Journal|magazine=Magazine|website=Website}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertEquals($text, $prepared->parsed_text());
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
      $this->assertEquals('{{cite book|chapter=A book chapter}}', $prepared->parsed_text());
      
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
    
  public function testDropAmazon() {
    $text = '{{Cite journal | publisher=amazon.com}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('publisher'));
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
    $this->assertTrue(in_array($expanded->get('date'), ['February 1935', '1935-02']));
    // Google recovers Feb 1935; Zotero returns 1935-02.
  }
  
  public function testLongAuthorLists() {
    $text = '{{cite web | https://arxiv.org/PS_cache/arxiv/pdf/1003/1003.3124v2.pdf}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Aad, G.', $expanded->first_author());
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
  
  public function testND() {  // n.d. is special case that template recognize.  Must protect final period.
    $text = '{{Cite journal|date =n.d.}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
    
    $text = '{{Cite journal|year=n.d.}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
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
     $this->assertEquals('Shannon, Claude E.', $prepared->first_author());
     $this->assertEquals('Shannon', $prepared->get('last1'));
     $this->assertEquals('Claude E.', $prepared->get('first1'));
     $this->assertEquals('379–423', $prepared->get('pages'));
     $this->assertEquals('27', $prepared->get('volume'));   
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
       $this->assertEquals('Clark, Herbert H.', $prepared->first_author());
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
    $this->assertEquals('Albertstein, Alfred A.', $prepared->first_author());
    $this->assertEquals('Charlie C.', $prepared->get('first3'));
    $this->assertEquals('etal', $prepared->get('displayauthors'));
  }
 
  public function testEtAlAsAuthor() {
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('author3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('last3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = etal. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('author3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = etal }}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('etal', $prepared->get('display-authors'));
    $this->assertNull($prepared->get('last3'));
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
    $text = "{{cite journal|last1=Bharti|first1=H.|last2=Guénard|first2=B.|last3=Bharti|first3=M.|last4=Economo|first4=E.P.|title=An updated checklist of the ants of India with their specific distributions in Indian states (Hymenoptera, Formicidae)|journal=ZooKeys|date=2016|volume=551|pages=1–83|doi=10.3897/zookeys.551.6767|pmid=26877665|pmc=4741291}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals('551', $expanded->get('issue'));
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
    
  public function testSICI() {
    $url = "https://www.fake-url.org/sici?sici=9999-9999(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
    $text = "{{Cite journal|url=$url}}";  // We use a rubbish ISSN and website so that this does not expand any more -- only test SICI code
    $expanded = $this->process_citation($text);
      
    $this->assertEquals('1961', $expanded->get('year'));
    $this->assertEquals('81', $expanded->get('volume'));
    $this->assertEquals('1', $expanded->get('issue'));
    $this->assertEquals('43', $expanded->get('pages'));
  }
  
  public function testJstorSICI() {
    $url = "https://www.jstor.org/sici?sici=0003-0279(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
    $text = "{{Cite journal|url=$url}}";
    $expanded = $this->process_citation($text);
      
    $this->assertEquals('594900', $expanded->get('jstor'));
    $this->assertEquals('1961', $expanded->get('year'));
    $this->assertEquals('81', $expanded->get('volume'));
    $this->assertEquals('1', $expanded->get('issue'));
    $this->assertEquals('43–52', $expanded->get('pages'));  // The jstor expansion add the page ending
  }
  
  public function testJstorSICIEncoded() {
    $text = '{{Cite journal|url=https://www.jstor.org/sici?sici=0003-0279(196101%2F03)81%3A1%3C43%3AWLIMP%3E2.0.CO%3B2-9}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('594900', $expanded->get('jstor'));
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
    
  public function testArxivMore1() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. Lett. 117, 211101 (2016)", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2016', $expanded->get('year'));
    $this->assertEquals('211101', $expanded->get('pages'));
  }
    
  public function testArxivMore2() {
    $text = "{{cite arxiv}}" ;
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. B 79, 115202 (2009)", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2009', $expanded->get('year'));
    $this->assertEquals('115202', $expanded->get('pages'));
  }
    
  public function testArxivMore3() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Acta Phys. Polon. B41 (2010), 2325-2333", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2010', $expanded->get('year'));
    $this->assertEquals('2325–2333', $expanded->get('pages'));
  }
    
  public function testArxivMore4() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. B 78, 245315 (2008)", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2008', $expanded->get('year'));
    $this->assertEquals('245315', $expanded->get('pages'));
  }
    
  public function testArxivMore5() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal of Noses37:1234,2012", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2012', $expanded->get('year'));
    $this->assertEquals('1234', $expanded->get('pages'));
    $this->assertEquals('37', $expanded->get('volume'));
  }

  public function testArxivMore6() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("", $expanded, TRUE);  // Make sure that empty string does not crash
    $this->assertEquals('cite arxiv', $expanded->wikiname());
  }
   
  public function testArxivMore7() {
    $text = "{{cite arxiv|date=1999}}"; // verify date update
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal 78 (2011) 888-999", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2011', $expanded->get('year'));
    $this->assertEquals('888–999', $expanded->get('pages'));
  }

  public function testArxivMore8() {
    $text = "{{cite arxiv|year=1999}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal, 11, 62 (2001)", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2001', $expanded->get('year'));
    $this->assertEquals('62', $expanded->get('pages'));
  }
    
  public function testArxivMore9() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal, 83:13232, 2018", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2018', $expanded->get('year'));
    $this->assertEquals('13232', $expanded->get('pages'));
  } 
  public function testArxivMore10() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal 1 (4), 2311 (1980)", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('1980', $expanded->get('year'));
    $this->assertEquals('2311', $expanded->get('pages'));
  }
    
  public function testArxivMore11() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("ZooKeys 212 (1999), 032412332, 33 pages", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('1999', $expanded->get('year'));
    $this->assertEquals('032412332', $expanded->get('pages'));
  }
 
  public function testArxivMore12() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("A&A 619, A49 (2018)", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2018', $expanded->get('year'));
    $this->assertEquals('Astronomy & Astrophysics', $expanded->get('journal'));
    $this->assertEquals('A49', $expanded->get('volume'));
    $this->assertEquals('619', $expanded->get('pages'));
  }
 
  public function testArxivMore13() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("ApJ, 767:L7, 2013 April 10", $expanded, TRUE);
    $this->assertEquals('The Astrophysical Journal', $expanded->get('journal'));
    $this->assertEquals('2013', $expanded->get('year'));
  }
 
  public function testArxivMore14() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Astrophys.J.639:L43-L46,2006F", $expanded, TRUE);
    $this->assertEquals('The Astrophysical Journal', $expanded->get('journal'));
    $this->assertEquals('2006', $expanded->get('year'));
  }

  public function testArxivMore15() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Information Processing Letters 115 (2015), pp. 633-634", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2015', $expanded->get('year'));
    $this->assertEquals('633–634', $expanded->get('pages'));
  }

  public function testArxivMore16() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Theoretical Computer Science, Volume 561, Pages 113-121, 2015", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2015', $expanded->get('year'));
    $this->assertEquals('113–121', $expanded->get('pages'));
  }

  public function testArxivMore17() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Scientometrics, volume 69, number 3, pp. 669-687, 2006", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2006', $expanded->get('year'));
    $this->assertEquals('669–687', $expanded->get('pages'));
  }

  public function testArxivMore18() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("International Journal of Geographical Information Science, 23(7), 2009, 823-837.", $expanded, TRUE);
    $this->assertEquals('cite journal', $expanded->wikiname());
    $this->assertEquals('2009', $expanded->get('year'));
    $this->assertEquals('823–837', $expanded->get('pages'));
  }
  
  public function testArxivMore19() {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("journal of Statistical Mechanics: Theory and Experiment, 2008 July", $expanded, TRUE);
    $this->assertEquals('cite arxiv', $expanded->wikiname());
    $this->assertNull($expanded->get('year'));
  }
 
   public function testDoiInline() {
    $text = '{{citation | title = {{doi-inline|10.1038/nature10000|Funky Paper}} }}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('Nature', $expanded->get('journal'));
    $this->assertEquals('Funky Paper', $expanded->get('title'));
    $this->assertEquals('10.1038/nature10000', $expanded->get('doi'));
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

    $text = '{{Cite journal|pages=15|doi=10.1016/j.biocontrol.2014.06.004}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('15–22', $expanded->get('pages')); // Converted should use long dashes

    $text = '{{Cite journal|doi=10.1007/s11746-998-0245-y|at=pp.425–439, see Table&nbsp;2 p.&nbsp;426 for tempering temperatures}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('pp.425–439, see Table&nbsp;2 p.&nbsp;426 for tempering temperatures', $expanded->get('at')); // Leave complex at=

    $text = '{{cite book|pages=See [//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]}}';
    $expanded = $this->process_citation($text); // Do not change this hidden URL
    $this->assertEquals('See [//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]', $expanded->get('pages'));
   
    $text = '{{cite book|pages=[//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]}}';
    $expanded = $this->process_citation($text); // Do not change dashes in this hidden URL, but upgrade URL to real one
    $this->assertEquals('[https://books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]', $expanded->get('pages'));
  }
 
  public function testBogusPageRanges() {  // At some point this test will age out (perhaps add special TRAVIS code to template.php
    $text = '{{Cite journal| doi = 10.1017/jpa.2018.43|title = New well-preserved scleritomes of Chancelloriida from early Cambrian Guanshan Biota, eastern Yunnan, China|journal = Journal of Paleontology|volume = 92|issue = 6|pages = 1–17|year = 2018|last1 = Zhao|first1 = Jun|last2 = Li|first2 = Guo-Biao|last3 = Selden|first3 = Paul A}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('955–971', $expanded->get('pages')); // Converted should use long dashes
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
    $text = '{{cite book |date=March 12, 1913 |title=Session Laws of the State of Washington, 1913 |chapter=Chapter 65: Classifying Public Highways |page=221 |chapterurl=http://leg.wa.gov/CodeReviser/documents/sessionlaw/1913c65.pdf |publisher=Washington State Legislature |accessdate=August 30, 2018}}';
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
      $text = '{{cite web | url=http://www.worldcat.org/oclc/9334453}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('cite book', $expanded->wikiname());
      $this->assertNull($expanded->get('url'));
      $this->assertEquals('9334453', $expanded->get('oclc'));
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
    
  public function testArxivPDf() {
    $text = '{{cite web|url=https://arxiv.org/ftp/arxiv/papers/1312/1312.7288.pdf}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('1312.7288', $expanded->get('arxiv'));  
  }
  
  public function testEmptyCitations() {
    $text = 'bad things like {{cite journal}}{{cite book|||}}{{cite arxiv}}{{cite web}} should not crash bot'; // bot removed pipes
    $expanded = $this->process_page($text);
    $this->assertEquals('bad things like {{cite journal}}{{cite book}}{{cite arxiv}}{{cite web}} should not crash bot', $expanded->parsed_text());
  }
 
  public function testBadBibcodeARXIVPages() {
    $text = '{{cite journal|bibcode=2017arXiv171102260L}}'; // Some bibcodes have pages set to arXiv:1711.02260
    $expanded = $this->process_citation($text);
    $pages = $expanded->get('pages');
    $volume = $expanded->get('volume');
    $this->assertEquals(FALSE, stripos($pages, 'arxiv'));
    $this->assertEquals(FALSE, stripos('1711', $volume));
    $this->assertNull($expanded->get('journal'));  // if we get a journal, the data is updated and test probably no longer gets bad data
    $text = "{{cite journal|bibcode=1995astro.ph..8159B|pages=8159}}"; // Pages from bibcode have slash in it astro-ph/8159B
    $expanded = $this->process_citation($text);
    $pages = $expanded->get('pages');
    $this->assertEquals(FALSE, stripos($pages, 'astro'));
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
    $this->assertEquals('RELICS: A Candidate z ∼ 10 Galaxy Strongly Lensed into a Spatially Resolved Arc', $expanded->get('title'));
  }

  public function testDropGoogleWebsite() {
    $text = "{{Cite book|website=Google.Com|url=http://Invalid.url.not-real.com/}}"; // Include a fake URL so that we are not testing: if (no url) then drop website
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('website'));
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
    
  //public function testFindHDL() {
  //  $text = '{{cite journal | pmid = 14527634 | doi = 10.1016/S1095-6433(02)00368-9 }}';
  //  $expanded = $this->process_citation($text);
  //  $this->assertEquals('10397/34754', $expanded->get('hdl'));
  //}

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
      
    $text = '{{cite journal |url=http://citeseerx.ist.psu.edu/viewdoc/download?doi=10.1.1.923.345&rep=rep1&type=pdf}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('10.1.1.923.345', $prepared->get('citeseerx'));
    $text = '{{cite journal |url=http://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.923.345}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('10.1.1.923.345', $prepared->get('citeseerx'));
 
  }
    
  public function testStripPDF() {
    $text = '{{cite journal |url=https://link.springer.com/content/pdf/10.1007/BF00428580.pdf}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('10.1007/BF00428580', $prepared->get('doi'));
  }
  
  public function testRemovePublisherWithWork() {
    $text = '{{cite journal|jstor=1148172|title=Strategic Acupuncture|work=Foreign Policy|issue=Winter 1980|pages=44–61|publisher=Washingtonpost.Newsweek Interactive, LLC|year=1980}}';
    $expanded = $this->process_citation($text);
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
   
    $text = '{{cite web|url=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22', $prepared->get('url'));
    $text = '{{cite web|url=http://www.google.com/search?hl=en&safe=off&client=firefox-a&rls=com.ubuntu%3Aen-US%3Aunofficial&q=%22west+coast+hotel+co.+v.+parrish%22+(site%3Anewsweek.com+OR+site%3Apost-gazette.com+OR+site%3Ausatoday.com+OR+site%3Awashingtonpost.com+OR+site%3Atime.com+OR+site%3Areuters.com+OR+site%3Aeconomist.com+OR+site%3Amiamiherald.com+OR+site%3Alatimes.com+OR+site%3Asfgate.com+OR+site%3Achicagotribune.com+OR+site%3Anytimes.com+OR+site%3Awsj.com+OR+site%3Ausnews.com+OR+site%3Amsnbc.com+OR+site%3Anj.com+OR+site%3Atheatlantic.com)&aq=o&oq=&aqi=}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('https://www.google.com/search?hl=en&safe=off&q=%22west+coast+hotel+co.+v.+parrish%22+(site%3Anewsweek.com+OR+site%3Apost-gazette.com+OR+site%3Ausatoday.com+OR+site%3Awashingtonpost.com+OR+site%3Atime.com+OR+site%3Areuters.com+OR+site%3Aeconomist.com+OR+site%3Amiamiherald.com+OR+site%3Alatimes.com+OR+site%3Asfgate.com+OR+site%3Achicagotribune.com+OR+site%3Anytimes.com+OR+site%3Awsj.com+OR+site%3Ausnews.com+OR+site%3Amsnbc.com+OR+site%3Anj.com+OR+site%3Atheatlantic.com)', $prepared->get('url'));
  }
 
  public function testDoiValidation() {
    $text = '{{cite web|last=Daintith|first=John|title=tar|url=http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022|work=Oxford University Press|publisher=A dictionary of chemistry|edition=6th|accessdate=14 March 2013}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('10.1093/acref/9780199204632.001.0001', $prepared->get('doi'));
  }
    
  public function testVolumeIssueDemixing() {
    $text = '{{cite journal|volume = 12(44)}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('44', $prepared->get('issue'));
    $this->assertEquals('12', $prepared->get('volume'));
    $text = '{{cite journal|volume = 12(44-33)}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('44–33', $prepared->get('issue'));
    $this->assertEquals('12', $prepared->get('volume'));
    $text = '{{cite journal|volume = 12(44-33)| number=222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('222', $prepared->get('number'));
    $this->assertEquals('12(44-33)', $prepared->get('volume'));
   
    $text = '{{cite journal|volume = 12, no. 44-33}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('44–33', $prepared->get('issue'));
    $this->assertEquals('12', $prepared->get('volume'));
    $text = '{{cite journal|volume = 12, no. 44-33| number=222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertEquals('222', $prepared->get('number'));
    $this->assertEquals('12, no. 44-33', $prepared->get('volume'));
  }
 
  public function testSpaces() {
      // None of the "spaces" in $text are normal spaces.  They are U+2000 to U+200A
      $text     = "{{cite book|title=X X X X X X X X X X X X}}";
      $text_out = '{{cite book|title=X X X X X X X X X X X X}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals($text_out, $expanded->parsed_text());
      $this->assertTrue($text != $text_out); // Verify test is valid -- We want to make sure that the spaces in $text are not normal spaces
  }
 
  public function testMultipleYears() {
    $text = '{{cite journal|doi=10.1080/1323238x.2006.11910818}}'; // Crossref has <year media_type="online">2017</year><year media_type="print">2006</year>
    $expanded = $this->process_citation($text);
    $this->assertEquals('2006', $expanded->get('year'));
  }
 
  public function testDuplicateParametersFlagging() {
    $text = '{{cite web|year=2010|year=2011}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('2011', $expanded->get('year'));
    $this->assertEquals('2010', $expanded->get('DUPLICATE_year'));
    $text = '{{cite web|year=|year=2011}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('2011', $expanded->get('year'));
    $this->assertNull($expanded->get('DUPLICATE_year'));
    $text = '{{cite web|year=2011|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('2011', $expanded->get('year'));
    $this->assertNull($expanded->get('DUPLICATE_year'));
    $text = '{{cite web|year=|year=|year=2011|year=|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('2011', $expanded->get('year'));
    $this->assertNull($expanded->get('DUPLICATE_year'));
    $text = '{{cite web|year=|year=|year=|year=|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('{{cite web|year=}}', $expanded->parsed_text());
  }
 
  public function testDoiThatIsJustAnISSN() {
    $text = '{{cite web |url=http://onlinelibrary.wiley.com/journal/10.1002/(ISSN)1099-0739/homepage/EditorialBoard.html}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals('10.1002/(ISSN)1099-0739', $expanded->get('doi'));
    $this->assertEquals('http://onlinelibrary.wiley.com/journal/10.1002/(ISSN)1099-0739/homepage/EditorialBoard.html', $expanded->get('url'));
    $this->assertEquals('cite web', $expanded->wikiname());
  }
  /* TODO 
  Test adding a paper with > 4 editors; this should trigger displayeditors
  Test finding a DOI and using it to expand a paper [See testLongAuthorLists - Arxiv example?]
  */    
}
