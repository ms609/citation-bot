<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';
require_once __DIR__ . '/../../../src/includes/big_jobs.php';

final class BigJobTest extends testBaseClass {

    public function testName(): void {
        $job = big_jobs_name();
        $this->assertSame('/dev/shm/_1', $job);
    }

    public function testFuncsExists(): void {
        big_jobs_check_overused(33);
        big_jobs_check_killed();
        $this->assertFaker();
    }

    public function testFiles(): void {
        $name = 'testFiles';
        $this->assertFalse(file_exists($name));
        hard_touch($name);
        $this->assertTrue(file_exists($name));
        hard_unlink($name);
        $this->assertFalse(file_exists($name));
    }

    public function testWeDied(): void {
        $name = 'testFiles2';
        touch(big_jobs_name());
        $lock_file = fopen($name, 'x+');
        big_jobs_we_died($lock_file);
        unlink($name);
        $this->assertFaker();
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
