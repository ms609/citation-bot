<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class TemplateTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_HTTP !== '' || BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }


 
  public function testLotsMagazines() : void {
    $text_in = "{{cite journal |last=Rauhut |first=O.W. |year=2004 |title=The interrelationships and evolution of basal theropod dinosaurs |journal=Special Papers in Palaeontology |volume=69 |pages=213}}";
    $prepared = $this->process_citation($text_in);
    $this->assertSame($text_in, $prepared->parsed_text());
  }

 
   
}
