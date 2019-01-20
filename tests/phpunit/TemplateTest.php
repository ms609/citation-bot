<?php

/*
 * Tests for Template.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
  public function testBibcodesBooks() {
    $this->requires_secrets(function() {
      $text = "{{Cite book|bibcode=1982mcts.book.....H}}";
      $expanded = $this->process_citation($text);
      $this->assertEquals('1982', $expanded->get('year'));
      $this->assertEquals('Houk', $expanded->get('last1'));
      $this->assertEquals('N.', $expanded->get('first1'));
      $this->assertNotNull($expanded->get('title'));
    });
  }
 

  public function testBibcodeDotEnding() {
    $this->requires_secrets(function() {
      $text='{{cite journal|title=Electric Equipment of the Dolomites Railway|journal=Nature|date=2 January 1932|volume=129|issue=3244|page=18|doi=10.1038/129018a0}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('1932Natur.129Q..18.', $expanded->get('bibcode'));
    });
  }

    
  public function testJustAnOCLC() {
    $this->requires_secrets(function() {
      $text = '{{cite web | url=http://www.worldcat.org/oclc/9334453}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('cite book', $expanded->wikiname());
      $this->assertNull($expanded->get('url'));
      $this->assertEquals('9334453', $expanded->get('oclc'));
      $this->assertEquals('The Shreveport Plan: A Long-range Guide for the Future Development of Metropolitan Shreveport', $expanded->get('title'));
    });
  }

  public function testJustAnLCCN() {
    $this->requires_secrets(function() {
      $text = '{{cite book | lccn=2009925036}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('Alternative Energy for Dummies', $expanded->get('title'));
    });
  }
    
  
}
