<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
  public function testLotsOfFloaters() {
    $text_in = "{{citation|doi=10.1007/s00373-007-0720-5}}";
    $prepared = $this->process_citation($text_in);
    $this->assertSame('huh', $prepared->parsed_text());
  }

 
   public function testLotsOfFloaters2() {
    $text_in = "{{citation|url = http://www.matem.unam.mx/urrutia/online_papers/Akiyama-Article.pdf}}";
    $prepared = $this->process_citation($text_in);
    $this->assertSame('huh', $prepared->parsed_text());
  }
 
   public function testLotsOfFloaters3() {
    $text_in = "{{citation | last1 = Kano | first1 = M. | last2 = Ruiz | first2 = Mari-Jo P. | author2-link = Mari-Jo P. Ruiz | last3 = Urrutia | first3 = Jorge | author3-link = Jorge Urrutia | doi = 10.1007/s00373-007-0720-5 | issue = suppl. 1 | journal = Graphs and Combinatorics | mr = 2320617 | pages = 1–39 | title = Jin Akiyama: a friend and his mathematics | url = http://www.matem.unam.mx/urrutia/online_papers/Akiyama-Article.pdf | volume = 23 | year = 2007 }}";
    $prepared = $this->process_citation($text_in);
    $this->assertSame('huh', $prepared->parsed_text());
  }
}
