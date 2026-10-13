<?php

declare(strict_types=1);

/*
 * Test-first regressions for the Citoid rate limiter in PR #6120.
 * These use only isolated local files; no Citoid HTTP requests are sent.
 * Includes mount-marker regressions for configured shared namespaces.
 */

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('citoid-regression')]
final class citoidRateLimitRegressionTest extends testBaseClass {
    /** @var list<string> */
    private array $test_directories = [];

    /** @var array<string, string|false> */
    private array $saved_environment = [];

    /**
     * Only delete directories allocated by this test. Never touch the live
     * rate-limit directory, which may be shared with other PHP processes.
     */
    #[\Override]
    protected function tearDown(): void {
        foreach ($this->saved_environment as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        foreach ($this->test_directories as $directory) {
            if (!is_dir($directory) || is_link($directory)) {
                continue;
            }
            $files = glob($directory . '/rate.pending.*');
            foreach ($files === false ? [] : $files as $file) {
                if (is_file($file) || is_link($file)) {
                    @unlink($file);
                }
            }
            foreach (['rate.state', 'rate.lock', 'rate.lock.replaced', 'rate.namespace'] as $name) {
                @unlink($directory . '/' . $name);
            }
            @rmdir($directory . '/rate.state');
            @rmdir($directory);
        }
        parent::tearDown();
    }

    private function freshDirectory(bool $create = true): string {
        $directory = sys_get_temp_dir() . '/citoid-regression-' . bin2hex(random_bytes(12));
        $this->test_directories[] = $directory;
        if ($create) {
            $this->assertTrue(mkdir($directory, 0700));
        }
        return $directory;
    }

    private function assignEnvironment(string $name, string $value): void {
        if (!array_key_exists($name, $this->saved_environment)) {
            $this->saved_environment[$name] = getenv($name, true);
        }
        $this->assertTrue(putenv($name . '=' . $value));
    }

    private static function admit(?string $directory, float $timeout = 0.2): bool {
        $method = new ReflectionMethod(Zotero::class, 'throttle_citoid_requests');
        return $method->invoke(null, $directory, $timeout);
    }

    private static function record(float $timestamp): string {
        $stamp = sprintf('%.6F', $timestamp);
        return 'CB1|' . $stamp . '|' . mb_substr(hash('sha256', $stamp), 0, 16, '8bit') . "\n";
    }

    private function writeState(string $directory, string $payload): void {
        $path = $directory . '/rate.state';
        $this->assertSame(mb_strlen($payload, '8bit'), file_put_contents($path, $payload));
        $this->assertTrue(chmod($path, 0600));
    }

    private static function pendingFiles(string $directory): array {
        $paths = glob($directory . '/rate.pending.*');
        return $paths === false ? [] : $paths;
    }

    /** A denied admission must never call the supplied HTTP transport. */
    public function testGuardedDispatchDeniesWithoutInvokingTransport(): void {
        $directory = $this->freshDirectory(false);
        $sent = 0;
        $reason = null;
        $dispatch = new ReflectionMethod(Zotero::class, 'citoid_guarded_send');
        $callback = static function () use (&$sent): string {
            ++$sent;
            return 'unexpected';
        };
        $result = $dispatch->invokeArgs(null, [$callback, &$reason, $directory]);
        $this->assertFalse($result);
        $this->assertSame('directory-unavailable', $reason);
        $this->assertSame(0, $sent);
    }

    /** Invalid state blocks the dispatch even when a lock can be acquired. */
    public function testGuardedDispatchRejectsCorruptStateWithoutTransport(): void {
        $directory = $this->freshDirectory();
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        $this->writeState($directory, 'invalid-checksum');
        $sent = 0;
        $reason = null;
        $dispatch = new ReflectionMethod(Zotero::class, 'citoid_guarded_send');
        $callback = static function () use (&$sent): string {
            ++$sent;
            return 'unexpected';
        };
        $result = $dispatch->invokeArgs(null, [$callback, &$reason, $directory]);
        $this->assertFalse($result);
        $this->assertSame('state-invalid', $reason);
        $this->assertSame(0, $sent);
    }

    /** Both request paths share a guard that invokes a transport only on admission. */
    public function testGuardedDispatchAllowsExactlyOneTransportInvocation(): void {
        $directory = $this->freshDirectory();
        $sent = 0;
        $reason = 'stale';
        $dispatch = new ReflectionMethod(Zotero::class, 'citoid_guarded_send');
        $callback = static function () use (&$sent): string {
            ++$sent;
            return 'metadata';
        };
        $result = $dispatch->invokeArgs(null, [$callback, &$reason, $directory]);
        $this->assertSame('metadata', $result);
        $this->assertNull($reason);
        $this->assertSame(1, $sent);
    }

    /** Even a second dispatch attempt cannot bypass a corrupted shared state. */
    public function testGuardedDispatchRetryAlsoRequiresAdmission(): void {
        $directory = $this->freshDirectory();
        $sent = 0;
        $reason = null;
        $dispatch = new ReflectionMethod(Zotero::class, 'citoid_guarded_send');
        $callback = static function () use (&$sent): string {
            ++$sent;
            return 'metadata';
        };
        $this->assertSame('metadata', $dispatch->invokeArgs(null, [$callback, &$reason, $directory]));
        $this->assertSame(1, $sent);
        $this->writeState($directory, 'invalid-checksum');
        $result = $dispatch->invokeArgs(null, [$callback, &$reason, $directory]);
        $this->assertFalse($result);
        $this->assertSame('state-invalid', $reason);
        $this->assertSame(1, $sent);
    }

    /** A held mutex reports contention without calling the transport. */
    public function testThrottleReportsLockTimeoutCategory(): void {
        $directory = $this->freshDirectory();
        $holder = fopen($directory . '/rate.lock', 'c+');
        $this->assertNotFalse($holder);
        try {
            $this->assertTrue(flock($holder, LOCK_EX | LOCK_NB));
            $reason = null;
            $throttle = new ReflectionMethod(Zotero::class, 'throttle_citoid_requests');
            $this->assertFalse($throttle->invokeArgs(null, [$directory, 0.0, &$reason]));
            $this->assertSame('lock-timeout', $reason);
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }
    }

    public function testValidClockRollbackRequiresConservativeRecovery(): void {
        $directory = $this->freshDirectory();
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        // A valid committed record may appear in the future after NTP correction.
        $this->writeState($directory, self::record(microtime(true) + 600.0));

        $start = hrtime(true);
        $this->assertTrue(self::admit($directory), 'A verified clock rollback must recover without manual reset');
        $elapsed = (hrtime(true) - $start) / 1e9;
        $this->assertGreaterThanOrEqual(0.85, $elapsed, 'Recovery must wait a full conservative interval');
        $this->assertLessThan(4.0, $elapsed, 'Clock rollback recovery must be bounded');
        $this->assertMatchesRegularExpression(
            '/^CB1\|[0-9]{10,12}\.[0-9]{6}\|[0-9a-f]{16}\n$/',
            (string) file_get_contents($directory . '/rate.state')
        );
    }

    /** A peer with an old wall clock must not grant an immediate admission. */
    public function testOldCommittedTimestampStillRequiresFullMonotonicWait(): void {
        $directory = $this->freshDirectory();
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        // Simulate a peer publishing with a clock one hour behind this host.
        $this->writeState($directory, self::record(microtime(true) - 3600.0));

        $start = hrtime(true);
        $this->assertTrue(self::admit($directory));
        $elapsed = (hrtime(true) - $start) / 1e9;
        $this->assertGreaterThanOrEqual(0.85, $elapsed, 'Stale peer wall time cannot eliminate pacing');
        $this->assertLessThan(4.0, $elapsed);
    }

    /** Switching between fast and slow peer timestamps must not cause a burst. */
    public function testAlternatingHostClockOffsetsNeverBypassSharedCooldown(): void {
        $directory = $this->freshDirectory();
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        $this->writeState($directory, self::record(microtime(true) + 3600.0));

        $start = hrtime(true);
        $this->assertTrue(self::admit($directory));
        // Represent another worker publishing an earlier timestamp after its
        // clock steps backward. A checksum-valid record still requires pacing.
        $this->writeState($directory, self::record(microtime(true) - 3600.0));
        $this->assertTrue(self::admit($directory));
        $elapsed = (hrtime(true) - $start) / 1e9;
        $this->assertGreaterThanOrEqual(1.85, $elapsed, 'Both admitted sends must be paced');
        $this->assertLessThan(7.0, $elapsed);
    }

    public function testCorruptFutureStateNeverAuthorizesRecovery(): void {
        $directory = $this->freshDirectory();
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        $bad = preg_replace('/[0-9a-f]{16}\n$/', "0000000000000000\n", self::record(microtime(true) + 600.0));
        $this->assertNotNull($bad);
        $this->writeState($directory, $bad);

        $this->assertFalse(self::admit($directory));
        $this->assertSame($bad, file_get_contents($directory . '/rate.state'));
        $this->assertSame([], self::pendingFiles($directory));
    }

    public function testDisappearedPublishedStateCannotTrustStaleLegacyTime(): void {
        $directory = $this->freshDirectory();
        // The legacy stamp can be old even if the missing published state was
        // recent. Its age is not evidence that a new request is safe.
        $legacy = (string) (microtime(true) - 3600.0);
        $this->assertSame(mb_strlen($legacy, '8bit'), file_put_contents($directory . '/rate.lock', $legacy));
        $this->writeState($directory, self::record(microtime(true)));
        $this->assertTrue(unlink($directory . '/rate.state'));

        $start = hrtime(true);
        $this->assertTrue(self::admit($directory));
        $elapsed = (hrtime(true) - $start) / 1e9;
        $this->assertGreaterThanOrEqual(0.85, $elapsed, 'Unknown missing state requires conservative cooldown');
        $this->assertLessThan(4.0, $elapsed);
    }

    public function testMissingConfiguredSharedDirectoryFailsWithoutCreation(): void {
        $directory = $this->freshDirectory(false);
        $this->assignEnvironment('CITOID_RATE_LIMIT_DIR', $directory);
        $this->assignEnvironment('CITOID_ALLOW_LOCAL_FALLBACK', '1');

        // Null selects the production configuration resolver. An unmounted
        // shared filesystem must not silently become an independent local lock.
        $this->assertFalse(self::admit(null));
        $this->assertDirectoryDoesNotExist($directory);
    }

    /** Provision a marker exactly as the configured shared filesystem requires. */
    private function writeNamespace(string $directory, string $id): void {
        $path = $directory . '/rate.namespace';
        $value = 'CBNS1|' . $id . "\n";
        $this->assertSame(mb_strlen($value, '8bit'), file_put_contents($path, $value));
        $this->assertTrue(chmod($path, 0600));
    }

    public function testSharedNamespaceRequiresPreprovisionedMarker(): void {
        $directory = $this->freshDirectory();
        $id = bin2hex(random_bytes(16));
        $this->assignEnvironment('CITOID_RATE_LIMIT_DIR', $directory);
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', $id);
        $this->assignEnvironment('CITOID_ALLOW_LOCAL_FALLBACK', '1');

        // A missing mount may expose an existing but unrelated local directory.
        $this->assertFalse(self::admit(null));
        $this->assertFileDoesNotExist($directory . '/rate.lock');
        $this->writeNamespace($directory, $id);
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', '');
        $this->assertFalse(self::admit(null), 'Empty namespace configuration must fail closed');
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', $id);
        $this->assertFalse(self::admit(null), 'Configured shared lock must be provisioned, not created implicitly');
        $this->assertFileDoesNotExist($directory . '/rate.lock');
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        $this->assertTrue(self::admit(null));
        $this->assertFileExists($directory . '/rate.state');
    }

    public function testSharedNamespaceRejectsWrongOrInvalidIdentifier(): void {
        $directory = $this->freshDirectory();
        $id = bin2hex(random_bytes(16));
        $this->writeNamespace($directory, $id);
        $this->assignEnvironment('CITOID_RATE_LIMIT_DIR', $directory);
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', str_repeat('a', 31));
        $this->assertFalse(self::admit(null));
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', mb_strtoupper($id, 'UTF-8'));
        $this->assertFalse(self::admit(null));
        $wrong = $id === str_repeat('0', 32) ? str_repeat('1', 32) : str_repeat('0', 32);
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', $wrong);
        $this->assertFalse(self::admit(null));
        $this->assertFileDoesNotExist($directory . '/rate.lock');
    }

    public function testSharedNamespaceRejectsSymlinkAndUnsafePermissions(): void {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('symlink')) {
            $this->markTestSkipped('POSIX symlinks required');
        }
        $directory = $this->freshDirectory();
        $id = bin2hex(random_bytes(16));
        $path = $directory . '/rate.namespace';
        $this->assignEnvironment('CITOID_RATE_LIMIT_DIR', $directory);
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', $id);
        $this->writeNamespace($directory, $id);
        $this->assertTrue(chmod($path, 0644));
        $this->assertFalse(self::admit(null));
        $this->assertTrue(chmod($path, 0600));
        $this->assertTrue(rename($path, $path . '.original'));
        try {
            if (!@symlink($path . '.original', $path)) {
                $this->markTestSkipped('Filesystem does not permit symlinks');
            }
            $this->assertFalse(self::admit(null));
        } finally {
            @unlink($path);
            $this->assertTrue(rename($path . '.original', $path));
        }
        $this->assertFileDoesNotExist($directory . '/rate.lock');
    }

