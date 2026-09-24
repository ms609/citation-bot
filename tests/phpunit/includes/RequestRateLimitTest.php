<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/includes/RequestRateLimit.php';

final class RequestRateLimitTest extends PHPUnit\Framework\TestCase {
    private string $base_directory;
    private string|false $previous_rate_limit_directory;
    /** @var array<string, string|false> */
    private array $previous_big_run_environment = [];

    #[\Override]
    protected function setUp(): void {
        $this->previous_rate_limit_directory = getenv('PHP_RATE_LIMIT_DIRECTORY');
        foreach ([
            'CITATION_BOT_BIG_RUN_MAX_TOTAL',
            'CITATION_BOT_BIG_RUN_MAX_LARGE',
            'CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS',
            'CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS',
            'CITATION_BOT_BIG_RUN_POOL_RETRY_SECONDS',
            'CITATION_BOT_BIG_RUN_TOKEN_CAPACITY',
            'CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND',
        ] as $name) {
            $this->previous_big_run_environment[$name] = getenv($name);
            putenv($name);
        }
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
        foreach ($this->previous_big_run_environment as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
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
        foreach (glob($this->base_directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
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

    public function testClientBucketCanonicalizesAndHashesRemoteAddress(): void {
        $compressed = request_rate_limit_client_bucket(
            'gadgetapi',
            '2001:4860:4860::8888'
        );
        $expanded = request_rate_limit_client_bucket(
            'gadgetapi',
            '2001:4860:4860:0000:0000:0000:0000:8888'
        );

        $this->assertSame($compressed, $expanded);
        $this->assertMatchesRegularExpression(
            '~\Aclient-[a-f0-9]{32}\z~D',
            $compressed
        );
        $this->assertStringNotContainsString('2001', $compressed);
        $this->assertNotSame(
            $compressed,
            request_rate_limit_client_bucket(
                'generate-template',
                '2001:4860:4860::8888'
            )
        );
    }

    public function testNonDirectClientAddressesSkipClientBucket(): void {
        $this->assertNull(request_rate_limit_client_bucket('gadgetapi', null));
        $this->assertNull(request_rate_limit_client_bucket('gadgetapi', ''));
        $this->assertNull(
            request_rate_limit_client_bucket('gadgetapi', 'not-an-ip-address')
        );
        $this->assertNull(request_rate_limit_client_bucket('gadgetapi', '127.0.0.1'));
        $this->assertNull(request_rate_limit_client_bucket('gadgetapi', '10.0.0.1'));
    }

    public function testLayeredLimitPreservesSharedCapacityForOtherClients(): void {
        for ($request = 0; $request < 2; ++$request) {
            $this->assertNull(
                request_rate_limit_consume_layered(
                    'layered-test',
                    4,
                    1.0,
                    2,
                    0.25,
                    '8.8.8.8',
                    $this->base_directory,
                    100.0
                )
            );
        }

        $this->assertSame(
            4,
            request_rate_limit_consume_layered(
                'layered-test',
                4,
                1.0,
                2,
                0.25,
                '8.8.8.8',
                $this->base_directory,
                100.0
            )
        );

        // Client A's rejected request did not consume a shared token.
        for ($request = 0; $request < 2; ++$request) {
            $this->assertNull(
                request_rate_limit_consume_layered(
                    'layered-test',
                    4,
                    1.0,
                    2,
                    0.25,
                    '1.1.1.1',
                    $this->base_directory,
                    100.0
                )
            );
        }

        // The original global cap still applies across distinct clients.
        $this->assertSame(
            1,
            request_rate_limit_consume_layered(
                'layered-test',
                4,
                1.0,
                2,
                0.25,
                '9.9.9.9',
                $this->base_directory,
                100.0
            )
        );
    }

    public function testPublicRateLimitedEntryPointsDoNotTrustForwardedFor(): void {
        foreach (['gadgetapi.php', 'generate_template.php'] as $entry_point) {
            $source = file_get_contents(dirname(__DIR__, 3) . '/src/' . $entry_point);
            $this->assertIsString($source);
            if (!is_string($source)) {
                throw new RuntimeException('Unable to read ' . $entry_point . '.');
            }

            $this->assertStringContainsString('request_rate_limit_consume_layered(', $source);
            $this->assertStringContainsString("\$_SERVER['REMOTE_ADDR']", $source);
            $this->assertStringNotContainsString('HTTP_X_FORWARDED_FOR', $source);
        }
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

    public function testExplicitBaseDirectoryOverridesEnvironment(): void {
        $environment_directory =
            $this->base_directory . DIRECTORY_SEPARATOR . 'environment-directory';
        $this->assertTrue(mkdir($environment_directory, 0700, true));
        $previous = getenv('PHP_RATE_LIMIT_DIRECTORY');

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY=' . $environment_directory);
            $this->assertNull(
                request_rate_limit_consume(
                    'explicit-directory',
                    1,
                    1.0,
                    $this->base_directory,
                    100.0
                )
            );
            $this->assertFileExists($this->rateLimitStatePath('explicit-directory'));
            $this->assertDirectoryDoesNotExist(
                $environment_directory .
                DIRECTORY_SEPARATOR .
                REQUEST_RATE_LIMIT_STATE_DIRECTORY
            );
        } finally {
            $this->restoreRateLimitDirectoryEnvironment($previous);
            @rmdir($environment_directory);
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

    public function testDirectoryCreationFailureFailsOpenNotEnv(): void {
        $blocking_path = $this->base_directory . DIRECTORY_SEPARATOR . 'not-a-directory';
        $this->assertNotFalse(file_put_contents($blocking_path, 'x'));

        try {
            $this->assertNull(
                request_rate_limit_consume(
                    'mkdir-failure',
                    1,
                    1.0,
                    $blocking_path,
                    100.0
                )
            );
        } finally {
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

    public function testStateFileSymlinkFailsOpenWithoutTouchingTarget(): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Symlink semantics are platform-specific on Windows.');
        }

        $state_directory = big_run_prepare_state_directory($this->base_directory);
        $this->assertIsString($state_directory);
        if (!is_string($state_directory)) {
            throw new RuntimeException('Expected private state directory.');
        }

        $target = $this->base_directory . DIRECTORY_SEPARATOR . 'rate-limit-target';
        $this->assertNotFalse(file_put_contents($target, 'unchanged'));
        $state_path = $state_directory . DIRECTORY_SEPARATOR . 'symlink-test.json';
        $this->assertTrue(symlink($target, $state_path));

        try {
            $this->assertNull(
                request_rate_limit_consume(
                    'symlink-test',
                    1,
                    1.0,
                    $this->base_directory,
                    100.0
                )
            );
            $this->assertSame('unchanged', file_get_contents($target));
        } finally {
            @unlink($state_path);
            @unlink($target);
        }
    }

    public function testStateDirectorySymlinkFailsOpen(): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Symlink semantics are platform-specific on Windows.');
        }

        $other = $this->base_directory . DIRECTORY_SEPARATOR . 'other-state';
        $this->assertTrue(mkdir($other, 0700));
        $state_directory = $this->base_directory . DIRECTORY_SEPARATOR . REQUEST_RATE_LIMIT_STATE_DIRECTORY;
        $this->assertTrue(symlink($other, $state_directory));
        $this->assertNull(
            request_rate_limit_consume('directory-symlink', 1, 1.0, $this->base_directory, 100.0)
        );
        @unlink($state_directory);
        @rmdir($other);
    }

    public function testLogFailureReportsDuplicateReasonOnlyOnce(): void {
        $log_path = bot_debug_log_path();
        $bucket = 'log-dedupe-' . bin2hex(random_bytes(4));

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

    public function testBaseDirectoryFallsBackToSystemTempDirectory(): void {
        $previous = getenv('PHP_RATE_LIMIT_DIRECTORY');

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY');
            $this->assertSame(sys_get_temp_dir(), request_rate_limit_base_directory());
        } finally {
            $this->restoreRateLimitDirectoryEnvironment($previous);
        }
    }

    public function testProbePoolIsSeparateAndPromotionChecksBulkCapacity(): void {
        $previous_total = getenv('CITATION_BOT_BIG_RUN_MAX_TOTAL');
        $previous_probes = getenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES');

        try {
            putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=2');
            putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=2');

            $probe_one = big_run_try_acquire_probe($this->base_directory, 100.0);
            $probe_two = big_run_try_acquire_probe($this->base_directory, 100.0);
            $this->assertTrue($probe_one[0]);
            $this->assertTrue($probe_two[0]);
            $this->assertIsString($probe_one[2]);
            $this->assertIsString($probe_two[2]);
            if (!is_string($probe_one[2]) || !is_string($probe_two[2])) {
                throw new RuntimeException('Expected probe lease IDs.');
            }

            $probe_full = big_run_try_acquire_probe($this->base_directory, 100.0);
            $this->assertSame([false, 30, null, 'probe_full', 2], $probe_full);

            $run_one = big_run_try_acquire(5, 'category', $this->base_directory, 100.0, false);
            $run_two = big_run_try_acquire(5, 'category', $this->base_directory, 100.0, false);
            $this->assertTrue($run_one[0]);
            $this->assertTrue($run_two[0]);
            $this->assertIsString($run_one[2]);
            $this->assertIsString($run_two[2]);
            if (!is_string($run_one[2]) || !is_string($run_two[2])) {
                throw new RuntimeException('Expected running lease IDs.');
            }

            $blocked = big_run_try_promote_probe_to_discovery(
                $probe_one[2],
                $this->base_directory,
                101.0
            );
            $this->assertFalse($blocked[0]);
            $this->assertSame('total_full', $blocked[3]);

            big_run_release($run_one[2], $this->base_directory);
            $promoted = big_run_try_promote_probe_to_discovery(
                $probe_two[2],
                $this->base_directory,
                102.0
            );
            $this->assertTrue($promoted[0]);
            $this->assertSame($probe_two[2], $promoted[2]);

            $state = $this->readBigRunState();
            $this->assertIsArray($state);
            if (!is_array($state)) {
                throw new RuntimeException('Expected big-run state array.');
            }
            $entries = $state['entries'] ?? null;
            $this->assertIsArray($entries);
            if (!is_array($entries)) {
                throw new RuntimeException('Expected big-run entries array.');
            }
            $this->assertCount(2, $entries);
            $promoted_entry = $entries[$probe_two[2]] ?? null;
            $this->assertIsArray($promoted_entry);
            if (!is_array($promoted_entry)) {
                throw new RuntimeException('Expected promoted discovery entry.');
            }
            $this->assertSame('discovery', $promoted_entry['phase'] ?? null);
            $this->assertSame(400.0, $state['tokens'] ?? null);

            big_run_release($probe_two[2], $this->base_directory);
            big_run_release($run_two[2], $this->base_directory);
        } finally {
            if ($previous_total === false) {
                putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL');
            } else {
                putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=' . $previous_total);
            }
            if ($previous_probes === false) {
                putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES');
            } else {
                putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=' . $previous_probes);
            }
        }
    }

    public function testSharedLeaseReportsLostAfterStalePruneAndReplacement(): void {
        $previous_stale = getenv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS');

        try {
            putenv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS=60');
            $old = big_run_try_acquire_discovery($this->base_directory, 100.0);
            $this->assertTrue($old[0]);
            $this->assertIsString($old[2]);
            if (!is_string($old[2])) {
                throw new RuntimeException('Expected original discovery lease ID.');
            }

            $replacement = big_run_try_acquire_discovery($this->base_directory, 161.0);
            $this->assertTrue($replacement[0]);
            $this->assertIsString($replacement[2]);
            if (!is_string($replacement[2])) {
                throw new RuntimeException('Expected replacement discovery lease ID.');
            }

            $this->assertSame(
                'lost',
                big_run_heartbeat_status($old[2], $this->base_directory, 162.0)
            );
            $this->assertSame(
                'ok',
                big_run_heartbeat_status($replacement[2], $this->base_directory, 162.0)
            );

            $state = $this->readBigRunState();
            $this->assertIsArray($state);
            if (!is_array($state)) {
                throw new RuntimeException('Expected big-run state array.');
            }
            $entries = $state['entries'] ?? null;
            $this->assertIsArray($entries);
            if (!is_array($entries)) {
                throw new RuntimeException('Expected big-run entries array.');
            }
            $this->assertArrayNotHasKey($old[2], $entries);
            $this->assertArrayHasKey($replacement[2], $entries);
        } finally {
            if ($previous_stale === false) {
                putenv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS');
            } else {
                putenv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS=' . $previous_stale);
            }
        }
    }

    public function testMalformedIndividualLeaseFailsClosed(): void {
        $state_directory = big_run_prepare_state_directory($this->base_directory);
        $this->assertIsString($state_directory);
        $state = [
            'tokens' => 400.0,
            'updated' => 100.0,
            'entries' => [
                'bgood' => [
                    'started_at' => 100.0,
                    'tier' => 'small',
                    'last_seen_at' => 100.0,
                    'phase' => 'running',
                ],
                'bbad' => [
                    'started_at' => 100.0,
                    'tier' => 'small',
                    'last_seen_at' => 100.0,
                ],
            ],
        ];
        $encoded = json_encode($state, JSON_THROW_ON_ERROR);
        $this->assertNotFalse(file_put_contents(big_run_state_path($this->base_directory), $encoded));

        $result = big_run_try_acquire(5, 'category', $this->base_directory, 100.0);
        $this->assertSame([false, 2, null, 'retry_later', 0], $result);
    }

    public function testOversizedBigRunStateFailsClosed(): void {
        $state_directory = big_run_prepare_state_directory($this->base_directory);
        $this->assertIsString($state_directory);
        $oversized = str_repeat(' ', BIG_RUN_STATE_MAX_BYTES + 1);
        $this->assertNotFalse(
            file_put_contents(big_run_state_path($this->base_directory), $oversized)
        );

        $result = big_run_try_acquire(5, 'category', $this->base_directory, 100.0);
        $this->assertSame([false, 2, null, 'retry_later', 0], $result);
    }

    public function testSymlinkedBigRunStateDirectoryFailsClosed(): void {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink() is required for this filesystem-hardening test.');
        }

        $target = $this->base_directory . DIRECTORY_SEPARATOR . 'state-target';
        $state_directory = big_run_state_directory($this->base_directory);
        $this->assertTrue(mkdir($target, 0700));
        if (!@symlink($target, $state_directory)) {
            @rmdir($target);
            $this->markTestSkipped('Filesystem does not permit symlink creation.');
        }

        try {
            $result = big_run_try_acquire(5, 'category', $this->base_directory, 100.0);
            $this->assertSame([false, 2, null, 'retry_later', 0], $result);
        } finally {
            @unlink($state_directory);
            @rmdir($target);
        }
    }

    public function testConcurrentProbeAcquisitionNeverOversubscribesProbePool(): void {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required for the probe concurrency regression test.');
        }

        $previous_probes = getenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES');
        putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=3');

        $request_rate_limit = realpath(dirname(__DIR__, 3) . '/src/includes/RequestRateLimit.php');
        $this->assertIsString($request_rate_limit);
        $barrier = $this->base_directory . DIRECTORY_SEPARATOR . 'probe-start';
        $child_script = $this->base_directory . DIRECTORY_SEPARATOR . 'probe-child.php';
        $child_source = <<<'PHP'
<?php
declare(strict_types=1);

require $argv[1];
$base = $argv[2];
$barrier = $argv[3];
$result_path = $argv[4];

$deadline = microtime(true) + 30.0;
while (!file_exists($barrier) && microtime(true) < $deadline) {
    usleep(1000);
}

$result = [false, 2, null, 'retry_later', 0];
for ($attempt = 0; $attempt < 1000; ++$attempt) {
    $result = big_run_try_acquire_probe($base, 100.0);
    if (($result[3] ?? null) !== 'retry_later') {
        break;
    }
    usleep(2000);
}
file_put_contents($result_path, json_encode($result, JSON_THROW_ON_ERROR));
PHP;
        $this->assertSame(
            mb_strlen($child_source, '8bit'),
            file_put_contents($child_script, $child_source)
        );

        $workers = big_run_max_discovery_probes() + 4;
        $processes = [];
        $result_paths = [];
        $null_device = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        try {
            for ($i = 0; $i < $workers; ++$i) {
                $result_path =
                    $this->base_directory . DIRECTORY_SEPARATOR . 'probe-result-' . (string) $i . '.json';
                $result_paths[] = $result_path;
                $_pipes = [];
                $process = proc_open( // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
                    [
                        PHP_BINARY,
                        $child_script,
                        $request_rate_limit,
                        $this->base_directory,
                        $barrier,
                        $result_path,
                    ],
                    [
                        0 => ['file', $null_device, 'r'],
                        1 => ['file', $null_device, 'a'],
                        2 => ['file', $null_device, 'a'],
                    ],
                    $_pipes
                );
                $this->assertIsResource($process);
                if (!is_resource($process)) {
                    throw new RuntimeException('Unable to start probe concurrency child.');
                }
                $processes[] = $process;
            }

            $this->assertTrue(touch($barrier));
            foreach ($processes as $key => $process) {
                $this->assertSame(0, proc_close($process));
                unset($processes[$key]);
            }

            $admitted = 0;
            $probe_full = 0;
            foreach ($result_paths as $result_path) {
                $raw = file_get_contents($result_path);
                $this->assertIsString($raw);
                $result = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                $this->assertIsArray($result);
                if (($result[0] ?? false) === true) {
                    ++$admitted;
                } elseif (($result[3] ?? null) === 'probe_full') {
                    ++$probe_full;
                }
            }

            $this->assertSame(big_run_max_discovery_probes(), $admitted);
            $this->assertSame($workers - big_run_max_discovery_probes(), $probe_full);
            $state = $this->readBigRunState();
            $this->assertIsArray($state);
            if (!is_array($state)) {
                throw new RuntimeException('Expected big-run state array.');
            }
            $entries = $state['entries'] ?? null;
            $this->assertIsArray($entries);
            if (!is_array($entries)) {
                throw new RuntimeException('Expected big-run entries array.');
            }
            $this->assertCount($admitted, $entries);
            $this->assertSame(400.0, $state['tokens'] ?? null);
            foreach ($entries as $entry) {
                $this->assertIsArray($entry);
                if (!is_array($entry)) {
                    throw new RuntimeException('Expected probe entry array.');
                }
                $this->assertSame('probe', $entry['phase'] ?? null);
            }
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    @proc_terminate($process);
                    @proc_close($process);
                }
            }
            @unlink($barrier);
            @unlink($child_script);
            foreach ($result_paths as $result_path) {
                @unlink($result_path);
            }
            if ($previous_probes === false) {
                putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES');
            } else {
                putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=' . $previous_probes);
            }
        }
    }

