<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class ProcessPageEditSummaryTest extends testBaseClass {
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

    public function testKnownCallersUnchanged(): void {
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

    public function testEmptyEditDefaults(): void {
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

    public function testSuggestedByPrefixAndCliTagInteraction(): void {
        $html = process_page_edit_summary_end('SomeUser', true, 'toolbar');
        $this->assertStringContainsString('| Suggested by SomeUser', $html);
        $this->assertStringContainsString('#UCB_toolbar', $html);

        $cli = process_page_edit_summary_end('SomeUser', false, 'toolbar');
        $this->assertStringContainsString('#UCB_toolbar', $cli);
        $this->assertStringNotContainsString('Suggested by', $cli);
    }
}
