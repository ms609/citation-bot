<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Identifiers that are already in a citation are cleaned up in tidy(), the same
 * way malformed arXiv, bibcode and PMC values already are.  add_if_new() has
 * always refused to create these values; these tests lock in the tidy side.
 */
final class TidyMalformedIdentifiersTest extends testBaseClass {

    public function testArxivAndEprintUseTheSameTidyRules(): void {
        foreach (['arxiv', 'eprint'] as $parameter) {
            $prefix = '{{cite web|title=Example|' . $parameter . '=';
            $template = $this->make_citation($prefix . '2401.00001}}');
            $template->tidy_parameter($parameter);
            $this->assertSame('cite arxiv', $template->wikiname());
            $this->assertSame('2401.00001', $template->get2($parameter));

            $commented = $prefix . '<!-- leave -->}}';
            $template = $this->make_citation($commented);
            $template->tidy_parameter($parameter);
            $this->assertSame($commented, $template->parsed_text());

            $template = $this->make_citation($prefix . 'not-an-id}}');
            $template->tidy_parameter($parameter);
            $this->assertNull($template->get2($parameter));
        }
    }

    public function testMalformedSsrnIsRemoved(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|ssrn=abc}}');
        $template->tidy();
        $this->assertNull($template->get2('ssrn'));
    }

    public function testSsrnLabelIsStripped(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|ssrn=SSRN 1234567}}');
        $template->tidy();
        $this->assertSame('1234567', $template->get2('ssrn'));
    }

    public function testSsrnWithLabelAndColonIsStripped(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|ssrn=ssrn: 1234567}}');
        $template->tidy();
        $this->assertSame('1234567', $template->get2('ssrn'));
    }

    public function testNumericSsrnOutsideKnownRangeIsKept(): void {
        // The range in ssrn_valid() will age; never delete a number someone typed because of it
        $template = $this->make_citation('{{cite journal|title=X|journal=J|ssrn=99}}');
        $template->tidy();
        $this->assertSame('99', $template->get2('ssrn'));
        $template = $this->make_citation('{{cite journal|title=X|journal=J|ssrn=9999999}}');
        $template->tidy();
        $this->assertSame('9999999', $template->get2('ssrn'));
    }

    public function testValidSsrnIsUntouched(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|ssrn=1234567}}');
        $template->tidy();
        $this->assertSame('1234567', $template->get2('ssrn'));
    }

    public function testCiteSsrnKeepsItsIdentifier(): void {
        // Removing the only identifier would leave {{cite ssrn}} with nothing to render
        $template = $this->make_citation('{{cite ssrn|ssrn=abc|title=X}}');
        $template->tidy();
        $this->assertSame('abc', $template->get2('ssrn'));
    }

    public function testMalformedS2cidIsRemoved(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|s2cid=abc}}');
        $template->tidy();
        $this->assertNull($template->get2('s2cid'));
    }

    public function testS2cidLabelIsStripped(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|s2cid=CorpusID:12345678}}');
        $template->tidy();
        $this->assertSame('12345678', $template->get2('s2cid'));
        $template = $this->make_citation('{{cite journal|title=X|journal=J|s2cid=S2CID 12345678}}');
        $template->tidy();
        $this->assertSame('12345678', $template->get2('s2cid'));
    }

    public function testValidS2cidIsUntouched(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|s2cid=12345678}}');
        $template->tidy();
        $this->assertSame('12345678', $template->get2('s2cid'));
    }

    public function testMalformedOclcIsRemoved(): void {
        $template = $this->make_citation('{{cite book|title=X|oclc=abc}}');
        $template->tidy();
        $this->assertNull($template->get2('oclc'));
    }

    public function testOclcLabelIsStripped(): void {
        $template = $this->make_citation('{{cite book|title=X|oclc=OCLC 12345678}}');
        $template->tidy();
        $this->assertSame('12345678', $template->get2('oclc'));
        $template = $this->make_citation('{{cite book|title=X|oclc=OCLC: 12345678}}');
        $template->tidy();
        $this->assertSame('12345678', $template->get2('oclc'));
    }

    public function testValidOclcFormsAreUntouched(): void {
        foreach (['12345678', 'ocm12345678', 'ocn123456789', 'on1234567890', '(OCoLC)12345678'] as $oclc) {
            $template = $this->make_citation('{{cite book|title=X|oclc=' . $oclc . '}}');
            $template->tidy();
            $this->assertSame($oclc, $template->get2('oclc'));
        }
    }

    public function testOclcWithDigitsButOddShapeIsKept(): void {
        // Not repairable, but there is a number in it: leave it for a human
        $template = $this->make_citation('{{cite book|title=X|oclc=12345 and 67890}}');
        $template->tidy();
        $this->assertSame('12345 and 67890', $template->get2('oclc'));
    }

    public function testHdlTrailingPunctuationIsRemoved(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|hdl=10.1000/abcdef.}}');
        $template->tidy();
        $this->assertSame('10.1000/abcdef', $template->get2('hdl'));
        $template = $this->make_citation('{{cite journal|title=X|journal=J|hdl=10.1000/abcdef,}}');
        $template->tidy();
        $this->assertSame('10.1000/abcdef', $template->get2('hdl'));
    }

    public function testHdlWithInternalDotsIsUntouched(): void {
        $template = $this->make_citation('{{cite journal|title=X|journal=J|hdl=10.1000/abc.def.ghi}}');
        $template->tidy();
        $this->assertSame('10.1000/abc.def.ghi', $template->get2('hdl'));
    }
}
