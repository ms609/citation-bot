<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  
   public function testJstor() {
     $text = "{{cite arXiv|eprint=2001.01484}}";
     $template = $this->process_citation($text);
     $this->assertNull($template->get('title'));
   }
     
   public function testFAIL() {
     $this->assertFalse("DO NOT COMMIT");
  }

}
