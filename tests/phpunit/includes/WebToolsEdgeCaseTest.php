<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Network-free edge cases for the web helpers: the big-run admission gates,
 * the admission output buffer and the batch summary printed by
 * edit_a_list_of_pages(). MediaWiki is never contacted; page reads and writes
 * are replaced with uopz stubs, and the shared admission state lives in a
 * throwaway directory.
 */
final class WebToolsEdgeCaseTest extends PHPUnit\Framework\TestCase {

    private const NOW = 1700000000.0;

    private const ENVIRONMENT = [
        'PHP_RATE_LIMIT_DIRECTORY',
        'CITATION_BOT_BIG_RUN_MAX_TOTAL',
        'CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES',
        'CITATION_BOT_BIG_RUN_MAX_LARGE',
        'CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS',
        'CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS',
        'CITATION_BOT_BIG_RUN_POOL_RETRY_SECONDS',
        'CITATION_BOT_BIG_RUN_TOKEN_CAPACITY',
        'CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND',
    ];

    private string $base_directory;
    private string $original_directory;
    /** @var array<string, string|false> */
    private array $saved_environment = [];
    private int $saved_buffer_level;

    #[\Override]
    protected function setUp(): void {
        foreach (self::ENVIRONMENT as $name) {
            $this->saved_environment[$name] = getenv($name);
            putenv($name);
        }
        $this->base_directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'citation-bot-web-edge-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($this->base_directory, 0700, true));
        putenv('PHP_RATE_LIMIT_DIRECTORY=' . $this->base_directory);
        $cwd = getcwd();
        $this->original_directory = $cwd === false ? $this->base_directory : $cwd;
        $this->saved_buffer_level = ob_get_level();
        unset($GLOBALS['citation_bot_big_run_current'], $GLOBALS['citation_bot_admission_buffer_level']);
    }

    #[\Override]
    protected function tearDown(): void {
        chdir($this->original_directory);
        while (ob_get_level() > $this->saved_buffer_level) {
            ob_end_clean();
        }
        foreach ($this->saved_environment as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
        unset($GLOBALS['citation_bot_big_run_current'], $GLOBALS['citation_bot_admission_buffer_level'], $GLOBALS['web_tools_edge_warnings']);
        $this->removeTree($this->base_directory);
    }

    private function removeTree(string $path): void {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    }

    private function requireUopz(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return') || !function_exists('uopz_redefine')) {
            $this->markTestSkipped('uopz is required for this scenario');
        }
    }

