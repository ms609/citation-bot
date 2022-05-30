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
 
  public function testModsArray() : void {
    $text = '{{cite journal | CITATION_BOT_PLACEHOLDER_BARE_URL = XYX }}';
    $template = $this->make_citation($text);
    $template->add('title', 'Thus');
    $this->assertNotNull($template->get2('CITATION_BOT_PLACEHOLDER_BARE_URL'));
    $array = $template->modifications();
    var_export($array);
    $this->assertNull($template->get2('CITATION_BOT_PLACEHOLDER_BARE_URL'));
    $this->assertTrue(FALSE);
  }


}
