<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {


  public function testUrlReferencesWithText6() {
      $text = "{{citation | last = Laver | first = Richard | arxiv =  | doi = 10.1006/aima.1995.1014 | issue = 2 | journal = Advances in Mathematics | mr = 1317621 | pages = 334–346 | title = On the algebra of elementary embeddings of a rank into itself| volume = 110 | year = 1995}}";
      $page = $this->process_page($text);
      $this->assertNull($page->parsed_text());
  }

 
  public function testBadPage() {  // Use this when debugging pages that crash the bot

      $this->assertTrue(FALSE); // prevent us from git committing with a website included

  }
}
