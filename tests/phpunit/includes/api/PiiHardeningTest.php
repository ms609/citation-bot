<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class PiiHardeningTest extends testBaseClass {
    public function testPiiParserExtractsBoundedDoi(): void {
        $this->assertSame(
            '10.1000/example',
            parse_pii_doi_response('<root><prism:doi>10.1000/example</prism:doi></root>')
        );
    }

    public function testPiiParserRejectsMalformedOrOversizedValues(): void {
        $this->assertSame('', parse_pii_doi_response('not xml'));
        $this->assertSame('', parse_pii_doi_response(
            '<prism:doi>10.1000/bad<unexpected></prism:doi>'
        ));
        $this->assertSame('', parse_pii_doi_response(
            '<prism:doi>10.1000/' . str_repeat('x', 600) . '</prism:doi>'
        ));
    }
}
