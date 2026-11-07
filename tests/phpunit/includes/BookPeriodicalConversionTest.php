<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class BookPeriodicalConversionTest extends testBaseClass {
    private const string PROCEEDINGS_TITLE = 'Proceedings. Structure in Complexity Theory Fourth Annual Conference (Cat. No.89CH2745-8)';
    private const string PROCEEDINGS_JOURNAL = '[1989] Proceedings. Structure in Complexity Theory Fourth Annual Conference';

    #[DataProvider('reportedCitations')]
    public function testReportedConferenceCitationsConvertInOnePass(
        string $doi, string $title, string $container, string $isbn, string $book_title, ?string $chapter
    ): void {
        $template = $this->make_citation('{{Cite journal|title=' . $title . '|journal=' . $container .
            '|doi=' . $doi . '|isbn=' . $isbn . '}}');

        $template->change_name_to('cite book');

        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame($book_title, $template->get('title'));
        $this->assertSame($chapter, $template->get2('chapter'));
        $this->assertNull($template->get2('journal'));
        $this->assertSame($isbn, $template->get('isbn'));
        $this->assertSame($doi, $template->get('doi'));
        $output = $template->parsed_text();
        $template->change_name_to('cite book');
        $this->assertSame($output, $template->parsed_text());
    }

    /** @return array<string, array{string, string, string, string, string, ?string}> */
    public static function reportedCitations(): array {
        $colt = "Proceedings of the sixth annual conference on Computational learning theory - COLT '93";
        $sfcs = 'The 43rd Annual IEEE Symposium on Foundations of Computer Science, 2002. Proceedings.';
        $ccc = "21st Annual IEEE Conference on Computational Complexity (CCC'06)";
        return [
            'whole SCT proceedings' => [
                '10.1109/sct.1989.41807', self::PROCEEDINGS_TITLE, self::PROCEEDINGS_JOURNAL,
                '0-8186-1958-9', self::PROCEEDINGS_TITLE, null,
            ],
            'COLT paper' => [
                '10.1145/168304.168311', 'Parameterized learning complexity', $colt,
                '0-89791-611-5', $colt, 'Parameterized learning complexity',
            ],
            'SFCS paper' => [
                '10.1109/sfcs.2002.1181978', 'The parameterized complexity of counting problems', $sfcs,
                '0-7695-1822-2', $sfcs, 'The parameterized complexity of counting problems',
            ],
            'CCC paper' => [
                '10.1109/ccc.2006.8', 'An Isomorphism between Subexponential and Parameterized Complexity Theory', $ccc,
                '0-7695-2549-0', $ccc, 'An Isomorphism between Subexponential and Parameterized Complexity Theory',
            ],
        ];
    }

    #[DataProvider('reportedCitations')]
    public function testRepairsPreviouslyConvertedBook(
        string $doi, string $title, string $container, string $isbn, string $book_title, ?string $chapter
    ): void {
        $template = $this->make_citation('{{Cite book|title=' . $title . '|journal=' . $container .
            '|doi=' . $doi . '|isbn=' . $isbn . '}}');

        $template->tidy_parameter('journal');

        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame($book_title, $template->get('title'));
        $this->assertSame($chapter, $template->get2('chapter'));
        $this->assertNull($template->get2('journal'));
        $this->assertSame($doi, $template->get('doi'));
        $this->assertSame($isbn, $template->get('isbn'));
        $output = $template->parsed_text();
        $template->tidy_parameter('journal');
        $this->assertSame($output, $template->parsed_text());
    }

    public function testExistingChapterBlocksLegacyBookRepair(): void {
        $input = '{{cite book|title=Edited Volume|chapter=Existing Chapter|journal=Conference Proceedings' .
            '|doi=10.1145/168304.168311|isbn=0-89791-611-5}}';
        $template = $this->make_citation($input);
        $template->tidy_parameter('journal');

        $this->assertSame($input, $template->parsed_text());
    }

    public function testOrdinaryJournalIsNotReclassifiedAsProceedings(): void {
        $input = '{{cite book|title=Example|journal=IEEE Transactions on Computers' .
            '|doi=10.1109/example|isbn=0-8186-1958-9}}';
        $template = $this->make_citation($input);
        $template->tidy_parameter('journal');

        $this->assertSame($input, $template->parsed_text());
    }

    public function testKnownSeriesCanStillMoveFromPeriodicalToSeries(): void {
        $template = $this->make_citation('{{cite journal|title=Example|periodical=Lecture Notes in Computer Science}}');

        $template->tidy_parameter('periodical');

        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('Lecture Notes in Computer Science', $template->get('series'));
        $this->assertNull($template->get2('periodical'));
    }

    public function testDeclinedBookSeriesConversionKeepsThePeriodical(): void {
        $template = $this->make_citation('{{cite journal|title=Example|periodical=Lecture Notes in Computer Science|trans-periodical=Translation}}');

        $template->tidy_parameter('periodical');

        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Lecture Notes in Computer Science', $template->get('periodical'));
        $this->assertSame('Translation', $template->get('trans-periodical'));
        $this->assertNull($template->get2('series'));
    }

    public function testDeclinedConversionDoesNotRemoveTheBaseForTranslatedJournal(): void {
        $template = $this->make_citation('{{cite journal|title=Example|journal=Lecture Notes in Computer Science' .
            '|series=Lecture Notes in Computer Science|trans-journal=Translation}}');

        $template->tidy_parameter('series');

        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Lecture Notes in Computer Science', $template->get('journal'));
        $this->assertSame('Translation', $template->get('trans-journal'));
    }

    #[DataProvider('unmappableContainers')]
    public function testDeclinesConversionThatWouldStrandPeriodicalFields(string $fields): void {
        $input = '{{cite journal' . $fields . '}}';
        $template = $this->make_citation($input);

        $template->change_name_to('cite book', true, true);

        $this->assertSame($input, $template->parsed_text());
    }

    /** @return array<string, array{string}> */
    public static function unmappableContainers(): array {
        return [
            'periodical alias' => ['|title=Paper|periodical=Publication'],
            'magazine alias' => ['|title=Paper|magazine=Publication'],
            'newspaper alias' => ['|title=Paper|newspaper=Publication'],
            'script journal' => ['|title=Paper|script-journal=zh:期刊'],
            'script work' => ['|title=Paper|script-work=zh:期刊'],
            'translated journal alongside base' => ['|title=Paper|journal=J|trans-journal=Translation'],
            'translated website' => ['|title=Paper|website=Site|trans-website=Translation'],
            'work without a chapter title' => ['|work=Publication'],
        ];
    }
}
