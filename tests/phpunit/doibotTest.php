<?php

/*
 * Tests for doibot.php.
 * When fixing a bug, enter the problematic citation as a new test case.
 * This will ensure that the bug does not recur.
 */

 // backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
 
class doibotTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
    if (!defined("PIPE_PLACEHOLDER")) {
// this is usually done elsewhere in the code
      define("PIPE_PLACEHOLDER", '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
    }
  }

  protected function tearDown() {
  }
  
  protected function template_parse_text_helper($text) {
    $page = new Page();
    $page->parse_text($text);
    return $page;
  }
  
  public function testJournalCapitalization() {
    $text = "{{Cite journal
      | pmid = 9858585 }}";
    $parsed_text = $this->template_parse_text_helper($text);
    $this->assertEquals($parsed_text->expand_text(), '{{Cite journal
      | pmid = 9858585
      | year = 1999
      | author1 = Gudas
      | first1 = J. M.
      | title = Cyclin E2, a novel G1 cyclin that binds Cdk2 and is aberrantly expressed in human cancers
      | journal = Molecular and Cellular Biology
      | volume = 19
      | issue = 1
      | pages = 612–22
      | last2 = Payton
      | first2 = M
      | last3 = Thukral
      | first3 = S
      | last4 = Chen
      | first4 = E
      | last5 = Bass
      | first5 = M
      | last6 = Robinson
      | first6 = M. O.
      | last7 = Coats
      | first7 = S
      | pmc = 83919 }}');
  }
}
