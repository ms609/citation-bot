<?php
declare(strict_types=1);

/*
 * Tests for Zotero.php - some of these work even when zotero fails because they check for the absence of bad data
 */

require_once __DIR__ . '/../testBaseClass.php';

final class zoteroTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_HTTP !== '' || BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }
 
  public function testIEEEdoi() : void {
    $url = "https://ieeexplore.ieee.org/document/4242344";
    $template = $this->process_citation('{{cite journal | url = ' . $url . ' }}');
    $this->assertSame('10.1109/ISSCC.2007.373373', $template->get2('doi'));
  }
 
}
