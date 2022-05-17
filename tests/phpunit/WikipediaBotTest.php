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
   
    public function testCoverageFixer() : void {
       WikipediaBot::make_ch();
       $this->assertTrue(TRUE);
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
      $api = new WikipediaBot();
      $this->assertSame('Citation_bot', $api->get_the_user());
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
    public function testGetLinks() : void {
      $json = WikipediaBot::get_links('Covid Watch');
      $this->assertTrue(substr_count($json, 'exists') > 15);
    }
   
    public function test_ret_okay1() : void {
      $this->assertFalse(WikipediaBot::ret_okay(NULL));
    }
    public function test_ret_okay2() : void {
      $response = (object) array('error' => (object) array('info' =>  'Hello, The database has been automatically locked so give up'));
      $this->assertFalse(WikipediaBot::ret_okay($response));
    }
    public function test_ret_okay3() : void {
      $response = (object) array('error' => (object) array('info' =>  'Greetings, abusefilter-warning-predatory so give up'));
      $this->assertTrue(WikipediaBot::ret_okay($response));
    }
    public function test_ret_okay4() : void {
      $response = (object) array('error' => (object) array('info' =>  'Greetings, this page is protected so give up'));
      $this->assertTrue(WikipediaBot::ret_okay($response));
    }
    public function test_ret_okay5() : void {
      $response = (object) array('error' => (object) array('info' =>  'weak'));
      $this->assertFalse(WikipediaBot::ret_okay($response));
    }
   
    public function test_response2page1() : void {
      $this->assertNull(WikipediaBot::response2page(NULL));
    }
    public function test_response2page2() : void {
      $response = (object) array('warnings' => (object) array('prop' =>  (object) array('*' => 'this is a prop')));
      $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page3() : void {
      $response = (object) array('warnings' => (object) array('info' =>  (object) array('*' => 'this is an info')));
      $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page4() : void {
      $response = (object) array('dogs' => (object) array('cats' =>  'this has no batchcomplete'));
      $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page5() : void {
      $response = (object) array('batchcomplete' => 'we did it but have not query');
      $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page6() : void {
      $response = (object) array('batchcomplete' => 'we did it', 'query' => (object) array('pages' => (object) array('0' => (object) array('x' => 'y'))));
      $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page7() : void {
      $response = (object) array('batchcomplete' => 'we did it', 'query' => (object) array('pages'));
      $response->query->pages= array((object) array('lastrevid' => 1, 'revisions' => 1, 'title' => 'x'));
      $response->query->pages[0]->revisions = array('0' => (object) array('timestamp' => 1));
      $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page8() : void {
      $response = (object) array('batchcomplete' => 'we did it', 'query' => (object) array('pages'));
      $response->query->pages= array((object) array('lastrevid' => 1, 'revisions' => 1, 'title' => 'x'));
      $response->query->pages[0]->revisions = array('0' => (object) array('timestamp' => 1));
      $response->query->tokens = (object) array('csrftoken' => 1);
      $this->assertNotNull(WikipediaBot::response2page($response));
    }
                                     
   public function test_resultsGood() : void {
      $result = NULL;
      $this->assertFalse(WikipediaBot::resultsGood($result));
    
      $result = (object) array ('edit' => 'nonresult');
      $this->assertFalse(WikipediaBot::resultsGood($result));

      $result = (object) array ('error' => (object) array('code' => '3', 'info' => 'y'));
      $this->assertFalse(WikipediaBot::resultsGood($result));

      $result = (object) array ('edit' => (object) array('result' => 'failed'));
      $this->assertFalse(WikipediaBot::resultsGood($result));
    
      $result = (object) array ('edit' => (object) array('result' => 'Success'));
      $this->assertTrue(WikipediaBot::resultsGood($result));
  }
   
}
