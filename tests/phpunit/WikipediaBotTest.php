<?php

/*
 * Tests for WikipediaBot.php
 */

require_once __DIR__ . '/../testBaseClass.php';
require_once __DIR__ . '/../../WikipediaBot.php';

  class WikipediaBotTest extends testBaseClass {
      
    public function testLoggedInUser() {
     $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $this->assertEquals("Citation bot test", $api->username());
     });
    }
      
    public function testCategoryMembers() {
     $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $this->assertTrue(count($api->category_members('GA-Class cricket articles')) > 10);
      $this->assertEquals(0, count($api->category_members('A category we expect to be empty')));
     });
    }
    
    public function testWhatTranscludes() {
     $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $this->assertTrue(count($api->what_transcludes('Graphical timeline')) > 10);
     });
    }
      
    public function testGetPrefixIndex() {
     $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $namespace = $api->get_namespace('Template:Cite journal');
      $this->assertEquals($api->namespace_id('Template'), $namespace);
      $results = $api->get_prefix_index('Cite jo', $namespace); // too many results if we just use 'Cite'
      $this->assertTrue(array_search('Template:Cite journal', $results) !== FALSE);
      $results = $api->get_prefix_index("If we retrieve anything here, it's an error", $namespace);
      $this->assertTrue(empty($results));
     });
    }
    
    public function testRedirects() {
     $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $this->assertEquals(-1, $api->is_redirect('NoSuchPage:ThereCan-tBe'));
      $this->assertEquals( 0, $api->is_redirect('User:Citation_bot'));
      $this->assertEquals( 1, $api->is_redirect('WP:UCB'));
      $this->assertEquals('User:Citation bot/use', $api->redirect_target('WP:UCB'));
     });
    }
    
    public function testNamespaces() {
     $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $vars = array(
            'format' => 'json',
            'action' => 'query',
            'meta'   => 'siteinfo',
            'siprop'  => 'namespaces',
        );
      $namespaces = $api->fetch($vars, 'POST');
      
      foreach ($namespaces->query->namespaces as $ns) {
        $ns_name = isset($ns->canonical)? $ns->canonical : '';
        $ns_id = (string) $ns->id;
        $this->assertEquals($ns_id, $api->namespace_id($ns_name));
        $this->assertEquals($ns_name, $api->namespace_name($ns_id));
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
      
    public function testGetLastRevision() {
     $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $this->assertEquals(805321380, 1 * $api->get_last_revision('User:Blocked testing account/readtest'));
     });
    }
}
