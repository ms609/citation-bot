<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {


 
   public function testGetDOIWithBadLastPageAndAuthor() {
      $text = '{{cite journal|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|pages=4346–99999 |year=2019|last1=WRONG|pmc=<!-- nope -->|pmid=<!-- nope -->|doi=10.1021/acs.analchem.8b04567}}';
      $template = $this->process_citation($text);
      $this->assertSame('10.1021/acs.analchem.8b04567', $template->get('doi'));
  }

  public function testBadPage() {  // Use this when debugging pages that crash the bot
    $this->assertTrue(FALSE);
  }
}
