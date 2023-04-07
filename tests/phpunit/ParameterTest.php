<?php
declare(strict_types=1);

/*
 * Tests for Parameter.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class ParameterTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }
  
  public function testOddSpaces() : void { // TODO
    $text = "{{Infobox settlement\n| image_skyline            = \n \n| image_caption            = \n}}";
    $template = $this->process_citation($text);
    $this->assertSame($text, $template->parsed_text());
  }
}

