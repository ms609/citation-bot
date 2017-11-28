<?php

/*
 * Current tests that are failing.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
require('login.php');
 
final class wikiFunctionsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
/*  
  public function testLogin() {
  }
  */
  public function testCategoryMembers() {
    $this->assertTrue(count(category_members('Stub-Class cricket articles')) > 10);
  }
  
  public function testWhatTranscludes() {
    $this->assertTrue(count(what_transcludes('Cite journal')) > 10);
  }
    
  public function testGetPrefixIndex() {
    $namespace = get_namespace('Template:Cite journal');
    $this->assertEquals(namespace_id('Template'), $namespace);
    $results = get_prefix_index('Cite j', $namespace); // too many results if we just use 'Cite'
    $this->assertTrue(array_search('Template:Cite journal', $results) !== FALSE);
    $results = get_prefix_index('blah blah blah blah blah Martin', $namespace); // if we get anything thats wrong
    $this->assertTrue(empty($results));
  }
  
  public function testRedirects() {
    $this->assertEquals(-1, is_redirect('NoSuchPage:ThereCan-tBe')[0]);
    $this->assertEquals(0, is_redirect('User:Citation_bot')[0]);
    $this->assertEquals(1, is_redirect('WP:UCB')[0]);
    
    // TODO fix article_id before restoring this test:
    #$this->assertEquals(article_id(redirect_target('WP:UCB')), is_redirect('WP:UCB')[1]);
  }  
    
  public function testWrite() {
    $bot = new Snoopy();
    $page = new page();
    $result = $page->get_text_from('User:AManWithNoPlan/bot_test_page');
    $this->assertNotNull($result);
    $page->expand_text();
    $result = $page->write();
    $this->assertEquals(FALSE, $result);  // test uses blocked account
    $bot = new Snoopy();
    $page = new page();
    $result = $page->get_text_from('dsafasdfdsfa34f34fsfrasdfdsafsdfasddsafadsafsdfasfd');
    $this->assertNotNull($result);
    $page->expand_text();
    $result = $page->write();
    $this->assertEquals(FALSE, $result);  // Rubbish page
 }
  
  public function testNamespaces() {
    $bot = new Snoopy();
    $bot->httpmethod="POST";
    $vars = array(
          'format' => 'json',
          'action' => 'query',
          'meta'   => 'siteinfo',
          'siprop'  => 'namespaces',
      );
    $bot->submit(API_ROOT, $vars);
    $namespaces = json_decode($bot->results);
    
    foreach ($namespaces->query->namespaces as $ns) {
      $ns_name = isset($ns->canonical)? $ns->canonical : '';
      $ns_id = (string) $ns->id;
      $this->assertEquals($ns_id, namespace_id($ns_name));
      $this->assertEquals($ns_name, namespace_name($ns_id));
    }
    
    /*
    // If the above assertions are throwing an error, you can generate an updated 
    // version of constants/namespace.php by running the below and pasting the content:
    echo "\n\n! Namespaces are out of date. Please update constants/namespace.php with the below:\n\n";
    echo "<?php\nconst NAMESPACES = Array(";
    foreach ($namespaces->query->namespaces as $ns) {
      $ns_name = isset($ns->canonical)? $ns->canonical : '';
      echo ("\n  " . (string) $ns->id . " => '" . $ns_name . "',");
    }
    echo ");\n\nconst NAMESPACE_ID = Array(";
    foreach ($namespaces->query->namespaces as $ns) {
      $ns_name = isset($ns->canonical)? $ns->canonical : '';
      echo ("\n  '" . strtolower($ns_name) . "' => " . (string) $ns->id . ",");
    }
    echo "\n);\n?>\n";
    exit(0);
    */
  }
  
  public function testIsValidUser() {
      $result = is_valid_user('Smith609');
      $this->assertEquals(TRUE, $result);
      $result = is_valid_user('Stanlha'); // Random user who at this point (Nov 2017) does not have page, but does exists
      $this->assertEquals(TRUE, $result);
      $result = is_valid_user('Stfasdfdsfadsfadsfdsfdsfadsfsafdsfadsfdsafanlha'); // Random user who at this point (Nov 2017) does not exist
      $this->assertEquals(FALSE, $result);
  }
    
  public function testGetLastRevision() {
    $this->assertTrue(is_int(1 * get_last_revision('User talk:Citation bot')));
  }
   
    // DOItools tests
  
  public function testFormatMultipleAuthors1() {
    $authors = 'M.A. Smith, Smith M.A., Smith MA., Martin A. Smith, MA Smith, Martin Smith'; // unparsable gibberish formatted in many ways--basically exists to check for code changes
    $result=format_multiple_authors($authors,FALSE);
    $this->assertEquals('m.a. Smith, Smith M.A.; Smith, M.A.; Martin A. Smith, M.A. Smith', $result);
  }
  public function testFormatMultipleAuthors2() {  // Semi-colon
    $authors = 'M.A. Smith; M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertEquals('Smith, M.A.; Smith, M.A.', $result);
  }
  public function testFormatMultipleAuthors3() { // Spaces
    $authors = 'M.A. Smith  M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertEquals('Smith, M.A.; Smith, M.A.', $result);
  }
  public function testFormatMultipleAuthors4() { // Commas
    $authors = 'M.A. Smith,  M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertEquals('Smith, M.A.; Smith, M.A.', $result);
  }
 
  public function testFormatAuthor1() {  
    $author = "Conway Morris S.C.";
    $result=format_author($author,FALSE);
    $this->assertEquals('Conway Morris, S.C.', $result); // Was c, Conway Morris S 
  }
  public function testFormatAuthor2() {  
    $author = "M.A. Smith";
    $result=format_author($author,FALSE);
    $this->assertEquals('Smith, M.A', $result);
  }
  public function testFormatAuthor3() {  
    $author = "Smith M.A.";
    $result=format_author($author,FALSE);
    $this->assertEquals('Smith, M.A.', $result); // Was a, Smith M
  }
  public function testFormatAuthor4() {  
    $author = "Smith MA.";
    $result=format_author($author,FALSE);
    $this->assertEquals('Smith, M.A.', $result);
  }
  public function testFormatAuthor5() {  
    $author = "Martin A. Smith";
    $result=format_author($author,FALSE);
    $this->assertEquals('Smith, Martin A', $result);
  }
  public function testFormatAuthor6() {  
    $author = "MA Smith";
    $result=format_author($author,FALSE);
    $this->assertEquals('Smith, M.A.', $result);
  }
  public function testFormatAuthor7() {  
    $author = "Martin Smith";
    $result=format_author($author,FALSE);
    $this->assertEquals('Smith, Martin', $result);
  }
  public function testFormatAuthor8() {  
    $author = "Conway Morris S.C..";
    $result=format_author($author,FALSE);
    $this->assertEquals('Conway Morris, S.C.', $result); //Was c, Conway Morris S
  }

  public function testCurlSetup() {
    $ch = curl_init();
    $url = "http://www.apple.com/";
    curl_setup($ch, $url);
    $this->assertNull(NULL); // Just looking for code coverage and access of unset variables, etc.
  }

  public function testPageIsRedirect() {
    $page = new page();
    $page->title = 'WP:UCB';
    $this->assertEquals(1, $page->is_redirect()[0]); // Different code than non-class function is_redirect($text)
  }
  public function testPageTextFromTitle() { // Not a great test. Mostly just verifies no crashes in code
    $page = new page();
    $result = $page->get_text_from('User:Citation_bot');
    $this->assertNotNull($result);
  }
  public function testEditSummary() {  // Not a great test. Mostly just verifies no crashes in code
    $page = new Page();
    $text = "{{Cite journal|pmid=9858586}}";
    $page->parse_text($text);
    $page->expand_text();
    $this->assertNotNull($page->edit_summary());
  }
  
  public function testMathInTitle() {
    // This MML code comes from a real CrossRef search of DOI 10.1016/j.newast.2009.05.001
    // $text_math is the correct final output
    $text_math = 'Spectroscopic analysis of the candidate <math><mrow>β</mrow></math> Cephei star <math><mrow>σ</mrow></math> Cas: Atmospheric characterization and line-profile variability';
    $text_mml  = 'Spectroscopic analysis of the candidate <mml:math altimg="si37.gif" overflow="scroll" xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.elsevier.com/xml/ja/dtd" xmlns:ja="http://www.elsevier.com/xml/ja/dtd" xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tb="http://www.elsevier.com/xml/common/table/dtd" xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd" xmlns:ce="http://www.elsevier.com/xml/common/dtd" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"><mml:mrow><mml:mi>β</mml:mi></mml:mrow></mml:math> Cephei star <mml:math altimg="si38.gif" overflow="scroll" xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.elsevier.com/xml/ja/dtd" xmlns:ja="http://www.elsevier.com/xml/ja/dtd" xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tb="http://www.elsevier.com/xml/common/table/dtd" xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd" xmlns:ce="http://www.elsevier.com/xml/common/dtd" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"><mml:mrow><mml:mi>σ</mml:mi></mml:mrow></mml:math> Cas: Atmospheric characterization and line-profile variability';
    $this->assertEquals($text_math,sanitize_string($text_math));      // Should not change
    $this->assertEquals($text_math,wikify_external_text($text_math)); // Should not change
    $this->assertEquals($text_math,wikify_external_text($text_mml));  // The most important test: mml converstion to <math>
  }
    
}
