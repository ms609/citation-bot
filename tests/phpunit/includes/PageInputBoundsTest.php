<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class PageInputBoundsTest extends testBaseClass {
    public function testPageTitleLimitUsesBytesNotUnicodeCharacters(): void {
        $ascii255 = str_repeat('a', 255);
        $multibyteTooLarge = str_repeat('é', 128); // 256 bytes in UTF-8.

        $this->assertSame(
            [$ascii255],
            filter_runnable_page_titles([$ascii255, $multibyteTooLarge])
        );
    }

    public function testPageTitleFilterSkipsInvalidAndDuplicateEntries(): void {
        ob_start();
        try {
            $filtered = filter_runnable_page_titles([
                'Second',
                '',
                " \t",
                null,
                7,
                'First',
                'Second',
            ]);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(['Second', 'First'], $filtered);
    }

    public function testExactly255Utf8BytesIsAccepted(): void {
        $title = str_repeat('é', 127) . 'a'; // 255 bytes, 128 characters.
        $this->assertSame(255, mb_strlen($title, '8bit'));
        $this->assertSame([$title], filter_runnable_page_titles([$title]));
    }

    public function testPageTitleFilterPreservesNonBlankWhitespaceAndCase(): void {
        $this->assertSame(
            [' Example ', 'Example', 'example'],
            filter_runnable_page_titles([' Example ', 'Example', 'example', 'Example'])
        );
    }

    public function testPageTitleFilterSkipsInvalidAndDuplicateEntries(): void {
        ob_start();
        try {
            $filtered = filter_runnable_page_titles([
                'Second',
                '',
                " \t",
                null,
                7,
                'First',
                'Second',
            ]);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(['Second', 'First'], $filtered);
    }

    public function testBatchInputLimitAccountsForTitlesAndSeparators(): void {
        $this->assertTrue(page_batch_input_within_limit(str_repeat('a', 511), 2));
        $this->assertTrue(page_batch_input_within_limit(str_repeat('a', 512), 2));
        $this->assertFalse(page_batch_input_within_limit(str_repeat('a', 513), 2));
        $this->assertFalse(page_batch_input_within_limit('a', 0));
    }

    public function testBatchInputLimitHandlesEmptyInputAndNegativeLimit(): void {
        $this->assertTrue(page_batch_input_within_limit('', 1));
        $this->assertFalse(page_batch_input_within_limit('', -1));
    }

    public function testBatchInputLimitUsesConfiguredDefault(): void {
        $this->assertTrue(page_batch_input_within_limit('Example'));
    }

    public function testBatchInputLimitUsesConfiguredDefault(): void {
        $this->assertTrue(page_batch_input_within_limit('Example'));
    }
}