    public function testDiscoveryProbeLimitCanBeTunedFromEnvironment(): void {
        $previous = getenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES');
        try {
            putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=7');
            $this->assertSame(7, big_run_max_discovery_probes());
        } finally {
            if ($previous === false) {
                putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES');
            } else {
                putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=' . $previous);
            }
        }
    }

    public function testMalformedJsonBigRunStateFailsClosed(): void {
        $state_directory = big_run_prepare_state_directory($this->base_directory);
        $this->assertIsString($state_directory);
        $this->assertNotFalse(
            file_put_contents(big_run_state_path($this->base_directory), '{"tokens":')
        );

        $result = big_run_try_acquire(5, 'category', $this->base_directory, 100.0);
        $this->assertSame([false, 2, null, 'retry_later', 0], $result);
    }

    public function testMalformedStateHeartbeatIsRetryableNotOwnershipLoss(): void {
        $lease = big_run_try_acquire_discovery($this->base_directory, 100.0);
        $this->assertTrue($lease[0]);
        $this->assertIsString($lease[2]);
        if (!is_string($lease[2])) {
            throw new RuntimeException('Expected discovery lease ID.');
        }

        $this->assertNotFalse(
            file_put_contents(big_run_state_path($this->base_directory), '{"entries":')
        );
        $this->assertSame(
            'retry',
            big_run_heartbeat_status($lease[2], $this->base_directory, 101.0)
        );
    }

