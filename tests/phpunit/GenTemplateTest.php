<?php
declare(strict_types=1);
/*
 * Tests for generate_template.php
 */
require_once __DIR__ . '/../testBaseClass.php';
 
final class GenTemplateTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_HTTP !== '' || BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }

  public function testGenTemplate() : void {
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
      $this->assertTrue((bool) strpos($template_text, 'Cite journal'));
  }
}
