<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Network-free edge cases for the shared big-run admission state machine in
 * RequestRateLimit.php. Every test uses its own private temporary directory
 * and an explicit clock, so nothing here touches shared production state.
 */
final class BigRunStateMachineEdgeCaseTest extends PHPUnit\Framework\TestCase {
    private const NOW = 1700000000.0;

    private string $base_directory;
    /** @var array<string, string|false> */
    private array $saved_environment = [];
    /** @var resource|null */
    private $held_lock = null;
    private int $saved_buffer_level;
    /** @var list<string> */
    private array $warnings = [];

    private const ENVIRONMENT = [
        'CITATION_BOT_BIG_RUN_MAX_TOTAL',
        'CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES',
        'CITATION_BOT_BIG_RUN_MAX_LARGE',
        'CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS',
        'CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS',
        'CITATION_BOT_BIG_RUN_POOL_RETRY_SECONDS',
        'CITATION_BOT_BIG_RUN_TOKEN_CAPACITY',
        'CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND',
    ];

    #[\Override]
    protected function setUp(): void {
        foreach (self::ENVIRONMENT as $name) {
            $this->saved_environment[$name] = getenv($name);
            putenv($name);
        }
        $this->base_directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'citation-bot-big-run-edge-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($this->base_directory, 0700, true));
        $this->saved_buffer_level = ob_get_level();
        unset($GLOBALS['citation_bot_big_run_current'], $GLOBALS['citation_bot_admission_buffer_level']);
    }

    #[\Override]
    protected function tearDown(): void {
        while (ob_get_level() > $this->saved_buffer_level) {
            ob_end_clean();
        }
        $held_lock = $this->held_lock;
        $this->held_lock = null;
        if (is_resource($held_lock)) {
            flock($held_lock, LOCK_UN);
            fclose($held_lock);
        }
        foreach ($this->saved_environment as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
        unset($GLOBALS['citation_bot_big_run_current'], $GLOBALS['citation_bot_admission_buffer_level']);
        $this->removeTree($this->base_directory);
    }

    private function removeTree(string $path): void {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    }

    private function useStubs(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('uopz is required for this scenario');
        }
    }

    /**
     * Run a callback that is expected to end the request through exit().
     *
     * @param callable(): void $call
     */
    private function endsTheRequest(callable $call): bool {
        $this->useStubs();
        $ended = new RuntimeException('exit');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('exit', static function () use ($ended): never {
            throw $ended;
        }, true);
        try {
            $call();
        } catch (RuntimeException $exception) {
            return $exception === $ended;
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('exit');
        }
        return false;
    }

    /** user_notice() discards its output under PHPUnit, so capture warnings directly. */
    private function captureWarnings(): void {
        $this->useStubs();
        $warnings = [];
        $this->warnings = &$warnings;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_warning', static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        }, true);
    }

    private function releaseWarnings(): void {
        /** @psalm-suppress UnusedFunctionCall */
        uopz_unset_return('report_warning');
    }

    /** @return array<array-key, mixed> */
    private function currentLease(): array {
        $current = $GLOBALS['citation_bot_big_run_current'] ?? null;
        return is_array($current) ? $current : [];
    }

