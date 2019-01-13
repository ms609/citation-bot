<?php

/*
 * Tests for DOITools.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class doiToolsTest extends testBaseClass {
  public function testFormat1() {
    $this->assertEquals('& a. Johnson', format_surname('& A. Johnson'));
  }
  public function testFormat2() {
    $this->assertEquals('Johnson; Smith', format_surname('Johnson; Smith'));
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
    $this->assertEquals('Johnson, A. B. C. D. E. F. G', format_author('A. B. C. D. E. F. G. Johnson'));
   }
  public function testFormat7() {
    $this->assertEquals(['John, .','Bob, .','Kim, .','Billy,'], format_multiple_authors('John;Bob;Kim;Billy', TRUE));
  } 
}
