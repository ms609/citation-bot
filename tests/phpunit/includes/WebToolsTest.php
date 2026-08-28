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

    public function testWriteRetriesCanSucceedOnFinalRetry(): void {
        $calls = 0;
        $result = run_write_with_retries(
            static function () use (&$calls): bool {
                ++$calls;
                return $calls === 3;
            },
            2
        );

        $this->assertTrue($result);
        $this->assertSame(3, $calls);
    }

    public function testWriteRetriesStopAfterConfiguredRetries(): void {
        $calls = 0;
        $result = run_write_with_retries(
            static function () use (&$calls): bool {
                ++$calls;
                return false;
            },
            2
        );

        $this->assertFalse($result);
        $this->assertSame(3, $calls);
    }

    public function testWriteRetriesStopImmediatelyOnSuccess(): void {
        $calls = 0;
        $result = run_write_with_retries(
            static function () use (&$calls): bool {
                ++$calls;
                return true;
            },
            2
        );

        $this->assertTrue($result);
        $this->assertSame(1, $calls);
    }
}
