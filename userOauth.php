<?php
 // To use the oauthclient library, run:
 // composer require mediawiki/oauthclient
 use MediaWiki\OAuthClient\Consumer;
 use MediaWiki\OAuthClient\Token;
 use MediaWiki\OAuthClient\Request;
 use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;

 final class userOauth {
  
  // To get this demo working, you need to go to this wiki and register a new OAuth consumer.
// Not that this URL must be of the long form with 'title=Special:OAuth', and not a clean URL.
$oauthUrl = 'https://meta.wikimedia.beta.wmflabs.org/w/index.php?title=Special:OAuth';
// When you register, you will get a consumer key and secret. Put these here (and for real
// applications, keep the secret secret! The key is public knowledge.).
$consumerKey = '';
$consumerSecret = '';
  
  public function logout() {
    session_start();
    session_destroy();
  }
  
  public fucntion callback() {
   if ( !isset( $_GET['oauth_verifier'] ) ) {
	echo "This page should only be access after redirection back from the wiki.";
	exit( 1 );
}
// Get the wiki URL and OAuth consumer details from the config file.
$config = require_once __DIR__ . '/config.php';
// Configure the OAuth client with the URL and consumer details.
$conf = new ClientConfig( $config['url'] );
$conf->setConsumer( new Consumer( $config['consumer_key'], $config['consumer_secret'] ) );
$client = new Client( $conf );
// Get the Request Token's details from the session and create a new Token object.
session_start();
$requestToken = new Token( $_SESSION['request_key'], $_SESSION['request_secret'] );
// Send an HTTP request to the wiki to retrieve an Access Token.
$accessToken = $client->complete( $requestToken,  $_GET['oauth_verifier'] );
// At this point, the user is authenticated, and the access token can be used to make authenticated
// API requests to the wiki. You can store the Access Token in the session or other secure
// user-specific storage and re-use it for future requests.
$_SESSION['access_key'] = $accessToken->key;
$_SESSION['access_secret'] = $accessToken->secret;
// You also no longer need the Request Token.
unset( $_SESSION['request_key'], $_SESSION['request_secret'] );
  }
  
  public function api_request() {
   // Get the wiki URL and OAuth consumer details from the config file.
$config = require_once __DIR__ . '/config.php';
// Make the api.php URL from the OAuth URL.
$apiUrl = preg_replace( '/index\.php.*/', 'api.php', $config['url'] );
// Configure the OAuth client with the URL and consumer details.
$conf = new ClientConfig( $config['url'] );
$conf->setConsumer( new Consumer( $config['consumer_key'], $config['consumer_secret'] ) );
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
// Make sure the config file exists. This is just to make sure the demo makes sense if someone loads
// it in the browser without reading the documentation.
$configFile = __DIR__ . '/config.php';
if ( !file_exists( $configFile ) ) {
	echo "Configuration could not be read. Please create $configFile by copying config.dist.php";
	exit( 1 );
}
// Get the wiki URL and OAuth consumer details from the config file.
$config = require_once $configFile;
// Configure the OAuth client with the URL and consumer details.
$conf = new ClientConfig( $config['url'] );
$conf->setConsumer( new Consumer( $config['consumer_key'], $config['consumer_secret'] ) );
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
