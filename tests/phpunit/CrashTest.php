<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testDashIsEquals() : void {
    $text_in = "{{cite journal|archive=url=https://xy.com }}";
    $template = $this->process_citation($text_in);
    $this->assertSame("https://xy.com", $template->get2('archive-url'));
    $this->assertNull($template->get2('archive'));
   
    $text_in = "{{cite news|archive=url=https://xy.com }}";
    $template = $this->process_citation($text_in);
    $this->assertSame("https://xy.com", $template->get2('archive-url'));
    $this->assertNull($template->get2('archive'));
  }

}
