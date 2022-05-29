<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBlankOtherThanComments() : void {
    $template = new Template();
    $text_in = "{{cite journal| title= # # # CITATION_BOT_PLACEHOLDER_COMMENT 1 # # #  # # # CITATION_BOT_PLACEHOLDER_COMMENT 2 # # # | journal= | issue=3 # # # CITATION_BOT_PLACEHOLDER_COMMENT 3 # # #| volume=65 |lccn= # # # CITATION_BOT_PLACEHOLDER_COMMENT 4 # # # cow # # # CITATION_BOT_PLACEHOLDER_COMMENT 5 # # # }}";
    $page = $this->make_citation($text_in);
    $this->assertTrue($template->blank_other_than_comments('isbn'));
    $this->assertTrue($template->blank_other_than_comments('title'));
    $this->assertTrue($template->blank_other_than_comments('journal'));
    $this->assertFalse($template->blank_other_than_comments('issue'));
    $this->assertFalse($template->blank_other_than_comments('volume'));
    $this->assertFalse($template->blank_other_than_comments('lccn'));
  }
}
