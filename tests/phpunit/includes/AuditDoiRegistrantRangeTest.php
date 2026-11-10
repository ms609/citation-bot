<?php
declare(strict_types=1);

/**
 * The DOI registrant rules are defined once, in doi_registrant_code_valid(), and used by both
 * is_doi_works() and doi_registrant_valid(), so the two can never disagree.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditDoiRegistrantRangeTest extends testBaseClass {

    /**
     * @return array<string, array{string, bool}>
     */
    public static function registrantProvider(): array {
        return [
            'one digit' => ['1', false],
            'two digits with subcode' => ['12.3', false],
            'three digits' => ['123', false],
            'three digits with subcode' => ['123.4', true],
            'four digits, leading zero' => ['0123', false],
            'four digits, leading zero, subcode' => ['0123.4', false],
            'lowest four digits' => ['1000', true],
            'four digits with subcode' => ['1234.5', true],
            'four digits with two subcodes' => ['1234.5.6', true],
            'test registrant' => ['5555', false],
            'test registrant with subcode' => ['5555.1', false],
            'highest four digits' => ['9999', true],
            'five digits, leading zero' => ['01234', false],
            'lowest five digits' => ['10000', true],
            'five digits with subcode, in range' => ['39999.1', true],
            'five digits with subcode, above range' => ['40000.1', false],
            'five digits 7xxxx' => ['70000', true],
            'five digits 8xxxx' => ['80000', true],
            'highest five digits' => ['89999', true],
            'five digits above range' => ['90000', false],
            'six digits' => ['100000', false],
            'six digits with subcode' => ['123456.7', false],
            'letters' => ['abc', false],
            'mixed' => ['12a4', false],
            'trailing dot' => ['1234.', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('registrantProvider')]
    public function testSharedRule(string $registrant, bool $expected): void {
        $this->assertSame($expected, doi_registrant_code_valid($registrant));
    }

    #[DataProvider('registrantProvider')]
    public function testValidatorAgreesWithSharedRule(string $registrant, bool $expected): void {
        $this->assertSame($expected, doi_registrant_valid('10.' . $registrant . '/abc'));
    }

    #[DataProvider('registrantProvider')]
    public function testIsDoiWorksRejectsWhatTheSharedRuleRejects(string $registrant, bool $expected): void {
        if ($expected) {
            // A possible registrant goes on to a live doi.org lookup, which this test must not make.
            $this->assertTrue(doi_registrant_code_valid($registrant));
            return;
        }
        $this->assertFalse(is_doi_works('10.' . $registrant . '/abc'));
    }

    public function testRulesAreNotDuplicatedInIsDoiWorks(): void {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/src/includes/doiTools.php');
        $this->assertStringContainsString('doi_registrant_code_valid(', $source);
        $this->assertStringNotContainsString('\\d\\d\\d\\d$~', $source);
    }
}
