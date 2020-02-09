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
      $text = '{{cite thesis|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}{{bots|allow=not_you}}';
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
      $this->assertSame(FALSE, $page->write(NULL, NULL));
  }

}
