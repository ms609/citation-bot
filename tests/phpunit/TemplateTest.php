<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testBLOCKMERGE() {
    $this->assertSame('MERGE', 'NO');
  }

public function testvalidate_and_add() {
     $text = "{{cite web}}";
     $template = $this->make_citation($text);
     $template->validate_and_add('author1', 'George @Hashtags Billy@hotmail.com', 'Sam @Hashtags Billy@hotmail.com', '', FALSE);
     echo $template->parsed_text();

     $text = "{{cite web}}";
     $template = $this->make_citation($text);
     $template->validate_and_add('author1', 'George @Hashtags', '', '', FALSE);
     echo $template->parsed_text();

     $text = "{{cite web}}";
     $template = $this->make_citation($text);
     $template->validate_and_add('author1', 'George Billy@hotmail.com', 'Sam @Hashtag', '', FALSE);
     echo $template->parsed_text();

     $text = "{{cite web}}";
     $template = $this->make_citation($text);
     $template->validate_and_add('author1', 'com', 'Sam', '', FALSE);
     echo $template->parsed_text();

   }

  
 
}
