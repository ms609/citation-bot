<?php
/*
 * setup.php sets up most of the page expansion. 
 * Most of the page expansion depends on everything else, 
 * particularly Template and Page.
*/

ini_set("user_agent", "Citation_bot; citations@tools.wmflabs.org");
include_once("./vendor/autoload.php");

if (!defined("HTML_OUTPUT") || getenv('TRAVIS')) {  // Fail safe code
  define("HTML_OUTPUT", FALSE);
}
if (!defined("FLUSHING_OKAY")) {  // Default when not gadget API
  define("FLUSHING_OKAY", TRUE);
}

//Optimisation
ob_implicit_flush();
if (!getenv('TRAVIS')) {
    ob_start();
}

if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') && file_exists('env.php')) {
  // An opportunity to set the PHP_OAUTH_ environment variables used in this function,
  // if they are not set already. Remember to set permissions (not readable!)
  include_once('env.php'); 
}
require_once("constants.php");
require_once("DOItools.php");
require_once("Page.php");
require_once("Template.php");
require_once("Parameter.php");
require_once("Comment.php");
require_once("wikiFunctions.php");
require_once("user_messages.php");
require_once("WikipediaBot.php");
require_once("expandFns.php");
require_once("zotero.php");

mb_internal_encoding('UTF-8'); // Avoid ??s
ini_set("memory_limit", "256M");

if (!isset($SLOW_MODE)) $SLOW_MODE = isset($_REQUEST["slow"]) ? $_REQUEST["slow"] : FALSE;
