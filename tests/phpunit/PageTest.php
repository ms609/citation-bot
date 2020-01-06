<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testTidy1() {
    $text = '{{cite web|postscript = <!-- A comment only --> }}';
    $template = $this->process_citation($text);
    $this->assertNull($template->get('postscript'));
  }
 
  public function testTidy3() {
    $text = '{{cite web|postscript = <!-- A comment only --> {{TETMP}} }}';
    $template = $this->process_citation($text);
    $this->assertNull($template->get('postscript'));
  }
}
