<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class CleanVolumeBoundaryTest extends testBaseClass {
    #[DataProvider('volumeCases')]
    public function testVolumeCleanup(string $input, string $expected): void {
        $this->assertSame($expected, clean_volume($input));
    }

    /** @return array<string, array{string, string}> */
    public static function volumeCases(): array {
        return [
            'Spanish Volumen is not partially removed' => ['Volumen 4', 'Volumen 4'],
            'single volume and issue' => ['12 (3)', '12'],
            'compact volume and issue' => ['12(3)', '12'],
            'labeled volume and issue' => ['Volume 12 (3)', '12'],
            'ordinary volume label' => ['Vol. 5', '5'],
            'adjacent volume prefix' => ['vol12', '12'],
            'issue label' => ['Issue 3', '3'],
            'ordinary word remains intact' => ['Revolution 4', 'Revolution 4'],
            'ambiguous parentheses still rejected' => ['Chapter (supplement)', ''],
            'letter-number volume' => ['T5', 'T5'],
            'date text still rejected' => ['12 March 2020', ''],
        ];
    }
}
