<?php
declare(strict_types=1);

/*
 * Tests for Zotero.php - some of these work even when zotero fails because they check for the absence of bad data
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class ZoteroTest extends testBaseClass {
 
  public function testZoteroExpansion_biorxiv() : void {
    $text = '{{Cite journal| biorxiv=326363 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Sunbeam: An extensible pipeline for analyzing metagenomic sequencing experiments', $expanded->get2('title'));
    $text = '{{Cite journal| biorxiv=326363 |doi=10.0000/Rubbish_bot_failure_test}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Sunbeam: An extensible pipeline for analyzing metagenomic sequencing experiments', $expanded->get2('title'));
  }
 
  public function testDropUrlCode() : void {  // url is same as one doi points to
     $text = '{{cite journal |pmc=XYZ|url=https://pubs.rsc.org/en/Content/ArticleLanding/1999/CP/a808518h|doi=10.1039/A808518H|title=A study of FeCO+ with correlated wavefunctions|journal=Physical Chemistry Chemical Physics|volume=1|issue=6|pages=967–975|year=1999|last1=Glaesemann|first1=Kurt R.|last2=Gordon|first2=Mark S.|last3=Nakano|first3=Haruyuki|bibcode=1999PCCP....1..967G}}';
     $expanded = $this->process_citation($text);
     $this->assertNotNull($expanded->get2('url'));
  }
  public function testDropUrlCode2() : void { // URL redirects to URL with the same DOI
     $text = '{{cite journal | last = De Vivo | first = B. | title = New constraints on the pyroclastic eruptive history of the Campanian volcanic Plain (Italy) | url = http://www.springerlink.com/content/8r046aa9t4lmjwxj/ | doi = 10.1007/s007100170010 }}';
     $expanded = $this->process_citation($text);
     $this->assertNotNull($expanded->get2('url'));
  }
  public function testDropUrlCode3() : void { // url is same as one doi points to, except for http vs. https
     $text = "{{cite journal |pmc=XYZ| first = Luca | last = D'Auria | year = 2015 | title = Magma injection beneath the urban area of Naples | url = http://www.nature.com/articles/srep13100 | doi=10.1038/srep13100 }}";
     $expanded = $this->process_citation($text);
     $this->assertNotNull($expanded->get2('url'));
  }

  public function testMR() : void {
     $text = "{{cite journal | mr = 22222 }}";
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get2('doi'));
  }
 
  public function testAccessDateAndDate() : void {
     $text = "{{cite journal | archive-date=2020 |accessdate=2020|title=X|journal=X|date=2020|issue=X|volume=X|chapter=X|pages=X|last1=X|first1=X|last2=X|first2=X }}";
     $template = $this->make_citation($text);  // Does not do anything other than touch code
     $this->assertFalse(Zotero::expand_by_zotero($template));
  }
 
  public function testDropSomeProxies() : void {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=proxy.libraries}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testDropSomeProxiesA() : void {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.sciencedirect.com/science/article/B1234-13241234-343242/}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testDropSomeProxiesB() : void {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.sciencedirect.com/science/article/pii/2222}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testDropSomeProxiesC() : void {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.springerlink.com/content/s}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testDropSomeProxiesD() : void {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://insights.ovid.com/pubmed|pmid=2222}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testDropSomeProxiesE() : void {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://cnn.com/|doi-access=free|url-status=dead|doi=10.0000/10000}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNull($template->get2('url'));
   }

   public function testDropSomeEquivURLS2() : void {
    $text = "{{cite journal|pmc=XYZ|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://iopscience.iop.org/324234}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
   }

   public function testDropSomeProxies3() : void {  
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://journals.lww.com/3243243}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
   }

   public function testDropSomeProxies4() : void {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://wkhealth.com/3243243}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
   }

   public function testDropSomeURLEquivs5() : void {
    $text = "{{cite journal|pmc=XYZ|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://bmj.com/cgi/pmidlookup/sss|pmid=333}}";
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testSimpleIEEE() : void {
    $url = "http://ieeexplore.ieee.org/arnumber=123456789";
    $url = Zotero::url_simplify($url);
    $this->assertSame('http:/ieeexplore.ieee.org/123456789', $url);
  }
 
  public function testIEEEdoi() : void {
    $url = "https://ieeexplore.ieee.org/document/4242344";
    $template = $this->process_citation('{{cite journal | url = ' . $url . ' }}');
    $this->assertSame('10.1109/ISSCC.2007.373373', $template->get2('doi'));
  }
 
  public function testIEEEdropBadURL() : void {
    $template = $this->process_citation('{{cite journal | url = https://ieeexplore.ieee.org/document/4242344341324324123412343214 |doi =10.1109/ISSCC.2007.373373 }}');
    $this->assertNull($template->get2('url'));
  }

  public function testZoteroResponse1() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_response = ' ';
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
   
  public function testZoteroResponse2() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_response = 'Remote page not found';
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

   public function testZoteroResponse3() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_response = 'Sorry, but 502 Bad Gateway was found';
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

  public function testZoteroResponse4() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_response = 'this will not be found to be valide JSON dude';
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse5() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data = '';
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse6() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data = 'Some stuff that should be encoded nicely';
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse7() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data = (object) array('title' => 'not found');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse8() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'NOT FOUND');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

  public function testZoteroResponse9() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'oup accepted manuscript', 'itemType' => 'webpage');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
    $zotero_data[0] = (object) array('bookTitle' => 'oup accepted manuscript', 'itemType' => 'webpage', 'title'=> 'this is good stuff');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
    $zotero_data[0] = (object) array('publicationTitle' => 'oup accepted manuscript', 'itemType' => 'webpage', 'title'=> 'this is good stuff');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse10() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('bookTitle' => '(pdf) This is a Title (pdf)', 'publisher' => 'JoeJoe', 'title' => 'Billy', 'itemType' => 'bookSection');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Billy', $template->get2('chapter'));
    $this->assertSame('JoeJoe', $template->get2('publisher'));
    $this->assertSame('This is a Title', $template->get2('title'));
  }

   public function testZoteroResponse11() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'journalArticle');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite journal', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
   }
   
   public function testZoteroResponse12() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'magazineArticle');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite magazine', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
  }
 
   public function testZoteroResponse13() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'blogPost');
    $zotero_response = json_encode($zotero_data);
    $url_kind = '';
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite web', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
  }
 
   public function testZoteroResponse14() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'film');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite web', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
  }
 
   public function testZoteroResponse15() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'thesis', 'university' => 'IowaIowa', 'thesisType' => 'Masters');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite thesis', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('IowaIowa', $template->get2('publisher'));
    $this->assertSame('Masters', $template->get2('type'));
  }

   public function testZoteroResponse16() : void {
    $text = '{{cite news|id=|publisher=Associated Press|author=Associated Press}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = 'http://cnn.com/story';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite news', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('Associated Press', $template->get2('agency'));
    $this->assertNull($template->get2('author'));
    $this->assertNull($template->get2('publisher'));
  }
 
   public function testZoteroResponse17() : void {
    $text = '{{cite news|id=|publisher=Reuters|author=Reuters}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = 'http://cnn.com/story';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite news', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('Reuters', $template->get2('agency'));
    $this->assertNull($template->get2('author'));
    $this->assertNull($template->get2('publisher'));
  }

   public function testZoteroResponse18() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'PMID: 25408617 PMCID: PMC4233402');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('25408617', $template->get2('pmid'));
    $this->assertSame('4233402', $template->get2('pmc'));
  }

   public function testZoteroResponse19() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'PMID: 25408617, 25408617');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('25408617', $template->get2('pmid'));
  }
 
   public function testZoteroResponse20() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'IMDb ID: nm321432123');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get2('title'));
  }
 
   public function testZoteroResponse21() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = 'en.wikipedia.org'; // No date citation
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'date' => '2010');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertNull($template->get2('date'));
    $this->assertNull($template->get2('year'));
  }
 
  public function testZoteroResponse22() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'bookSection');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
  }
 
   public function testZoteroResponse23() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $author[0] = array(0 => 'This is not a human author by any stretch of the imagination correspondent corporation', 1 => 'correspondent');
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'author' => $author);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertTrue($template->blank(['author', 'author1', 'last1', 'first1', 'first', 'last']));
  }
 
  public function testZoteroResponse24() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'DOI' => 'http://dx.doi.org/10.1021/acs.analchem.8b04567' );
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('10.1021/acs.analchem.8b04567', $template->get2('doi'));
  }
 
  public function testZoteroResponse25() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $creators[0] = (object) array('creatorType' => 'editor', 'firstName' => "Joe", "lastName" => "");
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'report', 'creators' => $creators);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('Joe', $template->get2('editor1'));
  }
 
  public function testZoteroResponse26() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $creators[0] = (object) array('creatorType' => 'translator', 'firstName' => "Joe", "lastName" => "");
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'report', 'creators' => $creators);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('Joe', $template->get2('translator1'));
  }
 
  public function testZoteroResponse27() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => "������Junk�����������", 'itemType' => 'webpage');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertNull($template->get2('title'));
  }
  
 public function testZoteroResponse28() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'type: dataset');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get2('title'));
  }
 
  public function testZoteroResponse29() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $creators[0] = (object) array('creatorType' => 'author', 'firstName' => "Joe", "lastName" => "");
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'report', 'creators' => $creators);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('Joe', $template->get2('author1'));
  }
 
   public function testZoteroResponse30() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $author[0] = array(0 => 'Smith', 1 => '');
    $author[1] = array(0 => 'Johnson', 1 => '');
    $author[2] = array(0 => 'Jackson', 1 => '');
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'author' => $author);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('Smith', $template->get2('author1'));
    $this->assertSame('Johnson', $template->get2('author2'));
    $this->assertSame('Jackson', $template->get2('author3'));
  }
 
   public function testZoteroResponse31() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_response = 'No items returned from any translator';
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

   public function testZoteroResponse32() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_response = 'An error occurred during translation. Please check translation with the Zotero client.';
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse33() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $creators[0] = (object) array('creatorType' => 'author', 'firstName' => "Joe", "lastName" => "");
    $zotero_data[0] = (object) array('title' => 'Central Authentication Service', 'itemType' => 'report', 'creators' => $creators);
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

   public function testZoteroResponse34() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'DOI: 10.1038/546031a');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('10.1038/546031a', $template->get2('doi'));
  }
 
  public function testZoteroResponse35() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_response = 'Internal Server Error';
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse36() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'journalArticle', 'publicationTitle' => "X");
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite journal', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('X', $template->get2('journal'));
  }
 
  public function testZoteroResponse37() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'newspaperArticle', 'publicationTitle' => "X");
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite news', $template->wikiname());
    $this->assertSame('Billy', $template->get2('title'));
    $this->assertSame('X', $template->get2('newspaper'));
  }
 
  public function testZoteroResponse38() : void {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = strtotime('12 December 2010');
    $url = '';
    $url_kind = '';
    $zotero_data[0] = (object) array('date' => '12 December 2020', 'title' => 'Billy', 'itemType' => 'newspaperArticle', 'publicationTitle' => "X");
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse39() : void {
    $text = '{{cite journal|url=https://www.sciencedirect.com/science/article/pii/S0024379512004405|title=Geometry of the Welch bounds|journal=Linear Algebra and Its Applications|volume=437|issue=10|pages=2455–2470|year=2012|last1=Datta|first1=S.|last2=Howard|first2=S.|last3=Cochran|first3=D.}}';
    $template = $this->make_citation($text);
    $access_date = 0;
    $url = 'https://www.sciencedirect.com/science/article/pii/S0024379512004405';
    $url_kind = 'url';
    $zotero_data[0] = (object) array('title' => 'Geometry of the Welch bounds', 'itemType' => 'journalArticle', 'DOI' => '10.1016/j.laa.2012.05.036');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(Zotero::process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertNotNull($template->get2('url')); // Used to drop when gets doi
    $this->assertSame('10.1016/j.laa.2012.05.036', $template->get2('doi'));
  }

  public function testRemoveURLthatRedirects() : void { // This URL is a redirect -- tests code that does that
    $text = '{{cite journal|pmc=XYZ|doi=10.1021/acs.analchem.8b04567|url=http://shortdoi.org/gf7sqt|pmid=30741529|pmc=6526953|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|pages=4346–4356|year=2019|last1=Colby|first1=Sean M.|last2=Thomas|first2=Dennis G.|last3=Nuñez|first3=Jamie R.|last4=Baxter|first4=Douglas J.|last5=Glaesemann|first5=Kurt R.|last6=Brown|first6=Joseph M.|last7=Pirrung|first7=Meg A.|last8=Govind|first8=Niranjan|last9=Teeguarden|first9=Justin G.|last10=Metz|first10=Thomas O.|last11=Renslow|first11=Ryan S.}}';
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNotNull($template->get2('url'));
  }
 
   public function testUseArchive() : void {
    $text = '{{cite journal|archive-url=https://web.archive.org/web/20160418061734/http://www.weimarpedia.de/index.php?id=1&tx_wpj_pi1%5barticle%5d=104&tx_wpj_pi1%5baction%5d=show&tx_wpj_pi1%5bcontroller%5d=article&cHash=0fc8834241a91f8cb7d6f1c91bc93489}}';
    $template = $this->make_citation($text);
    $tmp_array = array($template);
    expand_templates_from_archives($tmp_array);
    for ($x = 0; $x <= 10; $x++) {
      if ($template->get2('title') == NULL) {
        sleep(2); // Sometimes fails for no good reason
        expand_templates_from_archives($tmp_array);
      }
    }
    $this->assertSame('Goethe-Schiller-Denkmal - Weimarpedia', $template->get2('title'));
    
    $text = '{{cite journal|series=Xarchive-url=https://web.archive.org/web/20160418061734/http://www.weimarpedia.de/index.php?id=1&tx_wpj_pi1%5barticle%5d=104&tx_wpj_pi1%5baction%5d=show&tx_wpj_pi1%5bcontroller%5d=article&cHash=0fc8834241a91f8cb7d6f1c91bc93489}}';
    $template = $this->make_citation($text);
    $tmp_array = array($template);
    expand_templates_from_archives($tmp_array);
    $this->assertNull($template->get2('title'));
  }
 
  public function testZoteroExpansion_doi_not_from_crossref() : void {
   $text = '{{Cite journal|doi=.3233/PRM-140291}}';
   $expanded = $this->make_citation($text);
   $expanded->verify_doi();
   $this->assertSame('10.3233/PRM-140291', $expanded->get2('doi'));
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal|doi=10.3233/PRM-140291}}'; // mEDRA DOI - they do not provide RIS information from dx.doi.org
    $expanded = $this->process_citation($text);
    $this->assertTrue(strpos($expanded->get2('journal'), 'Journal of Pediatric Rehabilitation Medicine') === 0);// Sometimes includes a journal of....
   });
  }
 
  public function testCitationTemplateWithoutJournalZotero() : void {
   $this->requires_zotero(function() : void {
    $text = '{{citation|url=http://www.word-detective.com/2011/03/mexican-standoff/|title=Mexican standoff|work=The Word Detective|accessdate=2013-03-21}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('isbn')); // This citation used to crash code in ISBN search.  Mostly checking "something" to make Travis CI happy
   });
  }
 
  public function testZoteroExpansionAccessDates() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24663/|access-date=1978-12-12}}';  // Access date is too far in past, will not expand
    $expanded = $this->expand_via_zotero($text);
    $this->assertSame($text, $expanded->parsed_text());
   });
  }

  public function testZoteroExpansionNRM() : void {
   $this->requires_zotero(function() : void {
    $text = '{{cite journal | url = http://www.nrm.se/download/18.4e32c81078a8d9249800021554/Bengtson2004ESF.pdf}}';
    $expanded = $this->process_page($text);
    $this->assertTrue(TRUE); // Gives one fuzzy match.  For now we just check that this doesn't crash PHP.
    // In future we should use this match to expand citation.
   });
  }

  public function testNoneAdditionOfAuthor() : void {
   $this->requires_zotero(function() : void {
    // Rubbish author listed in page metadata; do not add. 
    $text = "{{cite web |url=http://www.westminster-abbey.org/our-history/people/sir-isaac-newton}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('last1'));
   });
  }
  
  public function testDateTidiness() : void {
   $this->requires_zotero(function() : void {
    $text = "{{cite web|title= Gelada| website= nationalgeographic.com |url= http://animals.nationalgeographic.com/animals/mammals/gelada/ |publisher=[[National Geographic Society]]|accessdate=7 March 2012}}";
    $expanded = $this->expand_via_zotero($text);
    $date = $expanded->get('date');
    $date = str_replace('10 May 2011', '', $date); // Sometimes we get no date
    $this->assertSame('', $date);
   });
  }
 
  public function testZoteroBadVolumes() : void { // has ( and such in it
   $this->requires_zotero(function() : void {
    $text = '{{cite journal|chapterurl=https://biodiversitylibrary.org/page/32550604}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertNull($expanded->get2('volume'));
   });
  }
 
  public function testZoteroKoreanLanguage() : void {
   $this->requires_zotero(function() : void {
    $text = '{{cite journal|chapter-url=http://www.newsen.com/news_view.php?uid=201606131737570410}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertNull($expanded->get2('title')); // Hopefully will work some day and not give � character
   });
  }
 
  public function testZoteroExpansion_osti() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal| osti=1406676 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1016/j.ifacol.2017.08.010', $expanded->get2('doi'));
   });
  $text = '{{Cite journal| osti=1406676 }}';
  $expanded = $this->process_citation($text);
  $this->assertSame($text, $expanded->parsed_text()); // Verify that lack of requires_zotero() blocks zotero
  }
    
  public function testZoteroExpansion_rfc() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal| rfc=6679 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Explicit Congestion Notification (ECN) for RTP over UDP', $expanded->get2('title'));
   });
  }
  
  public function testZoteroRespectDates() : void {
   $this->requires_zotero(function() : void {
      $text = '{{Use mdy dates}}{{cite web|url=https://www.nasa.gov/content/profile-of-john-glenn}}';
      $page = $this->process_page($text);
      $this->assertTrue((bool) strpos($page->parsed_text(), 'December 5, 2016'));
      $text = '{{Use dmy dates}}{{cite web|url=https://www.nasa.gov/content/profile-of-john-glenn}}';
      $page = $this->process_page($text);
      $this->assertTrue((bool) strpos($page->parsed_text(), '5 December 2016'));
   });
  }
 
  public function testZoteroExpansionPII() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal|url = https://www.sciencedirect.com/science/article/pii/S0024379512004405}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertSame('10.1016/j.laa.2012.05.036', $expanded->get2('doi'));
    $this->assertNull($expanded->get2('url')); // Recognize canonical publisher URL as duplicate of valid doi
   });
  }
      
  public function testZoteroExpansion_ssrn() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal| ssrn=195630 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('The Pricing of Internet Stocks', $expanded->get2('title'));
    $this->assertSame('September 1999', $expanded->get2('date'));
   });
  }
 
  public function testZoteroExpansionNYT() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal|url =https://www.nytimes.com/2018/06/11/technology/net-neutrality-repeal.html}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertSame("Net Neutrality Has Officially Been Repealed. Here's How That Could Affect You", $expanded->get2('title'));
    $this->assertSame('Keith', $expanded->get2('first1')); // Would be tidied to 'first' in final_parameter_tudy
    $this->assertSame('Collins', $expanded->get2('last1'));
    $this->assertSame('cite news', $expanded->wikiname());
   });
  }
 
  public function testZoteroExpansionNBK() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24662/|access-date=2099-12-12}}';  // Date is before access-date so will expand
    $expanded = $this->expand_via_zotero($text);
    $this->assertSame('Continuing Efforts to More Efficiently Use Laboratory Animals', $expanded->get2('title'));
    $this->assertSame('2004', $expanded->get2('year'));
    $this->assertSame('National Academies Press (US)', $expanded->get2('publisher'));
   });
  }
 
  public function testZoteroTruncateDOI() : void {
   $this->requires_zotero(function() : void {
    $text = '{{cite journal|url=http://www.oxfordhandbooks.com/view/10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('doi-broken-date'));
    $this->assertSame('http://www.oxfordhandbooks.com/view/10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023', $expanded->get2('url'));
    $this->assertSame('10.1093/oxfordhb/9780199552238.003.0023', $expanded->get2('doi'));
   });
  }
 
  public function testZoteroExpansion_hdl() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal| hdl=10411/OF7UCA }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Replication Data for: Perceiving emotion in non-social targets: The effect of trait empathy on emotional through art', $expanded->get2('title'));
   });
  }
 
  public function testZoteroExpansion_citeseerx() : void {
   $this->requires_zotero(function() : void {
    $text = '{{Cite journal| citeseerx=10.1.1.483.8892 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Chemical Kinetics Models for the Fatigue Behavior of Fused Silica Optical Fiber', $expanded->get2('title'));
   });
  }
 
}