    /** Collect report_warning() text, which user_notice() discards under PHPUnit. */
    private function captureWarnings(): void {
        $GLOBALS['web_tools_edge_warnings'] = [];
        // uopz rebinds the replacement without a class scope, so a global is the only shared place.
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_warning', static function (string $message): void {
            $collected = $GLOBALS['web_tools_edge_warnings'] ?? [];
            $collected[] = $message;
            $GLOBALS['web_tools_edge_warnings'] = $collected;
        }, true);
    }

    /** @return list<string> */
    private function warnings(): array {
        $collected = $GLOBALS['web_tools_edge_warnings'] ?? [];
        if (!is_array($collected)) {
            return [];
        }
        $warnings = [];
        foreach ($collected as $message) {
            if (is_string($message)) {
                $warnings[] = $message;
            }
        }
        return $warnings;
    }

    /** Make exit() throw so that a terminal busy page can be observed. */
    private function stubExit(RuntimeException $ended): void {
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('exit', static function () use ($ended): never {
            throw $ended;
        }, true);
    }

    /**
     * Record shutdown registrations instead of deferring them to the end of the process.
     *
     * @param list<array<int, mixed>> &$registered
     */
    private function captureShutdownFunctions(array &$registered): void {
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('register_shutdown_function', static function (mixed ...$arguments) use (&$registered): void {
            $registered[] = $arguments;
        }, true);
    }

    private function requireLeaseId(?string $entry_id): string {
        if ($entry_id === null) {
            $this->fail('Expected the gate to admit a lease');
        }
        return $entry_id;
    }

    /** Page fixtures: the title decides what happens to each page. */
    private function stubPages(): void {
        $title = '';
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'get_text_from', static function (string $page) use (&$title): bool {
            $title = $page;
            return $page !== 'Blank page';
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'expand_text', static function () use (&$title): bool {
            return $title !== 'No changes';
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'write', true, false);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'last_write_was_skipped', static function () use (&$title): bool {
            return str_starts_with($title, 'Conflict');
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'get_last_revision', '123');
    }

    private function unstubPages(): void {
        /** @psalm-suppress UnusedFunctionCall */
        uopz_unset_return(WikipediaBot::class, 'get_last_revision');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_unset_return(WikipediaBot::class, 'last_write_was_skipped');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_unset_return(Page::class, 'write');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_unset_return(Page::class, 'expand_text');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_unset_return(Page::class, 'get_text_from');
    }

    /**
     * @param array<string> $titles
     * @return string everything echoed by the run
     */
    private function runBatch(array $titles, string $run_type = 'other'): string {
        ob_start();
        try {
            edit_a_list_of_pages($titles, new WikipediaBot(), 'Fixture ', $run_type);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    // ---- message and retry-policy helpers (no HTML needed) ---------------------

    /** @return array<string, array{string, ?int, ?int, string}> */
    public static function busyMessageProvider(): array {
        return [
            'lost lease' => ['lease_lost', null, null, 'its shared lease was lost'],
            'availability check failed' => ['retry_later', null, 2, 'could not check big-run availability'],
            'quota exhausted' => ['tokens', 3, 90, 'quota is currently exhausted. Please try again in about 2 minutes.'],
            'quota without a wait' => ['tokens', null, null, 'about 1 second.'],
            'capacity without a count' => ['total_full', null, null, '(0 in progress)'],
            'unknown reasons are treated as capacity' => ['something_new', 4, 61, '(4 in progress). Please try again in about 2 minutes.'],
        ];
    }

    #[DataProvider('busyMessageProvider')]
    public function testBusyPageMessageWording(string $reason, ?int $active, ?int $retry_after, string $expected): void {
        $this->assertStringContainsString($expected, big_run_busy_page_message($reason, $active, $retry_after));
    }

    /** @return array<string, array{int, string}> */
    public static function humanizedWaitProvider(): array {
        return [
            'negative' => [-5, '1 second'],
            'zero' => [0, '1 second'],
            'one' => [1, '1 second'],
            'seconds' => [59, '59 seconds'],
            'exactly a minute' => [60, '1 minute'],
            'rounds up' => [61, '2 minutes'],
            'an hour' => [3600, '60 minutes'],
        ];
    }

    #[DataProvider('humanizedWaitProvider')]
    public function testHumanizedWaits(int $seconds, string $expected): void {
        $this->assertSame($expected, big_run_humanize_wait($seconds));
    }

    /** @return array<string, array{string, ?int, int}> */
    public static function retryAfterProvider(): array {
        return [
            'token wait gets a safety margin' => ['tokens', 10, 13],
            'token wait is at least one second' => ['tokens', 0, 2],
            'token wait without a value' => ['tokens', null, 2],
            'other reasons never retry sooner than two seconds' => ['retry_later', 0, 2],
            'other reasons keep a longer wait' => ['probe_full', 17, 17],
        ];
    }

    #[DataProvider('retryAfterProvider')]
    public function testEffectiveRetryAfter(string $reason, ?int $retry_after, int $expected): void {
        $this->assertSame($expected, big_run_effective_retry_after($reason, $retry_after));
    }

    public function testPoolWaitsNeverUndercutTheConfiguredBackoff(): void {
        $pool = big_run_pool_retry_seconds();
        foreach (['total_full', 'large_full'] as $reason) {
            $this->assertSame($pool, big_run_effective_retry_after($reason, null));
            $this->assertSame($pool, big_run_effective_retry_after($reason, 1));
            $this->assertSame($pool + 100, big_run_effective_retry_after($reason, $pool + 100));
        }
    }

    public function testBackwardsCompatibleExemptionMatchesConcurrencyExemption(): void {
        $this->assertTrue(big_run_is_exempt('testing', 'anyone'));
        $this->assertFalse(big_run_is_exempt('category', 'anyone'));
        foreach (DEV_USERS as $user) {
            $this->assertFalse(big_run_is_exempt('webform', $user), 'Trusted users still consume concurrency');
            $this->assertTrue(big_run_is_token_exempt('webform', $user));
        }
    }

    public function testGateDecisionUsesAStrictThreshold(): void {
        $this->assertFalse(big_run_gate_decision(BIG_RUN_PAGE_THRESHOLD, 'category', 'alice'));
        $this->assertTrue(big_run_gate_decision(BIG_RUN_PAGE_THRESHOLD + 1, 'category', 'alice'));
        $this->assertFalse(big_run_gate_decision(BIG_RUN_PAGE_THRESHOLD + 1000, 'testing', 'alice'));
    }

    // ---- everything is inert in the command line -------------------------------

    public function testGatesAreInertOutsideWebRequests(): void {
        $this->assertFalse(HTML_OUTPUT, 'This scenario is about the CLI / PHPUnit configuration');
        $this->assertNull(gate_big_run_probe('category', 'alice', $this->base_directory, self::NOW));
        $this->assertNull(gate_big_run_probe_to_discovery(null, $this->base_directory, self::NOW));
        $this->assertNull(gate_big_run_discovery('category', 'alice', $this->base_directory, self::NOW));
        $this->assertNull(gate_big_run(500, 'category', 'alice', $this->base_directory, self::NOW));
        release_big_run_lease(null, $this->base_directory);
        $this->assertSame([], glob($this->base_directory . DIRECTORY_SEPARATOR . '*') ?: [], 'No admission state is created');
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS);
    }

    public function testAdmissionBufferIsInertOutsideWebRequests(): void {
        $level = ob_get_level();
        bot_admission_buffer_start();
        $this->assertSame($level, ob_get_level());
        $this->assertArrayNotHasKey('citation_bot_admission_buffer_level', $GLOBALS);
        bot_admission_buffer_discard();
        bot_admission_buffer_flush();
        $this->assertSame($level, ob_get_level());
    }

    public function testFlushForgetsAStaleBufferLevel(): void {
        $GLOBALS['citation_bot_admission_buffer_level'] = 99;
        $level = ob_get_level();
        bot_admission_buffer_discard();
        $this->assertSame($level, ob_get_level(), 'A buffer that is not ours is never cleaned');
        bot_admission_buffer_flush();
        $this->assertArrayNotHasKey('citation_bot_admission_buffer_level', $GLOBALS);
        $this->assertSame($level, ob_get_level());
    }

    // ---- batch summary (CLI wording) -------------------------------------------

    public function testSingleEditConflictIsExplainedAfterTheSummary(): void {
        $this->requireUopz();
        $this->stubPages();
        try {
            $body = $this->runBatch(['Conflict page']);
        } finally {
            $this->unstubPages();
        }
        $this->assertStringContainsString('Done with page.', $body);
        $this->assertStringContainsString('Page skipped due to edit conflict — please try again: Conflict page', $body);
        $this->assertStringNotContainsString('Done all', $body);
    }

    public function testSingleSuccessfulPageHasNoConflictNotice(): void {
        $this->requireUopz();
        $this->stubPages();
        try {
            $body = $this->runBatch(['Fine page']);
        } finally {
            $this->unstubPages();
        }
        $this->assertStringContainsString('Done with page.', $body);
        $this->assertStringNotContainsString('edit conflict', $body);
    }

    public function testBlankPagesAreCountedAsUnchanged(): void {
        $this->requireUopz();
        $this->stubPages();
        try {
            $body = $this->runBatch(['Blank page', 'No changes']);
        } finally {
            $this->unstubPages();
        }
        $this->assertStringContainsString('Done all 2 pages: 0 changed, 2 unchanged, 0 failed.', $body);
    }

    public function testEveryConflictedPageIsListedForARetry(): void {
        $this->requireUopz();
        $this->stubPages();
        try {
            $body = $this->runBatch(['Conflict one', 'Fine page', 'Conflict two']);
        } finally {
            $this->unstubPages();
        }
        $this->assertStringContainsString('Done all 3 pages: 1 changed, 2 unchanged, 0 failed.', $body);
        $this->assertStringContainsString('Pages skipped due to edit conflict — please try again (2):', $body);
        $this->assertStringContainsString('  * Conflict one', $body);
        $this->assertStringContainsString('  * Conflict two', $body);
        $this->assertStringNotContainsString('* Fine page', $body);
    }

    public function testUnusableTitlesAreFilteredBeforeAnythingIsRead(): void {
        $this->requireUopz();
        $reads = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'get_text_from', static function (string $page) use (&$reads): bool {
            $reads[] = $page;
            return false;
        }, true);
        $this->captureWarnings();
        try {
            $body = $this->runBatch(['', '   ', "bad \xFF title", str_repeat('x', 300)]);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'get_text_from');
        }
        $this->assertSame([], $reads);
        $this->assertContains('No links to expand found', $this->warnings());
        $this->assertContains('Skipping page title with invalid UTF-8.', $this->warnings());
        $this->assertStringNotContainsString('Done all', $body);
    }

    // ---- separate-process scenarios: web output (HTML_OUTPUT === true) ---------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdmissionBufferLifecycle(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $level = ob_get_level();
        ob_start(); // stands in for the web server: receives whatever the flush releases

        bot_admission_buffer_start();
        $this->assertSame($level + 2, ob_get_level());
        $this->assertSame($level + 2, $GLOBALS['citation_bot_admission_buffer_level']);

        bot_admission_buffer_start(); // a second start must not stack another buffer
        $this->assertSame($level + 2, ob_get_level());

        echo 'tentative page';
        bot_admission_buffer_discard();
        $this->assertSame('', (string) ob_get_contents(), 'Discarding keeps the buffer open but empty');
        $this->assertSame($level + 2, ob_get_level());

        echo 'final page';
        ob_start(); // someone else nests another buffer: discard must leave ours alone
        echo 'nested';
        bot_admission_buffer_discard();
        $this->assertSame('nested', (string) ob_get_contents());
        ob_end_clean();
        $this->assertSame('final page', (string) ob_get_contents());

        bot_admission_buffer_flush();
        $this->assertSame($level + 1, ob_get_level(), 'Flushing closes only the admission buffer');
        $this->assertArrayNotHasKey('citation_bot_admission_buffer_level', $GLOBALS);
        $this->assertSame('final page', (string) ob_get_clean());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProbeGateAdmitsRecordsAndDefers(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        putenv('CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES=1');
        $registered = [];
        $this->captureShutdownFunctions($registered);
        $this->captureWarnings();
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);

        $this->assertNull(gate_big_run_probe('testing', 'alice', $this->base_directory, self::NOW), 'Testing is concurrency-exempt');
        $this->assertSame([], $registered);

        $entry_id = gate_big_run_probe('category', 'alice', $this->base_directory, self::NOW);
        $this->assertIsString($entry_id);
        $this->assertSame([['big_run_release', $entry_id, $this->base_directory]], $registered);
        $this->assertSame($entry_id, $GLOBALS['citation_bot_big_run_current']['entry_id']);
        $this->assertSame($this->base_directory, $GLOBALS['citation_bot_big_run_current']['base_directory']);

        ob_start();
        try {
            gate_big_run_probe('category', 'bob', $this->base_directory, self::NOW);
            $this->fail('A full probe pool must defer the request');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            ob_end_clean();
        }
        $this->assertCount(1, $this->warnings());
        $this->assertStringContainsString('currently at capacity', $this->warnings()[0]);
        $this->assertCount(1, $registered, 'A deferred request registers nothing');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProbePromotionKeepsOrDropsTheLease(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=1');
        $registered = [];
        $this->captureShutdownFunctions($registered);
        $this->captureWarnings();
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);

        $probe = gate_big_run_probe('category', 'alice', $this->base_directory, self::NOW);
        $this->assertIsString($probe);
        $this->assertSame($probe, gate_big_run_probe_to_discovery($probe, $this->base_directory, self::NOW));
        $this->assertSame($probe, gate_big_run_probe_to_discovery($probe, $this->base_directory, self::NOW), 'Promotion is idempotent');

        // A second probe cannot become discovery while the only normal slot is taken.
        $second = $this->requireLeaseId(gate_big_run_probe('category', 'bob', $this->base_directory, self::NOW));
        ob_start();
        try {
            gate_big_run_probe_to_discovery($second, $this->base_directory, self::NOW);
            $this->fail('A full pool must defer the promotion');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            ob_end_clean();
        }
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS, 'The failed lease is no longer current');
        $this->assertStringContainsString('currently at capacity', $this->warnings()[0] ?? '');
        $this->assertSame('lost', big_run_heartbeat_status($second, $this->base_directory, self::NOW), 'The denied lease was dropped');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDiscoveryGateAdmitsRecordsAndDefers(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        putenv('CITATION_BOT_BIG_RUN_MAX_TOTAL=1');
        $registered = [];
        $this->captureShutdownFunctions($registered);
        $this->captureWarnings();
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);

        $this->assertNull(gate_big_run_discovery('testing', 'alice', $this->base_directory, self::NOW));
        $this->assertSame([], $registered);

        $entry_id = gate_big_run_discovery('category', 'alice', $this->base_directory, self::NOW);
        $this->assertIsString($entry_id);
        $this->assertSame([['big_run_release', $entry_id, $this->base_directory]], $registered);
        $this->assertSame($entry_id, $GLOBALS['citation_bot_big_run_current']['entry_id']);

        ob_start();
        try {
            gate_big_run_discovery('category', 'bob', $this->base_directory, self::NOW);
            $this->fail('A full pool must defer discovery');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('(1 in progress)', $this->warnings()[0] ?? '');
        $this->assertCount(1, $registered);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLargeDiscoveryMarkingAdmitsOrDefers(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        putenv('CITATION_BOT_BIG_RUN_MAX_LARGE=1');
        $registered = [];
        $this->captureShutdownFunctions($registered);
        $this->captureWarnings();
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);

        $first = $this->requireLeaseId(gate_big_run_discovery('category', 'alice', $this->base_directory, self::NOW));
        gate_big_run_discovery_large($first, $this->base_directory, self::NOW);
        $this->assertSame('ok', big_run_heartbeat_status($first, $this->base_directory, self::NOW));

        $second = $this->requireLeaseId(gate_big_run_discovery('category', 'bob', $this->base_directory, self::NOW));
        $this->assertSame($second, $GLOBALS['citation_bot_big_run_current']['entry_id']);
        ob_start();
        try {
            gate_big_run_discovery_large($second, $this->base_directory, self::NOW);
            $this->fail('A full large pool must defer the request');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            ob_end_clean();
        }
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS, 'The denied lease is no longer current');
        $this->assertSame('lost', big_run_heartbeat_status($second, $this->base_directory, self::NOW));
        $this->assertStringContainsString('currently at capacity', $this->warnings()[0] ?? '');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFinalGateAdmitsNewRunsAndPromotesDiscoveryLeases(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $registered = [];
        $this->captureShutdownFunctions($registered);

        $fresh = gate_big_run(5, 'webform', 'alice', $this->base_directory, self::NOW);
        $this->assertIsString($fresh);
        $this->assertSame([['big_run_release', $fresh, $this->base_directory]], $registered);
        $this->assertSame($fresh, $GLOBALS['citation_bot_big_run_current']['entry_id']);

        $discovery = gate_big_run_discovery('category', 'bob', $this->base_directory, self::NOW);
        $this->assertIsString($discovery);
        $this->assertCount(2, $registered, 'Discovery registered its own release');
        $promoted = gate_big_run(5, 'category', 'bob', $this->base_directory, self::NOW, $discovery);
        $this->assertSame($discovery, $promoted);
        $this->assertCount(2, $registered, 'Promotion must not register a second release');

        // A discovery lease that turned out to be a small job is released immediately.
        $small = $this->requireLeaseId(gate_big_run_discovery('category', 'carol', $this->base_directory, self::NOW));
        $this->assertNull(gate_big_run(BIG_RUN_PAGE_THRESHOLD, 'category', 'carol', $this->base_directory, self::NOW, $small));
        $this->assertSame('lost', big_run_heartbeat_status($small, $this->base_directory, self::NOW));
        $this->assertArrayNotHasKey('citation_bot_big_run_current', $GLOBALS);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFinalGateDropsADiscoveryLeaseThatCannotBePromoted(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        putenv('CITATION_BOT_BIG_RUN_TOKEN_CAPACITY=1');
        putenv('CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND=0.5');
        $registered = [];
        $this->captureShutdownFunctions($registered);
        $this->captureWarnings();
        $ended = new RuntimeException('exit');
        $this->stubExit($ended);

        $this->assertIsString(gate_big_run(5, 'webform', 'alice', $this->base_directory, self::NOW), 'Spends the only token');
        $discovery = $this->requireLeaseId(gate_big_run_discovery('category', 'bob', $this->base_directory, self::NOW));

        ob_start();
        try {
            gate_big_run(5, 'category', 'bob', $this->base_directory, self::NOW, $discovery);
            $this->fail('An empty bucket must defer promotion');
        } catch (RuntimeException $exception) {
            $this->assertSame($ended, $exception);
        } finally {
            ob_end_clean();
        }
        $this->assertSame('lost', big_run_heartbeat_status($discovery, $this->base_directory, self::NOW));
        $this->assertStringContainsString('quota is currently exhausted', $this->warnings()[0] ?? '');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigWebBatchPrintsTheReminderAndHeartbeatsItsLease(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $registered = [];
        $this->captureShutdownFunctions($registered);
        $this->captureWarnings();
        $this->stubPages();
        try {
            $body = $this->runBatch(['Page 1', 'Page 2', 'Blank page', 'No changes', 'Conflict five', 'Page 6']);
        } finally {
            $this->unstubPages();
        }
        $this->assertContains(
            'Reminder: the bot will edit these pages automatically. You are responsible for checking its edits — please review the changes it makes.',
            $this->warnings()
        );
        $this->assertStringContainsString('Done all 6 pages: 3 changed, 3 unchanged, 0 failed.', $body);
        $this->assertCount(1, $registered, 'The final gate registered exactly one release');
        $this->assertSame('big_run_release', $registered[0][0]);
        $entry_id = $registered[0][1];
        $this->assertIsString($entry_id);
        $this->assertSame('ok', big_run_heartbeat_status($entry_id, $this->base_directory));

        big_run_release($entry_id, $this->base_directory);
        $this->assertSame('lost', big_run_heartbeat_status($entry_id, $this->base_directory));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTestingRunsSkipTheGateButStillGetTheReminder(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $registered = [];
        $this->captureShutdownFunctions($registered);
        $this->captureWarnings();
        $this->stubPages();
        try {
            $body = $this->runBatch(['Page 1', 'Page 2', 'Page 3', 'Page 4', 'Page 5'], 'testing');
        } finally {
            $this->unstubPages();
        }
        $this->assertStringContainsString('Done all 5 pages: 5 changed, 0 unchanged, 0 failed.', $body);
        $this->assertSame([], $registered);
        $this->assertSame([], glob($this->base_directory . DIRECTORY_SEPARATOR . '*') ?: [], 'No admission state is created');
        $this->assertTrue(
            in_array('Reminder: the bot will edit these pages automatically. You are responsible for checking its edits — please review the changes it makes.', $this->warnings(), true)
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWebBatchWithConflictsSummarisesThemWithoutBreakingTheCounts(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('HTML_OUTPUT', true);
        $this->stubPages();
        try {
            $many = $this->runBatch(['Conflict A', 'Fine page', 'Conflict B']);
            $single = $this->runBatch(['Conflict only']);
        } finally {
            $this->unstubPages();
        }
        $this->assertStringContainsString('Done all 3 pages: 1 changed, 2 unchanged, 0 failed.', $many);
        $this->assertStringContainsString('Done with page.', $single);
    }

    // ---- save-to-files mode ----------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveToFilesWritesSanitisedFileNames(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('SAVETOFILES_MODE', true);
        $this->assertTrue(chdir($this->base_directory));
        $this->stubPages();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'parsed_text', 'Expanded body');
        $this->captureWarnings();
        try {
            $body = $this->runBatch(['A/B: C*D?', 'No changes']);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'parsed_text');
            $this->unstubPages();
        }
        $filename = page_output_filename('A/B: C*D?');
        $this->assertMatchesRegularExpression('~^A_B__C_D_-[0-9a-f]{16}\.md$~D', $filename);
        $path = $this->base_directory . DIRECTORY_SEPARATOR . $filename;
        $this->assertFileExists($path);
        $this->assertSame('Expanded body', file_get_contents($path));
        $this->assertFileDoesNotExist($this->base_directory . DIRECTORY_SEPARATOR . page_output_filename('No changes'));
        $this->assertStringContainsString('Done all 2 pages: 1 changed, 1 unchanged, 0 failed.', $body);
        $this->assertNotContains('Save to file failed.', $this->warnings());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveToFilesReportsAShortWriteButStillCountsThePage(): void {
        $this->requireUopz();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('SAVETOFILES_MODE', true);
        $this->assertTrue(chdir($this->base_directory));
        $this->stubPages();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'parsed_text', 'Expanded body');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('file_put_contents', false);
        $this->captureWarnings();
        try {
            $body = $this->runBatch(['Some page', 'Other page']);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('file_put_contents');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'parsed_text');
            $this->unstubPages();
        }
        $this->assertSame(['Save to file failed.', 'Save to file failed.'], array_values(array_filter(
            $this->warnings(),
            static fn (string $message): bool => $message === 'Save to file failed.'
        )));
        $this->assertStringContainsString('Done all 2 pages: 2 changed, 0 unchanged, 0 failed.', $body);
    }
}
