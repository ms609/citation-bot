<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';
require_once __DIR__ . '/../../../src/includes/big_jobs.php';

final class bigJobTest extends testBaseClass {

    public function testName(): void {
        $state_directory = big_jobs_state_directory();
        $job = big_jobs_name();

        $this->assertDirectoryExists($state_directory);
        if (is_dir('/dev/shm') && is_writable('/dev/shm')) {
            $this->assertSame('/dev/shm', $state_directory);
        } else {
            $this->assertSame(
                (str_ends_with(sys_get_temp_dir(), '/') || str_ends_with(sys_get_temp_dir(), '\\')
                    ? sys_get_temp_dir()
                    : sys_get_temp_dir() . DIRECTORY_SEPARATOR) .
                    BIG_JOBS_FALLBACK_STATE_DIRECTORY,
                $state_directory
            );
        }
        $this->assertSame($state_directory . DIRECTORY_SEPARATOR . '_1', $job);
    }

    public function testSmallJobsDoNotCreateALock(): void {
        $lock_name = big_jobs_name();
        @unlink($lock_name);
        @unlink($lock_name . '_kill_job');
        big_jobs_check_overused(33);
        big_jobs_check_killed();
        $this->assertFileDoesNotExist($lock_name);
        $this->assertFileDoesNotExist($lock_name . '_kill_job');
    }

    public function testFiles(): void {
        $name = 'testFiles';
        $this->assertFalse(file_exists($name));
        hard_touch($name);
        $this->assertTrue(file_exists($name));
        @unlink($name);
        $this->assertFalse(file_exists($name));
    }

    public function testWeDied(): void {
        $lock_name = big_jobs_name();
        $guard_name = big_jobs_guard_name();
        @unlink($lock_name);
        @unlink($guard_name);

        $lock_file = fopen($lock_name, 'x+');
        $this->assertIsResource($lock_file);
        big_jobs_we_died($lock_file);

        $this->assertFalse(is_resource($lock_file));
        $this->assertFileDoesNotExist($lock_name);
        @unlink($guard_name);
    }

    public function testWeKill(): void {
        $lock_name = big_jobs_name();
        $guard_name = big_jobs_guard_name();
        $kill_name = $lock_name . '_kill_job';
        @unlink($lock_name);
        @unlink($kill_name);
        @unlink($guard_name);

        $this->assertFalse(big_jobs_kill());
        $this->assertTrue(touch($lock_name));
        $this->assertTrue(big_jobs_kill());
        $this->assertFileExists($lock_name);
        $this->assertFileExists($kill_name);

        @unlink($lock_name);
        @unlink($kill_name);
        @unlink($guard_name);
        $this->assertFileDoesNotExist($lock_name);
        $this->assertFileDoesNotExist($kill_name);
    }
}
