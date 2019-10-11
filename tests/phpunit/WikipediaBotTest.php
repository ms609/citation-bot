<?php

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
  class WikipediaBotTest extends testBaseClass {

      
    public function testCategoryMembers() {
      $api = new WikipediaBot();
      $this->assertTrue(count($api->category_members('GA-Class cricket articles')) > 10);
      $this->assertSame(0, count($api->category_members('A category we expect to be empty')));
    }


}
