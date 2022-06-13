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
   $this->process_citation("{{cite journal |last1=Freemon |first1=Frank R |title=Bubonic plague in the Book of Samuel |journal=Journal of the Royal Society of Medicine |date=September 2005 |volume=98 |issue=9 |pages=436  |issn=0141-0768|pmc=<!-- --> |pmid=<!-- --> }}");

   
   
   
  }

}
