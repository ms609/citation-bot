<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/../../testBaseClass.php';

final class BigJobsCoverageTest extends PHPUnit\Framework\TestCase {

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLargeJobPathReplacesStaleLockAndRefreshesActiveLock(): void {
        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', true);
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return('report_warning',
                                    function (string $message): void {
                                        $GLOBALS['big_jobs_coverage_warning'] = $message;
                                    }, true);

            require_once __DIR__ . '/../../../src/includes/big_jobs.php';

            $_SESSION = ['citation_bot_user_id' => 'coverage/user'];
            $lock_name = big_jobs_name();
            $kill_name = $lock_name . '_kill_job';

            @unlink($lock_name);
            @unlink($kill_name);

            hard_touch($lock_name);
            $stale_time = time() - 3700;
            $this->assertTrue(touch($lock_name, $stale_time, $stale_time));
            hard_touch($kill_name);
            $this->assertFileExists($lock_name);
            $this->assertFileExists($kill_name);

            big_jobs_check_overused(50);

            $this->assertTrue(defined('BIG_JOB_MODE'));
            $this->assertFileExists($lock_name);
            $this->assertFileDoesNotExist($kill_name);
            $this->assertStringStartsWith('/dev/shm/', $lock_name);
            $this->assertStringContainsString(
                'Large job mode: running 50 pages',
                (string) ($GLOBALS['big_jobs_coverage_warning'] ?? '')
            );

            $old_time = time() - 10;
            $this->assertTrue(touch($lock_name, $old_time, $old_time));
            clearstatcache(true, $lock_name);
            $before_refresh = filemtime($lock_name);
            $this->assertIsInt($before_refresh);

            big_jobs_check_killed();

            clearstatcache(true, $lock_name);
            $after_refresh = filemtime($lock_name);
            $this->assertIsInt($after_refresh);
            $this->assertGreaterThan($before_refresh, $after_refresh);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }
}
