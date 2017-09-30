<?php

/*
 * Tests for constants.php.
 */
 
 // backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}


class constantsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }

  public function testConstantsDefined() {
    $this->assertEquals(PIPE_PLACEHOLDER, '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
    for ($i = 0; $i < sizeof(JOURNAL_ACRONYMS); $i++) {
      $this->assertEquals(UCFIRST_JOURNAL_ACRONYMS[$i], mb_convert_case(JOURNAL_ACRONYMS[$i], MB_CASE_TITLE, "UTF-8"));
    }
    for ($i = 1; $i < sizeof(LC_SMALL_WORDS); $i++) { ## Not 0, which is "and Then"
      $this->assertEquals(UC_SMALL_WORDS[$i], mb_convert_case(LC_SMALL_WORDS[$i], MB_CASE_TITLE, "UTF-8"));
    }
  }
  
  public function testDoiRegExp() {
    preg_match(DOI_REGEXP, 'http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract', $match);
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', $match[1]);
    preg_match(DOI_REGEXP, ' 10.1016/j.physletb.2010.03.064', $match);
    $this->assertEquals('10.1016/j.physletb.2010.03.064', $match[1]);
  }

}
