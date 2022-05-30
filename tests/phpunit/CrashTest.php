<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $this->assertTrue(FALSE);
  }
 
  public function testThis() : void {
    $text = '{{new cambridge medieval history|ed10=That Guy}}';
    $template = $this->prepare_citation($text);
    $ret = $template->modifications();
    $expect = array ('modifications' => array ( ), 'additions' => array ( ), 'deletions' => array ( ), 'changeonly' => array ( ), 'dashes' => false, 'names' => false, );
    $this->assertTrue($ret == $expect);
  }
    

}
