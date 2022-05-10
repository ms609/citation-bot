<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest2 extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
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
    $text = "{{cite journal|archiveurl=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&btnG=Search&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8&oe=Bogus&rct=ABC}}";
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
    $text = "{{cite journal|url=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8&btnG=YUP}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&btnG=YUP', $template->get2('url'));
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
    $this->assertSame('https://go.galegroup.com/STUFF&u=UNIV&date=1234', $template->get2('url'));
  }

  public function testTidy65() : void {
    $text = "{{cite journal|url=https://link.galegroup.com/STUFF&u=UNIV&date=1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://link.galegroup.com/STUFF&u=UNIV&date=1234', $template->get2('url'));
  }
 
  public function testTidy66() : void {
    $text = "{{cite journal|url=https://search.proquest.com/STUFF/docview/1234/STUFF}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.proquest.com/docview/1234/STUFF', $template->get2('url'));
  }
 
 public function testTidy66b() : void {
    $text = "{{cite journal|url=http://host.com/login?url=https://search-proquest-com-stuff/STUFF/docview/1234/34123/342}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
  }
 
 public function testTidy66c() : void {
    $text = "{{cite journal|url=https://search.proquest.com/docview/1234abc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.proquest.com/docview/1234abc', $template->get2('url'));
  }
 
 public function testTidy66d() : void {
    $text = "{{cite journal|url=https://search.proquest.com/openview/1234abc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.proquest.com/openview/1234abc', $template->get2('url'));
  }
 
 public function testTidy67() : void {
    $text = "{{cite journal|url=https://0-search-proquest-com.schoo.org/STUFF/docview/1234/2314/3214}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
  }
 
 public function testTidy67b() : void {
    $text = "{{cite journal|url=https://0-search-proquest-com.scoolaid.net/STUFF/docview/1234/2314/3214}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
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
    $this->assertSame('https://www.proquest.com/dissertations/docview/1234', $template->get2('url'));
  }
 
  public function testTidy70() : void {
    $text = "{{cite journal|url=https://search.proquest.com/docview/1234/fulltext}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
  }
 
  public function testTidy70b() : void {
    $text = "{{cite journal|url=https://search.proquest.com/docview/1234?account=XXXXX}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
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
 
   public function testTidy89() : void {
    $text = "{{cite web|asin=0671748750}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('asin');
    $this->assertSame("0671748750", $template->get2('isbn'));
    $this->assertNull($template->get2('asin'));
    
    $text = "{{cite web|asin=6371748750}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('asin');
    $this->assertSame("6371748750", $template->get2('asin'));
    $this->assertNull($template->get2('isbn'));
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
    Template::$date_style = DATES_MDY;
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('January 20, 2010', $template->get2('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    Template::$date_style = DATES_DMY;       
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('20 January 2010', $template->get2('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    Template::$date_style = DATES_WHATEVER;   
    $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
    $this->assertSame('20 JAN 2010', $template->get2('archive-date'));
    $text = "{{cite journal}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->add_if_new('archive-date', 'SDAFEWFEWW#F#WFWEFESFEFSDFDFD'));
    $this->assertNull($template->get2('archive-date'));
  }
 
  public function testIssuesOnly() : void {
    $text = "{{cite journal |last=Rauhut |first=O.W. |year=2004 |title=The interrelationships and evolution of basal theropod dinosaurs |journal=Special Papers in Palaeontology |volume=69 |pages=213}}";
    $template = $this->process_citation($text); // Does not drop year - terrible bug
    $this->assertSame($text, $template->parsed_text());
  }
 
  public function testAccessDate1() : void {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    Template::$date_style = DATES_MDY;
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
    $this->assertSame('January 20, 2010', $template->get2('access-date'));
  }
 
  public function testAccessDate2() : void {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    Template::$date_style = DATES_DMY;       
    $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
    $this->assertSame('20 January 2010', $template->get2('access-date'));
  }
 
  public function testAccessDate3() : void {
    $text = "{{cite journal|url=XXX}}";
    $template = $this->make_citation($text);
    Template::$date_style = DATES_WHATEVER;   
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
    $text = "{{cite journal|doi=10.0000/Rubbish_bot_failure_test}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
    $this->assertSame('1 DEC 2019', $template->get2('doi-broken-date'));
  }

  public function testAddBrokenDateFormat2() : void {
    $text = "{{cite journal|doi=10.0000/Rubbish_bot_failure_test}}";
    $template = $this->make_citation($text);
    Template::$date_style = DATES_MDY;
    $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
    $this->assertSame('December 1, 2019', $template->get2('doi-broken-date'));
  }
 
  public function testAddBrokenDateFormat3() : void {
    $text = "{{cite journal|doi=10.0000/Rubbish_bot_failure_test}}";
    $template = $this->make_citation($text);
    Template::$date_style = DATES_DMY;
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
      
  public function testRenameSpecialCases1() : void {
    $text = "{{cite web|id=x}}";
    $template = $this->make_citation($text);
    $template->rename('work', 'work');
    $template->rename('work', 'work', 'new');
    $this->assertSame('new', $template->get2('work'));
  }
 
  public function testRenameSpecialCases2() : void {
    $text = "{{cite web|id=x}}";
    $template = $this->make_citation($text);
    $template->rename('work', 'journal');
    $template->rename('work', 'journal', 'new');
    $this->assertSame('New', $template->get2('journal'));
  }
 
  public function testRenameSpecialCases3() : void {
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
 
  public function testTidyMR() : void {
    $text = "{{cite web|mr=mr2343}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('mr');
    $this->assertSame('2343', $template->get2('mr'));
  }
 
  public function testTidyAgency() : void {
    $text = "{{cite web|agency=associated press|url=apnews.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('agency');
    $this->assertSame('associated press', $template->get2('work'));
    $this->assertNull($template->get2('agency'));
  }
 
  public function testTidyClass() : void {
    $text = "{{cite web|class=|series=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('class');
    $this->assertNull($template->get2('class'));
  }

   public function testTidyMonth() : void {
    $text = "{{cite web|day=3|month=Dec|year=2000}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('month');
    $this->assertNull($template->get2('day'));
    $this->assertNull($template->get2('month'));
    $this->assertSame('3 Dec 2000', $template->get2('date'));
    
    $text = "{{cite web|month=Dec|year=2000}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('month');
    $this->assertNull($template->get2('day'));
    $this->assertNull($template->get2('month'));
    $this->assertSame('Dec 2000', $template->get2('date'));
  }
 
  public function testTidyDeadurl() : void {
    $text = "{{cite web|deadurl=y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('deadurl');
    $this->assertNull($template->get2('deadurl'));
    $this->assertNull($template->get2('dead-url'));
    $this->assertSame('dead', $template->get2('url-status'));
   
    $text = "{{cite web|dead-url=alive}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('dead-url');
    $this->assertNull($template->get2('deadurl'));
    $this->assertNull($template->get2('dead-url'));
    $this->assertSame('live', $template->get2('url-status'));
  }

  public function testTidyLastAmp() : void {
    $text = "{{cite web|lastauthoramp=false}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('lastauthoramp');
    $this->assertNull($template->get2('last-author-amp'));
    $this->assertNull($template->get2('lastauthoramp'));
    $this->assertNull($template->get2('name-list-style'));
    
    $text = "{{cite web|last-author-amp=yes}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('last-author-amp');
    $this->assertNull($template->get2('last-author-amp'));
    $this->assertNull($template->get2('lastauthoramp'));
    $this->assertSame('amp', $template->get2('name-list-style'));
  }

  public function testTidyLaySummary() : void {
    $text = "{{cite web|laysummary=}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('laysummary');
    $this->assertNull($template->get2('laysummary'));
    
    $text = "{{cite web|lay-summary=http://cnn.com/}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('lay-summary');
    $this->assertNull($template->get2('lay-summary'));
    $this->assertSame('http://cnn.com/', $template->get2('lay-url'));
  }
 
  public function testTidyOIDOI() : void {
    $text = "{{cite web|doi=10.1093/oi/authority.9876543210|url=http://oxfordreference.com/view/10.1093/oi/authority.9876543210}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('doi');
    $this->assertNull($template->get2('doi'));
  }
 
  public function testTidyEPILDOI() : void {
    $text = "{{cite web|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1206|doi=10.1093/law:epil/9780199231690/law-9780199231690-e1206}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('doi');
    $this->assertNull($template->get2('doi'));
  }
 
  public function testTidyPeriodicalQuotes() : void {
    $text = "{{cite web|journal=‘X’}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('journal');
    $this->assertSame("'X'", $template->get2('journal'));
  }
 
  public function testTidyPublisherLinks() : void {
    $text = "{{cite web|publisher=[[XYZ|X]]|journal=X}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('[[XYZ|X]]', $template->get2('journal'));
    $this->assertNull($template->get2('publisher'));
  }

  public function testTidyPublisherGoogleSupport() : void {
    $text = "{{cite web|publisher=google hive mind|url=support.google.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Google Inc.', $template->get2('publisher'));
  }

  public function testTidyPublisherGoogleBlog() : void {
    $text = "{{cite web|publisher=google land hive mind|url=http://github.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('google land hive mind', $template->get2('publisher'));
  }

  public function testTidyPublisherAndWebsite() : void {
    $text = "{{cite web|publisher=New York Times|website=www.newyorktimes.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('New York Times', $template->get2('work'));
    $this->assertNull($template->get2('publisher'));
    $this->assertNull($template->get2('website'));
  }

  public function testTidyPublisherAndWorks() : void {
    $text = "{{cite web|publisher=new york times digital archive|journal=new york times communications llc}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('new york times communications llc', $template->get2('journal'));
    $this->assertNull($template->get2('publisher'));
  }

  public function testTidyTimesArchive() : void {
    $text = "{{cite web|publisher=the times digital archive.}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('The Times Digital Archive', $template->get2('publisher'));
  }
           
  public function testTidyTimesArchiveAndWork() : void {
    $text = "{{cite web|publisher=the times digital archive|newspaper=the times}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
  }

  public function testTidyWPandLegacy() : void {
    $text = "{{cite web|publisher=the washington post – via legacy.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Legacy.com', $template->get2('via'));
    $this->assertSame('[[The Washington Post]]', $template->get2('publisher'));
  }
 
  public function testTidyWPandWork() : void {
    $text = "{{cite web|publisher=the washington post websites|website=washingtonpost.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[The Washington Post]]', $template->get2('website'));
   
    $text = "{{cite web|publisher=the washington post websites|website=washington post}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('washington post', $template->get2('website'));
  }
 
  public function testTidyWPandDepartment() : void {
    $text = "{{cite web|publisher=the washington post|work=entertainment}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('entertainment', $template->get2('department'));
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('the washington post', $template->get2('newspaper'));
  }
 
  public function testTidySDandDepartment() : void {
    $text = "{{cite web|publisher=san diego union tribune|work=entertainment}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('entertainment', $template->get2('department'));
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('san diego union tribune', $template->get2('work'));
  }

  public function testTidyNYTandWorks() : void {
    $text = "{{cite web|publisher=the new york times (subscription required)|website=new york times website}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('new york times website', $template->get2('website'));
   
    $text = "{{cite web|publisher=the new york times|website=nytimes.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[The New York Times]]', $template->get2('website'));
  }
                    
  public function testTidySJMNandWorks() : void {
    $text = "{{cite web|publisher=san jose mercury news|website=mercury news}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('mercury news', $template->get2('website'));
   
    $text = "{{cite web|publisher=san jose mercury-news|website=mercurynews.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[San Jose Mercury News]]', $template->get2('website'));
  }
      
  public function testTidySDllc() : void {
    $text = "{{cite web|publisher=the san diego union tribune, LLC}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('The San Diego Union-Tribune', $template->get2('publisher'));
  }

  public function testTidyForbes() : void {
    $text = "{{cite web|publisher=forbes (forbes Media)}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Forbes Media', $template->get2('publisher'));
   
    $text = "{{cite web|publisher=Forbes, inc.}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Forbes', $template->get2('publisher'));
   
    $text = "{{cite web|publisher=forbes.com llc™}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Forbes', $template->get2('publisher'));
   
    $text = "{{cite web|publisher=forbes publishing company}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Forbes Publishing', $template->get2('publisher'));
  }       

  public function testTidyForbesandWorks() : void {
    $text = "{{cite web|publisher=forbes publishing|website=forbes website}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('forbes website', $template->get2('website'));
   
    $text = "{{cite web|publisher=forbes.com|website=from the website forbes.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[Forbes]]', $template->get2('website'));
  }
 
  public function testTidyForbesandAgency1() : void {
    $text = "{{cite web|publisher=forbes publishing|website=AFX News}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Forbes Publishing', $template->get2('publisher'));
    $this->assertSame('AFX News', $template->get2('agency'));
  }
 
  public function testTidyForbesandAgency2() : void {
    $text = "{{cite web|publisher=forbes.com|website=Thomson Financial News}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Forbes', $template->get2('publisher'));
    $this->assertSame('Thomson Financial News', $template->get2('agency'));
  }

  public function testTidyLATimes1() : void {
    $text = "{{cite web|publisher=the la times|work=This is a work that stays}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Los Angeles Times', $template->get2('publisher'));
  }
 
  public function testTidyLATimes2() : void {
    $text = "{{cite web|publisher=[[the la times]]|work=This is a work that stays}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('[[Los Angeles Times]]', $template->get2('publisher'));
  }  
              

  public function testTidyLAandWorks() : void {
    $text = "{{cite web|publisher=Los Angeles Times|website=los angeles times and stuff}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('los angeles times and stuff', $template->get2('website'));
   
    $text = "{{cite web|publisher=Los Angeles Times|website=latimes.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[Los Angeles Times]]', $template->get2('website'));
  }          

   public function testLAandVia() : void {
    $text = "{{cite web|publisher=Los Angeles Times|website=laweekly.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('Los Angeles Times', $template->get2('publisher'));
    $this->assertSame('laweekly.com', $template->get2('via'));
   }
              
   public function testNoPublisherNeeded() : void {
    $text = "{{cite web|publisher=This is Just Random Stuff|website=new york times magazine}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('new york times magazine', $template->get2('website'));
   }
 
   public function testTidyGaurdian1() : void {
    $text = "{{cite web|publisher=the guardian media group|work=This is a work that stays}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('This is a work that stays', $template->get2('work'));
    $this->assertSame('the guardian media group', $template->get2('publisher'));
  }
 
   public function testTidyGaurdian2() : void {
    $text = "{{cite web|publisher=the guardian media group|work=theguardian.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[The Guardian]]', $template->get2('work'));
  }

  public function testTidyEcon1() : void {
    $text = "{{cite web|publisher=the economist group|work=This is a work that stays}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('This is a work that stays', $template->get2('work'));
    $this->assertSame('the economist group', $template->get2('publisher'));
  }
 
   public function testTidyEcon2() : void {
    $text = "{{cite web|publisher=the economist group|work=economist.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[The Economist]]', $template->get2('work'));
  }


  public function testTidySD1() : void {
    $text = "{{cite web|publisher=the san diego union tribune|work=This is a work that stays}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertSame('This is a work that stays', $template->get2('work'));
    $this->assertSame('the san diego union tribune', $template->get2('publisher'));
  }
 
   public function testTidySD2() : void {
    $text = "{{cite web|publisher=the san diego union tribune|work=SignOnSanDiego.com}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[The San Diego Union-Tribune]]', $template->get2('work'));
  }
 
  public function testTidyNewsUk() : void {
    $text = "{{cite web|publisher=news UK|work=thetimes.co.uk}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('publisher');
    $this->assertNull($template->get2('publisher'));
    $this->assertSame('[[The Times]]', $template->get2('work'));
  }
 
   public function testRefComment() : void {
    $text = "{{cite web|ref=harv <!--  -->}}";
    $template = $this->process_citation($text);
    $this->assertSame('<!--  -->', $template->get2('ref'));
   }           
              
   public function testCleanBloomArchives() : void {
    $text = "{{cite web|archive-url=https://web.archive.org/web/434132432/https://www.bloomberg.com/tosv2.html?1}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archive-url');
    $this->assertNull($template->get2('archive-url'));
   }
 
   public function testCleanGoogleArchives() : void {
    $text = "{{cite web|archive-url=https://web.archive.org/web/434132432/https://apis.google.com/js/plusone.js}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archive-url');
    $this->assertNull($template->get2('archive-url'));
    
    $text = "{{cite web|archive-url=https://web.archive.org/web/434132432/https://www.google-analytics.com/ga.js}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('archive-url');
    $this->assertNull($template->get2('archive-url'));
   }        
           
   public function testCleanebscohost() : void {
    $text = "{{cite web|url=ebscohost.com?AN=1234}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('http://connection.ebscohost.com/c/articles/1234', $template->get2('url'));
   }                

   public function testNormalizeOxford() : void {
    $text = "{{cite web|url=http://latinamericanhistory.oxfordre.com/XYZ}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://oxfordre.com/latinamericanhistory/XYZ', $template->get2('url'));
   } 

   public function testShortenOxford() : void {
    $text = "{{cite web|url=https://oxfordre.com/latinamericanhistory/latinamericanhistory/latinamericanhistory/XYZ}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://oxfordre.com/latinamericanhistory/XYZ', $template->get2('url'));
   }

   public function testAnonymizeOxford() : void {
    $text = "{{cite web|url=https://www.oxforddnb.com/X;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.oxforddnb.com/X', $template->get2('url'));
    
    $text = "{{cite web|url=https://www.anb.org/x;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.anb.org/x', $template->get2('url'));
    
    $text = "{{cite web|url=https://www.oxfordartonline.com/Y;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.oxfordartonline.com/Y', $template->get2('url'));
    
    $text = "{{cite web|url=https://www.ukwhoswho.com/z;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.ukwhoswho.com/z', $template->get2('url'));
    
    $text = "{{cite web|url=https://www.oxfordmusiconline.com/z;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://www.oxfordmusiconline.com/z', $template->get2('url'));
    
    $text = "{{cite web|url=https://oxfordre.com/z;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://oxfordre.com/z', $template->get2('url'));
    
    $text = "{{cite web|url=https://oxfordaasc.com/z;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://oxfordaasc.com/z', $template->get2('url'));
    
    $text = "{{cite web|url=https://oxfordreference.com/z;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://oxfordreference.com/z', $template->get2('url'));
    
    $text = "{{cite web|url=https://oxford.universitypressscholarship.com/z;jsession?print}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('https://oxford.universitypressscholarship.com/z', $template->get2('url'));
   }       
          

   public function testOxforddnbDOIs() : void {
    $text = "{{cite web|url=https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-33369|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/ref:odnb/33369', $template->get2('doi'));
    $this->assertSame('978-0-19-861412-8', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
    
    $text = "{{cite web|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-108196|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y|title=Joe Blow - Oxford Dictionary of National Biography}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
    $this->assertSame('978-0-19-861412-8', $template->get2('isbn'));
    $this->assertSame('Joe Blow', $template->get2('title'));
    $this->assertNull($template->get2('doi-broken-date'));
   }       
       
   public function testANBDOIs() : void {
    $text = "{{cite web|url=https://www.anb.org/view/10.1093/anb/9780198606697.001.0001/anb-9780198606697-e-1800262|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/anb/9780198606697.article.1800262', $template->get2('doi'));
    $this->assertSame('978-0-19-860669-7', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
    
   public function testArtDOIs() : void {
    $text = "{{cite web|url=https://www.oxfordartonline.com/benezit/view/10.1093/benz/9780199773787.001.0001/acref-9780199773787-e-00183827|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/benz/9780199773787.article.B00183827', $template->get2('doi'));
    $this->assertSame('978-0-19-977378-7', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }

   public function testGroveDOIs() : void {
    $text = "{{cite web|url=https://www.oxfordartonline.com/groveart/view/10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-7000082129|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/gao/9781884446054.article.T082129', $template->get2('doi'));
    $this->assertSame('978-1-884446-05-4', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }

   public function testGroveDOIs2() : void {
    $text = "{{cite web|url=https://www.oxfordartonline.com/groveart/view/10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-7002085714|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/gao/9781884446054.article.T2085714', $template->get2('doi'));
    $this->assertSame('978-1-884446-05-4', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }

   public function testAASCDOIs() : void {
    $text = "{{cite web|url=https://oxfordaasc.com/view/10.1093/acref/9780195301731.001.0001/acref-9780195301731-e-41463|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acref/9780195301731.013.41463', $template->get2('doi'));
    $this->assertSame('978-0-19-530173-1', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }    

   public function testWhoWhoDOIs() : void {
    $text = "{{cite web|url=https://www.ukwhoswho.com/view/10.1093/ww/9780199540891.001.0001/ww-9780199540884-e-37305|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/ww/9780199540884.013.U37305', $template->get2('doi'));
    $this->assertSame('978-0-19-954089-1', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }  
 
   public function testMusicDOIs() : void {
    $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-0000040055|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/gmo/9781561592630.article.40055', $template->get2('doi'));
    $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }  

   public function testMusicDOIsA() : void {
    $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-1002242442|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/gmo/9781561592630.article.A2242442', $template->get2('doi'));
    $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   } 
 
   public function testMusicDOIsO() : void {
    $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-5000008391|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/gmo/9781561592630.article.O008391', $template->get2('doi'));
    $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   } 

   public function testMusicDOIsL() : void {
    $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-4002232256|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/gmo/9781561592630.article.L2232256', $template->get2('doi'));
    $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
 
   public function testMusicDOIsJ() : void {
    $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-2000095300|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/gmo/9781561592630.article.J095300', $template->get2('doi'));
    $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
 
   public function testLatinDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/latinamericanhistory/view/10.1093/acrefore/9780199366439.001.0001/acrefore-9780199366439-e-2|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780199366439.013.2', $template->get2('doi'));
    $this->assertSame('978-0-19-936643-9', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
 
 
   public function testEnvDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/environmentalscience/view/10.1093/acrefore/9780199389414.001.0001/acrefore-9780199389414-e-224|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780199389414.013.224', $template->get2('doi'));
    $this->assertSame('978-0-19-938941-4', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   } 

   public function testAmHistDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/view/10.1093/acrefore/9780199329175.001.0001/acrefore-9780199329175-e-17|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780199329175.013.17', $template->get2('doi'));
    $this->assertSame('978-0-19-932917-5', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }    

   public function testAfHistDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/africanhistory/view/10.1093/acrefore/9780190277734.001.0001/acrefore-9780190277734-e-191|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780190277734.013.191', $template->get2('doi'));
    $this->assertSame('978-0-19-027773-4', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }     

   public function testIntStudDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/view/10.1093/acrefore/9780190846626.001.0001/acrefore-9780190846626-e-39|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780190846626.013.39', $template->get2('doi'));
    $this->assertSame('978-0-19-084662-6', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
           
   public function testClimateDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/climatescience/view/10.1093/acrefore/9780190228620.001.0001/acrefore-9780190228620-e-699|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780190228620.013.699', $template->get2('doi'));
    $this->assertSame('978-0-19-022862-0', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
           
   public function testReligionDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/religion/view/10.1093/acrefore/9780199340378.001.0001/acrefore-9780199340378-e-568|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780199340378.013.568', $template->get2('doi'));
    $this->assertSame('978-0-19-934037-8', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
           
   public function testAnthroDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/anthropology/view/10.1093/acrefore/9780190854584.001.0001/acrefore-9780190854584-e-45|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780190854584.013.45', $template->get2('doi'));
    $this->assertSame('978-0-19-085458-4', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
 
   public function testClassicsDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/classics/view/10.1093/acrefore/9780199381135.001.0001/acrefore-9780199381135-e-7023|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780199381135.013.7023', $template->get2('doi'));
    $this->assertSame('978-0-19-938113-5', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
 
   public function testPsychDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/psychology/view/10.1093/acrefore/9780190236557.001.0001/acrefore-9780190236557-e-384|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780190236557.013.384', $template->get2('doi'));
    $this->assertSame('978-0-19-023655-7', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }     
           
   public function testPoliDOIs() : void {
    $text = "{{cite web|url=https://oxfordre.com/politics/view/10.1093/acrefore/9780190228637.001.0001/acrefore-9780190228637-e-181|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/acrefore/9780190228637.013.181', $template->get2('doi'));
    $this->assertSame('978-0-19-022863-7', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }     

   public function testOxPressDOIs() : void {
    $text = "{{cite web|url=https://oxford.universitypressscholarship.com/view/10.1093/oso/9780190124786.001.0001/oso-9780190124786|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/oso/9780190124786.001.0001', $template->get2('doi'));
    $this->assertSame('978-0-19-012478-6', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }      
           
   public function testMedDOIs() : void {
    $text = "{{cite web|url=https://oxfordmedicine.com/view/10.1093/med/9780199592548.001.0001/med-9780199592548-chapter-199|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/med/9780199592548.003.0199', $template->get2('doi'));
    $this->assertSame('978-0-19-959254-8', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
 
   public function testUnPressScholDOIs() : void {
    $text = "{{cite web|url=https://oxford.universitypressscholarship.com/view/10.1093/oso/9780198814122.001.0001/oso-9780198814122-chapter-5|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/oso/9780198814122.003.0005', $template->get2('doi'));
    $this->assertSame('978-0-19-881412-2', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
   }
 
   public function testOxHandbookDOIs() : void {
    $text = "{{cite web|url=https://www.oxfordhandbooks.com/view/10.1093/oxfordhb/9780198824633.001.0001/oxfordhb-9780198824633-e-1|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('url');
    $this->assertSame('10.1093/oxfordhb/9780198824633.013.1', $template->get2('doi'));
    $this->assertSame('978-0-19-882463-3', $template->get2('isbn'));
    $this->assertNull($template->get2('doi-broken-date'));
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
   
    $text = "{{cite web|url=http://search.proquest.com/docview/12341234|title=X|id=<!--- --->}}";  // Blocked by comment
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
 
  public function testConversionOfURL8() : void {
    $text = "{{cite web|url=https://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.483.8892|title=Xyz|pmc=341322|doi-access=free|doi=10.0000/Rubbish_bot_failure_test}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
  }

  public function testConversionOfURL9() : void {
    $text = "{{cite web|url=https://ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dfastool=sumsearch.org&&id=123456|title=Xyz|pmc=123456}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
  }
 
  public function testConversionOfURL10() : void {
    $text = "{{cite web|url=https://ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dfastool=sumsearch.org&&id=123456|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNotNull($template->get2('url'));
  }

 
  public function testConversionOfURL10B() : void {
    $text = "{{cite web|url=https://ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dfastool=sumsearch.org&&id=123456|pmid=123456|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
  }

  public function testConversionOfURL11() : void {
    $text = "{{cite web|url=https://zbmath.org/?q=an:7511.33034|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
  }

  public function testConversionOfURL12() : void {
    $text = "{{cite web|url=https://www.osti.gov/biblio/1760327-generic-advanced-computing-framework-executing-windows-based-dynamic-contingency-analysis-tool-parallel-cluster-machines|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
  }
 
  public function testConversionOfURL13() : void {
    $text = "{{cite web|url=https://zbmath.org/?q=an:75.1133.34|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
  }

  public function testConversionOfURL14() : void {
    $text = "{{cite web|url=https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234231|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
  }

  public function testConversionOfURL15() : void {
    $text = '{{cite web | url=https://www.osti.gov/energycitations/product.biblio.jsp?osti_id=2341|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}';
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
  }
 
  public function testVolumeIssueDemixing21() : void {
    $text = '{{cite journal|issue = volume 12|doi=10.0000/Rubbish_bot_failure_test}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get2('volume'));
    $this->assertNull($prepared->get2('issue'));
  }
 
  public function testVolumeIssueDemixing22() : void {
    $text = '{{cite journal|issue = volume 12XX|volume=12XX|doi=10.0000/Rubbish_bot_failure_test}}';
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
   
    $text = "{{cite web|url=http://worldcat.org/title/stuff/oclc/1234&referer=brief_results}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234', $template->get2('oclc'));
    $this->assertSame('https://worldcat.org/title/stuff/oclc/1234', $template->get2('url'));
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
    $this->assertSame("{{cite web| author1=George }}", $template->parsed_text());

    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->validate_and_add('author1', 'George Billy@hotmail.com', 'Sam @Hashtag', '', FALSE);
    $this->assertSame("{{cite web| last1=George | first1=Sam }}", $template->parsed_text());

    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->validate_and_add('author1', 'com', 'Sam', '', FALSE);
    $this->assertSame("{{cite web| last1=Com | first1=Sam }}", $template->parsed_text());
   
    $text = "{{cite web}}";
    $template = $this->make_citation($text);
    $template->validate_and_add('author1', '',  'George @Hashtags', '', FALSE);
    $this->assertSame("{{cite web| author1=George }}", $template->parsed_text());
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
     $this->assertNull($template->get2('arxiv'));
     $this->assertNull($template->get2('eprint'));
     $this->assertSame('http://arxiv.org/X/abs/3XXX41222342343242', $template->get2('url'));
     $this->assertSame('cite web', $template->wikiname());
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
     $text='{{Cite journal | doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=1999}}';
     $template = $this->make_citation($text);
     $this->assertTrue($template->add_if_new('doi', '10.1063/1.2263373'));
     $this->assertSame('10.1063/1.2263373', $template->get2('doi'));
   }
 
   public function testDropBadDOI() : void {
     $text='{{Cite journal | doi=10.1063/1.2263373|chapter-url=http://dx.doi.org/10.0000/Rubbish_bot_failure_test}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1063/1.2263373', $template->get2('doi'));
     $this->assertNotNull($template->get2('chapter-url'));
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
     $this->assertSame('{{Cite journal| page=33 }}', $template->parsed_text());

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
     $this->assertSame('3 May 1999', $template->get2('accessdate'));
     $this->assertNull($template->get2('access-date'));
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
 
   public function testSuppressWarnings() : void {
     $text='{{Cite journal |doi=((10.51134/sod.2013.039 )) }}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('doi-broken-date'));
     $this->assertNotNull($template->get2('journal'));
     $this->assertSame('10.51134/sod.2013.039', $template->get2('doi'));
     $this->assertSame('((10.51134/sod.2013.039 ))', $template->get3('doi'));
   }
 
   public function testAddEditorFalse() : void {
     $text='{{Cite journal |display-editors = 5 }}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->add_if_new('display-editors', '5'));
   }
                        
   public function testPMCEmbargo() : void {
     $text='{{Cite journal|pmc-embargo-date=January 22, 2020}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('pmc-embargo-date'));
    
     $text='{{Cite journal|pmc-embargo-date=January 22, 2090}}';
     $template = $this->process_citation($text);
     $this->assertSame('January 22, 2090', $template->get2('pmc-embargo-date'));
    
     $text='{{Cite journal|pmc-embargo-date=}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('pmc-embargo-date'));
    
     $text='{{Cite journal}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->add_if_new('pmc-embargo-date', 'November 15, 1990'));
     $this->assertFalse($template->add_if_new('pmc-embargo-date', 'November 15, 2010'));
     $this->assertFalse($template->add_if_new('pmc-embargo-date', 'November 15, 3010'));
     $this->assertTrue($template->add_if_new('pmc-embargo-date', 'November 15, 2090'));
     $this->assertFalse($template->add_if_new('pmc-embargo-date', 'November 15, 2080'));
   }

   public function testOnlineFirst() : void {
     $text='{{Cite journal|volume=Online First}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('volume'));
    
     $text='{{Cite journal|issue=Online First}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('issue'));
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
 
    public function testIDconvert12() : void {
     $text = '{{cite journal|id=<small></small>}}';
     $page = $this->process_page($text);
     $this->assertSame('{{cite journal}}', $page->parsed_text());
     $text = '{{cite journal|id=<small> </small>}}';
     $page = $this->process_page($text);
     $this->assertSame('{{cite journal}}', $page->parsed_text());
    }
 
    public function testIDconvert13() : void {
     $text = '{{cite journal|id=<small>{{MR|396410}}</small>}}';
     $page = $this->process_page($text);
     $this->assertSame('{{cite journal|mr=396410 }}', $page->parsed_text());
     $text = '{{cite journal|id=<small> </small>{{MR|396410}}}}';
     $page = $this->process_page($text);
     $this->assertSame('{{cite journal|mr=396410 }}', $page->parsed_text());
    }
 
 
    public function testIDconvert14() : void {
     $text = '{{cite journal|id=dafdasfd PMID 3432413 324214324324 }}';
     $template = $this->process_citation($text);
     $this->assertSame('3432413', $template->get2('pmid'));
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
     Template::$date_style = DATES_MDY;
     $template->add_if_new('date', '12-02-2019');
     $this->assertSame('February 12, 2019', $template->get2('date'));
     $template = $this->make_citation($text);
     Template::$date_style = DATES_DMY;
     $template->add_if_new('date', '12-02-2019');
     $this->assertSame('12 February 2019', $template->get2('date'));
     $template = $this->make_citation($text);
     Template::$date_style = DATES_WHATEVER;
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
 
   public function testTidyTAXON() : void {
     $text = '{{cite journal|journal=TAXON}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('journal');
     $this->assertSame('Taxon', $template->get2('journal'));
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
     $text = '{{cite journal|publisher=[[Abc|Abc]]|journal=Abc}}';
     $template = $this->make_citation($text);
     $template->tidy_parameter('publisher');
     $this->assertNull($template->get2('publisher'));
     $this->assertSame('[[abc|abc]]', strtolower($template->get2('journal'))); // Might "fix" Abc redirect to ABC
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
 
  public function testVerifyDOI1() : void {
     $text = '{{cite journal|doi=1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI2() : void {
     $text = '{{cite journal|doi=.1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI3() : void {
     $text = '{{cite journal|doi=0.1111/j.1471-0528.1995.tb09132.x}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI4() : void {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x.full}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI5() : void {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x#page_scan_tab_contents}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI6() : void {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x/abstract}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI7() : void {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.xv2}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI8() : void {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x;jsessionid}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI9() : void {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI10() : void {
     $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x;}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
  }

   public function testVerifyDOI11() : void {
     $text = '{{cite journal|doi=10.1175/1525-7541(2003)004&lt;1147:TVGPCP&gt;2.0.CO;2}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2', $template->get2('doi'));
  }

   public function testVerifyDOI12() : void {
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
 
  public function testCiteODNB1() : void {
     $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876;jsession=XYZ|doi=10.1093/ref:odnb/wrong_stuff|id=74876}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1093/ref:odnb/wrong_stuff', $template->get2('doi'));
     $this->assertSame('74876', $template->get2('id'));
     $this->assertSame('https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876', $template->get2('url'));
  }
 
  public function testCiteODNB2() : void {
     $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876|doi=10.1093/odnb/74876|id=74876}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1093/ref:odnb/74876', $template->get2('doi'));
     $this->assertSame('74876', $template->get2('id'));
  }
 
  public function testCiteODNB3() : void {
     $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|doi=10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
  }
   
  public function testCiteODNB4() : void {
     $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|id=107316}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
     $this->assertNull($template->get2('id'));
  }

  public function testCiteODNB5() : void {
     $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|id=107316|doi=10.0000/Rubbish_bot_failure_test}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
     $this->assertNull($template->get2('id'));
  }
 
  public function testSemanticscholar1() : void {
     $text = '{{cite web|url=https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704}}';
     $template = $this->process_citation($text);
     $this->assertSame('53378830', $template->get2('s2cid')); 
     $this->assertNull($template->get2('s2cid-access'));
     $this->assertSame('cite journal', $template->wikiname());
     $this->assertSame('10.1093/ser/mwp005', strtolower($template->get('doi')));
     // $this->assertSame('http://www.lisdatacenter.org/wps/liswps/480.pdf', $template->get2('url')); // OA URL
  }
 
   public function testSemanticscholar2() : void {
     $text = '{{cite web|url=https://www.semanticscholar.org/paper/The-Holdridge-life-zones-of-the-conterminous-United-Lugo-Brown/406120529d907d0c7bf96125b83b930ba56f29e4}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1046/j.1365-2699.1999.00329.x', strtolower($template->get('doi')));
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
 
  public function testSemanticscholar42() : void {
     $text = '{{cite web|url=https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704|pmc=32414}}'; // has a good free copy
     $template = $this->make_citation($text);
     $template->get_identifiers_from_url();
     $this->assertNull($template->get2('url')); 
     $this->assertNotNull($template->get2('s2cid'));
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
     $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=10.0000/Rubbish_bot_failure_test|bibcode=X|last1=X|first1=X}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->incomplete());
     $text = '{{cite journal|title=X|periodical=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=10.0000/Rubbish_bot_failure_test|bibcode=X|last1=X|first1=X}}';
     $template = $this->make_citation($text);
     $this->assertFalse($template->incomplete());
  
     $this->requires_bibcode(function() : void {
      $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=10.0000/Rubbish_bot_failure_test|bibcode=X|last1=X|first1=X}}';
      $template = $this->make_citation($text);
      $this->assertFalse($template->expand_by_adsabs());
      $text = '{{cite journal|title=X|periodical=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=10.0000/Rubbish_bot_failure_test|bibcode=X|last1=X|first1=X}}';
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
      $text = '{{Cite journal|journal=[[Zookeys]]}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('[[ZooKeys]]', $expanded->get2('journal'));
  }
 
   public function testRedirectFixing() : void {
     $text = '{{cite journal|journal=[[Journal Of Polymer Science]]}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('[[Journal of Polymer Science]]', $template->get2('journal'));
   }
 
    public function testRedirectFixing2() : void {
     $text = '{{cite journal|journal=[[Journal Of Polymer Science|"J Poly Sci"]]}}';
     $template = $this->prepare_citation($text);
     $this->assertSame('[[Journal of Polymer Science|J Poly Sci]]', $template->get2('journal'));
   }
 
    public function test1093DoiStuff1() : void {
     $text = '{{cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|via=hose}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('hose', $template->get2('work'));
    }
 
    public function test1093DoiStuff2() : void {
     $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('hose', $template->get2('work'));
    }
 
    public function test1093DoiStuff3() : void {
     $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|via=Hose}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('hose', $template->get2('work'));
    }
 
    public function test1093DoiStuff4() : void {
     $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=kittens|via=doggies}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('kittens via doggies', $template->get2('work'));
   }
 
     public function test1093DoiStuff5() : void {
     $text = '{{cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|via=hose}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('Hose', $template->get2('journal'));
    }
 
    public function test1093DoiStuff6() : void {
     $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('Hose', $template->get2('journal'));
    }
 
    public function test1093DoiStuff7() : void {
     $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|via=Hose}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('Hose', $template->get2('journal'));
    }
 
    public function test1093DoiStuff8() : void {
     $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=kittens|via=doggies}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('Kittens Via Doggies', $template->get2('journal'));
   }
 
 
    public function testFixURLinLocation() : void {
     $text = '{{cite journal|location=http://www.apple.com/indes.html}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('location'));
     $this->assertSame('http://www.apple.com/indes.html', $template->get2('url'));
     
     $text = '{{cite journal|location=http://www.apple.com/indes.html|url=http://www.apple.com/}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('location'));
     $this->assertSame('http://www.apple.com/indes.html', $template->get2('url'));
     
     $text = '{{cite journal|url=http://www.apple.com/indes.html|location=http://www.apple.com/}}';
     $template = $this->process_citation($text);
     $this->assertNull($template->get2('location'));
     $this->assertSame('http://www.apple.com/indes.html', $template->get2('url'));
     
     $text = '{{cite journal|url=http://www.apple.com/indes.html|location=http://www.ibm.com/}}';
     $template = $this->process_citation($text);
     $this->assertSame('http://www.ibm.com/', $template->get2('location'));
     $this->assertSame('http://www.apple.com/indes.html', $template->get2('url'));
   }
 
   public function testVcite() : void {
     $text = '{{vcite journal|doi=10.0000/Rubbish_bot_failure_test}}';
     $template = $this->process_citation($text);
     $this->assertNotNull($template->get2('doi-broken-date'));
   }
 
   public function testAddingJunk() : void {
     $text = '{{cite journal}}';
     $template = $this->make_citation($text);
     $template->add_if_new('title', 'n/A');
     $template->add_if_new('journal', 'Undefined');
     $this->assertSame($text, $template->parsed_text());
   }
 
   public function testCleanBritArchive() : void {
     $text = '{{Cite web|title=Register {{!}} British Newspaper Archive|url=https://www.britishnewspaperarchive.co.uk/account/register?countrykey=0&showgiftvoucherclaimingoptions=false&gift=false&nextpage=%2faccount%2flogin%3freturnurl%3d%252fviewer%252fbl%252f0003125%252f18850804%252f069%252f0004&rememberme=false&cookietracking=false&partnershipkey=0&newsletter=false&offers=false&registerreason=none&showsubscriptionoptions=false&showcouponmessaging=false&showfreetrialmessaging=false&showregisteroptions=false&showloginoptions=false&isonlyupgradeable=false|access-date=2022-02-17|website=www.britishnewspaperarchive.co.uk}}';
     $template = $this->process_citation($text);
     $this->assertSame('[[British Newspaper Archive]]', $template->get2('via'));
   }
 
   public function testHealthAffairs() : void {
     $text = '{{Cite web|url=https://www.healthaffairs.org/do/10.1377/hblog20180605.966625/full/|archiveurl=healthaffairs.org}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1377/forefront.20180605.966625', $template->get2('doi'));
     $this->assertSame('https://www.healthaffairs.org/do/10.1377/forefront.20180605.966625/full/', $template->get2('url'));
     $this->assertNull($template->get2('archiveurl'));
   }
 
  public function testAddExitingThings1() : void {
    $text = "{{Cite web}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('publisher',  'Springer Zone'));
    $this->assertFalse($expanded->add_if_new('publisher', 'Goodbye dead'));
  }
 
   public function testAddExitingThings2() : void {
    $text = "{{Cite web}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('location',  'Springer Zone'));
    $this->assertFalse($expanded->add_if_new('location', 'Goodbye dead'));
  }
 
   public function testAddExitingThings3() : void {
    $text = "{{Cite web}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('website',  'Springer Zone'));
    $this->assertFalse($expanded->add_if_new('website', 'Goodbye dead'));
  }

   public function testAllSortsOfBadData() : void {
    $text = "{{Cite journal|journal=arXiv|title=[No title found]|issue=null|volume=n/a|page=n/a|pages=null|pmc=1}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('{{Cite journal|journal=arXiv|title=[No title found]|volume=n/a|page=n/a|pmc=1}}', $expanded->parsed_text());
    
    $text = "{{Cite journal|journal=arXiv|title=[No TITLE found]|issue=null|volume=n/a|page=n/a|pages=null|pmc=1}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('{{Cite journal|journal=arXiv|title=[No TITLE found]|volume=n/a|page=n/a|pmc=1}}', $expanded->parsed_text());
  }
 
   public function testTidyUpNA() : void {
    $text = "{{Cite journal|volume=n/a|issue=3}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('volume'));

    $text = "{{Cite journal|issue=n/a|volume=3}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->get2('issue'));
  }

   public function testTestExistingVerifiedData() : void {
    $text = "{{Cite journal|volume=((4))}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->set('volume', '3'));
  }
 
  public function testTidyWebsites() : void {
    $text = "{{Cite web|website=Undefined}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('website');
    $this->assertNull($expanded->get2('website'));
   
    $text = "{{Cite web|website=latimes.com}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('website');
    $this->AssertSame('[[Los Angeles Times]]', $expanded->get2('website'));
   
    $text = "{{Cite web|website=nytimes.com}}";
     $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('website');
    $this->AssertSame('[[The New York Times]]', $expanded->get2('website'));
   
    $text = "{{Cite web|website=The Times Digital Archive}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('website');
    $this->AssertSame('[[The Times]]', $expanded->get2('website'));
   
    $text = "{{Cite web|website=electronic gaming monthly}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('website');
    $this->AssertSame('electronic gaming monthly', $expanded->get2('magazine'));
    $this->AssertSame('cite magazine', $expanded->wikiname());

    $text = "{{Cite web|website=the economist}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('website');
    $this->AssertSame('the economist', $expanded->get2('newspaper'));
    $this->AssertSame('cite news', $expanded->wikiname());
  }
 
  public function testTidyWorkers() : void { 
    $text = "{{Cite web|work=latimes.com}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('work');
    $this->AssertSame('[[Los Angeles Times]]', $expanded->get2('work'));
   
    $text = "{{Cite web|work=nytimes.com}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('work');
    $this->AssertSame('[[The New York Times]]', $expanded->get2('work'));
   
    $text = "{{Cite web|work=The Times Digital Archive}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('work');
    $this->AssertSame('[[The Times]]', $expanded->get2('work'));
  }
 
  public function testHasNoIssuesAtAll() : void {
    $text = "{{Cite journal|journal=oceanic linguistics special publications|issue=3|volume=5}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('issue');
    $this->AssertNull($expanded->get2('issue'));
    $this->AssertSame('5', $expanded->get2('volume'));
   
    $text = "{{Cite journal|journal=oceanic linguistics special publications|issue=3}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('issue');
    $this->AssertNull($expanded->get2('issue'));
    $this->AssertSame('3', $expanded->get2('volume'));
   
    $text = "{{Cite journal|journal=oceanic linguistics special publications}}";
    $expanded = $this->make_citation($text);
    $expanded->add_if_new('issue', '3');
    $this->AssertNull($expanded->get2('issue'));
    $this->AssertSame('3', $expanded->get2('volume'));
  }

   public function testTidyBadArchives() : void {
    $text = "{{Cite web|archive-url=https://www.britishnewspaperarchive.co.uk/account/register/dsfads}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('archive-url');
    $this->AssertNull($expanded->get2('archive-url'));

    $text = "{{Cite web|archive-url=https://meta.wikimedia.org/w/index.php?title=Special:UserLogin:DSFadsfds}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('archive-url');
    $this->AssertNull($expanded->get2('archive-url'));
   }

  public function testTidyBadISSN() : void {
    $text = "{{Cite web|issn=1111222X}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('issn');
    $this->AssertSame('1111-222X', $expanded->get2('issn'));
   
    $text = "{{Cite web|issn=1111-222x}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('issn');
    $this->AssertSame('1111-222X', $expanded->get2('issn'));
  }

   public function testTidyBadPeriodical() {
    $text = "{{Cite web|periodical=Undefined}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('periodical');
    $this->AssertNull($expanded->get2('periodical'));

    $text = "{{Cite web|periodical=medrxiv}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('periodical');
    $this->AssertNull($expanded->get2('periodical'));
    $this->AssertSame('medRxiv', $expanded->get2('work'));
    $this->AssertSame('cite document', $expanded->wikiname());
   }
 
   public function testTidyGoogleSupport() : void {
    $text = "{{Cite web|url=https://support.google.com/hello|publisher=Proudly made by the google plex}}";
    $expanded = $this->make_citation($text);
    $expanded->tidy_parameter('publisher');
    $this->AssertSame('Google Inc.', $expanded->get2('publisher'));
   }
 
   public function testTidyURLStatus() : void {
      $text = "{{cite web|url=http://x.com/|deadurl=sì}}";
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('deadurl');
      $this->AssertSame('dead', $expanded->get2('url-status'));
      $this->AssertNull($expanded->get2('deadurl'));
    
      $text = "{{cite web|url=http://x.com/|deadurl=live}}";
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('deadurl');
      $this->AssertSame('live', $expanded->get2('url-status'));
      $this->AssertNull($expanded->get2('deadurl'));
   }
 
   public function testTidyMonth2() : void {
      $text = "{{cite web|date=March 2000|month=march|day=11}}";
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('month');
      $this->AssertNull($expanded->get2('day'));
      $this->AssertNull($expanded->get2('month'));
   }
 
   public function testCulturalAdvice() : void {
      $text = "{{cite web|chapter=Cultural Advice|chapter-url=http://anu.edu.au}}";
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('chapter');
      $this->AssertNull($expanded->get2('chapter'));
      $this->AssertNull($expanded->get2('chapter-url'));
      $this->AssertSame('http://anu.edu.au', $expanded->get2('url'));
   }
   
   public function testChangeNameReject() : void {
      $text = "{{cite document|work=medrxiv}}";
      $expanded = $this->make_citation($text);
      $expanded->change_name_to('cite journal');
      $this->AssertSame('cite document', $expanded->wikiname());
   }
    
   public function testCapsNewPublisher() : void {
      $text = "{{cite web}}";
      $expanded = $this->make_citation($text);
      $expanded->add_if_new('publisher', 'EXPANSIONISM');
      $this->AssertSame('Expansionism', $expanded->get2('publisher'));
    
      $text = "{{cite web}}";
      $expanded = $this->make_citation($text);
      $expanded->add_if_new('publisher', 'EXPANSIONISM');
      $this->AssertSame('Expansionism', $expanded->get2('publisher'));
   }
    
   public function testAddAreManyThings() : void {
      $text = "{{cite news}}";
      $expanded = $this->make_citation($text);
      $expanded->add_if_new('newspaper', 'Rock Paper Shotgun');
      $this->AssertNull($expanded->get2('website'));
      $this->AssertSame('Rock Paper Shotgun', $expanded->get2('newspaper'));
   }
    
   public function testBloomWithVia() : void {
      $text = "{{cite news|via=bloomberg web services and such}}";
      $expanded = $this->make_citation($text);
      $expanded->add_if_new('newspaper', 'The Bloomberg is the way to go');
      $this->AssertNull($expanded->get2('via'));
   }
    
   public function testURLhiding() : void {
      $text = "{{cite journal|citeseerx=https://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.88.5725}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('10.1.1.88.5725', $expanded->get2('citeseerx'));
   }

   public function testURLhiding2() : void {
      $text = "{{cite journal|citeseerx=https://apple.com/stuff}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame($text, $expanded->parsed_text());
   }
    
   public function testCleanArxivDOI() : void {
      $text = "{{cite journal|doi=10.48550/arXiv.1234.56789}}";
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->AssertNull($expanded->get2('doi'));
      $this->AssertSame('1234.56789', $expanded->get2('eprint'));

      $text = "{{cite journal|doi=10.48550/arXiv.1234.56789|eprint=1234.56789}}";
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->AssertNull($expanded->get2('doi'));
      $this->AssertSame('1234.56789', $expanded->get2('eprint'));

      $text = "{{cite journal|doi=10.48550/arXiv.1234.56789|arxiv=1234.56789}}";
      $expanded = $this->make_citation($text);
      $expanded->tidy_parameter('doi');
      $this->AssertNull($expanded->get2('doi'));
      $this->AssertSame('1234.56789', $expanded->get2('arxiv'));
   }
 
   public function testAddCodeIfThisFails() : void { // Add more oxford code, if these start to work
      $this->AssertFalse(doi_works('10.1093/acref/9780199208951.013.q-author-00005-00000991')); // https://www.oxfordreference.com/view/10.1093/acref/9780199208951.001.0001/q-author-00005-00000991
      $this->AssertFalse(doi_works('10.1093/oao/9781884446054.013.8000020158')); // https://www.oxfordartonline.com/groveart/view/10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-8000020158
   }
 
   public function testGoogleBooksCleanup1() : void {
      $text = "{{cite LSA|url=https://books.google.com/booksid=12345}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
   }
   public function testGoogleBooksCleanup2() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?vid=12345}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
   }
   public function testGoogleBooksCleanup3() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?qid=12345}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
   }
   public function testGoogleBooksCleanup4() : void {
      $text = "{{cite LSA|url=https://books.google.com/?id=12345}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
   }
   public function testGoogleBooksCleanup5() : void {
      $text = "{{cite LSA|url=https://books.google.uk.co/books?isbn=12345}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?isbn=12345', $expanded->get2('url'));
    }
 
    public function testGoogleBooksHashCleanup1() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&q=xyz#q=abc}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }
 
    public function testGoogleBooksHashCleanup2() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&dq=xyz#q=abc}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup3() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&dq=abc#q=abc}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup4() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345#q=abc}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup5() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&vq=abc}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }
 
    public function testGoogleBooksHashCleanup6() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&vq=abc&pg=3214&q=xyz}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=abc&pg=3214', $expanded->get2('url'));
    }
 
    public function testGoogleBooksHashCleanup7() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&lpg=1234}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&pg=1234', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup8() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&q=isbn}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=isbn', $expanded->get2('url'));
     
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&q=isbn1234}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
     
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&q=inauthor:34123123}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup9() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&dq=isbn}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=isbn', $expanded->get2('url'));

      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&dq=isbn1234}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
     
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&dq=inauthor:34123123}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }
 
    public function testGoogleBooksHashCleanup10() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&d=ser&pg=3241&lpg=321&article_id=3241&sitesec=reviews}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&sitesec=reviews', $expanded->get2('url'));
    }
 
    public function testGoogleBooksHashCleanup11() : void {
      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&article_id=3241}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&article_id=3241', $expanded->get2('url'));

      $text = "{{cite LSA|url=https://books.google.com/books?id=12345&article_id=3241&q=huh}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('https://books.google.com/books?id=12345&q=huh&article_id=3241#v=onepage', $expanded->get2('url'));
    }
 
    public function testLotsOfZeros() : void {
      $text = "{{cite journal|volume=0000000000000|issue=00000000000}}";
      $expanded = $this->process_citation($text);
      $this->AssertNull($expanded->get2('volume'));
      $this->AssertNull($expanded->get2('issue'));
    }
 
    public function testWorkToMag() : void {
      $text = "{{cite journal|work=The New Yorker}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('The New Yorker', $expanded->get2('magazine'));
      $this->AssertNull($expanded->get2('work'));
      $this->AssertSame('cite magazine', $expanded->wikiname());
    }
 
    public function testWorkAgency() : void {
      $text = "{{cite news|work=Reuters|url=SomeThingElse.com}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('Reuters', $expanded->get2('agency'));
      $this->AssertNull($expanded->get2('work'));
    }
 
    public function testWorkofAmazon() : void {
      $text = "{{cite book|title=Has One|work=Amazon Inc}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('Has One', $expanded->get2('title'));
      $this->AssertNull($expanded->get2('publisher'));
    }
 
    public function testAbbrInPublisher() : void {
      $text = "{{cite web|publisher=nytc|work=new york times}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('new york times', $expanded->get2('work'));
      $this->AssertNull($expanded->get2('publisher'));

      $text = "{{cite web|publisher=nyt|work=new york times}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('new york times', $expanded->get2('work'));
      $this->AssertNull($expanded->get2('publisher'));
     

      $text = "{{cite web|publisher=wpc|work=washington post}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('washington post', $expanded->get2('newspaper'));
      $this->AssertNull($expanded->get2('publisher'));
    }

    public function testDoiThatFailsWeird() : void {
      $text = "{{cite web|doi=10.1111/j.1475-4983.2002.32412432423423421314324234233242314234|year=2002}}"; // Special Papers in Palaeontology - they do not work
      $expanded = $this->process_citation($text);
      $this->AssertNull($expanded->get2('doi'));
    }
 
    public function testBadURLStatusSettings1() : void {
      $text = "{{cite web|url-status=sì|url=X}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('dead', $expanded->get2('url-status'));
    }
    public function testBadURLStatusSettings2() : void {
      $text = "{{cite web|url-status=no|url=X}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('live', $expanded->get2('url-status'));
    }
    public function testBadURLStatusSettings3() : void {
      $text = "{{cite web|url-status=sì|url=X|archive-url=Y}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('dead', $expanded->get2('url-status'));
    }
    public function testBadURLStatusSettings4() : void {
      $text = "{{cite web|url-status=no|url=X|archive-url=Y}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('live', $expanded->get2('url-status'));
    }
    public function testBadURLStatusSettings5() : void {
      $text = "{{cite web|url-status=dead|url=X|archive-url=Y}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('dead', $expanded->get2('url-status'));
    }
    public function testBadURLStatusSettings6() : void {
      $text = "{{cite web|url-status=live|url=X|archive-url=Y}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('live', $expanded->get2('url-status'));
    }
 
     public function testCiteDocument() : void {
      $text = "{{cite document|url=x|website=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite web', $expanded->wikiname());
     
      $text = "{{cite document|url=x|magazine=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite magazine', $expanded->wikiname());  

      $text = "{{cite document|url=x|encyclopedia=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite encyclopedia', $expanded->wikiname());  

      $text = "{{cite document|url=x|journal=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite journal', $expanded->wikiname());  

      $text = "{{cite document|url=x|website=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite web', $expanded->wikiname());
     
      $text = "{{cite document|url=x|magazine=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite magazine', $expanded->wikiname());  

      $text = "{{cite document|url=x|encyclopedia=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite encyclopedia', $expanded->wikiname());  

      $text = "{{cite document|url=x|journal=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite journal', $expanded->wikiname());  
    }

    public function testCitePaper() : void {
      $text = "{{cite paper|url=x|website=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite web', $expanded->wikiname());
     
      $text = "{{cite paper|url=x|magazine=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite magazine', $expanded->wikiname());  

      $text = "{{cite paper|url=x|encyclopedia=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite encyclopedia', $expanded->wikiname());  

      $text = "{{cite paper|url=x|journal=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite journal', $expanded->wikiname());  

      $text = "{{Cite paper|url=x|website=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite web', $expanded->wikiname());
     
      $text = "{{Cite paper|url=x|magazine=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite magazine', $expanded->wikiname());  

      $text = "{{Cite paper|url=x|encyclopedia=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite encyclopedia', $expanded->wikiname());  

      $text = "{{Cite paper|url=x|journal=x}}";
      $expanded = $this->process_citation($text);
      $this->AssertSame('cite journal', $expanded->wikiname());  
    }
    
    public function testAreManyThings() : void {
      $text = "{{Cite web}}";
      $expanded = $this->make_citation($text);
      $expanded->add_if_new('newspaper', 'Ballotpedia');
      $this->AssertSame('Ballotpedia', $expanded->get2('website')); 
     
      $text = "{{Cite news}}";
      $expanded = $this->make_citation($text);
      $expanded->add_if_new('newspaper', 'Ballotpedia');
      $this->AssertSame('Ballotpedia', $expanded->get2('newspaper'));  
    }

}
