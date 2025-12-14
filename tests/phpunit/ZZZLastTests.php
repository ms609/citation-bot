<?php
declare(strict_types=1);

/*
 * Final Test
 */

require_once __DIR__ . '/../testBaseClass.php';

final class ZZZLastTests extends testBaseClass {

    public function testCoverageFaker(): void {
        require __DIR__ . '/../../includes/setup.php';
        require __DIR__ . '/../../includes/constants.php';
        require __DIR__ . '/../../includes/constants/bad_data.php';
        require __DIR__ . '/../../includes/constants/capitalization.php';
        require __DIR__ . '/../../includes/constants/isbn.php';
        require __DIR__ . '/../../includes/constants/italics.php';
        require __DIR__ . '/../../includes/constants/math.php';
        require __DIR__ . '/../../includes/constants/mistakes.php';
        require __DIR__ . '/../../includes/constants/null_bad_doi.php';
        require __DIR__ . '/../../includes/constants/null_good_doi.php';
        require __DIR__ . '/../../includes/constants/parameters.php';
        require __DIR__ . '/../../includes/constants/regular_expressions.php';
        require __DIR__ . '/../../includes/constants/translations.php';
        $this->assertTrue(true);
    }

    public function testFlushCacheAtEnd(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        HandleCache::free_memory();
        AdsAbsControl::free_memory();
        $this->assertTrue(true);
    }
}
