<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

   public function testDIE() {
      $this->assertSame(FALSE, TRUE);
  }
 
  public function testNobots2() {
      $text = '{{cite journal|url=https://search.proquest.com/docview/12341234|id=CITATION_BOT_PLACEHOLDER_COMMENT|title=Xyz}}';
      $page = $this->make_citation($text);
      $page->get_identifiers_from_url();
      $this->assertSame($text, $page->parsed_text());
  }

}
