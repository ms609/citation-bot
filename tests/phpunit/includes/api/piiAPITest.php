<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class piiAPITest extends testBaseClass {

    public function testPII(): void {
        new TestPage(); // Fill page name with test name for debugging
        $pii = 'S0960076019302699';
        $doi_expect = '10.1016/j.jsbmb.2019.105494';
        $doi = get_doi_from_pii($pii);
        $this->assertSame($doi_expect, $doi);
    }
}
