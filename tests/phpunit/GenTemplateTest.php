<?php
declare(strict_types=1);

/*
 * Tests for generate_template.php
 */

require_once __DIR__ . '/../testBaseClass.php';

final class GenTemplateTest extends testBaseClass {

    protected function setUp(): void {
        if (BAD_PAGE_API !== '') {
            $this->markTestSkipped();
        }
        $this->getTestResultObject()->setTimeoutForSmallTests(60);
        $this->getTestResultObject()->setTimeoutForMediumTests(120);
        $this->getTestResultObject()->setTimeoutForLargeTests(180);
    }

    public function testFillCache(): void {
        $this->fill_cache();
        $this->assertTrue(true);
    }

    public function testGenTemplate(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        // Run API
        ob_start();
        ob_start();
        $_GET['jstor'] = '373737';
        require(__DIR__ . '/../../generate_template.php');
        unset($_GET);
        $template_text = '';
        while (ob_get_level()) {
            $template_text = ob_get_contents() . $template_text;
            ob_end_clean();
        };
        ob_start(); // PHPUnit turns on a level of buffering itself -- Give it back to avoid "Risky Test"
        // Output checking time
        $this->assertTrue((bool) strpos($template_text, '{{cite journal | jstor=373737 |'));
        $this->assertTrue((bool) strpos($template_text, 'DOCTYPE html'));
    }
}
