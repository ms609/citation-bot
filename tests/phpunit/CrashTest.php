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
      $template->set('chapter', 'dsf34213as');
      $template->final_tidy();
   
      $text = '{{cite document}}';
      $template = $this->make_citation($text);
      $template->set('title', 'dsfas');
      $template->set('chapter', 'd243134fas');
      $template->set('series', 'dsdsdfssfas');
      $template->final_tidy();
   
      $text = '{{cite document}}';
      $template = $this->make_citation($text);
      $template->set('title', 'dsfas');
      $template->set('chapter', 'ds324123fas');
      $template->set('work', 'dsdsdfssfas');
      $template->final_tidy();
   
      $text = '{{cite document}}';
      $template = $this->make_citation($text);
      $template->set('title', 'dsfas');
      $template->set('chapter', 'ds34123423as');
      $template->set('work', 'dsdsdfssfas');
      $template->set('series', 'dsd344dfssfas');
      $template->final_tidy();
  }

}
