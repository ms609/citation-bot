<?php

declare(strict_types=1);

/** Offline ADS envelope and lookup regressions. No HTTP transport is invoked. */
require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AdsAbsCoveragePatch11Test extends testBaseClass {
    /** @return array<string, array{mixed}> */
    public static function invalidEnvelopeProvider(): array {
        return [
            'null' => [null],
            'scalar' => ['not JSON'],
            'array instead of object' => [['response' => ['numFound' => 0]]],
            'missing response' => [(object) []],
            'nonobject response' => [(object) ['response' => []]],
            'missing count' => [(object) ['response' => (object) ['docs' => []]]],
            'negative count' => [(object) ['response' => (object) ['numFound' => -1]]],
            'string count' => [(object) ['response' => (object) ['numFound' => '1']]],
            'missing docs with matches' => [(object) ['response' => (object) ['numFound' => 1]]],
            'nonarray docs' => [(object) ['response' => (object) ['numFound' => 1, 'docs' => (object) []]]],
            'empty matched docs' => [(object) ['response' => (object) ['numFound' => 1, 'docs' => []]]],
            'string record' => [(object) ['response' => (object) ['numFound' => 1, 'docs' => ['text']]]],
            'nonstring bibcode' => [(object) ['response' => (object) [
                'numFound' => 1, 'docs' => [(object) ['bibcode' => 23]],
            ]]],
            'malformed title' => [(object) ['response' => (object) [
                'numFound' => 1, 'docs' => [(object) ['title' => 'not an array']],
            ]]],
            'nonstring title entry' => [(object) ['response' => (object) [
                'numFound' => 1, 'docs' => [(object) ['title' => [42]]],
            ]]],
            'malformed author list' => [(object) ['response' => (object) [
                'numFound' => 1, 'docs' => [(object) ['author' => [null]]],
            ]]],
            'object year' => [(object) ['response' => (object) [
                'numFound' => 1, 'docs' => [(object) ['year' => (object) []]],
            ]]],
            'malformed DOI list' => [(object) ['response' => (object) [
                'numFound' => 1, 'docs' => [(object) ['doi' => [7]]],
            ]]],
        ];
    }

    #[DataProvider('invalidEnvelopeProvider')]
    public function testRejectsMalformedAdsEnvelopes(mixed $envelope): void {
        $this->assertNull(adsabs_valid_response($envelope));
    }

    public function testAcceptsEmptyAdsResponseWithoutDocs(): void {
        $response = (object) ['numFound' => 0];
        $envelope = (object) ['response' => $response];
        $this->assertSame($response, adsabs_valid_response($envelope));
    }

    public function testAcceptsWellTypedAdsRecords(): void {
        $record = (object) [
            'bibcode' => '2020Natur.001....1A', 'title' => ['A scientific result'],
            'doi' => ['10.1000/example'], 'author' => ['A. Researcher'],
            'year' => '2020', 'volume' => '1',
        ];
        $response = (object) ['numFound' => 1, 'docs' => [$record]];
        $this->assertTrue(adsabs_record_is_safe($record));
        $this->assertSame($response, adsabs_valid_response((object) ['response' => $response]));
    }

    public function testEmptyAdsResponseWithEmptyDocsIsAccepted(): void {
        $response = (object) ['numFound' => 0, 'docs' => []];
        $this->assertSame($response, adsabs_valid_response((object) ['response' => $response]));
    }

    /** @return array<string, array{string, array{limit: ?int, remaining: ?int, reset: ?int}}> */
    public static function headerProvider(): array {
        return [
            'all headers' => [
                "HTTP/1.1 200 OK\r\nX-RateLimit-Limit: 100\r\nX-RateLimit-Remaining: 3\r\nX-RateLimit-Reset: 1234567890\r\n",
                ['limit' => 100, 'remaining' => 3, 'reset' => 1234567890],
            ],
            'no headers' => ['HTTP/1.1 200 OK', ['limit' => null, 'remaining' => null, 'reset' => null]],
            'junk and negative values ignored' => [
                "x-ratelimit-limit: -1\nX-RateLimit-Remaining: nope\nX-RateLimit-Reset: 9",
                ['limit' => null, 'remaining' => null, 'reset' => 9],
            ],
            'case insensitive' => [
                "X-RATELIMIT-LIMIT:\t17\nX-ratelimit-remaining: 0\n",
                ['limit' => 17, 'remaining' => 0, 'reset' => null],
            ],
        ];
    }

    /**
     * @param string $headers
     * @param array{limit: ?int, remaining: ?int, reset: ?int} $expected
     */
    #[DataProvider('headerProvider')]
    public function testParsesRateLimitHeadersWithoutTransport(string $headers, array $expected): void {
        $this->assertSame($expected, adsabs_rate_limit_headers($headers));
    }

    public function testBibcodeClassifierDoesNotConfuseArticlesWithBooks(): void {
        $this->assertTrue(is_a_book_bibcode('2003....book.......'));
        $this->assertTrue(is_a_book_bibcode('2005....conf.......'));
        $this->assertTrue(is_a_book_bibcode('2007....PhD........'));
        $this->assertFalse(is_a_book_bibcode('2017NatCo...814879F'));
    }

    public function testCacheControlsAreResettableWithoutNetwork(): void {
        AdsAbsControl::small_give_up();
        AdsAbsControl::big_give_up();
        try {
            $this->assertTrue(AdsAbsControl::small_gave_up_yet());
            $this->assertTrue(AdsAbsControl::big_gave_up_yet());
        } finally {
            AdsAbsControl::small_back_on();
            AdsAbsControl::big_back_on();
        }
        $this->assertFalse(AdsAbsControl::small_gave_up_yet());
        $this->assertFalse(AdsAbsControl::big_gave_up_yet());
    }

    /**
     * ADS calls are injected; even an invalid candidate may never replace a
     * caller's original citation based on a mismatched remote record.
     *
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function rejectedLookupProvider(): array {
        $record = [
            'title' => ['A detailed scientific observation'],
            'bibcode' => '2020Natur.001....1A',
            'year' => '2020',
        ];
        return [
            'multiple matches' => [
                '{{cite journal|bibcode=tmp-ads11|eprint=1801.00001}}',
                ['numFound' => 2, 'docs' => [(object) $record]],
            ],
            'record too incomplete' => [
                '{{cite journal|bibcode=tmp-ads11|eprint=1801.00001}}',
                ['numFound' => 1, 'docs' => [(object) ['year' => '2020']]],
            ],
            'year differs by several years' => [
                '{{cite journal|bibcode=tmp-ads11|eprint=1801.00001|date=2020}}',
                ['numFound' => 1, 'docs' => [(object) array_replace($record, ['year' => '2015'])]],
            ],
            'invalid record year' => [
                '{{cite journal|bibcode=tmp-ads11|eprint=1801.00001|date=2020}}',
                ['numFound' => 1, 'docs' => [(object) array_replace($record, ['year' => 'unknown'])]],
            ],
            'different article title' => [
                '{{cite journal|bibcode=tmp-ads11|eprint=1801.00001|title=An unrelated discovery}}',
                ['numFound' => 1, 'docs' => [(object) $record]],
            ],
            'invalid all-dot bibcode' => [
                '{{cite journal|bibcode=tmp-ads11|eprint=1801.00001}}',
                ['numFound' => 1, 'docs' => [(object) array_replace($record, ['bibcode' => '2020....book.......'])]],
            ],
        ];
    }

    /**
     * @param string $citation
     * @param array<string, mixed> $response
     */
    #[DataProvider('rejectedLookupProvider')]
    public function testInjectedAdsLookupRejectsUnsafeMatches(string $citation, array $response): void {
        $template = $this->make_citation($citation);
        $queries = [];
        $lookup = static function (string $query) use (&$queries, $response): stdClass {
            $queries[] = $query;
            return (object) $response;
        };
        expand_by_adsabs($template, $lookup);
        $this->assertNotEmpty($queries, 'Offline fake must be called; a short-circuited test covers nothing');
        $this->assertSame('tmp-ads11', $template->get('bibcode'));
    }
}