    /** Create the state directory and take the shared lock from a second handle. */
    private function holdSharedLock(): void {
        $this->assertNotNull(big_run_prepare_state_directory($this->base_directory));
        $handle = fopen(big_run_lock_path($this->base_directory), 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        $this->held_lock = $handle;
    }

    private function corruptState(): void {
        $this->assertNotNull(big_run_prepare_state_directory($this->base_directory));
        $this->assertNotFalse(file_put_contents(big_run_state_path($this->base_directory), '{this is not json'));
    }

    private function discoveryLease(): string {
        $result = big_run_try_acquire_discovery($this->base_directory, self::NOW);
        $this->assertTrue($result[0]);
        $this->assertIsString($result[2]);
        return $result[2];
    }

    private function probeLease(): string {
        $result = big_run_try_acquire_probe($this->base_directory, self::NOW);
        $this->assertTrue($result[0]);
        $this->assertIsString($result[2]);
        return $result[2];
    }

    private function runningLease(int $pages = 5): string {
        $result = big_run_try_acquire($pages, 'webform', $this->base_directory, self::NOW, false);
        $this->assertTrue($result[0]);
        $this->assertIsString($result[2]);
        return $result[2];
    }

    /** @return list<array{0: bool, 1: int|null, 2: string|null, 3: string|null, 4: int|null}> */
    private function everyAdmissionCall(): array {
        $dir = $this->base_directory;
        return [
            big_run_try_acquire(5, 'webform', $dir, self::NOW),
            big_run_try_acquire_discovery($dir, self::NOW),
            big_run_try_acquire_probe($dir, self::NOW),
            big_run_try_promote_probe_to_discovery('missing', $dir, self::NOW),
            big_run_try_mark_discovery_large('missing', $dir, self::NOW),
            big_run_try_promote('missing', 5, 'webform', $dir, self::NOW),
        ];
    }

    // ---- configuration and arithmetic ------------------------------------------

    /** @return array<string, array{string|null, int}> */
    public static function maxTotalProvider(): array {
        return [
            'unset' => [null, 10],
            'empty' => ['', 10],
            'not a number' => ['abc', 10],
            'negative' => ['-5', 10],
            'with a space' => [' 7', 10],
            'decimal' => ['7.5', 10],
            'zero is below the minimum' => ['0', 10],
            'above the maximum' => ['101', 10],
            'lowest allowed' => ['1', 1],
            'plain' => ['7', 7],
            'leading zeros' => ['007', 7],
            'highest allowed' => ['100', 100],
        ];
    }

    #[DataProvider('maxTotalProvider')]
    public function testIntegerConfigurationFallsBackOnBadValues(?string $value, int $expected): void {
        if ($value === null) {
            putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL');
        } else {
            putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=' . $value);
        }
        $this->assertSame($expected, big_run_max_total());
    }

    /** @return array<string, array{string|null, float}> */
    public static function refillProvider(): array {
        return [
            'unset' => [null, 4.0],
            'empty' => ['', 4.0],
            'not numeric' => ['fast', 4.0],
            'not finite' => ['INF', 4.0],
            'not a number' => ['NAN', 4.0],
            'below the minimum' => ['0.0001', 4.0],
            'above the maximum' => ['2000000', 4.0],
            'plain decimal' => ['2.5', 2.5],
            'exponent' => ['1e3', 1000.0],
            'lowest allowed' => ['0.001', 0.001],
        ];
    }

    #[DataProvider('refillProvider')]
    public function testFloatConfigurationFallsBackOnBadValues(?string $value, float $expected): void {
        if ($value === null) {
            putenv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND');
        } else {
            putenv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND=' . $value);
        }
        $this->assertSame($expected, big_run_token_refill_per_second());
    }

    public function testLargePoolCanNeverExceedTheTotalPool(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=3');
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=50');
        $this->assertSame(3, big_run_max_large());
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=2');
        $this->assertSame(2, big_run_max_large());
    }

    public function testHeartbeatIntervalLeavesRoomForRetriesBeforeStaleTimeout(): void {
        putenv('CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS=60');
        putenv('CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS=600');
        $this->assertSame(20, big_run_heartbeat_interval_seconds());
        putenv('CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS=10');
        $this->assertSame(10, big_run_heartbeat_interval_seconds());
    }

    public function testTimestampsMustBeFiniteAndNonNegative(): void {
        $this->assertSame(0.0, big_run_resolve_now(0.0));
        $this->assertSame(12.5, big_run_resolve_now(12.5));
        $this->assertGreaterThan(1.0, big_run_resolve_now(null));
        foreach ([-1.0, NAN, INF, -INF] as $bad) {
            try {
                big_run_resolve_now($bad);
                $this->fail('Accepted ' . (string) $bad);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNegativePageCountsCannotBePriced(): void {
        $this->expectException(InvalidArgumentException::class);
        big_run_token_cost(-1, 'webform');
    }

    public function testTokenCostIsCappedAtTheBucketCapacity(): void {
        putenv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY=7');
        $this->assertLessThanOrEqual(7, big_run_token_cost(1000, 'category'));
        $this->assertSame(0, big_run_token_cost(0, 'webform'));
    }

    public function testChargeTypesCollapseRequesterControlledLabels(): void {
        $this->assertSame('webform', big_run_charge_type('anything-the-requester-sends'));
        $this->assertSame('webform', big_run_charge_type(''));
        $this->assertSame('webform', big_run_charge_type('other'));
        $this->assertSame('category', big_run_charge_type('category'));
        $this->assertSame('webform_linked', big_run_charge_type('webform_linked'));
        $this->assertSame('testing', big_run_charge_type('testing'));
    }

    public function testEntryIdsAreNeverAllDigits(): void {
        $first = big_run_new_entry_id();
        $this->assertMatchesRegularExpression('~^b[0-9a-f]{16}$~D', $first);
        $this->assertNotSame($first, big_run_new_entry_id());
    }

    public function testDiscoveryProbesAreRequiredOnlyWhenThereIsMoreToFetch(): void {
        $this->assertFalse(big_run_discovery_probe_requires_lease(1000, 1000, false));
        $this->assertFalse(big_run_discovery_probe_requires_lease(BIG_RUN_DISCOVERY_RAW_PROBE_LIMIT - 1, BIG_RUN_DISCOVERY_BATCH_PROBE_LIMIT - 1, true));
        $this->assertTrue(big_run_discovery_probe_requires_lease(BIG_RUN_DISCOVERY_RAW_PROBE_LIMIT, 0, true));
        $this->assertTrue(big_run_discovery_probe_requires_lease(0, BIG_RUN_DISCOVERY_BATCH_PROBE_LIMIT, true));
    }

    // ---- unusable shared state --------------------------------------------------

    public function testUnopenableLockFileFailsClosedForEveryPhase(): void {
        $this->assertNotNull(big_run_prepare_state_directory($this->base_directory));
        $this->assertTrue(mkdir(big_run_lock_path($this->base_directory))); // a directory cannot be flocked
        foreach ($this->everyAdmissionCall() as $result) {
            $this->assertSame([false, 2, null, 'retry_later', 0], $result);
        }
        $this->assertSame('retry', big_run_heartbeat_status('missing', $this->base_directory, self::NOW));
        big_run_release('missing', $this->base_directory);
        $this->addToAssertionCount(1);
    }

    public function testSymlinkedLockFileIsRefused(): void {
        $this->assertNotNull(big_run_prepare_state_directory($this->base_directory));
        $target = $this->base_directory . DIRECTORY_SEPARATOR . 'elsewhere';
        $this->assertNotFalse(file_put_contents($target, ''));
        $this->assertTrue(symlink($target, big_run_lock_path($this->base_directory)));
        $this->assertFalse(big_run_open_lock_handle($this->base_directory));
        $this->assertSame([false, 2, null, 'retry_later', 0], big_run_try_acquire_probe($this->base_directory, self::NOW));
    }

    public function testSymlinkedStateDirectoryIsRefused(): void {
        $real = $this->base_directory . DIRECTORY_SEPARATOR . 'real-state';
        $this->assertTrue(mkdir($real, 0700));
        $this->assertTrue(symlink($real, big_run_state_directory($this->base_directory)));
        $this->assertNull(big_run_prepare_state_directory($this->base_directory));
        $this->assertFalse(big_run_open_lock_handle($this->base_directory));
        $this->assertSame([false, 2, null, 'retry_later', 0], big_run_try_acquire(5, 'webform', $this->base_directory, self::NOW));
    }

    public function testContendedLockDefersEveryPhaseWithoutChangingState(): void {
        $this->holdSharedLock();
        $this->assertSame([false, 1, null, 'retry_later', 0], big_run_try_acquire(5, 'webform', $this->base_directory, self::NOW));
        $this->assertSame([false, 1, null, 'retry_later', 0], big_run_try_acquire_discovery($this->base_directory, self::NOW));
        $this->assertSame([false, 1, null, 'retry_later', 0], big_run_try_acquire_probe($this->base_directory, self::NOW));
        $this->assertSame([false, 1, null, 'retry_later', 0], big_run_try_promote_probe_to_discovery('x', $this->base_directory, self::NOW));
        $this->assertSame([false, 1, null, 'retry_later', 0], big_run_try_mark_discovery_large('x', $this->base_directory, self::NOW));
        $this->assertSame([false, 1, null, 'retry_later', 0], big_run_try_promote('x', 5, 'webform', $this->base_directory, self::NOW));
        $this->assertSame('retry', big_run_heartbeat_status('x', $this->base_directory, self::NOW));
        $this->assertFalse(big_run_heartbeat('x', $this->base_directory, self::NOW));
        big_run_release('x', $this->base_directory); // best effort: must not throw or hang
        $this->assertFileDoesNotExist(big_run_state_path($this->base_directory));
    }

    public function testInvalidStateDefersEveryPhaseAndIsNeverRepaired(): void {
        $this->corruptState();
        foreach ($this->everyAdmissionCall() as $result) {
            $this->assertSame([false, 2, null, 'retry_later', 0], $result);
        }
        $this->assertSame('retry', big_run_heartbeat_status('x', $this->base_directory, self::NOW));
        big_run_release('x', $this->base_directory);
        $this->assertSame('{this is not json', file_get_contents(big_run_state_path($this->base_directory)));
    }

    // ---- lease phases -----------------------------------------------------------

    public function testUnknownLeasesAreRejectedWithoutCreatingState(): void {
        $this->assertSame([false, 2, null, 'retry_later', 0], big_run_try_promote_probe_to_discovery('missing', $this->base_directory, self::NOW));
        $this->assertSame([false, 2, null, 'retry_later', 0], big_run_try_mark_discovery_large('missing', $this->base_directory, self::NOW));
        $this->assertSame([false, 2, null, 'retry_later', 0], big_run_try_promote('missing', 5, 'webform', $this->base_directory, self::NOW));
        $this->assertSame('lost', big_run_heartbeat_status('missing', $this->base_directory, self::NOW));
    }

    public function testPromotingAnAlreadyPromotedProbeIsIdempotent(): void {
        $id = $this->discoveryLease();
        $this->assertSame([true, null, $id, null, 1], big_run_try_promote_probe_to_discovery($id, $this->base_directory, self::NOW));
    }

    public function testRunningLeasesCannotBePromotedAsProbes(): void {
        $id = $this->runningLease();
        $this->assertSame([false, 2, null, 'retry_later', 1], big_run_try_promote_probe_to_discovery($id, $this->base_directory, self::NOW));
        $this->assertSame([false, 2, null, 'retry_later', 1], big_run_try_mark_discovery_large($id, $this->base_directory, self::NOW));
        $this->assertSame([false, 2, null, 'retry_later', 1], big_run_try_promote($id, 5, 'webform', $this->base_directory, self::NOW, false));
    }

    public function testProbePromotionIsDeniedWhenTheTotalPoolIsFull(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=1');
        $this->discoveryLease();
        $probe = $this->probeLease();
        $denied = big_run_try_promote_probe_to_discovery($probe, $this->base_directory, self::NOW);
        $this->assertSame([false, big_run_pool_retry_seconds(), null, 'total_full', 1], $denied);
        $this->assertSame('lost', big_run_heartbeat_status($probe, $this->base_directory, self::NOW), 'A denied probe is removed');
    }

    public function testProbePoolIsSeparateFromTheTotalPool(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=1');
        putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=2');
        $this->discoveryLease();
        $this->probeLease();
        $this->probeLease();
        $denied = big_run_try_acquire_probe($this->base_directory, self::NOW);
        $this->assertFalse($denied[0]);
        $this->assertSame('probe_full', $denied[3]);
        $this->assertSame(2, $denied[4]);
        $this->assertSame(big_run_pool_retry_seconds(), $denied[1]);
    }

    public function testDiscoveryIsDeniedWhenTheTotalPoolIsFull(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=2');
        $this->discoveryLease();
        $this->runningLease();
        $denied = big_run_try_acquire_discovery($this->base_directory, self::NOW);
        $this->assertSame([false, big_run_pool_retry_seconds(), null, 'total_full', 2], $denied);
    }

    public function testLargeDiscoveryIsIdempotentAndBoundedByTheLargePool(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=1');
        $first = $this->discoveryLease();
        $this->assertSame([true, null, $first, null, 1], big_run_try_mark_discovery_large($first, $this->base_directory, self::NOW));
        $this->assertSame([true, null, $first, null, 1], big_run_try_mark_discovery_large($first, $this->base_directory, self::NOW), 'Marking twice is harmless');

        $second = $this->discoveryLease();
        $denied = big_run_try_mark_discovery_large($second, $this->base_directory, self::NOW);
        $this->assertSame([false, big_run_pool_retry_seconds(), null, 'large_full', 1], $denied);
        $this->assertSame('lost', big_run_heartbeat_status($second, $this->base_directory, self::NOW), 'A denied lease is removed');
    }

    public function testPromotionIsDeniedWhenTheLargePoolIsFull(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=1');
        $this->runningLease(60);
        $id = $this->discoveryLease();
        $denied = big_run_try_promote($id, 60, 'webform', $this->base_directory, self::NOW, false);
        $this->assertSame([false, big_run_pool_retry_seconds(), null, 'large_full', 1], $denied);
        $this->assertSame('lost', big_run_heartbeat_status($id, $this->base_directory, self::NOW));
    }

    public function testPromotionOfAnAlreadyLargeLeaseDoesNotCountItAgainstItself(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=1');
        $id = $this->discoveryLease();
        $this->assertTrue(big_run_try_mark_discovery_large($id, $this->base_directory, self::NOW)[0]);
        $promoted = big_run_try_promote($id, 60, 'webform', $this->base_directory, self::NOW, false);
        $this->assertSame([true, null, $id, null, 1], $promoted);
    }

    public function testPromotionIsDeniedWhenTokensRunOut(): void {
        putenv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY=1');
        putenv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND=0.5');
        $this->assertTrue(big_run_try_acquire(5, 'webform', $this->base_directory, self::NOW)[0]); // spends the only token
        $id = $this->discoveryLease();
        $denied = big_run_try_promote($id, 5, 'webform', $this->base_directory, self::NOW);
        $this->assertSame([false, 2, null, 'tokens', 1], $denied);
        $this->assertSame('lost', big_run_heartbeat_status($id, $this->base_directory, self::NOW));
    }

    public function testTokenWaitCanOutlastAPoolWait(): void {
        putenv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY=100');
        putenv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND=0.01');
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=1');
        $first = big_run_try_acquire(60, 'category', $this->base_directory, self::NOW); // spends the bucket
        $this->assertTrue($first[0]);
        // A second large request: the pool is full and the bucket is empty; the longer wait is reported.
        $result = big_run_try_acquire(60, 'category', $this->base_directory, self::NOW);
        $this->assertFalse($result[0]);
        $this->assertSame('tokens', $result[3]);
        $this->assertGreaterThan(big_run_pool_retry_seconds(), (int) $result[1]);
    }

    public function testPoolWaitIsReportedWhenItIsTheLongerWait(): void {
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=1');
        putenv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY=1000');
        putenv('CITATION_BOT_BIG_RUN_POOL_RETRY_SECONDS=600');
        $this->runningLease(60);
        $result = big_run_try_acquire(60, 'webform', $this->base_directory, self::NOW, false);
        $this->assertSame([false, 600, null, 'large_full', 1], $result);
    }

    // ---- persistence failures ---------------------------------------------------

    /** @return array<string, array{string}> */
    public static function persistFailureProvider(): array {
        return [
            'admission' => ['acquire'],
            'discovery' => ['discovery'],
            'probe' => ['probe'],
        ];
    }

    #[DataProvider('persistFailureProvider')]
    public function testFailureToPersistNewLeasesIsReportedAsRetryLater(string $phase): void {
        $this->useStubs();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('big_run_store_state_file', false);
        try {
            $result = match ($phase) {
                'acquire' => big_run_try_acquire(5, 'webform', $this->base_directory, self::NOW),
                'discovery' => big_run_try_acquire_discovery($this->base_directory, self::NOW),
                default => big_run_try_acquire_probe($this->base_directory, self::NOW),
            };
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('big_run_store_state_file');
        }
        $this->assertSame([false, 2, null, 'retry_later', 0], $result);
    }

    public function testFailureToPersistDeferralsAndDenialsIsReportedAsRetryLater(): void {
        $this->useStubs();
        putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=1');
        $this->discoveryLease();
        $probe = $this->probeLease();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('big_run_store_state_file', false);
        try {
            $full_admission = big_run_try_acquire(5, 'webform', $this->base_directory, self::NOW);
            $full_discovery = big_run_try_acquire_discovery($this->base_directory, self::NOW);
            $new_probe = big_run_try_acquire_probe($this->base_directory, self::NOW);
            $denied_promotion = big_run_try_promote_probe_to_discovery($probe, $this->base_directory, self::NOW);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('big_run_store_state_file');
        }
        $this->assertSame([false, 2, null, 'retry_later', 1], $full_admission, 'Full pool: the deferral could not be saved');
        $this->assertSame([false, 2, null, 'retry_later', 1], $full_discovery);
        $this->assertSame([false, 2, null, 'retry_later', 1], $new_probe);
        $this->assertSame([false, 2, null, 'retry_later', 1], $denied_promotion);
    }

    public function testFailureToPersistAFullProbePoolIsReportedAsRetryLater(): void {
        $this->useStubs();
        putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=1');
        $this->probeLease();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('big_run_store_state_file', false);
        try {
            $result = big_run_try_acquire_probe($this->base_directory, self::NOW);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('big_run_store_state_file');
        }
        $this->assertFalse($result[0]);
        $this->assertSame(2, $result[1], 'The short retry applies because the denial itself was not recorded');
        $this->assertNull($result[2]);
        $this->assertSame('retry_later', $result[3]);
        $this->assertIsInt($result[4]);
    }

    public function testFailureToPersistLargePoolDenialsIsReportedAsRetryLater(): void {
        $this->useStubs();
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=1');
        $this->runningLease(60);
        $marking = $this->discoveryLease();
        $promoting = $this->discoveryLease();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('big_run_store_state_file', false);
        try {
            $large_denial = big_run_try_mark_discovery_large($marking, $this->base_directory, self::NOW);
            $promotion_denial = big_run_try_promote($promoting, 60, 'webform', $this->base_directory, self::NOW, false);
            $successful_marking = big_run_try_promote($promoting, 5, 'webform', $this->base_directory, self::NOW, false);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('big_run_store_state_file');
        }
        $this->assertFalse($large_denial[0]);
        $this->assertSame('retry_later', $large_denial[3]);
        $this->assertFalse($promotion_denial[0]);
        $this->assertSame('retry_later', $promotion_denial[3]);
        $this->assertFalse($successful_marking[0], 'Even an allowed promotion fails when it cannot be saved');
        $this->assertSame('retry_later', $successful_marking[3]);
    }

    public function testFailureToPersistTransitionsLeavesTheStateUntouched(): void {
        $this->useStubs();
        $discovery = $this->discoveryLease();
        $probe = $this->probeLease();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('big_run_store_state_file', false);
        try {
            $probe_result = big_run_try_promote_probe_to_discovery($probe, $this->base_directory, self::NOW);
            $large_result = big_run_try_mark_discovery_large($discovery, $this->base_directory, self::NOW);
            $promote_result = big_run_try_promote($discovery, 5, 'webform', $this->base_directory, self::NOW, false);
            $heartbeat = big_run_heartbeat_status($discovery, $this->base_directory, self::NOW);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('big_run_store_state_file');
        }
        $this->assertFalse($probe_result[0]);
        $this->assertSame('retry_later', $probe_result[3]);
        $this->assertFalse($large_result[0]);
        $this->assertSame('retry_later', $large_result[3]);
        $this->assertFalse($promote_result[0]);
        $this->assertSame('retry_later', $promote_result[3]);
        $this->assertSame('retry', $heartbeat);

        // Nothing was persisted, so the original leases are still exactly as they were.
        $this->assertSame([true, null, $discovery, null, 1], big_run_try_promote_probe_to_discovery($discovery, $this->base_directory, self::NOW));
        $this->assertSame('ok', big_run_heartbeat_status($probe, $this->base_directory, self::NOW));
    }

    public function testFailureToPersistReleaseKeepsTheLease(): void {
        $this->useStubs();
        $id = $this->discoveryLease();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('big_run_store_state_file', false);
        try {
            big_run_release($id, $this->base_directory);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('big_run_store_state_file');
        }
        $this->assertSame('ok', big_run_heartbeat_status($id, $this->base_directory, self::NOW));
        big_run_release($id, $this->base_directory);
        $this->assertSame('lost', big_run_heartbeat_status($id, $this->base_directory, self::NOW));
    }

    public function testReleaseIgnoresEmptyIdentifiersAndUnknownLeases(): void {
        $id = $this->discoveryLease();
        big_run_release('', $this->base_directory);
        big_run_release('unknown', $this->base_directory);
        $this->assertSame('ok', big_run_heartbeat_status($id, $this->base_directory, self::NOW));
        $this->assertSame('retry', big_run_heartbeat_status('', $this->base_directory, self::NOW));
    }

    // ---- heartbeats and lease loss ----------------------------------------------

    public function testHeartbeatRefreshesALiveLeaseAndReportsALostOne(): void {
        $id = $this->runningLease();
        $this->assertSame('ok', big_run_heartbeat_status($id, $this->base_directory, self::NOW + 5));
        $this->assertTrue(big_run_heartbeat($id, $this->base_directory, self::NOW + 6));
        $this->assertSame('lost', big_run_heartbeat_status('b' . str_repeat('0', 16), $this->base_directory, self::NOW));
        $this->assertFalse(big_run_heartbeat('b' . str_repeat('0', 16), $this->base_directory, self::NOW));
    }

    public function testStaleLeasesAreLostWhenAnotherRequestPrunesThem(): void {
        $id = $this->runningLease();
        $much_later = self::NOW + big_run_stale_timeout_seconds() + 1;
        $this->discoveryLease(); // does not prune with the old clock
        $this->assertTrue(big_run_try_acquire_probe($this->base_directory, $much_later)[0], 'Admission at the later time prunes the stale lease');
        $this->assertSame('lost', big_run_heartbeat_status($id, $this->base_directory, $much_later));
    }

    public function testHeartbeatOrStopReturnsStatusForHealthyAndRetryableLeases(): void {
        $id = $this->runningLease();
        $this->assertTrue(big_run_heartbeat_or_stop($id, $this->base_directory, self::NOW + 1));
        $this->holdSharedLock();
        $this->assertFalse(big_run_heartbeat_or_stop($id, $this->base_directory, self::NOW + 2), 'A busy lock is retried, not fatal');
    }

    public function testHeartbeatOrStopEndsTheRequestWhenTheLeaseIsLost(): void {
        $this->captureWarnings();
        $ended = new RuntimeException('exit');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('exit', static function () use ($ended): never {
            throw $ended;
        }, true);
        $GLOBALS['citation_bot_big_run_current'] = ['entry_id' => 'b' . str_repeat('1', 16), 'base_directory' => $this->base_directory, 'last_heartbeat_attempt' => 0.0];
        ob_start();
        try {
            big_run_heartbeat_or_stop('b' . str_repeat('1', 16), $this->base_directory, self::NOW);
            $this->fail('A lost lease must stop the request');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            ob_end_clean();
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('exit');
            $this->releaseWarnings();
        }
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('lost ownership of this bulk-run lease', $this->warnings[0]);
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS, 'The stopped lease is no longer current');
    }

    public function testLeaseLossUsesTheBusyPageWhileAdmissionIsStillBuffered(): void {
        $this->captureWarnings();
        ob_start(); // outer capture: receives the flushed admission buffer
        ob_start(); // the admission buffer itself
        $GLOBALS['citation_bot_admission_buffer_level'] = ob_get_level();
        try {
            $stopped = $this->endsTheRequest(static function (): void {
                big_run_stop_after_lease_loss('b' . str_repeat('2', 16));
            });
        } finally {
            while (ob_get_level() > $this->saved_buffer_level) {
                ob_end_clean();
            }
            $this->releaseWarnings();
        }
        $this->assertTrue($stopped, 'A lost lease must stop the request');
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('shared lease was lost', $this->warnings[0]);
        $this->assertArrayNotHasKey('citation_bot_admission_buffer_level', $GLOBALS);
    }

    public function testCurrentLeaseHeartbeatIsRateLimitedAndRecorded(): void {
        $id = $this->runningLease();
        big_run_maybe_heartbeat_current(self::NOW); // no current lease: nothing happens
        big_run_set_current_entry($id, $this->base_directory, self::NOW);
        $interval = big_run_heartbeat_interval_seconds();

        big_run_maybe_heartbeat_current(self::NOW + $interval - 1);
        $this->assertSame(self::NOW, $GLOBALS['citation_bot_big_run_current']['last_heartbeat_attempt'], 'Too soon: no attempt');

        big_run_maybe_heartbeat_current(self::NOW + $interval);
        $this->assertSame(self::NOW + $interval, $GLOBALS['citation_bot_big_run_current']['last_heartbeat_attempt']);
    }

    public function testCurrentLeaseHeartbeatRetriesSoonAfterATransientFailure(): void {
        $id = $this->runningLease();
        big_run_set_current_entry($id, $this->base_directory, self::NOW);
        $interval = big_run_heartbeat_interval_seconds();
        $this->holdSharedLock();
        big_run_maybe_heartbeat_current(self::NOW + $interval + 1);
        $this->assertSame(self::NOW, $GLOBALS['citation_bot_big_run_current']['last_heartbeat_attempt'], 'A failed attempt does not reset the timer');
    }

    public function testCurrentLeaseWithoutOptionalFieldsUsesTheConfiguredDirectoryAndHeartbeatsImmediately(): void {
        $saved_directory = getenv('PHP_RATE_LIMIT_DIRECTORY');
        putenv('PHP_RATE_LIMIT_DIRECTORY=' . $this->base_directory);
        try {
            $id = $this->runningLease();
            $GLOBALS['citation_bot_big_run_current'] = ['entry_id' => $id];
            big_run_maybe_heartbeat_current(self::NOW + 1.0);
            $current = $this->currentLease();
            $this->assertSame($id, $current['entry_id'] ?? null);
            $this->assertSame(
                self::NOW + 1.0,
                $current['last_heartbeat_attempt'] ?? null,
                'A missing timestamp counts as never attempted, so the first hook renews the lease'
            );
        } finally {
            if ($saved_directory === false) {
                putenv('PHP_RATE_LIMIT_DIRECTORY');
            } else {
                putenv('PHP_RATE_LIMIT_DIRECTORY=' . $saved_directory);
            }
        }
    }

    public function testCurrentLeaseHelpersIgnoreMalformedOrMismatchedEntries(): void {
        big_run_clear_current_entry('anything'); // nothing set: no error
        $GLOBALS['citation_bot_big_run_current'] = 'garbage';
        big_run_maybe_heartbeat_current(self::NOW);
        big_run_clear_current_entry();
        $this->assertSame('garbage', $GLOBALS['citation_bot_big_run_current'], 'Only array entries are managed');

        $GLOBALS['citation_bot_big_run_current'] = ['entry_id' => 123];
        big_run_maybe_heartbeat_current(self::NOW);
        $this->assertSame(['entry_id' => 123], $GLOBALS['citation_bot_big_run_current']);

        big_run_set_current_entry('keep-me', $this->base_directory, self::NOW);
        big_run_clear_current_entry('someone-else');
        $this->assertSame('keep-me', $GLOBALS['citation_bot_big_run_current']['entry_id']);
        big_run_clear_current_entry('keep-me');
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS);
    }
}
