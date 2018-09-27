<?php

/*
 * Tests for api_handlers/zotero.php, called from expandFns.php.
 */
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

// Initialize bot configuration
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
class ZoteroTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  protected function prepare_citation($text) {
    $this->assertEquals('{{', mb_substr($text, 0, 2));
    $this->assertEquals('}}', mb_substr($text, -2));
    $template = new Template();
    $template->parse_text($text);
    $template->prepare();
    return $template;
  }
  
  protected function process_citation($text) {
    $this->assertEquals('{{', mb_substr($text, 0, 2));
    $this->assertEquals('}}', mb_substr($text, -2));
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    $expanded_text = $page->parsed_text();
    $template = new Template();
    $template->parse_text($expanded_text);
    return $template;
  }
  
  protected function zotero_a_citation($text) {
    $template = $this->prepare_citation($text);
    expand_by_zotero($template);
    return $template;
  }

  protected function process_page($text) {  // Only used if more than just a citation template
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }
 
  public function testZoteroExpansionRGJunk() {
    $text = '{{Cite journal|url =  https://www.researchgate.net/publication/2344536}}';
    $expanded = $this->zotero_a_citation($text);
    $this->assertEquals($expanded->parsed_text(), $text);
  }

  public function testZoteroExpansionRGGood() {
    $text = '{{Cite journal|url =  https://www.researchgate.net/publication/23445361}}';
    $expanded = $this->zotero_a_citation($text);
    $this->assertEquals('10.1136/jnnp.2008.144360', $expanded->get('doi'));
  }

  public function testZoteroExpansionPII() {
    $text = '{{Cite journal|url = https://www.sciencedirect.com/science/article/pii/S0024379512004405}}';
    $expanded = $this->zotero_a_citation($text);
    $this->assertEquals('10.1016/j.laa.2012.05.036', $expanded->get('doi'));
  }

  public function testZoteroExpansionJstorBook() {
    $text = '{{Cite journal|url=https://www.jstor.org/stable/j.ctt6wp6td.10?seq=9#metadata_info_tab_contents}}';
    $expanded = $this->zotero_a_citation($text);
    $this->assertEquals($expanded->parsed_text(), $text);
  }

  public function testZoteroExpansionNBK() {
    $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24662/}}';
    $expanded = $this->zotero_a_citation($text);
    $this->assertEquals('Continuing Efforts to More Efficiently Use Laboratory Animals', $expanded->get('title'));
    $this->assertEquals('2004', $expanded->get('year'));
    $this->assertEquals('National Research Council (Us) Committee To Update Science', $expanded->get('last1'));
    $this->assertEquals('Medicine', $expanded->get('first')); // TODO : recognize this
  }

  public function testZoteroExpansionNYT() {
    $text = '{{Cite journal|url =https://www.nytimes.com/2018/06/11/technology/net-neutrality-repeal.html}}';
    $expanded = $this->zotero_a_citation($text);
    $expanded->tidy();
    $this->assertEquals("Net Neutrality Has Officially Been Repealed. Here's How That Could Affect You", $expanded->get('title'));
    $this->assertEquals('Keith', $expanded->get('first1')); // Would be tidied to 'first' in final_parameter_tudy
    $this->assertEquals('Collins', $expanded->get('last1'));
    $this->assertEquals('cite newspaper', $expanded->wikiname());
  }
  public function testZoteroExpansionRSRef() {
    $text = '<ref>http://rspb.royalsocietypublishing.org/content/285/1887/20181780</ref>';
    $expanded = $this->process_page($text);
    $this->assertTrue(strpos($expanded->parsed_text(), 'Hyoliths with pedicles illuminate the origin of the brachiopod body plan') !== FALSE);
    
    $text = '{{cite journal | url = http://www.nrm.se/download/18.4e32c81078a8d9249800021554/Bengtson2004ESF.pdf}}';
    $expanded = $this->process_page($text);
    $this->assertTrue(TRUE); // Gives one fuzzy match.  For now we just check that this doesn't crash PHP.
    // In future we should use this match to expand citation.
  }

}
