<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditTitleSimilarityAdditionalTest extends testBaseClass {
    #[DataProvider('differentTitles')]
    public function testDistinctWorksStayDistinctInEitherOrder(string $a, string $b): void {
        $this->assertFalse(titles_are_similar($a, $b));
        $this->assertFalse(titles_are_similar($b, $a));
    }

    public static function differentTitles(): array {
        $prefix = str_repeat('Études moléculaires et réactions biologiques ', 8);
        return [
            'two section labels, second differs' => ['Section A Part 1 Methods', 'Section A Part 2 Methods'],
            'two numbers, first differs' => ['Volume 2 Chapter 7', 'Volume 3 Chapter 7'],
            'two numbers, second differs' => ['Volume 2 Chapter 7', 'Volume 2 Chapter 8'],
            'roman versus arabic different values' => ['Section IV Methods', 'Section 5 Methods'],
            'non-ascii long label mismatch' => [$prefix . 'Section A', $prefix . 'Section B'],
            'non-ascii long number mismatch' => [$prefix . 'Part 1', $prefix . 'Part 2'],
            'unicode punctuation does not erase number' => ['Étude — Part 1', 'Étude — Part 2'],
            'entity encoded space does not erase number' => ['Volume&nbsp;2 Methods', 'Volume 3 Methods'],
        ];
    }

    #[DataProvider('equivalentTitles')]
    public function testEquivalentWorksRemainSimilarInEitherOrder(string $a, string $b): void {
        $this->assertTrue(titles_are_similar($a, $b));
        $this->assertTrue(titles_are_similar($b, $a));
    }

    public static function equivalentTitles(): array {
        return [
            'roman and arabic number with accent' => ['Étude Section IV', 'Étude Section 4'],
            'roman and arabic second number' => ['Volume 2 Part IV', 'Volume 2 Part 4'],
            'unicode diacritic variant' => ['Étude moléculaire', 'Etude moleculaire'],
            'HTML nonbreaking space' => ['Section&nbsp;IV Methods', 'Section 4 Methods'],
            'nonroman ordinary word' => ['Mix of Colours', 'Mix of Colors'],
        ];
    }
}
