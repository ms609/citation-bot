<?php
declare(strict_types=1);

/**
 * The three-edit tolerance of titles_are_similar() must not match different works:
 * titles that differ only in their numbering, and very short titles.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditTitleSimilarityNumberingTest extends testBaseClass {

    #[DataProvider('differentWorksProvider')]
    public function testDifferentWorksAreNotSimilar(string $title1, string $title2): void {
        $this->assertFalse(titles_are_similar($title1, $title2));
        $this->assertFalse(titles_are_similar($title2, $title1));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function differentWorksProvider(): array {
        return [
            'roman part numbers' => ['Henry the Fourth, Part I', 'Henry the Fourth, Part II'],
            'roman numbers with an HTML space' => ['Part I', 'Part&nbsp;II'],
            'different years with an HTML space' => ['Annual Report 2019', 'Annual&nbsp;Report 2020'],
            'arabic part numbers' => ['Henry the Fourth, Part 1', 'Henry the Fourth, Part 2'],
            'years' => ['Annual Report 2019', 'Annual Report 2020'],
            'chapter numbers' => ['Lecture 3', 'Lecture 12'],
            'lettered types' => ['Hepatitis C virus', 'Hepatitis D virus'],
            'world wars' => ['Britain in World War I', 'Britain in World War II'],
            'four letter titles' => ['Cats', 'Dogs'],
            'one letter apart, four letters' => ['Mars', 'Maps'],
            'a word that looks like a Roman numeral' => ['Mix', '1009'],
        ];
    }

    #[DataProvider('sameWorkProvider')]
    public function testSmallDifferencesAreStillSimilar(string $title1, string $title2): void {
        $this->assertTrue(titles_are_similar($title1, $title2));
        $this->assertTrue(titles_are_similar($title2, $title1));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sameWorkProvider(): array {
        return [
            'arabic and roman for the same number' => ['Henry the Fourth, Part 1', 'Henry the Fourth, Part I'],
            'equivalent short part numbers' => ['Part IV', 'Part 4'],
            'equivalent numbers beyond the edit limit' => ['Volume VIII', 'Volume 8'],
            'equivalent formatted part numbers' => ['Part&nbsp;<i>IV</i>', 'Part 4'],
            'spelling variant' => ['Colour perception in birds', 'Color perception in birds'],
            'plural' => ['Editorial comment', 'Editorial comments'],
            'ampersand' => ['Tom & Jerry', 'Tom and Jerry'],
            'leading article' => ['The Lancet', 'Lancet'],
            'punctuation only' => ['Mars', 'Mars.'],
            'one typo in a long title' => ['The effect of zinc on growth in rats', 'The effect of zink on growth in rats'],
            'number against no number' => ['Windows 10 guide', 'Windows guide'],
            'same numbers, different punctuation' => ['Covid-19 in children', 'Covid 19 in children'],
        ];
    }

    public function testNumberingHelper(): void {
        $this->assertFalse(compare_title_numbering('part i', 'part ii'));
        $this->assertFalse(compare_title_numbering('the hobbit, part 1', 'hobbit, part 2'));
        $this->assertTrue(compare_title_numbering('part 1', 'part i'));
        $this->assertNull(compare_title_numbering('windows 10 guide', 'windows guide'));
        $this->assertNull(compare_title_numbering('cats', 'dogs'));
        $this->assertSame(1990, roman_numeral_value('mcmxc'));
        $this->assertSame(14, roman_numeral_value('xiv'));
    }
}
