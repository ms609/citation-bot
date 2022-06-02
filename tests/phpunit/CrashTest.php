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
    var_export(is_doi_works("10.1036/0071422803"));
var_export(is_doi_works("10.6057/2012TCRR01.03")):
var_export(is_doi_works("10.5353/th_b3198232")):
var_export(is_doi_works("10.5353/th_b2952025")):
var_export(is_doi_works("10.5176/2251-1679_cgat17.12")):
var_export(is_doi_works("10.34172/hpp.2020.44")):
var_export(is_doi_works("10.31902/fll.37.2021.4")):
var_export(is_doi_works("10.2979/NWS.1998.10.3.1")):
var_export(is_doi_works("10.2979/NWS.1996.8.3.36")):
var_export(is_doi_works("10.24901/rehs.v33i129.533")):
var_export(is_doi_works("10.2307/1123979")):
var_export(is_doi_works("10.2277/0521586461")):
var_export(is_doi_works("10.2277/0521568544")):
var_export(is_doi_works("10.2277/0521497817")):
var_export(is_doi_works("10.21504/amj.v7i1.1931")):
var_export(is_doi_works("10.21504/amj.v6i2.1118")):
var_export(is_doi_works("10.2134/jeq1975.4124")):
var_export(is_doi_works("10.21236/ada614052")):
var_export(is_doi_works("10.18352/lq.10360")):
var_export(is_doi_works("10.17976/jpps/2019.01.04")):
var_export(is_doi_works("10.1525/ncl.1968.23.3.99p02284")):
var_export(is_doi_works("10.1525/hlq.2004.67.3.457")):
var_export(is_doi_works("10.1525/as.1945.14.24.01p17062")):
var_export(is_doi_works("10.1360/aps050172")):
var_export(is_doi_works("10.1353/nwsa.2004.0077")):
var_export(is_doi_works("10.1258/jrsm.97.6.297")):
var_export(is_doi_works("10.1258/jrsm.95.12.618")):
var_export(is_doi_works("10.1210/jc.82.10.3213")):
var_export(is_doi_works("10.1210/endo-meetings.2010.PART1.OR.OR08-5")):
var_export(is_doi_works("10.1175/0065-9401(2003)029<0001:CTRODJ>2.0.CO;2")):
var_export(is_doi_works("10.1139/gen-43-5-827")):
var_export(is_doi_works("10.1139/gen-42-4-668")):
var_export(is_doi_works("10.1139/cjz-78-12-2126")):
var_export(is_doi_works("10.1042/csb0001001")):
var_export(is_doi_works("10.1036/0071422803")):
var_export(is_doi_works("10.1007/BF00207213")):
  }

}
