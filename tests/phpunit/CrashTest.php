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
    $text = "{{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('Original code gives {{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf| pmc = 2491514}}', $expanded->parse_text());
  }

}
