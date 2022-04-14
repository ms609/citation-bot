<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage() : void {  // Use this when debugging pages that crash the bot
    $bad_page = BAD_PAGE_HTTP;
    $bad_page = urlencode(str_replace(' ', '_', trim($bad_page)));
    if ($bad_page !== "") {
      define("TRAVIS_PRINT", "YES");
      $ch = curl_init();
      if (strpos($bad_page, 'simple%3A') === 0) {
        $bad_page = str_replace('simple%3A', '', $bad_page);
        $the_url =  str_replace('en.', 'simple.', WIKI_ROOT) . '?title=' . $bad_page . '&action=raw';
      } else {
        $the_url =  WIKI_ROOT . '?title=' . $bad_page . '&action=raw';
      }
      curl_setopt_array($ch,
           [CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => BOT_USER_AGENT,
            CURLOPT_URL => $the_url]);
      $text = curl_exec($ch);
      curl_close($ch);
      $page = new TestPage();
      $page->parse_text($text);
      unset($text);
      AdsAbsControl::back_on();
      Zotero::unblock_zotero();
      $page->expand_text();
      AdsAbsControl::give_up();
      Zotero::block_zotero();
      echo "\n\n\n\n";
      $text = $page->parsed_text();
      unset($page);
      $text = str_replace("\r", "\n", $text);
      $text = str_replace(["\n\n\n\n\n\n\n", "\n\n\n\n\n\n", "\n\n\n\n\n", "\n\n\n\n", "\n\n\n", "\n\n"], ["\r\r\r\r\r\r\n", "\r\r\r\r\r\n", "\r\r\r\r\n", "\r\r\r\n", "\r\r\n", "\r\n"], $text); // Protect multiple line feeds
      $text = str_replace("\r\n\r", "\r\r\r", $text);
      $line = strtok($text, "\n");
      while ($line !== false) {
        $line = str_replace("\r", "\n", $line);
        echo $line . "\n";
        $line = strtok("\n");
      }
      echo "\n\n\n\n";
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
 
  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot using API to get page
    $bad_page = BAD_PAGE_API;
    $bad_page = str_replace(' ', '_', $bad_page);
    if ($bad_page !== "") {
      define("TRAVIS_PRINT", "YES");
      $page = new TestPage();
      $page->get_text_from($bad_page);
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
