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

    public function testExtractDoiWithClosingDelimitersWithoutResolver(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('uopz is required to stub DOI resolution.');
        }

        $this->assertTrue(uopz_set_return('doi_works', null));
        try {
            $doi = '10.1038/nature11111';
            $this->assertSame($doi, extract_doi($doi . '/figures</span>')[1]);
            $this->assertSame($doi, extract_doi($doi . '/epdfplus</i>')[1]);
            $this->assertSame($doi, extract_doi('(https://example.org/doi/' . $doi . '/figures).')[1]);
            $this->assertSame($doi, extract_doi($doi . '/figures)]</span>')[1]);
            $this->assertSame('10.4000/abstractairanica.4242', extract_doi('10.4000/abstractairanica.4242')[1]);
        } finally {
            $this->assertTrue(uopz_unset_return('doi_works'));
        }
    }

    #[DataProvider('existingDoiQueryProvider')]
    public function testExistingDoiQueryRepair(?bool $original_status, ?bool $candidate_status, string $expected_doi, bool $verified): void {
        $candidate = '10.1234/example';
        $original = $candidate . '?utm_source=example&seq=6';
        $checks = [];
        $this->with_function_stubs([
            'doi_works' => static function (string $doi) use ($original, $candidate, $original_status, $candidate_status, &$checks): ?bool {
                $checks[] = $doi;
                return match ($doi) {
                    $original => $original_status,
                    $candidate => $candidate_status,
                    default => false,
                };
            },
        ], function () use ($original, $candidate, $original_status, $expected_doi, $verified, &$checks): void {
            $template = $this->make_citation('{{cite journal |doi=' . $original . ' |doi-broken-date=2020-01-01}}');
            $this->assertSame($verified, $template->verify_doi());
            $this->assertSame($expected_doi, $template->get2('doi'));
            if ($verified) {
                $this->assertNull($template->get2('doi-broken-date'));
            } elseif ($original_status === null) {
                $this->assertSame('2020-01-01', $template->get2('doi-broken-date'));
            } else {
                $this->assertNotNull($template->get2('doi-broken-date'));
            }
            $this->assertSame($original, reset($checks));
            $this->assertSame($original_status === false, in_array($candidate, $checks, true));
        });
    }

    /** @return array<string, array{?bool, ?bool, string, bool}> */
    public static function existingDoiQueryProvider(): array {
        $original = '10.1234/example?utm_source=example&seq=6';
        return [
            'registered shorter DOI' => [false, true, '10.1234/example', true],
            'registered literal question mark' => [true, true, $original, true],
            'original lookup unavailable' => [null, true, $original, false],
            'candidate lookup unavailable' => [false, null, $original, false],
            'neither form resolves' => [false, false, $original, false],
        ];
    }

    public function testExistingDoiQueryDoesNotTakePrecedenceOverPunctuationRepair(): void {
        $doi = '10.1234/example?suffix=registered';
        $this->with_function_stubs([
            'doi_works' => static fn (string $identifier): bool => in_array($identifier, [$doi, '10.1234/example'], true),
        ], function () use ($doi): void {
            $template = $this->make_citation('{{cite journal |doi=' . $doi . '.}}');
            $this->assertTrue($template->verify_doi());
            $this->assertSame($doi, $template->get2('doi'));
        });
    }

    public function testExistingDoiQueryCannotSupplyAnUnrelatedDoi(): void {
        $doi = '10.1234/chapter';
        $other = '10.9999/unrelated';
        $this->with_function_stubs([
            'doi_works' => static fn (string $identifier): bool => in_array($identifier, [$doi, $other], true),
        ], function () use ($doi, $other): void {
            $template = $this->make_citation('{{cite journal |doi=' . $doi . '?related=' . $other . '}}');
            $this->assertTrue($template->verify_doi());
            $this->assertSame($doi, $template->get2('doi'));
        });
    }

    public function testExistingDoiQueryIsRepairedDuringTidy(): void {
        $doi = '10.1234/example';
        $this->with_function_stubs([
            'doi_works' => static fn (string $identifier): bool => $identifier === $doi,
        ], function () use ($doi): void {
            $template = $this->make_citation('{{cite journal |doi=' . $doi . ' ?utm_source=example&seq=6}}');
            $template->tidy_parameter('doi');
            $this->assertSame($doi, $template->get2('doi'));
            $this->assertNull($template->get2('doi-broken-date'));
        });
    }

    public function testExistingDoiQueryRepairRespectsAnInlineComment(): void {
        $doi = '10.1234/example';
        $protected_doi = $doi . '?utm_source=example # # # CITATION_BOT_PLACEHOLDER_COMMENT 0 # # #';
        $this->with_function_stubs([
            'doi_works' => static fn (string $identifier): bool => $identifier === $doi,
        ], function () use ($protected_doi): void {
            $template = $this->make_citation('{{cite journal |doi=' . $protected_doi . ' |doi-broken-date=2020-01-01}}');
            $this->assertFalse($template->verify_doi());
            $this->assertSame($protected_doi, $template->get2('doi'));
            $this->assertNotNull($template->get2('doi-broken-date'));
        });
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
            'closing parenthesis within the DOI' => ['10.1234/abc/figures)part', '10.1234/abc/figures)part'],
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
