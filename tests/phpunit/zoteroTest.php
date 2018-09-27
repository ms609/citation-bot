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
  
  protected function process_page($text) {  // Only used if more than just a citation template
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }
  
  protected function expand_via_zotero($text) {
    $expanded = $this->prepare_citation($text);
    expand_by_zotero($expanded);
    $expanded->tidy();
    return $expanded;
  }

  public function testZoteroExpansionRGJunk() { // The gives a rubbish title that we detect and reject
    $text = '{{Cite journal|url =  https://www.researchgate.net/publication/2344536}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals($expanded->parsed_text(), $text);
  }

  public function testZoteroExpansionRGGood() {
    $text = '{{Cite journal|url =  https://www.researchgate.net/publication/23445361}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals('10.1136/jnnp.2008.144360', $expanded->get('doi'));
  }

  public function testZoteroExpansionPII() {
    $text = '{{Cite journal|url = https://www.sciencedirect.com/science/article/pii/S0024379512004405}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals('10.1016/j.laa.2012.05.036', $expanded->get('doi'));
  }

  public function testZoteroExpansionNBK() {
    $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24662/}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals('Continuing Efforts to More Efficiently Use Laboratory Animals', $expanded->get('title'));
    $this->assertEquals('2004', $expanded->get('year'));
    $this->assertEquals('National Research Council (Us) Committee To Update Science', $expanded->get('last1'));
    $this->assertEquals('Medicine', $expanded->get('first1')); // TODO : recognize this as non-human
  }

  public function testZoteroExpansionNYT() {
    $text = '{{Cite journal|url =https://www.nytimes.com/2018/06/11/technology/net-neutrality-repeal.html}}';
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals("Net Neutrality Has Officially Been Repealed. Here's How That Could Affect You", $expanded->get('title'));
    $this->assertEquals('Keith', $expanded->get('first1')); // Would be tidied to 'first' in final_parameter_tudy
    $this->assertEquals('Collins', $expanded->get('last1'));
    $this->assertEquals('cite newspaper', $expanded->wikiname());
  }
  public function testZoteroExpansionRSRef() {
    $text = '<ref>http://rspb.royalsocietypublishing.org/content/285/1887/20181780</ref>';
    $expanded = $this->process_page($text);
    $this->assertTrue(mb_stripos($expanded->parsed_text(), 'Hyoliths with pedicles illuminate the origin of the brachiopod body plan') !== FALSE);
  }
    
  public function testZoteroExpansionNRM() {
    $text = '{{cite journal | url = http://www.nrm.se/download/18.4e32c81078a8d9249800021554/Bengtson2004ESF.pdf}}';
    $expanded = $this->process_page($text);
    $this->assertTrue(TRUE); // Gives one fuzzy match.  For now we just check that this doesn't crash PHP.
    // In future we should use this match to expand citation.
  }

  public function testDateTidiness() {
    $text = "{{cite web|title= Gelada| website= nationalgeographic.com |url= http://animals.nationalgeographic.com/animals/mammals/gelada/ |publisher=[[National Geographic Society]]|accessdate=7 March 2012}}";
    $expanded = $this->expand_via_zotero($text);
    $this->assertEquals('2011-05-10', $expanded->get('date'));
  }
  
}
