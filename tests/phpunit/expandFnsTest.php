<?php
declare(strict_types=1);

/*
 * Current tests that are failing.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class expandFnsTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }

  public function testCapitalization1a() : void {
    $text="{{cite book |last1=Adler |first1=Cyrus |last2=Singer |first2=Isidore |title=The Jewish encyclopedia: a descriptive record of the history, religion, literature, and customs of the Jewish people from the earliest times to the present day |date=1901 |publisher=Funk and Wagnalls |page=302 |hdl= |url=https://hdl.handle.net/2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358}}";
    $template = $this->process_citation($text);
    $this->assertNull($template->parsed_text());
  }

}
