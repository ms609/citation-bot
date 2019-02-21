<?php

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
  class WikipediaBotTest extends testBaseClass {
    
    public function testRedirects() {
      $api = new WikipediaBot();
      $this->assertEquals('User:Citation_bot', $api->redirect_target('WP:UCB'));
    }

}
