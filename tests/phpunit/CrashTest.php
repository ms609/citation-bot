<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {
 
   public function testblockCI() : void {
    Zotero::create_ch_zotero();
    WikipediaBot::make_ch();
  }

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    var_export(is_doi_works("10.3403/bsiso10294"));
    var_export(is_doi_works("10.21236/ada614052"));
    var_export(is_doi_works("10.3403/bsiso10294"));
    var_export(is_doi_works("10.1038/scientificamerican0302-76"));
    var_export(is_doi_works("10.22478/ufpb.1809-4783.2020v30n3.52231"));
    var_export(is_doi_works("10.3174/ajnr.A2583"));
    var_export(is_doi_works("10.5373/JARDCS/V12SP5/20201874"));
    var_export(is_doi_works("10.7861/clinmedicine.7-6-562"));
  }

}
