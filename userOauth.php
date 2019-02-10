<?php
// To use the oauthclient library, run:
// composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;

final class userOauth {
  
  /**
 * See https://tools.wmflabs.org/oauth-hello-world/index.php?action=download  public domain code
 * Set this to point to a file (outside the webserver root!) containing the following keys:
 * - consumerKey: The "consumer token" given to you when registering your app
 * - consumerSecret: The "secret token" given to you when registering your app
 */
  private $inifile = '/private/oauth-users.ini';
  private $mwOAuthAuthorizeUrl = 'https://meta.wikimedia.org/wiki/Special:OAuth/authorize';
  private $mwOAuthUrl = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';
  private $mwOAuthIW = 'meta';
  private $apiUrl = 'https://en.wikipedia.org/w/api.php';
  private $mytalkUrl = 'https://en.wikipedia.org/wiki/User_talk:Citation_bot';
  private $errorCode = 200;
 
  private $gConsumerKey;
  private $gTokenKey;
  private $gTokenSecret;
  private $gConsumerSecret;

  function __construct() {
 
     if (getenv('TRAVIS')) return; // this isn't gonna work
     $ini = parse_ini_file( $this->inifile );
     if ($ini === false || !isset($ini['consumerKey']) || !isset($ini['consumerSecret'])) {
        trigger_error('Valid oauth ini file not found');
     }
     $this->gConsumerKey = $ini['consumerKey'];
     $this->gConsumerSecret = $ini['consumerSecret'];
     // Setup the session cookie
     session_name( 'OAuth Citation Bot');
     $params = session_get_cookie_params();
     session_set_cookie_params(
        $params['lifetime'],
        dirname( $_SERVER['SCRIPT_NAME'] )
     );
     // Load the user token (request or access) from the session
     $this->gTokenKey = '';
     $this->gTokenSecret = '';
     session_start();
     if ( isset( $_SESSION['tokenKey'] ) ) {
       	$this->gTokenKey = $_SESSION['tokenKey'];
       	$this->gTokenSecret = $_SESSION['tokenSecret'];
     }
     session_write_close();
     // Fetch the access token if this is the callback from requesting authorization
     if ( isset( $_GET['oauth_verifier'] ) && $_GET['oauth_verifier'] ) {
        $this->fetchAccessToken();
     }
    
  }
  
  public function getKeys() {
 	  return $this->gTokenSecret;
  }

 /**
  * Handle a callback to fetch the access token
  * @return void
  */
  public function fetchAccessToken() {

 	$url = $this->mwOAuthUrl . '/token';
 	$url .= strpos( $url, '?' ) ? '&' : '?';
 	$url .= http_build_query( array(
 		'format' => 'json',
 		'oauth_verifier' => $_GET['oauth_verifier'],

 		// OAuth information
 		'oauth_consumer_key' => $this->gConsumerKey,
 		'oauth_token' => $this->gTokenKey,
 		'oauth_version' => '1.0',
 		'oauth_nonce' => md5( microtime() . mt_rand() ),
 		'oauth_timestamp' => time(),

 		// We're using secret key signatures here.
 		'oauth_signature_method' => 'HMAC-SHA1',
 	) );
 	$signature = sign_request( 'GET', $url );
 	$url .= "&oauth_signature=" . urlencode( $signature );
 	$ch = curl_init();
 	curl_setopt( $ch, CURLOPT_URL, $url );
 	//curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
 	curl_setopt( $ch, CURLOPT_USERAGENT, 'Citation Bot' );
 	curl_setopt( $ch, CURLOPT_HEADER, 0 );
 	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
 	$data = @curl_exec( $ch );
 	if ( !$data ) {
 		trigger_error('oauth Curl error: ' . curl_error($ch));
 	}
 	curl_close( $ch );
 	$token = json_decode( $data );
 	if ( is_object( $token ) && isset( $token->error ) ) {
 		trigger_error('Error retrieving token: ' . $token->error . '  ' . $token->message);
 	}
 	if ( !is_object( $token ) || !isset( $token->key ) || !isset( $token->secret ) ) {
 		$this->doAuthorizationRedirect();
 	}
 	// Save the access token
 	session_start();
 	$_SESSION['tokenKey'] = $this->gTokenKey = $token->key;
 	$_SESSION['tokenSecret'] = $this->gTokenSecret = $token->secret;
 	session_write_close();
  }
   
