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

    public function testBatchInputLimitAccountsForTitlesAndSeparators(): void {
        $this->assertTrue(page_batch_input_within_limit(str_repeat('a', 511), 2));
        $this->assertTrue(page_batch_input_within_limit(str_repeat('a', 512), 2));
        $this->assertFalse(page_batch_input_within_limit(str_repeat('a', 513), 2));
        $this->assertFalse(page_batch_input_within_limit('a', 0));
    }
}
