<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
   public function testNameStuff() {
     $text = '{{cite journal|author1=[[Robert Jay Charlson|Charlson]] |first1=R. J.}}';
     $template = $this->process_citation($text);
     $this->assertSame('Robert Jay Charlson', $template->get('author1-link'));
     $this->assertSame('Charlson', $template->get('last1'));
     $this->assertSame('R. J.', $template->get('first1'));
     $this->assertNull($template->get('author1'));
  }
  public function testCrossRefAddEditors() {
     $this->assertSame("Kopera", "");
  }

}
