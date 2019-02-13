<?php
 // To use the oauthclient library, run:
 // composer require mediawiki/oauthclient
 use MediaWiki\OAuthClient\Consumer;
 use MediaWiki\OAuthClient\Token;
 use MediaWiki\OAuthClient\Request;
 use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;

 final class userOauth {

    private $oauthUrl = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';
    private $apiUrl;
    private $consumerKey = ''; // NEED THIS
    private $consumerSecret = '';  // NEED THIS
    private $username;
    private $editToken;
    private $client;
  
    function __destruct() {
      session_start();
      session_destroy();
    }
  
    function __construct() {
      $conf = new ClientConfig($this->$oauthUrl);
      $conf->setConsumer( new Consumer($this->consumerKey, $this->consumerSecret) );
      $this->client = new Client($conf);
      $this->apiUrl = preg_replace( '/index\.php.*/', 'api.php', $this->oauthUrl);
    }
 
    public function get_username() {
      return $this->username;
    }

    public function get_edit_token() {
      return $this->editToken;
    }

  private function get_token() {
   if ( !isset( $_GET['oauth_verifier'] ) ) {
	echo "This page should only be access after redirection back from the wiki.";
	exit( 1 );
   }
   // Get the Request Token's details from the session and create a new Token object.
   session_start();
   $requestToken = new Token( $_SESSION['request_key'], $_SESSION['request_secret'] );
   // Send an HTTP request to the wiki to retrieve an Access Token.
   $accessToken = $client->complete( $requestToken,  $_GET['oauth_verifier'] );
   // At this point, the user is authenticated, and the access token can be used
   $_SESSION['access_key'] = $accessToken->key;
   $_SESSION['access_secret'] = $accessToken->secret;
   // You also no longer need the Request Token.
   unset( $_SESSION['request_key'], $_SESSION['request_secret'] );
  }
  
  private function use_token() {
    // Load the Access Token from the session.
    session_start();
    $accessToken = new Token( $_SESSION['access_key'], $_SESSION['access_secret'] );
    // get the authenticated user's identity.
    $ident = $client->identify( $accessToken );
    $this->username = $ident->username;
    // get the authenticated user's edit token.
    $this->editToken = json_decode( $client->makeOAuthCall(
	$accessToken,
	"$this->apiUrl?action=query&meta=tokens&format=json"
    ) )->query->tokens->csrftoken;
  }
 
  
  private function authorize token() {
    // Send an HTTP request to the wiki to get the authorization URL and a Request Token.
    // These are returned together as two elements in an array (with keys 0 and 1).
    list( $authUrl, $token ) = $client->initiate();
    // Store the Request Token in the session. We will retrieve it from there when the user is sent back
    // from the wiki (see demo/callback.php).
    session_start();
    $_SESSION['request_key'] = $token->key;
    $_SESSION['request_secret'] = $token->secret;
    // Redirect the user to the authorization URL. This is usually done with an HTTP redirect, but we're
    // making it a manual link here so you can see everything in action.
    echo "Go to this URL to authorize this demo:<br /><a href='$authUrl'>$authUrl</a>"
  }
 }
