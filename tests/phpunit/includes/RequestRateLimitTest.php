<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/includes/RequestRateLimit.php';

final class RequestRateLimitTest extends PHPUnit\Framework\TestCase {
    private string $base_directory;
    private string|false $previous_rate_limit_directory;

    #[\Override]
    protected function setUp(): void {
        $this->previous_rate_limit_directory = getenv('PHP_RATE_LIMIT_DIRECTORY');
        $this->base_directory =
            sys_get_temp_dir() .
            DIRECTORY_SEPARATOR .
            'citation-bot-rate-limit-test-' .
            bin2hex(random_bytes(8));

        $this->assertTrue(mkdir($this->base_directory, 0700, true));
        putenv('PHP_RATE_LIMIT_DIRECTORY=' . $this->base_directory);
    }

    #[\Override]
    protected function tearDown(): void {
        $this->restoreRateLimitDirectoryEnvironment($this->previous_rate_limit_directory);

        $state_directory =
            $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY;

        if (is_dir($state_directory)) {
            foreach (glob($state_directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($state_directory);
        }
        @rmdir($this->base_directory);
    }

    public function testCapacityIsAllowedThenNextRequestIsLimited(): void {
        $this->assertNull(
            request_rate_limit_consume('capacity-test', 2, 1.0, $this->base_directory, 100.0)
        );
        $this->assertNull(
            request_rate_limit_consume('capacity-test', 2, 1.0, $this->base_directory, 100.0)
        );
        $this->assertSame(
            1,
            request_rate_limit_consume('capacity-test', 2, 1.0, $this->base_directory, 100.0)
        );
    }

    public function testTokensRefillOverTime(): void {
        $this->assertNull(
            request_rate_limit_consume('refill-test', 1, 0.25, $this->base_directory, 100.0)
        );
        $this->assertSame(
            3,
            request_rate_limit_consume('refill-test', 1, 0.25, $this->base_directory, 101.0)
        );
        $this->assertNull(
            request_rate_limit_consume('refill-test', 1, 0.25, $this->base_directory, 104.0)
        );
    }

    public function testLockContentionRejectsImmediately(): void {
        $this->assertNull(
            request_rate_limit_consume('lock-test', 2, 1.0, $this->base_directory, 100.0)
        );

        $state_path =
            $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY .
            DIRECTORY_SEPARATOR .
            'lock-test.json';

        $handle = fopen($state_path, 'r+');
        $this->assertIsResource($handle);

        try {
            $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
            $started = microtime(true);
            $this->assertSame(
                1,
                request_rate_limit_consume('lock-test', 2, 1.0, $this->base_directory, 100.0)
            );
            $this->assertLessThan(0.5, microtime(true) - $started);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function testCorruptStateRecoversToFreshBucket(): void {
        $state_directory =
            $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY;
        $this->assertTrue(mkdir($state_directory, 0700, true));

        $state_path = $state_directory . DIRECTORY_SEPARATOR . 'corrupt-test.json';
        $this->assertNotFalse(file_put_contents($state_path, '{not-json'));

        $this->assertNull(
            request_rate_limit_consume('corrupt-test', 1, 1.0, $this->base_directory, 100.0)
        );
        $this->assertSame(
            1,
            request_rate_limit_consume('corrupt-test', 1, 1.0, $this->base_directory, 100.0)
        );
    }

    public function testInvalidConfigurationIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        request_rate_limit_consume('../bad', 1, 1.0, $this->base_directory, 100.0);
    }

    public function testNonPositiveCapacityIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        request_rate_limit_consume('capacity-validation', 0, 1.0, $this->base_directory, 100.0);
    }

    public function testNonPositiveRefillRateIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        request_rate_limit_consume('refill-validation', 1, 0.0, $this->base_directory, 100.0);
    }

    public function testNegativeTimestampIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        request_rate_limit_consume('timestamp-validation', 1, 1.0, $this->base_directory, -1.0);
    }

