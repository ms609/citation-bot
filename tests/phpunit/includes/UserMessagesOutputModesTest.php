<?php
declare(strict_types=1);

/*
 * Edge-case tests for user_messages.php in the default test mode (CI, no HTML),
 * plus the fatal-error reporters, which call exit() and therefore must run in a
 * short-lived PHP subprocess.  The tests that need other output-mode constants
 * (HTML_OUTPUT, BIG_JOB_MODE, ...) live in UserMessagesIsolatedModesTest.
 *
 * No network access and no uopz extension are required.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class UserMessagesOutputModesTest extends testBaseClass {

    /**
     * Run $body in a fresh PHP process after defining the requested constants
     * and loading user_messages.php.
     *
     * @param array{ci?: bool, html?: bool, logger?: bool} $options
     * @return array{int, string} exit code and combined output
     */
    private function runWithMode(array $options, string $body): array {
        if (!function_exists('exec')) {
            $this->markTestSkipped('exec() is unavailable');
        }
        $user_messages = realpath(dirname(__DIR__, 3) . '/src/includes/user_messages.php');
        $this->assertNotFalse($user_messages);

        $script = "<?php\ndeclare(strict_types=1);\n";
        $script .= "putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');\n";
        $script .= 'define("CI", ' . (($options['ci'] ?? false) ? 'true' : 'false') . ");\n";
        $script .= 'define("HTML_OUTPUT", ' . (($options['html'] ?? false) ? 'true' : 'false') . ");\n";
        if ($options['logger'] ?? false) {
            $script .= "function bot_debug_log(string \$text): void { echo '[LOG:', \$text, ']'; }\n";
        }
        $script .= 'require_once ' . var_export($user_messages, true) . ";\n";
        $script .= $body . "\n";

        $file = tempnam(sys_get_temp_dir(), 'umsg');
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

    // ---- exact output in the in-process (CI, non-HTML) mode -----------------

    public function testNonHtmlLinksHaveExactPlainTextForms(): void {
        $this->assertSame('PMID 12345', pubmed_link('pmid', '12345'));
        $this->assertSame('PMC 77', pubmed_link('pmc', '77'));
        $this->assertSame('2020Natur.123..456A', bibcode_link('2020Natur.123..456A'));
        $this->assertSame('10.1000/test', doi_link('10.1000/test'));
        $this->assertSame('JSTOR 12345', jstor_link('12345'));
        $this->assertSame('Wikipedia page : Foo bar', wiki_link('Foo bar'));
    }

    public function testNewTabAriaLabelAppendsNoticeAndKeepsTextInCi(): void {
        $this->assertSame('Example (opens in a new tab)', new_tab_aria_label('Example'));
        // The notice is appended with its own leading space, even for empty text.
        $this->assertSame(' (opens in a new tab)', new_tab_aria_label(''));
    }

    public function testEchoableLeavesMarkupAloneInCi(): void {
        $this->assertSame('<b>"x" & \'y\'</b>', echoable('<b>"x" & \'y\'</b>'));
    }

    public function testReportInlineIsSilentInCi(): void {
        ob_start();
        report_inline('inline text');
        $this->assertSame('', (string) ob_get_clean());
    }

    // ---- error reporters that terminate the process --------------------------

    public function testMinorErrorInCiTerminatesTheProcess(): void {
        [$exit_code, $output] = $this->runWithMode(
            ['ci' => true],
            'report_minor_error("ci minor problem"); echo "UNREACHABLE";'
        );
        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('ci minor problem', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    public function testFatalErrorWithoutLoggerWarnsToUserAndSystemLog(): void {
        [$exit_code, $output] = $this->runWithMode(
            [],
            'report_error("fatal no logger"); echo "UNREACHABLE";'
        );
        $this->assertSame(1, $exit_code);
        // Once through report_warning() and once through trigger_error().
        $this->assertSame(2, mb_substr_count($output, 'fatal no logger'));
        $this->assertStringContainsString('Warning', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    public function testFatalErrorWithLoggerLogsAndWarnsButSkipsSystemLog(): void {
        [$exit_code, $output] = $this->runWithMode(
            ['logger' => true],
            'report_error("fatal with logger"); echo "UNREACHABLE";'
        );
        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('[LOG:fatal with logger]', $output);
        $this->assertSame(2, mb_substr_count($output, 'fatal with logger'));
        $this->assertStringNotContainsString('Warning:', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }

    public function testFatalErrorInCiTriggersAPhpWarningThenExits(): void {
        [$exit_code, $output] = $this->runWithMode(
            ['ci' => true, 'logger' => true],
            'report_error("fatal in ci"); echo "UNREACHABLE";'
        );
        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('Warning: fatal in ci', $output);
        $this->assertStringNotContainsString('[LOG:', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }
}
