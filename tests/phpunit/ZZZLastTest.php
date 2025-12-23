<?php
declare(strict_types=1);

/*
 * Final Test
 */

require_once __DIR__ . '/../testBaseClass.php';

final class ZZZLastTest extends testBaseClass {

    public function testFlushCacheAtEnd(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        HandleCache::free_memory();
        AdsAbsControl::free_memory();
        $this->assertFaker();
    }
}
