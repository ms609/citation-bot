<?php
declare(strict_types=1);

/**
 * Date ranges written with an en dash are cleaned the same way as with a hyphen.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditDateRangeEnDashTest extends testBaseClass {

    public function testFullDateRangeWithHyphen(): void {
        $this->assertSame('January 2020 – March 2021', clean_dates('January 2020-March 2021'));
    }

    public function testFullDateRangeWithEnDash(): void {
        $this->assertSame('January 2020 – March 2021', clean_dates('January 2020–March 2021'));
    }

    public function testSeasonAndYearRangeWithEnDash(): void {
        $this->assertSame('Winter 2020–2021', clean_dates('Winter, 2020–2021'));
    }
}
