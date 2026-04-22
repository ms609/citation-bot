<?php
declare(strict_types=1);

/*
 * Tests for user_messages.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

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

    public function testReportPhaseRunsWithoutError(): void {
        report_phase('test phase');
        $this->assertFaker();
    }

    public function testReportActionRunsWithoutError(): void {
        report_action('test action');
        $this->assertFaker();
    }

    public function testReportInfoRunsWithoutError(): void {
        report_info('test info');
        $this->assertFaker();
    }

    public function testReportInactionRunsWithoutError(): void {
        report_inaction('test inaction');
        $this->assertFaker();
    }

    public function testReportWarningRunsWithoutError(): void {
        report_warning('test warning');
        $this->assertFaker();
    }

    public function testReportModificationRunsWithoutError(): void {
        report_modification('test modification');
        $this->assertFaker();
    }

    public function testReportAddRunsWithoutError(): void {
        report_add('test add');
        $this->assertFaker();
    }

    public function testReportForgetRunsWithoutError(): void {
        report_forget('test forget');
        $this->assertFaker();
    }

    public function testHtmlEchoCiProducesNoOutput(): void {
        ob_start();
        html_echo('html text', 'alt text');
        $output = (string) ob_get_clean();
        // In CI mode HTML is suppressed completely
        $this->assertSame('', $output);
    }
}
