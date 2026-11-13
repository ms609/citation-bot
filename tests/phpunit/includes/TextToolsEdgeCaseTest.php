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
}
