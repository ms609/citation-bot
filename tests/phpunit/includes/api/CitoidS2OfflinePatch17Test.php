<?php

declare(strict_types=1);

/**
 * Deterministic offline coverage of Citoid fallbacks and Semantic Scholar
 * request handling. Network transports are never called by these tests.
 */
require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('citoid-regression')]
final class CitoidS2OfflinePatch17Test extends testBaseClass {
    /**
     * Citoid emits these fields as substitutes for a missing title in some
     * item types. Do not regress to an empty citation title.
     */
    public function testCitoidUsesSupportedFallbackTitleFields(): void {
        foreach (['subject', 'caseName', 'nameOfAct'] as $field) {
            $title = 'Offline fallback ' . $field;
            $template = $this->make_citation('{{cite web|id=}}');
            Zotero::process_zotero_response(
                json_encode([['itemType' => 'webpage', $field => $title]], JSON_THROW_ON_ERROR),
                $template,
                'https://example.test/offline',
                0,
                false,
                true,
                true
            );
            $this->assertSame($title, $template->get2('title'), 'Fallback field ' . $field);
        }
    }

    public function testCitoidTitleIdentityRequiresComparableFields(): void {
        $matches = new ReflectionMethod(Zotero::class, 'response_title_matches_template');
        $result = (object) ['itemType' => 'journalArticle', 'title' => 'Unrelated study'];
        $this->assertFalse($matches->invoke(
            null, $result, $this->make_citation('{{cite journal|series=Academic Studies}}')
        ));
        $this->assertFalse($matches->invoke(
            null, $result, $this->make_citation('{{cite journal|trans-title=Different translation}}')
        ));
        $this->assertTrue($matches->invoke(
            null, $result, $this->make_citation('{{cite journal|trans-title=Unrelated study}}')
        ));
        $this->assertFalse($matches->invoke(
            null, $result, $this->make_citation('{{cite journal|chapter=Different chapter}}')
        ));
        $this->assertTrue($matches->invoke(
            null, (object) ['itemType' => 'bookSection', 'title' => 'Matching chapter'],
            $this->make_citation('{{cite book|chapter=Matching chapter}}')
        ));
    }

    public function testMalformedZoteroAuthorNamesFailValidation(): void {
        $normalize = new ReflectionMethod(Zotero::class, 'normalize_zotero_result');
        $item = (object) [
            'title' => 'Offline item',
            'itemType' => 'webpage',
            'author' => [['lastName' => (object) ['unsafe' => 'nested']]],
        ];
        $this->assertFalse($normalize->invoke(null, $item, 'https://example.test/offline'));
    }

    /**
     * Override only the low-level request result while preserving the real
     * response parsing, URL assembly, and downstream error handling.
     */
    private function withScholarResponse(string $body, Closure $assertions): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('semanticscholar_request', static fn (): string => $body, true);
        try {
            $assertions();
        } finally {
            uopz_unset_return('semanticscholar_request');
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

    public function testScholarTransportThrowableFailsClosedWithoutHttp(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec', static function (): string {
            throw new RuntimeException('Deliberate offline transport failure');
        }, true);
        $handle = bot_curl_init(0.5, HEADER_S2, 1024);
        $this->assertInstanceOf(CurlHandle::class, $handle);
        try {
            $this->assertSame('', semanticscholar_request($handle));
        } finally {
            uopz_unset_return('bot_curl_exec');
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
            uopz_unset_return('getS2CID');
        }
    }
}
