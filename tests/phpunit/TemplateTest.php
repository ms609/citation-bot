<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

   public function testNewspaperJournal101() {
    $text = "{{cite journal|website=xyz}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
    $this->assertSame('what is it?', $template->get_parsed());
  }       
}
