<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class WriteOutcomeTest extends testBaseClass {
    public function testSuccessfulWriteCountsAsChanged(): void {
        $this->assertTrue(page_result_from_write(true, false));
    }

    public function testConflictSkipCountsAsUnchanged(): void {
        $this->assertFalse(page_result_from_write(true, true));
    }

    public function testExhaustedWriteRetriesCountAsFailure(): void {
        $this->assertNull(page_result_from_write(false, false));
    }

    public function testFailureTakesPrecedenceOverSkipFlag(): void {
        $this->assertNull(page_result_from_write(false, true));
    }
}