    public function testDiscoveryProbeUsesCandidateAndAbsoluteBatchBounds(): void {
        $this->assertFalse(big_run_discovery_probe_requires_lease(24, 4, true));
        $this->assertTrue(big_run_discovery_probe_requires_lease(25, 1, true));
        $this->assertTrue(big_run_discovery_probe_requires_lease(0, 5, true));
        $this->assertFalse(big_run_discovery_probe_requires_lease(1000, 1000, false));
    }

    public function testBigRunTierClassifiesByPageCount(): void {
        $this->assertSame('small', big_run_tier(4));
        $this->assertSame('small', big_run_tier(49));
        $this->assertSame('large', big_run_tier(50));
        $this->assertSame('large', big_run_tier(500));
    }

    public function testBigRunTokenCostNormalizesUntrustedTypes(): void {
        $this->assertSame(8, big_run_token_cost(5, 'category'));
        $this->assertSame(74, big_run_token_cost(49, 'category'));
        $this->assertSame(113, big_run_token_cost(50, 'category'));
        $this->assertSame(225, big_run_token_cost(100, 'template'));
        $this->assertSame(15, big_run_token_cost(10, 'unknown-type'));
        $this->assertSame(0, big_run_token_cost(100, 'testing'));
    }

    public function testBigRunTokenCostIsCappedAtCapacity(): void {
        $this->assertSame(400, big_run_token_cost(178, 'category'));
        $this->assertSame(400, big_run_token_cost(1000, 'category'));
    }

