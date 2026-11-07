<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class WikipediaBotRetryTest extends testBaseClass {
    /**
     * @param bool $authenticated
     * @param list<string|false|RuntimeException> $responses
     * @param list<int> $expected_delays
     * @param bool $succeeds
     */
    #[DataProvider('readFailures')]
    public function testReadRetriesAreBounded(
        bool $authenticated,
        array $responses,
        array $expected_delays,
        bool $succeeds
    ): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub MediaWiki transport and sleep');
        }
        $attempt_count = count($responses);
        $calls = new ArrayObject();
        $delays = new ArrayObject();
        uopz_set_return('bot_curl_exec_withFalse', static function () use (&$responses, $calls): string|bool {
            $calls->append(true);
            $response = array_shift($responses);
            if ($response instanceof RuntimeException) {
                throw $response;
            }
            if ($response === null) {
                throw new LogicException('Unexpected extra transport attempt');
            }
            return $response;
        }, true);
        uopz_set_return('sleep', static function (int $seconds) use ($delays): int {
            $delays->append($seconds);
            return 0;
        }, true);
        try {
            if ($authenticated) {
                $method = new ReflectionMethod(WikipediaBot::class, 'query_api_authenticated');
                $json = $method->invoke(new WikipediaBot(), ['action' => 'query']);
                $this->assertSame($succeeds ? '{"batchcomplete":true}' : '', $json);
            } else {
                $result = WikipediaBot::read_details('Example');
                $this->assertSame($succeeds ? ['batchcomplete' => true] : [], (array) $result);
            }
            $this->assertSame([], $responses, 'All expected transport attempts should be consumed');
            $this->assertCount($attempt_count, $calls);
            $this->assertSame($expected_delays, $delays->getArrayCopy());
        } finally {
            uopz_unset_return('sleep');
            uopz_unset_return('bot_curl_exec_withFalse');
        }
    }

    /** @return iterable<string, array{bool, list<string|false|RuntimeException>, list<int>, bool}> */
    public static function readFailures(): iterable {
        $maxlag = '{"error":{"code":"maxlag","info":"Waiting for replicas"}}';
        $readonly = '{"error":{"code":"readonly","info":"Database read-only"}}';
        foreach (['anonymous' => false, 'authenticated' => true] as $name => $authenticated) {
            yield $name . ' recovers after mixed failures' => [
                $authenticated, [false, $maxlag, '<html>Unavailable</html>', '{"batchcomplete":true}'], [4, 5, 10, 20], true,
            ];
            yield $name . ' stops after four retryable responses' => [
                $authenticated, [$readonly, $readonly, $readonly, $readonly], [5, 10, 20], false,
            ];
            yield $name . ' stops after four empty responses' => [
                $authenticated, ['', '', '', ''], [4, 5, 4, 10, 4, 20], false,
            ];
            yield $name . ' stops on a terminal API error' => [
                $authenticated, ['{"error":{"code":"permissiondenied","info":"Permission denied"}}'],
                [run_type_mods(10, 2, 2, 1, 2)], false,
            ];
            yield $name . ' stops on a thrown transport error' => [
                $authenticated, [new RuntimeException('Fixture transport error')], [], false,
            ];
        }
    }

    public function testRepeatedContributionContinuationStopsAtBatchLimit(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub MediaWiki transport and sleep');
        }
        $requests = new ArrayObject();
        $contribution = static function (array $request) use ($requests): string {
            $requests->append($request);
            // A 21st request ends the fixture and lets the call-count assertion detect a missing cap.
            if ($requests->count() > 20) {
                return '{"query":{"usercontribs":[]}}';
            }
            return '{"query":{"usercontribs":[{"comment":"edit"}]},"continue":{"uccontinue":"repeat"}}';
        };
        uopz_set_return(WikipediaBot::class, 'query_api_authenticated', $contribution, true);
        uopz_set_return(WikipediaBot::class, 'query_api', $contribution, true);
        uopz_set_return('usleep', null);
        try {
            $edits = WikipediaBot::fetch_user_contribs('Fixture bot');
            $this->assertSame(array_fill(0, 20, 'edit'), array_column($edits, 'comment'));
            $this->assertCount(20, $requests);
            $this->assertSame(array_fill(0, 19, 'repeat'), array_column($requests->getArrayCopy(), 'uccontinue'));
        } finally {
            uopz_unset_return('usleep');
            uopz_unset_return(WikipediaBot::class, 'query_api');
            uopz_unset_return(WikipediaBot::class, 'query_api_authenticated');
        }
    }
}
