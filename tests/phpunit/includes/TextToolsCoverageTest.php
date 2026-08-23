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
}
