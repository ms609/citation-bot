<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testBLOCKMERGE() {
    $this->assertSame('MERGE', 'NO');
  }

  
 
}
