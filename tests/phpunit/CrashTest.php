<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $this->assertTrue(FALSE);
  }
 
    public function testFinalTidyThings1() : void {
      $text = "{{Cite web|year=|year=2000}}";
      $expanded = $this->process_citation($text);
      echo $expanded->parsed_text();
      $text = "{{Cite web|year=2000|year=2000}}";
      $expanded = $this->process_citation($text);
      echo $expanded->parsed_text();
      $text = "{{Cite web|year|year=2000}}";
      $expanded = $this->process_citation($text);
      echo $expanded->parsed_text();
      $text = "{{Cite web|year|year}}";
      $expanded = $this->process_citation($text);
      echo $expanded->parsed_text();
      $text = "{{Cite web|year|year 2000}}";
      $expanded = $this->process_citation($text);
      echo $expanded->parsed_text();
      $text = "{{Cite web|year 2000|year }}";
      $expanded = $this->process_citation($text);
      echo $expanded->parsed_text();
    }


}