    public function testBigRunEntryIdSurvivesJsonRoundTripAsStringKey(): void {
        $result = big_run_try_acquire_discovery(null, 100.0);
        $this->assertTrue($result[0]);
        $entry_id = $result[2];
        $this->assertIsString($entry_id);
        $this->assertMatchesRegularExpression('~\Ab[a-f0-9]{16}\z~D', $entry_id);

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $first_key = array_key_first($state['entries']);
        $this->assertIsString($first_key);
        $this->assertSame($entry_id, $first_key);
    }

    public function testBigRunAcquireAdmitsAndDeductsTokens(): void {
        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertTrue($result[0]);
        $this->assertIsString($result[2]);

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertSame(392.0, $state['tokens']);
        $this->assertCount(1, $state['entries']);
        $entries = $state['entries'];
        $this->assertIsArray($entries);
        $entry = array_values($entries)[0] ?? null;
        $this->assertIsArray($entry);
        $this->assertSame('running', $entry['phase'] ?? null);
    }

    public function testBigRunUsesSeparatePermanentLockAndStateFiles(): void {
        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertTrue($result[0]);

        $lock_path = big_run_lock_path($this->base_directory);
        $state_path = big_run_state_path($this->base_directory);
        $this->assertNotSame($lock_path, $state_path);
        $this->assertFileExists($lock_path);
        $this->assertFileExists($state_path);

        $raw = file_get_contents($state_path);
        $this->assertIsString($raw);
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('entries', $decoded);
    }

