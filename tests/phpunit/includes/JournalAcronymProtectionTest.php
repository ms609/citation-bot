<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class JournalAcronymProtectionTest extends testBaseClass {
    #[DataProvider('journalCases')]
    public function testJournalAcronymsArePreserved(string $input, string $expected): void {
        $this->assertSame($expected, wikify_external_text($input, use_title_case: true));
    }

    /** @return array<string, array{string, string}> */
    public static function journalCases(): array {
        return [
            'ACM and TOG' => ['ACM transactions on graphics (TOG)', 'ACM Transactions on Graphics (TOG)'],
            'AJR prefix' => ['AJR. American journal of roentgenology', 'AJR. American Journal of Roentgenology'],
            'IEEE' => ['IEEE transactions on computers', 'IEEE Transactions on Computers'],
            'ordinary lower words' => ['Journal of research in physics', 'Journal of Research in Physics'],
            'all uppercase is not locked' => ['MOLECULAR AND CELLULAR BIOLOGY', 'Molecular and Cellular Biology'],
        ];
    }
}
