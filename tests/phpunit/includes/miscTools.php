<?php
declare(strict_types=1);

require_once __DIR__ . '../../testBaseClass.php';

final class miscTools extends testBaseClass {
    public function testcheck_memory_usage(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        check_memory_usage('testcheck_memory_usage');
        $this->assertTrue(true);
    }
}
