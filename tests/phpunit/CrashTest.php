<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
    $t = $this->process_citation('{{cite journal   | last = Tencer   | first = John   | author2 = Forsberg, Kelsey Meeks   | title =  Postprocessing techniques for gradient percolation predictions on the square lattice   | journal = Phys. Rev. E    | volume = 103   | issue = 1   | year = 2021   | pages = 012115   | doi =  10.1103/PhysRevE.103.012115| pmid = 33601521  | bibcode = 2021PhRvE.103a2115T  | osti = 1778027  | s2cid = 231961701  }}');
  }

}
