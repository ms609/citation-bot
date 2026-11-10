<?php
declare(strict_types=1);

/**
 * Abbreviated page ranges must keep the leading digits of the first page:
 * 1298-305 is 1298–1305, not 1298–1505.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditPageRangeExpansionTest extends testBaseClass {

    #[DataProvider('abbreviatedRangeProvider')]
    public function testAbbreviatedRangeIsExpandedFromTheFirstPage(string $input, string $expected): void {
        $template = $this->make_citation('{{cite journal|title=A title}}');
        $this->assertTrue($template->add_if_new('pages', $input));
        $this->assertSame($expected, $template->get2('pages'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function abbreviatedRangeProvider(): array {
        return [
            'one digit suffix' => ['2342-5', '2342–2345'],
            'two digit suffix' => ['2342-45', '2342–2345'],
            'three digit suffix on four digit start' => ['1298-305', '1298–1305'],
            'three digit suffix on five digit start' => ['12298-305', '12298–12305'],
        ];
    }
}
