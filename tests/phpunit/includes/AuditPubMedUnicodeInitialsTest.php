<?php
declare(strict_types=1);

/**
 * PubMed authors whose initials are not ASCII must not be dropped.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditPubMedUnicodeInitialsTest extends testBaseClass {

    #[DataProvider('authorProvider')]
    public function testAuthorIsSplit(string $name, string $surname, string $initials): void {
        $split = pubmed_split_author_name($name);
        $this->assertNotNull($split);
        $this->assertSame($surname, $split[1]);
        $this->assertSame($initials, $split[2]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function authorProvider(): array {
        return [
            'ascii single initial' => ['Smith J', 'Smith', 'J'],
            'ascii two initials' => ['Smith JA', 'Smith', 'J. A'],
            'non-ascii single initial' => ['Hammer Ø', 'Hammer', 'Ø'],
            'non-ascii second initial' => ['Müller JÖ', 'Müller', 'J. Ö'],
            'non-ascii first initial' => ['Sæther ÅK', 'Sæther', 'Å. K'],
        ];
    }

    public function testSingleWordNameIsNotSplit(): void {
        $this->assertNull(pubmed_split_author_name('Consortium'));
    }
}
