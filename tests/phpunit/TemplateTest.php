<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

 
  public function testTidy70() {
    $text = "{{cite journal|issue=1|volume=2|doi=3}}";
    $template = $this->make_citation($text);
    print_r($template->param);
    $template->rename('doi', 'issue');
    print_r($template->param);
    $template->rename('volume', 'issue');
    print_r($template->param);
    $template->forget('issue');
    print_r($template->param);
    $template->forget('issue');
    print_r($template->param);
    $template->forget('issue');
    print_r($template->param);
  }
          
}
