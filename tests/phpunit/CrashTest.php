<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $text='{{cite book |last1=Adler |first1=Cyrus |last2=Singer |first2=Isidore |title=The Jewish encyclopedia: a descriptive record of the history, religion, literature, and customs of the Jewish people from the earliest times to the present day |date=1901 |publisher=Funk and Wagnalls |page=302 |hdl=2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358 |url=https://hdl.handle.net/2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358}}';
    $t = $this->process_citation($text);
    $this->assertNull($t->parsed_text());
   }
}
