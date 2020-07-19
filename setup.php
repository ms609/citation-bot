<?php
declare(strict_types=1);

if ((bool) getenv('TRAVIS') || isset($argv)) {
  error_reporting(E_ALL);
  define("HTML_OUTPUT", FALSE);
} else {
  error_reporting(E_ALL^E_NOTICE);
  define("HTML_OUTPUT", TRUE);
}

/*
 * setup.php sets up the environment
 * Most of the page expansion depends on everything else
*/

ini_set("user_agent", "Citation_bot; citations@tools.wmflabs.org");
include_once('./vendor/autoload.php');

if (!isset($FLUSHING_OKAY)) {  // Default when not gadget API
  $FLUSHING_OKAY = TRUE;
}

// We block these sometimes in testing
$BLOCK_BIBCODE_SEARCH = FALSE;
$BLOCK_ZOTERO_SEARCH  = FALSE;

//Optimisation
ob_implicit_flush();
if (!getenv('TRAVIS')) {
    ob_start();
}

require_once('user_messages.php');
if (file_exists('git_pull.lock')) report_error('GIT pull in progress');

if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') && file_exists('env.php')) {
  // An opportunity to set the PHP_OAUTH_ environment variables used in this function,
  // if they are not set already. Remember to set permissions (not readable!)
  ob_start();
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
require_once('constants.php');
require_once('NameTools.php');
require_once('expandFns.php');
require_once('zotero.php');
require_once('Parameter.php');
require_once('Comment.php');
require_once('WikipediaBot.php');
require_once('apiFunctions.php');
require_once('Template.php');
require_once('Page.php');

mb_internal_encoding('UTF-8');
ini_set("memory_limit", "256M");

if (file_exists('git_pull.lock')) report_error('GIT pull in progress');

if (isset($_REQUEST["slow"]) || getenv('TRAVIS') || (@$argv[2] === '--slow')) {
  $SLOW_MODE = TRUE;
} elseif (isset($argv[2])) {
  report_error("Unexpected text on the command.  Only --slow is valid second argument.  Found: " . $argv[2]);
} else {
  $SLOW_MODE = FALSE;
}

function check_blocked() : void {
  if (!getenv('TRAVIS') && ! WikipediaBot::is_valid_user('Citation_bot')) exit('</pre><div style="text-align:center"><h1>The Citation Bot is currently blocked because of disagreement over its usage.</h1><br/><h2><a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Join the discussion" target="_blank">Please join in the discussion</a></h2></div></body></html>');
}
