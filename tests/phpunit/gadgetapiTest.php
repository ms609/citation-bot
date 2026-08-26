<?php
declare(strict_types=1);

/*
 * Tests for gadgetapi.php
 */

require_once __DIR__ . '/../testBaseClass.php';
require_once __DIR__ . '/../../src/includes/GadgetApi.php';

final class gadgetapiTest extends testBaseClass {

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
        ob_start();
        $_POST['text'] = '{{cite journal|doi=10.1021/acs.jpca.4c00688 |pmid=<!-- --> |arxiv=<!-- --> |pmc=<!-- --> |url=<!-- --> }}';
        $_POST['summary'] = 'Something Nice';
        $_SERVER['HTTP_ORIGIN'] = 'https://en.wikipedia.org';
        // Note: gadgetapi.php runs in fast mode by default to prevent timeouts
        require(__DIR__ . '/../../src/gadgetapi.php');
        unset($_SERVER['HTTP_ORIGIN']);
        $json_text = ob_get_contents();
        ob_end_clean();
        while (ob_get_level()) {
            ob_end_flush();
        }
        ob_start(); // PHPUnit turns on a level of buffering itself -- Give it back to avoid "Risky Test"
        unset($_POST);
        unset($_REQUEST);
        // Output checking time
        $json = json_decode($json_text);
        $this->assertSame('{{cite journal|last1=Leyser Da Costa Gouveia |first1=Tiago |last2=Maganas |first2=Dimitrios |last3=Neese |first3=Frank |title=Restricted Open-Shell Hartree–Fock Method for a General Configuration State Function Featuring Arbitrarily Complex Spin-Couplings |journal=The Journal of Physical Chemistry A |date=2024 |volume=128 |issue=25 |pages=5041–5053 |doi=10.1021/acs.jpca.4c00688 |pmid=<!-- --> |arxiv=<!-- --> |pmc=<!-- --> |url=<!-- --> }}', $json->expandedtext);
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

    public function testGadgetAcceptsMinimumPageSize(): void {
        [$text, $summary] = gadget_api_validate_request([
            'text' => '123456',
            'summary' => '',
        ]);

        $this->assertSame('123456', $text);
        $this->assertSame('', $summary);
    }

    public function testGadgetAcceptsMaximumSummarySize(): void {
        [$text, $summary] = gadget_api_validate_request([
            'text' => 'normal text',
            'summary' => str_repeat('x', 1000),
        ]);

        $this->assertSame('normal text', $text);
        $this->assertSame(1000, mb_strlen($summary));
    }
}
