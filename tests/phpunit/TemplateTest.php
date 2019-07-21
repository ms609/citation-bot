<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testParameterWithNoParameters() {
    $text = "{{Cite web|url=https://pubs.acs.org/page/ancham/submission/prior.html|title=Policy Summary on Prior Publication|website=pubs.acs.org}}";
    $expanded = $this->process_citation($text);
    $this->assertNull($expanded->parsed_text());
  }

 }
