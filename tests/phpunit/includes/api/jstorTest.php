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
}
