<?php
declare(strict_types=1);

/*
 * Tests for Page.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class PageTest extends testBaseClass {
 
  public function testBadPage() : void {  // Use this when debugging pages that crash the bot
    $bad_page = ""; //  Replace with page name when debugging
    $bad_page = urlencode(str_replace(' ', '_', $bad_page));
    if ($bad_page !== "") {
      $ch = curl_init();
      curl_setopt_array($ch,
           [CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
            CURLOPT_URL => WIKI_ROOT . '?title=' . $bad_page . '&action=raw']);
      $text = curl_exec($ch);
      curl_close($ch);
      $page = new TestPage();
      $page->parse_text($text);
      AdsAbsControl::back_on();
      Zotero::unblock_zotero();
      $page->expand_text();
      AdsAbsControl::give_up();
      Zotero::block_zotero();
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
 
  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $bad_page = "Delhi"; //  Replace with page name when debugging
    $bad_page = urlencode(str_replace(' ', '_', $bad_page));
    if ($bad_page !== "") {
      $api = new WikipediaBot();
      $page = new TestPage();
      $page->get_text_from($bad_page, $api);
      AdsAbsControl::back_on();
      Zotero::unblock_zotero();
      $page->expand_text();
      AdsAbsControl::give_up();
      Zotero::block_zotero();
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
 
  public function testBadPage3() : void {  // Use this when debugging pages that crash the bot
    $bad_page = "Al-Qa'im (Abbasid caliph at Baghdad)"; //  Replace with page name when debugging
    $bad_page = str_replace(' ', '_', $bad_page); // Try without encoding
    if ($bad_page !== "") {
      $api = new WikipediaBot();
      $page = new TestPage();
      $page->get_text_from($bad_page, $api);
      AdsAbsControl::back_on();
      Zotero::unblock_zotero();
      $page->expand_text();
      AdsAbsControl::give_up();
      Zotero::block_zotero();
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
}
