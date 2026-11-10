<?php
declare(strict_types=1);

/**
 * "Viii" is capitalised like the other Roman numerals.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditRomanEightTitleTest extends testBaseClass {

    public function testTrailingRomanEight(): void {
        $this->assertSame('Dogs VIII', title_capitalization('Dogs Viii', true));
    }

    public function testLeadingRomanEight(): void {
        $this->assertSame('VIII: Dogs', title_capitalization('Viii: Dogs', true));
    }

    public function testRomanSevenStillWorks(): void {
        $this->assertSame('Dogs VII', title_capitalization('Dogs Vii', true));
    }
}
