<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
      $text = '{{Cite web |title= | year=2003 | title= Ten}}';
      $expanded = $this->process_citation($text);
      $text = "{{citation|year=|title=X|year=2000}}"; // Something between the two but with blank first is different code path
      $expanded = $this->process_citation($text);
  }

}
