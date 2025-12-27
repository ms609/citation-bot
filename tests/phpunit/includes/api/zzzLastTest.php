<?php
declare(strict_types=1);

/*
 * Final Test - thus burried deep in the test suite. Does not need to be last, but quite a ways in.
 */

require_once __DIR__ . '/../../../testBaseClass.php';

final class zzzLastTest extends testBaseClass {

    public function testFlushCacheAtEnd(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        HandleCache::free_memory();
        AdsAbsControl::free_memory();
        $this->assertFaker();
    }
}
