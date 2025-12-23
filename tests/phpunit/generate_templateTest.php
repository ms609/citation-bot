<?php
declare(strict_types=1);

/*
 * Tests for generate_template.php
 */

require_once __DIR__ . '/../testBaseClass.php';

final class generate_templateTest extends testBaseClass {

    public function testGenTemplate(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        // Run API
        ob_start();
        ob_start();
        $_GET['jstor'] = '373737';
        require(__DIR__ . '/../../src/generate_template.php');
        unset($_GET);
        $template_text = '';
        while (ob_get_level()) {
            $template_text = ob_get_contents() . $template_text;
            ob_end_clean();
        };
        ob_start(); // PHPUnit turns on a level of buffering itself -- Give it back to avoid "Risky Test"
        // Output checking time
        $this->assertTrue((bool) mb_strpos($template_text, '{{cite journal |'));
        $this->assertTrue((bool) mb_strpos($template_text, 'jstor=373737'));
        $this->assertTrue((bool) mb_strpos($template_text, 'DOCTYPE html'));
    }
}
