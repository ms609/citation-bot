<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {
 
  public function testBadPage() {  // Use this when debugging pages that crash the bot

      $text = file_get_contents('https://en.wikipedia.org/w/index.php?title=Russian_interference_in_the_2016_United_States_elections&action=raw&oldid=890503219');
      $page = new TestPage();
      $page->parse_text($text);
      $page->expand_text();
      $this->assertTrue(FALSE); // prevent us from git committing with a website included

  }
}
