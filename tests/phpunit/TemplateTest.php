<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {


 
  public function testURLCleanUp3() {
    $text = "{{cite journal|url=https://dx.doi.org/10.0000/BOGUS|doi=10.0000/THIS_IS_JUNK_DATA}}"; // Fail to add bogus
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('https://dx.doi.org/10.0000/BOGUS', $template->get('url'));
    $this->assertSame('10.0000/THIS_IS_JUNK_DATA', $template->get('doi'));
  }
 
  public function testURLCleanUp4() {
    $text = "{{cite journal|url=https://dx.doi.org/10.1093/oi/authority.x}}"; // A particularly semi-valid DOI
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertNull($template->get('doi'));
    $this->assertSame('https://dx.doi.org/10.1093/oi/authority.x', $template->get('url'));
  }
 
 
  public function testURLCleanUp6() {
    $text = "{{cite journal|doi= 10.1093/oi/authority.x|url=https://dx.doi.org/10.1093/oi/authority.xXXXXXXXXXX.pdf}}";
    $template = $this->make_citation($text);
    $this->assertFalse($template->get_identifiers_from_url());
    $this->assertSame('https://dx.doi.org/10.1093/oi/authority.xXXXXXXXXXX.pdf', $template->get('url'));
    $this->assertSame('10.1093/oi/authority.x', $template->get('doi'));
  }
 
  public function testDoiExpansionBook() {
    $this->assertNull('do don commit me');
  }
  
  
}