    public function testFailedPublishCannotReplaceStateDirectory(): void {
        $directory = $this->freshDirectory();
        $lock_path = $directory . '/rate.lock';
        $this->assertSame(0, file_put_contents($lock_path, ''));
        $this->assertTrue(mkdir($directory . '/rate.state', 0700));
        $inode = fileinode($lock_path);

        $this->assertFalse(self::admit($directory), 'rename onto a directory must fail closed');
        $this->assertDirectoryExists($directory . '/rate.state');
        clearstatcache(true, $lock_path);
        $this->assertSame($inode, fileinode($lock_path));
        $this->assertSame([], self::pendingFiles($directory), 'Ordinary publication failure must clean pending files');
    }

    public function testReplacedLockDuringPublishPreservesCommittedState(): void {
        $directory = $this->freshDirectory();
        $original = self::record(microtime(true) - 5.0);
        $this->writeState($directory, $original);
        $path = $directory . '/rate.lock';
        $handle = fopen($path, 'c+');
        $this->assertNotFalse($handle);
        try {
            $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
            $this->assertTrue(rename($path, $path . '.replaced'));
            $this->assertSame(0, file_put_contents($path, ''));
            $commit = new ReflectionMethod(Zotero::class, 'citoid_commit_state');
            $this->assertFalse($commit->invoke(null, $handle, $path, $directory));
            $this->assertSame($original, file_get_contents($directory . '/rate.state'));
            $this->assertSame([], self::pendingFiles($directory));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function testInjectedShortWritePreservesLastPublishedState(): void {
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('proc_open') ||
            !function_exists('posix_setrlimit') || !defined('POSIX_RLIMIT_FSIZE') ||
            !function_exists('pcntl_signal')) {
            $this->markTestSkipped('Linux POSIX subprocess limits and signals are required');
        }
        $directory = $this->freshDirectory();
        $original = self::record(microtime(true) - 5.0);
        $this->writeState($directory, $original);
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));