    public function testBaseDirectoryUsesEnvironmentOverride(): void {
        $previous = getenv('PHP_RATE_LIMIT_DIRECTORY');

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY=/tmp/citation-bot-rate-limit-test-override');
            $this->assertSame(
                '/tmp/citation-bot-rate-limit-test-override',
                request_rate_limit_base_directory()
            );
        } finally {
            $this->restoreRateLimitDirectoryEnvironment($previous);
        }
    }

    public function testBaseDirectoryFallsBackToSystemTempDirectory(): void {
        $previous = getenv('PHP_RATE_LIMIT_DIRECTORY');

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY');
            $this->assertSame(sys_get_temp_dir(), request_rate_limit_base_directory());
        } finally {
            $this->restoreRateLimitDirectoryEnvironment($previous);
        }
    }


    public function testBucketNameAcceptsMaximumLengthAndSafePunctuation(): void {
        $bucket = 'a' . str_repeat('._-', 21); // 64 bytes total.

        $this->assertSame(64, mb_strlen($bucket));
        $this->assertNull(
            request_rate_limit_consume($bucket, 1, 1.0, $this->base_directory, 100.0)
        );
        $this->assertFileExists($this->rateLimitStatePath($bucket));
    }

    public function testBucketNameRejectsBoundaryViolations(): void {
        foreach ([
            '',
            '-starts-with-dash',
            '.starts-with-dot',
            'contains/slash',
            'contains space',
            str_repeat('a', 65),
        ] as $bucket) {
            try {
                request_rate_limit_consume($bucket, 1, 1.0, $this->base_directory, 100.0);
                $this->fail('Expected invalid bucket name to be rejected: ' . $bucket);
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Invalid rate-limit bucket name.', $exception->getMessage());
            }
        }
    }

    public function testNonFiniteRefillRatesAreRejected(): void {
        foreach ([INF, -INF, NAN] as $refill_rate) {
            try {
                request_rate_limit_consume(
                    'nonfinite-refill',
                    1,
                    $refill_rate,
                    $this->base_directory,
                    100.0
                );
                $this->fail('Expected non-finite refill rate to be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'Rate-limit refill rate must be positive and finite.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function testNonFiniteTimestampsAreRejected(): void {
        foreach ([INF, -INF, NAN] as $timestamp) {
            try {
                request_rate_limit_consume(
                    'nonfinite-timestamp',
                    1,
                    1.0,
                    $this->base_directory,
                    $timestamp
                );
                $this->fail('Expected non-finite timestamp to be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'Rate-limit timestamp must be finite and non-negative.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function testBlankEnvironmentOverrideFallsBackToSystemTempDirectory(): void {
        $previous = getenv('PHP_RATE_LIMIT_DIRECTORY');

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY=');
            $this->assertSame(sys_get_temp_dir(), request_rate_limit_base_directory());
        } finally {
            $this->restoreRateLimitDirectoryEnvironment($previous);
        }
    }

    public function testStringZeroEnvironmentOverrideIsNotTreatedAsEmpty(): void {
        $previous = getenv('PHP_RATE_LIMIT_DIRECTORY');

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY=0');
            $this->assertSame('0', request_rate_limit_base_directory());
        } finally {
            $this->restoreRateLimitDirectoryEnvironment($previous);
        }
    }

    public function testMalformedSavedStatesAreDiscarded(): void {
        $states = [
            'missing-updated' => '{"tokens":0.0}',
            'nonnumeric-tokens' => '{"tokens":"nope","updated":100.0}',
            'nonnumeric-updated' => '{"tokens":0.0,"updated":"nope"}',
            'nonfinite-tokens' => '{"tokens":1e999,"updated":100.0}',
            'nonfinite-updated' => '{"tokens":0.0,"updated":1e999}',
            'negative-tokens' => '{"tokens":-1.0,"updated":100.0}',
            'negative-updated' => '{"tokens":0.0,"updated":-1.0}',
        ];

        foreach ($states as $bucket => $raw_state) {
            $this->writeRateLimitState($bucket, $raw_state);
            $this->assertNull(
                request_rate_limit_consume($bucket, 1, 1.0, $this->base_directory, 100.0),
                'Malformed state should reset to a fresh bucket: ' . $bucket
            );
        }
    }

    public function testNumericStringSavedStateIsAccepted(): void {
        $this->writeRateLimitState(
            'numeric-string-state',
            '{"tokens":"0.0","updated":"100.0"}'
        );

        $this->assertSame(
            1,
            request_rate_limit_consume(
                'numeric-string-state',
                1,
                1.0,
                $this->base_directory,
                100.0
            )
        );
    }

    public function testSavedTokensAreClampedToCapacity(): void {
        $this->writeRateLimitState('over-capacity-state', '{"tokens":50.0,"updated":100.0}');

        $this->assertNull(
            request_rate_limit_consume('over-capacity-state', 2, 1.0, $this->base_directory, 100.0)
        );
        $this->assertNull(
            request_rate_limit_consume('over-capacity-state', 2, 1.0, $this->base_directory, 100.0)
        );
        $this->assertSame(
            1,
            request_rate_limit_consume('over-capacity-state', 2, 1.0, $this->base_directory, 100.0)
        );
    }

    public function testWallClockRollbackDoesNotGrantRefillFromFutureTimestamp(): void {
        $this->writeRateLimitState('clock-rollback', '{"tokens":0.0,"updated":200.0}');

        $this->assertSame(
            1,
            request_rate_limit_consume('clock-rollback', 1, 1.0, $this->base_directory, 100.0)
        );
        $this->assertSame(
            1,
            request_rate_limit_consume('clock-rollback', 1, 1.0, $this->base_directory, 100.0)
        );
        $this->assertNull(
            request_rate_limit_consume('clock-rollback', 1, 1.0, $this->base_directory, 101.0)
        );
    }

    public function testRetryAfterRoundsFractionalDeficitUp(): void {
        $this->writeRateLimitState('fractional-retry', '{"tokens":0.1,"updated":100.0}');

        $this->assertSame(
            4,
            request_rate_limit_consume('fractional-retry', 1, 0.25, $this->base_directory, 100.0)
        );
    }

    public function testDirectoryCreationFailureFailsOpen(): void {
        $blocking_path = $this->base_directory . DIRECTORY_SEPARATOR . 'not-a-directory';
        $this->assertNotFalse(file_put_contents($blocking_path, 'x'));

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY=' . $blocking_path);
            $this->assertNull(
                request_rate_limit_consume(
                    'mkdir-failure',
                    1,
                    1.0,
                    $this->base_directory,
                    100.0
                )
            );
        } finally {
            putenv('PHP_RATE_LIMIT_DIRECTORY=' . $this->base_directory);
            @unlink($blocking_path);
        }
    }

    public function testStateFileOpenFailureFailsOpen(): void {
        $state_path = $this->rateLimitStatePath('open-failure');
        $state_directory = dirname($state_path);
        $this->assertTrue(mkdir($state_directory, 0700, true));
        $this->assertTrue(mkdir($state_path, 0700));

        try {
            $this->assertNull(
                request_rate_limit_consume(
                    'open-failure',
                    1,
                    1.0,
                    $this->base_directory,
                    100.0
                )
            );
        } finally {
            @rmdir($state_path);
        }
    }

    public function testLogFailureReportsDuplicateReasonOnlyOnce(): void {
        $log_path = tempnam(sys_get_temp_dir(), 'citation-bot-rate-limit-log-');
        $this->assertIsString($log_path);
        $previous_log = ini_get('error_log');
        $bucket = 'log-dedupe-' . bin2hex(random_bytes(4));

        try {
            ini_set('error_log', $log_path);
            request_rate_limit_log_failure($bucket, 'duplicate-reason');
            request_rate_limit_log_failure($bucket, 'duplicate-reason');
            request_rate_limit_log_failure($bucket, 'different-reason');

            $contents = file_get_contents($log_path);
            $this->assertIsString($contents);
            $this->assertSame(
                1,
                mb_substr_count(
                    $contents,
                    'Citation Bot rate limiter (' . $bucket . '): duplicate-reason; failing open.'
                )
            );
            $this->assertSame(
                1,
                mb_substr_count(
                    $contents,
                    'Citation Bot rate limiter (' . $bucket . '): different-reason; failing open.'
                )
            );
        } finally {
            ini_set('error_log', (string) $previous_log);
            @unlink($log_path);
        }
    }

    public function testStoreStateTruncatesLongerPreviousPayload(): void {
        $handle = fopen('php://temp', 'w+');
        $this->assertIsResource($handle);

        try {
            $this->assertNotFalse(fwrite($handle, str_repeat('x', 256)));
            $this->assertTrue(request_rate_limit_store_state($handle, 0.0, 1.0));
            $this->assertTrue(rewind($handle));
            $this->assertSame(
                '{"tokens":0.0,"updated":1.0}',
                stream_get_contents($handle)
            );
        } finally {
            fclose($handle);
        }
    }

    public function testStoreStateRejectsNonFiniteUpdatedTimestamp(): void {
        $handle = fopen('php://temp', 'w+');
        $this->assertIsResource($handle);

        try {
            $this->assertFalse(request_rate_limit_store_state($handle, 1.0, NAN));
        } finally {
            fclose($handle);
        }
    }

    public function testStoreStateWritesJson(): void {
        $handle = fopen('php://temp', 'w+');
        $this->assertIsResource($handle);

        try {
            $this->assertTrue(request_rate_limit_store_state($handle, 1.5, 100.0));
            $this->assertTrue(rewind($handle));
            $state = json_decode((string) stream_get_contents($handle), true);
            $this->assertSame(['tokens' => 1.5, 'updated' => 100.0], $state);
        } finally {
            fclose($handle);
        }
    }

    public function testStoreStateRejectsNonFiniteTokens(): void {
        $handle = fopen('php://temp', 'w+');
        $this->assertIsResource($handle);

        try {
            $this->assertFalse(request_rate_limit_store_state($handle, INF, 100.0));
        } finally {
            fclose($handle);
        }
    }

    public function testStoreStateRejectsReadOnlyStream(): void {
        $handle = fopen(__FILE__, 'r');
        $this->assertIsResource($handle);

        try {
            $this->assertFalse(request_rate_limit_store_state($handle, 1.0, 100.0));
        } finally {
            fclose($handle);
        }
    }


    private function rateLimitStatePath(string $bucket): string {
        return $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY .
            DIRECTORY_SEPARATOR .
            $bucket . '.json';
    }

    private function writeRateLimitState(string $bucket, string $raw_state): void {
        $state_path = $this->rateLimitStatePath($bucket);
        $state_directory = dirname($state_path);
        if (!is_dir($state_directory)) {
            $this->assertTrue(mkdir($state_directory, 0700, true));
        }
        $this->assertNotFalse(file_put_contents($state_path, $raw_state));
    }

    private function restoreRateLimitDirectoryEnvironment(string|false $previous): void {
        if ($previous === false) {
            putenv('PHP_RATE_LIMIT_DIRECTORY');
            return;
        }
        putenv('PHP_RATE_LIMIT_DIRECTORY=' . $previous);
    }

    public function testNonPositiveCapacityIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        request_rate_limit_consume('capacity-validation', 0, 1.0, $this->base_directory, 100.0);
    }

    public function testNonPositiveRefillRateIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        request_rate_limit_consume('refill-validation', 1, 0.0, $this->base_directory, 100.0);
    }

    public function testNegativeTimestampIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        request_rate_limit_consume('timestamp-validation', 1, 1.0, $this->base_directory, -1.0);
    }

    public function testBaseDirectoryUsesEnvironmentOverride(): void {
        $previous = getenv('PHP_RATE_LIMIT_DIRECTORY');

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY=/tmp/citation-bot-rate-limit-test-override');
            $this->assertSame(
                '/tmp/citation-bot-rate-limit-test-override',
                request_rate_limit_base_directory()
            );
        } finally {
            $this->restoreRateLimitDirectoryEnvironment($previous);
        }
    }

    public function testBaseDirectoryFallsBackToSystemTempDirectory(): void {
        $previous = getenv('PHP_RATE_LIMIT_DIRECTORY');

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY');
            $this->assertSame(sys_get_temp_dir(), request_rate_limit_base_directory());
        } finally {
            $this->restoreRateLimitDirectoryEnvironment($previous);
        }
    }

    public function testStoreStateWritesJson(): void {
        $handle = fopen('php://temp', 'w+');
        $this->assertIsResource($handle);

        try {
            $this->assertTrue(request_rate_limit_store_state($handle, 1.5, 100.0));
            $this->assertTrue(rewind($handle));
            $state = json_decode((string) stream_get_contents($handle), true);
            $this->assertSame(['tokens' => 1.5, 'updated' => 100.0], $state);
        } finally {
            fclose($handle);
        }
    }

    public function testStoreStateRejectsNonFiniteTokens(): void {
        $handle = fopen('php://temp', 'w+');
        $this->assertIsResource($handle);

        try {
            $this->assertFalse(request_rate_limit_store_state($handle, INF, 100.0));
        } finally {
            fclose($handle);
        }
    }

    public function testStoreStateRejectsReadOnlyStream(): void {
        $handle = fopen(__FILE__, 'r');
        $this->assertIsResource($handle);

        try {
            $this->assertFalse(request_rate_limit_store_state($handle, 1.0, 100.0));
        } finally {
            fclose($handle);
        }
    }

    private function restoreRateLimitDirectoryEnvironment(string|false $previous): void {
        if ($previous === false) {
            putenv('PHP_RATE_LIMIT_DIRECTORY');
            return;
        }
        putenv('PHP_RATE_LIMIT_DIRECTORY=' . $previous);
    }
}
