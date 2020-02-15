<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

 
   public function testNobots3() {
      $text = '{{cite thesis}}';
      $page = $this->process_citation($text);
      $this->assertSame('{{cite thesis|mr = 1234}}{{bots|allow=Citation Bot}}', $page->parsed_text());
  }
 
  
}
