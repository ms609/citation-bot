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

    public function testRepeatedSecondaryUrlCleanupAvoidsRedundantIdentifierLookups(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting URL lookups requires uopz');
        }

        $calls = 0;
        // Stub only identifier discovery, so the real cleanup, decision and
        // per-template cache still run without external API dependencies.
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $_template, ?string $_url = null) use (&$calls): bool {
            $calls++;
            return false;
        }, true);
        try {
            $template = $this->make_citation(
                '{{cite journal|title=Original|contribution-url=https://example.org/article|article-url=https://example.org/related}}'
            );

            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(1, $calls, 'The first unchanged secondary URL must be scanned');

            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(1, $calls, 'Repeating identical cleanup must not scan again');

            // No-argument discovery selects from the whole citation, so a
            // different secondary parameter must not repeat the same scan.
            clean_existing_urls($template, 'article-url');
            $this->assertSame(1, $calls, 'Other secondary URL cleanup should reuse the unchanged state');

            $template->set('title', 'Changed');
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(2, $calls, 'Changing citation metadata must allow a new lookup');

            $template->set('contribution-url', 'https://example.org/other');
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(3, $calls, 'Changing the URL must allow a new lookup');

            $other = $this->make_citation(
                '{{cite journal|title=Original|contribution-url=https://example.org/article}}'
            );
            clean_existing_urls($other, 'contribution-url');
            $this->assertSame(4, $calls, 'Another citation must have an independent cache');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testIdentifierLookupCacheRecordsPostExtractionState(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting URL lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $template, ?string $_url = null) use (&$calls): bool {
            $calls++;
            if ($calls === 1) {
                $template->set('title', 'Discovered title');
            }
            return true;
        }, true);
        try {
            $template = $this->make_citation(
                '{{cite journal|title=Original|contribution-url=https://example.org/paper}}'
            );
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame('Discovered title', $template->get('title'));
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(1, $calls, 'Metadata added by the lookup must not cause an immediate duplicate');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }
}
