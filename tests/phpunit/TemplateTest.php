<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {
 
 
  public function testGetDoiFromCrossref() {
     $text = '{{cite journal | title = Interaction of casein kinase 1 delta (CK1 delta) with the light chain LC2 of microtubule associated protein 1A (MAP1A)| bibcode = <!-- NO -->  | pmid = <!-- NO --> | doi = 10.1016/j.bbamcr.2005.05.004 }}';
     $expanded = $this->process_citation($text);
  }
  
  
 
}
