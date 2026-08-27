<?php
declare(strict_types=1);

/*
 * Tests for user_messages.php
 */

require_once __DIR__ . '/../../testBaseClass.php';
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

final class UserMessagesTest extends testBaseClass {

    public function testEchoablePlainText(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertSame('hello world', echoable('hello world'));
    }

    public function testEchoableNull(): void {
        $this->assertSame('', echoable(null));
    }

    public function testEchoableEmptyString(): void {
        $this->assertSame('', echoable(''));
    }

    // In CI mode HTML_OUTPUT is false, so echoable() returns the string unchanged
    public function testEchoableAngleBracketsNotEscapedInCi(): void {
        $this->assertSame('<test>', echoable('<test>'));
    }

    public function testPubmedLinkPmidContainsId(): void {
        $result = pubmed_link('pmid', '12345678');
        $this->assertStringContainsString('12345678', $result);
    }

    public function testPubmedLinkPmcContainsId(): void {
        $result = pubmed_link('pmc', '12345');
        $this->assertStringContainsString('12345', $result);
    }

    public function testPubmedLinkPmidUppercasesIdentifierLabel(): void {
        // In non-HTML (CI) mode the result is "PMID 12345678"
        $result = pubmed_link('pmid', '12345678');
        $this->assertStringContainsString('PMID', $result);
    }

    public function testBibcodeLinkContainsBibcode(): void {
        $result = bibcode_link('2020Natur.123..456A');
        $this->assertStringContainsString('2020Natur.123..456A', $result);
    }

    public function testDoiLinkContainsDoi(): void {
        $result = doi_link('10.1000/test');
        $this->assertStringContainsString('10.1000/test', $result);
    }

    public function testJstorLinkContainsId(): void {
        $result = jstor_link('12345');
        $this->assertStringContainsString('12345', $result);
    }

    public function testJstorLinkContainsJstorPrefix(): void {
        $result = jstor_link('12345');
        $this->assertStringContainsString('JSTOR', $result);
    }

    public function testWikiLinkContainsArticleName(): void {
        $result = wiki_link('Test Article');
        $this->assertStringContainsString('Test Article', $result);
    }

    #[DoesNotPerformAssertions]
    public function testReportPhaseRunsWithoutError(): void {
        report_phase('test phase');
    }

    #[DoesNotPerformAssertions]
    public function testReportActionRunsWithoutError(): void {
        report_action('test action');
    }

    #[DoesNotPerformAssertions]
    public function testReportInfoRunsWithoutError(): void {
        report_info('test info');
    }

    #[DoesNotPerformAssertions]
    public function testReportInactionRunsWithoutError(): void {
        report_inaction('test inaction');
    }

    #[DoesNotPerformAssertions]
    public function testReportWarningRunsWithoutError(): void {
        report_warning('test warning');
    }

    #[DoesNotPerformAssertions]
    public function testReportModificationRunsWithoutError(): void {
        report_modification('test modification');
    }

    #[DoesNotPerformAssertions]
    public function testReportAddRunsWithoutError(): void {
        report_add('test add');
    }

    #[DoesNotPerformAssertions]
    public function testReportForgetRunsWithoutError(): void {
        report_forget('test forget');
    }

    public function testHtmlEchoCiProducesNoOutput(): void {
        ob_start();
        html_echo('html text', 'alt text');
        $output = (string) ob_get_clean();
        // In CI mode HTML is suppressed completely
        $this->assertSame('', $output);
    }

    public function testReportPhaseRunsWithoutError2(): void {
        ob_start();
        report_phase('test phase');
        $this->assertSame('', (string) ob_get_clean());
    }

    public function testReportActionRunsWithoutError2(): void {
        ob_start();
        report_action('test action');
        $this->assertSame('', (string) ob_get_clean());
    }

    public function testReportInfoRunsWithoutError2(): void {
        ob_start();
        report_info('test info');
        $this->assertSame('', (string) ob_get_clean());
    }

    public function testReportInactionRunsWithoutError2(): void {
        ob_start();
        report_inaction('test inaction');
        $this->assertSame('', (string) ob_get_clean());
    }

    public function testReportWarningRunsWithoutError2(): void {
        ob_start();
        report_warning('test warning');
        $this->assertSame('', (string) ob_get_clean());
    }

    public function testReportModificationRunsWithoutError2(): void {
        ob_start();
        report_modification('test modification');
        $this->assertSame('', (string) ob_get_clean());
    }

    public function testReportAddRunsWithoutError2(): void {
        ob_start();
        report_add('test add');
        $this->assertSame('', (string) ob_get_clean());
    }

    public function testReportForgetRunsWithoutError2(): void {
        ob_start();
        report_forget('test forget');
        $this->assertSame('', (string) ob_get_clean());
    }

    /**
     * @return array{int, string}
     */
    private function runUserMessagesSubprocess(string $body): array {
        if (!function_exists('exec')) {
            $this->markTestSkipped('exec() is unavailable');
        }

        $user_messages = realpath(__DIR__ . '/../../../src/includes/user_messages.php');
        $this->assertNotFalse($user_messages);

        $bootstrap =
            'putenv("PUBLIC_BASE_URL=https://citations.toolforge.org");' .
            'define("CI", false);' .
            'define("HTML_OUTPUT", false);' .
            'function bot_debug_log(string $text): void {}' .
            'require_once ' . var_export($user_messages, true) . ';' .
            $body;

        $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($bootstrap);
        $output = [];
        $exit_code = -1;
        exec($command, $output, $exit_code); // phpcs:ignore

        return [$exit_code, implode("\n", $output)];
    }

    public function testMinorErrorDoesNotTerminateProductionCli(): void {
        [$exit_code, $output] = $this->runUserMessagesSubprocess(
            'report_minor_error("recoverable CLI condition"); echo "CONTINUED";'
        );

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('recoverable CLI condition', $output);
        $this->assertStringContainsString('CONTINUED', $output);
    }

    public function testFatalErrorReturnsNonZeroExitCode(): void {
        [$exit_code, $output] = $this->runUserMessagesSubprocess(
            'report_error("fatal CLI condition"); echo "UNREACHABLE";'
        );

        $this->assertContains($exit_code, [1, 255]); // Our code returns 1, but PHPUnit gives 255
        $this->assertStringContainsString('fatal CLI condition', $output);
        $this->assertStringNotContainsString('UNREACHABLE', $output);
    }
}
