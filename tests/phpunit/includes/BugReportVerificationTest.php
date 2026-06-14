<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

/**
 * Verification tests for Phase 2 bug fixes.
 * Each test reproduces a bug from the talk page and verifies the fix.
 * Run: php -d memory_limit=1G vendor/bin/phpunit --no-configuration --bootstrap src/includes/setup.php --filter testBugReport tests/phpunit/includes/BugReportVerificationTest.php
 */
final class BugReportVerificationTest extends testBaseClass {

    // ── § Vol. cleanup ─────────────────────────────────────────────────────

    public function testBugReportVolCleanup(): void {
        $text = '{{cite journal|volume=Vol. 10}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame('10', $template->get('volume'));
    }

    // ── § Cleanup |volume=Volume xxx ───────────────────────────────────────

    public function testBugReportVolumePrefix(): void {
        $text = '{{cite book|volume=Volume 5}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame('5', $template->get('volume'));
    }

    // ── § Cleanup |pages=pp xx / § Feature request: change at=pp... to pages= ──────

    public function testBugReportPagesPpPrefix(): void {
        $text = '{{cite journal|pages=pp. 251}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame('251', $template->get('pages'));
    }

    public function testBugReportPagesPPrefix(): void {
        $text = '{{cite journal|pages=p. 42}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame('42', $template->get('pages'));
    }

    // ── § Fix ISSN with lowercase x, not hyphen instead of hyphen ──────────

    public function testBugReportIssnLowercaseX(): void {
        $text = '{{cite journal|issn=1234-567x}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame('1234-567X', $template->get('issn'));
    }

    public function testBugReportIssnNonBreakingHyphen(): void {
        $text = sprintf('{{cite journal|issn=1234%s5678}}', "\u{2011}");
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame('1234-5678', $template->get('issn'));
    }

    public function testBugReportIssnEmDash(): void {
        $text = sprintf('{{cite journal|issn=1234%s5678}}', "\u{2014}");
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame('1234-5678', $template->get('issn'));
    }

    // ── § TNT volume/issue/pages=Online first/Onlinefirst ──────────────────

    public function testBugReportOnlineFirstVolume(): void {
        $text = '{{cite journal|volume=Online First}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('volume'));
        $this->assertFalse($template->has('volume'));
    }

    public function testBugReportOnlineFirstIssue(): void {
        $text = '{{cite journal|issue=Onlinefirst}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('issue'));
    }

    public function testBugReportOnlineFirstPages(): void {
        $text = '{{cite journal|pages=Online First}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('pages'));
    }

    // ── § More Things to TNT ───────────────────────────────────────────────

    public function testBugReportPubMedInWebsite(): void {
        $text = '{{cite journal|website=PubMed|pmid=12345}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('website'));
    }

    public function testBugReportPMCInWork(): void {
        $text = '{{cite journal|work=PMC|pmc=PMC12345}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('work'));
    }

    public function testBugReportNIHInWork(): void {
        $text = '{{cite journal|work=NIH|pmid=1}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('work'));
    }

    public function testBugReportNationalLibraryOfMedicine(): void {
        $text = '{{cite journal|website=National Library of Medicine|pmid=1}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('website'));
    }

    // ── § https://www.taylorfrancis.com/chapters/... is a chapter url ─────

    public function testBugReportTaylorFrancisChapterUrl(): void {
        $text = '{{cite book|chapter=Test Chapter|url=https://www.taylorfrancis.com/chapters/edit/10.4324/9781003000000-1/test}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('url'));
        $this->assertTrue($template->has('chapter-url'));
    }

    // ── § |contribution-url= vs |chapter-url= ──────────────────────────────

    public function testBugReportNoDuplicateChapterWhenContributionExists(): void {
        $text = '{{citation|contribution=My Chapter|contribution-url=https://example.com}}';
        $template = $this->make_citation($text);
        $result = $template->add_if_new('chapter', 'Test Chapter');
        $this->assertFalse($result);
        $this->assertFalse($template->has('chapter'));
    }

    // ── § bot adds |chapter= when template already has |contribution= ──────

    public function testBugReportNoDuplicateContributionWhenChapterExists(): void {
        $text = '{{citation|chapter=My Chapter|chapter-url=https://example.com}}';
        $template = $this->make_citation($text);
        $result = $template->add_if_new('contribution', 'Test');
        $this->assertFalse($result);
        $this->assertFalse($template->has('contribution'));
    }

    // ── § adds |chapter= to a {{citation}} template with |journal= ─────────

    public function testBugReportNoChapterWhenJournalInCitation(): void {
        $text = '{{citation|journal=Nature|title=Test Article}}';
        $template = $this->make_citation($text);
        $result = $template->add_if_new('chapter', 'Some Chapter');
        $this->assertFalse($result);
        $this->assertFalse($template->has('chapter'));
    }

    // ── § cookieAbsent ────────────────────────────────────────────────────

    public function testBugReportCookieAbsent(): void {
        $text = '{{cite journal|doi=10.1234/test|title=cookieAbsent}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('title'));
    }

    // ── § Adds cs1-formatted reference to CS2 article ─────────────────────

    public function testBugReportCS2ModeBareURL(): void {
        $page = $this->process_page('{{cs1 config|mode=cs2}}
<ref>https://doi.org/10.1007/s12668-011-0022-5</ref>');
        $parsed = $page->parsed_text();
        if (mb_strpos($parsed, '|doi=10.1007/s12668-011-0022-5') !== false
            && mb_strpos($parsed, '|title=') !== false) {
            $this->assertStringNotContainsString('{{cite web', $parsed);
        } else {
            $this->markTestSkipped('CrossRef/DOI expansion did not respond (rate limit or outage)');
        }
    }

    // ── § Cosmetic? / whitespace-only edits ────────────────────────────────

    public function testBugReportCosmeticWhitespaceOnlyNotEdit(): void {
        $page = new TestPage();
        $page->parse_text('{{cite web|url=https://example.com|title=Hello}}');
        $page->overwrite_text('{{cite web|url=https://example.com|title=Hello  }}');
        $this->assertFalse($page->expand_text());
    }

    // ── § Pointless whitespace-only edit / § Bot edit did nothing but add one space

    public function testBugReportWhitespaceBeforeCloseBraces(): void {
        $page = new TestPage();
        $page->parse_text('{{cite book|title=Test}}');
        $page->overwrite_text('{{cite book|title=Test }}');
        $this->assertFalse($page->expand_text());
    }

    // ── § COSMETICBOT changing last/first to last1/first1 ──────────────────

    public function testBugReportCosmeticRenumberingSingleAuthor(): void {
        $page = new TestPage();
        $page->parse_text('{{cite web|url=https://example.com|title=Hi|last=Smith|first=J}}');
        $page->overwrite_text('{{cite web|url=https://example.com|title=Hi|last1=Smith|first1=J}}');
        $this->assertFalse($page->expand_text());
    }

    // ── § cite biorxiv cosmetic edit ───────────────────────────────────────

    public function testBugReportBiorxivCaseOnly(): void {
        $page = new TestPage();
        $page->parse_text('{{cite biorxiv|title=Test}}');
        $page->overwrite_text('{{cite BioRxiv|title=Test}}');
        $this->assertFalse($page->expand_text());
    }

    // ── § Only removing a single empty parameter ───────────────────────────

    public function testBugReportBlankParamOnlyNotEdit(): void {
        $page = new TestPage();
        $page->parse_text('{{cite web|url=https://example.com|title=Hello}}');
        $page->overwrite_text('{{cite web|url=https://example.com|title=Hello|date=}}');
        $this->assertFalse($page->expand_text());
    }
}
