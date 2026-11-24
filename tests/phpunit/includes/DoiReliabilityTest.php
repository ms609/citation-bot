<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class DoiReliabilityTest extends testBaseClass {
    public function testLocallyRejectedDoiDoesNotAcquireBrokenDate(): void {
        $warnings = [];
        $this->assertTrue(uopz_set_hook('report_warning', static function (string $warning) use (&$warnings): void {
            $warnings[] = $warning;
        }));
        try {
            $this->with_function_stubs([
                'get_headers_array' => static function (string $url): false {
                    self::assertStringNotContainsString('https://doi.org/10.123/', $url);
                    return false;
                },
                'doi_active' => false,
                'bot_curl_exec' => '',
                'bot_curl_exec_withFalse' => false,
            ], function (): void {
                $template = $this->process_citation('{{cite journal|title=Study|journal=Example Journal|doi=10.123/abc}}');
                $this->assertSame('10.123/abc', $template->get2('doi'));
                $this->assertNull($template->get2('doi-broken-date'));
                $dated = $this->make_citation('{{cite journal|doi=10.123/abc|doi-broken-date=2001-01-01}}');
                $dated->mark_inactive_doi();
                $this->assertSame('2001-01-01', $dated->get2('doi-broken-date'));
            });
        } finally {
            $this->assertTrue(uopz_unset_hook('report_warning'));
        }
        $this->assertContains('Malformed DOI registrant needs review: 10.123/abc', $warnings);
    }

    public function testOnlyDefinitiveDoiFailureSetsBrokenDate(): void {
        foreach ([false, null, true] as $status) {
            HandleCache::free_memory();
            $headers = match ($status) {
                false => doi_headers_mark_transport_trust(['0' => 'HTTP/1.1 404 Not Found'], true),
                null => false,
                true => ['0' => 'HTTP/1.1 302 Found', 'Location' => 'https://example.test/study'],
            };
            $this->with_function_stubs([
                'get_headers_array' => static fn (): array|false => $headers,
            ], function () use ($status): void {
                $template = $this->make_citation('{{cite journal|doi=10.1000/status-fixture}}');
                $this->assertSame($status === true, $template->verify_doi());
                $this->assertSame($status === false, $template->has('doi-broken-date'));

                $template = $this->make_citation('{{cite journal|doi=10.1000/status-fixture|doi-broken-date=2001-01-01}}');
                if ($status !== false) {
                    $template->verify_doi();
                    $this->assertSame($status === null ? '2001-01-01' : null, $template->get2('doi-broken-date'));
                }
            });
        }
    }

    public function testThrottleDelayUsesSecondsToMicrosecondsConversion(): void {
        $this->assertSame(40000, dx_throttle_delay(10.0, 10.0));
        $half_delay = dx_throttle_delay(10.020, 10.0);
        $this->assertGreaterThanOrEqual(19999, $half_delay);
        $this->assertLessThanOrEqual(20001, $half_delay);
        $this->assertSame(0, dx_throttle_delay(10.041, 10.0));
        $this->assertSame(0, dx_throttle_delay(11.0, 10.0));
    }

    public function testFirstThrottleCallDoesNotSleep(): void {
        $this->assertSame(0, dx_throttle_delay(10.0, 0.0));
    }

    #[DataProvider('jstorDoiQueryProvider')]
    public function testJstorStableIdWithSearchParametersInDoi(string $jstor, ?bool $full_status, ?bool $bare_status, bool $remove, string $comment = ''): void {
        $bare = '10.7249/j.ctt4cgd90.12';
        // A bare query token exercises JSTOR removal independently of DOI query-parameter repair.
        $query = $bare_status === true ? '?x' : '?searchtext=internet+in+china&searchuri=/action/dobasicsearch?query=internet+in+china&seq=6';
        $doi = $bare . $query . $comment;
        $chapter_url = 'https://www.jstor.org/stable/' . $bare;
        $text = '{{Citation |last1=Tkacheva |first1=Olesya |chapter=The Internet in China: Threatened Tool of Expression and Mobilization' .
            ' |date=2013 |title=Internet Freedom and Political Space |pages=93–118 |chapter-url=' . $chapter_url .
            ' |publisher=RAND Corporation |isbn=978-0-8330-8063-9 |doi=' . $doi .
            ' |doi-broken-date=June 20, 2026 |doi-access=free |jstor=' . $jstor . '}}';
        $this->with_function_stubs([
            'doi_works' => static fn (string $identifier): ?bool => match ($identifier) {
                $doi => $full_status,
                $bare => $bare_status,
                default => false,
            },
            'doi_active' => false,
        ], function () use ($text, $doi, $chapter_url, $jstor, $remove): void {
            $template = $this->make_citation($text);
            $template->final_tidy();
            $this->assertSame($remove ? null : $doi, $template->get2('doi'));
            $this->assertSame($remove ? null : 'June 20, 2026', $template->get2('doi-broken-date'));
            $this->assertSame($remove ? null : 'free', $template->get2('doi-access'));
            $this->assertSame($jstor, $template->get2('jstor'));
            $this->assertSame($chapter_url, $template->get2('chapter-url'));
            $cleaned = $template->parsed_text();
            $template->final_tidy();
            $this->assertSame($cleaned, $template->parsed_text());
        });
    }

    /** @return array<string, array{string, ?bool, ?bool, bool, 4?: string}> */
    public static function jstorDoiQueryProvider(): array {
        $jstor = '10.7249/j.ctt4cgd90.12';
        return [
            'confirmed JSTOR-only identifier' => [$jstor, false, false, true],
            'different JSTOR identifier' => ['10.7249/j.ctt4cgd90.13', false, false, false],
            'full DOI resolves' => [$jstor, true, false, false],
            'short DOI resolves' => [$jstor, false, true, false],
            'full DOI status unknown' => [$jstor, null, false, false],
            'short DOI status unknown' => [$jstor, false, null, false],
            'DOI has an inline comment' => [$jstor, false, false, false, ' # # # CITATION_BOT_PLACEHOLDER_COMMENT 0 # # #'],
        ];
    }

    public function testReportedJstorChapterRemainsLinkedAfterPageExpansion(): void {
        $jstor = '10.7249/j.ctt4cgd90.12';
        $chapter_url = 'https://www.jstor.org/stable/' . $jstor;
        $text = '{{Citation |last1=Tkacheva |first1=Olesya |chapter=The Internet in China: Threatened Tool of Expression and Mobilization' .
            ' |date=2013 |title=Internet Freedom and Political Space |pages=93–118 |chapter-url=' . $chapter_url .
            ' |publisher=RAND Corporation |isbn=978-0-8330-8063-9 |doi=' . $jstor . '?searchtext=internet+in+china&seq=6' .
            ' |jstor=' . $jstor . '}}';
        $this->with_function_stubs([
            'doi_works' => false,
            'doi_active' => false,
            'bot_curl_exec' => '',
            'bot_curl_exec_withFalse' => false,
        ], function () use ($text, $jstor, $chapter_url): void {
            $template = $this->process_citation($text);
            $this->assertNull($template->get2('doi'));
            $this->assertNull($template->get2('doi-broken-date'));
            $this->assertSame($jstor, $template->get2('jstor'));
            $this->assertSame($chapter_url, $template->get2('chapter-url'));
        });
    }
}
