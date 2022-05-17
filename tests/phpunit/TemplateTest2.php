<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest2 extends testBaseClass {

 
   public function testVerifyDOI14() : void {
     $text = '{{cite journal|doi=10.1093/ww/9780199540891.001.0001/ww-9780199540884-e-12345}}';
     $template = $this->make_citation($text);
     $template->verify_doi();
     $this->assertSame('10.1093/ww/9780199540884.013.U12345', $template->get2('doi'));
  }
 
   public function testVerifyDOI15() : void {
     $text = '{{cite journal|doi=10.1093/ww/9780199540891.001.0001/ww-9780199540884-e-12345}}';
     $template = $this->process_citation($text);
     $this->assertSame('10.1093/ww/9780199540884.013.U12345', $template->get2('doi'));
  }
 
}
