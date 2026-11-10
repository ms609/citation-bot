<?php
declare(strict_types=1);

/**
 * Single-letter volumes are real volumes, not dates.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditCleanVolumeLetterTest extends testBaseClass {

    #[DataProvider('letterProvider')]
    public function testSingleLetterVolumeIsKept(string $volume): void {
        $this->assertSame($volume, clean_volume($volume));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function letterProvider(): array {
        return ['A' => ['A'], 'B' => ['B'], 'Z' => ['Z'], 'lower case' => ['c']];
    }

    public function testDatesAreStillRejected(): void {
        $this->assertSame('', clean_volume('12 March 2020'));
        $this->assertSame('', clean_volume('november'));
    }
}
