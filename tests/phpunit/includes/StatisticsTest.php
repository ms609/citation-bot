<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class StatisticsTest extends testBaseClass {

    public function testUcbFromCommentSingle(): void {
        $this->assertSame('#UCB_toolbar', statistics_ucb_from_comment('Suggested by Bob | #UCB_toolbar'));
        $this->assertSame('#UCB_Category', statistics_ucb_from_comment('Suggested by X | [[Category:CS1 errors]] | #UCB_Category'));
        $this->assertSame('#UCB_webform', statistics_ucb_from_comment('Add: title. | Use this bot | Suggested by Y | #UCB_webform'));
        $this->assertSame('#UCB_Gadget', statistics_ucb_from_comment('Add: title | Use this tool | #UCB_Gadget'));
    }

    public function testUcbFromCommentNoTag(): void {
        $this->assertSame(STATISTICS_UNTAGGED_LABEL, statistics_ucb_from_comment('Add: pages | Use this bot'));
        $this->assertSame(STATISTICS_UNTAGGED_LABEL, statistics_ucb_from_comment('Misc citation tidying. | Use this bot | Testing bot write function'));
        $this->assertSame(STATISTICS_UNTAGGED_LABEL, statistics_ucb_from_comment(''));
    }

    public function testUcbFromCommentFirstMatch(): void {
        // If somehow two tags present, first wins
        $this->assertSame('#UCB_toolbar', statistics_ucb_from_comment('#UCB_toolbar and #UCB_Category'));
    }

    public function testUcbFromCommentInvalidTruncated(): void {
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | Suggested by X | Linked from Foo | #UCB_webform_lin'));
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | Suggested by X | Linked from Foo | #UCB_webform_l'));
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | Suggested by X | Linked from Foo | #UCB_webform_li'));
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | #UCB_web'));
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | #UCB_webf'));
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | #UCB_Foo'));
    }

    public function testUcbFromCommentInvalidBareFragment(): void {
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | #UCB'));
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | #UCB_'));
        $this->assertSame(STATISTICS_INVALID_LABEL, statistics_ucb_from_comment('Add: lots | #UCB '));
    }

    public function testAggregate(): void {
        $edits = [
            (object) ['comment' => 'Add: title | #UCB_toolbar'],
            (object) ['comment' => 'Add: title | #UCB_toolbar'],
            (object) ['comment' => 'Add: title | #UCB_Category'],
            (object) ['comment' => 'Misc | Testing bot write function'],
            (object) ['comment' => ''],
        ];
        $counts = statistics_aggregate($edits);
        $this->assertSame(2, $counts['#UCB_toolbar']);
        $this->assertSame(1, $counts['#UCB_Category']);
        $this->assertSame(2, $counts[STATISTICS_UNTAGGED_LABEL]);
    }

    public function testAggregateArrayForm(): void {
        $edits = [
            ['comment' => 'Add: title | #UCB_webform'],
            ['comment' => 'Add: title | #UCB_webform'],
        ];
        $counts = statistics_aggregate($edits);
        $this->assertSame(2, $counts['#UCB_webform']);
    }

    public function testParseContribsResponseOk(): void {
        $obj = (object) [
            'query' => (object) [
                'usercontribs' => [
                    (object) ['comment' => 'a | #UCB_toolbar', 'timestamp' => '2026-08-31T00:00:00Z'],
                    (object) ['comment' => 'b | #UCB_Category', 'timestamp' => '2026-08-31T01:00:00Z'],
                ],
            ],
            'continue' => (object) ['uccontinue' => '20260831|123'],
        ];
        $parsed = statistics_parse_contribs_response($obj);
        $this->assertNotNull($parsed);
        $this->assertCount(2, $parsed['edits']);
        $this->assertSame('20260831|123', $parsed['continue']);
    }

    public function testParseContribsResponseNoContinue(): void {
        $obj = (object) [
            'query' => (object) [
                'usercontribs' => [
                    (object) ['comment' => 'a', 'timestamp' => '2026-08-31T00:00:00Z'],
                ],
            ],
        ];
        $parsed = statistics_parse_contribs_response($obj);
        $this->assertNotNull($parsed);
        $this->assertCount(1, $parsed['edits']);
        $this->assertNull($parsed['continue']);
    }

    public function testParseContribsResponseMalformed(): void {
        $this->assertNull(statistics_parse_contribs_response(null));
        $this->assertNull(statistics_parse_contribs_response((object) []));
        $this->assertNull(statistics_parse_contribs_response((object) ['query' => (object) []]));
    }

    public function testGenerateWikitextZero(): void {
        $now = new DateTimeImmutable('2026-08-31 00:00:00', new DateTimeZone('UTC'));
        $text = statistics_generate_wikitext([], 0, $now, 24);
        $this->assertStringContainsString('No edits were made in the last 24 hours', $text);
        $this->assertStringContainsString('2026-08-31 00:00:00 UTC', $text);
    }

    public function testGenerateWikitextWithData(): void {
        $counts = [
            '#UCB_toolbar' => 5,
            '#UCB_Category' => 3,
            STATISTICS_UNTAGGED_LABEL => 2,
        ];
        $now = new DateTimeImmutable('2026-08-31 12:34:56', new DateTimeZone('UTC'));
        $text = statistics_generate_wikitext($counts, 10, $now, 24);
        $this->assertStringContainsString('Total edits in last 24 hours: \'\'\'10\'\'\'', $text);
        $this->assertStringContainsString("''Last updated: 2026-08-31 12:34:56 UTC''", $text);
        $this->assertStringContainsString('{| class="wikitable sortable"', $text);
        $this->assertStringContainsString('! UCB type !! Edits', $text);
        $this->assertStringNotContainsString('Percentage', $text);
        $this->assertStringContainsString('<code>#UCB_toolbar</code> || 5', $text);
        $this->assertStringContainsString('<code>#UCB_Category</code> || 3', $text);
        $this->assertStringContainsString('<code>' . STATISTICS_UNTAGGED_LABEL . '</code> || 2', $text);
        $this->assertStringContainsString('! Total || 10', $text);
        $this->assertStringNotContainsString('%', $text);
        // Sorted descending – toolbar first
        $pos_toolbar = mb_strpos($text, '#UCB_toolbar');
        $pos_category = mb_strpos($text, '#UCB_Category');
        $this->assertTrue($pos_toolbar < $pos_category);
    }

    public function testGenerateWikitextEscapesPipe(): void {
        $counts = ['#UCB_toolbar|evil' => 1];
        $now = new DateTimeImmutable('2026-08-31 00:00:00', new DateTimeZone('UTC'));
        $text = statistics_generate_wikitext($counts, 1, $now, 24);
        $this->assertStringNotContainsString('#UCB_toolbar|evil', $text);
        $this->assertStringContainsString('&#124;', $text);
    }

    public function testKnownUcbTypesConstant(): void {
        $this->assertContains('#UCB_toolbar', KNOWN_UCB_TYPES);
        $this->assertContains('#UCB_Gadget', KNOWN_UCB_TYPES);
        $this->assertContains('#UCB_webform_linked', KNOWN_UCB_TYPES);
    }

    #[DataProvider('statisticsJobOutcomes')]
    public function testStatisticsJobWritesOnlyCompleteWindows(
        string $scenario,
        int $status,
        ?string $edit_summary,
    ): void {
        if (!function_exists('uopz_set_return') || !function_exists('proc_open')) {
            $this->markTestSkipped('The statistics CLI fixture requires uopz and proc_open');
        }

        $fixture = <<<'PHP'
require $argv[1] . '/src/includes/setup.php';
$scenario = $argv[2];
$responses = match ($scenario) {
    'partial' => ['{"query":{"usercontribs":[{"comment":"#UCB_Gadget"}]},"continue":{"uccontinue":"next"}}', ''],
    'malformed' => ['{"query":{"usercontribs":[]},"continue":{"uccontinue":false}}'],
    'empty' => ['{"query":{"usercontribs":[]}}'],
    default => ['{"query":{"usercontribs":[{"comment":"#UCB_Gadget"}]}}'],
};
uopz_set_return(WikipediaBot::class, 'query_api_authenticated', static function () use (&$responses): string {
    return array_shift($responses) ?? '';
}, true);
uopz_set_return(WikipediaBot::class, 'query_api', '');
uopz_set_return(WikipediaBot::class, 'get_a_page', 'Existing statistics');
$existing = (object) ['title' => 'User:Citation bot/statistics', 'lastrevid' => 5,
    'revisions' => [(object) ['timestamp' => '2026-11-08T12:00:00Z']]];
$missing = (object) ['title' => 'User:Citation bot/statistics', 'missing' => ''];
$details = $scenario === 'created' || $scenario === 'already created' ? $missing : $existing;
uopz_set_return(WikipediaBot::class, 'read_details', (object) [
    'curtimestamp' => '2026-11-08T12:01:00Z',
    'query' => (object) ['pages' => (object) ['1' => $details]],
]);
uopz_set_return(WikipediaBot::class, 'fetch', static function (array $request) use ($existing, $missing, $scenario): object {
    if ($request['action'] === 'edit') {
        echo 'EDIT: ', $request['summary'], PHP_EOL;
        if ($scenario === 'conflict') {
            return (object) ['error' => (object) ['code' => 'editconflict']];
        }
        if ($scenario === 'created' && ($request['createonly'] ?? '') !== '1') {
            throw new LogicException('The missing-page write was not create-only');
        }
        return (object) ['edit' => (object) ['result' => 'Success', 'newrevid' => 6]];
    }
    return (object) ['batchcomplete' => true, 'query' => (object) [
        'pages' => (object) ['1' => $scenario === 'created' ? $missing : $existing],
        'tokens' => (object) ['csrftoken' => 'fixture'],
    ]];
}, true);
uopz_set_return('bot_curl_exec_withFalse', static function (): never {
    throw new LogicException('Unexpected network request');
}, true);
require $argv[1] . '/src/update_statistics.php';
PHP;

        $process = proc_open( // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
            [PHP_BINARY, '-d', 'uopz.exit=1', '-r', $fixture, '--', dirname(__DIR__, 3), $scenario],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit_status = proc_close($process);

        $this->assertIsString($output);
        $this->assertSame($status, $exit_status, $output);
        if ($edit_summary === null) {
            $this->assertStringNotContainsString('EDIT: ', $output);
        } else {
            $this->assertSame(1, mb_substr_count($output, 'EDIT: '));
            $this->assertStringContainsString('EDIT: Update statistics: ' . $edit_summary, $output);
        }
    }

    /** @return array<string, array{string, int, string|null}> */
    public static function statisticsJobOutcomes(): array {
        return [
            'complete' => ['complete', 0, '1 edits in last 24 hours'],
            'empty is complete' => ['empty', 0, '0 edits in last 24 hours'],
            'later batch failed' => ['partial', 1, null],
            'malformed continuation' => ['malformed', 1, null],
            'conflict is not retried' => ['conflict', 1, '1 edits in last 24 hours'],
            'missing page is created atomically' => ['created', 0, '1 edits in last 24 hours'],
            'another writer created the page first' => ['already created', 1, null],
        ];
    }

}
