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
    public function testWriteSubmitsGuardedEditAndReportsItsResult(?object $result, bool $expected): void {
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
            $this->assertFalse($bot->last_write_was_skipped());
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
                    'basetimestamp' => '2026-10-25T12:00:00Z',
                    'starttimestamp' => '2026-10-25T12:01:00Z',
                    'nocreate' => '1',
                    'watchlist' => 'nochange',
                    'token' => 'fixture',
                ],
            ], $requests);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'fetch');
        }
    }

    /** @return array<string, array{object|null, bool}> */
    public static function editResults(): array {
        return [
            'accepted' => [(object) ['edit' => (object) ['result' => 'Success']], true],
            'rejected' => [(object) ['error' => (object) ['code' => 'protectedpage', 'info' => 'Protected']], false],
            'no response' => [null, false],
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
