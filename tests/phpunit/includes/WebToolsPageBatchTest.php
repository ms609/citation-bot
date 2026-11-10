<?php
declare(strict_types=1);

/*
 * Network-free edge-case tests for the page-batch, edit-summary, retry-text and
 * admission-buffer helpers in WebTools.php.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

final class WebToolsPageBatchTest extends testBaseClass {

    private const BUFFER_KEY = 'citation_bot_admission_buffer_level';

    #[\Override]
    protected function tearDown(): void {
        unset($GLOBALS[self::BUFFER_KEY]);
        parent::tearDown();
    }

    // ---- filter_runnable_page_titles ----------------------------------------

    public function testFilterKeepsFirstOccurrenceOrderAndReindexes(): void {
        $this->assertSame(
            ['B', 'A', 'C'],
            filter_runnable_page_titles(['B', 'A', 'B', 'C', 'A'])
        );
    }

    public function testFilterOfEmptyListIsEmpty(): void {
        $this->assertSame([], filter_runnable_page_titles([]));
    }

    public function testFilterDropsBlankAndWhitespaceOnlyTitles(): void {
        $this->assertSame(
            ['Real'],
            filter_runnable_page_titles(['', '   ', "\t\n", 'Real', "\r"])
        );
    }

    public function testFilterSkipsNonStringTitles(): void {
        $this->assertSame(
            ['ok'],
            filter_runnable_page_titles([1, null, ['x'], 'ok', 2.5, true, false])
        );
    }

    public function testFilterSkipsTitlesWithInvalidUtf8(): void {
        $this->assertSame(
            ['good', 'also good'],
            filter_runnable_page_titles(["bad\xFF", 'good', "\xC3", 'also good'])
        );
    }

    public function testFilterEverythingRejectedGivesEmptyList(): void {
        $this->assertSame([], filter_runnable_page_titles(['', 5, "\xFF", '  ']));
    }

    public function testFilterTitleLengthLimitIsInBytes(): void {
        $this->assertSame([str_repeat('a', 255)], filter_runnable_page_titles([str_repeat('a', 255)]));
        $this->assertSame([], filter_runnable_page_titles([str_repeat('a', 256)]));

        // Two-byte characters: 127 of them are 254 bytes, 128 are 256 bytes.
        $this->assertSame([str_repeat('é', 127)], filter_runnable_page_titles([str_repeat('é', 127)]));
        $this->assertSame([], filter_runnable_page_titles([str_repeat('é', 128)]));

        // 127 two-byte characters plus one ASCII byte is exactly 255 bytes.
        $exactly_255 = str_repeat('é', 127) . 'a';
        $this->assertSame([$exactly_255], filter_runnable_page_titles([$exactly_255]));
    }

    public function testFilterDoesNotModifyKeptTitles(): void {
        $this->assertSame([' padded '], filter_runnable_page_titles([' padded ']));
    }

    public function testFilterDeduplicationIsExactAndCaseSensitive(): void {
        $this->assertSame(
            ['a', 'A', ' a', 'a '],
            filter_runnable_page_titles(['a', 'A', ' a', 'a ', 'a', 'A'])
        );
    }

    public function testFilterLooksPastRejectedEntriesWhenDeduplicating(): void {
        $this->assertSame(
            ['First', 'Second'],
            filter_runnable_page_titles(['First', '', 'First', 7, 'Second', "\xFF", 'Second'])
        );
    }

    // ---- page_batch_input_within_limit --------------------------------------

    public function testBatchInputAllowsExactlyTwoFiftySixBytesPerPage(): void {
        $this->assertTrue(page_batch_input_within_limit('', 1));
        $this->assertTrue(page_batch_input_within_limit(str_repeat('x', 256), 1));
        $this->assertFalse(page_batch_input_within_limit(str_repeat('x', 257), 1));

        $this->assertTrue(page_batch_input_within_limit(str_repeat('x', 768), 3));
        $this->assertFalse(page_batch_input_within_limit(str_repeat('x', 769), 3));
    }

    public function testBatchInputRejectsNonPositiveLimits(): void {
        $this->assertFalse(page_batch_input_within_limit('', 0));
        $this->assertFalse(page_batch_input_within_limit('a', 0));
        $this->assertFalse(page_batch_input_within_limit('a', -5));
    }

    public function testBatchInputCountsBytesNotCharacters(): void {
        // 128 two-byte characters are exactly 256 bytes; one more character tips it over.
        $this->assertTrue(page_batch_input_within_limit(str_repeat('é', 128), 1));
        $this->assertFalse(page_batch_input_within_limit(str_repeat('é', 129), 1));
    }

    public function testBatchInputDefaultLimitAcceptsAnOrdinaryList(): void {
        $this->assertTrue(page_batch_input_within_limit('Some Page|Other Page|Third Page'));
    }

    // ---- page_result_from_write ---------------------------------------------

    public function testPageResultFromWriteCoversAllFourCombinations(): void {
        $this->assertNull(page_result_from_write(false, false));
        $this->assertNull(page_result_from_write(false, true));
        $this->assertTrue(page_result_from_write(true, false));
        $this->assertFalse(page_result_from_write(true, true));
    }

    // ---- retry / wait text ---------------------------------------------------

    public function testHumanizeWaitBoundariesBetweenSecondsAndMinutes(): void {
        $this->assertSame('1 second', big_run_humanize_wait(1));
        $this->assertSame('2 seconds', big_run_humanize_wait(2));
        $this->assertSame('1 second', big_run_humanize_wait(-5));
        $this->assertSame('59 seconds', big_run_humanize_wait(59));
        $this->assertSame('1 minute', big_run_humanize_wait(60));
        $this->assertSame('2 minutes', big_run_humanize_wait(61));
        $this->assertSame('2 minutes', big_run_humanize_wait(120));
        $this->assertSame('3 minutes', big_run_humanize_wait(121));
        $this->assertSame('60 minutes', big_run_humanize_wait(3600));
    }

    public function testEffectiveRetryAfterForTokenWaitsAddsThirtyPercentAndRoundsUp(): void {
        $this->assertSame(2, big_run_effective_retry_after('tokens', null));
        $this->assertSame(2, big_run_effective_retry_after('tokens', 0));
        $this->assertSame(2, big_run_effective_retry_after('tokens', -50));
        $this->assertSame(2, big_run_effective_retry_after('tokens', 1));
        $this->assertSame(13, big_run_effective_retry_after('tokens', 10));
        $this->assertSame(130, big_run_effective_retry_after('tokens', 100));
    }

    public function testEffectiveRetryAfterForPoolWaitsNeverDropsBelowFixedBackoff(): void {
        $floor = big_run_pool_retry_seconds();
        foreach (['total_full', 'large_full'] as $reason) {
            $this->assertSame($floor, big_run_effective_retry_after($reason, null));
            $this->assertSame($floor, big_run_effective_retry_after($reason, 0));
            $this->assertSame($floor, big_run_effective_retry_after($reason, 1));
            $this->assertSame($floor + 100, big_run_effective_retry_after($reason, $floor + 100));
        }
    }

    public function testEffectiveRetryAfterForOtherReasonsHasATwoSecondFloor(): void {
        foreach (['probe_full', 'retry_later', 'lease_lost', 'something_new'] as $reason) {
            $this->assertSame(2, big_run_effective_retry_after($reason, null), $reason);
            $this->assertSame(2, big_run_effective_retry_after($reason, 1), $reason);
            $this->assertSame(7, big_run_effective_retry_after($reason, 7), $reason);
        }
    }

    public function testBusyHeadersAlwaysAre503NoStoreWithMatchingRetryAfter(): void {
        $this->assertSame(
            ['status' => 503, 'retry_after' => '2', 'cache_control' => 'no-store'],
            big_run_busy_headers('lease_lost', null)
        );
        $this->assertSame(
            ['status' => 503, 'retry_after' => '9', 'cache_control' => 'no-store'],
            big_run_busy_headers('probe_full', 9)
        );
        $this->assertSame(
            ['status' => 503, 'retry_after' => '2', 'cache_control' => 'no-store'],
            big_run_busy_headers('tokens', null)
        );
    }

    public function testBusyPageMessageForLostLease(): void {
        $this->assertSame(
            'Citation Bot stopped this bulk run because its shared lease was lost. ' .
            'Please retry; stopping prevents stale work from exceeding the concurrency limit.',
            big_run_busy_page_message('lease_lost', null, null)
        );
    }

    public function testBusyPageMessageDefaultsWhenCountsAreUnknown(): void {
        $this->assertSame(
            'Citation Bot is currently at capacity with other bulk work (0 in progress). ' .
            'Please try again in about ' . big_run_humanize_wait(big_run_pool_retry_seconds()) . '.',
            big_run_busy_page_message('total_full', null, null)
        );
    }

    public function testBusyPageMessageForTokenQuotaWithUnknownWaitSaysOneSecond(): void {
        $this->assertSame(
            'Citation Bot\'s big-run quota is currently exhausted. Please try again in about 1 second.',
            big_run_busy_page_message('tokens', null, null)
        );
    }

    public function testBusyPageMessageForUnrecognizedReasonUsesCapacityWording(): void {
        $this->assertSame(
            'Citation Bot is currently at capacity with other bulk work (4 in progress). ' .
            'Please try again in about 2 minutes.',
            big_run_busy_page_message('probe_full', 4, 65)
        );
    }

    // ---- edit-summary and run-type helpers ----------------------------------

    public function testCategoryEditSummaryExactText(): void {
        $this->assertSame(
            '| Suggested by U | [[Category:Cat]] | #UCB_Category ',
            category_edit_summary_end('U', 'Cat')
        );
        $this->assertSame(
            '| Suggested by U | [[Category:Cat]] | #UCB_template ',
            category_edit_summary_end('U', 'Cat', false, false, 'template')
        );
        $this->assertSame(
            '| Suggested by U | [[Category:Cat]] | #UCB_automated_tools | Whitelisted category ',
            category_edit_summary_end('U', 'Cat', true, false, 'automated_tools')
        );
        $this->assertSame(
            '| Suggested by U | [[Category:Cat]] | #UCB_Category | Developer - max category limit override enabled ',
            category_edit_summary_end('U', 'Cat', true, true)
        );
    }

    public function testProcessPageEditSummaryExactText(): void {
        $this->assertSame('| Suggested by U | #UCB_webform ', process_page_edit_summary_end('U', true, null));
        $this->assertSame('| Suggested by U | #UCB_webform ', process_page_edit_summary_end('U', true, ''));
        $this->assertSame('| #UCB_CommandLine ', process_page_edit_summary_end('U', false, null));
        $this->assertSame('| #UCB_CommandLine ', process_page_edit_summary_end('U', false, ''));
        $this->assertSame('| #UCB_toolbar ', process_page_edit_summary_end('U', false, 'toolbar'));
        $this->assertSame('| Suggested by U | #UCB_Other ', process_page_edit_summary_end('U', true, 'nonsense'));
        $this->assertSame('| #UCB_Other ', process_page_edit_summary_end('', false, 'nonsense'));
    }

    public function testProcessPageEditSummaryTagsAreCaseSensitive(): void {
        foreach (['TOOLBAR', 'Toolbar', 'Webform', 'TEMPLATE', 'Automated_Tools'] as $edit) {
            $this->assertSame(
                '| #UCB_Other ',
                process_page_edit_summary_end('U', false, $edit),
                $edit
            );
        }
    }

    public function testBigRunTypeFromEditIsCaseSensitive(): void {
        $this->assertSame('other', big_run_type_from_edit('Toolbar'));
        $this->assertSame('other', big_run_type_from_edit('WEBFORM'));
        $this->assertSame('other', big_run_type_from_edit(' webform'));
    }

    public function testBigRunGateDecisionThresholdBoundaries(): void {
        $this->assertFalse(big_run_gate_decision(0, 'webform', 'U'));
        $this->assertFalse(big_run_gate_decision(1, 'webform', 'U'));
        $this->assertFalse(big_run_gate_decision(BIG_RUN_PAGE_THRESHOLD, 'webform', 'U'));
        $this->assertTrue(big_run_gate_decision(BIG_RUN_PAGE_THRESHOLD + 1, 'webform', 'U'));
        $this->assertFalse(big_run_gate_decision(1000000, 'testing', 'U'));
    }

    public function testBigRunExemptionsAreCaseSensitiveAndOnlyTestingIsConcurrencyExempt(): void {
        $this->assertTrue(big_run_is_exempt('testing', 'U'));
        $this->assertFalse(big_run_is_exempt('category', 'AManWithNoPlan'));
        $this->assertFalse(big_run_is_exempt('Testing', 'U'));
        $this->assertTrue(big_run_is_token_exempt('other', 'Redalert2fan'));
        $this->assertFalse(big_run_is_token_exempt('other', 'redalert2fan'));
        $this->assertFalse(big_run_is_token_exempt('Testing', 'U'));
        $this->assertFalse(linked_pages_should_override_limit('amanwithnoplan'));
        $this->assertFalse(linked_pages_should_override_limit(' AManWithNoPlan'));
    }

    // ---- admission buffer ----------------------------------------------------

    public function testAdmissionBufferStartDoesNothingWithoutHtmlOutput(): void {
        $this->assertFalse(HTML_OUTPUT);
        $level = ob_get_level();
        unset($GLOBALS[self::BUFFER_KEY]);

        bot_admission_buffer_start();

        $this->assertSame($level, ob_get_level());
        $this->assertArrayNotHasKey(self::BUFFER_KEY, $GLOBALS);
    }

    public function testAdmissionBufferDiscardWithoutRecordedLevelKeepsOutput(): void {
        unset($GLOBALS[self::BUFFER_KEY]);
        $base = ob_get_level();
        ob_start();
        try {
            echo 'keep me';
            bot_admission_buffer_discard();
            $this->assertSame('keep me', (string) ob_get_contents());
        } finally {
            while (ob_get_level() > $base) {
                ob_end_clean();
            }
        }
    }

    public function testAdmissionBufferDiscardOnlyActsAtTheRecordedLevel(): void {
        $base = ob_get_level();
        ob_start();
        try {
            $level = ob_get_level();
            echo 'first';

            $GLOBALS[self::BUFFER_KEY] = $level + 1; // A different, deeper level
            bot_admission_buffer_discard();
            $this->assertSame('first', (string) ob_get_contents());

            $GLOBALS[self::BUFFER_KEY] = 'not an int';
            bot_admission_buffer_discard();
            $this->assertSame('first', (string) ob_get_contents());

            $GLOBALS[self::BUFFER_KEY] = $level;
            bot_admission_buffer_discard();
            $this->assertSame('', (string) ob_get_contents());
            echo 'second';
            $this->assertSame('second', (string) ob_get_contents());
        } finally {
            while (ob_get_level() > $base) {
                ob_end_clean();
            }
        }
    }

    public function testAdmissionBufferFlushEmitsBufferedOutputAndForgetsTheLevel(): void {
        $base = ob_get_level();
        ob_start(); // Stands in for the web server's own buffer
        try {
            ob_start(); // The admission buffer
            $GLOBALS[self::BUFFER_KEY] = ob_get_level();
            echo 'buffered';

            bot_admission_buffer_flush();

            $this->assertArrayNotHasKey(self::BUFFER_KEY, $GLOBALS);
            $this->assertSame($base + 1, ob_get_level());
            $this->assertSame('buffered', (string) ob_get_contents());
        } finally {
            while (ob_get_level() > $base) {
                ob_end_clean();
            }
        }
    }

    public function testAdmissionBufferFlushWithStaleLevelOnlyForgetsIt(): void {
        $base = ob_get_level();
        ob_start();
        try {
            echo 'still buffered';
            $GLOBALS[self::BUFFER_KEY] = ob_get_level() + 5;

            bot_admission_buffer_flush();

            $this->assertArrayNotHasKey(self::BUFFER_KEY, $GLOBALS);
            $this->assertSame($base + 1, ob_get_level());
            $this->assertSame('still buffered', (string) ob_get_contents());
        } finally {
            while (ob_get_level() > $base) {
                ob_end_clean();
            }
        }
    }

    public function testAdmissionBufferFlushWithoutRecordedLevelIsHarmless(): void {
        unset($GLOBALS[self::BUFFER_KEY]);
        $level = ob_get_level();

        bot_admission_buffer_flush();

        $this->assertSame($level, ob_get_level());
        $this->assertArrayNotHasKey(self::BUFFER_KEY, $GLOBALS);
    }

    // ---- gating helpers when HTML output is off ------------------------------

    public function testGateFunctionsAreNoOpsOutsideHtmlOutput(): void {
        $this->assertFalse(HTML_OUTPUT);
        $this->assertNull(gate_big_run(500, 'category', 'SomeUser'));
        $this->assertNull(gate_big_run(500, 'category', 'SomeUser', null, null, 'some-entry'));
        $this->assertNull(gate_big_run_probe('category', 'SomeUser'));
        $this->assertNull(gate_big_run_discovery('category', 'SomeUser'));
        $this->assertNull(gate_big_run_probe_to_discovery(null));
    }

    #[DoesNotPerformAssertions]
    public function testReleasingANullLeaseIsHarmless(): void {
        release_big_run_lease(null);
        release_big_run_lease(null, sys_get_temp_dir());
    }

    // ---- edit_a_list_of_pages early exits ------------------------------------

    public function testEditListWithNoRunnablePagesStopsBeforeTouchingAnyPage(): void {
        ob_start();
        try {
            edit_a_list_of_pages([], new WikipediaBot(), 'Fixture');
            edit_a_list_of_pages(['', '   ', "\xFF"], new WikipediaBot(), 'Fixture');
            $body = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        // Only the two footers' newlines: the warnings are suppressed in CI and no page ran.
        $this->assertSame("\n\n", $body);
    }

    public function testEditListLargerThanTheLimitIsRejectedWithoutProcessing(): void {
        $limit = defined('MAX_PAGES_OVERRIDE') ? (int) MAX_PAGES_OVERRIDE : (int) MAX_PAGES;
        if ($limit > 5000) {
            $this->markTestSkipped('Page limit is too large to exceed cheaply in a unit test');
        }
        $titles = [];
        for ($i = 0; $i <= $limit; ++$i) {
            $titles[] = 'Over the limit ' . (string) $i;
        }
        ob_start();
        try {
            edit_a_list_of_pages($titles, new WikipediaBot(), 'Fixture');
            $body = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertSame("\n", $body);
    }
}
