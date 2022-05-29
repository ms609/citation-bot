<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBlankOtherThanComments() : void {
    $text_in = "{{cite journal| title=<!-- comment1 -->  <!-- comment2 -->| journal= | issue=3 <!-- comment3 -->| volume=65 |lccn= <!-- comment4 --> cow <!-- comment5 --> }}";
    $page = $this->process_page($text_in); // Have to do this so that comments stay as comments
    $template = (Template) Template::$all_templates[0];
    echo $template->parse_text();
    $this->assertTrue($template->blank_other_than_comments('isbn'));
    $this->assertTrue($template->blank_other_than_comments('title'));
    $this->assertTrue($template->blank_other_than_comments('journal'));
    $this->assertFalse($template->blank_other_than_comments('issue'));
    $this->assertFalse($template->blank_other_than_comments('volume'));
    $this->assertFalse($template->blank_other_than_comments('lccn'));
  }
}
