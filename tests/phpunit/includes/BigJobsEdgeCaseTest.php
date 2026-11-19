<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Network-free edge cases for the per-user large-run lease in big_jobs.php.
 *
 * Filesystem failures are simulated with uopz so that the fail-closed branches
 * can be exercised without needing an unwritable or hostile /dev/shm. Every
 * scenario uses its own random user, so it never touches another user's lease.
 */
final class BigJobsEdgeCaseTest extends PHPUnit\Framework\TestCase {

    private string $user_id = '';
    /** @var array<string, mixed> */
    private array $saved_session = [];
    /** @var list<string> */
    private array $cleanup_paths = [];
    /** @var list<resource> */
    private array $open_handles = [];

    /** @var array<string> */
    private array $users = [];

    #[\Override]
    protected function setUp(): void {
        $this->saved_session = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];
        $this->user_id = 'edge-' . bin2hex(random_bytes(6));
        $_SESSION = ['citation_bot_user_id' => $this->user_id];
        $this->cleanup_paths = [
            big_jobs_name(),
            big_jobs_guard_name(),
            big_jobs_name() . '_kill_job',
        ];
    }

    #[\Override]
    protected function tearDown(): void {
        foreach ($this->open_handles as $handle) {
            if (is_resource($handle)) {
                @fclose($handle);
            }
        }
        $this->open_handles = [];
        foreach ($this->cleanup_paths as $path) {
            @unlink($path);
        }
        unset($GLOBALS['citation_bot_big_jobs_lock_file'], $GLOBALS['citation_bot_admission_buffer_level']);
        $this->cleanupAdditionalBigJobUsers();
        $_SESSION = $this->saved_session;
    }

    private function requireUopz(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('uopz is required to simulate filesystem failures');
        }
    }

    /** @return resource */
    private function openLease() {
        $handle = fopen(big_jobs_name(), 'x+');
        $this->assertIsResource($handle);
        $this->open_handles[] = $handle;
        return $handle;
    }

    /**
     * Run a callback that is expected to end the request through exit().
     *
     * @param callable(): void $call
     */
    private function endsTheRequest(callable $call): bool {
        $this->requireUopz();
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

    /**
     * Run a callback while the named internal function returns a fixed value.
     *
     * @param string $function
     * @param mixed $value
     * @param callable(): void $callback
     */
    private function withStub(string $function, mixed $value, callable $callback): void {
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return($function, $value);
        try {
            $callback();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return($function);
        }
    }

    // ---- state directory -------------------------------------------------------

    public function testStateDirectoryRefusesASymlink(): void {
        $this->requireUopz();
        $this->withStub('is_link', true, function (): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('must not be a symlink');
            big_jobs_state_directory();
        });
    }

    public function testStateDirectoryReportsWhenItCannotBeCreated(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('is_dir', false);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('mkdir', false);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Unable to create private large-job state directory.');
            big_jobs_state_directory();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('is_dir');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('mkdir');
        }
    }

    public function testStateDirectoryRefusesWhenItCannotBeInspected(): void {
        $this->requireUopz();
        $this->withStub('lstat', false, function (): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Large-job state directory is unsafe.');
            big_jobs_state_directory();
        });
    }

    /** @return array<string, array{int}> */
    public static function foreignOwnerProvider(): array {
        return [
            'owned by another user' => [1],
            'owner cannot be determined' => [0],
        ];
    }

    #[DataProvider('foreignOwnerProvider')]
    public function testStateDirectoryRefusesAForeignOwner(int $offset): void {
        $this->requireUopz();
        if (!function_exists('posix_geteuid')) {
            $this->markTestSkipped('POSIX ownership checks are unavailable');
        }
        $owner = $offset === 0 ? false : posix_geteuid() + $offset;
        $this->withStub('fileowner', $owner, function (): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Large-job state directory has an unexpected owner.');
            big_jobs_state_directory();
        });
    }

    // ---- names -----------------------------------------------------------------

    /** @return array<string, array{mixed}> */
    public static function userIdProvider(): array {
        return [
            'empty' => [''],
            'plain' => ['alice'],
            'quote characters' => ["o'brien\"x"],
            'slashes' => ['a/b/c'],
            'padding needed' => ['ab'],
            'multibyte' => ["Zo\u{00eb} \u{4e2d}\u{6587}"],
            'path traversal' => ['../../etc/passwd'],
            'integer id' => [42],
        ];
    }

    #[DataProvider('userIdProvider')]
    public function testLeaseNamesNeverEscapeTheStateDirectory(mixed $user): void {
        $_SESSION = ['citation_bot_user_id' => $user];
        $name = big_jobs_name();
        $this->assertSame(big_jobs_state_directory(), dirname($name));
        $this->assertMatchesRegularExpression('~^[A-Za-z0-9+_]*_1$~', basename($name));
        $this->assertSame($name . '_guard', big_jobs_guard_name());
    }

    public function testDifferentUsersGetDifferentLeases(): void {
        $_SESSION = ['citation_bot_user_id' => 'alice'];
        $alice = big_jobs_name();
        $_SESSION = ['citation_bot_user_id' => 'bob'];
        $bob = big_jobs_name();
        $this->assertNotSame($alice, $bob);
    }

    // ---- guard -----------------------------------------------------------------

    public function testGuardIsCreatedPrivateAndReusable(): void {
        $guard = big_jobs_open_guard();
        $this->assertIsResource($guard);
        $this->open_handles[] = $guard;
        $this->assertSame(0600, fileperms(big_jobs_guard_name()) & 0777);
        $second = big_jobs_open_guard();
        $this->assertIsResource($second);
        $this->open_handles[] = $second;
    }

    public function testSymlinkedGuardIsRefused(): void {
        $target = tempnam(sys_get_temp_dir(), 'cb-guard-target');
        $this->assertIsString($target);
        $this->cleanup_paths[] = $target;
        $this->assertTrue(symlink($target, big_jobs_guard_name()));
        $this->assertFalse(big_jobs_open_guard());
    }

    public function testGuardThatCannotBeOpenedIsRefused(): void {
        $this->requireUopz();
        $this->withStub('fopen', false, function (): void {
            $this->assertFalse(big_jobs_open_guard());
        });
    }

    public function testGuardThatCannotBeInspectedIsRefused(): void {
        $this->requireUopz();
        $this->withStub('fstat', false, function (): void {
            $this->assertFalse(big_jobs_open_guard());
        });
    }

    public function testGuardWhosePathWasSwappedIsRefused(): void {
        $this->requireUopz();
        $directory_stat = lstat(big_jobs_state_directory());
        $this->assertIsArray($directory_stat);
        $calls = 0;
        // The first lstat() belongs to the state-directory check, the second to the guard path.
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('lstat', static function () use (&$calls, $directory_stat): array {
            $calls++;
            if ($calls === 1) {
                return $directory_stat;
            }
            return ['mode' => 0100600, 'dev' => PHP_INT_MAX, 'ino' => PHP_INT_MAX];
        }, true);
        try {
            $this->assertFalse(big_jobs_open_guard());
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('lstat');
        }
        $this->assertGreaterThanOrEqual(2, $calls);
    }

    public function testGuardThatIsNotARegularFileIsRefused(): void {
        $this->requireUopz();
        $directory_stat = lstat(big_jobs_state_directory());
        $this->assertIsArray($directory_stat);
        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('lstat', static function () use (&$calls, $directory_stat): array {
            $calls++;
            if ($calls === 1) {
                return $directory_stat;
            }
            return ['mode' => 0040700, 'dev' => 1, 'ino' => 1];
        }, true);
        try {
            $this->assertFalse(big_jobs_open_guard());
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('lstat');
        }
    }

    public function testGuardOwnedByAnotherUserIsRefused(): void {
        $this->requireUopz();
        if (!function_exists('posix_geteuid')) {
            $this->markTestSkipped('POSIX ownership checks are unavailable');
        }
        $calls = 0;
        $effective_user = posix_geteuid();
        // The first fileowner() belongs to the state-directory check, the second to the guard.
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('fileowner', static function () use (&$calls, $effective_user): int {
            $calls++;
            return $calls === 1 ? $effective_user : $effective_user + 1;
        }, true);
        try {
            $this->assertFalse(big_jobs_open_guard());
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('fileowner');
        }
        $this->assertGreaterThanOrEqual(2, $calls);
    }

    // ---- inode ownership -------------------------------------------------------

    public function testOwnsPathOnlyWhileTheSameInodeIsAtThePath(): void {
        $path = big_jobs_name();
        $handle = $this->openLease();
        $this->assertTrue(big_jobs_owns_path($handle, $path));

        $this->assertTrue(unlink($path));
        $this->assertFalse(big_jobs_owns_path($handle, $path), 'Removed path');

        $this->assertNotFalse(file_put_contents($path, 'replacement'));
        $this->assertFalse(big_jobs_owns_path($handle, $path), 'Replaced by a different inode');
    }

    public function testOwnsPathRefusesSymlinksAndDirectories(): void {
        $path = big_jobs_name();
        $handle = $this->openLease();
        $this->assertTrue(unlink($path));

        $this->assertTrue(symlink(big_jobs_guard_name(), $path));
        $this->assertFalse(big_jobs_owns_path($handle, $path), 'A symlink never proves ownership');
        $this->assertTrue(unlink($path));

        $this->assertTrue(mkdir($path, 0700));
        $this->assertFalse(big_jobs_owns_path($handle, $path), 'A directory never proves ownership');
        $this->assertTrue(rmdir($path));
    }

    public function testOwnsPathFailsClosedWhenTheHandleCannotBeInspected(): void {
        $this->requireUopz();
        $path = big_jobs_name();
        $handle = $this->openLease();
        $this->withStub('fstat', false, function () use ($handle, $path): void {
            $this->assertFalse(big_jobs_owns_path($handle, $path));
        });
    }

    // ---- terminal page ---------------------------------------------------------

    public function testStopPageEscapesTheMessageAndEndsTheRequest(): void {
        $level = ob_get_level();
        ob_start();
        try {
            $stopped = $this->endsTheRequest(static function (): void {
                big_jobs_stop_page('Tom & "Jerry" <b>\'s run');
            });
        } finally {
            $output = (string) ob_get_clean();
        }
        $this->assertTrue($stopped, 'A terminal message must end the request');
        $this->assertSame($level, ob_get_level());
        $this->assertStringContainsString('<h1>Tom &amp; &quot;Jerry&quot; &lt;b&gt;&apos;s run</h1>', $output);
        $this->assertStringNotContainsString('<b>', $output);
    }

    public function testStopPageFlushesAnAdmissionBufferThatIsStillOpen(): void {
        $level = ob_get_level();
        ob_start(); // outer capture
        ob_start(); // admission buffer
        $GLOBALS['citation_bot_admission_buffer_level'] = ob_get_level();
        try {
            $stopped = $this->endsTheRequest(static function (): void {
                big_jobs_stop_page('buffered');
            });
            $this->assertSame($level + 1, ob_get_level(), 'The admission buffer was flushed into the outer capture');
        } finally {
            $output = (string) ob_get_clean();
        }
        $this->assertTrue($stopped, 'A terminal message must end the request');
        $this->assertStringContainsString('buffered', $output);
        $this->assertArrayNotHasKey('citation_bot_admission_buffer_level', $GLOBALS);
    }

    // ---- shutdown cleanup ------------------------------------------------------

    public function testShutdownRemovesOnlyALeaseTheRequestStillOwns(): void {
        $path = big_jobs_name();
        $handle = $this->openLease();
        $GLOBALS['citation_bot_big_jobs_lock_file'] = $handle;

        big_jobs_we_died($handle);

        $this->assertFileDoesNotExist($path);
        $this->assertArrayNotHasKey('citation_bot_big_jobs_lock_file', $GLOBALS);
        $this->assertFalse(is_resource($handle), 'The lease handle is closed');
    }

    public function testShutdownLeavesAReplacementLeaseAlone(): void {
        $path = big_jobs_name();
        $handle = $this->openLease();
        $this->assertTrue(unlink($path));
        $this->assertNotFalse(file_put_contents($path, 'newer request'));

        big_jobs_we_died($handle);

        $this->assertFileExists($path);
        $this->assertSame('newer request', file_get_contents($path));
    }

    public function testShutdownKeepsTheGlobalWhenItBelongsToAnotherHandle(): void {
        $path = big_jobs_name();
        $handle = $this->openLease();
        $other = fopen('php://memory', 'r+');
        $this->assertIsResource($other);
        $this->open_handles[] = $other;
        $GLOBALS['citation_bot_big_jobs_lock_file'] = $other;

        big_jobs_we_died($handle);

        $this->assertSame($other, $GLOBALS['citation_bot_big_jobs_lock_file'], 'A global that names another handle is not ours to clear');
        $this->assertFileDoesNotExist($path, 'The pathname is still removed because this handle owns it');
        $this->assertFalse(is_resource($handle));
    }

    public function testShutdownStillClosesTheHandleWhenTheGuardIsUnavailable(): void {
        $target = tempnam(sys_get_temp_dir(), 'cb-guard-target');
        $this->assertIsString($target);
        $this->cleanup_paths[] = $target;
        $this->assertTrue(symlink($target, big_jobs_guard_name()));
        $path = big_jobs_name();
        $handle = $this->openLease();

        big_jobs_we_died($handle);

        $this->assertFalse(is_resource($handle));
        $this->assertFileExists($path, 'Without the guard nothing is unlinked');
    }

    public function testShutdownDoesNotUnlinkWhenTheGuardCannotBeLocked(): void {
        $this->requireUopz();
        $path = big_jobs_name();
        $handle = $this->openLease();
        $this->withStub('flock', false, function () use ($handle): void {
            big_jobs_we_died($handle);
        });
        $this->assertFileExists($path);
        $this->assertFalse(is_resource($handle));
    }

    // ---- kill requests ---------------------------------------------------------

    public function testKillIsRefusedWhenTheGuardIsUnavailable(): void {
        $target = tempnam(sys_get_temp_dir(), 'cb-guard-target');
        $this->assertIsString($target);
        $this->cleanup_paths[] = $target;
        $this->assertTrue(symlink($target, big_jobs_guard_name()));
        $this->openLease();
        $this->assertFalse(big_jobs_kill());
        $this->assertFileDoesNotExist(big_jobs_name() . '_kill_job');
    }

    public function testKillIsRefusedWhenTheGuardCannotBeLocked(): void {
        $this->requireUopz();
        $this->openLease();
        $this->withStub('flock', false, function (): void {
            $this->assertFalse(big_jobs_kill());
        });
        $this->assertFileDoesNotExist(big_jobs_name() . '_kill_job');
    }

    // ---- no-ops outside large web runs -----------------------------------------

    public function testHeartbeatHelpersDoNothingOutsideLargeWebRuns(): void {
        // HTML_OUTPUT is false under PHPUnit, so nothing may touch the filesystem.
        $this->assertTrue(big_jobs_heartbeat());
        big_jobs_maybe_heartbeat();
        big_jobs_check_killed();
        big_jobs_check_overused(500);
        big_jobs_acquire_large_run();
        $this->assertFileDoesNotExist(big_jobs_name());
        $this->assertFileDoesNotExist(big_jobs_guard_name());
        $this->assertFalse(defined('BIG_JOB_MODE'));
    }

    // ---- separate-process scenarios (HTML output, large-run mode) --------------

    /**
     * Put this process into large-web-run mode with a real lease on disk.
     *
     * @return resource
     */
    private function enterLargeRunMode(bool $with_lease_global = true) {
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        define('BIG_JOB_MODE', 'YES');
        $handle = $this->openLease();
        if ($with_lease_global) {
            $GLOBALS['citation_bot_big_jobs_lock_file'] = $handle;
        }
        return $handle;
    }

    /** Make exit() throw so that terminal pages can be observed. */
    private function stubExit(RuntimeException $ended): void {
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('exit', static function () use ($ended): never {
            throw $ended;
        }, true);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatRefreshesTheLeaseWithTheGivenTime(): void {
        $this->requireUopz();
        $this->enterLargeRunMode();
        $path = big_jobs_name();

        $this->assertTrue(big_jobs_heartbeat(1_700_000_000));
        clearstatcache(true, $path);
        $this->assertSame(1_700_000_000, filemtime($path));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatWithANegativeTimeDoesNotTouchTheLease(): void {
        $this->requireUopz();
        $this->enterLargeRunMode();
        $path = big_jobs_name();
        $this->assertTrue(touch($path, 1_600_000_000, 1_600_000_000));

        $this->assertFalse(big_jobs_heartbeat(-5));
        clearstatcache(true, $path);
        $this->assertSame(1_600_000_000, filemtime($path));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatReportsFailureWhenTheGuardIsUnavailable(): void {
        $this->requireUopz();
        $this->enterLargeRunMode();
        $path = big_jobs_name();
        $this->assertTrue(touch($path, 1_600_000_000, 1_600_000_000));
        $target = tempnam(sys_get_temp_dir(), 'cb-guard-target');
        $this->assertIsString($target);
        $this->cleanup_paths[] = $target;
        $this->assertTrue(symlink($target, big_jobs_guard_name()));

        $this->assertFalse(big_jobs_heartbeat(1_700_000_000));
        clearstatcache(true, $path);
        $this->assertSame(1_600_000_000, filemtime($path), 'An unavailable guard must not refresh the lease');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatReportsFailureWhenTheGuardCannotBeLocked(): void {
        $this->requireUopz();
        $this->enterLargeRunMode();
        $this->withStub('flock', false, function (): void {
            $this->assertFalse(big_jobs_heartbeat(1_700_000_000));
        });
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatWithoutAnOpenLeaseStopsTheRequest(): void {
        $this->requireUopz();
        $this->enterLargeRunMode(false);
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);
        ob_start();
        try {
            big_jobs_heartbeat();
            $this->fail('A request without a lease handle must stop');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            $output = (string) ob_get_clean();
        }
        $this->assertStringContainsString('Large run lock ownership was lost. Stopping this run.', $output);
        $this->assertStringContainsString('Edit another page', $output, 'The page footer is rendered');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatStopsARequestWhoseLeaseWasReplaced(): void {
        $this->requireUopz();
        $this->enterLargeRunMode();
        $path = big_jobs_name();
        $this->assertTrue(unlink($path));
        $this->assertNotFalse(file_put_contents($path, 'newer request'));
        $this->assertTrue(touch($path, 1_600_000_000, 1_600_000_000));
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);
        ob_start();
        try {
            big_jobs_heartbeat(1_700_000_000);
            $this->fail('A request that lost its lease must stop');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            $output = (string) ob_get_clean();
        }
        $this->assertStringContainsString('Large run lock ownership was lost', $output);
        clearstatcache(true, $path);
        $this->assertSame(1_600_000_000, filemtime($path), 'The replacement lease must not be refreshed');
        $this->assertSame('newer request', file_get_contents($path));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatHonoursAKillRequestExactlyOnce(): void {
        $this->requireUopz();
        $this->enterLargeRunMode();
        $this->assertTrue(big_jobs_kill());
        $kill_file = big_jobs_name() . '_kill_job';
        $this->assertFileExists($kill_file);
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);
        ob_start();
        try {
            big_jobs_check_killed();
            $this->fail('A killed run must stop');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            $output = (string) ob_get_clean();
        }
        $this->assertStringContainsString('Run killed as requested.', $output);
        $this->assertFileDoesNotExist($kill_file, 'The kill flag is consumed');
        $this->assertTrue(big_jobs_heartbeat(1_700_000_000), 'A later heartbeat is unaffected by the consumed flag');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPeriodicHeartbeatIsRateLimited(): void {
        $this->requireUopz();
        $this->enterLargeRunMode();
        $path = big_jobs_name();
        $start = 1_700_000_000;

        big_jobs_maybe_heartbeat($start);
        clearstatcache(true, $path);
        $this->assertSame($start, filemtime($path), 'The first hook always renews');

        big_jobs_maybe_heartbeat($start + BIG_JOBS_HEARTBEAT_INTERVAL_SECONDS - 1);
        clearstatcache(true, $path);
        $this->assertSame($start, filemtime($path), 'Too soon: the filesystem is not touched');

        big_jobs_maybe_heartbeat($start + BIG_JOBS_HEARTBEAT_INTERVAL_SECONDS);
        clearstatcache(true, $path);
        $this->assertSame($start + BIG_JOBS_HEARTBEAT_INTERVAL_SECONDS, filemtime($path));

        big_jobs_maybe_heartbeat($start);
        clearstatcache(true, $path);
        $this->assertSame($start, filemtime($path), 'A clock that went backwards renews immediately');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFailedPeriodicHeartbeatIsRetriedOnTheNextHook(): void {
        $this->requireUopz();
        $this->enterLargeRunMode();
        $path = big_jobs_name();
        $start = 1_700_000_000;
        $this->assertTrue(touch($path, 1_600_000_000, 1_600_000_000));

        $this->withStub('flock', false, function () use ($start): void {
            big_jobs_maybe_heartbeat($start);
        });
        clearstatcache(true, $path);
        $this->assertSame(1_600_000_000, filemtime($path), 'The failed attempt renewed nothing');

        big_jobs_maybe_heartbeat($start + 1);
        clearstatcache(true, $path);
        $this->assertSame($start + 1, filemtime($path), 'A failure does not start the quiet period');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAcquiringWhileAlreadyInLargeRunModeChangesNothing(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        define('BIG_JOB_MODE', 'YES');

        big_jobs_acquire_large_run();

        $this->assertFileDoesNotExist(big_jobs_name());
        $this->assertFileDoesNotExist(big_jobs_guard_name());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSecondLargeRunIsBlockedByAFreshLease(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $path = big_jobs_name();
        $this->assertNotFalse(file_put_contents($path, 'first run'));
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);
        ob_start();
        try {
            big_jobs_acquire_large_run();
            $this->fail('A second large run must be refused');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            $output = (string) ob_get_clean();
        }
        $this->assertStringContainsString('Run blocked by your existing big run.', $output);
        $this->assertSame('first run', file_get_contents($path), 'The existing lease is untouched');
        $this->assertFalse(defined('BIG_JOB_MODE'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLargeRunIsRefusedWhenTheGuardIsUnavailable(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $target = tempnam(sys_get_temp_dir(), 'cb-guard-target');
        $this->assertIsString($target);
        $this->cleanup_paths[] = $target;
        $this->assertTrue(symlink($target, big_jobs_guard_name()));
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);
        ob_start();
        try {
            big_jobs_acquire_large_run();
            $this->fail('A large run without a guard must be refused');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            $output = (string) ob_get_clean();
        }
        $this->assertStringContainsString('Unable to obtain large run guard.', $output);
        $this->assertFileDoesNotExist(big_jobs_name());
        $this->assertFalse(defined('BIG_JOB_MODE'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLargeRunIsRefusedWhenTheGuardCannotBeLocked(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('flock', false);
        ob_start();
        try {
            big_jobs_acquire_large_run();
            $this->fail('A large run that cannot lock its guard must be refused');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            $output = (string) ob_get_clean();
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('flock');
        }
        $this->assertStringContainsString('Unable to obtain large run guard.', $output);
        $this->assertFileDoesNotExist(big_jobs_name());
        $this->assertFalse(defined('BIG_JOB_MODE'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLargeRunIsRefusedWhenTheLeaseFileCannotBeCreated(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $path = big_jobs_name();
        // The lease exists and is fresh, but the check is made to believe it does not,
        // so creation with exclusive mode fails.
        $this->assertNotFalse(file_put_contents($path, 'racing request'));
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('file_exists', false);
        ob_start();
        try {
            big_jobs_acquire_large_run();
            $this->fail('A lease that cannot be created must stop the request');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            $output = (string) ob_get_clean();
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('file_exists');
        }
        $this->assertStringContainsString('Unable to obtain large run lock.', $output);
        $this->assertSame('racing request', file_get_contents($path));
        $this->assertFalse(defined('BIG_JOB_MODE'));
    }

    // ---- process-level and multi-user lease regressions ----

    private function cleanupAdditionalBigJobUsers(): void {
        foreach ($this->users as $user) {
            foreach ($this->pathsFor($user) as $path) {
                if (is_link($path) || is_file($path)) {
                    @unlink($path);
                } elseif (is_dir($path)) {
                    @rmdir($path);
                }
            }
        }
        $this->users = [];
        unset($_SESSION['citation_bot_user_id'], $GLOBALS['citation_bot_big_jobs_lock_file']);
    }

    private function newUser(): string {
        $user = 'edge-' . bin2hex(random_bytes(6));
        $this->users[] = $user;
        $_SESSION['citation_bot_user_id'] = $user;
        return $user;
    }

    /** @return array{lock: string, kill: string, guard: string} */
    private function pathsFor(string $user): array {
        $previous = $_SESSION['citation_bot_user_id'] ?? null;
        $_SESSION['citation_bot_user_id'] = $user;
        $lock = big_jobs_name();
        if ($previous === null) {
            unset($_SESSION['citation_bot_user_id']);
        } else {
            $_SESSION['citation_bot_user_id'] = $previous;
        }
        return ['lock' => $lock, 'kill' => $lock . '_kill_job', 'guard' => $lock . '_guard'];
    }

    private function danglingTarget(): string {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'citation-bot-missing-' . bin2hex(random_bytes(6));
    }

    /**
     * Run $body in a fresh process with HTML output enabled and big_jobs.php loaded.
     *
     * @return array{int, string} exit code and combined output
     */
    private function runBigJobsScript(string $user, string $body): array {
        if (!function_exists('exec')) {
            $this->markTestSkipped('exec() is unavailable');
        }
        $big_jobs = realpath(dirname(__DIR__, 3) . '/src/includes/big_jobs.php');
        $this->assertNotFalse($big_jobs);

        $script = "<?php\ndeclare(strict_types=1);\n";
        $script .= "define('HTML_OUTPUT', true);\n";
        $script .= "function bot_html_footer(): void { echo '[FOOTER]'; }\n";
        $script .= "function bot_admission_buffer_flush(): void { echo '[FLUSHED]'; }\n";
        $script .= 'require_once ' . var_export($big_jobs, true) . ";\n";
        $script .= '$_SESSION = [\'citation_bot_user_id\' => ' . var_export($user, true) . "];\n";
        $script .= $body . "\n";

        $file = tempnam(sys_get_temp_dir(), 'bjob');
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

    // ---- stop-page outcomes when acquiring a lease ---------------------------

    public function testSymlinkedGuardStopsAcquisitionWithoutCreatingALease(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);
        $this->assertTrue(symlink($this->danglingTarget(), $paths['guard']));

        [$exit_code, $output] = $this->runBigJobsScript(
            $user,
            'big_jobs_acquire_large_run(); echo "[UNREACHABLE]";'
        );

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('<h1>Unable to obtain large run guard.</h1>', $output);
        $this->assertStringContainsString('[FOOTER][FLUSHED]', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
        $this->assertFileDoesNotExist($paths['lock']);
        $this->assertTrue(is_link($paths['guard']), 'The foreign symlink must be left alone');
    }

    public function testFreshExistingLeaseBlocksASecondRunWithoutBeingStolen(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);
        $_SESSION['citation_bot_user_id'] = $user;
        hard_touch($paths['lock']);
        $mtime = time() - 30;
        $this->assertTrue(touch($paths['lock'], $mtime, $mtime));
        clearstatcache(true, $paths['lock']);

        [$exit_code, $output] = $this->runBigJobsScript(
            $user,
            'big_jobs_acquire_large_run(); echo "[UNREACHABLE]";'
        );

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('<h1>Run blocked by your existing big run.</h1>', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
        clearstatcache(true, $paths['lock']);
        $this->assertFileExists($paths['lock']);
        $this->assertSame($mtime, filemtime($paths['lock']), 'A blocked contender must not refresh the owner\'s lease');
    }

    public function testUncreatableLeasePathReportsLockFailure(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);
        // PHP resolves a dangling symlink when creating a file, so point it into a
        // directory that does not exist: the lease file can then never be created.
        $unreachable = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'citation-bot-missing-dir-' .
            bin2hex(random_bytes(6)) . DIRECTORY_SEPARATOR . 'lease';
        $this->assertTrue(symlink($unreachable, $paths['lock']));
        $this->assertFalse(file_exists($paths['lock']));

        [$exit_code, $output] = $this->runBigJobsScript(
            $user,
            'big_jobs_acquire_large_run(); echo "[UNREACHABLE]";'
        );

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('<h1>Unable to obtain large run lock.</h1>', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    public function testStopPageEscapesItsMessage(): void {
        $user = $this->newUser();

        [$exit_code, $output] = $this->runBigJobsScript(
            $user,
            'big_jobs_stop_page(\'<script>alert("x")</script> & \\\'more\\\'\'); echo "[UNREACHABLE]";'
        );

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString(
            '<h1>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &apos;more&apos;</h1>',
            $output
        );
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    // ---- heartbeat outcomes --------------------------------------------------

    public function testHeartbeatWithoutALeaseHandleStopsTheRun(): void {
        $user = $this->newUser();
        foreach (['big_jobs_heartbeat();', 'big_jobs_check_killed();', 'big_jobs_maybe_heartbeat(1000);'] as $call) {
            [$exit_code, $output] = $this->runBigJobsScript(
                $user,
                "define('BIG_JOB_MODE', 'YES'); " . $call . ' echo "[UNREACHABLE]";'
            );

            $this->assertSame(0, $exit_code, $call);
            $this->assertStringContainsString(
                '<h1>Large run lock ownership was lost. Stopping this run.</h1>',
                $output,
                $call
            );
            $this->assertStringNotContainsString('UNREACHABLE', $output, $call);
        }
    }

    public function testKillFlagStopsTheRunIsConsumedAndLeaseIsReleased(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);

        [$exit_code, $output] = $this->runBigJobsScript(
            $user,
            <<<'PHP'
big_jobs_acquire_large_run();
$kill = big_jobs_name() . '_kill_job';
hard_touch($kill);
echo file_exists($kill) ? '[FLAG]' : '[NOFLAG]';
big_jobs_heartbeat();
echo '[UNREACHABLE]';
PHP
        );

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('[FLAG]', $output);
        $this->assertStringContainsString('<h1>Run killed as requested.</h1>', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
        clearstatcache(true, $paths['kill']);
        clearstatcache(true, $paths['lock']);
        $this->assertFileDoesNotExist($paths['kill'], 'The kill flag is consumed');
        $this->assertFileDoesNotExist($paths['lock'], 'Shutdown cleanup releases the lease');
    }

    public function testReplacedLeaseIsDetectedAsOwnershipLossAndNeverDeletedByTheOldOwner(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);

        [$exit_code, $output] = $this->runBigJobsScript(
            $user,
            <<<'PHP'
big_jobs_acquire_large_run();
$lease = big_jobs_name();
unlink($lease);
hard_touch($lease); // A different inode now owns the pathname
big_jobs_heartbeat();
echo '[UNREACHABLE]';
PHP
        );

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString(
            '<h1>Large run lock ownership was lost. Stopping this run.</h1>',
            $output
        );
        $this->assertStringNotContainsString('UNREACHABLE', $output);
        clearstatcache(true, $paths['lock']);
        $this->assertFileExists(
            $paths['lock'],
            'The stale owner\'s shutdown cleanup must not delete a replacement lease'
        );
    }

    // ---- shutdown cleanup ----------------------------------------------------

    public function testLeaseIsReleasedWhenARunEndsNormally(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);

        [$exit_code, $output] = $this->runBigJobsScript(
            $user,
            'big_jobs_acquire_large_run(); echo big_jobs_name();'
        );

        $this->assertSame(0, $exit_code);
        $this->assertSame($paths['lock'], mb_trim($output));
        clearstatcache(true, $paths['lock']);
        $this->assertFileDoesNotExist($paths['lock']);
        $this->assertFileExists($paths['guard'], 'The permanent guard inode is never deleted by the application');
    }

    public function testNewRunCanStartAfterTheLeaseWasReleased(): void {
        $user = $this->newUser();

        [, $first] = $this->runBigJobsScript(
            $user,
            'big_jobs_acquire_large_run(); echo "[FIRST-OK]";'
        );
        [, $second] = $this->runBigJobsScript(
            $user,
            'big_jobs_acquire_large_run(); echo "[SECOND-OK]";'
        );

        $this->assertStringContainsString('[FIRST-OK]', $first);
        $this->assertStringContainsString('[SECOND-OK]', $second);
        $this->assertStringNotContainsString('blocked', $second);
    }

    // ---- in-process: behavior when large-job mode is inactive ----------------

    public function testNothingHappensOutsideHtmlOutputOrBigJobMode(): void {
        $this->assertFalse(HTML_OUTPUT);
        $this->assertFalse(defined('BIG_JOB_MODE'));
        $user = $this->newUser();
        $paths = $this->pathsFor($user);

        $this->assertTrue(big_jobs_heartbeat());
        big_jobs_maybe_heartbeat();
        big_jobs_check_killed();
        big_jobs_acquire_large_run();
        big_jobs_check_overused(5);
        big_jobs_check_overused(500);

        $this->assertFalse(defined('BIG_JOB_MODE'));
        $this->assertFileDoesNotExist($paths['lock']);
        $this->assertNull(big_jobs_current_lock_file());
    }

    public function testCurrentLockFileOnlyReturnsOpenResources(): void {
        unset($GLOBALS['citation_bot_big_jobs_lock_file']);
        $this->assertNull(big_jobs_current_lock_file());

        $GLOBALS['citation_bot_big_jobs_lock_file'] = 'not a resource';
        $this->assertNull(big_jobs_current_lock_file());

        $handle = fopen('php://memory', 'r+');
        $this->assertIsResource($handle);
        $GLOBALS['citation_bot_big_jobs_lock_file'] = $handle;
        $this->assertSame($handle, big_jobs_current_lock_file());

        fclose($handle);
        $this->assertNull(big_jobs_current_lock_file(), 'A closed handle is no longer a resource');

        // An open filesystem lease must satisfy the same resource contract.
        $lease = $this->openLease();
        $GLOBALS['citation_bot_big_jobs_lock_file'] = $lease;
        $this->assertSame($lease, big_jobs_current_lock_file());
        fclose($lease);
        $this->assertNull(big_jobs_current_lock_file(), 'A closed lease is no longer a resource');
    }

    // ---- in-process: state directory and naming ------------------------------

    public function testStateDirectoryIsPrivateOwnedAndStable(): void {
        $directory = big_jobs_state_directory();
        clearstatcache(true, $directory); // chmod() does not clear PHP's stat cache

        $this->assertDirectoryExists($directory);
        $this->assertDirectoryIsWritable($directory);
        $this->assertFalse(is_link($directory));
        $this->assertSame(BIG_JOBS_FALLBACK_STATE_DIRECTORY, basename($directory));
        $this->assertSame('citation-bot-big-jobs', basename($directory));
        $this->assertSame(0700, fileperms($directory) & 0777);
        if (function_exists('posix_geteuid')) {
            $this->assertSame(posix_geteuid(), fileowner($directory));
        }
        $this->assertSame($directory, big_jobs_state_directory());
    }

    public function testNamesAreConfinedToTheStateDirectoryForHostileUserIds(): void {
        $directory = big_jobs_state_directory();
        $seen = [];
        $ids = [
            '../../etc/passwd', "a\0b", "line\nbreak", '"quoted"', "it's", 'a/b+c==', 'üñïçødé',
            str_repeat('x', 500), '0', '..', '.', 'a b',
        ];
        foreach ($ids as $id) {
            $_SESSION['citation_bot_user_id'] = $id;
            $name = big_jobs_name();

            $this->assertSame($directory, dirname($name), 'user id: ' . bin2hex($id));
            $base = basename($name);
            $this->assertMatchesRegularExpression('~^[A-Za-z0-9+_]*_1$~', $base, 'user id: ' . bin2hex($id));
            $this->assertStringEndsWith('_1', $base);
            $this->assertArrayNotHasKey($name, $seen, 'Distinct users must get distinct lease names');
            $seen[$name] = true;
            $this->assertSame($name . '_guard', big_jobs_guard_name());
        }
    }

    public function testSameUserAlwaysGetsTheSameName(): void {
        $_SESSION['citation_bot_user_id'] = 'repeatable-user';
        $first = big_jobs_name();
        $_SESSION['citation_bot_user_id'] = 'repeatable-user';
        $this->assertSame($first, big_jobs_name());
        $_SESSION['citation_bot_user_id'] = 'another-user';
        $this->assertNotSame($first, big_jobs_name());
    }

    public function testMissingUserIdStillYieldsAWellFormedName(): void {
        unset($_SESSION['citation_bot_user_id']);
        $name = big_jobs_name();
        $this->assertSame(big_jobs_state_directory(), dirname($name));
        $this->assertSame('_1', basename($name));
    }

    // ---- in-process: low-level file helpers ----------------------------------

    public function testHardTouchCreatesAndRefreshesFilesAndIgnoresImpossiblePaths(): void {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'citation-bot-hard-touch-' . bin2hex(random_bytes(6));
        $this->assertTrue(mkdir($directory, 0700));
        $file = $directory . DIRECTORY_SEPARATOR . 'flag';
        try {
            $this->assertFileDoesNotExist($file);
            hard_touch($file);
            $this->assertFileExists($file);

            $this->assertTrue(touch($file, 1234, 1234));
            clearstatcache(true, $file);
            hard_touch($file);
            clearstatcache(true, $file);
            $this->assertGreaterThan(1234, filemtime($file));

            // A path whose parent does not exist is silently ignored.
            $impossible = $directory . DIRECTORY_SEPARATOR . 'missing' . DIRECTORY_SEPARATOR . 'flag';
            hard_touch($impossible);
            $this->assertFileDoesNotExist($impossible);
        } finally {
            @unlink($file);
            @rmdir($directory);
        }
    }

    public function testOwnsPathRequiresTheSameRegularFileInode(): void {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'citation-bot-owns-path-' . bin2hex(random_bytes(6));
        $this->assertTrue(mkdir($directory, 0700));
        $path = $directory . DIRECTORY_SEPARATOR . 'lease';
        $link = $directory . DIRECTORY_SEPARATOR . 'link';
        $handle = false;
        try {
            hard_touch($path);
            $handle = fopen($path, 'r+');
            $this->assertIsResource($handle);

            $this->assertTrue(big_jobs_owns_path($handle, $path));

            $this->assertTrue(symlink($path, $link));
            $this->assertFalse(big_jobs_owns_path($handle, $link), 'A symlink is never the held inode');
            unlink($link);

            $this->assertFalse(big_jobs_owns_path($handle, $directory), 'A directory is not a lease file');

            unlink($path);
            $this->assertFalse(big_jobs_owns_path($handle, $path), 'A missing path is not owned');

            hard_touch($path);
            $this->assertFalse(big_jobs_owns_path($handle, $path), 'A replacement inode is not owned');
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($link);
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function testOpenGuardCreatesPrivateFileAndRejectsLinksAndDirectories(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);

        $guard = big_jobs_open_guard();
        $this->assertIsResource($guard);
        fclose($guard);
        clearstatcache(true, $paths['guard']); // chmod() does not clear PHP's stat cache
        $this->assertFileExists($paths['guard']);
        $this->assertSame(0600, fileperms($paths['guard']) & 0777);

        unlink($paths['guard']);
        $this->assertTrue(symlink($this->danglingTarget(), $paths['guard']));
        $this->assertFalse(big_jobs_open_guard());
        unlink($paths['guard']);

        $this->assertTrue(mkdir($paths['guard'], 0700));
        $this->assertFalse(big_jobs_open_guard());
    }

    // ---- in-process: kill requests -------------------------------------------

    public function testKillRequestWithoutALeaseReportsNothingToKill(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);

        $this->assertFalse(big_jobs_kill());
        $this->assertFileDoesNotExist($paths['kill']);
    }

    public function testKillRequestFlagsAnExistingLeaseWithoutRemovingIt(): void {
        $this->openLease();
        $this->assertTrue(big_jobs_kill(), 'An open lease may be killed');
        $this->assertFileExists(big_jobs_name() . '_kill_job');

        $user = $this->newUser();
        $paths = $this->pathsFor($user);
        hard_touch($paths['lock']);

        $this->assertTrue(big_jobs_kill());
        clearstatcache(true, $paths['kill']);
        $this->assertFileExists($paths['kill']);
        $this->assertFileExists($paths['lock']);

        // Asking twice is harmless.
        $this->assertTrue(big_jobs_kill());
        $this->assertFileExists($paths['kill']);
    }

    public function testKillRequestFailsWhenTheGuardCannotBeOpened(): void {
        $user = $this->newUser();
        $paths = $this->pathsFor($user);
        hard_touch($paths['lock']);
        $this->assertTrue(symlink($this->danglingTarget(), $paths['guard']));

        $this->assertFalse(big_jobs_kill());
        $this->assertFileDoesNotExist($paths['kill']);
    }

    public function testKillRequestsAreScopedToTheRequestingUser(): void {
        $owner = $this->newUser();
        $owner_paths = $this->pathsFor($owner);
        hard_touch($owner_paths['lock']);

        $bystander = $this->newUser();
        $bystander_paths = $this->pathsFor($bystander);

        $this->assertFalse(big_jobs_kill(), 'A user with no lease has nothing to kill');
        $this->assertFileDoesNotExist($owner_paths['kill']);
        $this->assertFileDoesNotExist($bystander_paths['kill']);
    }

}
