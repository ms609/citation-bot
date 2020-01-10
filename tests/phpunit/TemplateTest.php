<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

   public function testNewspaperJournal111() {
    $text = "{{cite journal|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get('website'));
    $this->assertSame('news.bbc.co.uk', $template->get('work'));
    $this->assertSame('journal', $template->wikiname());  // Unchanged
  }
  
  public function testTidyChapterTitleSeries4() {
    $text = "{{cite book|journal=X}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $template->tidy_parameter('series');
    $this->assertSame('XYZ', $template->get('series'));
    $this->assertSame('X', $template->get('journal'));
   
    $text = "{{cite book|title=X}}";
    $template = $this->make_citation($text);
    $template->add_if_new('series', 'XYZ');
    $template->tidy_parameter('series');
    $this->assertSame('XYZ', $template->get('series'));
    $this->assertSame('X', $template->get('title'));
  }
 
}
