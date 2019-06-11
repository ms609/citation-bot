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
    $text = '{{Cite journal|url = https://www.sciencedirect.com/science/article/pii/S0024379512004405}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals('10.1016/j.laa.2012.05.036', $expanded->get('doi'));
    $this->assertNull($expanded->get('url')); // Recognize canonical publisher URL as duplicate of valid doi
  }

  
}
