<?php
declare(strict_types=1);

/*
 * Tests for Page.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class PageTest extends testBaseClass {
 
  public function testBadPage() : void {  // Use this when debugging pages that crash the bot
    $before_time = microtime(TRUE);
    $free_stuff = gc_collect_cycles();
    $after_time = microtime(TRUE);
    echo("\nBefore starting Freed " . (string) $free_stuff . " objects in GC cylce that took " . (string) ($after_time-$before_time) . " seconds\n" );
   

    $bad_page = "Vietnam War"; //  Replace with page name when debugging
    $bad_page = urlencode(str_replace(' ', '_', $bad_page));
      $ch = curl_init();
      curl_setopt_array($ch,
           [CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
            CURLOPT_URL => WIKI_ROOT . '?title=' . $bad_page . '&action=raw']);
      $text = curl_exec($ch);
      curl_close($ch);
       $before_time = microtime(TRUE);
    $free_stuff = gc_collect_cycles();
    $after_time = microtime(TRUE);
    echo("After CURL Freed " . (string) $free_stuff . " objects in GC cylce that took " . (string) ($after_time-$before_time) . " seconds\n" );
      $page = new TestPage();
       $before_time = microtime(TRUE);
    $free_stuff = gc_collect_cycles();
    $after_time = microtime(TRUE);
    echo("After make page Freed " . (string) $free_stuff . " objects in GC cylce that took " . (string) ($after_time-$before_time) . " seconds\n" );
      $page->parse_text($text);
       $before_time = microtime(TRUE);
    $free_stuff = gc_collect_cycles();
    $after_time = microtime(TRUE);
    echo("After parse textFreed " . (string) $free_stuff . " objects in GC cylce that took " . (string) ($after_time-$before_time) . " seconds\n" );
      AdsAbsControl::back_on();
      Zotero::unblock_zotero();
      $page->expand_text();
      AdsAbsControl::give_up();
      Zotero::block_zotero();
       $before_time = microtime(TRUE);
    $free_stuff = gc_collect_cycles();
    $after_time = microtime(TRUE);
    echo("Afterr process page Freed " . (string) $free_stuff . " objects in GC cylce that took " . (string) ($after_time-$before_time) . " seconds\n" );
         $page->parse_text($text);
       $before_time = microtime(TRUE);
    $free_stuff = gc_collect_cycles();
    $after_time = microtime(TRUE);
    echo("After parse textFreed " . (string) $free_stuff . " objects in GC cylce that took " . (string) ($after_time-$before_time) . " seconds\n" );
      AdsAbsControl::back_on();
      Zotero::unblock_zotero();
      $page->expand_text();
      AdsAbsControl::give_up();
      Zotero::block_zotero();
       $before_time = microtime(TRUE);
    $free_stuff = gc_collect_cycles();
    $after_time = microtime(TRUE);
    echo("Afterr process page Freed " . (string) $free_stuff . " objects in GC cylce that took " . (string) ($after_time-$before_time) . " seconds\n" );
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
  }
}
