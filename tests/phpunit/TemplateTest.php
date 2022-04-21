<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
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
     $this->assertSame('hose', $template->get2('journal'));
    }
 
    public function test1093DoiStuff6() : void {
     $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('hose', $template->get2('journal'));
    }
 
    public function test1093DoiStuff7() : void {
     $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|via=Hose}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('hose', $template->get2('journal'));
    }
 
    public function test1093DoiStuff8() : void {
     $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=kittens|via=doggies}}';
     $template = $this->make_citation($text);
     $template->forget('url');
     $this->assertNull($template->get2('url'));
     $this->assertNull($template->get2('via'));
     $this->assertNull($template->get2('website'));
     $this->assertSame('cite document', $template->wikiname());
     $this->assertSame('kittens via doggies', $template->get2('journal'));
   }

}
