<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

 
  
  public function testBadPage() : void {  // Use this when debugging pages that crash the bot
      Zotero::create_ch_zotero();
      WikipediaBot::make_ch();
      $this->assertTrue(FALSE);
  }


  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
     $result = doi_works('10.35808/ijeba/1');
     //print_r($result);
   //$this->process_citation("{{ }}");

   
   
   
  }

}
