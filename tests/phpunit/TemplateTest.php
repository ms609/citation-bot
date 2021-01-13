<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class TemplateTest extends testBaseClass {
 
  public function testLotsOfFloaters() : void {
    $text_in = "{{cite journal|issue 3 volume 5 | title Love|journal Dog|series Not mine today|chapter cows|this is random stuff | 123-4567-890 }}";
    $text_out= "{{cite book|this is random stuff |issue = 3|volume = 5|title = Love|chapter = Cows|journal = Dog|series = Not mine today|isbn = 123-4567-890}}";
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_out, $prepared->parsed_text());
  }
  
  public function testLotsOfFloaters2() : void {
    $text_in = "{{cite journal|isssue 3 volumee 5 | tittle Love|journall Dog|series Not mine today|chapte cows|this is random stuff | zauthor Joe }}";
    $text_out= "{{cite journal|isssue 3 volumee 5 | tittle Love|chapte cows|this is random stuff | zauthor Joe |journal = L Dog|series = Not mine today}}";
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_out, $prepared->parsed_text());
  }
 
  public function testLotsOfFloaters3() : void {
    $text_in = "{{cite journal| 123-4567-890-123 }}";
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame('123-4567-890-123', $prepared->get2('isbn'));
  }
 
  public function testLotsOfFloaters4() : void {
    $text_in = "{{cite journal| 123-4567-8901123 }}"; // 13 numbers
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_in, $prepared->parsed_text());
  }
 
  public function testLotsOfFloaters5() : void {
    $text_in = "{{cite journal| 12345678901 }}"; // 11 numbers
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_in, $prepared->parsed_text());
  }

  public function testParameterWithNoParameters() : void {
    $text = "{{Cite web | text without equals sign  }}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
    $text = "{{  No pipe  }}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
 
  public function testDashedTemplate() : void {
    $text = "{{cite_news}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{cite news}}", $expanded->parsed_text());
  }
 
  public function testTemplateConvertComplex() : void {
    $text = "{{cite article}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{cite news}}", $expanded->parsed_text());
   
    $text = "{{cite article|journal=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{cite journal|journal=X}}", $expanded->parsed_text());
   
    $text = "{{Cite article}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Cite news}}", $expanded->parsed_text());
   
    $text = "{{Cite article|journal=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Cite journal|journal=X}}", $expanded->parsed_text());
  }
 
  public function testTemplateConvertComplex2() : void {
    $text = "{{cite document}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{cite document}}", $expanded->parsed_text());
   
    $text = "{{cite document|doi=XXX/978-XXX}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("cite book", $expanded->wikiname());
   
    $text = "{{cite document|journal=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("cite journal", $expanded->wikiname());
   
    $text = "{{cite document|newspaper=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("cite news", $expanded->wikiname());
   
    $text = "{{cite document|chapter=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("cite book", $expanded->wikiname());
   
   
    $text = "{{Cite document}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{Cite document}}", $expanded->parsed_text());
   
    $text = "{{Cite document|doi=XXX/978-XXX}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("cite book", $expanded->wikiname());
   
    $text = "{{Cite document|journal=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("cite journal", $expanded->wikiname());
   
    $text = "{{Cite document|newspaper=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("cite news", $expanded->wikiname());
   
    $text = "{{Cite document|chapter=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("cite book", $expanded->wikiname());
  }

  public function testPureGarbage1() : void {
    $text = "{{cite journal|title=Bloomberg - Are you a robot?}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("{{cite journal}}", $expanded->parsed_text());
  }
 
  public function testPureGarbage2() : void {
    $text = "{{cite journal|title=Wayback Machine}}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
 
  public function testPureGarbage3() : void {
    $text = "{{cite journal|title=Wayback Machine|archive-url=XXX}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('title'));
  }
 
  public function testTheNation() : void {
    $text = "{{cite journal|issn=0027-8378}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('The Nation', $expanded->get2('journal'));
  }
   
  public function testNoGoonUTF8() : void {
    $text = "{{cite news |date=びっくり１位 白鴎|title=阪神びっくり１位 白鴎大・大山、鉄人魂の持ち主だ|journal=鉄人魂}}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
 
  public function testAddTitleSameAsWork() : void {
    $text = "{{Cite web|work=John Boy}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('title', 'John boy'));
    $this->assertNull($expanded->get2('title'));
  }
 
  public function testTitleOfNone() : void {
    $text = "{{Cite web|title=none}}";// none is magic flag
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('url', 'https://www.apple.com/'));
    $this->assertNull($expanded->get2('url'));             

    $text = "{{Cite web|title=None}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('url', 'https://www.apple.com/'));
    $this->assertSame('https://www.apple.com/', $expanded->get2('url'));  
  }

   public function testTitleLink() : void {
    $text = "{{Cite web|url=X}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('title-link', 'x'));
    $text = "{{Cite web}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('title-link', 'x'));
   }
 
   public function testAddAuthorAgain() : void {
    $text = "{{Cite web|last1=X}}";
    $expanded = $this->process_citation($text);
    $this->assertFalse($expanded->add_if_new('last1', 'Z'));
   }
 
   public function testAddAuthorAgainDiff1() : void {
    $text = "{{Cite web|last1=X}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('author1', 'Z'));
   }
 
   public function testAddAuthorAgainDiff2() : void {
    $text = "{{Cite web|author1=X}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('last1', 'Z'));
   }
 
   public function testAddS2CIDAgain() : void {
    $text = "{{Cite web|S2CID=X}}";
    $expanded = $this->process_citation($text);
    $this->assertFalse($expanded->add_if_new('s2cid', 'Z'));
   }

   public function testNatureBad() : void {
    $text = "{{Cite web|doi=10.1111/j.1572-0241.xxxx|jstor=XYZ}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('doi'));
   }
 
   public function testDotInVolumeIssue() : void {
    $text = "{{Cite web|issue=1234.|volume=2341.}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('1234', $expanded->get2('issue'));
    $this->assertSame('2341', $expanded->get2('volume'));  
   }
 
   public function testBadPMID() : void {
    $text = "{{Cite web|url=https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('pmid'));
    $this->assertNull($expanded->get2('pmc'));
    $this->assertSame('https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493', $expanded->get2('url'));  
   }
 
   public function testJournal2Web() : void {
    $text = "{{Cite journal|journal=www.cnn.com}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('www.cnn.com', $expanded->get2('website'));  
  }

  public function testCleanUpTemplates1() : void {
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
  }

  public function testCleanUpTemplates2() : void {
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
  }

  public function testCleanUpTemplates3() : void {
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

  public function testUseUnusedData() : void {
    $text = "{{Cite web | http://google.com | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6 }}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite web',          $prepared->wikiname());
    $this->assertSame('http://google.com', $prepared->get2('url'));
    $this->assertSame('I am a title',      $prepared->get2('title')); 
    $this->assertSame('Other, A. N.',      $prepared->get2('author'));
    $this->assertSame('9'           ,      $prepared->get2('issue'));
    $this->assertSame('22'          ,      $prepared->get2('volume'));
    $this->assertSame('5–6'         ,      $prepared->get2('pages'));
  }
 
  public function testGetDoiFromCrossref() : void {
     $text = '{{Cite journal | last1 = Glaesemann | first1 = K. R. | last2 = Fried | first2 = L. E. | doi = | title = Improved wood–kirkwood detonation chemical kinetics | journal = Theoretical Chemistry Accounts | volume = 120 | pages = 37–43 | year = 2007 |issue=1–3}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('10.1007/s00214-007-0303-9', $expanded->get2('doi'));
     $this->assertNull($expanded->get2('pmid'));  // do not want reference where pmid leads to doi
     $this->assertNull($expanded->get2('bibcode'));
     $this->assertNull($expanded->get2('pmc'));
  }
  
  public function testJstorExpansion1() : void {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true|website=i found this online}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite journal', $prepared->wikiname());
    $this->assertSame('1701972'     , $prepared->get2('jstor'));
    $this->assertNotNull($prepared->get2('website'));
  }

  public function testJstorExpansion2() : void {
    $text = "{{Cite journal | url=http://www.jstor.org/stable/10.2307/40237667|jstor=}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('40237667', $prepared->get2('jstor'));
    $this->assertNull($prepared->get2('doi'));
    $this->assertSame(2, substr_count($prepared->parsed_text(), 'jstor'));  // Verify that we do not have both jstor= and jstor=40237667.  Formerly testOverwriteBlanks()
  }

  public function testJstorExpansion3() : void {
    $text = "{{Cite web | url = http://www.jstor.org/stable/10.1017/s0022381613000030}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('10.1017/s0022381613000030', $prepared->get2('jstor'));
  }

  public function testJstorExpansion4() : void {
    $text = '{{cite web | via = UTF8 characters from JSTOR | url = https://www.jstor.org/stable/27695659}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Mórdha', $expanded->get2('last1'));
  }

  public function testJstorExpansion5() : void {
    $text = '{{cite journal | url = https://www-jstor-org.school.edu/stable/10.7249/mg1078a.10?seq=1#metadata_info_tab_contents }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.7249/mg1078a.10', $expanded->get2('jstor'));
  }

  public function testDrop10_2307() : void {
    $text = "{{Cite journal | jstor=10.2307/40237667}}";  // This should get cleaned up in tidy
    $prepared = $this->prepare_citation($text);
    $this->assertSame('40237667', $prepared->get2('jstor'));
  }

  public function testDropWeirdJunk() : void {
    $text = "{{cite web |title=Left Handed Incandescent Light Bulbs?|last=|first=|date=24 March 2011 |publisher=eLightBulbs |last1=Eisenbraun|first1=Blair|accessdate=27 July 2016}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('last'));
    $this->assertNull($expanded->get2('first'));
    $this->assertSame('Blair', $expanded->get2('first1'));
    $this->assertSame('Eisenbraun', $expanded->get2('last1'));
  }

   public function testRISJstorExpansion() : void {
    $text = "<ref name='jstor'>{{jstor|3073767}}</ref>"; // Check Page expansion too
    $page = $this->process_page($text);
    $expanded = $this->reference_to_template($page->parsed_text());
    $this->assertSame('Are Helionitronium Trications Stable?', $expanded->get2('title'));
    $this->assertSame('99', $expanded->get2('volume'));
    $this->assertSame('24', $expanded->get2('issue'));
    $this->assertSame('Francisco', $expanded->get2('last2')); 
    $this->assertSame('Eisfeld', $expanded->get2('last1')); 
    $this->assertSame('Proceedings of the National Academy of Sciences of the United States of America', $expanded->get2('journal')); 
    $this->assertSame('15303–15307', $expanded->get2('pages'));
    // JSTOR gives up these, but we do not add since we get journal title and URL is simply jstor stable
    $this->assertNull($expanded->get2('publisher'));
    $this->assertNull($expanded->get2('issn'));
    $this->assertNull($expanded->get2('url'));
  }
   
  public function testDOI1093() : void {
    $text = '{{cite web |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002}}';
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('{{cite document |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002}}', $template->parsed_text());
  
    $text = '{{Cite web |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002}}';
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('{{Cite document |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002}}', $template->parsed_text());
  } 
 
  public function testGroveMusic1() : void {
    $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002 |access-date=November 20, 2018 |url-access=subscription|via=Grove Music Online}}';
    $template = $this->process_citation($text);
    $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002 |doi=10.1093/gmo/9781561592630.article.J441700 |access-date=November 20, 2018 |url-access=subscription}}', $template->parsed_text());
  }
 
  public function testGroveMusic2() : void {
    $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002|via=Grove Music Online}}';
    $template = $this->process_citation($text);
    $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002|doi=10.1093/gmo/9781561592630.article.J441700 }}', $template->parsed_text());
  }
 
  public function testGroveMusic3() : void {
    $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|via=Grove Music Online}}';
    $template = $this->process_citation($text);
    $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|doi=10.1093/gmo/9781561592630.article.J441700 |via=Grove Music Online}}', $template->parsed_text());
  }
 
  public function testGroveMusic4() : void {
    $text = '{{cite web |url=https://doi.org/=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|website=Grove Music Online}}';
    $template = $this->process_citation($text);
    $this->assertSame('{{cite web |url=https://doi.org/=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|website=Grove Music Online|doi=10.1093/gmo/9781561592630.article.J441700 }}', $template->parsed_text());
  }
 
  public function testGroveMusic5() : void {
    $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|website=Grove Music Online|via=The Dog Farm}}';
    $template = $this->process_citation($text);
    $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|website=Grove Music Online|doi=10.1093/gmo/9781561592630.article.J441700 |via=The Dog Farm}}', $template->parsed_text());
  }
  
  public function testTidyLastFirts() : void {
    $text = '{{cite document |last=Howlett |first=Felicity|last2=Fred}}';
    $template = $this->process_citation($text);
    $this->assertSame('{{cite document |last1=Howlett |first1=Felicity|last2=Fred}}', $template->parsed_text());
  }

  public function testBrokenDoiUrlRetention1() : void {
    $text = '{{cite journal|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1301|title=Israel, Occupied Territories|publisher=|doi=10.1093/law:epil/9780199231690/law-9780199231690-e1301|doi-broken-date=2018-07-07}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('doi-broken-date'));
    $this->assertNotNull($expanded->get2('url'));
  }
 
   public function testBrokenDoiUrlRetention2() : void {
    // Newer code does not even add it
    $text = '{{cite journal|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1301}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('doi'));
    $this->assertNotNull($expanded->get2('url'));
  }
 
   public function testBrokenDoiUrlRetention3() : void {
    // valid 10.1098 DOI in contrast to evil ones
    $text = '{{cite journal|url=https://academic.oup.com/zoolinnean/advance-article-abstract/doi/10.1093/zoolinnean/zly047/5049994}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1093/zoolinnean/zly047', $expanded->get2('doi'));
    $this->assertNotNull($expanded->get2('url'));
  }
 
   public function testBrokenDoiUrlRetention4() : void {
    // This is an ISSN only doi: it is valid, but leave url too
    $text = '{{cite journal|url=http://onlinelibrary.wiley.com/journal/10.1111/(ISSN)1601-183X/issues }}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('doi'));
    $this->assertNotNull($expanded->get2('url'));
  }
 
 public function testCrazyDoubleDOI() : void {
    $doi = '10.1126/science.10.1126/SCIENCE.291.5501.24';
    $text = '{{cite journal|doi=' . $doi . '}}';
    $expanded = $this->process_citation($text);
    $this->assertSame($doi, $expanded->get2('doi'));
 }

 public function testBrokenDoiUrlChanges1() : void {
     $text = '{{cite journal|url=http://dx.doi.org/10.1111/j.1471-0528.1995.tb09132.x|doi=10.00/broken_and_invalid|doi-broken-date=12-31-1999}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $expanded->get2('doi'));
     $this->assertNotNull($expanded->get2('url'));
 }
 
  public function testBrokenDoiUrlChanges2() : void {
    // The following URL is "broken" since it is not escaped properly.  The cite template displays and links it wrong too.
     $text = '{{cite journal|doi=10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2|url=https://dx.doi.org/10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2}}';
     $expanded = $this->process_citation($text);
     $this->assertNotNull($expanded->get2('url'));
  }
   
  public function testBrokenDoiUrlChanges3() : void {
     $text = '{{cite journal|url=http://doi.org/10.14928/amstec.23.1_1|doi=10.14928/amstec.23.1_1}}';  // This also troublesome DOI
     $expanded = $this->process_citation($text);
     $this->assertNotNull($expanded->get2('url'));
  }
  
  public function testPmidExpansion() : void {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('1941451', $expanded->get2('pmid'));
  }
 
  public function testGetPMIDwitNoDOIorJournal() : void {  // Also has evil colon in the name.  Use wikilinks for code coverage reason
      $text = '{{cite journal|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|volume=[[91]]|issue=[[7|7]]|pages=4346|year=2019|last1=Colby}}';
      $template = $this->make_citation($text);
      $template->find_pmid();
      $this->assertSame('30741529', $template->get2('pmid'));
  }
   
  public function testPoundDOI() : void {
    $text = "{{cite book |url=https://link.springer.com/chapter/10.1007%2F978-3-642-75924-6_15#page-1}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1007/978-3-642-75924-6_15', $expanded->get2('doi'));
  }
 
  public function testPlusDOI() : void {
    $doi = "10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U#page_scan_tab_contents=342342"; // Also check #page_scan_tab_contents stuff too
    $text = "{{cite journal|doi = $doi }}";
    $expanded = $this->process_citation($text);
    $this->assertSame("10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U", $expanded->get2('doi'));
  }
 
  public function testNewsdDOI() : void {
    $text = "{{cite news|url=http://doi.org/10.1021/cen-v076n048.p024;jsessionid=222}}"; // Also check jsesssion removal
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1021/cen-v076n048.p024', $expanded->get2('doi'));
  }
 
  public function testChangeNothing1() : void {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x|pages=<!-- -->|title=<!-- -->|journal=<!-- -->|volume=<!-- -->|issue=<!-- -->|year=<!-- -->|authors=<!-- -->|pmid=<!-- -->|url=<!-- -->|s2cid=<!-- -->}}';
     $expanded = $this->process_page($text);
     $this->assertSame($text, $expanded->parsed_text());
  }
    
  public function testChangeNothing2() : void {
     $text = '{{cite journal | doi=10.000/broken_real_bad_and_tests_know_it | doi-broken-date = <!-- not broken and the bot is wrong --> }}';
     $expanded = $this->process_page($text);
     $this->assertSame($text, $expanded->parsed_text());
  }
    
  public function testChangeNothing3() : void {
     $text = '{{cite journal |title=The tumbling rotational state of 1I/‘Oumuamua<!-- do not change odd punctuation--> |journal=Nature title without caps <!-- Deny Citation Bot-->  |pages=383-386 <!-- do not change the dash--> }}';
     $expanded = $this->process_page($text);
     $this->assertSame($text, $expanded->parsed_text());
  }
  
  public function testNoLoseUrl() : void {
     $text = '{{cite book |last=Söderström |first=Ulrika |date=2015 |title=Sandby Borg: Unveiling the Sandby Borg Massacre |url= |location= |publisher=Kalmar lāns museum |isbn=9789198236620 |language=Swedish }}';
     $expanded = $this->process_page($text);
     $this->assertSame($text, $expanded->parsed_text());
  }
 
  public function testDotsAndVia() : void {
     $text = '{{cite journal|pmid=4957203|via=Pubmed}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('M. M.', $expanded->get2('first3'));
     $this->assertNull($expanded->get2('via'));
  }
 
  public function testLoseViaDup() : void {
     $text = '{{citation|work=Some Journal|via=Some Journal}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get2('via'));
     $text = '{{citation|publisher=Some Journal|via=Some Journal}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get2('via'));
     $text = '{{citation|newspaper=Some Journal|via=Some Journal}}';
     $expanded = $this->process_citation($text);
     $this->assertNull($expanded->get2('via'));
  }
    
  public function testJustBrackets() : void {
     $text = '{{cite book|title=[[W|12px|alt=W]]}}';
     $expanded = $this->process_citation($text);
     $this->assertSame($text, $expanded->parsed_text());
     $text = '{{cite book|title=[[File:Example.png|thumb|upright|alt=Example alt text|Example caption]]}}';
     $expanded = $this->process_citation($text);
     $this->assertSame($text, $expanded->parsed_text());
  }

  public function testBadAuthor2() : void {
      $text = '{{cite journal|title=Guidelines for the management of adults with hospital-acquired, ventilator-associated, and healthcare-associated pneumonia |journal=Am. J. Respir. Crit. Care Med. |volume=171 |issue=4 |pages=388–416 |year=2005 |pmid=15699079 |doi=10.1164/rccm.200405-644ST}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('American Thoracic Society', $expanded->get2('author1'));
  }
 
  public function testPmidIsZero() : void {
      $text = '{{cite journal|pmc=2676591}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get2('pmid'));
  }
  
  public function testPMCExpansion1() : void {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('154623', $expanded->get2('pmc'));
    $this->assertNull($expanded->get2('url'));
  }

  public function testPMCExpansion2() : void {
    $text = "{{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf', $expanded->get2('url'));
    $this->assertSame('2491514', $expanded->get2('pmc'));
  }
  
  public function testPMC2PMID() : void {
    $text = '{{cite journal|pmc=58796}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('11573006', $expanded->get2('pmid'));
  }
  
  public function testArxivExpansion() : void {
   $this->requires_arxiv(function() : void {
    $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}"
          . "{{Cite arxiv | eprint = 0806.0013 | class=forgetit|publisher=uk.arxiv}}"
          . '{{Cite arxiv |arxiv=1609.01689 | title = Accelerating Nuclear Configuration Interaction Calculations through a Preconditioned Block Iterative Eigensolver|class=cs.NA | year = 2016| last1 = Shao| first1 = Meiyue | display-authors = etal}}'
          . '{{cite arXiv|eprint=hep-th/0303241}}' // tests line feeds
          ;
    $expanded = $this->process_page($text);
    $templates = $expanded->extract_object('Template');
    $this->assertSame('cite journal', $templates[0]->wikiname());
    $this->assertSame('0806.0013', $templates[0]->get2('arxiv'));
    $this->assertSame('cite journal', $templates[1]->wikiname());
    $this->assertSame('0806.0013', $templates[1]->get2('arxiv'));
    $this->assertNull($templates[1]->get2('class'));
    $this->assertNull($templates[1]->get2('eprint'));
    $this->assertNull($templates[1]->get2('publisher'));
    $this->assertSame('2018', $templates[2]->get2('year'));
    $this->assertSame('Pascual Jordan, his contributions to quantum mechanics and his legacy in contemporary local quantum physics', $templates[3]->get2('title'));
   });
  }
  
  public function testAmazonExpansion1() : void {
    $text = "{{Cite web | url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('0226845494', $expanded->get2('isbn'));
    $this->assertNull($expanded->get2('asin'));
    $this->assertNull($expanded->get2('publisher'));
    $this->assertNull($expanded->get2('url'));
  }

  public function testAmazonExpansion2() : void {
    $text = "{{Cite web | chapter-url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('0226845494', $expanded->get2('isbn'));
    $this->assertNull($expanded->get2('asin'));
    $this->assertNull($expanded->get2('publisher'));
    $this->assertNull($expanded->get2('chapter-url'));
  }

  public function testAmazonExpansion3() : void {
    $text = "{{Cite web | url=https://www.amazon.com/Gold-Toe-Metropolitan-Dress-Three/dp/B0002TV0K8 | access-date=2012-04-20 | title=Gold Toe Men's Metropolitan Dress Sock (Pack of Three Pairs) at Amazon Men's Clothing store}}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());  // We do not touch this kind of URL
  }

  public function testAmazonExpansion4() : void {
    $text = "{{Cite web | chapter-url=http://www.amazon.eu/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('', $expanded->get2('isbn'));
    $this->assertNull($expanded->get2('asin'));
    $this->assertNull($expanded->get2('publisher'));
    $this->assertNull($expanded->get2('chapter-url'));
    $this->assertSame('{{ASIN|0226845494|country=eu}}', $expanded->get2('id'));
  }

  public function testAmazonExpansion5() : void {
    $text = "{{Cite book | chapter-url=http://www.amazon.eu/On-Origin-Phyla-James-Valentine/dp/0226845494 |isbn=exists}}";
    $expanded = $this->prepare_citation($text);;
    $this->assertNull($expanded->get2('asin'));
    $this->assertNull($expanded->get2('chapter-url'));
    $this->assertSame('exists', $expanded->get2('isbn'));
  }

  public function testRemoveASIN1() : void {
    $text = "{{Cite book | asin=B0002TV0K8 |isbn=}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('B0002TV0K8', $expanded->get2('asin'));
    $this->assertSame('', $expanded->get2('isbn')); // Empty, not non-existent
  }

  public function testRemoveASIN2() : void {
    $text = "{{Cite book | asin=0226845494 |isbn=0226845494}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('0226845494', $expanded->get2('isbn'));
    $this->assertNull($expanded->get2('asin'));
  }
 
  public function testAddASIN1() : void {
    $text = "{{Cite book |isbn=0226845494}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('asin', 'X'));
    $this->assertSame('0226845494', $expanded->get2('isbn'));
    $this->assertNull($expanded->get2('asin'));
  }

  public function testAddASIN2() : void {
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', '630000000')); //63.... code
    $this->assertSame('630000000', $expanded->get2('asin'));
  }

  public function testAddASIN3() : void {
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', 'BNXXXXXXXX')); // Not an ISBN at all
    $this->assertSame('BNXXXXXXXX', $expanded->get2('asin'));
  }

  public function testAddASIN4() : void {
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', '0781765625'));
    $this->assertSame('0781765625', $expanded->get2('isbn'));
    $this->assertNull($expanded->get2('asin'));
  }

  public function testAddASIN5() : void {
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', 'ABC'));
    $this->assertSame('ABC', $expanded->get2('asin'));
    $this->assertNull($expanded->get2('isbn'));
  }

  public function testAddASIN6() : void {
    $text = "{{Cite book|asin=xxxxxx}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('asin', 'ABC'));
    $this->assertSame('xxxxxx', $expanded->get2('asin'));
    $this->assertNull($expanded->get2('isbn'));
  }

  public function testAddASIN7() : void {
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', '12345'));
    $this->assertSame('12345', $expanded->get2('asin'));
    $this->assertNull($expanded->get2('isbn'));
  }
 
  public function testTemplateRenaming() : void {
    $text = "{{cite web|url=https://books.google.com/books?id=ecrwrKCRr7YC&pg=PA85&lpg=PA85&dq=vestibular+testing+lab+gianoli&keywords=lab&text=vestibular+testing+lab+gianoli|title=Practical Management of the Dizzy Patient|first=Joel A.|last=Goebel|date=6 December 2017|publisher=Lippincott Williams & Wilkins|via=Google Books}}";
    // Should add ISBN and thus convert to Cite book
    $expanded = $this->process_citation($text);
    $this->assertSame('9780781765626', $expanded->get2('isbn'));
    $this->assertSame('cite book', $expanded->wikiname());
  }
  
  public function testTemplateRenamingURLConvert() : void {
    $text='{{Cite journal|url=http://www.sciencedirect.com/science/article/pii/B9780123864543000129|last=Roberts|first=L.|date=2014|publisher=Academic Press|isbn=978-0-12-386455-0|editor-last=Wexler|editor-first=Philip|location=Oxford|pages=993–995|doi=10.1016/b978-0-12-386454-3.00012-9}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('http://www.sciencedirect.com/science/article/pii/B9780123864543000129', $expanded->get2('chapter-url'));
    $this->assertNull($expanded->get2('url'));
  }

  public function testDoiExpansion1() : void {
    $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('cite journal', $prepared->wikiname());
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $prepared->get2('doi'));
  }
 
  public function testDoiExpansion2() : void {
    $text = "{{Cite web | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
    $expanded = $this->prepare_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
    $this->assertNotNull($expanded->get2('url'));
  }
 
  public function testDoiExpansion3() : void {
    // Recognize official DOI targets in URL with extra fragments - fall back to S2
    $text = '{{cite journal | url = https://link.springer.com/article/10.1007/BF00233701#page-1 | doi = 10.1007/BF00233701}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('url'));
  }
 
  public function testDoiExpansion4() : void {
    // Replace this test with a real URL (if one exists)
    $text = "{{Cite web | url = http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf}}"; // Fake URL, real DOI
    $expanded= $this->prepare_citation($text);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
    // Do not drop PDF files, in case they are open access and the DOI points to a paywall
    $this->assertSame('http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf', $expanded->get2('url'));
  }

  public function testAddStuff() : void {
    $text = "{{cite book|publisher=exist}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('publisher', 'A new publisher to replace it'));
    
    $this->assertTrue($template->add_if_new('type', 'A description of this'));
    $this->assertSame('A description of this', $template->get2('type'));
   
    $this->assertTrue($template->add_if_new('id', 'A description of this thing'));
    $this->assertFalse($template->add_if_new('id', 'Another description of this'));
  }
 
  public function testURLCleanUp1() : void {
    $text = "{{cite book|url=ttps://junk}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://junk', $template->get2('url'));
  }

  public function testURLCleanUp2() : void {
    $text = "{{cite book|url=http://orbit.dtu.dk/en/publications/33333|doi=1234}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNotNull($template->get2('url'));
  }

  public function testURLCleanUp3() : void {
    $text = "{{cite book|url=https://ieeexplore.ieee.org/arnumber=1}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://ieeexplore.ieee.org/document/1', $template->get2('url'));
  }

  public function testURLCleanUp4() : void {
    $text = "{{cite book|url=https://ieeexplore.ieee.org/document/01}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://ieeexplore.ieee.org/document/1', $template->get2('url'));
  }

  public function testURLCleanUp5() : void {
    $text = "{{cite book|url=https://jstor.org/stuffy-Stuff/?refreqid=124}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://jstor.org/stuffy-Stuff/', $template->get2('url'));
  }

  public function testURLCleanUp6() : void {
    $text = "{{cite book|url=https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('10.7249/j.ctt4cgd90.10', $template->get2('jstor'));
    $this->assertNotNull($template->get2('url'));
  }

  public function testURLCleanUp7() : void {
    $text = "{{cite book|url=https://www.jstor.org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('10.7249/j.ctt4cgd90.10', $template->get2('jstor'));
    $this->assertNotNull($template->get2('url'));
  }

  public function testURLCleanUp8() : void {
    $text = "{{cite book|url=https://jstor.org/stable/pdfplus/12345.pdf|jstor=12345}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNotNull($template->get2('url'));
    $this->assertSame('12345', $template->get2('jstor'));
  }

  public function testURLCleanUp9() : void {
    $text = "{{cite book|url=https://jstor.org/discover/12345.pdf|jstor=12345}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNotNull($template->get2('url'));
    $this->assertSame('12345', $template->get2('jstor'));
  }

  public function testURLCleanUp10() : void {
    $text = "{{cite book|url=https://archive.org/detail/jstor-12345}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNotNull($template->get2('url'));
    $this->assertSame('12345', $template->get2('jstor'));
  }

  public function testURLCleanUp11() : void {
    $text = "{{cite book|url=https://jstor.org/stable/pdfplus/12345.pdf}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertNotNull($template->get2('url'));
    $this->assertSame('12345', $template->get2('jstor'));
  }
 
  public function testURLCleanUp12() : void {
    $text = "{{cite journal|url=https://dx.doi.org/10.0000/BOGUS}}"; // Add bogus
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNotNull($template->get2('url'));
    $this->assertSame('10.0000/BOGUS', $template->get2('doi'));
  }
 
  public function testURLCleanUp13() : void {
    $text = "{{cite journal|url=https://dx.doi.org/10.0000/BOGUS|doi=10.0000/THIS_IS_JUNK_DATA}}"; // Fail to add bogus
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('https://dx.doi.org/10.0000/BOGUS', $template->get2('url'));
    $this->assertSame('10.0000/THIS_IS_JUNK_DATA', $template->get2('doi'));
  }
 
  public function testURLCleanUp14() : void {
    $text = "{{cite journal|url=https://dx.doi.org/10.1093/oi/authority.x}}"; // A particularly semi-valid DOI
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNull($template->get2('doi'));
    $this->assertSame('https://dx.doi.org/10.1093/oi/authority.x', $template->get2('url'));
  }
 
  public function testURLCleanUp15() : void {
    $text = "{{cite journal|doi=10.5284/1000184|url=https://dx.doi.org/10.5284/1000184XXXXXXXXXX}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNotNull($template->get2('url'));
    $this->assertSame('10.5284/1000184', $template->get2('doi'));
  }
 
  public function testURLCleanUp16() : void {
    $text = "{{cite journal|doi= 10.1093/oi/authority.x|url=https://dx.doi.org/10.1093/oi/authority.xXXXXXXXXXX.pdf}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('https://dx.doi.org/10.1093/oi/authority.xXXXXXXXXXX.pdf', $template->get2('url'));
    $this->assertSame('10.1093/oi/authority.x', $template->get2('doi'));
  }
 
  public function testURLCleanUp17() : void {
    $text = "{{cite journal|url=https://SomeRandomWeb.com/10.5284/1000184}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('https://SomeRandomWeb.com/10.5284/1000184', $template->get2('url'));
    $this->assertNull($template->get2('doi'));
  }
 
  public function testHDLasDOIThing1() : void {
    $text='{{Cite journal | doi=20.1000/100|url=http://www.stuff.com/20.1000/100}}';
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('20.1000/100', $template->get2('doi'));
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testHDLasDOIThing2() : void {
    $text='{{Cite journal | doi=20.1000/100|url=http://www.stuff.com/20.1000/100.pdf}}';
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('20.1000/100', $template->get2('doi'));
    $this->assertSame('http://www.stuff.com/20.1000/100.pdf', $template->get2('url'));
  }
 
  public function testDoiExpansionBook() : void {
    $text = "{{cite book|doi=10.1007/978-981-10-3180-9_1}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('978-981-10-3179-3', $expanded->get2('isbn'));
  }
  
  public function testDoiEndings1() : void {
    $text = '{{cite journal | doi=10.1111/j.1475-4983.2012.01203.x/full}}';
    $expanded = $this->process_citation($text);   
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
  }

  public function testDoiEndings2() : void {
    $text = '{{cite journal| url=http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));  
  }

  public function testSeriesIsJournal() : void {
    $text = '{{citation | series = Annals of the New York Academy of Sciences| doi = 10.1111/j.1749-6632.1979.tb32775.x}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('journal')); // Doi returns exact same name for journal as series
  }
  
  public function testEmptyCoauthor() : void {
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

  public function testExpansionJstorBook() : void {
    $text = '{{Cite journal|url=https://www.jstor.org/stable/j.ctt6wp6td.10}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Verstraete', $expanded->get2('last1'));
  }
 
  public function testAP_zotero() : void {
    $text = '{{cite web|author=Associated Press |url=https://www.theguardian.com/science/2018/feb/03/scientists-discover-ancient-mayan-city-hidden-under-guatemalan-jungle}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('author'));
    $this->assertNull($expanded->get2('publisher'));
    $this->assertSame('Associated Press', $expanded->get2('agency'));
  }
    
  public function testPublisherRemoval() : void {
    foreach (array('Google News Archive', '[[Google]]', 'Google News',
                   'Google.com', '[[Google News]]') as $publisher) {
      $text = "{{cite journal | publisher = $publisher}}";
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get2('publisher'));
    }
  }

  public function testPublisherCoversion() : void {
    $text = '{{cite web|publisher=New york TiMES}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('publisher'));
    $this->assertSame('New york TiMES', $expanded->get2('work'));
  }
 
  public function testRemoveWikilinks0() : void {
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil]]}}");
    $this->assertSame('[[Pure Evil]]', $expanded->get2('title'));
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil|Pure Evil]]}}");  // Bot bug made these for a while
    $this->assertSame('[[Pure Evil]]', $expanded->get2('title'));
  }
  public function testRemoveWikilinks1() : void {
    $expanded = $this->process_citation("{{Cite journal|author1=[[Pure Evil]]}}");
    $this->assertSame('[[Pure Evil]]', $expanded->get2('author1'));
    $this->assertNull($expanded->get2('author1-link')); // No longer needs to be done
  }
  public function testRemoveWikilinks1b() : void {
    $expanded = $this->process_citation("{{Cite journal|author1=[[Pure]] and [[Evil]]}}");
    $this->assertSame('[[Pure]] and [[Evil]]', $expanded->get2('author1'));
  }
  public function testRemoveWikilinks1c() : void {
    $expanded = $this->process_citation("{{Cite journal|author1=[[Pure Evil|Approximate Physics]]}}");
    $this->assertSame('[[Pure Evil|Approximate Physics]]', $expanded->get2('author1'));
    $this->assertNull($expanded->get2('author1-link'));
  }

  public function testRemoveWikilinks2() : void {
    $expanded = $this->process_citation("{{Cite journal|journal=[[Pure Evil]]}}");
    $this->assertSame('[[Pure Evil]]', $expanded->get2('journal')); // leave fully linked journals
  }
  public function testRemoveWikilinks2b() : void {
    $expanded = $this->process_citation("{{Cite journal|journal=[[Pure]] and [[Evil]]}}");
    $this->assertSame('Pure and Evil', $expanded->get2('journal'));
  }
  public function testRemoveWikilinks2c() : void {
    $expanded = $this->process_citation("{{Cite journal|journal=Dark Lord of the Sith [[Pure Evil]]}}");
    $this->assertSame('Dark Lord of the Sith Pure Evil', $expanded->get2('journal'));
  }
  public function testRemoveWikilinks2d() : void {
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil]]}}");
    $this->assertSame('[[Pure Evil]]', $expanded->get2('title'));
    $this->assertNull($expanded->get2('title-link'));
  }

  public function testRemoveWikilinks3() : void {
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil|Approximate Physics]]}}");
    $this->assertSame('[[Pure Evil|Approximate Physics]]', $expanded->get2('title'));
    $this->assertNull($expanded->get2('title-link'));
  }
  public function testRemoveWikilinks3b() : void {
    $expanded = $this->process_citation("{{Cite journal|title=[[Dark]] Lord of the [[Sith (Star Wars)|Sith]] [[Pure Evil]]}}");
    $this->assertSame('Dark Lord of the Sith Pure Evil', $expanded->get2('title'));
  }
  public function testRemoveWikilinks3c() : void { // TODO - bring this back ?????
    $expanded = $this->process_citation("{{Cite journal|title=Dark Lord of the [[Sith (Star Wars)|Sith]] Pure Evil}}");
    $this->assertSame('Dark Lord of the [[Sith (Star Wars)|Sith]] Pure Evil', $expanded->get2('title'));
    $this->assertNull($expanded->get2('title-link'));
  }
  public function testRemoveWikilinks3d() : void {
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil] }}");
    $this->assertSame('Pure Evil', $expanded->get2('title'));
    $this->assertNull($expanded->get2('title-link'));
  }

  public function testRemoveWikilinks4() : void {
    $expanded = $this->process_citation("{{Cite journal|title=[Pure Evil]]}}");
    $this->assertSame('Pure Evil', $expanded->get2('title'));
  }
  public function testRemoveWikilinks4b() : void {
    $expanded = $this->process_citation("{{Cite journal|title=Dark Lord of the [[Sith]] Pure Evil}}");
    $this->assertSame('Dark Lord of the [[Sith]] Pure Evil', $expanded->get2('title'));
    $this->assertNull($expanded->get2('title-link'));
  }
  public function testRemoveWikilinks4c() : void {
    $expanded = $this->process_citation("{{cite journal|journal=[[Bulletin du Muséum national d’Histoire naturelle, Paris]]}}");
    $this->assertSame("[[Bulletin du Muséum national d'Histoire naturelle, Paris]]", $expanded->get2('journal'));
  }
  public function testRemoveWikilinks4d() : void {
    $expanded = $this->process_citation("{{cite journal|journal=[[Bulletin du Muséum national d’Histoire naturelle, Paris|Hose]]}}");
    $this->assertSame("[[Bulletin du Muséum national d'Histoire naturelle, Paris|Hose]]", $expanded->get2('journal'));
  }
 
  public function testRemoveWikilinks5() : void {
    $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil]]}}");
    $this->assertSame('Pure Evil', $expanded->get2('last1'));
    $this->assertSame('Pure Evil', $expanded->get2('author1-link'));
  }
  public function testRemoveWikilinks5b() : void {
    $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil|Approximate Physics]]}}");
    $this->assertSame('Approximate Physics', $expanded->get2('last1'));
    $this->assertSame('Pure Evil', $expanded->get2('author1-link'));
  }
 
  public function testRemoveWikilinks6() : void {
    $expanded = $this->process_citation("{{Cite journal|last2=[[Pure Evil]]}}");
    $this->assertSame('Pure Evil', $expanded->get2('last2'));
    $this->assertSame('Pure Evil', $expanded->get2('author2-link'));
  }
  public function testRemoveWikilinks6b() : void {
    $expanded = $this->process_citation("{{Cite journal|last2=[[Pure Evil|Approximate Physics]]}}");
    $this->assertSame('Approximate Physics', $expanded->get2('last2'));
    $this->assertSame('Pure Evil', $expanded->get2('author2-link'));
  }
 
  public function testRemoveWikilinks7() : void {
    $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil]] and [[Hoser]]}}");
    $this->assertSame('[[Pure Evil]] and [[Hoser]]', $expanded->get2('last1'));
    $this->assertNull($expanded->get2('author1-link'));
  }
  public function testRemoveWikilinks7b() : void {
    $expanded = $this->process_citation("{{Cite journal|last1=[[Pure {{!}} Evil]]}}");
    $this->assertNull($expanded->get2('author1-link'));
    $this->assertSame('[[Pure {{!}} Evil]]', $expanded->get2('last1'));
  }
  public function testRemoveWikilinks7c() : void {
    $text = "{{Cite journal|last=[[Nelarine Cornelius|Cornelius]]|first= [[Nelarine Cornelius|Nelarine]]|last2= Todres|first2= Mathew|last3= Janjuha-Jivraj|first3= Shaheena|last4= Woods|first4= Adrian|last5= Wallace|first5= James|date= 2008|title= Corporate Social Responsibility and the Social Enterprise|jstor= 25482219|journal= Journal of Business Ethics|volume= 81|issue= 2|pages= 355–370|doi= 10.1007/s10551-007-9500-7|s2cid= 154580752|url = <!-- dsfasdfds -->}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('last'));
    $this->assertNull($expanded->get2('first'));
    $this->assertSame('Cornelius', $expanded->get2('last1'));
    $this->assertSame('Nelarine', $expanded->get2('first1'));
    $this->assertSame('Nelarine Cornelius', $expanded->get2('author1-link'));
  }
 
 
  public function testRemoveWikilinks8() : void {
    $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil and Pure Evil and Pure Evil]] and Hoser}}");
    $this->assertSame('[[Pure Evil and Pure Evil and Pure Evil|Pure Evil and Pure Evil and Pure Evil and Hoser]]', $expanded->get2('title'));
    $this->assertNull($expanded->get2('title-link'));
  }
 
  public function testJournalCapitalization1() : void {
    $expanded = $this->process_citation("{{Cite journal|pmid=9858585}}");
    $this->assertSame('Molecular and Cellular Biology', $expanded->get2('journal'));
  }
 
  public function testJournalCapitalization2() : void {
    $expanded = $this->process_citation("{{Cite journal|journal=eJournal}}");
    $this->assertSame('eJournal', $expanded->get2('journal'));
  }
 
  public function testJournalCapitalization3() : void {
    $expanded = $this->process_citation("{{Cite journal|journal=EJournal}}");
    $this->assertSame('eJournal', $expanded->get2('journal'));
  }
 
  public function testJournalCapitalization4() : void {
    $expanded = $this->process_citation("{{Cite journal|journal=ejournal}}");
    $this->assertSame('eJournal', $expanded->get2('journal'));
  }
    
  public function testWebsiteAsJournal() : void {
    $text = '{{Cite journal | journal=www.foobar.com}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('www.foobar.com', $expanded->get2('website'));
    $this->assertNull($expanded->get2('journal'));
    $text = '{{Cite journal | journal=https://www.foobar.com}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('https://www.foobar.com', $expanded->get2('url'));
    $this->assertNull($expanded->get2('journal'));
    $text = '{{Cite journal | journal=[www.foobar.com]}}';
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
  
  public function testDropArchiveDotOrg() : void {
    $text = '{{Cite journal | publisher=archive.org}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('publisher'));
      
    $text = '{{Cite journal | website=archive.org|url=http://fake.url/NOT_REAL}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('http://fake.url/NOT_REAL', $expanded->get2('url'));
    $this->assertNull($expanded->get2('website'));
  }
 
  public function testCovertUrl2Chapter() : void {
    // Do not change
    $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/}}';
    $expanded = $this->make_citation($text);
    $expanded->change_name_to('cite book');
    $this->assertNull($expanded->get2('chapter-url'));
    $this->assertNull($expanded->get2('chapterurl'));
    $this->assertNotNull($expanded->get2('url'));
   
    $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/0}}';
    $expanded = $this->make_citation($text);
    $expanded->change_name_to('cite book');
    $this->assertNull($expanded->get2('chapter-url'));
    $this->assertNull($expanded->get2('chapterurl'));
    $this->assertNotNull($expanded->get2('url'));
   
    $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/1}}';
    $expanded = $this->make_citation($text);
    $expanded->change_name_to('cite book');
    $this->assertNull($expanded->get2('chapter-url'));
    $this->assertNull($expanded->get2('chapterurl'));
    $this->assertNotNull($expanded->get2('url'));
   
    $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page}}';
    $expanded = $this->make_citation($text);
    $expanded->change_name_to('cite book');
    $this->assertNull($expanded->get2('chapter-url'));
    $this->assertNull($expanded->get2('chapterurl'));
    $this->assertNotNull($expanded->get2('url')); 
   
    // Do change
    $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/232}}';
    $expanded = $this->make_citation($text);
    $expanded->change_name_to('cite book');
    $this->assertNotNull($expanded->get2('chapter-url'));
    $this->assertNull($expanded->get2('chapterurl'));
    $this->assertNull($expanded->get2('url'));
   
    $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/chapter/}}';
    $expanded = $this->make_citation($text);
    $expanded->change_name_to('cite book');
    $this->assertNotNull($expanded->get2('chapter-url'));
    $this->assertNull($expanded->get2('chapterurl'));
    $this->assertNull($expanded->get2('url'));
  }
 
  public function testPreferLinkedPublisher() : void {
    $text = "{{cite journal| journal=The History Teacher| publisher=''[[The History Teacher]]'' }}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('publisher'));
    $this->assertSame("[[The History Teacher]]", $expanded->get2('journal')); // Quotes do get dropped
  }
 
  public function testLeaveArchiveURL() : void {
    $text = '{{cite book |chapterurl=http://faculty.haas.berkeley.edu/shapiro/thicket.pdf|isbn=978-0-262-60041-5|archiveurl=https://web.archive.org/web/20070704074830/http://faculty.haas.berkeley.edu/shapiro/thicket.pdf }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('https://web.archive.org/web/20070704074830/http://faculty.haas.berkeley.edu/shapiro/thicket.pdf', $expanded->get2('archive-url'));
  }

  public function testScriptTitle() : void {
    $text = "{{cite book |author={{noitalic|{{lang|zh-hans|国务院人口普查办公室、国家统计局人口和社会科技统计司编}}}} |date=2012 |script-title=zh:中国2010年人口普查分县资料 |location=Beijing |publisher={{noitalic|{{lang|zh-hans|中国统计出版社}}}} [China Statistics Press] |page= |isbn=978-7-5037-6659-6 }}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('title')); // Already have script-title that matches what google books gives us
    $this->assertTrue($expanded->add_if_new('title', 'This English Only'));
    $this->assertSame('This English Only', $expanded->get2('title'));              
  }
    
  public function testPageDuplication() : void {
     // Fake bibcoce otherwise we'll find a bibcode
     $text = '{{cite journal| p=546 |doi=10.1103/PhysRev.57.546|title=Nuclear Fission of Separated Uranium Isotopes |journal=Physical Review |volume=57 |issue=6 |year=1940 |last1=Nier |first1=Alfred O. |last2=Booth |first2=E. T. |last3=Dunning |first3=J. R. |last4=Grosse |first4=A. V. |bibcode=XXXXXXXXXXXXX}}';
     $expanded = $this->process_citation($text);
     $this->assertSame($text, str_replace(' page=546 ', ' p=546 ', $expanded->parsed_text()));
   }

  public function testLastVersusAuthor() : void {
    $text = "{{cite journal|pmid=12858711}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('author1'));
    $this->assertSame('Lovallo', $expanded->get2('last1'));
  }
    
  public function testUnknownJournal() : void {
    $text = '{{cite journal }}';
    $expanded = $this->process_citation($text);
    $expanded->add_if_new('journal','Unknown');
    $this->assertTrue($expanded->blank('journal'));
  }

  public function testCiteArxivRecognition() : void {
    $text = '{{Cite web | eprint=1203.0149}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Cite arXiv', $expanded->name());
  }
 
  public function testTwoUrls() : void {
    $text = '{{citation|url=http://jstor.org/stable/333111333|chapter-url=http://adsabs.harvard.edu/abs/2222NatSR...814768S}}'; // Both fake
    $expanded = $this->process_citation($text);
    $this->assertSame('333111333', $expanded->get2('jstor'));
    $this->assertSame('2222NatSR...814768S', $expanded->get2('bibcode'));
    $this->assertNotNull($expanded->get2('url'));
    $this->assertNotNull($expanded->get2('chapter-url'));
  }

  public function testBrokenDoiDetection1() : void {
    $text = '{{cite journal|doi=10.3265/Nefrologia.pre2010.May.10269|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('doi-broken-date'));
  }

  public function testBrokenDoiDetection2() : void {
    $text = '{{cite journal|doi=10.3265/Nefrologia.NOTAREALDOI.broken|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('doi-broken-date'));
  }

  public function testBrokenDoiDetection3() : void {
    $text = '{{cite journal|doi= <!-- MC Hammer says to not touch this -->}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('doi-broken-date'));
    $this->assertSame('<!-- MC Hammer says to not touch this -->', $expanded->get2('doi'));
  }

  public function testBrokenDoiDetection4() : void {
    $text = '{{cite journal|doi= {{MC Hammer says to not touch this}} }}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('doi-broken-date'));
    $this->assertSame('{{MC Hammer says to not touch this}}', $expanded->get2('doi'));
  }

  public function testBrokenDoiDetection5() : void {
    $text = '{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}', $expanded->parsed_text());
  }
    
  public function testCrossRefEvilDoi() : void {
    $text = '{{cite journal | doi = 10.1002/(SICI)1097-0134(20000515)39:3<216::AID-PROT40>3.0.CO;2-#}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('doi-broken-date'));
    $this->assertSame('39', $expanded->get2('volume'));
  }

  public function testOpenAccessLookup1() : void {    
    $text = '{{cite journal|doi=10.1136/bmj.327.7429.1459}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('300808', $expanded->get2('pmc'));
  }

  public function testOpenAccessLookup2() : void {  
    $text = '{{cite journal|doi=10.1038/nature08244}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('0904.1532', $expanded->get2('arxiv'));
  }

  public function testOpenAccessLookup3() : void {   
    $text = '{{cite journal | vauthors = Shekelle PG, Morton SC, Jungvig LK, Udani J, Spar M, Tu W, J Suttorp M, Coulter I, Newberry SJ, Hardy M | title = Effect of supplemental vitamin E for the prevention and treatment of cardiovascular disease | journal = Journal of General Internal Medicine | volume = 19 | issue = 4 | pages = 380–9 | date = April 2004 | pmid = 15061748 | pmc = 1492195 | doi = 10.1111/j.1525-1497.2004.30090.x }}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('url'));
  }

  public function testOpenAccessLookup4() : void {  
    $text = '{{Cite journal | doi = 10.1063/1.4962420| title = Calculating vibrational spectra of molecules using tensor train decomposition| journal = J. Chem. Phys. | volume = 145| year = 2016| issue = 145| pages = 124101| last1 = Rakhuba| first1 = Maxim | last2 = Oseledets | first2 = Ivan| bibcode = 2016JChPh.145l4101R| arxiv =1605.08422}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('url'));
  }
 
  public function testOpenAccessLookup6() : void {  
    $text = '{{Cite journal | doi = 10.5260/chara.18.3.53|hdl=10393/35779}}'; 
    $expanded = $this->process_citation($text);
    $this->assertSame('10393/35779', $expanded->get2('hdl')); // This basically runs through a bunch of code to return 'have free'
  }
 
  public function testOpenAccessLookup7() : void {  
    $text = '{{Cite journal | doi = 10.5260/chara.18.3.53|hdl=10393/XXXXXX}}'; 
    $expanded = $this->process_citation($text);
    $this->assertSame('10393/XXXXXX', $expanded->get2('hdl')); // This basically runs through a bunch of code to return 'have free'
    $this->assertNull($expanded->get2('url'));
  }

  public function testSemanticScholar() : void {
   $text = "{{cite journal|doi=10.5555/555555}}";
   $template = $this->make_citation($text);
   $return = $template->get_unpaywall_url($template->get2('doi'));
   $this->assertSame('nothing', $return);
   $this->assertNull($template->get2('url'));
  }
  
  public function testUnPaywall1() : void {
   $text = "{{cite journal}}";
   $template = $this->make_citation($text);
   $template->get_semanticscholar_url('10.1145/358589.358596', '');
   $this->assertNull($template->get2('url'));
   $this->assertSame('10.1145/358589.358596', $template->get2('doi'));
  }

  public function testUnPaywall2() : void {
   $text = "{{cite journal|doi=10.1145/358589.358596|doi-access=free}}";
   $template = $this->make_citation($text);
   $template->get_semanticscholar_url($template->get2('doi'), '');
   $this->assertNull($template->get2('url'));
  }
 
  public function testCommentHandling() : void {
    $text = "{{cite book|pages=3333 <!-- yes --> }} {{cite book <!-- no --> | pages=3<nowiki>-</nowiki>6}} {{cite book | pages=3<pre>-</pre>6}} {{cite book | pages=3<math>-</math>6}} {{cite book | pages=3<score>-</score>6}} {{cite book | pages=3<chem>-</chem>6}}";
    $expanded_page = $this->process_page($text);
    $this->assertSame($text, $expanded_page->parsed_text());
  }
  
  public function testDoi2PMID() : void {
    $text = "{{cite journal|doi=10.1073/pnas.171325998}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('11573006', $expanded->get2('pmid'));
    $this->assertSame('58796', $expanded->get2('pmc'));
  }
 
  public function testSiciExtraction1() : void {
    $text='{{cite journal|url=http://fake.url/9999-9999(2002)152[0215:XXXXXX]2.0.CO;2}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('9999-9999', $expanded->get2('issn')); // Fake to avoid cross-ref search
    $this->assertSame('2002', $this->getDateAndYear($expanded));
    $this->assertSame('152', $expanded->get2('volume'));
    $this->assertSame('215', $expanded->get2('page'));
  }

  public function testSiciExtraction2() : void {
    // Now check that parameters are NOT extracted when certain parameters exist
    $text = "{{cite journal|date=2002|journal=SET|url=http:/1/fake.url/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('issn'));
    $this->assertSame('2002', $this->getDateAndYear($expanded));
    $this->assertSame('152', $expanded->get2('volume'));
    $this->assertSame('215', $expanded->get2('page'));
  }
 
  public function testUseISSN() : void {
      $text = "{{Cite book|issn=0031-0603}}";
      $expanded = $this->process_citation($text);
      $this->assertTrue(stripos($expanded->get('journal'), 'Entomologist') !== FALSE);
  }
 
  public function testParameterAlias() : void {
    $text = '{{cite journal |author-last1=Knops |author-first1=J.M. |author-last2=Nash III |author-first2=T.H.
    |date=1991 |title=Mineral cycling and epiphytic lichens: Implications at the ecosystem level 
    |journal=Lichenologist |volume=23 |pages=309–321 |doi=10.1017/S0024282991000452 |issue=3}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('last1'));
    $this->assertNull($expanded->get2('last2'));
    $this->assertNull($expanded->get2('first1'));
    $this->assertNull($expanded->get2('first2'));
  }
    
  public function testMisspeltParameters() : void {
    $text = "{{Cite journal | ahtour=S.-X. HU, M.-Y. ZHU, F.-C. ZHAO, and M. STEINER|tutle=A crown group priapulid from the early Cambrian Guanshan Lagerstätte,|jrounal=Geol. Mag.|pp. 1–5|year= 2017.}}";
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('author')); ## Check: the parameter might be broken down into last1, first1 etc
    $this->assertNotNull($expanded->get2('title'));
    $this->assertNotNull($expanded->get2('journal'));
    $this->assertNotNull($expanded->get2('pages'));
    $this->assertNotNull($this->getDateAndYear($expanded));
    
    $text = "{{Cite journal | ahtour=S.-X. HU, M.-Y. ZHU, F.-C. ZHAO, and M. STEINER|tutel=A crown group priapulid from the early Cambrian Guanshan Lagerstätte,|jrounal=Geol. Mag.|pp. 1–5|year= 2017.}}";
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('author')); ## Check: the parameter might be broken down into last1, first1 etc
    $this->assertNotNull($expanded->get2('tutel'));
    $this->assertNotNull($expanded->get2('journal'));
    $this->assertNotNull($expanded->get2('pages'));
    $this->assertNotNull($this->getDateAndYear($expanded)); 
     
    // Double-check pages expansion
    $text = "{{Cite journal|pp. 1-5}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('1–5', $expanded->get2('pages'));
      
    $text = "{{cite book|authorlinux=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('{{cite book|authorlink=X}}', $expanded->parsed_text());
      
    $text = "{{cite book|authorlinks33=X}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('{{cite book|authorlink33=X}}', $expanded->parsed_text());
  }
       
  public function testId2Param1() : void {
      $text = '{{cite book |id=ISBN 978-1234-9583-068, DOI 10.1234/bashifbjaksn.ch2, {{arxiv|1234.5678}} {{oclc|12354|4567}} {{oclc|1234}} {{ol|12345}} }}';
      $expanded = $this->process_citation($text);
      $this->assertSame('978-1234-9583-068', $expanded->get2('isbn'));
      $this->assertSame('1234.5678', $expanded->get2('arxiv'));
      $this->assertSame('10.1234/bashifbjaksn.ch2', $expanded->get2('doi'));
      $this->assertSame('1234', $expanded->get2('oclc'));
      $this->assertSame('12345', $expanded->get2('ol'));
      $this->assertNotNull($expanded->get2('doi-broken-date'));
      $this->assertSame(0, preg_match('~' . sprintf(Template::PLACEHOLDER_TEXT, '\d+') . '~i', $expanded->get2('id')));
  }

  public function testId2Param2() : void {
      $text = '{{cite book | id={{arxiv|id=1234.5678}}}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('1234.5678', $expanded->get2('arxiv'));
  }

  public function testId2Param3() : void {
      $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} }}';
      $expanded = $this->process_citation($text);
      $this->assertSame('astr.ph/1234.5678', $expanded->get2('arxiv'));     
  }

  public function testId2Param4() : void {
      $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} {{arxiv|astr.ph|1234.5678}} }}'; // Two of the same thing
      $expanded = $this->process_citation($text);
      $this->assertSame('astr.ph/1234.5678', $expanded->get2('arxiv'));
      $this->assertSame('{{cite book | arxiv=astr.ph/1234.5678 }}', $expanded->parsed_text());
  }

  public function testId2Param5() : void {
      $text = '{{cite book|pages=1–2|id={{arxiv|astr.ph|1234.5678}}}}{{cite book|pages=1–3|id={{arxiv|astr.ph|1234.5678}}}}'; // Two of the same sub-template, but in different tempalates
      $expanded = $this->process_page($text);
      $this->assertSame('{{cite book|pages=1–2|arxiv=astr.ph/1234.5678}}{{cite book|pages=1–3|arxiv=astr.ph/1234.5678}}', $expanded->parsed_text());
  }
  
  public function testNestedTemplates1() : void {
      $text = '{{cite book|pages=1-2| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} | id={{cite book|pages=1-3| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} }}  }} |  cool stuff | not cool}}}}';
      $expanded = $this->process_citation($text);
      $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
      $this->assertSame($text, $expanded->parsed_text());
  }

  public function testNestedTemplates2() : void {
      $text = '{{cite book|quote=See {{cite book|pages=1-2|quote=See {{cite book|pages=1-4}}}}|pages=1-3}}';
      $expanded = $this->process_citation($text);
      $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
      $this->assertSame($text, $expanded->parsed_text());
  }
    
  public function testDropPostscript() : void {
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
    
  public function testChangeParamaters1() : void {
      // publicationplace
      $text = '{{citation|publicationplace=Home}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|publication-place=Home}}', $prepared->parsed_text());
  }

  public function testChangeParamaters2() : void {
      $text = '{{citation|publication-place=Home|location=Away}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());
  }

  public function testChangeParamaters3() : void {
      // publicationdate
      $text = '{{citation|publicationdate=2000}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|publication-date=2000}}', $prepared->parsed_text());
  }

  public function testChangeParamaters4() : void {
      $text = '{{citation|publicationdate=2000|date=1999}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|publication-date=2000|date=1999}}', $prepared->parsed_text());
  }

  public function testChangeParamaters5() : void {
      // origyear
      $text = '{{citation|origyear=2000}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
  }

  public function testChangeParamaters6() : void {
      $text = '{{citation|origyear=2000|date=1999}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text()); 
 }

  public function testDropDuplicates1() : void {
      $text = '{{citation|work=Work|journal=|magazine=|website=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|work=Work}}', $prepared->parsed_text());
  }

  public function testDropDuplicates2() : void {
      $text = '{{citation|work=Work|journal=Journal|magazine=Magazine|website=Website}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());
  }
 
  public function testFixCAPSJunk() : void {
      $text = '{{citation|URL=X}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('X', $prepared->get('url'));
      $this->assertNull($prepared->get2('URL'));
  }

  public function testFixCAPSJunk2() : void {
      $text = '{{cite news|URL=X}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('X', $prepared->get('url'));
      $this->assertNull($prepared->get2('URL'));
  }
 
  public function testFixCAPSJunk3() : void {
      $text = '{{cite news|URL=X|url=Y}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('Y', $prepared->get('url'));
      $this->assertSame('X', $prepared->get('URL'));
  }
 
  public function testFixCAPSJunk4() : void {
      $text = '{{cite journal|URL=X|url=Y}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('Y', $prepared->get('url'));
      $this->assertNull($prepared->get2('URL'));
      $this->assertSame('X', $prepared->get('DUPLICATE_url'));
  }
 
  public function testBadPunctuation1() : void {
      $text = '{{citation|title=:: Huh ::}}';
      $prepared = $this->make_citation($text);
      $prepared->tidy_parameter('title');
      $this->assertSame(':: Huh ::', $prepared->get2('title'));
  }

  public function testBadPunctuation2() : void {
      $text = '{{citation|title=: Huh :}}';
      $prepared = $this->make_citation($text);
      $prepared->tidy_parameter('title');
      $this->assertSame('Huh', $prepared->get2('title'));
  }

  public function testBadPunctuation3() : void {
      $text = '{{citation|title=; Huh ;;}}';
      $prepared = $this->make_citation($text);
      $prepared->tidy_parameter('title');
      $this->assertSame('Huh ;;', $prepared->get2('title'));
  }
 
  public function testWorkParamter1() : void {
      $text = '{{citation|work=RUBBISH|title=Rubbish|chapter=Dog}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|title=Rubbish|chapter=Dog}}', $prepared->parsed_text());
  }

  public function testWorkParamter2() : void {
      $text = '{{cite book|series=Keep Series, Lose Work|work=Keep Series, Lose Work}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite book|series=Keep Series, Lose Work}}', $prepared->parsed_text());
  }

  public function testWorkParamter3() : void {
      $text = '{{cite journal|chapter=A book chapter|work=A book chapter}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite book|chapter=A book chapter}}', $prepared->parsed_text());
  }

  public function testWorkParamter4() : void {
      $text = '{{citation|work=I Live}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame($text, $prepared->parsed_text());
  }

  public function testWorkParamter5() : void {
      $text = '{{not cite|work=xyz|chapter=xzy}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{not cite|work=xyz|chapter=xzy}}', $prepared->parsed_text());
  }

  public function testWorkParamter6() : void {
      $text = '{{citation|work=xyz|journal=xyz}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|journal=Xyz}}', $prepared->parsed_text());
  }

  public function testWorkParamter7() : void {
      $text = '{{citation|work=|chapter=Keep work in Citation template}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{citation|work=|chapter=Keep work in Citation template}}', $prepared->parsed_text());
  }

  public function testWorkParamter8() : void {
      $text = '{{cite journal|work=work should become journal}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite journal|journal=Work Should Become Journal}}', $prepared->parsed_text());
  }

  public function testWorkParamter9() : void {
      $text = '{{cite magazine|work=abc}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite magazine|magazine=abc}}', $prepared->parsed_text());
  }

  public function testWorkParamter10() : void {
      $text = '{{cite journal|work=}}';
      $prepared = $this->prepare_citation($text);
      $prepared->final_tidy();
      $this->assertSame('{{cite journal|journal=}}', $prepared->parsed_text());
  }
  
  public function testOrigYearHandling() : void {
      $text = '{{cite book |year=2009 | origyear = 2000 }}';
      $prepared = $this->process_citation($text);
      $this->assertSame('2000', $prepared->get2('origyear'));
      $this->assertSame('2009', $this->getDateAndYear($prepared));
      
      $text = '{{cite book | origyear = 2000 }}';
      $prepared = $this->process_citation($text);
      $this->assertSame('2000', $this->getDateAndYear($prepared));
      $this->assertNull($prepared->get2('origyear'));
  }
    
  public function testDropAmazon() : void {
    $text = '{{Cite journal | publisher=amazon.com}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('publisher'));
    $text = '{{Cite journal | publisher=amazon.com|url=https://www.amazon.com/stuff}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('publisher'));
    $text = '{{Cite journal | publisher=amazon.com|url=https://www.amazon.com/dp/}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('publisher'));
  }
    
  public function testGoogleBooksExpansion() : void {
    $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html?id=SjpSkzjIzfsC&redir_esc=y}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC', $expanded->get2('url'));
    $this->assertSame('Wonderful Life: The Burgess Shale and the Nature of History', $expanded->get2('title'));
    $this->assertSame('9780393307009', $expanded->get2('isbn')   );
    $this->assertSame('Gould'        , $expanded->get2('last1'));
    $this->assertSame('Stephen Jay'  , $expanded->get2('first1') );
    $this->assertSame('17 September 1990'   , $expanded->get2('date'));
    $this->assertNull($expanded->get2('pages')); // Do not expand pages.  Google might give total pages to us
  }
 
   public function testGoogleBooksExpansion2() : void {
    $text = "{{Cite web | url=https://books.google.com/books?id=SjpSkzjIzfsC&printsec=frontcover#v=onepage}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC', $expanded->get2('url'));
   }

  public function testGoogleBooksExpansionNEW() : void {
    $text = "{{Cite web | url=https://www.google.com/books/edition/_/SjpSkzjIzfsC?hl=en}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
    $this->assertSame('https://www.google.com/books/edition/_/SjpSkzjIzfsC', $expanded->get2('url'));
    $this->assertSame('Wonderful Life: The Burgess Shale and the Nature of History',$expanded->get2('title'));
    $this->assertSame('9780393307009', $expanded->get2('isbn')   );
    $this->assertSame('Gould'        , $expanded->get2('last1'));
    $this->assertSame('Stephen Jay'  , $expanded->get2('first1') );
    $this->assertSame('17 September 1990'   , $expanded->get2('date'));
    $this->assertNull($expanded->get2('pages')); // Do not expand pages.  Google might give total pages to us
  }

  
  public function testGoogleDates() : void {
    $text = "{{cite book|url=https://books.google.com/books?id=yN8DAAAAMBAJ&pg=PA253}}";
    $expanded = $this->process_citation($text);
    $this->assertTrue(in_array($expanded->get2('date'), ['February 1935', '1935-02']));
    // Google recovers Feb 1935; Zotero returns 1935-02.
  }
  
  public function testLongAuthorLists() : void {
  $this->requires_arxiv(function() : void {
    $text = '{{cite web | https://arxiv.org/PS_cache/arxiv/pdf/1003/1003.3124v2.pdf|doi=<!--Do not add-->}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('The ATLAS Collaboration', $expanded->first_author());
   });
  }
  public function testLongAuthorLists2() : void {
    // Same paper as testLongAuthorLists(), but CrossRef records full list of authors instead of collaboration name
    $text = '{{cite web | 10.1016/j.physletb.2010.03.064}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('29', $expanded->get2('display-authors'));
    $this->assertSame('Aielli', $expanded->get2('last30'));
    $this->assertSame("Charged-particle multiplicities in pp interactions at <math>"
      . '\sqrt{s}=900\text{ GeV}' .
      "</math> measured with the ATLAS detector at the LHC", $expanded->get2('title'));
    $this->assertNull($expanded->get2('last31'));
  }
  
  public function testInPress() : void {  
    $text = '{{Cite journal|pmid=9858585|date =in press}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('1999', $this->getDateAndYear($expanded));
  }
  
 public function testISODates() : void {
    $text = '{{cite book |author=Me |title=Title |year=2007-08-01 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('2007-08-01', $prepared->get2('date'));
    $this->assertNull($prepared->get2('year'));
  }
  
  public function testND() : void {  // n.d. is special case that template recognize.  Must protect final period.
    $text = '{{Cite journal|date =n.d.}}';
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
    
    $text = '{{Cite journal|year=n.d.}}';
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
    
  public function testRIS() : void {
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
     $this->assertSame('A Mathematical Theory of Communication', $prepared->get2('title'));
     $this->assertSame('1948-07', $prepared->get2('date'));
     $this->assertSame('Bell System Technical Journal', $prepared->get2('journal'));
     $this->assertSame('Shannon, Claude E.', $prepared->first_author());
     $this->assertSame('Shannon', $prepared->get2('last1'));
     $this->assertSame('Claude E.', $prepared->get2('first1'));
     $this->assertSame('379–423', $prepared->get2('pages'));
     $this->assertSame('27', $prepared->get2('volume'));   
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
     $this->assertNull($prepared->get2('title'));
     $this->assertNull($prepared->get2('date'));
     $this->assertNull($prepared->get2('journal'));
     $this->assertSame('', $prepared->first_author());
     $this->assertNull($prepared->get2('last1'));
     $this->assertNull($prepared->get2('first1'));
     $this->assertNull($prepared->get2('pages'));
     $this->assertNull($prepared->get2('volume'));
   
      $text = '{{Cite journal  | TY - BOOK
Y1 - 1990
T1 - This will be a subtitle
ZZ - This will be ignored and not understood}}';
     $prepared = $this->prepare_citation($text);
     $this->assertSame('1990', $prepared->get2('year')); 
     $this->assertNull($prepared->get2('title'));
     $this->assertNull($prepared->get2('chapter'));
     $this->assertNull($prepared->get2('journal'));
     $this->assertNull($prepared->get2('series'));

     $text = '{{Cite journal  | TY - JOUR
