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

    public function testSuccessfulLookupIsReusedAcrossCleanupAndPagePhases(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $_template, ?string $_url = null) use (&$calls): bool {
            $calls++;
            return true;
        }, true);
        try {
            $template = $this->make_citation(
                '{{cite journal|title=Original|contribution-url=https://example.org/article}}'
            );
            clean_existing_urls($template, 'contribution-url');
            $template->scan_identifiers_from_url_if_needed(); // Same entry point as Page.php
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(1, $calls, 'One successful scan must suffice across call sites');

            $template->set('title', 'Changed');
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(2, $calls, 'Citation metadata changes invalidate the cache');
            $template->set('contribution-url', 'https://example.org/different');
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(3, $calls, 'Changing the URL invalidates the cache');

            $other = $this->make_citation(
                '{{cite journal|title=Original|contribution-url=https://example.org/article}}'
            );
            clean_existing_urls($other, 'contribution-url');
            $this->assertSame(4, $calls, 'Different citations never share state');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testFailedIdentifierLookupIsRetryable(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $_template, ?string $_url = null) use (&$calls): bool {
            $calls++;
            return $calls >= 2;
        }, true);
        try {
            $template = $this->make_citation('{{cite journal|contribution-url=https://example.org/article}}');
            clean_existing_urls($template, 'contribution-url');
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(2, $calls, 'A failed or negative lookup must not be permanently cached');
            clean_existing_urls($template, 'contribution-url');
            $this->assertSame(2, $calls, 'The later successful result may be cached');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testMultipleUrlsAreNotCachedOnPartialSuccess(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $_template, ?string $_url = null) use (&$calls): bool {
            $calls++;
            return true; // Even a true response might hide a second failed URL.
        }, true);
        try {
            $template = $this->make_citation(
                '{{cite book|url=https://example.org/book|chapter-url=https://example.org/chapter}}'
            );
            $template->scan_identifiers_from_url_if_needed();
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(2, $calls, 'Do not cache successes across different URL sources');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testNestedSameStateLookupDoesNotReenter(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $template, ?string $_url = null) use (&$calls): bool {
            $calls++;
            if ($calls === 1) {
                $template->scan_identifiers_from_url_if_needed();
            }
            return true;
        }, true);
        try {
            $template = $this->make_citation('{{cite journal|contribution-url=https://example.org/article}}');
            $template->scan_identifiers_from_url_if_needed();
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(1, $calls);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testNestedLookupAfterMetadataChangeIsDeferred(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $template, ?string $_url = null) use (&$calls): bool {
            $calls++;
            if ($calls === 1) {
                $template->set('title', 'Discovered title');
                $template->scan_identifiers_from_url_if_needed();
            }
            return true;
        }, true);
        try {
            $template = $this->make_citation('{{cite journal|title=Original|contribution-url=https://example.org/paper}}');
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(1, $calls, 'Do not recursively scan after metadata changes mid-lookup');
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(1, $calls, 'The completed lookup should cache its final state');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testChangedUrlDuringNestedLookupIsProcessedOnceAfterOuterScan(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $template, ?string $_url = null) use (&$calls): bool {
            ++$calls;
            if ($calls === 1) {
                $template->set('contribution-url', 'https://example.org/new');
                $template->scan_identifiers_from_url_if_needed();
            }
            return true;
        }, true);
        try {
            $template = $this->make_citation('{{cite journal|contribution-url=https://example.org/old}}');
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(2, $calls, 'A newly introduced URL needs one deferred scan');
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(2, $calls, 'The stable result is reused after the deferred scan');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testRepeatedNestedUrlChangesHaveBoundedScans(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $template, ?string $_url = null) use (&$calls): bool {
            ++$calls;
            $template->set('contribution-url', 'https://example.org/version-' . $calls);
            $template->scan_identifiers_from_url_if_needed();
            return true;
        }, true);
        try {
            $template = $this->make_citation('{{cite journal|contribution-url=https://example.org/start}}');
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(2, $calls, 'Only one extra pass is permitted for URL churn');
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(4, $calls, 'Unsettled URLs remain retryable on a later call');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testMissingUrlsDoNotTriggerIdentifierLookups(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $_template, ?string $_url = null) use (&$calls): bool {
            ++$calls;
            return false;
        }, true);
        try {
            $template = $this->make_citation('{{cite journal|title=No link}}');
            for ($i = 0; $i < 10; ++$i) {
                $template->scan_identifiers_from_url_if_needed();
            }
            $this->assertSame(0, $calls, 'No URL or website means there is nothing to extract');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testManyRepeatedSuccessfulScansCallDiscoveryOnlyOnce(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $_template, ?string $_url = null) use (&$calls): bool {
            ++$calls;
            return true;
        }, true);
        try {
            $template = $this->make_citation('{{cite journal|contribution-url=https://example.org/once}}');
            for ($i = 0; $i < 50; ++$i) {
                $template->scan_identifiers_from_url_if_needed();
            }
            $this->assertSame(1, $calls, 'Fifty identical requests must require only one successful lookup');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }
    public function testMultipleUrlReentrantLookupIsAlsoGuarded(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
        }

        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('find_identifiers_in_urls', static function (Template $template, ?string $_url = null) use (&$calls): bool {
            $calls++;
            if ($calls === 1) {
                $template->scan_identifiers_from_url_if_needed();
            }
            return true;
        }, true);
        try {
            $template = $this->make_citation(
                '{{cite journal|url=https://example.org/main|chapter-url=https://example.org/chapter}}'
            );
            $template->scan_identifiers_from_url_if_needed();
            $this->assertSame(1, $calls, 'Reentrancy protection applies even when caching is disabled');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }

    public function testIdentifierLookupCacheRecordsPostExtractionState(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('Counting identifier lookups requires uopz');
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
            $this->assertSame(1, $calls, 'Metadata added by discovery must not cause an immediate repeat');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('find_identifiers_in_urls');
        }
    }
}
