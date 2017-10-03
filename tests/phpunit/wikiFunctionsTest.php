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
 
class wikiFunctionsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
    require('login.php');
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
 
}
