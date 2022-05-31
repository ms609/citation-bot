<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $text = '{{cite journal | doi =  10.1103/PhysRevE.103.012115 }}';  // Give OSTI, thus will not add url
    $template = $this->make_citation($text);
    $template->get_open_access_url();
    $this->assertEquals('1778027', $template->get2('osti'));
    $template->get_open_access_url();
    $this->assertNull($template->get2('url'));
  }

}
