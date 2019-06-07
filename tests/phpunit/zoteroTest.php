<?php

/*
 * Tests for zotero.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
class ZoteroTest extends testBaseClass {
      
  public function testZoteroExpansionPII() {
    $text = '{{cite web|url=http://www.nzherald.co.nz/business/news/article.cfm?c_id=3&objectid=11433122}}';
    $expanded = $this->prepare_citation($text);
    $expanded->validate_and_add('author1', "isaac.davison@nzherald.co.nz @isaac_davison", "Isaac Davison Social Issues Reporter, NZ Herald", "");
    $this->assertNull($expanded->parsed_text());
  }
}
