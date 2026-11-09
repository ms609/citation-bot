<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Exercises gate_big_run in a cold web-like environment. HTML_OUTPUT is
 * redefined to true so the gate actually engages (setup.php defines it false
 * under PHPUnit); report_warning is intercepted so the busy-page wording can
 * be asserted without real output.
 */
final class BigRunGatePageTest extends PHPUnit\Framework\TestCase {

    private string $base_directory;
    private string|false $previous_rate_limit_directory;

    #[\Override]
    protected function setUp(): void {
        $this->previous_rate_limit_directory = getenv('PHP_RATE_LIMIT_DIRECTORY');
        $this->base_directory =
            sys_get_temp_dir() .
            DIRECTORY_SEPARATOR .
            'citation-bot-big-run-test-' .
            bin2hex(random_bytes(8));

        $this->assertTrue(mkdir($this->base_directory, 0700, true));
        putenv('PHP_RATE_LIMIT_DIRECTORY=' . $this->base_directory);
    }

    #[\Override]
    protected function tearDown(): void {
        if ($this->previous_rate_limit_directory === false) {
            putenv('PHP_RATE_LIMIT_DIRECTORY');
        } else {
            putenv('PHP_RATE_LIMIT_DIRECTORY=' . $this->previous_rate_limit_directory);
        }

        $state_directory =
            $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY;

        if (is_dir($state_directory)) {
            foreach (glob($state_directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($state_directory);
        }
        @rmdir($this->base_directory);
    }

    private function requireUopz(): void {
        if (!function_exists('uopz_redefine')) {
            $this->markTestSkipped('uopz extension required');
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigRunGateDefersWith503ForTotalPool(): void {
        $this->requireUopz();

        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', true);
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return(
                'report_warning',
                static function (string $message): void {
                    $GLOBALS['big_run_gate_warning'] = $message;
                },
                true
            );

            ob_start();
            big_run_render_busy_page('total_full', 8, null);
            ob_end_clean();

            $this->assertSame(503, http_response_code());
            $this->assertSame(
                'Citation Bot is currently at capacity with other bulk work (8 in progress). Please try again in about 30 seconds.',
                (string) ($GLOBALS['big_run_gate_warning'] ?? '')
            );
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigRunGateDefersWith503ForTokens(): void {
        $this->requireUopz();

        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', true);
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return(
                'report_warning',
                static function (string $message): void {
                    $GLOBALS['big_run_gate_warning'] = $message;
                },
                true
            );

            ob_start();
            big_run_render_busy_page('tokens', null, 29);
            ob_end_clean();

            $this->assertSame(503, http_response_code());
            $this->assertSame(
                'Citation Bot\'s big-run quota is currently exhausted. Please try again in about 38 seconds.',
                (string) ($GLOBALS['big_run_gate_warning'] ?? '')
            );
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigRunGateAcquireAdmitsAndWritesState(): void {
        $this->requireUopz();

        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', true);

            gate_big_run(5, 'category', 'SomeUser', null, 100.0);

            $state_path =
                $this->base_directory .
                DIRECTORY_SEPARATOR .
                REQUEST_RATE_LIMIT_STATE_DIRECTORY .
                DIRECTORY_SEPARATOR .
                BIG_RUN_STATE_FILE;

            $this->assertFileExists($state_path);
            $raw = file_get_contents($state_path);
            $this->assertIsString($raw);
            $state = json_decode($raw, true);
            $this->assertIsArray($state);
            $this->assertSame(392.0, $state['tokens']);
            $this->assertCount(1, $state['entries']);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSinglesAndTestingSkipGateButDevBulkStillConsumesConcurrency(): void {
        $this->requireUopz();

        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', true);

            $state_path =
                $this->base_directory .
                DIRECTORY_SEPARATOR .
                REQUEST_RATE_LIMIT_STATE_DIRECTORY .
                DIRECTORY_SEPARATOR .
                BIG_RUN_STATE_FILE;

            gate_big_run(4, 'category', 'SomeUser', null, 100.0);
            gate_big_run(5, 'testing', 'SomeUser', null, 100.0);
            $this->assertFileDoesNotExist($state_path);

            $entry_id = gate_big_run(5, 'category', 'AManWithNoPlan', null, 100.0);
            $this->assertIsString($entry_id);
            $this->assertFileExists($state_path);

            $raw = file_get_contents($state_path);
            $this->assertIsString($raw);
            $state = json_decode($raw, true);
            $this->assertIsArray($state);
            $this->assertSame(400.0, $state['tokens']);
            $this->assertCount(1, $state['entries']);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    /**
     * @param list<string> $titles
     * @param string $expected_warning
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('rejectedBatches')]
    public function testRejectedBatchReleasesProbeBeforePageReads(array $titles, string $expected_warning): void {
        $this->requireUopz();

        [$acquired, , $entry_id] = big_run_try_acquire_probe();
        if (!$acquired || $entry_id === null) {
            $this->fail('Expected to acquire a probe lease');
        }
        $state_path = big_run_state_path($this->base_directory);
        [, , $entries_before, $valid_before] = big_run_read_state_file($state_path, microtime(true));
        $this->assertTrue($valid_before);
        $this->assertArrayHasKey($entry_id, $entries_before);

        $reads = [];
        $warnings = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('MAX_PAGES', 1);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_warning', static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'get_text_from', static function (string $title) use (&$reads): bool {
            $reads[] = $title;
            return false;
        }, true);
        try {
            ob_start();
            edit_a_list_of_pages($titles, new WikipediaBot(), '', 'category', $entry_id);
        } finally {
            ob_end_clean();
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'get_text_from');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
        }

        $this->assertSame([], $reads);
        $this->assertContains($expected_warning, $warnings);
        [, , $entries_after, $valid_after] = big_run_read_state_file($state_path, microtime(true));
        $this->assertTrue($valid_after);
        $this->assertSame([], $entries_after);
    }

    /** @return array<string, array{list<string>, string}> */
    public static function rejectedBatches(): array {
        return [
            'no usable titles' => [['', '   '], 'No links to expand found'],
            'above the page limit' => [['First page', 'Second page'], 'Number of links is huge. Cancelling run. Maximum size is 1'],
        ];
    }

    public function testBulkEntryPointsAcquireProbeBeforeFirstRemoteDiscoveryCall(): void {
        $category = file_get_contents(dirname(__DIR__, 3) . '/src/category.php');
        $linked = file_get_contents(dirname(__DIR__, 3) . '/src/linked_pages.php');
        $this->assertIsString($category);
        $this->assertIsString($linked);

        $category_probe = mb_strpos($category, "gate_big_run_probe('category'");
        $category_remote = mb_strpos($category, 'WikipediaBot::category_members_bounded(');
        $linked_probe = mb_strpos($linked, "gate_big_run_probe('webform_linked'");
        $linked_remote = mb_strpos($linked, 'WikipediaBot::linked_pages_batch(');

        $this->assertIsInt($category_probe);
        $this->assertIsInt($category_remote);
        $this->assertIsInt($linked_probe);
        $this->assertIsInt($linked_remote);
        if (
            !is_int($category_probe) ||
            !is_int($category_remote) ||
            !is_int($linked_probe) ||
            !is_int($linked_remote)
        ) {
            throw new RuntimeException('Expected discovery source markers.');
        }
        $this->assertLessThan($category_remote, $category_probe);
        $this->assertLessThan($linked_remote, $linked_probe);
    }

    public function testCategoryDefersLargeLeaseUntilOversizedCategoryIsRuledOut(): void {
        $category = file_get_contents(dirname(__DIR__, 3) . '/src/category.php');
        $this->assertIsString($category);
        if (!is_string($category)) {
            throw new RuntimeException('Unable to read category.php.');
        }

        $this->assertStringContainsString('$extended_run = defined(\'MAX_PAGES_OVERRIDE\');', $category);
        $this->assertStringContainsString('$large_callback = null;', $category);
        $this->assertMatchesRegularExpression(
            '~if \(\$extended_run\) \{\s*\$large_callback = static function~',
            $category,
            'Only extended runs may reserve the large lease during discovery.'
        );
        $this->assertMatchesRegularExpression(
            '~\$pages_in_category = WikipediaBot::category_members_bounded\(.*?\$large_callback\s*\);~s',
            $category,
            'The bounded category scan must receive the conditional large callback.'
        );

        $cancellation = mb_strpos($category, 'if ($total > $category_limit) {');
        $deferred = mb_strpos($category, 'if (!$extended_run && $total >= BIG_RUN_LARGE_THRESHOLD) {');
        $deferred_end = mb_strpos(
            $category,
            'if (defined(\'MAX_PAGES_OVERRIDE\') && $total > $default_web_limit) {',
            is_int($deferred) ? $deferred : 0
        );
        $this->assertIsInt($cancellation);
        $this->assertIsInt($deferred);
        $this->assertIsInt($deferred_end);
        if (!is_int($cancellation) || !is_int($deferred) || !is_int($deferred_end)) {
            throw new RuntimeException('Expected category cancellation and deferred acquisition markers.');
        }
        $this->assertLessThan(
            $deferred,
            $cancellation,
            'Finite runs must cancel oversized categories before acquiring a large lease.'
        );

        $deferred_body = mb_substr($category, $deferred, $deferred_end - $deferred);
        $this->assertStringContainsString(
            'big_jobs_acquire_large_run();',
            $deferred_body,
            'The deferred block must acquire the per-user large lease.'
        );
        $this->assertStringContainsString(
            'gate_big_run_discovery_large($discovery_entry_id);',
            $deferred_body,
            'The deferred block must mark the discovery lease large.'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProbePromotesToDiscoveryAndThenChargesAtFinalAdmission(): void {
        $this->requireUopz();

        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', true);

            $entry_id = gate_big_run_probe('category', 'SomeUser', null, 100.0);
            $this->assertIsString($entry_id);
            if (!is_string($entry_id)) {
                throw new RuntimeException('Expected probe lease id.');
            }

            $state_path =
                $this->base_directory .
                DIRECTORY_SEPARATOR .
                REQUEST_RATE_LIMIT_STATE_DIRECTORY .
                DIRECTORY_SEPARATOR .
                BIG_RUN_STATE_FILE;

            $raw = file_get_contents($state_path);
            $this->assertIsString($raw);
            $state = json_decode($raw, true);
            $this->assertIsArray($state);
            $entry = $state['entries'][$entry_id] ?? null;
            $this->assertIsArray($entry);
            $this->assertSame('probe', $entry['phase'] ?? null);
            $this->assertSame(400.0, $state['tokens'] ?? null);

            $this->assertSame(
                $entry_id,
                gate_big_run_probe_to_discovery($entry_id, null, 101.0)
            );

            $raw = file_get_contents($state_path);
            $this->assertIsString($raw);
            $state = json_decode($raw, true);
            $this->assertIsArray($state);
            $entry = $state['entries'][$entry_id] ?? null;
            $this->assertIsArray($entry);
            $this->assertSame('discovery', $entry['phase'] ?? null);
            $this->assertSame(400.0, $state['tokens'] ?? null);

            $this->assertSame(
                $entry_id,
                gate_big_run(5, 'category', 'SomeUser', null, 102.0, $entry_id)
            );

            $raw = file_get_contents($state_path);
            $this->assertIsString($raw);
            $state = json_decode($raw, true);
            $this->assertIsArray($state);
            $entry = $state['entries'][$entry_id] ?? null;
            $this->assertIsArray($entry);
            $this->assertSame('running', $entry['phase'] ?? null);
            $this->assertSame(392.0, $state['tokens'] ?? null);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigRunGateDoesNothingInCliMode(): void {
        // setup.php defines HTML_OUTPUT=false under PHPUnit; no redefine needed.

        gate_big_run(50, 'category', 'SomeUser', null, 100.0);

        $state_path =
            $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY .
            DIRECTORY_SEPARATOR .
            BIG_RUN_STATE_FILE;

        $this->assertFileDoesNotExist($state_path);
    }
}
