<?php

/*
/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testBadPage() {  // Use this when debugging pages that crash the bot
      $text = '{{Cite journal|last=Šťovíček|first=Jan|date=2013-01-01|title=Deconstructibility and the Hill Lemma in Grothendieck categories|journal=Forum Mathematicum|language=en|volume=25|issue=1|pages=|arxiv=1005.3251|doi=10.1515/FORM.2011.113}}';
      $page = new TestPage();
      $page->parse_text($text);
      $page->expand_text();
    $this->assertNull($page->parsed_text());
  }
}
