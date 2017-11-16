<?php
/*
 * Tests of DOI tools
 */
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
final class DOItoolsTest extends PHPUnit\Framework\TestCase {
  protected function setUp() {
  }
  protected function tearDown() {
  }
  
public function testFormatMultipleAuthors() {
  $authors = 'M.A. Smith, Smith M.A., Smith MA., Martin A. Smith, MA Smith, Martin Smith';
  $result=format_multiple_authors($authors,FALSE);
  $this->assertNull($result);  // Not sure what it will be 
}

public function testFormatAuthor() {
  $authors = "Conway Morris S.C.";
  $result=format_author($authors,FALSE);
  $this->assertNull($result);  // Not sure what it will be 
}

public function testCurlSetup() {
  $ch = curl_init();
  $url = "http://www.apple.com/";
  curl_setup($ch, $url);
  $this->assertNull(NULL); // Just looking for segmentation faults
}

}