    public function testBigRunAcquireDefersWhenTokensInsufficient(): void {
        $this->writeBigRunState('{"tokens":0.0,"updated":100.0,"entries":{}}');
        $result = big_run_try_acquire(50, 'category', null, 100.0);
        $this->assertSame([false, 29, null, 'tokens', 0], $result);
    }

    public function testBigRunAcquireDistinguishesTotalPoolPressure(): void {
        $entries = [];
        for ($i = 0; $i < BIG_RUN_MAX_TOTAL; ++$i) {
            $entries['e' . $i] = [
                'started_at' => 100.0,
                'tier' => 'small',
                'last_seen_at' => 100.0,
                'phase' => 'running',
            ];
        }
        $this->writeBigRunState('{"tokens":400.0,"updated":100.0,"entries":' . json_encode($entries, JSON_THROW_ON_ERROR) . '}');

        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertSame([false, 30, null, 'total_full', 10], $result);
    }

    public function testBigRunAcquireDistinguishesLargeSubpoolPressure(): void {
        $entries = [];
        for ($i = 0; $i < BIG_RUN_MAX_LARGE; ++$i) {
            $entries['l' . $i] = [
                'started_at' => 100.0,
                'tier' => 'large',
                'last_seen_at' => 100.0,
                'phase' => 'running',
            ];
        }
        $this->writeBigRunState('{"tokens":400.0,"updated":100.0,"entries":' . json_encode($entries, JSON_THROW_ON_ERROR) . '}');

        $large_result = big_run_try_acquire(50, 'category', null, 100.0);
        $this->assertSame([false, 30, null, 'large_full', 4], $large_result);

        $small_result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertTrue($small_result[0]);
        $this->assertSame(5, $small_result[4]);
    }

    public function testDiscoveryLeaseAtomicallyConsumesTotalPoolSlotWithoutTokens(): void {
        for ($i = 0; $i < BIG_RUN_MAX_TOTAL; ++$i) {
            $result = big_run_try_acquire_discovery(null, 100.0);
            $this->assertTrue($result[0]);
            $this->assertIsString($result[2]);
        }

        $denied = big_run_try_acquire_discovery(null, 100.0);
        $this->assertSame([false, 30, null, 'total_full', 10], $denied);

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertSame(400.0, $state['tokens']);
        $this->assertCount(10, $state['entries']);
        foreach ($state['entries'] as $entry) {
            $this->assertSame('discovery', $entry['phase']);
        }
    }

    public function testDiscoveryLeasePromotesAndChargesExactlyOnce(): void {
        $discovery = big_run_try_acquire_discovery(null, 100.0);
        $this->assertTrue($discovery[0]);
        $entry_id = $discovery[2];
        $this->assertIsString($entry_id);

        $promoted = big_run_try_promote($entry_id, 5, 'category', null, 101.0);
        $this->assertTrue($promoted[0]);
        $this->assertSame($entry_id, $promoted[2]);

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertSame(392.0, $state['tokens']);
        $this->assertSame('small', $state['entries'][$entry_id]['tier']);
        $this->assertSame('running', $state['entries'][$entry_id]['phase']);
    }

    public function testDiscoveryLeaseCannotBePromotedTwice(): void {
        $discovery = big_run_try_acquire_discovery(null, 100.0);
        $this->assertTrue($discovery[0]);
        $entry_id = $discovery[2];
        $this->assertIsString($entry_id);

        $first = big_run_try_promote($entry_id, 5, 'category', null, 101.0);
        $this->assertTrue($first[0]);
        $state_after_first = $this->readBigRunState();
        $this->assertIsArray($state_after_first);
        $this->assertSame(392.0, $state_after_first['tokens']);

        $second = big_run_try_promote($entry_id, 5, 'category', null, 102.0);
        $this->assertFalse($second[0]);
        $this->assertSame('retry_later', $second[3]);

        $state_after_second = $this->readBigRunState();
        $this->assertIsArray($state_after_second);
        $this->assertSame(392.0, $state_after_second['tokens']);
        $this->assertSame('running', $state_after_second['entries'][$entry_id]['phase']);
    }

    public function testMissingDiscoveryLeaseCannotPromoteIntoUntrackedRun(): void {
        $result = big_run_try_promote('missing-entry', 5, 'category', null, 100.0);
        $this->assertFalse($result[0]);
        $this->assertSame('retry_later', $result[3]);
    }

    public function testTokenExemptRunStillConsumesConcurrencyWithoutTokens(): void {
        $result = big_run_try_acquire(50, 'category', null, 100.0, false);
        $this->assertTrue($result[0]);
        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertSame(400.0, $state['tokens']);
        $this->assertCount(1, $state['entries']);
        $entries = $state['entries'];
        $this->assertIsArray($entries);
        $entry = array_values($entries)[0] ?? null;
        $this->assertIsArray($entry);
        $this->assertSame('large', $entry['tier'] ?? null);
    }

    public function testFailedLargePromotionReleasesDiscoveryLeaseWithoutCharging(): void {
        $entries = [];
        for ($i = 0; $i < BIG_RUN_MAX_LARGE; ++$i) {
            $entries['l' . $i] = [
                'started_at' => 100.0,
                'tier' => 'large',
                'last_seen_at' => 100.0,
                'phase' => 'running',
            ];
        }
        $this->writeBigRunState('{"tokens":400.0,"updated":100.0,"entries":' . json_encode($entries, JSON_THROW_ON_ERROR) . '}');

        $discovery = big_run_try_acquire_discovery(null, 100.0);
        $this->assertTrue($discovery[0]);
        $entry_id = $discovery[2];
        $this->assertIsString($entry_id);

        $promoted = big_run_try_promote($entry_id, 50, 'category', null, 100.0);
        $this->assertSame([false, 30, null, 'large_full', 4], $promoted);

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertSame(400.0, $state['tokens']);
        $this->assertArrayNotHasKey($entry_id, $state['entries']);
        $this->assertCount(4, $state['entries']);
    }

