<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

   public function testBlockMerge() {
      $this->assertTrue(FALSE);
  }
 
   public function testNewspaperJournal111() {
    $text = "{{cite journal|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertNull($template->get('website'));
    $this->assertSame('news.bbc.co.uk', $template->get('work'));
    $this->assertSame('journal', $template->wikiname());  // Unchanged
  }

  public function testTidyWork2() {
    $text = "{{cite magazine|work=}}";
    $template = $this->make_citation($text);
    $template->prepare();
    $this->assertSame( "{{cite magazine|magazine=}}", $template->parsed_text());  
  }
 
  public function testTidyChapterTitleSeries3() {
    $text = "{{cite journal|series=XYZ|title=XYZ}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('XYZ', $template->get('title'));
    $this->assertNull($template->get('series'));
  }
  
  public function testTidyChapterTitleSeries4() {
    $text = "{{cite book|series=X|title=X}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('X', $template->get('series'));
    $this->assertNull($template->get('title'));
  }
 
}
