<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
      $text = '{{cite journal| doi = 10.1289/ehp.1002409  }}';
      $expanded = $this->process_citation($text);
  }

}
