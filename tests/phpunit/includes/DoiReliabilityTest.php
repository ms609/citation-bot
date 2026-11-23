<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class DoiReliabilityTest extends testBaseClass {
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
