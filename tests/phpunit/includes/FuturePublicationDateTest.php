<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class FuturePublicationDateTest extends testBaseClass {
    public function testFutureCoverMonthAndYearArePreserved(): void {
        $nextMonth = strtotime('first day of next month');
        $this->assertNotFalse($nextMonth);
        $month = date('F Y', $nextMonth);
        $this->assertSame($month, tidy_date($month));

        $year = (string) ((int) date('Y') + 1);
        $this->assertSame($year, tidy_date($year));
        $this->assertSame($year . '-01-01', tidy_date($year . '-01-01'));
    }

    public function testFutureEventDateValidationRemainsAvailable(): void {
        $fourDays = date('Y-m-d', strtotime('+4 days'));
        $this->assertSame('', tidy_date($fourDays, false));
        $this->assertSame($fourDays, tidy_date($fourDays));
        $twoDays = date('Y-m-d', strtotime('+2 days'));
        $this->assertSame($twoDays, tidy_date($twoDays, false));
        $year = (string) ((int) date('Y') + 1);
        $this->assertSame('', tidy_date($year, false));
    }

    public function testInvalidDatesAndVeryDistantFutureAreStillRejected(): void {
        $this->assertSame('2026', tidy_date('2026-02-30'));
        $this->assertSame('', tidy_date('3000-02-01'));
    }
}
