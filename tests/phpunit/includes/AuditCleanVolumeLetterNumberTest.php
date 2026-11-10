<?php
declare(strict_types=1);

/**
 * A letter followed by a number is a volume label, not a date.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditCleanVolumeLetterNumberTest extends testBaseClass {

    #[DataProvider('labelProvider')]
    public function testLetterNumberVolumeIsKept(string $volume): void {
        $this->assertSame($volume, clean_volume($volume));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function labelProvider(): array {
        return ['T5' => ['T5'], 'A-1' => ['A-1'], 'A1' => ['A1'], 'B12' => ['B12'], 'single letter' => ['C']];
    }

    public function testRealDatesAreStillRejected(): void {
        $this->assertSame('', clean_volume('Mar'));
        $this->assertSame('', clean_volume('12 March 2020'));
        $this->assertSame('', clean_volume('10pm'));
    }
}
