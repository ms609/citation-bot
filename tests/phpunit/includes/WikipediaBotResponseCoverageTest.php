<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class WikipediaBotResponseCoverageTest extends testBaseClass {

    private static function validPage(): stdClass {
        return (object) [
            'pageid' => 123,
            'ns' => 0,
            'title' => 'Example article',
            'lastrevid' => 456,
            'revisions' => [
                (object) [
                    'timestamp' => '2026-08-23T12:34:56Z',
                ],
            ],
        ];
    }

    private static function validResponse(): stdClass {
        return (object) [
            'batchcomplete' => true,
            'query' => (object) [
                'pages' => (object) [
                    '123' => self::validPage(),
                ],
                'tokens' => (object) [
                    'csrftoken' => 'test-token',
                ],
            ],
        ];
    }

    public function testRetOkayAcceptsResponseWithoutError(): void {
        $response = (object) [
            'batchcomplete' => true,
        ];

        $this->assertTrue(
            WikipediaBot::ret_okay($response)
        );
    }

    public function testRetOkayAcceptsEmptyObjectWithoutError(): void {
        $this->assertTrue(
            WikipediaBot::ret_okay(new stdClass())
        );
    }

    public function testRetOkayTreatsEditConflictAsPageSpecific(): void {
        $response = (object) [
            'error' => (object) [
                'code' => 'editconflict',
                'info' => 'Edit conflict',
            ],
        ];

        $this->assertTrue(
            WikipediaBot::ret_okay($response)
        );
    }

    public function testResponse2PageReturnsPageProperties(): void {
        $response = self::validResponse();

        $page = WikipediaBot::response2page($response);

        $this->assertNotNull($page);
        $this->assertSame(123, $page->pageid);
        $this->assertSame(456, $page->lastrevid);
        $this->assertSame('Example article', $page->title);
        $this->assertSame(
            '2026-08-23T12:34:56Z',
            $page->revisions[0]->timestamp
        );
    }

    public function testResponse2PageReturnsFirstPage(): void {
        $first = self::validPage();
        $first->title = 'First page';
        $first->pageid = 1;

        $second = self::validPage();
        $second->title = 'Second page';
        $second->pageid = 2;

        $response = (object) [
            'batchcomplete' => true,
            'query' => (object) [
                'pages' => (object) [
                    '1' => $first,
                    '2' => $second,
                ],
                'tokens' => (object) [
                    'csrftoken' => 'test-token',
                ],
            ],
        ];

        $page = WikipediaBot::response2page($response);

        $this->assertNotNull($page);
        $this->assertSame(1, $page->pageid);
        $this->assertSame('First page', $page->title);
    }

    #[DataProvider('missingPagePropertyProvider')]
    public function testResponse2PageRejectsMissingRequiredPageProperty(
        string $property
    ): void {
        $response = self::validResponse();

        $pages = (array) $response->query->pages;
        $page = reset($pages);
        if ($page === false) {
            $page = new stdClass();
        }

        switch ($property) {
            case 'lastrevid':
                unset($page->lastrevid);
                break;

            case 'title':
                unset($page->title);
                break;

            case 'revisions':
                unset($page->revisions);
                break;

            case 'timestamp':
                unset($page->revisions[0]->timestamp);
                break;
        }

        $this->assertNull(
            WikipediaBot::response2page($response)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingPagePropertyProvider(): array {
        return [
            'missing last revision id' => [
                'lastrevid',
            ],
            'missing title' => [
                'title',
            ],
            'missing revisions' => [
                'revisions',
            ],
            'missing revision timestamp' => [
                'timestamp',
            ],
        ];
    }

    public function testResponse2PageRejectsEmptyRevisions(): void {
        $response = self::validResponse();

        $pages = (array) $response->query->pages;
        $page = reset($pages);
        $page->revisions = [];

        $this->assertNull(
            WikipediaBot::response2page($response)
        );
    }

    public function testResponse2PageRejectsMissingTokensObject(): void {
        $response = self::validResponse();

        unset($response->query->tokens);

        $this->assertNull(
            WikipediaBot::response2page($response)
        );
    }

    public function testResponse2PageRejectsMissingCsrfToken(): void {
        $response = self::validResponse();

        unset($response->query->tokens->csrftoken);

        $this->assertNull(
            WikipediaBot::response2page($response)
        );
    }

    public function testResponse2PageRejectsNullCsrfToken(): void {
        $response = self::validResponse();

        $response->query->tokens->csrftoken = null;

        $this->assertNull(
            WikipediaBot::response2page($response)
        );
    }

    public function testResponse2PageCurrentlyAcceptsEmptyCsrfToken(): void {
        $response = self::validResponse();

        $response->query->tokens->csrftoken = '';

        // response2page() only tests isset(). write_page()
        // performs the stronger empty/string check later.
        $this->assertNotNull(
            WikipediaBot::response2page($response)
        );
    }

    public function testResponse2PageIgnoresUnrecognizedWarnings(): void {
        $response = self::validResponse();

        $response->warnings = (object) [
            'something-else' => (object) [
                '*' => 'Unknown warning',
            ],
        ];

        $page = WikipediaBot::response2page($response);

        $this->assertNotNull($page);
        $this->assertSame(
            'Example article',
            $page->title
        );
    }

    public function testResponse2PagePropWarningOverridesOtherwiseValidResponse(): void {
        $response = self::validResponse();

        $response->warnings = (object) [
            'prop' => (object) [
                '*' => 'Prop warning',
            ],
        ];

        $this->assertNull(
            WikipediaBot::response2page($response)
        );
    }

    public function testResponse2PageInfoWarningOverridesOtherwiseValidResponse(): void {
        $response = self::validResponse();

        $response->warnings = (object) [
            'info' => (object) [
                '*' => 'Info warning',
            ],
        ];

        $this->assertNull(
            WikipediaBot::response2page($response)
        );
    }

    public function testResponse2PageIgnoresExtraResponseFields(): void {
        $response = self::validResponse();

        $response->continue = (object) [
            'continue' => '||',
        ];
        $response->query->userinfo = (object) [
            'name' => 'Citation_bot',
        ];

        $page = WikipediaBot::response2page($response);

        $this->assertNotNull($page);
        $this->assertSame(
            'Example article',
            $page->title
        );
    }

    public function testResultsGoodErrorTakesPrecedenceOverSuccess(): void {
        $result = (object) [
            'error' => (object) [
                'code' => 'test-error',
                'info' => 'Something failed',
            ],
            'edit' => (object) [
                'result' => 'Success',
            ],
        ];

        $this->assertFalse(
            WikipediaBot::resultsGood($result)
        );
    }

    #[DataProvider('nonSuccessResultProvider')]
    public function testResultsGoodRequiresExactSuccess(
        string $result
    ): void {
        $response = (object) [
            'edit' => (object) [
                'result' => $result,
            ],
        ];

        $this->assertFalse(
            WikipediaBot::resultsGood($response)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonSuccessResultProvider(): array {
        return [
            'lowercase' => ['success'],
            'uppercase' => ['SUCCESS'],
            'leading space' => [' Success'],
            'trailing space' => ['Success '],
            'different success word' => ['Succeeded'],
        ];
    }

    public function testResultsGoodAcceptsExactSuccessWithExtraFields(): void {
        $result = (object) [
            'edit' => (object) [
                'result' => 'Success',
                'pageid' => 123,
                'title' => 'Example article',
                'oldrevid' => 456,
                'newrevid' => 457,
                'newtimestamp' => '2026-08-23T12:35:00Z',
            ],
        ];

        $this->assertTrue(
            WikipediaBot::resultsGood($result)
        );
    }

    #[DataProvider('recoverableWikipediaErrorProvider')]
    public function testRetOkayRejectsRecoverableErrors(
        string $info
    ): void {
        $response = (object) [
            'error' => (object) [
                'code' => 'test',
                'info' => $info,
            ],
        ];

        $this->assertFalse(
            WikipediaBot::ret_okay($response)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function recoverableWikipediaErrorProvider(): array {
        return [
            'invalid csrf' => [
                'Invalid CSRF token',
            ],
            'bad title' => [
                'Bad title',
            ],
            'page nonexistent' => [
                'The page you specified does not exist',
            ],
            'alternate nonexistent wording' => [
                "The page you specified doesn't exist",
            ],
        ];
    }
}
