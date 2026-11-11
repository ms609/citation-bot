<?php
declare(strict_types=1);

/*
 * Network-free edge-case tests for the big-run admission gate helpers in
 * RequestRateLimit.php: configuration parsing, token arithmetic, snapshot
 * validation, every admission phase, recovery, heartbeats and lease loss.
 *
 * Every test works in its own temporary base directory and passes an explicit
 * timestamp, so nothing depends on the real clock, the shared rate-limit
 * directory or the network.  Environment overrides are restored after each test.
 * The lease-loss terminator calls exit(), so it runs in a short-lived subprocess.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class RequestRateLimitHelpersTest extends PHPUnit\Framework\TestCase {

    private const NOW = 1000000.0;

    /** @var array<string> */
    private array $directories = [];

    /** @var array<string> */
    private array $environment_names = [];

    #[\Override]
    protected function tearDown(): void {
        foreach ($this->environment_names as $name) {
            putenv($name);
        }
        $this->environment_names = [];
        foreach ($this->directories as $directory) {
            $this->removeTree($directory);
        }
        $this->directories = [];
        unset($GLOBALS['citation_bot_big_run_current']);
        big_run_clear_last_state_invalid_reason();
        parent::tearDown();
    }

    private function removeTree(string $path): void {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..') {
                    $this->removeTree($path . DIRECTORY_SEPARATOR . $item);
                }
            }
        }
        @rmdir($path);
    }

    private function newBase(): string {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rrl-helpers-' . bin2hex(random_bytes(6));
        $this->assertTrue(mkdir($base, 0700));
        $this->directories[] = $base;
        return $base;
    }

    private function setEnv(string $name, string $value): void {
        $this->environment_names[] = $name;
        putenv($name . '=' . $value);
    }

    /**
     * @param string $base
     * @param array<string, mixed> $data
     */
    private function writeState(string $base, array $data): string {
        $this->assertNotNull(big_run_prepare_state_directory($base));
        $path = big_run_state_path($base);
        $this->assertNotFalse(file_put_contents($path, json_encode($data)));
        return $path;
    }

    private function writeRawState(string $base, string $raw): string {
        $this->assertNotNull(big_run_prepare_state_directory($base));
        $path = big_run_state_path($base);
        $this->assertNotFalse(file_put_contents($path, $raw));
        return $path;
    }

    /** @return array{started_at: float, tier: string, last_seen_at: float, phase: string} */
    private function entry(float $started, string $tier, float $last_seen, string $phase): array {
        return ['started_at' => $started, 'tier' => $tier, 'last_seen_at' => $last_seen, 'phase' => $phase];
    }

    /** @return array{0: float, 1: float, 2: array<string, array{started_at: float, tier: string, last_seen_at: float, phase: string}>, 3: bool} */
    private function readState(string $base): array {
        return big_run_read_state_file(big_run_state_path($base), 1.0e12);
    }

    /** @return array{started_at: float, tier: string, last_seen_at: float, phase: string} */
    private function entryOf(string $base, string $id): array {
        [, , $entries] = $this->readState($base);
        $this->assertArrayHasKey($id, $entries);
        return $entries[$id];
    }

    // ---- configuration parsing ----------------------------------------------

    public function testConfigIntAcceptsOnlyPlainDigitsInsideTheRange(): void {
        $name = 'CITATION_BOT_TEST_CONFIG_INT';
        $this->assertSame(7, big_run_config_int($name, 7, 1, 100)); // unset
        $accepted = ['1' => 1, '12' => 12, '100' => 100, '007' => 7];
        foreach ($accepted as $raw => $expected) {
            $this->setEnv($name, (string) $raw);
            $this->assertSame($expected, big_run_config_int($name, 50, 1, 100), "value {$raw}");
        }
        $rejected = ['', '0', '101', '-5', '1.5', ' 5', '5 ', "5\n", '+5', '1e1', 'abc', '0x10', '99999999999999999999'];
        foreach ($rejected as $raw) {
            $this->setEnv($name, $raw);
            $this->assertSame(50, big_run_config_int($name, 50, 1, 100), 'value ' . var_export($raw, true));
        }
    }

    public function testConfigFloatAcceptsFiniteNumbersInsideTheRange(): void {
        $name = 'CITATION_BOT_TEST_CONFIG_FLOAT';
        $this->assertSame(2.5, big_run_config_float($name, 2.5, 0.5, 10.0)); // unset
        $accepted = ['0.5' => 0.5, '10' => 10.0, '1e1' => 10.0, '3.25' => 3.25, '7' => 7.0];
        foreach ($accepted as $raw => $expected) {
            $this->setEnv($name, (string) $raw);
            $this->assertSame($expected, big_run_config_float($name, 2.0, 0.5, 10.0), "value {$raw}");
        }
        $rejected = ['', '0.49', '10.01', '-1', 'abc', 'nan', 'inf', '1e999', '1,5', '0x1A'];
        foreach ($rejected as $raw) {
            $this->setEnv($name, $raw);
            $this->assertSame(2.0, big_run_config_float($name, 2.0, 0.5, 10.0), 'value ' . var_export($raw, true));
        }
    }

    public function testPoolLimitsFallBackToDocumentedDefaults(): void {
        $this->assertSame(BIG_RUN_MAX_TOTAL, big_run_max_total());
        $this->assertSame(BIG_RUN_MAX_DISCOVERY_PROBES, big_run_max_discovery_probes());
        $this->assertSame(BIG_RUN_MAX_LARGE, big_run_max_large());
        $this->assertSame(BIG_RUN_STALE_TIMEOUT_SECONDS, big_run_stale_timeout_seconds());
        $this->assertSame(BIG_RUN_HEARTBEAT_INTERVAL_SECONDS, big_run_heartbeat_interval_seconds());
        $this->assertSame(BIG_RUN_POOL_RETRY_SECONDS, big_run_pool_retry_seconds());
        $this->assertSame(BIG_RUN_TOKEN_CAPACITY, big_run_token_capacity());
        $this->assertSame(BIG_RUN_TOKEN_REFILL_PER_SECOND, big_run_token_refill_per_second());
    }

    public function testPoolLimitsHonorValidOverridesAndRejectOutOfRangeOnes(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '3');
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES', '2');
        $this->setEnv('CITATION_BOT_BIG_RUN_POOL_RETRY_SECONDS', '45');
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY', '250');
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND', '0.5');
        $this->assertSame(3, big_run_max_total());
        $this->assertSame(2, big_run_max_discovery_probes());
        $this->assertSame(45, big_run_pool_retry_seconds());
        $this->assertSame(250, big_run_token_capacity());
        $this->assertSame(0.5, big_run_token_refill_per_second());

        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '101');
        $this->setEnv('CITATION_BOT_BIG_RUN_POOL_RETRY_SECONDS', '4');
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND', '0.0001');
        $this->assertSame(BIG_RUN_MAX_TOTAL, big_run_max_total());
        $this->assertSame(BIG_RUN_POOL_RETRY_SECONDS, big_run_pool_retry_seconds());
        $this->assertSame(BIG_RUN_TOKEN_REFILL_PER_SECOND, big_run_token_refill_per_second());
    }

    public function testLargePoolIsNeverBiggerThanTheTotalPool(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '2');
        $this->assertSame(2, big_run_max_large(), 'default of 4 is capped by a total of 2');
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_LARGE', '1');
        $this->assertSame(1, big_run_max_large());
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '100');
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_LARGE', '100');
        $this->assertSame(100, big_run_max_large());
    }

    public function testHeartbeatIntervalStaysWellInsideTheStaleTimeout(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS', '60');
        $this->assertSame(20, big_run_heartbeat_interval_seconds(), 'default 30 is capped at a third of 60');

        $this->setEnv('CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS', '600');
        $this->assertSame(20, big_run_heartbeat_interval_seconds());

        $this->setEnv('CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS', '5');
        $this->assertSame(5, big_run_heartbeat_interval_seconds());

        $this->setEnv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS', '86400');
        $this->setEnv('CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS', '600');
        $this->assertSame(600, big_run_heartbeat_interval_seconds());

        $this->setEnv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS', '59');
        $this->assertSame(BIG_RUN_STALE_TIMEOUT_SECONDS, big_run_stale_timeout_seconds(), 'below the minimum is ignored');
    }

    // ---- clock handling -------------------------------------------------------

    public function testResolveNowUsesTheClockOnlyWhenNoTimeIsGiven(): void {
        $before = microtime(true);
        $resolved = big_run_resolve_now(null);
        $after = microtime(true);
        $this->assertGreaterThanOrEqual($before, $resolved);
        $this->assertLessThanOrEqual($after, $resolved);
        $this->assertSame(0.0, big_run_resolve_now(0.0));
        $this->assertSame(12.5, big_run_resolve_now(12.5));
    }

    public function testResolveNowRejectsNegativeAndNonFiniteTimes(): void {
        foreach ([-0.001, -1.0, NAN, INF, -INF] as $bad) {
            try {
                big_run_resolve_now($bad);
                $this->fail('Expected an InvalidArgumentException for ' . var_export($bad, true));
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('finite and non-negative', $exception->getMessage());
            }
        }
    }

    // ---- paths and the private state directory ------------------------------

    public function testStateDirectoryTrimsTrailingSeparatorsOfEitherKind(): void {
        $expected = '/some/base' . DIRECTORY_SEPARATOR . REQUEST_RATE_LIMIT_STATE_DIRECTORY;
        $this->assertSame($expected, big_run_state_directory('/some/base'));
        $this->assertSame($expected, big_run_state_directory('/some/base///'));
        $this->assertSame($expected, big_run_state_directory('/some/base/\\/'));
        $this->assertSame(
            big_run_state_directory('/some/base') . DIRECTORY_SEPARATOR . BIG_RUN_STATE_FILE,
            big_run_state_path('/some/base/')
        );
        $this->assertSame(
            big_run_state_directory('/some/base') . DIRECTORY_SEPARATOR . BIG_RUN_LOCK_FILE,
            big_run_lock_path('/some/base/')
        );
    }

    public function testPrepareStateDirectoryCreatesAPrivateDirectoryAndIsIdempotent(): void {
        $base = $this->newBase();
        $directory = big_run_prepare_state_directory($base);
        $this->assertSame(big_run_state_directory($base), $directory);
        $this->assertDirectoryExists((string) $directory);
        clearstatcache(true, (string) $directory);
        $this->assertSame(0700, fileperms((string) $directory) & 0777);
        $this->assertSame($directory, big_run_prepare_state_directory($base));
    }

    public function testPrepareStateDirectoryTightensLoosePermissions(): void {
        $base = $this->newBase();
        $directory = big_run_state_directory($base);
        $this->assertTrue(mkdir($directory, 0755));
        $this->assertTrue(chmod($directory, 0755));
        $this->assertSame($directory, big_run_prepare_state_directory($base));
        clearstatcache(true, $directory);
        $this->assertSame(0700, fileperms($directory) & 0777);
    }

    public function testPrepareStateDirectoryRefusesSymlinksAndRegularFiles(): void {
        $base = $this->newBase();
        $target = $base . DIRECTORY_SEPARATOR . 'elsewhere';
        $this->assertTrue(mkdir($target, 0700));
        $this->assertTrue(symlink($target, big_run_state_directory($base)));
        $this->assertNull(big_run_prepare_state_directory($base));
        $this->assertFalse(big_run_open_lock_handle($base));

        $other = $this->newBase();
        $this->assertNotFalse(file_put_contents(big_run_state_directory($other), 'not a directory'));
        $this->assertNull(big_run_prepare_state_directory($other));
        $this->assertFalse(big_run_open_lock_handle($other));
    }

    public function testPrepareStateDirectoryFailsWhenTheBaseDoesNotExistAndCannotBeBuilt(): void {
        $base = $this->newBase();
        $file = $base . DIRECTORY_SEPARATOR . 'plain-file';
        $this->assertNotFalse(file_put_contents($file, 'x'));
        // A directory cannot be created underneath a regular file.
        $this->assertNull(big_run_prepare_state_directory($file . DIRECTORY_SEPARATOR . 'deeper'));
    }

    // ---- lock handles ----------------------------------------------------------

    public function testLockHandleIsAPrivateRegularFileAndSerialisesCallers(): void {
        $base = $this->newBase();
        $first = big_run_open_lock_handle($base);
        $this->assertIsResource($first);
        $lock_path = big_run_lock_path($base);
        clearstatcache(true, $lock_path);
        $this->assertSame(0600, fileperms($lock_path) & 0777);

        $this->assertTrue(big_run_try_lock($first));
        $second = big_run_open_lock_handle($base);
        $this->assertIsResource($second);
        $started = microtime(true);
        $this->assertFalse(big_run_try_lock($second, 3, 1000));
        $this->assertLessThan(2.0, microtime(true) - $started, 'a busy lock must not block');
        $this->assertTrue(flock($first, LOCK_UN));
        $this->assertTrue(big_run_try_lock($second));
        fclose($first);
        fclose($second);
    }

    public function testLockHandleRefusesASymlinkedLockFile(): void {
        $base = $this->newBase();
        $this->assertNotNull(big_run_prepare_state_directory($base));
        $this->assertTrue(symlink($base . DIRECTORY_SEPARATOR . 'victim', big_run_lock_path($base)));
        $this->assertFalse(big_run_open_lock_handle($base));
        $this->assertFileDoesNotExist($base . DIRECTORY_SEPARATOR . 'victim');
    }

    public function testTryLockWithZeroAttemptsNeverLocks(): void {
        $base = $this->newBase();
        $handle = big_run_open_lock_handle($base);
        $this->assertIsResource($handle);
        $this->assertFalse(big_run_try_lock($handle, 0));
        fclose($handle);
    }

    // ---- size tier, charge type and token cost ------------------------------

    public function testTierSwitchesAtTheLargeThreshold(): void {
        $this->assertSame('small', big_run_tier(0));
        $this->assertSame('small', big_run_tier(BIG_RUN_LARGE_THRESHOLD - 1));
        $this->assertSame('large', big_run_tier(BIG_RUN_LARGE_THRESHOLD));
        $this->assertSame('large', big_run_tier(PHP_INT_MAX));
        $this->assertSame('small', big_run_tier(-5));
    }

    public function testChargeTypeNormalisesRequesterControlledLabels(): void {
        $this->assertSame('category', big_run_charge_type('category'));
        $this->assertSame('webform_linked', big_run_charge_type('webform_linked'));
        $this->assertSame('testing', big_run_charge_type('testing'));
        $this->assertSame('webform', big_run_charge_type('webform'));
        foreach (['', 'CATEGORY', 'Testing', 'free', ' category', "category\n", 'webform_linked '] as $label) {
            $this->assertSame('webform', big_run_charge_type($label), var_export($label, true));
        }
    }

    public function testTokenCostScalesWithTypeAndSize(): void {
        $this->assertSame(0, big_run_token_cost(0, 'webform'));
        $this->assertSame(2, big_run_token_cost(1, 'webform'), 'ceil(1 * 1.5)');
        $this->assertSame(15, big_run_token_cost(10, 'category'));
        $this->assertSame(74, big_run_token_cost(49, 'webform_linked'));
        $this->assertSame(74, big_run_token_cost(49, 'webform'), 'ceil(49 * 1.5 * 1.0)');
        $this->assertSame(113, big_run_token_cost(50, 'webform'), 'ceil(50 * 1.5 * 1.5)');
        $this->assertSame(0, big_run_token_cost(500, 'testing'));
        $this->assertSame(
            big_run_token_cost(80, 'webform'),
            big_run_token_cost(80, 'anything-the-requester-chose')
        );
    }

    public function testTokenCostIsCappedAtTheBucketCapacity(): void {
        $this->assertSame(BIG_RUN_TOKEN_CAPACITY, big_run_token_cost(100000, 'webform'));
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY', '10');
        $this->assertSame(10, big_run_token_cost(100, 'category'));
        $this->assertSame(3, big_run_token_cost(2, 'category'));
    }

    public function testTokenCostRejectsNegativePageCounts(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-negative');
        big_run_token_cost(-1, 'webform');
    }

    // ---- discovery probe policy ----------------------------------------------

    public function testDiscoveryProbeIsOnlyRequiredWhenThereIsMoreToFetch(): void {
        $this->assertFalse(big_run_discovery_probe_requires_lease(1000, 1000, false));
        $this->assertFalse(big_run_discovery_probe_requires_lease(0, 0, true));
        $this->assertFalse(big_run_discovery_probe_requires_lease(
            BIG_RUN_DISCOVERY_RAW_PROBE_LIMIT - 1,
            BIG_RUN_DISCOVERY_BATCH_PROBE_LIMIT - 1,
            true
        ));
        $this->assertTrue(big_run_discovery_probe_requires_lease(BIG_RUN_DISCOVERY_RAW_PROBE_LIMIT, 0, true));
        $this->assertTrue(big_run_discovery_probe_requires_lease(0, BIG_RUN_DISCOVERY_BATCH_PROBE_LIMIT, true));
        $this->assertTrue(big_run_discovery_probe_requires_lease(
            BIG_RUN_DISCOVERY_RAW_PROBE_LIMIT + 100,
            BIG_RUN_DISCOVERY_BATCH_PROBE_LIMIT + 100,
            true
        ));
    }

    // ---- entry ids -----------------------------------------------------------

    public function testEntryIdsAreOpaqueNonNumericAndUnique(): void {
        $seen = [];
        for ($index = 0; $index < 100; ++$index) {
            $id = big_run_new_entry_id();
            $this->assertMatchesRegularExpression('~\Ab[0-9a-f]{16}\z~D', $id);
            $this->assertFalse(is_numeric($id));
            $seen[$id] = true;
        }
        $this->assertCount(100, $seen);
    }

    // ---- token refill --------------------------------------------------------

    public function testRefillAddsTokensPerElapsedSecondUpToCapacity(): void {
        $this->assertSame([40.0, 110.0], big_run_refill_tokens(0.0, 100.0, 110.0));
        $this->assertSame([14.0, 100.5], big_run_refill_tokens(12.0, 100.0, 100.5));
        $this->assertSame([(float) BIG_RUN_TOKEN_CAPACITY, 5000.0], big_run_refill_tokens(399.0, 100.0, 5000.0));
        $this->assertSame([(float) BIG_RUN_TOKEN_CAPACITY, 100.0], big_run_refill_tokens(1.0e9, 100.0, 100.0));
    }

    public function testRefillIgnoresClockRollbackButAdoptsTheNewTime(): void {
        $this->assertSame([5.0, 50.0], big_run_refill_tokens(5.0, 100.0, 50.0));
    }

    public function testRefillFollowsConfiguredRateAndCapacity(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND', '0.5');
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY', '20');
        $this->assertSame([6.0, 20.0], big_run_refill_tokens(1.0, 10.0, 20.0));
        $this->assertSame([20.0, 1000.0], big_run_refill_tokens(1.0, 10.0, 1000.0));
    }

    // ---- stale pruning and counting -----------------------------------------

    public function testPruneKeepsOnlyRecentNonFutureEntries(): void {
        $now = self::NOW;
        $entries = [
            'fresh' => $this->entry($now - 10, 'small', $now - 10, 'running'),
            'edge' => $this->entry($now - 2000, 'small', $now - 900, 'running'),
            'stale' => $this->entry($now - 2000, 'small', $now - 900.5, 'running'),
            'now' => $this->entry($now, 'large', $now, 'discovery'),
            'future' => $this->entry($now, 'small', $now + 0.5, 'probe'),
        ];
        $pruned = big_run_prune_stale_entries($entries, $now);
        $this->assertSame(['fresh', 'edge', 'now'], array_keys($pruned));
        $this->assertSame($entries['fresh'], $pruned['fresh']);
        $this->assertSame([], big_run_prune_stale_entries([], $now));
    }

    public function testPruneUsesTheConfiguredStaleTimeout(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS', '60');
        $now = self::NOW;
        $entries = [
            'recent' => $this->entry($now - 100, 'small', $now - 60, 'running'),
            'old' => $this->entry($now - 100, 'small', $now - 61, 'running'),
        ];
        $this->assertSame(['recent'], array_keys(big_run_prune_stale_entries($entries, $now)));
    }

    public function testCountHelpersSeparateTiersPhasesAndBulkEntries(): void {
        $now = self::NOW;
        $entries = [
            'a' => $this->entry($now, 'small', $now, 'probe'),
            'b' => $this->entry($now, 'small', $now, 'probe'),
            'c' => $this->entry($now, 'small', $now, 'discovery'),
            'd' => $this->entry($now, 'large', $now, 'discovery'),
            'e' => $this->entry($now, 'large', $now, 'running'),
            'f' => $this->entry($now, 'small', $now, 'running'),
        ];
        $this->assertSame(2, big_run_count_tier($entries, 'large'));
        $this->assertSame(4, big_run_count_tier($entries, 'small'));
        $this->assertSame(0, big_run_count_tier($entries, 'huge'));
        $this->assertSame(2, big_run_count_phase($entries, 'probe'));
        $this->assertSame(2, big_run_count_phase($entries, 'discovery'));
        $this->assertSame(2, big_run_count_phase($entries, 'running'));
        $this->assertSame(0, big_run_count_phase($entries, 'finished'));
        $this->assertSame(4, big_run_count_bulk_entries($entries), 'probes are outside the normal total');
        $this->assertSame(0, big_run_count_bulk_entries([]));
        $this->assertSame(0, big_run_count_tier([], 'small'));
        $this->assertSame(0, big_run_count_phase([], 'probe'));
    }

    // ---- reading the snapshot ------------------------------------------------

    public function testMissingStateMeansAFreshFullBucket(): void {
        $base = $this->newBase();
        $this->assertSame(
            [(float) BIG_RUN_TOKEN_CAPACITY, self::NOW, [], true],
            big_run_read_state_file(big_run_state_path($base), self::NOW)
        );
    }

    public function testValidSnapshotIsLoadedAndClampedToTheCurrentTime(): void {
        $base = $this->newBase();
        $now = self::NOW;
        $this->writeState($base, [
            'tokens' => 99999,
            'updated' => $now + 500,
            'entries' => [
                'b01' => $this->entry($now - 50, 'large', $now - 5, 'running'),
                'future' => $this->entry($now + 100, 'small', $now + 200, 'discovery'),
                'a.b_c-D9' => $this->entry($now - 10, 'small', $now - 10, 'probe'),
            ],
        ]);
        [$tokens, $updated, $entries, $valid] = big_run_read_state_file(big_run_state_path($base), $now);
        $this->assertTrue($valid);
        $this->assertSame((float) BIG_RUN_TOKEN_CAPACITY, $tokens, 'tokens above capacity are capped');
        $this->assertSame($now, $updated, 'a future update time is pulled back to now');
        $this->assertSame($this->entry($now - 50, 'large', $now - 5, 'running'), $entries['b01']);
        $this->assertSame($this->entry($now, 'small', $now, 'discovery'), $entries['future']);
        $this->assertArrayHasKey('a.b_c-D9', $entries);
    }

    public function testNumericStringsInTheSnapshotAreAccepted(): void {
        $base = $this->newBase();
        $this->writeRawState($base, '{"tokens":"12.5","updated":"900","entries":{"x":'
            . '{"started_at":"800","tier":"small","last_seen_at":"850","phase":"running"}}}');
        [$tokens, $updated, $entries, $valid] = big_run_read_state_file(big_run_state_path($base), 1000.0);
        $this->assertTrue($valid);
        $this->assertSame(12.5, $tokens);
        $this->assertSame(900.0, $updated);
        $this->assertSame($this->entry(800.0, 'small', 850.0, 'running'), $entries['x']);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidSnapshots(): array {
        $good = '{"started_at":1,"tier":"small","last_seen_at":2,"phase":"running"}';
        return [
            'empty file' => ['', 'state_empty'],
            'not json' => ['{oops', 'json_decode'],
            'json scalar' => ['42', 'top_level_schema'],
            'json null' => ['null', 'top_level_schema'],
            'missing tokens' => ['{"updated":1,"entries":{}}', 'top_level_schema'],
            'missing updated' => ['{"tokens":1,"entries":{}}', 'top_level_schema'],
            'missing entries' => ['{"tokens":1,"updated":1}', 'top_level_schema'],
            'entries not array' => ['{"tokens":1,"updated":1,"entries":5}', 'top_level_schema'],
            'tokens not numeric' => ['{"tokens":"many","updated":1,"entries":{}}', 'top_level_schema'],
            'updated not numeric' => ['{"tokens":1,"updated":"later","entries":{}}', 'top_level_schema'],
            'negative tokens' => ['{"tokens":-1,"updated":1,"entries":{}}', 'top_level_numeric'],
            'negative updated' => ['{"tokens":1,"updated":-1,"entries":{}}', 'top_level_numeric'],
            'entry id with slash' => ['{"tokens":1,"updated":1,"entries":{"a/b":' . $good . '}}', 'entry_schema'],
            'entry id with space' => ['{"tokens":1,"updated":1,"entries":{"a b":' . $good . '}}', 'entry_schema'],
            'empty entry id' => ['{"tokens":1,"updated":1,"entries":{"":' . $good . '}}', 'entry_schema'],
            'overlong entry id' => ['{"tokens":1,"updated":1,"entries":{"' . str_repeat('a', 81) . '":' . $good . '}}', 'entry_schema'],
            'list instead of map' => ['{"tokens":1,"updated":1,"entries":[' . $good . ']}', 'entry_schema'],
            'entry not array' => ['{"tokens":1,"updated":1,"entries":{"a":"x"}}', 'entry_schema'],
            'entry missing phase' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":1,"tier":"small","last_seen_at":2}}}', 'entry_schema'],
            'entry bad tier' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":1,"tier":"huge","last_seen_at":2,"phase":"running"}}}', 'entry_schema'],
            'entry bad phase' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":1,"tier":"small","last_seen_at":2,"phase":"done"}}}', 'entry_schema'],
            'entry non-string tier' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":1,"tier":1,"last_seen_at":2,"phase":"running"}}}', 'entry_schema'],
            'entry non-numeric start' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":"x","tier":"small","last_seen_at":2,"phase":"running"}}}', 'entry_schema'],
            'entry null last seen' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":1,"tier":"small","last_seen_at":null,"phase":"running"}}}', 'entry_schema'],
            'last seen before start' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":5,"tier":"small","last_seen_at":2,"phase":"running"}}}', 'entry_value'],
            'negative start' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":-5,"tier":"small","last_seen_at":2,"phase":"running"}}}', 'entry_value'],
            'large probe' => ['{"tokens":1,"updated":1,"entries":{"a":{"started_at":1,"tier":"large","last_seen_at":2,"phase":"probe"}}}', 'entry_value'],
            'one bad entry poisons all' => ['{"tokens":1,"updated":1,"entries":{"good":' . $good . ',"bad":{"x":1}}}', 'entry_schema'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSnapshots')]
    public function testMalformedSnapshotsFailClosedWithAClassifiedReason(string $raw, string $reason): void {
        $base = $this->newBase();
        $path = $this->writeRawState($base, $raw);
        big_run_clear_last_state_invalid_reason();

        [, , $entries, $valid] = big_run_read_state_file($path, self::NOW);
        $this->assertFalse($valid);
        $this->assertSame([], $entries, 'no partially parsed leases may leak out');
        $this->assertSame($reason, big_run_last_state_invalid_reason());
        $this->assertSame($raw, file_get_contents($path), 'reading must never rewrite the file');
    }

    public function testSnapshotSizeLimitIsInclusive(): void {
        $base = $this->newBase();
        $skeleton = '{"tokens":1,"updated":1,"entries":{},"pad":""}';
        $at_limit = str_replace('"pad":""', '"pad":"' . str_repeat('x', BIG_RUN_STATE_MAX_BYTES - mb_strlen($skeleton, '8bit')) . '"', $skeleton);
        $this->assertSame(BIG_RUN_STATE_MAX_BYTES, mb_strlen($at_limit, '8bit'));
        $path = $this->writeRawState($base, $at_limit);
        $this->assertTrue(big_run_read_state_file($path, self::NOW)[3]);

        big_run_clear_last_state_invalid_reason();
        file_put_contents($path, $at_limit . ' ');
        $this->assertFalse(big_run_read_state_file($path, self::NOW)[3]);
        $this->assertSame('state_oversized', big_run_last_state_invalid_reason());
    }

    public function testSymlinkedAndNonRegularStatePathsAreRejected(): void {
        $base = $this->newBase();
        $this->assertNotNull(big_run_prepare_state_directory($base));
        $path = big_run_state_path($base);

        $real = $base . DIRECTORY_SEPARATOR . 'real.json';
        $this->assertNotFalse(file_put_contents($real, '{"tokens":1,"updated":1,"entries":{}}'));
        $this->assertTrue(symlink($real, $path));
        $this->assertFalse(big_run_read_state_file($path, self::NOW)[3]);
        $this->assertSame('state_path_symlink', big_run_last_state_invalid_reason());
        $this->assertTrue(unlink($path));

        $this->assertTrue(mkdir($path, 0700));
        $this->assertFalse(big_run_read_state_file($path, self::NOW)[3]);
        $this->assertSame('state_path_not_regular', big_run_last_state_invalid_reason());
    }

    public function testClearingTheInvalidReasonForgetsIt(): void {
        $base = $this->newBase();
        $this->writeRawState($base, '');
        big_run_read_state_file(big_run_state_path($base), self::NOW);
        $this->assertSame('state_empty', big_run_last_state_invalid_reason());
        big_run_clear_last_state_invalid_reason();
        $this->assertNull(big_run_last_state_invalid_reason());
        $GLOBALS['citation_bot_big_run_last_state_invalid_reason'] = 123;
        $this->assertNull(big_run_last_state_invalid_reason(), 'a non-string reason is ignored');
    }

    // ---- writing the snapshot ------------------------------------------------

    public function testStoredStateRoundTripsPrivatelyWithoutLeavingTemporaryFiles(): void {
        $base = $this->newBase();
        $this->assertNotNull(big_run_prepare_state_directory($base));
        $path = big_run_state_path($base);
        $entries = ['b1' => $this->entry(10.0, 'large', 20.0, 'running')];

        $this->assertTrue(big_run_store_state_file($path, 123.5, 30.0, $entries));
        clearstatcache(true, $path);
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame([123.5, 30.0, $entries, true], big_run_read_state_file($path, 1000.0));

        $this->assertTrue(big_run_store_state_file($path, 1.0, 2.0, []));
        $this->assertSame([1.0, 2.0, [], true], big_run_read_state_file($path, 1000.0));

        $leftovers = glob(dirname($path) . DIRECTORY_SEPARATOR . '.big-run-state-*');
        $this->assertSame([], $leftovers);
    }

    public function testStoringRefusesNonFiniteOrOversizedStateAndKeepsTheOldFile(): void {
        $base = $this->newBase();
        $this->assertNotNull(big_run_prepare_state_directory($base));
        $path = big_run_state_path($base);
        $this->assertTrue(big_run_store_state_file($path, 5.0, 6.0, []));
        $original = (string) file_get_contents($path);

        $this->assertFalse(big_run_store_state_file($path, INF, 6.0, []));
        $this->assertFalse(big_run_store_state_file($path, NAN, 6.0, []));

        $entries = [];
        for ($index = 0; $index < 1000; ++$index) {
            $entries['entry' . $index] = $this->entry(1.0, 'small', 2.0, 'running');
        }
        $this->assertFalse(big_run_store_state_file($path, 5.0, 6.0, $entries));
        $this->assertSame($original, file_get_contents($path));
        $this->assertSame([], glob(dirname($path) . DIRECTORY_SEPARATOR . '.big-run-state-*'));
    }

    public function testStoringFailsWhenTheDirectoryIsMissing(): void {
        $base = $this->newBase();
        $path = $base . DIRECTORY_SEPARATOR . 'missing-dir' . DIRECTORY_SEPARATOR . 'big-run.json';
        $this->assertFalse(big_run_store_state_file($path, 1.0, 2.0, []));
        $this->assertFileDoesNotExist($path);
    }

    // ---- generic open-handle helper -------------------------------------------

    public function testStateHandleCreatesPrivateFilesAndRefusesSubstitutes(): void {
        $base = $this->newBase();
        $directory = big_run_prepare_state_directory($base) ?? self::fail('Could not prepare the state directory');

        $handle = request_rate_limit_open_state_handle($directory, 'bucket-a');
        $this->assertIsResource($handle);
        fclose($handle);
        $file = $directory . DIRECTORY_SEPARATOR . 'bucket-a.json';
        clearstatcache(true, $file);
        $this->assertSame(0600, fileperms($file) & 0777);

        $again = request_rate_limit_open_state_handle($directory, 'bucket-a');
        $this->assertIsResource($again);
        fclose($again);

        $this->assertTrue(symlink($base . DIRECTORY_SEPARATOR . 'victim', $directory . DIRECTORY_SEPARATOR . 'bucket-b.json'));
        $this->assertFalse(request_rate_limit_open_state_handle($directory, 'bucket-b'));
        $this->assertFileDoesNotExist($base . DIRECTORY_SEPARATOR . 'victim');

        $this->assertTrue(mkdir($directory . DIRECTORY_SEPARATOR . 'bucket-c.json', 0700));
        $this->assertFalse(request_rate_limit_open_state_handle($directory, 'bucket-c'));

        $this->assertFalse(request_rate_limit_open_state_handle($base . DIRECTORY_SEPARATOR . 'nope', 'bucket-d'));
    }

    // ---- admission -----------------------------------------------------------

    public function testAdmissionChargesTokensAndRecordsARunningLease(): void {
        $base = $this->newBase();
        $result = big_run_try_acquire(10, 'webform', $base, self::NOW);
        $this->assertTrue($result[0]);
        $this->assertNull($result[1]);
        $this->assertNull($result[3]);
        $this->assertSame(1, $result[4]);
        $id = (string) $result[2];
        $this->assertMatchesRegularExpression('~\Ab[0-9a-f]{16}\z~D', $id);

        [$tokens, $updated] = $this->readState($base);
        $this->assertSame(400.0 - 15.0, $tokens);
        $this->assertSame(self::NOW, $updated);
        $this->assertSame($this->entry(self::NOW, 'small', self::NOW, 'running'), $this->entryOf($base, $id));
    }

    public function testLargeRunsAreTaggedLargeAndChargedTheSurcharge(): void {
        $base = $this->newBase();
        $result = big_run_try_acquire(50, 'category', $base, self::NOW);
        $this->assertTrue($result[0]);
        $this->assertSame('large', $this->entryOf($base, (string) $result[2])['tier']);
        $this->assertSame(400.0 - 113.0, $this->readState($base)[0]);
    }

    public function testTestingRunsAndUnchargedRunsCostNothing(): void {
        $base = $this->newBase();
        $this->assertTrue(big_run_try_acquire(100, 'testing', $base, self::NOW)[0]);
        $this->assertSame(400.0, $this->readState($base)[0]);
        $this->assertTrue(big_run_try_acquire(10, 'webform', $base, self::NOW, false)[0]);
        $this->assertSame(400.0, $this->readState($base)[0]);
        $this->assertSame(2, count($this->readState($base)[2]));
    }

    public function testTotalPoolFullDefersWithPoolRetryDelay(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '2');
        $base = $this->newBase();
        $this->assertTrue(big_run_try_acquire(5, 'webform', $base, self::NOW)[0]);
        $this->assertTrue(big_run_try_acquire(5, 'webform', $base, self::NOW)[0]);
        $this->assertSame(
            [false, BIG_RUN_POOL_RETRY_SECONDS, null, 'total_full', 2],
            big_run_try_acquire(5, 'webform', $base, self::NOW)
        );
        $this->assertCount(2, $this->readState($base)[2]);
    }

    public function testProbesDoNotConsumeTheNormalTotal(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '1');
        $base = $this->newBase();
        $probe = big_run_try_acquire_probe($base, self::NOW);
        $this->assertTrue($probe[0]);
        $this->assertSame(0, $probe[4], 'probes are not counted as bulk entries');
        $this->assertTrue(big_run_try_acquire(5, 'webform', $base, self::NOW)[0]);
        $this->assertFalse(big_run_try_acquire(5, 'webform', $base, self::NOW)[0]);
    }

    public function testLargePoolFullDefersOnlyLargeRuns(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_LARGE', '1');
        $base = $this->newBase();
        $this->assertTrue(big_run_try_acquire(50, 'webform', $base, self::NOW)[0]);
        $this->assertSame(
            [false, BIG_RUN_POOL_RETRY_SECONDS, null, 'large_full', 1],
            big_run_try_acquire(50, 'webform', $base, self::NOW)
        );
        $this->assertTrue(big_run_try_acquire(49, 'webform', $base, self::NOW)[0], 'small runs still fit');
    }

    public function testTokenShortageReportsHowLongTheBucketNeedsToRefill(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY', '100');
        $base = $this->newBase();
        $first = big_run_try_acquire(60, 'webform', $base, self::NOW);
        $this->assertTrue($first[0], 'cost is capped at capacity, draining the bucket');
        $this->assertSame(0.0, $this->readState($base)[0]);

        $denied = big_run_try_acquire(10, 'webform', $base, self::NOW);
        $this->assertSame([false, 4, null, 'tokens', 1], $denied); // ceil(15 / 4.0)

        $this->assertFalse(big_run_try_acquire(10, 'webform', $base, self::NOW + 3.0)[0], '12 tokens < 15');
        $this->assertTrue(big_run_try_acquire(10, 'webform', $base, self::NOW + 4.0)[0], '16 tokens >= 15');
    }

    public function testTheLongerOfPoolAndTokenWaitIsReported(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY', '100');
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '1');
        $base = $this->newBase();
        $this->assertTrue(big_run_try_acquire(60, 'webform', $base, self::NOW)[0]);

        // Token wait of 4s is shorter than the 30s pool delay: the pool reason wins.
        $this->assertSame(
            [false, 30, null, 'total_full', 1],
            big_run_try_acquire(10, 'webform', $base, self::NOW)
        );

        // At 0.25 tokens/s the wait is 60s, which exceeds the pool delay: tokens win.
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND', '0.25');
        $this->assertSame(
            [false, 60, null, 'tokens', 1],
            big_run_try_acquire(10, 'webform', $base, self::NOW)
        );
    }

    public function testStaleLeasesAreReclaimedAtTheTimeoutBoundary(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '1');
        $base = $this->newBase();
        $first = big_run_try_acquire(5, 'webform', $base, self::NOW);
        $this->assertTrue($first[0]);

        $this->assertFalse(big_run_try_acquire(5, 'webform', $base, self::NOW + 900.0)[0], 'exactly at the timeout');
        $second = big_run_try_acquire(5, 'webform', $base, self::NOW + 900.5);
        $this->assertTrue($second[0]);
        $this->assertSame(1, $second[4]);
        $this->assertArrayNotHasKey((string) $first[2], $this->readState($base)[2]);
    }

    public function testAdmissionFailsClosedOnCorruptStateWithoutTouchingIt(): void {
        $base = $this->newBase();
        $path = $this->writeRawState($base, '{broken');
        $rejected = [false, 2, null, 'retry_later', 0];
        $this->assertSame($rejected, big_run_try_acquire(5, 'webform', $base, self::NOW));
        $this->assertSame($rejected, big_run_try_acquire_discovery($base, self::NOW));
        $this->assertSame($rejected, big_run_try_acquire_probe($base, self::NOW));
        $this->assertSame($rejected, big_run_try_promote_probe_to_discovery('x', $base, self::NOW));
        $this->assertSame($rejected, big_run_try_mark_discovery_large('x', $base, self::NOW));
        $this->assertSame($rejected, big_run_try_promote('x', 5, 'webform', $base, self::NOW));
        $this->assertSame('{broken', file_get_contents($path));
        $this->assertSame('json_decode', big_run_last_state_invalid_reason());
    }

    public function testAdmissionAsksCallersToRetryWhileAnotherProcessHoldsTheLock(): void {
        $base = $this->newBase();
        $holder = big_run_open_lock_handle($base);
        $this->assertIsResource($holder);
        $this->assertTrue(big_run_try_lock($holder));
        try {
            $busy = [false, 1, null, 'retry_later', 0];
            $this->assertSame($busy, big_run_try_acquire(5, 'webform', $base, self::NOW));
            $this->assertSame($busy, big_run_try_acquire_discovery($base, self::NOW));
            $this->assertSame($busy, big_run_try_acquire_probe($base, self::NOW));
            $this->assertSame($busy, big_run_try_promote_probe_to_discovery('x', $base, self::NOW));
            $this->assertSame($busy, big_run_try_mark_discovery_large('x', $base, self::NOW));
            $this->assertSame($busy, big_run_try_promote('x', 5, 'webform', $base, self::NOW));
            $this->assertSame('retry', big_run_heartbeat_status('x', $base, self::NOW));
            big_run_release('x', $base); // best effort, must not throw or block for long
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }
        $this->assertTrue(big_run_try_acquire(5, 'webform', $base, self::NOW)[0], 'the lock is usable again');
    }

    public function testAdmissionRejectsInvalidTimestamps(): void {
        $base = $this->newBase();
        $this->expectException(InvalidArgumentException::class);
        big_run_try_acquire(5, 'webform', $base, -1.0);
    }

    // ---- discovery and probe phases -----------------------------------------

    public function testDiscoveryLeasesAreSmallProvisionalAndSpendNoTokens(): void {
        $base = $this->newBase();
        $result = big_run_try_acquire_discovery($base, self::NOW);
        $this->assertTrue($result[0]);
        $this->assertSame(1, $result[4]);
        $this->assertSame($this->entry(self::NOW, 'small', self::NOW, 'discovery'), $this->entryOf($base, (string) $result[2]));
        $this->assertSame(400.0, $this->readState($base)[0]);
    }

    public function testDiscoveryIsDeferredWhenTheTotalPoolIsFull(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '1');
        $base = $this->newBase();
        $this->assertTrue(big_run_try_acquire_discovery($base, self::NOW)[0]);
        $this->assertSame(
            [false, BIG_RUN_POOL_RETRY_SECONDS, null, 'total_full', 1],
            big_run_try_acquire_discovery($base, self::NOW)
        );
        $this->assertCount(1, $this->readState($base)[2]);
    }

    public function testProbeLeasesAreCappedSeparately(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES', '2');
        $base = $this->newBase();
        $first = big_run_try_acquire_probe($base, self::NOW);
        $second = big_run_try_acquire_probe($base, self::NOW);
        $this->assertTrue($first[0]);
        $this->assertTrue($second[0]);
        $this->assertSame(
            [false, BIG_RUN_POOL_RETRY_SECONDS, null, 'probe_full', 2],
            big_run_try_acquire_probe($base, self::NOW)
        );
        $this->assertSame('probe', $this->entryOf($base, (string) $first[2])['phase']);
        $this->assertSame(400.0, $this->readState($base)[0]);

        big_run_release((string) $first[2], $base);
        $this->assertTrue(big_run_try_acquire_probe($base, self::NOW)[0], 'a freed slot is reusable');
    }

    public function testProbePromotionChecksTheNormalTotalAtTheBoundary(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '1');
        $base = $this->newBase();
        $probe = big_run_try_acquire_probe($base, self::NOW);
        $this->assertTrue($probe[0]);
        $id = (string) $probe[2];

        $promoted = big_run_try_promote_probe_to_discovery($id, $base, self::NOW + 5.0);
        $this->assertSame([true, null, $id, null, 1], $promoted);
        $this->assertSame('discovery', $this->entryOf($base, $id)['phase']);
        $this->assertSame(self::NOW + 5.0, $this->entryOf($base, $id)['last_seen_at']);

        // Promoting again is idempotent.
        $this->assertSame([true, null, $id, null, 1], big_run_try_promote_probe_to_discovery($id, $base, self::NOW + 6.0));
    }

    public function testProbePromotionIsDeniedAndCleanedUpWhenTheTotalIsFull(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '1');
        $base = $this->newBase();
        $probe = big_run_try_acquire_probe($base, self::NOW);
        $this->assertTrue(big_run_try_acquire(5, 'webform', $base, self::NOW)[0]);

        $denied = big_run_try_promote_probe_to_discovery((string) $probe[2], $base, self::NOW);
        $this->assertSame([false, BIG_RUN_POOL_RETRY_SECONDS, null, 'total_full', 1], $denied);
        $this->assertArrayNotHasKey((string) $probe[2], $this->readState($base)[2]);
    }

    public function testProbePromotionFailsClosedForUnknownOrWrongPhaseLeases(): void {
        $base = $this->newBase();
        $this->assertSame(
            [false, 2, null, 'retry_later', 0],
            big_run_try_promote_probe_to_discovery('b0000000000000000', $base, self::NOW)
        );
        $running = big_run_try_acquire(5, 'webform', $base, self::NOW);
        $this->assertSame(
            [false, 2, null, 'retry_later', 1],
            big_run_try_promote_probe_to_discovery((string) $running[2], $base, self::NOW)
        );
        $this->assertSame('running', $this->entryOf($base, (string) $running[2])['phase']);
    }

    public function testMarkingDiscoveryLargeMovesTheLeaseIntoTheLargePool(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_LARGE', '1');
        $base = $this->newBase();
        $first = big_run_try_acquire_discovery($base, self::NOW);
        $second = big_run_try_acquire_discovery($base, self::NOW);

        $marked = big_run_try_mark_discovery_large((string) $first[2], $base, self::NOW + 1.0);
        $this->assertSame([true, null, (string) $first[2], null, 2], $marked);
        $this->assertSame('large', $this->entryOf($base, (string) $first[2])['tier']);
        $this->assertSame('discovery', $this->entryOf($base, (string) $first[2])['phase']);
        $this->assertSame(400.0, $this->readState($base)[0], 'no tokens are charged yet');

        // Marking again changes nothing and still succeeds.
        $this->assertTrue(big_run_try_mark_discovery_large((string) $first[2], $base, self::NOW + 2.0)[0]);

        // The pool is now full, so the second discovery is refused and removed.
        $denied = big_run_try_mark_discovery_large((string) $second[2], $base, self::NOW + 3.0);
        $this->assertSame([false, BIG_RUN_POOL_RETRY_SECONDS, null, 'large_full', 1], $denied);
        $this->assertArrayNotHasKey((string) $second[2], $this->readState($base)[2]);
    }

    public function testMarkingLargeRequiresAnExistingDiscoveryLease(): void {
        $base = $this->newBase();
        $retry = static fn (int $count): array => [false, 2, null, 'retry_later', $count];
        $this->assertSame($retry(0), big_run_try_mark_discovery_large('b0000000000000000', $base, self::NOW));

        $probe = big_run_try_acquire_probe($base, self::NOW);
        $this->assertSame($retry(1), big_run_try_mark_discovery_large((string) $probe[2], $base, self::NOW));

        $running = big_run_try_acquire(5, 'webform', $base, self::NOW);
        $this->assertSame($retry(2), big_run_try_mark_discovery_large((string) $running[2], $base, self::NOW));
        $this->assertSame('small', $this->entryOf($base, (string) $running[2])['tier']);
    }

    // ---- final promotion -----------------------------------------------------

    public function testPromotionChargesTokensOnceAndSetsTheTier(): void {
        $base = $this->newBase();
        $discovery = big_run_try_acquire_discovery($base, self::NOW);
        $id = (string) $discovery[2];

        $promoted = big_run_try_promote($id, 60, 'category', $base, self::NOW + 10.0);
        $this->assertSame([true, null, $id, null, 1], $promoted);
        $entry = $this->entryOf($base, $id);
        $this->assertSame('running', $entry['phase']);
        $this->assertSame('large', $entry['tier']);
        $this->assertSame(self::NOW, $entry['started_at']);
        $this->assertSame(self::NOW + 10.0, $entry['last_seen_at']);
        $this->assertSame(400.0 - 135.0, $this->readState($base)[0]);

        // A running lease can never be promoted again (and so never double charged).
        $again = big_run_try_promote($id, 60, 'category', $base, self::NOW + 11.0);
        $this->assertSame([false, 2, null, 'retry_later', 1], $again);
        $this->assertSame(400.0 - 135.0, $this->readState($base)[0]);
    }

    public function testPromotionCanSkipTheTokenCharge(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire_discovery($base, self::NOW)[2];
        $this->assertTrue(big_run_try_promote($id, 10, 'webform', $base, self::NOW, false)[0]);
        $this->assertSame(400.0, $this->readState($base)[0]);
        $this->assertSame('small', $this->entryOf($base, $id)['tier']);
    }

    public function testPromotionOfAnUnknownOrProbeLeaseFailsClosed(): void {
        $base = $this->newBase();
        $this->assertSame(
            [false, 2, null, 'retry_later', 0],
            big_run_try_promote('b0000000000000000', 10, 'webform', $base, self::NOW)
        );
        $probe = (string) big_run_try_acquire_probe($base, self::NOW)[2];
        $this->assertSame(
            [false, 2, null, 'retry_later', 1],
            big_run_try_promote($probe, 10, 'webform', $base, self::NOW)
        );
        $this->assertSame('probe', $this->entryOf($base, $probe)['phase']);
    }

    public function testPromotionWithTooFewTokensDropsTheLeaseAndReportsTheWait(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY', '100');
        $base = $this->newBase();
        $this->assertTrue(big_run_try_acquire(60, 'webform', $base, self::NOW)[0]); // drains the bucket
        $discovery = (string) big_run_try_acquire_discovery($base, self::NOW)[2];

        $denied = big_run_try_promote($discovery, 10, 'webform', $base, self::NOW);
        $this->assertSame([false, 4, null, 'tokens', 1], $denied);
        $this->assertArrayNotHasKey($discovery, $this->readState($base)[2]);
    }

    public function testPromotionIntoAFullLargePoolIsRefusedAndTheLeaseDropped(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_LARGE', '1');
        $base = $this->newBase();
        $this->assertTrue(big_run_try_acquire(50, 'webform', $base, self::NOW)[0]);
        $discovery = (string) big_run_try_acquire_discovery($base, self::NOW)[2];

        $denied = big_run_try_promote($discovery, 50, 'webform', $base, self::NOW);
        $this->assertSame([false, BIG_RUN_POOL_RETRY_SECONDS, null, 'large_full', 1], $denied);
        $this->assertArrayNotHasKey($discovery, $this->readState($base)[2]);
    }

    public function testALargeDiscoveryLeaseDoesNotCountAgainstItselfWhenPromoted(): void {
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_LARGE', '1');
        $base = $this->newBase();
        $id = (string) big_run_try_acquire_discovery($base, self::NOW)[2];
        $this->assertTrue(big_run_try_mark_discovery_large($id, $base, self::NOW)[0]);
        $this->assertTrue(big_run_try_promote($id, 70, 'webform', $base, self::NOW)[0]);
        $this->assertSame('large', $this->entryOf($base, $id)['tier']);
    }

    public function testAnOvergrownRunThatShrankIsDowngradedOnPromotion(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire_discovery($base, self::NOW)[2];
        $this->assertTrue(big_run_try_mark_discovery_large($id, $base, self::NOW)[0]);
        $this->assertTrue(big_run_try_promote($id, 5, 'webform', $base, self::NOW)[0]);
        $this->assertSame('small', $this->entryOf($base, $id)['tier']);
    }

    // ---- release --------------------------------------------------------------

    public function testReleaseRemovesOnlyTheNamedLeaseAndNeverRefundsTokens(): void {
        $base = $this->newBase();
        $keep = (string) big_run_try_acquire(10, 'webform', $base, self::NOW)[2];
        $drop = (string) big_run_try_acquire(10, 'webform', $base, self::NOW)[2];
        $tokens_before = $this->readState($base)[0];

        big_run_release($drop, $base);
        [$tokens, , $entries] = $this->readState($base);
        $this->assertSame(array_keys($entries), [$keep]);
        $this->assertSame($tokens_before, $tokens);

        big_run_release($drop, $base); // second release is harmless
        $this->assertSame([$keep], array_keys($this->readState($base)[2]));
    }

    public function testReleaseIgnoresEmptyIdsEmptyBasesAndCorruptState(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire(10, 'webform', $base, self::NOW)[2];
        big_run_release('', $base);
        big_run_release($id, '');
        $this->assertArrayHasKey($id, $this->readState($base)[2]);

        $path = $this->writeRawState($base, 'garbage');
        big_run_release($id, $base);
        $this->assertSame('garbage', file_get_contents($path), 'release must not repair corrupt state');
    }

    // ---- heartbeats ------------------------------------------------------------

    public function testHeartbeatStatusRenewsKnownLeasesAndReportsLostOnes(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire(10, 'webform', $base, self::NOW)[2];

        $this->assertSame('ok', big_run_heartbeat_status($id, $base, self::NOW + 20.0));
        $this->assertSame(self::NOW + 20.0, $this->entryOf($base, $id)['last_seen_at']);
        $this->assertTrue(big_run_heartbeat($id, $base, self::NOW + 40.0));

        $this->assertSame('lost', big_run_heartbeat_status('b0000000000000000', $base, self::NOW + 40.0));
        $this->assertFalse(big_run_heartbeat('b0000000000000000', $base, self::NOW + 40.0));
        big_run_release($id, $base);
        $this->assertSame('lost', big_run_heartbeat_status($id, $base, self::NOW + 41.0));
    }

    public function testHeartbeatTreatsMissingInputsAndBadStateAsRetryable(): void {
        $base = $this->newBase();
        $this->assertSame('retry', big_run_heartbeat_status('', $base, self::NOW));
        $this->assertSame('retry', big_run_heartbeat_status('abc', '', self::NOW));
        $this->writeRawState($base, '{nope');
        $this->assertSame('retry', big_run_heartbeat_status('abc', $base, self::NOW));
        $this->assertFalse(big_run_heartbeat('abc', $base, self::NOW));
    }

    public function testHeartbeatDoesNotPruneButALaterAdmissionDoes(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire(10, 'webform', $base, self::NOW)[2];
        // The heartbeat does not prune, so a late renewal still succeeds until
        // some other admission removes the stale entry.
        $this->assertSame('ok', big_run_heartbeat_status($id, $base, self::NOW + 5000.0));
        $this->setEnv('CITATION_BOT_BIG_RUN_MAX_TOTAL', '1');
        $other = big_run_try_acquire(10, 'webform', $base, self::NOW + 5000.0 + 1000.0);
        $this->assertTrue($other[0], 'the renewed lease expired again and was pruned');
        $this->assertSame('lost', big_run_heartbeat_status($id, $base, self::NOW + 7000.0));
    }

    public function testHeartbeatOrStopReturnsStatusWhenTheLeaseIsNotLost(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire(10, 'webform', $base, self::NOW)[2];
        $this->assertTrue(big_run_heartbeat_or_stop($id, $base, self::NOW + 1.0));
        $this->writeRawState($base, '{nope');
        $this->assertFalse(big_run_heartbeat_or_stop($id, $base, self::NOW + 2.0));
    }

    // ---- request-wide current-entry tracking ---------------------------------

    public function testCurrentEntryCanBeSetAndSelectivelyCleared(): void {
        big_run_set_current_entry('b1', '/some/dir', 50.0);
        $this->assertSame(
            ['entry_id' => 'b1', 'base_directory' => '/some/dir', 'last_heartbeat_attempt' => 50.0],
            $GLOBALS['citation_bot_big_run_current']
        );

        big_run_clear_current_entry('other');
        $this->assertArrayHasKey('citation_bot_big_run_current', $GLOBALS, 'a different id must not clear it');
        big_run_clear_current_entry('b1');
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS);

        big_run_clear_current_entry(); // nothing set: no error
        big_run_set_current_entry('b2');
        big_run_clear_current_entry();
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS);
        $GLOBALS['citation_bot_big_run_current'] = 'corrupt';
        big_run_clear_current_entry('b2');
        $this->assertSame('corrupt', $GLOBALS['citation_bot_big_run_current']);
    }

    public function testSettingTheCurrentEntryRejectsInvalidTimes(): void {
        $this->expectException(InvalidArgumentException::class);
        big_run_set_current_entry('b1', null, NAN);
    }

    public function testRequestWideHeartbeatIsThrottledToTheConfiguredInterval(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire(10, 'webform', $base, 1000.0)[2];
        big_run_set_current_entry($id, $base, 1000.0);

        big_run_maybe_heartbeat_current(1029.0); // interval is 30s
        $this->assertSame(1000.0, $this->entryOf($base, $id)['last_seen_at']);

        big_run_maybe_heartbeat_current(1030.0);
        $this->assertSame(1030.0, $this->entryOf($base, $id)['last_seen_at']);
        $this->assertSame(1030.0, $GLOBALS['citation_bot_big_run_current']['last_heartbeat_attempt']);

        big_run_maybe_heartbeat_current(1059.0);
        $this->assertSame(1030.0, $this->entryOf($base, $id)['last_seen_at']);
        big_run_maybe_heartbeat_current(1060.0);
        $this->assertSame(1060.0, $this->entryOf($base, $id)['last_seen_at']);
    }

    public function testFailedRequestWideHeartbeatIsRetriedAtTheNextHook(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire(10, 'webform', $base, 1000.0)[2];
        big_run_set_current_entry($id, $base, 1000.0);
        $good_state = (string) file_get_contents(big_run_state_path($base));

        file_put_contents(big_run_state_path($base), '{nope');
        big_run_maybe_heartbeat_current(1100.0);
        $this->assertSame(1000.0, $GLOBALS['citation_bot_big_run_current']['last_heartbeat_attempt'], 'a failed attempt is not recorded');

        file_put_contents(big_run_state_path($base), $good_state);
        big_run_maybe_heartbeat_current(1101.0);
        $this->assertSame(1101.0, $this->entryOf($base, $id)['last_seen_at']);
        $this->assertSame(1101.0, $GLOBALS['citation_bot_big_run_current']['last_heartbeat_attempt']);
    }

    public function testRequestWideHeartbeatIgnoresMissingOrMalformedCurrentEntries(): void {
        unset($GLOBALS['citation_bot_big_run_current']);
        big_run_maybe_heartbeat_current(5000.0);
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS);

        foreach (['text', 12, [], ['entry_id' => 7], ['entry_id' => null]] as $junk) {
            $GLOBALS['citation_bot_big_run_current'] = $junk;
            big_run_maybe_heartbeat_current(5000.0);
            $this->assertSame($junk, $GLOBALS['citation_bot_big_run_current']);
        }
    }

    public function testRequestWideHeartbeatToleratesUnusableBookkeepingFields(): void {
        $base = $this->newBase();
        $id = (string) big_run_try_acquire(10, 'webform', $base, 1000.0)[2];
        // A non-numeric last attempt counts as "never", and a non-string
        // directory falls back to the default location (which has no such lease,
        // so only the bookkeeping is exercised here).
        $GLOBALS['citation_bot_big_run_current'] = [
            'entry_id' => $id,
            'base_directory' => $base,
            'last_heartbeat_attempt' => 'yesterday',
        ];
        big_run_maybe_heartbeat_current(1005.0);
        $this->assertSame(1005.0, $this->entryOf($base, $id)['last_seen_at']);
        $this->assertSame(1005.0, $GLOBALS['citation_bot_big_run_current']['last_heartbeat_attempt']);
    }

    // ---- recovery -----------------------------------------------------------------

    public function testRecoveryCheckReportsHealthyAndFreshState(): void {
        $base = $this->newBase();
        $fresh = big_run_recovery_check($base, self::NOW);
        $this->assertSame(
            ['ok' => true, 'reason' => null, 'state_path' => big_run_state_path($base), 'lease_entries' => 0, 'tokens' => (float) BIG_RUN_TOKEN_CAPACITY],
            $fresh
        );

        big_run_try_acquire(10, 'webform', $base, self::NOW);
        big_run_try_acquire_probe($base, self::NOW);
        $busy = big_run_recovery_check($base, self::NOW);
        $this->assertTrue($busy['ok']);
        $this->assertSame(2, $busy['lease_entries']);
        $this->assertSame(385.0, $busy['tokens']);
    }

    public function testRecoveryCheckClassifiesEveryFailureWithoutChangingAnything(): void {
        $this->assertSame(
            ['ok' => false, 'reason' => 'base_directory_empty', 'state_path' => '', 'lease_entries' => null, 'tokens' => null],
            big_run_recovery_check('', self::NOW)
        );

        $base = $this->newBase();
        $path = $this->writeRawState($base, '[1,2');
        $broken = big_run_recovery_check($base, self::NOW);
        $this->assertFalse($broken['ok']);
        $this->assertSame('json_decode', $broken['reason']);
        $this->assertNull($broken['lease_entries']);
        $this->assertNull($broken['tokens']);
        $this->assertSame('[1,2', file_get_contents($path));

        $holder = big_run_open_lock_handle($base);
        $this->assertIsResource($holder);
        $this->assertTrue(big_run_try_lock($holder));
        try {
            $this->assertSame('lock_busy', big_run_recovery_check($base, self::NOW, 2)['reason']);
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }

        $other = $this->newBase();
        $this->assertNotFalse(file_put_contents(big_run_state_directory($other), 'file in the way'));
        $this->assertSame('lock_open_failed', big_run_recovery_check($other, self::NOW)['reason']);
    }

    public function testRecoveryRejectsNonPositiveLockAttempts(): void {
        $base = $this->newBase();
        foreach ([0, -1] as $attempts) {
            try {
                big_run_recovery_check($base, self::NOW, $attempts);
                $this->fail('check must reject ' . $attempts);
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('positive', $exception->getMessage());
            }
            try {
                big_run_recovery_reset($base, self::NOW, $attempts);
                $this->fail('reset must reject ' . $attempts);
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('positive', $exception->getMessage());
            }
        }
    }

    public function testRecoveryResetInstallsFreshStateWhenNoneExists(): void {
        $base = $this->newBase();
        $this->setEnv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY', '50');
        $result = big_run_recovery_reset($base, self::NOW);
        $this->assertSame(
            ['ok' => true, 'reason' => null, 'state_path' => big_run_state_path($base), 'backup_path' => null],
            $result
        );
        $this->assertSame([50.0, self::NOW, [], true], $this->readState($base));
    }

    public function testRecoveryResetBacksUpTheOldSnapshotBeforeReplacingIt(): void {
        $base = $this->newBase();
        $path = $this->writeRawState($base, '{this is corrupt');
        $result = big_run_recovery_reset($base, self::NOW);
        $this->assertTrue($result['ok']);
        $backup = $result['backup_path'];
        $this->assertIsString($backup);
        $this->assertStringStartsWith($path . '.recovery-', $backup);
        $this->assertStringContainsString('-' . getmypid(), $backup);
        $this->assertSame('{this is corrupt', file_get_contents($backup));
        clearstatcache(true, $backup);
        $this->assertSame(0600, fileperms($backup) & 0777);
        $this->assertSame([(float) BIG_RUN_TOKEN_CAPACITY, self::NOW, [], true], $this->readState($base));
        $this->assertNull(big_run_last_state_invalid_reason());

        // The gate works again straight afterwards.
        $this->assertTrue(big_run_try_acquire(5, 'webform', $base, self::NOW)[0]);
    }

    public function testRecoveryResetClearsLiveLeasesAndKeepsEachBackup(): void {
        $base = $this->newBase();
        big_run_try_acquire(10, 'webform', $base, self::NOW);
        $first = big_run_recovery_reset($base, self::NOW);
        $second = big_run_recovery_reset($base, self::NOW);
        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertNotSame($first['backup_path'], $second['backup_path']);
        $this->assertFileExists((string) $first['backup_path']);
        $this->assertFileExists((string) $second['backup_path']);
        $this->assertSame([], $this->readState($base)[2]);
    }

    public function testRecoveryResetRefusesUnsafeStatePaths(): void {
        $base = $this->newBase();
        $this->assertNotNull(big_run_prepare_state_directory($base));
        $path = big_run_state_path($base);
        $this->assertTrue(symlink($base . DIRECTORY_SEPARATOR . 'victim', $path));
        $this->assertSame('state_path_symlink', big_run_recovery_reset($base, self::NOW)['reason']);
        $this->assertFileDoesNotExist($base . DIRECTORY_SEPARATOR . 'victim');
        $this->assertTrue(unlink($path));

        $this->assertTrue(mkdir($path, 0700));
        $result = big_run_recovery_reset($base, self::NOW);
        $this->assertFalse($result['ok']);
        $this->assertSame('state_path_not_regular', $result['reason']);
        $this->assertNull($result['backup_path']);
        $this->assertDirectoryExists($path);
    }

    public function testRecoveryResetReportsEmptyBaseBusyLockAndBlockedDirectory(): void {
        $this->assertSame(
            ['ok' => false, 'reason' => 'base_directory_empty', 'state_path' => '', 'backup_path' => null],
            big_run_recovery_reset('', self::NOW)
        );

        $base = $this->newBase();
        $path = $this->writeRawState($base, '{}');
        $holder = big_run_open_lock_handle($base);
        $this->assertIsResource($holder);
        $this->assertTrue(big_run_try_lock($holder));
        try {
            $this->assertSame('lock_busy', big_run_recovery_reset($base, self::NOW, 2)['reason']);
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }
        $this->assertSame('{}', file_get_contents($path), 'a refused reset leaves the snapshot alone');

        $other = $this->newBase();
        $this->assertNotFalse(file_put_contents(big_run_state_directory($other), 'file in the way'));
        $this->assertSame('lock_open_failed', big_run_recovery_reset($other, self::NOW)['reason']);
    }

    public function testRecoveryBackupNamesAvoidExistingFiles(): void {
        $base = $this->newBase();
        $state_path = $base . DIRECTORY_SEPARATOR . BIG_RUN_STATE_FILE;
        $first = big_run_recovery_backup_path($state_path) ?? self::fail('Could not allocate a recovery backup path');
        $this->assertMatchesRegularExpression(
            '~' . preg_quote($state_path, '~') . '\.recovery-\d{8}T\d{6}Z-\d+(-\d+)?\z~D',
            $first
        );

        $this->assertNotFalse(file_put_contents($first, 'taken'));
        $second = big_run_recovery_backup_path($state_path) ?? self::fail('Could not allocate a recovery backup path');
        $this->assertNotSame($first, $second);

        // Even a dangling symlink occupies a name.
        $this->assertTrue(symlink($base . DIRECTORY_SEPARATOR . 'missing', $second));
        $third = big_run_recovery_backup_path($state_path);
        $this->assertNotContains($third, [$first, $second]);
    }

    // ---- lease loss (terminates the process) -------------------------------------

    /**
     * Run $body in a fresh PHP process that has loaded only RequestRateLimit.php.
     *
     * @return array{int, string} exit code and combined output
     */
    private function runStandalone(string $setup, string $body): array {
        if (!function_exists('exec')) {
            $this->markTestSkipped('exec() is unavailable');
        }
        $source = realpath(dirname(__DIR__, 3) . '/src/includes/RequestRateLimit.php');
        $this->assertNotFalse($source);

        $script = "<?php\ndeclare(strict_types=1);\n";
        $script .= $setup . "\n";
        $script .= 'require_once ' . var_export($source, true) . ";\n";
        $script .= $body . "\n";

        $file = tempnam(sys_get_temp_dir(), 'rrl');
        $this->assertNotFalse($file);
        try {
            $this->assertNotFalse(file_put_contents($file, $script));
            $output = [];
            $exit_code = -1;
            $command = escapeshellarg(PHP_BINARY) . ' -d uopz.exit=1 -d display_errors=1 -d log_errors=0 ' .
                escapeshellarg($file) . ' 2>&1';
            exec($command, $output, $exit_code); // phpcs:ignore
        } finally {
            @unlink($file);
        }
        return [$exit_code, implode("\n", $output)];
    }

    public function testLeaseLossRendersTheBusyPageWhenAdmissionBufferingIsActive(): void {
        $setup = <<<'PHP'
function big_run_render_busy_page(string $reason, ?string $detail, int $retry): void {
    echo "BUSY[", $reason, "|", var_export($detail, true), "|", $retry, "]";
}
function bot_debug_log(string $text): void { echo "LOG[", $text, "]"; }
$GLOBALS['citation_bot_admission_buffer_level'] = 0;
PHP;
        $body = <<<'PHP'
$GLOBALS['citation_bot_big_run_current'] = ['entry_id' => 'b1', 'base_directory' => null, 'last_heartbeat_attempt' => 0.0];
big_run_stop_after_lease_loss('b1');
echo "UNREACHABLE";
PHP;
        [$exit_code, $output] = $this->runStandalone($setup, $body);
        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('BUSY[lease_lost|NULL|30]', $output);
        $this->assertStringNotContainsString('LOG[', $output, 'routine big-run events are now silent');
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    public function testRoutineBigRunEventsAreSilentWithAndWithoutBotLogger(): void {
        $body = <<<'PHP'
big_run_log('promoted', ['tier' => 'large', 'pages' => 50]);
echo 'DONE';
PHP;
        foreach ([
            '',
            'function bot_debug_log(string $text): void { echo "LOG[", $text, "]"; }',
        ] as $setup) {
            [$exit_code, $output] = $this->runStandalone($setup, $body);
            $this->assertSame(0, $exit_code);
            $this->assertSame('DONE', $output, 'no routine debug output on either logging path');
        }
    }

    public function testInvalidBigRunStateStillEmitsOperatorWarning(): void {
        $setup = 'function bot_debug_log(string $text): void { echo "LOG[", $text, "]"; }';
        $body = <<<'PHP'
big_run_log_state_invalid('test-corrupt-state');
echo '|DONE';
PHP;
        [$exit_code, $output] = $this->runStandalone($setup, $body);
        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString(
            'LOG[Citation Bot big-run gate: INVALID SHARED STATE (test-corrupt-state)',
            $output
        );
        $this->assertStringNotContainsString('"event":"state_invalid"', $output);
        $this->assertStringEndsWith(']|DONE', $output);
    }

    public function testLeaseLossWithoutBufferingWarnsRendersFooterAndExits(): void {
        $setup = <<<'PHP'
function report_warning(string $text): void { echo "WARN[", $text, "]"; }
function bot_html_footer(): void { echo "FOOTER"; }
function bot_debug_log(string $text): void {}
PHP;
        $body = <<<'PHP'
big_run_stop_after_lease_loss('b2');
echo "UNREACHABLE";
PHP;
        [$exit_code, $output] = $this->runStandalone($setup, $body);
        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('WARN[Citation Bot lost ownership of this bulk-run lease.', $output);
        $this->assertStringContainsString('please retry.]', $output);
        $this->assertStringEndsWith('FOOTER', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    public function testLeaseLossWithNoOutputHelpersStillExitsCleanly(): void {
        $body = <<<'PHP'
big_run_set_current_entry('b3');
big_run_stop_after_lease_loss('b3');
echo "UNREACHABLE";
PHP;
        [$exit_code, $output] = $this->runStandalone('', $body);
        $this->assertSame(0, $exit_code);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    public function testHeartbeatOrStopTerminatesWhenTheLeaseWasTakenAway(): void {
        $base = $this->newBase();
        $setup = <<<'PHP'
function bot_debug_log(string $text): void {}
function report_warning(string $text): void { echo "WARN"; }
PHP;
        $body = '$base = ' . var_export($base, true) . ";\n" . <<<'PHP'
big_run_heartbeat_or_stop('b0000000000000000', $base, 1000.0);
echo "UNREACHABLE";
PHP;
        [$exit_code, $output] = $this->runStandalone($setup, $body);
        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('WARN', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    public function testRateLimitLoggerFallsBackToTheSystemLogOnlyWithoutBotLogger(): void {
        $setup = '';
        $body = <<<'PHP'
request_rate_limit_debug_log('standalone message');
echo "|DONE";
PHP;
        [$exit_code, $output] = $this->runStandalone($setup, $body);
        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('standalone message', $output);
        $this->assertStringEndsWith('|DONE', $output);

        $setup = 'function bot_debug_log(string $text): void { echo "BOT[", $text, "]"; }';
        [, $output] = $this->runStandalone($setup, "request_rate_limit_debug_log('via bot');");
        $this->assertSame('BOT[via bot]', $output);
    }
}
