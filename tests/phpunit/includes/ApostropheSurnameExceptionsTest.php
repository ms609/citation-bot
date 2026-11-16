<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class ApostropheSurnameExceptionsTest extends testBaseClass {
    #[DataProvider('surnameCases')]
    public function testApostropheSurnameCasing(string $input, string $expected): void {
        $this->assertSame($expected, format_surname($input));
    }

    /** @return array<string, array{string, string}> */
    public static function surnameCases(): array {
        return [
            'Angelo' => ["D'Angelo", "D'Angelo"],
            'Angelo uppercase' => ["D'ANGELO", "D'Angelo"],
            'Angelo curly' => ["D’Angelo", "D’Angelo"],
            'Agostino' => ["D'Agostino", "D'Agostino"],
            'Hôpital' => ["L'Hôpital", "L'Hôpital"],
            'Hôpital curly' => ["L’Hôpital", "L’Hôpital"],
            'Alembert lowercase prefix' => ["d'Alembert", "d'Alembert"],
            'Hermite lowercase prefix' => ["l'Hermite", "l'Hermite"],
            'Existing O name' => ["O'Connor", "O'Connor"],
            'No apostrophe' => ['Smith', 'Smith'],
        ];
    }
}
