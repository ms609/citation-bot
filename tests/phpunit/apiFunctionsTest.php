<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  

  
  public function testArxivDateUpgradeSeesDate() {
      $text = '{{cite journal|author = Yasushi Komori |author2 = Kohji Matsumoto  |author3 = Hirofumi Tsumura|title = Hyperbolic-sine analogues of Eisenstein series, generalized Hurwitz numbers, and q-zeta functions|year = 2010}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('not null', $expanded->parsed_text());
  }

}
