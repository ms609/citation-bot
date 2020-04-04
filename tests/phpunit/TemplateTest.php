<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
  public function testParameterWithNoParameters() {
    $text = "{{Cite encyclopedia|last=Häussinger |first=Peter |last2=Glatthaar |first2=Reiinhard |last3=Rhode |first3=Wilhelm |last4=Kick |first4=Helmut |last5=Benkmann |first5=Christian |last6=Weber |first6=Josef |last7=Wunschel |first7=Hans-Jörg |llast8=Stenke |first8=Viktor |last9=Leicht |first9=Edith |last10=Stenger |first10=Hermann |title=Noble Gases |doi=10.1002/14356007.a17_485 |date=15 March 2001 |work=[[Ullmann's Encyclopedia of Industrial Chemistry]] |editor-first=Barbara |editor-last=Elvers |display-editors=etal |edition=7th |publisher=Wiley-VCH |language=en |isbn=978-3-527-32943-4 |volume=24 |at=sec. 9.  }}";
    $expanded = $this->process_citation($text);
    $this->assert($expanded->parsed_text());
  }

  
 
}
