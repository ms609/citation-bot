<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

// Initialize bot configuration
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
class PageTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  protected function process_page($text) {
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  public function testPageChangeSummary() {
      $page = $this->process_page('{{cite journal|chapter=chapter name|title=book name}}'); // Change to book from journal
      $this->assertEquals('Alter: template type. You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].',$page->edit_summary());
      $page = $this->process_page('{{cite book||quote=a quote}}'); // Just lose extra pipe
      $this->assertEquals('Misc citation tidying. You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].',$page->edit_summary());
  }

  public function testBotRead() {
    if (getenv('TRAVIS_PULL_REQUEST')) {
      echo 'S'; // Test skipped in pull requests, to protect Bot secrets
      $this->assertNull(NULL); // Make Travis happy
    } else {
      $page = new TestPage();
      $api = new WikipediaBot();
      $page->get_text_from('User:Blocked Testing Account/readtest', $api);
      $this->assertEquals('This page tests bots', $page->parsed_text());
    }
  }
  
  public function testBotExpandWrite() {
    if (getenv('TRAVIS_PULL_REQUEST')) {
      echo 'S'; // Test skipped in pull requests, to protect Bot secrets
      $this->assertNull(NULL); // Make Travis happy
    } else {
      $api = new WikipediaBot();
      $page = new TestPage();
      $writeTestPage = 'User:Blocked Testing Account/writetest';
      $page->get_text_from($writeTestPage, $api);
      $trialCitation = '{{Cite journal | title Bot Testing | ' .
        'doi_broken_date=1986-01-01 | doi = 10.1038/nature09068}}';
      $page->overwrite_text($trialCitation);
      $this->assertTrue($page->write($api, "Testing bot write function"));
      
      $page->get_text_from($writeTestPage, $api);
      $this->assertEquals($trialCitation, $page->parsed_text());
      $page->expand_text();
      $this->assertTrue(strpos($page->edit_summary(), 'journal, ') > 3);
      $this->assertTrue(strpos($page->edit_summary(), ' Removed ') > 3);
      $this->assertTrue($page->write($api));
      
      $page->get_text_from($writeTestPage, $api);
      $this->assertTrue(strpos($page->parsed_text(), 'Nature') > 5);
    }
  }
 
  public function testEmptyPage() {
      $page = $this->process_page('');
      $page = $this->process_page('  ');
      $page = $this->process_page('  move along, nothing to see here ');
      $page = $this->process_page('  move along, nothing to see here {{}} ');
      $this->assertNull(NULL);
  }

  public function testUrlReferences() {
      $page = $this->process_page("URL reference test 1 <ref name='bob'>http://doi.org/10.1007/s12668-011-0022-5< / ref>\n Second reference: \n<ref >  [https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3705692/] </ref> URL reference test 1");
      $this->assertEquals("URL reference test 1 <ref name='bob'>{{Cite journal |doi = 10.1007/s12668-011-0022-5|title = Reoccurring Patterns in Hierarchical Protein Materials and Music: The Power of Analogies|journal = Bionanoscience|volume = 1|issue = 4|pages = 153–161|year = 2011|last1 = Giesa|first1 = Tristan|last2 = Spivak|first2 = David I|last3 = Buehler|first3 = Markus J|arxiv = 1111.5297}}< / ref>\n Second reference: \n<ref >{{Cite journal |pmc = 3705692|year = 2013|last1 = Mahajan|first1 = P. T|title = Indian religious concepts on sexuality and marriage|journal = Indian Journal of Psychiatry|volume = 55|issue = Suppl 2|pages = S256–S262|last2 = Pimple|first2 = P|last3 = Palsetia|first3 = D|last4 = Dave|first4 = N|last5 = De Sousa|first5 = A|pmid = 23858264|doi = 10.4103/0019-5545.105547}}</ref> URL reference test 1", $page->parsed_text());
  }

  public function testUrlReferencesThatFail() {
      $text = 'testUrlReferencesThatFail <ref name="bob">http://this.fails/nothing< / ref> testUrlReferencesThatFail <ref >  http://this.fails/nothing </ref> testUrlReferencesThatFail';
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }
    
  public function testHugePage() {
      $text = @file_get_contents('https://en.wikipedia.org/w/index.php?title=Vietnam_War&action=raw');
      $this->process_page($text);
      $this->assertNull(NULL);
  }

}
