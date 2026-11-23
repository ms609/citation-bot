<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\Group;

final class PiiApiTest extends testBaseClass {

    /**
     * Stub only the cURL transport: the production PII lookup and XML
     * parsing must still run. This test must not depend on Elsevier uptime.
     */
    public function testPII(): void {
        new TestPage(); // Fill page name with test name for debugging

        $pii = 'S0960076019302699';
        $doi_expect = '10.1016/j.jsbmb.2019.105494';
        $response = '<coredata><prism:doi>' . $doi_expect . '</prism:doi></coredata>';
        $requests = 0;

        $stubs = [
            'bot_curl_exec' => static function (CurlHandle $_ch) use ($response, &$requests): string {
                $requests++;
                return $response;
            },
        ];
        $this->with_function_stubs($stubs, function () use ($doi_expect, $pii, &$requests): void {
            $this->assertSame($doi_expect, get_doi_from_pii($pii));
            $this->assertSame(1, $requests, 'The PII lookup must call the transport exactly once');
        });
    }

    public function testPIIResponseParserAcceptsDoiWithWhitespace(): void {
        $xml = "<coredata><prism:doi>\n 10.1016/j.jsbmb.2019.105494 \n</prism:doi></coredata>";
        $this->assertSame('10.1016/j.jsbmb.2019.105494', parse_pii_doi_response($xml));
        $this->assertSame('10.1000/example', parse_pii_doi_response('<PRISM:DOI>10.1000/example</PRISM:DOI>'));
    }

    public function testPIIResponseParserRejectsMissingOrMalformedDoi(): void {
        foreach ([
            '',
            '<error>rate limit exceeded</error>',
            '<coredata/>',
            '<prism:doi></prism:doi>',
            '<prism:doi>not-a-doi</prism:doi>',
            '<prism:doi>10.1</prism:doi>',
            '<prism:doi>10.1000/invalid</prism:other>',
        ] as $response) {
            $this->assertSame('', parse_pii_doi_response($response));
        }
    }

    /** The live API check is explicit, never part of required PR CI. */
    #[Group('external-api')]
    public function testPIILive(): void {
        if (getenv('CITATION_BOT_LIVE_PII') !== '1') {
            $this->markTestSkipped('Set CITATION_BOT_LIVE_PII=1 to test the live Elsevier API');
        }
        $this->assertSame('10.1016/j.jsbmb.2019.105494', get_doi_from_pii('S0960076019302699'));
    }
}
