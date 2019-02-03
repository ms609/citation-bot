<?php
/*
 * Tests for user_messages.php.  We use a string that would be encoded, not a realistic one
 */
require_once __DIR__ . '/../testBaseClass.php';

final class userMessagesTest extends testBaseClass {
  public function testEchoable() {
    $HTML = HTML_OUTPUT;
    $text = '<[Hello]>';
    define("HTML_OUTPUT", TRUE);
    $this->assertEquals('', echoable($text));
    define("HTML_OUTPUT", FALSE);
    $this->assertEquals('', echoable($text));
    define("HTML_OUTPUT", $HTML);
  }

  public function testPubMed() {
    $HTML = HTML_OUTPUT;
    $text = '<[Hello]>';
    define("HTML_OUTPUT", TRUE);
    $this->assertEquals('', pubmed_link($text, 'pmid'));
    define("HTML_OUTPUT", FALSE);
    $this->assertEquals('', pubmed_link($text, 'pmid'));
    define("HTML_OUTPUT", $HTML);
  }
 
  public function testBibCode() {
    $HTML = HTML_OUTPUT;
    $text = '<[Hello]>';
    define("HTML_OUTPUT", TRUE);
    $this->assertEquals('', bibcode_link($text));
    define("HTML_OUTPUT", FALSE);
    $this->assertEquals('', bibcode_link($text));
    define("HTML_OUTPUT", $HTML);
  }

  public function testDoiLink() {
    $HTML = HTML_OUTPUT;
    $text = '<[Hello]>';
    define("HTML_OUTPUT", TRUE);
    $this->assertEquals('', doi_link($text));
    define("HTML_OUTPUT", FALSE);
    $this->assertEquals('', doi_link($text));
    define("HTML_OUTPUT", $HTML);
  }

  public function testJstorLink() {
    $HTML = HTML_OUTPUT;
    $text = '<[Hello]>';
    define("HTML_OUTPUT", TRUE);
    $this->assertEquals('', jstor_link($text));
    define("HTML_OUTPUT", FALSE);
    $this->assertEquals('', jstor_link($text));
    define("HTML_OUTPUT", $HTML);
  }

}
