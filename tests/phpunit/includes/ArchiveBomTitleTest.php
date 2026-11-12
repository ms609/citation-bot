<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class ArchiveBomTitleTest extends testBaseClass {
    public function testUtf16BomOverridesConflictingHttpCharset(): void {
        $title = '日本語の研究';
        $archive = 'https://web.archive.org/web/20120101000000/https://example.org/a';
        $html = '<!doctype html><html><head><title>' . $title . '</title></head><body>Example</body></html>';
        foreach (['UTF-16LE' => "\xFF\xFE", 'UTF-16BE' => "\xFE\xFF"] as $encoding => $bom) {
            $body = $bom . mb_convert_encoding($html, $encoding, 'UTF-8');
            $response = "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=Windows-1252\r\n\r\n" . $body;
            $this->assertSame([$encoding], archive_candidate_encodings($response));
            $this->assertTrue(uopz_set_return('fetch_archive_page', $response));
            try {
                $template = $this->make_citation('{{cite web|archive-url=' . $archive . '|title=Archived copy}}');
                $templates = [$template];
                expand_templates_from_archives($templates);
                $this->assertSame($title, $template->get('title'));
            } finally {
                $this->assertTrue(uopz_unset_return('fetch_archive_page'));
            }
        }
    }
}
