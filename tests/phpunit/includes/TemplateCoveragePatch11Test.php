<?php

declare(strict_types=1);

/** Focused offline coverage for the remaining metadata acceptance and rejection paths. */
require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class TemplateCoveragePatch11Test extends testBaseClass {
    /**
     * Cover transforms as well as guards: a denied addition must preserve the
     * original field. Each fixture creates a fresh independent Template.
     *
     * @return array<string, array{string, string, string, bool}>
     */
    public static function addIfNewProvider(): array {
        return [
            'ismn normalization' => ['{{cite book}}', 'ismn', 'M123456789', true],
            'existing ismn preserved' => ['{{cite book|ismn=9790123456789}}', 'ismn', 'M987654321', false],
            'duplicate journal title' => ['{{cite journal|title=Science of Ocean Currents}}', 'journal', 'Science of Ocean Currents', false],
            'journal resists conference proceedings' => ['{{cite journal}}', 'journal', 'Conference Proceedings IEEE IEEE', false],
            'journal resists project titles' => ['{{cite journal}}', 'journal', 'Capstone Projects and Reports', false],
            'journal resists dissertations' => ['{{cite journal}}', 'journal', 'Dissertations at University', false],
            'journal resists doctoral series' => ['{{cite journal}}', 'journal', 'Doctoral Studies', false],
            'journal resists reports' => ['{{cite journal}}', 'journal', 'Report No. 20', false],
            'journal resists theses' => ['{{cite journal}}', 'journal', 'Theses and Projects', false],
            'journal resists IETF database' => ['{{cite journal}}', 'journal', 'IETF Datatracker', false],
            'journal resists proceedings' => ['{{cite journal}}', 'journal', 'International Workshop on Theory', false],
            'journal resists embedded book-title' => ['{{cite book|book-title=Proceedings}}', 'journal', 'A Journal Name', false],
            'journal resists translated work' => ['{{cite journal|script-work=zh:物理学}}', 'journal', 'Journal of Science', false],
            'journal resists electronic thesis' => ['{{cite journal}}', 'journal', 'Electronic Thesis Database', false],
            'journal resists book with chapter title and series' => [
                '{{cite book|chapter=Part One|title=Atomic Theory|series=Science Library}}', 'journal', 'Studies in Science', false,
            ],
            'journal cannot conflict with work alias' => ['{{cite journal|work=One Scientific Journal}}', 'journal', 'Another Scientific Journal', false],
            'series cannot repeat article title' => ['{{cite journal|title=The Complete Collection}}', 'series', 'The Complete Collection', false],
            'series cannot repeat journal' => ['{{cite journal|journal=Journal of Chemistry}}', 'series', 'Journal of Chemistry', false],
            'series typo placeholder rejected' => ['{{cite book}}', 'series', 'A Penguin book', false],
            'chapter cannot replace a contribution' => ['{{cite book|contribution=Overview}}', 'chapter', 'Introduction', false],
            'chapter cannot overwrite a different chapter' => ['{{cite book|chapter=Existing}}', 'chapter', 'New', false],
            'chapter cannot be imported into a press release' => ['{{cite press release}}', 'chapter', 'Chapter One', false],
            'chapter cannot be imported into a newspaper' => ['{{cite news}}', 'chapter', 'Chapter One', false],
            'citation chapter must not conflict with journal' => ['{{citation|work=Journal}}', 'chapter', 'Chapter One', false],
            'contribution cannot replace a chapter URL' => ['{{cite book|chapter-url=https://example.org/chapter}}', 'contribution', 'New', false],
            'contribution cannot replace a chapter' => ['{{cite book|chapter=Original Chapter}}', 'contribution', 'New', false],
            'citation contribution must not conflict with work' => ['{{citation|work=Journal}}', 'contribution', 'Part One', false],
            'article must not conflict with book title' => ['{{cite book|title=First|book-title=Volume}}', 'article', 'Second', false],
            'section must not conflict with work alias' => ['{{citation|work=Journal}}', 'section', 'Second', false],
            'section must not conflict with book title' => ['{{cite book|title=First|book-title=Volume}}', 'section', 'Second', false],
            'volume zero rejected' => ['{{cite journal}}', 'volume', '0', false],
            'volume Online First rejected' => ['{{cite journal}}', 'volume', 'Online First', false],
            'volume same as year rejected' => ['{{cite journal|date=2020}}', 'volume', '2020', false],
            'volume label rejected' => ['{{cite journal}}', 'volume', 'volume', false],
            'oversized volume rejected' => ['{{cite journal}}', 'volume', '123456789012345678901234', false],
            'issue zero rejected' => ['{{cite journal}}', 'issue', '0', false],
            'issue Online First rejected' => ['{{cite journal}}', 'issue', 'Online First', false],
            'issue label rejected' => ['{{cite journal}}', 'issue', 'issue', false],
            'issue 6 digits rejected' => ['{{cite journal}}', 'issue', '123456', false],
            'issue one before 1960 rejected' => ['{{cite journal|date=1940}}', 'issue', '1', false],
            'page huge number rejected' => ['{{cite journal}}', 'pages', '50001', false],
            'page gibibyte string rejected' => ['{{cite journal}}', 'pages', '20 gigabytes', false],
            'page must not replace precise locator' => ['{{cite journal|at=Figure 2}}', 'pages', '12-15', false],
            'pages must not overwrite editorial note' => ['{{cite journal|pages=see table 2}}', 'pages', '12-15', false],
            'pages must not overwrite multiple citations' => ['{{cite journal|pages=1,2}}', 'pages', '3-5', false],
            'publisher source must not be treated as title' => ['{{cite web|trans-title=Translated Title}}', 'title', 'Unrelated Title', false],
            'generic title currently accepted by metadata importer' => ['{{cite web}}', 'title', 'Archived copy', true],
            'title cannot replace an existing title' => ['{{cite web|title=The real title}}', 'title', 'Different title', false],
            'new ISSN disabled' => ['{{cite journal}}', 'issn', '0123-4567', false],
            'new forced ISSN disabled' => ['{{cite journal}}', 'issn_force', '0123-4567', false],
            'wrong access date rejected' => ['{{cite web}}', 'accessdate', 'definitely not a date', false],
            'wrong archive date rejected' => ['{{cite web}}', 'archivedate', 'definitely not a date', false],
            'year must respect publication-date' => ['{{cite journal|publication-date=2020}}', 'year', '2021', false],
            'orcid URL is not a work URL' => ['{{cite journal}}', 'url', 'https://orcid.org/0000-0002-1825-0097', false],
            'invalid URL rejected' => ['{{cite journal}}', 'url', 'not even a URL', false],
            'existing work URL preserved' => ['{{cite journal|url=https://example.org/old}}', 'url', 'https://example.org/new', false],
            'title with wiki markup blocks URL' => ['{{cite web|title=[[A linked article]]}}', 'url', 'https://example.org/new', false],
            'archive URL does not block a separate article URL' => [
                '{{cite web|archive-url=https://example.org/article}}', 'url', 'https://example.org/article', true,
            ],
        ];
    }

    #[DataProvider('addIfNewProvider')]
    public function testAddsOrRejectsMetadataWithoutNetwork(
        string $citation,
        string $field,
        string $candidate,
        bool $expected
    ): void {
        $template = $this->make_citation($citation);
        $before = $template->get2($field);
        $this->assertSame($expected, $template->add_if_new($field, $candidate));
        if ($expected) {
            $this->assertNotNull($template->get2($field), 'Accepted field must actually be populated');
        } else {
            $this->assertSame($before, $template->get2($field), 'Rejected field must be preserved');
        }
    }

    public function testNormalizesChapterAndContributionNumbers(): void {
        $chapter = $this->make_citation('{{cite book|title=An accessible book}}');
        $this->assertTrue($chapter->add_if_new('chapter', '[12] The Introduction'));
        $this->assertSame('The Introduction', $chapter->get2('chapter'));

        $contribution = $this->make_citation('{{cite book|title=An edited collection}}');
        $this->assertTrue($contribution->add_if_new('contribution', '[7] The Last Chapter'));
        $this->assertSame('The Last Chapter', $contribution->get2('contribution'));
    }

    public function testNormalizesPrefixedVolumeAndShortPageRange(): void {
        $volume = $this->make_citation('{{cite journal}}');
        $this->assertTrue($volume->add_if_new('volume', 'Volume: 012'));
        $this->assertSame('12', $volume->get2('volume'));

        $pages = $this->make_citation('{{cite journal}}');
        $this->assertTrue($pages->add_if_new('pages', '2342-5'));
        $this->assertSame('2342–2345', $pages->get2('pages'));
    }
}
