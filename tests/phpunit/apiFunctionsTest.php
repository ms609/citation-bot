<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {  

 public function testUrlReferences3() {
       $page = $this->process_page("{{cite journal|doi=10.1007/s12668-011-0022-5}}");
       $this->assertSame('huh', $page->parsed_text());
  }

}
