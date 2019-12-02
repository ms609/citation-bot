<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testPageChangeSummary() {
      $page = $this->process_page('<ref>https://doi.org/10.1023/A:1008280705142</ref>');
      $this->assertSame('There should be a doi here', $page->parsed_text());
  }
}
