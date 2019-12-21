<?php

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
  class WikipediaBotTest extends testBaseClass {
      

      
    public function testCategoryMembers() {

      $api = new WikipediaBot();
      print_r($api->category_members('GA-Class cricket articles of Low-importance'));
      print_r($api->category_members('A category we expect to be empty'));
      $this->assertTrue(count($api->category_members('GA-Class cricket articles of Low-importance')) > 10);
      $this->assertSame(0, count($api->category_members('A category we expect to be empty')));

    }
    
   
}
