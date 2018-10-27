<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {


  public function testRespectDates() {
      $text = '{{Use mdy dates}}{{cite web|url=https://www.nasa.gov/content/profile-of-john-glenn}}';
      $page = $this->process_page($text);
      $this->assertTrue((boolean) strpos($page->parsed_text(), '12-05-2016'));
      $text = '{{Use dmy dates}}{{cite web|url=https://www.nasa.gov/content/profile-of-john-glenn}}';
      $page = $this->process_page($text);
      $this->assertTrue((boolean) strpos($page->parsed_text(), '05-12-2016'));
  }
}
