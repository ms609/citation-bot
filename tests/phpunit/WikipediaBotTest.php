<?php
declare(strict_types=1);

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
  final class WikipediaBotTest extends testBaseClass {

    protected function setUp(): void {
     if (BAD_PAGE_API !== '') {
       $this->markTestSkipped();
     }
    }

    public function testLoggedInUser() : void {
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $this->assertSame("Citation bot test", $api->bot_account_name());
     });
    }
      
    public function testCategoryMembers() : void {
      $this->assertTrue(count(WikipediaBot::category_members('Indian drama films')) > 10);
      $this->assertSame(0, count(WikipediaBot::category_members('A category we expect to be empty')));
    }
    
    public function testRedirects() : void {
      $this->assertSame(-1, WikipediaBot::is_redirect('NoSuchPage:ThereCan-tBe'));
      $this->assertSame( 0, WikipediaBot::is_redirect('User:Citation_bot'));
      $this->assertSame( 1, WikipediaBot::is_redirect('WP:UCB'));
      $this->assertSame('User:Citation bot/use', WikipediaBot::redirect_target('WP:UCB'));
    }
      
    public function testGetLastRevision() : void {
      $this->assertSame('805321380', WikipediaBot::get_last_revision('User:Blocked testing account/readtest'));
    }
   
    public function testGetUserName() : void {
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot(); // Make sure one exists
      $this->assertSame('Citation_bot', $api->get_the_user());
     });
    }
   
    public function testNonStandardMode() : void {
      $this->assertFalse(WikipediaBot::NonStandardMode());
    }

    public function testIsValidUser() : void {
      $result = WikipediaBot::is_valid_user('Smith609');
      $this->assertSame(TRUE, $result);
      $result = WikipediaBot::is_valid_user('Stanlha'); // Random user who exists but does not have page as of Nov 2017
      $this->assertSame(TRUE, $result);
    }
    public function testIsINValidUser() : void {
      $result = WikipediaBot::is_valid_user('Not_a_valid_user_at_Dec_2017'); 
      $this->assertSame(FALSE, $result);
    }
    public function testIsIPUser() : void {
      $result = WikipediaBot::is_valid_user('178.16.5.186'); // IP address with talk page
      $this->assertSame(FALSE, $result);
    }
    public function testIsIP6User() : void {
      $result = WikipediaBot::is_valid_user('2602:306:bc8a:21e0:f0d4:b9dc:c050:2b2c'); // IP6 address with talk page
      $this->assertSame(FALSE, $result);
    }
    public function testIsBlockedUser() : void {
      $result = WikipediaBot::is_valid_user('RickK'); // BLOCKED
      $this->assertSame(FALSE, $result);
    }
}
