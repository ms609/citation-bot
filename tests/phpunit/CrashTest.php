<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $text = '{{citation|arxiv=2202.10024|title=TESS discovery of a sub-Neptune orbiting a mid-M dwarf TOI-2136}}';
    $template = $this->process_citation($text);
    $this->assertSame('TESS discovery of a sub-Neptune orbiting a mid-M dwarf TOI-2136', $template->get2('title'));
    $this->assertSame('10.1093/mnras/stac1448', $template->get2('doi'));
  }


  public function testblockCI() : void {
    $this->assertTrue(FALSE);
  }
}
