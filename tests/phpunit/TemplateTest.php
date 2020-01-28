<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 

  
  public function testBibcodesBooks() {
    $this->bibcode_secrets(function() {
      $text = "{{Cite book|bibcode=1982mcts.book.....H}}";
      $expanded = $this->process_citation($text);
      $this->assertSame('1982', $expanded->get('year'));
      $this->assertSame('Houk', $expanded->get('last1'));
      $this->assertSame('N.', $expanded->get('first1'));
      $this->assertNotNull($expanded->get('title'));
      $this->assertFalse($expanded->expand_by_adsabs());
    });
  }
 
  
 
    public function testFinalTidyComplicated() {);
     $this->assertNull('do not commmit'); 
   }

}
