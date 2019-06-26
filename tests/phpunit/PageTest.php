<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

 
  public function testEmptyPage() {
      $page = $this->process_page('{{cite journal | url=https://www.sciencedirect.com/science/article/pii/004019519400203L| title=Source parameters of the Anjar earthquake of July 21, 1956, India, and its seismotectonic implications for the Kutch rift basin | author=Chung W.-Y. & Gao H. | journal=Tectonophysics | year=1995 | volume=242 | issue=3–4 | pages=281–292 | doi=10.1016/0040-1951(94)00203-L | bibcode=1995Tectp.242..281C }}
');
      $this->assertNull($page->parsed_text());
  }

}
