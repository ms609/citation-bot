<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class WebToolsTest extends testBaseClass {
    public function testPageExceptionBoundaryReturnsOperationResult(): void {
        $this->assertTrue(
            run_page_with_exception_boundary('test page', static fn (): bool => true)
        );
        $this->assertFalse(
            run_page_with_exception_boundary('test page', static fn (): bool => false)
        );
    }

    public function testPageExceptionBoundaryCatchesValueError(): void {
        $result = run_page_with_exception_boundary(
            'bad page',
            static function (): bool {
                throw new ValueError('malformed external data');
            }
        );

        $this->assertNull($result);
    }
}
