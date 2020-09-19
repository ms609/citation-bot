<?php
declare(strict_types=1);
if (file_exists('git_pull.lock')) exit('GIT pull in progress');

/*
 * setup.php sets up the environment
 * Most of the page expansion depends on everything else
 */

ini_set("user_agent", "Citation_bot; citations@tools.wmflabs.org");
ini_set('output_buffering', false);
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
  exit("Unexpected text on the command.  Only --slow is valid second argument.");
} else {
  define("SLOW_MODE", FALSE);
}

//Optimisation
ob_implicit_flush();
if (!TRAVIS) {
    if (FLUSHING_OKAY) {
      while (ob_get_level()) {
        ob_end_flush();
      }
      ob_start();
