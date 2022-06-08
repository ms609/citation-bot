<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
      $result = WikipediaBot::is_valid_user("David(Owner, Founder, Creator and Lead Developer)");
     print_r($result);
  }

 
  public function testBadPage() : void {  // Use this when debugging pages that crash the bot
      $this->assertTrue(FALSE);
  }
}
