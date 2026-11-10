<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class piiAPITest extends testBaseClass {

    public function testParsePIIDOIResponse(): void {
        $this->assertSame(
            '10.1016/j.jsbmb.2019.105494',
            parse_pii_doi_response('<root><prism:doi>10.1016/j.jsbmb.2019.105494</prism:doi></root>')
        );
        $this->assertSame('', parse_pii_doi_response('<root><prism:doi>invalid</prism:doi></root>'));
        $this->assertSame('', parse_pii_doi_response(''));
    }

    public function testPII(): void {
        new TestPage(); // Fill page name with test name for debugging
        $pii = 'S0960076019302699';
        $doi_expect = '10.1016/j.jsbmb.2019.105494';
        $doi = get_doi_from_pii($pii);
        if ($doi === '') {
            $this->markTestSkipped('Elsevier PII API did not return a DOI (network, rate limit, or outage)');
        }
        $this->assertSame($doi_expect, $doi);
    }
}
