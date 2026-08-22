<?php

declare(strict_types=1);

// To use the oauthclient library, run: composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;

if (file_exists(__DIR__ . '/env.php')) {
    /** @psalm-suppress MissingFile */
    include_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/includes/PublicConfig.php';

/** The two ways we leave this script */
function death_time(string $err): never {
    unset($_SESSION['access_key'], $_SESSION['access_secret'], $_SESSION['citation_bot_user_id'], $_SESSION['request_key'], $_SESSION['request_secret'], $_SESSION['csrf_token']);
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>Authentication System Failure</title></head><body><main>', $err, '</main></body></html>';
    exit(0);
}

function return_to_sender(?string $where = null): never {
    if ($where === null) {
        $where = public_url('/');
    }
    if (preg_match('~\s+~', $where)) {
        death_time('Error in return_to_sender');
    }
    header("Location: " . $where);
    exit(0);
}

set_time_limit(120);

enforce_public_request_configuration(is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : null);
send_configured_cors_header(is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null);

if (@$_SERVER['REQUEST_URI'] === public_url_path('/authenticate.php')) {
    return_to_sender();
}

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/includes/setup.php';

$return_path = null;
if (isset($_GET['return'])) {
    if (!is_string($_GET['return']) || !is_valid_local_return_path($_GET['return'])) {
        death_time('Invalid Access URL');
    }
    $return_path = $_GET['return'];
}

if (getenv('PHP_WP_OAUTH_CONSUMER') === false || getenv('PHP_WP_OAUTH_SECRET') === false) {
    death_time("Citation Bot's authorization tokens not configured");
}

try {
    $conf = new ClientConfig('https://meta.wikimedia.org/w/index.php?title=Special:OAuth');
} catch (Throwable) {
    death_time("Citation Bot Could not contact meta.wikimedia.org");
}

try {
    $conf->setConsumer(new Consumer((string) getenv('PHP_WP_OAUTH_CONSUMER'), (string) getenv('PHP_WP_OAUTH_SECRET')));
    $conf->setUserAgent(BOT_USER_AGENT);
    $client = new Client($conf);
    unset($conf);
} catch (Throwable) {
    death_time("Citation Bot's internal authorization tokens did not work");
}

// Existing Access Grant - verify that it works since we are here anyway
if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
    try {
        $token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
        $auth_url = 'https://meta.wikimedia.org/w/api.php?action=query&meta=tokens&format=json';
        $client->makeOAuthCall($token, $auth_url);
        return_to_sender($return_path);
    } catch (Throwable) {
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
        if ($return_path !== null) {
            // This could only be tainted input if OAuth server itself was hacked, so flag as safe
            /** @psalm-taint-escape header */
            return_to_sender($return_path);
        }
        return_to_sender();
    } catch (Throwable) {
        /** fall through */
    }
    death_time("Incoming authorization tokens did not work - try again please");
}
unset($_SESSION['request_key'], $_SESSION['request_secret']);

try {
    $newcallback = oauth_callback_url($return_path);
} catch (Throwable) {
    death_time('Invalid public URL configuration');
}
unset($return_path);
unset($_GET, $_POST, $_REQUEST); // Memory minimize

// Nothing found.  Needs an access grant from scratch
try {
    $client->setCallback($newcallback);
    [$authUrl, $token] = $client->initiate();
    $_SESSION['request_key'] = $token->key;
    $_SESSION['request_secret'] = $token->secret;
    if (mb_strpos($authUrl, 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth/authorize&oauth_token=') !== 0 || preg_match('~\s+~', $authUrl)) {
        death_time('Corrupted OAuth URL');
    }
    return_to_sender($authUrl);
} catch (Throwable) {
    /** fall through */
}
death_time("Unable to initiate OAuth.");
