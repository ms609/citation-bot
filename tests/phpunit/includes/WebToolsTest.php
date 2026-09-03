<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class WebToolsTest extends testBaseClass {
    public function testPageExceptionBoundaryReturnsOperationResult(): void {
        $this->assertTrue(
            run_page_with_exception_boundary('test page', static fn (): bool => true)
        );
        $this->assertFalse(
            run_page_with_exception_boundary('test page', static fn (): bool => false)
        );
    }

    public function testPageExceptionBoundaryPreservesNullResult(): void {
        $this->assertNull(
            run_page_with_exception_boundary('test page', static fn (): ?bool => null)
        );
    }

    public function testPageExceptionBoundaryCatchesValueError(): void {
        $result = run_page_with_exception_boundary(
            'bad page',
            static function (): bool {
                throw new ValueError('malformed external data');
            }
        );

        $this->assertNull($result);
    }

    public function testNegativeRetryCountIsClampedToSingleAttempt(): void {
        $calls = 0;
        $result = run_write_with_retries(
            static function () use (&$calls): bool {
                ++$calls;
                return false;
            },
            -10
        );

        $this->assertFalse($result);
        $this->assertSame(1, $calls);
    }

    public function testWriteRetriesCanSucceedOnFinalRetry(): void {
        $calls = 0;
        $result = run_write_with_retries(
            static function () use (&$calls): bool {
                ++$calls;
                return $calls === 3;
            },
            2
        );

        $this->assertTrue($result);
        $this->assertSame(3, $calls);
    }

    public function testWriteRetriesStopAfterConfiguredRetries(): void {
        $calls = 0;
        $result = run_write_with_retries(
            static function () use (&$calls): bool {
                ++$calls;
                return false;
            },
            2
        );

        $this->assertFalse($result);
        $this->assertSame(3, $calls);
    }

    public function testWriteRetriesStopImmediatelyOnSuccess(): void {
        $calls = 0;
        $result = run_write_with_retries(
            static function () use (&$calls): bool {
                ++$calls;
                return true;
            },
            2
        );

        $this->assertTrue($result);
        $this->assertSame(1, $calls);
    }

    public function testCategoryScopeWinsOverWebformCaller(): void {
        $summary = category_edit_summary_end('SomeUser', 'CS1 errors: dates', false, false);
        $this->assertStringContainsString('| Suggested by SomeUser', $summary);
        $this->assertStringContainsString('[[Category:CS1 errors: dates]]', $summary);
        $this->assertStringContainsString('#UCB_Category', $summary);
        $this->assertStringNotContainsString('| #UCB_webform ', $summary);
        $this->assertSame('#UCB_Category', statistics_ucb_from_comment($summary));
    }

    public function testCategoryOverrideSuffixes(): void {
        $plain = category_edit_summary_end('U', 'C', false, false);
        $this->assertStringNotContainsString('Whitelisted', $plain);
        $this->assertStringNotContainsString('Developer', $plain);
        $dev_without_override = category_edit_summary_end('U', 'C', false, true);
        $this->assertStringNotContainsString('Whitelisted', $dev_without_override);
        $this->assertStringNotContainsString('Developer', $dev_without_override);

        $white = category_edit_summary_end('U', 'C', true, false);
        $this->assertStringContainsString('#UCB_Category', $white);
        $this->assertStringContainsString('Whitelisted category', $white);
        $this->assertSame('#UCB_Category', statistics_ucb_from_comment($white));

        $dev = category_edit_summary_end('U', 'C', true, true);
        $this->assertStringContainsString('Developer - max category limit override enabled', $dev);
        $this->assertSame('#UCB_Category', statistics_ucb_from_comment($dev));
    }

    public function testDeprecatedPersonalTagsFallThroughToOther(): void {
        foreach (['Headbomb', 'Smith609', 'arXiv'] as $edit) {
            $summary = process_page_edit_summary_end('SomeUser', true, $edit);
            $this->assertStringContainsString('#UCB_Other', $summary);
            $this->assertStringNotContainsString('#UCB_Headbomb', $summary);
            $this->assertStringNotContainsString('#UCB_Smith609', $summary);
            $this->assertStringNotContainsString('#UCB_arXiv', $summary);
            $this->assertSame('#UCB_Other', statistics_ucb_from_comment($summary));
        }
    }

    public function testProcessPageKnownCallersUnchanged(): void {
        $this->assertStringContainsString(
            '#UCB_toolbar',
            process_page_edit_summary_end('SomeUser', true, 'toolbar')
        );
        $this->assertStringContainsString(
            '#UCB_webform',
            process_page_edit_summary_end('SomeUser', true, 'webform')
        );
        $this->assertStringContainsString(
            '#UCB_automated_tools',
            process_page_edit_summary_end('SomeUser', true, 'automated_tools')
        );
        $this->assertStringContainsString(
            '#UCB_template',
            process_page_edit_summary_end('SomeUser', true, 'template')
        );
        $this->assertStringContainsString(
            '#UCB_Other',
            process_page_edit_summary_end('SomeUser', true, 'something-made-up')
        );
    }

    public function testProcessPageEmptyEditDefaults(): void {
        $this->assertStringContainsString(
            '#UCB_webform',
            process_page_edit_summary_end('SomeUser', true, null)
        );
        $this->assertStringContainsString(
            '#UCB_webform',
            process_page_edit_summary_end('SomeUser', true, '')
        );
        $this->assertStringContainsString(
            '#UCB_CommandLine',
            process_page_edit_summary_end('', false, null)
        );
    }

    public function testProcessPageSuggestedByPrefixAndCliTagInteraction(): void {
        $html = process_page_edit_summary_end('SomeUser', true, 'toolbar');
        $this->assertStringContainsString('| Suggested by SomeUser', $html);
        $this->assertStringContainsString('#UCB_toolbar', $html);

        $cli = process_page_edit_summary_end('SomeUser', false, 'toolbar');
        $this->assertStringContainsString('#UCB_toolbar', $cli);
        $this->assertStringNotContainsString('Suggested by', $cli);
    }
}
