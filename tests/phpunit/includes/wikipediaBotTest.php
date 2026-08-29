<?php
declare(strict_types=1);

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../../testBaseClass.php';
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

final class wikipediaBotTest extends testBaseClass {

    #[DoesNotPerformAssertions]
    public function testCoverageFixer(): void {
        WikipediaBot::make_ch();
    }

    public function testApiBoundariesCatchThrowable(): void {
        $source = file_get_contents(__DIR__ . '/../../../src/includes/WikipediaBot.php');
        $this->assertIsString($source);
        $this->assertStringNotContainsString('catch (Exception $E)', $source);
        $this->assertGreaterThanOrEqual(2, mb_substr_count($source, 'catch (Throwable $E)'));
    }

    public function testWikipediaCurlHandlesHaveExplicitResponseLimits(): void {
        $source = file_get_contents(__DIR__ . '/../../../src/includes/WikipediaBot.php');
        $this->assertIsString($source);
        $this->assertStringContainsString(
            'bot_curl_set_max_response_bytes(self::$ch_write, 16 * 1024 * 1024);',
            $source
        );
        $this->assertStringContainsString(
            'bot_curl_set_max_response_bytes(self::$ch_logout, 16 * 1024 * 1024);',
            $source
        );
    }

    public function testOAuthRequestConstructionIsInsideFetchBoundary(): void {
        $source = file_get_contents(__DIR__ . '/../../../src/includes/WikipediaBot.php');
        $this->assertIsString($source);

        $fetch_start = mb_strpos($source, 'private function fetch(');
        if ($fetch_start === false) {
            $this->fail('Could not locate WikipediaBot::fetch()');
        }
        $fetch_end = mb_strpos($source, 'public function write_page(', $fetch_start);
        if ($fetch_end === false) {
            $this->fail('Could not locate WikipediaBot::write_page()');
        }

        $fetch_source = mb_substr($source, $fetch_start, $fetch_end - $fetch_start);
        $try_position = mb_strpos($fetch_source, 'try {');
        $request_position = mb_strpos($fetch_source, 'Request::fromConsumerAndToken');
        $sign_position = mb_strpos($fetch_source, 'signRequest(');

        $this->assertNotFalse($try_position);
        $this->assertNotFalse($request_position);
        $this->assertNotFalse($sign_position);
        $this->assertTrue($try_position < $request_position);
        $this->assertTrue($try_position < $sign_position);
    }

    private function category_members_with_retry(string $category): array {
        $backoff_delays = [2, 5];
        $members = WikipediaBot::category_members($category);
        foreach ($backoff_delays as $delay) {
            if ($members !== []) {
                return $members;
            }
            sleep($delay);
            $members = WikipediaBot::category_members($category);
        }
        if ($members === []) {
            $this->markTestSkipped('Wikipedia API unavailable after retries (rate limit or outage)');
        }
        return $members;
    }

    public function testCategoryMembers1(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertTrue(count($this->category_members_with_retry('Indian drama films')) > 10);
    }

    public function testCategoryMembers2(): void {
        $this->assertSame(0, count(WikipediaBot::category_members('A category we expect to be empty')));
    }

    public function testRedirect1(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertSame(-1, WikipediaBot::is_redirect('NoSuchPage:ThereCan-tBe'));
    }

    public function testRedirect2(): void {
        $this->assertSame( 0, WikipediaBot::is_redirect('User:Citation_bot'));
    }

    public function testRedirect3(): void {
        $this->assertSame( 1, WikipediaBot::is_redirect('WP:UCB'));
    }

    public function testRedirect4(): void {
        $this->assertSame('User:Citation bot/use', WikipediaBot::redirect_target('WP:UCB'));
    }

    public function testGetLastRevision(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertSame('805321380', WikipediaBot::get_last_revision('User:Blocked testing account/readtest'));
    }

    public function testGetUserName(): void {
        new TestPage(); // Fill page name with test name for debugging
        $api = new WikipediaBot();
        $this->assertSame('Citation_bot', $api->get_the_user());
    }

    public function testNonStandardMode(): void {
        $this->assertFalse(WikipediaBot::non_standard_mode());
    }

