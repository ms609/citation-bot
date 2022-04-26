<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }

  public function testLotsOfFloaters() : void {
    $text_in = "{{cite journal|issue 3 volume 5 | title Love|journal Dog|series Not mine today|chapter cows|this is random stuff | 123-4567-890 }}";
    $text_out= "{{cite book|this is random stuff | issue=3 | volume=5 | title=Love | chapter=Cows | journal=Dog | series=Not mine today | isbn=123-4567-890 }}";
    $prepared = $this->prepare_citation($text_in);
    $this->assertSame($text_out, $prepared->parsed_text());
  }
  
  
}
