<?php
declare(strict_types=1);


   
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;
/*
 * Tests for WikipediaBot.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
  class WikipediaBotTest extends testBaseClass {
   
    public function testA1() : void {
     new Consumer(getenv('PHP_OAUTH_CONSUMER_TOKEN'), getenv('PHP_OAUTH_CONSUMER_SECRET'));
     $this->assertTrue(TRUE);
    }
    public function testA2() : void {
     new Consumer(FALSE, getenv('PHP_OAUTH_CONSUMER_SECRET'));
     $this->assertTrue(TRUE);
    }
    public function testA3() : void {
     new Consumer(getenv('PHP_OAUTH_CONSUMER_TOKEN'), FALSE);
     $this->assertTrue(TRUE);
    }
    public function testA4() : void {
     new Consumer(FALSE, FALSE);
     $this->assertTrue(TRUE);
    }
    public function testA5() : void {
     new Consumer('', getenv('PHP_OAUTH_CONSUMER_SECRET'));
     $this->assertTrue(TRUE);
    }
    public function testA6() : void {
     new Consumer(getenv('PHP_OAUTH_CONSUMER_TOKEN'), '');
     $this->assertTrue(TRUE);
    }
    public function testA7() : void {
     new Consumer('', '');
     $this->assertTrue(TRUE);
    }

    public function testB1() : void {
     new Token(getenv('PHP_OAUTH_ACCESS_TOKEN'), getenv('PHP_OAUTH_ACCESS_SECRET'));
     $this->assertTrue(TRUE);
    }
    public function testB2() : void {
     new Token(FALSE, getenv('PHP_OAUTH_ACCESS_SECRET'));
     $this->assertTrue(TRUE);
    }
    public function testB3() : void {
     new Token(getenv('PHP_OAUTH_ACCESS_TOKEN'), FALSE);
     $this->assertTrue(TRUE);
    }
    public function testB4() : void {
     new Token(FALSE, FALSE);
     $this->assertTrue(TRUE);
    }
    public function testB5() : void {
     new Token('', getenv('PHP_OAUTH_ACCESS_SECRET'));
     $this->assertTrue(TRUE);
    }
    public function testB6() : void {
     new Token(getenv('PHP_OAUTH_ACCESS_TOKEN'), '');
     $this->assertTrue(TRUE);
    }
    public function testB7() : void {
     new Token('','');
     $this->assertTrue(TRUE);
    }
   
}
