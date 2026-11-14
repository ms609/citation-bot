<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class ArchiveScanLimitsTest extends testBaseClass {
    public function testEncodingScanIncludesMetaAtBoundaryButNotAfterIt(): void {
        $this->assertSame(256 * 1024, ARCHIVE_ENCODING_SCAN_MAX_BYTES);
        $tag = '<meta charset="EUC-KR">';
        $body = str_repeat('x', ARCHIVE_ENCODING_SCAN_MAX_BYTES - mb_strlen($tag, '8bit')) .
            $tag . '<meta charset="BIG5">';

        $this->assertSame(['EUC-KR'], archive_candidate_encodings($body));
    }

    public function testEncodingScanPreservesHttpHeadersAndArchiveGuesses(): void {
        $headers = "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1252\r\n" .
            "X-Archive-Guessed-Charset: Shift_JIS\r\n\r\n";
        $body = str_repeat('x', ARCHIVE_ENCODING_SCAN_MAX_BYTES) .
            '<meta charset="BIG5">';

        $this->assertSame(
            ['windows-1252', 'Shift_JIS'],
            archive_candidate_encodings($headers . $body)
        );
        $this->assertSame(
            ['windows-1252'],
            archive_candidate_encodings($headers . $body, false)
        );
    }

    public function testEncodingScanStillHonorsBomAfterHttpHeaders(): void {
        $headers = "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1252\r\n\r\n";
        $body = "\xFF\xFE" . str_repeat('x', ARCHIVE_ENCODING_SCAN_MAX_BYTES) .
            '<meta charset="BIG5">';

        $this->assertSame(['UTF-16LE'], archive_candidate_encodings($headers . $body));
    }

    public function testTitleScanStopsAfterOneMebibyte(): void {
        $this->assertSame(1024 * 1024, ARCHIVE_TITLE_SCAN_MAX_BYTES);
        $response = '<html><head>' . str_repeat('x', ARCHIVE_TITLE_SCAN_MAX_BYTES) .
            '<title>Too late</title></head>';
        $window = archive_title_scan_window($response);

        $this->assertSame(ARCHIVE_TITLE_SCAN_MAX_BYTES, mb_strlen($window, '8bit'));
        $this->assertStringNotContainsString('Too late', $window);
    }

    public function testTitleScanStillStopsAfterOpeningBodyTag(): void {
        $response = '<html><head><title>Keep me</title></head>' .
            '<body class="main">' . str_repeat('x', ARCHIVE_TITLE_SCAN_MAX_BYTES);
        $window = archive_title_scan_window($response);

        $this->assertSame(
            '<html><head><title>Keep me</title></head><body class="main">',
            $window
        );
    }
}
