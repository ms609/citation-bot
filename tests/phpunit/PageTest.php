<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

   public function testBadPage() {  // Use this when debugging pages that crash the bot
    $text = file_get_contents('https://en.wikipedia.org/w/index.php?title=Inductive_programming&action=raw');
    $text = "{{cite web|url=https://pdfs.semanticscholar.org/48f2/2821220555f8e327c2aa9614fb28c98f9542.pdf}}";
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    $this->assertTrue(TRUE);
  }
}
