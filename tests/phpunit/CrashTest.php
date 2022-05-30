<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
      $text = '{{cite document|chapter=xyx|title=cvzxc}}';
      $template = $this->make_citation($text);
      $template->final_tidy();
   
      $text = '{{cite document|chapter=xyx|title=cvzxc|series=dsfadsdsf}}';
      $template = $this->make_citation($text);
      $template->final_tidy();
   
      $text = '{{cite document|chapter=xyx|title=cvzxc|work=dsfadsdsf}}';
      $template = $this->make_citation($text);
      $template->final_tidy();
   
      $text = '{{cite document|chapter=xyx|title=cvzxc|work=dsfadsdsf|series=4532453dsfadfas}}';
      $template = $this->make_citation($text);
      $template->final_tidy();
  }

}
