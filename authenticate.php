<?php

declare(strict_types=1);

set_time_limit(120);

@header('Access-Control-Allow-Origin: null');

if (@$_SERVER['REQUEST_URI'] === '/authenticate.php') {
    return_to_sender();
}

session_start();

require_once 'setup.php';

// To use the oauthclient library, run: composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;

// The two ways we leave this script
function death_time(string $err): never {
    unset($_SESSION['access_key'], $_SESSION['access_secret'], $_SESSION['citation_bot_user_id'], $_SESSION['request_key'], $_SESSION['request_secret']);
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Authentifcation System Failure</title></head><body><main>', $err, '</main></body></html>';
    exit;
}

function return_to_sender(string $where = 'https://citations.toolforge.org/'): never {
    if (preg_match('~\s+~', $where)) {
        death_time('Error in return_to_sender');
    }
    header("Location: " . $where);
    exit;
}

if (!getenv('PHP_WP_OAUTH_CONSUMER') || !getenv('PHP_WP_OAUTH_SECRET')) {
    death_time("Citation Bot's authorization tokens not configured");
}

try {
    $conf = new ClientConfig('https://meta.wikimedia.org/w/index.php?title=Special:OAuth');
}
catch (Throwable $e) {
    death_time("Citation Bot Could not contact meta.wikimedia.org");
}

try {
    $conf->setConsumer(new Consumer((string) getenv('PHP_WP_OAUTH_CONSUMER'), (string) getenv('PHP_WP_OAUTH_SECRET')));
    $conf->setUserAgent(BOT_USER_AGENT);
    $client = new Client($conf);
    unset($conf);
}
catch (Throwable $e) {
    death_time("Citation Bot's internal authorization tokens did not work");
}

// Existing Access Grant - verify that it works since we are here anyway
if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
    try {
        $token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
        $auth_url = 'https://meta.wikimedia.org/w/api.php?action=query&meta=tokens&format=json';
        $client->makeOAuthCall($token, $auth_url);
        return_to_sender();
    }
    catch (Throwable $e) {
        /** fall through */
    }
    death_time("Existing authorization tokens did not work - try again please");
}
// clear anything left over that did not work
unset($_SESSION['access_key'], $_SESSION['access_secret']);

// New Incoming Access Grant
if (is_string(@$_GET['oauth_verifier']) && is_string(@$_SESSION['request_key']) && is_string(@$_SESSION['request_secret'])) {
    try {
        $accessToken = $client->complete(new Token($_SESSION['request_key'], $_SESSION['request_secret']), $_GET['oauth_verifier']);
        if (empty($accessToken->key) || empty($accessToken->secret)) {
            throw new Exception('OAuth complete() call failed');
        }
        $_SESSION['access_key'] = $accessToken->key;
        $_SESSION['access_secret'] = $accessToken->secret;
        unset($_SESSION['request_key'], $_SESSION['request_secret']);
        if (is_string(@$_GET['return'])) {
            // This could only be tainted input if OAuth server itself was hacked, so flag as safe
            /** @psalm-taint-escape header */
            $where = trim($_GET['return']);
            if (mb_substr($where, 0, 1) !== '/' || preg_match('~\s+~', $where)) {
                death_time('Invalid Access URL');
            }
            return_to_sender($where);
        }
        return_to_sender();
    }
    catch (Throwable $e) {
        /** fall through */
    }
    death_time("Incoming authorization tokens did not work - try again please");
}
unset($_SESSION['request_key'], $_SESSION['request_secret']);
unset($_GET, $_POST, $_REQUEST); // Memory minimize

// Nothing found.  Needs an access grant from scratch
try {
    if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
        throw new Exception('Webserver URL variables not set');
    }
    $newcallback = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $client->setCallback($newcallback);
    [$authUrl, $token] = $client->initiate();
    $_SESSION['request_key'] = $token->key;
    $_SESSION['request_secret'] = $token->secret;
    if (strpos($authUrl, 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth/authorize&oauth_token=') !== 0 || preg_match('~\s+~', $authUrl)) {
        death_time('Corrupted OAuth URL');
    }
    return_to_sender($authUrl);
}
catch (Throwable $e) {
    /** fall through */
}
death_time("Unable to initiate OAuth.");
?>
