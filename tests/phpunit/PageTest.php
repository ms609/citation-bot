<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testNestedTemplates() {
      $text = '{{cite book|pages=1-2| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} | id={{cite book|pages=3-4| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} }}  }} |  cool stuff | not cool}}';
      $expanded = $this->process_citation($text);
      $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
      $this->assertSame($text, $expanded->parsed_text());
      
      $text = '{{cite book|quote=See {{cite book|pages=1-2|quote=See {{cite book|pages=1-4}}}}|pages=1-3}}';
      $expanded = $this->process_citation($text);
      $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
      $this->assertSame($text, $expanded->parsed_text());
  }
}
