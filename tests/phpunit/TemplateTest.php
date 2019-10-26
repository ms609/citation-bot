<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testParameterWithNoParameters() {
    $text = "{{Cite journal|title = Managerial Turnover and Leverage under a Takeover Threat|journal = The Journal of Finance|date = 2002-12-01|issn = 1540-6261|pages = 2619–2650|volume = 57|issue = 6|doi = 10.1111/1540-6261.00508|first = Walter|last = Novaes|hdl = 10419/186646}}";
    $expanded = $this->process_citation($text);
    $this->assertSame("WRONG", $expanded->parsed_text());

  }

 
}
