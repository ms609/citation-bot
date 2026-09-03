<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class EncodingBoundaryTest extends testBaseClass {
    public function testMbStrrevHonorsExplicitEncoding(): void {
        $this->assertSame('Aé', mb_strrev('éA', 'UTF-8'));
        $this->assertSame("A\xA9\xC3", mb_strrev('éA', '8bit'));
    }

    public function testMbSubstrReplaceAcceptsByteOffsets(): void {
        $subject = 'é ITS marker';
        $matched = preg_match('~ITS~', $subject, $match, PREG_OFFSET_CAPTURE);
        $this->assertSame(1, $matched);
        $this->assertSame(
            'é its marker',
            mb_substr_replace($subject, 'its', $match[0][1], 3, '8bit')
        );
    }

    public function testTitleCapitalizationPreservesCaseAfterMultibytePrefix(): void {
        $this->assertSame(
            'Café ITS Is Faster',
            title_capitalization('CAFÉ ITS IS FASTER', true)
        );
        $this->assertSame(
            'Café DOS Is Faster',
            title_capitalization('CAFÉ DOS IS FASTER', true)
        );
    }

    public function testArchiveTitleCandidateDecodesBeforeUnicodeOperations(): void {
        $this->assertSame(
            '“Café”',
            archive_decode_title_candidate(
                " \x93Caf\xE9\x94 ",
                ['windows-1252'],
                'https://web.archive.org/'
            )
        );

        $shift_jis = mb_convert_encoding(' 日本語 ', 'SJIS-win', 'UTF-8');
        $this->assertSame(
            '日本語',
            archive_decode_title_candidate(
                $shift_jis,
                ['Shift_JIS'],
                'https://web.archive.org/'
            )
        );
    }

    public function testArchiveConnectParsingToleratesNonUtf8ReasonBytes(): void {
        $response =
            "HTTP/1.1 200 \xFFConnection established\r\n\r\n" .
            "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n" .
            '<html></html>';

        $parts = archive_http_response_parts($response);
        $this->assertStringStartsWith('HTTP/1.1 200 OK', $parts['headers']);
        $this->assertSame('<html></html>', $parts['body']);
    }

    public function testRawBoundaryCallSitesUseEightBitMode(): void {
        $root = __DIR__ . '/../../../src/';
        $cases = [
            'includes/api/APIS2.php' => [
                "mb_stripos(\$response, 'Too Many Requests', 0, '8bit')",
                "mb_stripos(\$response, 'Paper not found', 0, '8bit')",
            ],
            'includes/api/APIBibCode.php' => [
                "mb_stripos(\$body, 'down for maintenance', 0, '8bit')",
            ],
            'includes/api/APIzotero.php' => [
                "private static function trim_raw_response",
                "private static function raw_response_excerpt",
                "mb_substr(\$response, 0, \$limit, '8bit')",
            ],
            'process_page.php' => [
                "mb_check_encoding(\$pages, 'UTF-8')",
                "mb_strpos(\$pages, '|', 0, '8bit')",
            ],
            'generate_template.php' => [
                "mb_check_encoding(\$value, 'UTF-8')",
                "mb_strlen(\$value) < 3",
                "mb_strlen(\$value) > 100",
            ],
            'includes/RequestRateLimit.php' => [
                "mb_rtrim(\$base_directory, \"/\\\\\", '8bit')",
            ],
        ];

        foreach ($cases as $relative => $needles) {
            $source = file_get_contents($root . $relative);
            $this->assertIsString($source);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $relative);
            }
        }
    }

    public function testConvertToUtf8DoesNotReinterpretValidUtf8(): void {
        $value = 'Café © Registered ® 日本語';
        $this->assertSame($value, convert_to_utf8($value));
    }

    public function testArchiveCharsetParsingUsesAsciiWhitespaceOnly(): void {
        $this->assertSame(
            'windows-1252',
            archive_charset_parameter("text/html; charset=  windows-1252 \t")
        );
        $this->assertSame(
            "windows-1252\u{00A0}",
            archive_charset_parameter("text/html; charset=windows-1252\xC2\xA0")
        );
    }

    public function testArchiveResponsePartsHandlesMalformedBytesBeforeSeparators(): void {
        $response =
            "HTTP/1.1 100 Continue\r\nX-Debug: \xFF\r\n\r\n" .
            "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\nbody\xFF";

        $parts = archive_http_response_parts($response);
        $this->assertStringStartsWith('HTTP/1.1 200 OK', $parts['headers']);
        $this->assertSame("body\xFF", $parts['body']);
    }

    public function testByteModeReplacementWorksAtMultipleMultibyteOffsets(): void {
        foreach (['é', '日本', '😀'] as $prefix) {
            $subject = $prefix . ' ITS suffix';
            preg_match('~ITS~', $subject, $match, PREG_OFFSET_CAPTURE);
            $this->assertSame(
                $prefix . ' its suffix',
                mb_substr_replace($subject, 'its', $match[0][1], 3, '8bit')
            );
        }
    }

    public function testWindows1252HeuristicUsesRawByteLiterals(): void {
        $source = file_get_contents(__DIR__ . '/../../../src/includes/api/APIarchives.php');
        $this->assertIsString($source);
        foreach (["\\xAE", "\\xA9", "\\x81", "\\x94", "\\x84"] as $literal) {
            $this->assertStringContainsString('"' . $literal . '"', $source);
        }
    }

    public function testPublicConfigEnforcementRemainsNoOpOnCli(): void {
        if (PHP_SAPI !== 'cli') {
            $this->markTestSkipped('CLI-specific behavior');
        }

        $before = mb_internal_encoding();
        try {
            mb_internal_encoding('ISO-8859-1');
            enforce_public_request_configuration(null);
            $this->assertSame('ISO-8859-1', mb_internal_encoding());
        } finally {
            mb_internal_encoding($before);
        }
    }
}
