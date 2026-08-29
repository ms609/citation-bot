<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class PageRetryBoundaryInteractionTest extends testBaseClass {
    public function testPageBoundaryContainsThrowableFromRetrySequence(): void {
        $calls = 0;

        $result = run_page_with_exception_boundary(
            'Example',
            static function () use (&$calls): bool {
                return run_write_with_retries(
                    static function () use (&$calls): bool {
                        ++$calls;
                        if ($calls === 1) {
                            return false;
                        }
                        throw new ValueError('write-layer failure');
                    },
                    2
                );
            }
        );

        $this->assertNull($result);
        $this->assertSame(2, $calls);
    }

    public function testPageBoundaryPreservesSuccessfulRetryResult(): void {
        $calls = 0;

        $result = run_page_with_exception_boundary(
            'Example',
            static function () use (&$calls): bool {
                return run_write_with_retries(
                    static function () use (&$calls): bool {
                        ++$calls;
                        return $calls === 2;
                    },
                    2
                );
            }
        );

        $this->assertTrue($result);
        $this->assertSame(2, $calls);
    }
}
