<?php
declare(strict_types=1);

/**
 * Regression coverage for secondary URL identifier discovery (PR #6304).
 */
require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditUrlCleanupIdentifierScanTest extends testBaseClass {
    /** @return array<string, array{string, string, string, string, bool}> */
    public static function scanCases(): array {
        $a = 'https://example.org/a';
        $b = 'https://example.org/b';
        $old = 'http://example.org/a';
        return [
            'primary unchanged' => ['url', $a, $a, $a, false],
            'primary normalized' => ['url', $old, $a, $a, true],
            'only contribution URL' => ['contribution-url', $a, $a, '', true],
            'only chapter URL' => ['chapter-url', $a, $a, '', true],
            'secondary different from primary' => ['chapter-url', $a, $a, $b, true],
            'secondary same as primary' => ['chapter-url', $a, $a, $a, false],
            'modified secondary matching primary' => ['contribution-url', $old, $a, $old, true],
            'normalized secondary with no primary' => ['contribution-url', $old, $a, '', true],
        ];
    }

    #[DataProvider('scanCases')]
    public function testScanDecision(string $param, string $before, string $after, string $primary, bool $expected): void {
        $this->assertSame($expected, url_cleanup_needs_identifier_scan($param, $before, $after, $primary));
    }

    /**
     * The earlier one-line change suppressed extraction of the MR identifier
     * when the normalized contribution URL was unchanged.
     */
    public function testUnchangedContributionUrlStillExtractsMathSciNetMr(): void {
        $page = $this->process_page(
            '{{cite journal|distribution-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234|title=mr=1234}}'
        );
        $this->assertMatchesRegularExpression('~\|\s*mr\s*=\s*1234(?:\s|\||\})~', $page->parsed_text());
    }
}
