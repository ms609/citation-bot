<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testAdsabsApi() {
      $text = '{{Cite journal | bibcode = 2018MNRAS.tmp.2192I}}';
      $page = new TestPage();
      $page->parse_text($text);
      $templates = $page->extract_object('Template');
      $page->expand_templates_from_identifier('bibcode', $templates);
      $this->assertNull($this->parsed_text());
  }
  
 
}
