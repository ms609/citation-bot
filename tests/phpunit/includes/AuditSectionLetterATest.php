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
        $this->assertSame('Series a Methods', title_capitalization('Series a Methods', true));
        $this->assertSame('Series a and b Methods', title_capitalization('Series a and b Methods', true));
        $this->assertSame('Series a', title_capitalization('Series a', true));
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

    public function testOnlyOriginallyUppercaseLabelsArePreserved(): void {
        $this->assertSame('Part A Methods: Take Part a Little', title_capitalization('Part A Methods: Take Part a Little', true));
        $this->assertSame('Class A and Section a Methods', title_capitalization('Class A and Section a Methods', true));
        $this->assertSame('Series A', title_capitalization('Series A', true));
        $this->assertSame('Series A', title_capitalization('SERIES A', true));
        $this->assertSame('Journal Series A and B Methods', title_capitalization('Journal Series A and b Methods', true));
        $this->assertSame('Series B Methods', title_capitalization('Series b Methods', true));
    }
}
