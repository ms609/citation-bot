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
 
    public function testDropDuplicates3() : void {
      $text = '{{citation|year=2000|year=}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());

      $text = '{{citation|year=|year=2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
   
      $text = '{{citation|year= | year= |year=| year=|year=2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
   
      $text = '{{citation|year=2000|year=2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
   
      $text = '{{citation|year 2000|year=2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
   
      $text = '{{citation|year=|year 2000|year=2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
   
      $text = '{{citation|year 2000|year=|year=2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
   
      $text = '{{citation|year=2000|year 2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());

      $text = '{{citation|year=2000|year=2000|year 2000|year=|year=2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
   
      $text = '{{citation|year=2000|year=2001|year=2000|year=2001|year=2000}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|DUPLICATE_year=2000|DUPLICATE_year=2001|DUPLICATE_year=2000|DUPLICATE_year=2001|year=2000}}', $prepared->parsed_text());
  
      $text = "{{Cite web|year=|year=2000}}";
      $expanded = $this->process_citation($text);
      $this->assertSame('{{Cite web|year=2000}}', $expanded->parsed_text());

      $text = "{{Cite web|year=2000|year=2000}}";
      $expanded = $this->process_citation($text);
      $this->assertSame('{{Cite web|year=2000}}', $expanded->parsed_text());

      $text = "{{Cite web|year|year=2000}}";
      $expanded = $this->process_citation($text);
      $this->assertSame('{{Cite web|year|year=2000}}', $expanded->parsed_text());

      $text = "{{Cite web|year|year}}";
      $expanded = $this->process_citation($text);
      $this->assertSame('{{Cite web|year|year}}', $expanded->parsed_text());

      $text = "{{Cite web|year|year 2000}}";
      $expanded = $this->process_citation($text);
      $this->assertSame('{{Cite web|year| year=2000 }}', $expanded->parsed_text());

      $text = "{{Cite web|year 2000|year }}";
      $expanded = $this->process_citation($text);
      $this->assertSame('{{Cite web|year | year=2000 }}', $expanded->parsed_text());
     
      $text = '{{citation|year=2000|year=||||||||||||||||||||||||||||||||||||||||}}';
      $prepared = $this->process_citation($text);
      $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }

}
