<?php

/*
 * Tests for DOITools.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class doiToolsTest extends testBaseClass {
  public function testFormat1() {
    $this->assertEquals('Johnson', format_surname('& Johnson'));
  }
  public function testFormat2() {
    $this->assertEquals('Johnson', format_surname('Johnson;Smith'));
  }
  public function testFormat3() {
    $this->assertEquals(FALSE, format_author(''));
  }
  public function testFormat4() {
    $this->assertEquals(FALSE, format_multiple_authors(''));
  }
  public function testFormat5() {
    $this->assertEquals('John;Bob;Kim;Billy', format_multiple_authors('John,Bob,Kim,Billy'));
  }
  public function testFormat6() {
    $this->assertEquals('A. B. C. D. E. F. G. Johnson', format_author('A. B. C. D. E. F. G. Johnson'));
   }
  public function testFormat7() {
    $this->assertEquals(['John','Bob','Kim','Billy'], format_multiple_authors('John;Bob;Kim;Billy'));
  } 
}
