<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Network-free edge cases for the text, date and identifier helpers in
 * TextTools.php.
 */
final class TextToolsEdgeCaseTest extends PHPUnit\Framework\TestCase {

    // ---- wikify_external_text --------------------------------------------------

    public function testSingleDollarLatexIsWrappedInMath(): void {
        $this->assertSame('<math>z\sim10</math>', wikify_external_text('$z\sim10$'));
    }

    /** @return array<string, array{string}> */
    public static function dollarProseProvider(): array {
        return [
            'prices' => ['costs $5 and $10'],
            'short variable without a command' => ['price $x$ only'],
            'escaped dollars' => ['an \$escaped\$ one'],
        ];
    }

    #[DataProvider('dollarProseProvider')]
    public function testDollarSignsWithoutLatexCommandsAreNotMath(string $title): void {
        $result = wikify_external_text($title);
        $this->assertStringNotContainsString('<math>', $result);
        $this->assertStringContainsString('$', $result);
    }

    public function testPricesKeepBothDollarSigns(): void {
        $this->assertStringContainsString('$5 and $10', wikify_external_text('costs $5 and $10'));
    }

    // ---- straighten_quotes -----------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function guillemetProvider(): array {
        return [
            'same opening guillemet at both ends' => ['«A » B«', '"A » B"'],
            'same closing guillemet at both ends' => ['»A « B»', '"A « B"'],
            'same entity at both ends' => ['&laquo;A &raquo; B&laquo;', '"A &raquo; B"'],
            'matching pair around everything' => ['«Title»', '"Title"'],
            'repeated outer character is left alone' => ['«A » B « C«', '«A » B « C«'],
            'mismatched ends with an inner guillemet is left alone' => ['«A » B»', '«A » B»'],
            'only one kind of guillemet is left alone' => ['«A B«', '«A B«'],
        ];
    }

    #[DataProvider('guillemetProvider')]
    public function testOuterGuillemetsAreStraightenedOnlyWhenUnambiguous(string $input, string $expected): void {
        $this->assertSame($expected, straighten_quotes($input, false));
    }

    public function testDoMoreStraightensEveryGuillemet(): void {
        $this->assertSame('"Jobs" "Iowa"', straighten_quotes('«Jobs» «Iowa»', true));
    }

    public function testStraightenQuotesLeavesInvalidUtf8AndEmptyStringsAlone(): void {
        $this->assertSame('', straighten_quotes('', false));
        $this->assertSame("bad \xFF bytes", straighten_quotes("bad \xFF bytes", true));
    }

    // ---- dates -----------------------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function impossibleDateProvider(): array {
        return [
            'year below 100' => ['0050-06-31', ''],
            'year far in the future' => ['3000-02-30', ''],
            'invalid month and day in the future' => ['3000-13-45', ''],
            'unknown-day marker keeps the month' => ['2001-05-00', 'May 2001'],
            'unknown month and day keeps the year' => ['2001-00-00', '2001'],
            'february 30th keeps the year' => ['2001-02-30', '2001'],
            'june 31st keeps the year' => ['2001-06-31', '2001'],
            'the year 0001 placeholder' => ['0001-01-01', ''],
            'day before the Unix epoch' => ['1969-12-31', '1969-12-31'],
            'invalid marker' => ['Invalid Date', ''],
        ];
    }

    #[DataProvider('impossibleDateProvider')]
    public function testTidyDateInsideRejectsOrReducesImpossibleDates(string $input, string $expected): void {
        $this->assertSame($expected, tidy_date_inside($input));
    }

    public function testTidyDateReducesDatesBeforeNineteenHundredToTheYear(): void {
        $this->assertSame('1850', tidy_date('1850-03-04'));
        $this->assertSame('1599', tidy_date('1599-12-25'));
        $this->assertSame('1850', tidy_date('31 June 1850'));
        $this->assertSame('44', tidy_date('0044'));
    }

    public function testTidyDateKeepsRecentDatesAndRejectsGarbage(): void {
        $this->assertSame('2011-01-30', tidy_date('30/1/2011'));
        $this->assertSame('', tidy_date(''));
        $this->assertSame('', tidy_date('no digits here'));
    }

    // ---- identifier validators -------------------------------------------------

    /** @return array<string, array{string, bool}> */
    public static function jstorProvider(): array {
        return [
            'plain number' => ['10.2307/123', true],
            'url with https' => ['https://www.example.org/1', false],
            'url with ftp' => ['ftp://example.org', false],
            'contains whitespace' => ['abc def', false],
            'contains the word jstor' => ['JSTOR123', false],
        ];
    }

    #[DataProvider('jstorProvider')]
    public function testJstorValid(string $value, bool $expected): void {
        $this->assertSame($expected, jstor_valid($value));
    }

    /** @return array<string, array{string, bool}> */
    public static function lccnProvider(): array {
        return [
            'eight digits' => ['12345678', true],
            'prefix with year' => ['n2001012345', true],
            'two letter prefix' => ['ab12345678', true],
            'too short' => ['1234567', false],
            'too long' => ['1234567890123', false],
            'letters where digits belong' => ['abcdefghij', false],
            'letter inside the serial' => ['2001a12345', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('lccnProvider')]
    public function testLccnValid(string $value, bool $expected): void {
        $this->assertSame($expected, lccn_valid($value));
    }

    // ---- italic tags, annual reviews, and date normalization ----

    // ---- wikify_italic_tags -------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function italicTagCases(): array {
        return [
            'plain text is untouched' => ['plain', 'plain'],
            'empty string' => ['', ''],
            'i tag' => ['<i>Homo sapiens</i>', "''Homo sapiens''"],
            'upper case tag' => ['<I>A</I>', "''A''"],
            'em tag' => ['<em>x</em>', "''x''"],
            'italic tag' => ['<italic>z</italic>', "''z''"],
            'italics tag' => ['<italics>y</italics>', "''y''"],
            'html-escaped tag' => ['&lt;em&gt;x&lt;/em&gt;', "''x''"],
            'several pairs' => ['<i>one</i> and <em>two</em>', "''one'' and ''two''"],
            'surrounding text kept' => ['A <i>b</i> c', "A ''b'' c"],
            'nested markup is left alone' => ['<i>a<b>c</b></i>', '<i>a<b>c</b></i>'],
            'empty pair is left alone' => ['<i></i>', '<i></i>'],
            'mismatched closing tag' => ['<i>x</em>', '<i>x</em>'],
            'unclosed tag' => ['<i>never closed', '<i>never closed'],
            'bold tag is not italics' => ['<b>x</b>', '<b>x</b>'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('italicTagCases')]
    public function testWikifyItalicTags(string $input, string $expected): void {
        $this->assertSame($expected, wikify_italic_tags($input));
    }

    public function testWikifyItalicTagsKeepsMultibyteContent(): void {
        $this->assertSame("''Müller – 北京''", wikify_italic_tags('<i>Müller – 北京</i>'));
    }

    // ---- is_annual_review_cell_biology ----------------------------------------

    public function testAnnualReviewDetectionByDoiPrefix(): void {
        $this->assertTrue(is_annual_review_cell_biology('', '10.1146/annurev-cellbio-100617-062558'));
        $this->assertTrue(is_annual_review_cell_biology('', '10.1146/annurev.cellbio.1.1.1'));
        $this->assertTrue(is_annual_review_cell_biology('', '10.1146/ANNUREV-CELLBIO-1'));
        $this->assertFalse(is_annual_review_cell_biology('', '10.1146/annurev-physiol-1'));
        $this->assertFalse(is_annual_review_cell_biology('', 'x10.1146/annurev-cellbio-1'));
        $this->assertFalse(is_annual_review_cell_biology('', '10.1146/annurevXcellbioX1'));
        $this->assertFalse(is_annual_review_cell_biology('', ''));
    }

    public function testAnnualReviewDetectionByJournalName(): void {
        $this->assertTrue(is_annual_review_cell_biology('Annual Review of Cell and Developmental Biology'));
        $this->assertTrue(is_annual_review_cell_biology('annual review of cell and developmental biology'));
        $this->assertFalse(is_annual_review_cell_biology('Annual Review of Cell Biology'));
        $this->assertFalse(is_annual_review_cell_biology(''));
        // Either signal is enough.
        $this->assertTrue(is_annual_review_cell_biology('Some Other Journal', '10.1146/annurev-cellbio-5'));
    }

    // ---- tidy_date_inside -----------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function dateCases(): array {
        return [
            'empty' => ['', ''],
            'invalid marker' => ['Invalid Date', ''],
            'slash epoch' => ['1/1/0001', ''],
            'iso epoch' => ['0001-01-01T00:00', ''],
            'day before the Unix epoch' => ['1969-12-31', '1969-12-31'],
            'zero date' => ['0000-00-00 00:00:00', ''],
            'no digits' => ['abc', ''],
            'single digit' => ['5', ''],
            'dangling dashes' => ['12--', ''],
            'archive unknown' => ['19xx', ''],
            'si.edu pattern' => ['2020 1234-5678', ''],
            'surrounding whitespace' => ['  2020-05-06  ', '2020-05-06'],
            'unicode en dash' => ["2020\u{2013}05\u{2013}06", '2020-05-06'],
            'time stamp with zone' => ['2020-05-06T10:00:00Z', '2020-05-06'],
            'month name' => ['Mar 2020', 'March 2020'],
            'same-year range' => ['2020-01-05 - 2020-03-10', "5 January \u{2013} 10 March 2020"],
            'multi-year range' => ['2019-12-05 - 2020-03-10', "5 December 2019 \u{2013} 10 March 2020"],
            'impossible range falls through' => ['2020-13-45 - 2020-14-46', ''],
            'us style slash date' => ['2/13/2001', '2001-02-13'],
            'european slash date' => ['13/2/2001', '2001-02-13'],
            'single digit us date' => ['1/31/2010', '2010-01-31'],
            'both fields above twelve' => ['13/13/2013', ''],
            'ambiguous slash date gives the year' => ['3/4/2001', '2001'],
            'impossible us date gives the year' => ['4/31/2001', '2001'],
            'impossible european date gives the year' => ['31/2/1913', '1913'],
            'same day and month' => ['5/5/2005', '2005-05-05'],
            'same two digits' => ['12/12/2012', '2012-12-12'],
            'plain year' => ['1999', '1999'],
            'zero is not a year' => ['0', ''],
            'one is not a year' => ['1', ''],
            'negative one is not a year' => ['-1', ''],
            'far future is garbage' => ['3000', ''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dateCases')]
    public function testTidyDateInside(string $input, string $expected): void {
        $this->assertSame($expected, tidy_date_inside($input));
    }

}
