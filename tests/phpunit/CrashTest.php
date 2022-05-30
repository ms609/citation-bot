<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
      $text = '{{cite document}}';
      $template = $this->make_citation($text);
      $template->set('title', 'dsfas');
      $template->set('chapter', 'dsfas');
      $template->final_tidy();
   
      $text = '{{cite document}}';
      $template = $this->make_citation($text);
      $template->set('title', 'dsfas');
      $template->set('chapter', 'dsfas');
      $template->set('series', 'dsdsdfssfas');
      $template->final_tidy();
   
      $text = '{{cite document}}';
      $template = $this->make_citation($text);
      $template->set('title', 'dsfas');
      $template->set('chapter', 'dsfas');
      $template->set('work', 'dsdsdfssfas');
      $template->final_tidy();
   
      $text = '{{cite document}}';
      $template = $this->make_citation($text);
      $template->set('title', 'dsfas');
      $template->set('chapter', 'dsfas');
      $template->set('work', 'dsdsdfssfas');
      $template->set('series', 'dsd344dfssfas');
      $template->final_tidy();
  }

}
