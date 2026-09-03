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

    public function testPageTitleFilterRejectsInvalidUtf8(): void {
        ob_start();
        try {
            $filtered = filter_runnable_page_titles([
                'Valid title',
                "bad\xFFtitle",
                "\xC3",
                '東京大学',
            ]);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(['Valid title', '東京大学'], $filtered);
    }

    public function testPageTitleFilterRejectsMalformedUtf8Classes(): void {
        $invalid = [
            "\xC0\xAF", // overlong slash
            "\xE0\x80\xAF", // overlong three-byte sequence
            "\xED\xA0\x80", // UTF-16 surrogate U+D800
            "\xF4\x90\x80\x80", // above U+10FFFF
        ];

        ob_start();
        try {
            $this->assertSame([], filter_runnable_page_titles($invalid));
        } finally {
            ob_end_clean();
        }
    }

    public function testPageTitleFilterAcceptsFourByteUtf8AtBoundary(): void {
        $title = str_repeat('a', 251) . '😀';

        $this->assertTrue(mb_check_encoding($title, 'UTF-8'));
        $this->assertSame(255, mb_strlen($title, '8bit'));
        $this->assertSame([$title], filter_runnable_page_titles([$title]));
    }

    public function testPageTitleFilterRejectsFourByteUtf8OverBoundary(): void {
        $title = str_repeat('a', 252) . '😀';

        ob_start();
        try {
            $this->assertSame([], filter_runnable_page_titles([$title]));
        } finally {
            ob_end_clean();
        }
    }

    public function testPageTitleFilterAcceptsUtf8AtByteBoundary(): void {
        $title = str_repeat('é', 127) . 'a';

        $this->assertTrue(mb_check_encoding($title, 'UTF-8'));
        $this->assertSame(255, mb_strlen($title, '8bit'));
        $this->assertSame([$title], filter_runnable_page_titles([$title]));
    }

    public function testPageTitleFilterAcceptsDecomposedUnicodeAtExactByteLimit(): void {
        $title = str_repeat('a', 252) . "e\u{0301}";

        $this->assertTrue(mb_check_encoding($title, 'UTF-8'));
        $this->assertSame(255, mb_strlen($title, '8bit'));
        $this->assertSame([$title], filter_runnable_page_titles([$title]));
    }

    public function testPageTitleFilterPreservesNonBlankWhitespaceAndCase(): void {
        $this->assertSame(
            [' Example ', 'Example', 'example'],
            filter_runnable_page_titles([' Example ', 'Example', 'example', 'Example'])
        );
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

    public function testPageTitleFilterPreservesValidDecomposedUnicode(): void {
        $title = "Cafe\u{0301}";

        $this->assertTrue(mb_check_encoding($title, 'UTF-8'));
        $this->assertSame([$title], filter_runnable_page_titles([$title]));
    }

    public function testPageTitleFilterRejectsInvalidUtf8AtByteBoundary(): void {
        $title = str_repeat('a', 254) . "\xC3";

        $this->assertSame(255, mb_strlen($title, '8bit'));
        ob_start();
        try {
            $this->assertSame([], filter_runnable_page_titles([$title]));
        } finally {
            ob_end_clean();
        }
    }
}