Y1 - 1990
JF - This is the Journal
T1 - This is the Title }}';
     $prepared = $this->prepare_citation($text);
     $this->assertSame('1990', $prepared->get2('year')); 
     $this->assertSame('This is the Journal', $prepared->get2('journal'));
     $this->assertSame('This is the Title', $prepared->get2('title'));
  }
    
  public function testEndNote() : void {
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
       $this->assertSame('The Works of Geoffrey Chaucer', $prepared->get2('title'));
       $this->assertSame('1957', $this->getDateAndYear($prepared));
       $this->assertSame('Houghton', $prepared->get2('publisher'));
       $this->assertSame('Boston', $prepared->get2('location'));
       
       $prepared = $this->process_citation($article);
       $this->assertSame('Clark, Herbert H.', $prepared->first_author());
       $this->assertSame('1982', $this->getDateAndYear($prepared));
       $this->assertSame('Hearers and Speech Acts', $prepared->get2('title'));
       $this->assertSame('58', $prepared->get2('volume'));
       $this->assertSame('332–373', $prepared->get2('pages'));
       
       
       $prepared = $this->process_citation($thesis);
       $this->assertSame('Cantucci, Elena', $prepared->first_author());
       $this->assertSame('Permian strata in South-East Asia', $prepared->get2('title'));
       $this->assertSame('1990', $this->getDateAndYear($prepared));
       $this->assertSame('University of California, Berkeley', $prepared->get2('publisher'));
       $this->assertSame('10.1038/ntheses.01928', $prepared->get2('doi'));
   
       $prepared = $this->process_citation($code_coverage1);
       $this->assertSame('This Title', $prepared->get2('title'));;
       $this->assertSame('9999-9999', $prepared->get2('issn'));
   
       $prepared = $this->process_citation($code_coverage2);
       $this->assertSame('This Title', $prepared->get2('title'));;
       $this->assertSame('000-000-000-0X', $prepared->get2('isbn'));  
  }
   
  public function testConvertingISBN10intoISBN13() : void { // URLS present just to speed up tests.  Fake years to trick date check
    $text = "{{cite book|isbn=0-9749009-0-7|url=https://books.google.com/books?id=to0yXzq_EkQC|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('978-0-9749009-0-2', $prepared->get2('isbn'));  // Convert with dashes
    
    $text = "{{cite book|isbn=978-0-9749009-0-2|url=https://books.google.com/books?id=to0yXzq_EkQC|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('978-0-9749009-0-2', $prepared->get2('isbn'));  // Unchanged with dashes
    
    $text = "{{cite book|isbn=9780974900902|url=https://books.google.com/books?id=to0yXzq_EkQC|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('9780974900902', $prepared->get2('isbn'));   // Unchanged without dashes
    
    $text = "{{cite book|isbn=0974900907|url=https://books.google.com/books?id=to0yXzq_EkQC|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('978-0974900902', $prepared->get2('isbn'));   // Convert without dashes
    
    $text = "{{cite book|isbn=1-84309-164-X|url=https://books.google.com/books?id=GvjwAQAACAAJ|year=2019}}";
    $prepared = $this->prepare_citation($text);  
    $this->assertSame('978-1-84309-164-6', $prepared->get2('isbn'));  // Convert with dashes and a big X
    
    $text = "{{cite book|isbn=184309164x|url=https://books.google.com/books?id=GvjwAQAACAAJ|year=2019}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('978-1843091646', $prepared->get2('isbn'));  // Convert without dashes and a tiny x
    
    $text = "{{cite book|isbn=Hello Brother}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Hello Brother', $prepared->get2('isbn')); // Rubbish unchanged
   
    $text = "{{cite book|isbn=184309164x 978324132412}}";
    $prepared = $this->prepare_citation($text);
    $this->assertSame('184309164x 978324132412', $prepared->get2('isbn'));  // Do not dash between multiple ISBNs
  }
   
  public function testEtAl() : void {
    $text = '{{cite book |auths=Alfred A Albertstein, Bertie B Benchmark, Charlie C. Chapman et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Albertstein, Alfred A.', $prepared->first_author());
    $this->assertSame('Charlie C.', $prepared->get2('first3'));
    $this->assertSame('etal', $prepared->get2('display-authors'));
  }
 
  public function testEtAlAsAuthor() : void {
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get2('display-authors'));
    $this->assertNull($prepared->get2('author3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = et al. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get2('display-authors'));
    $this->assertNull($prepared->get2('last3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = etal. }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get2('display-authors'));
    $this->assertNull($prepared->get2('author3'));
    $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = etal }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get2('display-authors'));
    $this->assertNull($prepared->get2('last3'));
    $text = '{{cite book|last1=etal}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get2('display-authors'));
    $this->assertNull($prepared->get2('last1'));
    $text = '{{cite book|last1=et al}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get2('display-authors'));
    $this->assertNull($prepared->get2('last1'));
    $text = '{{cite book|last1=et al}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('etal', $prepared->get2('display-authors'));
    $this->assertNull($prepared->get2('last1'));
  }
       
  public function testWebsite2Url1() : void {
      $text = '{{cite book |website=ttp://example.org }}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get2('url'));
  }

  public function testWebsite2Url2() : void {
      $text = '{{cite book |website=example.org }}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get2('url'));
  }

  public function testWebsite2Url3() : void {
      $text = '{{cite book |website=ttp://jstor.org/pdf/123456 | jstor=123456 }}';
      $prepared = $this->prepare_citation($text);
      $this->assertNotNull($prepared->get2('url'));
  }

  public function testWebsite2Url4() : void {
      $text = '{{cite book |website=ABC}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get2('url'));
      $this->assertSame('ABC', $prepared->get2('website'));
  }

  public function testWebsite2Url5() : void {
      $text = '{{cite book |website=ABC XYZ}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get2('url'));
      $this->assertSame('ABC XYZ', $prepared->get2('website'));
  }

  public function testWebsite2Url6() : void {
      $text = '{{cite book |website=http://ABC/ I have Spaces in Me}}';
      $prepared = $this->prepare_citation($text);
      $this->assertNull($prepared->get2('url'));
      $this->assertSame('http://ABC/ I have Spaces in Me', $prepared->get2('website'));
  }
  
  public function testHearst () : void {
    $text = '{{cite book|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=onepage&q&f=true}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Hearst Magazines', $expanded->get2('publisher'));
    $this->assertNull($expanded->get2('last1'));
    $this->assertNull($expanded->get2('last'));
    $this->assertNull($expanded->get2('author'));
    $this->assertNull($expanded->get2('author1'));
    $this->assertNull($expanded->get2('authors'));
    $this->assertSame('https://books.google.com/books?id=p-IDAAAAMBAJ&q=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194', $expanded->get2('url'));
  }
 
  public function testHearst2 () : void {
    $text = '{{cite book|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=snippet&q&f=true}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Hearst Magazines', $expanded->get2('publisher'));
    $this->assertNull($expanded->get2('last1'));
    $this->assertNull($expanded->get2('last'));
    $this->assertNull($expanded->get2('author'));
    $this->assertNull($expanded->get2('author1'));
    $this->assertNull($expanded->get2('authors'));
    $this->assertSame('https://books.google.com/books?id=p-IDAAAAMBAJ&q=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194', $expanded->get2('url'));
  }
       
  public function testInternalCaps() : void { // checks for title formating in tidy() not breaking things
    $text = '{{cite journal|journal=ZooTimeKids}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('ZooTimeKids', $prepared->get2('journal'));
  }
  
  public function testCapsAfterColonAndPeriodJournalTidy() : void {
    $text = '{{Cite journal |journal=In Journal Titles: a word following punctuation needs capitals. Of course.}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('In Journal Titles: A Word Following Punctuation Needs Capitals. Of Course.', 
                        $prepared->get2('journal'));
  }      

  public function testExistingWikiText() : void { // checks for formating in tidy() not breaking things
    $text = '{{cite journal|title=[[Zootimeboys]] and Girls|journal=[[Zootimeboys]] and Girls}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Zootimeboys and Girls', $prepared->get2('journal'));
    $this->assertSame('[[Zootimeboys]] and Girls', $prepared->get2('title'));
  }
  
  public function testNewWikiText() : void { // checks for new information that looks like wiki text and needs escaped
    $text = '{{Cite journal|doi=10.1021/jm00193a001}}';  // This has greek letters, [, ], (, and ).
    $expanded = $this->process_citation($text);
    $this->assertSame('Synthetic studies on β-lactam antibiotics. Part 10. Synthesis of 7β-&#91;2-carboxy-2-(4-hydroxyphenyl)acetamido&#93;-7.alpha.-methoxy-3-&#91;&#91;(1-methyl-1H-tetrazol-5-yl)thio&#93;methyl&#93;-1-oxa-1-dethia-3-cephem-4-carboxylic acid disodium salt (6059-S) and its related 1-oxacephems', $expanded->get2('title'));
  }
  
  public function testZooKeys() : void {
   $this->requires_secrets(function() : void {
    $text = '{{Cite journal|doi=10.3897/zookeys.445.7778}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('ZooKeys', $expanded->get2('journal'));
    $this->assertSame('445', $expanded->get2('issue'));
    $this->assertNull($expanded->get2('volume'));
    $text = '{{Cite journal|doi=10.3897/zookeys.445.7778|journal=[[Zookeys]]}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('445', $expanded->get2('issue'));
    $this->assertNull($expanded->get2('volume'));
    $text = "{{cite journal|last1=Bharti|first1=H.|last2=Guénard|first2=B.|last3=Bharti|first3=M.|last4=Economo|first4=E.P.|title=An updated checklist of the ants of India with their specific distributions in Indian states (Hymenoptera, Formicidae)|journal=ZooKeys|date=2016|volume=551|pages=1–83|doi=10.3897/zookeys.551.6767|pmid=26877665|pmc=4741291}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('551', $expanded->get2('issue'));
    $this->assertNull($expanded->get2('volume'));
   });
  }
 
  public function testZooKeysDoiTidy1() : void {
      $text = '{{Cite journal|doi=10.3897/zookeys.123.322222}}';
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->assertNull($expanded->get2('journal'));
      $this->assertSame('123', $expanded->get2('issue'));
  }

  public function testZooKeysDoiTidy2() : void {
      $text = '{{Cite journal|doi=10.3897/zookeys.123.322222|issue=2323323}}';
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->assertNull($expanded->get2('journal'));
      $this->assertSame('123', $expanded->get2('issue'));
  }

  public function testZooKeysDoiTidy3() : void {
      $text = '{{Cite journal|doi=10.3897/zookeys.123.322222|number=2323323}}';
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->assertNull($expanded->get2('journal'));
      $this->assertSame('123', $expanded->get2('issue'));
  }

  public function testZooKeysDoiTidy4() : void {
      $text = '{{Cite journal|doi=10.3897/zookeys.123.322222X}}';
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->assertNull($expanded->get2('journal'));
      $this->assertNull($expanded->get2('issue'));
  }
 
  public function testTitleItalics(){
    $text = '{{cite journal|doi=10.1111/pala.12168}}';
    $expanded = $this->process_citation($text);
    $title = $expanded->get2('title');
    $title = str_replace('‐', '-', $title); // Dashes vary
    $title = str_replace("'", "", $title);  // Sometimes there, sometime not
    $this->assertSame("The macro- and microfossil record of the Cambrian priapulid Ottoia", $title);
  }
  
  public function testSpeciesCaps() : void {
    $text = '{{Cite journal | doi = 10.1007%2Fs001140100225}}';
    $expanded = $this->process_citation($text);
    $this->assertSame(str_replace(' ', '', "Crypticmammalianspecies:Anewspeciesofwhiskeredbat(''Myotisalcathoe''n.sp.)inEurope"), 
                        str_replace(' ', '', $expanded->get2('title')));
    $text = '{{Cite journal | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1550-7408.2002.tb00224.x/full}}';
    // Should be able to drop /full from DOI in URL
    $expanded = $this->process_citation($text);
    $this->assertSame(str_replace(' ', '', "''Cryptosporidiumhominis''n.sp.(Apicomplexa:Cryptosporidiidae)fromHomosapiens"),
                        str_replace(' ', '', $expanded->get2('title'))); // Can't get Homo sapiens, can get nsp.
  }   
    
  public function testSICI() : void {
    $url = "https://fake.url/sici?sici=9999-9999(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
    $text = "{{Cite journal|url=$url}}";  // We use a rubbish ISSN and website so that this does not expand any more -- only test SICI code
    $expanded = $this->process_citation($text);
      
    $this->assertSame('1961', $expanded->get2('year'));
    $this->assertSame('81', $expanded->get2('volume'));
    $this->assertSame('1', $expanded->get2('issue'));
    $this->assertSame('43', $expanded->get2('page'));
  }
  
  public function testJstorSICI() : void {
    $url = "https://www.jstor.org/sici?sici=0003-0279(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
    $text = "{{Cite journal|url=$url}}";
    $expanded = $this->process_citation($text);
      
    $this->assertSame('594900', $expanded->get2('jstor'));
    $this->assertSame('1961', $expanded->get2('year'));
    $this->assertSame('81', $expanded->get2('volume'));
    $this->assertSame('1', $expanded->get2('issue'));
    $this->assertSame('43–52', $expanded->get2('pages'));  // The jstor expansion add the page ending
  }
  
  public function testJstorSICIEncoded() : void {
    $text = '{{Cite journal|url=https://www.jstor.org/sici?sici=0003-0279(196101%2F03)81%3A1%3C43%3AWLIMP%3E2.0.CO%3B2-9}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('594900', $expanded->get2('jstor'));
  }

  public function testIgnoreJstorPlants() : void {
    $text='{{Cite journal| url=http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972 |title=Holotype of Persoonia terminalis L.A.S.Johnson & P.H.Weston [family PROTEACEAE]}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972', $expanded->get2('url'));
    $this->assertNull($expanded->get2('jstor'));
    $this->assertNull($expanded->get2('doi'));
  }

  public function testConvertJournalToBook() : void {
    $text = '{{Cite journal|doi=10.1007/978-3-540-74735-2_15}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('cite book', $expanded->wikiname());
  }

  public function testRenameToJournal() : void {
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
 
  public function testArxivDocumentBibcodeCode() : void {
    $text = "{{cite arxiv| arxiv=1234|bibcode=abc}}";
    $template = $this->make_citation($text);
    $template->change_name_to('cite journal');
    $template->final_tidy();
    $this->assertSame('cite arxiv', $template->wikiname());
    $this->assertNull($template->get2('bibcode'));    
   
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
 
  public function testArxivToJournalIfDoi() : void {
    $text = "{{cite arxiv| eprint=1234|doi=10.0/000}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite journal', $template->wikiname());  
  }
 
  public function testChangeNameURL() : void {
    $text = "{{cite web|url=x|chapter-url=X|chapter=Z}}";
    $template = $this->process_citation($text);
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Z', $template->get2('chapter'));
    $this->assertSame('X', $template->get2('chapter-url'));
    $this->assertNull($template->get2('url')); // Remove since identical to chapter
  }
 
  public function testRenameToExisting() : void {
    $text = "{{cite journal|issue=1|volume=2|doi=3}}";
    $template = $this->make_citation($text);
    $this->assertSame('{{cite journal|issue=1|volume=2|doi=3}}', $template->parsed_text());
    $template->rename('doi', 'issue');
    $this->assertSame('{{cite journal|volume=2|issue=3}}', $template->parsed_text());
    $template->rename('volume', 'issue');
    $this->assertSame('{{cite journal|issue=2}}', $template->parsed_text());
    $template->forget('issue');
    $this->assertSame('{{cite journal}}', $template->parsed_text());
    $this->assertNull($template->get2('issue'));
    $this->assertNull($template->get2('doi'));
    $this->assertNull($template->get2('volume'));
  }
    
  public function testRenameToArxivWhenLoseUrl() : void {
    $text = "{{cite web|url=1|arxiv=2}}";
    $template = $this->make_citation($text);
    $template->forget('url');
    $this->assertSame('cite arxiv', $template->wikiname());
    $text = "{{cite web|url=1|arxiv=2|chapter-url=XYX}}";
    $template = $this->make_citation($text);
    $template->forget('url');
    $this->assertSame('cite web', $template->wikiname());
  }
 
  public function testArxivMore1() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. Lett. 117, 211101 (2016)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2016', $expanded->get2('year'));
    $this->assertSame('211101', $expanded->get2('page'));
  }
    
  public function testArxivMore2() : void {
    $text = "{{cite arxiv}}" ;
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. B 79, 115202 (2009)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2009', $expanded->get2('year'));
    $this->assertSame('115202', $expanded->get2('page'));
  }
    
  public function testArxivMore3() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Acta Phys. Polon. B41 (2010), 2325-2333", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2010', $expanded->get2('year'));
    $this->assertSame('2325–2333', $expanded->get2('pages'));
  }
    
  public function testArxivMore4() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Phys. Rev. B 78, 245315 (2008)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2008', $expanded->get2('year'));
    $this->assertSame('245315', $expanded->get2('page'));
  }
    
  public function testArxivMore5() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal of Noses37:1234,2012", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2012', $expanded->get2('year'));
    $this->assertSame('1234', $expanded->get2('page'));
    $this->assertSame('37', $expanded->get2('volume'));
  }

  public function testArxivMore6() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("", $expanded, TRUE);  // Make sure that empty string does not crash
    $this->assertSame('cite arxiv', $expanded->wikiname());
  }
   
  public function testArxivMore7() : void {
    $text = "{{cite arxiv|date=1999}}"; // verify date update
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal 78 (2011) 888-999", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2011', $expanded->get2('year'));
    $this->assertSame('888–999', $expanded->get2('pages'));
  }

  public function testArxivMore8() : void {
    $text = "{{cite arxiv|year=1999}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal, 11, 62 (2001)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2001', $expanded->get2('year'));
    $this->assertSame('62', $expanded->get2('page'));
  }
    
  public function testArxivMore9() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal, 83:13232, 2018", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2018', $expanded->get2('year'));
    $this->assertSame('13232', $expanded->get2('page'));
  } 
  public function testArxivMore10() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Journal 1 (4), 2311 (1980)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('1980', $expanded->get2('year'));
    $this->assertSame('2311', $expanded->get2('page'));
  }
    
  public function testArxivMore11() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("ZooKeys 212 (1999), 032412332, 33 pages", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('1999', $expanded->get2('year'));
    $this->assertNull($expanded->get2('page'));  // reject 032412332 as too big
  }
 
  public function testArxivMore12() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("A&A 619, A49 (2018)", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2018', $expanded->get2('year'));
    $this->assertSame('Astronomy & Astrophysics', $expanded->get2('journal'));
    $this->assertSame('A49', $expanded->get2('volume'));
    $this->assertSame('619', $expanded->get2('page'));
  }
 
  public function testArxivMore13() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("ApJ, 767:L7, 2013 April 10", $expanded, TRUE);
    $this->assertSame('The Astrophysical Journal', $expanded->get2('journal'));
    $this->assertSame('2013', $expanded->get2('year'));
  }
 
  public function testArxivMore14() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Astrophys.J.639:L43-L46,2006F", $expanded, TRUE);
    $this->assertSame('The Astrophysical Journal', $expanded->get2('journal'));
    $this->assertSame('2006', $expanded->get2('year'));
  }

  public function testArxivMore15() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Information Processing Letters 115 (2015), pp. 633-634", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2015', $expanded->get2('year'));
    $this->assertSame('633–634', $expanded->get2('pages'));
  }

  public function testArxivMore16() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Theoretical Computer Science, Volume 561, Pages 113-121, 2015", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2015', $expanded->get2('year'));
    $this->assertSame('113–121', $expanded->get2('pages'));
  }

  public function testArxivMore17() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("Scientometrics, volume 69, number 3, pp. 669-687, 2006", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2006', $expanded->get2('year'));
    $this->assertSame('669–687', $expanded->get2('pages'));
  }

  public function testArxivMore18() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("International Journal of Geographical Information Science, 23(7), 2009, 823-837.", $expanded, TRUE);
    $this->assertSame('cite journal', $expanded->wikiname());
    $this->assertSame('2009', $expanded->get2('year'));
    $this->assertSame('823–837', $expanded->get2('pages'));
  }
  
  public function testArxivMore19() : void {
    $text = "{{cite arxiv}}";
    $expanded = $this->process_citation($text);
    parse_plain_text_reference("journal of Statistical Mechanics: Theory and Experiment, 2008 July", $expanded, TRUE);
    $this->assertSame('cite arxiv', $expanded->wikiname());
    $this->assertNull($expanded->get2('year'));
  }
 
   public function testDoiInline() : void {
    $text = '{{citation | title = {{doi-inline|10.1038/nature10000|Funky Paper}} }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Nature', $expanded->get2('journal'));
    $this->assertSame('Funky Paper', $expanded->get2('title'));
    $this->assertSame('10.1038/nature10000', $expanded->get2('doi'));
    
    $text = '{{citation | title = {{doi-inline|10.1038/nature10000|Funky Paper}} | doi=10.1038/nature10000 }}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Nature', $expanded->get2('journal'));
    $this->assertSame('Funky Paper', $expanded->get2('title'));
    $this->assertSame('10.1038/nature10000', $expanded->get2('doi'));
  } 
  
  public function testPagesDash1() : void {
    $text = '{{cite journal|pages=1-2|title=do change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1–2', $prepared->get2('pages'));
  }
 
  public function testPagesDash2() : void {
    $text = '{{cite journal|at=1-2|title=do not change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1-2', $prepared->get2('at'));
  }
 
  public function testPagesDash3() : void {
    $text = '{{cite journal|pages=[http://bogus.bogus/1–2/ 1–2]|title=do not change }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('[http://bogus.bogus/1–2/ 1–2]', $prepared->get2('pages'));
  }
 
  public function testPagesDash4() : void {
    $text = '{{Cite journal|pages=15|doi=10.1016/j.biocontrol.2014.06.004}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('15–22', $expanded->get2('pages')); // Converted should use long dashes
  }
 
  public function testPagesDash5() : void {
    $text = '{{Cite journal|doi=10.1007/s11746-998-0245-y|at=pp.425–439, see Table&nbsp;2 p.&nbsp;426 for tempering temperatures}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('pp.425–439, see Table&nbsp;2 p.&nbsp;426 for tempering temperatures', $expanded->get2('at')); // Leave complex at=
  }
 
  public function testPagesDash6() : void {
    $text = '{{cite book|pages=See [//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]}}';
    $expanded = $this->process_citation($text); // Do not change this hidden URL
    $this->assertSame('See [//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]', $expanded->get2('pages'));
  }
 
  public function testPagesDash7() : void {
    $text = '{{cite book|pages=[//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]}}';
    $expanded = $this->process_citation($text); // Do not change dashes in this hidden URL, but upgrade URL to real one
    $this->assertSame('[https://books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]', $expanded->get2('pages'));
  }
 
  public function testPagesDash8() : void {
    $text = '{{cite journal|pages=AB-2|title=do change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('AB-2', $prepared->get2('pages'));
  }
 
  public function testPagesDash9() : void {
    $text = '{{cite journal|page=1-2|title=do change}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1-2', $prepared->get2('page')); // With no change, but will give warning to user
  }
 
  public function testBogusPageRanges() : void {  // Just keep incrementing year when test ages out
    $text = '{{Cite journal| doi = 10.1017/jpa.2018.43|title = New well-preserved scleritomes of Chancelloriida from early Cambrian Guanshan Biota, eastern Yunnan, China|journal = Journal of Paleontology|volume = 92|issue = 6|pages = 1–17|year = 2020|last1 = Zhao|first1 = Jun|last2 = Li|first2 = Guo-Biao|last3 = Selden|first3 = Paul A}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('955–971', $expanded->get2('pages')); // Converted should use long dashes
    $text = '{{Cite journal| doi = 10.1017/jpa.2018.43|pages = 960}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('960', $expanded->get2('pages')); // Existing page number was within existing range
  }
    
  public function testCollapseRanges() : void {
    $text = '{{cite journal|pages=1233-1233|year=1999-1999}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1233', $prepared->get2('pages'));
    $this->assertSame('1999', $prepared->get2('year'));
  }
    
  public function testSmallWords() : void {
    $text = '{{cite journal|journal=A Word in ny and n y About cow And Then boys the U S A and y and z}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('A Word in NY and N Y About Cow and then Boys the U S A and y and Z', $prepared->get2('journal')); 
    $text = '{{cite journal|journal=Ann of Math}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Ann of Math', $prepared->get2('journal')); 
    $text = '{{cite journal|journal=Ann. of Math.}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Ann. of Math.', $prepared->get2('journal')); 
    $text = '{{cite journal|journal=Ann. of Math}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Ann. of Math', $prepared->get2('journal')); 
  }
    
  public function testDoNotAddYearIfDate() : void {
    $text = '{{cite journal|date=2002|doi=10.1635/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('year'));
  }
                         
  public function testAccessDates() : void {
    $text = '{{cite book |date=March 12, 1913 |title=Session Laws of the State of Washington, 1913 |chapter=Chapter 65: Classifying Public Highways |page=221 |chapter-url=http://leg.wa.gov/CodeReviser/documents/sessionlaw/1913c65.pdf |publisher=Washington State Legislature |accessdate=August 30, 2018}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('access-date'));
    $text = '{{cite book |date=March 12, 1913 |title=Session Laws of the State of Washington, 1913 |chapter=Chapter 65: Classifying Public Highways |page=221 |chapterurl=http://leg.wa.gov/CodeReviser/documents/sessionlaw/1913c65.pdf |publisher=Washington State Legislature |accessdate=August 30, 2018}}';
    $expanded = $this->process_citation($text);
    $this->assertNotNull($expanded->get2('access-date'));
  }

  public function testIgnoreUnkownCiteTemplates() : void {
    $text = "{{Cite imaginary source | http://google.com | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6|doi=10.bad/bad }}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
  
  public function testJustAnISBN() : void {
     $text = '{{cite book |isbn=0471186368}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('explosives engineering', strtolower($expanded->get('title')));
     $this->assertNull($expanded->get2('url'));
  }
 
  public function testArxivPDf() : void {
   $this->requires_arxiv(function() : void {
    $text = '{{cite web|url=https://arxiv.org/ftp/arxiv/papers/1312/1312.7288.pdf}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('1312.7288', $expanded->get2('arxiv'));
   });   
  }
  
  public function testEmptyCitations() : void {
    $text = 'bad things like {{cite journal}}{{cite book|||}}{{cite arxiv}}{{cite web}} should not crash bot'; // bot removed pipes
    $expanded = $this->process_page($text);
    $this->assertSame('bad things like {{cite journal}}{{cite book}}{{cite arxiv}}{{cite web}} should not crash bot', $expanded->parsed_text());
  }

  public function testLatexMathInTitle() : void { // This contains Math stuff that should be z~10, but we just verify that we do not make it worse at this time.  See https://tex.stackexchange.com/questions/55701/how-do-i-write-sim-approximately-with-the-correct-spacing
   $this->requires_arxiv(function() : void {
    $text = "{{Cite arxiv|eprint=1801.03103}}";
    $expanded = $this->process_citation($text);
    $title = $expanded->get2('title');
    // For some reason we sometimes get the first one
    $title1 = 'A Candidate $z\sim10$ Galaxy Strongly Lensed into a Spatially Resolved Arc';
    $title2 = 'RELICS: A Candidate z ∼ 10 Galaxy Strongly Lensed into a Spatially Resolved Arc';
    if (in_array($title, [$title1, $title2])) {
       $this->assertTrue(TRUE);
    } else {
       $this->assertTrue($title); // What did we get
    }
   });
  }

  public function testDropGoogleWebsite() : void {
    $text = "{{Cite book|website=Google.Com|url=http://Invalid.url.not-real.com/}}"; // Include a fake URL so that we are not testing: if (no url) then drop website
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('website'));
  }

  public function testHornorificInTitle() : void { // compaints about this
    $text = "{{cite book|title=Letter from Sir Frederick Trench to the Viscount Duncannon on his proposal for a quay on the north bank of the Thames|url=https://books.google.com/books?id=oNBbAAAAQAAJ|year=1841}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('Trench', $expanded->get2('last1'));
    $this->assertSame('Frederick William', $expanded->get2('first1')); 
  }

  public function testPageRange() : void {
    $text = '{{Citation|doi=10.3406/befeo.1954.5607}}' ;
    $expanded = $this->process_citation($text);
    $this->assertSame('405–554', $expanded->get2('pages'));
  }

  public function testUrlConversions() : void {
    $text = '{{cite journal | url= https://mathscinet.ams.org/mathscinet-getitem?mr=0012343 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('0012343', $prepared->get2('mr'));
    $this->assertNotNull($prepared->get2('url'));
  }
  public function testUrlConversionsA() : void {
    $text = '{{cite journal | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234231}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1234231', $prepared->get2('ssrn'));
    $this->assertNotNull($prepared->get2('url'));
  }
  public function testUrlConversionsB() : void {
    $text = '{{cite journal | url=https://www.osti.gov/biblio/2341}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('2341', $prepared->get2('osti'));
    $this->assertNotNull($prepared->get2('url'));
  }
  public function testUrlConversionsC() : void {
    $text = '{{cite journal | url=https://www.osti.gov/energycitations/product.biblio.jsp?osti_id=2341}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('2341', $prepared->get2('osti'));
    $this->assertNotNull($prepared->get2('url'));
  }
  public function testUrlConversionsD() : void {
    $text = '{{cite journal | url=https://zbmath.org/?format=complete&q=an:1111.22222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1111.22222', $prepared->get2('zbl'));
    $this->assertNotNull($prepared->get2('url'));
  }
  public function testUrlConversionsE() : void {
    $text = '{{cite journal | url=https://zbmath.org/?format=complete&q=an:11.2222.44}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('11.2222.44', $prepared->get2('jfm'));
    $this->assertNotNull($prepared->get2('url'));
  }
  public function testUrlConversionsF() : void {
    $text = '{{cite journal |url=http://citeseerx.ist.psu.edu/viewdoc/download?doi=10.1.1.923.345&rep=rep1&type=pdf}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('10.1.1.923.345', $prepared->get2('citeseerx'));
    $text = '{{cite journal |url=http://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.923.345}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('10.1.1.923.345', $prepared->get2('citeseerx'));
  }
  public function testUrlConversionsG() : void {
    $text = '{{cite journal | archiveurl= https://mathscinet.ams.org/mathscinet-getitem?mr=0012343 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('0012343', $prepared->get2('mr'));
    $this->assertNull($prepared->get2('archiveurl'));
  }
    
  public function testStripPDF() : void {
    $text = '{{cite journal |url=https://link.springer.com/content/pdf/10.1007/BF00428580.pdf}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('10.1007/BF00428580', $prepared->get2('doi'));
  }
    
  public function testRemoveQuotes() : void {
    $text = '{{cite journal|title="Strategic Acupuncture"}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Strategic Acupuncture', $prepared->get2('title'));  
  }
  
  public function testTrimResearchGate() : void {
    $want = 'https://www.researchgate.net/publication/320041870';
    $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame($want, $prepared->get2('url'));
    $text = '{{cite journal|url=https://www.researchgate.net/profile/hello_user-person/publication/320041870_EXTRA_STUFF_ON_EN}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame($want, $prepared->get2('url'));
  }

  public function testTrimAcedamiaEdu() : void {
    $text = '{{cite web|url=http://acADemia.EDU/123456/extra_stuff}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('https://www.academia.edu/123456', $prepared->get2('url'));
  }
 
  public function testTrimProquestEbook() : void {
    $text = '{{cite web|url=https://ebookcentral.proquest.com/lib/claremont/detail.action?docID=123456}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=123456', $prepared->get2('url'));

    $text = '{{cite web|url=https://ebookcentral.proquest.com/lib/claremont/detail.action?docID=123456#}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=123456', $prepared->get2('url'));
   
    $text = '{{cite web|url=https://ebookcentral.proquest.com/lib/claremont/detail.action?docID=123456&query=&ppg=35#}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=123456&query=&ppg=35', $prepared->get2('url'));
   
    $text = '{{cite web|url=http://ebookcentral-proquest-com.libproxy.berkeley.edu/lib/claremont/detail.action?docID=123456#goto_toc}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=123456', $prepared->get2('url'));
  }

  public function testTrimGoogleStuff() : void {
    $text = '{{cite web|url=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8#The_hash#The_second_hash}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22#The_hash', $prepared->get2('url'));
  }
 
  public function testCleanRGTitles() : void {
    $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup|title=Hello {{!}} Request PDF}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Hello', $prepared->get2('title'));
    $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup|title=(PDF) Hello}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Hello', $prepared->get2('title'));
  }
 
  public function testHTMLNotLost() : void {
    $text = '{{cite journal|last1=&ndash;|first1=&ndash;|title=&ndash;|journal=&ndash;|edition=&ndash;|pages=&ndash;}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame($text, $prepared->parsed_text());
  }
 
  public function testTidyBookEdition() : void {
    $text = '{{cite book|title=Joe Blow (First Edition)}}';
    $template = $this->make_citation($text);
    $template->tidy_parameter('title');
    $this->assertSame('First', $template->get2('edition'));
    $this->assertSame('Joe Blow', $template->get2('title'));
  }
 
  public function testDoiValidation() : void {
    $text = '{{cite web|last=Daintith|first=John|title=tar|url=http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022|work=Oxford University Press|publisher=A dictionary of chemistry|edition=6th|accessdate=14 March 2013}}';
    $prepared = $this->prepare_citation($text);
    $this->assertNull($prepared->get2('doi'));
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('doi'));
  }
    
  public function testVolumeIssueDemixing1() : void {
    $text = '{{cite journal|volume = 12(44)}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('44', $prepared->get2('issue'));
    $this->assertSame('12', $prepared->get2('volume'));
  }

  public function testVolumeIssueDemixing2() : void {
    $text = '{{cite journal|volume = 12(44-33)}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('44–33', $prepared->get2('issue'));
    $this->assertSame('12', $prepared->get2('volume'));
  }

  public function testVolumeIssueDemixing3() : void {
    $text = '{{cite journal|volume = 12(44-33)| number=222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('222', $prepared->get2('number'));
    $this->assertSame('12(44-33)', $prepared->get2('volume'));
  }

  public function testVolumeIssueDemixing4() : void {
    $text = '{{cite journal|volume = 12, no. 44-33}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('44–33', $prepared->get2('issue'));
    $this->assertSame('12', $prepared->get2('volume'));
  }

  public function testVolumeIssueDemixing5() : void {
    $text = '{{cite journal|volume = 12, no. 44-33| number=222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('222', $prepared->get2('number'));
    $this->assertSame('12, no. 44-33', $prepared->get2('volume'));
  }

  public function testVolumeIssueDemixing6() : void {
    $text = '{{cite journal|volume = 12.33}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('33', $prepared->get2('issue'));
    $this->assertSame('12', $prepared->get2('volume'));
  }

  public function testVolumeIssueDemixing7() : void {
    $text = '{{cite journal|volume = 12.33| number=222}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('222', $prepared->get2('number'));
    $this->assertSame('12.33', $prepared->get2('volume'));
  }

  public function testVolumeIssueDemixing8() : void {
    $text = '{{cite journal|volume = Volume 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get2('volume'));
  }

  public function testVolumeIssueDemixing9() : void {
    $text = '{{cite book|volume = Volume 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('Volume 12', $prepared->get2('volume'));
  }
 
   public function testVolumeIssueDemixing10() : void {
    $text = '{{cite journal|volume = number 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('number 12', $prepared->get2('volume'));
    $this->assertNull($prepared->get2('issue'));
  }
 
   public function testVolumeIssueDemixing11() : void {
    $text = '{{cite journal|volume = number 12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get2('issue'));
    $this->assertNull($prepared->get2('volume'));
  }
 
   public function testVolumeIssueDemixing12() : void {
    $text = '{{cite journal|volume = number 12|issue=12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertNull($prepared->get2('volume'));
    $this->assertSame('12', $prepared->get2('issue'));
  }
 
  public function testVolumeIssueDemixing13() : void {
    $text = '{{cite journal|volume = number 12|issue=12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertNull($prepared->get2('volume'));
    $this->assertSame('12', $prepared->get2('issue'));
  }
 
   public function testVolumeIssueDemixing14() : void {
    $text = '{{cite journal|issue = number 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get2('issue'));
  }
 
   public function testVolumeIssueDemixing15() : void {
    $text = '{{cite journal|volume = v. 12}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get2('volume'));
  }
 
   public function testVolumeIssueDemixing16() : void {
    $text = '{{cite journal|issue =(12)}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get2('issue'));
  }
 
   public function testVolumeIssueDemixing17() : void {
    $text = '{{cite journal|issue = volume 8, issue 7}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('7', $prepared->get2('issue'));
    $this->assertSame('8', $prepared->get2('volume'));
  }
 
   public function testVolumeIssueDemixing18() : void {
    $text = '{{cite journal|issue = volume 8, issue 7|volume=8}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('7', $prepared->get2('issue'));
    $this->assertSame('8', $prepared->get2('volume'));
  }
 
   public function testVolumeIssueDemixing19() : void {
    $text = '{{cite journal|issue = volume 8, issue 7|volume=9}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('volume 8, issue 7', $prepared->get2('issue'));
    $this->assertSame('9', $prepared->get2('volume'));
  }

   public function testVolumeIssueDemixing20() : void {
    $text = '{{cite journal|issue = number 333XV }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('333XV', $prepared->get2('issue'));
    $this->assertNull($prepared->get2('volume'));
  }
 
  public function testCleanUpPages() : void {
    $text = '{{cite journal|pages=p.p. 20-23}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('20–23', $prepared->get2('pages')); // Drop p.p. and upgraded dashes
  }
 
  public function testSpaces() : void {
      // None of the "spaces" in $text are normal spaces.  They are U+2000 to U+200A
      $text     = "{{cite book|title=X X X X X X X X X X X X}}";
      $text_out = '{{cite book|title=X X X X X X X X X X X X}}';
      $expanded = $this->process_citation($text);
      $this->assertSame($text_out, $expanded->parsed_text());
      $this->assertTrue($text != $text_out); // Verify test is valid -- We want to make sure that the spaces in $text are not normal spaces
  }
 
  public function testMultipleYears() : void {
    $text = '{{cite journal|doi=10.1080/1323238x.2006.11910818}}'; // Crossref has <year media_type="online">2017</year><year media_type="print">2006</year>
    $expanded = $this->process_citation($text);
    $this->assertSame('2006', $expanded->get2('year'));
  }
 
  public function testDuplicateParametersFlagging() : void {
    $text = '{{cite web|year=2010|year=2011}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('2011', $expanded->get2('year'));
    $this->assertSame('2010', $expanded->get2('DUPLICATE_year'));
    $text = '{{cite web|year=|year=2011}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('2011', $expanded->get2('year'));
    $this->assertNull($expanded->get2('DUPLICATE_year'));
    $text = '{{cite web|year=2011|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('2011', $expanded->get2('year'));
    $this->assertNull($expanded->get2('DUPLICATE_year'));
    $text = '{{cite web|year=|year=|year=2011|year=|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('2011', $expanded->get2('year'));
    $this->assertNull($expanded->get2('DUPLICATE_year'));
    $text = '{{cite web|year=|year=|year=|year=|year=}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('{{cite web|year=}}', $expanded->parsed_text());
  }
 
  public function testBadPMIDSearch() : void { // Matches a PMID search
    $text = '{{cite journal |author=Fleming L |title=Ciguatera Fish Poisoning}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('pmid'));
    $this->assertNull($expanded->get2('doi'));
  }
 
  public function testDoiThatIsJustAnISSN() : void {
    $text = '{{cite web |url=http://onlinelibrary.wiley.com/journal/10.1002/(ISSN)1099-0739/homepage/EditorialBoard.html}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('10.1002/(ISSN)1099-0739', $expanded->get2('doi'));
    $this->assertSame('http://onlinelibrary.wiley.com/journal/10.1002/(ISSN)1099-0739/homepage/EditorialBoard.html', $expanded->get2('url'));
    $this->assertSame('cite web', $expanded->wikiname());
  }
 
  public function testEditors() : void {
    $text = '{{cite journal|editor3=Set}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('editor3-last', 'SetItL');
    $prepared->add_if_new('editor3-first', 'SetItF');
    $prepared->add_if_new('editor3', 'SetItN');
    $this->assertSame('Set', $prepared->get2('editor3'));
    $this->assertNull($prepared->get2('editor3-last'));
    $this->assertNull($prepared->get2('editor3-first'));
   
    $text = '{{cite journal}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('editor3-last', 'SetItL');
    $prepared->add_if_new('editor3-first', 'SetItF');
    $prepared->add_if_new('editor3', 'SetItN'); // Should not get set
    $this->assertSame('SetItL', $prepared->get2('editor3-last'));
    $this->assertSame('SetItF', $prepared->get2('editor3-first'));
    $this->assertNull($prepared->get2('editor3'));
   
    $text = '{{cite journal}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('editor33333-last', 'SetIt'); // Huge number
    $this->assertSame('SetIt', $prepared->get2('editor33333-last'));
  }
 
  public function testAddPages() : void {
    $text = '{{Cite journal|pages=1234-9}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('pages', '1230-1240');
    $this->assertSame('1234–9', $prepared->get2('pages'));
  }
 
   public function testAddPages2() : void {
    $text = '{{Cite journal|pages=1234-44}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('pages', '1230-1270');
    $this->assertSame('1234–44', $prepared->get2('pages'));
  }
 
  public function testAddPages3() : void {
    $text = '{{Cite journal|page=1234}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('pages', '1230-1270');
    $this->assertSame('1234', $prepared->get2('page'));
  }
 
  public function testAddPages4() : void {
    $text = '{{Cite journal|page=1234}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('pages', '1230-70');
    $this->assertSame('1234', $prepared->get2('page'));
  }

  public function testAddPages5() : void {
    $text = '{{Cite journal|page=1234}}';
    $prepared = $this->prepare_citation($text);
    $prepared->add_if_new('pages', '1230-9');
    $this->assertSame('1234', $prepared->get2('page'));
  }
 
  public function testAddBibcode() : void {
    $text = '{{Cite journal|bibcode=1arxiv1}}';
    $prepared = $this->make_citation($text);
    $prepared->add_if_new('bibcode_nosearch', '1234567890123456789');
    $this->assertSame('1234567890123456789', $prepared->get2('bibcode'));
  }
 
  public function testAddBibcode2() : void {
    $text = '{{Cite journal}}';
    $prepared = $this->make_citation($text);
    $prepared->add_if_new('bibcode_nosearch', '1arxiv1');
    $this->assertNull($prepared->get2('bibcode'));
  }
 
   public function testEdition() : void {
    $text = '{{Cite journal}}';
    $prepared = $this->prepare_citation($text);
    $this->assertTrue($prepared->add_if_new('edition', '1'));
    $this->assertSame('1', $prepared->get2('edition'));
    $this->assertFalse($prepared->add_if_new('edition', '2'));
    $this->assertSame('1', $prepared->get2('edition')); 
  }
 
  public function testFixRubbishVolumeWithDoi() : void {
    $text = '{{Cite journal|doi= 10.1136/bmj.2.3798.759-a |volume=3798 |issue=3798}}';
    $template = $this->prepare_citation($text);
    $template->final_tidy(); 
    $this->assertSame('3798', $template->get2('issue'));
    $this->assertSame('2', $template->get2('volume'));
  }
  
  public function testHandles1() : void {
    $template = $this->make_citation('{{Cite web|url=http://hdl.handle.net/10125/20269////|journal=X}}');
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('10125/20269', $template->get2('hdl'));
    $this->assertSame('cite web', $template->wikiname());
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testHandles2() : void {
    $template = $this->make_citation('{{Cite web|url=https://hdl.handle.net/handle////10125/20269}}');
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('cite web', $template->wikiname());
    $this->assertSame('10125/20269', $template->get2('hdl'));
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testHandles3() : void {
    $template = $this->make_citation('{{Cite journal|url=http://hdl.handle.net/handle/10125/dfsjladsflhdsfaewfsdfjhasjdfhldsaflkdshkafjhsdjkfhdaskljfhdsjklfahsdafjkldashafldsfhjdsa_TEST_DATA_FOR_BOT_TO_FAIL_ON}}');
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('http://hdl.handle.net/handle/10125/dfsjladsflhdsfaewfsdfjhasjdfhldsaflkdshkafjhsdjkfhdaskljfhdsjklfahsdafjkldashafldsfhjdsa_TEST_DATA_FOR_BOT_TO_FAIL_ON', $template->get2('url'));
    $this->assertNull($template->get2('hdl'));
  }
 
  public function testHandles4() : void {
    $template = $this->make_citation('{{Cite journal|url=http://digitallibrary.amnh.org/dataset.xhtml?persistentId=hdl:10125/20269;jsessionid=EE3BA49390611FCE0AAAEBB819E777BC?sequence=1}}');
    $template->get_identifiers_from_url();
    $this->assertSame('10125/20269', $template->get2('hdl'));
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testHandles5() : void {
    $template = $this->make_citation('{{Cite journal|url=http://hdl.handle.net/2027/loc.ark:/13960/t6349vh5n?urlappend=%3Bseq=672}}');
    $template->get_identifiers_from_url();
    $this->assertSame('2027/loc.ark:/13960/t6349vh5n?urlappend=%3Bseq=672', $template->get2('hdl'));
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testAuthorToLast() : void {
    $text = '{{Cite journal|author1=Last|first1=First}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('Last', $template->get2('last1'));
    $this->assertSame('First', $template->get2('first1'));
    $this->assertNull($template->get2('author1'));
   
    $text = '{{Cite journal|author1=Last|first2=First}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('Last', $template->get2('author1'));
    $this->assertSame('First', $template->get2('first2'));
    $this->assertNull($template->get2('last1'));
  }
 
  public function testAddArchiveDate() : void {
    $text = '{{Cite web|archive-url=https://web.archive.org/web/20190521084631/https://johncarlosbaez.wordpress.com/2018/09/20/patterns-that-eventually-fail/|archive-date=}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('2019-05-21', $template->get2('archive-date'));
   
    $text = '{{Cite web|archive-url=https://wayback.archive-it.org/4554/20190521084631/https://johncarlosbaez.wordpress.com/2018/09/20/patterns-that-eventually-fail/|archive-date=}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('2019-05-21', $template->get2('archive-date'));
  }
 
  public function testAddWebCiteDate() : void {
    $text = '{{Cite web|archive-url=https://www.webcitation.org/6klgx4ZPE}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('2016-09-24', $template->get2('archive-date'));
  }
 
  public function testJunkData() : void {
    $text = "{{Cite web | title=JSTOR THIS IS A LONG TITLE IN ALL CAPPS AND IT IS BAD|pmid=1974135}} " . 
            "{{Cite web | title=JSTOR This is bad data|journal=JSTOR This is bad data|jstor=1974136}}" .
            "{{Cite web | title=JSTOR This is a title on JSTOR|pmc=1974137}}" .
            "{{Cite web | title=JSTOR This is a title with IEEE Xplore Document|pmid=1974138}}" .
            "{{Cite web | title=IEEE Xplore This is a title with Document|pmid=1974138}}" .
            "{{Cite web | title=JSTOR This is a title document with Volume 3 and page 5|doi= 10.1021/jp101758y}}";
    $page = $this->process_page($text);
    $this->assertSame(0, substr_count($page->parsed_text(), 'JSTOR'));
  }

  public function testJunkData2() : void {
    $text = "{{cite journal|doi=10.1016/j.bbagen.2019.129466|journal=Biochimica Et Biophysica Acta|title=Shibboleth Authentication Request}}";
    $template = $this->process_citation($text);
    $this->assertSame('Biochimica et Biophysica Acta (BBA) - General Subjects', $template->get2('journal'));
    $this->assertSame('Time-resolved studies of metalloproteins using X-ray free electron laser radiation at SACLA', $template->get2('title'));
  }
 
  public function testISSN(){
    $text = '{{Cite journal|journal=Yes}}';
    $template = $this->prepare_citation($text);
    $template->add_if_new('issn', '1111-2222');
    $this->assertNull($template->get2('issn'));
    $template->add_if_new('issn_force', '1111-2222');
    $this->assertSame('1111-2222', $template->get2('issn'));
    $text = '{{Cite journal|journal=Yes}}';
    $template = $this->prepare_citation($text);
    $template->add_if_new('issn_force', 'EEEE-3333'); // Won't happen
    $this->assertNull($template->get2('issn'));
  }
 
  public function testURLS() : void {
    $text='{{cite journal|conference-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get2('mr'));
    $text='{{cite journal|conferenceurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get2('mr'));                
    $text='{{cite journal|contribution-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get2('mr'));
    $text='{{cite journal|contributionurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get2('mr'));
    $text='{{cite journal|article-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
    $template = $this->prepare_citation($text);
    $this->assertSame('1234', $template->get2('mr'));
  }
 
  public function testTidy1() : void {
    $text = '{{cite web|postscript = <!-- A comment only --> }}';
    $template = $this->process_citation($text);
    $this->assertNull($template->get2('postscript'));
  }
 
  public function testTidy1a() : void {
    $text = '{{cite web|postscript = <!-- A comment only --> {{Some Template}} }}';
    $template = $this->process_citation($text);
    $this->assertNull($template->get2('postscript'));
  }
 
  public function testTidy2() : void {
    $text = '{{citation|issue="Something Special"}}';
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertSame('Something Special', $template->get2('issue'));
  }
 
  public function testTidy3() : void {
    $text = "{{citation|issue=Dog \t\n\r\0\x0B }}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertSame('Dog', $template->get2('issue'));
  }

   public function testTidy4() : void {
    $text = "{{citation|issue=Dog &nbsp;}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertSame('Dog', $template->get2('issue'));
  }
 
  public function testTidy5() : void {
    $text = '{{citation|issue=&nbsp; Dog }}';
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertSame('Dog', $template->get2('issue'));
  }
 
  public function testTidy5b() : void {
    $text = "{{citation|agency=California Department of Public Health|publisher=California Tobacco Control Program}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('agency');
    $this->assertSame('California Department of Public Health', $template->get2('publisher'));
    $this->assertNull($template->get2('agency'));
  }

  public function testTidy6() : void {
    $text = "{{cite web|arxiv=xxxxxxxxxxxx}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('arxiv');
    $this->assertSame('cite arxiv', $template->wikiname());
  }
 
  public function testTidy6b() : void {
    $text = "{{cite web|author=X|authors=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('author');
    $this->assertSame('X', $template->get2('DUPLICATE_authors'));
  }

  public function testTidy7() : void {
    $text = "{{cite web|author1=[[Hoser|Yoser]]}}";  // No longer do this, COINS now fixed
    $template = $this->make_citation($text);
    $template->tidy_parameter('author1');
    $this->assertSame('[[Hoser|Yoser]]', $template->get2('author1'));
    $this->assertNull($template->get2('author1-link'));
  }

  public function testTidy8() : void {
    $text = "{{cite web|bibcode=abookthisis}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('bibcode');
    $this->assertSame('cite book', $template->wikiname());
  }

  public function testTidy9() : void {
    $text = "{{cite web|title=XXX|chapter=XXX}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter');
    $this->assertNull($template->get2('chapter'));
  }

  public function testTidy10() : void {
    $text = "{{cite web|doi=10.1267/science.040579197}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('doi');
    $this->assertNull($template->get2('doi'));
  }

  public function testTidy11() : void {
    $text = "{{cite web|doi=10.5284/1000184}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('doi');
    $this->assertNull($template->get2('doi'));
  }

  public function testTidy12() : void {
    $text = "{{cite web|doi=10.5555/TEST_DATA}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('doi');
    $this->assertNull($template->get2('doi'));
    $this->assertNull($template->get2('url'));
  }

  public function testTidy13() : void {
    $text = "{{cite web|format=Accepted manuscript}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('format');
    $this->assertNull($template->get2('format'));
  }

  public function testTidy14() : void {
    $text = "{{cite web|format=Submitted manuscript}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('format');
    $this->assertNull($template->get2('format'));
  }
 
  public function testTidy15() : void {
    $text = "{{cite web|format=Full text}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('format');
    $this->assertNull($template->get2('format'));
  }           
           
  public function testTidy16() : void {
    $text = "{{cite web|chapter-format=Accepted manuscript}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get2('chapter-format'));
  }

  public function testTidy17() : void {
    $text = "{{cite web|chapter-format=Submitted manuscript}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get2('chapter-format'));
  }
 
  public function testTidy18() : void {
    $text = "{{cite web|chapter-format=Full text}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get2('chapter-format'));
  }
  
  public function testTidy19() : void {
    $text = "{{cite web|chapter-format=portable document format|chapter-url=http://www.x.com/stuff.pdf}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get2('chapter-format'));
    $text = "{{cite web|format=portable document format|url=http://www.x.com/stuff.pdf}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('format');
    $this->assertNull($template->get2('format'));
  }
           
  public function testTidy20() : void {
    $text = "{{cite web|chapter-format=portable document format|chapterurl=http://www.x.com/stuff.pdf}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get2('chapter-format'));
  }
 
  public function testTidy21() : void {
    $text = "{{cite web|chapter-format=portable document format}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('chapter-format');
    $this->assertNull($template->get2('chapter-format'));
  }

  public function testTidy22() : void {
    $text = "{{cite web|periodical=X,}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('periodical');
    $this->assertSame('X', $template->get2('periodical'));
  }
           
  public function testTidy23() : void {
    $text = "{{cite journal|magazine=Xyz}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('magazine');
    $this->assertSame('Xyz', $template->get2('journal'));
  }
       
  public function testTidy24() : void {
    $text = "{{cite journal|others=|day=|month=}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('others');
    $template->tidy_parameter('day');
    $template->tidy_parameter('month');
    $this->assertSame('{{cite journal}}', $template->parsed_text());
  }
         
  public function testTidy25() : void {
    $text = "{{cite journal|archivedate=X|archive-date=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archivedate');
    $this->assertNull($template->get2('archivedate'));
  }
 
  public function testTidy26() : void {
    $text = "{{cite journal|newspaper=X|publisher=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
  }
               
  public function testTidy27() : void {
    $text = "{{cite journal|publisher=Proquest|thesisurl=proquest}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('ProQuest', $template->get2('via'));
  }

  public function testTidy28() : void {
    $text = "{{cite journal|url=stuff.maps.google.stuff|publisher=something from google land}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Google Maps', $template->get2('publisher'));
  }

  public function testTidy29() : void {
    $text = "{{cite journal|journal=X|publisher=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
  }

  public function testTidy30() : void {
    $text = "{{cite journal|series=Methods of Molecular Biology|journal=biomaas}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('series');
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('biomaas', $template->get2('journal'));
  }
           
  public function testTidy31() : void {
    $text = "{{cite journal|series=Methods of Molecular Biology|journal=Methods of Molecular Biology}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('series');
    $this->assertSame('cite book', $template->wikiname());
    $this->assertNull($template->get2('journal'));
  }

  public function testTidy32() : void {
    $text = "{{cite journal|title=A title (PDF)|pmc=1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('title');
    $this->assertSame('A title', $template->get2('title'));
  }
                      
  public function testTidy34() : void {
    $text = "{{cite journal|archive-url=http://web.archive.org/web/save/some_website}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archive-url');
    $this->assertNull($template->get2('archive-url'));
  }
      
  public function testTidy35() : void {
    $text = "{{cite journal|archive-url=XYZ|url=XYZ}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archive-url');
    $this->assertNull($template->get2('archive-url'));
  }
    
  public function testTidy36() : void {
    $text = "{{cite journal|series=|periodical=Methods of Molecular Biology}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('periodical');
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Methods of Molecular Biology', $template->get2('series'));
  }
            
  public function testTidy37() : void {
    $text = "{{cite journal|series=Methods of Molecular Biology|periodical=Methods of Molecular Biology}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('periodical');
    $this->assertSame('cite book', $template->wikiname());
    $this->assertSame('Methods of Molecular Biology', $template->get2('series'));
    $this->assertNull($template->get2('periodical'));
  } 

  public function testTidy38() : void {
    $text = "{{cite journal|archiveurl=http://researchgate.net/publication/1234_feasdfafdsfsd|title=(PDF) abc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://www.researchgate.net/publication/1234', $template->get2('archiveurl'));
    $this->assertSame('abc', $template->get2('title'));
  }

  public function testTidy39() : void {
    $text = "{{cite journal|archiveurl=http://academia.edu/documents/1234_feasdfafdsfsd}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://www.academia.edu/1234', $template->get2('archiveurl'));
  }
 
  public function testTidy40() : void {
    $text = "{{cite journal|archiveurl=https://zenodo.org/record/1234/files/dsafsd}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://zenodo.org/record/1234', $template->get2('archiveurl'));
  }

  public function testTidy42() : void {
    $text = "{{cite journal|archiveurl=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8&oe=Bogus&rct=ABC}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oe=Bogus&rct=ABC', $template->get2('archiveurl'));
  }
 
  public function testTidy43() : void {
    $text = "{{cite journal|archiveurl=https://sciencedirect.com/stuff_stuff?via=more_stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://sciencedirect.com/stuff_stuff', $template->get2('archiveurl'));
  }
 
  public function testTidy44() : void {
    $text = "{{cite journal|archiveurl=https://bloomberg.com/stuff_stuff?utm_=more_stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertSame('https://bloomberg.com/stuff_stuff', $template->get2('archiveurl'));
  }

  public function testTidy45() : void {
    $text = "{{cite journal|url=http://researchgate.net/publication/1234_feasdfafdsfsd|title=(PDF) abc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.researchgate.net/publication/1234', $template->get2('url'));
    $this->assertSame('abc', $template->get2('title'));
  }

  public function testTidy46() : void {
    $text = "{{cite journal|url=http://academia.edu/documents/1234_feasdfafdsfsd}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.academia.edu/1234', $template->get2('url'));
  }
 
  public function testTidy47() : void {
    $text = "{{cite journal|url=https://zenodo.org/record/1234/files/dfasd}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://zenodo.org/record/1234', $template->get2('url'));
  }

  public function testTidy48() : void {
    $text = "{{cite journal|url=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22', $template->get2('url'));
  }
 
  public function testTidy49() : void {
    $text = "{{cite journal|url=https://sciencedirect.com/stuff_stuff?via=more_stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://sciencedirect.com/stuff_stuff', $template->get2('url'));
  }
 
  public function testTidy50() : void {
    $text = "{{cite journal|url=https://bloomberg.com/stuff_stuff?utm_=more_stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://bloomberg.com/stuff_stuff', $template->get2('url'));
  }
    
  public function testTidy51() : void {
    $text = "{{cite journal|url=https://watermark.silverchair.com/rubbish}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertNull($template->get2('url'));
  }

  public function testTidy52() : void {
    $text = "{{cite journal|url=https://watermark.silverchair.com/rubbish|archiveurl=has_one}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://watermark.silverchair.com/rubbish', $template->get2('url'));
  }
 
  public function testTidy53() : void {
    $text = "{{cite journal|archiveurl=https://watermark.silverchair.com/rubbish}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertNull($template->get2('archiveurl'));
  }
 
  public function testTidy53b() : void {
    $text = "{{cite journal|url=https://s3.amazonaws.com/academia.edu/stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertNull($template->get2('url'));
  }
 
  public function testTidy53c() : void {
    $text = "{{cite journal|archiveurl=https://s3.amazonaws.com/academia.edu/stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archiveurl');
    $this->assertNull($template->get2('archiveurl'));
  }
 
  public function testTidy54() : void {
    $text = "{{cite journal|url=https://ieeexplore.ieee.org.proxy/document/1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://ieeexplore.ieee.org/document/1234', $template->get2('url'));
  }
 
  public function testTidy54b() : void {
    $text = "{{cite journal|url=https://ieeexplore.ieee.org.proxy/iel5/232/32/123456.pdf?yup}}";
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url();
    $this->assertSame('https://ieeexplore.ieee.org/document/123456', $template->get2('url'));
  }
 
  public function testTidy55() : void {
    $text = "{{cite journal|url=https://www.oxfordhandbooks.com.proxy/view/1234|via=Library}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.oxfordhandbooks.com/view/1234', $template->get2('url'));
    $this->assertNull($template->get2('via'));
  }
 
  public function testTidy55B() : void { // Do not drop AU
    $text = "{{cite news|title=Misogynist rants from Young Libs|url=http://www.theage.com.au/victoria/misogynist-rants-from-young-libs-20140809-3dfhw.html|accessdate=10 August 2014|newspaper=[[The Age]]|date=10 August 2014|agency=[[Fairfax Media]]}}";
    $template = $this->process_citation($text);
    $this->assertSame('http://www.theage.com.au/victoria/misogynist-rants-from-young-libs-20140809-3dfhw.html', $template->get2('url'));
  }

  public function testTidy56() : void {
    $text = "{{cite journal|url=https://www.oxfordartonline.com.proxy/view/1234|via=me}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.oxfordartonline.com/view/1234', $template->get2('url'));
    $this->assertSame('me', $template->get2('via'));
  }

  public function testTidy57() : void {
    $text = "{{cite journal|url=https://sciencedirect.com.proxy/stuff_stuff|via=the via}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.sciencedirect.com/stuff_stuff', $template->get2('url'));
  }
 
  public function testTidy58() : void {
    $text = "{{cite journal|url=https://www.random.com.mutex.gmu/stuff_stuff|via=the via}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.random.com/stuff_stuff', $template->get2('url'));
    $this->assertNull($template->get2('via'));
  }
 
  public function testTidy59() : void {
    $text = "{{cite journal|url=https://www-random-com.mutex.gmu/stuff_stuff|via=the via}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.random.com/stuff_stuff', $template->get2('url'));
    $this->assertNull($template->get2('via'));
  }

  public function testTidy60() : void {
    $text = "{{cite journal|url=http://proxy/url=https://go.galegroup.com%2fpsSTUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://go.galegroup.com/psSTUFF', $template->get2('url'));
  }
 
  public function testTidy61() : void {
    $text = "{{cite journal|url=http://proxy/url=https://go.galegroup.com/STUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://go.galegroup.com/STUFF', $template->get2('url'));
  }

  public function testTidy62() : void {
    $text = "{{cite journal|url=http://proxy/url=https://link.galegroup.com%2fpsSTUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://link.galegroup.com/psSTUFF', $template->get2('url'));
  }
 
  public function testTidy63() : void {
    $text = "{{cite journal|url=http://proxy/url=https://link.galegroup.com/STUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://link.galegroup.com/STUFF', $template->get2('url'));
  }
 
  public function testTidy64() : void {
    $text = "{{cite journal|url=https://go.galegroup.com/STUFF&u=UNIV&date=1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://go.galegroup.com/STUFF&date=1234', $template->get2('url'));
  }

  public function testTidy65() : void {
    $text = "{{cite journal|url=https://link.galegroup.com/STUFF&u=UNIV&date=1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://link.galegroup.com/STUFF&date=1234', $template->get2('url'));
  }
 
  public function testTidy66() : void {
    $text = "{{cite journal|url=https://search.proquest.com/STUFF/docview/1234/STUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234/STUFF', $template->get2('url'));
  }
 
 public function testTidy66b() : void {
    $text = "{{cite journal|url=http://host.com/login?url=https://search-proquest-com-stuff/STUFF/docview/1234/34123/342}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234', $template->get2('url'));
  }
 
 public function testTidy67() : void {
    $text = "{{cite journal|url=https://0-search-proquest-com.schoo.org/STUFF/docview/1234/2314/3214}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234', $template->get2('url'));
  }
 
  public function testTidy68() : void {
    $text = "{{cite journal|url=http://proxy-proquest.umi.com-org/pqd1234}}"; // Bogus, so deleted
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertNull($template->get2('url'));
  }
 
  public function testTidy69() : void {
    $text = "{{cite journal|url=https://search.proquest.com/dissertations/docview/1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/dissertations/docview/1234', $template->get2('url'));
  }
 
  public function testTidy70() : void {
    $text = "{{cite journal|url=https://search.proquest.com/docview/1234/fulltext}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234', $template->get2('url'));
  }
 
  public function testTidy70b() : void {
    $text = "{{cite journal|url=https://search.proquest.com/docview/1234?account=XXXXX}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/1234', $template->get2('url'));
  }
 
  public function testTidy71() : void {
    $text = "{{cite journal|pmc = pMC12341234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('pmc');
    $this->assertSame('12341234', $template->get2('pmc'));
  }
 
  public function testTidy72() : void {
    $text = "{{cite journal|quotes=false}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('quotes');
    $this->assertNull($template->get2('quotes'));
    $text = "{{cite journal|quotes=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('quotes');
    $this->assertNull($template->get2('quotes'));
    $text = "{{cite journal|quotes=Hello There}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('quotes');
    $this->assertSame('Hello There', $template->get2('quotes'));
  }
 
   public function testTidy73() : void {
    $text = "{{cite web|journal=www.cnn.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('journal');
    $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    
    $text = "{{cite web|journal=cnn.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('journal');
    $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    
    $text = "{{cite web|journal=www.x}}";
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
    $this->assertNull($template->get2('website'));
  }
 
   public function testTidy74() : void {
    $text = "{{cite web|url=http://proquest.umi.com/pqdweb?did=1100578721&sid=3&Fmt=3&clientId=3620&RQT=309&VName=PQD|id=Proquest Document ID 1100578721}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://search.proquest.com/docview/434365733', $template->get2('url'));
    $this->assertNull($template->get2('id'));
   }
 
    public function testTidy74c() : void {
    $text = "{{cite web|journal=openid transaction in progress|isbn=1234|chapter=X|title=Y}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get2('journal'));
   }

   public function testTidy75() : void {
    $text = "{{cite web|url=developers.google.com|publisher=the google hive mind}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Google Inc.', $template->get2('publisher'));
    $text = "{{cite web|url=support.google.com|publisher=the Google hive mind}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Google Inc.', $template->get2('publisher'));
   }
 
   public function testTidy76() : void {
    $text = "{{cite news |url=https://news.google.com/newspapers?id=rPZVAAAAIBAJ&sjid=4-EDAAAAIBAJ&pg=4073%2C7051142 |newspaper=Eugene Register-Guard |location=Oregon |last=Withers |first=Bud |title=Bend baseball bounces back |date=June 23, 1978 |page=1D }}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://news.google.com/newspapers?id=rPZVAAAAIBAJ&pg=4073%2C7051142', $template->get2('url'));
   }
 
   public function testTidy77() : void {
    $text = "{{cite journal |pages=Pages: 1-2 }}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('pages');
    $this->assertSame('1–2', $template->get2('pages'));
   }
 
   public function testTidy78() : void {
    $text = "{{cite journal |pages=p. 1-2 }}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('pages');
    $this->assertSame('1–2', $template->get2('pages'));
   }
 
   public function testTidy79() : void {
    $text = "{{cite arxiv|website=arXiv}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('website');
    $this->assertNull($template->get2('website'));
   }
 
   public function testTidy80() : void {
    $text = "{{cite web|url=https://www-rocksbackpages-com.wikipedialibrary.idm.oclc.org/Library/Article/camel-over-the-moon |via = wiki stuff }}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.rocksbackpages.com/Library/Article/camel-over-the-moon', $template->get2('url'));
    $this->assertNull($template->get2('via'));
   }
 
   public function testTidy81() : void {
    $text = "{{cite web|url=https://rocksbackpages-com.wikipedialibrary.idm.oclc.org/Library/Article/camel-over-the-moon |via=My Dog}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://rocksbackpages.com/Library/Article/camel-over-the-moon', $template->get2('url'));
    $this->assertSame('My Dog', $template->get2('via'));
   }
 
   public function testTidy82() : void {
    $text = "{{cite web|url=https://butte.idm.oclc.org/login?qurl=http://search.ebscohost.com/X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('http://search.ebscohost.com/X', $template->get2('url'));
   }
 
   public function testTidy83() : void {
    $text = "{{cite web|url=https://butte.idm.oclc.org/login?url=http://search.ebscohost.com/X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('http://search.ebscohost.com/X', $template->get2('url'));
   }
 
   public function testTidy84() : void {
    $text = "{{cite web|url=https://go.galegroup.com.ccclibrary.idm.oclc.org/X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://go.galegroup.com/X', $template->get2('url'));
   }
 
   public function testTidy85() : void {
    $text = "{{cite web|url=https://butte.idm.oclc.org/login?url=http://search.ebscohost.com%2fX}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('http://search.ebscohost.com/X', $template->get2('url'));
   }
 
   public function testTidy86() : void {
    $text = "{{cite web|url=https://login.libproxy.union.edu/login?qurl=https://go.gale.com%2fps%2fretrieve.do%3ftabID%3dT002%26resultListType%3dRESULT_LIST%26searchResultsType%3dSingleTab%26searchType%3dBasicSearchForm%26currentPosition%3d1%26docId%3dGALE%257CA493733315%26docType%3dArticle%26sort%3dRelevance%26contentSegment%3dZGPP-MOD1%26prodId%3dITOF%26contentSet%3dGALE%257CA493733315%26searchId%3dR2%26userGroupName%3dnysl_ca_unionc%26inPS%3dtrue}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://go.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE%7CA493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE%7CA493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true', $template->get2('url'));
   }
 
   public function testTidy87() : void {
    $text = "{{cite web|url=https://login.libproxy.union.edu/login?url=https://go.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE%7CA493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE%7CA493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame("https://go.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE%7CA493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE%7CA493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true", $template->get2('url'));
   }
 
   public function testTidy88() : void {
    $text = "{{cite web|url=https://login.libproxy.union.edu/login?url=https%3A%2F%2Fgo.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE%7CA493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE%7CA493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame("https://go.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE|A493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE|A493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true", $template->get2('url'));
   }
 
  public function testIncomplete() : void {
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
 
  public function testAddEditor() : void {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('editor1-last', 'Phil'));
    $this->assertSame('Phil', $template->get2('editor1-last'));
    $text = "{{cite journal|editor-last=Junk}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('editor1-last', 'Phil'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('editor1', 'Phil'));
    $this->assertSame('Phil', $template->get2('editor1'));
    $text = "{{cite journal|editor-last=Junk}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('editor1', 'Phil'));
  }

  public function testAddFirst() : void {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('first1', 'X M'));
    $this->assertSame('X. M.', $template->get2('first1'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('first2', 'X M'));
    $this->assertSame('X. M.', $template->get2('first2'));
  }
 
  public function testDisplayEd() : void {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('display-editors', '3'));
    $this->assertSame('3', $template->get2('display-editors'));
  }

  public function testArchiveDate() : void {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_MDY;
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('January 20, 2010', $template->get2('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_DMY;       
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('20 January 2010', $template->get2('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_WHATEVER;   
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('20 JAN 2010', $template->get2('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('archive-date', 'SDAFEWFEWW#F#WFWEFESFEFSDFDFD'));
    $this->assertNull($template->get2('archive-date'));
  }
 
  public function testAccessDate1() : void {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_MDY;
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
    $this->assertSame('January 20, 2010', $template->get2('access-date'));
  }
 
  public function testAccessDate2() : void {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_DMY;       
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
    $this->assertSame('20 January 2010', $template->get2('access-date'));
  }
 
  public function testAccessDate3() : void {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_WHATEVER;   
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
    $this->assertSame('20 JAN 2010', $template->get2('access-date'));
  }
 
  public function testAccessDate4() : void {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('access-date', 'SDAFEWFEWW#F#WFWEFESFEFSDFDFD'));
    $this->assertNull($template->get2('access-date'));
  }

  public function testAccessDate5() : void {
    $text = "{{cite journal}}"; // NO url
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010')); // Pretty bogus return value
    $this->assertNull($template->get2('access-date'));
  }
 
  public function testWorkStuff() : void {
    $text = "{{cite journal|work=Yes Indeed}}";
    $template = $this->make_citation($text);
    $template->add_if_new('journal', 'Yes indeed');
    $this->assertSame('Yes Indeed', $template->get2('journal'));
    $this->assertNull($template->get2('work'));
    $text = "{{cite journal|work=Yes Indeed}}";
    $template = $this->make_citation($text);
    $template->add_if_new('journal', 'No way sir');
    $this->assertSame('Yes Indeed', $template->get2('work'));
    $this->assertNull($template->get2('journal'));
  }
 
  public function testViaStuff() : void {
    $text = "{{cite journal|via=Yes Indeed}}";
    $template = $this->make_citation($text);
    $template->add_if_new('journal', 'Yes indeed');
    $this->assertSame('Yes Indeed', $template->get2('journal'));
    $this->assertNull($template->get2('via'));
  }
 
  public function testNewspaperJournal() : void {
    $text = "{{cite journal|publisher=news.bbc.co.uk}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get2('newspaper'));
  }
 
  public function testNewspaperJournalBBC() : void {
    $text = "{{cite journal|publisher=Bbc.com}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'BBC News'));
    $this->assertNull($template->get2('newspaper'));
    $this->assertSame('BBC News', $template->get2('work'));
    $this->assertNull($template->get2('publisher'));
  }
 
  public function testNewspaperJournaXl() : void {
    $text = "{{cite journal|work=exists}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get2('newspaper'));
    $this->assertSame('exists', $template->get2('work'));
  }
 
  public function testNewspaperJournaXk() : void {
    $text = "{{cite journal|via=This is from the times}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'Times'));
    $this->assertNull($template->get2('via'));
    $this->assertSame('Times', $template->get2('newspaper'));
  }

  public function testNewspaperJournal100() : void {
    $text = "{{cite journal|work=A work}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get2('newspaper'));
  }
 
  public function testNewspaperJournal101() : void {
    $text = "{{cite web|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get2('website'));
    $this->assertSame('News.BBC.co.uk', $template->get2('work'));
  }
 
   public function testNewspaperJournal102() : void {
    $text = "{{cite journal|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'Junk and stuff'));
    $this->assertNull($template->get2('website'));
    $this->assertSame('Junk and Stuff', $template->get2('newspaper'));
  }
 
  public function testNewspaperJournal2() : void {
    $text = "{{cite journal|via=Something}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'A newspaper'));
    
    $text = "{{cite journal|via=Times}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'The Times'));
    $this->assertNull($template->get2('via'));
    $this->assertSame('Times', $template->get2('newspaper'));
    
    $text = "{{cite journal|via=A Post website}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'The Sun Post'));
    $this->assertNull($template->get2('via'));
    $this->assertSame('The Sun Post', $template->get2('newspaper'));
  }

  public function testNewspaperJournal3() : void {
    $text = "{{cite journal|publisher=A Big Company}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'A Big Company'));
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('A Big Company', $template->get2('newspaper'));
  }
 
  public function testNewspaperJournal4() : void {
    $text = "{{cite journal|website=A Big Company}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('journal', 'A Big Company'));
    $this->assertSame('A Big Company', $template->get2('journal'));
    $this->assertNull($template->get2('website'));
    
    $text = "{{cite journal|website=A Big Company}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('journal', 'A Small Little Company'));
    $this->assertSame('A Small Little Company', $template->get2('journal'));
    $this->assertNull($template->get2('website'));
    
    $text = "{{cite journal|website=[[A Big Company]]}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('journal', 'A Small Little Company'));
    $this->assertSame('[[A Big Company]]', $template->get2('journal'));
    $this->assertNull($template->get2('website'));
  }
 
  public function testAddTwice() : void {
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('series', 'The Sun Post'));
    $this->assertFalse($template->add_if_new('series', 'The Dog'));
    $this->assertSame('The Sun Post', $template->get2('series'));
  }

  public function testExistingIsTitle() : void {
    $text = "{{cite journal|encyclopedia=Existing Data}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('title', 'Existing Data'));
    $this->assertNull($template->get2('title'));
   
    $text = "{{cite journal|dictionary=Existing Data}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('title', 'Existing Data'));
    $this->assertNull($template->get2('title'));
   
    $text = "{{cite journal|journal=Existing Data}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('title', 'Existing Data'));
    $this->assertNull($template->get2('title'));
  }
                      
  public function testUpdateIssue() : void {
    $text = "{{cite journal|issue=1|volume=}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('issue', '7'));
    $this->assertFalse($template->add_if_new('issue', '8'));
    $this->assertSame('7', $template->get2('issue'));
  }
 
  public function testExistingCustomPage() : void {
    $text = "{{cite journal|pages=footnote 7}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('pages', '3-22'));
    $this->assertSame('footnote 7', $template->get2('pages'));
  }
  
  public function testPagesIsArticle() : void {
    $text = "{{cite journal|pages=431234}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('pages', '1-34'));
    $this->assertSame('431234', $template->get2('pages'));
  }

  public function testExitingURL() : void {
    $text = "{{cite journal|conferenceurl=http://XXXX-TEST.COM}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('url', 'http://XXXX-TEST.COM'));
    $this->assertNull($template->get2('url'));
   
    $text = "{{cite journal|url=xyz}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('title-link', 'abc'));
    $this->assertNull($template->get2('title-lin'));
  }

  public function testResearchGateDOI() : void {
    $text = "{{cite journal|doi=10.13140/RG.2.2.26099.32807}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('doi', '10.1002/jcc.21074'));  // Not the same article, random
    $this->assertSame('10.1002/jcc.21074', $template->get2('doi'));
  }

  public function testResearchJstorDOI() : void {
    $text = "{{cite journal|doi=10.2307/1974136}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
    $this->assertNull($template->get2('doi'));
  }
 
  public function testAddBrokenDateFormat1() : void {
    $text = "{{cite journal|doi=10.3222/XXXXXXXxxxxxx}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
    $this->assertSame('1 DEC 2019', $template->get2('doi-broken-date'));
  }

  public function testAddBrokenDateFormat2() : void {
    $text = "{{cite journal|doi=10.3222/XXXXXXXxxxxxx}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_MDY;
    $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
    $this->assertSame('December 1, 2019', $template->get2('doi-broken-date'));
  }
 
  public function testAddBrokenDateFormat3() : void {
    $text = "{{cite journal|doi=10.3222/XXXXXXXxxxxxx}}";
    $template = $this->make_citation($text);
    $template->date_style = DATES_DMY;
    $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
    $this->assertSame('1 December 2019', $template->get2('doi-broken-date'));
  }
 
  public function testNotBrokenDOI() : void {
    $text = "{{cite journal|doi-broken-date = # # # CITATION_BOT_PLACEHOLDER_COMMENT # # # }}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('doi-broken-date', '1 DEC 2019'));
  }
 
   public function testForgettersChangeType() : void {
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
 
  public function testForgettersChangeOtherURLS() : void {
    $text = "{{cite web|chapter-url=Y|chapter=X}}";
    $template = $this->make_citation($text);
    $template->forget('chapter');
    $this->assertSame('Y', $template->get2('url'));

    $text = "{{cite web|chapterurl=Y|chapter=X}}";
    $template = $this->make_citation($text);
    $template->forget('chapter');
    $this->assertSame('Y', $template->get2('url'));
  }
      
  public function testForgettersChangeWWWWork() : void {
    $text = "{{cite web|url=X|work=www.apple.com}}";
    $template = $this->make_citation($text);
    $template->forget('url');
    $this->assertNull($template->get2('work'));
  }
      
  public function testCommentShields() : void {
    $text = "{{cite web|work = # # CITATION_BOT_PLACEHOLDER_COMMENT # #}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->set('work', 'new'));
    $this->assertSame('# # CITATION_BOT_PLACEHOLDER_COMMENT # #', $template->get2('work'));
  }
      
  public function testRenameSpecialCases() : void {
    $text = "{{cite web|id=x}}";
    $template = $this->make_citation($text);
    $template->rename('work', 'work');
    $template->rename('work', 'work', 'new');
    $this->assertSame('new', $template->get2('work'));
   
    $text = "{{cite web|id=x}}";
    $template = $this->make_citation($text);
    $template->rename('work', 'journal');
    $template->rename('work', 'journal', 'new');
    $this->assertSame('new', $template->get2('journal'));

 
    $text = "{{cite web}}"; // param will be null
    $template = $this->make_citation($text);
    $template->rename('work', 'journal');
    $template->rename('work', 'journal', 'new');
    $this->assertNull($template->get2('journal'));
  }
 
  public function testModificationsOdd() : void {
    $text = "{{cite web}}"; // param will be null to start
    $template = $this->make_citation($text);
    $this->assertTrue($template->add('work', 'The Journal'));
    $this->assertSame('The Journal', $template->get2('work'));
    $this->assertNull($template->get2('journal'));
    $ret = $template->modifications();
    $this->assertTrue(isset($ret['deletions']));
    $this->assertTrue(isset($ret['changeonly']));
    $this->assertTrue(isset($ret['additions']));
    $this->assertTrue(isset($ret['dashes']));
    $this->assertTrue(isset($ret['names']));
  }

  public function testAuthors1() : void {
    $text = "{{cite web|title=X}}";
    $template = $this->make_citation($text);
    $template->set('author3', '[[Joe|Joes]]'); // Must use set
    $template->tidy_parameter('author3');
    $this->assertSame('[[Joe|Joes]]', $template->get2('author3'));
    $this->assertNull($template->get2('author3-link'));
  }

  public function testMoreEtAl() : void {
    $text = "{{cite web|authors=John, et al.}}";
    $template = $this->make_citation($text);
    $template->handle_et_al();
    $this->assertSame('John', $template->get2('author'));
    $this->assertSame('etal', $template->get2('display-authors'));
  }
 
  public function testAddingEtAl() : void {
    $text = "{{cite web}}";
    $template = $this->process_citation($text);
    $template->set('authors', 'et al');
    $template->tidy_parameter('authors');
    $this->assertNull($template->get2('authors'));
    $this->assertSame('etal', $template->get2('display-authors'));
    $this->assertNull($template->get2('author'));
  }
 
   public function testAddingEtAl2() : void {
    $text = "{{cite web}}";
    $template = $this->process_citation($text);
    $template->set('author','et al');
    $template->tidy_parameter('author');
    $this->assertNull($template->get2('author'));
    $this->assertNull($template->get2('authors'));
    $this->assertSame('etal', $template->get2('display-authors'));
  }
 
  public function testCiteTypeWarnings1() : void {
    $text = "{{cite web|journal=X|chapter=|publisher=}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite journal', $template->wikiname());
    $this->assertNull($template->get2('chapter'));
    $this->assertNull($template->get2('publisher'));
   
    $text = "{{cite web|journal=X|chapter=Y|}}"; // Will warn user
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite web', $template->wikiname());
    $this->assertSame('Y', $template->get2('chapter'));
  }

  public function testCiteTypeWarnings2() : void {
    $text = "{{cite arxiv|eprint=XYZ|bibcode=XXXX}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get2('bibcode'));
  }

  public function testTidyPublisher() : void {
    $text = "{{citation|publisher='''''''''X'''''''''}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('X', $template->get2('work'));
  }
                      
  public function testTidyWork() : void {
    $text = "{{citation|work=|website=X}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get2('work'));

    $text = "{{cite web|work=}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get2('work'));
   
    $text = "{{cite journal|work=}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame( "{{cite journal|journal=}}", $template->parsed_text());
  }
                      
  public function testTidyChapterTitleSeries() : void {
    $text = "{{cite book|chapter=X|title=X}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get2('title'));
  }
 
  public function testTidyChapterTitleSeries2() : void {              
    $text = "{{cite journal|chapter=X|title=X}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertNull($template->get2('chapter'));
  }
 
  public function testETCTidy() : void {
    $text = "{{cite web|pages=342 etc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('pages');
    $this->assertSame('342 etc.', $template->get2('pages'));
  }
                      
  public function testZOOKEYStidy() : void {
    $text = "{{cite journal|journal=[[zOOkeys]]|volume=333|issue=22}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('volume');
    $this->assertNull($template->get2('volume'));
    $this->assertSame('22', $template->get2('issue'));
  }
                      
  public function testTidyViaStuff() : void {
    $text = "{{cite journal|via=A jstor|jstor=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('via');
    $this->assertNull($template->get2('via'));

    $text = "{{cite journal|via=google books etc|isbn=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('via');
    $this->assertNull($template->get2('via'));
   
    $text = "{{cite journal|via=questia etc|isbn=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('via');
    $this->assertNull($template->get2('via'));
   
    $text = "{{cite journal|via=library}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('via');
    $this->assertNull($template->get2('via'));
  }

  public function testConversionOfURL1() : void {
    $text = "{{cite journal|url=https://mathscinet.ams.org/mathscinet-getitem?mr=0012343|chapterurl=https://mathscinet.ams.org/mathscinet-getitem?mr=0012343}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('0012343', $template->get2('mr'));
  }
 
  public function testConversionOfURL3() : void {
    $text = "{{cite web|url=http://worldcat.org/issn/1234-1234}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234-1234', $template->get2('issn'));
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testConversionOfURL4() : void {
    $text = "{{cite web|url=http://lccn.loc.gov/1234}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234', $template->get2('lccn'));
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testConversionOfURL5() : void {
    $text = "{{cite web|url=http://openlibrary.org/books/OL/1234W}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234W', $template->get2('ol'));
    $this->assertNotNull($template->get2('url'));
  }
 
  public function testTidyJSTOR() : void {
    $text = "{{cite web|jstor=https://www.jstor.org/stable/123456}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('jstor');
    $this->assertSame('123456', $template->get2('jstor'));
    $this->assertSame('cite journal', $template->wikiname());
  }
  
  public function testAuthor2() : void {
    $text = "{{cite web|title=X}}";
    $template = $this->make_citation($text);
    $template->set('author1', 'Joe Jones Translated by John Smith');
    $template->tidy_parameter('author1');
    $this->assertSame('Joe Jones', $template->get2('author1'));
    $this->assertSame('Translated by John Smith', $template->get2('others'));
  }
  
  public function testAuthorsAndAppend3() : void {
    $text = "{{cite web|title=X}}";
    $template = $this->make_citation($text);
    $template->set('others', 'Kim Bill'); // Must use set
    $template->set('author1', 'Joe Jones Translated by John Smith');
    $template->tidy_parameter('author1');
    $this->assertSame('Joe Jones', $template->get2('author1'));
    $this->assertSame('Kim Bill; Translated by John Smith', $template->get2('others'));
  }

  public function testAuthorsAndAppend4() : void {
    $text = "{{cite web|title=X}}";
    $template = $this->make_citation($text);
    $template->set('others', 'CITATION_BOT_PLACEHOLDER_COMMENT');
    $template->set('author1', 'Joe Jones Translated by John Smith');
    $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get2('others'));
    $this->assertSame('Joe Jones Translated by John Smith', $template->get2('author1'));
  }
 
  public function testConversionOfURL6() : void {
    $text = "{{cite web|url=http://search.proquest.com/docview/12341234|title=X}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNotNull($template->get2('url'));
    $this->assertSame('{{ProQuest|12341234}}', $template->get2('id'));
   
    $text = "{{cite web|url=http://search.proquest.com/docview/12341234}}";  // No title
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
  }
 
  public function testConversionOfURL7() : void {
    $text = "{{cite web|url=https://search.proquest.com/docview/12341234|id=CITATION_BOT_PLACEHOLDER_COMMENT|title=Xyz}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get2('id'));
    $this->assertSame('https://search.proquest.com/docview/12341234', $template->get2('url'));
  }

  public function testVolumeIssueDemixing21() : void {
    $text = '{{cite journal|issue = volume 12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get2('volume'));
    $this->assertNull($prepared->get2('issue'));
  }
 
  public function testVolumeIssueDemixing22() : void {
    $text = '{{cite journal|issue = volume 12XX|volume=12XX|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12XX', $prepared->get2('volume'));
    $this->assertNull($prepared->get2('issue'));
  }
 
   public function testNewspaperJournal111() : void {
    $text = "{{cite journal|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get2('website'));
    $this->assertSame('News.BBC.co.uk', $template->get2('work'));
    $this->assertNull($template->get2('journal'));
    $this->assertSame('cite journal', $template->wikiname());  // Unchanged
    // This could all change in final_tidy()
  }
 
  public function testMoreEtAl2() : void {
    $text = "{{cite web|authors=Joe et al.}}";
    $template = $this->make_citation($text);
    $this->assertSame('Joe et al.', $template->get2('authors'));
    $template->handle_et_al();
    $this->assertSame('Joe', $template->get2('author'));
    $this->assertNull($template->get2('authors'));
    $this->assertSame('etal', $template->get2('display-authors'));
  }
 
   public function testCiteTypeWarnings3() : void {
    $text = "{{citation|title=XYZsadfdsfsdfdsafsd|chapter=DSRGgbgfbxdzfdfsXXXX|journal=adsfsd}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite book', $template->wikiname());

    $text = "{{Citation|title=XYZsadfdsfsdfdsafsd|chapter=DSRGgbgfbxdzfdfsXXXX|journal=adsfsd}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite book', $template->wikiname()); // Wikiname does not return the actual value, but the normalized one
  }

  public function testTidyWork2() : void {
    $text = "{{cite magazine|work=}}";
    $template = $this->make_citation($text);
    $template->prepare();
    $this->assertSame( "{{cite magazine|magazine=}}", $template->parsed_text());  
  }
 
  public function testTidyChapterTitleSeries3() : void {
    $text = "{{cite journal|title=XYZ}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $this->assertSame('XYZ', $template->get2('title'));
    $this->assertNull($template->get2('series'));
   
    $text = "{{cite journal|journal=XYZ}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $this->assertSame('XYZ', $template->get2('journal'));
    $this->assertNull($template->get2('series'));
  }
  
  public function testTidyChapterTitleSeries4() : void {
    $text = "{{cite book|journal=X}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $template->tidy_parameter('series');
    $this->assertSame('XYZ', $template->get2('series'));
    $this->assertSame('X', $template->get2('journal'));
   
    $text = "{{cite book|title=X}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $template->tidy_parameter('series');
    $this->assertSame('XYZ', $template->get2('series'));
    $this->assertSame('X', $template->get2('title'));
  }
 
  public function testAllZeroesTidy() : void {
    $text = "{{cite web|issue=000000000}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('issue');
    $this->assertNull($template->get2('issue'));
  }
 
  public function testConversionOfURL2() : void {
    $text = "{{cite web|url=http://worldcat.org/title/stuff/oclc/1234}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234', $template->get2('oclc'));
    $this->assertNotNull($template->get2('url'));
    $this->assertSame('cite book', $template->wikiname());           
  }
 
  public function testConversionOfURL2B() : void {
    $text = "{{cite web|url=http://worldcat.org/title/edition/oclc/1234}}"; // Edition
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNull($template->get2('oclc'));
    $this->assertSame('http://worldcat.org/title/edition/oclc/1234', $template->get2('url'));
    $this->assertSame('cite web', $template->wikiname());           
  }
 
  public function testAddDupNewsPaper() : void {
    $text = "{{cite web|work=I exist and submit}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('newspaper', 'bbc sports'));
    $this->assertSame('I exist and submit', $template->get2('work'));
    $this->assertNull($template->get2('newspaper'));
  }
 
 
  public function testAddBogusBibcode() : void {
    $text = "{{cite web|bibcode=Exists}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('bibcode', 'xyz')); 
    $this->assertSame('Exists', $template->get2('bibcode'));

    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('bibcode', 'Z')); 
    $this->assertSame('Z..................', $template->get2('bibcode'));
  }

  public function testvalidate_and_add() : void {
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
 
  public function testDateYearRedundancyEtc() : void {
    $text = "{{cite web|year=2004|date=}}";
    $template = $this->make_citation($text);
    $template->tidy();
    $this->assertSame("2004", $template->get2('year'));
    $this->assertNull($template->get2('date')); // Not an empty string anymore
   
    $text = "{{cite web|date=November 2004|year=}}";
    $template = $this->make_citation($text);
    $template->tidy();
    $this->assertSame("November 2004", $template->get2('date'));
    $this->assertNull($template->get2('year')); // Not an empty string anymore
   
    $text = "{{cite web|date=November 2004|year=Octorberish 2004}}";
    $template = $this->make_citation($text);
    $template->tidy();
    $this->assertSame("November 2004", $template->get2('date'));
    $this->assertNull($template->get2('year'));
   
    $text = "{{cite web|date=|year=Sometimes around 2004}}";
    $template = $this->make_citation($text);
    $template->tidy();
    $this->assertSame("Sometimes around 2004", $template->get2('date'));
    $this->assertNull($template->get2('year'));
  }
 
   public function testOddThing() : void {
     $text='{{journal=capitalization is Good}}';
     $template = $this->process_citation($text);
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testTranslator() : void {
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
 
   public function testAddDuplicateBibcode() : void {
     $text='{{cite web|url=https://ui.adsabs.harvard.edu/abs/1924MNRAS..84..308E/abstract|bibcode=1924MNRAS..84..308E}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->get_identifiers_from_url());
     $this->assertNotNull($template->get2('url'));
   }
 
   public function testNonUSAPubMedMore() : void {
     $text='{{cite web|url=https://europepmc.org/abstract/med/342432/pdf}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->get_identifiers_from_url());
     $this->assertNotNull($template->get2('url'));
     $this->assertSame('342432', $template->get2('pmid'));
     $this->assertSame('cite journal', $template->wikiname());
   }
 
   public function testNonUSAPubMedMore2() : void {
     $text='{{cite web|url=https://europepmc.org/scanned?pageindex=1234&articles=pmc43871}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->get_identifiers_from_url());
     $this->assertNull($template->get2('url'));
     $this->assertSame('43871', $template->get2('pmc'));
     $this->assertSame('cite journal', $template->wikiname());
   }

   public function testNonUSAPubMedMore3() : void {
     $text='{{cite web|url=https://pubmedcentralcanada.ca/pmcc/articles/PMC324123/pdf}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->get_identifiers_from_url());
     $this->assertNull($template->get2('url'));
     $this->assertSame('324123', $template->get2('pmc'));
     $this->assertSame('cite journal', $template->wikiname());
   }
 
   public function testRubbishArxiv() : void { // Something we do not understand, other than where it is from
     $text='{{cite web|url=http://arxiv.org/X/abs/3XXX41222342343242}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->get_identifiers_from_url());
     $this->assertSame('cite arxiv', $template->wikiname());
     $this->assertNull($template->get2('arxiv'));
     $this->assertNull($template->get2('eprint'));
     $this->assertSame('http://arxiv.org/X/abs/3XXX41222342343242', $template->get2('url'));
   }
 
   public function testArchiveAsURL() : void {
     $text='{{Cite web | url=https://web.archive.org/web/20111030210210/http://www.cap.ca/en/}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->get_identifiers_from_url()); // FALSE because we add no parameters or such
     $this->assertSame('http://www.cap.ca/en/', $template->get2('url'));
     $this->assertSame('https://web.archive.org/web/20111030210210/http://www.cap.ca/en/', $template->get2('archive-url'));
     $this->assertSame('2011-10-30', $template->get2('archive-date'));
   }
 
   public function testCAPSGoingAway1() : void {
     $text='{{Cite journal | doi=10.1016/j.ifacol.2017.08.010|title=THIS IS A VERY BAD ALL CAPS TITLE|journal=THIS IS A VERY BAD ALL CAPS JOURNAL}}';
     $template = $this->process_citation($text);
     $this->assertSame('Contingency Analysis Post-Processing with Advanced Computing and Visualization', $template->get2('title'));
     $this->assertSame('IFAC-PapersOnLine', $template->get2('journal'));   
   }
 
   public function testCAPSGoingAway2() : void {
     $text='{{Cite book | doi=10.1109/PESGM.2015.7285996|title=THIS IS A VERY BAD ALL CAPS TITLE|chapter=THIS IS A VERY BAD ALL CAPS CHAPTER}}';
     $template = $this->process_citation($text);
     $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get2('chapter'));
     $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get2('title')); 
   }
 
   public function testCAPSGoingAway3() : void {
     $text='{{Cite book | doi=10.1109/PESGM.2015.7285996|title=Same|chapter=Same}}';
     $template = $this->process_citation($text);
     $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get2('chapter'));
     $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get2('title')); 
   }
 
   public function testCAPSGoingAway4() : void {
     $text='{{Cite book | doi=10.1109/PESGM.2015.7285996|title=Same|chapter=Same|journal=Same}}';
     $template = $this->process_citation($text);
     $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get2('chapter'));
     $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get2('title')); 
     $this->assertSame('Same', $template->get2('journal'));
   }
 
   public function testCAPSGoingAway5() : void {
     $text='{{Cite book | jstor=TEST_DATA_IGNORE |title=Same|chapter=Same|journal=Same}}';
     $template = $this->process_citation($text);
     $this->assertSame('Same', $template->get2('journal'));
     $this->assertSame('Same', $template->get2('title'));
     $this->assertNull($template->get2('chapter'));
   }
 
   public function testAddDuplicateArchive() : void {
     $text='{{Cite book | archiveurl=XXX}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->add_if_new('archive-url', 'YYY'));
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testReplaceBadDOI() : void {
     $text='{{Cite journal | doi=10.0000/broken|doi-broken-date=1999}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->add_if_new('doi', '10.1063/1.2263373'));
     $this->assertSame('10.1063/1.2263373', $template->get2('doi'));
   }
 
   public function testDropBadDOI() : void {
     $text='{{Cite journal | doi=10.1063/1.2263373|chapter-url=http://dx.doi.org/10.000/broken}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1063/1.2263373', $template->get2('doi'));
     $this->assertNotNull($template->get2('chapter-url')); // TODO - should probably do this
   }
 
   public function testEmptyJunk() : void {
     $text='{{Cite journal| dsfasfdfasdfsdafsdafd = | issue = | issue = 33}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get2('issue'));
     $this->assertNull($template->get2('dsfasfdfasdfsdafsdafd'));
     $this->assertSame('{{Cite journal| issue = 33}}', $template->parsed_text());
   }
 
   public function testFloaters() : void {
     $text='{{Cite journal| p 33 }}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get2('page'));
     $this->assertSame('{{Cite journal|page = 33}}', $template->parsed_text());

     $text='{{Cite journal | p 33 |page=}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get2('page'));
    
     $text='{{Cite journal |33(22):11-12 }}';
     $template = $this->process_citation($text);
     $this->assertSame('22', $template->get2('issue'));
     $this->assertSame('33', $template->get2('volume'));
     $this->assertSame('11–12', $template->get2('pages'));
   }
 
    public function testFloaters2() : void {
     $text='{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 }}';
     $template = $this->process_citation($text);
     $this->assertSame('12 December 1990', $template->get2('access-date'));
   }
 
    public function testFloaters3() : void {
     $text='{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 |accessdate=}}';
     $template = $this->process_citation($text);
     $this->assertSame('12 December 1990', $template->get2('access-date'));
   }
 
    public function testFloaters4() : void {
     $text='{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 | accessdate = 3 May 1999 }}';
     $template = $this->process_citation($text);
     $this->assertSame('3 May 1999', $template->get2('access-date'));
     $this->assertNull($template->get2('accessdate'));
   }
 
    public function testFloaters5() : void {
     $text='{{Cite journal | issue 33 }}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get2('issue'));
   }
 
    public function testFloaters6() : void {
     $text='{{Cite journal | issue 33 |issue=}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get2('issue'));
   }
 
    public function testFloaters7() : void {
     $text='{{Cite journal | issue 33 | issue=22 }}';
     $template = $this->process_citation($text);
     $this->assertSame('22', $template->get2('issue'));
   }
 
    public function testFloaters8() : void {
     $text='{{Cite journal |  p 33 junk}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get2('page'));
   }
 
    public function testFloaters9() : void {
     $text='{{Cite journal |  p 33 junk|page=}}';
     $template = $this->process_citation($text);
     $this->assertSame('33', $template->get2('page'));
   }
 
   public function testIDconvert1() : void {
     $text='{{Cite journal | id = {{ASIN|3333|country=eu}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame($text, $template->parsed_text());
   }    

   public function testIDconvert2() : void {
     $text = '{{Cite journal | id = {{jstor|33333|issn=xxxx}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testIDconvert3() : void {
     $text = '{{Cite journal | id = {{ol|44444|author=xxxx}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testIDconvert4() : void {
     $text = '{{Cite journal | id = {{howdy|44444}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testIDconvert5() : void {
     $text='{{Cite journal | id = {{oclc|02268454}} {{ol|1234}}  }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame('02268454', $template->get2('oclc'));
     $this->assertSame('1234', $template->get2('ol'));
     $this->assertNull($template->get2('id'));
   }
 
   public function testIDconvert6() : void {
     $text='{{Cite journal | id = {{jfm|02268454}} {{lccn|1234}} {{mr|222}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame('02268454', $template->get2('jfm'));
     $this->assertSame('1234', $template->get2('lccn'));
     $this->assertSame('222', $template->get2('mr'));
     $this->assertNull($template->get2('id'));
   }
 
   public function testIDconvert6b() : void {
     $text='{{Cite journal | id = {{mr|id=222}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame('222', $template->get2('mr'));
     $this->assertNull($template->get2('id'));
   }
 
   public function testIDconvert7() : void {
     $text='{{Cite journal | id = {{osti|02268454}} {{ssrn|1234}} }}';
     $template = $this->process_citation($text);
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     $this->assertSame('02268454', $template->get2('osti'));
     $this->assertSame('1234', $template->get2('ssrn'));
     $this->assertNull($template->get2('id'));
   }

   public function testIDconvert8() : void {
     $text='{{Cite journal | id = {{ASIN|0226845494|country=eu}} }}';
     $template = $this->process_citation($text);
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testIDconvert9() : void {
     $text = '{{Cite journal | id = {{howdy|0226845494}} }}';
     $template = $this->process_citation($text);
     $this->assertSame($text, $template->parsed_text());
    }
 
    public function testIDconvert10() : void {
     $text = '{{Cite journal|id = {{arxiv}}}}';
     $template = $this->process_citation($text);
     $this->assertSame('{{Cite journal}}', $template->parsed_text());
    }
 
    public function testIDconvert11() : void {
     $text = '{{cite journal|id={{isbn}} {{oclc}} {{jstor}} {{arxiv}} }}';
     $page = $this->process_page($text);
     $this->assertSame('{{cite journal}}', $page->parsed_text());
    }
 
   public function testCAPS() : void {
     $text = '{{Cite journal | URL = }}';
     $template = $this->process_citation($text);
     $this->assertSame('', $template->get2('url'));
     $this->assertNull($template->get2('URL'));
    
     $text = '{{Cite journal | QWERTYUIOPASDFGHJKL = ABC}}';
     $template = $this->process_citation($text);
     $this->assertSame('ABC', $template->get2('qwertyuiopasdfghjkl'));
     $this->assertNull($template->get2('QWERTYUIOPASDFGHJKL'));
   }
 
   public function testDups() : void {
     $text = '{{Cite journal | DUPLICATE_URL = }}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('duplicate_url'));
     $this->assertNull($template->get2('DUPLICATE_URL'));
     $text = '{{Cite journal | duplicate_url = }}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('duplicate_url'));
     $this->assertNull($template->get2('DUPLICATE_URL'));
     $text = '{{Cite journal|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=}}';
     $template = $this->process_citation($text);
     $this->assertSame('{{Cite journal|id=}}', $template->parsed_text());
   }
 
   public function testDropSep() : void {
     $text = '{{Cite journal | author_separator = }}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('author_separator'));
     $this->assertNull($template->get2('author-separator'));
     $text = '{{Cite journal | author-separator = Something}}';
     $template = $this->process_citation($text);
     $this->assertSame('Something', $template->get2('author-separator'));
   }

   public function testCommonMistakes() : void {
     $text = '{{Cite journal | origmonth = X}}';
     $template = $this->process_citation($text);
     $this->assertSame('X', $template->get2('month'));
     $this->assertNull($template->get2('origmonth'));
   }
 
   public function testRoman() : void { // No roman and then wrong roman
     $text = '{{Cite journal | title=On q-Functions and a certain Difference Operator|doi=10.1017/S0080456800002751}}';
     $template = $this->process_citation($text);
     $this->assertSame('Transactions of the Royal Society of Edinburgh', $template->get2('journal'));
     $text = '{{Cite journal | title=XXI.—On q-Functions and a certain Difference Operator|doi=10.1017/S0080456800002751}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('journal'));
   }
 
   public function testRoman2() : void { // Bogus roman to start with
     $text = '{{Cite journal | title=Improved heat capacity estimator for path integral simulations. XXXI. part of many|doi=10.1063/1.1493184}}';
     $template = $this->process_citation($text);
     $this->assertSame('The Journal of Chemical Physics', $template->get2('journal'));
   }
 
   public function testRoman3() : void { // Bogus roman in the middle
     $text = "{{Cite journal | doi = 10.1016/0301-0104(82)87006-7|title=Are atoms intrinsic to molecular electronic wavefunctions? IIII. Analysis of FORS configurations}}";
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('journal'));
   }
   
   public function testRoman4() : void { // Right roman in the middle
     $text = "{{Cite journal | doi = 10.1016/0301-0104(82)87006-7|title=Are atoms intrinsic to molecular electronic wavefunctions? III. Analysis of FORS configurations}}";
     $template = $this->process_citation($text);
     $this->assertSame('Chemical Physics', $template->get2('journal'));
   }
 
   public function testAppendToComment() : void {
     $text = '{{cite web}}';
     $template = $this->make_citation($text);
     $template->set('id', 'CITATION_BOT_PLACEHOLDER_COMMENT');
     $this->assertFalse($template->append_to('id', 'joe'));
     $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get2('id'));
   }
 
   public function testAppendEmpty() : void {
     $text = '{{cite web|id=}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->append_to('id', 'joe'));
     $this->assertSame('joe', $template->get2('id'));
   }

   public function testAppendNull() : void {
     $text = '{{cite web}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->append_to('id', 'joe'));
     $this->assertSame('joe', $template->get2('id'));
   }

   public function testAppendEmpty2() : void {
     $text = '{{cite web|last=|id=}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->append_to('id', 'joe'));
     $this->assertSame('joe', $template->get2('id'));
   }
 
   public function testAppendAppend() : void {
     $text = '{{cite web|id=X}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->append_to('id', 'joe'));
     $this->assertSame('Xjoe', $template->get2('id'));
   }
 
   public function testDateStyles() : void {
     $text = '{{cite web}}';
     $template = $this->make_citation($text);
     $template->date_style = DATES_MDY;
     $template->add_if_new('date', '12-02-2019');
     $this->assertSame('February 12, 2019', $template->get2('date'));
     $template = $this->make_citation($text);
     $template->date_style = DATES_DMY;
     $template->add_if_new('date', '12-02-2019');
     $this->assertSame('12 February 2019', $template->get2('date'));
     $template = $this->make_citation($text);
     $template->date_style = DATES_WHATEVER;
     $template->add_if_new('date', '12-02-2019');
     $this->assertSame('12-02-2019', $template->get2('date'));
   }
 
    public function testFinalTidyComplicated() : void {
     $text = '{{cite book|series=A|journal=A}}';
     $template = $this->make_citation($text);
     $template->final_tidy();
     $this->assertSame('A', $template->get2('series'));
     $this->assertNull($template->get2('journal'));
     
     $text = '{{cite journal|series=A|journal=A}}';
     $template = $this->make_citation($text);
     $template->final_tidy();
     $this->assertSame('A', $template->get2('journal'));
     $this->assertNull($template->get2('series')); 
   }
 
   public function testFindDOIBadAuthorAndFinalPage() : void { // Testing this code:   If fail, try again with fewer constraints...
     $text = '{{cite journal|last=THIS_IS_BOGUS_TEST_DATA|pages=4346–43563413241234|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|year=2019}}';
     $template = $this->make_citation($text);
     $template->get_doi_from_crossref();
     $this->assertSame('10.1021/acs.analchem.8b04567', $template->get2('doi'));
   }
 
   public function testCAPSParams() : void {
     $text = '{{cite journal|ARXIV=|TITLE=|LAST1=|JOURNAL=}}';
     $template = $this->process_citation($text);
     $this->assertSame(strtolower($text), $template->parsed_text());
   }
 
   public function testRemoveBadPublisher() : void {
     $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=X-Y|pmc=1234123|publisher=u.s. National Library of medicine}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertNull($template->get2('publisher'));
   }
 
   public function testShortSpelling() : void {
     $text = '{{cite journal|list=X}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('X', $template->get2('last'));
    
     $text = '{{cite journal|las=X}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('X', $template->get2('last'));
    
     $text = '{{cite journal|lis=X}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('X', $template->get2('lis'));
   }
 
   public function testSpellingLots() : void {
     $text = '{{cite journal|totle=X|journul=X|serias=X|auther=X|lust=X|cows=X|pigs=X|contrubution-url=X|controbution-urls=X|chupter-url=X|orl=X}}';
     $template = $this->prepare_citation($text); 
     $this->assertSame('{{cite journal|title=X|journal=X|series=X|author=X|last=X|cows=X|page=X|contribution-url=X|contribution-url=X|chapter-url=X|url=X}}', $template->parsed_text());
   }
 
   public function testAlmostSame() : void {
    $this->requires_secrets(function() : void {
     $text = '{{cite journal|publisher=[[Abc|Abc]]|journal=Abc}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertNull($template->get2('publisher'));
     $this->assertSame('[[abc|abc]]', strtolower($template->get2('journal'))); // Might "fix" Abc redirect to ABC
    });
   }

   public function testRemoveAuthorLinks() : void {
     $text = '{{cite journal|author3-link=}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('author3-link'));

     $text = '{{cite journal|author3-link=|author3=X}}';
     $template = $this->process_citation($text);
     $this->assertSame('', $template->get2('author3-link'));
   }
 
   public function testBogusArxivPub() : void {
     $text = '{{cite journal|publisher=arXiv|arxiv=1234}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertNull($template->get2('publisher'));
    
     $text = '{{cite journal|publisher=arXiv}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertSame('arXiv', $template->get2('publisher'));
   }
 
   public function testBloombergConvert() : void {
     $text = '{{cite journal|url=https://www.bloomberg.com/tosv2.html?vid=&uuid=367763b0-e798-11e9-9c67-c5e97d1f3156&url=L25ld3MvYXJ0aWNsZXMvMjAxOS0wNi0xMC9ob25nLWtvbmctdm93cy10by1wdXJzdWUtZXh0cmFkaXRpb24tYmlsbC1kZXNwaXRlLWh1Z2UtcHJvdGVzdA==}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('url');
     $this->assertSame('https://www.bloomberg.com/news/articles/2019-06-10/hong-kong-vows-to-pursue-extradition-bill-despite-huge-protest', $template->get2('url'));
   }
 
   public function testWork2Enc() : void {
     $text = '{{cite web|url=plato.stanford.edu|work=X}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get2('work'));
     $this->assertSame('X', $template->get2('encyclopedia'));
    
     $text = '{{cite web|work=X from encyclopædia}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get2('work'));
     $this->assertSame('X from encyclopædia', $template->get2('encyclopedia'));
  
     $text = '{{cite journal|url=plato.stanford.edu|work=X}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get2('encyclopedia'));
     $this->assertSame('X', $template->get2('work'));
    
     $text = '{{cite journal|work=X from encyclopædia}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get2('encyclopedia'));
     $this->assertSame('X from encyclopædia', $template->get2('work'));
   }
 
   public function testNonPubs() : void {
     $text = '{{cite book|work=citeseerx.ist.psu.edu}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get2('work'));
     $this->assertSame('citeseerx.ist.psu.edu', $template->get2('title'));
    
     $text = '{{cite book|work=citeseerx.ist.psu.edu|title=Exists}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('work');
     $this->assertNull($template->get2('work'));
     $this->assertSame('Exists', $template->get2('title'));
   }
 
   public function testNullPages() : void {
     $text = '{{cite book|pages=null}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('pages');
     $this->assertNull($template->get2('pages'));
     $template->add_if_new('work', 'null');
     $template->add_if_new('pages', 'null');
     $template->add_if_new('author', 'null');
     $template->add_if_new('journal', 'null');
     $this->assertSame('{{cite book}}', $template->parsed_text());
   }
 
  public function testUpdateYear() : void {
     $text = '{{cite journal|date=2000}}';
     $template = $this->make_citation($text);
     $template->add_if_new('year', (string) date('Y'), 'crossref');
     $this->assertSame((string) date('Y'), $template->get2('year'));
     
     $text = '{{cite journal|year=ZYX}}';
     $template = $this->make_citation($text);
     $template->add_if_new('year', (string) date('Y'), 'crossref');
     $this->assertSame((string) date('Y'), $template->get2('year'));   
   
     $text = '{{cite journal|year=ZYX}}';
     $template = $this->make_citation($text);
     $template->add_if_new('year', (string) date('Y'), 'crossref');
     $this->assertSame((string) date('Y'), $template->get2('year'));   

     $text = '{{cite journal}}';
     $template = $this->make_citation($text);
     $template->add_if_new('year', (string) date('Y'), 'crossref');
     $this->assertSame((string) date('Y'), $template->get2('year'));   

     $text = '{{cite journal|year=1000}}';
     $template = $this->make_citation($text);
     $template->add_if_new('year', (string) ((int) date('Y') - 10), 'crossref');
     $this->assertSame('1000', $template->get2('year'));   
   
     $text = '{{cite journal|date=4000}}';
     $template = $this->make_citation($text);
     $template->add_if_new('year', (string) date('Y'), 'crossref');
     $this->assertSame('4000', $template->get2('date'));

     $text = '{{cite journal|year=4000}}';
     $template = $this->make_citation($text);
     $template->add_if_new('year', (string) date('Y'), 'crossref');
     $this->assertSame('4000', $template->get2('year'));
  }
 
  public function testVerifyDOI() : void {
     $text = '{{cite journal|doi=1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=.1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=0.1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x.full}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x#page_scan_tab_contents}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x/abstract}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.xv2}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x;jsessionid}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x;}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
   
     $text = '{{cite journal|doi=10.1175/1525-7541(2003)004&lt;1147:TVGPCP&gt;2.0.CO;2}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2', $template->get2('doi'));
   
     $text = '{{cite journal|doi=0.5240/7B2F-ED76-31F6-8CFB-4DB9-M}}'; // Not in crossref, and no meta data in DX.DOI.ORG
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.5240/7B2F-ED76-31F6-8CFB-4DB9-M', $template->get2('doi'));
  }
 
  public function testOxfordTemplate() : void {
     $text = '{{cite web |last1=Courtney |first1=W. P. |last2=Hinings |first2=Jessica |title=Woodley, George (bap. 1786, d. 1846) |url=https://doi.org/10.1093/ref:odnb/29929 |website=Oxford Dictionary of National Biography |publisher=Oxford University Press |accessdate=12 September 2019}}';
     $template = $this->process_citation($text);
     $this->assertSame('cite odnb', $template->wikiname());
     $this->assertSame('Woodley, George (bap. 1786, d. 1846)', $template->get2('title'));
     $this->assertNotNull($template->get2('url'));
     $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
     $this->assertNull($template->get2('publisher'));
  }
    // Now with caps in wikiname
   public function testOxfordTemplate2() : void {
     $text = '{{Cite web |last1=Courtney |first1=W. P. |last2=Hinings |first2=Jessica |title=Woodley, George (bap. 1786, d. 1846) |url=https://doi.org/10.1093/ref:odnb/29929 |website=Oxford Dictionary of National Biography |publisher=Oxford University Press |accessdate=12 September 2019}}';
     $template = $this->process_citation($text);
     $this->assertSame('cite odnb', $template->wikiname());
     $this->assertSame('Woodley, George (bap. 1786, d. 1846)', $template->get2('title'));
     $this->assertNotNull($template->get2('url'));
     $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
     $this->assertNull($template->get2('publisher'));
  }
 
  public function testSemanticscholar1() : void {
     $text = '{{cite web|url=https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704}}';
     $template = $this->process_citation($text);
     $this->assertSame('53378830', $template->get2('s2cid')); 
     $this->assertNull($template->get2('s2cid-access'));
     $this->assertSame('cite journal', $template->wikiname());
     $this->assertSame('10.1093/ser/mwp005', strtolower($template->get2('doi')));
     // $this->assertSame('http://www.lisdatacenter.org/wps/liswps/480.pdf', $template->get2('url')); // OA URL
  }
 
   public function testSemanticscholar2() : void {
     $text = '{{cite web|url=https://www.semanticscholar.org/paper/The-Holdridge-life-zones-of-the-conterminous-United-Lugo-Brown/406120529d907d0c7bf96125b83b930ba56f29e4}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1046/j.1365-2699.1999.00329.x', strtolower($template->get2('doi')));
     $this->assertSame('cite journal', $template->wikiname());
     $this->assertNull($template->get2('s2cid-access'));
     $this->assertSame('11733879', $template->get2('s2cid')); 
     $this->assertNotNull($template->get2('url'));
   }

  public function testSemanticscholar3() : void {
     $text = '{{cite web|url=https://pdfs.semanticscholar.org/8805/b4d923bee9c9534373425de81a1ba296d461.pdf }}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1007/978-3-540-78646-7_75', $template->get2('doi'));
     $this->assertSame('cite book', $template->wikiname());
     $this->assertNull($template->get2('s2cid-access'));
     $this->assertSame('1090322', $template->get2('s2cid')); 
     $this->assertNull($template->get2('url'));
  }

  public function testSemanticscholar4() : void { // s2cid does not match and ALL CAPS
     $text = '{{cite web|url=https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704|S2CID=XXXXXX}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('doi'));
     $this->assertSame('cite web', $template->wikiname());
     $this->assertNull($template->get2('s2cid-access'));
     $this->assertSame('XXXXXX', $template->get2('s2cid')); 
     $this->assertSame('https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704', $template->get2('url'));
  }
 
  public function testSemanticscholar41() : void { // s2cid does not match and ALL CAPS AND not cleaned up with initial tidy
     $text = '{{cite web|url=https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704|S2CID=XXXXXX}}';
     $template = $this->make_citation($text);
     $template->get_identifiers_from_url();
     $this->assertSame('XXXXXX', $template->get2('S2CID')); 
     $this->assertSame('https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704', $template->get2('url'));
  }
 
  public function testSemanticscholar5() : void {
     $text = '{{cite web|s2cid=1090322}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1007/978-3-540-78646-7_75', $template->get2('doi'));
     $this->assertSame('cite book', $template->wikiname());
     $this->assertNull($template->get2('s2cid-access'));
     $this->assertSame('1090322', $template->get2('s2cid')); 
     $this->assertNull($template->get2('url'));
  }
 
  public function testJournalIsBookSeries() : void {
     $text = '{{cite journal|journal=advances in enzymology and related areas of molecular biology}}';
     $template = $this->process_citation($text);
     $this->assertSame('cite book', $template->wikiname());
     $this->assertNull($template->get2('journal'));
     $this->assertSame('Advances in Enzymology and Related Areas of Molecular Biology', $template->get2('series')); 
  }

  public function testNameStuff() : void {
     $text = '{{cite journal|author1=[[Robert Jay Charlson|Charlson]] |first1=R. J.}}';
     $template = $this->process_citation($text);
     $this->assertSame('Robert Jay Charlson', $template->get2('author1-link'));
     $this->assertSame('Charlson', $template->get2('last1'));
     $this->assertSame('R. J.', $template->get2('first1'));
     $this->assertNull($template->get2('author1'));
  }

  public function testSaveAccessType() : void {
     $text = '{{cite web|url=http://doi.org/10.1063/1.2833100 |url-access=Tested}}';
     $template = $this->make_citation($text);
     $template->get_identifiers_from_url();
     $this->assertNull($template->get2('doi-access'));
     $this->assertNotNull($template->get2('url-access'));
  }
 
   public function testDontDoIt() : void { // "complete" already
     $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=X|bibcode=X|last1=X|first1=X}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->incomplete());
     $text = '{{cite journal|title=X|periodical=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=X|bibcode=X|last1=X|first1=X}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->incomplete());
  
     $this->requires_bibcode(function() : void {
      $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=X|bibcode=X|last1=X|first1=X}}';
      $template = $this->make_citation($text);
      $this->assertFalse($template->expand_by_adsabs());
      $text = '{{cite journal|title=X|periodical=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=X|bibcode=X|last1=X|first1=X}}';
      $template = $this->make_citation($text);
      $this->assertFalse($template->expand_by_adsabs());
     });
   }
 
  public function testBibcodeRemap() : void {
    $this->requires_bibcode(function() : void {
      $text='{{cite journal|bibcode=2018MNRAS.tmp.2192I}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('2018MNRAS.481..703I', $expanded->get2('bibcode'));
    });
  }

  public function testBibcodeDotEnding() : void {
    $this->requires_bibcode(function() : void {
      $text='{{cite journal|title=Electric Equipment of the Dolomites Railway|journal=Nature|date=2 January 1932|volume=129|issue=3244|page=18|doi=10.1038/129018a0}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('1932Natur.129Q..18.', $expanded->get2('bibcode'));
    });
  }

  public function testBibcodesBooks() : void {
    $this->requires_bibcode(function() : void {
      $text = "{{Cite book|bibcode=1982mcts.book.....H}}";
      $expanded = $this->process_citation($text);
      $this->assertSame('1982', $expanded->get2('year'));
      $this->assertSame('Houk', $expanded->get2('last1'));
      $this->assertSame('N.', $expanded->get2('first1'));
      $this->assertNotNull($expanded->get2('title'));
    });
    $text = "{{Cite book|bibcode=1982mcts.book.....H}}";  // Verify requires_bibcode() works
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('title'));
    $this->assertNull($expanded->get2('year'));
  }
  
  public function testBadBibcodeARXIVPages() : void {
   $this->requires_bibcode(function() : void {
    $text = "{{cite journal|bibcode=1995astro.ph..8159B|pages=8159}}"; // Pages from bibcode have slash in it astro-ph/8159B
    $expanded = $this->process_citation($text);
    $pages = (string) $expanded->get2('pages');
    $this->assertSame(FALSE, stripos($pages, 'astro'));
    $this->assertNull($expanded->get2('journal'));  // if we get a journal, the data is updated and test probably no longer gets bad data
   });
  }
 
  public function testNoBibcodesForArxiv() : void {
   $this->requires_bibcode(function() : void {
    $text = "{{Cite arxiv|last=Sussillo|first=David|last2=Abbott|first2=L. F.|date=2014-12-19|title=Random Walk Initialization for Training Very Deep Feedforward Networks|eprint=1412.6558 |class=cs.NE}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('bibcode'));  // If this eventually gets a journal, we will have to change the test
   });
  }

  public function testNoBibcodesForBookReview() : void {
   $this->requires_bibcode(function() : void {  // don't add isbn. It causes early exit
    $text = "{{cite book |title=Churchill's Bomb: How the United States Overtook Britain in the First Nuclear Arms Race |publisher=X|location=X|lccn=X|oclc=X}}";
    $expanded = $this->make_citation($text);
    $expanded->expand_by_adsabs(); // Won't expand because of bookish stuff
    $this->assertNull($expanded->get2('bibcode'));
   });
  }

  public function testFindBibcodeNoTitle() : void {
   $this->requires_bibcode(function() : void {
    $text = "{{Cite journal | last1 = Glaesemann | first1 = K. R. | last2 = Gordon | first2 = M. S. | last3 = Nakano | first3 = H. | journal = Physical Chemistry Chemical Physics | volume = 1 | issue = 6 | pages = 967–975| year = 1999 |issn = 1463-9076}}";
    $expanded = $this->make_citation($text);
    $expanded->expand_by_adsabs();
    $this->assertSame('1999PCCP....1..967G', $expanded->get2('bibcode'));
   });
  }
 
  public function testFindBibcodeForBook() : void {
   $this->requires_bibcode(function() : void {
    $text = "{{Cite journal | doi=10.2277/0521815363}}";
    $expanded = $this->make_citation($text);
    $expanded->expand_by_adsabs();
    $this->assertSame('2003hoe..book.....K', $expanded->get2('bibcode'));
   });
  }

  public function testZooKeys2() : void {
     $this->requires_secrets(function() : void { // this only works if we can query wikipedia and see if page exists
      $text = '{{Cite journal|journal=[[Zookeys]]}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('[[ZooKeys]]', $expanded->get2('journal'));
     });
  }
 
  public function testJustAnLCCN() : void {
    $this->requires_google(function() : void {
      $text = '{{cite book | lccn=2009925036}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('Alternative Energy for Dummies', $expanded->get2('title'));
    });
  }
 
   public function testRedirectFixing() : void {
    $this->requires_secrets(function() : void {
     $text = '{{cite journal|journal=[[Journal Of Polymer Science]]}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('[[Journal of Polymer Science]]', $template->get2('journal'));
    });
   }
 
    public function testRedirectFixing2() : void {
    $this->requires_secrets(function() : void {
     $text = '{{cite journal|journal=[[Journal Of Polymer Science|"J Poly Sci"]]}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('[[Journal of Polymer Science|J Poly Sci]]', $template->get2('journal'));
    });
   }
 
}
