<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class NameToolsEdgeCaseTest extends testBaseClass {

    #[DataProvider('juniorBoundaryProvider')]
    public function testJuniorTestBoundaryCases(
        string $input,
        array $expected
    ): void {
        $this->assertSame($expected, junior_test($input));
    }

    /**
     * @return array<string, array{string, array{string, string}}>
     */
    public static function juniorBoundaryProvider(): array {
        return [
            'trailing comma without suffix is removed' => [
                'Smith,',
                ['Smith', ''],
            ],
            'two digit ordinal suffix is accepted' => [
                'Smith 10th',
                ['Smith', ' 10th'],
            ],
            'largest two digit ordinal is accepted' => [
                'Smith 99th',
                ['Smith', ' 99th'],
            ],
            'three digit ordinal is not treated as a suffix' => [
                'Smith 100th',
                ['Smith 100th', ''],
            ],
            'unicode surname is preserved while suffix is removed' => [
                'García 2nd',
                ['García', ' 2nd'],
            ],
        ];
    }

    #[DataProvider('surnameEdgeProvider')]
    public function testFormatSurnameAdditionalCases(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_surname($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function surnameEdgeProvider(): array {
        return [
            'hyphenated uppercase surname' => [
                'SMITH-JONES',
                'Smith-Jones',
            ],
            'unicode uppercase surname' => [
                'GARCÍA',
                'García',
            ],
            'ampersand prefix' => [
                '&SMITH',
                '&Smith',
            ],
            'surrounding whitespace' => [
                '  SMITH  ',
                'Smith',
            ],
        ];
    }

    #[DataProvider('forenameEdgeProvider')]
    public function testFormatForenameAdditionalCases(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_forename($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function forenameEdgeProvider(): array {
        return [
            'hyphenated uppercase forename' => [
                'ANNE-MARIE',
                'Anne-Marie',
            ],
            'unicode uppercase forename' => [
                'ÉLODIE',
                'Élodie',
            ],
            'surrounding whitespace' => [
                '  JOHN  ',
                'John',
            ],
            'initials are not rewritten as a long name' => [
                'A B',
                'A B',
            ],
        ];
    }

    #[DataProvider('vancouverSuffixBoundaryProvider')]
    public function testVancouverSuffixBoundaries(
        string $suffix,
        bool $expected
    ): void {
        $this->assertSame($expected, vanc_suffix_valid($suffix));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function vancouverSuffixBoundaryProvider(): array {
        return [
            'Jnr accepted' => ['Jnr', true],
            'Snr accepted' => ['Snr', true],
            '3rd accepted' => ['3rd', true],
            'lowercase jr rejected' => ['jr', false],
            'uppercase TH rejected' => ['4TH', false],
            'two digit th suffix rejected' => ['10th', false],
        ];
    }

    public function testAuthorIsHumanUsesCharacterLengthForUnicode(): void {
        $this->assertTrue(author_is_human(str_repeat('é', 33)));
        $this->assertFalse(author_is_human(str_repeat('é', 34)));
    }

    public function testAuthorIsHumanLengthBoundaryForAscii(): void {
        $this->assertTrue(author_is_human(str_repeat('a', 33)));
        $this->assertFalse(author_is_human(str_repeat('a', 34)));
    }

    public function testAuthorIsHumanTrimsBeforeSuffixChecks(): void {
        $this->assertFalse(author_is_human('  Example Inc.  '));
        $this->assertFalse(author_is_human("\tExample LLC\n"));
    }

    #[DataProvider('formatAuthorEdgeProvider')]
    public function testFormatAuthorAdditionalCases(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_author($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formatAuthorEdgeProvider(): array {
        return [
            'html encoded apostrophe in surname' => [
                'O&#039;BRIEN, JOHN',
                "O'Brien, John",
            ],
            'leading conjunction is removed' => [
                'and JOHN SMITH',
                'Smith, John',
            ],
            'leading and trailing housekeeping punctuation' => [
                ';, JOHN SMITH ,;',
                'Smith, John',
            ],
            'unicode surname and forename' => [
                'GARCÍA, JOSÉ',
                'García, José',
            ],
            'hyphenated surname and forename' => [
                'SMITH-JONES, ANNE-MARIE',
                'Smith-Jones, Anne-Marie',
            ],
            'ordinal suffix following comma form' => [
                'SMITH, JOHN, 10th',
                'Smith, John 10th',
            ],
            'middle initial without punctuation' => [
                'JOHN Q PUBLIC',
                'Public, John Q.',
            ],
            'surrounding horizontal and vertical whitespace' => [
                "\tSMITH, JOHN\n",
                'Smith, John',
            ],
        ];
    }

    #[DataProvider('multipleAuthorsEdgeProvider')]
    public function testFormatMultipleAuthorsAdditionalCases(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_multiple_authors($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function multipleAuthorsEdgeProvider(): array {
        return [
            'and separator does not split Anderson or Landon' => [
                'John Anderson and Jane Landon',
                'Anderson, John; Landon, Jane',
            ],
            'html encoded ampersand becomes author separator' => [
                'John Anderson &amp; Jane Landon',
                'Anderson, John; Landon, Jane',
            ],
            'empty semicolon chunks are ignored' => [
                'John Anderson; ; Jane Landon;',
                'Anderson, John; Landon, Jane',
            ],
            'numeric affiliation markers are discarded' => [
                'John Smith1; Jane Doe2',
                'Smith, John; Doe, Jane',
            ],
            'plus and star affiliation markers are discarded' => [
                'John Smith+; Jane Doe*',
                'Smith, John; Doe, Jane',
            ],
            'single author with trailing comma' => [
                'John Smith,',
                'Smith, John',
            ],
        ];
    }

    #[DataProvider('cleanupProvider')]
    public function testCleanupFunctionsKeepTheirDistinctAndSemantics(
        string $function,
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, $function($input));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function cleanupProvider(): array {
        return [
            'full names retain word and' => [
                'clean_up_full_names',
                'Alice and Bob',
                'Alice and Bob',
            ],
            'last names remove word and' => [
                'clean_up_last_names',
                'Smith and Jones',
                'Smith Jones',
            ],
            'first names remove word and' => [
                'clean_up_first_names',
                'John and Paul',
                'John Paul',
            ],
            'full names normalize comma semicolon' => [
                'clean_up_full_names',
                'Alice,; Bob',
                'Alice; Bob',
            ],
            'last names normalize space semicolon' => [
                'clean_up_last_names',
                'Smith ; Jones',
                'Smith; Jones',
            ],
        ];
    }

    #[DataProvider('splitAuthorEdgeProvider')]
    public function testSplitAuthorAdditionalBoundaries(
        string $input,
        array $expected
    ): void {
        $this->assertSame($expected, split_author($input));
    }

    /**
     * @return array<string, array{string, array<string>}>
     */
    public static function splitAuthorEdgeProvider(): array {
        return [
            'empty forename is retained when there is exactly one comma' => [
                'Smith,',
                ['Smith', ''],
            ],
            'empty surname is retained when there is exactly one comma' => [
                ', John',
                ['', ' John'],
            ],
            'unicode components split without byte corruption' => [
                'García, José',
                ['García', ' José'],
            ],
        ];
    }

    #[DataProvider('underTwoAuthorsEdgeProvider')]
    public function testUnderTwoAuthorsAdditionalBoundaries(
        string $input,
        bool $expected
    ): void {
        $this->assertSame($expected, under_two_authors($input));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function underTwoAuthorsEdgeProvider(): array {
        return [
            'last comma first with no space' => ['Smith,John', true],
            'surrounding whitespace ignored by space comparison' => [' Smith, John ', true],
            'semicolon at first character still indicates multiple authors' => [';Smith', false],
            'empty string is not evidence of multiple authors' => ['', true],
        ];
    }

    #[DataProvider('badAuthorCaseProvider')]
    public function testIsBadAuthorPublishedIsCaseInsensitive(
        string $input,
        bool $expected
    ): void {
        $this->assertSame($expected, is_bad_author($input));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function badAuthorCaseProvider(): array {
        return [
            'lowercase published' => ['published', true],
            'uppercase published' => ['PUBLISHED', true],
            'mixed case published' => ['PuBlIsHeD', true],
            'pipe remains invalid' => ['|', true],
            'published as part of a name is allowed' => ['Published Author', false],
        ];
    }

    #[DataProvider('canonicalAuthorProvider')]
    public function testFormatAuthorIsIdempotentForCanonicalNames(string $name): void {
        $this->assertSame($name, format_author($name));
        $this->assertSame($name, format_author(format_author($name)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function canonicalAuthorProvider(): array {
        return [
            'plain canonical name' => ['Smith, John'],
            'apostrophe canonical name' => ["O'Brien, John"],
            'unicode canonical name' => ['García, José'],
            'hyphenated canonical name' => ['Smith-Jones, Anne-Marie'],
            'canonical ordinal suffix' => ['Smith, John 2nd'],
        ];
    }
}
diff --git a/tests/phpunit/includes/NameToolsMessyInputTest.php b/tests/phpunit/includes/NameToolsMessyInputTest.php
new file mode 100644
index 0000000..80b497c
--- /dev/null
++ b/tests/phpunit/includes/NameToolsMessyInputTest.php
@@ -0,0 +1,332 @@
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression tests for plausible-but-messy author data.
 *
 * These cases deliberately avoid asserting behavior for inputs that appear to
 * expose a defect (those are documented separately in the edge audit).
 */
final class NameToolsMessyInputTest extends testBaseClass {

    #[DataProvider('slightlyMalformedJuniorProvider')]
    public function testJuniorTestHandlesSlightlyMalformedSuffixesPredictably(
        string $input,
        array $expected
    ): void {
        $this->assertSame($expected, junior_test($input));
    }

    /**
     * @return array<string, array{string, array{string, string}}>
     */
    public static function slightlyMalformedJuniorProvider(): array {
        return [
            'uppercase ordinal letters are accepted' => [
                'Smith 4TH.',
                ['Smith', ' 4TH.'],
            ],
            'double period after ordinal is not consumed' => [
                'Smith 2nd..',
                ['Smith 2nd..', ''],
            ],
            'double period after junior is not consumed' => [
                'Smith Jr..',
                ['Smith Jr..', ''],
            ],
            'roman numeral is not treated as an ordinal suffix' => [
                'Smith III',
                ['Smith III', ''],
            ],
            'lowercase junior is not recognized' => [
                'Smith jr.',
                ['Smith jr.', ''],
            ],
            'tab before ordinal is not silently treated as a space' => [
                "Smith\t2nd",
                ["Smith\t2nd", ''],
            ],
        ];
    }

    #[DataProvider('messyFormatAuthorProvider')]
    public function testFormatAuthorCleansPlausibleOuterNoise(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_author($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function messyFormatAuthorProvider(): array {
        return [
            'punctuation only becomes empty' => [
                ' ;, . ,; ',
                '',
            ],
            'repeated outer punctuation is discarded' => [
                '.., John Smith ;,.',
                'Smith, John',
            ],
            'trailing redundant commas are discarded' => [
                'Smith, John,,,',
                'Smith, John',
            ],
            'mixed case leading conjunction is discarded' => [
                'And John Smith',
                'Smith, John',
            ],
            'uppercase parenthesized sir is discarded' => [
                'John (SIR.) Smith',
                'Smith, John',
            ],
            'multiple ordinary spaces do not create empty name components' => [
                "\tJohn   Smith\r\n",
                'Smith, John',
            ],
            'named HTML entities are decoded before name formatting' => [
                'Garc&iacute;a, Jos&eacute;',
                'García, José',
            ],
            'outer semicolon on canonical comma form is discarded' => [
                '; Smith, John ;',
                'Smith, John',
            ],
        ];
    }

    #[DataProvider('messyMultipleAuthorsProvider')]
    public function testFormatMultipleAuthorsToleratesRepeatedSeparatorsAndMarkers(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_multiple_authors($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function messyMultipleAuthorsProvider(): array {
        return [
            'repeated semicolons are ignored' => [
                'John Smith;;; Jane Doe;;;',
                'Smith, John; Doe, Jane',
            ],
            'repeated ampersands are ignored as empty separators' => [
                'John Smith && Jane Doe',
                'Smith, John; Doe, Jane',
            ],
            'repeated word and separators are ignored' => [
                'John Smith and and Jane Doe',
                'Smith, John; Doe, Jane',
            ],
            'uppercase AND is recognized as a separator' => [
                'John Smith AND Jane Doe',
                'Smith, John; Doe, Jane',
            ],
            'parenthesized numeric affiliation markers are discarded' => [
                'John Smith(1); Jane Doe(2)',
                'Smith, John; Doe, Jane',
            ],
            'leading and trailing separators do not create authors' => [
                ';; John Smith; Jane Doe;;',
                'Smith, John; Doe, Jane',
            ],
        ];
    }

    #[DataProvider('surnameNoiseProvider')]
    public function testFormatSurnameHandlesAdditionalRealWorldForms(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_surname($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function surnameNoiseProvider(): array {
        return [
            'unicode one-letter surname is uppercased as an initial' => [
                'é',
                'É',
            ],
            'single CJK surname remains intact' => [
                '王',
                '王',
            ],
            'von particle is normalized through public formatter' => [
                'VON TRAPP',
                'von Trapp',
            ],
            'de la particle is normalized through public formatter' => [
                'DE LA CRUZ',
                'de la Cruz',
            ],
            'O apostrophe plus hyphenated surname keeps both rules' => [
                "O'NEILL-SMITH",
                "O'Neill-Smith",
            ],
        ];
    }

    #[DataProvider('noisyInitialProvider')]
    public function testFormatInitialsIgnoresCommonSeparatorNoise(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, format_initials($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function noisyInitialProvider(): array {
        return [
            'commas are ignored' => ['A,B', 'A.B.'],
            'slashes are ignored' => ['A/B', 'A.B.'],
            'plus signs are ignored' => ['A+B', 'A.B.'],
            'spaces and periods are tolerated' => [' A . B ', 'A.B.'],
            'semicolon survives after noisy punctuation' => [' A / B ; ', 'A.B.;'],
        ];
    }

    #[DataProvider('isInitialsNoiseProvider')]
    public function testIsInitialsAdditionalPunctuationBoundaries(
        string $input,
        bool $expected
    ): void {
        $this->assertSame($expected, is_initials($input));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function isInitialsNoiseProvider(): array {
        return [
            'surrounding spaces are ignored' => [' A ', true],
            'three dotted uppercase initials are accepted' => ['A.B.C.', true],
            'semicolon between uppercase initials is ignored for length' => ['A;B', true],
            'mixed lowercase and uppercase is rejected' => ['a.B', false],
            'four dotted initials are too long' => ['A.B.C.D.', false],
        ];
    }

    #[DataProvider('cleanupMarkerProvider')]
    public function testCleanupFunctionsDiscardStackedAffiliationMarkers(
        string $function,
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, $function($input));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function cleanupMarkerProvider(): array {
        return [
            'full name plus and star markers' => [
                'clean_up_full_names',
                'Alice+* Smith',
                'Alice Smith',
            ],
            'last name plus and star markers' => [
                'clean_up_last_names',
                'Smith+* Jones',
                'Smith Jones',
            ],
            'first name plus and star markers' => [
                'clean_up_first_names',
                'John+* Paul',
                'John Paul',
            ],
            'single initial still gains a period after trimming' => [
                'clean_up_first_names',
                "\tQ\n",
                'Q.',
            ],
        ];
    }

    public function testSplitAuthorPreservesMessyComponentWhitespaceRatherThanCorruptingIt(): void {
        $this->assertSame(
            ['Smith', "\tJohn"],
            split_author("Smith,\tJohn")
        );
    }

    public function testSplitAuthorsPreservesTrailingEmptySemicolonComponent(): void {
        $this->assertSame(
            ['Smith', ''],
            split_authors('Smith;')
        );
    }

    #[DataProvider('badAuthorNearMissProvider')]
    public function testIsBadAuthorDoesNotOvermatchNearMisses(
        string $input,
        bool $expected
    ): void {
        $this->assertSame($expected, is_bad_author($input));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function badAuthorNearMissProvider(): array {
        return [
            'published with punctuation is not the sentinel' => ['Published.', false],
            'published followed by a name is not the sentinel' => ['Published Author', false],
            'publisher is not the sentinel' => ['Publisher', false],
            'two pipes are not the exact pipe sentinel' => ['||', false],
        ];
    }

    #[DataProvider('humanNearMissProvider')]
    public function testAuthorIsHumanDoesNotOvermatchSimilarWords(
        string $input,
        bool $expected
    ): void {
        $this->assertSame($expected, author_is_human($input));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function humanNearMissProvider(): array {
        return [
            'Theodore is not the word The' => ['Theodore Smith', true],
            'Booker is not a book suffix' => ['Booker Smith', true],
            'lowercase abc is not an uppercase acronym' => ['abc Smith', true],
            'two uppercase characters do not trigger three-letter acronym rule' => ['AB Smith', true],
        ];
    }

    #[DataProvider('vancouverMalformedProvider')]
    public function testVancouverSuffixRejectsWhitespaceAndPunctuationNoise(
        string $input
    ): void {
        $this->assertFalse(vanc_suffix_valid($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function vancouverMalformedProvider(): array {
        return [
            'leading space' => [' Jr'],
            'trailing space' => ['Jr '],
            'comma' => ['Jr,'],
            'period' => ['2nd.'],
            'embedded space' => ['2 nd'],
        ];
    }
}
