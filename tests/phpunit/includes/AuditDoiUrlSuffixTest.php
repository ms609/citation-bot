<?php
declare(strict_types=1);

/**
 * A publisher page suffix is cut from a DOI, but a DOI whose own suffix merely
 * starts with one of those words is left whole.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditDoiUrlSuffixTest extends testBaseClass {

    #[DataProvider('doiProvider')]
    public function testSuffixTrimming(string $input, string $expected): void {
        $this->assertSame($expected, trim_doi_url_suffix($input));
    }

    public function testExtractDoiWithClosingMarkupWithoutResolver(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub DOI resolution.');
        }

        uopz_set_return('doi_works', null);
        try {
            $doi = '10.1038/nature11111';
            $this->assertSame($doi, extract_doi($doi . '/figures</span>')[1]);
            $this->assertSame($doi, extract_doi($doi . '/epdfplus</i>')[1]);
            $this->assertSame('10.4000/abstractairanica.4242', extract_doi('10.4000/abstractairanica.4242')[1]);
        } finally {
            uopz_unset_return('doi_works');
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function doiProvider(): array {
        return [
            'suffix starts with abstract' => ['10.4000/abstractairanica.4242', '10.4000/abstractairanica.4242'],
            'suffix starts with full' => ['10.1234/fullerene.2020.1', '10.1234/fullerene.2020.1'],
            'suffix starts with pdf' => ['10.5555/pdfa-guide.7', '10.5555/pdfa-guide.7'],
            'suffix starts with figure' => ['10.1016/figures.2019.03.001', '10.1016/figures.2019.03.001'],
            'later segment starts with full' => ['10.1234/abc/fullerene', '10.1234/abc/fullerene'],
            'trailing full' => ['10.3389/fpsyg.2015.00001/full', '10.3389/fpsyg.2015.00001'],
            'trailing abstract' => ['10.1002/anie.201900001/abstract', '10.1002/anie.201900001'],
            'trailing pdf' => ['10.1111/j.1365-2958.2010.07165.x/pdf', '10.1111/j.1365-2958.2010.07165.x'],
            'trailing epdf with query' => ['10.1021/ja00000a001/epdf?x=1', '10.1021/ja00000a001'],
            'trailing fulltext.html' => ['10.1007/s00134-019-05678-0/fulltext.html', '10.1007/s00134-019-05678-0'],
            'trailing figure path' => ['10.1093/nar/gkz123/figure/1', '10.1093/nar/gkz123'],
            'query string' => ['10.1000/182?utm=x', '10.1000/182'],
            'markup' => ['10.1000/182</span>', '10.1000/182'],
            'encoded slash' => ['10.1000%2F182/full', '10.1000%2F182'],
            'nothing to cut' => ['10.1000/182', '10.1000/182'],
        ];
    }
}
