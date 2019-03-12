<?php
@session_start();
error_reporting(E_ALL^E_NOTICE);
define("HTML_OUTPUT");

// To use the oauthclient library, run:
// composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Client;

require_once('expandFns.php');

if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') || !getenv('PHP_OAUTH_CONSUMER_SECRET')) {
  report_error("Citation Bot's authorization tokens not configured");
}

try {
  $conf = new ClientConfig('https://meta.wikimedia.org/w/index.php?title=Special:OAuth');
  $conf->setConsumer(new Consumer(getenv('PHP_OAUTH_CONSUMER_TOKEN'), getenv('PHP_OAUTH_CONSUMER_SECRET')));
  $client = new Client($conf);
}
catch (Throwable $e) { report_error("Citation Bot's authorization tokens did not work"); } // PHP 7
catch (Exception $e) { report_error("Citation Bot's authorization tokens did not work"); } // PHP 5
    
// Existing Access Grant - verify that it works since we are here any way
if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
   try {
     json_decode( $client->makeOAuthCall(
      new Token($_SESSION['access_key'], $_SESSION['access_secret']),
         'https://meta.wikimedia.org/w/api.php?action=query&meta=tokens&format=json'
      ) )->query->tokens->csrftoken;
     echo ' Existing valid tokens user tokens already set';
     exit(0);
   }
   catch (Throwable $e) { ; } // PHP 7
   catch (Exception $e) { ; } // PHP 5
   @sesssion_destroy();
   @session_start();
   // We continue on and try to get a new key setup
   sleep(1);
}

// New Incoming Access Grant
if (isset($_GET['oauth_verifier']) && isset($_SESSION['request_key']) && isset($_SESSION['request_secret']) ) {
   try {
        $accessToken = $client->complete(new Token($_SESSION['request_key'], $_SESSION['request_secret']), $_GET['oauth_verifier']);
        $_SESSION['access_key'] = $accessToken->key;
        $_SESSION['access_secret'] = $accessToken->secret;
        setcookie(session_name(),session_id(),time()+(365*24*3600)); // We choose a one year duration
        unset($_SESSION['request_key']);unset($_SESSION['request_secret']);
        echo "Authorization Success.  Future requests should just work now.";
        exit(0);
   }
   catch (Throwable $e) { report_error("Incoming authorization tokens did not work"); } // PHP 7
   catch (Exception $e) { report_error("Incoming authorization tokens did not work"); } // PHP 5   
}

// New Incoming Access Grant without SESSION
if (isset($_GET['oauth_verifier'])) {
   @session_destroy();
   report_error("Incoming authorization tokens did not have matching session -- possible cookies lost");
}


// Nothing found.  Needs an access grant from scratch
try {
      list( $authUrl, $token ) = $client->initiate();
      $_SESSION['request_key'] = $token->key; // We will retrieve these from session when the user is sent back
      $_SESSION['request_secret'] = $token->secret;
      // Redirect the user to the authorization URL (only works if NO html has been sent).  Include non-header just in case
      @header("Location: $authUrl");
      sleep(1);
      echo "Go to this URL to <a href='$authUrl'>authorize citation bot</a>";
      exit(0);
    }
    // Something went wrong.  Blow it all away.
    catch (Throwable $e) { ; } // PHP 7
    catch (Exception $e) { ; } // PHP 5
    @session_destroy();
    report_error("Error authenticating.  Resetting.  Please try again.");


