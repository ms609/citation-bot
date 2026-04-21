<?php
declare(strict_types=1);

/*
 * Tests for user_messages.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class UserMessagesTest extends testBaseClass {

    // ======================== echoable() ========================

    public function testEchoablePlainText(): void {
        new TestPage();
        $this->assertSame('hello world', echoable('hello world'));
    }

    public function testEchoableNull(): void {
        new TestPage();
        $this->assertSame('', echoable(null));
    }

    public function testEchoableEmptyString(): void {
        new TestPage();
        $this->assertSame('', echoable(''));
    }

    // In CI mode HTML_OUTPUT is false, so echoable() returns the string unchanged
    public function testEchoableAngleBracketsNotEscapedInCi(): void {
        new TestPage();
        $this->assertSame('<test>', echoable('<test>'));
    }

    // ======================== pubmed_link() ========================

    public function testPubmedLinkPmidContainsId(): void {
        new TestPage();
        $result = pubmed_link('pmid', '12345678');
        $this->assertStringContainsString('12345678', $result);
    }

    public function testPubmedLinkPmcContainsId(): void {
        new TestPage();
        $result = pubmed_link('pmc', '12345');
        $this->assertStringContainsString('12345', $result);
    }

    public function testPubmedLinkPmidUppercasesIdentifierLabel(): void {
        new TestPage();
        // In non-HTML (CI) mode the result is "PMID 12345678"
        $result = pubmed_link('pmid', '12345678');
        $this->assertStringContainsString('PMID', $result);
    }

    // ======================== bibcode_link() ========================

    public function testBibcodeLinkContainsBibcode(): void {
        new TestPage();
        $result = bibcode_link('2020Natur.123..456A');
        $this->assertStringContainsString('2020Natur.123..456A', $result);
    }

    // ======================== doi_link() ========================

    public function testDoiLinkContainsDoi(): void {
        new TestPage();
        $result = doi_link('10.1000/test');
        $this->assertStringContainsString('10.1000/test', $result);
    }

    // ======================== jstor_link() ========================

    public function testJstorLinkContainsId(): void {
        new TestPage();
        $result = jstor_link('12345');
        $this->assertStringContainsString('12345', $result);
    }

    public function testJstorLinkContainsJstorPrefix(): void {
        new TestPage();
        $result = jstor_link('12345');
        $this->assertStringContainsString('JSTOR', $result);
    }

    // ======================== wiki_link() ========================

    public function testWikiLinkContainsArticleName(): void {
        new TestPage();
        $result = wiki_link('Test Article');
        $this->assertStringContainsString('Test Article', $result);
    }

    // ======================== report_* functions — no-crash in CI ========================

    public function testReportPhaseRunsWithoutError(): void {
        new TestPage();
        report_phase('test phase');
        $this->assertFaker();
    }

    public function testReportActionRunsWithoutError(): void {
        new TestPage();
        report_action('test action');
        $this->assertFaker();
    }

    public function testReportInfoRunsWithoutError(): void {
        new TestPage();
        report_info('test info');
        $this->assertFaker();
    }

    public function testReportInactionRunsWithoutError(): void {
        new TestPage();
        report_inaction('test inaction');
        $this->assertFaker();
    }

    public function testReportWarningRunsWithoutError(): void {
        new TestPage();
        report_warning('test warning');
        $this->assertFaker();
    }

    public function testReportModificationRunsWithoutError(): void {
        new TestPage();
        report_modification('test modification');
        $this->assertFaker();
    }

    public function testReportAddRunsWithoutError(): void {
        new TestPage();
        report_add('test add');
        $this->assertFaker();
    }

    public function testReportForgetRunsWithoutError(): void {
        new TestPage();
        report_forget('test forget');
        $this->assertFaker();
    }

    // ======================== html_echo() ========================

    public function testHtmlEchoCiProducesNoOutput(): void {
        new TestPage();
        ob_start();
        html_echo('html text', 'alt text');
        $output = (string) ob_get_clean();
        // In CI mode HTML is suppressed completely
        $this->assertSame('', $output);
    }
}
