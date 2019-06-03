<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

 
  public function testUrlReferencesWithText11() { // Two bad ones.  Make sure we do not loop or anything 
      $text = "{{cite web|url=http://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-4407|title=Calveley, Sir Hugh (d. 1394)|last=Fowler|first=K.|date=2004|location=Oxford|website=Oxford Dictionary of National Biography}}";
      $page = $this->process_page($text);
      $this->assertNull($page->parsed_text());
  
  }
  
}
