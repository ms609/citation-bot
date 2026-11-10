<?php
declare(strict_types=1);

/**
 * A date string that does not state a year must not be completed from today's date.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditDateWithoutYearTest extends testBaseClass {

    #[DataProvider('noYearProvider')]
    public function testStringWithoutYearIsRejected(string $input): void {
        $this->assertSame('', tidy_date($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function noYearProvider(): array {
        return [
            'relative hours' => ['12 hours ago'],
            'relative minutes' => ['30 minutes ago'],
            'relative days' => ['10 days ago'],
            'time of day' => ['10:30'],
            'time with meridian' => ['12:00 PM'],
            'month and day' => ['May 15'],
            'day and month' => ['15 March'],
            'ordinal day' => ['March 12th'],
        ];
    }

    public function testYearAfterCommaIsKeptAsTheYear(): void {
        $this->assertSame('2020', tidy_date('March, 2020'));
    }

    #[DataProvider('withYearProvider')]
    public function testStringWithYearIsUnchanged(string $input, string $expected): void {
        $this->assertSame($expected, tidy_date($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function withYearProvider(): array {
        return [
            'month and year' => ['March 2020', 'March 2020'],
            'day month year' => ['15 Mar 2020', '2020-03-15'],
            'iso' => ['2020-03-15', '2020-03-15'],
            'day month comma year' => ['3 October, 2016', '2016-10-03'],
            'two digit year' => ['15 Jan 99', '1999-01-15'],
        ];
    }
}