        // RLIMIT_FSIZE limits the pending record to 16 bytes, forcing fwrite
        // to fail or complete only partially before publication can occur.
        $fixture = dirname(__DIR__, 3) . '/fixtures/citoidShortWriteWorker.php';
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Fixed fault-injection subprocess.
        $process = proc_open(
            [PHP_BINARY, $fixture, $directory],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertTrue(is_resource($process), 'Unable to launch short-write worker');
        $stdout = '';
        $stderr = '';
        try {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
        }
        $this->assertSame(0, $status, 'Short-write worker failed: ' . $stdout . ' ' . $stderr);
        $this->assertSame($original, file_get_contents($directory . '/rate.state'));
        $this->assertSame([], self::pendingFiles($directory));
        $this->assertTrue(self::admit($directory), 'The original committed state must remain usable');
    }

    /** A replaced directory pathname must not validate an older open descriptor. */
    public function testCitoidDirectoryDescriptorDetectsReplacement(): void {
        $directory = $this->freshDirectory();
        $handle = fopen($directory, 'rb');
        $this->assertNotFalse($handle);
        $check = new ReflectionMethod(Zotero::class, 'citoid_directory_matches_path');
        try {
            $this->assertTrue($check->invoke(null, $handle, $directory));
            $this->assertTrue(rename($directory, $directory . '.moved'));
            $this->assertTrue(mkdir($directory, 0700));
            $this->assertFalse($check->invoke(null, $handle, $directory));
        } finally {
            fclose($handle);
            @rmdir($directory);
            @rename($directory . '.moved', $directory);
        }
    }

