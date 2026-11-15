<?php

declare(strict_types=1);

/** Offline checks for the shared Semantic Scholar transport setup. */
require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class SemanticScholarTransportTest extends testBaseClass {
    public function testEachHandleUsesTheSameTransportOptionsButIsIndependent(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }

        $calls = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_init', static function (float $seconds, array $headers, int $bytes) use (&$calls): CurlHandle {
            $calls[] = [$seconds, $headers, $bytes];
            $handle = curl_init();
            if ($handle === false) {
                throw new RuntimeException('Unable to create an offline cURL handle');
            }
            return $handle;
        }, true);
        try {
            $one = semanticscholar_new_handle();
            $two = semanticscholar_new_handle();
            $this->assertNotSame($one, $two, 'Callers must not share mutable CurlHandle objects');
            $this->assertSame([
                [0.5, HEADER_S2, 4 * 1024 * 1024],
                [0.5, HEADER_S2, 4 * 1024 * 1024],
            ], $calls);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_init');
        }
    }

    public function testRequestUrlPreservesBodyAndOptionalEmptyResponseWarnings(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Offline transport fixture requires uopz');
        }

        $handle = curl_init();
        if ($handle === false) {
            $this->fail('Unable to create an offline cURL handle');
        }
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
