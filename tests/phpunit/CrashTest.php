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
    $text = '{{cite journal | citation_bot_placeholder_bare_url = XYX }}';
    $template = $this->make_citation($text);
    $template->add('title', 'Thus');
    $this->assertNotNull($template->get2('citation_bot_placeholder_bare_url'));
    $array = $template->modifications();
    $expected = array ( 'modifications' =>  array ( 0 => 'title',  ),
                        'additions' =>  array ( 0 => 'title',  ),
                        'deletions' =>  array ( 0 => 'citation_bot_placeholder_bare_url', ),
                        'changeonly' => array (  ),
                        'dashes' => FALSE,
                        'names' => FALSE,)
    $this->assertTrue($array == $expected);
    $this->assertNull($template->get2('citation_bot_placeholder_bare_url'));
  }


}
