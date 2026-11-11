<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

/** Verify the ASCII suffix is removed without corrupting UTF-8 prefixes. */
final class AuditTitleSimilarityByteOffsetTest extends testBaseClass {
    #[DataProvider('equivalentPlurals')]
    public function testFinalYPluralHandlesMultibytePrefixes(string $singular, string $plural): void {
        $this->assertTrue(titles_have_equivalent_final_y_plural($singular, $plural));
        $this->assertTrue(titles_have_equivalent_final_y_plural($plural, $singular));
    }

    /** @return array<string, array{string, string}> */
    public static function equivalentPlurals(): array {
        return [
            'plain English' => ['civilsocietystudy', 'civilsocietystudies'],
            'accented prefix' => ['résuméstudy', 'résuméstudies'],
            'Greek prefix' => ['μελέτηstudy', 'μελέτηstudies'],
            'CJK prefix' => ['研究study', '研究studies'],
            'emoji prefix' => ['🔬family', '🔬families'],
            'short English word' => ['city', 'cities'],
        ];
    }

    #[DataProvider('nonEquivalentPlurals')]
    public function testFinalYPluralDoesNotHideOtherDifferences(string $left, string $right): void {
        $this->assertFalse(titles_have_equivalent_final_y_plural($left, $right));
        $this->assertFalse(titles_have_equivalent_final_y_plural($right, $left));
    }

    /** @return array<string, array{string, string}> */
    public static function nonEquivalentPlurals(): array {
        return [
            'distinct sections' => ['section1study', 'section2studies'],
            'changed prefix' => ['résuméstudy', 'résumé2studies'],
            'other ending' => ['civilsocietystudy', 'civilsocietystudiesx'],
            'ordinary typo' => ['civilsocietystudy', 'civilsocietystudie'],
            'non-ASCII suffix' => ['résuméstudý', 'résuméstudies'],
        ];
    }
}
