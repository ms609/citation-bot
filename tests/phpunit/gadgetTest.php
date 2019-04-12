<?php
/*
 * Tests for gadgetapi.php
 */
require_once __DIR__ . '/../testBaseClass.php';
 
final class gadgetTest extends testBaseClass {
  public function testGadget() {
      ob_start();
      $_POST['text'] = '{{cite|pmid=34213}}';
      $_POST['summary'] = 'Something Nice';
      require_once __DIR__ . '/../../gadgetapi.php';
      $json = ob_get_contents();
      ob_end_clean();
      $this->assertNULL($json);
  }
}
