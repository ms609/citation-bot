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
    $this->assertSame('news.bbc.co.uk', $template->get('newspaper'));
    $this->assertSame('newspaper', $template->wikiname());  
  }

  public function testVolumeIssueDemixing5() {
    $text = '{{cite journal|issue = volume 12|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12', $prepared->get('volume'));
    $this->assertNull($prepared->get('issue'));
  }
 
  public function testVolumeIssueDemixing14() {
    $text = '{{cite journal|issue = volume 12XX|volume=12XX|doi=XYZ}}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('12XX', $prepared->get('volume'));
    $this->assertNull($prepared->get('issue'));
  }
 
  public function testMoreEtAl2() {
    $text = "{{cite web|authors=et al.}}";
    $template = $this->make_citation($text);
    $this->assertSame('et al.', $template->get('authors'));
    $template->handle_et_al();
    $this->assertNull($template->get('author'));
    $this->assertNull($template->get('authors'));
    $this->assertSame('etal', $template->get('displayauthors'));
  }
 
   public function testCiteTypeWarnings3() {
    $text = "{{citation|title=XYZsadfdsfsdfdsafsd|chapter=DSRGgbgfbxdzfdfsXXXX|journal=adsfsd}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
    $this->assertSame('cite book', $template->wikiname());
  }

  public function testTidyWork2() {
    $text = "{{cite magazine|work=}}";
    $template = $this->make_citation($text);
    $template->final_tidy();
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
 
  public function testAllZeroesTidy() {
    $text = "{{cite web|pages=000000000}}";
    $template = $this->make_citation($text);
    $template->tidy_parameter('pages');
    $this->assertNull($template->get('pages'));
  }
 
  public function testConversionOfURL2() {
    $text = "{{cite web|url=http://worldcat.org/title/stuff/oclc/1234}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertSame('1234', $template->get('oclc'));
    $this->assertNull($template->get('url'));
    $this->assertSame('cite book', $template->wikiname());           
  }
 
}
