<?php

/*
 * Current tests that are failing.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

if (!function_exists(arxiv_callable_error_handler)) {
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
}
final class expandFnsTest extends PHPUnit\Framework\TestCase {
  protected function setUp() {
     set_error_handler("arxiv_callable_error_handler");
  }
  protected function tearDown() {
     set_error_handler(NULL);
  }
  
  
  public function testCapitalization() {
    $this->assertEquals('Molecular and Cellular Biology', title_capitalization(title_case('Molecular and cellular biology')));
    $this->assertEquals('z/Journal', title_capitalization(title_case('z/Journal')));
    $this->assertEquals('The Journal of Journals', title_capitalization('The Journal Of Journals')); // The, not the
  }

  public function testDoiRegExp() {
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
    $this->assertEquals('10.1016/j.physletb.2010.03.064', extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
  }  
}
