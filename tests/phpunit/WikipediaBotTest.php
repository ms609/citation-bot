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

    public function testFillCache(): void {
        $this->fill_cache();
        $this->assertTrue(true);
    }

    public function testCoverageFixer(): void {
            WikipediaBot::make_ch();
            $this->assertTrue(true);
    }

    public function testCategoryMembers(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(count(WikipediaBot::category_members('Indian drama films')) > 10);
        $this->assertSame(0, count(WikipediaBot::category_members('A category we expect to be empty')));
    }

    public function testRedirects(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame(-1, WikipediaBot::is_redirect('NoSuchPage:ThereCan-tBe'));
        $this->assertSame( 0, WikipediaBot::is_redirect('User:Citation_bot'));
        $this->assertSame( 1, WikipediaBot::is_redirect('WP:UCB'));
        $this->assertSame('User:Citation bot/use', WikipediaBot::redirect_target('WP:UCB'));
    }

    public function testGetLastRevision(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('805321380', WikipediaBot::get_last_revision('User:Blocked testing account/readtest'));
    }

    public function testGetUserName(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $api = new WikipediaBot();
        $this->assertSame('Citation_bot', $api->get_the_user());
    }

    public function testNonStandardMode(): void {
        $this->assertFalse(WikipediaBot::NonStandardMode());
    }

    public function testIsValidUser1(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $result = WikipediaBot::is_valid_user('Smith609');
        $this->assertTrue($result);
    }
    public function testIsValidUser2(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $result = WikipediaBot::is_valid_user('Stanlha'); // Random user who exists but does not have page as of Nov 2017
        $this->assertTrue($result);
    }
    public function testIsValidUser3(): void {
        $result = WikipediaBot::is_valid_user("David(Owner, Founder, Creator and Lead Developer)"); // Random user who has a name with funky characters
        $this->assertTrue($result);
    }
    public function testIsValidUserEmpty(): void {
        $result = WikipediaBot::is_valid_user("");
        $this->assertFalse($result);
    }
    public function testIsINValidUser(): void {
        $result = WikipediaBot::is_valid_user('Not_a_valid_user_at_Dec_2017');
        $this->assertFalse($result);
    }
    public function testIsIPUser(): void {
        $result = WikipediaBot::is_valid_user('178.16.5.186'); // IP address with talk page
        $this->assertFalse($result);
    }
    public function testIsIP6User(): void {
        $result = WikipediaBot::is_valid_user('2602:306:bc8a:21e0:f0d4:b9dc:c050:2b2c'); // IP6 address with talk page
        $this->assertFalse($result);
    }
    public function testIsBlockedUser(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $result = WikipediaBot::is_valid_user('RickK'); // BLOCKED
        $this->assertFalse($result);
    }
    public function testGetLinks(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $json = WikipediaBot::get_links('Covid Watch');
        $this->assertTrue(substr_count($json, 'exists') > 15);
    }

    public function test_ret_okay1(): void {
        $this->assertFalse(WikipediaBot::ret_okay(null));
    }
    public function test_ret_okay2(): void {
        $response = (object) ['error' => (object) ['info' =>    'Hello, The database has been automatically locked so give up']];
        $this->assertFalse(WikipediaBot::ret_okay($response));
    }
    public function test_ret_okay3(): void {
        $response = (object) ['error' => (object) ['info' =>    'Greetings, abusefilter-warning-predatory so give up']];
        $this->assertTrue(WikipediaBot::ret_okay($response));
    }
    public function test_ret_okay4(): void {
        $response = (object) ['error' => (object) ['info' =>    'Greetings, this page is protected so give up']];
        $this->assertTrue(WikipediaBot::ret_okay($response));
    }
    public function test_ret_okay5(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $response = (object) ['error' => (object) ['info' =>    'doggiesandcats']];
        $this->assertFalse(WikipediaBot::ret_okay($response));
    }

    public function test_response2page1(): void {
        $this->assertNull(WikipediaBot::response2page(null));
    }
    public function test_response2page2(): void {
        $response = (object) ['warnings' => (object)['prop' =>  (object) ['*' => 'this is a prop']]];
        $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page3(): void {
        $response = (object) ['warnings' => (object) ['info' =>  (object) ['*' => 'this is an info']]];
        $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page4(): void {
        $response = (object) ['dogs' => (object) ['cats' =>  'this has no batchcomplete']];
        $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page5(): void {
        $response = (object) ['batchcomplete' => 'we did it but have not query'];
        $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page6(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $response = (object) ['batchcomplete' => 'we did it', 'query' => (object) ['pages' => (object) ['0' => (object) ['x' => 'y']]]];
        $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page7(): void {
        $response = (object) ['batchcomplete' => 'we did it', 'query' => (object) ['pages']];
        $pages = [(object) ['lastrevid' => 1, 'revisions' => 1, 'title' => 'x']];
        $pages[0]->revisions = ['0' => (object) ['timestamp' => 1]];
        $response->query->pages= (object) $pages;
        $this->assertNull(WikipediaBot::response2page($response));
    }
    public function test_response2page8(): void {
        $response = (object) ['batchcomplete' => 'we did it', 'query' => (object) ['pages']];
        $pages = [(object) ['lastrevid' => 1, 'revisions' => 1, 'title' => 'x']];
        $pages[0]->revisions = ['0' => (object) ['timestamp' => 1]];
        $response->query->pages= (object) $pages;
        $response->query->tokens = (object) ['csrftoken' => 1];
        $this->assertNotNull(WikipediaBot::response2page($response));
    }

    public function test_resultsGood1(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $result = null;
        $this->assertFalse(WikipediaBot::resultsGood($result));
    }
    public function test_resultsGood2(): void {
        $result = (object) ['edit' => 'nonresult'];
        $this->assertFalse(WikipediaBot::resultsGood($result));
    }
    public function test_resultsGood3(): void {
        $result = (object) ['error' => (object) ['code' => '3', 'info' => 'y']];
        $this->assertFalse(WikipediaBot::resultsGood($result));
    }
    public function test_resultsGood4(): void {
        $result = (object) ['edit' => (object) ['result' => 'failed']];
        $this->assertFalse(WikipediaBot::resultsGood($result));
    }
    public function test_resultsGood5(): void {
        $result = (object) ['edit' => (object) ['result' => 'Success']];
        $this->assertTrue(WikipediaBot::resultsGood($result));
    }

}
