<?php
 // To use the oauthclient library, run:
 // composer require mediawiki/oauthclient
 use MediaWiki\OAuthClient\Consumer;
 use MediaWiki\OAuthClient\Token;
 use MediaWiki\OAuthClient\Request;
 use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;

 final class userOauth {

    private $oauthUrl = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';
    private $consumerKey = ''; 
    private $consumerSecret = '';
  
    public function logout() {
      session_start();
      session_destroy();
    }
  
  public function callback() {
   if ( !isset( $_GET['oauth_verifier'] ) ) {
	echo "This page should only be access after redirection back from the wiki.";
	exit( 1 );
   }

   $conf = new ClientConfig($this->$oauthUrl);
   $conf->setConsumer( new Consumer($this->consumerKey, $this->consumerSecret) );
   $client = new Client( $conf );
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
  
  public function api_request() {
   // Get the wiki URL and OAuth consumer details from the config file.
    $apiUrl = preg_replace( '/index\.php.*/', 'api.php', $this->oauthUrl);
    // Configure the OAuth client with the URL and consumer details.
    $conf = new ClientConfig($this->oauthUrl);
    $conf->setConsumer( new Consumer($this->consumerKey, $this->consumerSecret) );
    $client = new Client( $conf );
    // Load the Access Token from the session.
    session_start();
    $accessToken = new Token( $_SESSION['access_key'], $_SESSION['access_secret'] );
    // Example 1: get the authenticated user's identity.
    $ident = $client->identify( $accessToken );
    echo "You are authenticated as $ident->username.\n\n";
    // Example 2: do a simple API call.
    $userInfo = json_decode( $client->makeOAuthCall(
	$accessToken,
	"$apiUrl?action=query&meta=userinfo&uiprop=rights&format=json"
    ) );
    echo "== User info ==\n\n";
    print_r( $userInfo );
    // Example 3: make an edit (getting the edit token first).
    $editToken = json_decode( $client->makeOAuthCall(
	$accessToken,
	"$apiUrl?action=query&meta=tokens&format=json"
    ) )->query->tokens->csrftoken;
    $apiParams = [
	'action' => 'edit',
	'title' => 'User:' . $ident->username,
	'section' => 'new',
	'summary' => 'Hello World',
	'text' => 'I am learning to use the <code>mediawiki/oauthclient</code> library.',
	'token' => $editToken,
	'format' => 'json',
    ];
    $editResult = json_decode( $client->makeOAuthCall(
	$accessToken,
	$apiUrl,
	true,
	$apiParams
    ) );
  }
 
  
  public function index() {
    // Get the wiki URL and OAuth consumer details from the config file.
    $apiUrl = preg_replace( '/index\.php.*/', 'api.php', $this->oauthUrl);
    // Configure the OAuth client with the URL and consumer details.
    $conf = new ClientConfig($this->oauthUrl);
    $conf->setConsumer( new Consumer($this->consumerKey, $this->consumerSecret) );
    $client = new Client( $conf );
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
