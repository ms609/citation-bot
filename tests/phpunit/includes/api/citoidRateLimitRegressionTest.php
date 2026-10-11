<?php

declare(strict_types=1);

/*
 * Test-first regressions for the Citoid rate limiter in PR #6120.
 * These use only isolated local files; no Citoid HTTP requests are sent.
 * The clock-rollback, missing legacy state and missing shared directory cases
 * deliberately fail until the follow-up production fixes are applied.
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
            foreach (['rate.state', 'rate.lock', 'rate.lock.replaced'] as $name) {
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
}
