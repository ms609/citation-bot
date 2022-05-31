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
    $this->assertEquals('1778027', $template->get2('osti'));
    $this->assertNull($template->get2('url'));
  }

}
