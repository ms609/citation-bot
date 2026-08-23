<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class NameToolsCoverageTest extends testBaseClass {

    #[DataProvider('surnameProvider')]
    public function testFormatSurnameCoverage(string $input, string $expected): void {
        $this->assertSame($expected, format_surname($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function surnameProvider(): array {
        return [
            'single lowercase initial' => [
                'q',
                'Q',
            ],
            'single lowercase initial with period' => [
                'q.',
                'Q.',
            ],
            'O apostrophe prefix' => [
                "O'BRIEN",
                "O'Brien",
            ],
            'Mc prefix' => [
                'MCDONALD',
                'McDonald',
            ],
            'Mac prefix' => [
                'MACDONALD',
                'MacDonald',
            ],
            'Mac followed by h is not special-cased' => [
                'MACHADO',
                'Machado',
            ],
            'und particle is lowercased' => [
                'MEYER UND SCHMIDT',
                'Meyer und Schmidt',
            ],
        ];
    }

    #[DataProvider('initialsProvider')]
    public function testFormatInitialsCoverage(string $input, string $expected): void {
        $this->assertSame($expected, format_initials($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function initialsProvider(): array {
        return [
            'plain initials' => [
                'ab',
                'A.B.',
            ],
            'already punctuated initials' => [
                'A.B.',
                'A.B.',
            ],
            'hyphenated initials' => [
                'a-b',
                'A.B.',
            ],
            'semicolon is preserved' => [
                'a-b;',
                'A.B.;',
            ],
        ];
    }

    #[DataProvider('isInitialsProvider')]
    public function testIsInitialsCoverage(string $input, bool $expected): void {
        $this->assertSame($expected, is_initials($input));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function isInitialsProvider(): array {
        return [
            'one character' => [
                'A',
                true,
            ],
            'two uppercase characters' => [
                'AB',
                true,
            ],
            'two lowercase characters' => [
                'ab',
                false,
            ],
            'too many characters' => [
                'ABCD',
                false,
            ],
            'punctuation does not count toward length' => [
                'A-B',
                true,
            ],
        ];
    }

    #[DataProvider('humanAuthorProvider')]
    public function testAuthorIsHumanAdditionalBranches(
        string $author,
        bool $expected
    ): void {
        $this->assertSame($expected, author_is_human($author));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function humanAuthorProvider(): array {
        return [
            // Hits NON_HUMAN_AUTHORS substring detection.
            'university is institutional' => [
                'Example University',
                false,
            ],
            'library is institutional' => [
                'Example Library',
                false,
            ],
            'association is institutional' => [
                'Example Association',
                false,
            ],

            // Separate explicit suffix from the already-tested "Books".
            'singular book suffix' => [
                'Example Book',
                false,
            ],

            // Boundary: exactly three spaces is permitted.
            'four name components' => [
                'John Paul George Smith',
                true,
            ],
        ];
    }

    #[DataProvider('sirAuthorProvider')]
    public function testFormatAuthorRemovesSirForms(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_author($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sirAuthorProvider(): array {
        return [
            'parenthesized sir' => [
                'John (Sir) Smith',
                'Smith, John',
            ],
            'parenthesized sir with period' => [
                'John (sir.) Smith',
                'Smith, John',
            ],
            'leading sir' => [
                'sir John Smith',
                'Smith, John',
            ],
            'sir after leading comma' => [
                ', sir Smith',
                'Smith',
            ],
        ];
    }

    public function testCleanUpFullNamesPreservesOneTrailingPeriod(): void {
        $this->assertSame(
            'John Smith.',
            clean_up_full_names('John Smith...')
        );
    }

    public function testCleanUpFirstNamesPreservesTrailingPeriod(): void {
        $this->assertSame(
            'John.',
            clean_up_first_names('John.')
        );
    }

    public function testCleanUpLastNamesCollapsesInternalDoublePeriods(): void {
        $this->assertSame(
            'St. John',
            clean_up_last_names('St.. John')
        );
    }

    public function testFormatForenameNormalizesLongUppercaseName(): void {
        $this->assertSame(
            'John',
            format_forename('JOHN')
        );
    }

    public function testFormatForenameRemovesSpaceBeforePeriod(): void {
        $this->assertSame(
            'A B',
            format_forename('A . B')
        );
    }

    public function testSplitAuthorsFallsBackToCommas(): void {
        $this->assertSame(
            ['Smith', 'John', 'Doe', 'Jane'],
            split_authors('Smith,John,Doe,Jane')
        );
    }

    public function testJuniorTestAcceptsOrdinalWithPeriod(): void {
        $this->assertSame(
            ['Smith', ' 4th.'],
            junior_test('Smith 4th.')
        );
    }
}
