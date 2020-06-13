<?php

/*
 * Tests for Template.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class TemplateTest extends testBaseClass {
 

  public function testLongAuthorLists() : void {
  $this->requires_arxiv(function() : void {
    $text = '{{cite web | https://arxiv.org/PS_cache/arxiv/pdf/1003/1003.3124v2.pdf}}';
    $expanded = $this->process_citation($text);
    $this->assertSame('Aad, G.', $expanded->first_author());
    $this->assertNull($expanded->get2('class'));
   });
  }
  
 
}
