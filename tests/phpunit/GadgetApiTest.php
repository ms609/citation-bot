<?php
declare(strict_types=1);

/*
 * Tests for gadgetapi.php
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__) . '/testBaseClass.php';
require_once dirname(__DIR__, 2) . '/src/includes/GadgetApi.php';

final class GadgetApiTest extends testBaseClass {

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMalformedPercentEscapeDoesNotFailWholeGadgetRequest(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required for offline gadget expansion');
        }
        $this->assertTrue(uopz_set_return('request_rate_limit_consume_layered', null));
        $this->assertTrue(uopz_set_return('bot_curl_exec', ''));
        $this->assertTrue(uopz_set_return('bot_curl_exec_withFalse', false));
        $this->assertTrue(uopz_set_return('doi_works', false));
        $this->assertTrue(uopz_set_return('hdl_works', false));
        $this->assertTrue(uopz_set_return('sleep', 0));
        try {
            foreach ([
                '{{cite web|title=Example article|url=https://academic.oup.com/crawlprevention/governor?content=%2Fcaf%E9}}',
                '{{cite journal|title=Example article|hdl=1234/caf%E9}}',
                '{{cite journal|title=Example article|doi=10.1234/caf%E9}}',
            ] as $citation) {
                $response = $this->runGadgetEndpoint(
                    ['text' => $citation, 'summary' => 'Encoding regression'],
                    'https://en.wikipedia.org'
                );
                $this->assertSame(200, $response['status'], $citation);
                $body = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
                $this->assertStringContainsString('caf%E9', $body['expandedtext']);
            }
        } finally {
            $this->assertTrue(uopz_unset_return('sleep'));
            $this->assertTrue(uopz_unset_return('hdl_works'));
            $this->assertTrue(uopz_unset_return('doi_works'));
            $this->assertTrue(uopz_unset_return('bot_curl_exec_withFalse'));
            $this->assertTrue(uopz_unset_return('bot_curl_exec'));
            $this->assertTrue(uopz_unset_return('request_rate_limit_consume_layered'));
        }
    }

    /**
     * Execute the real gadgetapi.php endpoint and return its status and body.
     *
     * @param array<array-key, mixed> $post
     * @return array{status: int, body: string}
     */
    private function runGadgetEndpoint(array $post, ?string $origin): array {
        $_POST = $post;
        $_REQUEST = $post;

        if ($origin === null) {
            unset($_SERVER['HTTP_ORIGIN']);
        } else {
            $_SERVER['HTTP_ORIGIN'] = $origin;
        }

        http_response_code(200);
        $original_buffer_level = ob_get_level();

        ob_start();
        $capture_buffer_level = ob_get_level();

        require dirname(__DIR__, 2) . '/src/gadgetapi.php';

        // The endpoint must only unwind the buffers it opened itself.
        $this->assertSame(
            $capture_buffer_level,
            ob_get_level(),
            'gadgetapi.php must leave the caller output buffer level unchanged'
        );

        $body = ob_get_contents();
        $this->assertIsString($body);

        while (ob_get_level() > $original_buffer_level) {
            ob_end_clean();
        }

        $status = http_response_code();
        $this->assertIsInt($status);

        unset($_SERVER['HTTP_ORIGIN']);
        unset($_POST, $_REQUEST);

        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    private function assertGadgetRequestFailure(
        array $post,
        string $expected_error,
        int $expected_status
    ): void {
        try {
            gadget_api_validate_request($post);
            $this->fail('Expected GadgetApiRequestException');
        } catch (GadgetApiRequestException $exception) {
            $this->assertSame($expected_error, $exception->error_name);
            $this->assertSame($expected_status, $exception->http_status);
        }
    }

    public function testGadget(): void {
        new TestPage(); // Fill page name with test name for debugging
        $base_buffer_level = ob_get_level();
        ob_start();
        $_POST['text'] = '{{cite journal|doi=10.1021/acs.jpca.4c00688 |pmid=<!-- --> |arxiv=<!-- --> |pmc=<!-- --> |url=<!-- --> }}';
        $_POST['summary'] = 'Something Nice';
        $_SERVER['HTTP_ORIGIN'] = 'https://en.wikipedia.org';
        // Note: gadgetapi.php runs in fast mode by default to prevent timeouts
        require(dirname(__DIR__, 2) . '/src/gadgetapi.php');
        unset($_SERVER['HTTP_ORIGIN']);
        $json_text = ob_get_contents();
        while (ob_get_level() > $base_buffer_level) {
            ob_end_clean();
        }
        unset($_POST);
        unset($_REQUEST);
        // Output checking time
        $json = json_decode($json_text);
        $this->assertSame('{{cite journal|last1=Leyser da Costa Gouveia |first1=Tiago |last2=Maganas |first2=Dimitrios |last3=Neese |first3=Frank |title=Restricted Open-Shell Hartree–Fock Method for a General Configuration State Function Featuring Arbitrarily Complex Spin-Couplings |journal=The Journal of Physical Chemistry A |date=2024 |volume=128 |issue=25 |pages=5041–5053 |doi=10.1021/acs.jpca.4c00688 |pmid=<!-- --> |arxiv=<!-- --> |pmc=<!-- --> |url=<!-- --> }}', $json->expandedtext);
        $this->assertSame('Something Nice | Add: pages, issue, volume, date, journal, title, authors 1-3. | [[:en:WP:UCB|Use this tool]]. [[:en:WP:DBUG|Report bugs]]. | #UCB_Gadget ', $json->editsummary);
    }

    public function testGadgetApiErrorProducesJson(): void {
        ob_start();
        gadget_api_error('invalid_parameters', 400);
        $json_text = ob_get_clean();

        $this->assertIsString($json_text);

        $this->assertSame(
            ['error' => 'invalid_parameters'],
            json_decode($json_text, true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testGadgetRejectsMissingText(): void {
        $this->assertGadgetRequestFailure(
            ['summary' => 'test'],
            'invalid_parameters',
            400
        );
    }

    public function testGadgetRejectsArrayText(): void {
        $this->assertGadgetRequestFailure(
            [
                'text' => ['not', 'a', 'string'],
                'summary' => 'test',
            ],
            'invalid_parameters',
            400
        );
    }

    public function testGadgetRejectsArraySummary(): void {
        $this->assertGadgetRequestFailure(
            [
                'text' => 'normal text',
                'summary' => ['bad'],
            ],
            'invalid_parameters',
            400
        );
    }

    public function testGadgetRejectsTinyPage(): void {
        $this->assertGadgetRequestFailure(
            [
                'text' => '12345',
                'summary' => '',
            ],
            'page_too_small',
            400
        );
    }

    public function testGadgetRejectsHugePage(): void {
        $this->assertGadgetRequestFailure(
            [
                'text' => str_repeat('x', 150001),
                'summary' => '',
            ],
            'page_too_large',
            400
        );
    }

    public function testGadgetRejectsHugeSummary(): void {
        $this->assertGadgetRequestFailure(
            [
                'text' => 'normal text',
                'summary' => str_repeat('x', 1001),
            ],
            'summary_too_large',
            400
        );
    }

    public function testGadgetRejectsInvalidUtf8(): void {
        $this->assertGadgetRequestFailure(
            [
                'text' => "normal\xFFtext",
                'summary' => '',
            ],
            'invalid_utf8',
            400
        );
    }

    public function testWikitextPreflightAllowsProtectedSyntaxAndMultilingualText(): void {
        $text = str_repeat('引用の出典。', 9000) . "\n"
            . '<nowiki>' . str_repeat('{{', 100) . '</nowiki>'
            . '{{cite web|title={{lang|ja|引用}}}}';
        $page = new Page(new WikitextWorkBudget());
        $page->parse_text($text);

        $this->assertSame($text, $page->parsed_text());
    }

    public function testWikitextBudgetCountsWikilinkFramesDuringExpansion(): void {
        $page = new Page(new WikitextWorkBudget());
        $page->parse_text(str_repeat('[[', 49) . 'Page' . str_repeat(']]', 49));
        $this->expectException(WikitextTooComplexException::class);
        $page->expand_text();
    }

    public function testWikitextPreflightRejectsExcessiveSiblingTemplates(): void {
        $this->expectException(WikitextTooComplexException::class);
        (new WikitextWorkBudget())->preflight(str_repeat('{{example}}', 1201));
    }

    public function testWikitextBudgetRejectsExcessiveFieldsBeforeTemplateParsing(): void {
        $page = new Page(new WikitextWorkBudget());
        $page->parse_text('{{example' . str_repeat('|field=value', 257) . '}}');

        $this->expectException(WikitextTooComplexException::class);
        $page->extract_object(Template::class);
    }

    public function testWikitextTemplateByteBudgetIncludesMultibyteContent(): void {
        $content = str_repeat('界', 21844);
        $budget = new WikitextWorkBudget();
        $budget->recordTemplate('{{' . $content . '}}');

        $this->expectException(WikitextTooComplexException::class);
        $budget->recordTemplate('{{' . $content . 'x}}');
    }

    public function testBareReferenceConversionChecksCumulativeOutputBytes(): void {
        $convert = (new WikitextWorkBudget())->boundConversions(
            str_repeat('a', 1024 * 1024 - 2),
            static fn (array $matches): string => str_replace('a', '文', $matches[0])
        );
        $this->assertSame('文', $convert(['a']));
        $this->expectException(WikitextTooComplexException::class);
        $convert(['a']);
    }

    public function testWikitextBudgetChecksRestoredSizeBeforeReplacement(): void {
        $budget = new WikitextWorkBudget();
        $this->expectException(WikitextTooComplexException::class);
        $budget->checkReplacement(
            str_repeat('Placeholder ', 1000),
            'placeholder',
            str_repeat('x', 1100),
            case_insensitive: true
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetReturnsPageTooComplexBeforeExpansion(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required for offline gadget validation');
        }
        $this->assertTrue(uopz_set_return('request_rate_limit_consume_layered', null));
        $this->assertTrue(uopz_set_return(Page::class, 'expand_text', static function (): never {
            throw new LogicException('Preflight should stop before expansion');
        }, true));
        try {
            $text = str_repeat('{{example|', 49) . 'citation' . str_repeat('}}', 49);
            $response = $this->runGadgetEndpoint(['text' => $text, 'summary' => ''], 'https://en.wikipedia.org');

            $this->assertSame(422, $response['status']);
            $this->assertSame(
                ['error' => 'page_too_complex'],
                json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
            );
        } finally {
            $this->assertTrue(uopz_unset_return(Page::class, 'expand_text'));
            $this->assertTrue(uopz_unset_return('request_rate_limit_consume_layered'));
        }
    }

    public function testGadgetAcceptsMinimumPageSize(): void {
        [$text, $summary] = gadget_api_validate_request([
            'text' => '123456',
            'summary' => '',
        ]);

        $this->assertSame('123456', $text);
        $this->assertSame('', $summary);
    }

    public function testGadgetCountsUtf8CharactersWithADifferentInternalEncoding(): void {
        $saved_encoding = mb_internal_encoding();
        mb_internal_encoding('ISO-8859-1');
        try {
            $request = ['text' => str_repeat('界', 6), 'summary' => str_repeat('界', 1000)];
            $this->assertSame(array_values($request), gadget_api_validate_request($request));
            $this->assertGadgetRequestFailure(
                ['text' => str_repeat('界', 5), 'summary' => ''],
                'page_too_small',
                400
            );
        } finally {
            mb_internal_encoding($saved_encoding);
        }
    }

    public function testGadgetAcceptsMaximumSummarySize(): void {
        [$text, $summary] = gadget_api_validate_request([
            'text' => 'normal text',
            'summary' => str_repeat('x', 1000),
        ]);

        $this->assertSame('normal text', $text);
        $this->assertSame(1000, mb_strlen($summary));
    }

    /**
     * @param string $expanded
     * @param string $incoming_summary
     * @param int $expected_status
     * @param array<string, string> $expected_body
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('gadgetExpansionResults')]
    public function testGadgetEndpointReturnsExpectedExpansionResponse(
        string $expanded,
        string $incoming_summary,
        int $expected_status,
        array $expected_body
    ): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub citation expansion');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('request_rate_limit_consume_layered', null);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'expand_text', true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'parsed_text', $expanded);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'edit_summary', 'Use this bot');
        try {
            $response = $this->runGadgetEndpoint(
                ['text' => 'Original citation', 'summary' => $incoming_summary],
                'https://en.wikipedia.org'
            );
            $this->assertSame($expected_status, $response['status']);
            $this->assertSame($expected_body, json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR));
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'edit_summary');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'parsed_text');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'expand_text');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('request_rate_limit_consume_layered');
        }
    }

    /** @return array<string, array{string, string, int, array<string, string>}> */
    public static function gadgetExpansionResults(): array {
        return [
            'unchanged citation' => [
                'Original citation', 'Existing summary', 200,
                ['expandedtext' => 'Original citation', 'editsummary' => 'Existing summary'],
            ],
            'expanded citation with summary' => [
                'Expanded citation', 'Existing summary', 200,
                ['expandedtext' => 'Expanded citation', 'editsummary' => 'Existing summary | Use this tool| #UCB_Gadget '],
            ],
            'expanded citation without summary' => [
                'Expanded citation', '', 200,
                ['expandedtext' => 'Expanded citation', 'editsummary' => 'Use this tool| #UCB_Gadget '],
            ],
            'expanded citation with zero summary' => [
                'Expanded citation', '0', 200,
                ['expandedtext' => 'Expanded citation', 'editsummary' => '0 | Use this tool| #UCB_Gadget '],
            ],
            'zero expansion with summary' => [
                '0', 'Existing summary', 200,
                ['expandedtext' => '0', 'editsummary' => 'Existing summary | Use this tool| #UCB_Gadget '],
            ],
            'multilingual citation' => [
                'Café 東京 😀', 'Résumé', 200,
                ['expandedtext' => 'Café 東京 😀', 'editsummary' => 'Résumé | Use this tool| #UCB_Gadget '],
            ],
            'empty expansion' => ['', 'Existing summary', 500, ['error' => 'internal_error']],
        ];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetEndpointRejectsMissingText(): void {
        $response = $this->runGadgetEndpoint(
            ['summary' => 'test'],
            'https://en.wikipedia.org'
        );

        $this->assertSame(400, $response['status']);
        $this->assertSame(
            ['error' => 'invalid_parameters'],
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetEndpointRejectsArrayText(): void {
        $response = $this->runGadgetEndpoint(
            [
                'text' => ['bad'],
                'summary' => 'test',
            ],
            'https://en.wikipedia.org'
        );

        $this->assertSame(400, $response['status']);
        $this->assertSame(
            ['error' => 'invalid_parameters'],
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetEndpointRejectsTinyPage(): void {
        $response = $this->runGadgetEndpoint(
            [
                'text' => '12345',
                'summary' => '',
            ],
            'https://en.wikipedia.org'
        );

        $this->assertSame(400, $response['status']);
        $this->assertSame(
            ['error' => 'page_too_small'],
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetEndpointRejectsInvalidUtf8(): void {
        $response = $this->runGadgetEndpoint(
            [
                'text' => "normal\xFFtext",
                'summary' => '',
            ],
            'https://en.wikipedia.org'
        );

        $this->assertSame(400, $response['status']);
        $this->assertSame(
            ['error' => 'invalid_utf8'],
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetEndpointRejectsInvalidOrigin(): void {
        $response = $this->runGadgetEndpoint(
            [
                'text' => 'normal text',
                'summary' => '',
            ],
            'https://attacker.example'
        );

        $this->assertSame(403, $response['status']);
        $this->assertSame(
            ['error' => 'invalid_origin'],
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetEndpointRejectsMissingOrigin(): void {
        $response = $this->runGadgetEndpoint(
            [
                'text' => 'normal text',
                'summary' => '',
            ],
            null
        );

        $this->assertSame(403, $response['status']);
        $this->assertSame(
            ['error' => 'invalid_origin'],
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetEndpointDiscardsExpansionOutputOnFailure(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to force an expansion failure');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('request_rate_limit_consume_layered', null);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'expand_text', static function (): never {
            echo 'partial output that must not leak';
            throw new RuntimeException('expansion failed');
        }, true);
        try {
            $response = $this->runGadgetEndpoint(
                [
                    'text' => 'Original citation',
                    'summary' => '',
                ],
                'https://en.wikipedia.org'
            );
            $this->assertSame(500, $response['status']);
            $this->assertSame(
                ['error' => 'internal_error'],
                json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
            );
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'expand_text');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('request_rate_limit_consume_layered');
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGadgetEndpointDiscardsConstructionOutputOnSuccess(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_redefine')) {
            $this->markTestSkipped('uopz is required to simulate production constructor output');
        }
        // Production web runs have SLOW_MODE false, so the Page constructor
        // emits its fast-mode notice via report_info(). Under PHPUnit CI that
        // output is suppressed, which hid the corrupted-JSON regression where
        // the notice was echoed before output buffering started. Simulate the
        // production notice and require the success body to stay pure JSON.
        /** @psalm-suppress UnusedFunctionCall */
        uopz_redefine('SLOW_MODE', false);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('request_rate_limit_consume_layered', null);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'expand_text', true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'parsed_text', 'Original citation');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'edit_summary', 'Use this bot');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_info', static function (): void {
            echo 'constructor output that must not leak';
        }, true);
        try {
            $response = $this->runGadgetEndpoint(
                [
                    'text' => 'Original citation',
                    'summary' => 'Existing summary',
                ],
                'https://en.wikipedia.org'
            );
            $this->assertSame(200, $response['status']);
            $this->assertSame(
                ['expandedtext' => 'Original citation', 'editsummary' => 'Existing summary'],
                json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
            );
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_info');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'edit_summary');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'parsed_text');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(Page::class, 'expand_text');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('request_rate_limit_consume_layered');
        }
    }
}
