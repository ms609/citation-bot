<?php

/*
 * Tests for constants.php.
 */
 
 // backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

function arxiv_callable_error_handler($errno,$errstr,$errfile,$errline) {
      if ($errno === 1024 && $errstr === "API Error in query_adsabs: Unauthorized" && getenv('TRAVIS')) {
          echo "\n -API Error in query_adsabs: Unauthorized";
          return TRUE;
      } elseif ($errno === 1024 && $errstr === "Error in query_adsabs: Could not decode AdsAbs response" && getenv('TRAVIS')) {
          echo "\n -Error in query_adsabs: Could not decode AdsAbs response";
          return TRUE;
      } else {
          echo "\n STRING IS " . $errstr ;
          echo "\n ERRNUM IS " . $errno ;
          return FALSE;
      }
}
final class constantsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
     set_error_handler("arxiv_callable_error_handler");
  }
  protected function tearDown() {
     set_error_handler(NULL);
  }
  

  public function testConstantsDefined() {
    $this->assertEquals(PIPE_PLACEHOLDER, '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #');
    for ($i = 0; $i < sizeof(JOURNAL_ACRONYMS); $i++) {
      $this->assertEquals(UCFIRST_JOURNAL_ACRONYMS[$i], mb_convert_case(JOURNAL_ACRONYMS[$i], MB_CASE_TITLE, "UTF-8"));
    }
    for ($i = 1; $i < sizeof(LC_SMALL_WORDS); $i++) { ## Not 0, which is "and Then"
      $this->assertEquals(UC_SMALL_WORDS[$i], mb_convert_case(LC_SMALL_WORDS[$i], MB_CASE_TITLE, "UTF-8"));
    }
  }
  
}
