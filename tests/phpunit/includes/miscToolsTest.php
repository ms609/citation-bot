<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class miscToolsTest extends testBaseClass {
    public function testcheck_memory_usage(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        check_memory_usage('testcheck_memory_usage');
        $this->assertTrue(true);
    }

    public function testThrottle(): void { // Just runs over the code and basically does nothing
        for ($x = 0; $x <= 25; $x++) {
            throttle();
        }
        $this->assertNull(null);
    }


    public function testCovertUrl2Chapter1(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }
    public function testCovertUrl2Chapter2(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/0}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }
    public function testCovertUrl2Chapter3(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/1}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }
    public function testCovertUrl2Chapter4(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter5(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/232}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNotNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter6(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/chapter/}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNotNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNull($expanded->get2('url'));
    }



    public function testCiteODNB1(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876;jsession=XYZ|doi=10.1093/ref:odnb/wrong_stuff|id=74876}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/ref:odnb/wrong_stuff', $template->get2('doi'));
        $this->assertSame('74876', $template->get2('id'));
        $this->assertSame('https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876', $template->get2('url'));
    }

    public function testCiteODNB2(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876|doi=10.1093/odnb/74876|id=74876}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/ref:odnb/74876', $template->get2('doi'));
        $this->assertSame('74876', $template->get2('id'));
    }

    public function testCiteODNB3(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|doi=10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
    }

    public function testCiteODNB4(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|id=107316}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
        $this->assertNull($template->get2('id'));
    }

    public function testCiteODNB5(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|id=107316|doi=10.0000/Rubbish_bot_failure_test}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
        $this->assertNull($template->get2('id'));
    }

    public function testCiteODNB6(): void {
        $text = '{{Cite ODNB|id=107316|doi=10.1093/odnb/9780198614128.013.107316}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
        $this->assertNull($template->get2('id'));
    }

    public function testCiteODNB7(): void { // Prefer given doi over ID, This is a contrived test
        $text = '{{Cite ODNB|id=107316|doi=10.1038/ncomms14879}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1038/ncomms14879', $template->get2('doi'));
        $this->assertNull($template->get2('id'));
    }

}
