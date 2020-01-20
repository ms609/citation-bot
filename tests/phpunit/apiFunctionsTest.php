<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testAdsabsApi() {
    $this->assertNull('do not commit');
  }
  
   public function testJstor() {
     $text = "{{cite journal|url=https://jstor.org/stable/832414?seq=1234}}";
     $template = $this->make_citation($text);
     $this->assertTrue(expand_by_jstor($template));
     $this->assertNull($template->get('jstor')); // We don't do that here

     $text = "{{cite journal|jstor=832414?seq=1234}}";
     $template = $this->make_citation($text);
     $this->assertTrue(expand_by_jstor($template));
     $this->assertNull($template->get('url'));

     $text = "{{cite journal|jstor=123 123}}";
     $template = $this->make_citation($text);
     $this->assertFalse(expand_by_jstor($template));

     $text = "{{cite journal|jstor=i832414}}";
     $template = $this->make_citation($text);
     $this->assertFalse(expand_by_jstor($template));
  }
}
