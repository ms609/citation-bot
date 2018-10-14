<?php

require_once __DIR__ . '/../testBaseClass.php';
 
final class wikiFunctionsTest extends testBaseClass {
  

 h
public function testHugePage() {
     $text = file_get_contents('https://en.wikipedia.org/w/index.php?title=Bram_van_Leer&action=raw');
     $page = new TestPage();
     $page->parse_text($text);
     $page->expand_text();
   }
 
  
}
