<?php

/*
 * Tests for DOITools.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class doiToolsTest extends testBaseClass {
  public function testFormat1() {
    $this->assertEquals('& a. Johnson', format_surname('& A. Johnson'));
  }
}
