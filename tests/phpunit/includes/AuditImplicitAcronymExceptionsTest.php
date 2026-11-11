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
            'Monatsschrift abbreviation' => ['Mschr. Kinderheilk.'],
            'Zeitschrift abbreviation' => ['Ztschr. Physik'],
            'French eau after an apostrophe' => ["Revue des Sciences de l'eau"],
            'French eau as a word' => ['Eau et Industrie'],
            'a real vowel acronym stays upper case' => ['EAU Guidelines'],
        ];
    }

    public function testOtherVowelRunsAreStillAcronyms(): void {
        $this->assertSame('Journal of the IEEE', title_capitalization('Journal of the Ieee', true));
    }

    public function testListsStayPaired(): void {
        $this->assertSame(count(UCFIRST_JOURNAL_ACRONYMS), count(JOURNAL_ACRONYMS));
        foreach ([' WSCHR ' => ' Wschr ', ' WSCHR. ' => ' Wschr. ', ' MSCHR. ' => ' Mschr. ', ' ZTSCHR. ' => ' Ztschr. '] as $from => $to) {
            $index = array_search($from, UCFIRST_JOURNAL_ACRONYMS, true);
            $this->assertNotFalse($index);
            $this->assertSame($to, JOURNAL_ACRONYMS[$index]);
        }
    }
}