    public function testAutomatedToolsRequestDetection(): void {
        $request_uri = '/process_page.php?page=Example&edit=automated_tools';
        $this->assertTrue(WikipediaBot::is_automated_tools_request($request_uri));
        $this->assertFalse(WikipediaBot::is_automated_tools_request('/process_page.php?page=Example&edit=toolbar'));
        $this->assertFalse(WikipediaBot::is_automated_tools_request(null));
    }

    public function testIsValidUser1(): void {
        new TestPage(); // Fill page name with test name for debugging
        $result = WikipediaBot::is_valid_user('Smith609');
        $this->assertTrue($result);
    }

    public function testIsValidUser2(): void {
        new TestPage(); // Fill page name with test name for debugging
        $result = WikipediaBot::is_valid_user('Stanlha'); // Random user who exists but does not have page as of Nov 2017
        $this->assertTrue($result);
    }

    public function testIsValidUser3(): void {
        $result = WikipediaBot::is_valid_user("David(Owner, Founder, Creator and Lead Developer)"); // Random user who has a name with funky characters
        $this->assertTrue($result);
    }

    public function testIsValidUserEmpty(): void {
        $result = WikipediaBot::is_valid_user("");
        $this->assertFalse($result);
    }

    public function testValidUserResponseParser(): void {
        $this->assertTrue(WikipediaBot::valid_user_from_response(
            '{"query":{"users":[{"userid":123,"name":"Example"}]}}'
        ));
        $this->assertFalse(WikipediaBot::valid_user_from_response(
            '{"query":{"users":[{"name":"Missing","missing":""}]}}'
        ));
        $this->assertFalse(WikipediaBot::valid_user_from_response(
            '{"query":{"users":[{"name":"127.0.0.1","invalid":""}]}}'
        ));
        $this->assertFalse(WikipediaBot::valid_user_from_response(
            '{"query":{"users":[{"userid":123,"name":"Blocked","blockid":7}]}}'
        ));
        $this->assertTrue(WikipediaBot::valid_user_from_response(
            '{"query":{"users":[{"userid":123,"name":"Partial","blockid":7,"blockpartial":true}]}}'
        ));
    }

    public function testValidUserResponseParserRejectsMalformedShapes(): void {
        foreach ([
            '',
            'not json',
            '{}',
            '{"query":{"users":[]}}',
            '{"query":{"users":[{"userid":"123"}]}}',
            '{"query":{"users":[{"userid":123},{"userid":456}]}}',
            '{"unrelated":"userid"}',
        ] as $response) {
            $this->assertNull(WikipediaBot::valid_user_from_response($response));
        }
    }

    public function testIsINValidUser(): void {
        $result = WikipediaBot::is_valid_user('Not_a_valid_user_at_Dec_2017');
        $this->assertFalse($result);
    }

    public function testIsIPUser(): void {
        $result = WikipediaBot::is_valid_user('178.16.5.186'); // IP address with talk page
        $this->assertFalse($result);
    }

    public function testIsIP6User(): void {
        $result = WikipediaBot::is_valid_user('2602:306:bc8a:21e0:f0d4:b9dc:c050:2b2c'); // IP6 address with talk page
        $this->assertFalse($result);
    }

    public function testIsBlockedUser(): void {
        new TestPage(); // Fill page name with test name for debugging
        $result = WikipediaBot::is_valid_user('RickK'); // BLOCKED
        $this->assertFalse($result);
    }

    public function testGetLinks(): void {
        new TestPage(); // Fill page name with test name for debugging
        $json = WikipediaBot::get_links('Covid Watch');
        $this->assertTrue(mb_substr_count($json, 'exists') > 15);
    }

    public function test_ret_okay1(): void {
        $this->assertFalse(WikipediaBot::ret_okay(null));
    }

    public function test_ret_okay2(): void {
        $response = (object) ['error' => (object) ['info' => 'Hello, The database has been automatically locked so give up']];
        $this->assertFalse(WikipediaBot::ret_okay($response));
    }

    public function test_ret_okay3(): void {
        $response = (object) ['error' => (object) ['info' => 'Greetings, abusefilter-warning-predatory so give up']];
        $this->assertTrue(WikipediaBot::ret_okay($response));
    }

