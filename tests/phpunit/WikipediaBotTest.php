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
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $this->assertTrue(count($api->category_members('Indian drama films')) > 10);
      $this->assertSame(0, count($api->category_members('A category we expect to be empty')));
     });
    }
    
    public function testWhatTranscludes() : void {
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $this->assertTrue(count($api->what_transcludes('Graphical timeline')) > 10);
     });
    }
      
    public function testGetPrefixIndex() : void {
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $namespace = $api->get_namespace('Template:Cite journal');
      $this->assertSame(WikipediaBot::namespace_id('Template'), $namespace);
      $results = $api->get_prefix_index('Cite jo', $namespace); // too many results if we just use 'Cite'
      $this->assertTrue(array_search('Template:Cite journal', $results) !== FALSE);
      $results = $api->get_prefix_index("If we retrieve anything here, it's an error", $namespace);
      $this->assertTrue(empty($results));
     });
    }
    
    public function testRedirects() : void {
      $this->assertSame(-1, WikipediaBot::is_redirect('NoSuchPage:ThereCan-tBe'));
      $this->assertSame( 0, WikipediaBot::is_redirect('User:Citation_bot'));
      $this->assertSame( 1, WikipediaBot::is_redirect('WP:UCB'));
      $this->assertSame('User:Citation bot/use', WikipediaBot::redirect_target('WP:UCB'));
    }

    public function testNamespaces() : void {
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $vars = array(
            'format' => 'json',
            'action' => 'query',
            'meta'   => 'siteinfo',
            'siprop'  => 'namespaces',
        );
      $namespaces = $api->fetch($vars, 'POST');
      
      if ($namespaces == FALSE) {
        report_error('API failed to return anything for namespaces');
      }
      
      foreach ($namespaces->query->namespaces as $ns) {
        $ns_name = isset($ns->canonical)? $ns->canonical : '';
        $ns_id = $ns->id;
        $this->assertSame($ns_id, WikipediaBot::namespace_id($ns_name));
        $this->assertSame($ns_name, WikipediaBot::namespace_name($ns_id));
      }
      
      /*
      // If the above assertions are throwing an error, you can generate an updated 
      // version of constants/namespace.php by running the below and pasting the content:
      echo "\n\n! Namespaces are out of date. Please update constants/namespace.php with the below:\n\n";
      echo "<?php\nconst NAMESPACES = Array(";
      foreach ($namespaces->query->namespaces as $ns) {
        $ns_name = isset($ns->canonical)? $ns->canonical : '';
        echo ("\n  " . (string) $ns->id . " => '" . $ns_name . "',");
      }
      echo ");\n\nconst NAMESPACE_ID = Array(";
      foreach ($namespaces->query->namespaces as $ns) {
        $ns_name = isset($ns->canonical)? $ns->canonical : '';
        echo ("\n  '" . strtolower($ns_name) . "' => " . (string) $ns->id . ",");
      }
      echo "\n);\n?" . ">\n";
      exit(0);
      */
     });
    }
      
    public function testGetLastRevision() : void {
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $this->assertSame('805321380', $api->get_last_revision('User:Blocked testing account/readtest'));
     });
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
   
    public function testNonStandardWikiBotClass() : void {
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot(TRUE);
      $this->assertTrue(TRUE); // Just verify no crash
     });
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
