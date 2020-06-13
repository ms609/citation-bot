<?php

/*
 * Tests for Page.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class PageTest extends testBaseClass {

  public function testBadPage1() : void {  // Use this when debugging pages that crash the bot
    // This MUST be escaped page name-underscores not spaces and such
    $bad_page = "War"; //  Replace with something like "Vietnam_War" when debugging
    if ($bad_page !== "") {
      $text = file_get_contents(WIKI_ROOT . '?title=' . $bad_page . '&action=raw');
      $page = new TestPage();
      $page->parse_text($text);
      $BLOCK_BIBCODE_SEARCH = FALSE;
      $BLOCK_ZOTERO_SEARCH = FALSE;
      $page->expand_text();
      $BLOCK_BIBCODE_SEARCH = TRUE;
      $BLOCK_ZOTERO_SEARCH = TRUE;
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
 
  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    // This MUST be escaped page name-underscores not spaces and such
    $bad_page = "Cat"; //  Replace with something like "Vietnam_War" when debugging
    if ($bad_page !== "") {
      $text = file_get_contents(WIKI_ROOT . '?title=' . $bad_page . '&action=raw');
      $page = new TestPage();
      $page->parse_text($text);
      $BLOCK_BIBCODE_SEARCH = FALSE;
      $BLOCK_ZOTERO_SEARCH = FALSE;
      $page->expand_text();
      $BLOCK_BIBCODE_SEARCH = TRUE;
      $BLOCK_ZOTERO_SEARCH = TRUE;
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
 
   public function testBadPage3() : void {  // Use this when debugging pages that crash the bot
    // This MUST be escaped page name-underscores not spaces and such
    $bad_page = "Benzene"; //  Replace with something like "Vietnam_War" when debugging
    if ($bad_page !== "") {
      $text = file_get_contents(WIKI_ROOT . '?title=' . $bad_page . '&action=raw');
      $page = new TestPage();
      $page->parse_text($text);
      $BLOCK_BIBCODE_SEARCH = FALSE;
      $BLOCK_ZOTERO_SEARCH = FALSE;
      $page->expand_text();
      $BLOCK_BIBCODE_SEARCH = TRUE;
      $BLOCK_ZOTERO_SEARCH = TRUE;
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
}