    public function test_ret_okay4(): void {
        $response = (object) ['error' => (object) ['info' => 'Greetings, this page is protected so give up']];
        $this->assertTrue(WikipediaBot::ret_okay($response));
    }

    public function test_ret_okay5(): void {
        new TestPage(); // Fill page name with test name for debugging
        $response = (object) ['error' => (object) ['info' => 'doggiesandcats']];
        $this->assertFalse(WikipediaBot::ret_okay($response));
    }

    public function test_response2page1(): void {
        $this->assertNull(WikipediaBot::response2page(null));
    }

    public function test_response2page2(): void {
        $response = (object) ['warnings' => (object)['prop' => (object) ['*' => 'this is a prop']]];
        $this->assertNull(WikipediaBot::response2page($response));
    }

    public function test_response2page3(): void {
        $response = (object) ['warnings' => (object) ['info' => (object) ['*' => 'this is an info']]];
        $this->assertNull(WikipediaBot::response2page($response));
    }

    public function test_response2page4(): void {
        $response = (object) ['dogs' => (object) ['cats' => 'this has no batchcomplete']];
        $this->assertNull(WikipediaBot::response2page($response));
    }

    public function test_response2page5(): void {
        $response = (object) ['batchcomplete' => 'we did it but have not query'];
        $this->assertNull(WikipediaBot::response2page($response));
    }

    public function test_response2page6(): void {
        new TestPage(); // Fill page name with test name for debugging
        $response = (object) ['batchcomplete' => 'we did it', 'query' => (object) ['pages' => (object) ['0' => (object) ['x' => 'y']]]];
        $this->assertNull(WikipediaBot::response2page($response));
    }

    public function test_response2page7(): void {
        $response = (object) ['batchcomplete' => 'we did it', 'query' => (object) ['pages']];
        $pages = [(object) ['lastrevid' => 1, 'revisions' => 1, 'title' => 'x']];
        $pages[0]->revisions = ['0' => (object) ['timestamp' => 1]];
        $response->query->pages = (object) $pages;
        $this->assertNull(WikipediaBot::response2page($response));
    }

    public function test_response2page8(): void {
        $response = (object) ['batchcomplete' => 'we did it', 'query' => (object) ['pages']];
        $pages = [(object) ['lastrevid' => 1, 'revisions' => 1, 'title' => 'x']];
        $pages[0]->revisions = ['0' => (object) ['timestamp' => 1]];
        $response->query->pages = (object) $pages;
        $response->query->tokens = (object) ['csrftoken' => 1];
        $this->assertNotNull(WikipediaBot::response2page($response));
    }

    public function testResponse2PageRejectsMalformedNestedShapes(): void {
        $cases = [
            (object) ['batchcomplete' => true, 'query' => 'not an object'],
            (object) ['batchcomplete' => true, 'query' => (object) ['pages' => []]],
            (object) ['batchcomplete' => true, 'query' => (object) [
                'pages' => (object) ['0' => (object) [
                    'lastrevid' => 1,
                    'title' => 'x',
                    'revisions' => 'not an array',
                ]],
                'tokens' => (object) ['csrftoken' => 'token'],
            ]],
        ];

        foreach ($cases as $response) {
            $this->assertNull(WikipediaBot::response2page($response));
        }
    }

    public function testResultsGoodRejectsMalformedExternalShapes(): void {
        $this->assertFalse(WikipediaBot::resultsGood((object) ['error' => []]));
        $this->assertFalse(WikipediaBot::resultsGood(
            (object) ['error' => (object) ['code' => [], 'info' => 'bad']]
        ));
        $this->assertFalse(WikipediaBot::resultsGood((object) ['edit' => []]));
        $this->assertFalse(WikipediaBot::resultsGood(
            (object) ['edit' => (object) ['result' => []]]
        ));
    }

    public function test_resultsGood1(): void {
        new TestPage(); // Fill page name with test name for debugging
        $result = null;
        $this->assertFalse(WikipediaBot::resultsGood($result));
    }

    public function test_resultsGood2(): void {
        $result = (object) ['edit' => 'nonresult'];
        $this->assertFalse(WikipediaBot::resultsGood($result));
    }

    public function test_resultsGood3(): void {
        $result = (object) ['error' => (object) ['code' => '3', 'info' => 'y']];
        $this->assertFalse(WikipediaBot::resultsGood($result));
    }

