<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testIncomplete() {
    $text = "{{cite book|url=http://perma-archives.org/pqd1234|isbn=Xxxx|title=xxx|issue=a|volume=x}}"; // Non-date website
    $template = $this->make_citation($text);
    $this->assertFalse($template->profoundly_incomplete());
  }
     public function testIncomplete_part2() {
    $text = "{{cite book|url=http://a_perfectly_acceptable_website/pqd1234|isbn=Xxxx|issue=hh|volume=rrfff|title=xxx}}";
    $template = $this->make_citation($text);
    $this->assertTrue($template->profoundly_incomplete());
  }
 
  
 
}
