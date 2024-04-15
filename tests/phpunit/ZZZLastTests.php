<?php
declare(strict_types=1);

/*
 * Final Test
 */
require_once __DIR__ . '/../testBaseClass.php';

final class ZZZLastTests extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }

  public function testFillCache() : void {
    $this->fill_cache();
    $this->assertTrue(TRUE);
  }

  public function testFlushCacheAtEnd() : void {
    HandleCache::free_memory();
    AdsAbsControl::free_memory();
    $this->assertTrue(TRUE);
  }
}
