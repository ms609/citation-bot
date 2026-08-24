<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class JstorRisCoverageTest extends testBaseClass {

    /**
     * @return array{Template, string}
     */
    private function parseRis(
        string $ris,
        string $citation = '{{cite journal}}',
        bool $addUrl = false
    ): array {
        $template = $this->make_citation($citation);
        expand_by_RIS($template, $ris, $addUrl);

        return [$template, $ris];
    }

    public function testChapterRisMapsT1ToChapterAndT2ToTitle(): void {
        $ris = <<<RIS
TY - CHAP
T1 - The Chapter Title
T2 - The Book Title
ER -
RIS;

        [$template] = $this->parseRis($ris, '{{cite book}}');

        $this->assertSame(
            'The Chapter Title',
            $template->get2('chapter')
        );
        $this->assertSame(
            'The Book Title',
            $template->get2('title')
        );
    }

    public function testChapterRisMapsTiToChapterWhenT2Exists(): void {
        $ris = <<<RIS
TY - CHAP
TI - The Chapter Title
T2 - The Book Title
ER -
RIS;

        [$template] = $this->parseRis($ris, '{{cite book}}');

        $this->assertSame(
            'The Chapter Title',
            $template->get2('chapter')
        );
        $this->assertSame(
            'The Book Title',
            $template->get2('title')
        );
    }

    public function testFullBookIgnoresT1AndUsesT2AsTitle(): void {
        $ris = <<<RIS
TY - BOOK
T1 - Likely Subtitle
T2 - Main Book Title
ER -
RIS;

        [$template] = $this->parseRis($ris, '{{cite book}}');

        $this->assertSame(
            'Main Book Title',
            $template->get2('title')
        );
        $this->assertNull($template->get2('chapter'));
    }

    public function testBookBtBecomesTitle(): void {
        $ris = <<<RIS
TY - CHAP
BT - Container Book Title
ER -
RIS;

        [$template] = $this->parseRis($ris, '{{cite book}}');

        $this->assertSame(
            'Container Book Title',
            $template->get2('title')
        );
    }

    public function testJournalBtBecomesJournal(): void {
        $ris = <<<RIS
TY - JOUR
BT - Journal From BT
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame(
            'Journal from Bt',
            $template->get2('journal')
        );
    }

    public function testJournalJoBecomesJournal(): void {
        $ris = <<<RIS
TY - JOUR
JO - Journal From JO
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame(
            'Journal from Jo',
            $template->get2('journal')
        );
    }

    public function testIssueIsImported(): void {
        $ris = <<<RIS
TY - JOUR
IS - 17
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame('17', $template->get2('issue'));
    }

    public function testMultipleAuthorsAreImportedInOrder(): void {
        $ris = <<<RIS
TY - JOUR
AU - Smith, John
AU - Jones, Alice
AU - Brown, Robert
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame(
            'Smith',
            $template->get2('last1')
        );
        $this->assertSame(
            'Jones',
            $template->get2('last2')
        );
        $this->assertSame(
            'Brown',
            $template->get2('last3')
        );
        $this->assertSame(
            'John',
            $template->get2('first1')
        );
        $this->assertSame(
            'Alice',
            $template->get2('first2')
        );
        $this->assertSame(
            'Robert',
            $template->get2('first3')
        );
    }

    public function testY1BecomesDate(): void {
        $ris = <<<RIS
TY - JOUR
Y1 - 2024
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame('2024', $template->get2('date'));
    }

    public function testReviewTitleIsUsedWhenNoRealTitleExists(): void {
        $ris = <<<RIS
TY - JOUR
RI - The Reviewed Book
ER -
RIS;

        [$template, $remainingRis] = $this->parseRis($ris);

        $this->assertSame(
            'Reviewed work: The Reviewed Book',
            $template->get2('title')
        );

        // RI is consumed separately after parsing.
        $this->assertStringNotContainsString(
            'RI - The Reviewed Book',
            $remainingRis
        );
    }

    public function testReviewTitleDoesNotOverwriteExistingTitle(): void {
        $ris = <<<RIS
TY - JOUR
TI - Existing Article Title
RI - The Reviewed Book
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame(
            'Existing Article Title',
            $template->get2('title')
        );
    }

    public function testSnContainingIsbnIsAddedAsIsbn(): void {
        $ris = <<<RIS
TY - BOOK
SN - 155404295X
ER -
RIS;

        [$template, $remainingRis] = $this->parseRis(
            $ris,
            '{{cite book}}'
        );

        // add_if_new() applies the normal ISBN formatting.
        $this->assertSame(
            '1-55404-295-X',
            $template->get2('isbn')
        );
        $this->assertNull($template->get2('issn'));

        $this->assertStringNotContainsString(
            'SN - 155404295X',
            $remainingRis
        );
    }

    public function testSnContainingIssnIsNotAdded(): void {
        $ris = <<<RIS
TY - JOUR
SN - 1234-5678
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertNull($template->get2('isbn'));
        $this->assertNull($template->get2('issn'));
    }

    public function testUrlIsIgnoredWhenAddUrlIsFalse(): void {
        $ris = 'UR - https://example.org/article';

        [$template, $remainingRis] = $this->parseRis(
            $ris,
            '{{cite journal}}',
            false
        );

        $this->assertNull($template->get2('url'));

        // The RIS entry is nevertheless considered handled.
        $this->assertSame('', $remainingRis);
    }

    public function testUrlIsAddedWhenAddUrlIsTrue(): void {
        $ris = 'UR - https://example.org/article';

        [$template, $remainingRis] = $this->parseRis(
            $ris,
            '{{cite journal}}',
            true
        );

        $this->assertSame(
            'https://example.org/article',
            $template->get2('url')
        );
        $this->assertSame('', $remainingRis);
    }

    public function testUrlDoesNotOverwriteExistingUrl(): void {
        $ris = 'UR - https://new.example/article';

        [$template] = $this->parseRis(
            $ris,
            '{{cite journal|url=https://existing.example/article}}',
            true
        );

        $this->assertSame(
            'https://existing.example/article',
            $template->get2('url')
        );
    }

    public function testBookPublisherIsAdded(): void {
        $ris = <<<RIS
TY - BOOK
PB - Example Academic Press
ER -
RIS;

        [$template, $remainingRis] = $this->parseRis(
            $ris,
            '{{cite book}}'
        );

        $this->assertSame(
            'Example Academic Press',
            $template->get2('publisher')
        );

        $this->assertStringNotContainsString(
            'PB - Example Academic Press',
            $remainingRis
        );
    }

    public function testPublisherIsAddedWhenJournalIsAbsent(): void {
        $ris = <<<RIS
TY - JOUR
PB - Example Publisher
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame(
            'Example Publisher',
            $template->get2('publisher')
        );
    }

    public function testPublisherIsNotAddedWhenJournalExists(): void {
        $ris = <<<RIS
TY - JOUR
JF - Example Journal
PB - Example Publisher
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame(
            'Example Journal',
            $template->get2('journal')
        );
        $this->assertNull($template->get2('publisher'));
    }

    public function testPublisherIsAddedForBookEvenWhenJournalExists(): void {
        $ris = <<<RIS
TY - CHAP
JF - Some Journal-Like Container
PB - Example Book Publisher
ER -
RIS;

        [$template] = $this->parseRis(
            $ris,
            '{{cite book}}'
        );

        $this->assertSame(
            'Example Book Publisher',
            $template->get2('publisher')
        );
    }

    public function testSingleStartPageBecomesPage(): void {
        $ris = <<<RIS
TY - JOUR
SP - 42
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame('42', $template->get2('page'));
    }

    public function testEqualStartAndEndPageBecomesSinglePage(): void {
        $ris = <<<RIS
TY - JOUR
SP - 42
EP - 42
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame('42', $template->get2('page'));
    }

    public function testEndPageMayAppearBeforeStartPage(): void {
        $ris = <<<RIS
TY - JOUR
EP - 49
SP - 42
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame('42–49', $template->get2('pages'));
    }

    public function testBadStartPageAloneDoesNotSuppressRange(): void {
        $ris = <<<RIS
TY - JOUR
SP - i
EP - 25
ER -
RIS;

        [$template] = $this->parseRis($ris);

        // The implementation suppresses the range only when BOTH
        // the suspicious SP and suspicious EP conditions are true.
        $this->assertSame('i–25', $template->get2('pages'));
    }

    public function testBadEndPageAloneDoesNotSuppressRange(): void {
        $ris = <<<RIS
TY - JOUR
SP - 25
EP - 999
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame('25–999', $template->get2('pages'));
    }

    public function testInvalidDoiIsNotAdded(): void {
        $ris = <<<RIS
TY - JOUR
DO - NOT_A_DOI
ER -
RIS;

        [$template, $remainingRis] = $this->parseRis($ris);

        $this->assertNull($template->get2('doi'));

        // Since the DOI was rejected, this RIS field was not consumed.
        $this->assertStringContainsString(
            'DO - NOT_A_DOI',
            $remainingRis
        );
    }

    public function testHtmlEntitiesAreDecodedBeforeRisParsing(): void {
        $ris = <<<RIS
TY - JOUR
TI - Fish &amp; Chips
JF - Research &amp; Development
ER -
RIS;

        [$template] = $this->parseRis($ris);

        $this->assertSame(
            'Fish & Chips',
            $template->get2('title')
        );
        $this->assertSame(
            'Research & Development',
            $template->get2('journal')
        );
    }

    public function testIgnoredRisFieldsAreRemovedFromInput(): void {
        $ris = <<<RIS
TY - JOUR
M3 - Some miscellaneous value
N1 - Note one
N2 - Note two
KW - keyword
T3 - Subtitle
A2 - Secondary Author
A3 - Tertiary Author
ET - Second edition
LA - English
DA - 2024-01-01
CY - London
CR - Some citation
TT - Translated title
C1 - Address
DB - Database
AB - Abstract
H1 - Header
Y2 - 2025
ER -
RIS;

        [$template, $remainingRis] = $this->parseRis($ris);

        $this->assertSame(
            '{{cite journal}}',
            $template->parsed_text()
        );

        // All of these fields are explicitly discarded.
        $this->assertSame('', $remainingRis);
    }

    public function testMalformedRisLineIsIgnoredAndRemoved(): void {
        $ris = <<<RIS
This is not a valid RIS line
TY - JOUR
ER -
RIS;

        [$template, $remainingRis] = $this->parseRis($ris);

        $this->assertSame(
            '{{cite journal}}',
            $template->parsed_text()
        );
        $this->assertSame('', $remainingRis);
    }

    public function testBadAcceptedManuscriptTitleAbortsEntireImport(): void {
        $ris = <<<RIS
TY - JOUR
TI - oup accepted manuscript
AU - Smith, John
VL - 42
IS - 7
ER -
RIS;

        [$template, $remainingRis] = $this->parseRis($ris);

        $this->assertNull($template->get2('title'));
        $this->assertNull($template->get2('author1'));
        $this->assertNull($template->get2('volume'));
        $this->assertNull($template->get2('issue'));

        // Early return happens before the RIS input is consumed.
        $this->assertSame($ris, $remainingRis);
    }
}
