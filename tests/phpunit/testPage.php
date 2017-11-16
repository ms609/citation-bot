<?php
/*
 * Tests of Page()
 */
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

final class testPage extends PHPUnit\Framework\TestCase {
  protected function setUp() {
  }
  protected function tearDown() {
  }
  
public function testPageRedirect() {
    $page = new page();
    $page->title = 'WP:UCB';
    $this->assertEquals(1, $page->is_redirect()[0]);
}

public function testPageTextFromTitle() { // Not a great test. Mostly just verifies no crashes in code
    if(!isset($bot)) $bot = new Snoopy();
    $page = new page();
    $result = $page->get_text_from('User:Citation_bot');
    $this->assertNotNull($result);
}

public function testEditSummary() {  // Not a great test. Mostly just verifies no crashes in code
    if(!isset($bot)) $bot = new Snoopy();
    $text = "{{Cite journal|pmid=9858586}}"
    $page->parse_text($text);
    $page->expand_text();
    $this->assertNotNull($page->edit_summary());
}

}
