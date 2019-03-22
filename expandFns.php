<?php
/*
 * expandFns.php sets up most of the page expansion. 
 * Most of the page expansion depends on the classes in objects.php, 
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
require_once("constants.php");
require_once("DOItools.php");
require_once("Page.php");
require_once("Template.php");
require_once("Parameter.php");
require_once("Comment.php");
require_once("wikiFunctions.php");
require_once("user_messages.php");
require_once("WikipediaBot.php");

$api_files = glob('api_handlers/*.php');
foreach ($api_files as $file) {
    require_once($file);
}

const CROSSREFUSERNAME = 'martins@gmail.com';
// Use putenv to set PHP_ADSABSAPIKEY, PHP_GOOGLE_KEY and PHP_BOTUSERNAME environment variables

mb_internal_encoding('UTF-8'); // Avoid ??s

//Optimisation
ob_implicit_flush();
if (!getenv('TRAVIS')) {
    ob_start();
}
ini_set("memory_limit", "256M");
global $SLOW_MODE;
if (!isset($SLOW_MODE)) $SLOW_MODE = isset($_REQUEST["slow"]) ? $_REQUEST["slow"] : FALSE;

if (isset($_REQUEST["edit"]) && $_REQUEST["edit"]) {		
  $ON = TRUE;
}

