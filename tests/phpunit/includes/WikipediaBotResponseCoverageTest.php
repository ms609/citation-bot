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

    public function testResponse2PageReturnsFirstPageWith1(): void {
        $first = self::validPage();
        $first->title = 'First page';
        $first->pageid = 1;

        // These are not used, but set to show that calling validPage() again does not break things
        $second = self::validPage();
        $second->title = 'Second page';
        $second->pageid = 2;

        $response = (object) [
            'batchcomplete' => true,
            'query' => (object) [
                'pages' => (object) [
                    '1' => $first,
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

    public function testResponse2PageReturnsNothingWith2(): void {
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
                    '2' => $second, // Should not get two pages
                ],
                'tokens' => (object) [
                    'csrftoken' => 'test-token',
                ],
            ],
        ];

        $page = WikipediaBot::response2page($response);

        $this->assertNull($page);
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

    public function testCategoryMembersBoundedStopsAtLimitAndSignalsThresholds(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz extension required');
        }

        $pages = [];
        for ($i = 1; $i <= 60; ++$i) {
            $pages[] = (object) [
                'pageid' => $i,
                'ns' => 0,
                'title' => 'Article ' . (string) $i,
            ];
        }
        $response = json_encode(
            (object) [
                'query' => (object) ['categorymembers' => $pages],
                'continue' => (object) ['cmcontinue' => 'next', 'continue' => '-||'],
            ],
            JSON_THROW_ON_ERROR
        );

        $bulk_signals = 0;
        $large_signals = 0;
        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return(
                WikipediaBot::class,
                'query_api',
                static fn (array $_vars): string => $response,
                true
            );

            $titles = WikipediaBot::category_members_bounded(
                'Example',
                50,
                static function () use (&$bulk_signals): void {
                    ++$bulk_signals;
                },
                static function () use (&$large_signals): void {
                    ++$large_signals;
                }
            );

            $this->assertCount(50, $titles);
            $this->assertSame('Article 1', $titles[0]);
            $this->assertSame('Article 50', $titles[49]);
            $this->assertSame(1, $bulk_signals);
            $this->assertSame(1, $large_signals);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'query_api');
        }
    }

    public function testLinkedPagesBatchUsesBoundedGeneratorContinuation(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz extension required');
        }

        /** @var array<string, string> $captured */
        $captured = [];
        $response = json_encode(
            (object) [
                'query' => (object) [
                    'pages' => (object) [
                        '1' => (object) ['pageid' => 1, 'ns' => 0, 'title' => 'Alpha'],
                        '2' => (object) ['pageid' => 2, 'ns' => 118, 'title' => 'Draft:Beta'],
                        '-1' => (object) ['ns' => 0, 'title' => 'Missing', 'missing' => true],
                    ],
                ],
                'continue' => (object) [
                    'gplcontinue' => 'next-token',
                    'continue' => 'gplcontinue||',
                ],
            ],
            JSON_THROW_ON_ERROR
        );

        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return(
                WikipediaBot::class,
                'query_api',
                static function (array $vars) use (&$captured, $response): string {
                    $captured = $vars;
                    return $response;
                },
                true
            );

            $batch = WikipediaBot::linked_pages_batch(
                'User:Example',
                ['gplcontinue' => 'previous', 'continue' => '-||'],
                999
            );

            $this->assertIsArray($batch);
            $this->assertSame('query', $captured['action'] ?? null);
            $this->assertSame('links', $captured['generator'] ?? null);
            $this->assertSame('User:Example', $captured['titles'] ?? null);
            $this->assertSame('0|118', $captured['gplnamespace'] ?? null);
            $this->assertSame('500', $captured['gpllimit'] ?? null);
            $this->assertSame('previous', $captured['gplcontinue'] ?? null);
            $this->assertSame('-||', $captured['continue'] ?? null);
            $this->assertSame(
                [
                    ['ns' => 0, 'title' => 'Alpha'],
                    ['ns' => 118, 'title' => 'Draft:Beta'],
                ],
                $batch['links']
            );
            $this->assertSame(3, $batch['scanned']);
            $this->assertSame(
                ['gplcontinue' => 'next-token', 'continue' => 'gplcontinue||'],
                $batch['continue']
            );
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'query_api');
        }
    }

    public function testCategoryMembersBoundedCarriesContinuationAndShrinksNextBatch(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz extension required');
        }

        $first_pages = [];
        for ($i = 1; $i <= 5; ++$i) {
            $first_pages[] = (object) [
                'pageid' => $i,
                'ns' => 0,
                'title' => 'Article ' . (string) $i,
            ];
        }
        $second_pages = [];
        for ($i = 6; $i <= 8; ++$i) {
            $second_pages[] = (object) [
                'pageid' => $i,
                'ns' => 0,
                'title' => 'Article ' . (string) $i,
            ];
        }

        $responses = [
            json_encode(
                (object) [
                    'query' => (object) ['categorymembers' => $first_pages],
                    'continue' => (object) ['cmcontinue' => 'next-page', 'continue' => '-||'],
                ],
                JSON_THROW_ON_ERROR
            ),
            json_encode(
                (object) [
                    'query' => (object) ['categorymembers' => $second_pages],
                ],
                JSON_THROW_ON_ERROR
            ),
        ];
        /** @var array<int, array<string, string>> $calls */
        $calls = [];
        $bulk_signals = 0;
        $large_signals = 0;

        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return(
                WikipediaBot::class,
                'query_api',
                static function (array $vars) use (&$calls, &$responses): string {
                    $calls[] = $vars;
                    $response = array_shift($responses);
                    if (!is_string($response)) {
                        throw new RuntimeException('Unexpected extra category API call');
                    }
                    return $response;
                },
                true
            );

            $titles = WikipediaBot::category_members_bounded(
                'Example',
                8,
                static function () use (&$bulk_signals): void {
                    ++$bulk_signals;
                },
                static function () use (&$large_signals): void {
                    ++$large_signals;
                }
            );

            $this->assertCount(8, $titles);
            $this->assertSame('Article 1', $titles[0]);
            $this->assertSame('Article 8', $titles[7]);
            $this->assertSame(1, $bulk_signals);
            $this->assertSame(0, $large_signals);
            $this->assertCount(2, $calls);
            $first_call = $calls[0] ?? null;
            $second_call = $calls[1] ?? null;
            $this->assertIsArray($first_call);
            $this->assertIsArray($second_call);
            if (!is_array($first_call) || !is_array($second_call)) {
                throw new RuntimeException('Expected captured category API calls.');
            }
            $this->assertSame('5', $first_call['cmlimit'] ?? null);
            $this->assertArrayNotHasKey('cmcontinue', $first_call);
            $this->assertSame('next-page', $second_call['cmcontinue'] ?? null);
            $this->assertSame('-||', $second_call['continue'] ?? null);
            $this->assertSame('3', $second_call['cmlimit'] ?? null);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'query_api');
        }
    }

    public function testLinkedPagesBatchCarriesReturnedContinuationIntoNextRequest(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz extension required');
        }

        /** @var array<int, array<string, string>> $calls */
        $calls = [];
        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return(
                WikipediaBot::class,
                'query_api',
                static function (array $vars) use (&$calls): string {
                    $calls[] = $vars;
                    if (!isset($vars['gplcontinue'])) {
                        return json_encode(
                            (object) [
                                'query' => (object) [
                                    'pages' => (object) [
                                        '1' => (object) ['pageid' => 1, 'ns' => 0, 'title' => 'Alpha'],
                                        '2' => (object) ['pageid' => 2, 'ns' => 0, 'title' => 'Beta'],
                                    ],
                                ],
                                'continue' => (object) [
                                    'gplcontinue' => 'next-token',
                                    'continue' => 'gplcontinue||',
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        );
                    }

                    if ($vars['gplcontinue'] !== 'next-token' || $vars['continue'] !== 'gplcontinue||') {
                        throw new RuntimeException('Linked-pages continuation was not propagated');
                    }
                    return json_encode(
                        (object) [
                            'query' => (object) [
                                'pages' => (object) [
                                    '3' => (object) ['pageid' => 3, 'ns' => 118, 'title' => 'Draft:Gamma'],
                                ],
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    );
                },
                true
            );

            $first = WikipediaBot::linked_pages_batch('User:Example', null, 2);
            $this->assertIsArray($first);
            $this->assertSame(
                ['gplcontinue' => 'next-token', 'continue' => 'gplcontinue||'],
                $first['continue']
            );
            $this->assertSame(2, $first['scanned']);

            $second = WikipediaBot::linked_pages_batch('User:Example', $first['continue'], 2);
            $this->assertIsArray($second);
            $this->assertSame([['ns' => 118, 'title' => 'Draft:Gamma']], $second['links']);
            $this->assertSame(1, $second['scanned']);
            $this->assertNull($second['continue']);
            $this->assertCount(2, $calls);
            $first_call = $calls[0] ?? null;
            $second_call = $calls[1] ?? null;
            $this->assertIsArray($first_call);
            $this->assertIsArray($second_call);
            if (!is_array($first_call) || !is_array($second_call)) {
                throw new RuntimeException('Expected captured linked-page API calls.');
            }
            $this->assertSame('2', $first_call['gpllimit'] ?? null);
            $this->assertSame('2', $second_call['gpllimit'] ?? null);
            $this->assertSame('next-token', $second_call['gplcontinue'] ?? null);
            $this->assertSame('gplcontinue||', $second_call['continue'] ?? null);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'query_api');
        }
    }

    public function testCategoryProbeTakesLeaseBeforeSixthEmptyContinuationBatch(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz extension required');
        }

        $calls = 0;
        try {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return(
                WikipediaBot::class,
                'query_api',
                static function (array $_vars) use (&$calls): string {
                    ++$calls;
                    return json_encode(
                        (object) [
                            'query' => (object) ['categorymembers' => []],
                            'continue' => (object) [
                                'cmcontinue' => 'still-more-' . (string) $calls,
                                'continue' => '-||',
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    );
                },
                true
            );

            try {
                WikipediaBot::category_members_bounded(
                    'Example',
                    100,
                    static function (): void {
                        throw new RuntimeException('probe-bound-reached');
                    }
                );
                $this->fail('Expected the bounded probe callback to interrupt discovery');
            } catch (RuntimeException $exception) {
                $this->assertSame('probe-bound-reached', $exception->getMessage());
            }
            $this->assertSame(BIG_RUN_DISCOVERY_BATCH_PROBE_LIMIT, $calls);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'query_api');
        }
    }
}
