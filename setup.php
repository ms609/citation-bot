<?php
declare(strict_types=1);
error_reporting(E_ALL);
if (file_exists('git_pull.lock')) {
  sleep(5);
  exit('<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Citation Bot: error</title></head><body><h1>GIT pull in progress - please retry again in a moment</h1></body></html>');
}

/*
 * setup.php sets up the environment
 * Most of the page expansion depends on everything else
 */

function bot_debug_log(string $log_this) : void {
  @clearstatcache(); // Deal with multiple writers, but not so paranoid that we get a file lock
  flush();
  if (function_exists('echoable')) file_put_contents('CodeCoverage', echoable(WikipediaBot::GetLastUser()) . " :: " . echoable(Page::$last_title) . " :: $log_this\n", FILE_APPEND);
  flush();
}

if (isset($_REQUEST["wiki_base"])){
  $wiki_base = trim((string) $_REQUEST["wiki_base"]);
  if (!in_array($wiki_base, ['en', 'simple'])) {
     exit('<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Citation Bot: error</title></head><body><h1>Unsupported wiki requested - aborting</h1></body></html>');
  }
} else {
  $wiki_base = 'en';
}
define("WIKI_ROOT", 'https://'. $wiki_base . '.wikipedia.org/w/index.php');
define("API_ROOT", 'https://'. $wiki_base . '.wikipedia.org/w/api.php');
unset($wiki_base);

require_once 'constants.php';

ini_set("user_agent", BOT_USER_AGENT);
include_once './vendor/autoload.php';

define("TRAVIS", (bool) getenv('CI') || defined('__PHPUNIT_PHAR__') || defined('PHPUNIT_COMPOSER_INSTALL') || (strpos((string) @$_SERVER['argv'][0], 'phpunit') !== FALSE));

if ((string) @$_REQUEST["page"] . (string) @$argv[1] === "User:AManWithNoPlan/sandbox3") { // Specific page to make sure this code path keeps working
  define('EDIT_AS_USER', TRUE);
}

if (TRAVIS || isset($argv)) {
  define("HTML_OUTPUT", FALSE);
} else {
  define("HTML_OUTPUT", TRUE);
  ob_start();  // Always internal buffer website since server does this for us
}

// This is needed because the Gadget API expects only JSON back, therefore ALL output from the citation bot is thrown away
if (strpos((string) @$_SERVER['PHP_SELF'], '/gadgetapi.php') === FALSE) {
  define("FLUSHING_OKAY", TRUE);
} else {
  define("FLUSHING_OKAY", FALSE);
}

if (isset($_REQUEST["slow"]) || TRAVIS || (isset($argv) && in_array('--slow', $argv))) {
  define("SLOW_MODE", TRUE);
} else {
  define("SLOW_MODE", FALSE);
}

if (isset($argv) && in_array('--savetofiles', $argv)) {
  define("SAVETOFILES_MODE", TRUE);
} else {
  define("SAVETOFILES_MODE", FALSE);
}

ob_implicit_flush();
flush();

if (file_exists('env.php')) {
  // Set the environment variables with putenv(). Remember to set permissions (not readable!)
  ob_start();
  /** @psalm-suppress MissingFile */
  include_once('env.php');
  $env_output = trim(ob_get_contents());
  if ($env_output) {
    bot_debug_log("got this:\n" . $env_output);  // Something unexpected, so log it
  }
  unset($env_output);
  ob_end_clean();
}

if (!mb_internal_encoding('UTF-8') || !mb_regex_encoding('UTF-8')) { /** @phpstan-ignore-line */ /** We are very paranoid */
  exit('Unable to set encoding'); 
}

 // Needed for "Skin Cancer" and other large pages
ini_set("memory_limit", "1024M");
ini_set("pcre.backtrack_limit", "1425000000");
ini_set("pcre.recursion_limit", "425000000");
if (isset($_REQUEST["pcre"]) || (strpos((string) @$_SERVER['PHP_SELF'], '/gadgetapi.php') !== FALSE)) { // Willing to take slight performance penalty on Gadget
  ini_set("pcre.jit", "0");
}

date_default_timezone_set('UTC');

/** @psalm-suppress UnusedFunctionCall */
stream_context_set_default(['http' => ['timeout' => BOT_HTTP_TIMEOUT]]);
ini_set('default_socket_timeout', strval(BOT_HTTP_TIMEOUT));

define("PHP_ADSABSAPIKEY", (string) getenv("PHP_ADSABSAPIKEY"));
if ((string) getenv("PHP_S2APIKEY") !== "") {
  define("CONTEXT_S2", array('http'=>array('header'=>"x-api-key: " . (string) getenv("PHP_S2APIKEY") . "\r\n")));
} else {
  define("CONTEXT_S2", array());
}

// see https://www.ncbi.nlm.nih.gov/books/NBK25497/ for more information
// Without an API key, any site IP address posting more than 3 requests per second will receive an error message.
$nlm_tool = "WikipediaCitationBot";
$nlm_apikey = (string) getenv("NLM_APIKEY");
$nlm_email = (string) getenv("NLM_EMAIL");
if (!(strpos($nlm_email, '@') > 0)) $nlm_email = PUBMEDUSERNAME;
if (strlen($nlm_apikey) < 8) $nlm_apikey = ""; // Probably "xxxxx"
define ("NLM_LOGIN", "tool=" . urlencode($nlm_tool) . "&email=" . urlencode($nlm_email) . (($nlm_apikey === "") ? "" : ("&api_key=" . urlencode($nlm_apikey))));
unset($nlm_email, $nlm_apikey, $nlm_tool);

function check_blocked() : void {
  if (!TRAVIS && ! WikipediaBot::is_valid_user('Citation_bot')) {
    echo '</pre><div style="text-align:center"><h1>The Citation Bot is currently blocked because of disagreement over its usage.</h1><br/><h2><a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Join the discussion" target="_blank">Please join in the discussion</a></h2></div><footer><a href="./" title="Use Citation Bot again">Another&nbsp;page</a>?</footer></body></html>';
    exit();
  }
}

define("MAX_TRIES", 2);
require_once 'Comment.php';
require_once 'user_messages.php';
require_once 'NameTools.php';
require_once 'WikipediaBot.php';
require_once 'Parameter.php';
require_once 'expandFns.php';
require_once 'Template.php';
require_once 'Zotero.php';
require_once 'apiFunctions.php';
require_once 'Page.php';

if (isset($argv)) {
  define("MAX_PAGES", 1000000);
} else {
  define("MAX_PAGES", 3850);
}

if (!TRAVIS) { // This is explicity "tested" in test suite
  Zotero::create_ch_zotero();
  WikipediaBot::make_ch();
}

