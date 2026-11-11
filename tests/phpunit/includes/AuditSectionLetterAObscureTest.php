<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

/** Punctuation, Unicode, position, and grammatical regressions for PR 6337. */
final class AuditSectionLetterAObscureTest extends testBaseClass {
    #[DataProvider('preservedLabels')]
    public function testExplicitUppercaseLabels(string $title): void {
        $this->assertSame($title, title_capitalization($title, true));
    }

    public static function preservedLabels(): array {
        return [
            'section after colon' => ['Methods: Section A Results'],
            'part after comma' => ['Methods, Part A Results'],
            'part after dash' => ['Methods - Part A Results'],
            'series after colon' => ['Proceedings: Series A Methods'],
            'class after colon' => ['Experiments: Class A Methods'],
            'physica after colon' => ['Studies: Physica A Methods'],
            'two section labels' => ['Section A and Section B Methods'],
            'mixed part and section labels' => ['Part A and Section B Methods'],
            'label followed by colon' => ['Section A: Methods'],
            'label followed by comma' => ['Section A, Methods'],
            'label at title end' => ['Acta Crystallographica Section A'],
            'label in accented title' => ['Études de Physique Section A Methods'],
            'label before Unicode dash' => ['Section A — Methods'],
            'uppercase grammatical-looking label' => ['Take Part A Few-Body Interactions'],
        ];
    }

    #[DataProvider('articlesAndExistingConventions')]
    public function testArticlesAndExistingConventions(string $input, string $expected): void {
        $this->assertSame($expected, title_capitalization($input, true));
    }

    public static function articlesAndExistingConventions(): array {
        return [
            'article in sentence' => ['Tale of A City', 'Tale of a City'],
            'article in accented sentence' => ['Études of A System', 'Études of a System'],
            'take part a few' => ['We Took Part A Few Times', 'We Took Part a Few Times'],
            'took part a little' => ['We Took Part A Little', 'We Took Part a Little'],
            'in part a result' => ['In Part A Result of Choice', 'In Part a Result of Choice'],
            'lowercase section letter not promoted' => ['Section a Methods', 'Section a Methods'],
            'lowercase part letter not promoted' => ['Part a Methods', 'Part a Methods'],
            'lowercase class letter not promoted' => ['Class a Methods', 'Class a Methods'],
            'lowercase part at end' => ['Part a', 'Part a'],
            'lowercase section at end' => ['Section a', 'Section a'],
            'series a promoted' => ['Series a Methods', 'Series A Methods'],
            'series a at end promoted' => ['Series a', 'Series A'],
            'series a and b promoted' => ['Series a and b Methods', 'Series A and B Methods'],
            'US acronym retained' => ['Research in U S A Methods', 'Research in U S A Methods'],
        ];
    }
}
