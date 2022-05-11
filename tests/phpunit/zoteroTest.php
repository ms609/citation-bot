<?php
declare(strict_types=1);

/*
 * Tests for Zotero.php - some of these work even when zotero fails because they check for the absence of bad data
 */

require_once __DIR__ . '/../testBaseClass.php';
final class zoteroTest extends testBaseClass {



  public function testHDLSimpler1() : void {
    hdl_works('2027/mdp.39015064245429');
    hdl_works('2027/mdp.39015064245429?urlappend=%3Bseq=326');
    hdl_works('2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358');
    $text = '{{Cite web}}';
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url('https://hdl.handel.net/2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358');
    $this->assertSame('2027/mdp.39015064245429?urlappend=%3Bseq=326', $template->get2('hdl'));
  }

  public function testHDLSimpler2() : void {
    hdl_works('20.1000/100');
    hdl_works('20.1000/100?urlappend=%3Bseq=326');
    hdl_works('20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35');
    $text = '{{Cite web}}';
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url('https://hdl.handel.net/20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35');
    $this->assertSame('20.1000/100', $template->get2('hdl'));
  }

}