    /** Exercise the publication function, not merely the earlier state reader. */
    public function testCitoidCommitRejectsNonRegularPublishedTarget(): void {
        $directory = $this->freshDirectory();
        $lock_path = $directory . '/rate.lock';
        $this->assertSame(0, file_put_contents($lock_path, ''));
        $this->assertTrue(mkdir($directory . '/rate.state', 0700));
        $handle = fopen($lock_path, 'c+');
        $this->assertNotFalse($handle);
        try {
            $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
            $commit = new ReflectionMethod(Zotero::class, 'citoid_commit_state');
            $this->assertFalse($commit->invoke(null, $handle, $lock_path, $directory));
            $this->assertDirectoryExists($directory . '/rate.state');
            $this->assertSame([], self::pendingFiles($directory));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function testCitoidCommitDoesNotReplaceSymlinkedPublishedState(): void {
        if (!function_exists('symlink') || PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('POSIX symlinks required');
        }
        $directory = $this->freshDirectory();
        $target = $directory . '/unrelated';
        $this->assertSame(8, file_put_contents($target, 'outside!'));
        $this->assertTrue(symlink($target, $directory . '/rate.state'));
        $lock_path = $directory . '/rate.lock';
        $handle = fopen($lock_path, 'c+');
        $this->assertNotFalse($handle);
        try {
            $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
            $commit = new ReflectionMethod(Zotero::class, 'citoid_commit_state');
            $this->assertFalse($commit->invoke(null, $handle, $lock_path, $directory));
            $this->assertSame('outside!', file_get_contents($target));
            $this->assertTrue(is_link($directory . '/rate.state'));
            $this->assertSame([], self::pendingFiles($directory));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
            @unlink($target);
        }
    }

    public function testZeroTimeoutPerformsOneNonblockingAttempt(): void {
        $directory = $this->freshDirectory();
        $lock = fopen($directory . '/rate.lock', 'c+');
        $this->assertNotFalse($lock);
        try {
            $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
            $start = hrtime(true);
            $this->assertFalse(self::admit($directory, 0.0));
            $this->assertLessThan(0.5, (hrtime(true) - $start) / 1e9);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{int, string, string} */
    private static function runPendingCleanup(string $directory, string $mode): array {
        $script = dirname(__DIR__, 4) . '/tools/cleanup-citoid-pending.php';
        // A child PHP interpreter does not inherit the runner's uopz.exit flag.
        // Without this -d setting the offline tool intentionally exits 255.
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Fixed offline maintenance test subprocess.
        $process = proc_open(
            [PHP_BINARY, '-d', 'uopz.exit=1', $script, '--offline', $mode, $directory],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start offline cleanup subprocess');
        }
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), (string) $output, (string) $errors];
    }

    public function testOfflineCleanupPreservesLockStateAndRecentWrites(): void {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('proc_open') ||
            !function_exists('posix_geteuid')) {
            $this->markTestSkipped('POSIX CLI cleanup is required');
        }
        $directory = $this->freshDirectory();
        $lock_path = $directory . '/rate.lock';
        $this->assertSame(0, file_put_contents($lock_path, ''));
        // Production's c+ lock creation may leave mode 0644 with a 0022 umask.
        // Read-only group/other bits do not let another UID replace the mutex.
        $this->assertTrue(chmod($lock_path, 0644));
        $this->writeState($directory, self::record(microtime(true) - 10.0));
        $state = file_get_contents($directory . '/rate.state');
        $inode = fileinode($lock_path);
        $old = $directory . '/rate.pending.' . str_repeat('a', 24);
        $recent = $directory . '/rate.pending.' . str_repeat('b', 24);
        $unrecognized = $directory . '/rate.pending.crash-fixture';
        foreach ([$old, $recent, $unrecognized] as $path) {
            $this->assertSame(6, file_put_contents($path, 'orphan'));
            $this->assertTrue(chmod($path, 0600));
        }
        $this->assertTrue(touch($old, time() - 600));
        $this->assertTrue(touch($unrecognized, time() - 600));

        [$code, $stdout] = self::runPendingCleanup($directory, '--dry-run');
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Would delete', $stdout);
        $this->assertFileExists($old);

        [$code, $stdout] = self::runPendingCleanup($directory, '--delete');
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted', $stdout);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertFileExists($unrecognized);
        $this->assertSame($state, file_get_contents($directory . '/rate.state'));
        clearstatcache(true, $lock_path);
        $this->assertSame($inode, fileinode($lock_path));
    }

    public function testOfflineCleanupRejectsWritableLockAndUnsafeDirectoryArgument(): void {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('proc_open') ||
            !function_exists('posix_geteuid')) {
            $this->markTestSkipped('POSIX CLI cleanup is required');
        }
        $directory = $this->freshDirectory();
        $lock_path = $directory . '/rate.lock';
        $this->assertSame(0, file_put_contents($lock_path, ''));
        $this->assertTrue(chmod($lock_path, 0666));
        [$code] = self::runPendingCleanup($directory, '--dry-run');
        $this->assertSame(2, $code, 'A group/other-writable mutex must be refused');

        $this->assertTrue(chmod($lock_path, 0600));
        [$code] = self::runPendingCleanup($directory . '/../' . basename($directory), '--dry-run');
        $this->assertSame(2, $code, 'A traversal alias must be refused');
        [$code] = self::runPendingCleanup($directory . '/*', '--dry-run');
        $this->assertSame(2, $code, 'A glob expression must be refused');
    }

