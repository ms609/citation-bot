<?php

/*
 * Tests for expandFns.php.
 */

class ParameterTest extends PHPUnit_Framework_TestCase {

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
  
  public function testUseUnusedData() {
    $text = "{{Cite web | http://google.com | editor1link=test | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6 }}"
    $parsed_text = $this->template_parse_text_helper($text);
    $this->assertEquals($parsed_text->expand_text(), '{{Cite web | url = http://google.com | editor1-link=test | title =  I am a title | author = Other, A. N. | issue = 9 | volume = 22 | pages = 5-6 }}');
  }
  public function testJstorExpansion() {
    $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true}}"
    $parsed_text = $this->template_parse_text_helper($text);
    $this->assertEquals($parsed_text->expand_text(), '{{Cite journal| jstor=1701972}}'); // It's not "expand_text" that we're testing here.
  }
  public function testPmidExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}"
    $parsed_text = $this->template_parse_text_helper($text);
    $this->assertEquals($parsed_text->expand_text(), '{{Cite journal| pmid=1941451}}'); // It's not "expand_text" that we're testing here.
  }
  public function testPMCExpansion() {
    $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}"
    $parsed_text = $this->template_parse_text_helper($text);
    $this->assertEquals($parsed_text->expand_text(), '{{Cite journal|pmc = 154623}}'); // It's not "expand_text" that we're testing here.
  }
  public function testArxivExpansion() {
    $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}"
    $parsed_text = $this->template_parse_text_helper($text);
    $this->assertEquals($parsed_text->expand_text(), '{{Cite journal|arxiv = 0806.0013}}'); // It's not "expand_text" that we're testing here.
  }
  public function testAmazonExpansion() {
    $text = "{{Cite web | http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20}}"
    $parsed_text = $this->template_parse_text_helper($text);
    $this->assertEquals($parsed_text->expand_text(), '{{Cite book|asin = 0226845494}}'); // It's not "expand_text" that we're testing here.
  }
  public function testDoiExpansion() {
    $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}"
    $parsed_text = $this->template_parse_text_helper($text);
    $this->assertEquals($parsed_text->expand_text(), '{{Cite journal | doi=10.1111/j.1475-4983.2012.01203.x}}'); // It's not "expand_text" that we're testing here.
  }

}
