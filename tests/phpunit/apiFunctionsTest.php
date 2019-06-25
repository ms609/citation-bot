<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testAdsabsApi() {
    $template = new Template();
    $template->parse_text('{{Cite journal | bibcode = 2018MNRAS.tmp.2192I}}');
    $template->expand_by_adsabs();
    $this->assertNull($template->parsed_text());
  }
  
 
}
