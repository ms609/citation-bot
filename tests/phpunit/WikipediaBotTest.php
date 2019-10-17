<?php

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
  class WikipediaBotTest extends testBaseClass {

    
    public function testRedirects() {
     $this->requires_secrets(function() {
      $api = new WikipediaBot();
      global $last_WikipediaBot;
      $api = $last_WikipediaBot;
      $this->assertSame(-1, $api->is_redirect('NoSuchPage:ThereCan-tBe'));
      $this->assertSame( 0, $api->is_redirect('User:Citation_bot'));
      $this->assertSame( 1, $api->is_redirect('WP:UCB'));
      $this->assertSame( 0, $api->is_redirect('The_Journal_of_Physical_Chemistry_A'));
      $this->assertSame( 0, $api->is_redirect('The Journal of Physical Chemistry A'));
      $this->assertSame('User:Citation bot/use', $api->redirect_target('WP:UCB'));
     });
    }
    

}
