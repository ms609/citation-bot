<?php

/*
 * Tests for Parameter.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class ParameterTest extends testBaseClass {


  public function testValueWithPipeAndTrailingNewline() {
    $text = "{{cite journal |journal=  ''[[Alternative Press (music magazine)|Alternative Press]]''}}";
    $parameter = $this->process_citation($text);
    $this->assertNull($parameter->get('journal'));
  }

 
}



