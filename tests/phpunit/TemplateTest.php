<?php
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

 
final class TemplateTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
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

  public function testParameterWithNoParameters() {
    $text = '{{Cite web | testy1= | testy2=}}';
    $expanded = $this->process_citation($text);
    $expanded->rename('testy2','testy1');
    $this->assetNull($expanded->parsed_text());
  }
}
