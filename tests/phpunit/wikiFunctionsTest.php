<?php

/*
 * Current tests that are failing.
 */

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
require('login.php');
 
class wikiFunctionsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
  public function testLogin() {
  }
  
  public function testCategoryMembers() {
    $this->assertTrue(count(category_members('Stub-Class cricket articles')) > 10);
  }
  
  public function testWhatTranscludes() {
    $this->assertTrue(count(what_transcludes('Cite journal')) > 10);
  }
  
  public function testGetPrefixIndex() {
    $namespace_name = get_namespace('Template:Cite journal');
    $results = get_prefix_index('Cite ', $namespace=);
    $this->assertTrue(is_int(1 * $results[0]));
  }
  
  public function testRedirects() {
    $this->assertFalse(is_redirect('User:Citation bot'));
    $this->assertTrue(is_redirect('WP:UCB'));
    $this->assertEquals('ffe', redirect_target('WP:UCB'));
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
    print "\n\n! Namespaces are out of date. Please update constants/namespace.php with the below:\n\n";
    print "<?php\nconst NAMESPACES = Array(";
    foreach ($namespaces->query->namespaces as $ns) {
      $ns_name = isset($ns->canonical)? $ns->canonical : '';
      print ("\n  " . (string) $ns->id . " => '" . $ns_name . "',");
    }
    print ");\n\nconst NAMESPACE_ID = Array(";
    foreach ($namespaces->query->namespaces as $ns) {
      $ns_name = isset($ns->canonical)? $ns->canonical : '';
      print ("\n  '" . strtolower($ns_name) . "' => " . (string) $ns->id . ",");
    }
    print "\n);\n?>\n";
    die;
    */
  }
  
  public function testGetLastRevision() {
    $this->assertTrue(is_int(1 * get_last_revision('User talk:Citation bot')));
  }
 
}
