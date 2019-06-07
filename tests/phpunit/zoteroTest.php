<?php

/*
 * Tests for zotero.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
class ZoteroTest extends testBaseClass {
      
  public function testZoteroExpansionPII() {
    $text = '{{cite web|url=http://www.nzherald.co.nz/business/news/article.cfm?c_id=3&objectid=11433122}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertNull($expanded->parsed_text());
  }
}
