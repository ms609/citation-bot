<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testTidy1() {
    $text = '{{cite web|postscript = <!-- A comment only --> }}';
    $template = $this->make_citation($text);
    $template->tidy_parameter('postscript');
    $this->assertNull($template->get('postscript'));
  }
 
  public function testTidy3() {
    $text = '{{cite web|postscript = <!-- A comment only --> {{TETMP}} }}';
    $template = $this->make_citation($text);
    $template->tidy_parameter('postscript');
    $this->assertNull($template->get('postscript'));
  }
}
