<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

/** Adversarial, boundary, and Unicode regressions for PR 6334. */
final class AuditTitleSimilarityObscureTest extends testBaseClass {
    #[DataProvider('differentWorks')]
    public function testDifferentWorksInBothDirections(string $left, string $right): void {
        $this->assertFalse(titles_are_similar($left, $right), 'forward comparison');
        $this->assertFalse(titles_are_similar($right, $left), 'reverse comparison');
    }

    public static function differentWorks(): array {
        $long = str_repeat('Experimental observations of complex systems ', 8);
        $unicode = str_repeat('Études des phénomènes moléculaires ', 10);
        return [
            'first of two Arabic numbers' => ['Volume 2 Chapter 8', 'Volume 3 Chapter 8'],
            'second of two Arabic numbers' => ['Volume 2 Chapter 8', 'Volume 2 Chapter 9'],
            'first of two Roman numbers' => ['Part IV Section II', 'Part V Section II'],
            'second of two Roman numbers' => ['Part IV Section II', 'Part IV Section III'],
            'mixed Roman and Arabic unequal' => ['Part IV Chapter 2', 'Part 5 Chapter II'],
            'HTML numeric character reference separator' => ['Section&#32;A Results', 'Section&#32;B Results'],
            'HTML hexadecimal nonbreaking space' => ['Part&#xA0;1 Results', 'Part&#xA0;2 Results'],
            'Unicode nonbreaking space' => ["Part\u{00A0}1 Results", "Part\u{00A0}2 Results"],
            'Unicode narrow nonbreaking space' => ["Part\u{202F}1 Results", "Part\u{202F}2 Results"],
            'long ASCII title letter' => [$long . 'Section A', $long . 'Section B'],
            'long Unicode title letter' => [$unicode . 'Section A', $unicode . 'Section B'],
            'long Unicode title number' => [$unicode . 'Part 11', $unicode . 'Part 12'],
            'single-letter hepatitis variants' => ['Hepatitis C Treatment', 'Hepatitis D Treatment'],
            'short two-letter titles' => ['AB', 'CD'],
            'short three-letter titles' => ['Cat', 'Car'],
            'short four-letter titles' => ['Mars', 'Maps'],
            'different numeric year with accent' => ['Résumé 2022', 'Résumé 2023'],
            'Roman section with typo' => ['Colour Vision Section IV', 'Color Vison Section V'],
            'different lettered labels with typo' => ['Colour Vision Section A', 'Color Vison Section B'],
            'different number with punctuation' => ['Part 1: Introduction', 'Part 2 — Introduction'],
            'plural equivalence never overrides numbering' => ['Part 1 Studies', 'Part 2 Study'],
            'plural equivalence never overrides section letter' => ['Section A Studies', 'Section B Study'],
            'plural equivalence cannot ignore other word changes' => ['Civil Society Studies', 'Medical Society Study'],
        ];
    }

    #[DataProvider('sameWorks')]
    public function testEquivalentWorksInBothDirections(string $left, string $right): void {
        $this->assertTrue(titles_are_similar($left, $right), 'forward comparison');
        $this->assertTrue(titles_are_similar($right, $left), 'reverse comparison');
    }

    public static function sameWorks(): array {
        return [
            'Roman four and Arabic four' => ['Part IV', 'Part 4'],
            'Roman nine and Arabic nine' => ['Section IX Methods', 'Section 9 Methods'],
            'Roman forty and Arabic forty' => ['Section XL Methods', 'Section 40 Methods'],
            'mixed numbers equivalent' => ['Part IV Chapter 2', 'Part 4 Chapter II'],
            'accented and unaccented' => ['Études moléculaires', 'Etudes moleculaires'],
            'encoded space and plain space' => ['Part&nbsp;IV Methods', 'Part 4 Methods'],
            'Unicode nonbreaking space and ASCII' => ["Part\u{00A0}IV Methods", 'Part 4 Methods'],
            'regular word mix is not Roman' => ['Mix and Match', 'Mix and Matches'],
            'ordinary word civil is not numeral' => ['Civil Society Studies', 'Civil Society Study'],
            'case studies singular/plural' => ['Case Studies', 'Case Study'],
            'policy singular/plural' => ['Urban Policies', 'Urban Policy'],
            'unicode prefix before English plural' => ['Résumé Studies', 'Résumé Study'],
        ];
    }

    #[DataProvider('numberingHelperCases')]
    public function testNumberingHelper(string $left, string $right, ?bool $expected): void {
        $this->assertSame($expected, compare_title_numbering($left, $right));
        $this->assertSame($expected, compare_title_numbering($right, $left));
    }

    public static function numberingHelperCases(): array {
        return [
            'Roman IV equals 4' => ['Part IV', 'Part 4', true],
            'Roman IX equals 9' => ['Section IX', 'Section 9', true],
            'Roman XL equals 40' => ['Section XL', 'Section 40', true],
            'Roman IV differs from VI' => ['Part IV', 'Part VI', false],
            'year differs' => ['Annual Report 2022', 'Annual Report 2023', false],
            'Roman word without label' => ['Mix of Methods', '1009 of Methods', null],
            'unlabeled Roman-looking token' => ['Civil Studies', 'Civix Studies', null],
            'no numbers' => ['Quantum Mechanics', 'Classical Mechanics', null],
            'one missing number' => ['Part 1 Methods', 'Part Methods', null],
        ];
    }

    // ---- bidirectional title similarity regressions ----

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
