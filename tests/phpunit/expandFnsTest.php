<?php

/*
 * Current tests that are failing.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
 
use PHPUnit\Framework\TestCase;

class expandFnsTest extends TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  public function testCapitalization() {
    $this->assertEquals('Molecular and Cellular Biology', title_capitalization(title_case('Molecular and cellular biology')));
    $this->assertEquals('z/Journal', title_capitalization(title_case('z/Journal')));
    $this->assertEquals('The Journal of Journals', title_capitalization('The Journal Of Journals')); // The, not the
  }
  
}
