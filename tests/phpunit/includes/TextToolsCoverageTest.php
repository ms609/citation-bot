<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class TextToolsCoverageTest extends testBaseClass {

    public function testWikifyExternalTextWrapsUnsupportedMathTables(): void {
        $this->assertSame(
            '<nowiki><mtable>x</mtable></nowiki>',
            wikify_external_text('<mtable>x</mtable>')
        );
    }

    public function testSanitizeStringCanonicalizesScienceJournalName(): void {
        $this->assertSame('Science', sanitize_string(' Science (New York, N.Y.) '));
    }

    public function testStraightenQuotesHandlesMatchingOuterGuillemets(): void {
        $this->assertSame('Â«inner Â» sameÂ«', straighten_quotes('Â«inner Â» sameÂ«', false));
    }

    public function testTitleCapitalizationCoversSpecialCases(): void {
        $this->assertSame("Ac's", title_capitalization("AC'S", true));
        $this->assertSame('This ppm Code', title_capitalization('This PPM Code', true));
        $this->assertSame('This-ppm, Code', title_capitalization('This-PPM, Code', true));
        $this->assertSame('Journal Series A Notes', title_capitalization('Journal Series a Notes', true));
        $this->assertSame('netWorker', title_capitalization('NetWorker', true));
        $this->assertSame('MELUS', title_capitalization('Melus', true));
    }

    public function testTidyDatePadsSingleDigitSlashDates(): void {
        $this->assertSame('2011-01-30', tidy_date('30/1/2011'));
        $this->assertSame('2011-01-01', tidy_date('1/1/2011'));
    }

    public function testTidyDateRejectsImplausibleNumericYears(): void {
        $this->assertSame('', tidy_date('99999'));
        $this->assertSame('', tidy_date('00'));
    }

    public function testTidyDateExpandsOlderTwoDigitYear(): void {
        $this->assertSame('1999-04-29', tidy_date('07:30 , 04.29.99'));
    }

    public function testAddIsbnDashesFormatsTenDigitInput(): void {
        $this->assertSame('0-306-40615-2', addISBNdashes('0306406152'));
        $this->assertSame('9999999999999', addISBNdashes('9999999999999'));
    }

    public function testChangeIsbnRejectsMalformedTenDigitInput(): void {
        $this->assertSame('-123456789X', changeisbn10Toisbn13('-123456789X', 2007));
        $this->assertSame('12345X7890', changeisbn10Toisbn13('12345X7890', 2007));
    }

    public function testCleanVolumeRejectsMonthNames(): void {
        $this->assertSame('', clean_volume('November bananas'));
    }

    /**
     * @param array<mixed> $json
     */
    private function processDoiDate(
        array $json,
        string $citation = '{{cite journal}}'
    ): Template {
        $template = $this->make_citation($citation);
        process_doi_json($template, '10.1000/date-test', $json);
        return $template;
    }

    private function processZoteroDate(
        mixed $date,
        int $accessDate = 0,
        string $citation = '{{cite web}}'
    ): Template {
        $template = $this->make_citation($citation);
        $response = json_encode(
            [
                (object) [
                    'title' => 'External API date test',
                    'itemType' => 'webpage',
                    'date' => $date,
                ],
            ],
            JSON_THROW_ON_ERROR
        );

        Zotero::process_zotero_response(
            $response,
            $template,
            'https://example.test/article',
            $accessDate
        );

        return $template;
    }

    private function processRisDate(
        string $date,
        string $citation = '{{cite journal}}'
    ): Template {
        $template = $this->make_citation($citation);
        $ris = "TY - JOUR\nY1 - " . $date . "\nER -";
        expand_by_RIS($template, $ris, false);
        return $template;
    }

    public function testDoiDatePartsUsesIssuedYear(): void {
        $template = $this->processDoiDate([
            'issued' => ['date-parts' => [[2024, 6, 15]]],
            'created' => ['date-parts' => [[2023, 1, 1]]],
            'published-print' => ['date-parts' => [[2022, 1, 1]]],
        ]);

        $this->assertSame('2024', $template->get2('year'));
    }

    public function testDoiDatePartsFallsBackToCreatedWhenIssuedIsMissing(): void {
        $template = $this->processDoiDate([
            'created' => ['date-parts' => [[2023, 7, 4]]],
            'published-print' => ['date-parts' => [[2022, 1, 1]]],
        ]);

        $this->assertSame('2023', $template->get2('year'));
    }

    public function testDoiDatePartsFallsBackToCreatedWhenIssuedIsEmpty(): void {
        $template = $this->processDoiDate([
            'issued' => ['date-parts' => []],
            'created' => ['date-parts' => [[2023]]],
        ]);

        $this->assertSame('2023', $template->get2('year'));
    }

    public function testDoiDatePartsFallsBackToPublishedPrint(): void {
        $template = $this->processDoiDate([
            'issued' => ['date-parts' => []],
            'created' => ['date-parts' => [[]]],
            'published-print' => ['date-parts' => [[2022, 11, 30]]],
        ]);

        $this->assertSame('2022', $template->get2('year'));
    }

    public function testDoiDatePartsUsesFirstReturnedDateTuple(): void {
        $template = $this->processDoiDate([
            'issued' => ['date-parts' => [[2024, 6, 15], [2023, 1, 1]]],
        ]);

        $this->assertSame('2024', $template->get2('year'));
    }

    public function testDoiDatePartsRejectsMalformedStructuresWithoutThrowing(): void {
        $badValues = [
            ['issued' => '2024'],
            ['issued' => ['date-parts' => '2024']],
            ['issued' => ['date-parts' => []]],
            ['issued' => ['date-parts' => [[]]]],
            ['issued' => (object) ['date-parts' => [[2024]]]],
        ];

        foreach ($badValues as $json) {
            $template = $this->processDoiDate($json);
            $this->assertNull(
                $template->get2('year'),
                'Malformed external date structure unexpectedly produced a year'
            );
        }
    }

    public function testDoiDatePartsDoesNotOverwriteExistingYear(): void {
        $template = $this->processDoiDate(
            ['issued' => ['date-parts' => [[2024]]]],
            '{{cite journal|year=1999}}'
        );

        $this->assertSame('1999', $template->get2('year'));
    }

    public function testDoiDatePartsDoesNotAddYearWhenDateAlreadyExists(): void {
        $template = $this->processDoiDate(
            ['issued' => ['date-parts' => [[2024]]]],
            '{{cite journal|date=1999}}'
        );

        $this->assertSame('1999', $template->get2('date'));
        $this->assertNull($template->get2('year'));
    }

    public function testZoteroDateAcceptsStringDate(): void {
        $template = $this->processZoteroDate('2024-06-15');

        $this->assertSame(tidy_date('2024-06-15'), $template->get2('date'));
    }

    public function testZoteroDateAcceptsIntegerYearFromExternalApi(): void {
        $template = $this->processZoteroDate(2024);

        $this->assertSame('2024', $template->get2('date'));
    }

    public function testZoteroDateRejectsNonScalarDateField(): void {
        $template = $this->processZoteroDate(['unexpected' => 'array']);

        $this->assertNull($template->get2('date'));
        $this->assertNull($template->get2('title'));
    }

    public function testZoteroDateIgnoresTooShortDateValue(): void {
        $template = $this->processZoteroDate('99');

        $this->assertNull($template->get2('date'));
    }

    public function testZoteroDateBeforeAccessDateIsAccepted(): void {
        $accessDate = (int) strtotime('2024-06-15');
        $template = $this->processZoteroDate('2024-06-14', $accessDate);

        $this->assertSame(tidy_date('2024-06-14'), $template->get2('date'));
    }

    public function testZoteroDateEqualToAccessDateIsAccepted(): void {
        $accessDate = (int) strtotime('2024-06-15');
        $template = $this->processZoteroDate('2024-06-15', $accessDate);

        $this->assertSame(tidy_date('2024-06-15'), $template->get2('date'));
    }

    public function testZoteroDateAfterAccessDateIsRejected(): void {
        $accessDate = (int) strtotime('2024-06-15');
        $template = $this->processZoteroDate('2024-06-16', $accessDate);

        $this->assertNull($template->get2('date'));
    }

    public function testZoteroPre1900DateUsesNormalDateTidying(): void {
        $template = $this->processZoteroDate('1800-02-03');

        $this->assertSame(tidy_date('1800-02-03'), $template->get2('date'));
    }

    public function testRisDateUsesNormalDateTidying(): void {
        $template = $this->processRisDate('2024-06-15');

        $this->assertSame(tidy_date('2024-06-15'), $template->get2('date'));
    }

    public function testRisDateDoesNotOverwriteExistingDate(): void {
        $template = $this->processRisDate(
            '2024',
            '{{cite journal|date=1999}}'
        );

        $this->assertSame('1999', $template->get2('date'));
    }
}
