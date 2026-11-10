<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Heartbeat edge cases for the per-user large-job lease in big_jobs.php.
 *
 * These need HTML_OUTPUT to be true and BIG_JOB_MODE to be defined, which cannot
 * be changed once set, so each test runs in its own PHPUnit-managed child process
 * (keeping code coverage) and defines HTML_OUTPUT itself.  The class deliberately
 * does NOT extend testBaseClass, which would define HTML_OUTPUT as false.  Each
 * test uses a random user id so its lease files never collide with anything else.
 * No network access and no uopz are required.
 */
final class BigJobsIsolatedTest extends PHPUnit\Framework\TestCase {

    /** Define the constants, load big_jobs.php, take a lease and arrange cleanup. */
    private function acquireLeaseForNewUser(): string {
        define('HTML_OUTPUT', true);
        require_once dirname(__DIR__, 3) . '/src/includes/big_jobs.php';

        $_SESSION = ['citation_bot_user_id' => 'isolated-' . bin2hex(random_bytes(6))];
        $lease = big_jobs_name();
        $guard = big_jobs_guard_name();
        $kill = $lease . '_kill_job';

        big_jobs_acquire_large_run();
        $this->assertTrue(defined('BIG_JOB_MODE'));
        $this->assertFileExists($lease);

        // Registered after big_jobs_we_died(), so it runs after the lease is released.
        register_shutdown_function(static function () use ($lease, $guard, $kill): void {
            @unlink($kill);
            @unlink($lease);
            @unlink($guard);
        });
        return $lease;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatReportsFailureWhenGuardCannotBeOpened(): void {
        $lease = $this->acquireLeaseForNewUser();
        $guard = big_jobs_guard_name();
        $this->assertTrue(unlink($guard));
        $this->assertTrue(symlink('/nonexistent-citation-bot-target', $guard));
        $mtime = time() - 100;
        $this->assertTrue(touch($lease, $mtime, $mtime));

        try {
            $this->assertFalse(big_jobs_heartbeat());
            clearstatcache(true, $lease);
            $this->assertSame($mtime, filemtime($lease), 'A failed heartbeat must not refresh the lease');
            $this->assertFalse(big_jobs_kill(), 'A kill request cannot be recorded without the guard');
        } finally {
            unlink($guard);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNegativeClockValueDoesNotTouchTheLease(): void {
        $lease = $this->acquireLeaseForNewUser();
        $this->assertTrue(touch($lease, 5000, 5000));
        clearstatcache(true, $lease);

        $this->assertFalse(big_jobs_heartbeat(-1));
        clearstatcache(true, $lease);
        $this->assertSame(5000, filemtime($lease));

        $this->assertTrue(big_jobs_heartbeat(6000));
        clearstatcache(true, $lease);
        $this->assertSame(6000, filemtime($lease));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHeartbeatThrottleHonorsIntervalAndClockRollback(): void {
        $lease = $this->acquireLeaseForNewUser();
        $mtime = static function () use ($lease): int {
            clearstatcache(true, $lease);
            return (int) filemtime($lease);
        };

        big_jobs_maybe_heartbeat(1000);
        $this->assertSame(1000, $mtime());

        big_jobs_maybe_heartbeat(1014); // 14 seconds later: throttled
        $this->assertSame(1000, $mtime());

        big_jobs_maybe_heartbeat(1015); // exactly one interval later: runs
        $this->assertSame(1015, $mtime());

        big_jobs_maybe_heartbeat(900); // clock went backwards: runs
        $this->assertSame(900, $mtime());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFailedHeartbeatDoesNotAdvanceTheThrottleClock(): void {
        $lease = $this->acquireLeaseForNewUser();
        $mtime = static function () use ($lease): int {
            clearstatcache(true, $lease);
            return (int) filemtime($lease);
        };
        $this->assertTrue(touch($lease, 2000, 2000));

        // A negative time cannot touch the lease, so it must not start the throttle window...
        big_jobs_maybe_heartbeat(-5);
        $this->assertSame(2000, $mtime());

        // ...so the next call is still allowed to run.
        big_jobs_maybe_heartbeat(3000);
        $this->assertSame(3000, $mtime());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testKillRequestIsRecordedAgainstTheLiveLeaseWithoutStoppingTheCaller(): void {
        $lease = $this->acquireLeaseForNewUser();
        $kill = $lease . '_kill_job';
        $this->assertFileDoesNotExist($kill);

        $this->assertTrue(big_jobs_kill());
        clearstatcache(true, $kill);
        $this->assertFileExists($kill);
        $this->assertFileExists($lease);
        $this->assertSame($GLOBALS['citation_bot_big_jobs_lock_file'], big_jobs_current_lock_file());
    }
}