    public function test_resultsGood4(): void {
        $result = (object) ['edit' => (object) ['result' => 'failed']];
        $this->assertFalse(WikipediaBot::resultsGood($result));
    }

    public function test_resultsGood5(): void {
        $result = (object) ['edit' => (object) ['result' => 'Success']];
        $this->assertTrue(WikipediaBot::resultsGood($result));
    }

    public function testCategoryMemberParserSkipsMalformedExternalRecords(): void {
        $response = (object) [
            'query' => (object) [
                'categorymembers' => [
                    (object) ['title' => 'Article One'],
                    (object) ['title' => ['unexpected']],
                    'not-an-object',
                    (object) ['title' => 'Talk:Excluded'],
                    (object) ['title' => 'Article Two'],
                ],
            ],
        ];

        $this->assertSame(
            ['Article One', 'Article Two'],
            WikipediaBot::category_member_titles_from_response($response)
        );
        $this->assertNull(WikipediaBot::category_member_titles_from_response((object) ['query' => []]));
    }

    public function testLinkParserSkipsMalformedExternalRecords(): void {
        $json = json_encode([
            'parse' => [
                'links' => [
                    ['ns' => 0, 'exists' => '', '*' => 'Article One'],
                    ['ns' => 118, 'exists' => '', '*' => 'Draft:Article Two'],
                    ['ns' => '0', 'exists' => '', '*' => 'Wrong namespace type'],
                    ['ns' => 0, '*' => 'Missing exists'],
                    ['ns' => 0, 'exists' => '', '*' => ['unexpected']],
                    'not-an-array',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->assertSame(
            [
                ['ns' => 0, 'title' => 'Article One'],
                ['ns' => 118, 'title' => 'Draft:Article Two'],
            ],
            WikipediaBot::parse_links_response($json)
        );
        $this->assertNull(WikipediaBot::parse_links_response('{"parse":{"links":"bad"}}'));
        $this->assertNull(WikipediaBot::parse_links_response('not json'));
    }

    public function testFirstPageParserRejectsMalformedPagesShapes(): void {
        $this->assertNull(WikipediaBot::first_page_from_response(
            (object) ['query' => (object) ['pages' => []]]
        ));
        $this->assertNull(WikipediaBot::first_page_from_response(
            (object) ['query' => (object) ['pages' => (object) []]]
        ));
        $this->assertNull(WikipediaBot::first_page_from_response(
            (object) ['query' => (object) ['pages' => (object) ['1' => 'not-an-object']]]
        ));

        $page = WikipediaBot::first_page_from_response(
            (object) ['query' => (object) ['pages' => (object) ['1' => (object) ['title' => 'Example']]]]
        );
        $this->assertNotNull($page);
        $this->assertSame('Example', $page->title);
    }

    public function testRedirectTargetParserRequiresString(): void {
        $this->assertSame(
            'Target',
            WikipediaBot::redirect_target_from_response(
                (object) ['query' => (object) ['redirects' => [(object) ['to' => 'Target']]]]
            )
        );
        $this->assertNull(WikipediaBot::redirect_target_from_response(
            (object) ['query' => (object) ['redirects' => [(object) ['to' => []]]]]
        ));
        $this->assertNull(WikipediaBot::redirect_target_from_response(
            (object) ['query' => (object) ['redirects' => 'bad']]
        ));
    }

    public function testMediawikiErrorFieldsRejectObjectsAndArrays(): void {
        $this->assertSame(
            ['maxlag', 'Waiting'],
            WikipediaBot::mediawiki_error_fields((object) ['code' => 'maxlag', 'info' => 'Waiting'])
        );
        $this->assertNull(WikipediaBot::mediawiki_error_fields(
            (object) ['code' => (object) [], 'info' => 'bad']
        ));
        $this->assertNull(WikipediaBot::mediawiki_error_fields([]));
        $this->assertFalse(WikipediaBot::ret_okay(
            (object) ['error' => (object) ['code' => (object) [], 'info' => 'bad']]
        ));
        $this->assertTrue(WikipediaBot::fetch_response_is_retryable(
            (object) ['error' => (object) ['code' => [], 'info' => (object) []]]
        ));
    }

}
