<?php
declare(strict_types=1);
set_time_limit(120);
@session_start();
@header('Content-type: text/html; charset=utf-8');
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

require_once 'setup.php';

// To use the oauthclient library, run:
// composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Client;

// The two ways we leave this script - Some calls have extra calls to exit to make phpstan happy
function death_time(string $err) : void {
  unset($_SESSION['access_key']);
  unset($_SESSION['access_secret']);
  unset($_SESSION['citation_bot_user_id']);
  unset($_SESSION['request_key']);
  unset($_SESSION['request_secret']);     
  exit('<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Authentifcation System Failure</title></head><body><main>' . echoable($err) . '</main></body></html>');
}

function return_to_sender(string $where = 'https://citations.toolforge.org/') : void {
  header("Location: " . $where);
  exit(0);
}

if(session_status() !== PHP_SESSION_ACTIVE) {
  death_time("Citation Bot could not create a session");
}

if (!getenv('PHP_WP_OAUTH_CONSUMER') || !getenv('PHP_WP_OAUTH_SECRET')) {
  death_time("Citation Bot's authorization tokens not configured");
}

try {
  $conf = new ClientConfig('https://meta.wikimedia.org/w/index.php?title=Special:OAuth');
}
catch (Throwable $e) {
  death_time("Citation Bot Could not contact meta.wikimedia.org"); exit(1);
}

try {
  $conf->setConsumer(new Consumer((string) getenv('PHP_WP_OAUTH_CONSUMER'), (string) getenv('PHP_WP_OAUTH_SECRET')));
  $client = new Client($conf);
  unset($conf);
}
catch (Throwable $e) {
  death_time("Citation Bot's internal authorization tokens did not work"); exit(1);
}

// Existing Access Grant - verify that it works since we are here any way
if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
   try {
      $client->makeOAuthCall(
      new Token($_SESSION['access_key'], $_SESSION['access_secret']),
         'https://meta.wikimedia.org/w/api.php?action=query&meta=tokens&format=json');
      return_to_sender();
   }
   catch (Throwable $e) { ; }
   // We continue on and try to get a new key setup
   sleep(1);
}
// clear anything left over that did not work
unset($_SESSION['access_key']);
unset($_SESSION['access_secret']);

// New Incoming Access Grant
if (isset($_GET['oauth_verifier']) && isset($_SESSION['request_key']) && isset($_SESSION['request_secret']) ) {
   try {
        $accessToken = $client->complete(new Token($_SESSION['request_key'], $_SESSION['request_secret']), $_GET['oauth_verifier']);
        $_SESSION['access_key'] = $accessToken->key;
        $_SESSION['access_secret'] = $accessToken->secret;
        unset($_SESSION['request_key']);unset($_SESSION['request_secret']);
        if (isset($_GET['return'])) {
           // This could only be tainted input if OAuth server itself was hacked, so flag as safe
           /** @psalm-taint-escape header */
           $where = trim((string) $_GET['return']);
           return_to_sender($where);
        }
        return_to_sender();
   }
   catch (Throwable $e) { ; }
   death_time("Incoming authorization tokens did not work");
}
unset ($_SESSION['request_key']);
unset ($_SESSION['request_secret']);

// Nothing found.  Needs an access grant from scratch
$proto = (
         (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
         (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
      ) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = $_SERVER['REQUEST_URI'];
$newcallback = $proto . '://' . $host . $path;
$oldcallback = str_replace('citations.toolforge.org', 'tools.wmflabs.org/citations', $callback);

// TODO - tokens specify tools.wmflabs.org/citations, and not the correct newer hostname of citations.toolforge.org

try {
      $client->setCallback($oldcallback);
      list( $authUrl, $token ) = $client->initiate();
      $_SESSION['request_key'] = $token->key; // We will retrieve these from session when the user is sent back
      $_SESSION['request_secret'] = $token->secret;
      return_to_sender($authUrl);
}
catch (Throwable $e) { ; }
try {
      $client->setCallback($newcallback);
      list( $authUrl, $token ) = $client->initiate();
      $_SESSION['request_key'] = $token->key;
      $_SESSION['request_secret'] = $token->secret;
      return_to_sender($authUrl);
}
catch (Throwable $e) { ; }
death_time("Unable to initiate OAuth.");
?>
