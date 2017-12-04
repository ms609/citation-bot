<?php

// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
if (!defined('VERBOSE')) define('VERBOSE', TRUE);
$SLOW_MODE = TRUE;
 
final class wikiFunctionsTest extends PHPUnit\Framework\TestCase {
  
  private $api;
  protected function setUp() {
    $this->api = new WikipediaBot();
  }

  protected function tearDown() {
    unset($this->api);
  }
  
}
