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
    $text = "{{Cite web | doi=10.25300/MISQ/2015/39.4.9}}";
    $expanded = $this->process_citation($text);
   
    $text = "{{Cite web | doi=10.3403/bsiso10294}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('huh', $expanded->parsed_text());
  }

}
