<?php
declare(strict_types=1);

/**
 * A timestamp keeps the calendar date it was written with, whatever its UTC offset.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditDateTimezoneShiftTest extends testBaseClass {

    #[DataProvider('timestampProvider')]
    public function testCalendarDateIsKept(string $input, string $expected): void {
        $this->assertSame($expected, tidy_date($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function timestampProvider(): array {
        return [
            'negative offset, evening' => ['2020-03-15T23:30:00-05:00', '2020-03-15'],
            'negative offset, no colon' => ['2020-03-15T20:00:00-0500', '2020-03-15'],
            'negative offset, no seconds' => ['2020-03-15T23:30-05:00', '2020-03-15'],
            'zone abbreviation' => ['2020-03-15 23:30:00 EST', '2020-03-15'],
            'positive offset, early morning, no seconds' => ['2020-03-15T04:30+05:30', '2020-03-15'],
            'positive offset with seconds' => ['2020-03-15T04:30:00+05:30', '2020-03-15'],
            'utc' => ['2020-03-15T23:30:00Z', '2020-03-15'],
            'plain date' => ['2020-03-15', '2020-03-15'],
        ];
    }
}