    public function testBigRunReleaseFreesSlot(): void {
        $result = big_run_try_acquire_discovery(null, 100.0);
        $this->assertTrue($result[0]);
        $entry_id = $result[2];
        $this->assertIsString($entry_id);
        big_run_release($entry_id, null);

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertCount(0, $state['entries']);
    }

    public function testBigRunStaleEntriesArePrunedOnAcquire(): void {
        $entries = ['stale' => [
            'started_at' => 100.0,
            'tier' => 'small',
            'last_seen_at' => 100.0,
            'phase' => 'running',
        ]];
        $this->writeBigRunState('{"tokens":400.0,"updated":100.0,"entries":' . json_encode($entries, JSON_THROW_ON_ERROR) . '}');

        $result = big_run_try_acquire(5, 'category', null, 1001.0);
        $this->assertTrue($result[0]);
        $this->assertSame(1, $result[4]);
    }

    public function testBigRunHeartbeatRenewsLongRunningLease(): void {
        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertTrue($result[0]);
        $entry_id = $result[2];
        $this->assertIsString($entry_id);

        $this->assertTrue(big_run_heartbeat($entry_id, null, 800.0));
        $second = big_run_try_acquire(5, 'category', null, 1000.0);
        $this->assertTrue($second[0]);
        $this->assertSame(2, $second[4]);
    }

    public function testBigRunSavedTokensAreClampedToConfiguredCapacity(): void {
        $this->writeBigRunState('{"tokens":500.0,"updated":100.0,"entries":{}}');
        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertTrue($result[0]);
        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertSame(392.0, $state['tokens']);
    }

    public function testBigRunCorruptTopLevelStateFailsClosed(): void {
        $state_path = $this->bigRunStatePath();
        $state_directory = dirname($state_path);
        $this->assertTrue(mkdir($state_directory, 0700, true));
        $this->assertNotFalse(file_put_contents($state_path, '{not-json'));

        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertSame([false, 2, null, 'retry_later', 0], $result);
        $this->assertSame('{not-json', file_get_contents($state_path));
    }

    public function testBigRunIncompleteTopLevelStateFailsClosed(): void {
        foreach ([
            '{"tokens":400.0,"updated":100.0}',
            '{"tokens":400.0,"updated":100.0,"entries":null}',
            '{"updated":100.0,"entries":{}}',
            '{"tokens":400.0,"entries":{}}',
        ] as $raw_state) {
            $this->writeBigRunState($raw_state);
            $result = big_run_try_acquire(5, 'category', null, 100.0);
            $this->assertSame([false, 2, null, 'retry_later', 0], $result);
        }
    }

    public function testBigRunNonFiniteTopLevelStateFailsClosed(): void {
        $this->writeBigRunState('{"tokens":1e9999,"updated":100.0,"entries":{}}');
        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertSame([false, 2, null, 'retry_later', 0], $result);
    }

    public function testBigRunMalformedEntrySchemaFailsWholeSnapshotClosed(): void {
        $entries = [
            'valid' => ['started_at' => 90.0, 'tier' => 'small', 'last_seen_at' => 90.0, 'phase' => 'running'],
            'bad_tier' => ['started_at' => 90.0, 'tier' => 'bogus', 'last_seen_at' => 90.0, 'phase' => 'running'],
        ];
        $raw = '{"tokens":400.0,"updated":100.0,"entries":' .
            json_encode($entries, JSON_THROW_ON_ERROR) . '}';
        $this->writeBigRunState($raw);

        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertSame([false, 2, null, 'retry_later', 0], $result);
        $this->assertSame($raw, file_get_contents($this->bigRunStatePath()));
    }

    public function testBigRunMalformedEntryValueFailsWholeSnapshotClosed(): void {
        $entries = [
            'valid' => ['started_at' => 90.0, 'tier' => 'small', 'last_seen_at' => 90.0, 'phase' => 'running'],
            'negative' => ['started_at' => -1.0, 'tier' => 'small', 'last_seen_at' => 90.0, 'phase' => 'running'],
        ];
        $raw = '{"tokens":400.0,"updated":100.0,"entries":' .
            json_encode($entries, JSON_THROW_ON_ERROR) . '}';
        $this->writeBigRunState($raw);

        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertSame([false, 2, null, 'retry_later', 0], $result);
        $this->assertSame($raw, file_get_contents($this->bigRunStatePath()));
    }

    public function testBigRunFutureTimesAreClamped(): void {
        $entries = [
            'future' => ['started_at' => 500.0, 'tier' => 'small', 'last_seen_at' => 600.0, 'phase' => 'running'],
        ];
        $this->writeBigRunState(
            '{"tokens":400.0,"updated":100.0,"entries":' .
            json_encode($entries, JSON_THROW_ON_ERROR) . '}'
        );

        $result = big_run_try_acquire(5, 'category', null, 100.0);
        $this->assertTrue($result[0]);

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertSame(100.0, $state['entries']['future']['started_at']);
        $this->assertSame(100.0, $state['entries']['future']['last_seen_at']);
    }

    public function testBigRunCombinedDenialUsesLongerBackoff(): void {
        $entries = [];
        for ($i = 0; $i < BIG_RUN_MAX_TOTAL; ++$i) {
            $entries['e' . $i] = [
                'started_at' => 100.0,
                'tier' => 'small',
                'last_seen_at' => 100.0,
                'phase' => 'running',
            ];
        }
        $this->writeBigRunState('{"tokens":0.0,"updated":100.0,"entries":' . json_encode($entries, JSON_THROW_ON_ERROR) . '}');

        // Pool backoff is 30s, token wait is 29s: total-pool pressure wins.
        $result = big_run_try_acquire(50, 'category', null, 100.0);
        $this->assertSame([false, 30, null, 'total_full', 10], $result);
    }

