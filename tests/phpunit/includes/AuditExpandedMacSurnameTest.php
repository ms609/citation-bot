<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditExpandedMacSurnameTest extends testBaseClass {
    #[DataProvider('surnames')]
    public function testMacSurnameIsNotOvercapitalized(string $raw, string $expected): void {
        $this->assertSame($expected, format_surname($raw));
    }

    /** @return array<string, array{string, string}> */
    public static function surnames(): array {
        return [
            'Polish Maciejewski' => ['Maciejewski', 'Maciejewski'],
            'Polish uppercase Maciejewski' => ['MACIEJEWSKI', 'Maciejewski'],
            'Polish Mackiewicz' => ['Mackiewicz', 'Mackiewicz'],
            'Filipino Macapagal' => ['Macapagal', 'Macapagal'],
            'Filipino uppercase Macapagal' => ['MACAPAGAL', 'Macapagal'],
            'Filipino Macaraig' => ['Macaraig', 'Macaraig'],
            'Italian Macri' => ['Macri', 'Macri'],
            'Existing Gaelic MacDonald' => ['MACDONALD', 'MacDonald'],
            'Existing Gaelic MacLeod' => ['macleod', 'MacLeod'],
            'Existing Mackey' => ['Mackey', 'Mackey'],
        ];
    }
}
