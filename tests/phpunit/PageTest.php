<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

 
   public function testNobots3() {
      $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002 |access-date=November 20, 2018 |url-access=subscription|via=Grove Music Online}}';
      $template = $this->process_citation($text);
      $this->assertSame('What?', $template->parsed_text());
  }
 
  
}