    public function testBigRunAcquireFailsClosedOnStorageError(): void {
        $blocking_path = $this->base_directory . DIRECTORY_SEPARATOR . 'not-a-directory';
        $this->assertNotFalse(file_put_contents($blocking_path, 'x'));

        try {
            putenv('PHP_RATE_LIMIT_DIRECTORY=' . $blocking_path);
            $result = big_run_try_acquire(5, 'category', null, 100.0);
            $this->assertSame([false, 2, null, 'retry_later', 0], $result);
            $discovery = big_run_try_acquire_discovery(null, 100.0);
            $this->assertSame([false, 2, null, 'retry_later', 0], $discovery);
        } finally {
            putenv('PHP_RATE_LIMIT_DIRECTORY=' . $this->base_directory);
            @unlink($blocking_path);
        }
    }

    public function testBigRunLockContentionDefersImmediately(): void {
        $lock_path = big_run_lock_path($this->base_directory);
        $state_directory = dirname($lock_path);
        $this->assertTrue(mkdir($state_directory, 0700, true));
        $handle = fopen($lock_path, 'c+');
        $this->assertIsResource($handle);

        try {
            $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
            $started = microtime(true);
            $result = big_run_try_acquire(5, 'category', null, 100.0);
            $this->assertSame([false, 1, null, 'retry_later', 0], $result);
            $this->assertLessThan(0.5, microtime(true) - $started);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function testLargeDiscoverySubpoolIsEnforcedBeforeRunningPromotion(): void {
        $ids = [];
        for ($i = 0; $i < big_run_max_large(); ++$i) {
            $lease = big_run_try_acquire_discovery(null, 100.0);
            $this->assertTrue($lease[0]);
            $this->assertIsString($lease[2]);
            $ids[] = $lease[2];
            $marked = big_run_try_mark_discovery_large($lease[2], null, 100.0);
            $this->assertTrue($marked[0]);
        }

        // A preclassified large discovery lease must not count against itself
        // during final promotion.
        $first_id = $ids[0] ?? null;
        $this->assertIsString($first_id);
        if (!is_string($first_id)) {
            throw new RuntimeException('Expected discovery lease id.');
        }
        $promoted = big_run_try_promote($first_id, 50, 'category', null, 101.0);
        $this->assertTrue($promoted[0]);

        $extra = big_run_try_acquire_discovery(null, 102.0);
        $this->assertTrue($extra[0]);
        $this->assertIsString($extra[2]);
        $denied = big_run_try_mark_discovery_large($extra[2], null, 102.0);
        $this->assertFalse($denied[0]);
        $this->assertSame('large_full', $denied[3]);

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertArrayNotHasKey($extra[2], $state['entries']);
        $this->assertSame(big_run_max_large(), big_run_count_tier($state['entries'], 'large'));
    }

    public function testBigRunUntrustedSourceTypesBillAtWebformRateWithoutCallerNormalization(): void {
        $this->assertSame('category', big_run_charge_type('category'));
        $this->assertSame('webform_linked', big_run_charge_type('webform_linked'));
        $this->assertSame('webform', big_run_charge_type('template'));
        $this->assertSame('webform', big_run_charge_type('automated_tools'));
        $this->assertSame('webform', big_run_charge_type('toolbar'));
        $this->assertSame('webform', big_run_charge_type('something-made-up'));
        $this->assertSame(113, big_run_token_cost(50, 'template'));
        $this->assertSame(113, big_run_token_cost(50, 'automated_tools'));
        $this->assertSame(113, big_run_token_cost(50, 'webform'));
    }

    public function testConcurrentDiscoveryAcquisitionNeverOversubscribesPool(): void {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required for the concurrency regression test');
        }

        $request_rate_limit = realpath(dirname(__DIR__, 3) . '/src/includes/RequestRateLimit.php');
        $this->assertIsString($request_rate_limit);
        $barrier = $this->base_directory . DIRECTORY_SEPARATOR . 'discovery-start';
        $child_script = $this->base_directory . DIRECTORY_SEPARATOR . 'discovery-child.php';
        $child_source = <<<'PHP'
<?php
declare(strict_types=1);

require $argv[1];
$base = $argv[2];
$barrier = $argv[3];
$result_path = $argv[4];

$deadline = microtime(true) + 30.0;
while (!file_exists($barrier) && microtime(true) < $deadline) {
    usleep(1000);
}

$result = [false, 2, null, 'retry_later', 0];
for ($attempt = 0; $attempt < 1000; ++$attempt) {
    $result = big_run_try_acquire_discovery($base, 100.0);
    if (($result[3] ?? null) !== 'retry_later') {
        break;
    }
    usleep(2000);
}
file_put_contents($result_path, json_encode($result, JSON_THROW_ON_ERROR));
PHP;
        $this->assertNotFalse(file_put_contents($child_script, $child_source));

        $workers = big_run_max_total() + 8;
        $processes = [];
        $result_paths = [];
        $null_device = '/dev/null';
        for ($i = 0; $i < $workers; ++$i) {
            $result_path = $this->base_directory . DIRECTORY_SEPARATOR . 'result-' . (string) $i . '.json';
            $result_paths[] = $result_path;
            $_pipes = [];
            // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- process-isolation regression test
            $process = proc_open(
                [PHP_BINARY, $child_script, $request_rate_limit, $this->base_directory, $barrier, $result_path],
                [
                    0 => ['file', $null_device, 'r'],
                    1 => ['file', $null_device, 'a'],
                    2 => ['file', $null_device, 'a'],
                ],
                $_pipes
            );
            $this->assertIsResource($process);
            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start discovery-concurrency test child.');
            }
            $processes[] = $process;
        }

        $this->assertTrue(touch($barrier));
        foreach ($processes as $process) {
            $this->assertSame(0, proc_close($process));
        }

        $admitted = 0;
        $total_full = 0;
        foreach ($result_paths as $result_path) {
            $raw = file_get_contents($result_path);
            $this->assertIsString($raw);
            $result = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            $this->assertIsArray($result);
            if (($result[0] ?? false) === true) {
                ++$admitted;
            } elseif (($result[3] ?? null) === 'total_full') {
                ++$total_full;
            }
        }

        $this->assertSame(big_run_max_total(), $admitted);
        $this->assertSame($workers - big_run_max_total(), $total_full);
        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertCount($admitted, $state['entries']);
        $this->assertSame(400.0, $state['tokens']);
    }

