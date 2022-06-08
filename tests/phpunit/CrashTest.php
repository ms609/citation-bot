<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
      doi_works('10.1289/ehp.1002409');
      doi_works('10.25300/MISQ/2013/37.2.05');
  }

 
  public function testBadPage() : void {  // Use this when debugging pages that crash the bot
      $this->assertTrue(FALSE);
  }
}
