<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class unpaywallApiTest extends testBaseClass {

    public function testOpenAccessLookup1(): void {
        $this->assertNull(null);
        /* TODO - find an example of a DOI that is free on PMC, but not DOI
        $text = '{{cite journal|doi=10.1136/bmj.327.7429.1459}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('300808', $expanded->get2('pmc'));
        */
    }

    public function testOpenAccessLookup3(): void {
        $text = '{{cite journal | vauthors = Shekelle PG, Morton SC, Jungvig LK, Udani J, Spar M, Tu W, J Suttorp M, Coulter I, Newberry SJ, Hardy M | title = Effect of supplemental vitamin E for the prevention and treatment of cardiovascular disease | journal = Journal of General Internal Medicine | volume = 19 | issue = 4 | pages = 380–9 | date = April 2004 | pmid = 15061748 | pmc = 1492195 | doi = 10.1111/j.1525-1497.2004.30090.x }}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('url'));
    }

    public function testOpenAccessLookup4(): void {
        $text = '{{Cite journal | doi = 10.1063/1.4962420| title = Calculating vibrational spectra of molecules using tensor train decomposition| journal = J. Chem. Phys. | volume = 145| year = 2016| issue = 145| pages = 124101| last1 = Rakhuba| first1 = Maxim | last2 = Oseledets | first2 = Ivan| bibcode = 2016JChPh.145l4101R| arxiv =1605.08422}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('url'));
    }

    public function testOpenAccessLookup6(): void {
        $text = '{{Cite journal | doi = 10.5260/chara.18.3.53|hdl=10393/35779}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10393/35779', $expanded->get2('hdl')); // This basically runs through a bunch of code to return 'have free'
    }

    public function testOpenAccessLookup7(): void {
        $text = '{{Cite journal | doi = 10.5260/chara.18.3.53|hdl=10393/XXXXXX}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10393/XXXXXX', $expanded->get2('hdl')); // This basically runs through a bunch of code to return 'have free'
        $this->assertNull($expanded->get2('url'));
    }

    /** Test Unpaywall URL gets added. DOI gets an URL on BHL */
    public function testUnPaywall1(): void {
        $text = "{{cite journal|doi=10.1206/0003-0082(2006)3508[1:EEALSF]2.0.CO;2}}";
        $template = $this->make_citation($text);
        get_unpaywall_url($template, $template->get('doi'));
        $this->assertNotNull($template->get2('url'));
    }

    /** Test Unpaywall OA URL does not get added when doi-access=free */
    public function testUnPaywall2(): void {
        $text = "{{cite journal|doi=10.1145/358589.358596|doi-access=free}}";
        $template = $this->make_citation($text);
        get_unpaywall_url($template, $template->get('doi'));
        $this->assertNull($template->get2('url'));
    }

    public function testUnPaywall3(): void { // This DOI is free and resolves to doi.org
        $text = "{{cite journal|doi=10.1016/j.ifacol.2017.08.010}}";
        $template = $this->make_citation($text);
        get_unpaywall_url($template, $template->get('doi'));
        $this->assertNull($template->get2('url'));
    }
}
