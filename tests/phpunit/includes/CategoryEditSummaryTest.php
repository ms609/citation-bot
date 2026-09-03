<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class CategoryEditSummaryTest extends testBaseClass {
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
}
