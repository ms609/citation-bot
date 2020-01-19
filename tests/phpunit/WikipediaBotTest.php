<?php

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
  class WikipediaBotTest extends testBaseClass {
      

    
    public function testRedirects() {
      $api = new WikipediaBot();
      $this->assertSame( 0, WikipediaBot::is_redirect('Kray_twins', $api));

    }

    
}
