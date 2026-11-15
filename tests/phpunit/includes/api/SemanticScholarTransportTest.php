<?php

declare(strict_types=1);

/** Offline checks for the shared Semantic Scholar transport setup. */
require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class SemanticScholarTransportTest extends testBaseClass {
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testSharedHandleIsInitializedOnceWithOriginalTransportOptions(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }

        // Prepare a real configured handle before mocking bot_curl_init.
        // Direct cURL initialization would bypass the project's security policy.
        $fixture_handle = bot_curl_init(0.5, HEADER_S2, 4 * 1024 * 1024);
        $calls = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_init', static function (float $seconds, array $headers, int $bytes) use (&$calls, $fixture_handle): CurlHandle {
            $calls[] = [$seconds, $headers, $bytes];
            return $fixture_handle;
        }, true);
        try {
            $one = semanticscholar_new_handle();
            $two = semanticscholar_new_handle();
            $this->assertSame($one, $two, 'The four callers must share one CurlHandle');
            $this->assertSame([
                [0.5, HEADER_S2, 4 * 1024 * 1024],
            ], $calls);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_init');
        }
    }

    public function testAllFourCallersReuseOneHandleAndKeepWarningPolicies(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }

        // Construct the citation before hooking transport to avoid interference
        // from unrelated setup code in the citation factory.
        $template = $this->make_citation('{{cite journal|title=Offline example}}');
        $handles = [];
        $warnings = new ArrayObject();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec_withFalse', static function (CurlHandle $ch) use (&$handles): string {
            $handles[] = $ch;
            return '';
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_warning', static function (string $message) use ($warnings): void {
            $warnings->append($message);
        }, true);
        try {
            $this->assertSame('', getS2CID('https://example.test/article'));
            $this->assertSame('', ConvertS2CID_DOI('12345'));
            $this->assertNull(get_semanticscholar_license('12345'));
            get_semanticscholar_url($template, '10.1000/offline');

            $this->assertCount(4, $handles);
            $expected_handle = semanticscholar_new_handle();
            foreach ($handles as $handle) {
                $this->assertSame($expected_handle, $handle);
            }
            // Corpus/DOI lookups warn on empty bodies; license/legacy URL
            // lookups deliberately do not. Sharing a handle must not change this.
            $this->assertSame([
                'No response from semanticscholar.',
                'No response from semanticscholar.',
            ], $warnings->getArrayCopy());
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec_withFalse');
        }
    }

    public function testRequestUrlPreservesBodyAndOptionalEmptyResponseWarnings(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }

        $handle = bot_curl_init(0.5, HEADER_S2, 4 * 1024 * 1024);
        $warnings = new ArrayObject();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_warning', static function (string $message) use ($warnings): void {
            $warnings->append($message);
        }, true);
        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return('bot_curl_exec_withFalse', static fn (): string => '{"corpusId":42}', true);
            $this->assertSame(
                '{"corpusId":42}',
                semanticscholar_request_url($handle, 'https://api.semanticscholar.org/graph/v1/paper/CorpusID:42?fields=corpusId', true)
            );
            $this->assertSame([], $warnings->exchangeArray([]));

            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return('bot_curl_exec_withFalse', static fn (): string => '', true);
            $this->assertSame('', semanticscholar_request_url($handle, 'https://api.semanticscholar.org/v1/paper/10.1000/offline'));
            $this->assertSame([], $warnings->exchangeArray([]));
            $this->assertSame('', semanticscholar_request_url($handle, 'https://api.semanticscholar.org/graph/v1/paper/CorpusID:42', true));
            $this->assertSame(['No response from semanticscholar.'], $warnings->exchangeArray([]));
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec_withFalse');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
        }
    }
}
