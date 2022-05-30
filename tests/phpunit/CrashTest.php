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
      $text = "{{Cite web|title=Stuff|chapter=More Stuff}}";
      $expanded = $this->make_citation($text);
      $expanded->final_tidy();
      $this->AssertSame('cite book', $expanded->wikiname());

      $text = "{{Cite web|title=Stuff|chapter=More Stuff|series=X|journal=Y}}";
      $expanded = $this->make_citation($text);
      $expanded->final_tidy();
      $this->AssertSame('cite book', $expanded->wikiname());

      $text = "{{Cite web|title=Stuff|chapter=More Stuff|journal=Y}}";
      $expanded = $this->make_citation($text);
      $expanded->final_tidy();
      $this->AssertSame('cite book', $expanded->wikiname());

      $text = "{{Cite web|title=Stuff|chapter=More Stuff|series=X}}";
      $expanded = $this->make_citation($text);
      $expanded->final_tidy();
      $this->AssertSame('cite book', $expanded->wikiname());
    }


}
