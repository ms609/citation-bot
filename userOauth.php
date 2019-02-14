<?php
 // To use the oauthclient library, run:
 // composer require mediawiki/oauthclient
 use MediaWiki\OAuthClient\Consumer;
 use MediaWiki\OAuthClient\Token;
 use MediaWiki\OAuthClient\Request;
 use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;
 use MediaWiki\OAuthClient\ClientConfig;
 use MediaWiki\OAuthClient\Client;

 final class userOauth {

   private $oauthUrl = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';
   private $apiUrl   = 'https://meta.wikimedia.org/w/api.php';
   private $username;
   private $editToken;
   private $client;
  
   function __destruct() {
   }
  
   function __construct() {
      $conf = new ClientConfig($this->$oauthUrl);
      $conf->setConsumer(new Consumer(getenv('PHP_OAUTH_CONSUMER_TOKEN'), getenv('PHP_OAUTH_CONSUMER_SECRET')));  // Is this correct, or do we need a new token?
      $this->client = new Client($conf);
      if (isset( $_GET['oauth_verifier'] ) ) {
         $this->get_token();
      } else {
         $this->authorize_token();
      }
   }
 
   public function get_username() {
      return $this->username;
   }

   public function get_edit_token() {
      return $this->editToken;
   }

   private function get_token() {
     // Get the Request Token's details from the session and create a new Token object.
     $requestToken = new Token( $_SESSION['request_key'], $_SESSION['request_secret'] );
     // Send an HTTP request to the wiki to retrieve an Access Token.
     $accessToken = $client->complete( $requestToken,  $_GET['oauth_verifier'] );
     // At this point, the user is authenticated, and the access token can be used
     $_SESSION['access_key'] = $accessToken->key;
     $_SESSION['access_secret'] = $accessToken->secret;
     //   get the authenticated user's identity.
     $ident = $client->identify( $accessToken );
     $this->username = $ident->username;
     // get the authenticated user's edit token.
     $this->editToken = json_decode( $client->makeOAuthCall(
	$accessToken,
	"$this->apiUrl?action=query&meta=tokens&format=json"
     ) )->query->tokens->csrftoken;
     unset( $_SESSION['request_key'], $_SESSION['request_secret'] ); // No longer needed
   }
  
   private function authorize_token() {
    // Send an HTTP request to the wiki to get the authorization URL and a Request Token.
    // These are returned together as two elements in an array (with keys 0 and 1).
    list( $authUrl, $token ) = $client->initiate();
    // Store the Request Token in the session. We will retrieve it from there when the user is sent back from the wiki
    $_SESSION['request_key'] = $token->key;
    $_SESSION['request_secret'] = $token->secret;
    // Redirect the user to the authorization URL.
    header("Location: $authUrl"); // Automatic
    echo "<br />Go to this URL to authorize Citation Bot:<br /><a href='$authUrl'>$authUrl</a>"; // Manual too
    exit();
   }
 }
