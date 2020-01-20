<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testAdsabsApi() {
    $this->assertNull('do not commit');
  }
  
   public function testJstor() {
     $text = "{{cite journal|url=https://jstor.org/stable/832414?seq=1234}}";
     $template = $this->make_citation($text);
     expand_by_jstor($template);
     $this->assertNull($template->get('url'));
     $this->assertSame('832414', $template->get('jstor'));
  }
}
