<?php

/*
 * Tests for constants.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class constantsTest extends testBaseClass {

  public function testConstantsDefined() {
    $this->assertSame(count(UCFIRST_JOURNAL_ACRONYMS), count(JOURNAL_ACRONYMS));
    for ($i = 0; $i < sizeof(JOURNAL_ACRONYMS); $i++) {
      $this->assertSame(trim(JOURNAL_ACRONYMS[$i]), trim(title_capitalization(ucwords(trim(UCFIRST_JOURNAL_ACRONYMS[$i])), TRUE)));
      // Verify that they are padded with a space
      $this->assertSame   (' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i], -1, 1));
      $this->assertSame   (' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i],  1, 1));
      $this->assertSame   (' ', mb_substr(JOURNAL_ACRONYMS[$i], -1, 1));
      $this->assertSame   (' ', mb_substr(JOURNAL_ACRONYMS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(JOURNAL_ACRONYMS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(JOURNAL_ACRONYMS[$i],  1, 1));
    }
    $this->assertSame(count(LC_SMALL_WORDS), count(UC_SMALL_WORDS));
    for ($i = 0; $i < sizeof(LC_SMALL_WORDS); $i++) {
      // Verify that they match
      if (substr_count(UC_SMALL_WORDS[$i], ' ') === 2 && substr_count(UC_SMALL_WORDS[$i], '&') === 0) {
        $this->assertSame(UC_SMALL_WORDS[$i], mb_convert_case(LC_SMALL_WORDS[$i], MB_CASE_TITLE, "UTF-8"));
      } else {  // Weaker test for things with internal spaces or an & symbol (PHP 7.3 and 5.6 treat & differently)
        $this->assertSame(strtolower(UC_SMALL_WORDS[$i]), strtolower(LC_SMALL_WORDS[$i]));
      }
      // Verify that they are padded with a space
      $this->assertSame   (' ', mb_substr(UC_SMALL_WORDS[$i], -1, 1));
      $this->assertSame   (' ', mb_substr(UC_SMALL_WORDS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(UC_SMALL_WORDS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(UC_SMALL_WORDS[$i],  1, 1)); 
    }
    // Trailing dots and lots of dots....
    $text = "{{Cite journal|journal=Journal of the A.I.E.E.}}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
  
  public function testImplicitConstants() {
    // Consonants
    $this->assertSame('X', title_capitalization('x', TRUE));
    $this->assertSame('Xz', title_capitalization('xz', TRUE));
    $this->assertSame('XZZ BBBB/EEE', title_capitalization('xzz bbbb/eee', TRUE));
    $this->assertSame('XZZZ', title_capitalization('xzzz', TRUE));
    // Mixed
    $this->assertSame('Xzza', title_capitalization('xzza', TRUE));
    // Vowels
    $this->assertSame('AEIOU', title_capitalization('aeiou', TRUE));
    // Y is neither
    $this->assertSame('Aeiouy', title_capitalization('aeiouy', TRUE));
    $this->assertSame('Xzzzy', title_capitalization('xzzzy', TRUE));
    // Relationship Status = It's Complicated :-)
    $this->assertSame('Xzzzy Aeiouy AEIOU and xzzzy Aeiouy AEIOU', title_capitalization('xzzzy Aeiouy aeiou and xzzzy Aeiouy aeiou', TRUE));
    $this->assertSame('Xzzzy Aeiouy AEIOU and Xzzzy Aeiouy AEIOU', title_capitalization(ucwords('xzzzy Aeiouy aeiou and xzzzy Aeiouy aeiou'), TRUE));
  }
  
  public function testConstantsOrder() {
    $acronyms = JOURNAL_ACRONYMS; sort($acronyms, SORT_STRING | SORT_FLAG_CASE);
    $expected = current($acronyms);
    foreach (JOURNAL_ACRONYMS as $actual) {
      $this->assertSame(strtolower($expected), strtolower($actual));
      $expected = next($acronyms);
    }
  }
  
  public function testAllLowerCase() {
    $big_array = array_merge(HAS_NO_VOLUME, BAD_ACCEPTED_MANUSCRIPT_TITLES, BAD_AUTHORS,
                             PUBLISHER_ENDINGS, BAD_TITLES, IN_PRESS_ALIASES, NON_PUBLISHERS,
                             JOURNAL_IS_BOOK_SERIES);
    foreach ($big_array as $actual) {
      $this->assertSame(strtolower($actual), $actual);
    }
  }
  
  public function testAtoZ() {
    $start_alpha = '/* The following will be automatically updated to alphabetical order */';
    $end_alpha = '/* The above will be automatically updated to alphabetical order */';
    $filename = __DIR__ . '/../../constants/capitalization.php';
    $old_contents = file_get_contents($filename);
    $sections = explode($start_alpha, $old_contents);
    foreach ($sections as &$section) {
      $alpha_end = stripos($section, $end_alpha);
      if (!$alpha_end) continue;
      $alpha_bit = substr($section, 0, $alpha_end);
      $alpha_bits = explode(',', $alpha_bit);
      $alpha_bits = array_map('trim', $alpha_bits);
      sort($alpha_bits, SORT_STRING | SORT_FLAG_CASE);
      $bits_length = array_map('strlen', $alpha_bits);
      $bit_length = current($bits_length);
      $chunk_length = 0;
      $new_line = "\n          ";
      $alphaed = $new_line;
      $line_length = 10;
      foreach ($bits_length as $bit_length) {
        $bit = next($alpha_bits);
       $alphaed .= $bit ? ($bit . ', ') : '';
       $line_length += $bit_length + 2;
       if ($line_length > 86) {
         $alphaed .= $new_line;
         $line_length = 10;
        }
      }
      if ($alphaed == $new_line) $alphaed = '';
      $section = $alphaed . substr($section, $alpha_end);
    }
    
    $new_contents = implode($start_alpha, $sections);
    
    if (preg_replace('/\s+/','', $new_contents) == preg_replace('/\s+/','', $old_contents)) {
      $this->assertTrue(TRUE);
    } else {
      ob_flush();
      echo "\n\n" . $filename . " needs alphabetized as follows\n";
      echo $new_contents . "\n\n\n";
      ob_flush();
      $this->assertTrue(FALSE);
    }
  }
}
