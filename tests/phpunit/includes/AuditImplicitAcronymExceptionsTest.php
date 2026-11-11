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

    /**
     * @return array<string, array{string, string}>
     */
    public static function frenchEauProvider(): array {
        return [
            'all-caps French eau at start' => ['EAU ET INDUSTRIE', 'Eau et Industrie'],
            'all-caps French eau before adjective' => ['EAU POTABLE', 'Eau Potable'],
            'all-caps French elision' => ["L'EAU ET LA VIE", "L'Eau et la Vie"],
            'mixed-case French eau' => ['Eau et Industrie', 'Eau et Industrie'],
            'mixed-case French elision' => ["Revue des Sciences de l'eau", "Revue des Sciences de l'eau"],
            'French eau before adjective' => ['EAU NATURELLE', 'Eau Naturelle'],
            'French eau with apostrophe' => ["D'EAU POTABLE", "D'Eau Potable"],
            'French hyphenated water' => ['EAU-POTABLE', 'Eau-Potable'],
        ];
    }

    #[DataProvider('frenchEauProvider')]
    public function testFrenchEauIsNotAnAcronym(string $input, string $expected): void {
        $this->assertSame($expected, title_capitalization($input, true));
        $this->assertSame($expected, title_capitalization($input, false));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function acronymEauProvider(): array {
        return [
            'all-caps acronym in Guidelines' => ['EAU GUIDELINES', 'EAU Guidelines'],
            'all-caps acronym in singular Guideline' => ['EAU GUIDELINE UPDATE', 'EAU Guideline Update'],
            'all-caps acronym not at start' => ['NEW EAU GUIDELINES', 'New EAU Guidelines'],
            'quoted acronym' => ["'EAU GUIDELINES'", "'EAU Guidelines'"],
            'already-capitalized acronym' => ['EAU Guidelines', 'EAU Guidelines'],
            'annual congress' => ['EAU ANNUAL CONGRESS', 'EAU Annual Congress'],
            'urology congress' => ['THE EAU CONGRESS', 'The EAU Congress'],
            'recommendations' => ['EAU RECOMMENDATIONS', 'EAU Recommendations'],
            'association' => ['EAU ASSOCIATION', 'EAU Association'],
            'hyphenated guidelines' => ['EAU-GUIDELINES', 'EAU-Guidelines'],
            'en-dash guidelines' => ['EAU–GUIDELINES', 'EAU–Guidelines'],
            'dated guidelines' => ['EAU 2024 GUIDELINES', 'EAU 2024 Guidelines'],
            'dated hyphenated guidelines' => ['EAU-2024-GUIDELINES', 'EAU-2024-Guidelines'],
            'dated congress' => ['THE EAU 2025 CONGRESS', 'The EAU 2025 Congress'],
        ];
    }

    #[DataProvider('acronymEauProvider')]
    public function testEauAcronymIsPreserved(string $input, string $expected): void {
        $this->assertSame($expected, title_capitalization($input, true));
        $this->assertSame($expected, title_capitalization($input, false));
    }
}
