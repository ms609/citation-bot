<?php

declare(strict_types=1);

/**
 * Deterministic offline coverage of Semantic Scholar
 * request handling. Network transports are never called by these tests.
 */
require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('citoid-regression')]
final class SemanticScholarOfflineTest extends testBaseClass {
    /**
     * Override only the low-level request result while preserving the real
     * response parsing, URL assembly, and downstream error handling.
     */
    private function withScholarResponse(string $body, Closure $assertions): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec_withFalse', static fn (): string => $body, true);
        try {
            $assertions();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec_withFalse');
        }
    }

    public function testScholarCorpusIdCanBeParsedOffline(): void {
        $this->withScholarResponse('{"corpusId":987654}', function (): void {
            $this->assertSame('987654', getS2CID('https://example.test/paper'));
        });
        $this->withScholarResponse('{"corpusId":[]}', function (): void {
            $this->assertSame('', getS2CID('https://example.test/paper'));
        });
    }

    public function testScholarDoiResponseRejectedWithoutExternalLookup(): void {
        $this->withScholarResponse('{"externalIds":{"DOI":[]}}', function (): void {
            $this->assertSame('', ConvertS2CID_DOI('123456'));
        });
    }

    public function testScholarLicenseResponsesAreParsedOffline(): void {
        foreach (['{"isOpenAccess":true}' => true, '{"isOpenAccess":false}' => false] as $body => $expected) {
            $this->withScholarResponse($body, function () use ($expected): void {
                $this->assertSame($expected, get_semanticscholar_license('123456'));
            });
        }
        $this->withScholarResponse('{"error":"temporarily unavailable"}', function (): void {
            $this->assertNull(get_semanticscholar_license('123456'));
        });
        $this->withScholarResponse('', function (): void {
            $this->assertNull(get_semanticscholar_license('123456'));
        });
    }

    public function testScholarOpenAccessUrlRejectsUnlicensedMetadataOffline(): void {
        $template = $this->make_citation('{{cite web|id=}}');
        $this->withScholarResponse(
            '{"url":"https://example.test/paper","is_publisher_licensed":false,"openAccessPdf":{}}',
            function () use ($template): void {
                get_semanticscholar_url($template, '10.1000/offline');
            }
        );
        $this->assertNull($template->get2('url'));
    }

    public function testScholarLookupWarnsOnceForTransportFailuresAndEmptyResponses(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }
        $warnings = new ArrayObject();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_warning', static function (string $warning) use ($warnings): void {
            $warnings->append($warning);
        }, true);
        try {
            $transport_warning = 'Semantic Scholar request failed; continuing without this metadata.';
            $throw_transport_error = static function (): never {
                throw new RuntimeException('Deliberate offline transport failure');
            };
            $scenarios = [
                [$throw_transport_error, $transport_warning],
                [static fn (): bool => false, $transport_warning],
                [static fn (): string => '', 'No response from semanticscholar.'],
            ];
            foreach ($scenarios as [$transport, $warning]) {
                /** @psalm-suppress UnusedFunctionCall */
                uopz_set_return('bot_curl_exec_withFalse', $transport, true);
                try {
                    $this->assertSame('', getS2CID('https://example.test/paper'));
                    $this->assertSame([$warning], $warnings->exchangeArray([]));

                    $this->assertSame('', ConvertS2CID_DOI('123456'));
                    $this->assertSame([$warning], $warnings->exchangeArray([]));
                } finally {
                    /** @psalm-suppress UnusedFunctionCall */
                    uopz_unset_return('bot_curl_exec_withFalse');
                }
            }
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
        }
    }

    /**
     * Test URL-to-S2CID conversion without contacting Semantic Scholar. This
     * used to vary with API availability, causing coverage fluctuations.
     */
    public function testScholarUrlIdentityMismatchDoesNotOverwriteStoredIds(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('getS2CID', static fn (): string => '987654', true);
        try {
            foreach (['s2cid', 'S2CID'] as $field) {
                $template = $this->make_citation(
                    '{{cite web|url=https://www.semanticscholar.org/paper/offline|'
                    . $field . '=123456}}'
                );
                $template->get_identifiers_from_url();
                $this->assertSame('123456', $template->get2($field));
            }
            $template = $this->make_citation(
                '{{cite journal|title=Offline journal|url=https://www.semanticscholar.org/paper/offline}}'
            );
            $template->get_identifiers_from_url();
            $this->assertSame('987654', $template->get2('s2cid'));
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('getS2CID');
        }
    }
}
