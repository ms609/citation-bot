<?php
declare(strict_types=1);

/**
 * A solitary "A" is lower-cased as the article, except when it labels a section, part or series.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditSectionLetterATest extends testBaseClass {

    #[DataProvider('labelProvider')]
    public function testLabelKeepsItsCapital(string $name): void {
        $this->assertSame($name, title_capitalization($name, true));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function labelProvider(): array {
        return [
            'section' => ['Acta Crystallographica Section A Foundations of Crystallography'],
            'part' => ['Journal of Polymer Science Part A Polymer Chemistry'],
            'part label after take' => ['Take Part A Before Part B'],
            'part label before hyphenated topic' => ['Take Part A Few-Body Interactions'],
            'series' => ['Proceedings of the Royal Society Series A Mathematical Sciences'],
            'physica' => ['Physica A Statistical Mechanics'],
            'class, as before' => ['Journal of Class A Amplifiers'],
        ];
    }

    public function testArticleIsStillLowerCased(): void {
        $this->assertSame('Tale of a City', title_capitalization('Tale of A City', true));
        $this->assertSame('We Took Part a Few Times', title_capitalization('We Took Part A Few Times', true));
        $this->assertSame('Take Part a Little', title_capitalization(mb_ucwords('Take part a little'), true));
    }

    public function testLowerCaseInputIsNotPromoted(): void {
        $this->assertSame('Take Part a Little', title_capitalization('Take Part a Little', true));
        $this->assertSame('Class a Methods', title_capitalization('Class a Methods', true));
        $this->assertSame('Section a Methods', title_capitalization('Section a Methods', true));
        $this->assertSame('Part a Methods', title_capitalization('Part a Methods', true));
        $this->assertSame('Section a', title_capitalization('Section a', true));
        $this->assertSame('Part a', title_capitalization('Part a', true));
        $this->assertSame('Class a', title_capitalization('Class a', true));
        $this->assertSame('Physica a', title_capitalization('Physica a', true));
        $this->assertSame('Physica a Methods', title_capitalization('Physica a Methods', true));
    }

    public function testPartWithAnArticleIsNotASectionLabel(): void {
        $this->assertSame('We Took Part a Long Time Ago', title_capitalization('WE TOOK PART A LONG TIME AGO', true));
        $this->assertSame('We Took Part a Brief While', title_capitalization('WE TOOK PART A BRIEF WHILE', true));
        $this->assertSame('In Part a Result of Choice', title_capitalization('IN PART A RESULT OF CHOICE', true));
        $this->assertSame('Take Part A Long-Term Study', title_capitalization('Take Part A Long-Term Study', true));
    }

    public function testExistingSeriesCapitalizationIsPreserved(): void {
        $this->assertSame('Journal Series A Notes', title_capitalization('Journal Series a Notes', true));
        $this->assertSame('The Series A and B qu', title_capitalization('The Series a and b qu', true));
        $this->assertSame('Series A Methods', title_capitalization('Series a Methods', true));
        $this->assertSame('Series A and B Methods', title_capitalization('Series a and b Methods', true));
        $this->assertSame('Series A', title_capitalization('Series a', true));
    }

    public function testOnlyOriginallyUppercaseLabelsArePreserved(): void {
        $this->assertSame('Part A Methods: Take Part a Little', title_capitalization('Part A Methods: Take Part a Little', true));
        $this->assertSame('Class A and Section a Methods', title_capitalization('Class A and Section a Methods', true));
        $this->assertSame('Series A', title_capitalization('Series A', true));
        $this->assertSame('Series A', title_capitalization('SERIES A', true));
        $this->assertSame('Journal Series A and B Methods', title_capitalization('Journal Series A and b Methods', true));
        $this->assertSame('Series B Methods', title_capitalization('Series b Methods', true));
    }

    // ---- additional section and article capitalization cases ----

    #[DataProvider('capitalLabels')]
    public function testExplicitCapitalLabelsRemainCapitalized(string $input, string $expected): void {
        $this->assertSame($expected, title_capitalization($input, true));
    }

    public static function capitalLabels(): array {
        return [
            'section label after colon' => ['Methods: Section A Results', 'Methods: Section A Results'],
            'part label after dash' => ['Methods - Part A Results', 'Methods - Part A Results'],
            'series label before comma' => ['Series A, Methods', 'Series A, Methods'],
            'section label with unicode preceding text' => ['Études Section A Methods', 'Études Section A Methods'],
            'multiple capital labels' => ['Section A and Part A Methods', 'Section A and Part A Methods'],
            'capital label at end' => ['Acta Crystallographica Section A', 'Acta Crystallographica Section A'],
        ];
    }

    #[DataProvider('articles')]
    public function testIndefiniteArticlesRemainLowercase(string $input, string $expected): void {
        $this->assertSame($expected, title_capitalization($input, true));
    }

    public static function articles(): array {
        return [
            'article after ordinary word' => ['A Tale of A City', 'A Tale of a City'],
            'part article in a phrase' => ['We Took Part A Few Times', 'We Took Part a Few Times'],
            'lowercase section label remains lowercase' => ['Section a Methods', 'Section a Methods'],
            'lowercase part label at end' => ['Part a', 'Part a'],
        ];
    }

    public function testArticleStartingSubtitleKeepsCapitalA(): void {
        $this->assertSame('Methods: A New Approach', title_capitalization('Methods: A New Approach', true));
    }



    // ---- Unicode, punctuation, and grammatical capitalization cases ----

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
