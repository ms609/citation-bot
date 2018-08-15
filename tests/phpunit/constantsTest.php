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
    $this->assertEquals(PIPE_PLACEHOLDER, '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #');
    for ($i = 0; $i < sizeof(JOURNAL_ACRONYMS); $i++) {
      $this->assertEquals(UCFIRST_JOURNAL_ACRONYMS[$i], mb_convert_case(JOURNAL_ACRONYMS[$i], MB_CASE_TITLE, "UTF-8"));
    }
    $text_in  = 'Start ';
    $text_out = 'Start ';
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
      $text_out = $text_out . LC_SMALL_WORDS[$i];
      $text_in  = $text_in  . UC_SMALL_WORDS[$i];
    }
    // Test that they all work in one giant title
    $text_in  = $text_in  . ' Ending';
    $text_out = $text_out . ' Ending';
    $text = '{{cite journal|journal=$text_in}}';
    $expanded = $this->process_citation($text);
    $this->assertEquals($text_out, $expanded->get('journal')); 
  }
  
}
