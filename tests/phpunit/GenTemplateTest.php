<?php
/*
 * Tests for generate_template.php
 */
require_once(__DIR__ . '/../testBaseClass.php');
 
final class GenTemplateTest extends testBaseClass {
  public function testGenTemplate() {
      global $FLUSHING_OKAY;
      global $SLOW_MODE;
      // Run API
      ob_start();
      ob_start();
      $_GET['jstor'] = '373737';
      require_once(__DIR__ . '/../../generate_template.php');
      $template_text = ob_get_contents();
      ob_end_clean();
      $template_text = ob_get_contents() . $template_text;
      ob_end_clean();
      // Reset everything
      $FLUSHING_OKAY = TRUE;
      $SLOW_MODE = TRUE;
      while (ob_get_level()) { ob_end_flush(); };
      ob_start(); // PHPUnit turns on a level of buffering itself -- Give it back to avoid "Risky Test"
      // Output checking time
      $this->assertTrue((bool) strpos($template_text, 'Cite journal'));
  }
}
