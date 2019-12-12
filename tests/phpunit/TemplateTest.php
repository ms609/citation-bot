<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testParameterWithNoParameters() {
    $text = "{{cite journal|last1=Rowan|first1=Cris|title=Unplug-Don't Drug: A Critical Look at the Influence of Technology on Child Behavior with an Alternative Way of Responding other than Evaluation and Drugging|date=2010|volume=12|page=61|doi=10.1891/1559-4343.12.1.60}}";
    $expanded = $this->process_citation($text);
    $this->assertSame('COOL', $expanded->parsed_text());
  }

  
}
