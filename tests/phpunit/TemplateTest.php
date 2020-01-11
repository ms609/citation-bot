<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {


 
  public function testAddASIN() {
    $text = "{{Cite book |isbn=0226845494}}";
    $expanded = $this->make_citation($text);
    $this->assertFalse($expanded->add_if_new('asin', 'X'));
    $this->assertSame('0226845494', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
                       
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', '630000000')); //63.... code
    $this->assertSame('630000000', $expanded->get('asin'));
   
    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', 'BNXXXXXXXX')); // Not an ISBN at all
    $this->assertSame('BNXXXXXXXX', $expanded->get('asin'));

    $text = "{{Cite book}}";
    $expanded = $this->make_citation($text);
    $this->assertTrue($expanded->add_if_new('asin', '9780781765626'));
    $this->assertSame('9780781765626', $expanded->get('isbn'));
    $this->assertNull($expanded->get('asin'));
  }
 

 
  public function testIncomplete() {
    $text = "{{cite book|url=http://perma-archives.org/pqd1234|isbn=Xxxx|title=xxx|issue=a|volume=x}}"; // Non-date website
    $template = $this->make_citation($text);
    $this->assertFalse($template->profoundly_incomplete());
    $text = "{{cite book|url=http://a_perfectly_acceptable_website/pqd1234|isbn=Xxxx|issue=hh|volume=rrfff|title=xxx}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->profoundly_incomplete());
  }
 
  
 
}
