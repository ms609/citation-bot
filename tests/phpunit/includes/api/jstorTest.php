<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class jstorTest extends testBaseClass {
    public function testJstor1(): void {
        $text = "{{cite journal|url=https://jstor.org/stable/832414?seq=1234}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertSame('832414', $template->get2('jstor'));
    }

    public function testJstor2(): void {
        $text = "{{cite journal|jstor=832414?seq=1234}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertNull($template->get2('url'));
    }

    public function testJstor3(): void {
        $text = "{{cite journal|jstor=123 123}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testJstor4(): void {
        $text = "{{cite journal|jstor=i832414}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testJstor5(): void {
        $text = "{{cite journal|jstor=4059223|title=This is not the right title}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testJstorGoofyRIS(): void {
        $text = "{{cite book| jstor=resrep24545| title=Safeguarding Digital Democracy Digital Innovation and Democracy Initiative Roadmap}}";
        $prepared = $this->process_citation($text);
        $this->assertSame('Kornbluh', $prepared->get2('last1'));
    }

    public function testJstorExpansion1(): void {
        $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true|website=i found this online}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('cite journal', $prepared->wikiname());
        $this->assertSame('1701972', $prepared->get2('jstor'));
        $this->assertNotNull($prepared->get2('website'));
    }

    public function testJstorExpansion2(): void {
        $text = "{{Cite journal | url=http://www.jstor.org/stable/10.2307/40237667|jstor=}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('40237667', $prepared->get2('jstor'));
        $this->assertNull($prepared->get2('doi'));
        $this->assertSame(2, mb_substr_count($prepared->parsed_text(), 'jstor'));  // Verify that we do not have both jstor= and jstor=40237667.   Formerly testOverwriteBlanks()
    }

    public function testJstorExpansion4(): void {
        $text = '{{cite web | via = UTF8 characters from JSTOR | url = https://www.jstor.org/stable/27695659}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Mórdha', $expanded->get2('last1'));
    }

    public function testJstorExpansion5(): void {
        $text = '{{cite journal | url = https://www-jstor-org.school.edu/stable/10.7249/mg1078a.10?seq=1#metadata_info_tab_contents }}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.7249/mg1078a.10', $expanded->get2('jstor'));
    }

    public function testRISJstorExpansion(): void {
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

    public function testDrop10_2307(): void {
        $text = "{{Cite journal | jstor=10.2307/40237667}}";  // This should get cleaned up in tidy
        $prepared = $this->prepare_citation($text);
        $this->assertSame('40237667', $prepared->get2('jstor'));
    }

    public function testExpansionJstorBook(): void {
        $text = '{{Cite journal|url=https://www.jstor.org/stable/j.ctt6wp6td.10}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Verstraete', $expanded->get2('last1'));
    }
    
}
