<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/** Local filesystem tests for the common bulk gate lock lifecycle. */
final class BigRunLockLifecycleTest extends PHPUnit\Framework\TestCase {
    /** @var list<string> */
    private array $directories = [];

    private function newBase(): string {
        $base = sys_get_temp_dir() . '/big-run-lock-life-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($base, 0700));
        $this->directories[] = $base;
        return $base;
    }

    #[\Override]
    protected function tearDown(): void {
        foreach ($this->directories as $base) {
            $directory = big_run_state_directory($base);
            if (is_dir($directory) && !is_link($directory)) {
                foreach (glob($directory . '/*') ?: [] as $path) {
                    @unlink($path);
                }
                @rmdir($directory);
            }
            foreach (glob($base . '/*') ?: [] as $child) {
                if (is_file($child) || is_link($child)) {
                    @unlink($child);
                }
            }
            @rmdir($base);
        }
        parent::tearDown();
    }

    public function testAcquisitionBusyAndReleaseCloseTheExpectedDescriptors(): void {
        $base = $this->newBase();
        $first = big_run_acquire_state_lock($base);
        $this->assertSame('locked', $first['status']);
        if ($first['status'] !== 'locked') {
            $this->fail('Unable to acquire the first lock');
        }
        $handle = $first['handle'];
        $this->assertIsResource($handle);
        try {
            // The contender must close its own descriptor on failure, but
            // must not release the descriptor owned by the first request.
            $this->assertSame(['status' => 'busy'], big_run_acquire_state_lock($base, 2));
            $this->assertIsResource($handle);
        } finally {
            big_run_release_state_lock($handle);
        }
        $this->assertFalse(is_resource($handle));
        $second = big_run_acquire_state_lock($base);
        if ($second['status'] !== 'locked') {
            $this->fail('The lock must be available after release');
        }
        big_run_release_state_lock($second['handle']);
    }

    public function testUnavailableLockAndSymlinkBothFailClosed(): void {
        $base = $this->newBase();
        $unusable_base = $base . '/not-a-directory';
        $this->assertNotFalse(file_put_contents($unusable_base, 'keep'));
        $this->assertSame(['status' => 'open_failed'], big_run_acquire_state_lock($unusable_base));
        $this->assertNotNull(big_run_prepare_state_directory($base));
        $target = $base . '/outside';
        $path = big_run_lock_path($base);
        $this->assertNotFalse(file_put_contents($target, 'untouched'));
        $this->assertTrue(symlink($target, $path));
        $this->assertSame(['status' => 'open_failed'], big_run_acquire_state_lock($base));
        $this->assertSame('untouched', file_get_contents($target));
    }

    public function testProbeDoesNotUseTheOrdinaryBulkCapacity(): void {
        $base = $this->newBase();
        $probe = big_run_try_acquire_probe($base, 1000000.0);
        $this->assertTrue($probe[0]);
        $this->assertSame(0, $probe[4]);
        $bulk = big_run_try_acquire_discovery($base, 1000000.0);
        $this->assertTrue($bulk[0]);
        $this->assertSame(1, $bulk[4]);
        [, , $entries, $valid] = big_run_read_state_file(big_run_state_path($base), 1000000.0);
        $this->assertTrue($valid);
        $this->assertSame(1, big_run_count_phase($entries, 'probe'));
        $this->assertSame(1, big_run_count_bulk_entries($entries));
    }
}
