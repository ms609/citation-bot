<?php
 // To use the oauthclient library, run:
 // composer require mediawiki/oauthclient
 use MediaWiki\OAuthClient\Consumer;
 use MediaWiki\OAuthClient\Token;
 use MediaWiki\OAuthClient\Request;
 use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;

 final class userOauth {
  
  protected function logout() {
    session_start();
    session_destroy();
  }
 
 }
