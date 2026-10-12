<?php

declare(strict_types=1);

/** Offline coverage for rejecting incompatible or misleading citation metadata. */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class TemplateCoveragePatch10Test extends testBaseClass {
    /**
     * Each case starts with an independently parsed citation, never a live API.
     * Assert the return value and the original metadata where it already exists.
     *
     * @param string $citation
     * @param string $field
     * @param string $candidate
     * @param array<string, string> $preserved
     */
    #[DataProvider('unsafeMetadataProvider')]
    public function testRejectsUnsafeMetadata(
        string $citation,
        string $field,
        string $candidate,
        array $preserved = []
    ): void {
        $template = $this->make_citation($citation);
        $original_field = $template->get2($field);
        $this->assertFalse($template->add_if_new($field, $candidate));
        $this->assertSame($original_field, $template->get2($field), 'A rejected field must remain unchanged');
        foreach ($preserved as $name => $value) {
            $this->assertSame($value, $template->get2($name));
        }
    }

    /** @return array<string, array{string, string, string, array<string, string>}> */
    public static function unsafeMetadataProvider(): array {
        return [
            'translated title takes precedence' => [
                '{{cite web|trans-title=The Original}}', 'title', 'A different title', ['trans-title' => 'The Original'],
            ],
            'book title blocks an unverified article title' => [
                '{{cite web|book-title=A Book}}', 'title', 'Another Title', ['book-title' => 'A Book'],
            ],
            'nonblank title is preserved' => [
                '{{cite web|title=Correct Title}}', 'title', 'Wrong Title', ['title' => 'Correct Title'],
            ],
            'same journal and article title' => [
                '{{cite journal|title=Quantum Experiments}}', 'journal', 'Quantum Experiments',
                ['title' => 'Quantum Experiments'],
            ],
            'translation blocks a competing journal' => [
                '{{cite journal|trans-work=Translated Journal}}', 'journal', 'Other Journal',
                ['trans-work' => 'Translated Journal'],
            ],
            'book-title blocks a competing journal' => [
                '{{cite journal|book-title=Proceedings}}', 'journal', 'Another Journal',
                ['book-title' => 'Proceedings'],
            ],
            'repository not mistaken for journal' => [
                '{{cite journal}}', 'journal', 'Wiley Online Library', [],
            ],
            'conference not mistaken for journal' => [
                '{{cite journal}}', 'journal', 'International Workshop on Databases', [],
            ],
            'article title not repeated as series' => [
                '{{cite journal|title=Physics Bulletin}}', 'series', 'Physics Bulletin',
                ['title' => 'Physics Bulletin'],
            ],
            'journal not repeated as series' => [
                '{{cite journal|journal=Physics Bulletin}}', 'series', 'Physics Bulletin',
                ['journal' => 'Physics Bulletin'],
            ],
            'generic Penguin book series rejected' => [
                '{{cite book}}', 'series', 'A Penguin book', [],
            ],
            'news does not accept book-only chapter' => [
                '{{cite news}}', 'chapter', 'Chapter One', [],
            ],
            'magazine does not accept book-only chapter' => [
                '{{cite magazine}}', 'chapter', 'Chapter One', [],
            ],
            'existing contribution blocks chapter' => [
                '{{cite web|contribution=Introduction}}', 'chapter', 'New Chapter',
                ['contribution' => 'Introduction'],
            ],
            'existing chapter blocks contribution' => [
                '{{cite book|chapter=Introduction}}', 'contribution', 'New Contribution',
                ['chapter' => 'Introduction'],
            ],
            'journal work blocks citation contribution' => [
                '{{citation|work=Journal}}', 'contribution', 'New Contribution', ['work' => 'Journal'],
            ],
            'journal work blocks citation article' => [
                '{{citation|work=Journal}}', 'article', 'New Article', ['work' => 'Journal'],
            ],
            'book-title blocks contribution' => [
                '{{cite book|title=Article|book-title=Book}}', 'contribution', 'New Contribution',
                ['book-title' => 'Book'],
            ],
            'book-title blocks section' => [
                '{{cite book|title=Article|book-title=Book}}', 'section', 'New Section',
                ['book-title' => 'Book'],
            ],
            'do not replace explicit page locator' => [
                '{{cite journal|at=Figure 2}}', 'pages', '12–24', ['at' => 'Figure 2'],
            ],
            'oversized page number rejected' => [
                '{{cite journal}}', 'pages', '60000', [],
            ],
            'volume zero rejected' => [
                '{{cite journal}}', 'volume', '0', [],
            ],
            'Online First is not a volume' => [
                '{{cite journal}}', 'volume', 'Online First', [],
            ],
            'volume is not a volume number' => [
                '{{cite journal}}', 'volume', 'volume', [],
            ],
            'overlong volume rejected' => [
                '{{cite journal}}', 'volume', '123456789012345678901', [],
            ],
            'issue zero rejected' => [
                '{{cite journal}}', 'issue', '0', [],
            ],
            'Online First is not an issue' => [
                '{{cite journal}}', 'issue', 'Online First', [],
            ],
            'issue label is not an issue number' => [
                '{{cite journal}}', 'issue', 'issue', [],
            ],
            'six-digit issue is invalid' => [
                '{{cite journal}}', 'issue', '123456', [],
            ],
            'modern issue one not backported to old journal' => [
                '{{cite journal|date=1948}}', 'issue', '1', ['date' => '1948'],
            ],
            'new ISSN additions disabled' => [
                '{{cite journal}}', 'issn', '1234-5678', [],
            ],
            'forced new ISSN additions disabled' => [
                '{{cite journal}}', 'issn_force', '1234-5678', [],
            ],
            'invalid access date is rejected' => [
                '{{cite web}}', 'access-date', 'not-a-date', [],
            ],
            'invalid archive date is rejected' => [
                '{{cite web}}', 'archive-date', 'not-a-date', [],
            ],
            'existing publication date takes precedence' => [
                '{{cite journal|publication-date=2016}}', 'date', '2016', ['publication-date' => '2016'],
            ],
            'existing publication date takes precedence over year' => [
                '{{cite journal|publication-date=2016}}', 'year', '2016', ['publication-date' => '2016'],
            ],
            'expired embargo cannot be added' => [
                '{{cite journal}}', 'pmc-embargo-date', '1999-01-01', [],
            ],
            'non-year embargo cannot be added' => [
                '{{cite journal}}', 'pmc-embargo-date', 'not-an-embargo', [],
            ],
        ];
    }

    public function testSafeChapterAndIssueStillCanBeAdded(): void {
        $chapter = $this->make_citation('{{cite book|title=An Encyclopedia}}');
        $this->assertTrue($chapter->add_if_new('chapter', 'Introduction'));
        $this->assertSame('Introduction', $chapter->get2('chapter'));

        $issue = $this->make_citation('{{cite journal|title=An Article}}');
        $this->assertTrue($issue->add_if_new('issue', '3'));
        $this->assertSame('3', $issue->get2('issue'));
    }
}
