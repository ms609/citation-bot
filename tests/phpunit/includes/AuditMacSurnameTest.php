<?php
declare(strict_types=1);

/**
 * Not every surname starting with "Mac" takes a capital after it.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditMacSurnameTest extends testBaseClass {

    #[DataProvider('surnameProvider')]
    public function testSurname(string $input, string $expected): void {
        $this->assertSame($expected, format_surname($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function surnameProvider(): array {
        return [
            'Macron' => ['MACRON', 'Macron'],
            'Macedo' => ['macedo', 'Macedo'],
            'Macías' => ['MACÍAS', 'Macías'],
            'Mackey' => ['Mackey', 'Mackey'],
            'MacDonald is still a patronymic' => ['MACDONALD', 'MacDonald'],
            'MacLeod is still a patronymic' => ['macleod', 'MacLeod'],
        ];
    }
}
