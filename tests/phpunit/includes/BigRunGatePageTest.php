<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/../../testBaseClass.php';

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
    public function testBigRunGateDefersAndRendersBusyPageForBigFull(): void {
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
            big_run_render_busy_page('big_full', 8, null);
            ob_end_clean();

            $this->assertSame(
                'Citation Bot is currently at capacity with other big runs (8 in progress). Please try again shortly.',
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
    public function testBigRunGateDefersAndRendersBusyPageForTokens(): void {
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
            $this->assertSame(392.0, $state['tokens']); // 400 - ceil(5*1.5*1.0)
            $this->assertCount(1, $state['entries']);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigRunGateSkipsSinglesDevAndTestingWithoutState(): void {
        $this->requireUopz();

        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', true);

            gate_big_run(4, 'category', 'SomeUser', null, 100.0);
            gate_big_run(5, 'category', 'AManWithNoPlan', null, 100.0);
            gate_big_run(5, 'testing', 'SomeUser', null, 100.0);

            $state_path =
                $this->base_directory .
                DIRECTORY_SEPARATOR .
                REQUEST_RATE_LIMIT_STATE_DIRECTORY .
                DIRECTORY_SEPARATOR .
                BIG_RUN_STATE_FILE;

            $this->assertFileDoesNotExist($state_path);
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
