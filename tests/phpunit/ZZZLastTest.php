<?php
declare(strict_types=1);

/*
 * Final Test
 */

require_once __DIR__ . '/../testBaseClass.php';

final class ZZZLastTest extends testBaseClass {

    public function testFlushCacheAtEnd(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        HandleCache::free_memory();
        AdsAbsControl::free_memory();
        $this->assertTrue(true);
    }
}
