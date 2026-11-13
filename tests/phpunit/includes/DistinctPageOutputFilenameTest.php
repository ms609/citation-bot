<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class DistinctPageOutputFilenameTest extends testBaseClass {
    public function testSavingDifferentTitlesDoesNotReuseSanitizedFilename(): void {
        $slash = page_output_filename('日本語/研究');
        $space = page_output_filename('日本語 研究');
        $this->assertNotSame($slash, $space);
        $this->assertNotSame(mb_strtolower(page_output_filename('Résumé')), mb_strtolower(page_output_filename('résumé')));
        $this->assertSame($slash, page_output_filename('日本語/研究'));
        $filename = page_output_filename(str_repeat('𐐑', 100) . '\\');
        $this->assertTrue(mb_check_encoding($filename, 'UTF-8'));
        $this->assertLessThanOrEqual(255, mb_strlen($filename, '8bit'));
        $this->assertStringEndsWith('.md', $filename);
    }
}
