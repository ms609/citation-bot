<?php

/*
 * Tests for constants.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class constantsTest extends testBaseClass {

  public function testConstantsDefined() {
    $this->assertEquals(count(UCFIRST_JOURNAL_ACRONYMS), count(JOURNAL_ACRONYMS));
    for ($i = 0; $i < sizeof(JOURNAL_ACRONYMS); $i++) {
      $this->assertEquals(trim(JOURNAL_ACRONYMS[$i]), trim(title_capitalization(ucwords(trim(UCFIRST_JOURNAL_ACRONYMS[$i])), TRUE)));
      // Verify that they are padded with a space
      $this->assertEquals   (' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i], -1, 1));
      $this->assertEquals   (' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i],  1, 1));
      $this->assertEquals   (' ', mb_substr(JOURNAL_ACRONYMS[$i], -1, 1));
      $this->assertEquals   (' ', mb_substr(JOURNAL_ACRONYMS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(JOURNAL_ACRONYMS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(JOURNAL_ACRONYMS[$i],  1, 1));
    }
    $this->assertEquals(count(LC_SMALL_WORDS), count(UC_SMALL_WORDS));
    for ($i = 0; $i < sizeof(LC_SMALL_WORDS); $i++) {
      // Verify that they match
      if (substr_count(UC_SMALL_WORDS[$i], ' ') === 2 && substr_count(UC_SMALL_WORDS[$i], '&') === 0) {
        $this->assertEquals(UC_SMALL_WORDS[$i], mb_convert_case(LC_SMALL_WORDS[$i], MB_CASE_TITLE, "UTF-8"));
      } else {  // Weaker test for things with internal spaces or an & symbol (PHP 7.3 and 5.6 treat & differently)
        $this->assertEquals(strtolower(UC_SMALL_WORDS[$i]), strtolower(LC_SMALL_WORDS[$i]));
      }
      // Verify that they are padded with a space
      $this->assertEquals   (' ', mb_substr(UC_SMALL_WORDS[$i], -1, 1));
      $this->assertEquals   (' ', mb_substr(UC_SMALL_WORDS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(UC_SMALL_WORDS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(UC_SMALL_WORDS[$i],  1, 1)); 
    }
    // Trailing dots and lots of dots....
    $text = "{{Cite journal|journal=Journal of the A.I.E.E.}}";
    $expanded = $this->process_citation($text);
    $this->assertEquals($text, $expanded->parsed_text());
  }
  
  public function testImplicitConstants() {
    // Consonants
    $this->assertEquals('X', title_capitalization('x', TRUE));
    $this->assertEquals('Xz', title_capitalization('xz', TRUE));
    $this->assertEquals('XZZ', title_capitalization('xzz', TRUE));
    $this->assertEquals('XZZZ', title_capitalization('xzzz', TRUE));
    // Mixed
    $this->assertEquals('Xzza', title_capitalization('xzza', TRUE));
    // Vowels
    $this->assertEquals('AEIOU', title_capitalization('aeiou', TRUE));
    // Y is neither
    $this->assertEquals('Aeiouy', title_capitalization('aeiouy', TRUE));
    $this->assertEquals('Xzzzy', title_capitalization('xzzzy', TRUE));
    // Relationship Status = It's Complicated :-)
    $this->assertEquals('Xzzzy Aeiouy AEIOU and xzzzy Aeiouy AEIOU', title_capitalization('xzzzy Aeiouy aeiou and xzzzy Aeiouy aeiou', TRUE));
    $this->assertEquals('Xzzzy Aeiouy AEIOU and Xzzzy Aeiouy AEIOU', title_capitalization(ucwords('xzzzy Aeiouy aeiou and xzzzy Aeiouy aeiou'), TRUE));
  }
  
  public function testConstantsOrder() {
    $acronyms = JOURNAL_ACRONYMS; sort($acronyms, SORT_STRING | SORT_FLAG_CASE);
    $expected = current($acronyms);
    foreach (JOURNAL_ACRONYMS as $actual) {
      $this->assertEquals(strtolower($expected), strtolower($actual));
      $expected = next($acronyms);
    }
  }
  
  public function testAllLowerCase() {
    $big_array = array_merge(HAS_NO_VOLUME, BAD_ACCEPTED_MANUSCRIPT_TITLES, BAD_AUTHORS,
                             PUBLISHER_ENDINGS, BAD_TITLES, IN_PRESS_ALIASES, NON_PUBLISHERS);
    foreach ($big_array as $actual) {
      $this->assertEquals(strtolower($actual), $actual);
    }
  }
}
