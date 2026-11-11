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
    }

    public function testOnlyOriginallyUppercaseLabelsArePreserved(): void {
        $this->assertSame('Part A Methods: Take Part a Little', title_capitalization('Part A Methods: Take Part a Little', true));
        $this->assertSame('Class A and Section a Methods', title_capitalization('Class A and Section a Methods', true));
    }
}
