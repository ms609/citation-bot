<?php
declare(strict_types=1);

/**
 * The "Vol"/"No" prefix must only be stripped when it is a prefix, not when
 * it is the start of an ordinary word.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditVolumePrefixTest extends testBaseClass {

    #[DataProvider('realPrefixProvider')]
    public function testRealPrefixIsStripped(string $volume, string $expected): void {
        $template = $this->make_citation('{{cite journal|volume=' . $volume . '}}');
        $template->tidy_parameter('volume');
        $this->assertSame($expected, $template->get2('volume'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function realPrefixProvider(): array {
        return [
            'Vol. with space' => ['Vol. 12', '12'],
            'Volume with space' => ['Volume 12', '12'],
            'vol without separator' => ['vol12', '12'],
            'No. with space' => ['No. 12', '12'],
        ];
    }

    #[DataProvider('wordProvider')]
    public function testWordsStartingWithThePrefixAreKept(string $volume, string $start): void {
        $template = $this->make_citation('{{cite book|volume=' . $volume . '}}');
        $template->tidy_parameter('volume');
        $this->assertStringStartsWith($start, (string) $template->get2('volume'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function wordProvider(): array {
        return [
            'Volumes' => ['Volumes One and Two', 'Volumes'],
            'Notes' => ['Notes and Queries', 'Notes'],
            'Nova' => ['Nova Series', 'Nova'],
        ];
    }
}
