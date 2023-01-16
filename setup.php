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

if (isset($_POST["page"]) && strpos((string) $_POST["page"], 'ZOTERO_ONLY|') === 0) {
  define("SLOW_MODE", TRUE);
  define("ZOTERO_ONLY", TRUE);
} elseif (isset($_POST['linkpage']) && (strpos($_POST['linkpage'], 'ZOTERO') !== FALSE)) {
  define("SLOW_MODE", TRUE);
  define("ZOTERO_ONLY", TRUE);
} elseif (isset($_REQUEST["slow"]) || TRAVIS || (isset($argv[2]) && $argv[2] === '--slow')) {
  define("SLOW_MODE", TRUE);
  define("ZOTERO_ONLY", FALSE);
} elseif (isset($argv[2]) && $argv[2] === '--zotero') {
  define("SLOW_MODE", TRUE);
  define("ZOTERO_ONLY", TRUE);
} elseif (isset($argv[2])) {
  exit("Unexpected text on the command.  Only --slow is valid second argument.");
} else {
  define("SLOW_MODE", FALSE);
  define("ZOTERO_ONLY", FALSE);
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
    file_put_contents('CodeCoverage', "\n" . $env_output . "\n", FILE_APPEND);  // Something unexpected, so log it
  }
  unset($env_output);
  ob_end_clean();
}

if (!mb_internal_encoding('UTF-8') || !mb_regex_encoding('UTF-8')) { /** @phpstan-ignore-line */ /** We are very paranoid */
  exit('Unable to set encoding'); 
}

 // Needed for "Skin Cancer" and other large pages
ini_set("memory_limit", "800M");
ini_set("pcre.backtrack_limit", "1425000000");
ini_set("pcre.recursion_limit", "425000000");


date_default_timezone_set('UTC');

/** @psalm-suppress UnusedFunctionCall */
stream_context_set_default(['http' => ['timeout' => 20]]);
ini_set('default_socket_timeout', '20');

define("PHP_ADSABSAPIKEY", (string) getenv("PHP_ADSABSAPIKEY"));
if ((string) getenv("PHP_S2APIKEY") !== "") {
  define("CONTEXT_S2", array('http'=>array('header'=>"x-api-key: " . (string) getenv("PHP_S2APIKEY") . "\r\n")));
} else {
  define("CONTEXT_S2", array());
}

function check_blocked() : void {
  if (!TRAVIS && ! WikipediaBot::is_valid_user('Citation_bot')) {
    echo '</pre><div style="text-align:center"><h1>The Citation Bot is currently blocked because of disagreement over its usage.</h1><br/><h2><a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Join the discussion" target="_blank">Please join in the discussion</a></h2></div><footer><a href="./" title="Use Citation Bot again">Another&nbsp;page</a>?</footer></body></html>';
    exit();
  }
}

function unlock_user() : void {
  @session_start();
  unset($_SESSION['big_and_busy']);     
  @session_write_close();
}

function check_overused() : void {
 return;
  /**  No longer enforcing - TODO figure out some way to get this to work.  Seems to just hang.  Also, re-enable kill_big_job.php
 if (!HTML_OUTPUT) return;
 if (isset($_SESSION['big_and_busy']) && $_SESSION['big_and_busy'] === 'BLOCK4') {
   echo '</pre><div style="text-align:center"><h1>Run blocked by your existing big run.</h1></div><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
   exit();
 }
 ob_start(); // Buffer output for big jobs
 @session_start();
 define('BIG_JOB_MODE', 'YES');
 register_shutdown_function('unlock_user');
 $_SESSION['big_and_busy'] = 'BLOCK4';
 @session_write_close();
 **/
}

function check_killed() : void {
 if(!defined('BIG_JOB_MODE')) return;
 @session_start(['read_and_close' => TRUE]);
 if (isset($_SESSION['kill_the_big_job'])) {
   @session_start();
   unset($_SESSION['kill_the_big_job']);
   unset($_SESSION['big_and_busy']);
   @session_write_close();
   echo '</pre><div style="text-align:center"><h1>Run killed as requested.</h1></div><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
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
define("BIG_RUN", 50);

if (!TRAVIS) { // This is explicity "tested" in test suite
  Zotero::create_ch_zotero();
  WikipediaBot::make_ch();
}

