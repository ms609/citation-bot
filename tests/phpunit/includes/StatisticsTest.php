<?php

declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

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
}
