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
    hdl_works('20.1000/100');
    hdl_works('20.1000/100?urlappend=;seq=326');
    hdl_works('20.1000/100?urlappend=;seq=326;ownerid=13510798900390116-35');
    hdl_works('20.1000/100');
    hdl_works('20.1000/100?urlappend=%3Bseq=326');
    hdl_works('20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35');
    ob_flush();
    $text = '{{Cite web}}';
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url('https://hdl.handle.net/2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358');
    $this->assertNull($template->parsed_text());
  }

  public function testHDLSimpler2() : void {
    $text = '{{Cite web}}';
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url('https://hdl.handle.net/20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35');
    $this->assertNull($template->parsed_text());
  }
  
  public function testHDLSimpler3() : void {
    $text = '{{Cite web}}';
    $template = $this->make_citation($text);
    $template->get_identifiers_from_url('https://hdl.handle.net/20.1000/100?urlappend=;seq=326;ownerid=13510798900390116-35');
    $this->assertNull($template->parsed_text());
  }

}
