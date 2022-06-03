<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $this->process_citation('{{Cite web |title= | year=2003 |url=https://patron.library.wisc.edu/authn/splash?url=https://www.oxfordartonline.com/groveart/view/10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-7000083760 |access-date=2022-04-23 |doi=10.1093/gao/9781884446054.article.T083760 | last1=Fort | first1=Ilene Susan | title= Ten American Painters (The Ten)}}');
  }

}
