<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class unpaywallApiTest extends testBaseClass {

    public function testUnpaywallParserAcceptsExpectedShape(): void {
        $result = parse_unpaywall_response(
            '{"journal_name":"Example Journal","best_oa_location":' .
            '{"host_type":"repository","evidence":"oa repository",' .
            '"url_for_landing_page":"https://example.test/article"}}'
        );
        $this->assertNotNull($result);
        $this->assertSame('repository', $result->best_oa_location->host_type);
    }

    public function testUnpaywallParserRejectsMalformedNestedShapes(): void {
        foreach ([
            'not json',
            '[]',
            '{"best_oa_location":[]}',
            '{"best_oa_location":"repository"}',
            '{"best_oa_location":{"host_type":[]}}',
            '{"best_oa_location":{"evidence":{}}}',
            '{"best_oa_location":{"url_for_landing_page":[]}}',
            '{"best_oa_location":{"url":{}}}',
            '{"journal_name":[]}',
        ] as $response) {
            $this->assertNull(parse_unpaywall_response($response));
        }
    }

    public function testUnpaywallParserAllowsNoBestLocation(): void {
        $result = parse_unpaywall_response('{"doi":"10.1000/test","best_oa_location":null}');
        $this->assertNotNull($result);
        $this->assertFalse(isset($result->best_oa_location));
    }

    public function testKnownBadOpenAccessDoiIsRejectedWithoutARequest(): void {
        $template = $this->make_citation('{{cite journal|doi=' . BAD_OA_URL[0] . '}}');
        $this->assertSame('wrong', get_unpaywall_url($template, BAD_OA_URL[0]));
        $this->assertNull($template->get2('url'));
    }

    public function testOpenAccessLookupSkipsCitationsThatAlreadyHaveEnoughInformation(): void {
        $citations = [
            'missing DOI' => '{{cite journal|title=No DOI}}',
            'broken DOI' => '{{cite journal|doi=10.1000/broken|doi-broken-date=2020}}',
            'Oxford DOI' => '{{cite journal|doi=10.1093/example}}',
            'PMC' => '{{cite journal|doi=10.1000/example|pmc=123}}',
            'arXiv' => '{{cite journal|doi=10.1000/example|arxiv=2401.00001}}',
            'eprint' => '{{cite journal|doi=10.1000/example|eprint=2401.00001}}',
            'CiteSeerX' => '{{cite journal|doi=10.1000/example|citeseerx=10.1.1.1.1}}',
            'bioRxiv' => '{{cite journal|doi=10.1000/example|biorxiv=2024.01.01.123456}}',
            'medRxiv' => '{{cite journal|doi=10.1000/example|medrxiv=2024.01.01.123456}}',
            'RFC' => '{{cite journal|doi=10.1000/example|rfc=1234}}',
            'free DOI' => '{{cite journal|doi=10.1000/example|doi-access=free}}',
            'free JSTOR' => '{{cite journal|doi=10.1000/example|jstor=123|jstor-access=free}}',
            'free OSTI' => '{{cite journal|doi=10.1000/example|osti=123|osti-access=free}}',
            'free Handle' => '{{cite journal|doi=10.1000/example|hdl=123/456|hdl-access=free}}',
            'free Open Library' => '{{cite journal|doi=10.1000/example|ol=OL123M|ol-access=free}}',
        ];

        foreach ($citations as $description => $citation) {
            $template = $this->make_citation($citation);
            $before = $template->parsed_text();
            get_open_access_url($template);
            $this->assertSame($before, $template->parsed_text(), 'Citation changed for case: ' . $description);
            $this->assertNull($template->get2('url'), 'URL added for case: ' . $description);
            $this->assertNull($template->get2('chapter-url'), 'Chapter URL added for case: ' . $description);
        }
    }

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
        $text = "{{cite journal|doi=10.1206/0003-0090(2004)286<0001:MPTASO>2.0.CO;2}}";
        $template = $this->make_citation($text);
        $result = get_unpaywall_url($template, $template->get('doi'));
        if ($result === 'rate_limited' || $result === 'url_unreachable') {
            $this->markTestSkipped('Unpaywall API or BHL URL was unavailable (' . $result . ')');
        }
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
