<?php

/*
 * Tests for DOITools.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class doiToolsTest extends testBaseClass {
  public function testFormat1() {
    $this->assertEquals('Johsnon ', wikify_external_text('Johsnon&nbsp;'));
    $this->assertEquals('[Johsnon And me]', title_capitalization('[Johsnon And me]'));
    $this->assertEquals('ABC', remove_comments('A<!-- -->B# # # CITATION_BOT_PLACEHOLDER_COMMENT 33 # # #C'));
    $this->assertEquals('', tidy_date('22/22/2010'));  // That is not valid date code
    $this->assertEquals('The date is 88 but not three', tidy_date('The date is 88 but not three')); // The give up code
  }
}
