<?php

/*
 * Tests for zotero.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
class ZoteroTest extends testBaseClass {

// TODO - seems to want a login or cookie   
//public function testZoteroExpansionRG() {
//    $text = '{{Cite journal|url =https://www.researchgate.net/publication/23445361}}';
//    $expanded = $this->expand_via_zotero($text);
//    $this->assertEquals('10.1136/jnnp.2008.144360', $expanded->get('doi'));
//  }
      
  public function testZoteroExpansionPII() {
    $text = '{{cite book |year=1844 |title=The Acts of the Parliaments of Scotland  |hdl=2027/mdp.39015035897480 |publisher=}}';
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->parsed_text());
  }

  
}
