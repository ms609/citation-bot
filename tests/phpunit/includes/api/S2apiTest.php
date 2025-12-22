<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class S2apiTest extends testBaseClass {
    public function testSemanticScholar(): void {
        $text = "{{cite journal|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        $return = get_unpaywall_url($template, $template->get('doi'));
        $this->assertSame('nothing', $return);
        $this->assertNull($template->get2('url'));
    }

    public function testS2CIDlicenseFalse(): void {
        sleep(4);
        $this->assertFalse(get_semanticscholar_license('94502986'));
    }

    public function testS2CIDlicenseTrue(): void {
        sleep(4);
        $this->assertFalse(get_semanticscholar_license('52813129'));
    }

    public function testS2CIDlicenseTrue2(): void {      
        sleep(4);
        $this->assertTrue(get_semanticscholar_license('73436496'));
    }

    public function testSemanticscholar2(): void {
        sleep(4);
        $text = '{{cite web|url=https://www.semanticscholar.org/paper/The-Holdridge-life-zones-of-the-conterminous-United-Lugo-Brown/406120529d907d0c7bf96125b83b930ba56f29e4}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1046/j.1365-2699.1999.00329.x', mb_strtolower($template->get('doi')));
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertNull($template->get2('s2cid-access'));
        $this->assertSame('11733879', $template->get2('s2cid'));
        $this->assertNull($template->get2('url'));
    }

    public function testSemanticscholar3(): void {
        sleep(4);
        $text = '{{cite web|url=https://pdfs.semanticscholar.org/8805/b4d923bee9c9534373425de81a1ba296d461.pdf }}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1007/978-3-540-78646-7_75', $template->get2('doi'));
        $this->assertSame('cite book', $template->wikiname());
        $this->assertNull($template->get2('s2cid-access'));
        $this->assertSame('1090322', $template->get2('s2cid'));
        $this->assertNull($template->get2('url'));
    }

    public function testSemanticscholar4(): void { // s2cid does not match and ALL CAPS
        sleep(4);
        $text = '{{cite web|url=https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704|S2CID=XXXXXX}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('doi'));
        $this->assertSame('cite web', $template->wikiname());
        $this->assertNull($template->get2('s2cid-access'));
        $this->assertSame('XXXXXX', $template->get2('s2cid'));
        $this->assertSame('https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704', $template->get2('url'));
    }

    public function testSemanticscholar41(): void { // s2cid does not match and ALL CAPS AND not cleaned up with initial tidy
        sleep(4);
        $text = '{{cite web|url=https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704|S2CID=XXXXXX}}';
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('XXXXXX', $template->get2('S2CID'));
        $this->assertSame('https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704', $template->get2('url'));
    }

    public function testSemanticscholar42(): void {
        sleep(4);
        $text = '{{cite web|url=https://semanticscholar.org/paper/861fc89e94d8564adc670fbd35c48b2d2f487704|pmc=32414}}'; // has a good free copy
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNull($template->get2('url'));
        $this->assertNotNull($template->get2('s2cid'));
    }

    public function testSemanticscholar5(): void {
        sleep(4);
        $text = '{{cite web|s2cid=1090322}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1007/978-3-540-78646-7_75', $template->get2('doi'));
        $this->assertSame('cite book', $template->wikiname());
        $this->assertNull($template->get2('s2cid-access'));
        $this->assertSame('1090322', $template->get2('s2cid'));
        $this->assertNull($template->get2('url'));
    }

}
