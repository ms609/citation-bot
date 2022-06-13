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
     //$result = doi_works('10.21236/ad0012012');
     //print_r($result);
   $this->process_citation("{{Cite journal|last=Webb|first=Thomas E F|date=July 2006|title='Dottyville'—Craiglockhart War Hospital and shell-shock treatment in the First World War|journal=Journal of the Royal Society of Medicine|volume=99|issue=7|pages=342–346|doi=|issn=0141-0768|pmc=<!-- --> |pmid=<!-- --> }}");

   
   
   
  }

}
