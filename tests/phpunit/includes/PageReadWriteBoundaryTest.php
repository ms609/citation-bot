<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class PageReadWriteBoundaryTest extends testBaseClass {
    #[DataProvider('unreadablePages')]
    public function testUnreadablePageIsRejected(object $details, string $wikitext, int $expected_reads): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub MediaWiki');
        }

        $reads = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'read_details', $details);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'get_a_page', static function () use (&$reads, $wikitext): string {
            ++$reads;
            return $wikitext;
        }, true);
        try {
            $this->assertFalse((new Page())->get_text_from('Example'));
            $this->assertSame($expected_reads, $reads);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'get_a_page');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'read_details');
        }
    }

    /** @return array<string, array{object, string, int}> */
    public static function unreadablePages(): array {
        $details = (object) ['query' => (object) ['pages' => [
            (object) ['title' => 'Example', 'touched' => '2026-10-25T12:00:00Z', 'lastrevid' => 2],
        ]]];
        $missing_title = (object) ['query' => (object) ['pages' => [
            (object) ['touched' => '2026-10-25T12:00:00Z', 'lastrevid' => 2],
        ]]];
        return [
            'API error' => [(object) ['error' => (object) ['info' => 'Read failed']], '', 0],
            'missing title' => [$missing_title, '', 0],
            'empty wikitext' => [$details, '', 1],
            'redirect' => [$details, '#REDIRECT [[Target]]', 1],
            'long redirect' => [$details, '#REDIRECT [[Target]]' . str_repeat(' content', 300), 1],
        ];
    }

    public function testPageReadsTheRevisionReturnedByItsMetadataQuery(): void {
        if (!function_exists('uopz_set_hook') || !function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub MediaWiki');
        }

        $details = (object) ['query' => (object) ['pages' => [
            (object) ['title' => 'Example', 'touched' => '2026-10-25T12:00:00Z', 'lastrevid' => 456],
        ]]];
        $urls = new ArrayObject();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'read_details', $details);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec_withFalse', 'Text from the expected revision');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_hook('curl_setopt_array', static function (CurlHandle $handle, array $options) use ($urls): void {
            if (isset($options[CURLOPT_URL])) {
                $urls->append($options[CURLOPT_URL]);
            }
        });

        try {
            $page = new Page();
            $this->assertTrue($page->get_text_from('Example'));
            $this->assertSame('Text from the expected revision', $page->parsed_text());
            $this->assertSame([WIKI_ROOT . '?title=Example&action=raw&oldid=456'], $urls->getArrayCopy());
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_hook('curl_setopt_array');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec_withFalse');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'read_details');
        }
    }

    /**
     * @param string $text
     * @param int $last_revision
     * @param string|null $token
     * @param string $started_editing
     * @param bool $conflict
     * @param list<string> $expected_actions
     */
    #[DataProvider('rejectedEdits')]
    public function testUnsafeWriteDoesNotSubmitEdit(
        string $text,
        int $last_revision,
        ?string $token,
        string $started_editing,
        bool $conflict,
        array $expected_actions,
    ): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub MediaWiki');
        }

        $preflight = $token === null ? null : self::editPreflight($token);
        $actions = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_minor_error', null);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'fetch', static function (array $request) use (&$actions, $preflight): ?object {
            $actions[] = $request['action'];
            return $preflight;
        }, true);
        try {
            $bot = new WikipediaBot();
            $this->assertSame($conflict, $bot->write_page('Example', $text, 'Summary', $last_revision, $started_editing));
            $this->assertSame($conflict, $bot->last_write_was_skipped());
            $this->assertSame($expected_actions, $actions);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'fetch');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_minor_error');
        }
    }

    /** @return array<string, array{string, int, string|null, string, bool, list<string>}> */
    public static function rejectedEdits(): array {
        return [
            'unreplaced placeholder' => ['CITATION_BOT_PLACEHOLDER_COMMENT', 2, 'fixture', '', false, []],
            'stale revision' => ['Wikitext', 1, 'fixture', '', true, ['query']],
            'newer server timestamp' => ['Wikitext', 2, 'fixture', '2026-10-25T11:59:59Z', true, ['query']],
            'failed preflight' => ['Wikitext', 2, null, '', false, ['query']],
            'empty token' => ['Wikitext', 2, '', '', false, ['query']],
        ];
    }

    private static function editPreflight(string $token): object {
        return (object) [
            'batchcomplete' => true,
            'query' => (object) [
                'tokens' => (object) ['csrftoken' => $token],
                'pages' => (object) ['1' => (object) [
                    'pageid' => 1,
                    'title' => 'Example',
                    'lastrevid' => 2,
                    'revisions' => [(object) ['timestamp' => '2026-10-25T12:00:00Z']],
                ]],
            ],
        ];
    }

    #[DataProvider('editResults')]
    public function testWriteSubmitsGuardedEditAndReportsItsResult(?object $result, bool $expected, bool $skipped, ?int $revision): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub MediaWiki');
        }

        $preflight = self::editPreflight('fixture');
        $requests = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'fetch', static function (array $request) use (&$requests, $preflight, $result): ?object {
            $requests[] = $request;
            return $request['action'] === 'query' ? $preflight : $result;
        }, true);
        try {
            $bot = new WikipediaBot();
            $this->assertSame($expected, $bot->write_page('Example', 'Updated citation', 'Fix citation', 2, '2026-10-25T12:01:00Z'));
            $this->assertSame($skipped, $bot->last_write_was_skipped());
            $this->assertSame($revision, $bot->last_written_revision());
            $this->assertSame([
                [
                    'action' => 'query',
                    'prop' => 'info|revisions',
                    'rvprop' => 'timestamp',
                    'meta' => 'tokens',
                    'titles' => 'Example',
                ],
                [
                    'action' => 'edit',
                    'title' => 'Example',
                    'text' => 'Updated citation',
                    'summary' => 'Fix citation',
                    'notminor' => '1',
                    'bot' => '1',
                    'baserevid' => '2',
                    'starttimestamp' => '2026-10-25T12:01:00Z',
                    'nocreate' => '1',
                    'watchlist' => 'nochange',
                    'token' => 'fixture',
                ],
            ], $requests);
            if ($revision !== null) {
                // A preflight conflict in a later batch item must clear the old diff ID.
                $this->assertTrue($bot->write_page('Example', 'Outdated citation', 'Fix citation', 1, '2026-10-25T12:01:00Z'));
                $this->assertTrue($bot->last_write_was_skipped());
                $this->assertNull($bot->last_written_revision());
                $this->assertCount(3, $requests);
            }
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'fetch');
        }
    }

    /** @return array<string, array{object|null, bool, bool, int|null}> */
    public static function editResults(): array {
        return [
            'accepted' => [(object) ['edit' => (object) ['result' => 'Success', 'newrevid' => 123]], true, false, 123],
            'no change' => [(object) ['edit' => (object) ['result' => 'Success', 'nochange' => true]], true, false, null],
            'rejected' => [(object) ['error' => (object) ['code' => 'protectedpage', 'info' => 'Protected']], false, false, null],
            'edit conflict after preflight' => [(object) ['error' => (object) ['code' => 'editconflict']], true, true, null],
            'no response' => [null, false, false, null],
        ];
    }

    #[DataProvider('statisticsPreflights')]
    public function testStatisticsWriteValidatesPreflightAndAllowsCreation(
        object $preflight,
        ?bool $existing,
        bool $accepted
    ): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub MediaWiki');
        }

        $recorded_requests = new ArrayObject();
        $reads = [];
        $title = 'User:Citation bot/statistics';
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'read_details', static function (string $page) use (&$reads): object {
            $reads[] = $page;
            return (object) ['curtimestamp' => '2026-10-25T12:01:00Z'];
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'fetch', static function (array $request) use ($recorded_requests, $preflight, $accepted): object {
            $recorded_requests->append($request);
            if ($request['action'] === 'query') {
                return $preflight;
            }
            return (object) ['edit' => (object) ['result' => $accepted ? 'Success' : 'Failure']];
        }, true);
        try {
            $this->assertSame($accepted, (new WikipediaBot())->write_statistics_page($title, 'Statistics', 'Update statistics'));
            $this->assertSame($existing === true ? [$title] : [], $reads);
            /** @var non-empty-list<array<string, string>> $requests */
            $requests = $recorded_requests->getArrayCopy();
            $this->assertSame(['action' => 'query', 'prop' => 'info|revisions', 'rvprop' => 'timestamp',
                'meta' => 'tokens', 'titles' => $title], $requests[0]);
            if ($existing === null) {
                $this->assertCount(1, $requests);
                return;
            }
            $this->assertCount(2, $requests);
            $edit = ['action' => 'edit', 'title' => $title, 'text' => 'Statistics', 'summary' => 'Update statistics',
                'notminor' => '1', 'bot' => '1', 'watchlist' => 'nochange', 'token' => 'fixture'];
            if ($existing) {
                $edit['basetimestamp'] = '2026-10-25T12:00:00Z';
                $edit['starttimestamp'] = '2026-10-25T12:01:00Z';
            } else {
                $edit['createonly'] = '1';
            }
            $this->assertSame($edit, $requests[1]);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'fetch');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'read_details');
        }
    }

    /** @return array<string, array{object, bool|null, bool}> */
    public static function statisticsPreflights(): array {
        $missing = self::editPreflight('fixture');
        $missing->query->pages = (object) ['-1' => (object) ['missing' => true]];
        $missing_without_token = self::editPreflight('');
        $missing_without_token->query->pages = $missing->query->pages;
        return [
            'update existing page' => [self::editPreflight('fixture'), true, true],
            'create missing page' => [$missing, false, true],
            'edit rejected by MediaWiki' => [$missing, false, false],
            'existing page without token' => [self::editPreflight(''), null, false],
            'missing page without token' => [$missing_without_token, null, false],
            'unrecognized preflight' => [(object) [], null, false],
        ];
    }
}
