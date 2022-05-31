<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $t = $this->process_citation('{{cite journal|doi=10.11468/seikatsueisei1925.16.2_123}}');
  }

}
