<?php

/*
 * Tests for zotero.php - some of these work even when zotero fails because they check for the absence of bad data
 */

require_once __DIR__ . '/../testBaseClass.php';
 
class ZoteroTest extends testBaseClass {
 
  public function testZoteroExpansion_biorxiv() {
    $text = '{{Cite journal| biorxiv=326363 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Sunbeam: An extensible pipeline for analyzing metagenomic sequencing experiments', $expanded->get('title'));
    $text = '{{Cite journal| biorxiv=326363 |doi=10.0000/Rubbish_bot_failure_test}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Sunbeam: An extensible pipeline for analyzing metagenomic sequencing experiments', $expanded->get('title'));
  }
 
  public function testDropUrlCode() {  // url is same as one doi points to
     $text = '{{cite journal |pmc=XYZ|url=https://pubs.rsc.org/en/Content/ArticleLanding/1999/CP/a808518h|doi=10.1039/A808518H|title=A study of FeCO+ with correlated wavefunctions|journal=Physical Chemistry Chemical Physics|volume=1|issue=6|pages=967–975|year=1999|last1=Glaesemann|first1=Kurt R.|last2=Gordon|first2=Mark S.|last3=Nakano|first3=Haruyuki|bibcode=1999PCCP....1..967G}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }
  public function testDropUrlCode2() { // URL redirects to URL with the same DOI
     $text = '{{cite journal | last = De Vivo | first = B. | title = New constraints on the pyroclastic eruptive history of the Campanian volcanic Plain (Italy) | url = http://www.springerlink.com/content/8r046aa9t4lmjwxj/ | doi = 10.1007/s007100170010 }}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }
  public function testDropUrlCode3() { // url is same as one doi points to, except for http vs. https
     $text = "{{cite journal |pmc=XYZ| first = Luca | last = D'Auria | year = 2015 | title = Magma injection beneath the urban area of Naples | url = http://www.nature.com/articles/srep13100 | doi=10.1038/srep13100 }}";
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('url'));
  }

  public function testMR() {
     $text = "{{cite journal | mr = 22222 }}";
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get('doi'));
  }
 
  public function testDropSomeProxies() {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=proxy.libraries}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));
    
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.sciencedirect.com/science/article/B1234-13241234-343242/}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));
    
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.sciencedirect.com/science/article/pii/2222}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));

    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.springerlink.com/content/s}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));
   
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://insights.ovid.com/pubmed|pmid=2222}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));
   }

   public function testDropSomeEquivURLS2() {
    $text = "{{cite journal|pmc=XYZ|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://iopscience.iop.org/324234}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));
   }

   public function testDropSomeProxies3() {  
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://journals.lww.com/3243243}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));
   }

   public function testDropSomeProxies4() {
    $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://wkhealth.com/3243243}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));
   }

   public function testDropSomeURLEquivs5() {
    $text = "{{cite journal|pmc=XYZ|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://bmj.com/cgi/pmidlookup/sss|pmid=333}}";
    $template = $this->make_citation($text);
    drop_urls_that_match_dois([$template]);
    $this->assertNull($template->get('url'));
  }
 
  public function testSimpleIEEE() {
    $url = "http://ieeexplore.ieee.org/arnumber=123456789";
    $url = url_simplify($url);
    $this->assertSame('http://ieeexplore.ieee.org/123456789/', $url);
  }
 
  public function testIEEEdoi() {
    $url = "https://ieeexplore.ieee.org/document/4242344";
    $template = $this->process_citation('{{cite journal | url = ' . $url . ' }}');
    $this->assertSame('10.1109/ISSCC.2007.373373', $template->get('doi'));
  }

  public function testZoteroResponse1() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_response = ' ';
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
   
  public function testZoteroResponse2() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_response = 'Remote page not found';
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

   public function testZoteroResponse3() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_response = 'Sorry, but 502 Bad Gateway was found';
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

  public function testZoteroResponse4() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_response = 'this will not be found to be valide JSON dude';
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse5() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data = '';
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse6() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data = 'Some stuff that should be encoded nicely';
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse7() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data = (object) array('title' => 'not found');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse8() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'NOT FOUND');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

  public function testZoteroResponse9() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'oup accepted manuscript', 'itemType' => 'webpage');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
    $zotero_data[0] = (object) array('bookTitle' => 'oup accepted manuscript', 'itemType' => 'webpage', 'title'=> 'this is good stuff');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
    $zotero_data[0] = (object) array('publicationTitle' => 'oup accepted manuscript', 'itemType' => 'webpage', 'title'=> 'this is good stuff');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse10() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('bookTitle' => '(pdf) This is a Title (pdf)', 'publisher' => 'JoeJoe', 'title' => 'Billy', 'itemType' => 'bookSection');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Billy', $template->get('chapter'));
    $this->assertSame('JoeJoe', $template->get('publisher'));
    $this->assertSame('This is a Title', $template->get('title'));
  }

   public function testZoteroResponse11() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'journalArticle');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite journal', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
   }
   
   public function testZoteroResponse12() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'magazineArticle');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite magazine', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
  }
 
   public function testZoteroResponse13() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'blogPost');
    $zotero_response = json_encode($zotero_data);
    $url_kind = NULL;
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite web', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
  }
 
   public function testZoteroResponse14() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'film');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite web', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
  }
 
   public function testZoteroResponse15() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'thesis', 'university' => 'IowaIowa', 'thesisType' => 'Masters');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite thesis', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('IowaIowa', $template->get('publisher'));
    $this->assertSame('Masters', $template->get('type'));
  }

   public function testZoteroResponse16() {
    $text = '{{cite news|id=|publisher=Associated Press|author=Associated Press}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = 'http://cnn.com/story';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite news', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('Associated Press', $template->get('agency'));
    $this->assertNull($template->get('author'));
    $this->assertNull($template->get('publisher'));
  }
 
   public function testZoteroResponse17() {
    $text = '{{cite news|id=|publisher=Reuters|author=Reuters}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = 'http://cnn.com/story';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite news', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('Reuters', $template->get('agency'));
    $this->assertNull($template->get('author'));
    $this->assertNull($template->get('publisher'));
  }

   public function testZoteroResponse18() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'PMID: 25408617 PMCID: PMC4233402');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('25408617', $template->get('pmid'));
    $this->assertSame('4233402', $template->get('pmc'));
  }

   public function testZoteroResponse19() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'PMID: 25408617, 25408617');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('25408617', $template->get('pmid'));
  }
 
   public function testZoteroResponse20() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'IMDb ID: nm321432123');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get('title'));
  }
 
   public function testZoteroResponse21() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = 'en.wikipedia.org'; // No date citation
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'date' => '2010');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertNull($template->get('date'));
    $this->assertNull($template->get('year'));
  }
 
  public function testZoteroResponse22() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'bookSection');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
  }
 
   public function testZoteroResponse23() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $author[0] = array(0 => 'This is not a human author by any stretch of the imagination correspondent corporation', 1 => 'correspondent');
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'author' => $author);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get('title'));
    $this->assertTrue($template->blank(['author', 'author1', 'last1', 'first1', 'first', 'last']));
  }
 
  public function testZoteroResponse24() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'DOI' => 'http://dx.doi.org/10.1021/acs.analchem.8b04567' );
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('10.1021/acs.analchem.8b04567', $template->get('doi'));
  }
 
  public function testZoteroResponse25() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $creators[0] = (object) array('creatorType' => 'editor', 'firstName' => "Joe", "lastName" => "");
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'report', 'creators' => $creators);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('Joe', $template->get('editor1'));
  }
 
  public function testZoteroResponse26() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $creators[0] = (object) array('creatorType' => 'translator', 'firstName' => "Joe", "lastName" => "");
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'report', 'creators' => $creators);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('Joe', $template->get('translator1'));
  }
 
  public function testZoteroResponse27() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => "������Junk�����������", 'itemType' => 'webpage');
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertNull($template->get('title'));
  }
  
 public function testZoteroResponse28() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'type: dataset');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get('title'));
  }
 
  public function testZoteroResponse29() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $creators[0] = (object) array('creatorType' => 'author', 'firstName' => "Joe", "lastName" => "");
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'report', 'creators' => $creators);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('Joe', $template->get('author1'));
  }
 
   public function testZoteroResponse30() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $author[0] = array(0 => 'Smith', 1 => '');
    $author[1] = array(0 => 'Johnson', 1 => '');
    $author[2] = array(0 => 'Jackson', 1 => '');
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'author' => $author);
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('Smith', $template->get('author1'));
    $this->assertSame('Johnson', $template->get('author2'));
    $this->assertSame('Jackson', $template->get('author3'));
  }
 
   public function testZoteroResponse31() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_response = 'No items returned from any translator';
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

   public function testZoteroResponse32() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_response = 'An error occurred during translation. Please check translation with the Zotero client.';
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse33() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $creators[0] = (object) array('creatorType' => 'author', 'firstName' => "Joe", "lastName" => "");
    $zotero_data[0] = (object) array('title' => 'Central Authentication Service', 'itemType' => 'report', 'creators' => $creators);
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }

   public function testZoteroResponse34() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'DOI: 10.1038/546031a');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('10.1038/546031a', $template->get('doi'));
  }
 
  public function testZoteroResponse35() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_response = 'Internal Server Error';
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse36() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'journalArticle', 'publicationTitle' => "X");
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite journal', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('X', $template->get('journal'));
  }
 
  public function testZoteroResponse37() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = FALSE;
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('title' => 'Billy', 'itemType' => 'newspaperArticle', 'publicationTitle' => "X");
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame('cite news', $template->wikiname());
    $this->assertSame('Billy', $template->get('title'));
    $this->assertSame('X', $template->get('newspaper'));
  }
 
  public function testZoteroResponse38() {
    $text = '{{cite web|id=}}';
    $template = $this->make_citation($text);
    $access_date = '12 December 2010';
    $url = '';
    $url_kind = NULL;
    $zotero_data[0] = (object) array('date' => '12 December 2020', 'title' => 'Billy', 'itemType' => 'newspaperArticle', 'publicationTitle' => "X");
    $zotero_response = json_encode($zotero_data);
    $this->assertFalse(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testZoteroResponse39() {
    $text = '{{cite journal|url=https://www.sciencedirect.com/science/article/pii/S0024379512004405|title=Geometry of the Welch bounds|journal=Linear Algebra and Its Applications|volume=437|issue=10|pages=2455–2470|year=2012|last1=Datta|first1=S.|last2=Howard|first2=S.|last3=Cochran|first3=D.}}';
    $template = $this->make_citation($text);
    $access_date = NULL;
    $url = 'https://www.sciencedirect.com/science/article/pii/S0024379512004405';
    $url_kind = 'url';
    $zotero_data[0] = (object) array('title' => 'Geometry of the Welch bounds', 'itemType' => 'journalArticle', 'DOI' => '10.1016/j.laa.2012.05.036');
    $zotero_response = json_encode($zotero_data);
    $this->assertTrue(process_zotero_response($zotero_response, $template, $url, $url_kind, $access_date));
    $this->assertNull($template->get('url')); // Drop when gets doi
    $this->assertSame('10.1016/j.laa.2012.05.036', $template->get('doi'));
  }

  public function testRemoveURLthatRedirects() { // This URL is a redirect -- tests code that does that
    $text = '{{cite journal|pmc=XYZ|doi=10.1021/acs.analchem.8b04567|url=http://shortdoi.org/gf7sqt|pmid=30741529|pmc=6526953|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|pages=4346–4356|year=2019|last1=Colby|first1=Sean M.|last2=Thomas|first2=Dennis G.|last3=Nuñez|first3=Jamie R.|last4=Baxter|first4=Douglas J.|last5=Glaesemann|first5=Kurt R.|last6=Brown|first6=Joseph M.|last7=Pirrung|first7=Meg A.|last8=Govind|first8=Niranjan|last9=Teeguarden|first9=Justin G.|last10=Metz|first10=Thomas O.|last11=Renslow|first11=Ryan S.}}';
    $template = $this->make_citation($text);
    drop_urls_that_match_dois(array($template));
    $this->assertNull($template->get('url'));
  }
 
  public function testZoteroExpansion_doi_not_from_crossref() {
   $text = '{{Cite journal|doi=.3233/PRM-140291}}';
   $expanded = $this->make_citation($text);
   $expanded->verify_doi();
   $this->assertSame('10.3233/PRM-140291', $expanded->get('doi'));
   $this->requires_zotero(function() {
    $text = '{{Cite journal|doi=10.3233/PRM-140291}}'; // mEDRA DOI - they do not provide RIS information from dx.doi.org
    $expanded = $this->process_citation($text);
    $this->assertTrue(strpos($expanded->get('journal'), 'Journal of Pediatric Rehabilitation Medicine') === 0);// Sometimes includes a journal of....
   });
  }
 
  public function testCitationTemplateWithoutJournalZotero() {
   $this->requires_zotero(function() {
    $text = '{{citation|url=http://www.word-detective.com/2011/03/mexican-standoff/|title=Mexican standoff|work=The Word Detective|accessdate=2013-03-21}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('isbn')); // This citation used to crash code in ISBN search.  Mostly checking "something" to make Travis CI happy
   });
  }
 
  public function testZoteroExpansionAccessDates() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24663/|access-date=1978-12-12}}';  // Access date is too far in past, will not expand
    $expanded = $this->expand_via_zotero($text);
    $this->assertSame($text, $expanded->parsed_text());
   });
  }

  public function testZoteroExpansionNRM() {
   $this->requires_zotero(function() {
    $text = '{{cite journal | url = http://www.nrm.se/download/18.4e32c81078a8d9249800021554/Bengtson2004ESF.pdf}}';
    $expanded = $this->process_page($text);
    $this->assertTrue(TRUE); // Gives one fuzzy match.  For now we just check that this doesn't crash PHP.
    // In future we should use this match to expand citation.
   });
  }

  public function testNoneAdditionOfAuthor() {
   $this->requires_zotero(function() {
    // Rubbish author listed in page metadata; do not add. 
    $text = "{{cite web |url=http://www.westminster-abbey.org/our-history/people/sir-isaac-newton}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('last1'));
   });
  }
  
  public function testDateTidiness() {
   $this->requires_zotero(function() {
    $text = "{{cite web|title= Gelada| website= nationalgeographic.com |url= http://animals.nationalgeographic.com/animals/mammals/gelada/ |publisher=[[National Geographic Society]]|accessdate=7 March 2012}}";
    $expanded = $this->expand_via_zotero($text);
    $date = $expanded->get('date');
    $date = str_replace('10 May 2011', '', $date); // Sometimes we get no date
    $this->assertSame('', $date);
   });
  }
 
  public function testZoteroBadVolumes() { // has ( and such in it
   $this->requires_zotero(function() {
    $text = '{{cite journal|chapterurl=https://biodiversitylibrary.org/page/32550604}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertNull($expanded->get('volume'));
   });
  }
 
  public function testZoteroKoreanLanguage() {
   $this->requires_zotero(function() {
    $text = '{{cite journal|chapter-url=http://www.newsen.com/news_view.php?uid=201606131737570410}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertNull($expanded->get('title')); // Hopefully will work some day and not give � character
   });
  }
 
  public function testZoteroExpansion_osti() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal| osti=1406676 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1016/j.ifacol.2017.08.010', $expanded->get('doi'));
   });
  $text = '{{Cite journal| osti=1406676 }}';
  $expanded = $this->process_citation($text);
  $this->assertSame($text, $expanded->parsed_text()); // Verify that lack of requires_zotero() blocks zotero
  }
    
  public function testZoteroExpansion_rfc() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal| rfc=6679 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Explicit Congestion Notification (ECN) for RTP over UDP', $expanded->get('title'));
   });
  }
  
  public function testZoteroRespectDates() {
   $this->requires_zotero(function() {
      $text = '{{Use mdy dates}}{{cite web|url=https://www.nasa.gov/content/profile-of-john-glenn}}';
      $page = $this->process_page($text);
      $this->assertTrue((boolean) strpos($page->parsed_text(), 'December 5, 2016'));
      $text = '{{Use dmy dates}}{{cite web|url=https://www.nasa.gov/content/profile-of-john-glenn}}';
      $page = $this->process_page($text);
      $this->assertTrue((boolean) strpos($page->parsed_text(), '5 December 2016'));
   });
  }
 
  public function testZoteroExpansionPII() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal|url = https://www.sciencedirect.com/science/article/pii/S0024379512004405}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertSame('10.1016/j.laa.2012.05.036', $expanded->get('doi'));
    $this->assertNull($expanded->get('url')); // Recognize canonical publisher URL as duplicate of valid doi
   });
  }
      
  public function testZoteroExpansion_ssrn() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal| ssrn=195630 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('The Pricing of Internet Stocks', $expanded->get('title'));
    $this->assertSame('September 1999', $expanded->get('date'));
   });
  }
 
  public function testZoteroExpansionNYT() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal|url =https://www.nytimes.com/2018/06/11/technology/net-neutrality-repeal.html}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertSame("Net Neutrality Has Officially Been Repealed. Here's How That Could Affect You", $expanded->get('title'));
    $this->assertSame('Keith', $expanded->get('first1')); // Would be tidied to 'first' in final_parameter_tudy
    $this->assertSame('Collins', $expanded->get('last1'));
    $this->assertSame('cite news', $expanded->wikiname());
   });
  }
 
  public function testZoteroExpansionNBK() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24662/|access-date=2099-12-12}}';  // Date is before access-date so will expand
    $expanded = $this->expand_via_zotero($text);
    $this->assertSame('Continuing Efforts to More Efficiently Use Laboratory Animals', $expanded->get('title'));
    $this->assertSame('2004', $expanded->get('year'));
    $this->assertSame('National Academies Press (US)', $expanded->get('publisher'));
   });
  }
 
  public function testZoteroTruncateDOI() {
   $this->requires_zotero(function() {
    $text = '{{cite journal|url=http://www.oxfordhandbooks.com/view/10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get('doi-broken-date'));
    $this->assertSame('http://www.oxfordhandbooks.com/view/10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023', $expanded->get('url'));
    $this->assertSame('10.1093/oxfordhb/9780199552238.003.0023', $expanded->get('doi'));
   });
  }
 
  public function testZoteroExpansion_hdl() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal| hdl=10411/OF7UCA }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Replication Data for: Perceiving emotion in non-social targets: The effect of trait empathy on emotional through art', $expanded->get('title'));
   });
  }
 
  public function testZoteroExpansion_citeseerx() {
   $this->requires_zotero(function() {
    $text = '{{Cite journal| citeseerx=10.1.1.483.8892 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Chemical Kinetics Models for the Fatigue Behavior of Fused Silica Optical Fiber', $expanded->get('title'));
   });
  }
 
}
