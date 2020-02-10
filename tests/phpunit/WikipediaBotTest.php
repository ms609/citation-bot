<?php

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
  class WikipediaBotTest extends testBaseClass {

  public function testGetPMIDwitNoDOIorJournal() {  // Also has evil colon in the name
      $text = '{{cite journal|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|volume=91|issue=7|pages=4346 |year=2019|last1=Colby}}';
      $template = $this->make_citation($text);
      $template->find_pmid();
      $this->assertSame('30741529', $template->get('pmid'));
  }
   
  public function testGet() {
      $this->assertFalse(TRUE);
  }
}
