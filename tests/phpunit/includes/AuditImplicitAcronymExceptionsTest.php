<?php
declare(strict_types=1);

/**
 * Words made only of consonants or only of vowels are upper-cased as implied acronyms.
 * Common German journal abbreviations and the French word "eau" are not acronyms.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditImplicitAcronymExceptionsTest extends testBaseClass {

    #[DataProvider('unchangedProvider')]
    public function testNameIsLeftAlone(string $name): void {
        $this->assertSame($name, title_capitalization($name, true));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unchangedProvider(): array {
        return [
            'Wochenschrift abbreviation' => ['Klin. Wschr.'],
            'Wochenschrift abbreviation without dot' => ['Klin Wschr'],
            'Wochenschrift abbreviation before a comma' => ['Wschr., Neue Folge'],
            'Monatsschrift abbreviation' => ['Mschr. Kinderheilk.'],
            'Monatsschrift abbreviation in parentheses' => ['Kinderheilkunde (Mschr.)'],
            'Zeitschrift abbreviation' => ['Ztschr. Physik'],
            'Zeitschrift abbreviation before a semicolon' => ['Ztschr; Physik'],
            'French eau after an apostrophe' => ["Revue des Sciences de l'eau"],
            'French eau as a word' => ['Eau et Industrie'],
            'a real vowel acronym stays upper case' => ['EAU Guidelines'],
        ];
    }

    public function testOtherVowelRunsAreStillAcronyms(): void {
        $this->assertSame('Journal of the IEEE', title_capitalization('Journal of the Ieee', true));
    }

    public function testAcronymSurvivesTitleCaseConversion(): void {
        $this->assertSame('EAU Guidelines', title_capitalization('EAU GUIDELINES', true));
        $this->assertSame("'EAU Guidelines'", title_capitalization("'EAU GUIDELINES'", true));
        $this->assertSame("L'Eau et la Vie", title_capitalization("L'EAU ET LA VIE", true));
    }

    public function testAbbreviationsSurviveTitleCaseConversion(): void {
        $this->assertSame('Klin. Wschr., Neue Folge', title_capitalization('KLIN. WSCHR., NEUE FOLGE', true));
    }
}
