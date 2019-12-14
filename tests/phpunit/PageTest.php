<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

     public function testCategoryMembers() {
      $this->assertTrue(count($api->category_members('GA-Class cricket articles of Low-importance')) > 10);
      $this->assertSame(0, count($api->category_members('A category we expect to be empty')));
     }
    public function testCategoryMembers2() {
      $api = new WikipediaBot();
      $this->assertTrue(count($api->category_members('GA-Class cricket articles of Low-importance')) > 10);
      $this->assertSame(0, count($api->category_members('A category we expect to be empty')));
    }
 
  public function testMultiArxiv() {
      $page = $this->process_page('Arxiv stuff');
      $this->assertNull($page->parsed_text());
  }
 
 
}
