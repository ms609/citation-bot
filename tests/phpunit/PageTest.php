<?php

/*
/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testBadPage() {  // Use this when debugging pages that crash the bot
    $bad_page = "2016_Turkish_coup_d%27état_attempt"; //  Replace with something like "Vietnam_War" when debugging
    if ($bad_page !== "") {
      $text = file_get_contents('https://en.wikipedia.org/w/index.php?title=' . $bad_page . '&action=raw');
      $page = new TestPage();
      $page->parse_text($text);
      $page->expand_text();
    }
    $bad_page = "List_of_Flashpoint_episodes"; //  Replace with something like "Vietnam_War" when debugging
    if ($bad_page !== "") {
      $text = file_get_contents('https://en.wikipedia.org/w/index.php?title=' . $bad_page . '&action=raw');
      $page = new TestPage();
      $page->parse_text($text);
      $page->expand_text();
    }
    $this->assertTrue(FASLE);
  }
}
