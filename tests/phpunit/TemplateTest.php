<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {


  
  public function testHandles1() {
    $template = new Template();
    $template->parse_text('{{Cite journal|url=http://hdl.handle.net/10125/20269}}');
    $template->get_identifiers_from_url();
    $this->assertSame('10125/20269', $template->get('hdl'));
    $this->assertNull($template->get('url'));
  }
  public function testHandles2() {
    $template = new Template();
    $template->parse_text('{{Cite journal|url=https://hdl.handle.net/handle/10125/20269}}');
    $template->get_identifiers_from_url();
    $this->assertSame('10125/20269', $template->get('hdl'));
    $this->assertNull($template->get('url'));
  }
  public function testHandles3() {
    $template = new Template();
    $template->parse_text('{{Cite journal|url=http://digitallibrary.amnh.org/dataset.xhtml?persistentId=hdl:10125/20269;jsessionid=EE3BA49390611FCE0AAAEBB819E777BC?sequence=1}}');
    $template->get_identifiers_from_url();
    $this->assertSame('10125/20269', $template->get('hdl'));
    $this->assertNull($template->get('url'));
  }
}
