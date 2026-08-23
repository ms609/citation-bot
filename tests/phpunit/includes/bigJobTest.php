<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';
require_once __DIR__ . '/../../../src/includes/big_jobs.php';

final class bigJobTest extends testBaseClass {

    public function testName(): void {
        $job = big_jobs_name();
        $this->assertSame('/dev/shm/_1', $job);
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
        touch($lock_name);
        $lock_file = tmpfile();
        $this->assertIsResource($lock_file);
        big_jobs_we_died($lock_file);
        $this->assertFalse(is_resource($lock_file));
        $this->assertFileDoesNotExist($lock_name);
    }

    public function testWeKill(): void {
        $this->assertFalse(big_jobs_kill());
        touch(big_jobs_name());
        $this->assertTrue(big_jobs_kill());
        $this->assertTrue(file_exists(big_jobs_name()));
        $this->assertTrue(file_exists(big_jobs_name() . '_kill_job'));
        unlink(big_jobs_name());
        unlink(big_jobs_name() . '_kill_job');
        $this->assertFalse(file_exists(big_jobs_name()));
        $this->assertFalse(file_exists(big_jobs_name() . '_kill_job'));
    }
}
