<?php
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

final class apiFunctionsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  
  protected function prepare_citation($text) {
    $template = new Template();
    $template->parse_text($text);
    $template->prepare();
    return $template;
  }
  
  protected function process_citation($text) {
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
  
  protected function requires_secrets($function) {
    if (getenv('TRAVIS_PULL_REQUEST')) {
      echo 'S'; // Skipping test: Risks exposing secret keys
      $this->assertNull(NULL); // Make Travis think we tested something
    } else {
      $function();
    }
  }
  
  public function testAdsabsApi() {
    $this->requires_secrets(function() {
      $bibcodes = [
       '2017NatCo...814879F',
       '1974JPal...48..524M',
       '1996GSAB..108..195R',
       '1966Natur.211..116M',
       '1995Sci...267...77R',
       '1995Geo....23..967E',
       ];
      $text = '{{Cite journal | bibcode = ' . implode('}}{{Cite journal | bibcode = ', $bibcodes) . '}}';
      $page = new TestPage();
      $page->parse_text($text);
      $templates = $page->extract_object('Template');
      $page->expand_templates_from_identifier('bibcode', $templates);
      $this->assertEquals('Nature', $templates[3]->get('journal'));
      $this->assertEquals('Geology', $templates[5]->get('journal'));
      $this->assertEquals('14879', $templates[0]->get('pages'));
    });
  }
}
