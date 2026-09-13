<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/../../testBaseClass.php';

final class BigJobsCoverageTest extends PHPUnit\Framework\TestCase {

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLargeJobCanBeAcquiredDuringDiscoveryAndReportedAtFinalCount(): void {
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
            $guard_name = big_jobs_guard_name();
            $kill_name = $lock_name . '_kill_job';

            @unlink($lock_name);
            @unlink($kill_name);
            @unlink($guard_name);

            hard_touch($lock_name);
            $stale_time = time() - 3700;
            $this->assertTrue(touch($lock_name, $stale_time, $stale_time));
            hard_touch($kill_name);
            $this->assertFileExists($lock_name);
            $this->assertFileExists($kill_name);

            // Incremental discovery reaches the large threshold before the
            // final page count is known.
            big_jobs_acquire_large_run();

            $this->assertTrue(defined('BIG_JOB_MODE'));
            $this->assertFileExists($lock_name);
            $this->assertFileExists($guard_name);
            $this->assertFileDoesNotExist($kill_name);
            $this->assertSame(big_jobs_state_directory(), dirname($lock_name));
            $this->assertArrayNotHasKey('big_jobs_coverage_warning', $GLOBALS);

            // Final admission/reporting is idempotent and uses the final count.
            big_jobs_check_overused(73);
            $this->assertStringContainsString(
                'Large job mode: running 73 pages',
                (string) ($GLOBALS['big_jobs_coverage_warning'] ?? '')
            );

            $old_time = time() - 10;
            $this->assertTrue(touch($lock_name, $old_time, $old_time));
            clearstatcache(true, $lock_name);
            $before_refresh = filemtime($lock_name);
            $this->assertIsInt($before_refresh);

            $heartbeat_time = time() + 2;
            big_jobs_maybe_heartbeat($heartbeat_time);

            clearstatcache(true, $lock_name);
            $after_refresh = filemtime($lock_name);
            $this->assertIsInt($after_refresh);
            $this->assertSame($heartbeat_time, $after_refresh);

            // The explicit page-loop check remains a forced heartbeat/kill check.
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

            // The permanent guard is intentionally not deleted by application
            // code; clean it only after this separate-process test exits.
            register_shutdown_function(static function () use ($guard_name): void {
                @unlink($guard_name);
            });
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    public function testStaleTakeoverIsSerializedAndOldOwnerCannotTouchReplacement(): void {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open required');
        }

        $big_jobs_path = realpath(__DIR__ . '/../../../src/includes/big_jobs.php');
        $this->assertIsString($big_jobs_path);

        $test_directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'citation-bot-big-jobs-race-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($test_directory, 0700, true));

        $user = 'race/user-' . bin2hex(random_bytes(6));
        $_SESSION = ['citation_bot_user_id' => $user];
        $lock_name = big_jobs_name();
        $guard_name = big_jobs_guard_name();
        $kill_name = $lock_name . '_kill_job';
        @unlink($lock_name);
        @unlink($kill_name);
        @unlink($guard_name);

        $script = $test_directory . DIRECTORY_SEPARATOR . 'worker.php';
        $ready_a = $test_directory . DIRECTORY_SEPARATOR . 'a-ready';
        $resume_a = $test_directory . DIRECTORY_SEPARATOR . 'a-resume';
        $unexpected_a = $test_directory . DIRECTORY_SEPARATOR . 'a-unexpected';
        $go = $test_directory . DIRECTORY_SEPARATOR . 'go';
        $release = $test_directory . DIRECTORY_SEPARATOR . 'release';
        $result_b = $test_directory . DIRECTORY_SEPARATOR . 'b-result';
        $result_c = $test_directory . DIRECTORY_SEPARATOR . 'c-result';

        $worker = <<<'PHP'
<?php
declare(strict_types=1);

define('HTML_OUTPUT', true);
function bot_html_footer(): void {}
function bot_admission_buffer_flush(): void {}

require $argv[1];
$_SESSION = ['citation_bot_user_id' => $argv[3]];
$role = $argv[2];

if ($role === 'owner') {
    big_jobs_acquire_large_run();
    $lock = big_jobs_name();
    touch($lock, time() - BIG_JOBS_STALE_SECONDS - 10, time() - BIG_JOBS_STALE_SECONDS - 10);
    file_put_contents($argv[4], 'ready');
    while (!file_exists($argv[5])) {
        usleep(10000);
    }
    big_jobs_heartbeat();
    file_put_contents($argv[6], 'unexpected');
    exit(3);
}

$result_path = $argv[4];
register_shutdown_function(static function () use ($result_path): void {
    if (!file_exists($result_path)) {
        file_put_contents($result_path, defined('BIG_JOB_MODE') ? 'won' : 'blocked');
    }
});
while (!file_exists($argv[5])) {
    usleep(10000);
}
big_jobs_acquire_large_run();
file_put_contents($result_path, 'won');
while (!file_exists($argv[6])) {
    usleep(10000);
}
PHP;
        $this->assertSame(mb_strlen($worker, '8bit'), file_put_contents($script, $worker));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes_a = [];
        $pipes_b = [];
        $pipes_c = [];
        $process_a = false;
        $process_b = false;
        $process_c = false;

        $wait_for_file = static function (string $path): bool {
            $deadline = microtime(true) + 30.0;
            while (microtime(true) < $deadline) {
                clearstatcache(true, $path);
                if (file_exists($path)) {
                    return true;
                }
                usleep(10000);
            }
            return false;
        };

        try {
            // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- stale-owner race regression test
            $process_a = proc_open(
                [PHP_BINARY, $script, $big_jobs_path, 'owner', $user, $ready_a, $resume_a, $unexpected_a],
                $descriptors,
                $pipes_a
            );
            $this->assertIsResource($process_a);
            $this->assertTrue($wait_for_file($ready_a));

            // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- stale-owner race regression test
            $process_b = proc_open(
                [PHP_BINARY, $script, $big_jobs_path, 'contender', $user, $result_b, $go, $release],
                $descriptors,
                $pipes_b
            );
            // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- stale-owner race regression test
            $process_c = proc_open(
                [PHP_BINARY, $script, $big_jobs_path, 'contender', $user, $result_c, $go, $release],
                $descriptors,
                $pipes_c
            );
            $this->assertIsResource($process_b);
            $this->assertIsResource($process_c);

            hard_touch($go);
            $this->assertTrue($wait_for_file($result_b));
            $this->assertTrue($wait_for_file($result_c));

            $outcomes = [
                mb_trim((string) file_get_contents($result_b)),
                mb_trim((string) file_get_contents($result_c)),
            ];
            sort($outcomes);
            $this->assertSame(['blocked', 'won'], $outcomes);
            $this->assertFileExists($lock_name);
            $this->assertFileExists($guard_name);

            // Resume the old stale owner after replacement. Its heartbeat must
            // stop that process rather than refreshing the winner's pathname.
            hard_touch($resume_a);
            proc_close($process_a);
            $process_a = false;
            $this->assertFileDoesNotExist($unexpected_a);
            $this->assertFileExists($lock_name);

            hard_touch($release);
            proc_close($process_b);
            $process_b = false;
            proc_close($process_c);
            $process_c = false;
            clearstatcache(true, $lock_name);
            $this->assertFileDoesNotExist($lock_name);
        } finally {
            hard_touch($resume_a);
            hard_touch($go);
            hard_touch($release);
            foreach ([$process_a, $process_b, $process_c] as $process) {
                if (is_resource($process)) {
                    /** @psalm-suppress NoValue */
                    @proc_terminate($process);
                    /** @psalm-suppress NoValue */
                    @proc_close($process);
                }
            }
            foreach ([$pipes_a, $pipes_b, $pipes_c] as $pipes) {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        @fclose($pipe);
                    }
                }
            }
            @unlink($lock_name);
            @unlink($kill_name);
            @unlink($guard_name);
            foreach (glob($test_directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($test_directory);
        }
    }
}
