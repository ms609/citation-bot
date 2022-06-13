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
   $this->process_citation("{{cite journal |last1=Asensi |first1=Victor |last2=Fierer |first2=Joshua |title=Of Rats and Men: Poussin's Plague at Ashdod |journal=Emerging Infectious Diseases |date=January 2018 |volume=24 |issue=1 |pages=186–187 |doi=10.3201/eid2401.AC2401 |pmc=5749463 |issn=1080-6040}}</ref><ref name="Freemon">{{cite journal |last1=Freemon |first1=Frank R |title=Bubonic plague in the Book of Samuel |journal=Journal of the Royal Society of Medicine |date=September 2005 |volume=98 |issue=9 |pages=436  |issn=0141-0768|pmc=<!-- --> |pmid=<!-- --> }}");

   
   
   
  }

}