 /**
  * Utility function to sign a request
  *
  * Note this doesn't properly handle the case where a parameter is set both in 
  * the query string in $url and in $params, or non-scalar values in $params.
  *
  * @param string $method Generally "GET" or "POST"
  * @param string $url URL string
  * @param array $params Extra parameters for the Authorization header or post 
  * 	data (if application/x-www-form-urlencoded).
  * @return string Signature
  */
  private function sign_request( $method, $url, $params = array() ) {

 	$parts = parse_url( $url );

 	// We need to normalize the endpoint URL
 	$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'http';
 	$host = isset( $parts['host'] ) ? $parts['host'] : '';
 	$port = isset( $parts['port'] ) ? $parts['port'] : ( $scheme == 'https' ? '443' : '80' );
 	$path = isset( $parts['path'] ) ? $parts['path'] : '';
 	if ( ( $scheme == 'https' && $port != '443' ) ||
 		( $scheme == 'http' && $port != '80' ) 
 	) {
 		// Only include the port if it's not the default
 		$host = "$host:$port";
 	}

 	// Also the parameters
 	$pairs = array();
 	parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
 	$query += $params;
 	unset( $query['oauth_signature'] );
 	if ( $query ) {
 		$query = array_combine(
 			// rawurlencode follows RFC 3986 since PHP 5.3
 			array_map( 'rawurlencode', array_keys( $query ) ),
 			array_map( 'rawurlencode', array_values( $query ) )
 		);
 		ksort( $query, SORT_STRING );
 		foreach ( $query as $k => $v ) {
 			$pairs[] = "$k=$v";
 		}
 	}

 	$toSign = rawurlencode( strtoupper( $method ) ) . '&' .
 		rawurlencode( "$scheme://$host$path" ) . '&' .
 		rawurlencode( join( '&', $pairs ) );
 	$key = rawurlencode( $this->gConsumerSecret ) . '&' . rawurlencode( $this->gTokenSecret );
 	return base64_encode( hash_hmac( 'sha1', $toSign, $key, true ) );
  }



 /**
  * Request authorization
  * @return void
  */
 private function doAuthorizationRedirect() {

 	// First, we need to fetch a request token.
 	// The request is signed with an empty token secret and no token key.
 	$this->gTokenSecret = '';
 	$url = $this->mwOAuthUrl . '/initiate';
 	$url .= strpos( $url, '?' ) ? '&' : '?';
 	$url .= http_build_query( array(
 		'format' => 'json',
 		
 		// OAuth information
 		'oauth_callback' => 'oob', // Must be "oob" or something prefixed by the configured callback URL
 		'oauth_consumer_key' => $this->gConsumerKey,
 		'oauth_version' => '1.0',
 		'oauth_nonce' => md5( microtime() . mt_rand() ),
 		'oauth_timestamp' => time(),

 		// We're using secret key signatures here.
 		'oauth_signature_method' => 'HMAC-SHA1',
 	) );
 	$signature = sign_request( 'GET', $url );
 	$url .= "&oauth_signature=" . urlencode( $signature );
 	$ch = curl_init();
 	curl_setopt( $ch, CURLOPT_URL, $url );
 	curl_setopt( $ch, CURLOPT_USERAGENT, 'Citation Bot' );
 	curl_setopt( $ch, CURLOPT_HEADER, 0 );
 	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
 	$data = curl_exec( $ch );
 	if ( !$data ) {
 		trigger_error('Curl error: ' . curl_error( $ch ) );
 	}
 	curl_close( $ch );
 	$token = json_decode( $data );
 	if ( is_object( $token ) && isset( $token->error ) ) {
 		trigger_error('Error retrieving token: ' . $token->error . '  ' . $token->message );
 	}
 	if ( !is_object( $token ) || !isset( $token->key ) || !isset( $token->secret ) ) {
 		trigger_error('Invalid response from token request');
 	}
 	// Now we have the request token, we need to save it for later.
 	session_start();
 	$_SESSION['tokenKey'] = $token->key;
 	$_SESSION['tokenSecret'] = $token->secret;
 	session_write_close();
 	// Then we send the user off to authorize
 	$url = $this->mwOAuthAuthorizeUrl;
 	$url .= strpos( $url, '?' ) ? '&' : '?';
 	$url .= http_build_query( array(
 		'oauth_token' => $token->key,
 		'oauth_consumer_key' => $this->gConsumerKey,
 	) );
   trigger_error('Please see  ' . $url);
  }
  
}
