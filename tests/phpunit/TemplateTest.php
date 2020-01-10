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
    $this->assertSame('News.BBC.co.uk', $template->get('work'));
    $this->assertSame('journal', $template->wikiname());  // Unchanged
    $template->final_tidy();
    $this->assertSame('News.BBC.co.uk', $template->get('journal'));
  }
  

 
}
