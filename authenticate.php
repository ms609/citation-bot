<?php
// To use the oauthclient library, run:
// composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Client;

  private function authenticate_user() {
    if (!HTML_OUTPUT) return;
    try {
      $conf = new ClientConfig('https://meta.wikimedia.org/w/index.php?title=Special:OAuth');
      $conf->setConsumer($this->consumer);
      $client = new Client($conf);
      
      // Existing Access Grant
      if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
        $this->userEditToken = json_decode( $client->makeOAuthCall(
           	new Token($_SESSION['access_key'], $_SESSION['access_secret']),
      	    'https://meta.wikimedia.org/w/api.php?action=query&meta=tokens&format=json'
         ) )->query->tokens->csrftoken;
        return;
      }
      // New Incoming Access Grant
      if (isset($_GET['oauth_verifier']) && isset($_SESSION['request_key']) && isset($_SESSION['request_secret']) ) {
        $accessToken = $client->complete(new Token($_SESSION['request_key'], $_SESSION['request_secret']), $_GET['oauth_verifier']);
        $_SESSION['access_key'] = $accessToken->key;
        $_SESSION['access_secret'] = $accessToken->secret;
        setcookie(session_name(),session_id(),time()+(365*24*3600)); // We choose a one year duration
        unset($_SESSION['request_key']);unset($_SESSION['request_secret']);
        echo "Authorization Success.  Future requests should just work now.";
        exit(0);
      }
      // New Incoming Access Grant without SESSION.  Throw exeption to avoid possible infinite loop.
      if (isset($_GET['oauth_verifier'])) {
        throw new Exception("OAuth Verification without Session Key/Secret");
      }
      // Nothing found.  Needs an access grant from scratch
      list( $authUrl, $token ) = $client->initiate();
      $_SESSION['request_key'] = $token->key; // We will retrieve these from session when the user is sent back
      $_SESSION['request_secret'] = $token->secret;
      // Redirect the user to the authorization URL (only works if NO html has been sent).  Include non-header just in case
      @header("Location: $authUrl");
      echo "<br />Go to this URL to <a href='$authUrl'>authorize citation bot</a>";
      exit(0);
    }
    // Something went wrong.  Blow it all away.
    catch (Throwable $e) { ; } // PHP 7
    catch (Exception $e) { ; } // PHP 5
    @session_destroy();
    html_echo("<br />Error authenticating.  Resetting.  Please try again.");
    trigger_error($e->getMessage());
    exit(0); // Should not get here
  }