    public function testHeartbeatIntervalKeepsRetryMarginInsideStaleTimeout(): void {
        putenv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS=60');
        putenv('CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS=59');
        $this->assertSame(20, big_run_heartbeat_interval_seconds());

        putenv('CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS=15');
        $this->assertSame(15, big_run_heartbeat_interval_seconds());
    }

    public function testBigRunConfigurationCanBeTunedFromEnvironment(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=6');
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=3');
        putenv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY=250');
        putenv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND=2.5');

        $this->assertSame(6, big_run_max_total());
        $this->assertSame(3, big_run_max_large());
        $this->assertSame(250, big_run_token_capacity());
        $this->assertSame(2.5, big_run_token_refill_per_second());
    }

    private function bigRunStatePath(): string {
        return $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY .
            DIRECTORY_SEPARATOR .
            BIG_RUN_STATE_FILE;
    }

    private function writeBigRunState(string $raw_state): void {
        $state_path = $this->bigRunStatePath();
        $state_directory = dirname($state_path);
        if (!is_dir($state_directory)) {
            $this->assertTrue(mkdir($state_directory, 0700, true));
        }
        $this->assertNotFalse(file_put_contents($state_path, $raw_state));
    }

    private function readBigRunState(): mixed {
        $raw = file_get_contents($this->bigRunStatePath());
        $this->assertIsString($raw);
        $state = json_decode($raw, true);
        $this->assertIsArray($state);
        return $state;
    }

    public function testBigRunRecoveryCheckReportsMalformedStateReason(): void {
        $this->writeBigRunState('{not-json');

        $result = big_run_recovery_check(null, 100.0, 1);
        $this->assertFalse($result['ok']);
        $this->assertSame('json_decode', $result['reason']);
        $this->assertSame($this->bigRunStatePath(), $result['state_path']);
        $this->assertNull($result['lease_entries']);
        $this->assertNull($result['tokens']);
    }

    public function testBigRunRecoveryCheckReportsValidState(): void {
        $entries = [
            'active' => [
                'started_at' => 90.0,
                'tier' => 'small',
                'last_seen_at' => 95.0,
                'phase' => 'running',
            ],
        ];
        $this->writeBigRunState(
            '{"tokens":123.0,"updated":100.0,"entries":' .
            json_encode($entries, JSON_THROW_ON_ERROR) . '}'
        );

        $result = big_run_recovery_check(null, 100.0, 1);
        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(1, $result['lease_entries']);
        $this->assertSame(123.0, $result['tokens']);
    }

    public function testBigRunRecoveryResetPreservesSnapshotAndWritesFreshState(): void {
        $raw = '{not-json';
        $this->writeBigRunState($raw);

        $result = big_run_recovery_reset(null, 100.0, 1);
        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertIsString($result['backup_path']);
        $this->assertFileExists($result['backup_path']);
        $this->assertSame($raw, file_get_contents($result['backup_path']));

        $state = $this->readBigRunState();
        $this->assertIsArray($state);
        $this->assertSame((float) big_run_token_capacity(), $state['tokens']);
        $this->assertSame(100.0, $state['updated']);
        $this->assertSame([], $state['entries']);

        $state_mode = fileperms($this->bigRunStatePath());
        $backup_mode = fileperms($result['backup_path']);
        $directory_mode = fileperms(dirname($this->bigRunStatePath()));
        $this->assertIsInt($state_mode);
        $this->assertIsInt($backup_mode);
        $this->assertIsInt($directory_mode);
        $this->assertSame(0600, $state_mode & 0777);
        $this->assertSame(0600, $backup_mode & 0777);
        $this->assertSame(0700, $directory_mode & 0777);
    }

    public function testBigRunRecoveryResetRefusesBusyPermanentLock(): void {
        $lock_handle = big_run_open_lock_handle($this->base_directory);
        $this->assertIsResource($lock_handle);

        try {
            $this->assertTrue(flock($lock_handle, LOCK_EX | LOCK_NB));
            $result = big_run_recovery_reset(null, 100.0, 1);
            $this->assertFalse($result['ok']);
            $this->assertSame('lock_busy', $result['reason']);
        } finally {
            flock($lock_handle, LOCK_UN);
            fclose($lock_handle);
        }
    }

    public function testBigRunRecoveryResetRejectsSymlinkStatePath(): void {
        $state_directory = dirname($this->bigRunStatePath());
        if (!is_dir($state_directory)) {
            $this->assertTrue(mkdir($state_directory, 0700, true));
        }

        $outside = $this->base_directory . DIRECTORY_SEPARATOR . 'outside-state';
        $this->assertNotFalse(file_put_contents($outside, 'do-not-touch'));
        if (!@symlink($outside, $this->bigRunStatePath())) {
            @unlink($outside);
            $this->markTestSkipped('symlink support is required for this recovery test');
        }

        $result = big_run_recovery_reset(null, 100.0, 1);
        $this->assertFalse($result['ok']);
        $this->assertSame('state_path_symlink', $result['reason']);
        $this->assertSame('do-not-touch', file_get_contents($outside));

        @unlink($this->bigRunStatePath());
        @unlink($outside);
    }
}
