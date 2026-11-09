<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class FatcatTest extends testBaseClass {
    private const string DOI = '10.1080/14786449908621245';
    private const string LOOKUP = 'https://scholar.archive.org/api/fatcat/v2/release/lookup/fulltext?id_type=doi&id_value=10.1080%2F14786449908621245';
    private const string DOWNLOAD = 'https://archive.org/download/crossref-pre-1909-scholarly-works/10.1080%252F14786449608620921.zip/10.1080%252F14786449908621245.pdf';
    private const string STORAGE = 'https://ia600805.us.archive.org/view_archive.php?archive=/13/items/crossref-pre-1909-scholarly-works/10.1080%252F14786449608620921.zip&file=10.1080%252F14786449908621245.pdf';

    /**
     * @param list<array{status: int, location: string, content_type: string}|null> $responses
     * @param list<array{string, bool}> &$requests
     */
    private function stubFatcat(array $responses, array &$requests): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Fatcat responses');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('fatcat_http_response', static function (string $url, bool $head) use ($responses, &$requests): ?array {
            $index = count($requests);
            $requests[] = [$url, $head];
            return $responses[$index] ?? null;
        }, true);
    }

    /** @return array{status: int, location: string, content_type: string} */
    private static function response(int $status, string $location = '', string $content_type = ''): array {
        return ['status' => $status, 'location' => $location, 'content_type' => $content_type];
    }

    public function testFatcatFollowsArchiveRedirectAndKeepsStableUrl(): void {
        $requests = [];
        $this->stubFatcat([
            self::response(302, self::DOWNLOAD),
            self::response(302, self::STORAGE),
            self::response(200, '', 'Application/PDF; charset=binary'),
        ], $requests);

        try {
            $this->assertSame(self::DOWNLOAD, fatcat_fulltext_url(self::DOI));
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('fatcat_http_response');
        }

        $this->assertSame([[self::LOOKUP, false], [self::DOWNLOAD, true], [self::STORAGE, true]], $requests);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRateLimitSuppressesTheNextLookup(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Fatcat responses');
        }
        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec_withFalse', static function () use (&$calls): string {
            $calls++;
            return '';
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('curl_getinfo', static function (CurlHandle $handle, int $option): int|string {
            return match ($option) {
                CURLINFO_RESPONSE_CODE => 429,
                CURLINFO_RETRY_AFTER => 15,
                default => '',
            };
        }, true);
        try {
            $this->assertNull(fatcat_fulltext_url(self::DOI));
            $this->assertNull(fatcat_fulltext_url('10.1080/14786449908621246'));
            $this->assertSame(1, $calls);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('curl_getinfo');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec_withFalse');
        }
    }

    /** @param list<array{status: int, location: string, content_type: string}|null> $responses */
    #[DataProvider('unusableResponses')]
    public function testFatcatRejectsUnusableResponses(array $responses, int $request_count): void {
        $requests = [];
        $this->stubFatcat($responses, $requests);

        try {
            $this->assertNull(fatcat_fulltext_url(self::DOI));
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('fatcat_http_response');
        }

        $this->assertCount($request_count, $requests);
    }

    /** @return array<string, array{list<array{status: int, location: string, content_type: string}|null>, int}> */
    public static function unusableResponses(): array {
        $download = self::response(302, self::DOWNLOAD);
        return [
            'no public copy' => [[self::response(404)], 1],
            'rate limited' => [[self::response(429)], 1],
            'transport failure' => [[null], 1],
            'HTML verification response' => [[self::response(200, '', 'text/html')], 1],
            'redirect to verification' => [[self::response(302, 'https://scholar.archive.org/verify')], 1],
            'empty location' => [[self::response(302)], 1],
            'external host' => [[self::response(302, 'https://archive.org.example.test/download/a/b.pdf')], 1],
            'userinfo' => [[self::response(302, 'https://example.test@archive.org/download/a/b.pdf')], 1],
            'HTTP copy' => [[self::response(302, 'http://archive.org/download/a/b.pdf')], 1],
            'unreviewed Archive.org item' => [[self::response(302, str_replace('crossref-pre-1909-scholarly-works', 'other-item', self::DOWNLOAD))], 1],
            'case-sensitive Archive.org item' => [[self::response(302, str_replace('crossref-pre-1909-scholarly-works', 'CROSSREF-pre-1909-scholarly-works', self::DOWNLOAD))], 1],
            'Wayback result without release verification' => [[self::response(302, 'https://web.archive.org/web/20200101000000/https://example.org/paper.pdf')], 1],
            'PDF member for a different DOI' => [[self::response(302, str_replace('14786449908621245', '14786449908621246', self::DOWNLOAD))], 1],
            'incomplete item URL' => [[self::response(302, 'https://archive.org/download/a')], 1],
            'unsafe wiki markup' => [[self::response(302, 'https://archive.org/download/a/b|test.pdf')], 1],
            'restricted download' => [[$download, self::response(403)], 2],
            'login page' => [[$download, self::response(200, '', 'text/html')], 2],
            'HEAD transport failure' => [[$download, null], 2],
            'private redirect' => [[$download, self::response(302, 'https://127.0.0.1/private.pdf')], 2],
            'storage host lookalike' => [[$download, self::response(302, 'https://ia600805.us.archive.org.example.test/a.pdf')], 2],
            'storage redirect changes PDF member' => [[$download, self::response(302, str_replace('14786449908621245', '14786449908621246', self::STORAGE))], 2],
            'storage redirect changes collection' => [[$download, self::response(302, str_replace('crossref-pre-1909-scholarly-works', 'other-item', self::STORAGE))], 2],
            'redirect loop' => [[$download, $download, $download, $download], 4],
        ];
    }

    #[DataProvider('openAccessOutcomes')]
    public function testOpenAccessFallback(string $unpaywall, string $fields, int $expected_requests, bool $expect_semantic_scholar): void {
        $requests = [];
        $responses = $expected_requests === 1
            ? [self::response(404)]
            : [self::response(302, self::DOWNLOAD), self::response(200, '', 'application/pdf')];
        $this->stubFatcat($responses, $requests);
        $template = $this->make_citation('{{cite journal|title=Uranium radiation|doi=' . self::DOI . $fields . '}}');
        $before = $template->parsed_text();
        $semantic_scholar_calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_unpaywall_url', $unpaywall);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_semanticscholar_url', static function () use (&$semantic_scholar_calls): void {
            $semantic_scholar_calls++;
        }, true);
        try {
            get_open_access_url($template);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_semanticscholar_url');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_unpaywall_url');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('fatcat_http_response');
        }

        $this->assertCount(SLOW_MODE ? $expected_requests : 0, $requests);
        $this->assertSame((int) ($expect_semantic_scholar || (!SLOW_MODE && $expected_requests > 0)), $semantic_scholar_calls);
        if (SLOW_MODE && $expected_requests === 2) {
            $this->assertSame(self::DOWNLOAD, $template->get2('url'));
            $this->assertSame(self::DOI, $template->get2('doi'));
            $this->assertNull($template->get2('doi-access'));
            $this->assertNull($template->get2('archive-url'));
        } else {
            $this->assertSame($before, $template->parsed_text());
        }
    }

    /** @return array<string, array{string, string, int, bool}> */
    public static function openAccessOutcomes(): array {
        return [
            'Unpaywall had no usable link' => ['nothing', '', 2, false],
            'Fatcat has no public copy' => ['nothing', '', 1, true],
            'Unpaywall link failed its probe' => ['url_unreachable', '', 2, false],
            'Unpaywall found a publisher copy' => ['publisher', '', 0, false],
            'Unpaywall added a copy' => ['got one', '', 0, true],
            'Unpaywall rate limited' => ['rate_limited', '', 0, true],
            'existing URL' => ['nothing', '|url=https://example.org/article', 0, true],
            'existing archive' => ['nothing', '|archive-url=https://web.archive.org/web/20200101000000/https://example.org/article', 0, true],
            'existing chapter URL' => ['nothing', '|chapter-url=https://example.org/chapter', 0, true],
            'existing title link' => ['nothing', '|title-link=Uranium radiation', 0, true],
            'chapter title' => ['nothing', '|chapter=Chapter title', 0, true],
            'URL format already specified' => ['nothing', '|format=HTML', 0, true],
            'publication year outside the collection' => ['nothing', '|date=1 January 1910', 0, true],
            'suffixed publication year outside the collection' => ['nothing', '|year=2020a', 0, true],
            'publication year range outside the collection' => ['nothing', '|year=2020–2025', 0, true],
            'free DOI' => ['nothing', '|doi-access=free', 0, false],
            'PMC full text' => ['nothing', '|pmc=123456', 0, false],
            'broken DOI' => ['nothing', '|doi-broken-date=2024-01-01', 0, false],
        ];
    }
}
