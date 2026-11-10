<?php
declare(strict_types=1);

/*
 * Network-free edge-case tests for the per-user large-job lease in big_jobs.php.
 *
 * Every test uses its own random user id, so lease, kill-flag and guard files
 * never collide with other tests or with real runs.  Cases that end in the
 * terminal stop page (which calls exit) or that depend on HTML_OUTPUT being
 * true run in a short-lived PHP subprocess; the rest run in-process.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class BigJobsEdgeCasesTest extends PHPUnit\Framework\TestCase {

    /** @var array<string> */
    private array $users = [];

    #[\Override]
    protected function tearDown(): void {
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
        parent::tearDown();
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
    }

    // ---- in-process: state directory and naming ------------------------------

    public function testStateDirectoryIsPrivateOwnedAndStable(): void {
        $directory = big_jobs_state_directory();
        clearstatcache(true, $directory); // chmod() does not clear PHP's stat cache

        $this->assertDirectoryExists($directory);
        $this->assertDirectoryIsWritable($directory);
        $this->assertFalse(is_link($directory));
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