    public function testOfflineCleanupRejectsHeldLockAndUnsafePendingFile(): void {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('proc_open') ||
            !function_exists('posix_geteuid') || !function_exists('symlink')) {
            $this->markTestSkipped('POSIX CLI cleanup and symlinks are required');
        }
        $directory = $this->freshDirectory();
        $lock_path = $directory . '/rate.lock';
        $lock = fopen($lock_path, 'c+');
        $this->assertNotFalse($lock);
        $old = $directory . '/rate.pending.' . str_repeat('c', 24);
        $this->assertSame(6, file_put_contents($old, 'orphan'));
        $this->assertTrue(touch($old, time() - 600));
        try {
            $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
            [$code, , $stderr] = self::runPendingCleanup($directory, '--delete');
            $this->assertSame(2, $code, $stderr);
            $this->assertStringContainsString('Lock is held', $stderr);
            $this->assertFileExists($old);
            $this->assertTrue(flock($lock, LOCK_UN));
            $link = $directory . '/rate.pending.' . str_repeat('d', 24);
            $this->assertTrue(symlink($old, $link));
            [$code, , $stderr] = self::runPendingCleanup($directory, '--delete');
            $this->assertSame(2, $code, $stderr);
            $this->assertStringContainsString('Unsafe pending entry', $stderr);
            $this->assertFileExists($old);
            $this->assertTrue(is_link($link));
        } finally {
            fclose($lock);
        }
    }

    /**
     * Burst test: bounded lock waits allow a few paced admissions and reject
     * the rest. Run CITOID_STRESS_WORKERS=20 or 50 for heavier local testing.
     */
    #[Group('citoid-stress')]
    public function testContentionBurstPacesAdmissionsAndBoundsDenials(): void {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('proc_open') ||
            !function_exists('proc_terminate') || !function_exists('proc_get_status')) {
            $this->markTestSkipped('Independent POSIX subprocesses required');
        }
        $workers = getenv('CITOID_STRESS_WORKERS') ?: '10';
        $this->assertContains($workers, ['10', '20', '50']);
        $count = (int) $workers;
        $directory = $this->freshDirectory();
        $fixture = dirname(__DIR__, 3) . '/fixtures/citoidContentionWorker.php';
        $start = $directory . '/start';
        $processes = [];
        $success = [];
        $denied = 0;
        try {
            for ($i = 0; $i < $count; ++$i) {
                $ready = $directory . '/ready-' . $i;
                // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Fixed load-test subprocess.
                $process = proc_open(
                    [PHP_BINARY, $fixture, $directory, $ready, $start, '2.2'],
                    [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes
                );
                $this->assertTrue(is_resource($process));
                $processes[] = ['process' => $process, 'pipes' => $pipes];
            }
            $deadline = hrtime(true) + 40000000000;
            do {
                $ready_count = 0;
                for ($i = 0; $i < $count; ++$i) {
                    $ready_path = $directory . '/ready-' . $i;
                    clearstatcache(true, $ready_path);
                    $ready_count += (int) is_file($ready_path);
                }
                if ($ready_count === $count) {
                    break;
                }
                usleep(20000);
            } while (hrtime(true) < $deadline);
            $this->assertSame($count, $ready_count, 'Subprocess startup timed out');
            $this->assertNotFalse(file_put_contents($start, 'go'));
            $deadline = hrtime(true) + 10000000000;
            foreach ($processes as $worker) {
                $process = $worker['process'];
                while (proc_get_status($process)['running'] && hrtime(true) < $deadline) {
                    usleep(20000);
                }
                $this->assertFalse(proc_get_status($process)['running'], 'Contention worker stalled');
                $output = stream_get_contents($worker['pipes'][1]);
                $error = stream_get_contents($worker['pipes'][2]);
                $this->assertMatchesRegularExpression('/^[01] [0-9]+\.[0-9]{6}\n$/', (string) $output, (string) $error);
                [$granted, $timestamp] = explode(' ', mb_trim((string) $output));
                if ($granted === '1') {
                    $success[] = (float) $timestamp;
                } else {
                    ++$denied;
                }
            }
            $this->assertGreaterThanOrEqual(1, count($success));
            $this->assertGreaterThan(0, $denied, 'Overloaded workers must fail closed');
            sort($success);
            for ($i = 1; $i < count($success); ++$i) {
                $this->assertGreaterThanOrEqual(0.85, $success[$i] - $success[$i - 1]);
            }
        } finally {
            foreach ($processes as $worker) {
                $process = $worker['process'];
                if (proc_get_status($process)['running']) {
                    @proc_terminate($process, 9);
                }
                foreach ($worker['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
            }
            for ($i = 0; $i < $count; ++$i) {
                @unlink($directory . '/ready-' . $i);
            }
            @unlink($start);
        }
    }

    /** Configured shared locks must be owner-writable but not writable by others. */
    public function testConfiguredSharedMutexRejectsGroupWritableModeAndAccepts0644(): void {
        $directory = $this->freshDirectory();
        $id = bin2hex(random_bytes(16));
        $this->writeNamespace($directory, $id);
        $this->assignEnvironment('CITOID_RATE_LIMIT_DIR', $directory);
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', $id);
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        $this->assertTrue(chmod($directory . '/rate.lock', 0666));
        $this->assertFalse(self::admit(null));
        $this->assertFileDoesNotExist($directory . '/rate.state');
        $this->assertTrue(chmod($directory . '/rate.lock', 0644));
        $this->assertTrue(self::admit(null, 2.0));
        $this->assertFileExists($directory . '/rate.state');
    }

    /** Absolute aliases must not masquerade as the provisioned shared path. */
    public function testConfiguredSharedDirectoryRejectsDotDotAlias(): void {
        $directory = $this->freshDirectory();
        $id = bin2hex(random_bytes(16));
        $this->writeNamespace($directory, $id);
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        $alias = $directory . '/../' . basename($directory);
        $this->assignEnvironment('CITOID_RATE_LIMIT_DIR', $alias);
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', $id);
        $this->assertFalse(self::admit(null));
        $this->assertFileDoesNotExist($directory . '/rate.state');
    }

    /** A symlinked directory alias must be refused before opening the mutex. */
    public function testConfiguredSharedDirectoryRejectsSymlinkAlias(): void {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('symlink')) {
            $this->markTestSkipped('POSIX symlinks required');
        }
        $directory = $this->freshDirectory();
        $id = bin2hex(random_bytes(16));
        $this->writeNamespace($directory, $id);
        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        $alias = $directory . '-alias';
        if (!@symlink($directory, $alias)) {
            $this->markTestSkipped('Cannot construct symlink alias');
        }
        try {
            $this->assignEnvironment('CITOID_RATE_LIMIT_DIR', $alias);
            $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', $id);
            $this->assertFalse(self::admit(null));
            $this->assertFileDoesNotExist($directory . '/rate.state');
        } finally {
            @unlink($alias);
        }
    }
}
