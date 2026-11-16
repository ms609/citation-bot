<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class DottedDateParsingTest extends testBaseClass {
    #[DataProvider('dottedDates')]
    public function testDottedDateParsing(string $input, string $expected): void {
        $this->assertSame($expected, tidy_date($input));
    }

    /** @return array<string, array{string, string}> */
    public static function dottedDates(): array {
        return [
            'year-first dotted date' => ['2020.05.06', '2020-05-06'],
            'year-first leap day' => ['2020.02.29', '2020-02-29'],
            'unpadded year-first date' => ['2020.5.6', '2020-05-06'],
            'invalid year-first date' => ['2020.02.30', '2020'],
            'day-first dotted date' => ['20.05.06', '2006-05-20'],
            'month-first dotted date' => ['04.29.08', '2008-04-29'],
            'dotted date after time' => ['07:30 , 04.29.08', '2008-04-29'],
            'invalid day-first date' => ['31.02.2001', '2001'],
        ];
    }
}
