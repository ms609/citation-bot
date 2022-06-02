<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {
 
   public function testblockCI() : void {
    Zotero::create_ch_zotero();
    WikipediaBot::make_ch();
    $this->assertTrue(FALSE);
  }

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    is_doi_works("10.3403/bsiso10294");
    is_doi_works("10.21236/ada614052");
    is_doi_works("10.5479/si.00963801.5-301.449");
  }

}
