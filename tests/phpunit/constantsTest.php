<?php

/*
 * Tests for constants.php.
 */
error_reporting(E_ALL);
 // backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}


final class constantsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }

  public function testConstantsDefined() {
    $this->assertEquals(count(UCFIRST_JOURNAL_ACRONYMS), count(JOURNAL_ACRONYMS));
    for ($i = 0; $i < sizeof(JOURNAL_ACRONYMS); $i++) {
      $this->assertEquals(UCFIRST_JOURNAL_ACRONYMS[$i], mb_convert_case(JOURNAL_ACRONYMS[$i], MB_CASE_TITLE, "UTF-8"));
    }
    $this->assertEquals(count(LC_SMALL_WORDS), count(UC_SMALL_WORDS));
    for ($i = 0; $i < sizeof(LC_SMALL_WORDS); $i++) {
      // Verify that they match
      if (substr_count(UC_SMALL_WORDS[$i], ' ') === 2) {
        $this->assertEquals(UC_SMALL_WORDS[$i], mb_convert_case(LC_SMALL_WORDS[$i], MB_CASE_TITLE, "UTF-8"));
      } else {  // Weaker test for things with internal spaces
        $this->assertEquals(strtolower(UC_SMALL_WORDS[$i]), strtolower(LC_SMALL_WORDS[$i]));
      }
      // Verify that they are padded with a space
      $this->assertEquals   (' ', mb_substr(UC_SMALL_WORDS[$i], -1, 1));
      $this->assertEquals   (' ', mb_substr(UC_SMALL_WORDS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(UC_SMALL_WORDS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(UC_SMALL_WORDS[$i],  1, 1)); 
    }
  }
  
  public function testConstantsOrder() {
    $acronyms = JOURNAL_ACRONYMS; sort($acronyms, SORT_STRING | SORT_FLAG_CASE);
    $expected = current($acronyms);
    foreach (JOURNAL_ACRONYMS as $actual) {
      $this->assertEquals(strtolower($expected), strtolower($actual));
      $expected = next($acronyms);
    }
  }
}
