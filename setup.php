<?php
declare(strict_types=1);
if (file_exists('git_pull.lock')) exit('GIT pull in progress');

/*
 * setup.php sets up the environment
 * Most of the page expansion depends on everything else
 */

ini_set("user_agent", "Citation_bot; citations@tools.wmflabs.org");
include_once('./vendor/autoload.php');

define("TRAVIS", (bool) getenv('TRAVIS'));

if (TRAVIS || isset($argv)) {
  error_reporting(E_ALL);
  define("HTML_OUTPUT", FALSE);
} else {
  error_reporting(E_ALL^E_NOTICE);
  define("HTML_OUTPUT", TRUE);
}

// This is needed because the Gadget API expects only JSON back, therefore ALL output from the citation bot is thrown away
if (strpos((string) @$_SERVER['PHP_SELF'], '/gadgetapi.php') === FALSE) {
  define("FLUSHING_OKAY", TRUE);
} else {
  define("FLUSHING_OKAY", FALSE);
}

if (isset($_REQUEST["slow"]) || TRAVIS || (isset($argv[2]) && $argv[2] === '--slow')) {
  define("SLOW_MODE", TRUE);
} elseif (isset($argv[2])) {
  exit("Unexpected text on the command.  Only --slow is valid second argument.  Found: " . $argv[2]);
} else {
  define("SLOW_MODE", FALSE);
}

//Optimisation
ob_implicit_flush();
if (!TRAVIS) {
    ob_start();
}

if (file_exists('env.php')) {
  // Set the environment variables with putenv(). Remember to set permissions (not readable!)
  ob_start();
  /** @psalm-suppress MissingFile */
  include_once('env.php');
  $env_output = trim(str_replace(['Reading authentication tokens from tools.wmflabs.org.',
                                  'Reading authentication tokens from tools.wmflabs.org-dev.',
                                  'Reading authentication tokens from citations.toolforge.org.',
                                  'Reading authentication tokens from citations-dev.toolforge.org.',
                                  'Reading authentication tokens.', ' '],
                                 ['', '', '', '', '', ''], ob_get_contents()));
  if ($env_output) {
    ob_end_flush();  // Something unexpected, so print it out
  } else {
    ob_end_clean();
  }
}

mb_internal_encoding('UTF-8');
ini_set("memory_limit", "256M");

/** @psalm-suppress UnusedFunctionCall */
stream_context_set_default(['http' => ['timeout' => 20]]);

define("PHP_ADSABSAPIKEY", (string) getenv("PHP_ADSABSAPIKEY"));
define("PHP_GOOGLEKEY", (string) getenv("PHP_GOOGLEKEY"));
define("PHP_S2APIKEY", (string) getenv("PHP_S2APIKEY"));

function check_blocked() : void {
  if (!TRAVIS && ! WikipediaBot::is_valid_user('Citation_bot')) exit('</pre><div style="text-align:center"><h1>The Citation Bot is currently blocked because of disagreement over its usage.</h1><br/><h2><a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Join the discussion" target="_blank">Please join in the discussion</a></h2></div><footer><a href="./" title="Use Citation Bot again">Another&nbsp;page</a>?</footer></body></html>');
}

define("MAX_TRIES", 2);
require_once('constants.php');
require_once('NameTools.php');
require_once('expandFns.php');
require_once('Zotero.php');
require_once('Parameter.php');
require_once('Comment.php');
require_once('WikipediaBot.php');
require_once('apiFunctions.php');
require_once('Template.php');
require_once('Page.php');
require_once('user_messages.php');

