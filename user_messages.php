<?php
declare(strict_types=1);

require_once 'constants.php';   // @codeCoverageIgnore

function html_echo(string $text, string $alternate_text='') : void {
  /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT and TRAVIS cannot be false */
  if (!TRAVIS || defined("TRAVIS_PRINT")) echo HTML_OUTPUT ? $text : $alternate_text; // @codeCoverageIgnore 
}

function user_notice(string $symbol, string $class, string $text) : void {
  if (!TRAVIS || defined("TRAVIS_PRINT")) {
    // @codeCoverageIgnoreStart
    if (defined('BIG_JOB_MODE') && in_array($class, array("boring", "removed", "added", "changed", "subsubitem"))) {
      echo '.'; // Echo something to keep the code alive, but not so much to overfill the cache
      return;
    }
    // These are split over three lines to avoid creating a single long string during error conditions - which could blow out the memory
    /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT cannot be false */
    echo "\n " . (HTML_OUTPUT ? "<span class='$class'>" : "") . $symbol;
    if (strlen($text) > 5242880) { //  much bigger than https://en.wikipedia.org/wiki/Special:LongPages
      echo "HUGE amount of text NOT printed";
      bot_debug_log("HUGE amount of text NOT printed");
    } else {
      echo $text;
    }
    /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT cannot be false */
    echo HTML_OUTPUT ? "</span>" : "";
    // @codeCoverageIgnoreEnd
  }
}

function report_phase(string $text) : void { user_notice("\n>", "phase", $text); }
function report_action(string $text) : void { user_notice(">", "subitem", $text); }
function report_info(string $text) : void { user_notice("  >", "subsubitem", $text); }
function report_inaction(string $text) : void { user_notice("  .", "boring", $text); }
function report_warning(string $text) : void  { user_notice("  !", "warning", $text); }
function report_modification(string $text) : void { user_notice("  ~", "changed", $text); }
function report_add(string $text) : void { user_notice("  +", "added", $text); }
function report_forget(string $text) : void { user_notice("  -", "removed", $text); }
function report_inline(string $text) : void { if (!TRAVIS || defined("TRAVIS_PRINT")) echo " $text"; }
// call report_warning to give users a message before we die
function report_error(string $text) : never {
  report_warning($text);  // @codeCoverageIgnoreStart
  if (TRAVIS) {
     trigger_error($text);  // Stop this test now
  } elseif (function_exists('bot_debug_log')) {
     bot_debug_log($text);  // Code logfile, if defined
  } else {
     trigger_error($text);  // System Logfile
  }
  exit();  // @codeCoverageIgnoreEnd
}
function report_minor_error(string $text) : void {  // For things we want to error in tests, but continue on Wikipedia
  // @codeCoverageIgnoreStart
  /** @psalm-suppress RedundantCondition */ /* PSALM thinks HTML_OUTPUT cannot be FALSE */
  if (!HTML_OUTPUT) { // command line and TRAVIS
    report_error($text);
  } else {
    bot_debug_log($text);
    report_warning($text);
  }
  // @codeCoverageIgnoreEnd
}

function quietly(callable $function, string $text) : void { // Stuff suppressed when running on the command line
  /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT cannot be false */
  if (HTML_OUTPUT || TRAVIS) {
    $function($text);
  }
}

// special flags to mark this function as making all untrustworthy input magically safe to output
function echoable(?string $string) : string {
 /**
   * @psalm-taint-escape html
   * @psalm-taint-escape has_quotes
   */
  $string = (string) $string;
  /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT cannot be false */
  return HTML_OUTPUT ? htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : $string;
}

function pubmed_link(string $identifier, string $pm) : string {
  /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT cannot be false */
  return HTML_OUTPUT 
       ? '<a href="https://www.ncbi.nlm.nih.gov/pubmed/' . urlencode($pm) . '" target="_blank">' . strtoupper($identifier) . ' ' . echoable($pm) . "</a>"   // @codeCoverageIgnore
       : strtoupper($identifier) . ' ' . echoable($pm);
}

function bibcode_link(string $id) : string {
  /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT cannot be false */
  return HTML_OUTPUT
    ? '<a href="https://ui.adsabs.harvard.edu/abs/' . urlencode($id) . '" target="_blank">' . echoable($id) . '</a>'   // @codeCoverageIgnore
    : echoable($id);
}

function doi_link(string $doi) : string {
  /* PSALM thinks HTML_OUTPUT cannot be false */
 /**
   * @psalm-suppress TypeDoesNotContainType
   */
  $return = HTML_OUTPUT
    ? '<a href="https://dx.doi.org/' . doi_encode(urldecode($doi)) . '" target="_blank">' . echoable($doi) . '</a>'      // @codeCoverageIgnore
    : echoable($doi);
  return $return;
}

function jstor_link(string $id) : string {
  /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT cannot be false */
  return HTML_OUTPUT
    ? '<a href="https://www.jstor.org/citation/ris/' . urlencode($id) . '" target="_blank">JSTOR ' . echoable($id) . '</a>'    // @codeCoverageIgnore
    : "JSTOR " . echoable($id);
}

function wiki_link(string $page) : string {
  /** @psalm-suppress TypeDoesNotContainType */ /* PSALM thinks HTML_OUTPUT cannot be false */
  return HTML_OUTPUT
    ? '<a href="' . WIKI_ROOT . '?title=' . urlencode(str_replace(' ', '_', $page)) . '" target="_blank">Wikipedia page: ' . echoable($page) . '</a>'    // @codeCoverageIgnore
    : "Wikipedia page : " . echoable($page);
}
